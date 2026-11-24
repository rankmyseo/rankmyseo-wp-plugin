<?php
/**
 * Matches cached RankMySEO insights to the post open in the editor.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

use RankMySEO\Modules\AiActions\ActionRepository;

/**
 * Reads the 15-minute insights cache and does not start a new collection.
 *
 * Local mode returns an empty match and never touches the HTTP client.
 * The site token stays in PHP.
 */
final class EditorInsightReader {
	/**
	 * Page-update actions the editor knows how to explain.
	 *
	 * @var list<string>
	 */
	private const ACTIONS = [
		'refresh',
		'tune',
		'expand',
		'rewrite',
		'merge',
		'wait',
	];

	/**
	 * Insights cache.
	 *
	 * @var InsightsCache
	 */
	private InsightsCache $insights;

	/**
	 * Approval queue.
	 *
	 * @var ActionRepository
	 */
	private ActionRepository $actions;

	/**
	 * Constructor.
	 *
	 * @param InsightsCache    $insights Insights cache.
	 * @param ActionRepository $actions  Approval queue.
	 */
	public function __construct( InsightsCache $insights, ActionRepository $actions ) {
		$this->insights = $insights;
		$this->actions  = $actions;
	}

	/**
	 * Match cached keywords and page actions to one post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     Optional URL override.
	 * @return array<string, mixed>
	 */
	public function for_post( int $post_id, string $url = '' ): array {
		$cache     = $this->insights->peek();
		$connected = ! empty( $cache['connected'] );
		$permalink = '' !== $url ? $url : ( $post_id > 0 ? (string) get_permalink( $post_id ) : '' );
		$keyword   = $this->keyword( $cache, $post_id, $permalink );
		$page      = $this->page_action( $cache, $post_id, $permalink );
		$pending   = $post_id > 0 ? $this->pending_count( $post_id ) : 0;
		$issues    = isset( $cache['issues'] ) && is_array( $cache['issues'] ) ? $cache['issues'] : [];

		return [
			'connected'     => $connected,
			'matched'       => null !== $keyword || null !== $page,
			'keyword'       => $keyword,
			'page_action'   => $page,
			'pending_count' => $pending,
			'issue_count'   => count( $issues ),
			'queue_url'     => admin_url( 'admin.php?page=rankmyseo-ai-queue' ),
		];
	}

	/**
	 * First matching keyword, preferring one with a real position.
	 *
	 * @param array<string, mixed> $cache     Cached insights.
	 * @param int                  $post_id   Post ID.
	 * @param string               $permalink Page URL.
	 * @return array<string, mixed>|null
	 */
	private function keyword( array $cache, int $post_id, string $permalink ): ?array {
		$keywords = isset( $cache['keywords'] ) && is_array( $cache['keywords'] ) ? $cache['keywords'] : [];
		$rows     = isset( $keywords['rows'] ) && is_array( $keywords['rows'] ) ? $keywords['rows'] : [];
		$chosen   = null;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! $this->matches( $row, $post_id, $permalink ) ) {
				continue;
			}
			$chosen = $row;
			if ( isset( $row['position'] ) && is_int( $row['position'] ) ) {
				break;
			}
		}

		if ( null === $chosen ) {
			return null;
		}

		$position = isset( $chosen['position'] ) && is_int( $chosen['position'] ) ? $chosen['position'] : null;
		$outside  = null === $position && ! empty( $chosen['outside_top_20'] );
		$delta    = isset( $chosen['positions_gained'] ) && is_int( $chosen['positions_gained'] ) ? $chosen['positions_gained'] : null;

		return [
			'keyword'          => (string) ( $chosen['keyword'] ?? '' ),
			'position'         => $position,
			'outside_top_20'   => $outside,
			'positions_gained' => $delta,
		];
	}

	/**
	 * First matching page-update action.
	 *
	 * @param array<string, mixed> $cache     Cached insights.
	 * @param int                  $post_id   Post ID.
	 * @param string               $permalink Page URL.
	 * @return array<string, mixed>|null
	 */
	private function page_action( array $cache, int $post_id, string $permalink ): ?array {
		$rows = isset( $cache['page_actions'] ) && is_array( $cache['page_actions'] ) ? $cache['page_actions'] : [];
		if ( [] === $rows && isset( $cache['page_updates'] ) && is_array( $cache['page_updates'] ) ) {
			$rows = $cache['page_updates'];
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! $this->matches( $row, $post_id, $permalink ) ) {
				continue;
			}
			$action = sanitize_key( (string) ( $row['action'] ?? '' ) );
			if ( ! in_array( $action, self::ACTIONS, true ) ) {
				continue;
			}
			$summary = trim( (string) ( $row['summary'] ?? '' ) );
			if ( '' === $summary ) {
				$summary = $this->fallback( $action );
			}
			return [
				'action'  => $action,
				'label'   => $this->label( $action ),
				'summary' => $summary,
			];
		}

		return null;
	}

	/**
	 * Whether a cached row belongs to this post.
	 *
	 * @param array<string, mixed> $row       Cached row.
	 * @param int                  $post_id   Post ID.
	 * @param string               $permalink Page URL.
	 * @return bool
	 */
	private function matches( array $row, int $post_id, string $permalink ): bool {
		if ( $post_id > 0 && isset( $row['wp_post_id'] ) && is_int( $row['wp_post_id'] ) && $row['wp_post_id'] === $post_id ) {
			return true;
		}
		$url = isset( $row['url'] ) ? (string) $row['url'] : '';
		if ( '' === $url || '' === $permalink ) {
			return false;
		}
		return $this->key( $url ) === $this->key( $permalink );
	}

	/**
	 * Host-aware path key. Another site's URL does not match this one.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function key( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return strtolower( untrailingslashit( $url ) );
		}
		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$path = '/' . trim( $path, '/' );
		if ( '/' !== $path ) {
			$path = untrailingslashit( $path );
		}
		$home      = wp_parse_url( home_url( '/' ) );
		$home_host = is_array( $home ) && isset( $home['host'] ) ? strtolower( (string) $home['host'] ) : '';
		if ( '' !== $host && '' !== $home_host && $host !== $home_host ) {
			return 'other:' . $host . $path;
		}
		return $path;
	}

	/**
	 * Pending and stale suggestions for this page.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function pending_count( int $post_id ): int {
		$count = 0;
		foreach ( [ 'pending', 'stale' ] as $status ) {
			foreach ( $this->actions->list_actions( $status ) as $action ) {
				if ( (int) ( $action['wp_post_id'] ?? 0 ) === $post_id ) {
					++$count;
				}
			}
		}
		return $count;
	}

	/**
	 * Short badge label.
	 *
	 * @param string $action Page action.
	 * @return string
	 */
	private function label( string $action ): string {
		switch ( $action ) {
			case 'refresh':
				return __( 'Refresh', 'rankmyseo' );
			case 'tune':
				return __( 'Tune', 'rankmyseo' );
			case 'expand':
				return __( 'Expand', 'rankmyseo' );
			case 'rewrite':
				return __( 'Rewrite', 'rankmyseo' );
			case 'merge':
				return __( 'Merge', 'rankmyseo' );
			default:
				return __( 'Wait', 'rankmyseo' );
		}
	}

	/**
	 * Plain explanation used when the cache has no summary.
	 *
	 * @param string $action Page action.
	 * @return string
	 */
	private function fallback( string $action ): string {
		switch ( $action ) {
			case 'refresh':
				return __( 'Update this page so it stays current.', 'rankmyseo' );
			case 'tune':
				return __( 'Small edits can help this page.', 'rankmyseo' );
			case 'expand':
				return __( 'This page needs more useful detail.', 'rankmyseo' );
			case 'rewrite':
				return __( 'Rewriting this page is the next step.', 'rankmyseo' );
			case 'merge':
				return __( 'This page overlaps another page. Consider combining them.', 'rankmyseo' );
			default:
				return __( 'Leave this page as it is for now.', 'rankmyseo' );
		}
	}
}
