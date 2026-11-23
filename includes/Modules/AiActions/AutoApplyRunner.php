<?php
/**
 * Applies allowed pending actions when the policy is on.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

/**
 * Cron runner for opt-in auto-apply. content_patch is never included.
 */
final class AutoApplyRunner {
	/**
	 * Action store.
	 *
	 * @var ActionRepository
	 */
	private ActionRepository $actions;

	/**
	 * Executor.
	 *
	 * @var ActionExecutor
	 */
	private ActionExecutor $executor;

	/**
	 * Constructor.
	 *
	 * @param ActionRepository $actions  Action store.
	 * @param ActionExecutor   $executor Executor.
	 */
	public function __construct( ActionRepository $actions, ActionExecutor $executor ) {
		$this->actions  = $actions;
		$this->executor = $executor;
	}

	/**
	 * Apply every pending action the policy allows.
	 *
	 * @return int Number applied.
	 */
	public function run(): int {
		$applied = 0;
		foreach ( $this->actions->list_actions( 'pending' ) as $action ) {
			if ( ActionCatalog::is_approval_only( (string) $action['action_type'] ) ) {
				continue;
			}
			$result = $this->executor->apply( (int) $action['id'], 'auto' );
			if ( ! empty( $result['ok'] ) ) {
				++$applied;
			}
		}
		return $applied;
	}
}
