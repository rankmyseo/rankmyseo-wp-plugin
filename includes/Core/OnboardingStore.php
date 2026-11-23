<?php
/**
 * Dedicated onboarding and brand-identity storage.
 *
 * Kept out of rankmyseo_settings module blobs so sibling modules can own
 * their own options without merge conflicts. Logo and brand defaults live here.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Core;

/**
 * Reads and writes onboarding completion plus site identity.
 */
final class OnboardingStore {
	/**
	 * Option set to 1 when guided setup is completed.
	 */
	public const OPTION_COMPLETED = 'rankmyseo_onboarding_completed';

	/**
	 * Option holding organization/person identity and logo defaults.
	 */
	public const OPTION_IDENTITY = 'rankmyseo_site_identity';

	/**
	 * Module ids the wizard is allowed to toggle.
	 *
	 * @var list<string>
	 */
	public const WIZARD_MODULES = [ 'meta', 'schema', 'sitemaps', 'redirects' ];

	/**
	 * Shared settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Shared settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether guided setup has been completed.
	 *
	 * @return bool
	 */
	public function is_completed(): bool {
		if ( (bool) get_option( self::OPTION_COMPLETED, false ) ) {
			return true;
		}

		return (bool) $this->settings->get( 'wizard_completed', false );
	}

	/**
	 * Return the public onboarding state for the setup wizard.
	 *
	 * @return array<string, mixed>
	 */
	public function get_state(): array {
		$identity = $this->get_identity();

		return [
			'completed'       => $this->is_completed(),
			'site_identity'   => $identity,
			'modules'         => $this->wizard_modules(),
			'blog_public'     => '0' !== (string) get_option( 'blog_public', '1' ),
			'cloud_connected' => false,
			'skipped_cloud'   => (bool) $identity['skipped_cloud'],
		];
	}

	/**
	 * Persist wizard choices and mark onboarding complete.
	 *
	 * @param array<string, mixed> $input   Sanitized-later request payload.
	 * @param int                  $user_id Current user ID.
	 * @return array<string, mixed> Updated public state.
	 */
	public function complete( array $input, int $user_id ): array {
		$identity = $this->get_identity();

		if ( array_key_exists( 'site_type', $input ) ) {
			$site_type = sanitize_key( (string) $input['site_type'] );
			if ( in_array( $site_type, Settings::ALLOWED_SITE_TYPES, true ) ) {
				$identity['site_type'] = $site_type;
			}
		}

		if ( array_key_exists( 'site_name', $input ) ) {
			$site_name             = sanitize_text_field( (string) $input['site_name'] );
			$identity['site_name'] = function_exists( 'mb_substr' ) ? mb_substr( $site_name, 0, 120 ) : substr( $site_name, 0, 120 );
		}

		if ( array_key_exists( 'title_separator', $input ) ) {
			$separator = trim( (string) $input['title_separator'] );
			if ( in_array( $separator, Settings::ALLOWED_SEPARATORS, true ) ) {
				$identity['title_separator'] = $separator;
			}
		}

		if ( array_key_exists( 'logo_id', $input ) ) {
			$identity['logo_id'] = absint( $input['logo_id'] );
		}

		if ( array_key_exists( 'logo_url', $input ) ) {
			$identity['logo_url'] = $this->sanitize_logo_url( (string) $input['logo_url'] );
		}

		$identity['skipped_cloud'] = ! array_key_exists( 'skip_cloud', $input ) || $this->to_bool( $input['skip_cloud'] );
		$identity['updated_at']    = gmdate( 'Y-m-d H:i:s' );

		update_option( self::OPTION_IDENTITY, $identity, false );
		update_option( self::OPTION_COMPLETED, 1, false );

		$settings_update = [
			'site_type'        => $identity['site_type'],
			'site_name'        => $identity['site_name'],
			'title_separator'  => $identity['title_separator'],
			'wizard_completed' => true,
			'wizard_dismissed' => true,
		];

		if ( isset( $input['modules'] ) && is_array( $input['modules'] ) ) {
			$settings_update['modules'] = $this->sanitize_modules( $input['modules'] );
		}

		$this->settings->update( $settings_update );
		$this->settings->set_user_wizard_dismissed( $user_id, true );

		return $this->get_state();
	}

	/**
	 * Return stored identity merged with safe defaults.
	 *
	 * Logo fields stay in this option and are not copied into rankmyseo_settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_identity(): array {
		$stored = get_option( self::OPTION_IDENTITY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		$site_type = isset( $stored['site_type'] ) ? sanitize_key( (string) $stored['site_type'] ) : '';
		if ( ! in_array( $site_type, Settings::ALLOWED_SITE_TYPES, true ) ) {
			$site_type = (string) $this->settings->get( 'site_type', 'organization' );
		}

		$site_name = isset( $stored['site_name'] ) ? sanitize_text_field( (string) $stored['site_name'] ) : '';
		if ( '' === $site_name ) {
			$site_name = sanitize_text_field( (string) $this->settings->get( 'site_name', '' ) );
		}
		if ( '' === $site_name ) {
			$site_name = sanitize_text_field( (string) get_bloginfo( 'name' ) );
		}

		$separator = isset( $stored['title_separator'] ) ? (string) $stored['title_separator'] : '';
		if ( ! in_array( $separator, Settings::ALLOWED_SEPARATORS, true ) ) {
			$separator = (string) $this->settings->get( 'title_separator', '|' );
		}

		return [
			'site_type'       => $site_type,
			'site_name'       => $site_name,
			'logo_id'         => isset( $stored['logo_id'] ) ? absint( $stored['logo_id'] ) : 0,
			'logo_url'        => isset( $stored['logo_url'] ) ? $this->sanitize_logo_url( (string) $stored['logo_url'] ) : '',
			'title_separator' => $separator,
			'skipped_cloud'   => ! isset( $stored['skipped_cloud'] ) || $this->to_bool( $stored['skipped_cloud'] ),
		];
	}

	/**
	 * Return the four wizard module toggles.
	 *
	 * @return array<string, bool>
	 */
	public function wizard_modules(): array {
		$modules = [];
		foreach ( self::WIZARD_MODULES as $module_id ) {
			$modules[ $module_id ] = $this->settings->is_module_enabled( $module_id );
		}

		return $modules;
	}

	/**
	 * Keep only known wizard module flags.
	 *
	 * @param array<mixed> $modules Raw module map.
	 * @return array<string, bool>
	 */
	private function sanitize_modules( array $modules ): array {
		$clean = [];
		foreach ( self::WIZARD_MODULES as $module_id ) {
			if ( array_key_exists( $module_id, $modules ) ) {
				$clean[ $module_id ] = $this->to_bool( $modules[ $module_id ] );
			}
		}

		return $clean;
	}

	/**
	 * Accept only http(s) logo URLs.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function sanitize_logo_url( string $url ): string {
		$clean = esc_url_raw( trim( $url ) );
		if ( '' === $clean ) {
			return '';
		}

		$scheme = wp_parse_url( $clean, PHP_URL_SCHEME );
		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), [ 'http', 'https' ], true ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * Coerce mixed request values to boolean.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), [ '1', 'true', 'yes', 'on' ], true );
		}

		return (bool) $value;
	}
}
