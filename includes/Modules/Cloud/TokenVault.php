<?php
/**
 * Encrypted at-rest storage for RankMySEO Cloud credentials.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

/**
 * Stores the site token and webhook secret encrypted in a WordPress option.
 *
 * Plaintext credentials are never written to options, REST responses, or logs.
 * Non-secret connection details live in a separate option so admin screens can
 * render status without decrypting on every view.
 */
final class TokenVault {
	/**
	 * Option holding the encrypted credential blob. Not autoloaded.
	 */
	public const CONNECTION_OPTION = 'rankmyseo_cloud_connection';

	/**
	 * Option holding non-secret connection details. Not autoloaded.
	 */
	public const SETTINGS_OPTION = 'rankmyseo_cloud_settings';

	/**
	 * Cipher name for libsodium secretbox.
	 */
	public const CIPHER_SODIUM = 'sodium';

	/**
	 * Cipher name for the OpenSSL AES-256-GCM fallback.
	 */
	public const CIPHER_OPENSSL = 'aes-256-gcm';

	/**
	 * Forced cipher for tests. Null selects sodium, then OpenSSL.
	 *
	 * @var string|null
	 */
	private ?string $forced_cipher;

	/**
	 * Request-local decrypted secrets.
	 *
	 * @var array{token: string, webhook_secret: string, poll_token: string}|null
	 */
	private ?array $secrets = null;

	/**
	 * Request-local reconnect flag. Mirrors the public option for this instance.
	 *
	 * @var bool
	 */
	private bool $reconnect_required = false;

	/**
	 * Constructor.
	 *
	 * @param string|null $forced_cipher Optional cipher override (`sodium` or `aes-256-gcm`).
	 */
	public function __construct( ?string $forced_cipher = null ) {
		$this->forced_cipher = $forced_cipher;
	}

	/**
	 * Whether a usable connection token is stored and the site is not awaiting reconnect.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		$state = $this->read_public();
		return ! empty( $state['connected'] )
			&& empty( $state['reconnect_required'] )
			&& '' !== $this->get_secret( 'token' );
	}

	/**
	 * Whether an authenticated call was rejected and the admin should reconnect.
	 *
	 * @return bool
	 */
	public function needs_reconnect(): bool {
		if ( $this->reconnect_required ) {
			return true;
		}
		$state = $this->read_public();
		return ! empty( $state['reconnect_required'] );
	}

	/**
	 * Whether any bearer token is stored, including when reconnect is required.
	 *
	 * @return bool
	 */
	public function has_token(): bool {
		return '' !== $this->get_secret( 'token' );
	}

	/**
	 * Read one decrypted secret. Returns an empty string when it is missing.
	 *
	 * @param string $key Secret key: token, webhook_secret, or poll_token.
	 * @return string
	 */
	public function get_secret( string $key ): string {
		$secrets = $this->read_secrets();
		if ( ! isset( $secrets[ $key ] ) ) {
			return '';
		}
		return (string) $secrets[ $key ];
	}

	/**
	 * Merge and encrypt credential fields.
	 *
	 * @param array<string, string> $secrets Keys among token, webhook_secret, poll_token.
	 * @return void
	 * @throws \RuntimeException When the host cannot encrypt.
	 */
	public function save_secrets( array $secrets ): void {
		$current = $this->read_secrets();
		foreach ( array( 'token', 'webhook_secret', 'poll_token' ) as $key ) {
			if ( array_key_exists( $key, $secrets ) ) {
				$current[ $key ] = (string) $secrets[ $key ];
			}
		}
		$this->write_secrets( $current );
	}

	/**
	 * Merge non-secret connection details.
	 *
	 * @param array<string, mixed> $patch Fields to update.
	 * @return array<string, mixed> Public state safe for REST.
	 */
	public function update_public( array $patch ): array {
		if ( array_key_exists( 'reconnect_required', $patch ) ) {
			$this->reconnect_required = ! empty( $patch['reconnect_required'] );
		}
		$state = array_merge( $this->read_public(), $patch );
		$state = $this->sanitize_public( $state );
		update_option( self::SETTINGS_OPTION, $state, false );
		return $this->public_state();
	}

	/**
	 * Public connection snapshot. Never includes tokens or the webhook secret.
	 *
	 * @return array<string, mixed>
	 */
	public function public_state(): array {
		$state     = $this->read_public();
		$has_token = $this->has_token();
		$connected = ! empty( $state['connected'] ) && empty( $state['reconnect_required'] ) && $has_token;

		if ( ! empty( $state['reconnect_required'] ) && $has_token ) {
			$health = 'reconnect';
		} elseif ( $connected ) {
			$health = 'degraded' === $state['health'] ? 'degraded' : 'connected';
		} else {
			$health = 'disconnected';
		}

		$pairing = null;
		if ( '' !== $state['pairing_code'] ) {
			$pairing = array(
				'code'       => $state['pairing_code'],
				'pair_url'   => $state['pairing_url'],
				'expires_at' => $state['pairing_expires_at'],
			);
		}

		return array(
			'connected'          => $connected,
			'reconnect_required' => (bool) $state['reconnect_required'],
			'health'             => $health,
			'site_name'          => (string) $state['site_name'],
			'site_host'          => (string) $state['site_host'],
			'site_url'           => (string) $state['site_url'],
			'domain'             => (string) $state['domain'],
			'project_id'         => (string) $state['project_id'],
			'connection_id'      => (string) $state['connection_id'],
			'plan_id'            => (string) $state['plan_id'],
			'plan_label'         => (string) $state['plan_label'],
			'token_prefix'       => (string) $state['token_prefix'],
			'last_sync_at'       => (string) $state['last_sync_at'],
			'last_error'         => (string) $state['last_error'],
			'pairing'            => $pairing,
		);
	}

	/**
	 * Flag the connection so background sync stops until an admin reconnects.
	 *
	 * @param string $message Calm explanation.
	 * @return void
	 */
	public function mark_reconnect( string $message ): void {
		$this->update_public(
			array(
				'connected'          => false,
				'reconnect_required' => true,
				'health'             => 'reconnect',
				'last_error'         => $message,
			)
		);
	}

	/**
	 * Delete credentials, public connection details, and cached cloud reads.
	 *
	 * @return void
	 */
	public function wipe(): void {
		delete_option( self::CONNECTION_OPTION );
		delete_option( self::SETTINGS_OPTION );
		delete_transient( 'rankmyseo_cloud_insights' );
		delete_transient( 'rankmyseo_cloud_sync_lock' );
		$this->reconnect_required = false;
		$this->secrets            = array(
			'token'          => '',
			'webhook_secret' => '',
			'poll_token'     => '',
		);
	}

	/**
	 * Short display prefix for a bearer token. Never returns the full token.
	 *
	 * @param string $token Full rms_wp_ token.
	 * @return string
	 */
	public function prefix_from_token( string $token ): string {
		if ( ! str_starts_with( $token, 'rms_wp_' ) ) {
			return '';
		}
		$prefix = substr( $token, 0, 13 );
		if ( ! is_string( $prefix ) || 1 !== preg_match( '/^rms_wp_[A-Za-z0-9_-]{6}$/', $prefix ) ) {
			return '';
		}
		return $prefix;
	}

	/**
	 * Default public connection record.
	 *
	 * @return array<string, mixed>
	 */
	private function default_public(): array {
		return array(
			'connected'          => false,
			'reconnect_required' => false,
			'health'             => 'disconnected',
			'site_name'          => '',
			'site_host'          => '',
			'site_url'           => '',
			'domain'             => '',
			'project_id'         => '',
			'connection_id'      => '',
			'plan_id'            => '',
			'plan_label'         => '',
			'token_prefix'       => '',
			'last_sync_at'       => '',
			'last_error'         => '',
			'pairing_code'       => '',
			'pairing_url'        => '',
			'pairing_expires_at' => '',
		);
	}

	/**
	 * Read and sanitize the public option.
	 *
	 * @return array<string, mixed>
	 */
	private function read_public(): array {
		$stored = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return $this->sanitize_public( $stored );
	}

	/**
	 * Sanitize public fields and drop anything that looks like a credential.
	 *
	 * @param array<string, mixed> $input Raw option or patch.
	 * @return array<string, mixed>
	 */
	private function sanitize_public( array $input ): array {
		$state = $this->default_public();

		$state['connected']          = ! empty( $input['connected'] );
		$state['reconnect_required'] = ! empty( $input['reconnect_required'] );

		$health = isset( $input['health'] ) ? sanitize_key( (string) $input['health'] ) : 'disconnected';
		if ( ! in_array( $health, array( 'disconnected', 'connected', 'reconnect', 'degraded' ), true ) ) {
			$health = 'disconnected';
		}
		$state['health'] = $health;

		foreach ( array( 'site_name', 'site_host', 'domain', 'plan_id', 'plan_label', 'last_error' ) as $key ) {
			$state[ $key ] = $this->plain_text( $input[ $key ] ?? '', 180 );
		}
		$state['project_id']         = $this->plain_text( $input['project_id'] ?? '', 64 );
		$state['connection_id']      = $this->plain_text( $input['connection_id'] ?? '', 64 );
		$state['last_sync_at']       = $this->plain_text( $input['last_sync_at'] ?? '', 40 );
		$state['site_url']           = $this->http_url( $input['site_url'] ?? '' );
		$state['pairing_url']        = $this->http_url( $input['pairing_url'] ?? '' );
		$state['pairing_expires_at'] = $this->plain_text( $input['pairing_expires_at'] ?? '', 40 );

		$code                  = strtoupper( $this->plain_text( $input['pairing_code'] ?? '', 16 ) );
		$code                  = preg_replace( '/[^A-Z0-9-]/', '', $code );
		$state['pairing_code'] = is_string( $code ) ? $code : '';

		$prefix = isset( $input['token_prefix'] ) ? (string) $input['token_prefix'] : '';
		if ( 1 === preg_match( '/^rms_wp_[A-Za-z0-9_-]{6}$/', $prefix ) ) {
			$state['token_prefix'] = $prefix;
		}

		return $state;
	}

	/**
	 * Sanitize a short display string and blank values that contain credentials.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length.
	 * @return string
	 */
	private function plain_text( $value, int $max ): string {
		$text = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		if ( str_contains( $text, 'rms_wp_' ) || str_contains( $text, 'whsec_' ) ) {
			return '';
		}
		if ( strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max );
		}
		return $text;
	}

	/**
	 * Accept only http(s) URLs.
	 *
	 * @param mixed $value Raw URL.
	 * @return string
	 */
	private function http_url( $value ): string {
		$url = esc_url_raw( is_scalar( $value ) ? (string) $value : '' );
		if ( '' === $url ) {
			return '';
		}
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'https', 'http' ), true ) ) {
			return '';
		}
		if ( str_contains( $url, 'rms_wp_' ) || str_contains( $url, 'whsec_' ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Decrypt stored secrets, or return empty fields.
	 *
	 * @return array{token: string, webhook_secret: string, poll_token: string}
	 */
	private function read_secrets(): array {
		if ( null !== $this->secrets ) {
			return $this->secrets;
		}

		$empty = array(
			'token'          => '',
			'webhook_secret' => '',
			'poll_token'     => '',
		);

		$stored = get_option( self::CONNECTION_OPTION, null );
		if ( ! is_array( $stored ) ) {
			$this->secrets = $empty;
			return $this->secrets;
		}

		$json = $this->decrypt( $stored );
		if ( ! is_string( $json ) || '' === $json ) {
			$this->secrets = $empty;
			return $this->secrets;
		}

		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			$this->secrets = $empty;
			return $this->secrets;
		}

		foreach ( array_keys( $empty ) as $key ) {
			if ( isset( $decoded[ $key ] ) && is_string( $decoded[ $key ] ) ) {
				$empty[ $key ] = $decoded[ $key ];
			}
		}
		$this->secrets = $empty;
		return $this->secrets;
	}

	/**
	 * Encrypt and persist secrets.
	 *
	 * @param array{token: string, webhook_secret: string, poll_token: string} $secrets Secret map.
	 * @return void
	 * @throws \RuntimeException When encryption is unavailable or fails.
	 */
	private function write_secrets( array $secrets ): void {
		$json = wp_json_encode( $secrets );
		if ( ! is_string( $json ) ) {
			throw new \RuntimeException( 'This server could not store the RankMySEO connection.' );
		}

		$blob = $this->encrypt( $json );
		update_option( self::CONNECTION_OPTION, $blob, false );
		$this->secrets = $secrets;
	}

	/**
	 * Choose an available cipher.
	 *
	 * @return string
	 * @throws \RuntimeException When neither libsodium nor OpenSSL is available.
	 */
	private function cipher(): string {
		if ( is_string( $this->forced_cipher ) && '' !== $this->forced_cipher ) {
			return $this->forced_cipher;
		}
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			return self::CIPHER_SODIUM;
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			return self::CIPHER_OPENSSL;
		}
		throw new \RuntimeException( 'This server cannot encrypt the RankMySEO connection.' );
	}

	/**
	 * Derive a 32-byte key from WordPress salts.
	 *
	 * @return string
	 */
	private function key(): string {
		$material = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' );
		if ( function_exists( 'sodium_crypto_generichash' ) ) {
			$key = sodium_crypto_generichash( $material, '', 32 );
			if ( is_string( $key ) && 32 === strlen( $key ) ) {
				return $key;
			}
		}
		return hash( 'sha256', $material, true );
	}

	/**
	 * Encrypt plaintext into a versioned option blob.
	 *
	 * @param string $plaintext JSON secret payload.
	 * @return array<string, mixed>
	 * @throws \RuntimeException When encryption fails.
	 */
	private function encrypt( string $plaintext ): array {
		$cipher = $this->cipher();
		$key    = $this->key();

		if ( self::CIPHER_SODIUM === $cipher ) {
			if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
				throw new \RuntimeException( 'This server cannot encrypt the RankMySEO connection.' );
			}
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$sealed = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			return array(
				'v'       => 1,
				'cipher'  => self::CIPHER_SODIUM,
				'nonce'   => $this->encode( $nonce ),
				'tag'     => '',
				'payload' => $this->encode( $sealed ),
			);
		}

		if ( self::CIPHER_OPENSSL !== $cipher || ! function_exists( 'openssl_encrypt' ) ) {
			throw new \RuntimeException( 'This server cannot encrypt the RankMySEO connection.' );
		}

		$iv     = random_bytes( 12 );
		$tag    = '';
		$sealed = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		if ( ! is_string( $sealed ) || strlen( $tag ) < 16 ) {
			throw new \RuntimeException( 'This server could not store the RankMySEO connection.' );
		}

		return array(
			'v'       => 1,
			'cipher'  => self::CIPHER_OPENSSL,
			'nonce'   => $this->encode( $iv ),
			'tag'     => $this->encode( $tag ),
			'payload' => $this->encode( $sealed ),
		);
	}

	/**
	 * Decrypt a stored blob. Returns null when the blob is unreadable.
	 *
	 * @param array<string, mixed> $blob Stored option value.
	 * @return string|null
	 */
	private function decrypt( array $blob ): ?string {
		if ( ( $blob['v'] ?? 0 ) !== 1 || ! isset( $blob['cipher'], $blob['nonce'], $blob['payload'] ) ) {
			return null;
		}

		$nonce   = $this->decode( (string) $blob['nonce'] );
		$payload = $this->decode( (string) $blob['payload'] );
		if ( ! is_string( $nonce ) || ! is_string( $payload ) ) {
			return null;
		}

		try {
			$key = $this->key();
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return null;
		}

		if ( self::CIPHER_SODIUM === $blob['cipher'] && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			try {
				$open = sodium_crypto_secretbox_open( $payload, $nonce, $key );
			} catch ( \SodiumException $exception ) {
				unset( $exception );
				return null;
			}
			return is_string( $open ) ? $open : null;
		}

		if ( self::CIPHER_OPENSSL === $blob['cipher'] && function_exists( 'openssl_decrypt' ) ) {
			$tag = $this->decode( isset( $blob['tag'] ) ? (string) $blob['tag'] : '' );
			if ( ! is_string( $tag ) || '' === $tag ) {
				return null;
			}
			$open = openssl_decrypt( $payload, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag );
			return is_string( $open ) ? $open : null;
		}

		return null;
	}

	/**
	 * Encode raw bytes for storage in a WordPress option.
	 *
	 * @param string $raw Raw bytes.
	 * @return string
	 */
	private function encode( string $raw ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- ciphertext encoding, not obfuscation.
		return base64_encode( $raw );
	}

	/**
	 * Decode stored bytes. Returns null when the value is not valid.
	 *
	 * @param string $encoded Encoded bytes.
	 * @return string|null
	 */
	private function decode( string $encoded ): ?string {
		if ( '' === $encoded ) {
			return null;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- ciphertext encoding, not obfuscation.
		$raw = base64_decode( $encoded, true );
		return is_string( $raw ) ? $raw : null;
	}
}
