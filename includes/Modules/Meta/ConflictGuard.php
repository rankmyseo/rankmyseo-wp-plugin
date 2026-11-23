<?php
/**
 * Pauses duplicate head tags while another SEO plugin is still active.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

use RankMySEO\Core\Capabilities;

/**
 * Detects active SEO plugins and shows one calm, dismissible admin notice.
 */
final class ConflictGuard {
	public const USER_META_DISMISSED = '_rankmyseo_meta_conflict_notice';

	/**
	 * Title settings repository.
	 *
	 * @var TitleSettings
	 */
	private TitleSettings $titles;

	/**
	 * Optional test override. Null uses live detection.
	 *
	 * @var bool|null
	 */
	private ?bool $conflict_override;

	/**
	 * Constructor.
	 *
	 * @param TitleSettings $titles            Title settings.
	 * @param bool|null     $conflict_override Force a conflict state in tests.
	 */
	public function __construct( TitleSettings $titles, ?bool $conflict_override = null ) {
		$this->titles            = $titles;
		$this->conflict_override = $conflict_override;
	}

	/**
	 * Register admin notice hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'handle_notice_action' ] );
		add_action( 'admin_notices', [ $this, 'render_notice' ] );
	}

	/**
	 * Whether another plugin is already printing SEO tags.
	 *
	 * @return bool
	 */
	public function has_conflict(): bool {
		if ( null !== $this->conflict_override ) {
			return $this->conflict_override;
		}

		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
			return true;
		}

		return class_exists( 'WPSEO_Frontend' )
			|| class_exists( 'RankMath' )
			|| class_exists( 'AIOSEO\\Plugin\\AIOSEO' );
	}

	/**
	 * Whether RankMySEO should skip its own head tags.
	 *
	 * @return bool
	 */
	public function should_pause_head(): bool {
		if ( $this->titles->is_force_head() ) {
			return false;
		}

		return $this->has_conflict();
	}

	/**
	 * Handle dismiss and "print tags anyway" links.
	 *
	 * @return void
	 */
	public function handle_notice_action(): void {
		if ( ! isset( $_GET['rankmyseo_meta_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below before any state change.
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rankmyseo_meta_notice' ) ) {
			return;
		}

		if ( ! Capabilities::current_user_can_manage() ) {
			return;
		}

		$action = sanitize_key( wp_unslash( (string) $_GET['rankmyseo_meta_notice'] ) );

		if ( 'force' === $action ) {
			$this->titles->set_force_head( true );
		}

		if ( in_array( $action, [ 'dismiss', 'force' ], true ) ) {
			update_user_meta( get_current_user_id(), self::USER_META_DISMISSED, '1' );
		}

		wp_safe_redirect( remove_query_arg( [ 'rankmyseo_meta_notice', '_wpnonce' ] ) );
		exit;
	}

	/**
	 * Print a dismissible notice when head output is paused.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		if ( ! $this->has_conflict() || ! Capabilities::current_user_can_manage() ) {
			return;
		}

		if ( '1' === (string) get_user_meta( get_current_user_id(), self::USER_META_DISMISSED, true ) ) {
			return;
		}

		$force_url   = wp_nonce_url( add_query_arg( 'rankmyseo_meta_notice', 'force' ), 'rankmyseo_meta_notice' );
		$dismiss_url = wp_nonce_url( add_query_arg( 'rankmyseo_meta_notice', 'dismiss' ), 'rankmyseo_meta_notice' );

		echo '<div class="notice notice-info is-dismissible"><p>';
		echo esc_html__( 'Another SEO plugin is already adding search titles. RankMySEO is pausing its own titles and social tags so visitors do not see duplicates. You can import those settings, then turn the other plugin off.', 'rankmyseo' );
		echo '</p><p>';
		echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=rankmyseo-import' ) ) . '">';
		echo esc_html__( 'Review import', 'rankmyseo' );
		echo '</a> ';
		echo '<a class="button button-secondary" href="' . esc_url( $force_url ) . '">';
		echo esc_html__( 'Print RankMySEO tags anyway', 'rankmyseo' );
		echo '</a> ';
		echo '<a href="' . esc_url( $dismiss_url ) . '">';
		echo esc_html__( 'Dismiss', 'rankmyseo' );
		echo '</a>';
		echo '</p></div>';
	}
}
