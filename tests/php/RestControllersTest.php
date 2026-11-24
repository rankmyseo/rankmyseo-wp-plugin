<?php
/**
 * Unit tests for RankMySEO REST controllers and RestRegistry.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Core\Settings;
use RankMySEO\Database\Migrator;
use RankMySEO\Database\Schema;
use RankMySEO\Modules\ModuleManager;
use RankMySEO\Modules\Meta\MetaModule;
use RankMySEO\Modules\Redirects\RedirectsModule;
use RankMySEO\Container;
use RankMySEO\Rest\RestRegistry;
use RankMySEO\Rest\SettingsController;
use RankMySEO\Rest\StatusController;
use RankMySEO\Rest\UiPreferencesController;
use RankMySEO_Test_State;
use WP_Error;
use WP_REST_Request;
use wpdb;

final class RestControllersTest extends TestCase {
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	public function test_rest_registry_registers_all_v1_routes(): void {
		$container = new Container();
		$settings  = new Settings();
		$modules   = new ModuleManager( $settings );
		$migrator  = new Migrator( new wpdb(), static function (): void {} );

		$registry = new RestRegistry(
			[
				new SettingsController( $settings, $modules ),
				new StatusController( $settings, $modules, $migrator ),
				new UiPreferencesController( $settings ),
			]
		);

		$registry->register_routes();

		$routes = array_map(
			static fn( array $entry ): string => $entry['namespace'] . $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);

		$this->assertContains( 'rankmyseo/v1/settings', $routes );
		$this->assertContains( 'rankmyseo/v1/status', $routes );
		$this->assertContains( 'rankmyseo/v1/ui-preferences', $routes );
		$this->assertNotNull( $container );
	}

	public function test_permission_checks_enforce_capabilities(): void {
		$settings   = new Settings();
		$modules    = new ModuleManager( $settings );
		$controller = new SettingsController( $settings, $modules );

		$this->assertTrue( $controller->check_manage_permission() );

		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$manage_result                           = $controller->check_manage_permission();
		$this->assertInstanceOf( WP_Error::class, $manage_result );
		$this->assertSame( 'rankmyseo_forbidden', $manage_result->get_error_code() );
		$this->assertTrue( $controller->check_edit_posts_permission() );

		RankMySEO_Test_State::$current_user_caps = [];
		$edit_result                             = $controller->check_edit_posts_permission();
		$this->assertInstanceOf( WP_Error::class, $edit_result );
	}

	public function test_settings_controller_get_and_post_persist_module_toggles(): void {
		$container = new Container();
		$settings  = new Settings();
		$container->set( Settings::class, $settings );

		$modules = new ModuleManager( $settings );
		$modules->add( new MetaModule( $container ) );
		$modules->add( new RedirectsModule( $container ) );

		$controller = new SettingsController( $settings, $modules );

		$get_response = $controller->get_settings();
		$get_data     = $get_response->get_data();

		$this->assertSame( 200, $get_response->get_status() );
		$this->assertTrue( $get_data['settings']['modules']['meta'] );
		$this->assertCount( 2, $get_data['modules'] );

		$request = new WP_REST_Request();
		$request->set_json_params(
			[
				'site_name' => 'RankMySEO Demo',
				'modules'   => [
					'redirects' => false,
				],
			]
		);

		$post_response = $controller->update_settings( $request );
		$post_data     = $post_response->get_data();

		$this->assertTrue( $post_data['updated'] );
		$this->assertSame( 'RankMySEO Demo', $post_data['settings']['site_name'] );
		$this->assertFalse( $post_data['settings']['modules']['redirects'] );
		$this->assertTrue( $post_data['settings']['modules']['meta'] );
	}

	public function test_status_controller_reports_health_and_database_state(): void {
		$wpdb                  = new wpdb();
		$wpdb->existing_tables = array_values( Schema::get_table_names( 'wp_' ) );
		$migrator              = new Migrator( $wpdb, static function (): void {} );
		$migrator->migrate();

		$settings = new Settings();
		$modules  = new ModuleManager( $settings );

		$controller = new StatusController( $settings, $modules, $migrator );
		$response   = $controller->get_status();
		$data       = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['database']['tables_healthy'] );
		$this->assertSame( Schema::DB_VERSION, $data['database']['installed_version'] );
		$this->assertFalse( $data['cloud']['connected'] );
		$this->assertSame( 'local', $data['cloud']['tier'] );
		$this->assertTrue( $data['site']['blog_public'] );
		$this->assertArrayHasKey( 'elementor', $data['builders'] );
		$this->assertArrayHasKey( 'divi', $data['builders'] );
		$this->assertFalse( $data['builders']['elementor']['active'] );
		$this->assertFalse( $data['builders']['divi']['active'] );
	}

	public function test_ui_preferences_controller_updates_per_user_mode_and_wizard(): void {
		$settings   = new Settings();
		$controller = new UiPreferencesController( $settings );

		$initial = $controller->get_preferences()->get_data();
		$this->assertSame( 'simple', $initial['ui_mode'] );
		$this->assertFalse( $initial['wizard_dismissed'] );

		$request = new WP_REST_Request();
		$request->set_json_params(
			[
				'ui_mode'          => 'advanced',
				'wizard_dismissed' => true,
			]
		);

		$updated = $controller->update_preferences( $request )->get_data();
		$this->assertSame( 'advanced', $updated['ui_mode'] );
		$this->assertTrue( $updated['wizard_dismissed'] );
	}
}
