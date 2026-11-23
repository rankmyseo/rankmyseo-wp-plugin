<?php
/**
 * Apache and Nginx export for exact redirects.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Renders active exact redirects as server rules. Regex rules are listed as comments.
 */
final class ServerRulesExporter {
	/**
	 * Render Apache mod_alias rules.
	 *
	 * @param list<array<string, mixed>> $rows Redirect rows.
	 * @return string
	 */
	public function to_htaccess( array $rows ): string {
		$lines   = [];
		$lines[] = '# RankMySEO redirects';
		$lines[] = '<IfModule mod_alias.c>';

		foreach ( $rows as $row ) {
			if ( empty( $row['is_active'] ) ) {
				continue;
			}

			$source = (string) ( $row['source_path'] ?? '' );
			$target = (string) ( $row['target_url'] ?? '' );
			$code   = (int) ( $row['status_code'] ?? 301 );
			$type   = (string) ( $row['match_type'] ?? 'exact' );

			if ( 'exact' !== $type ) {
				$lines[] = '# Skipped ' . $type . ' rule. Review it in RankMySEO before enabling: ' . $this->comment( $source );
				continue;
			}

			if ( ! $this->is_safe_token( $source ) || ( '' !== $target && ! $this->is_safe_token( $target ) ) ) {
				$lines[] = '# Skipped rule with unsafe characters: ' . $this->comment( $source );
				continue;
			}

			if ( 410 === $code || 451 === $code ) {
				$lines[] = 'RedirectMatch ' . $code . ' ^' . preg_quote( $source, ' ' ) . '/?$';
				continue;
			}

			if ( '' === $target ) {
				continue;
			}

			$lines[] = 'Redirect ' . $code . ' ' . $source . ' ' . $target;
		}

		$lines[] = '</IfModule>';
		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * Render Nginx rewrite rules.
	 *
	 * @param list<array<string, mixed>> $rows Redirect rows.
	 * @return string
	 */
	public function to_nginx( array $rows ): string {
		$lines   = [];
		$lines[] = '# RankMySEO redirects';

		foreach ( $rows as $row ) {
			if ( empty( $row['is_active'] ) ) {
				continue;
			}

			$source = (string) ( $row['source_path'] ?? '' );
			$target = (string) ( $row['target_url'] ?? '' );
			$code   = (int) ( $row['status_code'] ?? 301 );
			$type   = (string) ( $row['match_type'] ?? 'exact' );

			if ( 'exact' !== $type ) {
				$lines[] = '# Skipped ' . $type . ' rule. Review it in RankMySEO before enabling: ' . $this->comment( $source );
				continue;
			}

			if ( ! $this->is_safe_token( $source ) || ( '' !== $target && ! $this->is_safe_token( $target ) ) ) {
				$lines[] = '# Skipped rule with unsafe characters: ' . $this->comment( $source );
				continue;
			}

			$quoted = preg_quote( $source, '/' );
			if ( 410 === $code || 451 === $code ) {
				$lines[] = 'location = ' . $source . ' { return ' . $code . '; }';
				continue;
			}

			if ( '' === $target ) {
				continue;
			}

			if ( 301 === $code ) {
				$lines[] = 'rewrite ^' . $quoted . '/?$ ' . $target . ' permanent;';
				continue;
			}

			if ( 302 === $code ) {
				$lines[] = 'rewrite ^' . $quoted . '/?$ ' . $target . ' redirect;';
				continue;
			}

			$lines[] = 'location = ' . $source . ' { return ' . $code . ' ' . $target . '; }';
		}

		$lines[] = '';
		return implode( "\n", $lines );
	}

	/**
	 * Whether a path or URL is safe to place in a server config line.
	 *
	 * @param string $value Token to check.
	 * @return bool
	 */
	private function is_safe_token( string $value ): bool {
		if ( '' === $value ) {
			return false;
		}

		return 1 !== preg_match( '/[\r\n;#\'"\\\\]/', $value );
	}

	/**
	 * Reduce a value to a single comment-safe line.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function comment( string $value ): string {
		$value = str_replace( [ "\r", "\n", '#' ], ' ', $value );
		if ( strlen( $value ) > 180 ) {
			$value = substr( $value, 0, 180 );
		}
		return $value;
	}
}
