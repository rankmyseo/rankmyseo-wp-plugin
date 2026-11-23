<?php
/**
 * Redirect rule service: validation, O(1) matching, loops, and CSV.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are returned to the REST layer, not echoed.

/**
 * Coordinates storage, cache, safety checks, and hit counting.
 */
final class RedirectService {
	/**
	 * Allowed HTTP status codes.
	 *
	 * @var int[]
	 */
	public const STATUS_CODES = [ 301, 302, 307, 410, 451 ];

	/**
	 * Status codes that do not need a target URL.
	 *
	 * @var int[]
	 */
	public const TERMINAL_CODES = [ 410, 451 ];

	/**
	 * Allowed match types.
	 *
	 * @var list<string>
	 */
	public const MATCH_TYPES = [ 'exact', 'prefix', 'regex' ];

	/**
	 * Allowed origin labels.
	 *
	 * @var list<string>
	 */
	public const ORIGINS = [ 'manual', 'auto_slug', 'import', 'ai' ];

	/**
	 * Minimum seconds between hit-counter writes for the same rule.
	 */
	public const HIT_THROTTLE_SECONDS = 60;

	/**
	 * Sources that must never be redirected.
	 *
	 * @var list<string>
	 */
	private const PROTECTED_SOURCES = [
		'/',
		'/wp-login.php',
		'/xmlrpc.php',
		'/wp-cron.php',
	];

	/**
	 * Rule store.
	 *
	 * @var RedirectStore
	 */
	private RedirectStore $store;

	/**
	 * Path normalizer.
	 *
	 * @var PathNormalizer
	 */
	private PathNormalizer $paths;

	/**
	 * Regex safety guard.
	 *
	 * @var RegexGuard
	 */
	private RegexGuard $regex;

	/**
	 * Loop and chain detector.
	 *
	 * @var LoopGuard
	 */
	private LoopGuard $loops;

	/**
	 * CSV codec.
	 *
	 * @var CsvCodec
	 */
	private CsvCodec $csv;

	/**
	 * Server config exporter.
	 *
	 * @var ServerRulesExporter
	 */
	private ServerRulesExporter $server_rules;

	/**
	 * Object cache.
	 *
	 * @var RedirectCache
	 */
	private RedirectCache $cache;

	/**
	 * Module settings.
	 *
	 * @var RedirectSettings
	 */
	private RedirectSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param RedirectStore       $store        Rule store.
	 * @param PathNormalizer      $paths        Path normalizer.
	 * @param RegexGuard          $regex        Regex guard.
	 * @param LoopGuard           $loops        Loop detector.
	 * @param CsvCodec            $csv          CSV codec.
	 * @param ServerRulesExporter $server_rules Server export helper.
	 * @param RedirectCache       $cache        Object cache.
	 * @param RedirectSettings    $settings     Module settings.
	 */
	public function __construct(
		RedirectStore $store,
		PathNormalizer $paths,
		RegexGuard $regex,
		LoopGuard $loops,
		CsvCodec $csv,
		ServerRulesExporter $server_rules,
		RedirectCache $cache,
		RedirectSettings $settings
	) {
		$this->store        = $store;
		$this->paths        = $paths;
		$this->regex        = $regex;
		$this->loops        = $loops;
		$this->csv          = $csv;
		$this->server_rules = $server_rules;
		$this->cache        = $cache;
		$this->settings     = $settings;
	}

	/**
	 * Create a redirect rule.
	 *
	 * @param array<string, mixed> $input        Rule fields.
	 * @param bool                 $allow_update When true, an existing source is updated (CSV import).
	 * @return array{redirect: array<string, mixed>, warning: string, chain: list<string>, updated: bool}
	 * @throws RedirectException When validation fails.
	 */
	public function create( array $input, bool $allow_update = false ): array {
		$prepared = $this->prepare_input( $input, 0 );
		$existing = $this->store->find_duplicate( $prepared['source_hash'], $prepared['match_type'], 0 );

		if ( null !== $existing ) {
			if ( ! $allow_update ) {
				throw new RedirectException(
					'redirect_duplicate',
					__( 'A redirect for that address already exists.', 'rankmyseo' )
				);
			}

			$updated            = $this->update( (int) $existing['id'], $input );
			$updated['updated'] = true;
			return $updated;
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		$id  = $this->store->insert(
			[
				'source_path'   => $prepared['source_path'],
				'source_hash'   => $prepared['source_hash'],
				'match_type'    => $prepared['match_type'],
				'target_url'    => $prepared['target_url'],
				'status_code'   => $prepared['status_code'],
				'is_active'     => $prepared['is_active'],
				'source_origin' => $prepared['source_origin'],
				'hits'          => 0,
				'created_at'    => $now,
				'updated_at'    => $now,
			]
		);

		if ( $id < 1 ) {
			throw new RedirectException(
				'redirect_store',
				__( 'The redirect could not be saved.', 'rankmyseo' )
			);
		}

		$this->cache->bust();
		$stored = $this->must_find( $id );

		return [
			'redirect' => $stored,
			'warning'  => $prepared['warning'],
			'chain'    => $prepared['chain'],
			'updated'  => false,
		];
	}

	/**
	 * Update a redirect rule.
	 *
	 * @param int                  $id    Rule ID.
	 * @param array<string, mixed> $input Changed fields.
	 * @return array{redirect: array<string, mixed>, warning: string, chain: list<string>, updated: bool}
	 * @throws RedirectException When validation fails or the rule is missing.
	 */
	public function update( int $id, array $input ): array {
		$current   = $this->must_find( $id );
		$merged    = array_merge(
			[
				'source'        => $current['source_path'],
				'target'        => $current['target_url'],
				'status_code'   => $current['status_code'],
				'match_type'    => $current['match_type'],
				'is_active'     => $current['is_active'],
				'source_origin' => $current['source_origin'],
			],
			$input
		);
		$prepared  = $this->prepare_input( $merged, $id );
		$duplicate = $this->store->find_duplicate( $prepared['source_hash'], $prepared['match_type'], $id );

		if ( null !== $duplicate ) {
			throw new RedirectException(
				'redirect_duplicate',
				__( 'Another redirect already uses that address.', 'rankmyseo' )
			);
		}

		$this->store->update(
			$id,
			[
				'source_path'   => $prepared['source_path'],
				'source_hash'   => $prepared['source_hash'],
				'match_type'    => $prepared['match_type'],
				'target_url'    => $prepared['target_url'],
				'status_code'   => $prepared['status_code'],
				'is_active'     => $prepared['is_active'],
				'source_origin' => $prepared['source_origin'],
			]
		);

		$this->cache->bust();

		return [
			'redirect' => $this->must_find( $id ),
			'warning'  => $prepared['warning'],
			'chain'    => $prepared['chain'],
			'updated'  => true,
		];
	}

	/**
	 * Delete one rule.
	 *
	 * @param int $id Rule ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		$deleted = $this->store->delete( $id );
		if ( $deleted ) {
			$this->cache->bust();
		}
		return $deleted;
	}

	/**
	 * Apply a bulk action.
	 *
	 * @param string $action delete, activate, or deactivate.
	 * @param int[]  $ids    Rule IDs.
	 * @return int
	 * @throws RedirectException When the action is unknown.
	 */
	public function bulk( string $action, array $ids ): int {
		if ( 'delete' === $action ) {
			$count = $this->store->delete_ids( $ids );
		} elseif ( 'activate' === $action ) {
			$count = $this->store->set_active_ids( $ids, true );
		} elseif ( 'deactivate' === $action ) {
			$count = $this->store->set_active_ids( $ids, false );
		} else {
			throw new RedirectException(
				'redirect_bulk',
				__( 'Choose delete, activate, or deactivate.', 'rankmyseo' )
			);
		}

		if ( $count > 0 ) {
			$this->cache->bust();
		}

		return $count;
	}

	/**
	 * Point a chained rule directly at the final target.
	 *
	 * @param int $id Rule ID.
	 * @return array{redirect: array<string, mixed>, warning: string, chain: list<string>, updated: bool}
	 * @throws RedirectException When the rule cannot be flattened.
	 */
	public function flatten( int $id ): array {
		$rule = $this->must_find( $id );
		if ( 'exact' !== $rule['match_type'] || in_array( (int) $rule['status_code'], self::TERMINAL_CODES, true ) ) {
			throw new RedirectException(
				'redirect_flatten',
				__( 'Only exact redirects with a destination can be flattened.', 'rankmyseo' )
			);
		}

		$map   = $this->normalized_exact_map( $id );
		$final = $this->loops->final_target( $this->paths->normalize_path( (string) $rule['target_url'] ), $map );

		if ( $final === (string) $rule['source_path'] ) {
			throw new RedirectException(
				LoopGuard::LOOP,
				__( 'This redirect loops back to itself, so it cannot be flattened.', 'rankmyseo' )
			);
		}

		return $this->update(
			$id,
			[
				'target' => $final,
			]
		);
	}

	/**
	 * Search rules for the admin table.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function search( array $filters ): array {
		return $this->store->search( $filters );
	}

	/**
	 * Find one rule.
	 *
	 * @param int $id Rule ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		return $this->store->find( $id );
	}

	/**
	 * Match a normalized path. Exact hash lookup is attempted before regex rules.
	 *
	 * @param string $path Normalized request path.
	 * @return array<string, mixed>|null
	 */
	public function match( string $path ): ?array {
		$hash      = $this->paths->hash( $path );
		$cache_key = 'exact:' . $hash;
		$cached    = $this->cache->get( $cache_key );

		if ( $cached['found'] ) {
			$exact = $cached['value'];
		} else {
			$exact = $this->store->find_active_exact_by_hash( $hash );
			$this->cache->set( $cache_key, $exact );
		}

		if ( is_array( $exact ) ) {
			return $exact;
		}

		$prefix = $this->match_prefix( $path );
		if ( null !== $prefix ) {
			return $prefix;
		}

		return $this->match_regex( $path );
	}

	/**
	 * Test a URL the way the front end would.
	 *
	 * @param string $url Path or absolute URL.
	 * @return array<string, mixed>
	 */
	public function test_url( string $url ): array {
		$path  = $this->paths->normalize_path( $url );
		$query = $this->paths->extract_query( $url );
		$rule  = $this->match( $path );

		if ( null === $rule ) {
			return [
				'matched' => false,
				'path'    => $path,
				'query'   => $query,
			];
		}

		return [
			'matched'     => true,
			'path'        => $path,
			'query'       => $query,
			'redirect_id' => (int) $rule['id'],
			'match_type'  => (string) $rule['match_type'],
			'status_code' => (int) $rule['status_code'],
			'target_url'  => $this->build_location( $rule, $path, $query ),
			'source_path' => (string) $rule['source_path'],
		];
	}

	/**
	 * Build the Location value for a matched rule.
	 *
	 * @param array<string, mixed> $rule  Matched rule.
	 * @param string               $path  Request path.
	 * @param string               $query Request query string without "?".
	 * @return string|null Null for 410 and 451.
	 */
	public function build_location( array $rule, string $path, string $query ): ?string {
		$code = (int) ( $rule['status_code'] ?? 0 );
		if ( in_array( $code, self::TERMINAL_CODES, true ) ) {
			return null;
		}

		$target = (string) ( $rule['target_url'] ?? '' );
		if ( 'regex' === ( $rule['match_type'] ?? '' ) ) {
			$target = $this->regex->replace( (string) $rule['source_path'], $path, $target );
		}

		try {
			$target = $this->sanitize_target( $target, $code );
		} catch ( RedirectException $exception ) {
			unset( $exception );
			return null;
		}

		if ( null === $target ) {
			return null;
		}

		if ( $this->settings->preserve_query() && '' !== $query && ! str_contains( $target, '?' ) ) {
			$target .= '?' . $query;
		}

		return $this->absolute_url( $target );
	}

	/**
	 * Record a hit using the throttled counter.
	 *
	 * @param int $id Rule ID.
	 * @return bool
	 */
	public function record_hit( int $id ): bool {
		return $this->store->increment_hits( $id, self::HIT_THROTTLE_SECONDS );
	}

	/**
	 * Create or refresh the automatic slug-change redirect and retarget chains.
	 *
	 * @param string $from_path Old public path.
	 * @param string $to_path   New public path.
	 * @return array<string, mixed>|null
	 */
	public function create_auto_slug( string $from_path, string $to_path ): ?array {
		$from = $this->paths->normalize_path( $from_path );
		$to   = $this->paths->normalize_path( $to_path );

		if ( '' === $from || '' === $to || $from === $to || $this->is_protected_source( $from ) ) {
			return null;
		}

		$this->retarget_existing( $from, $to );

		try {
			$result = $this->create(
				[
					'source'        => $from,
					'target'        => $to,
					'status_code'   => 301,
					'match_type'    => 'exact',
					'is_active'     => true,
					'source_origin' => 'auto_slug',
				],
				true
			);
		} catch ( RedirectException $exception ) {
			unset( $exception );
			return null;
		}

		/**
		 * Fires after an automatic slug redirect is saved.
		 *
		 * @param array<string, mixed> $redirect Saved rule.
		 */
		do_action( 'rankmyseo_auto_slug_redirect_created', $result['redirect'] );

		return $result['redirect'];
	}

	/**
	 * Export redirects.
	 *
	 * @param string $format csv, htaccess, or nginx.
	 * @return array{filename: string, mime_type: string, content: string}
	 * @throws RedirectException When the format is unknown.
	 */
	public function export( string $format ): array {
		$rows = $this->store->export_rows();

		if ( 'htaccess' === $format ) {
			return [
				'filename'  => 'rankmyseo-redirects.htaccess',
				'mime_type' => 'text/plain',
				'content'   => $this->server_rules->to_htaccess( $rows ),
			];
		}

		if ( 'nginx' === $format ) {
			return [
				'filename'  => 'rankmyseo-redirects.nginx.conf',
				'mime_type' => 'text/plain',
				'content'   => $this->server_rules->to_nginx( $rows ),
			];
		}

		if ( 'csv' !== $format ) {
			throw new RedirectException(
				'redirect_export',
				__( 'Choose CSV, Apache, or Nginx export.', 'rankmyseo' )
			);
		}

		return [
			'filename'  => 'rankmyseo-redirects.csv',
			'mime_type' => 'text/csv',
			'content'   => $this->csv->export( $rows ),
		];
	}

	/**
	 * Import a CSV document.
	 *
	 * @param string $csv CSV text.
	 * @return array{created: int, updated: int, skipped: int, errors: list<array{line: int, message: string}>}
	 */
	public function import_csv( string $csv ): array {
		$parsed = $this->csv->parse( $csv );
		$result = [
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => [],
		];

		$line = 1;
		foreach ( $parsed as $row ) {
			++$line;
			try {
				$saved = $this->create(
					[
						'source'        => (string) ( $row['source_path'] ?? '' ),
						'target'        => (string) ( $row['target_url'] ?? '' ),
						'status_code'   => (int) ( $row['status_code'] ?? 301 ),
						'match_type'    => (string) ( $row['match_type'] ?? 'exact' ),
						'is_active'     => ! isset( $row['is_active'] ) || ! in_array( strtolower( (string) $row['is_active'] ), [ '0', 'false', 'no' ], true ),
						'source_origin' => in_array( (string) ( $row['source_origin'] ?? '' ), self::ORIGINS, true )
							? (string) $row['source_origin']
							: 'import',
					],
					true
				);
				if ( $saved['updated'] ) {
					++$result['updated'];
				} else {
					++$result['created'];
				}
			} catch ( RedirectException $exception ) {
				++$result['skipped'];
				$result['errors'][] = [
					'line'    => $line,
					'message' => $exception->getMessage(),
				];
			}
		}

		return $result;
	}

	/**
	 * Validate and normalize input for insert or update.
	 *
	 * @param array<string, mixed> $input     Raw input.
	 * @param int                  $except_id Rule being updated, or 0.
	 * @return array<string, mixed>
	 * @throws RedirectException When validation fails.
	 */
	private function prepare_input( array $input, int $except_id ): array {
		$match_type = sanitize_key( (string) ( $input['match_type'] ?? 'exact' ) );
		if ( ! in_array( $match_type, self::MATCH_TYPES, true ) ) {
			throw new RedirectException(
				'redirect_match_type',
				__( 'Match type must be exact, prefix, or regex.', 'rankmyseo' )
			);
		}

		$status = (int) ( $input['status_code'] ?? 301 );
		if ( ! in_array( $status, self::STATUS_CODES, true ) ) {
			throw new RedirectException(
				'redirect_status',
				__( 'Status must be 301, 302, 307, 410, or 451.', 'rankmyseo' )
			);
		}

		$source = $this->paths->normalize_source( (string) ( $input['source'] ?? $input['source_path'] ?? '' ), $match_type );
		if ( '' === $source ) {
			throw new RedirectException(
				'redirect_source',
				__( 'Enter the old address to redirect.', 'rankmyseo' )
			);
		}

		if ( 'regex' === $match_type ) {
			$reason = $this->regex->rejection_reason( $source );
			if ( null !== $reason ) {
				throw new RedirectException(
					'redirect_regex',
					__( 'That regular expression is unsafe or invalid. Shorten it and avoid nested wildcards.', 'rankmyseo' )
				);
			}
		} elseif ( strlen( $source ) > 512 || $this->is_protected_source( $source ) ) {
			throw new RedirectException(
				'redirect_protected',
				__( 'That address cannot be redirected.', 'rankmyseo' )
			);
		}

		$target_raw = $input['target'] ?? $input['target_url'] ?? null;
		$target     = $this->sanitize_target( is_string( $target_raw ) ? $target_raw : null, $status );
		$warning    = '';
		$chain      = [];

		if ( null !== $target ) {
			$target_path = $this->paths->normalize_path( $target );
			if ( 'regex' === $match_type && $this->regex->matches( $source, $target_path ) ) {
				throw new RedirectException(
					LoopGuard::LOOP,
					__( 'That pattern also matches its own destination, which would loop.', 'rankmyseo' )
				);
			}

			if ( 'prefix' === $match_type && $this->prefix_matches( $source, $target_path ) ) {
				throw new RedirectException(
					LoopGuard::SELF,
					__( 'A prefix redirect cannot send visitors to an address it also matches.', 'rankmyseo' )
				);
			}

			$map        = $this->normalized_exact_map( $except_id );
			$source_key = 'regex' === $match_type ? '' : $source;
			if ( '' !== $source_key ) {
				$inspected = $this->loops->inspect( $source_key, $target_path, $map );
				if ( ! $inspected['ok'] ) {
					$message = LoopGuard::SELF === $inspected['code']
						? __( 'A redirect cannot send an address to itself.', 'rankmyseo' )
						: __( 'That redirect would loop. Choose a destination that does not point back here.', 'rankmyseo' );
					throw new RedirectException( $inspected['code'], $message );
				}
				$warning = $inspected['code'];
				$chain   = $inspected['chain'];
			}
		}

		$origin = sanitize_key( (string) ( $input['source_origin'] ?? 'manual' ) );
		if ( ! in_array( $origin, self::ORIGINS, true ) ) {
			$origin = 'manual';
		}

		$active = array_key_exists( 'is_active', $input ) ? (bool) $input['is_active'] : true;

		return [
			'source_path'   => $source,
			'source_hash'   => $this->paths->hash( $source ),
			'match_type'    => $match_type,
			'target_url'    => $target,
			'status_code'   => $status,
			'is_active'     => $active,
			'source_origin' => $origin,
			'warning'       => $warning,
			'chain'         => $chain,
		];
	}

	/**
	 * Sanitize a destination URL. Terminal statuses return null.
	 *
	 * @param string|null $target Raw target.
	 * @param int         $status HTTP status.
	 * @return string|null
	 * @throws RedirectException When a required target is missing or unsafe.
	 */
	private function sanitize_target( ?string $target, int $status ): ?string {
		if ( in_array( $status, self::TERMINAL_CODES, true ) ) {
			return null;
		}

		$target = trim( (string) $target );
		if ( '' === $target ) {
			throw new RedirectException(
				'redirect_target',
				__( 'Enter the page visitors should open.', 'rankmyseo' )
			);
		}

		if ( preg_match( '#^(javascript|data|vbscript|file):#i', $target ) ) {
			throw new RedirectException(
				'redirect_target',
				__( 'That destination is not a valid web address.', 'rankmyseo' )
			);
		}

		if ( str_starts_with( $target, '//' ) ) {
			throw new RedirectException(
				'redirect_target',
				__( 'That destination is not a valid web address.', 'rankmyseo' )
			);
		}

		if ( preg_match( '#^https?://#i', $target ) ) {
			$parts = wp_parse_url( $target );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
				throw new RedirectException(
					'redirect_target',
					__( 'That destination is not a valid web address.', 'rankmyseo' )
				);
			}
			$clean = function_exists( 'esc_url_raw' ) ? esc_url_raw( $target ) : $target;
			if ( ! is_string( $clean ) || '' === $clean ) {
				throw new RedirectException(
					'redirect_target',
					__( 'That destination is not a valid web address.', 'rankmyseo' )
				);
			}
			return $clean;
		}

		if ( ! str_starts_with( $target, '/' ) ) {
			$target = '/' . $target;
		}

		$normalized = $this->paths->normalize_path( $target );
		$query      = $this->paths->extract_query( $target );
		if ( '' !== $query ) {
			return $normalized . '?' . $query;
		}

		return $normalized;
	}

	/**
	 * Whether a source path is reserved.
	 *
	 * @param string $path Normalized path.
	 * @return bool
	 */
	private function is_protected_source( string $path ): bool {
		if ( in_array( $path, self::PROTECTED_SOURCES, true ) ) {
			return true;
		}

		return str_starts_with( $path, '/wp-admin' );
	}

	/**
	 * Longest active prefix rule that matches the path.
	 *
	 * @param string $path Normalized path.
	 * @return array<string, mixed>|null
	 */
	private function match_prefix( string $path ): ?array {
		$rules       = $this->cached_rules( 'prefix' );
		$best        = null;
		$best_length = -1;

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$source = (string) ( $rule['source_path'] ?? '' );
			if ( $this->prefix_matches( $source, $path ) && strlen( $source ) > $best_length ) {
				$best        = $rule;
				$best_length = strlen( $source );
			}
		}

		return $best;
	}

	/**
	 * First active regex rule that matches the path.
	 *
	 * @param string $path Normalized path.
	 * @return array<string, mixed>|null
	 */
	private function match_regex( string $path ): ?array {
		foreach ( $this->cached_rules( 'regex' ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$pattern = (string) ( $rule['source_path'] ?? '' );
			if ( $this->regex->matches( $pattern, $path ) ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Cached active prefix or regex rules.
	 *
	 * @param string $match_type prefix or regex.
	 * @return list<array<string, mixed>>
	 */
	private function cached_rules( string $match_type ): array {
		$key    = 'rules:' . $match_type;
		$cached = $this->cache->get( $key );
		if ( $cached['found'] && is_array( $cached['value'] ) ) {
			return $cached['value'];
		}

		$rules = $this->store->active_rules( $match_type, 200 );
		$this->cache->set( $key, $rules );
		return $rules;
	}

	/**
	 * Whether a prefix source matches a path.
	 *
	 * @param string $source Normalized prefix.
	 * @param string $path   Normalized path.
	 * @return bool
	 */
	private function prefix_matches( string $source, string $path ): bool {
		if ( '' === $source || '/' === $source ) {
			return false;
		}

		return $path === $source || str_starts_with( $path, $source . '/' );
	}

	/**
	 * Exact source => normalized target path map.
	 *
	 * @param int $except_id Rule to omit.
	 * @return array<string, string>
	 */
	private function normalized_exact_map( int $except_id ): array {
		$map = [];
		foreach ( $this->store->exact_target_map( $except_id ) as $source => $target ) {
			$map[ (string) $source ] = $this->paths->normalize_path( (string) $target );
		}
		return $map;
	}

	/**
	 * Point active rules that land on $from_path at $to_path instead.
	 *
	 * @param string $from_path Old normalized path.
	 * @param string $to_path   New normalized path.
	 * @return void
	 */
	private function retarget_existing( string $from_path, string $to_path ): void {
		foreach ( $this->store->list_for_retarget() as $row ) {
			$target = (string) ( $row['target_url'] ?? '' );
			if ( '' === $target ) {
				continue;
			}
			if ( $this->paths->normalize_path( $target ) !== $from_path ) {
				continue;
			}
			if ( $this->paths->normalize_path( (string) ( $row['source_path'] ?? '' ) ) === $to_path ) {
				continue;
			}

			$this->store->update(
				(int) $row['id'],
				[
					'target_url' => $to_path,
				]
			);
		}
	}

	/**
	 * Turn a relative target into an absolute URL.
	 *
	 * @param string $target Sanitized target.
	 * @return string
	 */
	private function absolute_url( string $target ): string {
		if ( preg_match( '#^https?://#i', $target ) ) {
			return $target;
		}

		return home_url( $target );
	}

	/**
	 * Load a rule or throw.
	 *
	 * @param int $id Rule ID.
	 * @return array<string, mixed>
	 * @throws RedirectException When the rule does not exist.
	 */
	private function must_find( int $id ): array {
		$rule = $this->store->find( $id );
		if ( null === $rule ) {
			throw new RedirectException(
				'redirect_not_found',
				__( 'That redirect no longer exists.', 'rankmyseo' )
			);
		}

		return $rule;
	}
}
