<?php
/**
 * Unit tests for the visible FAQ block and schema sync.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Modules\Schema\ContentBlocks;
use RankMySEO\Modules\Schema\ContextFactory;
use RankMySEO\Modules\Schema\FaqContent;
use RankMySEO\Modules\Schema\GraphBuilder;
use RankMySEO\Modules\Schema\SchemaMeta;
use RankMySEO\Modules\Schema\SchemaSettings;
use RankMySEO_Test_State;
use WP_Post;

final class FaqBlockTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_renders_details_and_heading_layouts_without_a_script(): void {
		$details = FaqContent::render(
			[
				'layout'    => 'details',
				'questions' => [
					[
						'question' => 'Why shoes?',
						'answer'   => "They last.\n\nReally.",
					],
				],
			]
		);
		$this->assertStringContainsString( '<details class="rankmyseo-faq__item">', $details );
		$this->assertStringContainsString( '<summary class="rankmyseo-faq__question">Why shoes?</summary>', $details );
		$this->assertStringContainsString( '<p>They last.</p><p>Really.</p>', $details );
		$this->assertStringNotContainsString( '<script', $details );

		$headings = FaqContent::render(
			[
				'layout'    => 'headings',
				'questions' => [
					[
						'question' => 'Care?',
						'answer'   => '<script>alert(1)</script>Wipe them.',
					],
				],
			]
		);
		$this->assertStringContainsString( '<h3 class="rankmyseo-faq__question">Care?</h3>', $headings );
		$this->assertStringNotContainsString( '<script>', $headings );
		$this->assertStringContainsString( 'Wipe them.', $headings );
		$this->assertArrayHasKey( 'rankmyseo-faq', RankMySEO_Test_State::$enqueued_styles );
	}

	public function test_save_copies_block_pairs_and_leaves_meta_only_posts_alone(): void {
		$faq   = new FaqContent();
		$post  = $this->post( 40, $this->block( 'From block?', 'Yes.' ) );
		RankMySEO_Test_State::$posts[40] = $post;
		RankMySEO_Test_State::$post_meta[40][ SchemaMeta::META_FAQ ] = '[{"question":"Stale?","answer":"Old."}]';

		$faq->sync( 40, $post );
		$stored = json_decode( (string) RankMySEO_Test_State::$post_meta[40][ SchemaMeta::META_FAQ ], true );
		$this->assertSame( 'From block?', $stored[0]['question'] );
		$this->assertCount( 1, $stored );

		$plain = $this->post( 41, 'No block here.' );
		RankMySEO_Test_State::$posts[41] = $plain;
		RankMySEO_Test_State::$post_meta[41][ SchemaMeta::META_FAQ ] = '[{"question":"Kept?","answer":"Yes."}]';
		$faq->sync( 41, $plain );
		$this->assertStringContainsString( 'Kept?', (string) RankMySEO_Test_State::$post_meta[41][ SchemaMeta::META_FAQ ] );

		$revision              = $this->post( 42, $this->block( 'Skip?', 'Yes.' ) );
		$revision->post_type   = 'revision';
		$faq->sync( 42, $revision );
		$this->assertArrayNotHasKey( 42, RankMySEO_Test_State::$post_meta );
	}

	public function test_schema_uses_the_block_when_present_and_meta_when_absent(): void {
		$settings = new SchemaSettings();
		$with     = $this->post(
			50,
			'Intro ' . $this->block( 'From block?', 'Yes, with "quotes".' )
		);
		RankMySEO_Test_State::$posts[50] = $with;
		RankMySEO_Test_State::$post_meta[50][ SchemaMeta::META_FAQ ] = '[{"question":"Stale?","answer":"Old."}]';

		$context = ( new ContextFactory( $settings ) )->for_post( 50 );
		$this->assertSame( 'From block?', $context->faqs[0]['question'] );
		$this->assertCount( 1, $context->faqs );

		$document = ( new GraphBuilder() )->build( $context );
		$faq      = $this->faq_node( $document );
		$this->assertNotNull( $faq );
		$this->assertCount( 1, $faq['mainEntity'] );
		$this->assertSame( 'From block?', $faq['mainEntity'][0]['name'] );

		$meta_only = $this->post( 51, 'Plain content' );
		RankMySEO_Test_State::$posts[51] = $meta_only;
		RankMySEO_Test_State::$post_meta[51][ SchemaMeta::META_FAQ ] = '[{"question":"Kept?","answer":"Yes."}]';
		$meta_context = ( new ContextFactory( $settings ) )->for_post( 51 );
		$meta_graph   = ( new GraphBuilder() )->build( $meta_context );
		$meta_faq     = $this->faq_node( $meta_graph );
		$this->assertNotNull( $meta_faq );
		$this->assertSame( 'Kept?', $meta_faq['mainEntity'][0]['name'] );
	}

	public function test_block_is_registered_for_php_rendering(): void {
		( new ContentBlocks() )->register_blocks();
		$this->assertArrayHasKey( 'rankmyseo/faq', RankMySEO_Test_State::$block_types );
		$callback = RankMySEO_Test_State::$block_types['rankmyseo/faq']['render_callback'];
		$this->assertIsCallable( $callback );
		$html = $callback(
			[
				'questions' => [
					[
						'question' => 'Ready?',
						'answer'   => 'Yes.',
					],
				],
			]
		);
		$this->assertStringContainsString( 'Ready?', $html );
	}

	private function post( int $id, string $content ): WP_Post {
		$post               = new WP_Post();
		$post->ID           = $id;
		$post->post_title   = 'FAQ page';
		$post->post_name    = 'faq-page';
		$post->post_type    = 'page';
		$post->post_content = $content;
		$post->post_status  = 'publish';
		return $post;
	}

	private function block( string $question, string $answer ): string {
		$json = wp_json_encode(
			[
				'questions' => [
					[
						'question' => $question,
						'answer'   => $answer,
					],
				],
				'layout'    => 'details',
			]
		);

		return '<!-- wp:rankmyseo/faq ' . $json . ' /-->';
	}

	/**
	 * @param array<string, mixed> $document JSON-LD document.
	 * @return array<string, mixed>|null
	 */
	private function faq_node( array $document ): ?array {
		$graph = $document['@graph'] ?? [];
		if ( ! is_array( $graph ) ) {
			return null;
		}
		foreach ( $graph as $node ) {
			if ( is_array( $node ) && isset( $node['@type'] ) && 'FAQPage' === $node['@type'] ) {
				return $node;
			}
		}

		return null;
	}
}
