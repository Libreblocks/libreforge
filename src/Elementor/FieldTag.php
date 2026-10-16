<?php
/**
 * Elementor dynamic tag: any LibreForge value as text, in any Elementor widget.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use LibreBlocks\Forge\Plugin;

defined( 'ABSPATH' ) || exit;

final class FieldTag extends Tag {

	use SourceControls;

	public function get_name(): string {
		return 'libreforge-field';
	}

	public function get_title(): string {
		return __( 'LibreForge field', 'libreforge' );
	}

	public function get_group(): string {
		return 'libreforge';
	}

	public function get_categories(): array {
		return [ Module::TEXT_CATEGORY, Module::NUMBER_CATEGORY, Module::POST_META_CATEGORY, Module::COLOR_CATEGORY ];
	}

	protected function register_controls(): void {
		$this->add_source_controls();

		$this->add_control( 'format', [
			'label'   => __( 'Show as', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'options' => Choices::formats(),
			'default' => 'auto',
		] );
	}

	public function render(): void {
		$s = $this->get_settings();

		echo Plugin::instance()->renderer()->field( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the Renderer.
			'source' => (string) ( $s['source'] ?? 'object' ),
			'key'    => Choices::key( $s ),
			'format' => (string) ( $s['format'] ?? 'auto' ),
			'tag'    => 'span',
		] );
	}
}
