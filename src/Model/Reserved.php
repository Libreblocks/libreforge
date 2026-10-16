<?php
/**
 * Names WordPress already uses, which must not become post type or taxonomy slugs.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model;

defined( 'ABSPATH' ) || exit;

final class Reserved {

	public const POST_TYPES = [
		'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
		'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
		'wp_navigation', 'wp_font_family', 'wp_font_face', 'action', 'author', 'order', 'theme',
	];

	// Public query variables: a post type or taxonomy with one of these names breaks front-end requests.
	public const QUERY_VARS = [
		'attachment', 'attachment_id', 'author', 'author_name', 'calendar', 'cat', 'category', 'category__and',
		'category__in', 'category__not_in', 'category_name', 'comments_per_page', 'comments_popup', 'custom',
		'customize_messenger_channel', 'customized', 'cpage', 'day', 'debug', 'embed', 'error', 'exact', 'feed',
		'fields', 'hour', 'link_category', 'm', 'minute', 'monthnum', 'more', 'name', 'nav_menu', 'nonce',
		'nopaging', 'offset', 'order', 'orderby', 'p', 'page', 'page_id', 'paged', 'pagename', 'pb', 'perm',
		'post', 'post__in', 'post__not_in', 'post_format', 'post_mime_type', 'post_status', 'post_tag',
		'post_type', 'posts', 'posts_per_archive_page', 'posts_per_page', 'preview', 'robots', 's', 'search',
		'second', 'sentence', 'showposts', 'static', 'status', 'subpost', 'subpost_id', 'tag', 'tag__and',
		'tag__in', 'tag__not_in', 'tag_id', 'tag_slug__and', 'tag_slug__in', 'taxonomy', 'tb', 'term', 'terms',
		'theme', 'title', 'type', 'types', 'w', 'withcomments', 'withoutcomments', 'year',
	];

	public static function is_reserved_post_type( string $slug ): bool {
		return in_array( $slug, self::POST_TYPES, true ) || in_array( $slug, self::QUERY_VARS, true );
	}

	public static function is_reserved_taxonomy( string $slug ): bool {
		return in_array( $slug, self::QUERY_VARS, true ) || in_array( $slug, self::POST_TYPES, true );
	}
}
