<?php
/**
 * Elementor widget: Loop Grid.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

final class LoopGridWidget extends Widget {

	public function get_name(): string {
		return 'libreforge-loop-grid';
	}

	public function get_title(): string {
		return __( 'Loop Grid', 'libreforge' );
	}

	public function get_icon(): string {
		return 'eicon-posts-grid';
	}

	public function get_keywords(): array {
		return [ 'listing', 'loop', 'grid', 'query', 'libreforge' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'query_section', [ 'label' => __( 'What to list', 'libreforge' ) ] );

		$this->add_control( 'query_id', [
			'label'       => __( 'Query', 'libreforge' ),
			'type'        => Controls_Manager::SELECT,
			'options'     => Choices::queries(),
			'default'     => '',
			'description' => __( 'Built in LibreForge → Query Builder. Or leave empty and pick a post type below.', 'libreforge' ),
		] );

		$this->add_control( 'post_type', [
			'label'     => __( 'Post type', 'libreforge' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => Choices::post_types(),
			'default'   => '',
			'condition' => [ 'query_id' => '' ],
		] );

		$this->add_control( 'per_page', [
			'label'     => __( 'How many', 'libreforge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 6,
			'min'       => 1,
			'max'       => 100,
			'condition' => [ 'query_id' => '' ],
		] );

		$this->add_control( 'sort', [
			'label'     => __( 'Sort by', 'libreforge' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'date:DESC',
			'options'   => [
				'date:DESC'      => __( 'Newest first', 'libreforge' ),
				'date:ASC'       => __( 'Oldest first', 'libreforge' ),
				'title:ASC'      => __( 'Title A→Z', 'libreforge' ),
				'title:DESC'     => __( 'Title Z→A', 'libreforge' ),
				'menu_order:ASC' => __( 'Manual order', 'libreforge' ),
				'rand:DESC'      => __( 'Random', 'libreforge' ),
			],
			'condition' => [ 'query_id' => '' ],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'design_section', [ 'label' => __( 'Item design', 'libreforge' ) ] );

		$this->add_control( 'template_id', [
			'label'       => __( 'Item template', 'libreforge' ),
			'type'        => Controls_Manager::SELECT,
			'options'     => Choices::templates(),
			'default'     => '',
			'description' => __( 'The card repeated for every item. Create one in LibreForge → Listings.', 'libreforge' ),
		] );

		$this->add_responsive_control( 'columns', [
			'label'          => __( 'Columns', 'libreforge' ),
			'type'           => Controls_Manager::NUMBER,
			'min'            => 1,
			'max'            => 6,
			'default'        => 3,
			'tablet_default' => 2,
			'mobile_default' => 1,
		] );

		$this->add_control( 'gap', [
			'label'   => __( 'Space between items (px)', 'libreforge' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => 24,
			'min'     => 0,
			'max'     => 120,
		] );

		$this->add_control( 'pagination', [
			'label'   => __( 'Pagination', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'none',
			'options' => [
				'none'      => __( 'None', 'libreforge' ),
				'numbers'   => __( 'Page numbers', 'libreforge' ),
				'prev_next' => __( 'Previous / next', 'libreforge' ),
			],
		] );

		$this->add_control( 'empty_text', [
			'label'       => __( 'Message when empty', 'libreforge' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => __( 'Nothing found.', 'libreforge' ),
		] );

		$this->add_control( 'listing_id', [
			'label'       => __( 'Listing ID (for filters)', 'libreforge' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => 'imoveis',
			'description' => __( 'Optional. Give the same ID to the LibreSift filters that should control only this listing.', 'libreforge' ),
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		[ $orderby, $order ] = array_pad( explode( ':', (string) ( $s['sort'] ?? 'date:DESC' ) ), 2, 'DESC' );

		$this->output( $this->renderer()->loop( [
			'query_id'       => (int) ( $s['query_id'] ?? 0 ),
			'post_type'      => (string) ( $s['post_type'] ?? '' ),
			'per_page'       => (int) ( $s['per_page'] ?? 6 ),
			'orderby'        => $orderby,
			'order'          => $order,
			'template_id'    => (int) ( $s['template_id'] ?? 0 ),
			'columns'        => (int) ( $s['columns'] ?? 3 ),
			'columns_tablet' => (int) ( $s['columns_tablet'] ?? 2 ),
			'columns_mobile' => (int) ( $s['columns_mobile'] ?? 1 ),
			'gap'            => (int) ( $s['gap'] ?? 24 ),
			'pagination'     => (string) ( $s['pagination'] ?? 'none' ),
			'empty_text'     => (string) ( $s['empty_text'] ?? '' ),
			'listing_id'     => sanitize_key( (string) ( $s['listing_id'] ?? '' ) ),
			'instance'       => $this->get_id(),
		] ) );
	}
}
