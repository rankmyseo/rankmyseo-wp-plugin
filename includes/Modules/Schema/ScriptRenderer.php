<?php
/**
 * Safe JSON-LD script tag renderer.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Encodes a schema document and neutralizes script-breaking sequences.
 */
final class ScriptRenderer {
	/**
	 * Render a JSON-LD script element, or an empty string when there is nothing to print.
	 *
	 * @param array<string, mixed> $document Schema document with @context and @graph.
	 * @return string
	 */
	public function render( array $document ): string {
		$graph = $document['@graph'] ?? null;
		if ( ! is_array( $graph ) || [] === $graph ) {
			return '';
		}

		$json = wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}

		// Keep a literal "</script" sequence from closing the HTML element early.
		$json = str_ireplace( '</script', '<\/script', $json );

		return '<script type="application/ld+json">' . $json . '</script>';
	}
}
