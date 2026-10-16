<?php
/**
 * Turns a raw value into display HTML (escaped).
 *
 * Formats: auto, text, date, number, currency, label (option labels of a choice
 * field), html (rich text), autop (line breaks to paragraphs), trim (limit words),
 * image (attachment ID to <img>), url.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Render;

use LibreBlocks\Core\Fields\Field;

defined( 'ABSPATH' ) || exit;

final class Formatter {

	/**
	 * @param mixed                $value
	 * @param array<string, mixed> $settings format, date_format, decimals, dec_point, thousands_sep,
	 *                                       currency, words, separator, size.
	 */
	public static function format( $value, array $settings, ?Field $field = null ): string {
		$format = (string) ( $settings['format'] ?? 'auto' );

		if ( 'auto' === $format ) {
			$format = self::auto_format( $field );
		}

		if ( is_array( $value ) && ! in_array( $format, [ 'label', 'image' ], true ) ) {
			$separator = (string) ( $settings['separator'] ?? ', ' );

			return implode( esc_html( $separator ), array_map(
				fn( $item ) => self::format( $item, [ 'format' => $format ] + $settings, $field ),
				array_filter( $value, 'is_scalar' )
			) );
		}

		switch ( $format ) {
			case 'date':
				$timestamp = self::timestamp( $value );
				return null === $timestamp ? esc_html( (string) $value ) : esc_html( wp_date( (string) ( $settings['date_format'] ?? '' ) ?: get_option( 'date_format' ), $timestamp ) );

			case 'number':
				return is_numeric( $value )
					? esc_html( number_format( (float) $value, (int) ( $settings['decimals'] ?? 0 ), (string) ( $settings['dec_point'] ?? ',' ), (string) ( $settings['thousands_sep'] ?? '.' ) ) )
					: esc_html( (string) $value );

			case 'currency':
				return is_numeric( $value )
					? esc_html( trim( (string) ( $settings['currency'] ?? 'R$' ) ) . ' ' . number_format( (float) $value, (int) ( $settings['decimals'] ?? 2 ), (string) ( $settings['dec_point'] ?? ',' ), (string) ( $settings['thousands_sep'] ?? '.' ) ) )
					: esc_html( (string) $value );

			case 'label':
				return esc_html( implode( (string) ( $settings['separator'] ?? ', ' ), self::labels( (array) $value, $field ) ) );

			case 'html':
				return wp_kses_post( (string) $value );

			case 'autop':
				return wp_kses_post( wpautop( (string) $value ) );

			case 'trim':
				return esc_html( wp_trim_words( wp_strip_all_tags( (string) $value ), max( 1, (int) ( $settings['words'] ?? 20 ) ) ) );

			case 'image':
				$id = is_array( $value ) ? (int) reset( $value ) : (int) $value;
				return $id ? (string) wp_get_attachment_image( $id, (string) ( $settings['size'] ?? 'medium' ) ) : '';

			case 'url':
				return esc_url( (string) $value );

			case 'switch':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? esc_html__( 'Yes', 'libreforge' ) : esc_html__( 'No', 'libreforge' );
		}

		return esc_html( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Date fields store "Y-m-d" in the site's local time; reading them as UTC would
	 * show the previous day in timezones west of UTC (e.g. Brazil).
	 *
	 * @param mixed $value
	 */
	private static function timestamp( $value ): ?int {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		try {
			return ( new \DateTimeImmutable( (string) $value, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Option labels of a choice field ("sp" → "São Paulo").
	 *
	 * @param array<int, mixed> $values
	 * @return string[]
	 */
	public static function labels( array $values, ?Field $field ): array {
		$labels = [];

		foreach ( $values as $value ) {
			$label = (string) $value;

			foreach ( $field ? $field->options : [] as $option ) {
				if ( $option['value'] === (string) $value ) {
					$label = $option['label'];
				}
			}

			$labels[] = $label;
		}

		return $labels;
	}

	/**
	 * Sensible default per field type, so beginners get "São Paulo" instead of "sp"
	 * and a formatted date instead of "2026-10-16".
	 */
	private static function auto_format( ?Field $field ): string {
		if ( ! $field ) {
			return 'text';
		}

		switch ( $field->type ) {
			case 'select':
			case 'radio':
			case 'checkbox':
				return 'label';
			case 'date':
			case 'datetime':
				return 'date';
			case 'wysiwyg':
				return 'html';
			case 'textarea':
				return 'autop';
			case 'media':
				return 'image';
			case 'switch':
				return 'switch';
			case 'url':
				return 'url';
		}

		return 'text';
	}
}
