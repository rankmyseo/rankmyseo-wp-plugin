=== RankMySEO ===
Contributors: rankmyseo
Tags: seo, schema, xml sitemap, redirects, on-page seo
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free local WordPress SEO for titles, links, schema, sitemaps, redirects, and shops. RankMySEO Cloud is optional.

== Description ==

RankMySEO helps WordPress site owners, bloggers, agencies, and WooCommerce stores improve a site inside WordPress. Every core feature runs on the site. No account is required, and the plugin does not load Google Fonts.

The public front end prints titles, meta tags, canonical links, and JSON-LD. It does not load extra JavaScript. A small stylesheet is added only on pages that show the breadcrumb or FAQ block. A breadcrumbs widget prints its own short style block only on pages where that widget is placed. Elementor and Divi load a RankMySEO panel only inside their visual editors.

= Designed for beginners and pros =

* **Simple (Guided) mode**: Plain-English guidance, a 3-step setup wizard, and an Ask AI drawer that inspects this site offline. This is the default for a new install.
* **Advanced (Pro) mode**: One click, per user, shows module controls, diagnostics, and technical settings. Switching modes does not delete anything. Both modes read the same settings.

= What works with no account =

* SEO titles, meta descriptions, canonical URLs, robots directives, and Open Graph / social previews in the block editor, the classic editor, quick edit, Elementor, and Divi. Each editor shows a live search preview and a live social preview. The Elementor and Divi panels load only while those editors are open.
* One main keyword plus up to five secondary keywords, a cornerstone flag for the pages that matter most, and a local on-page SEO score from 0 to 100. The score warns when another page targets the same keyword.
* Local internal-link suggestions in the editor, and an orphan-page list on the home screen. Both are built from pages already published on this site.
* A Missing details screen for published posts, pages, products, and images that lack a title, description, main keyword, or image description. Empty titles and descriptions can be filled from local templates after a preview. WordPress Bulk Edit can apply those templates or clear a custom title and description.
* Schema.org JSON-LD in one `@graph`, including site identity, articles, breadcrumbs, FAQ content, and WooCommerce products (variation offers, return policy, and shipping details when WooCommerce is active). The block editor Schema tab can set the type for that page, including a how-to, or turn structured data off for that page.
* WooCommerce store tools, used only while WooCommerce is active: brand, GTIN/EAN/UPC/ISBN, and MPN on the product, GTIN and MPN on each variation, merchant shipping and returns details, product share tags, shorter product and category addresses, filtered shop links pointed at the main shop page, and sold-out products left out of search. Catalog address changes stay off until an administrator opts in.
* A visible breadcrumb block, shortcode, template tag, and Elementor breadcrumbs widget. They use the same trail as the breadcrumb structured data.
* A visible FAQ block whose questions and answers are included in the page structured data.
* XML sitemaps, a robots.txt editor, an optional `/llms.txt` guide for AI search assistants, and optional IndexNow pings. The llms.txt guide and IndexNow stay off until an administrator turns them on.
* Redirects (301, 302, 307, 410, and 451), slug-change protection, and a 404 log with one-click redirect creation.
* A non-destructive importer for Yoast SEO, Rank Math, and All in One SEO, with a dry run and rollback. The other plugin's data is not deleted.
* An offline Ask AI assistant that reads local SEO data and does not contact any server.
* Custom tables for redirects, 404 logs, internal links, suggested actions, rollback snapshots, the audit log, import backups, and the Cloud sync queue.
* Capabilities `rankmyseo_manage` and `rankmyseo_approve_ai`.

= Optional RankMySEO Cloud =

When an administrator connects the site to [RankMySEO Cloud](https://rankmyseo.com), the plugin can show tracked keyword rankings and a page-update verdict, and it can draft titles, descriptions, image descriptions, and other edits. Those drafts are applied only after a person approves them. Automatic apply stays off until an administrator turns it on and sets limits. Connecting is optional. Nothing is sent while the site is disconnected. See External services below.

The Settings screen includes an optional diagnostics switch. It is off by default and the choice is stored in WordPress. Version 1.0.0 does not send a diagnostics report when that switch is on.

== Installation ==

1. Download `rankmyseo.zip` from the GitHub Releases page: [https://github.com/rankmyseo/rankmyseo-wp-plugin/releases](https://github.com/rankmyseo/rankmyseo-wp-plugin/releases).
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Choose `rankmyseo.zip`, install it, and activate RankMySEO.
4. Open **RankMySEO** in WordPress admin and follow the setup wizard:
   * Step 1: site name, whether the site is a business or a person, the title separator, and an optional logo.
   * Step 2: turn on search titles, structured data, the XML sitemap, and redirects. These run on the site.
   * Step 3: connect RankMySEO Cloud, or choose **Skip for now — keep using locally**.
   * **Skip to Advanced Setup** leaves the wizard and opens the full controls. It does not delete anything.

The plugin is ready to use after activation. Composer and Node are not required on the site.

Developers can rebuild the admin and editor assets from the public source at [https://github.com/rankmyseo/rankmyseo-wp-plugin](https://github.com/rankmyseo/rankmyseo-wp-plugin):

1. `npm ci && npm run build`
2. `composer install --no-dev --optimize-autoloader`
3. `bash scripts/package-plugin.sh`

== Frequently Asked Questions ==

= Do I need a RankMySEO account? =

No. Titles, meta, scores, internal links, schema, sitemaps, redirects, the importer, and the offline assistant all run inside WordPress. RankMySEO Cloud is optional.

= Does RankMySEO phone home? =

No. Activation, deactivation, and normal page loads do not contact RankMySEO or IndexNow. RankMySEO Cloud is contacted only after an administrator connects the site and then syncs, asks a connected question, drafts a suggestion, or reviews suggestions. IndexNow is contacted only after an administrator turns that setting on. The optional `/llms.txt` guide is generated on the site and is not fetched from anywhere else.

= Is the diagnostics switch a phone home? =

No. The switch is off until an administrator turns it on, and the choice is stored in WordPress. Version 1.0.0 does not send a diagnostics report.

= Does RankMySEO load Google Fonts? =

No.

= Can I switch between Simple and Advanced mode? =

Yes. The choice is stored per user. Both modes read the same settings.

= Can I score more than one keyword? =

Yes. Each post, page, or product has one main keyword and up to five secondary keywords. The score also warns when another page on this site uses the same main keyword.

= What is a cornerstone page? =

A page you mark as one of the most important on the site. RankMySEO expects more internal links to it and prefers it when suggesting a link.

= What is an orphan page? =

A published page that no other page on this site links to. The home screen lists them after the local link index has included that page. The index is built on the site.

= Can RankMySEO fill in missing titles? =

Yes. Missing details lists published posts, pages, products, and images that lack a search title, description, main keyword, or image description. You can preview a fill from your local templates and then apply it. WordPress Bulk Edit can apply the same title and description templates. Connected sites can also ask RankMySEO for a draft, which waits in the approval queue.

= Does this work with WooCommerce? =

Yes, when WooCommerce is active. You can store a brand, a GTIN/EAN/UPC/ISBN, and an MPN on the product, plus a GTIN and MPN on each variation. Structured data can include one offer per variation, a return policy, and shipping details. Catalog address changes and the sold-out search control stay off until you turn them on.

= Does this work with Elementor or Divi? =

Yes. Each editor gets a RankMySEO panel, loaded only while that editor is open. Elementor also has a breadcrumbs widget that uses the same trail as the structured data. The block editor has Breadcrumbs and FAQ blocks, and a Schema tab for the structured data on that page.

= What is the llms.txt page? =

An optional page at `/llms.txt` that lists important public pages in plain language for AI search assistants. It is off until you enable it, and it is built on this site. If a physical `llms.txt` file is already in the site root, the web server serves that file instead.

= What is removed if I delete the plugin? =

By default, your SEO data stays in the database so you can reinstall without losing it. In Settings, turn off "Keep SEO data if plugin is uninstalled" before deleting the plugin if you want RankMySEO to remove its tables, options, transients, scheduled tasks, and saved SEO fields. That sets `delete_data_on_uninstall`.

= Will this replace another SEO plugin immediately? =

No. If another SEO plugin is active, RankMySEO can pause its own head output and import from Yoast SEO, Rank Math, or All in One SEO after a dry run. The importer does not delete the other plugin's data, and the copy can be rolled back.

= Will AI change my pages by itself? =

No. Suggestions stay in the approval queue until a person applies them, and an applied change can be undone. Automatic apply is off until an administrator enables it and sets which suggestion types, post types, and addresses it may touch.

= Where is the source for the compiled JavaScript? =

The TypeScript and React source is in the GitHub repository linked above. The WordPress.org package includes the compiled files in `build/`.

== Screenshots ==

1. Simple mode home with the next best actions for this site, including orphan pages.
2. Three-step setup wizard, with a way to skip into Advanced mode.
3. Titles and meta templates, including the search and social preview.
4. Block editor sidebar with the SEO score, main and secondary keywords, cornerstone flag, snippet preview, and social preview.
5. Internal-link suggestions inside the editor.
6. Missing details screen, with a local template fill and the optional approval queue.
7. Schema settings for the site identity and the JSON-LD preview.
8. Store settings for WooCommerce brand, identifiers, shipping, returns, and catalog addresses.
9. Sitemaps, robots.txt, the optional llms.txt guide, and the optional IndexNow switch.
10. Redirect rules and the 404 log.
11. Importer dry run for Yoast SEO, Rank Math, or All in One SEO, with rollback.
12. Offline Ask AI drawer.
13. Optional RankMySEO Cloud connection screen.
14. Approval queue with a before-and-after preview and one-click undo.
15. Elementor or Divi SEO panel, shown only inside that editor.
16. Breadcrumbs and FAQ blocks, using the same content as the structured data.

== External services ==

This plugin works entirely on your site until an administrator turns on an optional service. No service is contacted on activation, deactivation, or a normal front-end page view. While the site is disconnected from RankMySEO Cloud and IndexNow is off, the plugin makes no outbound HTTP requests.

The optional diagnostics switch in Settings is stored in WordPress. Version 1.0.0 does not send it anywhere. The `/llms.txt` guide, XML sitemaps, link index, and on-page score are also built on the site.

= RankMySEO Cloud (optional) =

* **Service**: [RankMySEO](https://rankmyseo.com), API host `https://api.rankmyseo.com`.
* **When it is used**: Only after an administrator clicks Connect and approves the pairing code, or saves a connection another way from the RankMySEO Cloud screen. Inventory sync, insights, suggested actions, chat, token rotation, policy save, and disconnect run only after that. Chat starts when a person sends a message. Drafting missing titles, descriptions, or image descriptions calls `actions/generate` only after Connect and an explicit draft click. Saving automatic-apply rules calls `PUT /wp/v1/policy` only after Connect. The block editor reads the saved insights cache and does not call RankMySEO on its own.
* **Endpoints used after connection**: `https://api.rankmyseo.com/wp/v1/pair/start`, `https://api.rankmyseo.com/wp/v1/pair/poll`, `https://api.rankmyseo.com/wp/v1/connection`, `https://api.rankmyseo.com/wp/v1/disconnect`, `https://api.rankmyseo.com/wp/v1/rotate-token`, `https://api.rankmyseo.com/wp/v1/sync/inventory`, `https://api.rankmyseo.com/wp/v1/sync/events`, `https://api.rankmyseo.com/wp/v1/insights`, `https://api.rankmyseo.com/wp/v1/policy` (GET when reading the rules, PUT when an administrator saves them), `https://api.rankmyseo.com/wp/v1/actions`, `https://api.rankmyseo.com/wp/v1/actions/generate`, `https://api.rankmyseo.com/wp/v1/actions/{id}/ack`, `https://api.rankmyseo.com/wp/v1/chat/message`, `https://api.rankmyseo.com/wp/v1/chat/progress`, and `https://api.rankmyseo.com/wp/v1/chat/thread`. The approval page opened in the browser is the address RankMySEO returns with the pairing code. The plugin's own requests go only to `https://api.rankmyseo.com`.
* **Data sent**: During pairing, the site URL, site name, and WordPress and plugin versions. During sync, public URLs, titles, meta descriptions, focus keywords, on-page scores, and a content hash of the modified time, title, and description. The post body is not sent. A chat message can include the question plus the current page URL, title, meta description, focus keyword, and score. A missing-details draft sends the page id, public URL, content type, title, and which of title, description, or image description is empty. It does not send the page body. Saving automatic-apply rules sends those rules: whether it is enabled, which suggestion types and post types are allowed, excluded addresses, and the daily limit. Approving, skipping, or undoing a suggestion sends that outcome back. The site token stays encrypted in WordPress and is not shown in the admin screen. The browser never calls `api.rankmyseo.com` and never sees the token.
* **Terms of Service**: [https://rankmyseo.com/terms](https://rankmyseo.com/terms)
* **Privacy Policy**: [https://rankmyseo.com/privacy](https://rankmyseo.com/privacy)

= IndexNow (optional) =

* **Service**: [IndexNow](https://www.indexnow.org/), endpoint `https://api.indexnow.org/indexnow`.
* **When it is used**: Only after an administrator enables IndexNow. The plugin then submits URLs when public, indexable content is published, updated, or removed. Turning IndexNow off clears the pending queue and does not submit anything.
* **Data sent**: The site host, the IndexNow key served from this site, and the list of changed public URLs. No post content, account data, or visitor data is sent.
* **Terms and privacy**: [https://www.indexnow.org/terms](https://www.indexnow.org/terms). That page includes the IndexNow terms and the privacy statement. IndexNow does not publish a separate privacy URL.

== Changelog ==

= 1.0.0 =
* First public release.
* Simple and Advanced modes, a 3-step setup wizard, and an offline Ask AI assistant. No outbound requests until RankMySEO Cloud is connected or IndexNow is enabled. No Google Fonts.
* Titles, meta descriptions, canonicals, robots, and social previews, including the block editor, classic editor, quick edit, Elementor, and Divi, with live search and social previews.
* On-page SEO score for one main keyword and up to five secondary keywords, a cornerstone flag, and a same-keyword warning.
* Local internal-link suggestions and an orphan-page list.
* Missing details screen: filter published posts, pages, products, and images, fill empty fields from local templates, or queue RankMySEO suggestions for approval. Image descriptions can be queued and undone.
* Bulk edit can apply title and description templates or clear custom search details, alongside the existing search-visibility control.
* Elementor breadcrumbs widget, a visible breadcrumb block, shortcode, and template tag, and a visible FAQ block. The visible content and the JSON-LD stay in sync.
* Schema.org JSON-LD `@graph`, including site identity, articles, breadcrumbs, FAQ, and WooCommerce products. The block editor Schema tab can set the type for one page.
* WooCommerce store tools: brand, GTIN/EAN/UPC/ISBN, MPN on the product and on each variation, variation offers, return and shipping details, product share tags, and optional catalog URL rules. Nothing is sent off the site.
* XML sitemaps, robots.txt controls, an optional llms.txt guide, and optional IndexNow.
* Redirects, slug-change protection, and a 404 log.
* Importer for Yoast SEO, Rank Math, and All in One SEO, with dry run and rollback.
* Optional RankMySEO Cloud connection, with encrypted tokens, inventory sync, an approval queue, rollback snapshots, connected chat cards, and editor insights read from the saved cache.
* Block editor shows a tracked keyword's position and a page-update verdict from the saved insights cache when the site is connected.
* Data is kept on uninstall unless the administrator enables deletion.

== Upgrade Notice ==

= 1.0.0 =
First public release of RankMySEO. SEO data stays in WordPress unless you turn off "Keep SEO data if plugin is uninstalled" before deleting the plugin.
