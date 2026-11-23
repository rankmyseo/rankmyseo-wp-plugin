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

	/** @var array<string, string> */
	public static array $bloginfo = [
		'name' => 'Test Site',
	];

	/** @var array<string, array<int, callable>> */
	public static array $filters = [];

	/** @var list<array{url: string, args: array<string, mixed>}> */
	public static array $http_log = [];

	/** @var array<string, string> */
	public static array $salts = [];

	/** @var array<string, mixed> */
	public static array $transients = [];

	/** @var array<string, int> */
	public static array $cron = [];

	/** @var list<object> */
	public static array $posts = [];

	/** @var array<int, array<string, mixed>> */
	public static array $post_meta = [];

	/** @var int */
	public static int $next_post_id = 100;

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
		self::$http_log          = [];
		self::$salts             = [
			'auth'        => 'test-auth-salt',
			'secure_auth' => 'test-secure-auth-salt',
		];
		self::$transients        = [];
		self::$cron              = [];
		self::$posts             = [];
		self::$post_meta         = [];
		self::$next_post_id      = 100;
		self::$bloginfo          = [ 'name' => 'Test Site' ];
		$GLOBALS['wp_version']   = '6.4.3';
		$GLOBALS['wpdb']         = new wpdb();
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

		private string $raw_body = '';

		/** @var array<string, string> */
		private array $headers = [];

		public function set_body( string $body ): void {
			$this->raw_body = $body;
		}

		public function get_body(): string {
			return $this->raw_body;
		}

		public function set_header( string $name, string $value ): void {
			$this->headers[ strtolower( $name ) ] = $value;
		}

		/** @return string */
		public function get_header( string $name ) {
			return $this->headers[ strtolower( $name ) ] ?? '';
		}

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

		/** @param mixed $value */
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
			return $this->body_params[ $key ] ?? null;
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
		public string $postmeta = 'wp_postmeta';
		/** @var list<string> */
		public array $queries = [];
		/** @var list<string> */
		public array $existing_tables = [];

		public function get_charset_collate(): string {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
		}

		public int $insert_id = 0;

		/** @var list<array<string, mixed>> */
		public array $sync_rows = [];

		/** @var array<string, list<array<string, mixed>>> */
		public array $table_rows = [];

		/** @param mixed ...$args */
		public function prepare( string $query, ...$args ): string {
			$index  = 0;
			$parsed = preg_replace_callback(
				'/%[sd]/',
				static function ( array $match ) use ( &$index, $args ): string {
					$value = $args[ $index ] ?? '';
					++$index;
					if ( '%d' === $match[0] ) {
						return (string) (int) $value;
					}
					return "'" . addslashes( (string) $value ) . "'";
				},
				$query
			);
			return is_string( $parsed ) ? $parsed : $query;
		}

		public function get_var( string $query ): ?string {
			$this->queries[] = $query;
			if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $query, $matches ) ) {
				return in_array( $matches[1], $this->existing_tables, true ) ? $matches[1] : null;
			}
			if ( preg_match( '/count\s*\(/i', $query ) ) {
				return (string) count( $this->match_table_rows( $query ) );
			}
			return null;
		}

		/**
		 * @param mixed $output
		 * @return list<array<string, mixed>>
		 */
		public function get_results( string $query, $output = null ): array {
			unset( $output );
			$this->queries[] = $query;
			if ( ! str_contains( $query, 'rankmyseo_sync_queue' ) ) {
				return $this->match_table_rows( $query );
			}
			$rows = $this->sync_rows;
			if ( preg_match_all( "/(\\w+)\\s*=\\s*'((?:\\\\'|[^'])*)'/", $query, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$column = $match[1];
					$value  = stripslashes( $match[2] );
					$rows   = array_values(
						array_filter(
							$rows,
							static function ( array $row ) use ( $column, $value ): bool {
								return (string) ( $row[ $column ] ?? '' ) === $value;
							}
						)
					);
				}
			}
			if ( preg_match( '/\\bid\\s*=\\s*(\\d+)/', $query, $match ) ) {
				$id   = (int) $match[1];
				$rows = array_values(
					array_filter(
						$rows,
						static function ( array $row ) use ( $id ): bool {
							return (int) ( $row['id'] ?? 0 ) === $id;
						}
					)
				);
			}
			if ( preg_match( "/scheduled_at\\s*<=\\s*'([^']+)'/", $query, $match ) ) {
				$cutoff = $match[1];
				$rows   = array_values(
					array_filter(
						$rows,
						static function ( array $row ) use ( $cutoff ): bool {
							return strcmp( (string) ( $row['scheduled_at'] ?? '' ), $cutoff ) <= 0;
						}
					)
				);
			}
			usort(
				$rows,
				static function ( array $left, array $right ): int {
					return (int) ( $left['id'] ?? 0 ) <=> (int) ( $right['id'] ?? 0 );
				}
			);
			if ( preg_match( '/LIMIT\\s+(\\d+)/', $query, $match ) ) {
				$rows = array_slice( $rows, 0, (int) $match[1] );
			}
			return $rows;
		}

		/**
		 * @param array<string, mixed> $data
		 * @param mixed                $format
		 * @return int|false
		 */
		public function insert( string $table, array $data, $format = null ) {
			unset( $format );
			if ( ! str_contains( $table, 'rankmyseo_sync_queue' ) ) {
				if ( ! str_contains( $table, 'rankmyseo_' ) ) {
					return false;
				}
				++$this->insert_id;
				$row       = $data;
				$row['id'] = $this->insert_id;
				if ( ! isset( $this->table_rows[ $table ] ) ) {
					$this->table_rows[ $table ] = [];
				}
				$this->table_rows[ $table ][] = $row;
				return 1;
			}
			++$this->insert_id;
			$row       = $data;
			$row['id'] = $this->insert_id;
			if ( ! isset( $row['locked_at'] ) ) {
				$row['locked_at'] = '';
			}
			if ( ! isset( $row['last_error'] ) ) {
				$row['last_error'] = '';
			}
			$this->sync_rows[] = $row;
			return 1;
		}

		/**
		 * @param array<string, mixed> $data
		 * @param array<string, mixed> $where
		 * @param mixed                $format
		 * @param mixed                $where_format
		 */
		public function update( string $table, array $data, array $where, $format = null, $where_format = null ): int {
			unset( $format, $where_format );
			if ( ! str_contains( $table, 'rankmyseo_sync_queue' ) ) {
				if ( ! isset( $this->table_rows[ $table ] ) ) {
					return 0;
				}
				$count = 0;
				foreach ( $this->table_rows[ $table ] as $index => $row ) {
					if ( ! $this->row_matches_where( $row, $where ) ) {
						continue;
					}
					$this->table_rows[ $table ][ $index ] = array_merge( $row, $data );
					++$count;
				}
				return $count;
			}
			$id    = isset( $where['id'] ) ? (int) $where['id'] : 0;
			$count = 0;
			foreach ( $this->sync_rows as $index => $row ) {
				if ( (int) ( $row['id'] ?? 0 ) !== $id ) {
					continue;
				}
				$this->sync_rows[ $index ] = array_merge( $row, $data );
				++$count;
			}
			return $count;
		}

		public function query( string $query ): int {
			$this->queries[] = $query;
			return 1;
		}

		/**
		 * @param array<string, mixed> $where
		 * @param mixed                $where_format
		 */
		public function delete( string $table, array $where, $where_format = null ): int {
			unset( $where_format );
			if ( ! isset( $this->table_rows[ $table ] ) ) {
				return 0;
			}
			$before                     = count( $this->table_rows[ $table ] );
			$this->table_rows[ $table ] = array_values(
				array_filter(
					$this->table_rows[ $table ],
					function ( array $row ) use ( $where ): bool {
						return ! $this->row_matches_where( $row, $where );
					}
				)
			);
			return $before - count( $this->table_rows[ $table ] );
		}

		/**
		 * @param mixed $output
		 * @return array<string, mixed>|null
		 */
		public function get_row( string $query, $output = null ) {
			$rows = $this->get_results( $query, $output );
			return $rows[0] ?? null;
		}

		/**
		 * @param array<string, mixed> $row
		 * @param array<string, mixed> $where
		 */
		private function row_matches_where( array $row, array $where ): bool {
			foreach ( $where as $column => $value ) {
				if ( (string) ( $row[ $column ] ?? '' ) !== (string) $value ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * @return list<array<string, mixed>>
		 */
		private function match_table_rows( string $query ): array {
			if ( ! preg_match( '/\bFROM\s+`?([A-Za-z0-9_]+)`?/i', $query, $found ) ) {
				return [];
			}
			$table = $found[1];
			$rows  = $this->table_rows[ $table ] ?? [];
			if ( preg_match( '/\bWHERE\s+(.+?)(\s+ORDER\s+BY|\s+LIMIT\s+|$)/is', $query, $where_match ) ) {
				$parts = preg_split( '/\s+AND\s+/i', trim( $where_match[1] ) );
				$parts = is_array( $parts ) ? $parts : [];
				foreach ( $parts as $part ) {
					$part = trim( $part );
					if ( preg_match( "/^([A-Za-z0-9_]+)\\s*=\\s*'((?:\\\\'|[^'])*)'$/", $part, $eq ) ) {
						$column = $eq[1];
						$value  = stripslashes( $eq[2] );
						$rows   = array_values(
							array_filter(
								$rows,
								static function ( array $row ) use ( $column, $value ): bool {
									return (string) ( $row[ $column ] ?? '' ) === $value;
								}
							)
						);
						continue;
					}
					if ( preg_match( '/^([A-Za-z0-9_]+)\s*=\s*(-?\d+)$/', $part, $eq ) ) {
						$column = $eq[1];
						$value  = $eq[2];
						$rows   = array_values(
							array_filter(
								$rows,
								static function ( array $row ) use ( $column, $value ): bool {
									return (string) ( $row[ $column ] ?? '' ) === $value;
								}
							)
						);
						continue;
					}
					if ( preg_match( "/^([A-Za-z0-9_]+)\\s*>=\\s*'([^']*)'$/", $part, $eq ) ) {
						$column = $eq[1];
						$value  = $eq[2];
						$rows   = array_values(
							array_filter(
								$rows,
								static function ( array $row ) use ( $column, $value ): bool {
									return strcmp( (string) ( $row[ $column ] ?? '' ), $value ) >= 0;
								}
							)
						);
					}
				}
			}
			if ( preg_match( '/\bLIMIT\s+(\d+)/i', $query, $limit ) ) {
				$rows = array_slice( $rows, 0, (int) $limit[1] );
			}
			return $rows;
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
	function current_user_can( string $capability, mixed ...$args ): bool {
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

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		unset( $priority, $accepted_args );
		RankMySEO_Test_State::$filters[ $hook_name ][] = $callback;
		return true;
	}
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
		foreach ( RankMySEO_Test_State::$filters[ $hook_name ] as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/** @param mixed ...$args */
	function do_action( string $hook_name, ...$args ): void {
		foreach ( RankMySEO_Test_State::$actions[ $hook_name ] ?? [] as $callback ) {
			$callback( ...$args );
		}
	}
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme = 'auth' ): string {
		return RankMySEO_Test_State::$salts[ $scheme ] ?? ( 'salt-' . $scheme );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data
	 * @return string|false
	 */
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
		$json = json_encode( $data, $options, $depth );
		return is_string( $json ) ? $json : false;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/** @param mixed $url */
	function esc_url_raw( $url ): string {
		$url = trim( (string) $url );
		if ( 1 !== preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		return $url;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/** @param mixed $text */
	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param mixed $url
	 * @return mixed
	 */
	function wp_parse_url( $url, int $component = -1 ) {
		$parts = parse_url( (string) $url );
		if ( false === $parts ) {
			return false;
		}
		if ( -1 === $component ) {
			return $parts;
		}
		$map = [
			PHP_URL_SCHEME => 'scheme',
			PHP_URL_HOST   => 'host',
			PHP_URL_PATH   => 'path',
			PHP_URL_QUERY  => 'query',
		];
		$key = $map[ $component ] ?? null;
		if ( null === $key ) {
			return null;
		}
		return $parts[ $key ] ?? null;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/** @param mixed $thing */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	/**
	 * @param mixed                $url
	 * @param array<string, mixed> $args
	 * @return mixed
	 */
	function wp_remote_request( $url, array $args = [] ) {
		RankMySEO_Test_State::$http_log[] = [
			'url'  => (string) $url,
			'args' => $args,
		];
		$pre = apply_filters( 'pre_http_request', false, $args, (string) $url );
		if ( false !== $pre ) {
			return $pre;
		}
		return new WP_Error( 'http_request_failed', 'A test blocked this HTTP request.' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/** @param mixed $response */
	function wp_remote_retrieve_response_code( $response ): int {
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}
		return 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/** @param mixed $response */
	function wp_remote_retrieve_body( $response ): string {
		return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	/**
	 * @param mixed  $response
	 * @param string $header
	 * @return string
	 */
	function wp_remote_retrieve_header( $response, string $header ): string {
		if ( ! is_array( $response ) || ! isset( $response['headers'] ) || ! is_array( $response['headers'] ) ) {
			return '';
		}
		foreach ( $response['headers'] as $name => $value ) {
			if ( strtolower( (string) $name ) === strtolower( $header ) ) {
				return is_array( $value ) ? (string) reset( $value ) : (string) $value;
			}
		}
		return '';
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
	/** @return int|false */
	function wp_next_scheduled( string $hook, array $args = [] ) {
		unset( $args );
		return RankMySEO_Test_State::$cron[ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = [] ): bool {
		unset( $recurrence, $args );
		RankMySEO_Test_State::$cron[ $hook ] = $timestamp;
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook, array $args = [] ): int {
		unset( $args );
		$had = isset( RankMySEO_Test_State::$cron[ $hook ] ) ? 1 : 0;
		unset( RankMySEO_Test_State::$cron[ $hook ] );
		return $had;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/** @param mixed $post */
	function get_permalink( $post ): string {
		$slug = is_object( $post ) ? (string) ( $post->post_name ?? 'post' ) : 'post';
		return 'https://example.com/' . $slug . '/';
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/** @param mixed $post */
	function get_post( $post ) {
		if ( is_object( $post ) ) {
			return $post;
		}
		foreach ( RankMySEO_Test_State::$posts as $item ) {
			if ( (int) $item->ID === (int) $post ) {
				return $item;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param mixed $key
	 * @return mixed
	 */
	function get_post_meta( int $post_id, $key = '', bool $single = false ) {
		$meta = RankMySEO_Test_State::$post_meta[ $post_id ] ?? [];
		if ( '' === $key ) {
			return $meta;
		}
		if ( ! is_string( $key ) || ! array_key_exists( $key, $meta ) ) {
			return $single ? '' : [];
		}
		return $meta[ $key ];
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/** @param array<string, mixed> $args */
	function get_posts( array $args = [] ): array {
		$limit  = (int) ( $args['numberposts'] ?? 5 );
		$status = (string) ( $args['post_status'] ?? 'publish' );
		$found  = [];
		foreach ( RankMySEO_Test_State::$posts as $post ) {
			if ( (string) ( $post->post_status ?? '' ) !== $status ) {
				continue;
			}
			$found[] = $post;
			if ( count( $found ) >= $limit ) {
				break;
			}
		}
		return $found;
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	/** @param mixed $post */
	function wp_is_post_revision( $post ) {
		return is_object( $post ) && isset( $post->post_type ) && 'revision' === $post->post_type;
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	/** @param mixed $post */
	function wp_is_post_autosave( $post ): bool {
		return is_object( $post ) && isset( $post->post_name ) && str_contains( (string) $post->post_name, 'autosave' );
	}
}

if ( ! function_exists( 'is_post_type_viewable' ) ) {
	/** @param mixed $post_type */
	function is_post_type_viewable( $post_type ): bool {
		$type = is_object( $post_type ) ? (string) ( $post_type->name ?? '' ) : (string) $post_type;
		return in_array( $type, [ 'post', 'page' ], true );
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, "/\\" );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * @return string|int
	 */
	function current_time( string $type, bool $gmt = false ) {
		unset( $gmt );
		if ( 'timestamp' === $type || 'U' === $type ) {
			return time();
		}
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists( string $meta_type, int $object_id, string $meta_key ): bool {
		if ( 'post' !== $meta_type ) {
			return false;
		}
		return array_key_exists( $meta_key, RankMySEO_Test_State::$post_meta[ $object_id ] ?? [] );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/** @param mixed $meta_value */
	function update_post_meta( int $post_id, string $meta_key, $meta_value ): int {
		RankMySEO_Test_State::$post_meta[ $post_id ][ $meta_key ] = $meta_value;
		return 1;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $meta_key ): bool {
		unset( RankMySEO_Test_State::$post_meta[ $post_id ][ $meta_key ] );
		return true;
	}
}

if ( ! function_exists( 'strip_shortcodes' ) ) {
	function strip_shortcodes( string $content ): string {
		return (string) preg_replace( '/\[[^\]]+\]/', '', $content );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $data ): string {
		$clean = (string) preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $data );
		$clean = (string) preg_replace( '/<iframe\b[^>]*>.*?<\/iframe>/is', '', $clean );
		return (string) preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean );
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * @param array<string, mixed> $postarr
	 * @return int
	 */
	function wp_insert_post( array $postarr, bool $wp_error = false ) {
		unset( $wp_error );
		$id = ++RankMySEO_Test_State::$next_post_id;
		RankMySEO_Test_State::$posts[] = (object) array(
			'ID'           => $id,
			'post_type'    => (string) ( $postarr['post_type'] ?? 'post' ),
			'post_status'  => (string) ( $postarr['post_status'] ?? 'draft' ),
			'post_title'   => (string) ( $postarr['post_title'] ?? '' ),
			'post_content' => (string) ( $postarr['post_content'] ?? '' ),
			'post_name'    => (string) ( $postarr['post_name'] ?? 'post' ),
			'post_parent'  => (int) ( $postarr['post_parent'] ?? 0 ),
		);
		return $id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/**
	 * @param array<string, mixed> $postarr
	 * @return int
	 */
	function wp_update_post( array $postarr, bool $wp_error = false ) {
		unset( $wp_error );
		$id = (int) ( $postarr['ID'] ?? 0 );
		foreach ( RankMySEO_Test_State::$posts as $index => $post ) {
			if ( (int) $post->ID !== $id ) {
				continue;
			}
			foreach ( $postarr as $key => $value ) {
				if ( 'ID' === $key ) {
					continue;
				}
				$post->{$key} = $value;
			}
			RankMySEO_Test_State::$posts[ $index ] = $post;
			return $id;
		}
		return 0;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( int $post_id, bool $force_delete = false ): bool {
		unset( $force_delete );
		RankMySEO_Test_State::$posts = array_values(
			array_filter(
				RankMySEO_Test_State::$posts,
				static function ( object $post ) use ( $post_id ): bool {
					return (int) $post->ID !== $post_id;
				}
			)
		);
		unset( RankMySEO_Test_State::$post_meta[ $post_id ] );
		return true;
	}
}

if ( ! function_exists( 'wp_save_post_revision' ) ) {
	function wp_save_post_revision( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! is_object( $post ) ) {
			return 0;
		}
		return wp_insert_post(
			array(
				'post_type'    => 'revision',
				'post_status'  => 'inherit',
				'post_title'   => (string) ( $post->post_title ?? '' ),
				'post_content' => (string) ( $post->post_content ?? '' ),
				'post_parent'  => $post_id,
				'post_name'    => (string) $post_id . '-revision',
			)
		);
	}
}

if ( ! function_exists( 'rankmyseo_test_http_response' ) ) {
	/**
	 * @param array<string, mixed> $body
	 * @param array<string, string> $headers
	 * @return array<string, mixed>
	 */
	function rankmyseo_test_http_response( int $status, array $body, array $headers = [] ): array {
		return [
			'headers'  => $headers,
			'body'     => (string) json_encode( $body ),
			'response' => [
				'code'    => $status,
				'message' => 'OK',
			],
			'cookies'  => [],
		];
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
