<?php
/**
 * Robots directive value object and wp_robots mapper.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Sanitizes stored robots settings and merges them into WordPress wp_robots.
 */
final class Robots {
	public const IMAGE_PREVIEWS = [ '', 'none', 'standard', 'large' ];

	/**
	 * Default stored robots object. False and -1 mean "do not emit".
	 *
	 * @return array<string, bool|int|string>
	 */
	public static function defaults(): array {
		return [
			'noindex'           => false,
			'nofollow'          => false,
			'noarchive'         => false,
			'nosnippet'         => false,
			'noimageindex'      => false,
			'max_snippet'       => -1,
			'max_image_preview' => '',
			'max_video_preview' => -1,
		];
	}

	/**
	 * Sanitize a robots payload from REST, post meta, or a form field.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, bool|int|string>
	 */
	public static function sanitize( $value ): array {
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : [];
		}

		if ( ! is_array( $value ) ) {
			return self::defaults();
		}

		$clean = self::defaults();

		foreach ( [ 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' ] as $flag ) {
			if ( array_key_exists( $flag, $value ) ) {
				$clean[ $flag ] = self::to_bool( $value[ $flag ] );
			}
		}

		if ( array_key_exists( 'max_snippet', $value ) ) {
			$clean['max_snippet'] = self::bound_int( $value['max_snippet'], -1, 320 );
		}

		if ( array_key_exists( 'max_video_preview', $value ) ) {
			$clean['max_video_preview'] = self::bound_int( $value['max_video_preview'], -1, 600 );
		}

		if ( array_key_exists( 'max_image_preview', $value ) ) {
			$preview = sanitize_key( (string) $value['max_image_preview'] );
			if ( in_array( $preview, self::IMAGE_PREVIEWS, true ) ) {
				$clean['max_image_preview'] = $preview;
			}
		}

		return $clean;
	}

	/**
	 * Whether the sanitized object carries any directive we should print.
	 *
	 * @param array<string, bool|int|string> $robots Sanitized robots.
	 * @return bool
	 */
	public static function is_default( array $robots ): bool {
		return self::defaults() === $robots;
	}

	/**
	 * Merge RankMySEO directives into the wp_robots array.
	 *
	 * A site-wide noindex from WordPress (blog_public = 0) is never removed.
	 *
	 * @param array<string, bool|string>     $existing Directives already set by WordPress.
	 * @param array<string, bool|int|string> $robots   Sanitized RankMySEO robots.
	 * @param bool                           $blog_public Whether the site is visible to search engines.
	 * @return array<string, bool|string>
	 */
	public static function apply( array $existing, array $robots, bool $blog_public ): array {
		$directives = $existing;

		if ( ! $blog_public ) {
			$directives['noindex'] = true;
		}

		foreach ( [ 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' ] as $flag ) {
			if ( 'noindex' === $flag && ! $blog_public ) {
				continue;
			}

			if ( ! empty( $robots[ $flag ] ) ) {
				$directives[ $flag ] = true;
			}
		}

		$max_snippet = isset( $robots['max_snippet'] ) ? (int) $robots['max_snippet'] : -1;
		if ( $max_snippet >= 0 ) {
			$directives['max-snippet'] = (string) $max_snippet;
		}

		$image_preview = isset( $robots['max_image_preview'] ) ? (string) $robots['max_image_preview'] : '';
		if ( in_array( $image_preview, [ 'none', 'standard', 'large' ], true ) ) {
			$directives['max-image-preview'] = $image_preview;
		}

		$max_video = isset( $robots['max_video_preview'] ) ? (int) $robots['max_video_preview'] : -1;
		if ( $max_video >= 0 ) {
			$directives['max-video-preview'] = (string) $max_video;
		}

		return $directives;
	}

	/**
	 * Coerce a flag to bool without treating an absent value as true.
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

		$normalized = strtolower( trim( (string) $value ) );
		return in_array( $normalized, [ '1', 'true', 'yes', 'on' ], true );
	}

	/**
	 * Cast a numeric input into an inclusive range.
	 *
	 * @param mixed $value Raw number.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	private static function bound_int( $value, int $min, int $max ): int {
		if ( is_string( $value ) && ! is_numeric( $value ) ) {
			return $min;
		}

		$number = (int) $value;
		if ( $number < $min ) {
			return $min;
		}
		if ( $number > $max ) {
			return $max;
		}

		return $number;
	}
}
