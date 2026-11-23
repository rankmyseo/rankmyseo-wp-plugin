<?php
/**
 * Sitemap, IndexNow, and crawl-control settings stored outside the autoloaded core option.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Typed repository for Module 4 settings, the IndexNow log, and the submission queue.
 */
final class SitemapSettings {
	/**
	 * Compact sitemap settings. Not autoloaded.
	 */
	public const OPTION_KEY = 'rankmyseo_sitemap_settings';

	/**
	 * Raw advanced robots.txt body. Not autoloaded.
	 */
	public const ROBOTS_OPTION_KEY = 'rankmyseo_robots_custom';

	/**
	 * IndexNow submission log ring buffer. Not autoloaded.
	 */
	public const LOG_OPTION_KEY = 'rankmyseo_indexnow_log';

	/**
	 * Debounced IndexNow URL queue. Not autoloaded.
	 */
	public const QUEUE_OPTION_KEY = 'rankmyseo_indexnow_queue';

	/**
	 * Cache generation counter. Bumping it invalidates sitemap transients.
	 */
	public const CACHE_GEN_OPTION_KEY = 'rankmyseo_sitemap_cache_gen';

	/**
	 * Consecutive IndexNow failure count used for backoff.
	 */
	public const ATTEMPTS_OPTION_KEY = 'rankmyseo_indexnow_attempts';

	/**
	 * Public IndexNow endpoint. Requests are sent only after an admin enables IndexNow.
	 */
	public const INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';

	/**
	 * Default URLs per sitemap file.
	 */
	public const DEFAULT_PER_PAGE = 1000;

	/**
	 * Lowest allowed URLs-per-sitemap value.
	 */
	public const MIN_PER_PAGE = 100;

	/**
	 * Highest allowed URLs-per-sitemap value.
	 */
	public const MAX_PER_PAGE = 2000;

	/**
	 * Maximum IndexNow log rows kept.
	 */
	public const LOG_LIMIT = 50;

	/**
	 * Crawl hygiene keys. All default off.
	 *
	 * @var list<string>
	 */
	public const CRAWL_KEYS = [
		'redirect_attachments',
		'noindex_attachments',
		'noindex_search',
		'noindex_comment_pages',
		'strip_replytocom',
		'remove_rsd',
		'remove_wlw',
		'remove_shortlink',
	];

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		$crawl = [];
		foreach ( self::CRAWL_KEYS as $key ) {
			$crawl[ $key ] = false;
		}

		return [
			'max_urls'          => self::DEFAULT_PER_PAGE,
			'include_images'    => true,
			'include_authors'   => true,
			'post_types'        => [],
			'taxonomies'        => [],
			'excluded_post_ids' => [],
			'excluded_term_ids' => [],
			'indexnow_enabled'  => false,
			'indexnow_key'      => '',
			'allow_crawl'       => true,
			'robots_mode'       => 'preset',
			'robots_preset'     => 'default',
			'crawl'             => $crawl,
		];
	}

	/**
	 * Sanitized settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return $this->sanitize( $stored, $this->defaults() );
	}

	/**
	 * Persist a partial settings update.
	 *
	 * @param array<string, mixed> $input Partial settings.
	 * @return array<string, mixed>
	 */
	public function update( array $input ): array {
		$current = $this->all();
		$next    = $this->sanitize( $input, $current );
		update_option( self::OPTION_KEY, $next, false );

		if ( array_key_exists( 'robots_custom', $input ) ) {
			$this->set_robots_custom( (string) $input['robots_custom'] );
		}

		return $this->all();
	}

	/**
	 * Maximum URLs in one sitemap file.
	 *
	 * @return int
	 */
	public function max_urls(): int {
		return (int) $this->all()['max_urls'];
	}

	/**
	 * Whether image tags are included.
	 *
	 * @return bool
	 */
	public function include_images(): bool {
		return (bool) $this->all()['include_images'];
	}

	/**
	 * Whether author archives are included.
	 *
	 * @return bool
	 */
	public function include_authors(): bool {
		return (bool) $this->all()['include_authors'];
	}

	/**
	 * Whether IndexNow submission is enabled.
	 *
	 * @return bool
	 */
	public function is_indexnow_enabled(): bool {
		return (bool) $this->all()['indexnow_enabled'];
	}

	/**
	 * Whether virtual robots rules leave the site crawlable.
	 *
	 * @return bool
	 */
	public function allow_crawl(): bool {
		return (bool) $this->all()['allow_crawl'];
	}

	/**
	 * Explicit post-type map.
	 *
	 * @return array<string, bool>
	 */
	public function post_type_map(): array {
		$map = $this->all()['post_types'];
		return is_array( $map ) ? $map : [];
	}

	/**
	 * Explicit taxonomy map.
	 *
	 * @return array<string, bool>
	 */
	public function taxonomy_map(): array {
		$map = $this->all()['taxonomies'];
		return is_array( $map ) ? $map : [];
	}

	/**
	 * Whether a post type belongs in the sitemap.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public function is_post_type_included( string $post_type ): bool {
		return RobotsMeta::is_type_included( $post_type, $this->post_type_map() );
	}

	/**
	 * Whether a taxonomy belongs in the sitemap.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	public function is_taxonomy_included( string $taxonomy ): bool {
		return RobotsMeta::is_type_included( $taxonomy, $this->taxonomy_map() );
	}

	/**
	 * Excluded post IDs.
	 *
	 * @return list<int>
	 */
	public function excluded_post_ids(): array {
		$ids = $this->all()['excluded_post_ids'];
		return is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : [];
	}

	/**
	 * Excluded term IDs.
	 *
	 * @return list<int>
	 */
	public function excluded_term_ids(): array {
		$ids = $this->all()['excluded_term_ids'];
		return is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : [];
	}

	/**
	 * Crawl hygiene flags.
	 *
	 * @return array<string, bool>
	 */
	public function crawl(): array {
		$crawl = $this->all()['crawl'];
		return is_array( $crawl ) ? $crawl : [];
	}

	/**
	 * Simple-mode bundle: hide attachments and search results from crawlers.
	 *
	 * @return bool
	 */
	public function hides_utility_pages(): bool {
		$crawl = $this->crawl();
		return ! empty( $crawl['redirect_attachments'] )
			&& ! empty( $crawl['noindex_attachments'] )
			&& ! empty( $crawl['noindex_search'] );
	}

	/**
	 * Robots editor mode: preset or custom.
	 *
	 * @return string
	 */
	public function robots_mode(): string {
		$mode = (string) $this->all()['robots_mode'];
		return in_array( $mode, [ 'preset', 'custom' ], true ) ? $mode : 'preset';
	}

	/**
	 * Active safe preset.
	 *
	 * @return string
	 */
	public function robots_preset(): string {
		$preset = (string) $this->all()['robots_preset'];
		return in_array( $preset, [ 'default', 'open' ], true ) ? $preset : 'default';
	}

	/**
	 * Stored custom robots.txt body.
	 *
	 * @return string
	 */
	public function robots_custom(): string {
		$stored = get_option( self::ROBOTS_OPTION_KEY, '' );
		return is_string( $stored ) ? $stored : '';
	}

	/**
	 * Replace the custom robots.txt body.
	 *
	 * @param string $rules Raw rules.
	 * @return string
	 */
	public function set_robots_custom( string $rules ): string {
		$clean = $this->sanitize_robots( $rules );
		update_option( self::ROBOTS_OPTION_KEY, $clean, false );
		return $clean;
	}

	/**
	 * Return a valid IndexNow key, generating one locally when missing.
	 *
	 * Generation uses random bytes only. It does not contact IndexNow.
	 *
	 * @return string
	 */
	public function ensure_key(): string {
		$current = $this->all();
		$key     = (string) $current['indexnow_key'];
		if ( self::is_valid_key( $key ) ) {
			return $key;
		}

		try {
			$key = bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $exception ) {
			unset( $exception );
			$key = substr( hash( 'sha256', home_url( '/' ) . microtime( true ) ), 0, 32 );
		}

		$current['indexnow_key'] = $key;
		update_option( self::OPTION_KEY, $current, false );
		return $key;
	}

	/**
	 * Replace the IndexNow key with a new 32-character hex key.
	 *
	 * @return string
	 */
	public function rotate_key(): string {
		try {
			$key = bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $exception ) {
			unset( $exception );
			$key = substr( hash( 'sha256', home_url( '/' ) . microtime( true ) . 'rotate' ), 0, 32 );
		}

		$current                 = $this->all();
		$current['indexnow_key'] = $key;
		update_option( self::OPTION_KEY, $current, false );
		return $key;
	}

	/**
	 * Whether a string is a 32-character lowercase hex IndexNow key.
	 *
	 * @param string $key Candidate key.
	 * @return bool
	 */
	public static function is_valid_key( string $key ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $key );
	}

	/**
	 * Recent IndexNow submissions, newest first.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function get_log(): array {
		$stored = get_option( self::LOG_OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$log = [];
		foreach ( $stored as $entry ) {
			if ( is_array( $entry ) ) {
				$log[] = $entry;
			}
		}

		return $log;
	}

	/**
	 * Push one log row and keep the newest 50.
	 *
	 * @param array<string, mixed> $entry Log row.
	 * @return void
	 */
	public function append_log( array $entry ): void {
		$log = $this->get_log();
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::LOG_LIMIT );
		update_option( self::LOG_OPTION_KEY, $log, false );
	}

	/**
	 * Queued IndexNow URLs.
	 *
	 * @return list<string>
	 */
	public function get_queue(): array {
		$stored = get_option( self::QUEUE_OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$urls = [];
		foreach ( $stored as $url ) {
			if ( is_string( $url ) && '' !== $url ) {
				$urls[] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Replace the IndexNow queue.
	 *
	 * @param array<int, string> $urls URLs waiting for submission.
	 * @return void
	 */
	public function save_queue( array $urls ): void {
		update_option( self::QUEUE_OPTION_KEY, array_values( $urls ), false );
	}

	/**
	 * Current backoff attempt count.
	 *
	 * @return int
	 */
	public function attempts(): int {
		return max( 0, (int) get_option( self::ATTEMPTS_OPTION_KEY, 0 ) );
	}

	/**
	 * Increase and return the backoff attempt count.
	 *
	 * @return int
	 */
	public function increment_attempts(): int {
		$next = $this->attempts() + 1;
		update_option( self::ATTEMPTS_OPTION_KEY, $next, false );
		return $next;
	}

	/**
	 * Clear the backoff counter after a successful submission.
	 *
	 * @return void
	 */
	public function reset_attempts(): void {
		update_option( self::ATTEMPTS_OPTION_KEY, 0, false );
	}

	/**
	 * Current sitemap cache generation.
	 *
	 * @return int
	 */
	public function cache_generation(): int {
		return max( 1, (int) get_option( self::CACHE_GEN_OPTION_KEY, 1 ) );
	}

	/**
	 * Invalidate cached sitemap XML by bumping the generation counter.
	 *
	 * @return int
	 */
	public function flush_cache(): int {
		$next = $this->cache_generation() + 1;
		update_option( self::CACHE_GEN_OPTION_KEY, $next, false );
		return $next;
	}

	/**
	 * Sanitize settings against a base state.
	 *
	 * @param array<string, mixed> $input Incoming values.
	 * @param array<string, mixed> $base  Current state.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input, array $base ): array {
		$state = array_merge( $this->defaults(), $base );

		if ( array_key_exists( 'max_urls', $input ) ) {
			$max = (int) $input['max_urls'];
			if ( $max < self::MIN_PER_PAGE ) {
				$max = self::MIN_PER_PAGE;
			}
			if ( $max > self::MAX_PER_PAGE ) {
				$max = self::MAX_PER_PAGE;
			}
			$state['max_urls'] = $max;
		}

		foreach ( [ 'include_images', 'include_authors', 'indexnow_enabled', 'allow_crawl' ] as $bool_key ) {
			if ( array_key_exists( $bool_key, $input ) ) {
				$state[ $bool_key ] = $this->to_bool( $input[ $bool_key ] );
			}
		}

		if ( array_key_exists( 'post_types', $input ) && is_array( $input['post_types'] ) ) {
			$state['post_types'] = $this->sanitize_flag_map( $input['post_types'] );
		}

		if ( array_key_exists( 'taxonomies', $input ) && is_array( $input['taxonomies'] ) ) {
			$state['taxonomies'] = $this->sanitize_flag_map( $input['taxonomies'] );
		}

		if ( array_key_exists( 'excluded_post_ids', $input ) ) {
			$state['excluded_post_ids'] = $this->sanitize_id_list( $input['excluded_post_ids'] );
		}

		if ( array_key_exists( 'excluded_term_ids', $input ) ) {
			$state['excluded_term_ids'] = $this->sanitize_id_list( $input['excluded_term_ids'] );
		}

		if ( array_key_exists( 'indexnow_key', $input ) ) {
			$key = strtolower( trim( (string) $input['indexnow_key'] ) );
			if ( self::is_valid_key( $key ) ) {
				$state['indexnow_key'] = $key;
			}
		}

		if ( array_key_exists( 'robots_mode', $input ) ) {
			$mode = sanitize_key( (string) $input['robots_mode'] );
			if ( in_array( $mode, [ 'preset', 'custom' ], true ) ) {
				$state['robots_mode'] = $mode;
			}
		}

		if ( array_key_exists( 'robots_preset', $input ) ) {
			$preset = sanitize_key( (string) $input['robots_preset'] );
			if ( in_array( $preset, [ 'default', 'open' ], true ) ) {
				$state['robots_preset'] = $preset;
			}
		}

		$crawl = is_array( $state['crawl'] ) ? $state['crawl'] : [];
		if ( isset( $input['crawl'] ) && is_array( $input['crawl'] ) ) {
			foreach ( self::CRAWL_KEYS as $key ) {
				if ( array_key_exists( $key, $input['crawl'] ) ) {
					$crawl[ $key ] = $this->to_bool( $input['crawl'][ $key ] );
				}
			}
		}

		if ( array_key_exists( 'hide_utility_pages', $input ) ) {
			$hide                          = $this->to_bool( $input['hide_utility_pages'] );
			$crawl['redirect_attachments'] = $hide;
			$crawl['noindex_attachments']  = $hide;
			$crawl['noindex_search']       = $hide;
		}

		$state['crawl'] = $crawl;

		return $state;
	}

	/**
	 * Sanitize a name => bool map.
	 *
	 * @param array<mixed> $map Raw map.
	 * @return array<string, bool>
	 */
	private function sanitize_flag_map( array $map ): array {
		$clean = [];
		$count = 0;
		foreach ( $map as $name => $enabled ) {
			if ( $count >= 50 ) {
				break;
			}
			$key = sanitize_key( (string) $name );
			if ( '' === $key ) {
				continue;
			}
			$clean[ $key ] = $this->to_bool( $enabled );
			++$count;
		}

		return $clean;
	}

	/**
	 * Sanitize a list of positive IDs.
	 *
	 * @param mixed $value Raw list or comma-separated string.
	 * @return list<int>
	 */
	private function sanitize_id_list( $value ): array {
		if ( is_string( $value ) ) {
			$parts = preg_split( '/[\s,]+/', $value );
			$value = is_array( $parts ) ? $parts : [];
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$ids = [];
		foreach ( $value as $item ) {
			$id = (int) $item;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
			if ( count( $ids ) >= 500 ) {
				break;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitize a robots.txt body.
	 *
	 * @param string $rules Raw rules.
	 * @return string
	 */
	private function sanitize_robots( string $rules ): string {
		$rules = str_replace( [ "\0", "\r\n", "\r" ], [ '', "\n", "\n" ], $rules );
		if ( function_exists( 'sanitize_textarea_field' ) ) {
			$rules = sanitize_textarea_field( $rules );
		}
		if ( strlen( $rules ) > 20000 ) {
			$rules = substr( $rules, 0, 20000 );
		}

		return trim( $rules );
	}

	/**
	 * Coerce mixed input to bool.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, [ '1', 'true', 'yes', 'on' ], true ) ) {
				return true;
			}
			if ( in_array( $lower, [ '0', 'false', 'no', 'off', '' ], true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}
}
