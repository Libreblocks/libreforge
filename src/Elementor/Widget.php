<?php
/**
 * Base of the LibreForge Elementor widgets.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use LibreBlocks\Forge\Plugin;
use LibreBlocks\Forge\Render\Renderer;

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
	 * Inside templates being designed, show a real item (see EditorPreview).
	 *
	 * @param callable(): string $render
	 */
	protected function with_preview( callable $render ): string {
		return (string) EditorPreview::wrap( $render );
	}

	/**
	 * Prints the HTML, or an editor-only hint when there is nothing to show.
	 */
	protected function output( string $html ): void {
		if ( '' === $html && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$hint = $this->renderer()->hint() ?: __( 'Nothing to show for this item yet.', 'libreforge' );
			$html = '<div class="lf-notice">' . esc_html( $hint ) . '</div>';
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by the Renderer.
	}
}
