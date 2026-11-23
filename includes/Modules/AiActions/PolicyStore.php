<?php
/**
 * Opt-in auto-apply policy.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

/**
 * Stores rankmyseo_ai_policy and enforces v1 guardrails.
 *
 * The stored shape matches GET and PUT /wp/v1/policy.
 */
final class PolicyStore {
	public const OPTION = 'rankmyseo_ai_policy';

	/**
	 * Policy used when nothing has been saved. Auto-apply is off.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'enabled'      => false,
			'actionTypes'  => array(),
			'postTypes'    => array(),
			'excludedUrls' => array( '/' ),
			'dailyCap'     => 10,
			'articles'     => 'draft',
		);
	}

	/**
	 * Read the saved policy. content_patch is always removed.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$stored = get_option( self::OPTION, array() );
		return $this->normalize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Validate a replacement policy.
	 *
	 * @param mixed $input Raw body.
	 * @return array{ok: true, policy: array<string, mixed>}|array{ok: false, message: string}
	 */
	public function parse( $input ): array {
		if ( ! is_array( $input ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Send the auto-apply settings.', 'rankmyseo' ),
			);
		}
		if ( array_key_exists( 'articles', $input ) && 'draft' !== $input['articles'] ) {
			return array(
				'ok'      => false,
				'message' => __( 'New articles are saved as drafts.', 'rankmyseo' ),
			);
		}
		if ( isset( $input['enabled'] ) && ! is_bool( $input['enabled'] ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Say whether auto-apply is on.', 'rankmyseo' ),
			);
		}

		$requested = isset( $input['actionTypes'] ) && is_array( $input['actionTypes'] ) ? $input['actionTypes'] : array();
		foreach ( $requested as $item ) {
			if ( ActionCatalog::CONTENT_PATCH === $item ) {
				return array(
					'ok'      => false,
					'message' => __( 'Page content changes stay on the approval queue.', 'rankmyseo' ),
				);
			}
			if ( ! is_string( $item ) || ! in_array( $item, ActionCatalog::CLOUD_TYPES, true ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'That action type is not one this site can auto-apply.', 'rankmyseo' ),
				);
			}
		}

		$post_types = isset( $input['postTypes'] ) && is_array( $input['postTypes'] ) ? $input['postTypes'] : array();
		if ( count( $post_types ) > 20 ) {
			return array(
				'ok'      => false,
				'message' => __( 'Post types are short names, up to 20.', 'rankmyseo' ),
			);
		}
		foreach ( $post_types as $type ) {
			if ( ! is_string( $type ) || 1 !== preg_match( '/^[a-z0-9_-]{1,32}$/', $type ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'Post types are short names, up to 20.', 'rankmyseo' ),
				);
			}
		}

		$excluded = isset( $input['excludedUrls'] ) && is_array( $input['excludedUrls'] ) ? $input['excludedUrls'] : array();
		if ( count( $excluded ) > 50 ) {
			return array(
				'ok'      => false,
				'message' => __( 'Keep the excluded list to 50 addresses.', 'rankmyseo' ),
			);
		}
		$excluded_urls = array();
		foreach ( $excluded as $item ) {
			if ( ! is_string( $item ) || '' === $item || strlen( $item ) > 2048 ) {
				return array(
					'ok'      => false,
					'message' => __( 'Each excluded address must be on this site.', 'rankmyseo' ),
				);
			}
			if ( str_starts_with( $item, '/' ) ) {
				$excluded_urls[] = $item;
				continue;
			}
			$url = PayloadSanitizer::url( $item );
			if ( '' === $url || ! $this->same_host( $url ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'Each excluded address must be on this site.', 'rankmyseo' ),
				);
			}
			$excluded_urls[] = $url;
		}

		if ( isset( $input['dailyCap'] ) && ( ! is_int( $input['dailyCap'] ) || $input['dailyCap'] < 0 || $input['dailyCap'] > 100 ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'The daily cap is a whole number from 0 to 100.', 'rankmyseo' ),
			);
		}

		$types = array();
		foreach ( $requested as $item ) {
			if ( is_string( $item ) && ActionCatalog::CONTENT_PATCH !== $item && ! in_array( $item, $types, true ) ) {
				$types[] = $item;
			}
		}

		return array(
			'ok'     => true,
			'policy' => array(
				'enabled'      => true === ( $input['enabled'] ?? false ),
				'actionTypes'  => $types,
				'postTypes'    => array_values( array_filter( $post_types, 'is_string' ) ),
				'excludedUrls' => $excluded_urls,
				'dailyCap'     => isset( $input['dailyCap'] ) && is_int( $input['dailyCap'] ) ? $input['dailyCap'] : 10,
				'articles'     => 'draft',
			),
		);
	}

	/**
	 * Save a parsed policy.
	 *
	 * @param array<string, mixed> $policy Policy.
	 * @return void
	 */
	public function save( array $policy ): void {
		update_option( self::OPTION, $this->normalize( $policy ), false );
	}

	/**
	 * Whether an action may be applied without a person clicking approve.
	 *
	 * @param array<string, mixed> $action        Hydrated action.
	 * @param string               $post_type     Target post type.
	 * @param int                  $applied_today Auto-applies already used today.
	 * @return bool
	 */
	public function allows( array $action, string $post_type, int $applied_today ): bool {
		$policy = $this->get();
		if ( empty( $policy['enabled'] ) ) {
			return false;
		}
		$type = (string) ( $action['action_type'] ?? '' );
		if ( ActionCatalog::is_approval_only( $type ) ) {
			return false;
		}
		$allowed = isset( $policy['actionTypes'] ) && is_array( $policy['actionTypes'] ) ? $policy['actionTypes'] : array();
		$names   = array( $type, ActionCatalog::canonical( $type ) );
		$match   = false;
		foreach ( $names as $name ) {
			if ( in_array( $name, $allowed, true ) ) {
				$match = true;
			}
		}
		if ( ! $match ) {
			return false;
		}

		$post_types = isset( $policy['postTypes'] ) && is_array( $policy['postTypes'] ) ? $policy['postTypes'] : array();
		if ( array() === $post_types || ! in_array( $post_type, $post_types, true ) ) {
			return false;
		}

		$cap = isset( $policy['dailyCap'] ) ? (int) $policy['dailyCap'] : 0;
		if ( $applied_today >= $cap ) {
			return false;
		}

		$url = (string) ( $action['target_url'] ?? '' );
		return ! $this->is_excluded( $url, $policy['excludedUrls'] ?? array() );
	}

	/**
	 * Normalize a stored or incoming policy.
	 *
	 * @param array<string, mixed> $raw Raw policy.
	 * @return array<string, mixed>
	 */
	private function normalize( array $raw ): array {
		$base  = $this->defaults();
		$types = array();
		if ( isset( $raw['actionTypes'] ) && is_array( $raw['actionTypes'] ) ) {
			foreach ( $raw['actionTypes'] as $item ) {
				if ( is_string( $item ) && in_array( $item, ActionCatalog::CLOUD_TYPES, true ) && ActionCatalog::CONTENT_PATCH !== $item && ! in_array( $item, $types, true ) ) {
					$types[] = $item;
				}
			}
		}
		$post_types = array();
		if ( isset( $raw['postTypes'] ) && is_array( $raw['postTypes'] ) ) {
			foreach ( $raw['postTypes'] as $item ) {
				if ( is_string( $item ) && 1 === preg_match( '/^[a-z0-9_-]{1,32}$/', $item ) ) {
					$post_types[] = $item;
				}
			}
		}
		$excluded = array();
		if ( isset( $raw['excludedUrls'] ) && is_array( $raw['excludedUrls'] ) ) {
			foreach ( $raw['excludedUrls'] as $item ) {
				if ( is_string( $item ) && '' !== $item && strlen( $item ) <= 2048 ) {
					$excluded[] = $item;
				}
			}
		}
		$cap = isset( $raw['dailyCap'] ) && is_numeric( $raw['dailyCap'] ) ? (int) $raw['dailyCap'] : (int) $base['dailyCap'];
		if ( $cap < 0 ) {
			$cap = 0;
		}
		if ( $cap > 100 ) {
			$cap = 100;
		}

		return array(
			'enabled'      => true === ( $raw['enabled'] ?? false ),
			'actionTypes'  => $types,
			'postTypes'    => array_slice( $post_types, 0, 20 ),
			'excludedUrls' => array_slice( $excluded, 0, 50 ),
			'dailyCap'     => $cap,
			'articles'     => 'draft',
		);
	}

	/**
	 * Whether a URL is on the excluded list. "/" matches only the homepage.
	 *
	 * @param string       $url      Target URL.
	 * @param array<mixed> $excluded Excluded paths or URLs.
	 * @return bool
	 */
	private function is_excluded( string $url, array $excluded ): bool {
		$path = $this->path_of( $url );
		foreach ( $excluded as $item ) {
			if ( ! is_string( $item ) || '' === $item ) {
				continue;
			}
			if ( '/' === $item ) {
				if ( '/' === $path ) {
					return true;
				}
				continue;
			}
			if ( str_starts_with( $item, '/' ) ) {
				$prefix = untrailingslashit( $item );
				if ( $path === $item || $path === $prefix || str_starts_with( $path, $prefix . '/' ) ) {
					return true;
				}
				continue;
			}
			if ( $url === $item || $path === $this->path_of( $item ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Path portion of a URL or path.
	 *
	 * @param string $value URL or path.
	 * @return string
	 */
	private function path_of( string $value ): string {
		$path = $value;
		if ( 1 === preg_match( '#^https?://#i', $value ) ) {
			$parsed = wp_parse_url( $value, PHP_URL_PATH );
			$path   = is_string( $parsed ) && '' !== $parsed ? $parsed : '/';
		}
		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = untrailingslashit( $path );
		}
		return strtolower( $path );
	}

	/**
	 * Whether a URL host matches this site.
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	private function same_host( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) && is_string( $home ) && strtolower( $host ) === strtolower( $home );
	}
}
