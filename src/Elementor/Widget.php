<?php
/**
 * Base of the LibreForge Elementor widgets.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use LibreBlocks\Forge\Plugin;
use LibreBlocks\Forge\Render\Renderer;
use LibreBlocks\Forge\Templates\Templates;

defined( 'ABSPATH' ) || exit;

abstract class Widget extends \Elementor\Widget_Base {

	use SourceControls;

	public function get_categories(): array {
		return [ Integration::CATEGORY ];
	}

	protected function renderer(): Renderer {
		return Plugin::instance()->renderer();
	}

	/**
	 * In the Elementor editor of an item template there is no loop yet:
	 * show a real sample item so widgets display data.
	 *
	 * @param callable(): string $render
	 */
	protected function with_preview( callable $render ): string {
		$context = libreblocks()->context();
		$sample  = null;

		if ( 0 === $context->depth() && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$post = get_post();

			if ( $post && Templates::POST_TYPE === $post->post_type ) {
				$sample = libreblocks()->get( 'forge.templates' )->sample_object( $post->ID );
			}
		}

		if ( ! $sample ) {
			return $render();
		}

		return $context->with( $sample, $render );
	}

	/**
	 * Prints the HTML, or an editor-only hint when there is nothing to show.
	 */
	protected function output( string $html ): void {
		if ( '' === $html && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$html = '<div class="lf-notice">' . esc_html__( 'Nothing to show for this item yet.', 'libreforge' ) . '</div>';
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by the Renderer.
	}
}
