<?php
/**
 * Elementor widget: Dynamic Terms.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

final class TermsWidget extends Widget {

	public function get_name(): string {
		return 'libreforge-terms';
	}

	public function get_title(): string {
		return __( 'Dynamic Terms', 'libreforge' );
	}

	public function get_icon(): string {
		return 'eicon-tags';
	}

	public function get_keywords(): array {
		return [ 'terms', 'categories', 'tags', 'taxonomy', 'libreforge' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [ 'label' => __( 'Terms', 'libreforge' ) ] );

		$this->add_control( 'taxonomy', [
			'label'   => __( 'Taxonomy', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'options' => Choices::taxonomies(),
			'default' => 'category',
		] );

		$this->add_control( 'style', [
			'label'   => __( 'Style', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'text',
			'options' => [
				'text'   => __( 'Text with separator', 'libreforge' ),
				'badges' => __( 'Badges', 'libreforge' ),
			],
		] );

		$this->add_control( 'separator', [
			'label'     => __( 'Separator', 'libreforge' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => ', ',
			'condition' => [ 'style' => 'text' ],
		] );

		$this->add_control( 'limit', [ 'label' => __( 'Maximum (0 = all)', 'libreforge' ), 'type' => Controls_Manager::NUMBER, 'default' => 0, 'min' => 0 ] );
		$this->add_control( 'link', [ 'label' => __( 'Link each term', 'libreforge' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ] );

		$this->end_controls_section();

		$this->start_controls_section( 'style_section', [ 'label' => __( 'Terms', 'libreforge' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'typography', 'selector' => '{{WRAPPER}} .lf-terms' ] );
		$this->add_control( 'color', [
			'label'     => __( 'Color', 'libreforge' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .lf-terms, {{WRAPPER}} .lf-terms a' => 'color: {{VALUE}};' ],
		] );
		$this->add_control( 'badge_background', [
			'label'     => __( 'Badge background', 'libreforge' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .lf-terms--badges .lf-terms__term' => 'background-color: {{VALUE}};' ],
			'condition' => [ 'style' => 'badges' ],
		] );
		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		$this->output( $this->with_preview( fn() => $this->renderer()->terms( [
			'taxonomy'  => (string) ( $s['taxonomy'] ?? 'category' ),
			'separator' => (string) ( $s['separator'] ?? ', ' ),
			'link'      => 'yes' === ( $s['link'] ?? '' ),
			'limit'     => (int) ( $s['limit'] ?? 0 ),
			'style'     => (string) ( $s['style'] ?? 'text' ),
		] ) ) );
	}
}
