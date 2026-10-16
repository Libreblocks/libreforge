<?php
/**
 * Elementor dynamic tag: a URL from the current item or a field (for buttons and links).
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;
use LibreBlocks\Forge\Render\Values;

defined( 'ABSPATH' ) || exit;

final class UrlTag extends Data_Tag {

	use SourceControls;

	public function get_name(): string {
		return 'libreforge-url';
	}

	public function get_title(): string {
		return __( 'LibreForge link', 'libreforge' );
	}

	public function get_group(): string {
		return 'libreforge';
	}

	public function get_categories(): array {
		return [ Module::URL_CATEGORY ];
	}

	protected function register_controls(): void {
		$this->add_source_controls( [
			'object' => __( 'Item data (title, date…)', 'libreforge' ),
			'meta'   => __( 'Custom field', 'libreforge' ),
			'option' => __( 'Options page', 'libreforge' ),
			'token'  => __( 'Text with tokens', 'libreforge' ),
		], [ 'url', 'email', 'text', 'media' ] );
	}

	/**
	 * @param array<string, mixed> $options
	 */
	public function get_value( array $options = [] ): string {
		$s      = $this->get_settings();
		$source = (string) ( $s['source'] ?? 'object' );
		$key    = Choices::key( $s );

		// "Item data" in a link means the item address.
		if ( 'object' === $source && ! in_array( $key, [ 'permalink', 'avatar_url', 'featured_image_url' ], true ) ) {
			$key = 'permalink';
		}

		$value = ( new Values( libreblocks()->context(), libreblocks()->tokens() ) )->get( $source, $key );

		if ( is_numeric( $value ) && 'meta' === $source ) {
			$value = (string) wp_get_attachment_url( (int) $value ); // An attachment field links to the file.
		}

		if ( is_email( (string) $value ) ) {
			$value = 'mailto:' . $value;
		}

		return esc_url_raw( (string) $value );
	}
}
