<?php
/**
 * Redirects manager and 404 monitor module.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

use RankMySEO\Modules\AbstractModule;
use RankMySEO\Rest\NotFoundController;
use RankMySEO\Rest\RedirectsController;

/**
 * Module 5: O(1) redirects, safe regex, slug protection, and a 404 monitor.
 */
final class RedirectsModule extends AbstractModule {
	/**
	 * Cron hook that prunes the 404 log. Also cleared on deactivation.
	 */
	public const CRON_HOOK = 'rankmyseo_prune_404_logs';

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'redirects';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Redirects & 404s', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( '301/302/307/410/451 redirects with O(1) hash lookup, regex safety guards, slug-change protection, and deduplicated 404 logging.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Spot broken links and send visitors to the right page automatically when a URL changes.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_ready(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			return;
		}

		$settings  = new RedirectSettings();
		$paths     = new PathNormalizer( $settings );
		$regex     = new RegexGuard();
		$redirects = new RedirectService(
			new WpdbRedirectStore( $wpdb ),
			$paths,
			$regex,
			new LoopGuard(),
			new CsvCodec(),
			new ServerRulesExporter(),
			new RedirectCache(),
			$settings
		);
		$monitor   = new Monitor404( new WpdbNotFoundStore( $wpdb ), $paths, $regex, $settings, $redirects );
		$frontend  = new FrontendInterceptor( $redirects, $monitor, $paths );
		$slugs     = new SlugWatcher( $redirects, $settings, $paths );

		add_action( 'template_redirect', [ $frontend, 'handle' ], 0 );
		add_action( 'post_updated', [ $slugs, 'on_post_updated' ], 10, 3 );
		add_action( self::CRON_HOOK, [ $monitor, 'prune_scheduled' ] );
		add_action( 'init', [ $this, 'ensure_cron' ] );

		$redirect_controller = new RedirectsController( $redirects, $settings );
		$log_controller      = new NotFoundController( $monitor );
		add_action( 'rest_api_init', [ $redirect_controller, 'register_routes' ] );
		add_action( 'rest_api_init', [ $log_controller, 'register_routes' ] );
	}

	/**
	 * Schedule the daily 404 prune when it is not already queued.
	 *
	 * @return void
	 */
	public function ensure_cron(): void {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		$delay = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
		wp_schedule_event( time() + $delay, 'daily', self::CRON_HOOK );
	}
}
