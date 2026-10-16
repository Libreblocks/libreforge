<?php
/**
 * Elementor widget: Dynamic Link / button.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

final class LinkWidget extends Widget {

	public function get_name(): string {
		return 'libreforge-link';
	}

	public function get_title(): string {
		return __( 'Dynamic Link', 'libreforge' );
	}

	public function get_icon(): string {
		return 'eicon-button';
	}

	public function get_keywords(): array {
		return [ 'button', 'link', 'dynamic', 'libreforge' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [ 'label' => __( 'Link', 'libreforge' ) ] );

		$this->add_control( 'text', [
			'label'       => __( 'Text', 'libreforge' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => __( 'See more', 'libreforge' ),
			'description' => __( 'Tokens allowed, e.g. See {{object.prop|post_title}}', 'libreforge' ),
		] );

		$this->add_control( 'url_source', [
			'label'   => __( 'Goes to', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'item',
			'options' => [
				'item'   => __( 'The item page', 'libreforge' ),
				'meta'   => __( 'A field (URL, e-mail or phone)', 'libreforge' ),
				'option' => __( 'An options page field', 'libreforge' ),
				'custom' => __( 'A custom address', 'libreforge' ),
			],
		] );

		$this->add_control( 'url_meta', [
			'label'     => __( 'Field', 'libreforge' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => Choices::fields(),
			'condition' => [ 'url_source' => 'meta' ],
		] );

		$this->add_control( 'url_meta_custom', [
			'label'     => __( 'Field name', 'libreforge' ),
			'type'      => Controls_Manager::TEXT,
			'condition' => [ 'url_source' => 'meta', 'url_meta' => '__custom' ],
		] );

		$this->add_control( 'url_option', [
			'label'     => __( 'Option', 'libreforge' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => Choices::options(),
			'condition' => [ 'url_source' => 'option' ],
		] );

		$this->add_control( 'url_custom', [
			'label'       => __( 'Address', 'libreforge' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'https://wa.me/55{{object.meta|whatsapp}}',
			'condition'   => [ 'url_source' => 'custom' ],
		] );

		$this->add_control( 'style', [
			'label'   => __( 'Style', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'button',
			'options' => [
				'button' => __( 'Button', 'libreforge' ),
				'link'   => __( 'Text link', 'libreforge' ),
			],
		] );

		$this->add_control( 'new_tab', [ 'label' => __( 'Open in a new tab', 'libreforge' ), 'type' => Controls_Manager::SWITCHER ] );

		$this->end_controls_section();

		$this->start_controls_section( 'style_section', [ 'label' => __( 'Button', 'libreforge' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'typography', 'selector' => '{{WRAPPER}} .lf-link' ] );

		$this->add_control( 'text_color', [
			'label'     => __( 'Text color', 'libreforge' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .lf-link' => 'color: {{VALUE}};' ],
		] );

		$this->add_control( 'background', [
			'label'     => __( 'Background', 'libreforge' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .lf-link--button' => 'background-color: {{VALUE}};' ],
			'condition' => [ 'style' => 'button' ],
		] );

		$this->add_responsive_control( 'padding', [
			'label'      => __( 'Padding', 'libreforge' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [ '{{WRAPPER}} .lf-link--button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			'condition'  => [ 'style' => 'button' ],
		] );

		$this->add_control( 'radius', [
			'label'      => __( 'Rounded corners', 'libreforge' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'selectors'  => [ '{{WRAPPER}} .lf-link--button' => 'border-radius: {{SIZE}}{{UNIT}};' ],
			'condition'  => [ 'style' => 'button' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s      = $this->get_settings_for_display();
		$source = (string) ( $s['url_source'] ?? 'item' );
		$key    = '';

		if ( 'meta' === $source ) {
			$key = '__custom' === ( $s['url_meta'] ?? '' ) ? sanitize_key( (string) ( $s['url_meta_custom'] ?? '' ) ) : (string) ( $s['url_meta'] ?? '' );
		} elseif ( 'option' === $source ) {
			$key = (string) ( $s['url_option'] ?? '' );
		} elseif ( 'custom' === $source ) {
			$key = (string) ( $s['url_custom'] ?? '' );
		}

		$this->output( $this->with_preview( fn() => $this->renderer()->link( [
			'url_source' => $source,
			'url_key'    => $key,
			'text'       => (string) ( $s['text'] ?? '' ),
			'new_tab'    => 'yes' === ( $s['new_tab'] ?? '' ),
			'style'      => (string) ( $s['style'] ?? 'button' ),
		] ) ) );
	}
}
