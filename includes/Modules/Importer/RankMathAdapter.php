<?php
/**
 * Reads Rank Math post meta, term meta, titles, and the redirections table.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Maps leftover Rank Math data without deleting it.
 */
final class RankMathAdapter implements SourceAdapter {
	/**
	 * Meta keys that indicate a post or term can be imported.
	 *
	 * @var array<int, string>
	 */
	private const KEYS = [
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_canonical_url',
		'rank_math_robots',
		'rank_math_facebook_title',
		'rank_math_facebook_description',
		'rank_math_facebook_image',
		'rank_math_facebook_image_id',
		'rank_math_twitter_title',
		'rank_math_twitter_description',
		'rank_math_twitter_image',
		'rank_math_twitter_image_id',
		'rank_math_twitter_card_type',
	];

	/**
	 * Plugin file that means Rank Math is still active.
	 *
	 * @var array<int, string>
	 */
	private const PLUGINS = [
		'seo-by-rank-math/rank-math.php',
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
		return 'rankmath';
	}

	/**
	 * Name shown on the import card.
	 *
	 * @return string
	 */
	public function label(): string {
		return 'Rank Math';
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
			return $this->reader->count_posts_with_meta( self::KEYS );
		}
		if ( 'terms' === $phase ) {
			return $this->reader->count_terms_with_meta( self::KEYS );
		}
		if ( 'redirects' === $phase ) {
			return $this->reader->table_exists( 'rank_math_redirections' ) ? $this->reader->count_table( 'rank_math_redirections' ) : 0;
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
			return $this->slice_objects( 'post', $offset, $limit );
		}
		if ( 'terms' === $phase ) {
			return $this->slice_objects( 'term', $offset, $limit );
		}
		if ( 'redirects' === $phase ) {
			if ( ! $this->reader->table_exists( 'rank_math_redirections' ) ) {
				return [
					'scanned' => 0,
					'items'   => [],
				];
			}
			$rows  = $this->reader->table_rows( 'rank_math_redirections', $offset, $limit );
			$items = [];
			foreach ( $rows as $row ) {
				foreach ( $this->map_redirect( $row ) as $item ) {
					$items[] = $item;
				}
			}
			return [
				'scanned' => count( $rows ),
				'items'   => $items,
			];
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
	 * Page posts or terms.
	 *
	 * @param string $type   post or term.
	 * @param int    $offset Offset.
	 * @param int    $limit  Limit.
	 * @return array{scanned: int, items: array<int, WorkItem>}
	 */
	private function slice_objects( string $type, int $offset, int $limit ): array {
		$ids = 'post' === $type
			? $this->reader->post_ids_with_meta( self::KEYS, $offset, $limit )
			: $this->reader->term_ids_with_meta( self::KEYS, $offset, $limit );

		$items = [];
		foreach ( $ids as $object_id ) {
			$meta  = 'post' === $type
				? $this->reader->post_meta( $object_id, self::KEYS )
				: $this->reader->term_meta( $object_id, self::KEYS );
			$built = $this->bundle->build( $this->record( $meta ) );
			if ( [] === $built['meta'] ) {
				continue;
			}
			$label = 'post' === $type ? $this->reader->post_label( $object_id ) : $this->reader->term_label( $object_id );
			if ( '' === $label ) {
				$label = '#' . $object_id;
			}
			$items[] = new WorkItem( $type, $object_id, $label, $built['meta'], null, null, $built['unsupported'] );
		}

		return [
			'scanned' => count( $ids ),
			'items'   => $items,
		];
	}

	/**
	 * Convert Rank Math meta into the shared record.
	 *
	 * @param array<string, mixed> $meta Source meta.
	 * @return array<string, mixed>
	 */
	private function record( array $meta ): array {
		$keywords = $this->keywords( $meta['rank_math_focus_keyword'] ?? '' );
		$card     = strtolower( (string) ( $meta['rank_math_twitter_card_type'] ?? '' ) );
		if ( 'summary_card' === $card ) {
			$card = 'summary';
		}
		if ( 'summary_large_image' !== $card && 'summary' !== $card ) {
			$card = '';
		}

		return [
			'title'               => $meta['rank_math_title'] ?? '',
			'description'         => $meta['rank_math_description'] ?? '',
			'focus'               => $keywords['focus'],
			'secondary'           => $keywords['secondary'],
			'canonical'           => $meta['rank_math_canonical_url'] ?? '',
			'robots'              => AdapterSupport::robots( $meta['rank_math_robots'] ?? [] ),
			'og_title'            => $meta['rank_math_facebook_title'] ?? '',
			'og_description'      => $meta['rank_math_facebook_description'] ?? '',
			'og_image'            => AdapterSupport::first_image( $meta['rank_math_facebook_image'] ?? '', $meta['rank_math_facebook_image_id'] ?? '' ),
			'twitter_title'       => $meta['rank_math_twitter_title'] ?? '',
			'twitter_description' => $meta['rank_math_twitter_description'] ?? '',
			'twitter_image'       => AdapterSupport::first_image( $meta['rank_math_twitter_image'] ?? '', $meta['rank_math_twitter_image_id'] ?? '' ),
			'twitter_card'        => $card,
		];
	}

	/**
	 * Split a comma-separated focus keyword list.
	 *
	 * @param mixed $value Raw keywords.
	 * @return array{focus: string, secondary: array<int, string>}
	 */
	private function keywords( $value ): array {
		$value = PlainText::decode_structured( $value );
		if ( is_array( $value ) ) {
			$parts = $value;
		} else {
			$split = preg_split( '/\s*,\s*/', (string) $value );
			$parts = is_array( $split ) ? $split : [];
		}

		$clean = [];
		foreach ( $parts as $part ) {
			$text = PlainText::line( $part, MetaCatalog::KEYWORD_MAX );
			if ( '' === $text || in_array( $text, $clean, true ) ) {
				continue;
			}
			$clean[] = $text;
		}

		return [
			'focus'     => $clean[0] ?? '',
			'secondary' => array_slice( $clean, 1, MetaCatalog::SECONDARY_MAX ),
		];
	}

	/**
	 * Expand one redirections-table row into work items.
	 *
	 * @param array<string, mixed> $row Source row.
	 * @return array<int, WorkItem>
	 */
	private function map_redirect( array $row ): array {
		$sources = PlainText::decode_structured( $row['sources'] ?? [] );
		if ( ! is_array( $sources ) ) {
			return [];
		}

		$status = (int) ( $row['header_code'] ?? 301 );
		$active = 'inactive' !== strtolower( (string) ( $row['status'] ?? 'active' ) );
		$target = (string) ( $row['url_to'] ?? '' );
		$items  = [];

		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$comparison = strtolower( (string) ( $source['comparison'] ?? 'exact' ) );
			if ( ! in_array( $comparison, [ 'exact', 'start', 'regex' ], true ) ) {
				continue;
			}
			$match = 'start' === $comparison ? 'prefix' : $comparison;
			$built = AdapterSupport::redirect( $this->paths, (string) ( $source['pattern'] ?? '' ), $target, $status, $match, $active );
			if ( null === $built ) {
				continue;
			}
			$items[] = new WorkItem( 'redirect', 0, (string) $built['source_path'], [], $built );
		}

		return $items;
	}

	/**
	 * Build the site-settings patch from rank-math-options-titles.
	 *
	 * @return array{options: array<string, array<string, mixed>>, unsupported: array<int, string>}|null
	 */
	private function settings_patch(): ?array {
		$titles = PlainText::decode_structured( $this->reader->option( 'rank-math-options-titles' ) );
		if ( ! is_array( $titles ) ) {
			return null;
		}

		$options     = [];
		$unsupported = [];
		$core        = [];
		$separator   = AdapterSupport::separator( $titles['title_separator'] ?? '' );
		if ( '' !== $separator ) {
			$core['title_separator'] = $separator;
		}

		$kind = strtolower( (string) ( $titles['knowledgegraph_type'] ?? '' ) );
		$name = PlainText::line( $titles['knowledgegraph_name'] ?? '', 120 );
		$logo = PlainText::url_or_attachment( $titles['knowledgegraph_logo'] ?? '' );
		if ( 'person' === $kind ) {
			$core['site_type'] = 'person';
		} elseif ( 'company' === $kind || 'organization' === $kind ) {
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
				$titles['social_url_facebook'] ?? '',
				AdapterSupport::profile_url( (string) ( $titles['social_url_twitter'] ?? '' ) ),
				$titles['social_url_instagram'] ?? '',
				$titles['social_url_linkedin'] ?? '',
				$titles['social_url_youtube'] ?? '',
				$titles['social_url_pinterest'] ?? '',
			]
		);
		if ( [] !== $profiles ) {
			$schema['same_as'] = $profiles;
		}
		if ( [] !== $schema ) {
			$options[ MetaCatalog::OPTION_SCHEMA ] = $schema;
		}

		$title_doc = [];
		$home      = $this->templates( (string) ( $titles['homepage_title'] ?? '' ), (string) ( $titles['homepage_description'] ?? '' ) );
		if ( [] !== $home['fields'] ) {
			$title_doc['homepage'] = $home['fields'];
			$unsupported           = array_merge( $unsupported, $home['unsupported'] );
		}
		foreach ( [ 'post', 'page' ] as $type ) {
			$row = $this->template_row( (string) ( $titles[ 'pt_' . $type . '_title' ] ?? '' ), (string) ( $titles[ 'pt_' . $type . '_description' ] ?? '' ) );
			if ( null !== $row ) {
				$title_doc['post_types'][ $type ] = $row['fields'];
				$unsupported                      = array_merge( $unsupported, $row['unsupported'] );
			}
		}
		foreach ( [ 'category', 'post_tag' ] as $taxonomy ) {
			$row = $this->template_row( (string) ( $titles[ 'tax_' . $taxonomy . '_title' ] ?? '' ), (string) ( $titles[ 'tax_' . $taxonomy . '_description' ] ?? '' ) );
			if ( null !== $row ) {
				$title_doc['taxonomies'][ $taxonomy ] = $row['fields'];
				$unsupported                          = array_merge( $unsupported, $row['unsupported'] );
			}
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
	 * Translate a title and description.
	 *
	 * @param string $title       Raw title.
	 * @param string $description Raw description.
	 * @return array{fields: array<string, string>, unsupported: array<int, string>}
	 */
	private function templates( string $title, string $description ): array {
		$fields      = [];
		$unsupported = [];
		foreach ( [
			'title'       => $title,
			'description' => $description,
		] as $key => $value ) {
			$built = $this->bundle->build( [ 'title' => $value ] );
			$text  = $built['meta'][ MetaCatalog::TITLE ] ?? '';
			$max   = 'description' === $key ? MetaCatalog::DESCRIPTION_MAX : MetaCatalog::TITLE_MAX;
			if ( mb_strlen( $text ) > $max ) {
				$text = mb_substr( $text, 0, $max );
			}
			if ( '' !== $text ) {
				$fields[ $key ] = $text;
				$unsupported    = array_merge( $unsupported, $built['unsupported'] );
			}
		}

		return [
			'fields'      => $fields,
			'unsupported' => $unsupported,
		];
	}

	/**
	 * Translate a template row that always includes noindex false when text exists.
	 *
	 * @param string $title       Raw title.
	 * @param string $description Raw description.
	 * @return array{fields: array<string, mixed>, unsupported: array<int, string>}|null
	 */
	private function template_row( string $title, string $description ): ?array {
		$pair = $this->templates( $title, $description );
		if ( [] === $pair['fields'] ) {
			return null;
		}

		return [
			'fields'      => [
				'title'       => $pair['fields']['title'] ?? '',
				'description' => $pair['fields']['description'] ?? '',
				'noindex'     => false,
			],
			'unsupported' => $pair['unsupported'],
		];
	}
}
