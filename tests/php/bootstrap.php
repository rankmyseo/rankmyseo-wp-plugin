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

	/** @var array<int, array<string, mixed>> */
	public static array $post_meta = [];

	/** @var array<int, array<string, mixed>> */
	public static array $term_meta = [];

	/** @var array<int, WP_Post> */
	public static array $posts = [];

	/** @var array<int, WP_Term> */
	public static array $terms = [];

	/** @var list<array<string, mixed>> */
	public static array $registered_meta = [];

	/** @var array<string, string> */
	public static array $nonces = [];

	public static bool $is_admin = false;

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
		self::$bloginfo          = [
			'name'        => 'Test Site',
			'description' => 'A calm tagline',
		];
		self::$post_meta         = [];
		self::$term_meta         = [];
		self::$posts             = [];
		self::$terms             = [];
		self::$registered_meta   = [];
		self::$nonces            = [];
		self::$is_admin          = false;
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

		/** @var array<string, mixed> */
		private array $params = [];

		/** @param mixed $value */
		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		/** @return mixed */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
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

		/** @param mixed ...$args */
		public function prepare( string $query, ...$args ): string {
			$escaped = array_map(
				static fn( $arg ): string => "'" . addslashes( (string) $arg ) . "'",
				$args
			);
			return vsprintf( str_replace( '%s', '%s', $query ), $escaped );
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
	function add_action( string $hook_name, callable $callback, int $priority = 10 ): bool {
		unset( $priority );
		RankMySEO_Test_State::$actions[ $hook_name ][] = $callback;
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

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, callable $callback, int $priority = 10 ): bool {
		return add_action( $hook_name, $callback, $priority );
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	/** @param mixed $callback */
	function remove_action( string $hook_name, $callback, int $priority = 10 ): bool {
		unset( $hook_name, $callback, $priority );
		return true;
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 0;
		public string $post_title = '';
		public string $post_type = 'post';
		public string $post_excerpt = '';
		public string $post_content = '';
		public string $post_name = '';
		public string $post_status = 'publish';
		public string $post_date_gmt = '';
		public string $post_modified_gmt = '';
		public int $post_author = 1;
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public int $term_id = 0;
		public string $name = '';
		public string $taxonomy = 'category';
		public string $description = '';
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

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
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

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return esc_url_raw( $url );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/** @return array<string, mixed>|false */
	function wp_parse_url( string $url ) {
		$parts = parse_url( $url );
		return is_array( $parts ) ? $parts : false;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/** @param mixed $value */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/** @param mixed $data */
	function wp_json_encode( $data, int $flags = 0 ) {
		$json = json_encode( $data, $flags );
		return false === $json ? false : $json;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( string $nonce, string $action ): bool {
		return isset( RankMySEO_Test_State::$nonces[ $action ] ) && hash_equals( RankMySEO_Test_State::$nonces[ $action ], $nonce );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action ): string {
		RankMySEO_Test_State::$nonces[ $action ] = 'nonce-' . $action;
		return RankMySEO_Test_State::$nonces[ $action ];
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	function wp_is_post_revision( int $post_id ): bool {
		unset( $post_id );
		return false;
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	function wp_is_post_autosave( int $post_id ): bool {
		unset( $post_id );
		return false;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/** @return mixed */
	function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
		if ( '' === $key ) {
			return RankMySEO_Test_State::$post_meta[ $post_id ] ?? [];
		}
		if ( ! isset( RankMySEO_Test_State::$post_meta[ $post_id ][ $key ] ) ) {
			return $single ? '' : [];
		}
		$value = RankMySEO_Test_State::$post_meta[ $post_id ][ $key ];
		return $single ? $value : [ $value ];
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

if ( ! function_exists( 'get_post' ) ) {
	/** @param mixed $post */
	function get_post( $post ): ?WP_Post {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		return RankMySEO_Test_State::$posts[ $id ] ?? null;
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
			$post          = new WP_Post_Type();
			$post->name    = 'post';
			$post->labels  = (object) [
				'name'          => 'Posts',
				'singular_name' => 'Post',
			];
			$page          = new WP_Post_Type();
			$page->name    = 'page';
			$page->labels  = (object) [
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

RankMySEO_Test_State::reset();
