<?php
/**
 * Virtual robots.txt rules, physical-file detection, and crawl warnings.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Builds the virtual robots.txt body and explains when it will not be used.
 */
final class RobotsTxt {
	/**
	 * Settings repository.
	 *
	 * @var SitemapSettings
	 */
	private SitemapSettings $settings;

	/**
	 * Site root used to detect a physical robots.txt. Defaults to ABSPATH.
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
	 * @param string          $root        Directory that would contain a physical robots.txt.
	 * @param callable|null   $file_exists Optional exists check receiving the absolute path.
	 */
	public function __construct( SitemapSettings $settings, string $root = '', ?callable $file_exists = null ) {
		$this->settings    = $settings;
		$this->root        = '' !== $root ? $root : ( defined( 'ABSPATH' ) ? ABSPATH : '' );
		$this->file_exists = $file_exists;
	}

	/**
	 * Absolute path of the physical robots.txt WordPress would defer to.
	 *
	 * @return string
	 */
	public function physical_path(): string {
		return rtrim( $this->root, '/\\' ) . '/robots.txt';
	}

	/**
	 * Whether a physical robots.txt file is present.
	 *
	 * WordPress serves that file directly and does not run the robots_txt filter.
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
	 * Whether rules contain a site-wide Disallow: / directive.
	 *
	 * @param string $rules Robots.txt body.
	 * @return bool
	 */
	public function blocks_entire_site( string $rules ): bool {
		return 1 === preg_match( '/^\s*disallow\s*:\s*\/\s*(?:#.*)?$/mi', $rules );
	}

	/**
	 * Render the virtual robots.txt body WordPress should output.
	 *
	 * @return string
	 */
	public function render(): string {
		if ( ! $this->settings->allow_crawl() ) {
			return $this->append_sitemap( "User-agent: *\nDisallow: /\n" );
		}

		if ( 'custom' === $this->settings->robots_mode() ) {
			$custom = trim( $this->settings->robots_custom() );
			if ( '' === $custom ) {
				$custom = $this->preset_rules( 'default' );
			}
			return $this->append_sitemap( $custom );
		}

		return $this->append_sitemap( $this->preset_rules( $this->settings->robots_preset() ) );
	}

	/**
	 * Preview custom rules the same way they would be served, plus warnings.
	 *
	 * @param string $rules Raw editor contents.
	 * @return array{content: string, blocks_entire_site: bool, warnings: list<array{code: string, message: string}>}
	 */
	public function preview_custom( string $rules ): array {
		$content = $this->append_sitemap( trim( $rules ) );
		return [
			'content'            => $content,
			'blocks_entire_site' => $this->blocks_entire_site( $content ),
			'warnings'           => $this->warnings_for( $content ),
		];
	}

	/**
	 * Warnings for the rules that would actually be served.
	 *
	 * @return list<array{code: string, message: string}>
	 */
	public function warnings(): array {
		return $this->warnings_for( $this->render() );
	}

	/**
	 * Filter callback for WordPress' virtual robots.txt.
	 *
	 * Keeps WordPress' site-wide block when the site is discouraged from indexing,
	 * and still appends the Sitemap line.
	 *
	 * @param string $output      Existing virtual robots.txt.
	 * @param mixed  $blog_public Blog public flag passed by WordPress.
	 * @return string
	 */
	public function filter_robots_txt( string $output, $blog_public ): string {
		unset( $output );
		if ( '0' === (string) $blog_public ) {
			$body = $this->append_sitemap( "User-agent: *\nDisallow: /\n" );
		} else {
			$body = $this->render();
		}

		/**
		 * Filter the virtual robots.txt body RankMySEO is about to return.
		 *
		 * @param string $body        Robots.txt body.
		 * @param mixed  $blog_public Blog public flag.
		 */
		$filtered = apply_filters( 'rankmyseo_robots_txt', $body, $blog_public );
		return is_string( $filtered ) ? $filtered : $body;
	}

	/**
	 * Safe preset bodies. Neither preset blocks CSS, JavaScript, or uploads.
	 *
	 * @param string $preset Preset slug.
	 * @return string
	 */
	private function preset_rules( string $preset ): string {
		if ( 'open' === $preset ) {
			return "User-agent: *\nAllow: /\n";
		}

		return "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
	}

	/**
	 * Append a single Sitemap line when the body does not already contain one.
	 *
	 * @param string $rules Rule body.
	 * @return string
	 */
	private function append_sitemap( string $rules ): string {
		$rules = rtrim( $rules );
		if ( 1 === preg_match( '/^\s*sitemap\s*:/mi', $rules ) ) {
			return $rules . "\n";
		}

		if ( '' === $rules ) {
			$rules = "User-agent: *\nAllow: /";
		}

		return $rules . "\n\nSitemap: " . SitemapLinks::index_url() . "\n";
	}

	/**
	 * Build warning rows for a rendered body.
	 *
	 * @param string $content Rendered robots.txt.
	 * @return list<array{code: string, message: string}>
	 */
	private function warnings_for( string $content ): array {
		$warnings = [];

		if ( $this->has_physical_file() ) {
			$warnings[] = [
				'code'    => 'physical_file',
				'message' => sprintf(
					/* translators: %s: absolute path of the physical robots.txt file. */
					__( 'A physical robots.txt file is present at %s. WordPress will serve that file, so these virtual rules will not run. Remove or rename the file to use the rules below.', 'rankmyseo' ),
					$this->physical_path()
				),
			];
		}

		if ( $this->blocks_entire_site( $content ) ) {
			$warnings[] = [
				'code'    => 'disallow_all',
				'message' => __( 'These rules include Disallow: /, which blocks crawlers from the entire site.', 'rankmyseo' ),
			];
		}

		return $warnings;
	}
}
