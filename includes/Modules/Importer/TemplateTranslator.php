<?php
/**
 * Converts source title variables into RankMySEO tokens.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Maps %%title%%, %title%, and #post_title style variables onto one token set.
 */
final class TemplateTranslator {
	/**
	 * Yoast %%name%% variables that have a RankMySEO token.
	 *
	 * @var array<string, string>
	 */
	private const YOAST = [
		'title'            => 'title',
		'sitename'         => 'sitename',
		'sitedesc'         => 'tagline',
		'sep'              => 'sep',
		'excerpt'          => 'excerpt',
		'excerpt_only'     => 'excerpt',
		'category'         => 'category',
		'primary_category' => 'category',
		'page'             => 'page',
		'pagenumber'       => 'page',
		'currentyear'      => 'currentyear',
		'currentmonth'     => 'currentmonth',
		'name'             => 'author',
		'author'           => 'author',
		'term_title'       => 'term_title',
		'term_description' => 'term_description',
		'searchphrase'     => 'searchphrase',
		'date'             => 'date',
		'pt_plural'        => 'pt_plural',
	];

	/**
	 * Hash-style variables used by All in One SEO templates.
	 *
	 * @var array<string, string>
	 */
	private const HASH = [
		'post_title'           => 'title',
		'site_title'           => 'sitename',
		'blog_title'           => 'sitename',
		'tagline'              => 'tagline',
		'separator'            => 'sep',
		'separator_sa'         => 'sep',
		'post_excerpt'         => 'excerpt',
		'post_content'         => 'excerpt',
		'author_name'          => 'author',
		'author_first_name'    => 'author',
		'taxonomy_title'       => 'term_title',
		'taxonomy_description' => 'term_description',
		'categories'           => 'category',
		'current_year'         => 'currentyear',
		'current_month'        => 'currentmonth',
		'page_number'          => 'page',
		'search_term'          => 'searchphrase',
		'post_date'            => 'date',
		'archive_date'         => 'date',
		'post_type_plural'     => 'pt_plural',
	];

	/**
	 * Percent variables that differ in name from the RankMySEO catalog.
	 *
	 * @var array<string, string>
	 */
	private const ALIASES = [
		'sitedesc'     => 'tagline',
		'term'         => 'term_title',
		'search_query' => 'searchphrase',
		'categories'   => 'category',
		'name'         => 'author',
		'post_title'   => 'title',
		'site_title'   => 'sitename',
		'excerpt_only' => 'excerpt',
	];

	/**
	 * Translate a template and list variables we could not map.
	 *
	 * @param string $value Raw template or plain text.
	 * @return array{text: string, unsupported: array<int, string>}
	 */
	public function translate( string $value ): array {
		$unsupported = [];
		$text        = $value;

		$text = $this->replace(
			'/%%([a-z0-9_]+)%%/i',
			$text,
			self::YOAST,
			static function ( string $name ): string {
				return '%%' . $name . '%%';
			},
			$unsupported
		);

		$text = $this->replace(
			'/#([a-z][a-z0-9_]*)/i',
			$text,
			self::HASH,
			static function ( string $name ): string {
				return '#' . $name;
			},
			$unsupported
		);

		$known = array_fill_keys( MetaCatalog::TOKENS, true );
		$text  = $this->replace(
			'/%([a-z0-9_]+)%/i',
			$text,
			self::ALIASES,
			static function ( string $name ) use ( $known ): string {
				return isset( $known[ $name ] ) ? '%' . $name . '%' : '';
			},
			$unsupported,
			true
		);

		if ( preg_match_all( '/%[a-z0-9_]+\([^)]*\)%/i', $text, $custom ) ) {
			foreach ( $custom[0] as $token ) {
				$unsupported[] = (string) $token;
			}
		}

		return [
			'text'        => $text,
			'unsupported' => array_values( array_unique( $unsupported ) ),
		];
	}

	/**
	 * Replace one variable syntax.
	 *
	 * @param string                $pattern     Regular expression.
	 * @param string                $text        Current template.
	 * @param array<string, string> $map         Known names to RankMySEO tokens.
	 * @param callable              $original    Builds the original token for the unsupported list.
	 * @param array<int, string>    $unsupported Unsupported tokens collected by reference.
	 * @param bool                  $keep_known  When true, catalog tokens are preserved.
	 * @return string
	 */
	private function replace( string $pattern, string $text, array $map, callable $original, array &$unsupported, bool $keep_known = false ): string {
		$replaced = preg_replace_callback(
			$pattern,
			static function ( array $matches ) use ( $map, $original, &$unsupported, $keep_known ): string {
				$name = strtolower( (string) $matches[1] );
				if ( isset( $map[ $name ] ) ) {
					return '%' . $map[ $name ] . '%';
				}

				if ( $keep_known && in_array( $name, MetaCatalog::TOKENS, true ) ) {
					return '%' . $name . '%';
				}

				$token = (string) $original( $name );
				if ( '' === $token ) {
					$token = (string) $matches[0];
				}
				$unsupported[] = '' !== $token ? $token : (string) $matches[0];
				return (string) $matches[0];
			},
			$text
		);

		return is_string( $replaced ) ? $replaced : $text;
	}
}
