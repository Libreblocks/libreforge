<?php
/**
 * Elementor integration: a "LibreForge" widget category, six widgets and
 * dynamic tags. Loaded only when Elementor fires "elementor/init".
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use LibreBlocks\Core\Plugin as Core;

defined( 'ABSPATH' ) || exit;

final class Integration {

	public const CATEGORY = 'libreforge';

	public function __construct( private Core $core ) {}

	public function hook(): void {
		add_action( 'elementor/elements/categories_registered', [ $this, 'category' ] );
		add_action( 'elementor/widgets/register', [ $this, 'widgets' ] );
		add_action( 'elementor/dynamic_tags/register', [ $this, 'tags' ] );
	}

	/**
	 * @param \Elementor\Elements_Manager $manager
	 */
	public function category( $manager ): void {
		$manager->add_category( self::CATEGORY, [ 'title' => 'LibreForge', 'icon' => 'eicon-database' ] );
	}

	/**
	 * @param \Elementor\Widgets_Manager $manager
	 */
	public function widgets( $manager ): void {
		foreach ( [ LoopGridWidget::class, FieldWidget::class, ImageWidget::class, LinkWidget::class, TermsWidget::class, RepeaterWidget::class ] as $class ) {
			$manager->register( new $class() );
		}
	}

	/**
	 * @param \Elementor\Core\DynamicTags\Manager $manager
	 */
	public function tags( $manager ): void {
		$manager->register_group( 'libreforge', [ 'title' => 'LibreForge' ] );
		$manager->register( new FieldTag() );
		$manager->register( new UrlTag() );
		$manager->register( new ImageTag() );
	}
}
