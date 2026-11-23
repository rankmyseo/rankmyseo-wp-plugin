<?php
/**
 * Variable bag used by the title and description template engine.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Plain-text replacement values keyed by template token name.
 */
final class TemplateContext {
	/**
	 * Replacement values.
	 *
	 * @var array<string, string>
	 */
	private array $vars;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $vars Replacement values.
	 */
	private function __construct( array $vars ) {
		$this->vars = $vars;
	}

	/**
	 * Build a context and strip HTML from every value.
	 *
	 * @param array<string, scalar|null> $vars Raw values.
	 * @return self
	 */
	public static function from_array( array $vars ): self {
		$clean = [];

		foreach ( $vars as $key => $value ) {
			$name = strtolower( (string) $key );
			if ( '' === $name ) {
				continue;
			}
			$clean[ $name ] = Text::plain( (string) $value );
		}

		return new self( $clean );
	}

	/**
	 * Return one replacement value.
	 *
	 * @param string $key Token name without percent signs.
	 * @return string
	 */
	public function get( string $key ): string {
		return $this->vars[ strtolower( $key ) ] ?? '';
	}

	/**
	 * Return the full replacement map.
	 *
	 * @return array<string, string>
	 */
	public function all(): array {
		return $this->vars;
	}
}
