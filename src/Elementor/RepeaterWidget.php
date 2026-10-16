<?php
/**
 * Elementor widget: Dynamic Repeater. Shows the rows of a repeater field as a
 * list or as an accordion (question / answer, specs, steps...).
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

final class RepeaterWidget extends Widget {

	public function get_name(): string {
		return 'libreforge-repeater';
	}

	public function get_title(): string {
		return __( 'Dynamic Repeater', 'libreforge' );
	}

	public function get_icon(): string {
		return 'eicon-accordion';
	}

	public function get_keywords(): array {
		return [ 'repeater', 'faq', 'accordion', 'toggle', 'list', 'rows', 'libreforge' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [ 'label' => __( 'Repeater', 'libreforge' ) ] );

		$this->add_source_controls(
			[
				'meta'   => __( 'Custom field', 'libreforge' ),
				'option' => __( 'Options page', 'libreforge' ),
			],
			[ 'repeater' ]
		);

		$this->add_item_control();

		$this->add_control( 'layout', [
			'label'   => __( 'Show as', 'libreforge' ),
			'type'    => Controls_Manager::CHOOSE,
			'default' => 'list',
			'toggle'  => false,
			'options' => [
				'list'      => [ 'title' => __( 'List', 'libreforge' ), 'icon' => 'eicon-bullet-list' ],
				'accordion' => [ 'title' => __( 'Accordion', 'libreforge' ), 'icon' => 'eicon-accordion' ],
			],
		] );

		$this->add_control( 'title_key', [
			'label'       => __( 'Title (clickable)', 'libreforge' ),
			'type'        => Controls_Manager::SELECT,
			'options'     => Choices::repeater_children(),
			'default'     => '',
			'description' => __( 'The sub-field shown in each header, e.g. the question.', 'libreforge' ),
			'condition'   => [ 'layout' => 'accordion' ],
		] );

		$this->add_control( 'row_format', [
			'label'       => __( 'Content of each row (optional)', 'libreforge' ),
			'type'        => Controls_Manager::WYSIWYG,
			'default'     => '',
			'description' => __( 'Empty = the sub-fields are shown automatically (in the accordion: all except the title). To customize, use {{object.meta|sub_field_name}}.', 'libreforge' ),
		] );

		$this->add_control( 'tag', [
			'label'     => __( 'List type', 'libreforge' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'div',
			'options'   => [
				'div' => __( 'Blocks', 'libreforge' ),
				'ul'  => __( 'Bulleted list', 'libreforge' ),
				'ol'  => __( 'Numbered list', 'libreforge' ),
			],
			'condition' => [ 'layout' => 'list' ],
		] );

		$accordion = [ 'layout' => 'accordion' ];

		$this->add_control( 'single_open', [
			'label'       => __( 'Only one open at a time', 'libreforge' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => 'yes',
			'condition'   => $accordion,
		] );

		$this->add_control( 'open_first', [
			'label'     => __( 'First item starts open', 'libreforge' ),
			'type'      => Controls_Manager::SWITCHER,
			'default'   => '',
			'condition' => $accordion,
		] );

		$this->add_control( 'icon', [
			'label'     => __( 'Icon', 'libreforge' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'chevron',
			'options'   => [
				'chevron' => __( 'Arrow', 'libreforge' ),
				'plus'    => __( 'Plus / minus', 'libreforge' ),
				'none'    => __( 'No icon', 'libreforge' ),
			],
			'condition' => $accordion,
		] );

		$this->add_control( 'icon_position', [
			'label'     => __( 'Icon position', 'libreforge' ),
			'type'      => Controls_Manager::CHOOSE,
			'default'   => 'right',
			'toggle'    => false,
			'options'   => [
				'left'  => [ 'title' => __( 'Left', 'libreforge' ), 'icon' => 'eicon-h-align-left' ],
				'right' => [ 'title' => __( 'Right', 'libreforge' ), 'icon' => 'eicon-h-align-right' ],
			],
			'condition' => [ 'layout' => 'accordion', 'icon!' => 'none' ],
		] );

		$this->add_control( 'faq_schema', [
			'label'       => __( 'FAQ for Google (schema)', 'libreforge' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => '',
			'description' => __( 'Adds FAQ structured data so the questions can appear in Google results. Use it once per page, only for real questions and answers.', 'libreforge' ),
			'condition'   => $accordion,
		] );

		$this->end_controls_section();

		$this->register_list_style();
		$this->register_accordion_style();
	}

	private function register_list_style(): void {
		$this->start_controls_section( 'list_style', [
			'label'     => __( 'List', 'libreforge' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => [ 'layout' => 'list' ],
		] );

		$this->add_responsive_control( 'row_gap', [
			'label'      => __( 'Space between rows', 'libreforge' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'selectors'  => [ '{{WRAPPER}} .lf-repeater__item + .lf-repeater__item' => 'margin-top: {{SIZE}}{{UNIT}};' ],
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'list_typography', 'selector' => '{{WRAPPER}} .lf-repeater' ] );

		$this->end_controls_section();
	}

	private function register_accordion_style(): void {
		$condition = [ 'layout' => 'accordion' ];

		// Items.
		$this->start_controls_section( 'accordion_items', [ 'label' => __( 'Accordion: items', 'libreforge' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => $condition ] );

		$this->add_responsive_control( 'acc_gap', [
			'label'      => __( 'Space between items', 'libreforge' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
			'selectors'  => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-gap: {{SIZE}}{{UNIT}};' ],
		] );

		$this->add_control( 'acc_bg', [
			'label'     => __( 'Background', 'libreforge' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-bg: {{VALUE}};' ],
		] );

		$this->add_control( 'acc_border', [
			'label'     => __( 'Border color', 'libreforge' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-border: {{VALUE}};' ],
		] );

		$this->add_control( 'acc_border_width', [
			'label'      => __( 'Border width', 'libreforge' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 6 ] ],
			'selectors'  => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-border-width: {{SIZE}}{{UNIT}};' ],
		] );

		$this->add_control( 'acc_radius', [
			'label'      => __( 'Rounded corners', 'libreforge' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 0, 'max' => 40 ] ],
			'selectors'  => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-radius: {{SIZE}}{{UNIT}};' ],
		] );

		$this->end_controls_section();

		// Title.
		$this->start_controls_section( 'accordion_title', [ 'label' => __( 'Accordion: title', 'libreforge' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => $condition ] );

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'acc_title_typography', 'selector' => '{{WRAPPER}} .lf-accordion__title' ] );

		$this->add_responsive_control( 'acc_title_padding', [
			'label'      => __( 'Padding', 'libreforge' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [ '{{WRAPPER}} .lf-accordion__title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
		] );

		$this->start_controls_tabs( 'acc_title_states' );

		$this->start_controls_tab( 'acc_title_closed', [ 'label' => __( 'Closed', 'libreforge' ) ] );
		$this->add_control( 'acc_title_color', [ 'label' => __( 'Text color', 'libreforge' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-title: {{VALUE}};' ] ] );
		$this->add_control( 'acc_title_bg', [ 'label' => __( 'Background', 'libreforge' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-title-bg: {{VALUE}};' ] ] );
		$this->end_controls_tab();

		$this->start_controls_tab( 'acc_title_open', [ 'label' => __( 'Open', 'libreforge' ) ] );
		$this->add_control( 'acc_title_color_open', [ 'label' => __( 'Text color', 'libreforge' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-title-open: {{VALUE}};' ] ] );
		$this->add_control( 'acc_title_bg_open', [ 'label' => __( 'Background', 'libreforge' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-title-bg-open: {{VALUE}};' ] ] );
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control( 'acc_icon_color', [
			'label'     => __( 'Icon color', 'libreforge' ),
			'type'      => Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-icon: {{VALUE}};' ],
		] );

		$this->end_controls_section();

		// Content.
		$this->start_controls_section( 'accordion_content', [ 'label' => __( 'Accordion: content', 'libreforge' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => $condition ] );

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'acc_content_typography', 'selector' => '{{WRAPPER}} .lf-accordion__content' ] );

		$this->add_control( 'acc_content_color', [
			'label'     => __( 'Text color', 'libreforge' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .lf-accordion' => '--lf-acc-content: {{VALUE}};' ],
		] );

		$this->add_responsive_control( 'acc_content_padding', [
			'label'      => __( 'Padding', 'libreforge' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [ '{{WRAPPER}} .lf-accordion__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		$this->output( $this->with_preview( fn() => $this->renderer()->repeater( [
			'object_id'     => (int) ( $s['object_id'] ?? 0 ),
			'source'        => (string) ( $s['source'] ?? 'meta' ),
			'key'           => Choices::key( $s ),
			'format'        => (string) ( $s['row_format'] ?? '' ),
			'tag'           => (string) ( $s['tag'] ?? 'div' ),
			'layout'        => (string) ( $s['layout'] ?? 'list' ),
			'title_key'     => (string) ( $s['title_key'] ?? '' ),
			'single_open'   => 'yes' === ( $s['single_open'] ?? '' ),
			'open_first'    => 'yes' === ( $s['open_first'] ?? '' ),
			'icon'          => (string) ( $s['icon'] ?? 'chevron' ),
			'icon_position' => (string) ( $s['icon_position'] ?? 'right' ),
			'faq_schema'    => 'yes' === ( $s['faq_schema'] ?? '' ),
		] ) ) );
	}
}
