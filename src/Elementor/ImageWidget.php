<?php
/**
 * Elementor widget: Dynamic Image.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

final class ImageWidget extends Widget {

	public function get_name(): string {
		return 'libreforge-image';
	}

	public function get_title(): string {
		return __( 'Dynamic Image', 'libreforge' );
	}

	public function get_icon(): string {
		return 'eicon-image';
	}

	public function get_keywords(): array {
		return [ 'image', 'photo', 'featured', 'dynamic', 'libreforge' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [ 'label' => __( 'Image', 'libreforge' ) ] );

		$this->add_source_controls(
			[
				'featured' => __( 'Featured image', 'libreforge' ),
				'meta'     => __( 'Image field', 'libreforge' ),
				'option'   => __( 'Options page', 'libreforge' ),
			],
			[ 'media', 'gallery' ]
		);

		$this->add_item_control();

		$this->add_control( 'size', [
			'label'   => __( 'Size', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'options' => Choices::sizes(),
			'default' => 'medium_large',
		] );

		$this->add_control( 'link', [
			'label'   => __( 'Link', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'none',
			'options' => [
				'none' => __( 'No link', 'libreforge' ),
				'item' => __( 'To the item', 'libreforge' ),
				'file' => __( 'To the image file', 'libreforge' ),
			],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'style_section', [ 'label' => __( 'Image', 'libreforge' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_responsive_control( 'height', [
			'label'      => __( 'Height', 'libreforge' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'vh' ],
			'range'      => [ 'px' => [ 'min' => 50, 'max' => 800 ] ],
			'selectors'  => [ '{{WRAPPER}} .lf-image img' => 'height: {{SIZE}}{{UNIT}}; object-fit: cover;' ],
		] );

		$this->add_control( 'radius', [
			'label'      => __( 'Rounded corners', 'libreforge' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
			'selectors'  => [ '{{WRAPPER}} .lf-image img' => 'border-radius: {{SIZE}}{{UNIT}};' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s      = $this->get_settings_for_display();
		$source = (string) ( $s['source'] ?? 'featured' );

		$this->output( $this->with_preview( fn() => $this->renderer()->image( [
			'object_id' => (int) ( $s['object_id'] ?? 0 ),
			'source' => $source,
			'key'    => 'featured' === $source ? '' : Choices::key( $s ),
			'size'   => (string) ( $s['size'] ?? 'medium_large' ),
			'link'   => (string) ( $s['link'] ?? 'none' ),
		] ) ) );
	}
}
