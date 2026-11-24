=== RankMySEO ===
Contributors: rankmyseo
Tags: seo, schema, xml sitemap, redirects, on-page seo
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Standalone WordPress SEO for titles, schema, sitemaps, redirects, and an offline assistant. RankMySEO Cloud is optional.

== Description ==

RankMySEO helps WordPress beginners and experienced SEO professionals optimize a site inside WordPress. Every core on-page feature runs locally. No account is required.

= Designed for beginners and pros =

* **Simple (Guided) mode**: Plain-English guidance, a 3-step setup wizard, and an Ask AI drawer that inspects this site offline.
* **Advanced (Pro) mode**: One click, per user, shows module controls, diagnostics, and technical settings. Switching modes does not delete anything.

= What works with no account =

* SEO titles, meta descriptions, canonical URLs, robots directives, and Open Graph / social previews in the block editor, the classic editor, quick edit, Elementor, and Divi. The Elementor and Divi panels load only while those editors are open.
* A local on-page SEO score from 0 to 100, including a warning when another page targets the same keyword.
* Schema.org JSON-LD in one `@graph`, including site identity, articles, breadcrumbs, and WooCommerce products (variation offers, return policy, and shipping details when WooCommerce is active). Elementor can also place a visible RankMySEO breadcrumbs widget.
* Optional catalog URL controls for WooCommerce: cleaner shop links, shorter product and category addresses, and sold-out products left out of search.
* XML sitemaps, a robots.txt editor, an optional llms.txt guide, and optional IndexNow pings.
* A visible breadcrumb block, shortcode, and template tag that use the same trail as the breadcrumb structured data and the Elementor breadcrumbs widget.
* A visible FAQ block whose questions and answers are included in the page structured data.
* Redirects (301, 302, 307, 410, and 451), slug-change protection, and a 404 log.
* A non-destructive importer for Yoast SEO, Rank Math, and All in One SEO, with a dry run and rollback.
* An offline Ask AI assistant that reads local SEO data and does not contact any server.
* Custom tables for redirects, 404 logs, suggested actions, rollback snapshots, the audit log, and import backups.
* Capabilities `rankmyseo_manage` and `rankmyseo_approve_ai`.

= Optional RankMySEO Cloud =

When an administrator connects the site to [RankMySEO Cloud](https://rankmyseo.com), the plugin can show tracked keyword rankings and suggested page updates, and it can apply those suggestions only after a person approves them. Connecting is optional. Nothing is sent while the site is disconnected. See External services below.

The public front end prints titles, meta tags, canonical links, and JSON-LD. It does not load extra JavaScript. A small stylesheet is added only on pages that show the breadcrumb or FAQ block. A breadcrumbs widget prints its own short style block only on pages where that widget is placed. Elementor and Divi load a RankMySEO panel only inside their visual editors.

== Installation ==

1. Upload the `rankmyseo` folder to `/wp-content/plugins/`, or install the zip from the Plugins screen.
2. Activate RankMySEO through the Plugins screen.
3. Open **RankMySEO** in WordPress admin. Follow the setup wizard, or switch to Advanced mode.

The plugin is ready to use after activation. Composer and Node are not required on the site.

Developers can rebuild the admin and editor assets from the public source at [https://github.com/rankmyseo/rankmyseo-wp-plugin](https://github.com/rankmyseo/rankmyseo-wp-plugin):

1. `npm ci && npm run build`
2. `composer install --no-dev --optimize-autoloader`

== Frequently Asked Questions ==

= Do I need a RankMySEO account? =

No. Titles, meta, schema, sitemaps, redirects, the importer, and the offline assistant all run inside WordPress. RankMySEO Cloud is optional.

= Does RankMySEO phone home? =

No. Activation, deactivation, and normal page loads do not contact RankMySEO or IndexNow. RankMySEO Cloud is contacted only after an administrator connects the site and then syncs, asks a connected question, or reviews suggestions. IndexNow is contacted only after an administrator turns that setting on.

= Is telemetry on by default? =

No. The optional diagnostic toggle is off until an administrator turns it on.

= Can I switch between Simple and Advanced mode? =

Yes. The choice is stored per user. Both modes read the same settings.

= What is removed if I delete the plugin? =

By default, your SEO data stays in the database so you can reinstall without losing it. In Settings, turn off "Keep SEO data if plugin is uninstalled" before deleting the plugin if you want RankMySEO to remove its tables, options, transients, scheduled tasks, and saved SEO fields. That sets `delete_data_on_uninstall`.

= Will this replace another SEO plugin immediately? =

No. If another SEO plugin is active, RankMySEO can pause its own head output and import from Yoast SEO, Rank Math, or All in One SEO after a dry run. The importer does not delete the other plugin's data.

= Where is the source for the compiled JavaScript? =

The TypeScript and React source is in the GitHub repository linked above. The WordPress.org package includes the compiled files in `build/`.

== Screenshots ==

1. Simple mode home with the next best actions for this site.
2. Three-step setup wizard, with a way to skip into Advanced mode.
3. Titles and meta templates, including the search and social preview.
4. Block editor sidebar with the SEO score, snippet preview, and social preview.
5. Schema settings for the site identity and the JSON-LD preview.
6. Sitemaps, robots.txt, and the optional IndexNow switch.
7. Redirect rules and the 404 log.
8. Importer dry run for another SEO plugin, with rollback.
9. Offline Ask AI drawer.
10. Optional RankMySEO Cloud connection screen.
11. Approval queue with a before-and-after preview and one-click undo.

== External services ==

This plugin works entirely on your site until an administrator turns on an optional service. No service is contacted on activation, deactivation, or a normal front-end page view. While the site is disconnected from RankMySEO Cloud and IndexNow is off, the plugin makes no outbound HTTP requests.

= RankMySEO Cloud (optional) =

* **Service**: [RankMySEO](https://rankmyseo.com), API host `https://api.rankmyseo.com`.
* **When it is used**: Only after an administrator clicks Connect and approves the pairing code, or saves a connection another way from the RankMySEO Cloud screen. Inventory sync, insights, suggested actions, chat, token rotation, and disconnect run only after that. Chat starts when a person sends a message. Drafting missing titles, descriptions, or image descriptions calls `actions/generate` only after Connect and an explicit Draft click. The block editor reads the saved insights cache and does not call RankMySEO on its own.
* **Endpoints used after connection**: `https://api.rankmyseo.com/wp/v1/pair/start`, `https://api.rankmyseo.com/wp/v1/pair/poll`, `https://api.rankmyseo.com/wp/v1/connection`, `https://api.rankmyseo.com/wp/v1/disconnect`, `https://api.rankmyseo.com/wp/v1/rotate-token`, `https://api.rankmyseo.com/wp/v1/sync/inventory`, `https://api.rankmyseo.com/wp/v1/sync/events`, `https://api.rankmyseo.com/wp/v1/insights`, `https://api.rankmyseo.com/wp/v1/policy`, `https://api.rankmyseo.com/wp/v1/actions`, `https://api.rankmyseo.com/wp/v1/actions/generate`, `https://api.rankmyseo.com/wp/v1/actions/{id}/ack`, `https://api.rankmyseo.com/wp/v1/chat/message`, `https://api.rankmyseo.com/wp/v1/chat/progress`, and `https://api.rankmyseo.com/wp/v1/chat/thread`. The approval page opened in the browser is the address RankMySEO returns with the pairing code.
* **Data sent**: During pairing, the site URL, site name, and WordPress and plugin versions. During sync, public URLs, titles, meta descriptions, focus keywords, and on-page scores. A chat message can include the question plus the current page URL, title, meta description, focus keyword, and score. Approving, skipping, or undoing a suggestion sends that outcome back. The site token stays encrypted in WordPress and is not shown in the admin screen. The browser never calls `api.rankmyseo.com` and never sees the token.
* **Terms of Service**: [https://rankmyseo.com/terms](https://rankmyseo.com/terms)
* **Privacy Policy**: [https://rankmyseo.com/privacy](https://rankmyseo.com/privacy)

= IndexNow (optional) =

* **Service**: [IndexNow](https://www.indexnow.org/), endpoint `https://api.indexnow.org/indexnow`.
* **When it is used**: Only after an administrator enables IndexNow. The plugin then submits URLs when public, indexable content is published, updated, or removed. Turning IndexNow off clears the pending queue and does not submit anything.
* **Data sent**: The site host, the IndexNow key served from this site, and the list of changed public URLs. No post content, account data, or visitor data is sent.
* **Terms**: [https://www.indexnow.org/terms](https://www.indexnow.org/terms)
* **Privacy**: [https://www.indexnow.org/privacy](https://www.indexnow.org/privacy)

== Changelog ==

= 1.0.0 =
* First public release.
* Simple and Advanced modes, a 3-step setup wizard, and an offline Ask AI assistant.
* Titles, meta descriptions, canonicals, robots, and social previews, including the block editor, classic editor, quick edit, Elementor, and Divi.
* Elementor breadcrumbs widget, using the same trail as the schema breadcrumb list.
* On-page SEO score and a same-keyword warning.
* Schema.org JSON-LD `@graph`.
* WooCommerce store tools: product identifiers, variation offers, return and shipping details, catalog URL rules, and product share tags. Nothing is sent off the site.
* XML sitemaps, robots.txt controls, an optional llms.txt guide, and optional IndexNow.
* Visible breadcrumbs (block, shortcode, and template tag) and a visible FAQ block that fills FAQ structured data.
* Redirects, slug-change protection, and a 404 log.
* Importer for Yoast SEO, Rank Math, and All in One SEO, with dry run and rollback.
* Optional RankMySEO Cloud connection, with encrypted tokens, inventory sync, an approval queue, rollback snapshots, and connected chat cards.
* Missing details screen: filter published posts, pages, products, and images, fill empty fields from local templates, or queue RankMySEO suggestions for approval. Image descriptions can be queued and undone.
* Block editor shows a tracked keyword's Google position and a page-update verdict from the saved insights cache when the site is connected.
* Bulk edit can apply title and description templates or clear custom search details, alongside the existing search-visibility control.
* Data is kept on uninstall unless the administrator enables deletion.

== Upgrade Notice ==

= 1.0.0 =
First public release of RankMySEO. SEO data stays in WordPress unless you turn off "Keep SEO data if plugin is uninstalled" before deleting the plugin.
