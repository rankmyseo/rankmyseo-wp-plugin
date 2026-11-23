<?php
/**
 * Registers RankMySEO Cloud services in the plugin container.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

use RankMySEO\Container;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\ModuleManager;

/**
 * Wires the vault, HTTP client, queue, and pairing service as singletons.
 */
final class CloudServices {
	/**
	 * Register cloud services. Safe to call once during plugin construction.
	 *
	 * @param Container $container Service container.
	 * @return void
	 */
	public static function register( Container $container ): void {
		$container->singleton(
			TokenVault::class,
			static function (): TokenVault {
				return new TokenVault();
			}
		);

		$container->singleton(
			CloudClient::class,
			static function ( Container $services ): CloudClient {
				$vault = $services->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				return new CloudClient( $vault );
			}
		);

		$container->singleton(
			SyncQueue::class,
			static function ( Container $services ): SyncQueue {
				global $wpdb;
				$vault = $services->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				return new SyncQueue( $wpdb, $vault );
			}
		);

		$container->singleton(
			SyncWorker::class,
			static function ( Container $services ): SyncWorker {
				$vault = $services->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				$client = $services->get( CloudClient::class );
				assert( $client instanceof CloudClient );
				$queue = $services->get( SyncQueue::class );
				assert( $queue instanceof SyncQueue );
				return new SyncWorker( $vault, $client, $queue );
			}
		);

		$container->singleton(
			InsightsCache::class,
			static function ( Container $services ): InsightsCache {
				$vault = $services->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				$client = $services->get( CloudClient::class );
				assert( $client instanceof CloudClient );
				return new InsightsCache( $vault, $client );
			}
		);

		$container->singleton(
			PairingService::class,
			static function ( Container $services ): PairingService {
				$vault = $services->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				$client = $services->get( CloudClient::class );
				assert( $client instanceof CloudClient );
				$worker = $services->get( SyncWorker::class );
				assert( $worker instanceof SyncWorker );
				$settings = $services->get( Settings::class );
				assert( $settings instanceof Settings );
				$modules = $services->get( ModuleManager::class );
				assert( $modules instanceof ModuleManager );
				return new PairingService( $vault, $client, $worker, $settings, $modules );
			}
		);
	}
}
