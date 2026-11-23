<?php
/**
 * RankMySEO Cloud module.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

use RankMySEO\Modules\AbstractModule;

/**
 * Optional cloud connection. Registers sync hooks only after an administrator enables it.
 */
final class CloudModule extends AbstractModule {
	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'cloud';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'RankMySEO Cloud', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Optional site connection for tracked keyword rankings, next actions, and inventory sync.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_simple_description(): string {
		return __( 'Optionally connect RankMySEO to see keyword rankings and suggested page edits in WordPress.', 'rankmyseo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled_by_default(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_ready(): bool {
		if ( ! $this->container->has( TokenVault::class ) ) {
			return false;
		}
		$vault = $this->container->get( TokenVault::class );
		return $vault instanceof TokenVault && $vault->is_connected();
	}

	/**
	 * {@inheritDoc}
	 */
	public function requires_cloud(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		if ( ! $this->container->has( SyncWorker::class ) || ! $this->container->has( SyncQueue::class ) ) {
			return;
		}

		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_before_delete_post' ), 10, 2 );
		$this->worker()->register_hooks();
	}

	/**
	 * Queue a publish or unpublish event.
	 *
	 * @param string $new_status New status.
	 * @param string $old_status Previous status.
	 * @param mixed  $post       Post object.
	 * @return void
	 */
	public function on_transition_post_status( string $new_status, string $old_status, $post ): void {
		if ( $new_status === $old_status || ! is_object( $post ) || ! $this->can_enqueue( $post ) ) {
			return;
		}

		if ( 'publish' === $new_status ) {
			$this->enqueue_inventory( $post );
			$this->enqueue_event( $post, 'post_published' );
			return;
		}

		if ( 'publish' === $old_status ) {
			$this->enqueue_event( $post, 'post_updated' );
		}
	}

	/**
	 * Queue a slug change or a published-post update.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $after   Updated post.
	 * @param mixed $before  Previous post.
	 * @return void
	 */
	public function on_post_updated( int $post_id, $after, $before ): void {
		unset( $post_id );
		if ( ! is_object( $after ) || ! is_object( $before ) || ! $this->can_enqueue( $after ) ) {
			return;
		}
		if ( (string) $after->post_status !== (string) $before->post_status ) {
			return;
		}
		if ( 'publish' !== (string) $after->post_status ) {
			return;
		}

		$this->enqueue_inventory( $after );
		if ( (string) $after->post_name !== (string) $before->post_name ) {
			$this->enqueue_event( $after, 'slug_changed', PostPayloadBuilder::previous_url( $after, $before ) );
			return;
		}
		$this->enqueue_event( $after, 'post_updated' );
	}

	/**
	 * Queue a delete event.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $post    Post object, when WordPress provides it.
	 * @return void
	 */
	public function on_before_delete_post( int $post_id, $post = null ): void {
		if ( ! is_object( $post ) && function_exists( 'get_post' ) ) {
			$post = get_post( $post_id );
		}
		if ( ! is_object( $post ) || ! $this->can_enqueue( $post ) ) {
			return;
		}
		$this->enqueue_event( $post, 'post_deleted' );
	}

	/**
	 * Whether hooks should write a queue row for this post.
	 *
	 * @param object $post Post object.
	 * @return bool
	 */
	private function can_enqueue( object $post ): bool {
		if ( ! $this->container->has( TokenVault::class ) || ! PostPayloadBuilder::is_syncable( $post ) ) {
			return false;
		}
		$vault = $this->container->get( TokenVault::class );
		return $vault instanceof TokenVault && $vault->is_connected();
	}

	/**
	 * Enqueue an inventory snapshot.
	 *
	 * @param object $post Post object.
	 * @return void
	 */
	private function enqueue_inventory( object $post ): void {
		$row = PostPayloadBuilder::inventory_from_post( $post );
		if ( null !== $row ) {
			$this->queue()->enqueue_inventory( $row );
		}
	}

	/**
	 * Enqueue one event.
	 *
	 * @param object      $post         Post object.
	 * @param string      $type         Event type.
	 * @param string|null $previous_url Previous URL for slug changes.
	 * @return void
	 */
	private function enqueue_event( object $post, string $type, ?string $previous_url = null ): void {
		$event = PostPayloadBuilder::event_from_post( $post, $type, $previous_url );
		if ( null !== $event ) {
			$this->queue()->enqueue_event( $event );
		}
	}

	/**
	 * Resolve the sync queue.
	 *
	 * @return SyncQueue
	 */
	private function queue(): SyncQueue {
		$queue = $this->container->get( SyncQueue::class );
		assert( $queue instanceof SyncQueue );
		return $queue;
	}

	/**
	 * Resolve the sync worker.
	 *
	 * @return SyncWorker
	 */
	private function worker(): SyncWorker {
		$worker = $this->container->get( SyncWorker::class );
		assert( $worker instanceof SyncWorker );
		return $worker;
	}
}
