<?php
/**
 * Unit tests for RankMySEO\Core\Settings.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Core\Settings;
use RankMySEO_Test_State;

final class SettingsTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_defaults_and_sanitization_guard_against_invalid_values(): void {
		$settings = new Settings();

		$sanitized = $settings->sanitize(
			[
				'default_ui_mode'        => 'invalid_mode',
				'site_type'              => 'alien',
				'site_name'              => '<b>Acme & Co</b>   ',
				'title_separator'        => '$$$',
				'keep_data_on_uninstall' => 'false',
				'telemetry_opt_in'       => '1',
				'modules'                => [
					'meta'          => '0',
					'schema'        => true,
					'unknown_entry' => true,
				],
			]
		);

		$this->assertSame( 'simple', $sanitized['default_ui_mode'] );
		$this->assertSame( 'organization', $sanitized['site_type'] );
		$this->assertSame( 'Acme & Co', $sanitized['site_name'] );
		$this->assertSame( '|', $sanitized['title_separator'] );
		$this->assertFalse( $sanitized['keep_data_on_uninstall'] );
		$this->assertTrue( $sanitized['telemetry_opt_in'] );
		$this->assertFalse( $sanitized['modules']['meta'] );
		$this->assertTrue( $sanitized['modules']['schema'] );
		$this->assertArrayNotHasKey( 'unknown_entry', $sanitized['modules'] );
	}

	public function test_update_persists_partial_changes_without_wiping_other_keys(): void {
		$settings = new Settings();
		$settings->ensure_defaults();

		$settings->update(
			[
				'site_name'       => 'My Shop',
				'title_separator' => '-',
				'site_type'       => 'person',
			]
		);

		$settings->set_module_enabled( 'redirects', false );

		$all = $settings->all();

		$this->assertSame( 'My Shop', $all['site_name'] );
		$this->assertSame( '-', $all['title_separator'] );
		$this->assertSame( 'person', $all['site_type'] );
		$this->assertFalse( $settings->is_module_enabled( 'redirects' ) );
		$this->assertTrue( $settings->is_module_enabled( 'meta' ) );
	}

	public function test_per_user_ui_mode_and_wizard_dismissal(): void {
		$settings = new Settings();

		$this->assertSame( 'simple', $settings->get_user_ui_mode( 7 ) );

		$saved = $settings->set_user_ui_mode( 7, 'advanced' );
		$this->assertSame( 'advanced', $saved );
		$this->assertSame( 'advanced', $settings->get_user_ui_mode( 7 ) );
		$this->assertSame( 'simple', $settings->get_user_ui_mode( 8 ) );

		$fallback = $settings->set_user_ui_mode( 7, 'bogus' );
		$this->assertSame( 'simple', $fallback );

		$this->assertFalse( $settings->is_wizard_dismissed_for_user( 7 ) );
		$settings->set_user_wizard_dismissed( 7, true );
		$this->assertTrue( $settings->is_wizard_dismissed_for_user( 7 ) );
		$this->assertFalse( $settings->is_wizard_dismissed_for_user( 8 ) );
	}
}
