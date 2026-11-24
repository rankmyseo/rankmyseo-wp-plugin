<?php
/**
 * Fills empty SEO fields from the site's title patterns.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Renders local templates and writes only the fields an editor asked to fill.
 *
 * This class never calls RankMySEO. The rendered text is saved as the custom
 * field, so the row is no longer missing that detail.
 */
final class TemplateFill {
	/**
	 * Fields a template can fill.
	 *
	 * @var array<int, string>
	 */
	public const FIELDS = [
		MissingMetadataQuery::MISSING_TITLE,
		MissingMetadataQuery::MISSING_DESCRIPTION,
		MissingMetadataQuery::MISSING_IMAGE_ALT,
	];

	/**
	 * Title settings.
	 *
	 * @var TitleSettings
	 */
	private TitleSettings $titles;

	/**
	 * Template renderer.
	 *
	 * @var TemplateEngine
	 */
	private TemplateEngine $engine;

	/**
	 * Missing-metadata reader.
	 *
	 * @var MissingMetadataQuery
	 */
	private MissingMetadataQuery $query;

	/**
	 * Constructor.
	 *
	 * @param TitleSettings        $titles Title settings.
	 * @param TemplateEngine       $engine Template renderer.
	 * @param MissingMetadataQuery $query  Row reader.
	 */
	public function __construct( TitleSettings $titles, TemplateEngine $engine, MissingMetadataQuery $query ) {
		$this->titles = $titles;
		$this->engine = $engine;
		$this->query  = $query;
	}

	/**
	 * Preview rendered values without writing.
	 *
	 * @param array<int, int>    $ids          Post IDs.
	 * @param array<int, string> $fields       Fields to fill.
	 * @param bool               $only_missing Skip fields that already have a value.
	 * @return array<string, mixed>
	 */
	public function preview( array $ids, array $fields, bool $only_missing = true ): array {
		$fields = $this->fields( $fields );
		$items  = [];

		foreach ( $this->unique_ids( $ids ) as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$row = $this->query->find( $id );
			if ( null === $row ) {
				continue;
			}
			$changes = $this->changes( $id, $row, $fields, $only_missing );
			if ( [] === $changes ) {
				continue;
			}
			$items[] = [
				'id'        => $id,
				'title'     => (string) $row['title'],
				'post_type' => (string) $row['post_type'],
				'permalink' => (string) $row['permalink'],
				'changes'   => $changes,
			];
		}

		return [
			'items' => $items,
		];
	}

	/**
	 * Save the rendered values for the selected posts.
	 *
	 * @param array<int, int>    $ids          Post IDs.
	 * @param array<int, string> $fields       Fields to fill.
	 * @param bool               $only_missing Skip fields that already have a value.
	 * @return array<string, mixed>
	 */
	public function apply( array $ids, array $fields, bool $only_missing = true ): array {
		$preview = $this->preview( $ids, $fields, $only_missing );
		$applied = 0;
		$items   = [];

		foreach ( $preview['items'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id      = (int) $item['id'];
			$changes = isset( $item['changes'] ) && is_array( $item['changes'] ) ? $item['changes'] : [];
			$wrote   = false;
			foreach ( $changes as $change ) {
				if ( ! is_array( $change ) ) {
					continue;
				}
				if ( $this->write_change( $id, $change ) ) {
					$wrote = true;
				}
			}
			if ( $wrote ) {
				++$applied;
				$fresh = $this->query->find( $id );
				if ( null !== $fresh ) {
					$items[] = $fresh;
				}
			}
		}

		return [
			'ok'      => true,
			'applied' => $applied,
			'items'   => $items,
			'preview' => $preview['items'],
		];
	}

	/**
	 * Save inline edits for one post. Only keys present in the input change.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $input   title, description, focus_keyword, image_alt.
	 * @return array<string, mixed>
	 */
	public function save_fields( int $post_id, array $input ): array {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [
				'ok'      => false,
				'status'  => 403,
				'message' => __( 'You cannot edit that page.', 'rankmyseo' ),
			];
		}
		if ( null === $this->query->find( $post_id ) ) {
			return [
				'ok'      => false,
				'status'  => 404,
				'message' => __( 'That page is not on this list.', 'rankmyseo' ),
			];
		}

		if ( array_key_exists( 'title', $input ) ) {
			$this->store_text( $post_id, MetaKeys::TITLE, MetaSanitizer::sanitize_title( $input['title'] ) );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$this->store_text( $post_id, MetaKeys::DESCRIPTION, MetaSanitizer::sanitize_description( $input['description'] ) );
		}
		if ( array_key_exists( 'focus_keyword', $input ) ) {
			$this->store_text( $post_id, MetaKeys::FOCUS_KEYWORD, MetaSanitizer::sanitize_keyword( $input['focus_keyword'] ) );
		}
		if ( array_key_exists( 'image_alt', $input ) ) {
			$post = get_post( $post_id );
			if ( is_object( $post ) ) {
				$image = $this->query->image_id( $post );
				if ( $image > 0 && current_user_can( 'edit_post', $image ) ) {
					$this->store_text( $image, '_wp_attachment_image_alt', Text::plain( (string) $input['image_alt'], 300 ) );
				}
			}
		}

		$fresh = $this->query->find( $post_id );
		return [
			'ok'   => true,
			'item' => $fresh ?? [],
		];
	}

	/**
	 * Write one rendered field even when it already has a value.
	 *
	 * Used by the WordPress bulk editor, where the admin chose the template.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   title or description.
	 * @return bool
	 */
	public function apply_pattern( int $post_id, string $field ): bool {
		if ( ! in_array( $field, [ MissingMetadataQuery::MISSING_TITLE, MissingMetadataQuery::MISSING_DESCRIPTION ], true ) ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		$row = $this->query->find( $post_id );
		if ( null === $row && ! is_object( get_post( $post_id ) ) ) {
			return false;
		}
		$post = get_post( $post_id );
		if ( ! is_object( $post ) ) {
			return false;
		}
		$rendered = MissingMetadataQuery::MISSING_TITLE === $field ? $this->render_title( $post ) : $this->render_description( $post );
		if ( '' === $rendered ) {
			return false;
		}
		$key = MissingMetadataQuery::MISSING_TITLE === $field ? MetaKeys::TITLE : MetaKeys::DESCRIPTION;
		update_post_meta( $post_id, $key, $rendered );
		return true;
	}

	/**
	 * Remove one custom field so the site pattern is used again.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   title or description.
	 * @return void
	 */
	public function clear_field( int $post_id, string $field ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( MissingMetadataQuery::MISSING_TITLE === $field ) {
			delete_post_meta( $post_id, MetaKeys::TITLE );
		}
		if ( MissingMetadataQuery::MISSING_DESCRIPTION === $field ) {
			delete_post_meta( $post_id, MetaKeys::DESCRIPTION );
		}
	}

	/**
	 * Remove custom search text and leave robots alone.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function clear_custom( int $post_id ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( [ MetaKeys::TITLE, MetaKeys::DESCRIPTION, MetaKeys::CANONICAL, MetaKeys::FOCUS_KEYWORD ] as $key ) {
			delete_post_meta( $post_id, $key );
		}
	}

	/**
	 * Render the title pattern for a post.
	 *
	 * @param object $post Post object.
	 * @return string
	 */
	public function render_title( object $post ): string {
		$patterns = $this->titles->patterns_for( (string) $post->post_type );
		return $this->engine->replace( $patterns['title'], $this->context( $post, null ) );
	}

	/**
	 * Render the description pattern for a post.
	 *
	 * @param object $post Post object.
	 * @return string
	 */
	public function render_description( object $post ): string {
		$patterns = $this->titles->patterns_for( (string) $post->post_type );
		return $this->engine->replace( $patterns['description'], $this->context( $post, null ) );
	}

	/**
	 * Render the image description pattern.
	 *
	 * @param object      $post  Content post.
	 * @param object|null $image Attachment, when different from the post.
	 * @return string
	 */
	public function render_alt( object $post, ?object $image ): string {
		$subject = $image ?? $post;
		return Text::plain( $this->engine->replace( $this->titles->image_alt_pattern(), $this->context( $post, $subject ) ), 300 );
	}

	/**
	 * Changes that would be written for one row.
	 *
	 * @param int                  $post_id      Post ID.
	 * @param array<string, mixed> $row          Presented row.
	 * @param array<int, string>   $fields       Requested fields.
	 * @param bool                 $only_missing Skip filled fields.
	 * @return list<array<string, string>>
	 */
	private function changes( int $post_id, array $row, array $fields, bool $only_missing ): array {
		$post = get_post( $post_id );
		if ( ! is_object( $post ) ) {
			return [];
		}
		$missing = isset( $row['missing'] ) && is_array( $row['missing'] ) ? $row['missing'] : [];
		$changes = [];

		foreach ( $fields as $field ) {
			if ( $only_missing && ! in_array( $field, $missing, true ) ) {
				continue;
			}
			if ( MissingMetadataQuery::MISSING_TITLE === $field ) {
				$after   = $this->render_title( $post );
				$before  = (string) $row['seo_title'];
				$pattern = $this->titles->patterns_for( (string) $post->post_type )['title'];
			} elseif ( MissingMetadataQuery::MISSING_DESCRIPTION === $field ) {
				$after   = $this->render_description( $post );
				$before  = (string) $row['description'];
				$pattern = $this->titles->patterns_for( (string) $post->post_type )['description'];
			} elseif ( MissingMetadataQuery::MISSING_IMAGE_ALT === $field ) {
				$image = $this->image_object( $post );
				if ( null === $image ) {
					continue;
				}
				if ( ! current_user_can( 'edit_post', (int) $image->ID ) ) {
					continue;
				}
				$after   = $this->render_alt( $post, $image );
				$before  = (string) $row['image_alt'];
				$pattern = $this->titles->image_alt_pattern();
			} else {
				continue;
			}

			if ( '' === $after || $after === $before ) {
				continue;
			}
			$changes[] = [
				'field'   => $field,
				'before'  => $before,
				'after'   => $after,
				'pattern' => $pattern,
			];
		}

		return $changes;
	}

	/**
	 * Persist one previewed change.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $change  Preview change.
	 * @return bool
	 */
	private function write_change( int $post_id, array $change ): bool {
		$field = isset( $change['field'] ) ? (string) $change['field'] : '';
		$after = isset( $change['after'] ) ? (string) $change['after'] : '';
		if ( '' === $after || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		if ( MissingMetadataQuery::MISSING_TITLE === $field ) {
			update_post_meta( $post_id, MetaKeys::TITLE, MetaSanitizer::sanitize_title( $after ) );
			return true;
		}
		if ( MissingMetadataQuery::MISSING_DESCRIPTION === $field ) {
			update_post_meta( $post_id, MetaKeys::DESCRIPTION, MetaSanitizer::sanitize_description( $after ) );
			return true;
		}
		if ( MissingMetadataQuery::MISSING_IMAGE_ALT === $field ) {
			$post = get_post( $post_id );
			if ( ! is_object( $post ) ) {
				return false;
			}
			$image = $this->query->image_id( $post );
			if ( $image <= 0 || ! current_user_can( 'edit_post', $image ) ) {
				return false;
			}
			update_post_meta( $image, '_wp_attachment_image_alt', Text::plain( $after, 300 ) );
			return true;
		}
		return false;
	}

	/**
	 * Store text, or delete the key when the editor cleared it.
	 *
	 * @param int    $object_id Post or attachment ID.
	 * @param string $key       Meta key.
	 * @param string $value     Sanitized value.
	 * @return void
	 */
	private function store_text( int $object_id, string $key, string $value ): void {
		if ( '' === $value ) {
			delete_post_meta( $object_id, $key );
			return;
		}
		update_post_meta( $object_id, $key, $value );
	}

	/**
	 * Replacement values for one post and its image.
	 *
	 * @param object      $post  Content post.
	 * @param object|null $image Attachment used for the file name.
	 * @return TemplateContext
	 */
	private function context( object $post, ?object $image ): TemplateContext {
		$excerpt = trim( (string) $post->post_excerpt );
		if ( '' === $excerpt ) {
			$excerpt = wp_strip_all_tags( (string) $post->post_content );
		}
		$categories = function_exists( 'get_the_category' ) ? get_the_category( (int) $post->ID ) : [];
		$category   = ( is_array( $categories ) && isset( $categories[0] ) && is_object( $categories[0] ) ) ? (string) $categories[0]->name : '';
		$year       = function_exists( 'wp_date' ) ? (string) wp_date( 'Y' ) : gmdate( 'Y' );
		$month      = function_exists( 'wp_date' ) ? (string) wp_date( 'F' ) : gmdate( 'F' );
		$subject    = $image ?? $post;

		return TemplateContext::from_array(
			[
				'title'        => (string) $subject->post_title,
				'sep'          => $this->titles->separator(),
				'sitename'     => $this->titles->site_name(),
				'tagline'      => $this->titles->tagline(),
				'excerpt'      => $excerpt,
				'category'     => $category,
				'page'         => '',
				'currentyear'  => $year,
				'currentmonth' => $month,
				'author'       => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
				'filename'     => $this->filename( $image ?? $post ),
				'pt_plural'    => $this->plural( (string) $post->post_type ),
			]
		);
	}

	/**
	 * File name without its extension.
	 *
	 * @param object $image Attachment or post.
	 * @return string
	 */
	private function filename( object $image ): string {
		$file = (string) get_post_meta( (int) $image->ID, '_wp_attached_file', true );
		if ( '' === $file ) {
			$file = (string) $image->post_name;
		}
		$base = basename( str_replace( '\\', '/', $file ) );
		$base = (string) preg_replace( '/\.[a-z0-9]+$/i', '', $base );
		return str_replace( [ '-', '_' ], ' ', $base );
	}

	/**
	 * Plural label, or the post type name.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private function plural( string $post_type ): string {
		if ( ! function_exists( 'get_post_type_object' ) ) {
			return $post_type;
		}
		$object = get_post_type_object( $post_type );
		if ( is_object( $object ) && isset( $object->labels->name ) ) {
			return (string) $object->labels->name;
		}
		return $post_type;
	}

	/**
	 * Attachment object for a row, when one exists.
	 *
	 * @param object $post Content post.
	 * @return object|null
	 */
	private function image_object( object $post ): ?object {
		$image_id = $this->query->image_id( $post );
		if ( $image_id <= 0 ) {
			return null;
		}
		$image = get_post( $image_id );
		return is_object( $image ) ? $image : null;
	}

	/**
	 * Keep only known fields.
	 *
	 * @param array<int, string> $fields Requested fields.
	 * @return array<int, string>
	 */
	private function fields( array $fields ): array {
		$clean = [];
		foreach ( $fields as $field ) {
			if ( in_array( $field, self::FIELDS, true ) && ! in_array( $field, $clean, true ) ) {
				$clean[] = $field;
			}
		}
		return [] === $clean ? self::FIELDS : $clean;
	}

	/**
	 * Positive unique IDs, capped so one request stays small.
	 *
	 * @param array<int, int> $ids Raw IDs.
	 * @return array<int, int>
	 */
	private function unique_ids( array $ids ): array {
		$clean = [];
		foreach ( $ids as $id ) {
			$number = (int) $id;
			if ( $number > 0 && ! in_array( $number, $clean, true ) ) {
				$clean[] = $number;
			}
			if ( count( $clean ) >= 100 ) {
				break;
			}
		}
		return $clean;
	}
}
