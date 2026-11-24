<?php
/**
 * Classic Editor metabox with nonce-backed fields and a React mount point.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

use RankMySEO\Core\Settings;
use RankMySEO\Modules\Schema\SchemaMeta;

/**
 * Saves SEO fields from the classic post form even when JavaScript is unavailable.
 */
final class ClassicMetabox {
	/**
	 * Meta store.
	 *
	 * @var MetaStore
	 */
	private MetaStore $store;

	/**
	 * Core settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Title settings.
	 *
	 * @var TitleSettings
	 */
	private TitleSettings $titles;

	/**
	 * Constructor.
	 *
	 * @param MetaStore     $store    Meta store.
	 * @param Settings      $settings Core settings.
	 * @param TitleSettings $titles   Title settings.
	 */
	public function __construct( MetaStore $store, Settings $settings, TitleSettings $titles ) {
		$this->store    = $store;
		$this->settings = $settings;
		$this->titles   = $titles;
	}

	/**
	 * Register metabox and save hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_boxes' ] );
		add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );
	}

	/**
	 * Add the metabox on classic editor screens for public post types.
	 *
	 * @return void
	 */
	public function add_boxes(): void {
		if ( $this->is_block_editor_screen() ) {
			return;
		}

		$post_types = get_post_types( [ 'public' => true ] );
		if ( ! is_array( $post_types ) ) {
			return;
		}

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'rankmyseo_meta',
				__( 'RankMySEO', 'rankmyseo' ),
				[ $this, 'render' ],
				(string) $post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render hidden inputs plus the React mount node.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		$meta   = $this->store->read_post( (int) $post->ID );
		$robots = is_array( $meta['robots'] ) ? $meta['robots'] : Robots::defaults();
		wp_nonce_field( 'rankmyseo_save_meta_' . (int) $post->ID, 'rankmyseo_meta_nonce' );

		$fields = [
			'rankmyseo_title'               => (string) $meta['title'],
			'rankmyseo_description'         => (string) $meta['description'],
			'rankmyseo_canonical'           => (string) $meta['canonical'],
			'rankmyseo_focus_keyword'       => (string) $meta['focus_keyword'],
			'rankmyseo_cornerstone'         => ! empty( $meta['cornerstone'] ) ? '1' : '0',
			'rankmyseo_og_title'            => (string) $meta['og_title'],
			'rankmyseo_og_description'      => (string) $meta['og_description'],
			'rankmyseo_og_image'            => (string) $meta['og_image'],
			'rankmyseo_twitter_title'       => (string) $meta['twitter_title'],
			'rankmyseo_twitter_description' => (string) $meta['twitter_description'],
			'rankmyseo_twitter_image'       => (string) $meta['twitter_image'],
			'rankmyseo_twitter_card'        => '' !== (string) $meta['twitter_card'] ? (string) $meta['twitter_card'] : 'summary_large_image',
			'rankmyseo_schema_type'         => SchemaMeta::read_type( (int) $post->ID ),
		];

		echo '<div id="rankmyseo-classic-fields" class="rankmyseo-classic-fields">';
		foreach ( $fields as $name => $value ) {
			printf(
				'<input type="hidden" id="%1$s" name="%1$s" value="%2$s" />',
				esc_attr( $name ),
				esc_attr( $value )
			);
		}

		$keywords = isset( $meta['secondary_keywords'] ) && is_array( $meta['secondary_keywords'] ) ? $meta['secondary_keywords'] : [];
		printf(
			'<input type="hidden" id="rankmyseo_secondary_keywords" name="rankmyseo_secondary_keywords" value="%s" />',
			esc_attr( (string) wp_json_encode( $keywords ) )
		);
		printf(
			'<input type="hidden" id="rankmyseo_robots" name="rankmyseo_robots" value="%s" />',
			esc_attr( (string) wp_json_encode( $robots ) )
		);
		echo '</div>';

		$config = [
			'postId'    => (int) $post->ID,
			'postType'  => (string) $post->post_type,
			'permalink' => (string) get_permalink( $post ),
			'postTitle' => (string) $post->post_title,
			'uiMode'    => $this->settings->get_user_ui_mode( get_current_user_id() ),
			'siteName'  => $this->titles->site_name(),
			'siteUrl'   => (string) home_url( '/' ),
			'separator' => $this->titles->separator(),
			'tagline'   => $this->titles->tagline(),
		];

		printf(
			'<div id="rankmyseo-classic-root" class="rms-root" data-rankmyseo-classic="%s"></div>',
			esc_attr( (string) wp_json_encode( $config ) )
		);

		echo '<noscript><p>';
		echo esc_html__( 'JavaScript is off, so the live preview is hidden. The saved search title and description above stay attached to this page.', 'rankmyseo' );
		echo '</p></noscript>';
	}

	/**
	 * Persist classic form fields after a valid nonce and capability check.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_post( int $post_id, \WP_Post $post ): void {
		unset( $post );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['rankmyseo_meta_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['rankmyseo_meta_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'rankmyseo_save_meta_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->store->write_post( $post_id, $this->bundle_from_request() );
		if ( isset( $_POST['rankmyseo_schema_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above.
			SchemaMeta::write_type( $post_id, wp_unslash( $_POST['rankmyseo_schema_type'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- SchemaMeta::write_type sanitizes the type.
		}
	}

	/**
	 * Read sanitized field values from the classic form.
	 *
	 * @return array<string, mixed>
	 */
	public function bundle_from_request(): array {
		$read = static function ( string $key ): string {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the metabox nonce.
				return '';
			}
			return (string) wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Sanitized by MetaSanitizer::sanitize_bundle().
		};

		return [
			'title'               => $read( 'rankmyseo_title' ),
			'description'         => $read( 'rankmyseo_description' ),
			'canonical'           => $read( 'rankmyseo_canonical' ),
			'focus_keyword'       => $read( 'rankmyseo_focus_keyword' ),
			'secondary_keywords'  => $read( 'rankmyseo_secondary_keywords' ),
			'cornerstone'         => $read( 'rankmyseo_cornerstone' ),
			'robots'              => $read( 'rankmyseo_robots' ),
			'og_title'            => $read( 'rankmyseo_og_title' ),
			'og_description'      => $read( 'rankmyseo_og_description' ),
			'og_image'            => $read( 'rankmyseo_og_image' ),
			'twitter_title'       => $read( 'rankmyseo_twitter_title' ),
			'twitter_description' => $read( 'rankmyseo_twitter_description' ),
			'twitter_image'       => $read( 'rankmyseo_twitter_image' ),
			'twitter_card'        => $read( 'rankmyseo_twitter_card' ),
		];
	}

	/**
	 * Whether the current admin screen is the block editor.
	 *
	 * @return bool
	 */
	private function is_block_editor_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		return is_object( $screen ) && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
	}
}
