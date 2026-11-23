<?php
/**
 * Creates a 301 when a published post or page slug changes.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Listens to post_updated and skips drafts, revisions, and autosaves.
 */
final class SlugWatcher {
	/**
	 * Redirect service.
	 *
	 * @var RedirectService
	 */
	private RedirectService $redirects;

	/**
	 * Module settings.
	 *
	 * @var RedirectSettings
	 */
	private RedirectSettings $settings;

	/**
	 * Path normalizer.
	 *
	 * @var PathNormalizer
	 */
	private PathNormalizer $paths;

	/**
	 * Constructor.
	 *
	 * @param RedirectService  $redirects Redirect service.
	 * @param RedirectSettings $settings  Module settings.
	 * @param PathNormalizer   $paths     Path normalizer.
	 */
	public function __construct( RedirectService $redirects, RedirectSettings $settings, PathNormalizer $paths ) {
		$this->redirects = $redirects;
		$this->settings  = $settings;
		$this->paths     = $paths;
	}

	/**
	 * WordPress post_updated callback.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $after   Post after the update.
	 * @param \WP_Post $before  Post before the update.
	 * @return void
	 */
	public function on_post_updated( int $post_id, \WP_Post $after, \WP_Post $before ): void {
		$this->handle_slug_change(
			$post_id,
			[
				'post_name'   => (string) $before->post_name,
				'post_status' => (string) $before->post_status,
				'post_type'   => (string) $before->post_type,
			],
			[
				'post_name'   => (string) $after->post_name,
				'post_status' => (string) $after->post_status,
				'post_type'   => (string) $after->post_type,
			]
		);
	}

	/**
	 * Create an automatic redirect when a public slug changes.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $before  Previous post fields.
	 * @param array<string, mixed> $after   Updated post fields.
	 * @return array<string, mixed>|null
	 */
	public function handle_slug_change( int $post_id, array $before, array $after ): ?array {
		if ( ! $this->settings->auto_slug_enabled() ) {
			return null;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return null;
		}

		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return null;
		}

		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
			return null;
		}

		$old_status = (string) ( $before['post_status'] ?? '' );
		$new_status = (string) ( $after['post_status'] ?? '' );
		$post_type  = (string) ( $after['post_type'] ?? $before['post_type'] ?? '' );

		if ( 'publish' !== $old_status || 'publish' !== $new_status ) {
			return null;
		}

		if ( 'revision' === $post_type || ! $this->is_supported_type( $post_type ) ) {
			return null;
		}

		$old_slug = (string) ( $before['post_name'] ?? '' );
		$new_slug = (string) ( $after['post_name'] ?? '' );
		if ( '' === $old_slug || '' === $new_slug || $old_slug === $new_slug ) {
			return null;
		}

		$paths = $this->paths_for( $post_id, $old_slug, $new_slug, $post_type );
		if ( $paths['from'] === $paths['to'] ) {
			return null;
		}

		return $this->redirects->create_auto_slug( $paths['from'], $paths['to'] );
	}

	/**
	 * Whether the post type should receive automatic redirects.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	private function is_supported_type( string $post_type ): bool {
		if ( in_array( $post_type, [ 'revision', 'nav_menu_item', 'attachment', 'customize_changeset' ], true ) ) {
			return false;
		}

		if ( function_exists( 'is_post_type_viewable' ) ) {
			return (bool) is_post_type_viewable( $post_type );
		}

		return in_array( $post_type, [ 'post', 'page' ], true );
	}

	/**
	 * Resolve the old and new public paths.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $old_slug Previous slug.
	 * @param string $new_slug New slug.
	 * @param string $post_type Post type.
	 * @return array{from: string, to: string}
	 */
	private function paths_for( int $post_id, string $old_slug, string $new_slug, string $post_type ): array {
		$from = '/' . $old_slug;
		$to   = '/' . $new_slug;

		if ( 'page' === $post_type && function_exists( 'get_page_uri' ) ) {
			$uri = get_page_uri( $post_id );
			if ( is_string( $uri ) && '' !== $uri ) {
				$trimmed = trim( $uri, '/' );
				$to      = '/' . $trimmed;
				$parent  = '';
				$slash   = strrpos( $trimmed, '/' );
				if ( false !== $slash ) {
					$parent = substr( $trimmed, 0, $slash );
					$from   = '/' . $parent . '/' . $old_slug;
				}
			}
		} elseif ( function_exists( 'get_permalink' ) ) {
			$permalink = get_permalink( $post_id );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				$to = $permalink;
			}
		}

		/**
		 * Filter the paths used for an automatic slug redirect.
		 *
		 * @param array{from: string, to: string} $paths     Old and new paths.
		 * @param int                             $post_id   Post ID.
		 * @param string                          $old_slug  Previous slug.
		 * @param string                          $new_slug  New slug.
		 * @param string                          $post_type Post type.
		 */
		$filtered = apply_filters(
			'rankmyseo_slug_redirect_paths',
			[
				'from' => $from,
				'to'   => $to,
			],
			$post_id,
			$old_slug,
			$new_slug,
			$post_type
		);

		if ( ! is_array( $filtered ) ) {
			$filtered = [
				'from' => $from,
				'to'   => $to,
			];
		}

		return [
			'from' => $this->paths->normalize_path( (string) ( $filtered['from'] ?? $from ) ),
			'to'   => $this->paths->normalize_path( (string) ( $filtered['to'] ?? $to ) ),
		];
	}
}
