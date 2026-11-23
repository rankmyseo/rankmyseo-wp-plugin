<?php
/**
 * Tests for opt-in uninstall cleanup.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Core\Uninstaller;

final class UninstallerTest extends TestCase {
	public function test_delete_runs_only_when_the_flag_is_enabled(): void {
		$this->assertFalse( Uninstaller::should_delete( [] ) );
		$this->assertFalse( Uninstaller::should_delete( 'keep' ) );
		$this->assertFalse(
			Uninstaller::should_delete(
				[
					'keep_data_on_uninstall'   => true,
					'delete_data_on_uninstall' => false,
				]
			)
		);
		$this->assertTrue(
			Uninstaller::should_delete(
				[
					'delete_data_on_uninstall' => true,
				]
			)
		);
		$this->assertTrue(
			Uninstaller::should_delete(
				[
					'delete_data_on_uninstall' => 'yes',
				]
			)
		);
		$this->assertFalse(
			Uninstaller::should_delete(
				[
					'delete_data_on_uninstall' => 'false',
				]
			)
		);
	}

	public function test_legacy_keep_data_flag_still_opts_into_deletion(): void {
		$this->assertTrue(
			Uninstaller::should_delete(
				[
					'keep_data_on_uninstall' => false,
				]
			)
		);
		$this->assertFalse(
			Uninstaller::should_delete(
				[
					'keep_data_on_uninstall' => true,
				]
			)
		);
	}
}
