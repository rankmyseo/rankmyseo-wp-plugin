<?php
/**
 * Server-to-server HTTP client for RankMySEO Cloud.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

/**
 * Calls the site-scoped WordPress API. Sends nothing until a method is invoked,
 * and authenticated calls do nothing while the site is disconnected.
 */
final class CloudClient {
	/**
	 * Production API origin.
	 */
	public const DEFAULT_BASE_URL = 'https://api.rankmyseo.com';

	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * Constructor.
	 *
	 * @param TokenVault $vault Encrypted credential store.
	 */
	public function __construct( TokenVault $vault ) {
		$this->vault = $vault;
	}

	/**
	 * Start a one-click pairing session.
	 *
	 * @param string $site_url       Public site URL.
	 * @param string $site_name      Site name.
	 * @param string $wp_version     WordPress version.
	 * @param string $plugin_version Plugin version.
	 * @return CloudResult
	 */
	public function start_pair( string $site_url, string $site_name, string $wp_version, string $plugin_version ): CloudResult {
		return $this->request(
			'POST',
			'/wp/v1/pair/start',
			array(
				'siteUrl'       => $site_url,
				'siteName'      => $site_name,
				'wpVersion'     => $wp_version,
				'pluginVersion' => $plugin_version,
			),
			false
		);
	}

	/**
	 * Poll a pairing code with the server-side poll secret.
	 *
	 * @param string $code       Pairing code.
	 * @param string $poll_token Poll secret. Never leaves PHP.
	 * @return CloudResult
	 */
	public function poll_pair( string $code, string $poll_token ): CloudResult {
		return $this->request(
			'POST',
			'/wp/v1/pair/poll',
			array(
				'code'      => $code,
				'pollToken' => $poll_token,
			),
			false
		);
	}

	/**
	 * Read the bound site, plan, and connection health.
	 *
	 * @return CloudResult
	 */
	public function get_connection(): CloudResult {
		if ( ! $this->vault->is_connected() ) {
			return CloudResult::local( 'disconnected', __( 'Connect RankMySEO before checking this site.', 'rankmyseo' ) );
		}
		return $this->request( 'GET', '/wp/v1/connection', null, true );
	}

	/**
	 * Ask RankMySEO to revoke this site token. Best effort.
	 *
	 * @return CloudResult
	 */
	public function disconnect(): CloudResult {
		if ( ! $this->vault->has_token() ) {
			return CloudResult::local( 'disconnected', __( 'This site is already disconnected.', 'rankmyseo' ) );
		}
		return $this->request( 'POST', '/wp/v1/disconnect', array(), true );
	}

	/**
	 * Rotate the site token. The previous token stops working.
	 *
	 * @return CloudResult
	 */
	public function rotate_token(): CloudResult {
		if ( ! $this->vault->has_token() ) {
			return CloudResult::local( 'disconnected', __( 'Connect RankMySEO before rotating the token.', 'rankmyseo' ) );
		}
		return $this->request( 'POST', '/wp/v1/rotate-token', array(), true );
	}

	/**
	 * Send a batch of posts for this site.
	 *
	 * @param list<array<string, mixed>> $posts Inventory rows.
	 * @return CloudResult
	 */
	public function sync_inventory( array $posts ): CloudResult {
		if ( ! $this->vault->is_connected() ) {
			return CloudResult::local( 'disconnected', __( 'Connect RankMySEO before syncing this site.', 'rankmyseo' ) );
		}
		return $this->request( 'POST', '/wp/v1/sync/inventory', array( 'posts' => $posts ), true );
	}

	/**
	 * Send a batch of publish, update, delete, or slug events.
	 *
	 * @param list<array<string, mixed>> $events Event rows.
	 * @return CloudResult
	 */
	public function sync_events( array $events ): CloudResult {
		if ( ! $this->vault->is_connected() ) {
			return CloudResult::local( 'disconnected', __( 'Connect RankMySEO before syncing this site.', 'rankmyseo' ) );
		}
		return $this->request( 'POST', '/wp/v1/sync/events', array( 'events' => $events ), true );
	}

	/**
	 * Read cached-on-our-side keyword, issue, and page-update insights.
	 *
	 * @param string|null $url        Optional page URL on this site.
	 * @param int|null    $wp_post_id Optional WordPress post ID.
	 * @return CloudResult
	 */
	public function get_insights( ?string $url = null, ?int $wp_post_id = null ): CloudResult {
		if ( ! $this->vault->is_connected() ) {
			return CloudResult::local( 'disconnected', __( 'Connect RankMySEO to see rankings for this site.', 'rankmyseo' ) );
		}
		$query = array();
		if ( is_string( $url ) && '' !== $url ) {
			$query['url'] = $url;
		}
		if ( is_int( $wp_post_id ) && $wp_post_id > 0 ) {
			$query['wpPostId'] = (string) $wp_post_id;
		}
		return $this->request( 'GET', '/wp/v1/insights', null, true, $query );
	}

	/**
	 * Read the site auto-apply policy.
	 *
	 * @return CloudResult
	 */
	public function get_policy(): CloudResult {
		if ( ! $this->vault->is_connected() ) {
			return CloudResult::local( 'disconnected', __( 'Connect RankMySEO before reading site policy.', 'rankmyseo' ) );
		}
		return $this->request( 'GET', '/wp/v1/policy', null, true );
	}

	/**
	 * Save the site auto-apply policy.
	 *
	 * @param array<string, mixed> $policy Policy body.
	 * @return CloudResult
	 */
	public function put_policy( array $policy ): CloudResult {
		if ( ! $this->vault->is_connected() ) {
			return CloudResult::local( 'disconnected', __( 'Connect RankMySEO before saving site policy.', 'rankmyseo' ) );
		}
		return $this->request( 'PUT', '/wp/v1/policy', $policy, true );
	}

	/**
	 * Resolve the API origin. The constant wins as the default; the filter can override it.
	 *
	 * @return string
	 */
	public function base_url(): string {
		$base = self::DEFAULT_BASE_URL;
		if ( defined( 'RANKMYSEO_API_BASE_URL' ) ) {
			$constant = constant( 'RANKMYSEO_API_BASE_URL' );
			if ( is_string( $constant ) && '' !== $constant ) {
				$base = $constant;
			}
		}

		$filtered = apply_filters( 'rankmyseo_cloud_api_base_url', $base );
		if ( ! is_string( $filtered ) || '' === $filtered ) {
			$filtered = $base;
		}
		$filtered = rtrim( $filtered, '/' );
		$scheme   = wp_parse_url( $filtered, PHP_URL_SCHEME );
		$host     = wp_parse_url( $filtered, PHP_URL_HOST );
		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'https', 'http' ), true ) ) {
			return self::DEFAULT_BASE_URL;
		}
		if ( ! is_string( $host ) || '' === $host ) {
			return self::DEFAULT_BASE_URL;
		}
		return $filtered;
	}

	/**
	 * Perform one HTTP request. Authenticated calls require a stored token.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $path   Path beginning with /wp/v1/.
	 * @param array<string, mixed>|null $body   JSON body, or null.
	 * @param bool                      $auth   Whether to send the bearer token.
	 * @param array<string, string>     $query  Query parameters.
	 * @return CloudResult
	 */
	private function request( string $method, string $path, ?array $body, bool $auth, array $query = array() ): CloudResult {
		$token = '';
		if ( $auth ) {
			$token = $this->vault->get_secret( 'token' );
			if ( '' === $token ) {
				return CloudResult::local( 'disconnected', __( 'Connect RankMySEO before syncing this site.', 'rankmyseo' ) );
			}
		}

		$url = $this->base_url() . $path;
		if ( array() !== $query ) {
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => 'RankMySEO-WordPress/' . ( defined( 'RANKMYSEO_VERSION' ) ? RANKMYSEO_VERSION : '0.1.0' ),
		);
		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
		}
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$args = array(
			'method'      => $method,
			'timeout'     => 20,
			'redirection' => 0,
			'headers'     => $headers,
			'cookies'     => array(),
			'sslverify'   => true,
		);
		if ( null !== $body ) {
			$encoded      = wp_json_encode( $body );
			$args['body'] = is_string( $encoded ) ? $encoded : '{}';
		}

		$response = wp_remote_request( $url, $args );
		$result   = $this->parse_response( $response );

		if ( $auth && $this->should_mark_reconnect( $result->status, $result->error ) ) {
			$message = '' !== $result->message
				? $result->message
				: __( 'RankMySEO needs you to connect this site again.', 'rankmyseo' );
			$this->vault->mark_reconnect( $message );
			if ( '' === $result->message ) {
				$result->message = $message;
			}
		}

		return $result;
	}

	/**
	 * Map a WordPress HTTP response into a CloudResult without throwing.
	 *
	 * @param mixed $response wp_remote_request return value.
	 * @return CloudResult
	 */
	private function parse_response( $response ): CloudResult {
		$result            = new CloudResult();
		$result->attempted = true;

		if ( is_wp_error( $response ) ) {
			$raw             = $response->get_error_message();
			$timeout         = is_string( $raw ) && false !== stripos( $raw, 'timed out' );
			$result->error   = $timeout ? 'timeout' : 'transport';
			$result->message = $timeout
				? __( 'RankMySEO took too long to answer. Your site is unchanged.', 'rankmyseo' )
				: __( 'RankMySEO could not be reached. Your site is unchanged.', 'rankmyseo' );
			$result->status  = 0;
			return $result;
		}

		if ( ! is_array( $response ) ) {
			$result->error   = 'transport';
			$result->message = __( 'RankMySEO could not be reached. Your site is unchanged.', 'rankmyseo' );
			return $result;
		}

		$status         = (int) wp_remote_retrieve_response_code( $response );
		$result->status = $status;
		$decoded        = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body           = is_array( $decoded ) ? $decoded : array();
		$error          = isset( $body['error'] ) && is_string( $body['error'] ) ? sanitize_key( $body['error'] ) : '';
		$message        = isset( $body['message'] ) && is_string( $body['message'] ) ? $this->clean_message( $body['message'] ) : '';

		if ( $status >= 200 && $status < 300 ) {
			$result->ok   = true;
			$result->data = $body;
			return $result;
		}

		$result->error = '' !== $error ? $error : 'http_' . $status;
		if ( '' === $message ) {
			$message = $status >= 500
				? __( 'RankMySEO had a problem answering. Nothing on your site was changed.', 'rankmyseo' )
				: __( 'RankMySEO could not complete that request.', 'rankmyseo' );
		}
		$result->message = $message;
		$result->data    = $body;

		if ( 429 === $status ) {
			$retry               = $this->retry_after_seconds( $response );
			$result->retry_after = $retry > 0 ? $retry : 60;
		}

		return $result;
	}

	/**
	 * Authenticated 401 and 403 responses mean this token should not keep calling.
	 * A plan limit is not a broken connection.
	 *
	 * @param int    $status HTTP status.
	 * @param string $error  API error code.
	 * @return bool
	 */
	private function should_mark_reconnect( int $status, string $error ): bool {
		if ( 401 === $status ) {
			return true;
		}
		if ( 403 === $status && 'plan_limit' !== $error ) {
			return true;
		}
		return false;
	}

	/**
	 * Read Retry-After as whole seconds.
	 *
	 * @param array<string, mixed> $response HTTP response array.
	 * @return int
	 */
	private function retry_after_seconds( array $response ): int {
		$raw = wp_remote_retrieve_header( $response, 'retry-after' );
		if ( is_numeric( $raw ) ) {
			return max( 0, (int) $raw );
		}
		return 0;
	}

	/**
	 * Strip markup from an API message before it reaches the admin UI.
	 *
	 * @param string $message Raw message.
	 * @return string
	 */
	private function clean_message( string $message ): string {
		$clean = sanitize_text_field( wp_strip_all_tags( $message ) );
		$clean = (string) preg_replace( '/rms_wp_[A-Za-z0-9_\-]{7,}/', '', $clean );
		$clean = (string) preg_replace( '/whsec_[A-Za-z0-9_\-]+/', '', $clean );
		$clean = trim( (string) preg_replace( '/\s+/', ' ', $clean ) );
		if ( strlen( $clean ) > 300 ) {
			$clean = substr( $clean, 0, 300 );
		}
		return $clean;
	}
}
