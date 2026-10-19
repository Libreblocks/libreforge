<?php
/**
 * Optional LibreForge modules shown on the dashboard. Relations ships with
 * LibreForge; each other module is its own plugin ("LibreForge – Maps"...).
 * Installed modules get an on/off switch (on by default).
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Modules;

defined( 'ABSPATH' ) || exit;

final class Catalog {

	public const OPTION = 'libreforge_modules';


	/**
	 * @return array<int, array{id: string, title: string, description: string, icon: string, plugin?: string, pro: bool, available: bool}>
	 */
	public static function all(): array {
		$modules = [
			[ 'id' => 'relations', 'title' => __( 'Relations', 'libreforge' ), 'description' => __( 'Connect posts, terms and users to each other.', 'libreforge' ), 'icon' => 'networking', 'pro' => false, 'available' => true ],
			[ 'id' => 'dynamic_visibility', 'plugin' => __( 'LibreForge – Dynamic Visibility', 'libreforge' ), 'title' => __( 'Dynamic visibility', 'libreforge' ), 'description' => __( 'Show or hide any block by rules.', 'libreforge' ), 'icon' => 'visibility', 'pro' => true ],
			[ 'id' => 'content_tables', 'plugin' => __( 'LibreForge – Content Tables', 'libreforge' ), 'title' => __( 'Content tables', 'libreforge' ), 'description' => __( 'Large datasets in their own fast database table.', 'libreforge' ), 'icon' => 'database', 'pro' => true ],
			[ 'id' => 'calendar', 'plugin' => __( 'LibreForge – Calendar', 'libreforge' ), 'title' => __( 'Calendar', 'libreforge' ), 'description' => __( 'List items by date in a monthly calendar.', 'libreforge' ), 'icon' => 'calendar-alt', 'pro' => true ],
			[ 'id' => 'maps', 'plugin' => __( 'LibreForge – Maps', 'libreforge' ), 'title' => __( 'Maps', 'libreforge' ), 'description' => __( 'Show listing items as pins on a map.', 'libreforge' ), 'icon' => 'location-alt', 'pro' => true ],
			[ 'id' => 'favorites', 'plugin' => __( 'LibreForge – Favorites and Compare', 'libreforge' ), 'title' => __( 'Favorites and compare', 'libreforge' ), 'description' => __( 'Visitors save and compare items.', 'libreforge' ), 'icon' => 'heart', 'pro' => true ],
			[ 'id' => 'profile', 'plugin' => __( 'LibreForge – User Profile', 'libreforge' ), 'title' => __( 'User profile', 'libreforge' ), 'description' => __( 'Front-end account area for members.', 'libreforge' ), 'icon' => 'id-alt', 'pro' => true ],
			[ 'id' => 'tables_charts', 'plugin' => __( 'LibreForge – Tables and Charts', 'libreforge' ), 'title' => __( 'Tables and charts', 'libreforge' ), 'description' => __( 'Turn queries into tables and charts.', 'libreforge' ), 'icon' => 'chart-bar', 'pro' => true ],
		];

		/**
		 * Filters the module catalog. Add-ons set "available" => true for the modules they provide.
		 *
		 * @param array<int, array<string, mixed>> $modules
		 */
		$modules = (array) apply_filters( 'libreforge/modules', $modules );

		return array_map( function ( array $module ) {
			$module += [ 'available' => false, 'pro' => false ];

			$module['enabled'] = $module['available'] && self::enabled( (string) $module['id'] );

			return $module;
		}, $modules );
	}

	/**
	 * Whether a module is switched on. Safe to call early (no translations involved).
	 */
	public static function enabled( string $id ): bool {
		$saved = (array) get_option( self::OPTION, [] );

		return ! isset( $saved[ $id ] ) || (bool) $saved[ $id ];
	}

	public static function set_enabled( string $id, bool $enabled ): void {
		$saved        = (array) get_option( self::OPTION, [] );
		$saved[ $id ] = $enabled;

		update_option( self::OPTION, $saved );
	}
}
