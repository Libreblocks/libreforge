<?php
/**
 * Shortcodes for themes and page builders other than Gutenberg and Elementor.
 *
 *   [libreforge_loop query="12" template="34" columns="3" pagination="numbers"]
 *   [libreforge_loop post_type="depoimento" per_page="6" template="34"]
 *   [libreforge_field key="preco" source="meta" format="currency"]
 *   [libreforge_template id="34"]   (renders a template for the current item)
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Render;

use LibreBlocks\Forge\Templates\Templates;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {

	public function __construct( private Renderer $renderer, private Templates $templates ) {}

	public function hook(): void {
		add_shortcode( 'libreforge_loop', [ $this, 'loop' ] );
		add_shortcode( 'libreforge_field', [ $this, 'field' ] );
		add_shortcode( 'libreforge_template', [ $this, 'template' ] );
		add_shortcode( 'libreforge_repeater', [ $this, 'repeater' ] );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function loop( $atts ): string {
		$a = shortcode_atts( [
			'query'          => 0,
			'post_type'      => '',
			'per_page'       => 6,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'template'       => 0,
			'columns'        => 3,
			'columns_tablet' => 2,
			'columns_mobile' => 1,
			'gap'            => 24,
			'pagination'     => 'none',
			'empty'          => '',
			'id'             => '',
		], (array) $atts, 'libreforge_loop' );

		return $this->renderer->loop( [
			'query_id'       => absint( $a['query'] ),
			'post_type'      => sanitize_key( $a['post_type'] ),
			'per_page'       => (int) $a['per_page'],
			'orderby'        => sanitize_key( $a['orderby'] ),
			'order'          => strtoupper( (string) $a['order'] ),
			'template_id'    => absint( $a['template'] ),
			'columns'        => (int) $a['columns'],
			'columns_tablet' => (int) $a['columns_tablet'],
			'columns_mobile' => (int) $a['columns_mobile'],
			'gap'            => (int) $a['gap'],
			'pagination'     => sanitize_key( $a['pagination'] ),
			'empty_text'     => sanitize_text_field( $a['empty'] ),
			'listing_id'     => sanitize_key( $a['id'] ),
		] );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function field( $atts ): string {
		$a = shortcode_atts( [
			'source'      => 'meta',
			'key'         => 'title',
			'format'      => 'auto',
			'date_format' => '',
			'decimals'    => 0,
			'currency'    => 'R$',
			'before'      => '',
			'after'       => '',
			'fallback'    => '',
			'tag'         => 'span',
			'link'        => '',
			'item'        => 0,
		], (array) $atts, 'libreforge_field' );

		return $this->renderer->field( [
			'source'      => sanitize_key( $a['source'] ),
			'key'         => 'token' === $a['source'] ? (string) $a['key'] : sanitize_text_field( $a['key'] ),
			'format'      => sanitize_key( $a['format'] ),
			'date_format' => sanitize_text_field( $a['date_format'] ),
			'decimals'    => (int) $a['decimals'],
			'currency'    => sanitize_text_field( $a['currency'] ),
			'before'      => sanitize_text_field( $a['before'] ),
			'after'       => sanitize_text_field( $a['after'] ),
			'fallback'    => sanitize_text_field( $a['fallback'] ),
			'tag'         => sanitize_key( $a['tag'] ),
			'link'        => filter_var( $a['link'], FILTER_VALIDATE_BOOLEAN ),
			'object_id'   => absint( $a['item'] ),
		] );
	}

	/**
	 * [libreforge_repeater key="faq" layout="accordion" title="pergunta" single="yes" schema="yes"]
	 *
	 * @param array<string, string>|string $atts
	 */
	public function repeater( $atts ): string {
		$a = shortcode_atts( [
			'key'      => '',
			'source'   => 'meta',
			'item'     => 0,
			'layout'   => 'list',
			'title'    => '',
			'single'   => 'yes',
			'open'     => 'no',
			'icon'     => 'chevron',
			'position' => 'right',
			'schema'   => 'no',
			'tag'      => 'div',
		], (array) $atts, 'libreforge_repeater' );

		return $this->renderer->repeater( [
			'key'           => 'option' === $a['source'] ? sanitize_text_field( $a['key'] ) : sanitize_key( $a['key'] ),
			'source'        => 'option' === $a['source'] ? 'option' : 'meta',
			'object_id'     => absint( $a['item'] ),
			'layout'        => 'accordion' === $a['layout'] ? 'accordion' : 'list',
			'title_key'     => sanitize_key( $a['title'] ),
			'single_open'   => filter_var( $a['single'], FILTER_VALIDATE_BOOLEAN ),
			'open_first'    => filter_var( $a['open'], FILTER_VALIDATE_BOOLEAN ),
			'icon'          => sanitize_key( $a['icon'] ),
			'icon_position' => sanitize_key( $a['position'] ),
			'faq_schema'    => filter_var( $a['schema'], FILTER_VALIDATE_BOOLEAN ),
			'tag'           => sanitize_key( $a['tag'] ),
		] );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function template( $atts ): string {
		$a = shortcode_atts( [ 'id' => 0 ], (array) $atts, 'libreforge_template' );

		return $this->templates->render( absint( $a['id'] ) );
	}
}
