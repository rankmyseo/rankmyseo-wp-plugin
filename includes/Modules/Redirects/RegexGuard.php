<?php
/**
 * Validates and executes redirect regular expressions with ReDoS limits.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Rejects unsafe patterns and runs the ones that pass under a bounded PCRE limit.
 */
final class RegexGuard {
	/**
	 * Maximum stored pattern length.
	 */
	public const MAX_LENGTH = 256;

	/**
	 * Backtrack limit applied while testing or executing a redirect pattern.
	 */
	public const BACKTRACK_LIMIT = '10000';

	/**
	 * Recursion limit applied while testing or executing a redirect pattern.
	 */
	public const RECURSION_LIMIT = '100';

	/**
	 * Return null when the pattern is safe, or a machine reason when it is not.
	 *
	 * @param string $pattern Raw regular expression without delimiters.
	 * @return string|null
	 */
	public function rejection_reason( string $pattern ): ?string {
		if ( '' === $pattern ) {
			return 'empty';
		}

		if ( strlen( $pattern ) > self::MAX_LENGTH ) {
			return 'too_long';
		}

		if ( preg_match( '/[\x00-\x1F\x7F]/', $pattern ) ) {
			return 'control_char';
		}

		if ( str_contains( $pattern, '(*' ) ) {
			return 'backtrack_verb';
		}

		if ( preg_match( '/\(\?(?:R|r|[0-9]+)/', $pattern ) ) {
			return 'recursion';
		}

		if ( $this->has_catastrophic_nesting( $pattern ) ) {
			return 'catastrophic';
		}

		$delimited = $this->delimit( $pattern );
		if ( null === $delimited ) {
			return 'delimiter';
		}

		$valid = $this->with_limits(
			static function () use ( $delimited ): bool {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Compile-test only; the handler is restored immediately.
				set_error_handler(
					static function (): bool {
						return true;
					}
				);
				$result = preg_match( $delimited, '' );
				restore_error_handler();
				return false !== $result;
			}
		);

		if ( ! $valid ) {
			return 'invalid';
		}

		return null;
	}

	/**
	 * Whether a pattern passes validation.
	 *
	 * @param string $pattern Raw regular expression without delimiters.
	 * @return bool
	 */
	public function is_safe( string $pattern ): bool {
		return null === $this->rejection_reason( $pattern );
	}

	/**
	 * Match a subject under the bounded PCRE limits.
	 *
	 * Unsafe or catastrophic patterns never run.
	 *
	 * @param string $pattern Raw regular expression without delimiters.
	 * @param string $subject Path to test.
	 * @return bool
	 */
	public function matches( string $pattern, string $subject ): bool {
		if ( ! $this->is_safe( $pattern ) ) {
			return false;
		}

		$delimited = $this->delimit( $pattern );
		if ( null === $delimited ) {
			return false;
		}

		return $this->with_limits(
			static function () use ( $delimited, $subject ): bool {
				$result = @preg_match( $delimited, $subject ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$error  = preg_last_error();
				if ( PREG_BACKTRACK_LIMIT_ERROR === $error || PREG_RECURSION_LIMIT_ERROR === $error ) {
					return false;
				}
				return 1 === $result;
			}
		);
	}

	/**
	 * Apply $1–$9 replacements when the target uses them.
	 *
	 * @param string $pattern Raw regular expression without delimiters.
	 * @param string $subject Request path.
	 * @param string $target  Target template.
	 * @return string
	 */
	public function replace( string $pattern, string $subject, string $target ): string {
		if ( ! str_contains( $target, '$' ) ) {
			return $target;
		}

		$delimited = $this->delimit( $pattern );
		if ( null === $delimited || ! $this->is_safe( $pattern ) ) {
			return $target;
		}

		$replaced = $this->with_limits(
			static function () use ( $delimited, $target, $subject ): string {
				$result = @preg_replace( $delimited, $target, $subject, 1 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return is_string( $result ) ? $result : $target;
			}
		);

		return $replaced;
	}

	/**
	 * Detect nested quantifiers and quantified alternation that cause ReDoS.
	 *
	 * @param string $pattern Raw pattern.
	 * @return bool
	 */
	private function has_catastrophic_nesting( string $pattern ): bool {
		$stripped = preg_replace( '/\\\\./', '', $pattern );
		if ( ! is_string( $stripped ) ) {
			return true;
		}

		$stripped = preg_replace( '/\[[^\]]*\]/', 'a', $stripped );
		if ( ! is_string( $stripped ) ) {
			return true;
		}

		if ( preg_match( '/\([^()]*[+*?][^()]*\)[+*?{]/', $stripped ) ) {
			return true;
		}

		if ( preg_match( '/(?:\.\*){2,}|(?:\.\+){2,}/', $stripped ) ) {
			return true;
		}

		if ( preg_match( '/\([^()]*\|[^()]*\)[+*?{]/', $stripped ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Wrap a pattern in a delimiter it does not already contain.
	 *
	 * @param string $pattern Raw pattern.
	 * @return string|null
	 */
	private function delimit( string $pattern ): ?string {
		foreach ( [ '#', '~', '!', '%', '@' ] as $delimiter ) {
			if ( ! str_contains( $pattern, $delimiter ) ) {
				return $delimiter . $pattern . $delimiter . 'iu';
			}
		}

		return null;
	}

	/**
	 * Run a callback with a tight PCRE backtrack and recursion limit.
	 *
	 * @template T
	 * @param callable(): T $callback Callback to execute.
	 * @return T
	 */
	private function with_limits( callable $callback ) {
		$backtrack = ini_get( 'pcre.backtrack_limit' );
		$recursion = ini_get( 'pcre.recursion_limit' );

		ini_set( 'pcre.backtrack_limit', self::BACKTRACK_LIMIT ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Bounds regex execution against ReDoS, then restores the previous value.
		ini_set( 'pcre.recursion_limit', self::RECURSION_LIMIT ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Bounds regex execution against ReDoS, then restores the previous value.

		try {
			return $callback();
		} finally {
			if ( false !== $backtrack ) {
				ini_set( 'pcre.backtrack_limit', (string) $backtrack ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Restores the limit captured before the bounded match.
			}
			if ( false !== $recursion ) {
				ini_set( 'pcre.recursion_limit', (string) $recursion ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Restores the limit captured before the bounded match.
			}
		}
	}
}
