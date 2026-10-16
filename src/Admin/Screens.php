<?php
/**
 * The LibreForge menu (its own top-level item) and its screens. Every screen
 * mounts the same React app (build/admin.js) with a different section.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Admin;

use LibreBlocks\Core\Admin\Icons;
use LibreBlocks\Core\Plugin as Core;

defined( 'ABSPATH' ) || exit;

final class Screens {

	public const HANDLE = 'libreforge-admin';

	public const MENU = 'libreforge';

	public function __construct( private Core $core ) {}

	/**
	 * Page slug => section shown by the app.
	 *
	 * @return array<string, array{section: string, title: string, icon: string}>
	 */
	public static function pages(): array {
		return [
			self::MENU                 => [ 'section' => 'dashboard', 'title' => __( 'Dashboard', 'libreforge' ), 'icon' => 'dashboard' ],
			'libreforge-post-types'    => [ 'section' => 'post_type', 'title' => __( 'Post types', 'libreforge' ), 'icon' => 'admin-post' ],
			'libreforge-taxonomies'    => [ 'section' => 'taxonomy', 'title' => __( 'Taxonomies', 'libreforge' ), 'icon' => 'tag' ],
			'libreforge-field-groups'  => [ 'section' => 'field_group', 'title' => __( 'Field groups', 'libreforge' ), 'icon' => 'forms' ],
			'libreforge-options-pages' => [ 'section' => 'options_page', 'title' => __( 'Options pages', 'libreforge' ), 'icon' => 'admin-settings' ],
			'libreforge-glossaries'    => [ 'section' => 'glossary', 'title' => __( 'Glossaries', 'libreforge' ), 'icon' => 'list-view' ],
			'libreforge-queries'       => [ 'section' => 'query', 'title' => __( 'Queries', 'libreforge' ), 'icon' => 'filter' ],
			'libreforge-templates'     => [ 'section' => 'templates', 'title' => __( 'Item templates', 'libreforge' ), 'icon' => 'layout' ],
		];
	}

	public static function url( string $section ): string {
		foreach ( self::pages() as $slug => $page ) {
			if ( $page['section'] === $section ) {
				return admin_url( 'admin.php?page=' . $slug );
			}
		}

		return admin_url( 'admin.php?page=' . self::MENU );
	}

	public function hook(): void {
		// Titles are translated, so the menu is declared once translations are loaded.
		add_action( 'init', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function add_menu(): void {
		$pages = [];

		foreach ( self::pages() as $slug => $page ) {
			$pages[] = [
				'slug'   => $slug,
				'title'  => $page['title'],
				'render' => function () use ( $page ) {
					printf( '<div id="libreforge-app" data-section="%s"></div>', esc_attr( $page['section'] ) );
				},
			];
		}

		$this->core->get( 'admin' )->add_menu( [
			'slug'  => self::MENU,
			'title' => 'LibreForge',
			'icon'  => Icons::data_uri( 'libreforge' ),
			'order' => 10,
			'pages' => $pages,
		] );
	}

	public function enqueue( string $hook_suffix ): void {
		$admin   = $this->core->get( 'admin' );
		$current = null;

		foreach ( self::pages() as $slug => $page ) {
			if ( $admin->page_hook( $slug ) === $hook_suffix ) {
				$current = $page['section'];
			}
		}

		$asset_file = LIBREFORGE_DIR . 'build/admin.asset.php';

		if ( ! $current || ! is_file( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;
		$tabs  = [];

		foreach ( self::pages() as $slug => $page ) {
			$tabs[] = [
				'key'   => $page['section'],
				'label' => $page['title'],
				'href'  => admin_url( 'admin.php?page=' . $slug ),
				'icon'  => $page['icon'],
			];
		}

		wp_enqueue_script( self::HANDLE, LIBREFORGE_URL . 'build/admin.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'libreforge', LIBREFORGE_DIR . 'languages' );
		$admin->share_ui_translations( self::HANDLE );
		wp_add_inline_script( self::HANDLE, 'window.libreforgeAdmin = ' . wp_json_encode( [
			'icon'    => Icons::data_uri( 'libreforge' ),
			'version' => LIBREFORGE_VERSION,
			'tabs'    => $tabs,
		] ) . ';', 'before' );

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'dashicons' );

		if ( is_file( LIBREFORGE_DIR . 'build/admin.css' ) ) {
			wp_enqueue_style( self::HANDLE, LIBREFORGE_URL . 'build/admin.css', [ 'wp-components' ], $asset['version'] );
		}
	}
}
