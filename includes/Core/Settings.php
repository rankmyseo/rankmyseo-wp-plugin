<?php
/**
 * Typed settings manager and per-user UI preferences repository.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Core;

/**
 * Manages plugin options and per-user UI mode preferences with strict sanitization.
 */
final class Settings {
	/**
	 * Primary autoloaded WordPress option key.
	 */
	public const OPTION_KEY = 'rankmyseo_settings';

	/**
	 * User meta key storing the per-user experience mode ("simple" or "advanced").
	 */
	public const USER_META_UI_MODE = '_rankmyseo_ui_mode';

	/**
	 * User meta key storing whether the user dismissed the onboarding wizard.
	 */
	public const USER_META_WIZARD_DISMISSED = '_rankmyseo_wizard_dismissed';

	/**
	 * Allowed experience mode values.
	 *
	 * @var list<string>
	 */
	public const ALLOWED_UI_MODES = [ 'simple', 'advanced' ];

	/**
	 * Allowed site identity types.
	 *
	 * @var list<string>
	 */
	public const ALLOWED_SITE_TYPES = [ 'organization', 'person' ];

	/**
	 * Allowed title separators.
	 *
	 * @var list<string>
	 */
	public const ALLOWED_SEPARATORS = [ '-', '|', '·', '—', '/', '•' ];

	/**
	 * Return strongly typed default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return [
			'default_ui_mode'        => 'simple',
			'site_type'              => 'organization',
			'site_name'              => '',
			'title_separator'        => '|',
			'keep_data_on_uninstall' => true,
			'telemetry_opt_in'       => false,
			'wizard_completed'       => false,
			'wizard_dismissed'       => false,
			'modules'                => [
				'meta'      => true,
				'analyzer'  => true,
				'schema'    => true,
				'sitemaps'  => true,
				'redirects' => true,
				'importer'  => true,
				'cloud'     => false,
				'ai_queue'  => false,
			],
		];
	}

	/**
	 * Ensure the settings option exists with defaults on activation.
	 *
	 * @return void
	 */
	public function ensure_defaults(): void {
		$existing = get_option( self::OPTION_KEY, null );

		if ( null === $existing || false === $existing ) {
			$defaults              = $this->get_defaults();
			$defaults['site_name'] = sanitize_text_field( (string) get_bloginfo( 'name' ) );
			add_option( self::OPTION_KEY, $defaults, '', true );
			return;
		}

		if ( is_array( $existing ) ) {
			$merged = $this->sanitize( $existing, $this->get_defaults() );
			update_option( self::OPTION_KEY, $merged, true );
		}
	}

	/**
	 * Return all settings merged with typed defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return $this->sanitize( $stored, $this->get_defaults() );
	}

	/**
	 * Retrieve a single setting value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Optional fallback value if key does not exist.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		$all = $this->all();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $fallback;
	}

	/**
	 * Update one or more settings with validation and sanitization.
	 *
	 * @param array<string, mixed> $values Partial or full settings map.
	 * @return array<string, mixed> Sanitized full settings after save.
	 */
	public function update( array $values ): array {
		$current   = $this->all();
		$sanitized = $this->sanitize( $values, $current );

		update_option( self::OPTION_KEY, $sanitized, true );

		return $sanitized;
	}

	/**
	 * Check whether a given module is enabled.
	 *
	 * @param string $module_id Module identifier.
	 * @return bool
	 */
	public function is_module_enabled( string $module_id ): bool {
		$modules = $this->get( 'modules', [] );

		if ( is_array( $modules ) && array_key_exists( $module_id, $modules ) ) {
			return (bool) $modules[ $module_id ];
		}

		$defaults = $this->get_defaults();
		$default  = is_array( $defaults['modules'] ) && array_key_exists( $module_id, $defaults['modules'] )
			? (bool) $defaults['modules'][ $module_id ]
			: false;

		return $default;
	}

	/**
	 * Toggle a single module enabled state.
	 *
	 * @param string $module_id Module identifier.
	 * @param bool   $enabled   Target enabled state.
	 * @return array<string, mixed> Updated settings.
	 */
	public function set_module_enabled( string $module_id, bool $enabled ): array {
		return $this->update(
			[
				'modules' => [
					$module_id => $enabled,
				],
			]
		);
	}

	/**
	 * Sanitize incoming settings against a base state and typed defaults.
	 *
	 * @param array<string, mixed> $input Raw input values.
	 * @param array<string, mixed> $base  Existing settings state.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input, array $base = [] ): array {
		$defaults = $this->get_defaults();
		$state    = array_merge( $defaults, $base );

		if ( array_key_exists( 'default_ui_mode', $input ) ) {
			$mode = sanitize_key( (string) $input['default_ui_mode'] );
			if ( in_array( $mode, self::ALLOWED_UI_MODES, true ) ) {
				$state['default_ui_mode'] = $mode;
			}
		}

		if ( array_key_exists( 'site_type', $input ) ) {
			$site_type = sanitize_key( (string) $input['site_type'] );
			if ( in_array( $site_type, self::ALLOWED_SITE_TYPES, true ) ) {
				$state['site_type'] = $site_type;
			}
		}

		if ( array_key_exists( 'site_name', $input ) ) {
			$site_name          = sanitize_text_field( (string) $input['site_name'] );
			$state['site_name'] = mb_substr( $site_name, 0, 120 );
		}

		if ( array_key_exists( 'title_separator', $input ) ) {
			$separator = trim( (string) $input['title_separator'] );
			if ( in_array( $separator, self::ALLOWED_SEPARATORS, true ) ) {
				$state['title_separator'] = $separator;
			}
		}

		foreach ( [ 'keep_data_on_uninstall', 'telemetry_opt_in', 'wizard_completed', 'wizard_dismissed' ] as $bool_key ) {
			if ( array_key_exists( $bool_key, $input ) ) {
				$state[ $bool_key ] = $this->to_bool( $input[ $bool_key ] );
			}
		}

		$default_modules = is_array( $defaults['modules'] ) ? $defaults['modules'] : [];
		$base_modules    = isset( $base['modules'] ) && is_array( $base['modules'] ) ? $base['modules'] : [];
		$modules         = array_merge( $default_modules, $base_modules );

		if ( isset( $input['modules'] ) && is_array( $input['modules'] ) ) {
			foreach ( $input['modules'] as $module_id => $enabled ) {
				$clean_id = sanitize_key( (string) $module_id );
				if ( '' === $clean_id || ! array_key_exists( $clean_id, $default_modules ) ) {
					continue;
				}
				$modules[ $clean_id ] = $this->to_bool( $enabled );
			}
		}

		$state['modules'] = $modules;

		return $state;
	}

	/**
	 * Get the effective UI mode ("simple" or "advanced") for a given user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string
	 */
	public function get_user_ui_mode( int $user_id ): string {
		if ( $user_id > 0 ) {
			$stored = get_user_meta( $user_id, self::USER_META_UI_MODE, true );
			if ( is_string( $stored ) && in_array( $stored, self::ALLOWED_UI_MODES, true ) ) {
				return $stored;
			}
		}

		$default_mode = (string) $this->get( 'default_ui_mode', 'simple' );
		return in_array( $default_mode, self::ALLOWED_UI_MODES, true ) ? $default_mode : 'simple';
	}

	/**
	 * Persist a user's UI mode preference in WordPress user meta.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $mode    "simple" or "advanced".
	 * @return string Sanitized mode value.
	 */
	public function set_user_ui_mode( int $user_id, string $mode ): string {
		$clean_mode = sanitize_key( $mode );
		if ( ! in_array( $clean_mode, self::ALLOWED_UI_MODES, true ) ) {
			$clean_mode = 'simple';
		}

		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::USER_META_UI_MODE, $clean_mode );
		}

		return $clean_mode;
	}

	/**
	 * Check whether the onboarding wizard is dismissed for a user or site-wide.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public function is_wizard_dismissed_for_user( int $user_id ): bool {
		if ( (bool) $this->get( 'wizard_completed', false ) || (bool) $this->get( 'wizard_dismissed', false ) ) {
			return true;
		}

		if ( $user_id > 0 ) {
			return (bool) get_user_meta( $user_id, self::USER_META_WIZARD_DISMISSED, true );
		}

		return false;
	}

	/**
	 * Persist per-user wizard dismissal state.
	 *
	 * @param int  $user_id   WordPress user ID.
	 * @param bool $dismissed Dismissal flag.
	 * @return bool
	 */
	public function set_user_wizard_dismissed( int $user_id, bool $dismissed ): bool {
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::USER_META_WIZARD_DISMISSED, $dismissed ? '1' : '0' );
		}

		return $dismissed;
	}

	/**
	 * Safely coerce mixed values to boolean.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, [ '1', 'true', 'yes', 'on' ], true ) ) {
				return true;
			}
			if ( in_array( $lower, [ '0', 'false', 'no', 'off', '' ], true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}
}
