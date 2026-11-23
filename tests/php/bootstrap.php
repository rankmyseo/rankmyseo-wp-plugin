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

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'RANKMYSEO_VERSION' ) ) {
	define( 'RANKMYSEO_VERSION', '1.0.0' );
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

	/** @var list<array{url: string, args: array<string, mixed>}> */
	public static array $http_log = [];

	/** @var array<string, string> */
	public static array $salts = [];

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

	/** @var array<int, array<string, mixed>> */
	public static array $term_meta = [];

	/** @var array<int, WP_Term> */
	public static array $terms = [];

	/** @var array<string, string> */
	public static array $nonces = [];

	/** @var array<string, string> */
	public static array $bloginfo = [
		'name'        => 'Test Site',
		'description' => 'A test site',
	];

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
		self::$filter_meta       = [];
		self::$http_log          = [];
		self::$salts             = [
			'auth'        => 'test-auth-salt',
			'secure_auth' => 'test-secure-auth-salt',
		];
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
		self::$term_meta         = [];
		self::$terms             = [];
		self::$nonces            = [];
		self::$bloginfo          = [
			'name'        => 'Test Site',
			'description' => 'A test site',
		];
		self::$next_post_id      = 100;
		self::$roles             = [
			'administrator' => new WP_Role( 'administrator', [ 'manage_options' => true, 'edit_posts' => true ] ),
			'editor'        => new WP_Role( 'editor', [ 'edit_posts' => true ] ),
			'subscriber'    => new WP_Role( 'subscriber', [ 'read' => true ] ),
		];
		$GLOBALS['wpdb']         = new wpdb();
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

		private string $raw_body = '';

		public function set_body( string $body ): void {
			$this->raw_body = $body;
		}

		public function get_body(): string {
			return $this->raw_body;
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

		public int $insert_id = 0;

		/** @var list<array<string, mixed>> */
		public array $sync_rows = [];

		/** @var array<string, list<array<string, mixed>>> */
		public array $table_rows = [];

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
		 * @param string $output Requested output format.
		 * @return mixed
		 */
		public function get_row( string $query, string $output = 'OBJECT' ) {
			$rows = $this->get_results( $query, is_string( $output ) ? $output : 'ARRAY_A' );
			return $rows[0] ?? null;
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

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return esc_attr( $text );
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
		if ( ! empty( RankMySEO_Test_State::$current_user_caps[ $capability ] ) ) {
			return true;
		}

		if ( in_array( $capability, [ 'edit_post', 'edit_term' ], true ) && ! empty( RankMySEO_Test_State::$current_user_caps['edit_posts'] ) ) {
			return true;
		}

		return false;
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
		$count = isset( RankMySEO_Test_State::$cron[ $hook ] ) ? count( (array) RankMySEO_Test_State::$cron[ $hook ] ) : 0;
		unset( RankMySEO_Test_State::$cron[ $hook ] );
		$before = count( RankMySEO_Test_State::$scheduled );
		RankMySEO_Test_State::$scheduled = array_values(
			array_filter(
				RankMySEO_Test_State::$scheduled,
				static function ( array $event ) use ( $hook ): bool {
					return $event['hook'] !== $hook;
				}
			)
		);
		return $count + ( $before - count( RankMySEO_Test_State::$scheduled ) );
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
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}
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
		if ( ! RankMySEO_Test_State::$nonce_valid ) {
			return false;
		}
		if ( isset( RankMySEO_Test_State::$nonces[ $action ] ) ) {
			return hash_equals( RankMySEO_Test_State::$nonces[ $action ], (string) $nonce );
		}
		return 1;
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
		public string $description = '';
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
		if ( is_object( $post ) ) {
			return $post;
		}
		$id = (int) $post;
		if ( isset( RankMySEO_Test_State::$posts[ $id ] ) && is_object( RankMySEO_Test_State::$posts[ $id ] ) ) {
			return RankMySEO_Test_State::$posts[ $id ];
		}
		foreach ( RankMySEO_Test_State::$posts as $item ) {
			if ( is_object( $item ) && (int) ( $item->ID ?? 0 ) === $id ) {
				return $item;
			}
		}
		return null;
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
		if ( is_object( $post ) && ! $post instanceof WP_Post ) {
			$slug = (string) ( $post->post_name ?? 'post' );
			return home_url( ( '' !== $slug ? $slug : 'post' ) . '/' );
		}
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


if ( ! class_exists( 'WP_Post_Type' ) ) {
	class WP_Post_Type {
		public string $name = '';
		public object $labels;
	}
}

if ( ! class_exists( 'WP_Taxonomy' ) ) {
	class WP_Taxonomy {
		public string $name = '';
		public object $labels;
	}
}

if ( ! function_exists( 'strip_shortcodes' ) ) {
	function strip_shortcodes( string $content ): string {
		return (string) preg_replace( '/\[[^\]]+\]/', '', $content );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action ): string {
		RankMySEO_Test_State::$nonces[ $action ] = 'nonce-' . $action;
		return RankMySEO_Test_State::$nonces[ $action ];
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

if ( ! function_exists( 'update_post_meta' ) ) {
	/** @param mixed $value */
	function update_post_meta( int $post_id, string $key, $value ): bool {
		RankMySEO_Test_State::$post_meta[ $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $key ): bool {
		unset( RankMySEO_Test_State::$post_meta[ $post_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	/** @return mixed */
	function get_term_meta( int $term_id, string $key = '', bool $single = false ) {
		if ( '' === $key ) {
			return RankMySEO_Test_State::$term_meta[ $term_id ] ?? [];
		}
		if ( ! isset( RankMySEO_Test_State::$term_meta[ $term_id ][ $key ] ) ) {
			return $single ? '' : [];
		}
		$value = RankMySEO_Test_State::$term_meta[ $term_id ][ $key ];
		return $single ? $value : [ $value ];
	}
}

if ( ! function_exists( 'update_term_meta' ) ) {
	/** @param mixed $value */
	function update_term_meta( int $term_id, string $key, $value ): bool {
		RankMySEO_Test_State::$term_meta[ $term_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_term_meta' ) ) {
	function delete_term_meta( int $term_id, string $key ): bool {
		unset( RankMySEO_Test_State::$term_meta[ $term_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists( string $meta_type, int $object_id, string $meta_key ): bool {
		$bucket = 'term' === $meta_type ? RankMySEO_Test_State::$term_meta : RankMySEO_Test_State::$post_meta;
		return isset( $bucket[ $object_id ] ) && array_key_exists( $meta_key, $bucket[ $object_id ] );
	}
}

if ( ! function_exists( 'get_term' ) ) {
	/** @param mixed $term */
	function get_term( $term, string $taxonomy = '' ): ?WP_Term {
		unset( $taxonomy );
		$id = is_object( $term ) ? (int) $term->term_id : (int) $term;
		return RankMySEO_Test_State::$terms[ $id ] ?? null;
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	/** @param mixed $args */
	function get_post_types( $args = [], string $output = 'names' ): array {
		unset( $args );
		if ( 'objects' === $output ) {
			$post         = new WP_Post_Type();
			$post->name   = 'post';
			$post->labels = (object) [
				'name'          => 'Posts',
				'singular_name' => 'Post',
			];
			$page         = new WP_Post_Type();
			$page->name   = 'page';
			$page->labels = (object) [
				'name'          => 'Pages',
				'singular_name' => 'Page',
			];
			return [
				'post' => $post,
				'page' => $page,
			];
		}
		return [
			'post' => 'post',
			'page' => 'page',
		];
	}
}

if ( ! function_exists( 'get_taxonomies' ) ) {
	/** @param mixed $args */
	function get_taxonomies( $args = [], string $output = 'names' ): array {
		unset( $args );
		if ( 'objects' === $output ) {
			$category         = new WP_Taxonomy();
			$category->name   = 'category';
			$category->labels = (object) [
				'name'          => 'Categories',
				'singular_name' => 'Category',
			];
			return [ 'category' => $category ];
		}
		return [
			'category' => 'category',
			'post_tag' => 'post_tag',
		];
	}
}

if ( ! function_exists( 'register_post_meta' ) ) {
	/** @param array<string, mixed> $args */
	function register_post_meta( string $post_type, string $meta_key, array $args ): bool {
		RankMySEO_Test_State::$registered_meta[] = [
			'object_type' => 'post',
			'object'      => $post_type,
			'key'         => $meta_key,
			'args'        => $args,
		];
		return true;
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	function get_locale(): string {
		return 'en_US';
	}
}

if ( ! function_exists( 'absint' ) ) {
	/** @param mixed $maybeint */
	function absint( $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return RankMySEO_Test_State::$is_admin;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	/** @param mixed $post */
	function get_edit_post_link( $post, string $context = 'display' ): string {
		unset( $context );
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		return admin_url( 'post.php?post=' . $id . '&action=edit' );
	}
}

if ( ! function_exists( 'get_registered_meta_keys' ) ) {
	/** @return array<string, array<string, mixed>> */
	function get_registered_meta_keys( string $object_type, string $object_subtype = '' ): array {
		$keys = [];
		foreach ( RankMySEO_Test_State::$registered_meta as $entry ) {
			if ( ( $entry['object_type'] ?? '' ) !== $object_type ) {
				continue;
			}
			if ( '' !== $object_subtype && ( $entry['object'] ?? '' ) !== $object_subtype ) {
				continue;
			}
			$key = (string) ( $entry['key'] ?? '' );
			if ( '' !== $key ) {
				$keys[ $key ] = is_array( $entry['args'] ?? null ) ? $entry['args'] : [];
			}
		}
		return $keys;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string, mixed> $args Query args.
	 * @return list<WP_Post>
	 */
	function get_posts( array $args = [] ): array {
		$types   = (array) ( $args['post_type'] ?? [ 'post' ] );
		$status  = (array) ( $args['post_status'] ?? [ 'publish' ] );
		$exclude = array_map( 'intval', (array) ( $args['post__not_in'] ?? [] ) );
		$meta    = (string) ( $args['meta_key'] ?? '' );
		$limit   = (int) ( $args['posts_per_page'] ?? 5 );
		$found   = [];
		foreach ( RankMySEO_Test_State::$posts as $post ) {
			if ( ! in_array( $post->post_type, $types, true ) ) {
				continue;
			}
			if ( ! in_array( $post->post_status, $status, true ) ) {
				continue;
			}
			if ( in_array( (int) $post->ID, $exclude, true ) ) {
				continue;
			}
			if ( '' !== $meta ) {
				$stored = RankMySEO_Test_State::$post_meta[ $post->ID ][ $meta ] ?? '';
				if ( '' === trim( (string) $stored ) ) {
					continue;
				}
			}
			$found[] = $post;
			if ( $limit > 0 && count( $found ) >= $limit ) {
				break;
			}
		}
		return $found;
	}
}

if ( ! function_exists( 'selected' ) ) {
	/** @param mixed $selected @param mixed $current */
	function selected( $selected, $current = true, bool $display = true ): string {
		$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Boolean attribute.
		}
		return $result;
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var array<string, mixed> */
		public array $query_vars = [];

		public bool $main_query = true;

		public function is_main_query(): bool {
			return $this->main_query;
		}

		/** @return mixed */
		public function get( string $key ) {
			return $this->query_vars[ $key ] ?? '';
		}

		/** @param mixed $value */
		public function set( string $key, $value ): void {
			$this->query_vars[ $key ] = $value;
		}
	}
}

if ( ! function_exists( 'register_term_meta' ) ) {
	/** @param array<string, mixed> $args */
	function register_term_meta( string $taxonomy, string $meta_key, array $args ): bool {
		RankMySEO_Test_State::$registered_meta[] = [
			'object_type' => 'term',
			'taxonomy'    => $taxonomy,
			'key'         => $meta_key,
			'args'        => $args,
		];
		return true;
	}
}


if ( ! function_exists( 'absint' ) ) {
	/** @param mixed $maybeint */
	function absint( $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '', string $scheme = 'admin' ): string {
		unset( $scheme );
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title, string $fallback = '', string $context = 'save' ): string {
		unset( $fallback, $context );
		$title = strtolower( strip_tags( $title ) );
		$title = (string) preg_replace( '/[^a-z0-9\s\-]/', '', $title );
		$title = (string) preg_replace( '/[\s\-]+/', '-', $title );
		return trim( $title, '-' );
	}
}


if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme = 'auth' ): string {
		return RankMySEO_Test_State::$salts[ $scheme ] ?? ( 'salt-' . $scheme );
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

if ( ! function_exists( 'is_post_type_viewable' ) ) {
	/** @param mixed $post_type */
	function is_post_type_viewable( $post_type ): bool {
		$type = is_object( $post_type ) ? (string) ( $post_type->name ?? '' ) : (string) $post_type;
		return in_array( $type, [ 'post', 'page' ], true );
	}
}

if ( ! function_exists( 'rankmyseo_test_http_response' ) ) {
	/**
	 * @param array<string, mixed>  $body
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
		$id                              = ++RankMySEO_Test_State::$next_post_id;
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
	/**
	 * @return int
	 */
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

RankMySEO_Test_State::reset();
