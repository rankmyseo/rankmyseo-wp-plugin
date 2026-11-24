<?php
/**
 * Writes the SEO score when a post is saved outside the live editor.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Analyzer;

use RankMySEO\Modules\Meta\MetaKeys;
use RankMySEO\Modules\Meta\MetaSanitizer;
use RankMySEO\Modules\Meta\TemplateContext;
use RankMySEO\Modules\Meta\TemplateEngine;
use RankMySEO\Modules\Meta\TitleSettings;
use WP_Post;

/**
 * Fallback scorer for classic, quick edit, and REST saves.
 */
final class ScorePersister {
	/**
	 * Calculator.
	 *
	 * @var ScoreCalculator
	 */
	private ScoreCalculator $calculator;

	/**
	 * Title settings used to resolve template tokens.
	 *
	 * @var TitleSettings
	 */
	private TitleSettings $titles;

	/**
	 * Template renderer.
	 *
	 * @var TemplateEngine
	 */
	private TemplateEngine $templates;

	/**
	 * Constructor.
	 *
	 * @param TitleSettings        $titles     Title settings.
	 * @param ScoreCalculator|null $calculator Optional calculator.
	 * @param TemplateEngine|null  $templates  Optional template engine.
	 */
	public function __construct( TitleSettings $titles, ?ScoreCalculator $calculator = null, ?TemplateEngine $templates = null ) {
		$this->titles     = $titles;
		$this->calculator = $calculator ?? new ScoreCalculator();
		$this->templates  = $templates ?? new TemplateEngine();
	}

	/**
	 * Register the save hook after quick edit and the classic metabox.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post', [ $this, 'save_post' ], 20, 2 );
	}

	/**
	 * Recompute and store the score for a real post save.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object when WordPress provides it.
	 * @return void
	 */
	public function save_post( int $post_id, $post = null ): void {
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
		if ( 'revision' === $post->post_type || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( ! in_array( $post->post_status, [ 'publish', 'draft', 'pending', 'future', 'private' ], true ) ) {
			return;
		}

		$public = get_post_types( [ 'public' => true ] );
		if ( ! is_array( $public ) || ! in_array( $post->post_type, $public, true ) ) {
			return;
		}
		if ( get_current_user_id() > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$keyword = (string) get_post_meta( $post_id, MetaKeys::FOCUS_KEYWORD, true );
		$result  = $this->calculator->analyze( $this->input_for( $post, $keyword ) );
		$summary = wp_json_encode( $this->calculator->summary_payload( $result ) );

		update_post_meta( $post_id, MetaKeys::SEO_SCORE, (int) $result['score'] );
		update_post_meta( $post_id, AnalyzerMeta::SUMMARY, is_string( $summary ) ? $summary : '' );
	}

	/**
	 * Analyzer input from the saved post and its SEO meta.
	 *
	 * @param WP_Post $post    Post.
	 * @param string  $keyword Focus keyword.
	 * @return array<string, mixed>
	 */
	private function input_for( WP_Post $post, string $keyword ): array {
		$meta_title = (string) get_post_meta( (int) $post->ID, MetaKeys::TITLE, true );
		$meta_desc  = (string) get_post_meta( (int) $post->ID, MetaKeys::DESCRIPTION, true );
		$robots     = get_post_meta( (int) $post->ID, MetaKeys::ROBOTS, true );
		$context    = TemplateContext::from_array(
			[
				'title'       => $post->post_title,
				'sep'         => $this->titles->separator(),
				'sitename'    => $this->titles->site_name(),
				'tagline'     => $this->titles->tagline(),
				'excerpt'     => $post->post_excerpt,
				'currentyear' => gmdate( 'Y' ),
			]
		);

		$cornerstone = get_post_meta( (int) $post->ID, MetaKeys::CORNERSTONE, true );

		return [
			'focus_keyword'      => $keyword,
			'secondary_keywords' => MetaSanitizer::sanitize_keywords( get_post_meta( (int) $post->ID, MetaKeys::SECONDARY_KEYWORDS, true ) ),
			'seo_title'          => $this->templates->replace( '' !== $meta_title ? $meta_title : '%title% %sep% %sitename%', $context ),
			'meta_description'   => $this->templates->replace( '' !== $meta_desc ? $meta_desc : '%excerpt%', $context ),
			'slug'               => $post->post_name,
			'content_html'       => $post->post_content,
			'post_type'          => $post->post_type,
			'locale'             => function_exists( 'get_locale' ) ? (string) get_locale() : 'en',
			'noindex'            => is_array( $robots ) && ! empty( $robots['noindex'] ),
			'site_url'           => home_url( '/' ),
			'cornerstone'        => true === $cornerstone || 1 === $cornerstone || '1' === (string) $cornerstone,
		];
	}
}
