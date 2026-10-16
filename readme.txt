=== LibreForge ===
Contributors: libreblocks
Tags: custom post types, custom fields, taxonomies, options page, meta box
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: libreblocks-core
Stable tag: 0.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create post types, taxonomies, custom fields, options pages and glossaries without code.

== Description ==

LibreForge is the content engine of the LibreBlocks family.

* **Post types**: labels generated from the singular and plural names, archive, URLs, REST/block editor, menu icon.
* **Taxonomies**: hierarchical or tag-like, attached to any post type.
* **Field groups**: 24 field types (text, number, dates, choices, media, gallery, post/term/user pickers, repeater, group, tabs...) shown on posts, terms or user profiles, with conditional logic.
* **Options pages**: site-wide settings screens; values are readable in tokens such as {{option|lbforge_contact:phone}}.
* **Glossaries**: reusable option lists for choice fields.

Values are stored in the native WordPress tables (post, term and user meta, options), so your data stays readable by any theme or plugin.

Requires the LibreBlocks Core plugin.

== Frequently Asked Questions ==

= Is my data removed when I uninstall? =

No. Definitions are kept so a reinstall brings everything back. Add `define( 'LIBREFORGE_DELETE_DATA', true );` to wp-config.php before uninstalling to remove them.

== Changelog ==

= 0.4.1 =
* Fix: blocks inside an item template now preview a real item in the editor.
* Item templates no longer appear as a content type in lists.

= 0.4.0 =
* Query Builder: saved queries of posts, terms or users with taxonomy and field filters, sorting, tokens and a live preview.
* Item templates designed with the block editor or Elementor, previewed with a real item.
* Blocks: Loop Grid, Dynamic Field, Dynamic Image, Dynamic Link, Dynamic Terms and Dynamic Repeater.
* Elementor widgets for the same six elements plus dynamic tags (field, link, image).
* Values are formatted automatically: option labels, dates, money, rich text.

= 0.3.0 =
* Custom fields are created right inside each post type and taxonomy.
* Simpler editors: one page with collapsible sections, plain-language help and an Actions sidebar.
* The identifier (slug) and field names fill in automatically from the names.
* Fields can show as columns in the admin list; REST API per field; duplicate fields.

= 0.2.0 =
* Own LibreForge menu and dashboard with counters, quick start and modules.
* Lists as cards with search; editors split into sections with a sticky save bar.
* Icon picker, live menu preview and an easier glossary editor.
* Options pages get their own menu by default.

= 0.1.0 =
* Post types, taxonomies, field groups, options pages and glossaries.
