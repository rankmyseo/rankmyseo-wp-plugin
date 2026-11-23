<?php
/**
 * Unit tests for RankMySEO\Core\Capabilities.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Core\Capabilities;
use RankMySEO_Test_State;

final class CapabilitiesTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_add_caps_maps_administrator_and_editor_cleanly(): void {
		Capabilities::add_caps();

		$admin      = get_role( 'administrator' );
		$editor     = get_role( 'editor' );
		$subscriber = get_role( 'subscriber' );

		$this->assertNotNull( $admin );
		$this->assertNotNull( $editor );
		$this->assertNotNull( $subscriber );

		$this->assertTrue( $admin->has_cap( Capabilities::CAP_MANAGE ) );
		$this->assertTrue( $admin->has_cap( Capabilities::CAP_APPROVE_AI ) );

		$this->assertFalse( $editor->has_cap( Capabilities::CAP_MANAGE ) );
		$this->assertTrue( $editor->has_cap( Capabilities::CAP_APPROVE_AI ) );

		$this->assertFalse( $subscriber->has_cap( Capabilities::CAP_MANAGE ) );
		$this->assertFalse( $subscriber->has_cap( Capabilities::CAP_APPROVE_AI ) );
	}

	public function test_remove_caps_clears_custom_capabilities_from_all_roles(): void {
		Capabilities::add_caps();
		Capabilities::remove_caps();

		$admin  = get_role( 'administrator' );
		$editor = get_role( 'editor' );

		$this->assertNotNull( $admin );
		$this->assertNotNull( $editor );

		$this->assertFalse( $admin->has_cap( Capabilities::CAP_MANAGE ) );
		$this->assertFalse( $admin->has_cap( Capabilities::CAP_APPROVE_AI ) );
		$this->assertFalse( $editor->has_cap( Capabilities::CAP_APPROVE_AI ) );
		$this->assertTrue( $admin->has_cap( 'manage_options' ) );
	}

	public function test_capability_helpers_respect_current_user_permissions(): void {
		RankMySEO_Test_State::$current_user_caps = [];
		$this->assertFalse( Capabilities::current_user_can_manage() );
		$this->assertFalse( Capabilities::current_user_can_approve_ai() );

		RankMySEO_Test_State::$current_user_caps = [
			Capabilities::CAP_APPROVE_AI => true,
		];
		$this->assertFalse( Capabilities::current_user_can_manage() );
		$this->assertTrue( Capabilities::current_user_can_approve_ai() );

		RankMySEO_Test_State::$current_user_caps = [
			'manage_options' => true,
		];
		$this->assertTrue( Capabilities::current_user_can_manage() );
		$this->assertTrue( Capabilities::current_user_can_approve_ai() );
	}
}
