<?php
/**
 * Percent-token template renderer for SEO titles and descriptions.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Replaces tokens such as %title% and %sep% and cleans dangling separators.
 */
final class TemplateEngine {
	/**
	 * Render a template against a context.
	 *
	 * Unknown tokens are left in place so authors can see them.
	 *
	 * @param string          $template Template string.
	 * @param TemplateContext $context  Replacement values.
	 * @return string
	 */
	public function replace( string $template, TemplateContext $context ): string {
		$vars = $context->all();

		$rendered = (string) preg_replace_callback(
			'/%([a-z0-9_]+)%/i',
			static function ( array $matches ) use ( $vars ): string {
				$key = strtolower( (string) $matches[1] );
				if ( array_key_exists( $key, $vars ) ) {
					return $vars[ $key ];
				}
				return (string) $matches[0];
			},
			$template
		);

		return $this->cleanup( $rendered, $vars['sep'] ?? '' );
	}

	/**
	 * Collapse whitespace and remove separators left behind by empty tokens.
	 *
	 * @param string $value Rendered text.
	 * @param string $sep   Separator token value.
	 * @return string
	 */
	private function cleanup( string $value, string $sep ): string {
		$value = trim( (string) preg_replace( '/\s+/u', ' ', $value ) );

		if ( '' === $sep ) {
			return $value;
		}

		$quoted        = preg_quote( $sep, '/' );
		$pattern_edges = '/^(?:' . $quoted . '\s*)+|(?:\s*' . $quoted . ')+$/u';
		$pattern_dupes = '/(?:\s*' . $quoted . '\s*){2,}/u';

		do {
			$next = trim( (string) preg_replace( $pattern_edges, '', $value ) );
			$next = trim( (string) preg_replace( $pattern_dupes, ' ' . $sep . ' ', $next ) );
			$next = trim( (string) preg_replace( '/\s+/u', ' ', $next ) );

			if ( $next === $value ) {
				break;
			}

			$value = $next;
		} while ( true );

		return $value;
	}
}
