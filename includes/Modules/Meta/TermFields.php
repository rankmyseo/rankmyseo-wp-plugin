<?php
/**
 * Term edit fields for taxonomy title templates and robots.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Classic term edit form for the same meta keys used on posts.
 */
final class TermFields {
	/**
	 * Meta store.
	 *
	 * @var MetaStore
	 */
	private MetaStore $store;

	/**
	 * Constructor.
	 *
	 * @param MetaStore $store Meta store.
	 */
	public function __construct( MetaStore $store ) {
		$this->store = $store;
	}

	/**
	 * Register term form hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_taxonomies' ], 30 );
		add_action( 'edited_term', [ $this, 'save_term' ], 10, 3 );
	}

	/**
	 * Attach the edit form to each public taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		$taxonomies = get_taxonomies( [ 'public' => true ] );
		if ( ! is_array( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy ) {
			add_action( (string) $taxonomy . '_edit_form_fields', [ $this, 'render' ] );
		}
	}

	/**
	 * Render term fields.
	 *
	 * @param \WP_Term $term Term being edited.
	 * @return void
	 */
	public function render( \WP_Term $term ): void {
		$meta   = $this->store->read_term( (int) $term->term_id );
		$robots = is_array( $meta['robots'] ) ? $meta['robots'] : Robots::defaults();
		wp_nonce_field( 'rankmyseo_save_term_' . (int) $term->term_id, 'rankmyseo_term_nonce' );

		$this->row( __( 'Search title', 'rankmyseo' ), 'rankmyseo_title', (string) $meta['title'] );
		$this->row( __( 'Search description', 'rankmyseo' ), 'rankmyseo_description', (string) $meta['description'] );

		echo '<tr class="form-field"><th scope="row">';
		echo esc_html__( 'Hide from search', 'rankmyseo' );
		echo '</th><td><label><input type="checkbox" name="rankmyseo_term_noindex" value="1" ';
		checked( ! empty( $robots['noindex'] ) );
		echo ' /> ';
		echo esc_html__( 'Ask search engines not to show this archive.', 'rankmyseo' );
		echo '</label></td></tr>';
	}

	/**
	 * Save term fields.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function save_term( int $term_id, int $tt_id, string $taxonomy ): void {
		unset( $tt_id );

		if ( ! isset( $_POST['rankmyseo_term_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['rankmyseo_term_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'rankmyseo_save_term_' . $term_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$public = get_taxonomies( [ 'public' => true ] );
		if ( ! is_array( $public ) || ! in_array( $taxonomy, $public, true ) ) {
			return;
		}

		$current = $this->store->read_term( $term_id );
		$title   = isset( $_POST['rankmyseo_title'] ) ? (string) wp_unslash( $_POST['rankmyseo_title'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in MetaStore.
		$desc    = isset( $_POST['rankmyseo_description'] ) ? (string) wp_unslash( $_POST['rankmyseo_description'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in MetaStore.

		$current['title']        = $title;
		$current['description']  = $desc;
		$robots                  = is_array( $current['robots'] ) ? Robots::sanitize( $current['robots'] ) : Robots::defaults();
		$robots['noindex']       = isset( $_POST['rankmyseo_term_noindex'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['rankmyseo_term_noindex'] ) );
		$current['robots']       = $robots;
		$current['twitter_card'] = '' !== (string) $current['twitter_card'] ? (string) $current['twitter_card'] : 'summary_large_image';

		$this->store->write_term( $term_id, $current );
	}

	/**
	 * Row.
	 *
	 * @param string $label Field label.
	 * @param string $name  Input name.
	 * @param string $value Current value.
	 * @return void
	 */
	private function row( string $label, string $name, string $value ): void {
		echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="large-text" /></td></tr>';
	}
}
