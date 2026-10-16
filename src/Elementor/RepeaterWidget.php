<?php
/**
 * Elementor widget: Dynamic Repeater. Each row is printed with a small text
 * template where {{object.meta|sub_field}} reads the row's sub-fields.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

final class RepeaterWidget extends Widget {

	public function get_name(): string {
		return 'libreforge-repeater';
	}

	public function get_title(): string {
		return __( 'Dynamic Repeater', 'libreforge' );
	}

	public function get_icon(): string {
		return 'eicon-bullet-list';
	}

	public function get_keywords(): array {
		return [ 'repeater', 'faq', 'list', 'rows', 'libreforge' ];
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

		$this->add_control( 'row_format', [
			'label'       => __( 'Each row', 'libreforge' ),
			'type'        => Controls_Manager::WYSIWYG,
			'default'     => '<strong>{{object.meta|question}}</strong><p>{{object.meta|answer}}</p>',
			'description' => __( 'Write how each row looks. {{object.meta|sub_field_name}} shows a sub-field of the row.', 'libreforge' ),
		] );

		$this->add_control( 'tag', [
			'label'   => __( 'List type', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'div',
			'options' => [
				'div' => __( 'Blocks', 'libreforge' ),
				'ul'  => __( 'Bulleted list', 'libreforge' ),
				'ol'  => __( 'Numbered list', 'libreforge' ),
			],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'style_section', [ 'label' => __( 'Rows', 'libreforge' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'row_gap', [
			'label'      => __( 'Space between rows', 'libreforge' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'selectors'  => [ '{{WRAPPER}} .lf-repeater__item + .lf-repeater__item' => 'margin-top: {{SIZE}}{{UNIT}};' ],
		] );
		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		$this->output( $this->with_preview( fn() => $this->renderer()->repeater( [
			'source' => (string) ( $s['source'] ?? 'meta' ),
			'key'    => Choices::key( $s ),
			'format' => (string) ( $s['row_format'] ?? '' ),
			'tag'    => (string) ( $s['tag'] ?? 'div' ),
		] ) ) );
	}
}
