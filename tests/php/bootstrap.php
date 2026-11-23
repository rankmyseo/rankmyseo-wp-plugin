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

	/** @var array<string, list<array{priority: int, accepted_args: int}>> */
	public static array $filter_meta = [];

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

	/** @var list<array{hook: string, callback: mixed, priority: int}> */
	public static array $removed_actions = [];

	/** @var array<int, WP_Post> */
	public static array $posts = [];

	/** @var array<int, array<string, mixed>> */
	public static array $post_meta = [];

	/** @var array<int, array<string, string>> */
	public static array $users = [];

	/** @var array<int, string> */
	public static array $permalinks = [];

	/** @var array<int, array<int, WP_Term>> */
	public static array $categories = [];

	/** @var array<int, string> */
	public static array $thumbnails = [];

	/** @var list<WP_Comment> */
	public static array $comments = [];

	/** @var array<int, array<string, mixed>> */
	public static array $comment_meta = [];

	/** @var list<array<string, mixed>> */
	public static array $registered_meta = [];

	/** @var array<string, array<string, mixed>> */
	public static array $cache = [];

	/** @var bool */
	public static bool $is_admin = false;

	/** @var bool */
	public static bool $is_singular = false;

	/** @var bool */
	public static bool $is_feed = false;

	/** @var bool */
	public static bool $is_404 = false;

	/** @var bool */
	public static bool $doing_ajax = false;

	/** @var bool */
	public static bool $doing_cron = false;

	/** @var int */
	public static int $queried_object_id = 0;

	/** @var bool */
	public static bool $nonce_valid = true;

	/** @var list<int> */
	public static array $http_status = [];

	/** @var list<array{location: string, status: int}> */
	public static array $redirects = [];

	/** @var list<array{hook: string, args: array<int, mixed>}> */
	public static array $scheduled = [];

	/** @var array<string, string> */
	public static array $bloginfo = [
		'name'        => 'Test Site',
		'description' => 'A test site',
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
		self::$filter_meta       = [];
		self::$rewrites          = [];
		self::$cron              = [];
		self::$transients        = [];
		self::$query_vars        = [];
		self::$remote_posts      = 0;
		self::$remote_bodies     = [];
		self::$removed_actions   = [];
		self::$posts             = [];
		self::$post_meta         = [];
		self::$users             = [];
		self::$permalinks        = [];
		self::$categories        = [];
		self::$thumbnails        = [];
		self::$comments          = [];
		self::$comment_meta      = [];
		self::$registered_meta   = [];
		self::$is_admin          = false;
		self::$is_singular       = false;
		self::$is_feed           = false;
		self::$is_404            = false;
		self::$doing_ajax        = false;
		self::$doing_cron        = false;
		self::$queried_object_id = 0;
		self::$cache             = [];
		self::$nonce_valid       = true;
		self::$http_status       = [];
		self::$redirects         = [];
		self::$scheduled         = [];
		self::$bloginfo          = [
			'name'        => 'Test Site',
			'description' => 'A test site',
		];
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

		/** @var array<string, mixed> */
		private array $params = [];

		/** @var array<string, string> */
		private array $headers = [];

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

		/**
		 * @param mixed $value
		 */
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		/** @return mixed */
		public function get_param( string $key ) {
			if ( array_key_exists( $key, $this->params ) ) {
				return $this->params[ $key ];
			}
			if ( array_key_exists( $key, $this->json_params ) ) {
				return $this->json_params[ $key ];
			}

			return null;
		}

		public function set_header( string $key, string $value ): void {
			$this->headers[ strtolower( str_replace( '_', '-', $key ) ) ] = $value;
		}

		public function get_header( string $key ): ?string {
			$normalized = strtolower( str_replace( '_', '-', $key ) );
			return $this->headers[ $normalized ] ?? null;
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
	/** @param mixed ...$args */
	function current_user_can( string $capability, ...$args ): bool {
		unset( $args );
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
	/** @param mixed $callback */
	function remove_action( string $hook_name, $callback, int $priority = 10 ): bool {
		RankMySEO_Test_State::$removed_actions[] = [
			'hook'     => $hook_name,
			'callback' => $callback,
			'priority' => $priority,
		];
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Store the callback itself so registration assertions can see it, and
	 * keep priority metadata so apply_filters honors accepted argument counts.
	 *
	 * @param mixed $callback
	 */
	function add_filter( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		RankMySEO_Test_State::$filters[ $hook_name ][]     = $callback;
		RankMySEO_Test_State::$filter_meta[ $hook_name ][] = [
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];
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
		if ( ! empty( RankMySEO_Test_State::$cron[ $hook ] ) ) {
			return RankMySEO_Test_State::$cron[ $hook ][0];
		}
		foreach ( RankMySEO_Test_State::$scheduled as $event ) {
			if ( $event['hook'] === $hook ) {
				return time();
			}
		}
		return false;
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

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return trim( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', strip_tags( $str ) ) );
	}
}

if ( ! function_exists( 'wp_safe_remote_post' ) ) {
	/**
	 * @param mixed                $url
	 * @param array<string, mixed> $args
	 * @return WP_Error
	 */
	function wp_safe_remote_post( $url, array $args = [] ): WP_Error {
		unset( $url );
		++RankMySEO_Test_State::$remote_posts;
		$body                                  = isset( $args['body'] ) ? (string) $args['body'] : '';
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

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param mixed $value
	 * @param mixed ...$args
	 * @return mixed
	 */
	function apply_filters( string $hook_name, $value, ...$args ) {
		if ( empty( RankMySEO_Test_State::$filters[ $hook_name ] ) ) {
			return $value;
		}

		$hooks = [];
		foreach ( RankMySEO_Test_State::$filters[ $hook_name ] as $index => $callback ) {
			$meta    = RankMySEO_Test_State::$filter_meta[ $hook_name ][ $index ] ?? [
				'priority'      => 10,
				'accepted_args' => 1,
			];
			$hooks[] = [
				'callback'      => $callback,
				'priority'      => (int) $meta['priority'],
				'accepted_args' => (int) $meta['accepted_args'],
			];
		}

		usort(
			$hooks,
			static function ( array $left, array $right ): int {
				return $left['priority'] <=> $right['priority'];
			}
		);

		foreach ( $hooks as $hook ) {
			$callback = $hook['callback'];
			$accepted = max( 0, (int) $hook['accepted_args'] );
			$incoming = array_merge( [ $value ], $args );
			if ( is_callable( $callback ) ) {
				$reflection = new ReflectionFunction( $callback instanceof Closure ? $callback : Closure::fromCallable( $callback ) );
				$accepted   = min( $accepted, $reflection->getNumberOfParameters() );
			}
			$call  = array_slice( $incoming, 0, $accepted );
			$value = $callback( ...$call );
		}

		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, callable $callback, int $priority = 10 ): bool {
		RankMySEO_Test_State::$filters[ $hook_name ][ $priority ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/** @param mixed ...$args */
	function do_action( string $hook_name, ...$args ): void {
		if ( empty( RankMySEO_Test_State::$actions[ $hook_name ] ) ) {
			return;
		}

		foreach ( RankMySEO_Test_State::$actions[ $hook_name ] as $callback ) {
			$callback( ...$args );
		}
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
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
			$parsed = parse_url( $url );
			return false === $parsed ? false : $parsed;
		}

		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		$url = trim( $url );
		if ( 1 !== preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		return $url;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/** @param mixed $nonce */
	function wp_verify_nonce( $nonce, string $action = '-1' ) {
		unset( $nonce, $action );
		return RankMySEO_Test_State::$nonce_valid ? 1 : false;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return RankMySEO_Test_State::$is_admin;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax(): bool {
		return RankMySEO_Test_State::$doing_ajax;
	}
}

if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron(): bool {
		return RankMySEO_Test_State::$doing_cron;
	}
}

if ( ! function_exists( 'is_404' ) ) {
	function is_404(): bool {
		return RankMySEO_Test_State::$is_404;
	}
}

if ( ! function_exists( 'status_header' ) ) {
	function status_header( int $code ): void {
		RankMySEO_Test_State::$http_status[] = $code;
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers(): void {
	}
}

if ( ! function_exists( 'wp_redirect' ) ) {
	function wp_redirect( string $location, int $status = 302 ): bool {
		RankMySEO_Test_State::$redirects[] = [
			'location' => $location,
			'status'   => $status,
		];
		return true;
	}
}

if ( ! function_exists( 'wp_cache_get' ) ) {
	/**
	 * @param string $key
	 * @param string $group
	 * @return mixed
	 */
	function wp_cache_get( string $key, string $group = '' ) {
		return RankMySEO_Test_State::$cache[ $group ][ $key ] ?? false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	function wp_cache_set( string $key, $value, string $group = '', int $expire = 0 ): bool {
		unset( $expire );
		RankMySEO_Test_State::$cache[ $group ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( string $key, string $group = '' ): bool {
		unset( RankMySEO_Test_State::$cache[ $group ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( string $hook ): int|false {
		foreach ( RankMySEO_Test_State::$scheduled as $event ) {
			if ( $event['hook'] === $hook ) {
				return time();
			}
		}

		return false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/** @param mixed $args */
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook, $args = [] ): bool {
		RankMySEO_Test_State::$scheduled[] = [
			'hook' => $hook,
			'args' => [
				'timestamp'  => $timestamp,
				'recurrence' => $recurrence,
				'payload'    => $args,
			],
		];
		return true;
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

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 0;
		public string $post_title = '';
		public string $post_content = '';
		public string $post_excerpt = '';
		public string $post_type = 'post';
		public string $post_status = 'publish';
		public string $post_date_gmt = '2026-01-01 00:00:00';
		public string $post_modified_gmt = '2026-01-02 00:00:00';
		public string $post_name = '';
		public int $post_author = 1;
		public int $post_parent = 0;
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public int $term_id = 0;
		public string $name = '';
		public string $slug = '';
		public string $taxonomy = 'category';
	}
}

if ( ! class_exists( 'WP_Comment' ) ) {
	class WP_Comment {
		public int $comment_ID = 0;
		public string $comment_author = '';
		public string $comment_content = '';
		public string $comment_date_gmt = '';
		public int $comment_post_ID = 0;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/** @param mixed $thing */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data
	 * @return string|false
	 */
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
		$json = json_encode( $data, $options, $depth );
		return false === $json ? false : $json;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/** @param mixed $value */
	function wp_unslash( $value ) {
		if ( is_string( $value ) ) {
			return stripslashes( $value );
		}
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return $value;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
		$stripped = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text     = strip_tags( is_string( $stripped ) ? $stripped : $text );
		if ( $remove_breaks ) {
			$collapsed = preg_replace( '/[\r\n\t ]+/', ' ', $text );
			$text      = is_string( $collapsed ) ? $collapsed : $text;
		}
		return trim( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return str_replace( [ '"', "'", '<', '>' ], '', $url );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		$url = trim( $url );
		if ( 1 !== preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		return esc_url( $url );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $string ): string {
		return rtrim( $string, "/\\" ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $string ): string {
		return rtrim( $string, "/\\" );
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	function get_locale(): string {
		return 'en_US';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return RankMySEO_Test_State::$is_admin;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular(): bool {
		return RankMySEO_Test_State::$is_singular;
	}
}

if ( ! function_exists( 'is_search' ) ) {
	function is_search(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_archive' ) ) {
	function is_archive(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_feed' ) ) {
	function is_feed(): bool {
		return RankMySEO_Test_State::$is_feed;
	}
}

if ( ! function_exists( 'is_404' ) ) {
	function is_404(): bool {
		return RankMySEO_Test_State::$is_404;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax(): bool {
		return RankMySEO_Test_State::$doing_ajax;
	}
}

if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron(): bool {
		return RankMySEO_Test_State::$doing_cron;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id(): int {
		return RankMySEO_Test_State::$queried_object_id;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/** @param mixed $post */
	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		$id = (int) $post;
		return RankMySEO_Test_State::$posts[ $id ] ?? null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/** @return mixed */
	function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
		$all = RankMySEO_Test_State::$post_meta[ $post_id ] ?? [];
		if ( '' === $key ) {
			return $all;
		}
		if ( ! array_key_exists( $key, $all ) ) {
			return $single ? '' : [];
		}
		return $single ? $all[ $key ] : [ $all[ $key ] ];
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/** @param mixed $post */
	function get_permalink( $post = 0 ): string {
		$resolved = get_post( $post );
		if ( ! $resolved instanceof WP_Post ) {
			return '';
		}
		if ( isset( RankMySEO_Test_State::$permalinks[ $resolved->ID ] ) ) {
			return RankMySEO_Test_State::$permalinks[ $resolved->ID ];
		}
		$slug = '' !== $resolved->post_name ? $resolved->post_name : (string) $resolved->ID;
		return home_url( $slug . '/' );
	}
}

if ( ! function_exists( 'get_the_author_meta' ) ) {
	function get_the_author_meta( string $field, int $user_id = 0 ): string {
		return (string) ( RankMySEO_Test_State::$users[ $user_id ][ $field ] ?? '' );
	}
}

if ( ! function_exists( 'get_the_category' ) ) {
	/** @return array<int, WP_Term> */
	function get_the_category( int $post_id = 0 ): array {
		return RankMySEO_Test_State::$categories[ $post_id ] ?? [];
	}
}

if ( ! function_exists( 'get_category_link' ) ) {
	/** @param mixed $term */
	function get_category_link( $term ): string {
		$slug = $term instanceof WP_Term ? $term->slug : 'category';
		return home_url( 'category/' . $slug . '/' );
	}
}

if ( ! function_exists( 'get_post_ancestors' ) ) {
	/** @param mixed $post */
	function get_post_ancestors( $post ): array {
		$resolved = get_post( $post );
		if ( ! $resolved instanceof WP_Post ) {
			return [];
		}
		$ancestors = [];
		$parent    = $resolved->post_parent;
		$guard     = 0;
		while ( $parent > 0 && $guard < 20 ) {
			$ancestors[] = $parent;
			$parent_post = get_post( $parent );
			$parent      = $parent_post instanceof WP_Post ? $parent_post->post_parent : 0;
			++$guard;
		}
		return $ancestors;
	}
}

if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
	function get_the_post_thumbnail_url( int $post_id, string $size = 'post-thumbnail' ): string {
		unset( $size );
		return RankMySEO_Test_State::$thumbnails[ $post_id ] ?? '';
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( int $attachment_id, string $size = 'thumbnail' ): string {
		unset( $size );
		return RankMySEO_Test_State::$thumbnails[ $attachment_id ] ?? '';
	}
}

if ( ! function_exists( 'register_meta' ) ) {
	/** @param array<string, mixed> $args */
	function register_meta( string $object_type, string $meta_key, array $args = [] ): bool {
		RankMySEO_Test_State::$registered_meta[] = [
			'object_type' => $object_type,
			'meta_key'    => $meta_key,
			'args'        => $args,
		];
		return true;
	}
}

if ( ! function_exists( 'get_comments' ) ) {
	/** @param array<string, mixed> $args */
	function get_comments( array $args = [] ): array {
		unset( $args );
		return RankMySEO_Test_State::$comments;
	}
}

if ( ! function_exists( 'get_comment_meta' ) ) {
	/** @return mixed */
	function get_comment_meta( int $comment_id, string $key = '', bool $single = false ) {
		$all = RankMySEO_Test_State::$comment_meta[ $comment_id ] ?? [];
		if ( '' === $key ) {
			return $all;
		}
		if ( ! array_key_exists( $key, $all ) ) {
			return $single ? '' : [];
		}
		return $single ? $all[ $key ] : [ $all[ $key ] ];
	}
}

RankMySEO_Test_State::reset();
