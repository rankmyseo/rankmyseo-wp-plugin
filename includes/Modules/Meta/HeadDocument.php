<?php
/**
 * Escaped head tag document for description, canonical, OpenGraph, and Twitter.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Builds a single, de-duplicated block of head tags.
 */
final class HeadDocument {
	/**
	 * Meta description.
	 *
	 * @var string
	 */
	public string $description = '';

	/**
	 * Canonical URL.
	 *
	 * @var string
	 */
	public string $canonical = '';

	/**
	 * OpenGraph locale.
	 *
	 * @var string
	 */
	public string $og_locale = 'en_US';

	/**
	 * OpenGraph type.
	 *
	 * @var string
	 */
	public string $og_type = 'website';

	/**
	 * OpenGraph title.
	 *
	 * @var string
	 */
	public string $og_title = '';

	/**
	 * OpenGraph description.
	 *
	 * @var string
	 */
	public string $og_description = '';

	/**
	 * OpenGraph URL.
	 *
	 * @var string
	 */
	public string $og_url = '';

	/**
	 * OpenGraph site name.
	 *
	 * @var string
	 */
	public string $og_site_name = '';

	/**
	 * OpenGraph image URL.
	 *
	 * @var string
	 */
	public string $og_image = '';

	/**
	 * OpenGraph image width.
	 *
	 * @var int
	 */
	public int $og_image_width = 0;

	/**
	 * OpenGraph image height.
	 *
	 * @var int
	 */
	public int $og_image_height = 0;

	/**
	 * Article published time.
	 *
	 * @var string
	 */
	public string $published_time = '';

	/**
	 * Article modified time.
	 *
	 * @var string
	 */
	public string $modified_time = '';

	/**
	 * Twitter card type.
	 *
	 * @var string
	 */
	public string $twitter_card = 'summary_large_image';

	/**
	 * Twitter title.
	 *
	 * @var string
	 */
	public string $twitter_title = '';

	/**
	 * Twitter description.
	 *
	 * @var string
	 */
	public string $twitter_description = '';

	/**
	 * Twitter image URL.
	 *
	 * @var string
	 */
	public string $twitter_image = '';

	/**
	 * Render the head block. Each tag is emitted at most once.
	 *
	 * @return string
	 */
	public function render(): string {
		$tags = $this->tags();
		// Slash-separated name is the public extension point named in the architecture plan.
		$filtered = apply_filters( 'rankmyseo/head/tags', $tags ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		if ( ! is_array( $filtered ) ) {
			$filtered = $tags;
		}

		$lines = [];
		$seen  = [];

		foreach ( $filtered as $tag ) {
			if ( ! is_array( $tag ) ) {
				continue;
			}

			$rendered = $this->render_tag( $tag );
			if ( '' === $rendered ) {
				continue;
			}

			$key = $this->identity( $tag );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$lines[]      = $rendered;
		}

		if ( [] === $lines ) {
			return '';
		}

		return "<!-- RankMySEO Meta -->\n" . implode( "\n", $lines ) . "\n<!-- /RankMySEO Meta -->\n";
	}

	/**
	 * Build the default tag list before the head filter runs.
	 *
	 * @return list<array{tag: string, escape: string, attrs: array<string, string>}>
	 */
	public function tags(): array {
		$tags = [];

		if ( '' !== $this->description ) {
			$tags[] = $this->meta_name( 'description', $this->description );
		}

		if ( '' !== $this->canonical ) {
			$tags[] = [
				'tag'    => 'link',
				'escape' => 'url',
				'attrs'  => [
					'rel'  => 'canonical',
					'href' => $this->canonical,
				],
			];
		}

		$tags[] = $this->meta_property( 'og:locale', $this->og_locale );
		$tags[] = $this->meta_property( 'og:type', $this->og_type );
		$tags[] = $this->meta_property( 'og:title', $this->og_title );
		$tags[] = $this->meta_property( 'og:description', $this->og_description );
		$tags[] = $this->meta_property( 'og:url', $this->og_url, true );
		$tags[] = $this->meta_property( 'og:site_name', $this->og_site_name );

		if ( '' !== $this->og_image ) {
			$tags[] = $this->meta_property( 'og:image', $this->og_image, true );
			if ( $this->og_image_width > 0 ) {
				$tags[] = $this->meta_property( 'og:image:width', (string) $this->og_image_width );
			}
			if ( $this->og_image_height > 0 ) {
				$tags[] = $this->meta_property( 'og:image:height', (string) $this->og_image_height );
			}
		}

		if ( 'article' === $this->og_type ) {
			if ( '' !== $this->published_time ) {
				$tags[] = $this->meta_property( 'article:published_time', $this->published_time );
			}
			if ( '' !== $this->modified_time ) {
				$tags[] = $this->meta_property( 'article:modified_time', $this->modified_time );
			}
		}

		$tags[] = $this->meta_name( 'twitter:card', $this->twitter_card );
		$tags[] = $this->meta_name( 'twitter:title', $this->twitter_title );
		$tags[] = $this->meta_name( 'twitter:description', $this->twitter_description );

		if ( '' !== $this->twitter_image ) {
			$tags[] = $this->meta_name( 'twitter:image', $this->twitter_image, true );
		}

		return $tags;
	}

	/**
	 * Render tag.
	 *
	 * @param array<string, mixed> $tag Tag definition.
	 * @return string
	 */
	private function render_tag( array $tag ): string {
		$name = isset( $tag['tag'] ) ? (string) $tag['tag'] : '';
		if ( ! in_array( $name, [ 'meta', 'link' ], true ) ) {
			return '';
		}

		$attrs = isset( $tag['attrs'] ) && is_array( $tag['attrs'] ) ? $tag['attrs'] : [];
		$url   = isset( $tag['escape'] ) && 'url' === $tag['escape'];
		$parts = [];

		foreach ( $attrs as $key => $value ) {
			$attr = strtolower( (string) $key );
			if ( ! in_array( $attr, [ 'name', 'content', 'property', 'rel', 'href' ], true ) ) {
				continue;
			}

			$text = trim( (string) $value );
			if ( '' === $text ) {
				continue;
			}

			$escaped = ( $url && in_array( $attr, [ 'href', 'content' ], true ) ) ? esc_url( $text ) : esc_attr( $text );
			if ( '' === $escaped ) {
				continue;
			}

			$parts[] = $attr . '="' . $escaped . '"';
		}

		if ( [] === $parts ) {
			return '';
		}

		return '<' . $name . ' ' . implode( ' ', $parts ) . ' />';
	}

	/**
	 * Identity used to drop duplicate tags after filters run.
	 *
	 * @param array<string, mixed> $tag Tag definition.
	 * @return string
	 */
	private function identity( array $tag ): string {
		$attrs = isset( $tag['attrs'] ) && is_array( $tag['attrs'] ) ? $tag['attrs'] : [];
		$name  = isset( $tag['tag'] ) ? (string) $tag['tag'] : '';
		$key   = isset( $attrs['name'] ) ? (string) $attrs['name'] : '';
		if ( '' === $key && isset( $attrs['property'] ) ) {
			$key = (string) $attrs['property'];
		}
		if ( '' === $key && isset( $attrs['rel'] ) ) {
			$key = (string) $attrs['rel'];
		}

		return $name . ':' . strtolower( $key );
	}

	/**
	 * Build a name/content meta tag definition.
	 *
	 * @param string $name    Meta name.
	 * @param string $content Meta content.
	 * @param bool   $url     Whether the content is a URL.
	 * @return array{tag: string, escape: string, attrs: array<string, string>}
	 */
	private function meta_name( string $name, string $content, bool $url = false ): array {
		return [
			'tag'    => 'meta',
			'escape' => $url ? 'url' : 'text',
			'attrs'  => [
				'name'    => $name,
				'content' => $content,
			],
		];
	}

	/**
	 * Build an OpenGraph property meta tag definition.
	 *
	 * @param string $property OpenGraph property.
	 * @param string $content  Property content.
	 * @param bool   $url      Whether the content is a URL.
	 * @return array{tag: string, escape: string, attrs: array<string, string>}
	 */
	private function meta_property( string $property, string $content, bool $url = false ): array {
		return [
			'tag'    => 'meta',
			'escape' => $url ? 'url' : 'text',
			'attrs'  => [
				'property' => $property,
				'content'  => $content,
			],
		];
	}
}
