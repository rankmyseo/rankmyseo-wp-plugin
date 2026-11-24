<?php
/**
 * Read and write per-post and per-term SEO meta.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Persistence helper for the registered RankMySEO meta keys.
 */
final class MetaStore {
	/**
	 * Read post meta. Robots is null when the key has never been saved.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function read_post( int $post_id ): array {
		return $this->read( 'post', $post_id );
	}

	/**
	 * Read term meta. Robots is null when the key has never been saved.
	 *
	 * @param int $term_id Term ID.
	 * @return array<string, mixed>
	 */
	public function read_term( int $term_id ): array {
		return $this->read( 'term', $term_id );
	}

	/**
	 * Replace the full SEO bundle for a post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $input   Raw or public field names.
	 * @return array<string, mixed> Sanitized bundle that was stored.
	 */
	public function write_post( int $post_id, array $input ): array {
		$bundle = MetaSanitizer::sanitize_bundle( $input );
		$this->write( 'post', $post_id, $bundle );
		return $bundle;
	}

	/**
	 * Replace the full SEO bundle for a term.
	 *
	 * @param int                  $term_id Term ID.
	 * @param array<string, mixed> $input   Raw or public field names.
	 * @return array<string, mixed>
	 */
	public function write_term( int $term_id, array $input ): array {
		$bundle = MetaSanitizer::sanitize_bundle( $input );
		$this->write( 'term', $term_id, $bundle );
		return $bundle;
	}

	/**
	 * Update only the noindex flag and preserve the rest of the robots object.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $noindex Whether the post should be hidden from search.
	 * @return void
	 */
	public function set_post_noindex( int $post_id, bool $noindex ): void {
		$current           = $this->read_post( $post_id );
		$robots            = is_array( $current['robots'] ) ? Robots::sanitize( $current['robots'] ) : Robots::defaults();
		$robots['noindex'] = $noindex;
		update_post_meta( $post_id, MetaKeys::ROBOTS, $robots );
	}

	/**
	 * Read.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @return array<string, mixed>
	 */
	private function read( string $object_type, int $object_id ): array {
		$robots_set = $this->exists( $object_type, $object_id, MetaKeys::ROBOTS );

		return [
			'title'               => $this->string_meta( $object_type, $object_id, MetaKeys::TITLE ),
			'description'         => $this->string_meta( $object_type, $object_id, MetaKeys::DESCRIPTION ),
			'canonical'           => $this->string_meta( $object_type, $object_id, MetaKeys::CANONICAL ),
			'robots'              => $robots_set ? Robots::sanitize( $this->raw_meta( $object_type, $object_id, MetaKeys::ROBOTS ) ) : null,
			'focus_keyword'       => $this->string_meta( $object_type, $object_id, MetaKeys::FOCUS_KEYWORD ),
			'secondary_keywords'  => MetaSanitizer::sanitize_keywords( $this->raw_meta( $object_type, $object_id, MetaKeys::SECONDARY_KEYWORDS ) ),
			'cornerstone'         => MetaSanitizer::sanitize_bool( $this->raw_meta( $object_type, $object_id, MetaKeys::CORNERSTONE ) ),
			'og_title'            => $this->string_meta( $object_type, $object_id, MetaKeys::OG_TITLE ),
			'og_description'      => $this->string_meta( $object_type, $object_id, MetaKeys::OG_DESCRIPTION ),
			'og_image'            => $this->string_meta( $object_type, $object_id, MetaKeys::OG_IMAGE ),
			'twitter_title'       => $this->string_meta( $object_type, $object_id, MetaKeys::TWITTER_TITLE ),
			'twitter_description' => $this->string_meta( $object_type, $object_id, MetaKeys::TWITTER_DESCRIPTION ),
			'twitter_image'       => $this->string_meta( $object_type, $object_id, MetaKeys::TWITTER_IMAGE ),
			'twitter_card'        => $this->string_meta( $object_type, $object_id, MetaKeys::TWITTER_CARD ),
		];
	}

	/**
	 * Write.
	 *
	 * @param string               $object_type post or term.
	 * @param int                  $object_id   Object ID.
	 * @param array<string, mixed> $bundle      Sanitized bundle.
	 * @return void
	 */
	private function write( string $object_type, int $object_id, array $bundle ): void {
		$map = [
			'title'               => MetaKeys::TITLE,
			'description'         => MetaKeys::DESCRIPTION,
			'canonical'           => MetaKeys::CANONICAL,
			'focus_keyword'       => MetaKeys::FOCUS_KEYWORD,
			'og_title'            => MetaKeys::OG_TITLE,
			'og_description'      => MetaKeys::OG_DESCRIPTION,
			'og_image'            => MetaKeys::OG_IMAGE,
			'twitter_title'       => MetaKeys::TWITTER_TITLE,
			'twitter_description' => MetaKeys::TWITTER_DESCRIPTION,
			'twitter_image'       => MetaKeys::TWITTER_IMAGE,
			'twitter_card'        => MetaKeys::TWITTER_CARD,
		];

		foreach ( $map as $field => $meta_key ) {
			$value = isset( $bundle[ $field ] ) ? (string) $bundle[ $field ] : '';
			$this->update_or_delete( $object_type, $object_id, $meta_key, $value );
		}

		$keywords = isset( $bundle['secondary_keywords'] ) && is_array( $bundle['secondary_keywords'] )
			? $bundle['secondary_keywords']
			: [];

		if ( [] === $keywords ) {
			$this->delete( $object_type, $object_id, MetaKeys::SECONDARY_KEYWORDS );
		} else {
			$this->update( $object_type, $object_id, MetaKeys::SECONDARY_KEYWORDS, $keywords );
		}

		if ( ! empty( $bundle['cornerstone'] ) ) {
			$this->update( $object_type, $object_id, MetaKeys::CORNERSTONE, '1' );
		} else {
			$this->delete( $object_type, $object_id, MetaKeys::CORNERSTONE );
		}

		$robots = isset( $bundle['robots'] ) && is_array( $bundle['robots'] )
			? Robots::sanitize( $bundle['robots'] )
			: Robots::defaults();
		$this->update( $object_type, $object_id, MetaKeys::ROBOTS, $robots );
	}

	/**
	 * String meta.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @return string
	 */
	private function string_meta( string $object_type, int $object_id, string $key ): string {
		$raw = $this->raw_meta( $object_type, $object_id, $key );
		return is_scalar( $raw ) ? (string) $raw : '';
	}

	/**
	 * Raw meta.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @return mixed
	 */
	private function raw_meta( string $object_type, int $object_id, string $key ) {
		if ( 'term' === $object_type ) {
			return get_term_meta( $object_id, $key, true );
		}

		return get_post_meta( $object_id, $key, true );
	}

	/**
	 * Exists.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @return bool
	 */
	private function exists( string $object_type, int $object_id, string $key ): bool {
		return metadata_exists( $object_type, $object_id, $key );
	}

	/**
	 * Update or delete.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @param string $value       Stored value.
	 * @return void
	 */
	private function update_or_delete( string $object_type, int $object_id, string $key, string $value ): void {
		if ( '' === $value ) {
			$this->delete( $object_type, $object_id, $key );
			return;
		}

		$this->update( $object_type, $object_id, $key, $value );
	}

	/**
	 * Update.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @param mixed  $value       Stored value.
	 * @return void
	 */
	private function update( string $object_type, int $object_id, string $key, $value ): void {
		if ( 'term' === $object_type ) {
			update_term_meta( $object_id, $key, $value );
			return;
		}

		update_post_meta( $object_id, $key, $value );
	}

	/**
	 * Delete.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   Object ID.
	 * @param string $key         Meta key.
	 * @return void
	 */
	private function delete( string $object_type, int $object_id, string $key ): void {
		if ( 'term' === $object_type ) {
			delete_term_meta( $object_id, $key );
			return;
		}

		delete_post_meta( $object_id, $key );
	}
}
