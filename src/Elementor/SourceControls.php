<?php
/**
 * The "Source + field" controls shared by widgets and dynamic tags.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

trait SourceControls {

	/**
	 * @param array<string, string>|null $sources     Allowed sources (defaults to all).
	 * @param string[]|null              $field_types Field types offered for "Custom field".
	 */
	protected function add_source_controls( ?array $sources = null, ?array $field_types = null, string $prefix = 'key' ): void {
		$sources = $sources ?? Choices::sources();

		$this->add_control( 'source', [
			'label'   => __( 'Source', 'libreforge' ),
			'type'    => Controls_Manager::SELECT,
			'options' => $sources,
			'default' => (string) array_key_first( $sources ),
		] );

		if ( isset( $sources['object'] ) ) {
			$this->add_control( $prefix . '_object', [
				'label'     => __( 'Data', 'libreforge' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => Choices::builtin(),
				'default'   => 'title',
				'condition' => [ 'source' => 'object' ],
			] );
		}

		$this->add_control( $prefix . '_meta', [
			'label'     => __( 'Custom field', 'libreforge' ),
			'type'      => Controls_Manager::SELECT,
			'options'   => Choices::fields( $field_types ),
			'default'   => '',
			'condition' => [ 'source' => 'meta' ],
		] );

		$this->add_control( $prefix . '_custom', [
			'label'     => __( 'Field name', 'libreforge' ),
			'type'      => Controls_Manager::TEXT,
			'condition' => [ 'source' => 'meta', $prefix . '_meta' => '__custom' ],
		] );

		if ( isset( $sources['option'] ) ) {
			$this->add_control( $prefix . '_option', [
				'label'     => __( 'Option', 'libreforge' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => Choices::options(),
				'default'   => '',
				'condition' => [ 'source' => 'option' ],
			] );
		}

		if ( isset( $sources['token'] ) ) {
			$this->add_control( $prefix . '_token', [
				'label'       => __( 'Text', 'libreforge' ),
				'type'        => Controls_Manager::TEXTAREA,
				'description' => __( 'Ex.: Hello {{user.meta|first_name}}!', 'libreforge' ),
				'condition'   => [ 'source' => 'token' ],
			] );
		}
	}
}
