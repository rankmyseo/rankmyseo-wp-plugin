<?php
/**
 * Reads rankmyseo/faq blocks and keeps FAQ schema in step with them.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

use WP_Post;

/**
 * The visible FAQ block is the source of truth when a post contains one.
 * Posts with only stored FAQ meta keep using that meta.
 */
final class FaqContent {
	/**
	 * Block name stored in post content.
	 */
	public const BLOCK = 'rankmyseo/faq';

	/**
	 * Register the save hook that copies block pairs into FAQ meta.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post', [ $this, 'sync' ], 20, 2 );
	}

	/**
	 * Copy FAQ block pairs into `_rankmyseo_schema_faq`.
	 *
	 * Posts without the block are left alone, so imported or assistant-written
	 * pairs stay in place.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Saved post.
	 * @return void
	 */
	public function sync( int $post_id, mixed $post = null ): void {
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post ) ) {
			return;
		}
		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! self::has_block( (string) $post->post_content ) ) {
			return;
		}

		$encoded = SchemaMeta::sanitize_faq( self::pairs_from_content( (string) $post->post_content ) );
		if ( '' === $encoded ) {
			delete_post_meta( $post_id, SchemaMeta::META_FAQ );
			return;
		}

		update_post_meta( $post_id, SchemaMeta::META_FAQ, $encoded );
	}

	/**
	 * FAQ pairs for schema. A FAQ block replaces stored meta so the two cannot diverge.
	 *
	 * @param WP_Post $post Post object.
	 * @return list<array{question: string, answer: string}>
	 */
	public static function for_post( WP_Post $post ): array {
		$content = (string) $post->post_content;
		if ( self::has_block( $content ) ) {
			return self::pairs_from_content( $content );
		}

		return SchemaMeta::read_faqs( (int) $post->ID );
	}

	/**
	 * Whether content contains the FAQ block.
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	public static function has_block( string $content ): bool {
		return str_contains( $content, 'wp:' . self::BLOCK );
	}

	/**
	 * Question and answer pairs from every FAQ block in the content.
	 *
	 * @param string $content Post content.
	 * @return list<array{question: string, answer: string}>
	 */
	public static function pairs_from_content( string $content ): array {
		$pairs  = [];
		$offset = 0;
		$needle = '<!-- wp:' . self::BLOCK;
		$length = strlen( $content );
		while ( $offset < $length ) {
			$start = strpos( $content, $needle, $offset );
			if ( false === $start ) {
				break;
			}
			$json_start = strpos( $content, '{', $start );
			if ( false === $json_start ) {
				break;
			}
			$json   = self::json_object_at( $content, $json_start );
			$offset = $json_start + max( 1, strlen( $json ) );
			if ( '' === $json ) {
				continue;
			}
			$decoded = json_decode( $json, true );
			if ( is_array( $decoded ) && isset( $decoded['questions'] ) && is_array( $decoded['questions'] ) ) {
				$pairs = array_merge( $pairs, SchemaMeta::normalize_faqs( $decoded['questions'] ) );
			}
		}

		return SchemaMeta::normalize_faqs( $pairs );
	}

	/**
	 * Render the visible FAQ block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render( array $attributes ): string {
		$questions = isset( $attributes['questions'] ) && is_array( $attributes['questions'] )
			? SchemaMeta::normalize_faqs( $attributes['questions'] )
			: [];
		if ( [] === $questions ) {
			return '';
		}

		FrontendStyle::enqueue( 'rankmyseo-faq', 'assets/css/faq.css' );
		$layout = isset( $attributes['layout'] ) && 'headings' === $attributes['layout'] ? 'headings' : 'details';
		if ( 'headings' === $layout ) {
			return self::headings( $questions );
		}

		return self::details( $questions );
	}

	/**
	 * Native disclosure list. No script is required to open an answer.
	 *
	 * @param list<array{question: string, answer: string}> $questions Pairs.
	 * @return string
	 */
	private static function details( array $questions ): string {
		$html = '<div class="rankmyseo-faq">';
		foreach ( $questions as $pair ) {
			$html .= '<details class="rankmyseo-faq__item"><summary class="rankmyseo-faq__question">' . esc_html( $pair['question'] ) . '</summary>';
			$html .= '<div class="rankmyseo-faq__answer">' . self::paragraphs( $pair['answer'] ) . '</div></details>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Heading and answer list.
	 *
	 * @param list<array{question: string, answer: string}> $questions Pairs.
	 * @return string
	 */
	private static function headings( array $questions ): string {
		$html = '<section class="rankmyseo-faq" aria-label="' . esc_attr__( 'Questions', 'rankmyseo' ) . '">';
		foreach ( $questions as $pair ) {
			$html .= '<h3 class="rankmyseo-faq__question">' . esc_html( $pair['question'] ) . '</h3>';
			$html .= '<div class="rankmyseo-faq__answer">' . self::paragraphs( $pair['answer'] ) . '</div>';
		}
		$html .= '</section>';

		return $html;
	}

	/**
	 * Turn plain answer text into paragraphs.
	 *
	 * @param string $answer Answer text.
	 * @return string
	 */
	private static function paragraphs( string $answer ): string {
		$parts = preg_split( '/\R{2,}/', trim( $answer ) );
		if ( ! is_array( $parts ) || [] === $parts ) {
			return '';
		}

		$html = '';
		foreach ( $parts as $part ) {
			$text = trim( (string) $part );
			if ( '' === $text ) {
				continue;
			}
			$html .= '<p>' . nl2br( esc_html( $text ), false ) . '</p>';
		}

		return $html;
	}

	/**
	 * Read one JSON object starting at an opening brace.
	 *
	 * @param string $content Full content.
	 * @param int    $start   Offset of `{`.
	 * @return string
	 */
	private static function json_object_at( string $content, int $start ): string {
		$length    = strlen( $content );
		$depth     = 0;
		$in_string = false;
		$escape    = false;
		for ( $index = $start; $index < $length; $index++ ) {
			$char = $content[ $index ];
			if ( $in_string ) {
				if ( $escape ) {
					$escape = false;
					continue;
				}
				if ( '\\' === $char ) {
					$escape = true;
					continue;
				}
				if ( '"' === $char ) {
					$in_string = false;
				}
				continue;
			}
			if ( '"' === $char ) {
				$in_string = true;
				continue;
			}
			if ( '{' === $char ) {
				++$depth;
				continue;
			}
			if ( '}' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					return substr( $content, $start, $index - $start + 1 );
				}
			}
		}

		return '';
	}
}
