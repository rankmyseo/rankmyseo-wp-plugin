<?php
/**
 * Sanitizers for AI action payloads.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

/**
 * Cleans titles, URLs, robots, and HTML before they touch WordPress.
 *
 * Meta keys match the titles module (`_rankmyseo_title` and siblings) and the
 * schema fields `_rankmyseo_schema_type` and `_rankmyseo_schema_data`.
 */
final class PayloadSanitizer {
	public const META_TITLE       = '_rankmyseo_title';
	public const META_DESCRIPTION = '_rankmyseo_description';
	public const META_CANONICAL   = '_rankmyseo_canonical';
	public const META_ROBOTS      = '_rankmyseo_robots';
	public const META_FOCUS       = '_rankmyseo_focus_keyword';
	public const META_SCHEMA_TYPE = '_rankmyseo_schema_type';
	public const META_SCHEMA_DATA = '_rankmyseo_schema_data';
	public const META_IMAGE_ALT   = '_wp_attachment_image_alt';

	public const TITLE_MAX       = 255;
	public const DESCRIPTION_MAX = 500;
	public const KEYWORD_MAX     = 200;
	public const ALT_MAX         = 300;

	/**
	 * Text fields a card can show as a single line.
	 *
	 * @var list<string>
	 */
	private const PREVIEW_TEXT_KEYS = array(
		'suggested',
		'seoTitle',
		'title',
		'seo_title',
		'seoDescription',
		'description',
		'metaDescription',
		'content',
		'body',
		'current',
		'canonical',
		'schemaType',
		'schema_type',
		'imageAlt',
		'alt',
	);

	/**
	 * Plain text with shortcodes and markup removed.
	 *
	 * @param string $value Raw text.
	 * @param int    $max   Maximum length.
	 * @return string
	 */
	public static function plain( string $value, int $max ): string {
		$clean = sanitize_text_field( strip_shortcodes( wp_strip_all_tags( $value ) ) );
		if ( strlen( $clean ) > $max ) {
			$clean = substr( $clean, 0, $max );
		}
		return $clean;
	}

	/**
	 * HTTP(S) URL, or an empty string when the scheme is not allowed.
	 *
	 * @param string $value Raw URL.
	 * @return string
	 */
	public static function url( string $value ): string {
		$clean = esc_url_raw( trim( $value ) );
		if ( '' === $clean ) {
			return '';
		}
		$scheme = wp_parse_url( $clean, PHP_URL_SCHEME );
		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		return $clean;
	}

	/**
	 * Post HTML with scripts, frames, and event handlers removed.
	 *
	 * @param string $value Raw HTML.
	 * @return string
	 */
	public static function html( string $value ): string {
		$clean = (string) preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $value );
		$clean = (string) preg_replace( '/<iframe\b[^>]*>.*?<\/iframe>/is', '', $clean );
		$clean = (string) preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean );
		return wp_kses_post( $clean );
	}

	/**
	 * First non-empty string among the given keys.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param array<int, string>   $keys    Keys in preference order.
	 * @return string
	 */
	public static function text_field( array $payload, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $payload[ $key ] ) || ! is_string( $payload[ $key ] ) ) {
				continue;
			}
			if ( '' !== trim( $payload[ $key ] ) ) {
				return $payload[ $key ];
			}
		}
		return '';
	}

	/**
	 * Robots object aligned with the titles module.
	 *
	 * @param mixed $value Raw robots value.
	 * @return array<string, bool|int|string>
	 */
	public static function robots( $value ): array {
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		$clean = array(
			'noindex'           => false,
			'nofollow'          => false,
			'noarchive'         => false,
			'nosnippet'         => false,
			'noimageindex'      => false,
			'max_snippet'       => -1,
			'max_image_preview' => '',
			'max_video_preview' => -1,
		);

		foreach ( array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' ) as $flag ) {
			if ( array_key_exists( $flag, $value ) ) {
				$clean[ $flag ] = self::to_bool( $value[ $flag ] );
			}
		}

		return $clean;
	}

	/**
	 * Readable before/after line for a card.
	 *
	 * @param string               $type    Action type.
	 * @param array<string, mixed> $payload Payload.
	 * @return string
	 */
	public static function preview( string $type, array $payload ): string {
		$canonical = ActionCatalog::canonical( $type );
		if ( ActionCatalog::ADD_REDIRECT === $canonical ) {
			$source = self::text_field( $payload, array( 'source_path', 'source', 'from' ) );
			$target = self::text_field( $payload, array( 'target_url', 'target', 'to', 'suggested' ) );
			if ( '' === $source && '' === $target ) {
				return self::text_field( $payload, array( 'suggested', 'current', 'title' ) );
			}
			return trim( $source . ' → ' . $target );
		}

		$text = self::text_field( $payload, self::PREVIEW_TEXT_KEYS );
		if ( '' !== $text ) {
			return self::plain( $text, 500 );
		}
		if ( isset( $payload['faq'] ) && is_array( $payload['faq'] ) ) {
			return __( 'FAQ structured data', 'rankmyseo' );
		}
		if ( self::text_payload_is_blank( $payload ) ) {
			return '';
		}
		$encoded = wp_json_encode( $payload );
		return is_string( $encoded ) ? self::plain( $encoded, 240 ) : '';
	}

	/**
	 * Whether every recognized text field is empty.
	 *
	 * An empty title is stored as {"seoTitle":""}. Encoding that array makes the
	 * approval card strike through raw JSON. A blank text payload is an empty string.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return bool
	 */
	private static function text_payload_is_blank( array $payload ): bool {
		$saw_text_key = false;
		foreach ( $payload as $key => $value ) {
			if ( ! is_string( $key ) ) {
				return false;
			}
			if ( in_array( $key, self::PREVIEW_TEXT_KEYS, true ) ) {
				$saw_text_key = true;
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					return false;
				}
				if ( ! is_string( $value ) && null !== $value && '' !== $value && array() !== $value ) {
					return false;
				}
				continue;
			}
			if ( null === $value || '' === $value || array() === $value ) {
				continue;
			}
			return false;
		}

		return $saw_text_key;
	}

	/**
	 * Coerce a flag without treating an empty value as true.
	 *
	 * @param mixed $value Raw flag.
	 * @return bool
	 */
	private static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return 1 === (int) $value;
		}
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on' ), true );
	}
}
