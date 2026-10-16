<?php
/**
 * LibreForge keeps its definitions on uninstall: deleting them would hide every
 * post type, taxonomy and field the site relies on if the plugin is reinstalled.
 * Define LIBREFORGE_DELETE_DATA as true in wp-config.php to remove everything.
 *
 * @package LibreBlocks\Forge
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'lbforge_flush_rewrite' );

if ( defined( 'LIBREFORGE_DELETE_DATA' ) && LIBREFORGE_DELETE_DATA ) {
	global $wpdb;

	$table = $wpdb->prefix . 'lbforge_definitions';

	// Options pages store their values in lbforge_{slug}.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lbforge\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$versions = get_option( 'lbcore_schema_versions', [] );
	unset( $versions['lbforge_definitions'] );
	update_option( 'lbcore_schema_versions', $versions, false );
}
