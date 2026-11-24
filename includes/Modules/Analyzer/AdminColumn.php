<?php
/**
 * Sortable SEO score column and list-table filters.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

use RankMySEO\Modules\Meta\MetaKeys;
use WP_Query;

/**
 * Posts and pages list filters for score and focus keyword.
 */
final class AdminColumn {
	/**
	 * Register list-table hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_sortable' ] );
		add_action( 'restrict_manage_posts', [ $this, 'render_filter' ] );
		add_action( 'pre_get_posts', [ $this, 'apply_query' ] );
		add_action( 'admin_head', [ $this, 'print_styles' ] );
	}

	/**
	 * Make the SEO column sortable on public post types.
	 *
	 * @return void
	 */
	public function register_sortable(): void {
		$post_types = get_post_types( [ 'public' => true ] );
		if ( ! is_array( $post_types ) ) {
			return;
		}
		foreach ( $post_types as $post_type ) {
			add_filter( 'manage_edit-' . $post_type . '_sortable_columns', [ $this, 'sortable_columns' ] );
		}
	}

	/**
	 * Add the score sort key.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['rankmyseo'] = 'rankmyseo_seo_score';
		return $columns;
	}

	/**
	 * Dropdown for "Needs SEO work" and "Missing focus keyword".
	 *
	 * @param string $post_type Current list post type.
	 * @return void
	 */
	public function render_filter( string $post_type ): void {
		$public = get_post_types( [ 'public' => true ] );
		if ( ! is_array( $public ) || ! in_array( $post_type, $public, true ) ) {
			return;
		}

		$current = $this->requested_filter();
		echo '<label class="screen-reader-text" for="rankmyseo_seo_filter">' . esc_html__( 'Filter by SEO score', 'rankmyseo' ) . '</label>';
		echo '<select name="rankmyseo_seo_filter" id="rankmyseo_seo_filter">';
		echo '<option value="">' . esc_html__( 'All SEO scores', 'rankmyseo' ) . '</option>';
		printf(
			'<option value="needs_work"%1$s>%2$s</option>',
			selected( $current, 'needs_work', false ),
			esc_html__( 'Needs SEO work', 'rankmyseo' )
		);
		printf(
			'<option value="missing_keyword"%1$s>%2$s</option>',
			selected( $current, 'missing_keyword', false ),
			esc_html__( 'Missing focus keyword', 'rankmyseo' )
		);
		printf(
			'<option value="cornerstone"%1$s>%2$s</option>',
			selected( $current, 'cornerstone', false ),
			esc_html__( 'Cornerstone content', 'rankmyseo' )
		);
		echo '</select>';
	}

	/**
	 * Apply the score sort and the SEO filter to the main list query.
	 *
	 * @param WP_Query    $query  Current query.
	 * @param string|null $filter Optional filter override for tests.
	 * @return void
	 */
	public function apply_query( $query, ?string $filter = null ): void {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		$filter = $filter ?? $this->requested_filter();
		if ( 'needs_work' === $filter ) {
			$query->set(
				'meta_query',
				[
					[
						'key'     => MetaKeys::SEO_SCORE,
						'value'   => 80,
						'compare' => '<',
						'type'    => 'NUMERIC',
					],
				]
			);
		} elseif ( 'cornerstone' === $filter ) {
			$query->set(
				'meta_query',
				[
					[
						'key'     => MetaKeys::CORNERSTONE,
						'value'   => '1',
						'compare' => '=',
					],
				]
			);
		} elseif ( 'missing_keyword' === $filter ) {
			$query->set(
				'meta_query',
				[
					'relation' => 'OR',
					[
						'key'     => MetaKeys::FOCUS_KEYWORD,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => MetaKeys::FOCUS_KEYWORD,
						'value'   => '',
						'compare' => '=',
					],
				]
			);
		}

		$orderby = (string) $query->get( 'orderby' );
		if ( 'rankmyseo_seo_score' === $orderby && 'missing_keyword' !== $filter ) {
			$query->set( 'meta_key', MetaKeys::SEO_SCORE );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Inline pill styles for the posts list. Text stays ink-colored for contrast.
	 *
	 * @return void
	 */
	public function print_styles(): void {
		if ( ! $this->is_list_screen() ) {
			return;
		}
		echo '<style id="rankmyseo-score-pills">';
		echo '.rankmyseo-score-pill{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-weight:600;font-variant-numeric:tabular-nums;color:#1b1e24;background:#f1f2f4;}';
		echo '.rankmyseo-score-pill.is-good{background:#e8f7f0;}';
		echo '.rankmyseo-score-pill.is-needs-work{background:#fef3c7;}';
		echo '.rankmyseo-score-pill.is-poor{background:#fdecec;}';
		echo '.rankmyseo-score-pill.is-no-keyword{background:#f1f2f4;}';
		echo '.rankmyseo-focus{display:block;color:#1b1e24;}';
		echo '.rankmyseo-cornerstone{display:inline-flex;margin-top:4px;padding:1px 8px;border-radius:999px;background:#ff5c22;color:#1b1e24;font-size:11px;font-weight:600;}';
		echo '</style>';
	}

	/**
	 * Read the SEO filter from the list request.
	 *
	 * @return string
	 */
	public function requested_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table filter.
		$filter = isset( $_GET['rankmyseo_seo_filter'] ) ? sanitize_key( wp_unslash( (string) $_GET['rankmyseo_seo_filter'] ) ) : '';
		return in_array( $filter, [ 'needs_work', 'missing_keyword', 'cornerstone' ], true ) ? $filter : '';
	}

	/**
	 * Whether the current admin screen is a public post list.
	 *
	 * @return bool
	 */
	private function is_list_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return is_admin();
		}
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen ) {
			return false;
		}
		if ( 'edit' !== $screen->base ) {
			return false;
		}
		$public = get_post_types( [ 'public' => true ] );
		return is_array( $public ) && in_array( (string) $screen->post_type, $public, true );
	}
}
