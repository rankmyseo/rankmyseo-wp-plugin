<?php
/**
 * Reads All in One SEO tables, legacy meta, and the options blob.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Maps leftover All in One SEO data without deleting it.
 */
final class AioseoAdapter implements SourceAdapter {
	/**
	 * Legacy v3 post meta keys.
	 *
	 * @var array<int, string>
	 */
	private const LEGACY_KEYS = [
		'_aioseop_title',
		'_aioseop_description',
		'_aioseop_keywords',
		'_aioseop_custom_link',
		'_aioseop_noindex',
		'_aioseop_nofollow',
	];

	/**
	 * Plugin file that means All in One SEO is still active.
	 *
	 * @var array<int, string>
	 */
	private const PLUGINS = [
		'all-in-one-seo-pack/all_in_one_seo_pack.php',
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
	 * Post IDs already stored in the v4 table.
	 *
	 * @var array<int, int>|null
	 */
	private ?array $table_post_ids = null;

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
		return 'aioseo';
	}

	/**
	 * Name shown on the import card.
	 *
	 * @return string
	 */
	public function label(): string {
		return 'All in One SEO';
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
			$table  = $this->reader->table_exists( 'aioseo_posts' ) ? $this->reader->count_table( 'aioseo_posts' ) : 0;
			$legacy = $this->reader->count_posts_with_meta( self::LEGACY_KEYS, $this->table_post_ids() );
			return $table + $legacy;
		}
		if ( 'terms' === $phase ) {
			return $this->reader->table_exists( 'aioseo_terms' ) ? $this->reader->count_table( 'aioseo_terms' ) : 0;
		}
		if ( 'redirects' === $phase ) {
			return $this->reader->table_exists( 'aioseo_redirects' ) ? $this->reader->count_table( 'aioseo_redirects' ) : 0;
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
			return $this->slice_posts( $offset, $limit );
		}
		if ( 'terms' === $phase ) {
			return $this->slice_table( 'aioseo_terms', 'term', $offset, $limit );
		}
		if ( 'redirects' === $phase ) {
			return $this->slice_redirects( $offset, $limit );
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
	 * Page v4 post rows first, then leftover v3 meta.
	 *
	 * @param int $offset Offset across both sources.
	 * @param int $limit  Page size.
	 * @return array{scanned: int, items: array<int, WorkItem>}
	 */
	private function slice_posts( int $offset, int $limit ): array {
		$table_count = $this->reader->table_exists( 'aioseo_posts' ) ? $this->reader->count_table( 'aioseo_posts' ) : 0;
		$items       = [];
		$scanned     = 0;

		if ( $offset < $table_count ) {
			$rows    = $this->reader->table_rows( 'aioseo_posts', $offset, $limit );
			$scanned = count( $rows );
			foreach ( $rows as $row ) {
				$item = $this->map_row( $row, 'post' );
				if ( null !== $item ) {
					$items[] = $item;
				}
			}
			$limit -= $scanned;
			$offset = 0;
		} else {
			$offset -= $table_count;
		}

		if ( $limit > 0 ) {
			$ids      = $this->reader->post_ids_with_meta( self::LEGACY_KEYS, $offset, $limit, $this->table_post_ids() );
			$scanned += count( $ids );
			foreach ( $ids as $post_id ) {
				$built = $this->bundle->build( $this->legacy_record( $this->reader->post_meta( $post_id, self::LEGACY_KEYS ) ) );
				if ( [] === $built['meta'] ) {
					continue;
				}
				$label   = $this->reader->post_label( $post_id );
				$items[] = new WorkItem( 'post', $post_id, '' !== $label ? $label : '#' . $post_id, $built['meta'], null, null, $built['unsupported'] );
			}
		}

		return [
			'scanned' => $scanned,
			'items'   => $items,
		];
	}

	/**
	 * Page one AIOSEO table.
	 *
	 * @param string $suffix Table suffix.
	 * @param string $type   post or term.
	 * @param int    $offset Offset.
	 * @param int    $limit  Limit.
	 * @return array{scanned: int, items: array<int, WorkItem>}
	 */
	private function slice_table( string $suffix, string $type, int $offset, int $limit ): array {
		if ( ! $this->reader->table_exists( $suffix ) ) {
			return [
				'scanned' => 0,
				'items'   => [],
			];
		}

		$rows  = $this->reader->table_rows( $suffix, $offset, $limit );
		$items = [];
		foreach ( $rows as $row ) {
			$item = $this->map_row( $row, $type );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		return [
			'scanned' => count( $rows ),
			'items'   => $items,
		];
	}

	/**
	 * Page redirect rows.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array{scanned: int, items: array<int, WorkItem>}
	 */
	private function slice_redirects( int $offset, int $limit ): array {
		if ( ! $this->reader->table_exists( 'aioseo_redirects' ) ) {
			return [
				'scanned' => 0,
				'items'   => [],
			];
		}

		$rows  = $this->reader->table_rows( 'aioseo_redirects', $offset, $limit );
		$items = [];
		foreach ( $rows as $row ) {
			$match  = PlainText::is_truthy( $row['regex'] ?? false ) ? 'regex' : 'exact';
			$status = (int) ( $row['type'] ?? ( $row['header'] ?? ( $row['redirect_type'] ?? 301 ) ) );
			$active = ! array_key_exists( 'enabled', $row ) || PlainText::is_truthy( $row['enabled'] );
			$built  = AdapterSupport::redirect(
				$this->paths,
				(string) ( $row['source_url'] ?? '' ),
				(string) ( $row['target_url'] ?? '' ),
				$status,
				$match,
				$active
			);
			if ( null === $built ) {
				continue;
			}
			$items[] = new WorkItem( 'redirect', 0, (string) $built['source_path'], [], $built );
		}

		return [
			'scanned' => count( $rows ),
			'items'   => $items,
		];
	}

	/**
	 * Map one v4 post or term row.
	 *
	 * @param array<string, mixed> $row  Table row.
	 * @param string               $type post or term.
	 * @return WorkItem|null
	 */
	private function map_row( array $row, string $type ): ?WorkItem {
		$id_key = 'term' === $type ? 'term_id' : 'post_id';
		$id     = (int) ( $row[ $id_key ] ?? 0 );
		if ( $id < 1 ) {
			return null;
		}

		$built = $this->bundle->build( $this->table_record( $row ) );
		if ( [] === $built['meta'] ) {
			return null;
		}

		$label = 'term' === $type ? $this->reader->term_label( $id ) : $this->reader->post_label( $id );
		return new WorkItem( $type, $id, '' !== $label ? $label : '#' . $id, $built['meta'], null, null, $built['unsupported'] );
	}

	/**
	 * Convert a v4 table row into the shared record.
	 *
	 * @param array<string, mixed> $row Table row.
	 * @return array<string, mixed>
	 */
	private function table_record( array $row ): array {
		$phrases   = PlainText::decode_structured( $row['keyphrases'] ?? '' );
		$focus     = '';
		$secondary = [];
		if ( is_array( $phrases ) ) {
			$focus_row = $phrases['focus'] ?? [];
			if ( is_array( $focus_row ) ) {
				$focus = (string) ( $focus_row['keyphrase'] ?? '' );
			}
			$extra = $phrases['additional'] ?? [];
			if ( is_array( $extra ) ) {
				foreach ( $extra as $item ) {
					if ( is_array( $item ) && isset( $item['keyphrase'] ) ) {
						$secondary[] = (string) $item['keyphrase'];
					}
				}
			}
		}

		$robots = [];
		$custom = ! PlainText::is_truthy( $row['robots_default'] ?? false );
		if ( $custom || PlainText::is_truthy( $row['robots_noindex'] ?? false ) ) {
			foreach ( [ 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' ] as $flag ) {
				if ( PlainText::is_truthy( $row[ 'robots_' . $flag ] ?? false ) ) {
					$robots[ $flag ] = true;
				}
			}
		}

		$card = strtolower( (string) ( $row['twitter_card'] ?? '' ) );
		if ( 'summary_large_image' !== $card && 'summary' !== $card ) {
			$card = '';
		}

		return [
			'title'               => $row['title'] ?? '',
			'description'         => $row['description'] ?? '',
			'focus'               => $focus,
			'secondary'           => $secondary,
			'canonical'           => $row['canonical_url'] ?? '',
			'robots'              => $robots,
			'og_title'            => $row['og_title'] ?? '',
			'og_description'      => $row['og_description'] ?? '',
			'og_image'            => $row['og_image_custom_url'] ?? '',
			'twitter_title'       => $row['twitter_title'] ?? '',
			'twitter_description' => $row['twitter_description'] ?? '',
			'twitter_image'       => $row['twitter_image_custom_url'] ?? '',
			'twitter_card'        => $card,
		];
	}

	/**
	 * Convert legacy v3 meta into the shared record.
	 *
	 * @param array<string, mixed> $meta Legacy meta.
	 * @return array<string, mixed>
	 */
	private function legacy_record( array $meta ): array {
		$keywords = preg_split( '/\s*,\s*/', (string) ( $meta['_aioseop_keywords'] ?? '' ) );
		$parts    = is_array( $keywords ) ? $keywords : [];
		$focus    = PlainText::line( $parts[0] ?? '', MetaCatalog::KEYWORD_MAX );

		return [
			'title'       => $meta['_aioseop_title'] ?? '',
			'description' => $meta['_aioseop_description'] ?? '',
			'focus'       => $focus,
			'secondary'   => array_slice( $parts, 1, MetaCatalog::SECONDARY_MAX ),
			'canonical'   => $meta['_aioseop_custom_link'] ?? '',
			'robots'      => [
				'noindex'  => PlainText::is_truthy( $meta['_aioseop_noindex'] ?? false ),
				'nofollow' => PlainText::is_truthy( $meta['_aioseop_nofollow'] ?? false ),
			],
		];
	}

	/**
	 * Build the site-settings patch from aioseo_options.
	 *
	 * @return array{options: array<string, array<string, mixed>>, unsupported: array<int, string>}|null
	 */
	private function settings_patch(): ?array {
		$options = PlainText::decode_structured( $this->reader->option( 'aioseo_options' ) );
		if ( ! is_array( $options ) ) {
			return null;
		}

		$search = isset( $options['searchAppearance'] ) && is_array( $options['searchAppearance'] ) ? $options['searchAppearance'] : [];
		$global = isset( $search['global'] ) && is_array( $search['global'] ) ? $search['global'] : [];
		$schema = isset( $global['schema'] ) && is_array( $global['schema'] ) ? $global['schema'] : [];
		$social = isset( $options['social'] ) && is_array( $options['social'] ) ? $options['social'] : [];

		$core      = [];
		$identity  = [];
		$separator = AdapterSupport::separator( $global['separator'] ?? '' );
		if ( '' !== $separator ) {
			$core['title_separator'] = $separator;
		}

		$kind = strtolower( (string) ( $schema['siteRepresents'] ?? '' ) );
		$name = '';
		$logo = '';
		if ( 'person' === $kind ) {
			$name              = PlainText::line( $schema['personName'] ?? '', 120 );
			$logo              = PlainText::url_or_attachment( $schema['personLogo'] ?? '' );
			$core['site_type'] = 'person';
		} elseif ( 'organization' === $kind || 'company' === $kind ) {
			$name              = PlainText::line( $schema['organizationName'] ?? '', 120 );
			$logo              = PlainText::url_or_attachment( $schema['organizationLogo'] ?? '' );
			$core['site_type'] = 'organization';
		}
		if ( '' !== $name ) {
			$core['site_name'] = $name;
		}

		if ( isset( $core['site_type'] ) ) {
			$identity['entity_type'] = $core['site_type'];
		}
		if ( '' !== $name ) {
			$identity['name'] = $name;
		}
		if ( '' !== $logo && ! ctype_digit( $logo ) ) {
			$identity['logo_url'] = $logo;
		}

		$profiles = [];
		if ( isset( $social['profiles'] ) && is_array( $social['profiles'] ) ) {
			$profiles = AdapterSupport::urls( $social['profiles']['urls'] ?? $social['profiles'] );
		}
		if ( [] !== $profiles ) {
			$identity['same_as'] = $profiles;
		}

		$stored      = [];
		$unsupported = [];
		if ( [] !== $core ) {
			$stored[ MetaCatalog::OPTION_CORE ] = $core;
		}
		if ( [] !== $identity ) {
			$stored[ MetaCatalog::OPTION_SCHEMA ] = $identity;
		}

		$post_types = isset( $search['postTypes'] ) && is_array( $search['postTypes'] ) ? $search['postTypes'] : [];
		$title_doc  = [];
		foreach ( [ 'post', 'page' ] as $type ) {
			$row  = isset( $post_types[ $type ] ) && is_array( $post_types[ $type ] ) ? $post_types[ $type ] : [];
			$pair = $this->templates( (string) ( $row['title'] ?? '' ), (string) ( $row['metaDescription'] ?? '' ) );
			if ( null === $pair ) {
				continue;
			}
			$title_doc['post_types'][ $type ] = $pair['fields'];
			$unsupported                      = array_merge( $unsupported, $pair['unsupported'] );
		}
		if ( [] !== $title_doc ) {
			$stored[ MetaCatalog::OPTION_TITLES ] = $title_doc;
		}

		if ( [] === $stored ) {
			return null;
		}

		return [
			'options'     => $stored,
			'unsupported' => array_values( array_unique( $unsupported ) ),
		];
	}

	/**
	 * Translate a template pair.
	 *
	 * @param string $title       Raw title.
	 * @param string $description Raw description.
	 * @return array{fields: array<string, mixed>, unsupported: array<int, string>}|null
	 */
	private function templates( string $title, string $description ): ?array {
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

		if ( [] === $fields ) {
			return null;
		}

		return [
			'fields'      => [
				'title'       => $fields['title'] ?? '',
				'description' => $fields['description'] ?? '',
				'noindex'     => false,
			],
			'unsupported' => $unsupported,
		];
	}

	/**
	 * Post IDs covered by the v4 table so legacy meta is not imported twice.
	 *
	 * @return array<int, int>
	 */
	private function table_post_ids(): array {
		if ( null !== $this->table_post_ids ) {
			return $this->table_post_ids;
		}

		if ( ! $this->reader->table_exists( 'aioseo_posts' ) ) {
			$this->table_post_ids = [];
			return [];
		}

		$this->table_post_ids = $this->reader->table_column( 'aioseo_posts', 'post_id' );
		return $this->table_post_ids;
	}
}
