<?php
/**
 * PHPUnit bootstrap providing lightweight, deterministic WordPress stubs.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'RANKMYSEO_VERSION' ) ) {
	define( 'RANKMYSEO_VERSION', '0.1.0' );
}

if ( ! defined( 'RANKMYSEO_DB_VERSION' ) ) {
	define( 'RANKMYSEO_DB_VERSION', 1 );
}

if ( ! defined( 'RANKMYSEO_DIR' ) ) {
	define( 'RANKMYSEO_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'RANKMYSEO_URL' ) ) {
	define( 'RANKMYSEO_URL', 'https://example.com/wp-content/plugins/rankmyseo/' );
}

/**
 * In-memory state store for deterministic WordPress function stubs in unit tests.
 */
final class RankMySEO_Test_State {
	/** @var array<string, mixed> */
	public static array $options = [];

	/** @var array<int, array<string, mixed>> */
	public static array $user_meta = [];

	/** @var array<string, WP_Role> */
	public static array $roles = [];

	/** @var array<string, bool> */
	public static array $current_user_caps = [];

	/** @var int */
	public static int $current_user_id = 1;

	/** @var array<int, array{namespace: string, route: string, args: array<mixed>}> */
	public static array $registered_routes = [];

	/** @var array<string, array<int, callable>> */
	public static array $actions = [];

	/** @var array<string, array<int, mixed>> */
	public static array $filters = [];

	/** @var list<array{regex: string, query: string, after: string}> */
	public static array $rewrites = [];

	/** @var array<string, list<int>> */
	public static array $cron = [];

	/** @var array<string, mixed> */
	public static array $transients = [];

	/** @var array<string, mixed> */
	public static array $query_vars = [];

	/** @var int */
	public static int $remote_posts = 0;

	/** @var list<string> */
	public static array $remote_bodies = [];

	/** @var array<string, string> */
	public static array $bloginfo = [
		'name' => 'Test Site',
	];

	public static function reset(): void {
		self::$options           = [ 'blog_public' => '1' ];
		self::$user_meta         = [];
		self::$current_user_id   = 1;
		self::$current_user_caps = [
			'manage_options'       => true,
			'edit_posts'           => true,
			'rankmyseo_manage'     => true,
			'rankmyseo_approve_ai' => true,
		];
		self::$registered_routes = [];
		self::$actions           = [];
		self::$filters           = [];
		self::$rewrites          = [];
		self::$cron              = [];
		self::$transients        = [];
		self::$query_vars        = [];
		self::$remote_posts      = 0;
		self::$remote_bodies     = [];
		self::$bloginfo          = [ 'name' => 'Test Site' ];
		self::$roles             = [
			'administrator' => new WP_Role( 'administrator', [ 'manage_options' => true, 'edit_posts' => true ] ),
			'editor'        => new WP_Role( 'editor', [ 'edit_posts' => true ] ),
			'subscriber'    => new WP_Role( 'subscriber', [ 'read' => true ] ),
		];
	}
}

if ( ! class_exists( 'WP_Role' ) ) {
	class WP_Role {
		public string $name;
		/** @var array<string, bool> */
		public array $capabilities;

		/** @param array<string, bool> $capabilities */
		public function __construct( string $name, array $capabilities = [] ) {
			$this->name         = $name;
			$this->capabilities = $capabilities;
		}

		public function has_cap( string $cap ): bool {
			return ! empty( $this->capabilities[ $cap ] );
		}

		public function add_cap( string $cap, bool $grant = true ): void {
			$this->capabilities[ $cap ] = $grant;
		}

		public function remove_cap( string $cap ): void {
			unset( $this->capabilities[ $cap ] );
		}
	}
}

if ( ! class_exists( 'WP_Roles' ) ) {
	class WP_Roles {
		/** @var array<string, array<string, mixed>> */
		public array $roles = [];

		public function __construct() {
			foreach ( RankMySEO_Test_State::$roles as $slug => $role ) {
				$this->roles[ $slug ] = [
					'name'         => ucfirst( $slug ),
					'capabilities' => $role->capabilities,
				];
			}
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;
		/** @var mixed */
		public $data;

		/** @param mixed $data */
		public function __construct( string $code = '', string $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		/** @return mixed */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		public const READABLE  = 'GET';
		public const CREATABLE = 'POST';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string, mixed> */
		private array $json_params = [];
		/** @var array<string, mixed> */
		private array $body_params = [];

		/** @param array<string, mixed> $json_params */
		public function set_json_params( array $json_params ): void {
			$this->json_params = $json_params;
		}

		/** @return array<string, mixed> */
		public function get_json_params(): array {
			return $this->json_params;
		}

		/** @param array<string, mixed> $body_params */
		public function set_body_params( array $body_params ): void {
			$this->body_params = $body_params;
		}

		/** @return array<string, mixed> */
		public function get_body_params(): array {
			return $this->body_params;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		private $data;
		private int $status;

		/** @param mixed $data */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/** @return mixed */
		public function get_data() {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {
	public string $prefix = 'wp_';
	public string $posts = 'wp_posts';
	public string $postmeta = 'wp_postmeta';
	public string $terms = 'wp_terms';
	public string $term_taxonomy = 'wp_term_taxonomy';
	public string $termmeta = 'wp_termmeta';
	public string $users = 'wp_users';
		/** @var list<string> */
		public array $queries = [];
		/** @var list<string> */
		public array $existing_tables = [];

		public function get_charset_collate(): string {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
		}

		/** @param mixed ...$args */
		public function prepare( string $query, ...$args ): string {
			$index  = 0;
			$result = preg_replace_callback(
				'/%[sd]/',
				static function ( array $match ) use ( $args, &$index ): string {
					$arg = $args[ $index ] ?? '';
					++$index;
					if ( '%d' === $match[0] ) {
						return (string) (int) $arg;
					}
					return "'" . addslashes( (string) $arg ) . "'";
				},
				$query
			);

			return is_string( $result ) ? $result : $query;
		}

		/**
		 * @param string $query
		 * @param string $output
		 * @return list<array<string, mixed>>
		 */
		public function get_results( string $query, string $output = 'ARRAY_A' ): array {
			unset( $output );
			$this->queries[] = $query;
			return [];
		}

		public function get_var( string $query ): ?string {
			$this->queries[] = $query;
			if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $query, $matches ) ) {
				return in_array( $matches[1], $this->existing_tables, true ) ? $matches[1] : null;
			}
			return null;
		}

		public function query( string $query ): int {
			$this->queries[] = $query;
			return 1;
		}

		public function esc_like( string $text ): string {
			return addcslashes( $text, '_%\\' );
		}
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	/**
	 * @param mixed $data
	 * @return mixed
	 */
	function esc_sql( $data ) {
		if ( is_array( $data ) ) {
			return array_map( 'esc_sql', $data );
		}
		return addslashes( (string) $data );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		$stripped = strip_tags( $str );
		return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', $stripped ) );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/** @param mixed $default_value */
	function get_option( string $option, $default_value = false ) {
		return array_key_exists( $option, RankMySEO_Test_State::$options )
			? RankMySEO_Test_State::$options[ $option ]
			: $default_value;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/** @param mixed $value */
	function add_option( string $option, $value = '', string $deprecated = '', $autoload = 'yes' ): bool {
		unset( $deprecated, $autoload );
		if ( array_key_exists( $option, RankMySEO_Test_State::$options ) ) {
			return false;
		}
		RankMySEO_Test_State::$options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/** @param mixed $value */
	function update_option( string $option, $value, $autoload = null ): bool {
		unset( $autoload );
		RankMySEO_Test_State::$options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		unset( RankMySEO_Test_State::$options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	/** @return mixed */
	function get_user_meta( int $user_id, string $key = '', bool $single = false ) {
		$val = RankMySEO_Test_State::$user_meta[ $user_id ][ $key ] ?? ( $single ? '' : [] );
		return $val;
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	/** @param mixed $meta_value */
	function update_user_meta( int $user_id, string $meta_key, $meta_value ): bool {
		RankMySEO_Test_State::$user_meta[ $user_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return RankMySEO_Test_State::$current_user_id;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return ! empty( RankMySEO_Test_State::$current_user_caps[ $capability ] );
	}
}

if ( ! function_exists( 'get_role' ) ) {
	function get_role( string $role ): ?WP_Role {
		return RankMySEO_Test_State::$roles[ $role ] ?? null;
	}
}

if ( ! function_exists( 'wp_roles' ) ) {
	function wp_roles(): WP_Roles {
		return new WP_Roles();
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '' ): string {
		return RankMySEO_Test_State::$bloginfo[ $show ] ?? '';
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://example.com/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		unset( $priority, $accepted_args );
		RankMySEO_Test_State::$actions[ $hook_name ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( string $hook_name, callable $callback, int $priority = 10 ): bool {
		unset( $hook_name, $callback, $priority );
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param mixed $callback
	 */
	function add_filter( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		unset( $priority, $accepted_args );
		RankMySEO_Test_State::$filters[ $hook_name ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( string $regex, string $query, string $after = 'bottom' ): void {
		RankMySEO_Test_State::$rewrites[] = [
			'regex' => $regex,
			'query' => $query,
			'after' => $after,
		];
	}
}

if ( ! function_exists( '__return_false' ) ) {
	function __return_false(): bool {
		return false;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/** @return mixed */
	function get_transient( string $transient ) {
		return RankMySEO_Test_State::$transients[ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/** @param mixed $value */
	function set_transient( string $transient, $value, int $expiration = 0 ): bool {
		unset( $expiration );
		RankMySEO_Test_State::$transients[ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		unset( RankMySEO_Test_State::$transients[ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/** @param array<int, mixed> $args */
	function wp_next_scheduled( string $hook, array $args = [] ) {
		unset( $args );
		if ( empty( RankMySEO_Test_State::$cron[ $hook ] ) ) {
			return false;
		}
		return RankMySEO_Test_State::$cron[ $hook ][0];
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/** @param array<int, mixed> $args */
	function wp_schedule_single_event( int $timestamp, string $hook, array $args = [] ): bool {
		unset( $args );
		RankMySEO_Test_State::$cron[ $hook ][] = $timestamp;
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/** @param array<int, mixed> $args */
	function wp_clear_scheduled_hook( string $hook, array $args = [] ): int {
		unset( $args );
		$count = isset( RankMySEO_Test_State::$cron[ $hook ] ) ? count( RankMySEO_Test_State::$cron[ $hook ] ) : 0;
		unset( RankMySEO_Test_State::$cron[ $hook ] );
		return $count;
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	/** @param mixed $default_value */
	function get_query_var( string $query_var, $default_value = '' ) {
		return RankMySEO_Test_State::$query_vars[ $query_var ] ?? $default_value;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		$url = trim( $url );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		return $url;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url
	 * @param int    $component
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ) {
		if ( -1 === $component ) {
			return parse_url( $url );
		}
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data
	 */
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ): string|false {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return trim( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', strip_tags( $str ) ) );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/** @param mixed $thing */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_safe_remote_post' ) ) {
	/**
	 * @param mixed $url
	 * @param array<string, mixed> $args
	 * @return WP_Error
	 */
	function wp_safe_remote_post( $url, array $args = [] ): WP_Error {
		unset( $url );
		++RankMySEO_Test_State::$remote_posts;
		$body = isset( $args['body'] ) ? (string) $args['body'] : '';
		RankMySEO_Test_State::$remote_bodies[] = $body;
		return new WP_Error( 'http_request_failed', 'External HTTP is stubbed in tests.' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/** @param mixed $response */
	function wp_remote_retrieve_response_code( $response ): int {
		unset( $response );
		return 0;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param mixed $value
	 * @param mixed ...$args
	 * @return mixed
	 */
	function apply_filters( string $hook_name, $value, ...$args ) {
		unset( $hook_name, $args );
		return $value;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	/** @param array<mixed> $args */
	function register_rest_route( string $route_namespace, string $route, array $args = [] ): bool {
		RankMySEO_Test_State::$registered_routes[] = [
			'namespace' => $route_namespace,
			'route'     => $route,
			'args'      => $args,
		];
		return true;
	}
}

RankMySEO_Test_State::reset();
