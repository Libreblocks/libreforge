<?php
/**
 * Elementor widget: Dynamic Field.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

final class FieldWidget extends Widget {

	public function get_name(): string {
		return 'libreforge-field';
	}

	public function get_title(): string {
		return __( 'Dynamic Field', 'libreforge' );
	}

	public function get_icon(): string {
		return 'eicon-database';
	}

	public function get_keywords(): array {
		return [ 'field', 'meta', 'custom field', 'dynamic', 'libreforge' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [ 'label' => __( 'What to show', 'libreforge' ) ] );
		$this->add_source_controls();
		$this->add_item_control();
		$this->end_controls_section();

		$this->start_controls_section( 'format_section', [ 'label' => __( 'Format', 'libreforge' ) ] );

		$this->add_control( 'format', [
			'label'   => __( 'Show as', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'options' => Choices::formats(),
			'default' => 'auto',
		] );

		$this->add_control( 'date_format', [
			'label'       => __( 'Date format', 'libreforge' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'd/m/Y',
			'condition'   => [ 'format' => 'date' ],
		] );

		$this->add_control( 'decimals', [
			'label'     => __( 'Decimals', 'libreforge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'min'       => 0,
			'max'       => 4,
			'condition' => [ 'format' => [ 'number', 'currency' ] ],
		] );

		$this->add_control( 'currency', [
			'label'     => __( 'Currency symbol', 'libreforge' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => 'R$',
			'condition' => [ 'format' => 'currency' ],
		] );

		$this->add_control( 'words', [
			'label'     => __( 'Words', 'libreforge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 20,
			'condition' => [ 'format' => 'trim' ],
		] );

		$this->add_control( 'tag', [
			'label'   => __( 'HTML tag', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'div',
			'options' => array_combine( [ 'div', 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'small' ], [ 'div', 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'small' ] ),
		] );

		$this->add_control( 'before', [ 'label' => __( 'Text before', 'libreforge' ), 'type' => Controls_Manager::TEXT ] );
		$this->add_control( 'after', [ 'label' => __( 'Text after', 'libreforge' ), 'type' => Controls_Manager::TEXT ] );
		$this->add_control( 'fallback', [ 'label' => __( 'If empty, show', 'libreforge' ), 'type' => Controls_Manager::TEXT ] );
		$this->add_control( 'link', [ 'label' => __( 'Link to the item', 'libreforge' ), 'type' => Controls_Manager::SWITCHER, 'default' => '' ] );

		$this->end_controls_section();

		$this->start_controls_section( 'style_section', [ 'label' => __( 'Text', 'libreforge' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'typography', 'selector' => '{{WRAPPER}} .lf-field' ] );

		$this->add_control( 'color', [
			'label'     => __( 'Color', 'libreforge' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .lf-field, {{WRAPPER}} .lf-field a' => 'color: {{VALUE}};' ],
		] );

		$this->add_responsive_control( 'align', [
			'label'     => __( 'Alignment', 'libreforge' ),
			'type'      => Controls_Manager::CHOOSE,
			'options'   => [
				'left'   => [ 'title' => __( 'Left', 'libreforge' ), 'icon' => 'eicon-text-align-left' ],
				'center' => [ 'title' => __( 'Center', 'libreforge' ), 'icon' => 'eicon-text-align-center' ],
				'right'  => [ 'title' => __( 'Right', 'libreforge' ), 'icon' => 'eicon-text-align-right' ],
			],
			'selectors' => [ '{{WRAPPER}} .lf-field' => 'text-align: {{VALUE}};' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		$this->output( $this->with_preview( fn() => $this->renderer()->field( [
			'object_id'   => (int) ( $s['object_id'] ?? 0 ),
			'source'      => (string) ( $s['source'] ?? 'object' ),
			'key'         => Choices::key( $s ),
			'format'      => (string) ( $s['format'] ?? 'auto' ),
			'date_format' => (string) ( $s['date_format'] ?? '' ),
			'decimals'    => (int) ( $s['decimals'] ?? 0 ),
			'currency'    => (string) ( $s['currency'] ?? 'R$' ),
			'words'       => (int) ( $s['words'] ?? 20 ),
			'tag'         => (string) ( $s['tag'] ?? 'div' ),
			'before'      => (string) ( $s['before'] ?? '' ),
			'after'       => (string) ( $s['after'] ?? '' ),
			'fallback'    => (string) ( $s['fallback'] ?? '' ),
			'link'        => 'yes' === ( $s['link'] ?? '' ),
		] ) ) );
	}
}
