<?php
/**
 * Unit tests for RankMySEO\Modules\ModuleManager.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Core\Settings;
use RankMySEO\Modules\ModuleInterface;
use RankMySEO\Modules\ModuleManager;
use RankMySEO_Test_State;

final class ModuleManagerTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_boot_only_registers_enabled_modules_and_is_idempotent(): void {
		$settings = new Settings();
		$settings->update(
			[
				'modules' => [
					'meta'     => true,
					'sitemaps' => false,
				],
			]
		);

		$meta_calls     = 0;
		$sitemaps_calls = 0;

		$meta_module     = $this->create_spy_module( 'meta', 'On-Page & Titles', $meta_calls );
		$sitemaps_module = $this->create_spy_module( 'sitemaps', 'Sitemaps', $sitemaps_calls );

		$manager = new ModuleManager( $settings );
		$manager->add( $meta_module );
		$manager->add( $sitemaps_module );

		$manager->boot();
		$manager->boot();

		$this->assertSame( 1, $meta_calls );
		$this->assertSame( 0, $sitemaps_calls );
		$this->assertTrue( $manager->is_booted( 'meta' ) );
		$this->assertFalse( $manager->is_booted( 'sitemaps' ) );
		$this->assertFalse( $manager->is_enabled( 'nonexistent' ) );
	}

	public function test_get_catalog_exposes_metadata_and_enabled_flags(): void {
		$settings = new Settings();
		$manager  = new ModuleManager( $settings );
		$calls    = 0;

		$manager->add( $this->create_spy_module( 'schema', 'Schema', $calls ) );
		$manager->boot();

		$catalog = $manager->get_catalog();

		$this->assertCount( 1, $catalog );
		$this->assertSame( 'schema', $catalog[0]['id'] );
		$this->assertSame( 'Schema', $catalog[0]['name'] );
		$this->assertTrue( $catalog[0]['enabled'] );
		$this->assertTrue( $catalog[0]['booted'] );
		$this->assertSame( 'Plain summary', $catalog[0]['simple_description'] );
	}

	private function create_spy_module( string $id, string $name, int &$calls ): ModuleInterface {
		return new class( $id, $name, $calls ) implements ModuleInterface {
			private string $id;
			private string $name;
			private int $calls;

			public function __construct( string $id, string $name, int &$calls ) {
				$this->id    = $id;
				$this->name  = $name;
				$this->calls = &$calls;
			}

			public function get_id(): string {
				return $this->id;
			}

			public function get_name(): string {
				return $this->name;
			}

			public function get_description(): string {
				return 'Technical summary';
			}

			public function get_simple_description(): string {
				return 'Plain summary';
			}

			public function is_enabled_by_default(): bool {
				return true;
			}

			public function is_ready(): bool {
				return true;
			}

			public function requires_cloud(): bool {
				return false;
			}

			public function register(): void {
				++$this->calls;
			}
		};
	}
}
