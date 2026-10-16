<?php
/**
 * Optional LibreForge modules shown on the dashboard. None ships in 0.1;
 * they are listed so users see what is coming and which ones are Pro.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Modules;

defined( 'ABSPATH' ) || exit;

final class Catalog {

	/**
	 * @return array<int, array{id: string, title: string, description: string, icon: string, pro: bool, available: bool}>
	 */
	public static function all(): array {
		$modules = [
			[ 'id' => 'relations', 'title' => __( 'Relations', 'libreforge' ), 'description' => __( 'Connect posts, terms and users to each other.', 'libreforge' ), 'icon' => 'networking', 'pro' => true ],
			[ 'id' => 'dynamic_visibility', 'title' => __( 'Dynamic visibility', 'libreforge' ), 'description' => __( 'Show or hide any block by rules.', 'libreforge' ), 'icon' => 'visibility', 'pro' => true ],
			[ 'id' => 'content_tables', 'title' => __( 'Content tables', 'libreforge' ), 'description' => __( 'Large datasets in their own fast database table.', 'libreforge' ), 'icon' => 'database', 'pro' => true ],
			[ 'id' => 'calendar', 'title' => __( 'Calendar', 'libreforge' ), 'description' => __( 'List items by date in a monthly calendar.', 'libreforge' ), 'icon' => 'calendar-alt', 'pro' => true ],
			[ 'id' => 'maps', 'title' => __( 'Maps', 'libreforge' ), 'description' => __( 'Show listing items as pins on a map.', 'libreforge' ), 'icon' => 'location-alt', 'pro' => true ],
			[ 'id' => 'favorites', 'title' => __( 'Favorites and compare', 'libreforge' ), 'description' => __( 'Visitors save and compare items.', 'libreforge' ), 'icon' => 'heart', 'pro' => true ],
			[ 'id' => 'profile', 'title' => __( 'User profile', 'libreforge' ), 'description' => __( 'Front-end account area for members.', 'libreforge' ), 'icon' => 'id-alt', 'pro' => true ],
			[ 'id' => 'tables_charts', 'title' => __( 'Tables and charts', 'libreforge' ), 'description' => __( 'Turn queries into tables and charts.', 'libreforge' ), 'icon' => 'chart-bar', 'pro' => true ],
		];

		/**
		 * Filters the module catalog. Add-ons set "available" => true for the modules they provide.
		 *
		 * @param array<int, array<string, mixed>> $modules
		 */
		$modules = (array) apply_filters( 'libreforge/modules', $modules );

		return array_map( fn( array $module ) => $module + [ 'available' => false ], $modules );
	}
}
