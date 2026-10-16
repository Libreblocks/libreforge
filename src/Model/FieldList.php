<?php
/**
 * Cleans and validates the list of field configs edited in the field builder
 * (used by field groups and options pages).
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model;

use LibreBlocks\Core\Fields\Field;
use LibreBlocks\Core\Fields\Registry;

defined( 'ABSPATH' ) || exit;

final class FieldList {

	/** Meta keys owned by WordPress core that a field must never overwrite. */
	private const PROTECTED_KEYS = [ '_edit_lock', '_edit_last', '_thumbnail_id', '_wp_page_template', '_wp_old_slug', 'session_tokens' ];

	/** Arguments that may contain markup (e.g. the content of an "html" field). */
	private const HTML_ARGS = [ 'content' ];

	public function __construct( private Registry $fields ) {}

	/**
	 * @param mixed $input
	 * @return array<int, array<string, mixed>>
	 */
	public function sanitize( $input ): array {
		$clean = [];

		foreach ( is_array( $input ) ? $input : [] as $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}

			$config['name'] = str_replace( '-', '_', (string) ( $config['name'] ?? '' ) );
			$field          = Field::from_array( $config )->to_array();

			$field['label']       = sanitize_text_field( (string) $field['label'] );
			$field['description'] = wp_kses_post( (string) $field['description'] );
			$field['width']       = in_array( $field['width'], [ '100%', '75%', '66%', '50%', '33%', '25%' ], true ) ? $field['width'] : '100%';
			$field['default']     = $this->scalar_tree( $field['default'] );
			$field['fields']      = $this->sanitize( $config['fields'] ?? [] );
			$field['options']     = array_map( fn( array $option ) => [
				'value' => sanitize_text_field( $option['value'] ),
				'label' => sanitize_text_field( $option['label'] ),
			], $field['options'] );

			foreach ( array_keys( $field ) as $key ) {
				if ( ! in_array( $key, [ 'name', 'type', 'label', 'description', 'required', 'default', 'options', 'glossary', 'conditions', 'width', 'fields' ], true ) ) {
					$field[ $key ] = in_array( $key, self::HTML_ARGS, true ) ? wp_kses_post( (string) $field[ $key ] ) : $this->scalar_tree( $field[ $key ] );
				}
			}

			$field['conditions'] = $this->sanitize_conditions( $field['conditions'] );

			$clean[] = $field;
		}

		return $clean;
	}

	/**
	 * @param array<int, array<string, mixed>> $configs
	 * @return array<string, string> "fields.<index>[.fields.<index>]" => message
	 */
	public function validate( array $configs, string $path = 'fields' ): array {
		$errors = [];
		$names  = [];

		foreach ( $configs as $index => $config ) {
			$key  = $path . '.' . $index;
			$type = (string) $config['type'];

			if ( ! $this->fields->has( $type ) ) {
				/* translators: %s: field type */
				$errors[ $key ] = sprintf( __( 'Unknown field type "%s".', 'libreforge' ), $type );
				continue;
			}

			if ( ! $this->fields->get( $type )->stores_value() ) {
				continue;
			}

			$name  = (string) $config['name'];
			$label = $config['label'] ?: $name;

			if ( '' === $name ) {
				/* translators: %s: field label */
				$errors[ $key ] = sprintf( __( 'The field "%s" needs a name.', 'libreforge' ), $label );
			} elseif ( isset( $names[ $name ] ) ) {
				/* translators: %s: field name */
				$errors[ $key ] = sprintf( __( 'The name "%s" is used by more than one field.', 'libreforge' ), $name );
			} elseif ( in_array( $name, self::PROTECTED_KEYS, true ) || 0 === strpos( $name, '_wp_' ) ) {
				/* translators: %s: field name */
				$errors[ $key ] = sprintf( __( 'The name "%s" is reserved by WordPress.', 'libreforge' ), $name );
			}

			$names[ $name ] = true;

			if ( in_array( $type, [ 'repeater', 'group' ], true ) ) {
				$errors += $this->validate( $config['fields'], $key . '.fields' );
			}
		}

		return $errors;
	}

	/**
	 * Names of every top-level field that stores a value.
	 *
	 * @param array<int, array<string, mixed>> $configs
	 * @return string[]
	 */
	public function storing_names( array $configs ): array {
		$names = [];

		foreach ( $configs as $config ) {
			if ( $this->fields->has( $config['type'] ) && $this->fields->get( $config['type'] )->stores_value() && '' !== $config['name'] ) {
				$names[] = $config['name'];
			}
		}

		return $names;
	}

	/**
	 * @param mixed $conditions
	 * @return array<string, mixed>
	 */
	private function sanitize_conditions( $conditions ): array {
		$rules = [];

		foreach ( (array) ( is_array( $conditions ) ? ( $conditions['rules'] ?? [] ) : [] ) as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['field'] ) ) {
				continue;
			}

			$operator = (string) ( $rule['operator'] ?? '=' );

			$rules[] = [
				'field'    => sanitize_key( (string) $rule['field'] ),
				'operator' => in_array( $operator, \LibreBlocks\Core\Fields\Conditions::OPERATORS, true ) ? $operator : '=',
				'value'    => $this->scalar_tree( $rule['value'] ?? '' ),
			];
		}

		if ( ! $rules ) {
			return [];
		}

		return [
			'relation' => 'OR' === strtoupper( (string) ( $conditions['relation'] ?? 'AND' ) ) ? 'OR' : 'AND',
			'rules'    => $rules,
		];
	}

	/**
	 * Keeps booleans, numbers and text (sanitized), recursively.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function scalar_tree( $value ) {
		if ( is_array( $value ) ) {
			return array_map( [ $this, 'scalar_tree' ], $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : null;
	}
}
