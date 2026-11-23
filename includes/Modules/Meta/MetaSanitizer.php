<?php
/**
 * Sanitizers for per-object SEO meta values.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Normalizes titles, descriptions, keywords, robots, and social fields.
 */
final class MetaSanitizer {
	public const TITLE_MAX       = 255;
	public const DESCRIPTION_MAX = 500;
	public const KEYWORD_MAX     = 200;
	public const SECONDARY_MAX   = 5;

	/**
	 * Sanitize an SEO or social title. Template tokens are preserved.
	 *
	 * @param mixed $value Raw title.
	 * @return string
	 */
	public static function sanitize_title( $value ): string {
		return Text::plain( (string) $value, self::TITLE_MAX );
	}

	/**
	 * Sanitize a meta or social description.
	 *
	 * @param mixed $value Raw description.
	 * @return string
	 */
	public static function sanitize_description( $value ): string {
		return Text::plain( (string) $value, self::DESCRIPTION_MAX );
	}

	/**
	 * Sanitize a canonical URL. Empty is allowed and means "use the default".
	 *
	 * @param mixed $value Raw URL.
	 * @return string
	 */
	public static function sanitize_canonical( $value ): string {
		$url = Text::url_or_attachment( (string) $value );
		return ctype_digit( $url ) ? '' : $url;
	}

	/**
	 * Sanitize a focus keyword.
	 *
	 * @param mixed $value Raw keyword.
	 * @return string
	 */
	public static function sanitize_keyword( $value ): string {
		return Text::plain( (string) $value, self::KEYWORD_MAX );
	}

	/**
	 * Sanitize up to five secondary keywords.
	 *
	 * @param mixed $value List or comma-separated string.
	 * @return list<string>
	 */
	public static function sanitize_keywords( $value ): array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			} else {
				$parts = preg_split( '/\s*,\s*/', $value );
				$value = is_array( $parts ) ? $parts : [];
			}
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$clean = [];
		foreach ( $value as $keyword ) {
			$text = self::sanitize_keyword( $keyword );
			if ( '' === $text || in_array( $text, $clean, true ) ) {
				continue;
			}
			$clean[] = $text;
			if ( count( $clean ) >= self::SECONDARY_MAX ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize a social image URL or attachment ID.
	 *
	 * @param mixed $value Raw image value.
	 * @return string
	 */
	public static function sanitize_image( $value ): string {
		return Text::url_or_attachment( (string) $value );
	}

	/**
	 * Sanitize the Twitter card type.
	 *
	 * @param mixed $value Raw card type.
	 * @return string
	 */
	public static function sanitize_twitter_card( $value ): string {
		$card = sanitize_key( (string) $value );
		if ( in_array( $card, [ 'summary', 'summary_large_image' ], true ) ) {
			return $card;
		}

		return 'summary_large_image';
	}

	/**
	 * Sanitize a full meta payload into the stored shape.
	 *
	 * @param array<string, mixed> $input Raw fields keyed by public names or meta keys.
	 * @return array<string, mixed>
	 */
	public static function sanitize_bundle( array $input ): array {
		$pick = static function ( array $source, string $name, string $meta ) {
			if ( array_key_exists( $name, $source ) ) {
				return $source[ $name ];
			}
			if ( array_key_exists( $meta, $source ) ) {
				return $source[ $meta ];
			}
			return null;
		};

		return [
			'title'               => self::sanitize_title( $pick( $input, 'title', MetaKeys::TITLE ) ?? '' ),
			'description'         => self::sanitize_description( $pick( $input, 'description', MetaKeys::DESCRIPTION ) ?? '' ),
			'canonical'           => self::sanitize_canonical( $pick( $input, 'canonical', MetaKeys::CANONICAL ) ?? '' ),
			'robots'              => Robots::sanitize( $pick( $input, 'robots', MetaKeys::ROBOTS ) ?? [] ),
			'focus_keyword'       => self::sanitize_keyword( $pick( $input, 'focus_keyword', MetaKeys::FOCUS_KEYWORD ) ?? '' ),
			'secondary_keywords'  => self::sanitize_keywords( $pick( $input, 'secondary_keywords', MetaKeys::SECONDARY_KEYWORDS ) ?? [] ),
			'og_title'            => self::sanitize_title( $pick( $input, 'og_title', MetaKeys::OG_TITLE ) ?? '' ),
			'og_description'      => self::sanitize_description( $pick( $input, 'og_description', MetaKeys::OG_DESCRIPTION ) ?? '' ),
			'og_image'            => self::sanitize_image( $pick( $input, 'og_image', MetaKeys::OG_IMAGE ) ?? '' ),
			'twitter_title'       => self::sanitize_title( $pick( $input, 'twitter_title', MetaKeys::TWITTER_TITLE ) ?? '' ),
			'twitter_description' => self::sanitize_description( $pick( $input, 'twitter_description', MetaKeys::TWITTER_DESCRIPTION ) ?? '' ),
			'twitter_image'       => self::sanitize_image( $pick( $input, 'twitter_image', MetaKeys::TWITTER_IMAGE ) ?? '' ),
			'twitter_card'        => self::sanitize_twitter_card( $pick( $input, 'twitter_card', MetaKeys::TWITTER_CARD ) ?? '' ),
		];
	}
}
