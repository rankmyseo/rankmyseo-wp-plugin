<?php
/**
 * Queues title, description, and image-description suggestions.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\TokenVault;
use RankMySEO\Modules\Meta\MissingMetadataQuery;

/**
 * Asks RankMySEO for suggestions and stores them on the approval queue.
 *
 * Nothing on the page is written here. Each queued row keeps a before and
 * after value, a locally computed risk, and a base hash so approval can
 * snapshot and roll the change back.
 */
final class MissingMetadataQueue {
	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

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
	 * Executor, used only to fingerprint the current page.
	 *
	 * @var ActionExecutor
	 */
	private ActionExecutor $executor;

	/**
	 * Missing-metadata reader.
	 *
	 * @var MissingMetadataQuery
	 */
	private MissingMetadataQuery $query;

	/**
	 * Constructor.
	 *
	 * @param TokenVault           $vault    Credential vault.
	 * @param CloudClient          $client   Cloud client.
	 * @param ActionRepository     $actions  Action store.
	 * @param ActionExecutor       $executor Executor.
	 * @param MissingMetadataQuery $query    Row reader.
	 */
	public function __construct(
		TokenVault $vault,
		CloudClient $client,
		ActionRepository $actions,
		ActionExecutor $executor,
		MissingMetadataQuery $query
	) {
		$this->vault    = $vault;
		$this->client   = $client;
		$this->actions  = $actions;
		$this->executor = $executor;
		$this->query    = $query;
	}

	/**
	 * Queue suggestions for the selected posts. Disconnected sites are refused
	 * before any HTTP call.
	 *
	 * @param array<int, int> $ids Post IDs.
	 * @return array<string, mixed>
	 */
	public function queue( array $ids ): array {
		if ( ! $this->vault->is_connected() ) {
			return [
				'ok'      => false,
				'status'  => 409,
				'message' => __( 'Connect RankMySEO before drafting suggestions.', 'rankmyseo' ),
			];
		}

		$posts = [];
		foreach ( $this->unique_ids( $ids ) as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$row = $this->query->find( $id );
			if ( null === $row ) {
				continue;
			}
			$fields = array_values(
				array_intersect(
					is_array( $row['missing'] ) ? $row['missing'] : [],
					[ 'title', 'description', 'image_alt' ]
				)
			);
			if ( [] === $fields ) {
				continue;
			}
			$posts[] = [
				'wpPostId' => $id,
				'url'      => (string) $row['permalink'],
				'postType' => (string) $row['post_type'],
				'title'    => (string) $row['title'],
				'fields'   => $fields,
			];
		}

		if ( [] === $posts ) {
			return [
				'ok'      => false,
				'status'  => 400,
				'message' => __( 'Choose pages that are missing a title, description, or image description.', 'rankmyseo' ),
			];
		}

		$result = $this->client->generate_actions(
			[
				'source' => 'missing_metadata',
				'posts'  => $posts,
			]
		);
		if ( ! $result->ok ) {
			return [
				'ok'      => false,
				'status'  => $result->status > 0 ? $result->status : 502,
				'message' => '' !== $result->message ? $result->message : __( 'RankMySEO could not draft suggestions right now.', 'rankmyseo' ),
			];
		}

		$allowed = [];
		foreach ( $posts as $post ) {
			$allowed[] = (int) $post['wpPostId'];
		}
		$queued = $this->import( $result->data, $allowed );
		if ( [] === $queued ) {
			return [
				'ok'      => true,
				'queued'  => 0,
				'actions' => [],
				'message' => __( 'RankMySEO did not suggest a title, description, or image description for the selected pages.', 'rankmyseo' ),
			];
		}

		return [
			'ok'      => true,
			'queued'  => count( $queued ),
			'actions' => $queued,
			'message' => sprintf(
				/* translators: %d: number of suggestions waiting for approval. */
				_n( '%d suggestion is waiting for approval. Nothing was changed yet.', '%d suggestions are waiting for approval. Nothing was changed yet.', count( $queued ), 'rankmyseo' ),
				count( $queued )
			),
		];
	}

	/**
	 * Store cloud actions and suggestion rows as pending local actions.
	 *
	 * @param array<string, mixed> $data    Cloud body.
	 * @param array<int, int>      $allowed Post IDs the editor selected.
	 * @return list<array<string, mixed>>
	 */
	private function import( array $data, array $allowed ): array {
		$stored = [];
		$seen   = [];
		$rows   = isset( $data['actions'] ) && is_array( $data['actions'] ) ? $data['actions'] : [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$action = $this->store_action( $row, $allowed );
			if ( null === $action ) {
				continue;
			}
			$key = (string) $action['action_type'] . ':' . (string) $action['wp_post_id'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$stored[]     = $action;
		}

		$suggestions = isset( $data['suggestions'] ) && is_array( $data['suggestions'] ) ? $data['suggestions'] : [];
		foreach ( $suggestions as $suggestion ) {
			if ( ! is_array( $suggestion ) ) {
				continue;
			}
			foreach ( $this->actions_from_suggestion( $suggestion ) as $row ) {
				$action = $this->store_action( $row, $allowed );
				if ( null === $action ) {
					continue;
				}
				$key = (string) $action['action_type'] . ':' . (string) $action['wp_post_id'];
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$stored[]     = $action;
			}
		}

		return $stored;
	}

	/**
	 * Insert one pending action when the type and post are allowed.
	 *
	 * @param array<string, mixed> $row     Cloud action.
	 * @param array<int, int>      $allowed Selected post IDs.
	 * @return array<string, mixed>|null
	 */
	private function store_action( array $row, array $allowed ): ?array {
		$type = $this->type_name( isset( $row['actionType'] ) ? (string) $row['actionType'] : '' );
		if ( '' === $type ) {
			return null;
		}
		$post_id = isset( $row['wpPostId'] ) ? (int) $row['wpPostId'] : 0;
		if ( $post_id <= 0 || ! in_array( $post_id, $allowed, true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}
		$local = $this->query->find( $post_id );
		if ( null === $local ) {
			return null;
		}

		$after  = isset( $row['after'] ) && is_array( $row['after'] ) ? $row['after'] : [];
		$before = isset( $row['before'] ) && is_array( $row['before'] ) ? $row['before'] : [];
		$after  = $this->normalize_after( $type, $after, $local );
		$text   = PayloadSanitizer::preview( $type, $after );
		if ( '' === $text ) {
			return null;
		}
		$before = $this->normalize_before( $type, $before, $local );
		if ( PayloadSanitizer::preview( $type, $before ) === $text ) {
			return null;
		}

		$id    = isset( $row['id'] ) && is_string( $row['id'] ) ? $row['id'] : '';
		$saved = $this->actions->upsert_cloud(
			[
				'id'         => $id,
				'actionType' => $type,
				'wpPostId'   => $post_id,
				'targetUrl'  => (string) ( $row['targetUrl'] ?? $local['permalink'] ),
				'source'     => 'missing_metadata',
				'rationale'  => $this->rationale( $row ),
				'baseHash'   => $this->executor->base_hash( $post_id ),
				'before'     => $before,
				'after'      => $after,
				'riskTier'   => 'low',
			]
		);
		if ( $saved <= 0 ) {
			return null;
		}
		$fresh = $this->actions->find( $saved );
		if ( null === $fresh || 'pending' !== (string) $fresh['status'] ) {
			return null;
		}
		return $this->actions->present( $fresh );
	}

	/**
	 * Turn one suggestion object into action payloads.
	 *
	 * @param array<string, mixed> $suggestion Suggestion.
	 * @return list<array<string, mixed>>
	 */
	private function actions_from_suggestion( array $suggestion ): array {
		$post_id = isset( $suggestion['wpPostId'] ) ? (int) $suggestion['wpPostId'] : 0;
		$rows    = [];
		$map     = [
			ActionCatalog::UPDATE_TITLE            => [ 'title', 'seoTitle' ],
			ActionCatalog::UPDATE_META_DESCRIPTION => [ 'description', 'seoDescription', 'metaDescription' ],
			ActionCatalog::IMAGE_ALT               => [ 'imageAlt', 'alt' ],
		];
		foreach ( $map as $type => $keys ) {
			$text = PayloadSanitizer::text_field( $suggestion, $keys );
			if ( '' === $text ) {
				continue;
			}
			$after = ActionCatalog::IMAGE_ALT === $type
				? [ 'imageAlt' => $text ]
				: ( ActionCatalog::UPDATE_TITLE === $type ? [ 'seoTitle' => $text ] : [ 'seoDescription' => $text ] );
			if ( isset( $suggestion['attachmentId'] ) ) {
				$after['attachmentId'] = (int) $suggestion['attachmentId'];
			}
			$rows[] = [
				'actionType' => $type,
				'wpPostId'   => $post_id,
				'rationale'  => isset( $suggestion['rationale'] ) ? (string) $suggestion['rationale'] : '',
				'after'      => $after,
			];
		}
		return $rows;
	}

	/**
	 * Map a cloud type onto a queue type this screen accepts.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	private function type_name( string $type ): string {
		$type = sanitize_key( str_replace( '-', '_', $type ) );
		$map  = [
			'image_alt'               => ActionCatalog::IMAGE_ALT,
			'update_image_alt'        => ActionCatalog::IMAGE_ALT,
			'update_title'            => ActionCatalog::UPDATE_TITLE,
			'meta_title'              => ActionCatalog::UPDATE_TITLE,
			'update_meta_description' => ActionCatalog::UPDATE_META_DESCRIPTION,
			'meta_description'        => ActionCatalog::UPDATE_META_DESCRIPTION,
		];
		return $map[ $type ] ?? '';
	}

	/**
	 * Make sure the proposed object uses the keys the executor writes.
	 *
	 * @param string               $type  Canonical type.
	 * @param array<string, mixed> $after Proposed payload.
	 * @param array<string, mixed> $local Local row.
	 * @return array<string, mixed>
	 */
	private function normalize_after( string $type, array $after, array $local ): array {
		if ( ActionCatalog::UPDATE_TITLE === $type && ! $this->has_text( $after, [ 'seoTitle', 'title', 'suggested' ] ) ) {
			return $after;
		}
		if ( ActionCatalog::IMAGE_ALT === $type ) {
			if ( ! isset( $after['attachmentId'] ) && ! isset( $after['attachment_id'] ) && ! empty( $local['image_id'] ) ) {
				$after['attachmentId'] = (int) $local['image_id'];
			}
		}
		return $after;
	}

	/**
	 * Fill the previous value from the page when the cloud omitted it.
	 *
	 * @param string               $type   Canonical type.
	 * @param array<string, mixed> $before Before payload.
	 * @param array<string, mixed> $local  Local row.
	 * @return array<string, mixed>
	 */
	private function normalize_before( string $type, array $before, array $local ): array {
		if ( ActionCatalog::UPDATE_TITLE === $type && ! $this->has_text( $before, [ 'seoTitle', 'title', 'suggested' ] ) ) {
			$before['seoTitle'] = (string) $local['seo_title'];
		}
		if ( ActionCatalog::UPDATE_META_DESCRIPTION === $type && ! $this->has_text( $before, [ 'seoDescription', 'description', 'metaDescription', 'suggested' ] ) ) {
			$before['seoDescription'] = (string) $local['description'];
		}
		if ( ActionCatalog::IMAGE_ALT === $type && ! $this->has_text( $before, [ 'imageAlt', 'alt', 'suggested' ] ) ) {
			$before['imageAlt'] = (string) $local['image_alt'];
		}
		return $before;
	}

	/**
	 * Whether any key has text.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param array<int, string>   $keys    Keys.
	 * @return bool
	 */
	private function has_text( array $payload, array $keys ): bool {
		return '' !== PayloadSanitizer::text_field( $payload, $keys );
	}

	/**
	 * Plain rationale, with a calm fallback.
	 *
	 * @param array<string, mixed> $row Cloud action.
	 * @return string
	 */
	private function rationale( array $row ): string {
		$text = isset( $row['rationale'] ) && is_string( $row['rationale'] ) ? sanitize_text_field( $row['rationale'] ) : '';
		if ( '' !== $text ) {
			return $text;
		}
		return __( 'Suggested for this page. Review the difference before approving it.', 'rankmyseo' );
	}

	/**
	 * Positive unique IDs, capped.
	 *
	 * @param array<int, int> $ids Raw IDs.
	 * @return array<int, int>
	 */
	private function unique_ids( array $ids ): array {
		$clean = [];
		foreach ( $ids as $id ) {
			$number = (int) $id;
			if ( $number > 0 && ! in_array( $number, $clean, true ) ) {
				$clean[] = $number;
			}
			if ( count( $clean ) >= 100 ) {
				break;
			}
		}
		return $clean;
	}
}
