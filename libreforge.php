<?php
/**
 * Plugin Name:       LibreForge
 * Plugin URI:        https://libreblocks.com/libreforge
 * Description:       Post types, custom fields, queries, item templates and dynamic listings for Gutenberg and Elementor, without code.
 * Version:           0.8.7
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Requires Plugins:  libreblocks-core
 * Author:            LibreBlocks
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       libreforge
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIBREFORGE_VERSION', '0.8.7' );
define( 'LIBREFORGE_FILE', __FILE__ );
define( 'LIBREFORGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIBREFORGE_URL', plugin_dir_url( __FILE__ ) );
define( 'LIBREFORGE_REQUIRES_CORE', '0.3.0' );

/**
 * Boots LibreForge once LibreBlocks Core is ready.
 *
 * @param LibreBlocks\Core\Plugin $core
 */
function libreforge_boot( $core ) {
	$compatible = $core->products()->register( 'libreforge', array(
		'name'          => 'LibreForge',
		'version'       => LIBREFORGE_VERSION,
		'file'          => LIBREFORGE_FILE,
		'requires_core' => LIBREFORGE_REQUIRES_CORE,
		'menu'          => 'libreforge',
	) );

	if ( ! $compatible ) {
		return;
	}

	LibreBlocks\Core\Autoloader::register( 'LibreBlocks\\Forge\\', LIBREFORGE_DIR . 'src/' );
	LibreBlocks\Forge\Plugin::instance()->boot( $core );
}

// On the activation request the core has already fired its "loaded" action.
if ( did_action( 'libreblocks/core/loaded' ) && function_exists( 'libreblocks' ) ) {
	libreforge_boot( libreblocks() );
} else {
	add_action( 'libreblocks/core/loaded', 'libreforge_boot' );
}

register_activation_hook( __FILE__, function () {
	if ( class_exists( 'LibreBlocks\\Forge\\Plugin' ) ) {
		LibreBlocks\Forge\Plugin::instance()->activate();
	}
} );

add_action( 'admin_notices', function () {
	if ( ! function_exists( 'libreblocks' ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'LibreForge needs the LibreBlocks Core plugin. Please install and activate it.', 'libreforge' )
		);
	}
} );
