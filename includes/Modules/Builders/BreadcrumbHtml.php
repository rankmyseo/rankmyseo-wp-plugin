<?php
/**
 * Accessible breadcrumb markup shared by builder widgets.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Builders;

/**
 * Renders a breadcrumb trail as a navigation list.
 */
final class BreadcrumbHtml {
	/**
	 * Scoped presentation for the trail. Printed with the markup so the public
	 * site does not load a RankMySEO stylesheet.
	 */
	private const STYLE = '.rankmyseo-breadcrumbs{font-size:14px;line-height:1.5}.rankmyseo-breadcrumbs ol{display:flex;flex-wrap:wrap;gap:.35em .5em;list-style:none;margin:0;padding:0}.rankmyseo-breadcrumbs li{display:inline}.rankmyseo-breadcrumbs li+li::before{content:"/";margin-right:.5em;color:#5c6370}.rankmyseo-breadcrumbs a{color:#1b1e24}.rankmyseo-breadcrumbs a:focus{outline:2px solid #ff5c22;outline-offset:2px}';

	/**
	 * Render escaped breadcrumb HTML.
	 *
	 * The last item is the current page and is not a link.
	 *
	 * @param list<array{name?: string, url?: string}> $crumbs Trail items.
	 * @return string
	 */
	public static function render( array $crumbs ): string {
		if ( [] === $crumbs ) {
			return '';
		}

		$last  = count( $crumbs ) - 1;
		$items = '';

		foreach ( $crumbs as $index => $crumb ) {
			$name = isset( $crumb['name'] ) ? (string) $crumb['name'] : '';
			$url  = isset( $crumb['url'] ) ? (string) $crumb['url'] : '';
			$safe = esc_html( $name );

			if ( $index === $last ) {
				$items .= '<li class="rankmyseo-breadcrumb-current" aria-current="page">' . $safe . '</li>';
				continue;
			}

			$href = esc_url( $url );
			if ( '' === $href ) {
				$items .= '<li>' . $safe . '</li>';
				continue;
			}

			$items .= '<li><a href="' . $href . '">' . $safe . '</a></li>';
		}

		return '<style>' . self::STYLE . '</style><nav class="rankmyseo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'rankmyseo' ) . '"><ol>' . $items . '</ol></nav>';
	}
}
