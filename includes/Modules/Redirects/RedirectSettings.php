<?php
/**
 * Redirect and 404 monitor settings stored outside the shared autoloaded option.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Typed settings for slug redirects, query handling, and 404 pruning.
 */
final class RedirectSettings {
	/**
	 * Option key. Not autoloaded, so parallel modules can keep their own options.
	 */
	public const OPTION_KEY = 'rankmyseo_redirects_settings';

	/**
	 * Maximum number of custom ignore or bot patterns.
	 */
	public const MAX_PATTERNS = 50;

	/**
	 * Maximum length of a single pattern.
	 */
	public const MAX_PATTERN_LENGTH = 200;

	/**
	 * Return default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'auto_slug'            => true,
			'preserve_query'       => true,
			'strip_trailing_slash' => true,
			'ignore_patterns'      => [],
			'bot_patterns'         => [],
			'max_rows'             => 10000,
			'ttl_days'             => 30,
		];
	}

	/**
	 * Return sanitized settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return $this->sanitize( $stored );
	}

	/**
	 * Persist a partial settings update.
	 *
	 * @param array<string, mixed> $input Incoming values.
	 * @return array<string, mixed>
	 */
	public function update( array $input ): array {
		$clean = $this->sanitize( array_merge( $this->all(), $input ) );
		update_option( self::OPTION_KEY, $clean, false );
		return $clean;
	}

	/**
	 * Whether published slug changes create a 301 automatically.
	 *
	 * @return bool
	 */
	public function auto_slug_enabled(): bool {
		return (bool) $this->all()['auto_slug'];
	}

	/**
	 * Whether the original query string is appended to the target.
	 *
	 * @return bool
	 */
	public function preserve_query(): bool {
		return (bool) $this->all()['preserve_query'];
	}

	/**
	 * Whether trailing slashes are ignored when matching paths.
	 *
	 * @return bool
	 */
	public function strip_trailing_slash(): bool {
		return (bool) $this->all()['strip_trailing_slash'];
	}

	/**
	 * Extra 404 path ignore patterns.
	 *
	 * @return list<string>
	 */
	public function ignore_patterns(): array {
		$patterns = $this->all()['ignore_patterns'];
		return is_array( $patterns ) ? array_values( $patterns ) : [];
	}

	/**
	 * Extra bot user-agent substrings.
	 *
	 * @return list<string>
	 */
	public function bot_patterns(): array {
		$patterns = $this->all()['bot_patterns'];
		return is_array( $patterns ) ? array_values( $patterns ) : [];
	}

	/**
	 * Maximum 404 rows kept after pruning.
	 *
	 * @return int
	 */
	public function max_rows(): int {
		return (int) $this->all()['max_rows'];
	}

	/**
	 * Days a 404 row may sit idle before pruning.
	 *
	 * @return int
	 */
	public function ttl_days(): int {
		return (int) $this->all()['ttl_days'];
	}

	/**
	 * Sanitize a settings payload into the full stored shape.
	 *
	 * @param array<string, mixed> $input Raw values.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$defaults = $this->defaults();

		return [
			'auto_slug'            => $this->to_bool( $input['auto_slug'] ?? $defaults['auto_slug'] ),
			'preserve_query'       => $this->to_bool( $input['preserve_query'] ?? $defaults['preserve_query'] ),
			'strip_trailing_slash' => $this->to_bool( $input['strip_trailing_slash'] ?? $defaults['strip_trailing_slash'] ),
			'ignore_patterns'      => $this->sanitize_patterns( $input['ignore_patterns'] ?? [] ),
			'bot_patterns'         => $this->sanitize_patterns( $input['bot_patterns'] ?? [] ),
			'max_rows'             => $this->clamp_int( $input['max_rows'] ?? $defaults['max_rows'], 100, 100000, 10000 ),
			'ttl_days'             => $this->clamp_int( $input['ttl_days'] ?? $defaults['ttl_days'], 1, 365, 30 ),
		];
	}

	/**
	 * Coerce a value to a boolean.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, [ '1', 'true', 'yes', 'on' ], true ) ) {
				return true;
			}
			if ( in_array( $lower, [ '0', 'false', 'no', 'off', '' ], true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}

	/**
	 * Clamp an integer setting.
	 *
	 * @param mixed $value   Raw value.
	 * @param int   $min     Minimum allowed value.
	 * @param int   $max     Maximum allowed value.
	 * @param int   $fallback Fallback when the value is not numeric.
	 * @return int
	 */
	private function clamp_int( $value, int $min, int $max, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
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

	/**
	 * Sanitize a list of ignore or bot patterns.
	 *
	 * @param mixed $value Raw value. A newline-separated string is also accepted.
	 * @return list<string>
	 */
	private function sanitize_patterns( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\r\n|\r|\n/', $value );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$clean = [];
		foreach ( $value as $pattern ) {
			if ( ! is_string( $pattern ) && ! is_numeric( $pattern ) ) {
				continue;
			}
			$text = sanitize_text_field( (string) $pattern );
			$text = trim( $text );
			if ( '' === $text ) {
				continue;
			}
			if ( strlen( $text ) > self::MAX_PATTERN_LENGTH ) {
				$text = substr( $text, 0, self::MAX_PATTERN_LENGTH );
			}
			$clean[] = $text;
			if ( count( $clean ) >= self::MAX_PATTERNS ) {
				break;
			}
		}

		return array_values( array_unique( $clean ) );
	}
}
