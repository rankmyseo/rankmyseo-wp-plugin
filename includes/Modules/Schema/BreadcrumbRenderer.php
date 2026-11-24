<?php
/**
 * Visible breadcrumb markup.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Renders an accessible breadcrumb nav from a shared trail.
 */
final class BreadcrumbRenderer {
	/**
	 * Default display options.
	 *
	 * @return array{separator: string, show_home: bool, show_current: bool}
	 */
	public static function defaults(): array {
		return [
			'separator'    => '/',
			'show_home'    => true,
			'show_current' => true,
		];
	}

	/**
	 * Semantic breadcrumb HTML.
	 *
	 * @param list<array{name: string, url: string}> $crumbs Trail from BreadcrumbTrail.
	 * @param array<string, mixed>                   $args   Separator, show_home, and show_current.
	 * @return string
	 */
	public function html( array $crumbs, array $args = [] ): string {
		$options = $this->options( $args );
		$visible = $this->visible_crumbs( $crumbs, $options['show_home'], $options['show_current'] );
		if ( [] === $visible ) {
			return '';
		}

		FrontendStyle::enqueue( 'rankmyseo-breadcrumbs', 'assets/css/breadcrumbs.css' );

		$last  = count( $visible ) - 1;
		$items = '';
		foreach ( $visible as $index => $crumb ) {
			$separator = 0 === $index ? '' : '<span class="rankmyseo-breadcrumbs__sep" aria-hidden="true">' . esc_html( $options['separator'] ) . '</span>';
			$current   = $index === $last;
			$label     = esc_html( $crumb['name'] );
			if ( $current || '' === $crumb['url'] ) {
				$body = '<span' . ( $current ? ' aria-current="page"' : '' ) . '>' . $label . '</span>';
			} else {
				$body = '<a href="' . esc_url( $crumb['url'] ) . '">' . $label . '</a>';
			}
			$items .= '<li class="rankmyseo-breadcrumbs__item">' . $separator . $body . '</li>';
		}

		return '<nav class="rankmyseo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'rankmyseo' ) . '"><ol>' . $items . '</ol></nav>';
	}

	/**
	 * Normalize display options.
	 *
	 * @param array<string, mixed> $args Raw args.
	 * @return array{separator: string, show_home: bool, show_current: bool}
	 */
	private function options( array $args ): array {
		$defaults  = self::defaults();
		$separator = isset( $args['separator'] ) ? trim( wp_strip_all_tags( (string) $args['separator'] ) ) : $defaults['separator'];
		if ( '' === $separator ) {
			$separator = $defaults['separator'];
		}
		if ( mb_strlen( $separator ) > 12 ) {
			$separator = mb_substr( $separator, 0, 12 );
		}

		return [
			'separator'    => $separator,
			'show_home'    => $this->flag( $args['show_home'] ?? $defaults['show_home'] ),
			'show_current' => $this->flag( $args['show_current'] ?? $defaults['show_current'] ),
		];
	}

	/**
	 * Apply show-home and show-current.
	 *
	 * @param list<array{name: string, url: string}> $crumbs       Full trail.
	 * @param bool                                   $show_home    Whether the first crumb stays.
	 * @param bool                                   $show_current Whether the last crumb stays.
	 * @return list<array{name: string, url: string}>
	 */
	private function visible_crumbs( array $crumbs, bool $show_home, bool $show_current ): array {
		$visible = array_values( $crumbs );
		if ( ! $show_home && isset( $visible[0] ) && $this->is_home_url( $visible[0]['url'] ) ) {
			array_shift( $visible );
		}
		if ( ! $show_current && count( $visible ) > 0 ) {
			array_pop( $visible );
		}

		return $visible;
	}

	/**
	 * Whether a URL is the site home.
	 *
	 * @param string $url Crumb URL.
	 * @return bool
	 */
	private function is_home_url( string $url ): bool {
		$home  = untrailingslashit( home_url( '/' ) );
		$crumb = untrailingslashit( $url );
		return '' !== $home && $home === $crumb;
	}

	/**
	 * Coerce a display flag.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function flag( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return ! in_array( strtolower( trim( $value ) ), [ '0', 'false', 'no', 'off', '' ], true );
		}

		return (bool) $value;
	}
}
