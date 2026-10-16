=== LibreForge ===
Contributors: libreblocks
Tags: custom post types, custom fields, taxonomies, options page, meta box
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: libreblocks-core
Stable tag: 0.8.1
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

== Shortcodes ==

For themes and builders other than the block editor and Elementor:

* `[libreforge_loop query="12" template="34" columns="3" pagination="numbers"]` - a listing from a saved query.
* `[libreforge_loop post_type="team" per_page="6" template="34"]` - a listing of a post type.
* `[libreforge_field key="price" format="currency"]` - a field of the current item.
* `[libreforge_template id="34"]` - an item template for the current item.

== Frequently Asked Questions ==

= Is my data removed when I uninstall? =

No. Definitions are kept so a reinstall brings everything back. Add `define( 'LIBREFORGE_DELETE_DATA', true );` to wp-config.php before uninstalling to remove them.

== Changelog ==

= 0.8.1 =
* Elementor Pro theme templates (single post, archive): widgets and dynamic tags preview the item chosen in "Preview settings".
* Fix: the "LibreForge field" dynamic tag returns the bare value, so it works in color and number controls (e.g. a background color from a field).

= 0.8.0 =
* Dynamic Repeater as an accordion: clickable title sub-field, content below, only one open, first open, arrow or plus icon, full Elementor styling.
* Optional FAQ structured data (schema.org FAQPage) for Google.
* New shortcode [libreforge_repeater key="faq" layout="accordion"].

= 0.7.1 =
* Dynamic widgets: new "From which item" option to show the data of a specific item on any page.
* Dynamic Repeater: leave "Each row" empty to show every sub-field automatically.
* The Elementor editor explains why an element shows nothing (e.g. the page has no such field).
* Fix: PHP warning in the repeater shortcode without a list type.

= 0.7.0 =
* Listings can be refreshed by filters (LibreSift): settings travel signed, the wrapper is always printed.
* New "Listing ID" option on the Loop Grid block, widget and shortcode (id="").
* Other LibreBlocks plugins can register their own definition kinds.

= 0.6.0 =
* Page of each item: pick an item template for the single pages of a post type.
* Shortcodes for any theme or builder: [libreforge_loop], [libreforge_field] and [libreforge_template].

= 0.5.0 =
* Relations: one to one, one to many and many to many between post types, terms and users, with a picker box on each side.
* Queries can list the items related to the current item (e.g. the properties of this broker).
* New tokens {{relation.children|slug}} and {{relation.parents|slug}}.

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
