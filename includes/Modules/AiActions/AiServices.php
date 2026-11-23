<?php
/**
 * Registers AI approval services in the plugin container.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Container;
use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\TokenVault;
use RankMySEO\Rest\AiActionsController;

/**
 * Wires the queue, executor, chat bridge, and REST controller.
 */
final class AiServices {
	/**
	 * Register services. Safe to call once during plugin construction.
	 *
	 * @param Container $container Service container.
	 * @return void
	 */
	public static function register( Container $container ): void {
		$container->singleton(
			ActionRepository::class,
			static function (): ActionRepository {
				global $wpdb;
				return new ActionRepository( $wpdb );
			}
		);

		$container->singleton(
			SnapshotRepository::class,
			static function (): SnapshotRepository {
				global $wpdb;
				return new SnapshotRepository( $wpdb );
			}
		);

		$container->singleton(
			AuditLog::class,
			static function (): AuditLog {
				global $wpdb;
				return new AuditLog( $wpdb );
			}
		);

		$container->singleton(
			RedirectWriter::class,
			static function (): RedirectWriter {
				global $wpdb;
				return new RedirectWriter( $wpdb );
			}
		);

		$container->singleton(
			PolicyStore::class,
			static function (): PolicyStore {
				return new PolicyStore();
			}
		);

		$container->singleton(
			WebhookVerifier::class,
			static function (): WebhookVerifier {
				return new WebhookVerifier();
			}
		);

		$container->singleton(
			ActionSync::class,
			static function ( Container $services ): ActionSync {
				$client = $services->get( CloudClient::class );
				assert( $client instanceof CloudClient );
				$actions = $services->get( ActionRepository::class );
				assert( $actions instanceof ActionRepository );
				$vault = $services->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				return new ActionSync( $client, $actions, $vault );
			}
		);

		$container->singleton(
			ActionExecutor::class,
			static function ( Container $services ): ActionExecutor {
				$actions = $services->get( ActionRepository::class );
				assert( $actions instanceof ActionRepository );
				$snapshots = $services->get( SnapshotRepository::class );
				assert( $snapshots instanceof SnapshotRepository );
				$audit = $services->get( AuditLog::class );
				assert( $audit instanceof AuditLog );
				$redirects = $services->get( RedirectWriter::class );
				assert( $redirects instanceof RedirectWriter );
				$policy = $services->get( PolicyStore::class );
				assert( $policy instanceof PolicyStore );
				$client = $services->get( CloudClient::class );
				assert( $client instanceof CloudClient );
				$vault = $services->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				return new ActionExecutor( $actions, $snapshots, $audit, $redirects, $policy, $client, $vault );
			}
		);

		$container->singleton(
			ChatBridge::class,
			static function ( Container $services ): ChatBridge {
				$client = $services->get( CloudClient::class );
				assert( $client instanceof CloudClient );
				$vault = $services->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				$sync = $services->get( ActionSync::class );
				assert( $sync instanceof ActionSync );
				$actions = $services->get( ActionRepository::class );
				assert( $actions instanceof ActionRepository );
				return new ChatBridge( $client, $vault, $sync, $actions );
			}
		);

		$container->singleton(
			AutoApplyRunner::class,
			static function ( Container $services ): AutoApplyRunner {
				$actions = $services->get( ActionRepository::class );
				assert( $actions instanceof ActionRepository );
				$executor = $services->get( ActionExecutor::class );
				assert( $executor instanceof ActionExecutor );
				return new AutoApplyRunner( $actions, $executor );
			}
		);

		$container->singleton(
			AiActionsController::class,
			static function ( Container $services ): AiActionsController {
				$actions = $services->get( ActionRepository::class );
				assert( $actions instanceof ActionRepository );
				$executor = $services->get( ActionExecutor::class );
				assert( $executor instanceof ActionExecutor );
				$sync = $services->get( ActionSync::class );
				assert( $sync instanceof ActionSync );
				$policy = $services->get( PolicyStore::class );
				assert( $policy instanceof PolicyStore );
				$audit = $services->get( AuditLog::class );
				assert( $audit instanceof AuditLog );
				$chat = $services->get( ChatBridge::class );
				assert( $chat instanceof ChatBridge );
				$webhooks = $services->get( WebhookVerifier::class );
				assert( $webhooks instanceof WebhookVerifier );
				$vault = $services->get( TokenVault::class );
				assert( $vault instanceof TokenVault );
				$runner = $services->get( AutoApplyRunner::class );
				assert( $runner instanceof AutoApplyRunner );
				$client = $services->get( CloudClient::class );
				assert( $client instanceof CloudClient );
				return new AiActionsController( $actions, $executor, $sync, $policy, $audit, $chat, $webhooks, $vault, $runner, $client );
			}
		);
	}
}
