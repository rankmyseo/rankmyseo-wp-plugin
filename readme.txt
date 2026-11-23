=== RankMySEO ===
Contributors: rankmyseo
Tags: seo, schema, xml sitemap, redirects, on-page seo
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete standalone WordPress SEO plugin with Simple and Advanced modes, optional RankMySEO Cloud insights, and safe human-approved AI actions.

== Description ==

RankMySEO helps both WordPress beginners and experienced SEO professionals optimize their site directly inside WordPress. Every core on-page SEO feature runs locally on your site with zero external account required.

= Designed for Beginners and Pros =

* **Simple (Guided) Mode**: Plain-English guidance, prioritized next steps, a quick 3-step setup wizard, and an approachable Ask AI assistant drawer.
* **Advanced (Pro) Mode**: Switch in one click per user to unlock granular controls, module management, system diagnostics, and full technical configuration without losing any settings.

= Core Standalone SEO Architecture =

* Modular on-page SEO foundation covering titles and meta descriptions, real-time content analysis, Schema.org structured data, XML sitemaps and crawl controls, redirects and 404 monitoring, and non-destructive migration imports.
* Clean WordPress-native admin experience with zero frontend JavaScript or CSS bloat.
* Dedicated custom tables for redirects, 404 logs, AI actions, pre-change rollback snapshots, audit logs, sync queues, and import backups.
* Role-based capability controls (`rankmyseo_manage` and `rankmyseo_approve_ai`).

= Optional RankMySEO Cloud Connection =

When you choose to connect your site to RankMySEO Cloud, you can view tracked keyword rankings, Search Console data, and review proposed AI updates with side-by-side diffs and one-click rollback. Connecting is completely optional and never runs automatically.

== Installation ==

1. Upload the `rankmyseo` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open the **RankMySEO** menu in WordPress Admin to follow the guided setup or switch straight to Advanced Mode.

== Frequently Asked Questions ==

= Do I need a RankMySEO account to use this plugin? =

No. All standalone SEO modules run locally inside WordPress with zero external requests. Connecting to RankMySEO Cloud is strictly optional.

= Does RankMySEO send data or telemetry automatically? =

No. The plugin makes zero external calls on activation, deactivation, or page load. Anonymous diagnostic telemetry is off by default and requires explicit opt-in.

= Can I switch between Simple and Advanced modes without losing settings? =

Yes. Simple and Advanced modes are stored per user and read the exact same underlying settings. Switching modes never resets or deletes any configuration.

= Where is the unminified source code for compiled JavaScript and CSS? =

Full TypeScript and React source code is publicly available on GitHub at [https://github.com/rankmyseo/rankmyseo-wp-plugin](https://github.com/rankmyseo/rankmyseo-wp-plugin). To rebuild assets locally, run `npm install && npm run build`.

== External services ==

This plugin works 100% locally by default and does not connect to any external service unless an administrator explicitly enables an optional integration.

= RankMySEO Cloud (Optional) =

* **Service URLs**: `https://api.rankmyseo.com/wp/v1/pair/start`, `https://api.rankmyseo.com/wp/v1/pair/poll`, `https://api.rankmyseo.com/wp/v1/connection`, `https://api.rankmyseo.com/wp/v1/disconnect`, `https://api.rankmyseo.com/wp/v1/rotate-token`, `https://api.rankmyseo.com/wp/v1/sync/inventory`, `https://api.rankmyseo.com/wp/v1/sync/events`, `https://api.rankmyseo.com/wp/v1/insights`, and `https://api.rankmyseo.com/wp/v1/policy`. The approval page opened in the browser is the address RankMySEO returns with the pairing code.
* **When data is sent**: Only after a site administrator clicks Connect and approves the pairing code. Inventory and event sync, insights, token rotation, and disconnect run after that. Nothing is sent while the site is disconnected.
* **What data is sent**: Site URL, site name, WordPress and plugin versions during pairing, then public post URLs, titles, meta descriptions, focus keywords, and on-page scores during sync. The site token and webhook secret stay encrypted in WordPress and are not shown in the admin screen.
* **Terms of Service**: [https://rankmyseo.com/terms](https://rankmyseo.com/terms)
* **Privacy Policy**: [https://rankmyseo.com/privacy](https://rankmyseo.com/privacy)

= IndexNow (Optional) =

* **Service URL**: `https://api.indexnow.org/indexnow`
* **When data is sent**: Only when the optional IndexNow ping feature is enabled and public indexable content is published, updated, or removed.
* **What data is sent**: The site host, generated IndexNow verification key, and the list of changed public URLs.
* **Terms and Documentation**: [https://www.indexnow.org/terms](https://www.indexnow.org/terms)

== Changelog ==

= 0.1.0 =
* Initial foundation release: plugin bootstrap, PSR-4 core container, module manager, settings storage, capabilities, versioned custom database migrations, REST API (`rankmyseo/v1`), dual-mode Simple/Advanced React admin shell, 3-step Setup Wizard shell, and local Ask AI assistant drawer.
* Optional RankMySEO Cloud pairing with encrypted token storage, inventory and event sync, and cached keyword insights.

== Upgrade Notice ==

= 0.1.0 =
Initial foundation release of RankMySEO.
