<?php
/**
 * Front-end redirect matcher and 404 logger.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Runs on template_redirect, skipping admin, AJAX, cron, REST, and XML-RPC.
 */
final class FrontendInterceptor {
	/**
	 * Redirect service.
	 *
	 * @var RedirectService
	 */
	private RedirectService $redirects;

	/**
	 * 404 monitor.
	 *
	 * @var Monitor404
	 */
	private Monitor404 $monitor;

	/**
	 * Path normalizer.
	 *
	 * @var PathNormalizer
	 */
	private PathNormalizer $paths;

	/**
	 * Last decision, exposed for tests when exit is suppressed.
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $last_dispatch = null;

	/**
	 * Constructor.
	 *
	 * @param RedirectService $redirects Redirect service.
	 * @param Monitor404      $monitor   404 monitor.
	 * @param PathNormalizer  $paths     Path normalizer.
	 */
	public function __construct( RedirectService $redirects, Monitor404 $monitor, PathNormalizer $paths ) {
		$this->redirects = $redirects;
		$this->monitor   = $monitor;
		$this->paths     = $paths;
	}

	/**
	 * Match the current request and redirect, or log a 404.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( $this->should_skip() ) {
			return;
		}

		$path  = $this->paths->current_request_path();
		$query = $this->paths->current_request_query();
		$rule  = $this->redirects->match( $path );

		if ( null === $rule ) {
			if ( ! function_exists( 'is_404' ) || is_404() ) {
				$this->monitor->record_current_request();
			}
			return;
		}

		$this->dispatch( $rule, $path, $query );
	}

	/**
	 * Send the HTTP response for a matched rule.
	 *
	 * @param array<string, mixed> $rule  Matched rule.
	 * @param string               $path  Request path.
	 * @param string               $query Query string.
	 * @return void
	 */
	public function dispatch( array $rule, string $path, string $query ): void {
		$status   = (int) ( $rule['status_code'] ?? 301 );
		$location = $this->redirects->build_location( $rule, $path, $query );
		$this->redirects->record_hit( (int) ( $rule['id'] ?? 0 ) );

		$this->last_dispatch = [
			'id'          => (int) ( $rule['id'] ?? 0 ),
			'status_code' => $status,
			'location'    => $location,
		];

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		if ( null !== $location && function_exists( 'wp_redirect' ) ) {
			// External destinations are allowed after validation, so wp_safe_redirect() would reject them.
			wp_redirect( $location, $status ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		} elseif ( function_exists( 'status_header' ) ) {
			status_header( $status );
		}

		$should_exit = apply_filters( 'rankmyseo_redirect_should_exit', true );
		if ( false === $should_exit ) {
			return;
		}

		if ( null === $location ) {
			echo $this->terminal_body( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message is escaped inside terminal_body().
		}

		exit;
	}

	/**
	 * Whether this request should not be intercepted.
	 *
	 * @return bool
	 */
	private function should_skip(): bool {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		return false;
	}

	/**
	 * Short body for 410 and 451 responses.
	 *
	 * @param int $status HTTP status.
	 * @return string
	 */
	private function terminal_body( int $status ): string {
		if ( 451 === $status ) {
			$message = __( 'Unavailable for legal reasons.', 'rankmyseo' );
		} else {
			$message = __( 'This page is gone.', 'rankmyseo' );
		}

		return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html( (string) $status ) . '</title></head><body><p>' . esc_html( $message ) . '</p></body></html>';
	}
}
