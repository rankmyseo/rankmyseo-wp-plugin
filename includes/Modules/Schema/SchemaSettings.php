<?php
/**
 * Schema module settings repository.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Stores site-wide schema choices outside the core settings option.
 */
final class SchemaSettings {
	/**
	 * Option key. Kept compact enough to autoload.
	 */
	public const OPTION_KEY = 'rankmyseo_schema_settings';

	/**
	 * LocalBusiness subtypes offered in Advanced mode.
	 *
	 * @var list<string>
	 */
	public const LOCAL_BUSINESS_TYPES = [
		'LocalBusiness',
		'Dentist',
		'Restaurant',
		'Store',
		'MedicalBusiness',
		'LegalService',
		'ProfessionalService',
		'HealthAndBeautyBusiness',
		'HomeAndConstructionBusiness',
		'AutomotiveBusiness',
		'FoodEstablishment',
		'LodgingBusiness',
	];

	/**
	 * Weekday names accepted for opening hours.
	 *
	 * @var list<string>
	 */
	public const WEEKDAYS = [
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
		'Sunday',
	];

	/**
	 * Default post-type to schema-type map.
	 *
	 * @return array<string, string>
	 */
	public function default_post_type_schema(): array {
		return [
			'post'    => 'BlogPosting',
			'page'    => 'WebPage',
			'product' => 'Product',
		];
	}

	/**
	 * Typed defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'entity_type'                => 'organization',
			'name'                       => '',
			'description'                => '',
			'logo_url'                   => '',
			'telephone'                  => '',
			'same_as'                    => [],
			'search_action'              => true,
			'currency'                   => 'USD',
			'post_type_schema'           => $this->default_post_type_schema(),
			'woocommerce_product'        => true,
			'local_business_enabled'     => false,
			'local_business_type'        => 'LocalBusiness',
			'local_business_name'        => '',
			'local_business_telephone'   => '',
			'local_business_price_range' => '',
			'address_street'             => '',
			'address_city'               => '',
			'address_region'             => '',
			'address_postal_code'        => '',
			'address_country'            => '',
			'geo_latitude'               => '',
			'geo_longitude'              => '',
			'opening_hours'              => [],
		];
	}

	/**
	 * Whether the schema option has been saved at least once.
	 *
	 * @return bool
	 */
	public function is_saved(): bool {
		$stored = get_option( self::OPTION_KEY, null );
		return is_array( $stored );
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return $this->sanitize( $stored );
	}

	/**
	 * Settings prepared for the admin screen, filling identity from core site settings when unset.
	 *
	 * @param string $fallback_name Publisher name from core settings or the site title.
	 * @param string $fallback_type `organization` or `person`.
	 * @return array<string, mixed>
	 */
	public function for_admin( string $fallback_name, string $fallback_type ): array {
		$data = $this->all();

		if ( '' === $data['name'] && '' !== $fallback_name ) {
			$data['name'] = $fallback_name;
		}

		if ( ! $this->is_saved() && in_array( $fallback_type, [ 'organization', 'person' ], true ) ) {
			$data['entity_type'] = $fallback_type;
		}

		return $data;
	}

	/**
	 * Persist a partial settings update.
	 *
	 * @param array<string, mixed> $values Incoming values.
	 * @return array<string, mixed>
	 */
	public function update( array $values ): array {
		$sanitized = $this->sanitize( $values, $this->all() );
		update_option( self::OPTION_KEY, $sanitized, true );

		return $sanitized;
	}

	/**
	 * Whether automatic WooCommerce Product nodes (and duplicate suppression) are on.
	 *
	 * @return bool
	 */
	public function is_product_schema_enabled(): bool {
		return (bool) $this->all()['woocommerce_product'];
	}

	/**
	 * Site defaults and the admin screen for the per-page schema control.
	 *
	 * @return array{schemaDefaults: array<string, string>, schemaSettingsUrl: string, productSchemaEnabled: bool}
	 */
	public function editor_hints(): array {
		$map      = $this->all()['post_type_schema'];
		$defaults = [];
		if ( is_array( $map ) ) {
			foreach ( $map as $post_type => $schema_type ) {
				$key  = sanitize_key( (string) $post_type );
				$type = SchemaMeta::sanitize_type( is_string( $schema_type ) ? $schema_type : '' );
				if ( '' === $key || '' === $type ) {
					continue;
				}
				$defaults[ $key ] = $type;
			}
		}

		$url = function_exists( 'admin_url' ) ? (string) admin_url( 'admin.php?page=rankmyseo-schema' ) : 'admin.php?page=rankmyseo-schema';

		return [
			'schemaDefaults'       => $defaults,
			'schemaSettingsUrl'    => $url,
			'productSchemaEnabled' => $this->is_product_schema_enabled(),
		];
	}

	/**
	 * Schema type configured for a post type.
	 *
	 * @param string $post_type Post type name.
	 * @return string
	 */
	public function schema_type_for_post_type( string $post_type ): string {
		$map = $this->all()['post_type_schema'];
		$key = sanitize_key( $post_type );
		if ( is_array( $map ) && isset( $map[ $key ] ) && is_string( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		$defaults = $this->default_post_type_schema();
		return $defaults[ $key ] ?? 'WebPage';
	}

	/**
	 * Sanitize incoming values onto a base state.
	 *
	 * @param array<string, mixed> $input Raw values.
	 * @param array<string, mixed> $base  Existing state.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input, array $base = [] ): array {
		$state = array_merge( $this->defaults(), $base );

		if ( array_key_exists( 'entity_type', $input ) ) {
			$entity = sanitize_key( (string) $input['entity_type'] );
			if ( in_array( $entity, [ 'organization', 'person' ], true ) ) {
				$state['entity_type'] = $entity;
			}
		}

		if ( array_key_exists( 'name', $input ) ) {
			$state['name'] = $this->clean_line( $input['name'], 120 );
		}

		if ( array_key_exists( 'description', $input ) ) {
			$state['description'] = $this->clean_line( $input['description'], 500 );
		}

		if ( array_key_exists( 'logo_url', $input ) ) {
			$state['logo_url'] = esc_url_raw( trim( (string) $input['logo_url'] ) );
		}

		if ( array_key_exists( 'telephone', $input ) ) {
			$state['telephone'] = $this->clean_line( $input['telephone'], 40 );
		}

		if ( array_key_exists( 'same_as', $input ) ) {
			$state['same_as'] = $this->sanitize_urls( $input['same_as'] );
		}

		if ( array_key_exists( 'search_action', $input ) ) {
			$state['search_action'] = $this->to_bool( $input['search_action'] );
		}

		if ( array_key_exists( 'currency', $input ) ) {
			$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $input['currency'] ) ?? '' );
			if ( 1 === preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				$state['currency'] = $currency;
			}
		}

		if ( isset( $input['post_type_schema'] ) && is_array( $input['post_type_schema'] ) ) {
			$map = is_array( $state['post_type_schema'] ) ? $state['post_type_schema'] : [];
			foreach ( $input['post_type_schema'] as $post_type => $schema_type ) {
				$key = sanitize_key( (string) $post_type );
				if ( '' === $key ) {
					continue;
				}
				$type = SchemaMeta::sanitize_type( (string) $schema_type );
				if ( '' === $type ) {
					continue;
				}
				$map[ $key ] = $type;
			}
			$state['post_type_schema'] = $map;
		}

		if ( array_key_exists( 'woocommerce_product', $input ) ) {
			$state['woocommerce_product'] = $this->to_bool( $input['woocommerce_product'] );
		}

		if ( array_key_exists( 'local_business_enabled', $input ) ) {
			$state['local_business_enabled'] = $this->to_bool( $input['local_business_enabled'] );
		}

		if ( array_key_exists( 'local_business_type', $input ) ) {
			$type = preg_replace( '/[^A-Za-z0-9]/', '', (string) $input['local_business_type'] ) ?? '';
			if ( in_array( $type, self::LOCAL_BUSINESS_TYPES, true ) ) {
				$state['local_business_type'] = $type;
			}
		}

		foreach (
			[
				'local_business_name'        => 120,
				'local_business_telephone'   => 40,
				'local_business_price_range' => 12,
				'address_street'             => 180,
				'address_city'               => 80,
				'address_region'             => 80,
				'address_postal_code'        => 20,
				'address_country'            => 2,
			] as $field => $max
		) {
			if ( array_key_exists( $field, $input ) ) {
				$state[ $field ] = $this->clean_line( $input[ $field ], $max );
			}
		}

		if ( array_key_exists( 'address_country', $input ) ) {
			$state['address_country'] = strtoupper( (string) $state['address_country'] );
		}

		if ( array_key_exists( 'geo_latitude', $input ) ) {
			$state['geo_latitude'] = $this->sanitize_coordinate( $input['geo_latitude'], -90, 90 );
		}

		if ( array_key_exists( 'geo_longitude', $input ) ) {
			$state['geo_longitude'] = $this->sanitize_coordinate( $input['geo_longitude'], -180, 180 );
		}

		if ( array_key_exists( 'opening_hours', $input ) ) {
			$state['opening_hours'] = $this->sanitize_hours( $input['opening_hours'] );
		}

		return $state;
	}

	/**
	 * Build the LocalBusiness snapshot consumed by the graph builder.
	 *
	 * @param bool $force Include the snapshot even when the site-wide toggle is off.
	 * @return array<string, mixed>|null
	 */
	public function local_business_snapshot( bool $force = false ): ?array {
		$settings = $this->all();
		if ( ! $force && empty( $settings['local_business_enabled'] ) ) {
			return null;
		}

		return [
			'type'          => (string) $settings['local_business_type'],
			'name'          => (string) $settings['local_business_name'],
			'telephone'     => '' !== (string) $settings['local_business_telephone']
				? (string) $settings['local_business_telephone']
				: (string) $settings['telephone'],
			'price_range'   => (string) $settings['local_business_price_range'],
			'image'         => (string) $settings['logo_url'],
			'street'        => (string) $settings['address_street'],
			'city'          => (string) $settings['address_city'],
			'region'        => (string) $settings['address_region'],
			'postal_code'   => (string) $settings['address_postal_code'],
			'country'       => (string) $settings['address_country'],
			'latitude'      => (string) $settings['geo_latitude'],
			'longitude'     => (string) $settings['geo_longitude'],
			'opening_hours' => is_array( $settings['opening_hours'] ) ? $settings['opening_hours'] : [],
		];
	}

	/**
	 * Collapse a value to a single trimmed line.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum characters.
	 * @return string
	 */
	private function clean_line( $value, int $max ): string {
		$text = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		if ( mb_strlen( $text ) > $max ) {
			$text = mb_substr( $text, 0, $max );
		}

		return $text;
	}

	/**
	 * Keep only absolute http(s) profile URLs.
	 *
	 * @param mixed $value Newline string or list.
	 * @return list<string>
	 */
	private function sanitize_urls( $value ): array {
		if ( is_string( $value ) ) {
			$parts = preg_split( '/\s+/', $value );
			if ( false === $parts ) {
				$parts = [];
			}
		} elseif ( is_array( $value ) ) {
			$parts = $value;
		} else {
			return [];
		}

		$urls = [];
		foreach ( $parts as $part ) {
			if ( count( $urls ) >= 15 ) {
				break;
			}
			$url = esc_url_raw( trim( (string) $part ) );
			if ( '' === $url || in_array( $url, $urls, true ) ) {
				continue;
			}
			$urls[] = $url;
		}

		return $urls;
	}

	/**
	 * Sanitize a latitude or longitude to a normalized string.
	 *
	 * @param mixed $value Raw value.
	 * @param float $min   Inclusive minimum.
	 * @param float $max   Inclusive maximum.
	 * @return string
	 */
	private function sanitize_coordinate( $value, float $min, float $max ): string {
		$string = trim( (string) $value );
		if ( '' === $string || ! is_numeric( $string ) ) {
			return '';
		}

		$number = (float) $string;
		if ( $number < $min || $number > $max ) {
			return '';
		}

		return rtrim( rtrim( sprintf( '%.6F', $number ), '0' ), '.' );
	}

	/**
	 * Sanitize opening-hours rows.
	 *
	 * @param mixed $value Raw rows.
	 * @return list<array{day: string, opens: string, closes: string}>
	 */
	private function sanitize_hours( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$rows = [];
		foreach ( $value as $row ) {
			if ( count( $rows ) >= 14 || ! is_array( $row ) ) {
				continue;
			}
			$day = isset( $row['day'] ) ? sanitize_text_field( (string) $row['day'] ) : '';
			if ( ! in_array( $day, self::WEEKDAYS, true ) ) {
				continue;
			}
			$opens  = $this->sanitize_time( $row['opens'] ?? '' );
			$closes = $this->sanitize_time( $row['closes'] ?? '' );
			if ( '' === $opens || '' === $closes ) {
				continue;
			}
			$rows[] = [
				'day'    => $day,
				'opens'  => $opens,
				'closes' => $closes,
			];
		}

		return $rows;
	}

	/**
	 * Accept a 24-hour HH:MM time.
	 *
	 * @param mixed $value Raw time.
	 * @return string
	 */
	private function sanitize_time( $value ): string {
		$time = trim( (string) $value );
		if ( 1 === preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return $time;
		}

		return '';
	}

	/**
	 * Coerce mixed input to a boolean.
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
