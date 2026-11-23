<?php
/**
 * Applies, rejects, and rolls back AI actions.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

use RankMySEO\Modules\Cloud\CloudClient;
use RankMySEO\Modules\Cloud\TokenVault;

/**
 * Captures a snapshot, changes WordPress, and can restore that snapshot.
 */
final class ActionExecutor {
	/**
	 * Action store.
	 *
	 * @var ActionRepository
	 */
	private ActionRepository $actions;

	/**
	 * Snapshot store.
	 *
	 * @var SnapshotRepository
	 */
	private SnapshotRepository $snapshots;

	/**
	 * Audit log.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Redirect writer.
	 *
	 * @var RedirectWriter
	 */
	private RedirectWriter $redirects;

	/**
	 * Auto-apply policy.
	 *
	 * @var PolicyStore
	 */
	private PolicyStore $policy;

	/**
	 * Cloud client.
	 *
	 * @var CloudClient
	 */
	private CloudClient $client;

	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * Constructor.
	 *
	 * @param ActionRepository   $actions   Action store.
	 * @param SnapshotRepository $snapshots Snapshot store.
	 * @param AuditLog           $audit     Audit log.
	 * @param RedirectWriter     $redirects Redirect writer.
	 * @param PolicyStore        $policy    Auto-apply policy.
	 * @param CloudClient        $client    Cloud client.
	 * @param TokenVault         $vault     Credential vault.
	 */
	public function __construct(
		ActionRepository $actions,
		SnapshotRepository $snapshots,
		AuditLog $audit,
		RedirectWriter $redirects,
		PolicyStore $policy,
		CloudClient $client,
		TokenVault $vault
	) {
		$this->actions   = $actions;
		$this->snapshots = $snapshots;
		$this->audit     = $audit;
		$this->redirects = $redirects;
		$this->policy    = $policy;
		$this->client    = $client;
		$this->vault     = $vault;
	}

	/**
	 * Apply one pending action.
	 *
	 * @param int                       $id      Local action ID.
	 * @param string                    $actor   human or auto.
	 * @param array<string, mixed>|null $edited  Optional replacement for the proposed object.
	 * @param bool                      $force   Apply even when the page changed since the suggestion.
	 * @return array<string, mixed>
	 */
	public function apply( int $id, string $actor, ?array $edited = null, bool $force = false ): array {
		$action = $this->actions->find( $id );
		if ( null === $action ) {
			return $this->fail_result( __( 'That suggestion is not on this site.', 'rankmyseo' ), 404 );
		}
		if ( ! in_array( (string) $action['status'], array( 'pending', 'stale' ), true ) ) {
			return $this->fail_result( __( 'That suggestion can no longer be applied.', 'rankmyseo' ), 409 );
		}
		if ( 'auto' === $actor && ActionCatalog::is_approval_only( (string) $action['action_type'] ) ) {
			return $this->fail_result( __( 'Page content changes stay on the approval queue.', 'rankmyseo' ), 409 );
		}

		$post_id   = isset( $action['wp_post_id'] ) ? (int) $action['wp_post_id'] : 0;
		$post_type = $this->post_type( $post_id, is_array( $action['after'] ) ? $action['after'] : array() );
		if ( 'auto' === $actor ) {
			$window = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
			$since  = gmdate( 'Y-m-d H:i:s', time() - $window );
			if ( ! $this->policy->allows( $action, $post_type, $this->audit->count_auto_since( $since ) ) ) {
				return $this->fail_result( __( 'Auto-apply is off for this change.', 'rankmyseo' ), 409 );
			}
		}

		if ( 'auto' !== $actor && $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) && ! current_user_can( 'edit_posts' ) ) {
			return $this->fail_result( __( 'You cannot edit that page.', 'rankmyseo' ), 403 );
		}

		$after = is_array( $action['after'] ) ? $action['after'] : array();
		if ( is_array( $edited ) ) {
			$after = array_merge( $after, $edited );
		}
		if ( isset( $edited['suggested'] ) && is_string( $edited['suggested'] ) ) {
			$after['suggested'] = $edited['suggested'];
		}

		if ( ! $force && $post_id > 0 && '' !== (string) $action['base_hash'] ) {
			$current = $this->current_hash( $post_id );
			if ( $current !== (string) $action['base_hash'] ) {
				$this->actions->update_columns( $id, array( 'status' => 'stale' ) );
				return $this->fail_result( __( 'This page changed since the suggestion was made. Review it again before applying.', 'rankmyseo' ), 409 );
			}
		}

		$snapshot_doc = $this->capture( $action, $post_id );
		$snapshot_id  = $this->snapshots->insert( $id, $snapshot_doc, $post_id > 0 ? $post_id : null );
		if ( $snapshot_id <= 0 ) {
			return $this->fail_result( __( 'The previous version could not be saved, so nothing was changed.', 'rankmyseo' ), 500 );
		}

		$result = $this->mutate( (string) $action['action_type'], $post_id, $after );
		if ( empty( $result['ok'] ) ) {
			$this->actions->update_columns(
				$id,
				array(
					'status'        => 'failed',
					'error_message' => (string) ( $result['message'] ?? '' ),
					'resolved_at'   => $this->now(),
				)
			);
			$this->audit->record( AuditLog::FAILED, 'user', (string) ( $result['message'] ?? '' ), 'action', $id );
			$this->acknowledge( $action, 'failed', (string) $snapshot_id, (string) ( $result['message'] ?? '' ) );
			return $this->fail_result( (string) ( $result['message'] ?? __( 'The change could not be applied.', 'rankmyseo' ) ), 400 );
		}

		if ( isset( $result['created'] ) && is_array( $result['created'] ) ) {
			$snapshot_doc['created'] = $result['created'];
		}
		$revision = isset( $result['revision_id'] ) ? (int) $result['revision_id'] : 0;
		$this->snapshots->update_document( $snapshot_id, $snapshot_doc, $revision );

		$applied_by = 'auto' === $actor ? 'auto' : 'human';
		$this->actions->update_columns(
			$id,
			array(
				'status'           => 'applied',
				'applied_by'       => $applied_by,
				'wp_actor_id'      => get_current_user_id(),
				'wp_revision_id'   => $revision > 0 ? $revision : null,
				'proposed_payload' => wp_json_encode( $after ),
				'resolved_at'      => $this->now(),
				'error_message'    => '',
			)
		);

		$event = 'auto' === $actor ? AuditLog::AUTO : AuditLog::APPLIED;
		$this->audit->record(
			$event,
			'auto' === $actor ? 'auto_apply' : 'user',
			ActionCatalog::label( (string) $action['action_type'] ),
			'action',
			$id,
			array( 'snapshot_id' => $snapshot_id )
		);
		$this->acknowledge( $action, 'applied', (string) $snapshot_id, '' );

		$fresh = $this->actions->find( $id );
		return array(
			'ok'      => true,
			'message' => __( 'Applied. You can undo this change.', 'rankmyseo' ),
			'action'  => null !== $fresh ? $this->actions->present( $fresh ) : array(),
		);
	}

	/**
	 * Reject a pending action.
	 *
	 * @param int    $id   Local action ID.
	 * @param string $note Optional note.
	 * @return array<string, mixed>
	 */
	public function reject( int $id, string $note = '' ): array {
		$action = $this->actions->find( $id );
		if ( null === $action ) {
			return $this->fail_result( __( 'That suggestion is not on this site.', 'rankmyseo' ), 404 );
		}
		if ( ! in_array( (string) $action['status'], array( 'pending', 'stale' ), true ) ) {
			return $this->fail_result( __( 'That suggestion can no longer be rejected.', 'rankmyseo' ), 409 );
		}
		$this->actions->update_columns(
			$id,
			array(
				'status'        => 'rejected',
				'applied_by'    => 'human',
				'wp_actor_id'   => get_current_user_id(),
				'resolved_at'   => $this->now(),
				'error_message' => substr( sanitize_text_field( $note ), 0, 500 ),
			)
		);
		$this->audit->record( AuditLog::REJECTED, 'user', __( 'Suggestion skipped.', 'rankmyseo' ), 'action', $id );
		$this->acknowledge( $action, 'rejected', '', substr( sanitize_text_field( $note ), 0, 500 ) );
		$fresh = $this->actions->find( $id );
		return array(
			'ok'      => true,
			'message' => __( 'Skipped. Nothing on the site was changed.', 'rankmyseo' ),
			'action'  => null !== $fresh ? $this->actions->present( $fresh ) : array(),
		);
	}

	/**
	 * Restore the snapshot taken before an applied action.
	 *
	 * @param int $id Local action ID.
	 * @return array<string, mixed>
	 */
	public function rollback( int $id ): array {
		$action = $this->actions->find( $id );
		if ( null === $action ) {
			return $this->fail_result( __( 'That suggestion is not on this site.', 'rankmyseo' ), 404 );
		}
		if ( 'applied' !== (string) $action['status'] ) {
			return $this->fail_result( __( 'Only an applied change can be undone.', 'rankmyseo' ), 409 );
		}
		$snapshot = $this->snapshots->latest_for_action( $id );
		if ( null === $snapshot ) {
			return $this->fail_result( __( 'The previous version is missing, so nothing was changed.', 'rankmyseo' ), 409 );
		}

		$restored = $this->restore( $snapshot['snapshot'] );
		if ( empty( $restored['ok'] ) ) {
			return $this->fail_result( (string) ( $restored['message'] ?? __( 'The previous version could not be restored.', 'rankmyseo' ) ), 409 );
		}

		$this->snapshots->mark_restored( (int) $snapshot['id'] );
		$this->actions->update_columns(
			$id,
			array(
				'status'      => 'rolled_back',
				'resolved_at' => $this->now(),
			)
		);
		$this->audit->record( AuditLog::ROLLED_BACK, 'user', __( 'Change undone.', 'rankmyseo' ), 'action', $id, array( 'snapshot_id' => (int) $snapshot['id'] ) );
		$this->acknowledge( $action, 'rolled_back', (string) $snapshot['id'], '' );

		$fresh = $this->actions->find( $id );
		return array(
			'ok'      => true,
			'message' => __( 'Undone. The previous version is back.', 'rankmyseo' ),
			'action'  => null !== $fresh ? $this->actions->present( $fresh ) : array(),
		);
	}

	/**
	 * Capture meta, post fields, or an empty created-entity shell.
	 *
	 * @param array<string, mixed> $action  Hydrated action.
	 * @param int                  $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function capture( array $action, int $post_id ): array {
		$type = ActionCatalog::canonical( (string) $action['action_type'] );
		$doc  = array(
			'object_type' => ActionCatalog::ADD_REDIRECT === $type ? 'redirect' : 'post',
			'wp_post_id'  => $post_id > 0 ? $post_id : null,
			'meta'        => array(),
			'post'        => array(),
			'created'     => null,
		);
		if ( $post_id <= 0 ) {
			return $doc;
		}
		$post = get_post( $post_id );
		if ( is_object( $post ) ) {
			$doc['post'] = array(
				'post_content' => (string) $post->post_content,
				'post_title'   => (string) $post->post_title,
				'post_status'  => (string) $post->post_status,
			);
		}
		foreach ( $this->meta_keys_for( (string) $action['action_type'], is_array( $action['after'] ) ? $action['after'] : array() ) as $key ) {
			$doc['meta'][ $key ] = metadata_exists( 'post', $post_id, $key ) ? get_post_meta( $post_id, $key, true ) : null;
		}
		return $doc;
	}

	/**
	 * Perform the WordPress write for one type.
	 *
	 * @param string               $type    Action type.
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $after   Proposed payload.
	 * @return array<string, mixed>
	 */
	private function mutate( string $type, int $post_id, array $after ): array {
		$canonical = ActionCatalog::canonical( $type );
		if ( ActionCatalog::ADD_REDIRECT === $canonical ) {
			$created = $this->redirects->create( $after );
			if ( empty( $created['ok'] ) ) {
				return $created;
			}
			return array(
				'ok'      => true,
				'created' => array(
					'type' => 'redirect',
					'id'   => (int) $created['id'],
				),
			);
		}
		if ( ActionCatalog::is_draft( $type ) ) {
			return $this->create_draft( $after );
		}
		if ( $post_id <= 0 || ! is_object( get_post( $post_id ) ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'That page is not on this site.', 'rankmyseo' ),
			);
		}
		if ( ActionCatalog::CONTENT_PATCH === $canonical || ActionCatalog::ADD_INTERNAL_LINK === $canonical ) {
			return $this->patch_content( $post_id, $canonical, $after );
		}
		return $this->write_meta( $post_id, $type, $after );
	}

	/**
	 * Write SEO meta for title, description, canonical, robots, keyword, or schema.
	 *
	 * @param int                  $post_id Post ID.
	 * @param string               $type    Action type.
	 * @param array<string, mixed> $after   Proposed payload.
	 * @return array<string, mixed>
	 */
	private function write_meta( int $post_id, string $type, array $after ): array {
		$writes = $this->meta_writes( $type, $after );
		if ( array() === $writes ) {
			return array(
				'ok'      => false,
				'message' => __( 'That suggestion did not include a value to save.', 'rankmyseo' ),
			);
		}
		if ( isset( $writes[ PayloadSanitizer::META_ROBOTS ] ) && is_array( $writes[ PayloadSanitizer::META_ROBOTS ] ) ) {
			$robots = $writes[ PayloadSanitizer::META_ROBOTS ];
			if ( ! empty( $robots['noindex'] ) && $this->is_front_page( $post_id ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'The homepage stays visible to search engines.', 'rankmyseo' ),
				);
			}
		}
		foreach ( $writes as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return array( 'ok' => true );
	}

	/**
	 * Meta keys and values for one action.
	 *
	 * @param string               $type  Action type.
	 * @param array<string, mixed> $after Proposed payload.
	 * @return array<string, mixed>
	 */
	private function meta_writes( string $type, array $after ): array {
		$canonical = ActionCatalog::canonical( $type );
		$writes    = array();
		if ( ActionCatalog::UPDATE_TITLE === $canonical || ActionCatalog::ALIAS_META === $type ) {
			$title = PayloadSanitizer::plain( PayloadSanitizer::text_field( $after, array( 'suggested', 'seoTitle', 'title', 'seo_title' ) ), PayloadSanitizer::TITLE_MAX );
			if ( ActionCatalog::UPDATE_TITLE === $canonical && '' !== $title ) {
				$writes[ PayloadSanitizer::META_TITLE ] = $title;
			}
			if ( ActionCatalog::ALIAS_META === $type && $this->has_any( $after, array( 'suggested', 'seoTitle', 'title', 'seo_title' ) ) ) {
				$writes[ PayloadSanitizer::META_TITLE ] = $title;
			}
		}
		if ( ActionCatalog::UPDATE_META_DESCRIPTION === $canonical || ActionCatalog::ALIAS_META === $type ) {
			$keys = ActionCatalog::UPDATE_META_DESCRIPTION === $canonical
				? array( 'suggested', 'seoDescription', 'description', 'metaDescription' )
				: array( 'seoDescription', 'description', 'metaDescription', 'meta_description' );
			if ( $this->has_any( $after, $keys ) || ActionCatalog::UPDATE_META_DESCRIPTION === $canonical ) {
				$text = PayloadSanitizer::plain( PayloadSanitizer::text_field( $after, $keys ), PayloadSanitizer::DESCRIPTION_MAX );
				if ( '' !== $text || ActionCatalog::UPDATE_META_DESCRIPTION === $canonical ) {
					$writes[ PayloadSanitizer::META_DESCRIPTION ] = $text;
				}
			}
		}
		if ( ActionCatalog::UPDATE_CANONICAL === $canonical || ( ActionCatalog::ALIAS_META === $type && $this->has_any( $after, array( 'canonical', 'seoCanonical' ) ) ) ) {
			$raw = PayloadSanitizer::text_field( $after, array( 'suggested', 'canonical', 'seoCanonical' ) );
			$url = PayloadSanitizer::url( $raw );
			if ( '' === $url && '' !== $raw ) {
				return array();
			}
			$writes[ PayloadSanitizer::META_CANONICAL ] = $url;
		}
		if ( ActionCatalog::ALIAS_META === $type && $this->has_any( $after, array( 'focusKeyword', 'focus_keyword' ) ) ) {
			$writes[ PayloadSanitizer::META_FOCUS ] = PayloadSanitizer::plain(
				PayloadSanitizer::text_field( $after, array( 'focusKeyword', 'focus_keyword' ) ),
				PayloadSanitizer::KEYWORD_MAX
			);
		}
		if ( ActionCatalog::ALIAS_META === $type && isset( $after['robots'] ) ) {
			$writes[ PayloadSanitizer::META_ROBOTS ] = PayloadSanitizer::robots( $after['robots'] );
		}
		if ( ActionCatalog::UPDATE_SCHEMA === $canonical ) {
			$schema_type = sanitize_key( PayloadSanitizer::text_field( $after, array( 'schemaType', 'schema_type', 'type', 'suggested' ) ) );
			if ( '' !== $schema_type ) {
				$writes[ PayloadSanitizer::META_SCHEMA_TYPE ] = substr( $schema_type, 0, 64 );
			}
			if ( isset( $after['faq'] ) && is_array( $after['faq'] ) ) {
				$writes[ PayloadSanitizer::META_SCHEMA_DATA ] = $this->clean_faq( $after['faq'] );
			} elseif ( isset( $after['schema'] ) && is_array( $after['schema'] ) ) {
				$writes[ PayloadSanitizer::META_SCHEMA_DATA ] = $after['schema'];
			}
		}
		return $writes;
	}

	/**
	 * Meta keys that a change might touch, used for the snapshot.
	 *
	 * @param string               $type  Action type.
	 * @param array<string, mixed> $after Proposed payload.
	 * @return list<string>
	 */
	private function meta_keys_for( string $type, array $after ): array {
		$keys  = array_keys( $this->meta_writes( $type, $after ) );
		$clean = array();
		foreach ( $keys as $key ) {
			$clean[] = (string) $key;
		}
		return $clean;
	}

	/**
	 * Create a draft post. The status is always draft.
	 *
	 * @param array<string, mixed> $after Proposed payload.
	 * @return array<string, mixed>
	 */
	private function create_draft( array $after ): array {
		$title = PayloadSanitizer::plain( PayloadSanitizer::text_field( $after, array( 'title', 'suggested' ) ), PayloadSanitizer::TITLE_MAX );
		$body  = PayloadSanitizer::html( PayloadSanitizer::text_field( $after, array( 'body', 'content', 'markdown', 'suggested' ) ) );
		if ( '' === $title && '' === $body ) {
			return array(
				'ok'      => false,
				'message' => __( 'A draft needs a title or some text.', 'rankmyseo' ),
			);
		}
		$post_type = isset( $after['postType'] ) && is_string( $after['postType'] ) ? sanitize_key( $after['postType'] ) : 'post';
		if ( '' === $post_type ) {
			$post_type = 'post';
		}
		$created = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $body,
				'post_status'  => 'draft',
				'post_type'    => $post_type,
			),
			true
		);
		if ( is_wp_error( $created ) || ! is_int( $created ) || $created <= 0 ) {
			return array(
				'ok'      => false,
				'message' => __( 'The draft could not be created.', 'rankmyseo' ),
			);
		}
		$description = PayloadSanitizer::plain( PayloadSanitizer::text_field( $after, array( 'metaDescription', 'description' ) ), PayloadSanitizer::DESCRIPTION_MAX );
		if ( '' !== $description ) {
			update_post_meta( $created, PayloadSanitizer::META_DESCRIPTION, $description );
		}
		$stored = get_post( $created );
		if ( is_object( $stored ) && 'draft' !== (string) $stored->post_status ) {
			wp_update_post(
				array(
					'ID'          => $created,
					'post_status' => 'draft',
				)
			);
		}
		return array(
			'ok'      => true,
			'created' => array(
				'type' => 'post',
				'id'   => $created,
			),
		);
	}

	/**
	 * Update post content and keep a native revision.
	 *
	 * @param int                  $post_id    Post ID.
	 * @param string               $canonical  Canonical type.
	 * @param array<string, mixed> $after      Proposed payload.
	 * @return array<string, mixed>
	 */
	private function patch_content( int $post_id, string $canonical, array $after ): array {
		$post = get_post( $post_id );
		if ( ! is_object( $post ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'That page is not on this site.', 'rankmyseo' ),
			);
		}
		$current = (string) $post->post_content;
		if ( ActionCatalog::ADD_INTERNAL_LINK === $canonical ) {
			$href   = PayloadSanitizer::url( PayloadSanitizer::text_field( $after, array( 'url', 'href', 'suggested' ) ) );
			$anchor = PayloadSanitizer::plain( PayloadSanitizer::text_field( $after, array( 'anchor', 'title', 'label' ) ), 180 );
			$host   = wp_parse_url( $href, PHP_URL_HOST );
			$home   = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( '' === $href || '' === $anchor || ! is_string( $host ) || ! is_string( $home ) || strtolower( $host ) !== strtolower( $home ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'The link has to stay on this site.', 'rankmyseo' ),
				);
			}
			$next = $current . "\n\n" . '<p><a href="' . esc_url_raw( $href ) . '">' . esc_html( $anchor ) . '</a></p>';
		} else {
			$next = PayloadSanitizer::html( PayloadSanitizer::text_field( $after, array( 'suggested', 'content', 'body' ) ) );
			if ( '' === trim( wp_strip_all_tags( $next ) ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'The new page text was empty, so nothing was changed.', 'rankmyseo' ),
				);
			}
		}

		$revision = (int) wp_save_post_revision( $post_id );
		$updated  = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $next,
			),
			true
		);
		if ( is_wp_error( $updated ) || 0 === $updated ) {
			return array(
				'ok'      => false,
				'message' => __( 'The page text could not be updated.', 'rankmyseo' ),
			);
		}
		return array(
			'ok'          => true,
			'revision_id' => $revision,
		);
	}

	/**
	 * Restore a snapshot document.
	 *
	 * @param array<string, mixed> $document Snapshot JSON.
	 * @return array<string, mixed>
	 */
	private function restore( array $document ): array {
		$created = isset( $document['created'] ) && is_array( $document['created'] ) ? $document['created'] : null;
		if ( is_array( $created ) && isset( $created['type'], $created['id'] ) ) {
			$id = (int) $created['id'];
			if ( 'redirect' === $created['type'] ) {
				$this->redirects->delete( $id );
				return array( 'ok' => true );
			}
			if ( 'post' === $created['type'] ) {
				$post = get_post( $id );
				if ( is_object( $post ) && 'draft' !== (string) $post->post_status ) {
					return array(
						'ok'      => false,
						'message' => __( 'That draft was published, so it was left in place.', 'rankmyseo' ),
					);
				}
				wp_delete_post( $id, true );
				return array( 'ok' => true );
			}
		}

		$post_id = 0;
		if ( isset( $document['post'] ) && is_array( $document['post'] ) ) {
			$post_fields = $document['post'];
			if ( isset( $post_fields['post_content'] ) ) {
				$post_id = $this->post_id_from_snapshot( $document );
			}
		}
		if ( isset( $document['meta'] ) && is_array( $document['meta'] ) ) {
			foreach ( $document['meta'] as $key => $value ) {
				if ( ! is_string( $key ) ) {
					continue;
				}
				$target = $post_id > 0 ? $post_id : $this->post_id_from_snapshot( $document );
				if ( $target <= 0 ) {
					continue;
				}
				$post_id = $target;
				if ( null === $value ) {
					delete_post_meta( $target, $key );
				} else {
					update_post_meta( $target, $key, $value );
				}
			}
		}
		if ( $post_id > 0 && isset( $document['post'] ) && is_array( $document['post'] ) && array_key_exists( 'post_content', $document['post'] ) ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => (string) $document['post']['post_content'],
					'post_title'   => (string) ( $document['post']['post_title'] ?? '' ),
				)
			);
		}
		return array( 'ok' => true );
	}

	/**
	 * Post ID recorded beside the snapshot is not inside the JSON.
	 * Callers pass it through the repository row. This fallback reads created ids only.
	 *
	 * The repository row is not in the document, so content rollback uses the
	 * post id stored on the snapshot row via a side channel set before restore.
	 *
	 * @param array<string, mixed> $document Snapshot JSON.
	 * @return int
	 */
	private function post_id_from_snapshot( array $document ): int {
		if ( isset( $document['wp_post_id'] ) && is_numeric( $document['wp_post_id'] ) ) {
			return (int) $document['wp_post_id'];
		}
		return 0;
	}

	/**
	 * Tell RankMySEO the outcome. A network failure does not undo the local change.
	 *
	 * @param array<string, mixed> $action      Action before the status change.
	 * @param string               $status      Cloud status.
	 * @param string               $snapshot_id Snapshot ID.
	 * @param string               $error       Error text.
	 * @return void
	 */
	private function acknowledge( array $action, string $status, string $snapshot_id, string $error ): void {
		if ( ! $this->vault->is_connected() ) {
			return;
		}
		$cloud_id = (string) $action['cloud_action_id'];
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/i', $cloud_id ) ) {
			return;
		}
		$body = array(
			'status'     => $status,
			'wpUserHash' => hash( 'sha256', untrailingslashit( home_url() ) . ':' . (string) get_current_user_id() ),
		);
		if ( '' !== $snapshot_id ) {
			$body['snapshotId'] = $snapshot_id;
		}
		if ( '' !== $error ) {
			$body['errorMessage'] = substr( $error, 0, 500 );
		}
		$this->client->ack_action( $cloud_id, $body );
	}

	/**
	 * SHA-256 of the fields a suggestion might have been based on.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function current_hash( int $post_id ): string {
		$post = get_post( $post_id );
		$bag  = array(
			'title'   => (string) get_post_meta( $post_id, PayloadSanitizer::META_TITLE, true ),
			'content' => is_object( $post ) ? (string) $post->post_content : '',
		);
		$json = wp_json_encode( $bag );
		return hash( 'sha256', is_string( $json ) ? $json : '' );
	}

	/**
	 * Post type used by the auto-apply whitelist.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $after   Proposed payload.
	 * @return string
	 */
	private function post_type( int $post_id, array $after ): string {
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( is_object( $post ) ) {
				return (string) $post->post_type;
			}
		}
		if ( isset( $after['postType'] ) && is_string( $after['postType'] ) ) {
			$type = sanitize_key( $after['postType'] );
			return '' !== $type ? $type : 'post';
		}
		return 'post';
	}

	/**
	 * Whether any listed key is present.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param array<int, string>   $keys    Keys.
	 * @return bool
	 */
	private function has_any( array $payload, array $keys ): bool {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the post is the site homepage.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_front_page( int $post_id ): bool {
		$front = (int) get_option( 'page_on_front' );
		return $front > 0 && $front === $post_id;
	}

	/**
	 * Plain FAQ pairs.
	 *
	 * @param array<mixed> $faq Raw FAQ list.
	 * @return list<array<string, string>>
	 */
	private function clean_faq( array $faq ): array {
		$clean = array();
		foreach ( $faq as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$question = PayloadSanitizer::plain( isset( $item['question'] ) && is_string( $item['question'] ) ? $item['question'] : '', 300 );
			$answer   = PayloadSanitizer::plain( isset( $item['answer'] ) && is_string( $item['answer'] ) ? $item['answer'] : '', 2000 );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$clean[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
			if ( count( $clean ) >= 20 ) {
				break;
			}
		}
		return $clean;
	}

	/**
	 * Current GMT timestamp.
	 *
	 * @return string
	 */
	private function now(): string {
		$now = current_time( 'mysql', true );
		return is_string( $now ) ? $now : gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Error result.
	 *
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return array<string, mixed>
	 */
	private function fail_result( string $message, int $status ): array {
		return array(
			'ok'      => false,
			'status'  => $status,
			'message' => $message,
		);
	}
}
