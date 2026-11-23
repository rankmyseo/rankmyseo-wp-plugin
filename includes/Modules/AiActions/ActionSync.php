<?php
/**
 * Pulls pending actions from RankMySEO Cloud.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\TokenVault;

/**
 * Syncs GET /wp/v1/actions and optional POST /wp/v1/actions/generate.
 */
final class ActionSync {
	/**
	 * Cloud client.
	 *
	 * @var CloudClient
	 */
	private CloudClient $client;

	/**
	 * Action store.
	 *
	 * @var ActionRepository
	 */
	private ActionRepository $actions;

	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * Constructor.
	 *
	 * @param CloudClient      $client  Cloud client.
	 * @param ActionRepository $actions Action store.
	 * @param TokenVault       $vault   Credential vault.
	 */
	public function __construct( CloudClient $client, ActionRepository $actions, TokenVault $vault ) {
		$this->client  = $client;
		$this->actions = $actions;
		$this->vault   = $vault;
	}

	/**
	 * Pull pending actions. Optionally ask RankMySEO to project new ones first.
	 *
	 * @param bool                 $generate Whether to call actions/generate first.
	 * @param array<string, mixed> $body     Generate body.
	 * @return array<string, mixed>
	 */
	public function pull( bool $generate = false, array $body = array() ): array {
		if ( ! $this->vault->is_connected() ) {
			return array(
				'ok'       => false,
				'status'   => 409,
				'message'  => __( 'Connect RankMySEO before loading suggestions.', 'rankmyseo' ),
				'imported' => 0,
				'actions'  => $this->present_all(),
			);
		}

		if ( $generate ) {
			$generated = $this->client->generate_actions( $this->generate_body( $body ) );
			if ( ! $generated->ok && 'disconnected' === $generated->error ) {
				return array(
					'ok'       => false,
					'status'   => 409,
					'message'  => $generated->message,
					'imported' => 0,
					'actions'  => $this->present_all(),
				);
			}
		}

		$listed = $this->client->list_actions( array( 'status' => 'pending' ) );
		if ( ! $listed->ok ) {
			return array(
				'ok'       => false,
				'status'   => $listed->status > 0 ? $listed->status : 502,
				'message'  => '' !== $listed->message ? $listed->message : __( 'Suggestions could not be loaded.', 'rankmyseo' ),
				'imported' => 0,
				'actions'  => $this->present_all(),
			);
		}

		$imported = 0;
		$rows     = isset( $listed->data['actions'] ) && is_array( $listed->data['actions'] ) ? $listed->data['actions'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( $this->actions->upsert_cloud( $row ) > 0 ) {
				++$imported;
			}
		}

		return array(
			'ok'       => true,
			'status'   => 200,
			'message'  => __( 'Suggestions are up to date.', 'rankmyseo' ),
			'imported' => $imported,
			'actions'  => $this->present_all(),
		);
	}

	/**
	 * Store one cloud action object from a webhook or a chat card.
	 *
	 * @param array<string, mixed> $cloud Cloud action.
	 * @return int
	 */
	public function ingest( array $cloud ): int {
		return $this->actions->upsert_cloud( $cloud );
	}

	/**
	 * Present every local action.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function present_all(): array {
		$items = array();
		foreach ( $this->actions->list_actions() as $action ) {
			$items[] = $this->actions->present( $action );
		}
		return $items;
	}

	/**
	 * Body accepted by POST /wp/v1/actions/generate.
	 *
	 * @param array<string, mixed> $body Request body.
	 * @return array<string, mixed>
	 */
	private function generate_body( array $body ): array {
		$clean = array();
		if ( isset( $body['wpPostId'] ) && is_numeric( $body['wpPostId'] ) && (int) $body['wpPostId'] > 0 ) {
			$clean['wpPostId'] = (int) $body['wpPostId'];
		}
		if ( isset( $body['url'] ) && is_string( $body['url'] ) && '' !== $body['url'] ) {
			$clean['url'] = $body['url'];
		}
		if ( isset( $body['sources'] ) && is_array( $body['sources'] ) ) {
			$sources = array();
			foreach ( $body['sources'] as $source ) {
				if ( is_string( $source ) && in_array( $source, array( 'page_edit', 'operator_chat', 'site_audit', 'article' ), true ) ) {
					$sources[] = $source;
				}
			}
			if ( array() !== $sources ) {
				$clean['sources'] = $sources;
			}
		}
		return $clean;
	}
}
