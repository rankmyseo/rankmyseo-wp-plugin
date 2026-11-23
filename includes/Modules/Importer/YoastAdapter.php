<?php
/**
 * Reads Yoast SEO post meta, term options, titles, and premium redirects.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Maps leftover Yoast SEO data without deleting it.
 */
final class YoastAdapter implements SourceAdapter {
	/**
	 * Post meta keys that indicate a post can be imported.
	 *
	 * @var array<int, string>
	 */
	private const POST_KEYS = [
		'_yoast_wpseo_title',
		'_yoast_wpseo_metadesc',
		'_yoast_wpseo_focuskw',
		'_yoast_wpseo_canonical',
		'_yoast_wpseo_meta-robots-noindex',
		'_yoast_wpseo_meta-robots-nofollow',
		'_yoast_wpseo_meta-robots-adv',
		'_yoast_wpseo_opengraph-title',
		'_yoast_wpseo_opengraph-description',
		'_yoast_wpseo_opengraph-image',
		'_yoast_wpseo_opengraph-image-id',
		'_yoast_wpseo_twitter-title',
		'_yoast_wpseo_twitter-description',
		'_yoast_wpseo_twitter-image',
		'_yoast_wpseo_twitter-image-id',
	];

	/**
	 * Plugin files that mean Yoast is still active.
	 *
	 * @var array<int, string>
	 */
	private const PLUGINS = [
		'wordpress-seo/wp-seo.php',
		'wordpress-seo-premium/wp-seo-premium.php',
	];

	/**
	 * Source reader.
	 *
	 * @var SourceReader
	 */
	private SourceReader $reader;

	/**
	 * Meta encoder.
	 *
	 * @var MetaBundle
	 */
	private MetaBundle $bundle;

	/**
	 * Path hasher.
	 *
	 * @var PathHash
	 */
	private PathHash $paths;

	/**
	 * Constructor.
	 *
	 * @param SourceReader       $reader     Source data.
	 * @param TemplateTranslator $translator Token translator.
	 * @param PathHash|null      $paths      Path hasher.
	 */
	public function __construct( SourceReader $reader, TemplateTranslator $translator, ?PathHash $paths = null ) {
		$this->reader = $reader;
		$this->bundle = new MetaBundle( $translator );
		$this->paths  = $paths ?? new PathHash( true );
	}

	/**
	 * Machine ID: yoast, rankmath, or aioseo.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'yoast';
	}

	/**
	 * Name shown on the import card.
	 *
	 * @return string
	 */
	public function label(): string {
		return 'Yoast SEO';
	}

	/**
	 * Counts and whether the plugin is active or only leftover data.
	 *
	 * @return array{active: bool, found: bool, posts: int, terms: int, redirects: int, settings: int}
	 */
	public function detect(): array {
		$posts     = $this->count( 'posts' );
		$terms     = $this->count( 'terms' );
		$redirects = $this->count( 'redirects' );
		$settings  = $this->count( 'settings' );

		return [
			'active'    => $this->reader->plugin_active( self::PLUGINS ),
			'found'     => ( $posts + $terms + $redirects + $settings ) > 0,
			'posts'     => $posts,
			'terms'     => $terms,
			'redirects' => $redirects,
			'settings'  => $settings,
		];
	}

	/**
	 * Count records for one phase.
	 *
	 * @param string $phase settings, posts, terms, or redirects.
	 * @return int
	 */
	public function count( string $phase ): int {
		if ( 'posts' === $phase ) {
			return $this->reader->count_posts_with_meta( self::POST_KEYS );
		}
		if ( 'terms' === $phase ) {
			return count( $this->term_rows() );
		}
		if ( 'redirects' === $phase ) {
			return count( $this->redirect_rows() );
		}
		if ( 'settings' === $phase ) {
			return null === $this->settings_patch() ? 0 : 1;
		}
		return 0;
	}

	/**
	 * Page one phase.
	 *
	 * `scanned` is how far the cursor should move, even when a row maps to nothing.
	 *
	 * @param string $phase  Phase name.
	 * @param int    $offset Zero-based offset.
	 * @param int    $limit  Maximum source rows.
	 * @return array{scanned: int, items: array<int, WorkItem>}
	 */
	public function slice( string $phase, int $offset, int $limit ): array {
		if ( $limit < 1 ) {
			return [
				'scanned' => 0,
				'items'   => [],
			];
		}

		if ( 'posts' === $phase ) {
			$ids   = $this->reader->post_ids_with_meta( self::POST_KEYS, $offset, $limit );
			$items = [];
			foreach ( $ids as $post_id ) {
				$item = $this->map_post( $post_id );
				if ( null !== $item ) {
					$items[] = $item;
				}
			}
			return [
				'scanned' => count( $ids ),
				'items'   => $items,
			];
		}

		if ( 'terms' === $phase ) {
			return $this->slice_list( $this->term_rows(), $offset, $limit, [ $this, 'map_term_row' ] );
		}

		if ( 'redirects' === $phase ) {
			return $this->slice_list( $this->redirect_rows(), $offset, $limit, [ $this, 'map_redirect_row' ] );
		}

		if ( 'settings' === $phase ) {
			if ( $offset > 0 ) {
				return [
					'scanned' => 0,
					'items'   => [],
				];
			}
			$patch = $this->settings_patch();
			if ( null === $patch ) {
				return [
					'scanned' => 0,
					'items'   => [],
				];
			}
			return [
				'scanned' => 1,
				'items'   => [
					new WorkItem( 'option', 0, __( 'Site settings', 'rankmyseo' ), [], null, $patch['options'], $patch['unsupported'] ),
				],
			];
		}

		return [
			'scanned' => 0,
			'items'   => [],
		];
	}

	/**
	 * Map one post.
	 *
	 * @param int $post_id Post ID.
	 * @return WorkItem|null
	 */
	private function map_post( int $post_id ): ?WorkItem {
		$meta   = $this->reader->post_meta( $post_id, self::POST_KEYS );
		$record = $this->record_from_post_meta( $meta );
		$built  = $this->bundle->build( $record );
		if ( [] === $built['meta'] ) {
			return null;
		}

		$label = $this->reader->post_label( $post_id );
		if ( '' === $label ) {
			$label = '#' . $post_id;
		}

		return new WorkItem( 'post', $post_id, $label, $built['meta'], null, null, $built['unsupported'] );
	}

	/**
	 * Map one taxonomy row stored in wpseo_taxonomy_meta.
	 *
	 * @param array<string, mixed> $row Term row.
	 * @return WorkItem|null
	 */
	public function map_term_row( array $row ): ?WorkItem {
		$data    = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : [];
		$term_id = (int) ( $row['term_id'] ?? 0 );
		if ( $term_id < 1 ) {
			return null;
		}

		$robots = [];
		$index  = strtolower( (string) ( $data['wpseo_noindex'] ?? '' ) );
		if ( 'noindex' === $index ) {
			$robots['noindex'] = true;
		}

		$record = [
			'title'               => $data['wpseo_title'] ?? '',
			'description'         => $data['wpseo_desc'] ?? '',
			'focus'               => $data['wpseo_focuskw'] ?? '',
			'canonical'           => $data['wpseo_canonical'] ?? '',
			'robots'              => $robots,
			'og_title'            => $data['wpseo_opengraph-title'] ?? '',
			'og_description'      => $data['wpseo_opengraph-description'] ?? '',
			'og_image'            => $data['wpseo_opengraph-image'] ?? ( $data['wpseo_opengraph-image-id'] ?? '' ),
			'twitter_title'       => $data['wpseo_twitter-title'] ?? '',
			'twitter_description' => $data['wpseo_twitter-description'] ?? '',
			'twitter_image'       => $data['wpseo_twitter-image'] ?? ( $data['wpseo_twitter-image-id'] ?? '' ),
		];

		$built = $this->bundle->build( $record );
		if ( [] === $built['meta'] ) {
			return null;
		}

		$label = $this->reader->term_label( $term_id );
		if ( '' === $label ) {
			$label = (string) ( $row['taxonomy'] ?? '' );
		}

		return new WorkItem( 'term', $term_id, '' !== $label ? $label : '#' . $term_id, $built['meta'], null, null, $built['unsupported'] );
	}

	/**
	 * Map one premium redirect.
	 *
	 * @param array<string, mixed> $row Redirect row.
	 * @return WorkItem|null
	 */
	public function map_redirect_row( array $row ): ?WorkItem {
		$format = strtolower( (string) ( $row['format'] ?? 'plain' ) );
		$match  = 'regex' === $format ? 'regex' : 'exact';
		$status = (int) ( $row['type'] ?? 301 );
		$built  = AdapterSupport::redirect(
			$this->paths,
			(string) ( $row['origin'] ?? '' ),
			(string) ( $row['url'] ?? '' ),
			$status,
			$match,
			true
		);

		if ( null === $built ) {
			return null;
		}

		return new WorkItem( 'redirect', 0, (string) $built['source_path'], [], $built );
	}

	/**
	 * Convert post meta into the shared record.
	 *
	 * @param array<string, mixed> $meta Post meta.
	 * @return array<string, mixed>
	 */
	private function record_from_post_meta( array $meta ): array {
		$robots = AdapterSupport::robots( $meta['_yoast_wpseo_meta-robots-adv'] ?? '' );
		$index  = (string) ( $meta['_yoast_wpseo_meta-robots-noindex'] ?? '' );
		if ( '1' === $index ) {
			$robots['noindex'] = true;
		}
		if ( '1' === (string) ( $meta['_yoast_wpseo_meta-robots-nofollow'] ?? '' ) ) {
			$robots['nofollow'] = true;
		}

		return [
			'title'               => $meta['_yoast_wpseo_title'] ?? '',
			'description'         => $meta['_yoast_wpseo_metadesc'] ?? '',
			'focus'               => $meta['_yoast_wpseo_focuskw'] ?? '',
			'canonical'           => $meta['_yoast_wpseo_canonical'] ?? '',
			'robots'              => $robots,
			'og_title'            => $meta['_yoast_wpseo_opengraph-title'] ?? '',
			'og_description'      => $meta['_yoast_wpseo_opengraph-description'] ?? '',
			'og_image'            => AdapterSupport::first_image( $meta['_yoast_wpseo_opengraph-image'] ?? '', $meta['_yoast_wpseo_opengraph-image-id'] ?? '' ),
			'twitter_title'       => $meta['_yoast_wpseo_twitter-title'] ?? '',
			'twitter_description' => $meta['_yoast_wpseo_twitter-description'] ?? '',
			'twitter_image'       => AdapterSupport::first_image( $meta['_yoast_wpseo_twitter-image'] ?? '', $meta['_yoast_wpseo_twitter-image-id'] ?? '' ),
		];
	}

	/**
	 * Flatten wpseo_taxonomy_meta into rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function term_rows(): array {
		$stored = $this->reader->option( 'wpseo_taxonomy_meta' );
		$stored = PlainText::decode_structured( $stored );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$rows = [];
		foreach ( $stored as $taxonomy => $terms ) {
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term_id => $data ) {
				if ( ! is_array( $data ) ) {
					continue;
				}
				$rows[] = [
					'taxonomy' => (string) $taxonomy,
					'term_id'  => (int) $term_id,
					'data'     => $data,
				];
			}
		}

		return $rows;
	}

	/**
	 * Normalize premium redirects from the option.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function redirect_rows(): array {
		$stored = $this->reader->option( 'wpseo-premium-redirects-base' );
		$stored = PlainText::decode_structured( $stored );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$rows = [];
		foreach ( $stored as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( isset( $value['origin'] ) || ( is_int( $key ) && isset( $value[0] ) ) ) {
				$origin = (string) ( $value['origin'] ?? $value[0] ?? '' );
				$url    = (string) ( $value['url'] ?? $value[1] ?? '' );
				$type   = (int) ( $value['type'] ?? $value[2] ?? 301 );
				$format = (string) ( $value['format'] ?? $value[3] ?? 'plain' );
				$rows[] = [
					'origin' => $origin,
					'url'    => $url,
					'type'   => $type,
					'format' => $format,
				];
				continue;
			}

			if ( is_string( $key ) ) {
				$rows[] = [
					'origin' => $key,
					'url'    => (string) ( $value['url'] ?? $value[0] ?? '' ),
					'type'   => (int) ( $value['type'] ?? $value[1] ?? 301 ),
					'format' => (string) ( $value['format'] ?? $value[2] ?? 'plain' ),
				];
			}
		}

		return $rows;
	}

	/**
	 * Build the site-settings patch.
	 *
	 * @return array{options: array<string, array<string, mixed>>, unsupported: array<int, string>}|null
	 */
	private function settings_patch(): ?array {
		$titles = PlainText::decode_structured( $this->reader->option( 'wpseo_titles' ) );
		$social = PlainText::decode_structured( $this->reader->option( 'wpseo_social' ) );
		$wpseo  = PlainText::decode_structured( $this->reader->option( 'wpseo' ) );

		if ( ! is_array( $titles ) && ! is_array( $social ) && ! is_array( $wpseo ) ) {
			return null;
		}

		$titles = is_array( $titles ) ? $titles : [];
		$social = is_array( $social ) ? $social : [];
		$wpseo  = is_array( $wpseo ) ? $wpseo : [];

		$options     = [];
		$unsupported = [];
		$core        = [];

		$separator = AdapterSupport::separator( $titles['separator'] ?? ( $wpseo['separator'] ?? '' ) );
		if ( '' !== $separator ) {
			$core['title_separator'] = $separator;
		}

		$kind = strtolower( (string) ( $titles['company_or_person'] ?? '' ) );
		$name = '';
		$logo = '';
		if ( 'person' === $kind ) {
			$name              = PlainText::line( $titles['person_name'] ?? '', 120 );
			$logo              = PlainText::url_or_attachment( $titles['person_logo'] ?? ( $titles['person_logo_id'] ?? '' ) );
			$core['site_type'] = 'person';
		} elseif ( 'company' === $kind || 'organization' === $kind ) {
			$name              = PlainText::line( $titles['company_name'] ?? '', 120 );
			$logo              = PlainText::url_or_attachment( $titles['company_logo'] ?? ( $titles['company_logo_id'] ?? '' ) );
			$core['site_type'] = 'organization';
		}

		if ( '' !== $name ) {
			$core['site_name'] = $name;
		}
		if ( [] !== $core ) {
			$options[ MetaCatalog::OPTION_CORE ] = $core;
		}

		$schema = [];
		if ( isset( $core['site_type'] ) ) {
			$schema['entity_type'] = $core['site_type'];
		}
		if ( '' !== $name ) {
			$schema['name'] = $name;
		}
		if ( '' !== $logo && ! ctype_digit( $logo ) ) {
			$schema['logo_url'] = $logo;
		}
		$profiles = AdapterSupport::urls(
			[
				$social['facebook_site'] ?? '',
				AdapterSupport::profile_url( (string) ( $social['twitter_site'] ?? '' ) ),
				$social['instagram_url'] ?? '',
				$social['linkedin_url'] ?? '',
				$social['youtube_url'] ?? '',
				$social['pinterest_url'] ?? '',
				$social['wikipedia_url'] ?? '',
				$social['other_social_urls'] ?? [],
			]
		);
		if ( [] !== $profiles ) {
			$schema['same_as'] = $profiles;
		}
		if ( [] !== $schema ) {
			$options[ MetaCatalog::OPTION_SCHEMA ] = $schema;
		}

		$title_doc = [];
		$home      = $this->translate_pair( (string) ( $titles['title-home-wpseo'] ?? '' ), (string) ( $titles['metadesc-home-wpseo'] ?? '' ) );
		if ( [] !== $home['fields'] ) {
			$title_doc['homepage'] = $home['fields'];
			$unsupported           = array_merge( $unsupported, $home['unsupported'] );
		}

		$post = $this->translate_template_row( (string) ( $titles['title-post'] ?? '' ), (string) ( $titles['metadesc-post'] ?? '' ), $titles['noindex-post'] ?? null );
		if ( null !== $post ) {
			$title_doc['post_types']['post'] = $post['fields'];
			$unsupported                     = array_merge( $unsupported, $post['unsupported'] );
		}
		$page = $this->translate_template_row( (string) ( $titles['title-page'] ?? '' ), (string) ( $titles['metadesc-page'] ?? '' ), $titles['noindex-page'] ?? null );
		if ( null !== $page ) {
			$title_doc['post_types']['page'] = $page['fields'];
			$unsupported                     = array_merge( $unsupported, $page['unsupported'] );
		}
		$category = $this->translate_template_row( (string) ( $titles['title-tax-category'] ?? '' ), (string) ( $titles['metadesc-tax-category'] ?? '' ), $titles['noindex-tax-category'] ?? null );
		if ( null !== $category ) {
			$title_doc['taxonomies']['category'] = $category['fields'];
			$unsupported                         = array_merge( $unsupported, $category['unsupported'] );
		}

		if ( [] !== $title_doc ) {
			$options[ MetaCatalog::OPTION_TITLES ] = $title_doc;
		}

		if ( [] === $options ) {
			return null;
		}

		return [
			'options'     => $options,
			'unsupported' => array_values( array_unique( $unsupported ) ),
		];
	}

	/**
	 * Translate a homepage title and description.
	 *
	 * @param string $title       Raw title template.
	 * @param string $description Raw description template.
	 * @return array{fields: array<string, string>, unsupported: array<int, string>}
	 */
	private function translate_pair( string $title, string $description ): array {
		$fields      = [];
		$unsupported = [];
		$title_text  = $this->bundle_text( $title, MetaCatalog::TITLE_MAX );
		$desc_text   = $this->bundle_text( $description, MetaCatalog::DESCRIPTION_MAX );
		$unsupported = array_merge( $unsupported, $title_text['unsupported'], $desc_text['unsupported'] );
		if ( '' !== $title_text['text'] ) {
			$fields['title'] = $title_text['text'];
		}
		if ( '' !== $desc_text['text'] ) {
			$fields['description'] = $desc_text['text'];
		}

		return [
			'fields'      => $fields,
			'unsupported' => $unsupported,
		];
	}

	/**
	 * Translate a post type or taxonomy template row.
	 *
	 * @param string $title       Raw title.
	 * @param string $description Raw description.
	 * @param mixed  $noindex     Source noindex flag.
	 * @return array{fields: array<string, mixed>, unsupported: array<int, string>}|null
	 */
	private function translate_template_row( string $title, string $description, $noindex ): ?array {
		$pair = $this->translate_pair( $title, $description );
		if ( [] === $pair['fields'] && ! PlainText::is_truthy( $noindex ) ) {
			return null;
		}

		$fields = [
			'title'       => $pair['fields']['title'] ?? '',
			'description' => $pair['fields']['description'] ?? '',
			'noindex'     => PlainText::is_truthy( $noindex ),
		];

		if ( '' === $fields['title'] && '' === $fields['description'] && ! $fields['noindex'] ) {
			return null;
		}

		return [
			'fields'      => $fields,
			'unsupported' => $pair['unsupported'],
		];
	}

	/**
	 * Translate one template string.
	 *
	 * @param string $value Raw template.
	 * @param int    $max   Maximum length.
	 * @return array{text: string, unsupported: array<int, string>}
	 */
	private function bundle_text( string $value, int $max ): array {
		$built = $this->bundle->build( [ 'title' => $value ] );
		$text  = $built['meta'][ MetaCatalog::TITLE ] ?? '';
		if ( mb_strlen( $text ) > $max ) {
			$text = mb_substr( $text, 0, $max );
		}
		if ( '' === PlainText::line( $value, 20 ) ) {
			return [
				'text'        => '',
				'unsupported' => [],
			];
		}

		return [
			'text'        => $text,
			'unsupported' => $built['unsupported'],
		];
	}

	/**
	 * Slice an in-memory list and map each row.
	 *
	 * @param array<int, array<string, mixed>> $rows   Source rows.
	 * @param int                              $offset Offset.
	 * @param int                              $limit  Limit.
	 * @param callable                         $mapper Mapper.
	 * @return array{scanned: int, items: array<int, WorkItem>}
	 */
	private function slice_list( array $rows, int $offset, int $limit, callable $mapper ): array {
		$window = array_slice( $rows, $offset, $limit );
		$items  = [];
		foreach ( $window as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = $mapper( $row );
			if ( $item instanceof WorkItem ) {
				$items[] = $item;
			}
		}

		return [
			'scanned' => count( $window ),
			'items'   => $items,
		];
	}
}
