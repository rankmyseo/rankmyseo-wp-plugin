<?php
/**
 * Posts list SEO column plus Quick Edit and Bulk Edit fields.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

use RankMySEO\Modules\Analyzer\ScoreBands;

/**
 * Inline editing for search title, description, focus keyword, and noindex.
 */
final class QuickBulkEdit {
	/**
	 * Meta store.
	 *
	 * @var MetaStore
	 */
	private MetaStore $store;

	/**
	 * Template fill.
	 *
	 * @var TemplateFill
	 */
	private TemplateFill $fill;

	/**
	 * Constructor.
	 *
	 * @param MetaStore    $store Meta store.
	 * @param TemplateFill $fill  Optional template fill. Built from defaults when omitted.
	 */
	public function __construct( MetaStore $store, ?TemplateFill $fill = null ) {
		$this->store = $store;
		$this->fill  = $fill ?? new TemplateFill(
			new TitleSettings( new \RankMySEO\Core\Settings() ),
			new TemplateEngine(),
			new MissingMetadataQuery( $store )
		);
	}

	/**
	 * Register list-table hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_columns' ] );
		add_action( 'quick_edit_custom_box', [ $this, 'render_quick_edit' ], 10, 2 );
		add_action( 'bulk_edit_custom_box', [ $this, 'render_bulk_edit' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_post' ] );
	}

	/**
	 * Attach the SEO column to every public post type list table.
	 *
	 * @return void
	 */
	public function register_columns(): void {
		$post_types = get_post_types( [ 'public' => true ] );
		if ( ! is_array( $post_types ) ) {
			return;
		}

		foreach ( $post_types as $post_type ) {
			add_filter( 'manage_' . $post_type . '_posts_columns', [ $this, 'add_column' ] );
			add_action( 'manage_' . $post_type . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		}
	}

	/**
	 * Add the SEO column before the date column.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$next = [];
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$next['rankmyseo'] = __( 'SEO', 'rankmyseo' );
			}
			$next[ $key ] = $label;
		}

		if ( ! isset( $next['rankmyseo'] ) ) {
			$next['rankmyseo'] = __( 'SEO', 'rankmyseo' );
		}

		return $next;
	}

	/**
	 * Render the SEO column cell and the data Quick Edit reads.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'rankmyseo' !== $column ) {
			return;
		}

		$meta    = $this->store->read_post( $post_id );
		$robots  = is_array( $meta['robots'] ) ? $meta['robots'] : Robots::defaults();
		$noindex = ! empty( $robots['noindex'] );
		$score   = get_post_meta( $post_id, MetaKeys::SEO_SCORE, true );
		$score   = is_numeric( $score ) ? (string) (int) $score : '';

		$has_keyword = '' !== (string) $meta['focus_keyword'];
		$band        = '' !== $score ? ScoreBands::band( (int) $score, $has_keyword ) : '';
		$band_label  = '' !== $band ? ScoreBands::label( $band ) : '';

		echo '<div class="rankmyseo-seo-col">';
		echo '<span class="rankmyseo-score-slot" data-rankmyseo-analyzer-score="' . esc_attr( $score ) . '">';
		if ( '' !== $score ) {
			printf(
				'<span class="rankmyseo-score-pill %1$s"><span class="rankmyseo-score-value">%2$s</span> <span class="rankmyseo-score-label">%3$s</span></span>',
				esc_attr( ScoreBands::css_class( $band ) ),
				esc_html( $score ),
				esc_html( $band_label )
			);
		} else {
			echo '<span aria-hidden="true">—</span>';
			echo '<span class="screen-reader-text">' . esc_html__( 'No score yet', 'rankmyseo' ) . '</span>';
		}
		echo '</span>';

		if ( $has_keyword ) {
			echo '<span class="rankmyseo-focus">' . esc_html( (string) $meta['focus_keyword'] ) . '</span>';
		}

		if ( $noindex ) {
			echo '<span class="rankmyseo-noindex">' . esc_html__( 'Hidden', 'rankmyseo' ) . '</span>';
		}

		$cornerstone = get_post_meta( $post_id, MetaKeys::CORNERSTONE, true );
		if ( true === $cornerstone || 1 === $cornerstone || '1' === (string) $cornerstone ) {
			echo '<span class="rankmyseo-cornerstone">' . esc_html__( 'Cornerstone', 'rankmyseo' ) . '</span>';
		}

		printf(
			'<span class="hidden rankmyseo-inline-data" data-title="%1$s" data-description="%2$s" data-focus="%3$s" data-noindex="%4$s"></span>',
			esc_attr( (string) $meta['title'] ),
			esc_attr( (string) $meta['description'] ),
			esc_attr( (string) $meta['focus_keyword'] ),
			esc_attr( $noindex ? '1' : '0' )
		);
		echo '</div>';
	}

	/**
	 * Quick Edit fields.
	 *
	 * @param string $column    Column key.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_quick_edit( string $column, string $post_type ): void {
		if ( 'rankmyseo' !== $column || ! $this->supports( $post_type ) ) {
			return;
		}

		echo '<fieldset class="inline-edit-col-right rankmyseo-quick-edit">';
		echo '<div class="inline-edit-col">';
		echo '<input type="hidden" name="rankmyseo_quick_edit" value="1" />';
		echo '<label><span class="title">' . esc_html__( 'Search title', 'rankmyseo' ) . '</span>';
		echo '<span class="input-text-wrap"><input type="text" name="rankmyseo_qe_title" value="" /></span></label>';
		echo '<label><span class="title">' . esc_html__( 'Search description', 'rankmyseo' ) . '</span>';
		echo '<span class="input-text-wrap"><textarea name="rankmyseo_qe_description" rows="2"></textarea></span></label>';
		echo '<label><span class="title">' . esc_html__( 'Focus keyword', 'rankmyseo' ) . '</span>';
		echo '<span class="input-text-wrap"><input type="text" name="rankmyseo_qe_focus" value="" /></span></label>';
		echo '<label class="alignleft"><input type="checkbox" name="rankmyseo_qe_noindex" value="1" /> ';
		echo '<span class="checkbox-title">' . esc_html__( 'Hide from search', 'rankmyseo' ) . '</span></label>';
		echo '</div></fieldset>';
	}

	/**
	 * Bulk Edit noindex control.
	 *
	 * @param string $column    Column key.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public function render_bulk_edit( string $column, string $post_type ): void {
		if ( 'rankmyseo' !== $column || ! $this->supports( $post_type ) ) {
			return;
		}

		echo '<fieldset class="inline-edit-col-right rankmyseo-bulk-edit">';
		echo '<div class="inline-edit-col">';
		echo '<input type="hidden" name="rankmyseo_bulk_edit" value="1" />';
		echo '<label><span class="title">' . esc_html__( 'Search visibility', 'rankmyseo' ) . '</span>';
		echo '<select name="rankmyseo_bulk_noindex">';
		echo '<option value="-1">' . esc_html__( '— No change —', 'rankmyseo' ) . '</option>';
		echo '<option value="0">' . esc_html__( 'Show in search', 'rankmyseo' ) . '</option>';
		echo '<option value="1">' . esc_html__( 'Hide from search', 'rankmyseo' ) . '</option>';
		echo '</select></label>';
		echo '<label><span class="title">' . esc_html__( 'Search title', 'rankmyseo' ) . '</span>';
		echo '<select name="rankmyseo_bulk_title">';
		echo '<option value="-1">' . esc_html__( '— No change —', 'rankmyseo' ) . '</option>';
		echo '<option value="template">' . esc_html__( 'Apply title template', 'rankmyseo' ) . '</option>';
		echo '<option value="clear">' . esc_html__( 'Clear custom title', 'rankmyseo' ) . '</option>';
		echo '</select></label>';
		echo '<label><span class="title">' . esc_html__( 'Search description', 'rankmyseo' ) . '</span>';
		echo '<select name="rankmyseo_bulk_description">';
		echo '<option value="-1">' . esc_html__( '— No change —', 'rankmyseo' ) . '</option>';
		echo '<option value="template">' . esc_html__( 'Apply description template', 'rankmyseo' ) . '</option>';
		echo '<option value="clear">' . esc_html__( 'Clear custom description', 'rankmyseo' ) . '</option>';
		echo '</select></label>';
		echo '<label><span class="title">' . esc_html__( 'Custom search details', 'rankmyseo' ) . '</span>';
		echo '<select name="rankmyseo_bulk_clear_meta">';
		echo '<option value="-1">' . esc_html__( '— No change —', 'rankmyseo' ) . '</option>';
		echo '<option value="1">' . esc_html__( 'Clear custom title, description, and keyword', 'rankmyseo' ) . '</option>';
		echo '</select></label>';
		echo '</div></fieldset>';
	}

	/**
	 * Save Quick Edit and Bulk Edit submissions.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( $this->is_bulk_request() ) {
			$this->save_bulk( $post_id );
			return;
		}

		if ( $this->is_quick_request() ) {
			$this->save_quick( $post_id );
		}
	}

	/**
	 * Save quick.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_quick( int $post_id ): void {
		$nonce = isset( $_POST['_inline_edit'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_inline_edit'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'inlineeditnonce' ) ) {
			return;
		}

		$current                  = $this->store->read_post( $post_id );
		$current['title']         = MetaSanitizer::sanitize_title( $this->post_value( 'rankmyseo_qe_title' ) );
		$current['description']   = MetaSanitizer::sanitize_description( $this->post_value( 'rankmyseo_qe_description' ) );
		$current['focus_keyword'] = MetaSanitizer::sanitize_keyword( $this->post_value( 'rankmyseo_qe_focus' ) );
		$robots                   = is_array( $current['robots'] ) ? Robots::sanitize( $current['robots'] ) : Robots::defaults();
		$robots['noindex']        = '1' === $this->post_value( 'rankmyseo_qe_noindex' );
		$current['robots']        = $robots;
		$current['twitter_card']  = '' !== (string) $current['twitter_card'] ? (string) $current['twitter_card'] : 'summary_large_image';

		$this->store->write_post( $post_id, $current );
	}

	/**
	 * Save bulk.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_bulk( int $post_id ): void {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-posts' ) ) {
			return;
		}

		$noindex = isset( $_REQUEST['rankmyseo_bulk_noindex'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['rankmyseo_bulk_noindex'] ) ) : '-1';
		if ( in_array( $noindex, [ '0', '1' ], true ) ) {
			$this->store->set_post_noindex( $post_id, '1' === $noindex );
		}

		$clear = isset( $_REQUEST['rankmyseo_bulk_clear_meta'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['rankmyseo_bulk_clear_meta'] ) ) : '-1';
		if ( '1' === $clear ) {
			$this->fill->clear_custom( $post_id );
			return;
		}

		$this->apply_bulk_field( $post_id, 'rankmyseo_bulk_title', MissingMetadataQuery::MISSING_TITLE );
		$this->apply_bulk_field( $post_id, 'rankmyseo_bulk_description', MissingMetadataQuery::MISSING_DESCRIPTION );
	}

	/**
	 * Apply or clear one bulk-edit template field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Request key.
	 * @param string $field   title or description.
	 * @return void
	 */
	private function apply_bulk_field( int $post_id, string $key, string $field ): void {
		$value = isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) ) : '-1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Caller verifies the bulk-edit nonce.
		if ( 'template' === $value ) {
			$this->fill->apply_pattern( $post_id, $field );
		}
		if ( 'clear' === $value ) {
			$this->fill->clear_field( $post_id, $field );
		}
	}

	/**
	 * Is quick request.
	 *
	 * @return bool
	 */
	private function is_quick_request(): bool {
		return isset( $_POST['rankmyseo_quick_edit'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['rankmyseo_quick_edit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified before values are saved.
	}

	/**
	 * Is bulk request.
	 *
	 * @return bool
	 */
	private function is_bulk_request(): bool {
		return isset( $_REQUEST['rankmyseo_bulk_edit'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_REQUEST['rankmyseo_bulk_edit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified before values are saved.
	}

	/**
	 * Post value.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private function post_value( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the inline-edit nonce.
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the inline-edit nonce.
	}

	/**
	 * Supports.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function supports( string $post_type ): bool {
		$post_types = get_post_types( [ 'public' => true ] );
		return is_array( $post_types ) && in_array( $post_type, $post_types, true );
	}
}
