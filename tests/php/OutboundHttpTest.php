<?php
/**
 * Guards the plugin against unexpected outbound HTTP.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;

final class OutboundHttpTest extends TestCase {
	public function test_outbound_http_exists_only_in_cloud_and_indexnow_transports(): void {
		$root    = dirname( __DIR__, 2 );
		$allowed = [
			$root . '/includes/Modules/Cloud/CloudClient.php',
			$root . '/includes/Modules/Sitemaps/WpIndexNowTransport.php',
		];

		$matches = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root . '/includes', \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof \SplFileInfo || 'php' !== $file->getExtension() ) {
				continue;
			}

			$contents = (string) file_get_contents( $file->getPathname() );
			if ( preg_match( '/wp_remote_|wp_safe_remote_|curl_exec|curl_init/', $contents ) ) {
				$matches[] = $file->getPathname();
			}
		}

		sort( $allowed );
		sort( $matches );

		$this->assertSame( $allowed, $matches );
	}
}
