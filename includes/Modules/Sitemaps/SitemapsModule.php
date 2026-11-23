<?php
/**
 * XML sitemaps, IndexNow, and virtual robots.txt module.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

use RankMySEO\Modules\AbstractModule;

/**
 * Disables core sitemaps when this module is enabled and serves RankMySEO crawl routes.
 */
final class SitemapsModule extends AbstractModule {
	/**
	 * Cached document builder for this request.
	 *
	 * @var SitemapDocuments|null
	 */
	private ?SitemapDocuments $documents = null;

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'sitemaps';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Sitemaps & Crawl', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Paginated XML sitemaps, virtual IndexNow key and async pings, robots.txt editor, and crawl hygiene controls.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Keep an up-to-date map of your site for search engines and notify them when you publish new pages.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_ready(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Hooks are registered only when the sitemaps module is enabled, so core sitemaps stay intact when it is off.
	 */
	public function register(): void {
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_action( 'init', [ $this, 'register_rewrites' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'maybe_render' ], 0 );
		add_filter( 'robots_txt', [ $this->robots(), 'filter_robots_txt' ], 100, 2 );

		add_action( 'save_post', [ $this, 'invalidate_cache' ], 10, 1 );
		add_action( 'deleted_post', [ $this, 'invalidate_cache' ], 10, 1 );
		add_action( 'wp_trash_post', [ $this, 'invalidate_cache' ], 10, 1 );
		add_action( 'untrashed_post', [ $this, 'invalidate_cache' ], 10, 1 );
		add_action( 'created_term', [ $this, 'invalidate_terms' ], 10, 0 );
		add_action( 'edited_term', [ $this, 'invalidate_terms' ], 10, 0 );
		add_action( 'delete_term', [ $this, 'invalidate_terms' ], 10, 0 );

		$indexnow = $this->indexnow();
		add_action( IndexNow::CRON_HOOK, [ $indexnow, 'process_queue' ] );
		add_action( 'transition_post_status', [ $indexnow, 'on_transition' ], 10, 3 );
		add_action( 'post_updated', [ $indexnow, 'on_post_updated' ], 10, 3 );
		add_action( 'before_delete_post', [ $indexnow, 'on_delete' ], 10, 1 );
		add_action( 'update_option_' . \RankMySEO\Core\Settings::OPTION_KEY, [ $this, 'maybe_flush_rewrites' ], 10, 2 );

		( new CrawlControls( $this->sitemap_settings()->crawl() ) )->register();
		$this->register_cli();
	}

	/**
	 * Register public rewrite rules for the sitemap, stylesheet, and IndexNow key.
	 *
	 * @return void
	 */
	public function register_rewrites(): void {
		add_rewrite_rule( '^sitemap_index\\.xml$', 'index.php?rankmyseo_sitemap=index', 'top' );
		add_rewrite_rule( '^sitemap\\.xml$', 'index.php?rankmyseo_sitemap=redirect', 'top' );
		add_rewrite_rule(
			'^([a-z0-9_-]+)-sitemap([0-9]*)\\.xml$',
			'index.php?rankmyseo_sitemap=$matches[1]&rankmyseo_sitemap_page=$matches[2]',
			'top'
		);
		add_rewrite_rule( '^rankmyseo-sitemap\\.xsl$', 'index.php?rankmyseo_sitemap=xsl', 'top' );

		if ( $this->indexnow()->is_enabled() ) {
			add_rewrite_rule( '^([a-f0-9]{32})\\.txt$', 'index.php?rankmyseo_indexnow_key=$matches[1]', 'top' );
		}
	}

	/**
	 * Add sitemap query vars.
	 *
	 * @param array<int, string> $vars Existing public query vars.
	 * @return array<int, string>
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'rankmyseo_sitemap';
		$vars[] = 'rankmyseo_sitemap_page';
		$vars[] = 'rankmyseo_indexnow_key';
		return $vars;
	}

	/**
	 * Serve sitemap XML, the stylesheet, the IndexNow key, or the sitemap.xml redirect.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		$key = get_query_var( 'rankmyseo_indexnow_key' );
		if ( is_string( $key ) && '' !== $key ) {
			$body = $this->indexnow()->virtual_key_body( $key );
			if ( is_string( $body ) ) {
				$this->send( $body, 'text/plain; charset=UTF-8' );
			}
		}

		$name = get_query_var( 'rankmyseo_sitemap' );
		if ( ! is_string( $name ) || '' === $name ) {
			return;
		}

		if ( 'xsl' === $name ) {
			$this->send( $this->documents()->render_xsl(), 'text/xsl; charset=UTF-8' );
		}

		if ( 'redirect' === $name ) {
			$this->redirect( SitemapLinks::index_url() );
		}

		if ( 'index' === $name ) {
			$this->send( $this->documents()->render_index(), 'application/xml; charset=UTF-8' );
		}

		$page_raw = get_query_var( 'rankmyseo_sitemap_page' );
		$page     = is_numeric( $page_raw ) && (int) $page_raw > 1 ? (int) $page_raw : 1;
		$xml      = $this->documents()->render_child( $name, $page );
		if ( ! is_string( $xml ) ) {
			$this->send(
				( new XmlRenderer() )->render_urlset( [], SitemapLinks::xsl_url() ),
				'application/xml; charset=UTF-8',
				404
			);
			return;
		}

		$this->send( $xml, 'application/xml; charset=UTF-8' );
	}

	/**
	 * Drop cached sitemap XML after a post change.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function invalidate_cache( int $post_id = 0 ): void {
		if ( $post_id > 0 && function_exists( 'wp_is_post_autosave' ) && ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) ) {
			return;
		}

		$this->cache()->flush();
	}

	/**
	 * Drop cached sitemap XML after a term change.
	 *
	 * @return void
	 */
	public function invalidate_terms(): void {
		$this->cache()->flush();
	}

	/**
	 * Flush rewrites when the sitemaps module is toggled so core sitemaps can return.
	 *
	 * @param mixed $old_value     Previous option value.
	 * @param mixed $updated_value New option value.
	 * @return void
	 */
	public function maybe_flush_rewrites( $old_value, $updated_value ): void {
		$old_enabled = is_array( $old_value ) && ! empty( $old_value['modules']['sitemaps'] );
		$new_enabled = is_array( $updated_value ) && ! empty( $updated_value['modules']['sitemaps'] );
		if ( $old_enabled !== $new_enabled ) {
			update_option( 'rankmyseo_flush_rewrite_rules', 1, false );
		}
	}

	/**
	 * WP-CLI handler for `wp rankmyseo sitemap flush` and `ping-indexnow`.
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function handle_cli( array $args, array $assoc_args ): void {
		unset( $assoc_args );
		$subcommand = isset( $args[0] ) ? (string) $args[0] : 'flush';

		if ( 'ping-indexnow' === $subcommand ) {
			$this->indexnow()->process_queue();
			$this->cli_success( __( 'IndexNow queue processed.', 'rankmyseo' ) );
			return;
		}

		$this->cache()->flush();
		$this->cli_success( __( 'Sitemap cache flushed.', 'rankmyseo' ) );
	}

	/**
	 * Register the WP-CLI command when WP-CLI is running.
	 *
	 * @return void
	 */
	private function register_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! constant( 'WP_CLI' ) || ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		call_user_func( [ 'WP_CLI', 'add_command' ], 'rankmyseo sitemap', [ $this, 'handle_cli' ] );
	}

	/**
	 * Print a WP-CLI success line.
	 *
	 * @param string $message Success message.
	 * @return void
	 */
	private function cli_success( string $message ): void {
		if ( class_exists( 'WP_CLI' ) ) {
			call_user_func( [ 'WP_CLI', 'success' ], $message );
		}
	}

	/**
	 * Send a crawler response and stop the request.
	 *
	 * @param string $body         Response body.
	 * @param string $content_type Content-Type header value.
	 * @param int    $status       HTTP status code.
	 * @return void
	 */
	private function send( string $body, string $content_type, int $status = 200 ): void {
		if ( ! headers_sent() ) {
			if ( function_exists( 'status_header' ) ) {
				status_header( $status );
			} else {
				header( 'HTTP/1.1 ' . $status );
			}
			header( 'Content-Type: ' . $content_type );
			header( 'X-Robots-Tag: noindex, follow' );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is escaped in XmlRenderer. The IndexNow body is a 32-character hex key.
		echo $body;
		exit;
	}

	/**
	 * Redirect to the canonical sitemap index.
	 *
	 * @param string $location Absolute URL.
	 * @return void
	 */
	private function redirect( string $location ): void {
		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $location, 301 );
			exit;
		}

		header( 'Location: ' . $location, true, 301 );
		exit;
	}

	/**
	 * Sitemap settings service.
	 *
	 * @return SitemapSettings
	 */
	private function sitemap_settings(): SitemapSettings {
		if ( $this->container->has( SitemapSettings::class ) ) {
			$service = $this->container->get( SitemapSettings::class );
			if ( $service instanceof SitemapSettings ) {
				return $service;
			}
		}

		return new SitemapSettings();
	}

	/**
	 * IndexNow service.
	 *
	 * @return IndexNow
	 */
	private function indexnow(): IndexNow {
		if ( $this->container->has( IndexNow::class ) ) {
			$service = $this->container->get( IndexNow::class );
			if ( $service instanceof IndexNow ) {
				return $service;
			}
		}

		return new IndexNow( $this->sitemap_settings(), new WpIndexNowTransport() );
	}

	/**
	 * Robots.txt service.
	 *
	 * @return RobotsTxt
	 */
	private function robots(): RobotsTxt {
		if ( $this->container->has( RobotsTxt::class ) ) {
			$service = $this->container->get( RobotsTxt::class );
			if ( $service instanceof RobotsTxt ) {
				return $service;
			}
		}

		return new RobotsTxt( $this->sitemap_settings() );
	}

	/**
	 * Sitemap cache.
	 *
	 * @return SitemapCache
	 */
	private function cache(): SitemapCache {
		return new SitemapCache( $this->sitemap_settings() );
	}

	/**
	 * Document builder.
	 *
	 * @return SitemapDocuments
	 */
	private function documents(): SitemapDocuments {
		if ( $this->documents instanceof SitemapDocuments ) {
			return $this->documents;
		}

		$settings = $this->sitemap_settings();
		$source   = new WordPressSitemapSource( $settings );
		if ( $this->container->has( WordPressSitemapSource::class ) ) {
			$resolved = $this->container->get( WordPressSitemapSource::class );
			if ( $resolved instanceof WordPressSitemapSource ) {
				$source = $resolved;
			}
		}

		$this->documents = new SitemapDocuments( $source, $settings, new XmlRenderer(), $this->cache() );
		return $this->documents;
	}
}
