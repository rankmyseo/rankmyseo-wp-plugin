<?php
/**
 * Virtual llms.txt markdown, physical-file detection, and link selection.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Builds the /llms.txt body in memory. It never writes a file.
 */
final class LlmsTxt {
	/**
	 * Most links read from one post type.
	 */
	public const MAX_PER_TYPE = 100;

	/**
	 * Most links in the whole file, including the homepage and manual URLs.
	 */
	public const MAX_TOTAL = 500;

	/**
	 * Settings repository.
	 *
	 * @var SitemapSettings
	 */
	private SitemapSettings $settings;

	/**
	 * Indexable link source.
	 *
	 * @var LlmsCatalog
	 */
	private LlmsCatalog $catalog;

	/**
	 * Site root used to detect a physical llms.txt. Defaults to ABSPATH.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Optional file_exists replacement for tests.
	 *
	 * @var callable|null
	 */
	private $file_exists;

	/**
	 * Constructor.
	 *
	 * @param SitemapSettings $settings    Settings repository.
	 * @param LlmsCatalog     $catalog     Indexable link source.
	 * @param string          $root        Directory that would contain a physical llms.txt.
	 * @param callable|null   $file_exists Optional exists check receiving the absolute path.
	 */
	public function __construct( SitemapSettings $settings, LlmsCatalog $catalog, string $root = '', ?callable $file_exists = null ) {
		$this->settings    = $settings;
		$this->catalog     = $catalog;
		$this->root        = '' !== $root ? $root : ( defined( 'ABSPATH' ) ? ABSPATH : '' );
		$this->file_exists = $file_exists;
	}

	/**
	 * Whether the virtual file should be served.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->settings->llms_enabled();
	}

	/**
	 * Absolute path of a physical llms.txt the web server would serve first.
	 *
	 * @return string
	 */
	public function physical_path(): string {
		return rtrim( $this->root, '/\\' ) . '/llms.txt';
	}

	/**
	 * Whether a physical llms.txt file is present.
	 *
	 * The web server serves that file before WordPress rewrites run.
	 *
	 * @return bool
	 */
	public function has_physical_file(): bool {
		$path = $this->physical_path();
		if ( null !== $this->file_exists ) {
			return (bool) call_user_func( $this->file_exists, $path );
		}

		return '' !== $path && file_exists( $path );
	}

	/**
	 * Public URL of the virtual file.
	 *
	 * @return string
	 */
	public function public_url(): string {
		return home_url( '/llms.txt' );
	}

	/**
	 * Warnings for the admin screen.
	 *
	 * @return list<array{code: string, message: string}>
	 */
	public function warnings(): array {
		if ( ! $this->has_physical_file() ) {
			return [];
		}

		return [
			[
				'code'    => 'physical_file',
				'message' => sprintf(
					/* translators: %s: absolute path of the physical llms.txt file. */
					__( 'A physical llms.txt file is present at %s. The web server will serve that file, so this virtual guide will not run. Remove or rename the file to use the guide below.', 'rankmyseo' ),
					$this->physical_path()
				),
			],
		];
	}

	/**
	 * Post types that belong in the file.
	 *
	 * A type left out of the sitemap stays out. The llms.txt map can narrow the rest.
	 *
	 * @return array<string, string> Post type name => label.
	 */
	public function selected_types(): array {
		$llms     = $this->settings->llms_post_types();
		$selected = [];
		foreach ( $this->catalog->post_types() as $name => $label ) {
			if ( ! $this->settings->is_post_type_included( $name ) ) {
				continue;
			}
			if ( array_key_exists( $name, $llms ) && ! $llms[ $name ] ) {
				continue;
			}
			$selected[ $name ] = $label;
		}

		return $selected;
	}

	/**
	 * Automatic groups before manual include and exclude rules.
	 *
	 * @return list<array{label: string, links: list<array{title: string, url: string, description: string}>}>
	 */
	public function automatic_groups(): array {
		$groups = [];
		$home   = $this->home_link();
		$used   = 0;

		if ( null !== $home ) {
			$groups[] = [
				'label' => __( 'Site', 'rankmyseo' ),
				'links' => [ $home ],
			];
			++$used;
		}

		foreach ( $this->selected_types() as $name => $label ) {
			if ( $used >= self::MAX_TOTAL ) {
				break;
			}
			$room  = min( self::MAX_PER_TYPE, self::MAX_TOTAL - $used );
			$links = [];
			foreach ( $this->catalog->links_for( $name, $room ) as $link ) {
				$key = $this->canonical( $link['url'] );
				if ( '' === $key || $this->already_listed( $groups, $key ) ) {
					continue;
				}
				$links[] = [
					'title'       => $link['title'],
					'url'         => $link['url'],
					'description' => $link['description'],
				];
				++$used;
				if ( count( $links ) >= $room ) {
					break;
				}
			}
			if ( [] !== $links ) {
				$groups[] = [
					'label' => $label,
					'links' => $links,
				];
			}
		}

		return $groups;
	}

	/**
	 * Markdown body following the llms.txt layout.
	 *
	 * Safe to call when the feature is off. The public rewrite is what stays dark.
	 *
	 * @return string
	 */
	public function render(): string {
		$name    = $this->one_line( $this->site_name(), 120 );
		$summary = $this->one_line( $this->site_summary( $name ), 300 );
		$intro   = $this->intro_text( $this->settings->llms_intro() );
		$lines   = [ '# ' . $name, '', '> ' . $summary, '' ];
		if ( '' !== $intro ) {
			$lines[] = $intro;
			$lines[] = '';
		}

		foreach ( $this->groups_for_document() as $group ) {
			$lines[] = '## ' . $this->one_line( $group['label'], 80 );
			$lines[] = '';
			foreach ( $group['links'] as $link ) {
				$lines[] = $this->link_line( $link['title'], $link['url'], $link['description'] );
			}
			$lines[] = '';
		}

		$body = rtrim( implode( "\n", $lines ) ) . "\n";

		/**
		 * Filter the virtual llms.txt body RankMySEO is about to return.
		 *
		 * @param string $body Markdown body.
		 */
		$filtered = apply_filters( 'rankmyseo_llms_txt', $body );
		return is_string( $filtered ) && '' !== $filtered ? $filtered : $body;
	}

	/**
	 * How many links the rendered file would contain.
	 *
	 * @return int
	 */
	public function link_count(): int {
		$count = 0;
		foreach ( $this->groups_for_document() as $group ) {
			$count += count( $group['links'] );
		}

		return $count;
	}

	/**
	 * Groups after manual include and exclude rules.
	 *
	 * @return list<array{label: string, links: list<array{title: string, url: string, description: string}>}>
	 */
	public function groups_for_document(): array {
		$exclude = $this->excluded_keys();
		$groups  = [];
		foreach ( $this->automatic_groups() as $group ) {
			$links = [];
			foreach ( $group['links'] as $link ) {
				$key = $this->canonical( $link['url'] );
				if ( '' === $key || isset( $exclude[ $key ] ) ) {
					continue;
				}
				$links[] = $link;
			}
			if ( [] !== $links ) {
				$groups[] = [
					'label' => $group['label'],
					'links' => $links,
				];
			}
		}

		$extra = [];
		foreach ( $this->settings->llms_include_urls() as $url ) {
			$key  = $this->canonical( $url );
			$seen = $this->already_listed( $groups, $key )
				|| $this->already_listed(
					[
						[
							'label' => '',
							'links' => $extra,
						],
					],
					$key
				);
			if ( '' === $key || isset( $exclude[ $key ] ) || $seen ) {
				continue;
			}
			if ( $this->count_links( $groups ) + count( $extra ) >= self::MAX_TOTAL ) {
				break;
			}
			$extra[] = [
				'title'       => $this->title_from_url( $url ),
				'url'         => $url,
				'description' => '',
			];
		}

		if ( [] !== $extra ) {
			$groups[] = [
				'label' => __( 'Included links', 'rankmyseo' ),
				'links' => $extra,
			];
		}

		return $groups;
	}

	/**
	 * Homepage link, or null when it cannot be formed.
	 *
	 * @return array{title: string, url: string, description: string}|null
	 */
	private function home_link(): ?array {
		$url = $this->canonical( home_url( '/' ) );
		if ( '' === $url ) {
			return null;
		}

		return [
			'title'       => $this->site_name(),
			'url'         => trailingslashit( $url ),
			'description' => '',
		];
	}

	/**
	 * Site name used as the document title.
	 *
	 * @return string
	 */
	private function site_name(): string {
		$name = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$name = $this->one_line( $name, 120 );
		return '' !== $name ? $name : __( 'Site', 'rankmyseo' );
	}

	/**
	 * One-line summary under the title.
	 *
	 * @param string $name Site name.
	 * @return string
	 */
	private function site_summary( string $name ): string {
		$summary = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'description' ) : '';
		$summary = $this->one_line( $summary, 300 );
		if ( '' !== $summary ) {
			return $summary;
		}

		return sprintf(
			/* translators: %s: site name. */
			__( 'Public pages on %s.', 'rankmyseo' ),
			$name
		);
	}

	/**
	 * Canonical keys the file must omit.
	 *
	 * @return array<string, true>
	 */
	private function excluded_keys(): array {
		$keys = [];
		foreach ( $this->settings->llms_exclude_urls() as $url ) {
			$key = $this->canonical( $url );
			if ( '' !== $key ) {
				$keys[ $key ] = true;
			}
		}

		return $keys;
	}

	/**
	 * Whether a canonical URL is already present.
	 *
	 * @param list<array{label: string, links: list<array{title: string, url: string, description: string}>}> $groups Groups to scan.
	 * @param string                                                                                          $key    Canonical URL.
	 * @return bool
	 */
	private function already_listed( array $groups, string $key ): bool {
		foreach ( $groups as $group ) {
			foreach ( $group['links'] as $link ) {
				if ( $this->canonical( $link['url'] ) === $key ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Count links across groups.
	 *
	 * @param list<array{label: string, links: list<array{title: string, url: string, description: string}>}> $groups Groups.
	 * @return int
	 */
	private function count_links( array $groups ): int {
		$count = 0;
		foreach ( $groups as $group ) {
			$count += count( $group['links'] );
		}

		return $count;
	}

	/**
	 * One markdown list item.
	 *
	 * @param string $title       Link text.
	 * @param string $url         Absolute URL.
	 * @param string $description Optional description after the colon.
	 * @return string
	 */
	private function link_line( string $title, string $url, string $description ): string {
		$safe_title = $this->link_text( $title );
		$line       = '- [' . $safe_title . '](' . $url . ')';
		$detail     = $this->one_line( $description, 180 );
		if ( '' !== $detail ) {
			$line .= ': ' . $detail;
		}

		return $line;
	}

	/**
	 * Readable title for a manually included URL.
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private function title_from_url( string $url ): string {
		$path    = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );
		$segment = trim( basename( untrailingslashit( $path ) ) );
		if ( '' === $segment ) {
			return $this->site_name();
		}

		$segment = str_replace( [ '-', '_' ], ' ', $segment );
		return $this->one_line( $segment, 120 );
	}

	/**
	 * Link text that cannot break the markdown link.
	 *
	 * @param string $title Raw title.
	 * @return string
	 */
	private function link_text( string $title ): string {
		$text = str_replace( [ '[', ']', '(', ')' ], '', $this->one_line( $title, 180 ) );
		return '' !== $text ? $text : __( 'Untitled', 'rankmyseo' );
	}

	/**
	 * Plain intro note. Heading markers are removed so the note stays a paragraph.
	 *
	 * @param string $intro Raw note.
	 * @return string
	 */
	private function intro_text( string $intro ): string {
		$intro = trim( wp_strip_all_tags( $intro ) );
		$parts = preg_split( '/\R/', $intro );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$lines = [];
		foreach ( $parts as $part ) {
			$line = ltrim( trim( (string) $part ), "# \t" );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Collapse text to one line and cap its length.
	 *
	 * @param string $text Raw text.
	 * @param int    $max  Maximum characters.
	 * @return string
	 */
	private function one_line( string $text, int $max ): string {
		$text = trim( wp_strip_all_tags( $text ) );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( mb_strlen( $text ) > $max ) {
			return rtrim( mb_substr( $text, 0, $max ) );
		}

		return $text;
	}

	/**
	 * Comparable http(s) URL without a trailing slash, query, or fragment.
	 *
	 * A leading slash is resolved against the site home.
	 *
	 * @param string $url Raw URL or path.
	 * @return string Empty when the value is not a usable http(s) URL.
	 */
	public function canonical( string $url ): string {
		$url = trim( $url );
		if ( '' === $url || str_contains( $url, '(' ) || str_contains( $url, ')' ) || preg_match( '/\s/', $url ) ) {
			return '';
		}
		if ( str_starts_with( $url, '/' ) ) {
			$url = home_url( $url );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return '';
		}

		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		if ( '' === $path ) {
			$path = '/';
		}
		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = untrailingslashit( $path );
		}

		$host = strtolower( (string) $parts['host'] );
		if ( isset( $parts['port'] ) ) {
			$port = (int) $parts['port'];
			if ( $port > 0 && $port <= 65535 ) {
				$host .= ':' . $port;
			}
		}

		return $scheme . '://' . $host . $path;
	}
}
