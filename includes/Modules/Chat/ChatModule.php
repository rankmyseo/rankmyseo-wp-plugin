<?php
/**
 * Local chat module.
 *
 * Registers the extension point for a later connected Operator chat.
 * The offline inspector itself is wired through ChatController and does not
 * depend on this module being enabled in the shared settings map.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Chat;

use RankMySEO\Modules\AbstractModule;

/**
 * Keeps a stable filter slot for connected chat without outbound HTTP.
 */
final class ChatModule extends AbstractModule {
	/**
	 * Whether the filter has already been registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'chat';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Local SEO Assistant', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Deterministic on-site SEO inspector for titles, descriptions, scores, thin content, 404s, and redirects. Connected chat is added later.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Ask what to fix on this site. Answers come from WordPress itself until you choose to connect RankMySEO Cloud.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Not listed in the shared module toggles, so this is not user-disableable
	 * from Settings and does not collide with sibling module options.
	 */
	public function is_enabled_by_default(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;
		add_filter( 'rankmyseo_chat_local_response', [ $this, 'ensure_operator_slot' ], 20, 2 );
	}

	/**
	 * Guarantee the operator slot exists for a later PR to fill in.
	 *
	 * Offline replies stay local. This filter does not call the network.
	 *
	 * @param array<string, mixed> $response Local reply.
	 * @param string               $message  Original question.
	 * @return array<string, mixed>
	 */
	public function ensure_operator_slot( array $response, string $message ): array {
		unset( $message );

		if ( ! isset( $response['operator_slot'] ) || ! is_array( $response['operator_slot'] ) ) {
			$response['operator_slot'] = [
				'enabled' => false,
				'mode'    => 'local',
			];
		}

		if ( ! isset( $response['action_cards'] ) || ! is_array( $response['action_cards'] ) ) {
			$response['action_cards'] = [];
		}

		return $response;
	}
}
