<?php
/**
 * Builds the full label sets WordPress expects from just a singular and a plural name.
 * Templates avoid grammatical gender so they read well in Portuguese and Spanish too;
 * every label can still be overridden one by one.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model\Kinds;

defined( 'ABSPATH' ) || exit;

final class Labels {

	/**
	 * @param array<string, string> $overrides
	 * @return array<string, string>
	 */
	public static function post_type( string $singular, string $plural, array $overrides = [] ): array {
		$s = $singular;
		$p = $plural;
		$l = mb_strtolower( $plural );

		$labels = [
			'name'                     => $p,
			'singular_name'            => $s,
			'menu_name'                => $p,
			'name_admin_bar'           => $s,
			'add_new'                  => __( 'Add new', 'libreforge' ),
			/* translators: %s: singular name */
			'add_new_item'             => sprintf( __( 'Add %s', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'edit_item'                => sprintf( __( 'Edit %s', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'new_item'                 => sprintf( __( 'New item: %s', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'view_item'                => sprintf( __( 'View %s', 'libreforge' ), $s ),
			/* translators: %s: plural name */
			'view_items'               => sprintf( __( 'View %s', 'libreforge' ), $p ),
			/* translators: %s: plural name */
			'all_items'                => sprintf( __( 'All: %s', 'libreforge' ), $p ),
			/* translators: %s: plural name */
			'search_items'             => sprintf( __( 'Search %s', 'libreforge' ), $l ),
			/* translators: %s: singular name */
			'parent_item_colon'        => sprintf( __( 'Parent %s:', 'libreforge' ), $s ),
			/* translators: %s: plural name, lowercase */
			'not_found'                => sprintf( __( 'Nothing found in %s.', 'libreforge' ), $l ),
			/* translators: %s: plural name, lowercase */
			'not_found_in_trash'       => sprintf( __( 'Nothing found in %s in the trash.', 'libreforge' ), $l ),
			/* translators: %s: plural name */
			'archives'                 => sprintf( __( '%s archive', 'libreforge' ), $p ),
			/* translators: %s: singular name */
			'attributes'               => sprintf( __( '%s attributes', 'libreforge' ), $s ),
			'featured_image'           => __( 'Featured image', 'libreforge' ),
			'set_featured_image'       => __( 'Set featured image', 'libreforge' ),
			'remove_featured_image'    => __( 'Remove featured image', 'libreforge' ),
			'use_featured_image'       => __( 'Use as featured image', 'libreforge' ),
			'insert_into_item'         => __( 'Insert into item', 'libreforge' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this item', 'libreforge' ),
			/* translators: %s: plural name */
			'items_list'               => sprintf( __( '%s list', 'libreforge' ), $p ),
			/* translators: %s: plural name */
			'items_list_navigation'    => sprintf( __( '%s list navigation', 'libreforge' ), $p ),
			/* translators: %s: plural name */
			'filter_items_list'        => sprintf( __( 'Filter %s list', 'libreforge' ), $l ),
			/* translators: %s: singular name */
			'item_published'           => sprintf( __( '%s published.', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'item_updated'             => sprintf( __( '%s updated.', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'item_scheduled'           => sprintf( __( '%s scheduled.', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'item_reverted_to_draft'   => sprintf( __( '%s reverted to draft.', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'item_link'                => sprintf( __( '%s link', 'libreforge' ), $s ),
		];

		return array_merge( $labels, $overrides );
	}

	/**
	 * @param array<string, string> $overrides
	 * @return array<string, string>
	 */
	public static function taxonomy( string $singular, string $plural, array $overrides = [] ): array {
		$s = $singular;
		$p = $plural;
		$l = mb_strtolower( $plural );

		$labels = [
			'name'                       => $p,
			'singular_name'              => $s,
			'menu_name'                  => $p,
			/* translators: %s: plural name */
			'all_items'                  => sprintf( __( 'All: %s', 'libreforge' ), $p ),
			/* translators: %s: singular name */
			'edit_item'                  => sprintf( __( 'Edit %s', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'view_item'                  => sprintf( __( 'View %s', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'update_item'                => sprintf( __( 'Update %s', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'add_new_item'               => sprintf( __( 'Add %s', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'new_item_name'              => sprintf( __( 'Name of the new item: %s', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'parent_item'                => sprintf( __( 'Parent %s', 'libreforge' ), $s ),
			/* translators: %s: singular name */
			'parent_item_colon'          => sprintf( __( 'Parent %s:', 'libreforge' ), $s ),
			/* translators: %s: plural name */
			'search_items'               => sprintf( __( 'Search %s', 'libreforge' ), $l ),
			/* translators: %s: plural name, lowercase */
			'popular_items'              => sprintf( __( 'Most used: %s', 'libreforge' ), $l ),
			/* translators: %s: plural name, lowercase */
			'separate_items_with_commas' => sprintf( __( 'Separate %s with commas', 'libreforge' ), $l ),
			/* translators: %s: plural name, lowercase */
			'add_or_remove_items'        => sprintf( __( 'Add or remove %s', 'libreforge' ), $l ),
			/* translators: %s: plural name, lowercase */
			'choose_from_most_used'      => sprintf( __( 'Choose from the most used: %s', 'libreforge' ), $l ),
			/* translators: %s: plural name, lowercase */
			'not_found'                  => sprintf( __( 'Nothing found in %s.', 'libreforge' ), $l ),
			/* translators: %s: plural name, lowercase */
			'no_terms'                   => sprintf( __( 'No %s', 'libreforge' ), $l ),
			/* translators: %s: plural name */
			'items_list'                 => sprintf( __( '%s list', 'libreforge' ), $p ),
			/* translators: %s: plural name */
			'items_list_navigation'      => sprintf( __( '%s list navigation', 'libreforge' ), $p ),
			'back_to_items'              => __( '← Back', 'libreforge' ),
		];

		return array_merge( $labels, $overrides );
	}
}
