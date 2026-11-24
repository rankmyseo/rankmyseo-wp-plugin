<?php
/**
 * Finds published content that is missing search details.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Lists posts, pages, products, and images with an empty SEO field.
 */
final class MissingMetadataQuery {
	public const TYPE_POST       = 'post';
	public const TYPE_PAGE       = 'page';
	public const TYPE_PRODUCT    = 'product';
	public const TYPE_ATTACHMENT = 'attachment';

	public const MISSING_TITLE       = 'title';
	public const MISSING_DESCRIPTION = 'description';
	public const MISSING_KEYWORD     = 'focus_keyword';
	public const MISSING_IMAGE_ALT   = 'image_alt';

	/**
	 * Post types this screen can list.
	 *
	 * @var list<string>
	 */
	public const TYPES = [
		self::TYPE_POST,
		self::TYPE_PAGE,
		self::TYPE_PRODUCT,
		self::TYPE_ATTACHMENT,
	];

	/**
	 * Missing-field filters the screen accepts.
	 *
	 * @var list<string>
	 */
	public const FILTERS = [
		self::MISSING_TITLE,
		self::MISSING_DESCRIPTION,
		self::MISSING_KEYWORD,
		self::MISSING_IMAGE_ALT,
	];

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
	 * Return one page of rows that are missing at least one requested field.
	 *
	 * @param array<string, mixed> $args missing, post_type, search, page, per_page.
	 * @return array<string, mixed>
	 */
	public function search( array $args ): array {
		$missing  = $this->filter_name( isset( $args['missing'] ) ? (string) $args['missing'] : '' );
		$type     = $this->type_name( isset( $args['post_type'] ) ? (string) $args['post_type'] : '' );
		$search   = trim( (string) ( $args['search'] ?? '' ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = (int) ( $args['per_page'] ?? 20 );
		if ( $per_page < 1 ) {
			$per_page = 20;
		}
		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		$rows = [];
		foreach ( $this->collect( $type ) as $post ) {
			$item = $this->present( $post );
			if ( [] === $item['missing'] ) {
				continue;
			}
			if ( '' !== $missing && ! in_array( $missing, $item['missing'], true ) ) {
				continue;
			}
			if ( '' !== $search && ! $this->matches_search( $item, $search ) ) {
				continue;
			}
			$rows[] = $item;
		}

		usort(
			$rows,
			static function ( array $left, array $right ): int {
				return strcasecmp( (string) $left['title'], (string) $right['title'] );
			}
		);

		$total  = count( $rows );
		$offset = ( $page - 1 ) * $per_page;

		return [
			'items'      => array_slice( $rows, $offset, $per_page ),
			'total'      => $total,
			'page'       => $page,
			'per_page'   => $per_page,
			'missing'    => $missing,
			'post_type'  => $type,
			'search'     => $search,
			'post_types' => $this->type_choices(),
		];
	}

	/**
	 * Present one post, or null when it is not a supported published item.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! is_object( $post ) || ! $this->is_listed( $post ) ) {
			return null;
		}

		return $this->present( $post );
	}

	/**
	 * Public row for one post.
	 *
	 * @param object $post Post object.
	 * @return array<string, mixed>
	 */
	public function present( object $post ): array {
		$id    = (int) $post->ID;
		$meta  = $this->store->read_post( $id );
		$type  = (string) $post->post_type;
		$image = $this->image_id( $post );
		$alt   = $image > 0 ? trim( (string) get_post_meta( $image, '_wp_attachment_image_alt', true ) ) : '';
		$title = trim( (string) $meta['title'] );
		$desc  = trim( (string) $meta['description'] );
		$focus = trim( (string) $meta['focus_keyword'] );

		$missing = [];
		if ( '' === $title ) {
			$missing[] = self::MISSING_TITLE;
		}
		if ( '' === $desc ) {
			$missing[] = self::MISSING_DESCRIPTION;
		}
		if ( '' === $focus ) {
			$missing[] = self::MISSING_KEYWORD;
		}
		if ( $image > 0 && '' === $alt ) {
			$missing[] = self::MISSING_IMAGE_ALT;
		}

		$permalink = (string) get_permalink( $post );
		if ( '' === $permalink ) {
			$permalink = home_url( '?p=' . $id );
		}

		return [
			'id'            => $id,
			'post_type'     => $type,
			'type_label'    => $this->type_label( $type ),
			'title'         => '' !== (string) $post->post_title ? (string) $post->post_title : __( '(no title)', 'rankmyseo' ),
			'permalink'     => $permalink,
			'edit_link'     => admin_url( 'post.php?post=' . $id . '&action=edit' ),
			'seo_title'     => $title,
			'description'   => $desc,
			'focus_keyword' => $focus,
			'image_id'      => $image > 0 ? $image : null,
			'image_alt'     => $alt,
			'missing'       => $missing,
		];
	}

	/**
	 * Attachment that owns the image description for this row.
	 *
	 * @param object $post Post object.
	 * @return int
	 */
	public function image_id( object $post ): int {
		$id = (int) $post->ID;
		if ( self::TYPE_ATTACHMENT === (string) $post->post_type ) {
			return $id;
		}

		$thumb = (int) get_post_meta( $id, '_thumbnail_id', true );
		if ( $thumb <= 0 || ! is_object( get_post( $thumb ) ) ) {
			return 0;
		}

		return $thumb;
	}

	/**
	 * Whether a post belongs on this screen.
	 *
	 * @param object $post Post object.
	 * @return bool
	 */
	private function is_listed( object $post ): bool {
		$type = (string) $post->post_type;
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return false;
		}

		$status = (string) $post->post_status;
		if ( self::TYPE_ATTACHMENT === $type ) {
			return in_array( $status, [ 'publish', 'inherit' ], true );
		}

		return 'publish' === $status;
	}

	/**
	 * Load supported posts for one type, or every supported type.
	 *
	 * @param string $type Empty for every type.
	 * @return list<object>
	 */
	private function collect( string $type ): array {
		$types = '' !== $type ? [ $type ] : self::TYPES;
		$found = [];

		foreach ( $types as $name ) {
			$statuses = self::TYPE_ATTACHMENT === $name ? [ 'publish', 'inherit' ] : [ 'publish' ];
			$posts    = get_posts(
				[
					'post_type'      => [ $name ],
					'post_status'    => $statuses,
					'posts_per_page' => -1,
				]
			);
			if ( ! is_array( $posts ) ) {
				continue;
			}
			foreach ( $posts as $post ) {
				if ( is_object( $post ) && $this->is_listed( $post ) ) {
					$found[] = $post;
				}
			}
		}

		return $found;
	}

	/**
	 * Case-insensitive match on the visible title and the slug.
	 *
	 * @param array<string, mixed> $item   Row.
	 * @param string               $search Search text.
	 * @return bool
	 */
	private function matches_search( array $item, string $search ): bool {
		$post = get_post( (int) $item['id'] );
		$slug = is_object( $post ) ? (string) $post->post_name : '';
		$hay  = strtolower( (string) $item['title'] . ' ' . $slug );
		return str_contains( $hay, strtolower( $search ) );
	}

	/**
	 * Normalize a missing-field filter. Empty means any missing field.
	 *
	 * @param string $missing Raw filter.
	 * @return string
	 */
	private function filter_name( string $missing ): string {
		$missing = sanitize_key( $missing );
		if ( in_array( $missing, [ '', 'any' ], true ) ) {
			return '';
		}

		return in_array( $missing, self::FILTERS, true ) ? $missing : '';
	}

	/**
	 * Normalize a post type filter.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	private function type_name( string $type ): string {
		$type = sanitize_key( $type );
		return in_array( $type, self::TYPES, true ) ? $type : '';
	}

	/**
	 * Choices for the post type filter.
	 *
	 * @return list<array{name: string, label: string}>
	 */
	private function type_choices(): array {
		$choices = [];
		foreach ( self::TYPES as $type ) {
			$choices[] = [
				'name'  => $type,
				'label' => $this->type_label( $type ),
			];
		}
		return $choices;
	}

	/**
	 * Plain label for a post type.
	 *
	 * @param string $type Post type.
	 * @return string
	 */
	private function type_label( string $type ): string {
		switch ( $type ) {
			case self::TYPE_PAGE:
				return __( 'Pages', 'rankmyseo' );
			case self::TYPE_PRODUCT:
				return __( 'Products', 'rankmyseo' );
			case self::TYPE_ATTACHMENT:
				return __( 'Images', 'rankmyseo' );
			default:
				return __( 'Posts', 'rankmyseo' );
		}
	}
}
