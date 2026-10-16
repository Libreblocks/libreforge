<?php
/**
 * Elementor dynamic tag: an image from the current item (featured or an image field).
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;
use LibreBlocks\Forge\Render\Values;

defined( 'ABSPATH' ) || exit;

final class ImageTag extends Data_Tag {

	use SourceControls;

	public function get_name(): string {
		return 'libreforge-image';
	}

	public function get_title(): string {
		return __( 'LibreForge image', 'libreforge' );
	}

	public function get_group(): string {
		return 'libreforge';
	}

	public function get_categories(): array {
		return [ Module::IMAGE_CATEGORY ];
	}

	protected function register_controls(): void {
		$this->add_source_controls(
			[
				'featured' => __( 'Featured image', 'libreforge' ),
				'meta'     => __( 'Image field', 'libreforge' ),
				'option'   => __( 'Options page', 'libreforge' ),
			],
			[ 'media', 'gallery' ]
		);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{id: int, url: string}
	 */
	public function get_value( array $options = [] ): array {
		$s      = $this->get_settings();
		$source = (string) ( $s['source'] ?? 'featured' );
		$values = new Values( libreblocks()->context(), libreblocks()->tokens() );
		$value  = 'featured' === $source ? $values->get( 'object', 'featured_image' ) : $values->get( $source, Choices::key( $s ) );

		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		$id = (int) $value;

		return [ 'id' => $id, 'url' => $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '' ];
	}
}
