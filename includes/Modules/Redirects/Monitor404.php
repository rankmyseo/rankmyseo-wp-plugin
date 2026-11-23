<?php
/**
 * Deduplicated 404 monitor with bot and probe filtering.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are returned to the REST layer, not echoed.

/**
 * Records human 404s, hides scanner noise, and prunes the log.
 */
final class Monitor404 {
	/**
	 * Path fragments left by common exploit probes.
	 *
	 * @var list<string>
	 */
	private const PROBE_NEEDLES = [
		'.env',
		'wp-login',
		'xmlrpc',
		'wp-config',
		'.git',
		'wlwmanifest',
		'phpmyadmin',
		'adminer',
		'.aws',
		'id_rsa',
		'.ds_store',
		'composer.lock',
		'vendor/phpunit',
		'eval-stdin',
		'/wp-admin',
		'etc/passwd',
	];

	/**
	 * User-agent fragments treated as bots.
	 *
	 * @var list<string>
	 */
	private const BOT_NEEDLES = [
		'bot',
		'crawl',
		'spider',
		'slurp',
		'semrush',
		'ahrefs',
		'mj12bot',
		'dotbot',
		'petalbot',
		'bytespider',
		'gptbot',
		'claudebot',
		'facebookexternalhit',
		'python-requests',
		'curl/',
		'wget',
		'go-http-client',
		'headlesschrome',
	];

	/**
	 * Log store.
	 *
	 * @var NotFoundStore
	 */
	private NotFoundStore $store;

	/**
	 * Path normalizer.
	 *
	 * @var PathNormalizer
	 */
	private PathNormalizer $paths;

	/**
	 * Regex guard for optional regex: ignore patterns.
	 *
	 * @var RegexGuard
	 */
	private RegexGuard $regex;

	/**
	 * Module settings.
	 *
	 * @var RedirectSettings
	 */
	private RedirectSettings $settings;

	/**
	 * Redirect service used by one-click conversion.
	 *
	 * @var RedirectService|null
	 */
	private ?RedirectService $redirects;

	/**
	 * Constructor.
	 *
	 * @param NotFoundStore        $store     Log store.
	 * @param PathNormalizer       $paths     Path normalizer.
	 * @param RegexGuard           $regex     Regex guard.
	 * @param RedirectSettings     $settings  Module settings.
	 * @param RedirectService|null $redirects Redirect service for one-click fixes.
	 */
	public function __construct(
		NotFoundStore $store,
		PathNormalizer $paths,
		RegexGuard $regex,
		RedirectSettings $settings,
		?RedirectService $redirects = null
	) {
		$this->store     = $store;
		$this->paths     = $paths;
		$this->regex     = $regex;
		$this->settings  = $settings;
		$this->redirects = $redirects;
	}

	/**
	 * Attach a redirect service after both objects exist.
	 *
	 * @param RedirectService $redirects Redirect service.
	 * @return void
	 */
	public function set_redirects( RedirectService $redirects ): void {
		$this->redirects = $redirects;
	}

	/**
	 * Record the current front-end 404 when it is not noise.
	 *
	 * @return bool True when a row was written or updated.
	 */
	public function record_current_request(): bool {
		if ( function_exists( 'is_404' ) && ! is_404() ) {
			return false;
		}

		$referer = '';
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) );
		}

		$user_agent = '';
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) );
		}

		return $this->record(
			$this->paths->current_request_path(),
			$this->paths->current_request_query(),
			$referer,
			$user_agent
		);
	}

	/**
	 * Record a normalized 404 when it passes the noise filter.
	 *
	 * @param string $path       Normalized path.
	 * @param string $query      Query string.
	 * @param string $referer    Referrer.
	 * @param string $user_agent User agent.
	 * @return bool
	 */
	public function record( string $path, string $query, string $referer, string $user_agent ): bool {
		$path = $this->paths->normalize_path( $path );
		if ( $this->should_ignore( $path, $user_agent ) ) {
			return false;
		}

		$query      = $this->clip( $query, 512 );
		$referer    = $this->clip( $referer, 512 );
		$user_agent = $this->clip( $user_agent, 255 );
		$hash       = $this->paths->hash( $path );
		$now        = gmdate( 'Y-m-d H:i:s' );

		return $this->store->upsert( $path, $hash, $query, $referer, $user_agent, $now ) > 0;
	}

	/**
	 * Whether a path or user agent is scanner noise or an ignore rule.
	 *
	 * @param string $path       Normalized path.
	 * @param string $user_agent User agent.
	 * @return bool
	 */
	public function should_ignore( string $path, string $user_agent ): bool {
		$lower_path = strtolower( $path );
		foreach ( self::PROBE_NEEDLES as $needle ) {
			if ( str_contains( $lower_path, $needle ) ) {
				return true;
			}
		}

		if ( 1 === preg_match( '/\.php$/', $lower_path ) ) {
			return true;
		}

		if ( 1 === preg_match( '/\.(?:map|ico|css|js|png|jpe?g|gif|webp|svg|woff2?|ttf|eot|mp4|webm|pdf|zip|rar|gz|bak)$/', $lower_path ) ) {
			return true;
		}

		$agent = strtolower( trim( $user_agent ) );
		if ( '' === $agent ) {
			return true;
		}

		foreach ( self::BOT_NEEDLES as $needle ) {
			if ( str_contains( $agent, $needle ) ) {
				return true;
			}
		}

		foreach ( $this->settings->bot_patterns() as $pattern ) {
			if ( $this->pattern_matches( $agent, $pattern ) ) {
				return true;
			}
		}

		foreach ( $this->settings->ignore_patterns() as $pattern ) {
			if ( $this->pattern_matches( $lower_path, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Search the log.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function search( array $filters ): array {
		return $this->store->search( $filters );
	}

	/**
	 * Mark one row ignored.
	 *
	 * @param int $id Log ID.
	 * @return bool
	 */
	public function ignore( int $id ): bool {
		return $this->store->set_status( $id, 'ignored' );
	}

	/**
	 * Delete one row.
	 *
	 * @param int $id Log ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return $this->store->delete( $id );
	}

	/**
	 * Bulk ignore or delete.
	 *
	 * @param string $action ignore or delete.
	 * @param int[]  $ids    Log IDs.
	 * @return int
	 * @throws RedirectException When the action is unknown.
	 */
	public function bulk( string $action, array $ids ): int {
		if ( 'ignore' === $action ) {
			return $this->store->ignore_ids( $ids );
		}

		if ( 'delete' === $action ) {
			return $this->store->delete_ids( $ids );
		}

		throw new RedirectException(
			'not_found_bulk',
			__( 'Choose ignore or delete.', 'rankmyseo' )
		);
	}

	/**
	 * Create a redirect from a 404 row and mark the row redirected.
	 *
	 * @param int    $id          Log ID.
	 * @param string $target      Destination URL.
	 * @param int    $status_code HTTP status, default 301.
	 * @return array{redirect: array<string, mixed>, warning: string, chain: list<string>, updated: bool, log: array<string, mixed>}
	 * @throws RedirectException When the log row or redirect service is missing, or validation fails.
	 */
	public function convert_to_redirect( int $id, string $target, int $status_code = 301 ): array {
		if ( null === $this->redirects ) {
			throw new RedirectException(
				'not_found_redirect',
				__( 'Redirects are not available right now.', 'rankmyseo' )
			);
		}

		$log = $this->store->find( $id );
		if ( null === $log ) {
			throw new RedirectException(
				'not_found_missing',
				__( 'That broken page is no longer in the log.', 'rankmyseo' )
			);
		}

		$created = $this->redirects->create(
			[
				'source'        => (string) $log['request_path'],
				'target'        => $target,
				'status_code'   => $status_code,
				'match_type'    => 'exact',
				'is_active'     => true,
				'source_origin' => 'manual',
			],
			true
		);

		$this->store->set_status( $id, 'redirected' );
		$updated = $this->store->find( $id );

		return [
			'redirect' => $created['redirect'],
			'warning'  => $created['warning'],
			'chain'    => $created['chain'],
			'updated'  => $created['updated'],
			'log'      => $updated ?? $log,
		];
	}

	/**
	 * Cron entry point. Discards the prune count so the action callback stays void.
	 *
	 * @return void
	 */
	public function prune_scheduled(): void {
		$this->prune();
	}

	/**
	 * Delete stale rows and enforce the maximum row count.
	 *
	 * @return int Rows removed.
	 */
	public function prune(): int {
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $this->settings->ttl_days() * 86400 ) );
		$removed  = $this->store->delete_older_than( $cutoff );
		$removed += $this->store->trim_to( $this->settings->max_rows() );
		return $removed;
	}

	/**
	 * Match an ignore pattern as a literal, a glob, or a guarded regex.
	 *
	 * @param string $haystack Lowercase path or user agent.
	 * @param string $pattern  Stored pattern.
	 * @return bool
	 */
	private function pattern_matches( string $haystack, string $pattern ): bool {
		$pattern = trim( $pattern );
		if ( '' === $pattern ) {
			return false;
		}

		if ( str_starts_with( strtolower( $pattern ), 'regex:' ) ) {
			$expression = trim( substr( $pattern, 6 ) );
			return $this->regex->is_safe( $expression ) && $this->regex->matches( $expression, $haystack );
		}

		$quoted = preg_quote( $pattern, '#' );
		$quoted = str_replace( '\*', '.*', $quoted );
		return 1 === preg_match( '#' . $quoted . '#i', $haystack );
	}

	/**
	 * Limit a string to a column width.
	 *
	 * @param string $value Raw value.
	 * @param int    $max   Maximum bytes.
	 * @return string
	 */
	private function clip( string $value, int $max ): string {
		$value = str_replace( "\0", '', $value );
		if ( strlen( $value ) <= $max ) {
			return $value;
		}

		return substr( $value, 0, $max );
	}
}
