<?php
/**
 * Approval-card previews for empty SEO fields.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Modules\AiActions\PayloadSanitizer;

final class PayloadSanitizerTest extends TestCase {
	public function test_empty_text_payloads_preview_as_an_empty_string(): void {
		$this->assertSame( '', PayloadSanitizer::preview( 'update_title', [ 'seoTitle' => '' ] ) );
		$this->assertSame( '', PayloadSanitizer::preview( 'update_meta_description', [ 'seoDescription' => '   ' ] ) );
		$this->assertSame( '', PayloadSanitizer::preview( 'image_alt', [ 'imageAlt' => '' ] ) );
		$this->assertSame(
			'Shop Artisan Roast coffee and brew gear',
			PayloadSanitizer::preview( 'update_title', [ 'seoTitle' => 'Shop Artisan Roast coffee and brew gear' ] )
		);
	}

	public function test_non_text_payloads_stay_readable(): void {
		$robots = PayloadSanitizer::preview( 'update_robots', [ 'robots' => [ 'noindex' => true ] ] );
		$this->assertStringContainsString( 'noindex', $robots );
		$this->assertStringNotContainsString( 'seoTitle', $robots );

		$this->assertSame(
			'FAQ structured data',
			PayloadSanitizer::preview( 'update_schema', [ 'faq' => [ [ 'question' => 'Why?' ] ] ] )
		);
	}
}
