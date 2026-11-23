<?php
/**
 * Escaped XML and XSL output for sitemaps.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Renders sitemap index, urlset, and stylesheet documents.
 */
final class XmlRenderer {
	/**
	 * Render a sitemap index document.
	 *
	 * @param list<array{loc: string, lastmod?: string}> $sitemaps Child sitemap entries.
	 * @param string                                     $xsl_url  Stylesheet URL.
	 * @return string
	 */
	public function render_index( array $sitemaps, string $xsl_url ): string {
		$body = '';
		foreach ( $sitemaps as $sitemap ) {
			$loc = $this->esc( $sitemap['loc'] );
			if ( '' === $loc ) {
				continue;
			}
			$body   .= "\t<sitemap>\n\t\t<loc>{$loc}</loc>\n";
			$lastmod = isset( $sitemap['lastmod'] ) ? $this->esc( $sitemap['lastmod'] ) : '';
			if ( '' !== $lastmod ) {
				$body .= "\t\t<lastmod>{$lastmod}</lastmod>\n";
			}
			$body .= "\t</sitemap>\n";
		}

		return $this->header( $xsl_url )
			. '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
			. $body
			. '</sitemapindex>' . "\n";
	}

	/**
	 * Render a urlset document.
	 *
	 * @param list<array{loc: string, lastmod?: string, images?: list<string>}> $urls    URL entries.
	 * @param string                                                            $xsl_url Stylesheet URL.
	 * @return string
	 */
	public function render_urlset( array $urls, string $xsl_url ): string {
		$has_images = false;
		foreach ( $urls as $url ) {
			if ( ! empty( $url['images'] ) ) {
				$has_images = true;
				break;
			}
		}

		$image_ns = $has_images ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '';
		$body     = '';

		foreach ( $urls as $url ) {
			$loc = $this->esc( $url['loc'] );
			if ( '' === $loc ) {
				continue;
			}
			$body   .= "\t<url>\n\t\t<loc>{$loc}</loc>\n";
			$lastmod = isset( $url['lastmod'] ) ? $this->esc( $url['lastmod'] ) : '';
			if ( '' !== $lastmod ) {
				$body .= "\t\t<lastmod>{$lastmod}</lastmod>\n";
			}
			foreach ( $url['images'] ?? [] as $image ) {
				$image_loc = $this->esc( $image );
				if ( '' === $image_loc ) {
					continue;
				}
				$body .= "\t\t<image:image>\n\t\t\t<image:loc>{$image_loc}</image:loc>\n\t\t</image:image>\n";
			}
			$body .= "\t</url>\n";
		}

		return $this->header( $xsl_url )
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . $image_ns . '>' . "\n"
			. $body
			. '</urlset>' . "\n";
	}

	/**
	 * Human-readable XSL stylesheet for sitemap XML.
	 *
	 * @return string
	 */
	public function render_xsl(): string {
		return <<<'XSL'
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" exclude-result-prefixes="s image">
<xsl:output method="html" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
<html>
<head>
<meta charset="utf-8"/>
<title>XML Sitemap</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:32px;color:#1B1E24;background:#F3F1EE}
h1{font-size:20px;margin:0 0 8px}
p{color:#5C6370;font-size:14px}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #E4E0DB;border-radius:8px}
th,td{text-align:left;padding:10px 12px;border-bottom:1px solid #E4E0DB;font-size:13px;vertical-align:top}
th{font-size:12px;letter-spacing:.02em;text-transform:uppercase;color:#5C6370}
a{color:#E64E17;word-break:break-all}
</style>
</head>
<body>
<h1>XML Sitemap</h1>
<p>This file lists public URLs for search engines. RankMySEO generated it on your site.</p>
<xsl:choose>
<xsl:when test="s:sitemapindex">
<table>
<thead><tr><th>Sitemap</th><th>Updated</th></tr></thead>
<tbody>
<xsl:for-each select="s:sitemapindex/s:sitemap">
<tr>
<td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
<td><xsl:value-of select="s:lastmod"/></td>
</tr>
</xsl:for-each>
</tbody>
</table>
</xsl:when>
<xsl:otherwise>
<table>
<thead><tr><th>URL</th><th>Updated</th><th>Images</th></tr></thead>
<tbody>
<xsl:for-each select="s:urlset/s:url">
<tr>
<td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
<td><xsl:value-of select="s:lastmod"/></td>
<td><xsl:value-of select="count(image:image)"/></td>
</tr>
</xsl:for-each>
</tbody>
</table>
</xsl:otherwise>
</xsl:choose>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
XSL;
	}

	/**
	 * XML declaration plus the stylesheet processing instruction.
	 *
	 * @param string $xsl_url Stylesheet URL.
	 * @return string
	 */
	private function header( string $xsl_url ): string {
		$href = $this->esc( $xsl_url );
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<?xml-stylesheet type="text/xsl" href="' . $href . '"?>' . "\n";
	}

	/**
	 * Escape a value for XML text and attribute contexts.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function esc( string $value ): string {
		$clean = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $value );
		if ( ! is_string( $clean ) ) {
			$clean = $value;
		}

		return htmlspecialchars( $clean, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}
}
