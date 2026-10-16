<?php
/**
 * Short, escaped HTML of a field value for admin list columns.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Fields;

use LibreBlocks\Core\Fields\Field;

defined( 'ABSPATH' ) || exit;

final class ColumnValue {

	private const EMPTY = '<span aria-hidden="true">—</span>';

	/**
	 * @param mixed $value
	 */
	public static function render( Field $field, $value ): string {
		if ( null === $value || '' === $value || [] === $value ) {
			return self::EMPTY;
		}

		switch ( $field->type ) {
			case 'switch':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? '<span class="dashicons dashicons-yes" aria-label="' . esc_attr__( 'Yes', 'libreforge' ) . '"></span>' : self::EMPTY;

			case 'media':
				return wp_get_attachment_image( (int) $value, [ 40, 40 ] ) ?: self::EMPTY;

			case 'gallery':
				$ids = array_slice( array_map( 'absint', (array) $value ), 0, 3 );
				return implode( ' ', array_map( fn( $id ) => wp_get_attachment_image( $id, [ 40, 40 ] ), $ids ) ) ?: self::EMPTY;

			case 'color':
				return sprintf( '<span style="display:inline-block;width:18px;height:18px;border-radius:4px;vertical-align:middle;background:%1$s" title="%1$s"></span>', esc_attr( (string) $value ) );

			case 'select':
			case 'radio':
			case 'checkbox':
				$labels = [];

				foreach ( (array) $value as $item ) {
					$label = (string) $item;

					foreach ( $field->options as $option ) {
						if ( $option['value'] === (string) $item ) {
							$label = $option['label'];
						}
					}

					$labels[] = $label;
				}

				return esc_html( implode( ', ', $labels ) );

			case 'post_picker':
				return esc_html( implode( ', ', array_map( fn( $id ) => get_the_title( (int) $id ), (array) $value ) ) );

			case 'term_picker':
				return esc_html( implode( ', ', array_filter( array_map( function ( $id ) {
					$term = get_term( (int) $id );
					return $term instanceof \WP_Term ? $term->name : '';
				}, (array) $value ) ) ) );

			case 'user_picker':
				return esc_html( implode( ', ', array_filter( array_map( function ( $id ) {
					$user = get_userdata( (int) $id );
					return $user ? $user->display_name : '';
				}, (array) $value ) ) ) );

			case 'repeater':
				/* translators: %d: number of items */
				return esc_html( sprintf( _n( '%d item', '%d items', count( (array) $value ), 'libreforge' ), count( (array) $value ) ) );

			case 'wysiwyg':
			case 'textarea':
				return esc_html( wp_trim_words( wp_strip_all_tags( (string) $value ), 12 ) );
		}

		return is_scalar( $value ) ? esc_html( (string) $value ) : self::EMPTY;
	}
}
