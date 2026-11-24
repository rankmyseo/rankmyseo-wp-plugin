<?php
/**
 * Builds the local internal-link index from published posts and pages.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Links;

use RankMySEO\Modules\Meta\MetaKeys;
use RankMySEO\Modules\Meta\MetaSanitizer;
use WP_Post;

/**
 * Indexes on save and in chunks. Nothing here contacts another server.
 */
final class LinkIndexer {
	/**
	 * How many posts one reindex request handles.
	 */
	public const BATCH_DEFAULT = 20;

	/**
	 * Count store.
	 *
	 * @var LinkRepository
	 */
	private LinkRepository $repository;

	/**
	 * HTML parser.
	 *
	 * @var LinkParser
	 */
	private LinkParser $parser;

	/**
	 * Suggestion ranker.
	 *
	 * @var LinkRanker
	 */
	private LinkRanker $ranker;

	/**
	 * Permalink map cached for one batch.
	 *
	 * @var array<string, int>|null
	 */
	private ?array $permalinks = null;

	/**
	 * Constructor.
	 *
	 * @param LinkRepository  $repository Count store.
	 * @param LinkParser|null $parser     Optional parser.
	 * @param LinkRanker|null $ranker     Optional ranker.
	 */
	public function __construct( LinkRepository $repository, ?LinkParser $parser = null, ?LinkRanker $ranker = null ) {
		$this->repository = $repository;
		$this->parser     = $parser ?? new LinkParser();
		$this->ranker     = $ranker ?? new LinkRanker();
	}

	/**
	 * Index a post whenever it is saved.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post', [ $this, 'on_save' ], 30, 2 );
		add_action( 'before_delete_post', [ $this, 'on_delete' ] );
	}

	/**
	 * Refresh one post after WordPress has stored its content.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object when provided.
	 * @return void
	 */
	public function on_save( int $post_id, $post = null ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$this->permalinks = null;
		if ( ! $this->is_indexable( $post ) ) {
			$this->repository->remove_post( (int) $post->ID );
			return;
		}
		$this->index_post( $post );
	}

	/**
	 * Drop a deleted post from the index.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_delete( int $post_id ): void {
		$this->repository->remove_post( $post_id );
	}

	/**
	 * Index one published post.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function index_post( WP_Post $post ): void {
		$site    = (string) home_url( '/' );
		$targets = [];
		foreach ( $this->parser->internal_urls( (string) $post->post_content, $site ) as $url ) {
			$target = $this->resolve( $url );
			if ( $target > 0 ) {
				$targets[] = $target;
			}
		}
		$this->repository->replace_outgoing(
			(int) $post->ID,
			$targets,
			$this->is_cornerstone( (int) $post->ID )
		);
	}

	/**
	 * Index a slice of published posts and pages.
	 *
	 * @param int $offset Zero-based offset.
	 * @param int $limit  Batch size, 1–50.
	 * @return array{processed: int, nextOffset: int, done: bool, total: int}
	 */
	public function reindex( int $offset, int $limit ): array {
		$offset           = max( 0, $offset );
		$limit            = max( 1, min( 50, $limit > 0 ? $limit : self::BATCH_DEFAULT ) );
		$posts            = $this->published_posts();
		$total            = count( $posts );
		$slice            = array_slice( $posts, $offset, $limit );
		$this->permalinks = null;
		foreach ( $slice as $post ) {
			if ( $this->is_indexable( $post ) ) {
				$this->index_post( $post );
			} else {
				$this->repository->remove_post( (int) $post->ID );
			}
		}
		$next = $offset + count( $slice );
		return [
			'processed'  => count( $slice ),
			'nextOffset' => $next,
			'done'       => $next >= $total,
			'total'      => $total,
		];
	}

	/**
	 * Published indexable posts and pages with no incoming internal links.
	 *
	 * @param int $limit Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function list_orphans( int $limit ): array {
		$limit = max( 1, $limit );
		$rows  = [];
		foreach ( $this->orphan_posts() as $post ) {
			$rows[] = $this->public_post( $post );
			if ( count( $rows ) >= $limit ) {
				break;
			}
		}
		return $rows;
	}

	/**
	 * How many published indexable posts and pages have no incoming links.
	 *
	 * @return int
	 */
	public function orphan_count(): int {
		return count( $this->orphan_posts() );
	}

	/**
	 * How many published posts and pages are marked cornerstone.
	 *
	 * @return int
	 */
	public function cornerstone_count(): int {
		$count = 0;
		foreach ( $this->published_posts() as $post ) {
			if ( $this->is_indexable( $post ) && $this->is_cornerstone( (int) $post->ID ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Suggest internal links for the editor.
	 *
	 * @param array<string, mixed> $query post_id, focus, secondary, title, slug.
	 * @return list<array<string, mixed>>
	 */
	public function suggestions( array $query ): array {
		$post_id   = isset( $query['post_id'] ) ? (int) $query['post_id'] : 0;
		$post      = $post_id > 0 ? get_post( $post_id ) : null;
		$focus     = trim( (string) ( $query['focus'] ?? '' ) );
		$title     = trim( (string) ( $query['title'] ?? '' ) );
		$slug      = trim( (string) ( $query['slug'] ?? '' ) );
		$secondary = $query['secondary'] ?? [];
		if ( ! is_array( $secondary ) ) {
			$secondary = [];
		}
		if ( $post instanceof WP_Post ) {
			if ( '' === $focus ) {
				$focus = (string) get_post_meta( $post_id, MetaKeys::FOCUS_KEYWORD, true );
			}
			if ( '' === $title ) {
				$title = (string) $post->post_title;
			}
			if ( '' === $slug ) {
				$slug = (string) $post->post_name;
			}
			if ( [] === $secondary ) {
				$secondary = MetaSanitizer::sanitize_keywords( get_post_meta( $post_id, MetaKeys::SECONDARY_KEYWORDS, true ) );
			}
		}
		$source_terms = $post instanceof WP_Post ? $this->term_slugs( (int) $post->ID ) : [];
		$english      = $this->is_english( function_exists( 'get_locale' ) ? (string) get_locale() : 'en' );
		$candidates   = [];
		foreach ( $this->published_posts() as $candidate ) {
			if ( ! $this->is_indexable( $candidate ) ) {
				continue;
			}
			$id = (int) $candidate->ID;
			if ( $id === $post_id ) {
				continue;
			}
			$counts       = $this->repository->counts_for( $id );
			$candidates[] = [
				'id'                 => $id,
				'title'              => (string) $candidate->post_title,
				'slug'               => (string) $candidate->post_name,
				'url'                => (string) get_permalink( $candidate ),
				'focus_keyword'      => (string) get_post_meta( $id, MetaKeys::FOCUS_KEYWORD, true ),
				'secondary_keywords' => MetaSanitizer::sanitize_keywords( get_post_meta( $id, MetaKeys::SECONDARY_KEYWORDS, true ) ),
				'terms'              => $this->term_slugs( $id ),
				'cornerstone'        => $this->is_cornerstone( $id ),
				'incoming'           => $counts['incoming'],
			];
		}
		return $this->ranker->rank(
			[
				'id'                 => $post_id,
				'title'              => $title,
				'slug'               => $slug,
				'focus_keyword'      => $focus,
				'secondary_keywords' => $secondary,
				'terms'              => $source_terms,
			],
			$candidates,
			$english
		);
	}

	/**
	 * Published indexable posts with a stored incoming count of zero.
	 *
	 * A post is an orphan only after it has been indexed, so an empty index
	 * does not report every page as an orphan.
	 *
	 * @return list<WP_Post>
	 */
	private function orphan_posts(): array {
		$zeros = array_fill_keys( $this->repository->zero_incoming_ids(), true );
		$posts = [];
		foreach ( $this->published_posts() as $post ) {
			if ( ! $this->is_indexable( $post ) ) {
				continue;
			}
			if ( isset( $zeros[ (int) $post->ID ] ) ) {
				$posts[] = $post;
			}
		}
		return $posts;
	}

	/**
	 * Published posts and pages.
	 *
	 * @return list<WP_Post>
	 */
	private function published_posts(): array {
		$posts = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);
		$list  = [];
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$list[] = $post;
			}
		}
		usort(
			$list,
			static fn( WP_Post $left, WP_Post $right ): int => (int) $left->ID <=> (int) $right->ID
		);
		return $list;
	}

	/**
	 * Whether the post can appear in search and in the link index.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool
	 */
	private function is_indexable( WP_Post $post ): bool {
		if ( 'publish' !== $post->post_status ) {
			return false;
		}
		if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
			return false;
		}
		$robots = get_post_meta( (int) $post->ID, MetaKeys::ROBOTS, true );
		return ! ( is_array( $robots ) && ! empty( $robots['noindex'] ) );
	}

	/**
	 * Whether the cornerstone flag is stored.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_cornerstone( int $post_id ): bool {
		$value = get_post_meta( $post_id, MetaKeys::CORNERSTONE, true );
		return true === $value || 1 === $value || '1' === (string) $value;
	}

	/**
	 * Category slugs for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return list<string>
	 */
	private function term_slugs( int $post_id ): array {
		if ( ! function_exists( 'get_the_category' ) ) {
			return [];
		}
		$terms = get_the_category( $post_id );
		if ( ! is_array( $terms ) ) {
			return [];
		}
		$slugs = [];
		foreach ( $terms as $term ) {
			if ( is_object( $term ) && '' !== (string) $term->slug ) {
				$slugs[] = (string) $term->slug;
			}
		}
		return $slugs;
	}

	/**
	 * Resolve an internal URL to a published post ID.
	 *
	 * @param string $url Absolute URL.
	 * @return int
	 */
	private function resolve( string $url ): int {
		$normalized = $this->parser->normalize( $url );
		if ( function_exists( 'url_to_postid' ) ) {
			$id = (int) url_to_postid( $url );
			if ( $id > 0 && $this->target_ok( $id ) ) {
				return $id;
			}
		}
		$map = $this->permalink_map();
		$id  = $map[ $normalized ] ?? 0;
		return $this->target_ok( $id ) ? $id : 0;
	}

	/**
	 * Whether a resolved ID is a published post or page.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function target_ok( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		$post = get_post( $post_id );
		return $post instanceof WP_Post && 'publish' === $post->post_status && in_array( $post->post_type, [ 'post', 'page' ], true );
	}

	/**
	 * Map normalized permalinks to post IDs.
	 *
	 * @return array<string, int>
	 */
	private function permalink_map(): array {
		if ( null !== $this->permalinks ) {
			return $this->permalinks;
		}
		$map = [];
		foreach ( $this->published_posts() as $post ) {
			$map[ $this->parser->normalize( (string) get_permalink( $post ) ) ] = (int) $post->ID;
		}
		$this->permalinks = $map;
		return $map;
	}

	/**
	 * Public row for the dashboard and the assistant.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function public_post( WP_Post $post ): array {
		return [
			'id'        => (int) $post->ID,
			'title'     => (string) $post->post_title,
			'slug'      => (string) $post->post_name,
			'type'      => (string) $post->post_type,
			'url'       => (string) get_permalink( $post ),
			'edit_link' => (string) get_edit_post_link( $post, 'raw' ),
		];
	}

	/**
	 * English locales use word forms. Others stay exact.
	 *
	 * @param string $locale Locale code.
	 * @return bool
	 */
	private function is_english( string $locale ): bool {
		$code = strtolower( str_replace( '_', '-', '' !== $locale ? $locale : 'en' ) );
		return str_starts_with( $code, 'en' );
	}
}
