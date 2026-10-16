<?php
/**
 * Option lists for Elementor select controls, built once per request.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use LibreBlocks\Forge\Rest\BlocksController;

defined( 'ABSPATH' ) || exit;

final class Choices {

	/** @var array<string, mixed>|null */
	private static ?array $data = null;

	/**
	 * @return array<string, mixed>
	 */
	private static function data(): array {
		if ( null === self::$data ) {
			self::$data = BlocksController::collect();
		}

		return self::$data;
	}

	/**
	 * @param array<int, array{value: mixed, label: string}> $items
	 * @return array<string, string> value => label, as Elementor expects.
	 */
	private static function map( array $items, string $empty = '' ): array {
		$options = '' === $empty ? [] : [ '' => $empty ];

		foreach ( $items as $item ) {
			$options[ (string) $item['value'] ] = (string) $item['label'];
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	public static function queries(): array {
		return self::map( self::data()['queries'], __( '— Simple mode (post type) —', 'libreforge' ) );
	}

	/**
	 * @return array<string, string>
	 */
	public static function templates(): array {
		return self::map( self::data()['templates'], __( '— Only the title —', 'libreforge' ) );
	}

	/**
	 * @return array<string, string>
	 */
	public static function post_types(): array {
		return self::map( self::data()['post_types'], __( '— Choose —', 'libreforge' ) );
	}

	/**
	 * @return array<string, string>
	 */
	public static function taxonomies(): array {
		return self::map( self::data()['taxonomies'] );
	}

	/**
	 * @param string[]|null $types Field types to keep (e.g. [ 'media' ]).
	 * @return array<string, string>
	 */
	public static function fields( ?array $types = null ): array {
		$items = array_filter( self::data()['fields'], fn( $field ) => ! $types || in_array( $field['type'], $types, true ) );
		$items = array_map( fn( $field ) => [ 'value' => $field['value'], 'label' => $field['label'] . ' (' . $field['value'] . ')' ], $items );

		return self::map( array_values( $items ), __( '— Choose —', 'libreforge' ) ) + [ '__custom' => __( 'Other (type the name)…', 'libreforge' ) ];
	}

	/**
	 * Items of the post types that have LibreForge fields, for "From which item".
	 *
	 * @return array<string, string>
	 */
	public static function items(): array {
		$types = [];

		foreach ( self::data()['fields'] as $field ) {
			if ( 'post' === $field['object'] ) {
				$types = array_merge( $types, (array) $field['where'] );
			}
		}

		$options = [ '' => __( 'The current item (page or listing)', 'libreforge' ) ];

		foreach ( array_unique( $types ) as $type ) {
			$object = get_post_type_object( $type );

			foreach ( get_posts( [ 'post_type' => $type, 'posts_per_page' => 50, 'orderby' => 'title', 'order' => 'ASC' ] ) as $post ) {
				$options[ (string) $post->ID ] = sprintf( '%s — %s', get_the_title( $post ) ?: '#' . $post->ID, $object ? $object->labels->singular_name : $type );
			}
		}

		return $options;
	}

	/**
	 * Sub-fields of every repeater ("faq → Question (question)"), for the accordion title.
	 *
	 * @return array<string, string>
	 */
	public static function repeater_children(): array {
		$options = [ '' => __( 'The first sub-field (automatic)', 'libreforge' ) ];

		foreach ( self::data()['fields'] as $field ) {
			foreach ( $field['children'] ?? [] as $child ) {
				$options[ (string) $child['value'] ] = sprintf( '%s → %s (%s)', $field['label'], $child['label'], $child['value'] );
			}
		}

		return $options;
	}

	/**
	 * @return array<string, string>
	 */
	public static function options(): array {
		return self::map( self::data()['options'], __( '— Choose —', 'libreforge' ) );
	}

	/**
	 * @return array<string, string>
	 */
	public static function sizes(): array {
		return self::map( self::data()['sizes'] );
	}

	/**
	 * @return array<string, string>
	 */
	public static function builtin(): array {
		return [
			'title'         => __( 'Title / name', 'libreforge' ),
			'excerpt'       => __( 'Excerpt / description', 'libreforge' ),
			'content'       => __( 'Full content', 'libreforge' ),
			'date'          => __( 'Publication date', 'libreforge' ),
			'modified'      => __( 'Last update', 'libreforge' ),
			'author'        => __( 'Author name', 'libreforge' ),
			'permalink'     => __( 'Address (URL)', 'libreforge' ),
			'post_type'     => __( 'Content type', 'libreforge' ),
			'comment_count' => __( 'Number of comments', 'libreforge' ),
			'count'         => __( 'Number of items (terms)', 'libreforge' ),
			'avatar_url'    => __( 'Avatar URL (users)', 'libreforge' ),
			'id'            => __( 'ID', 'libreforge' ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function sources(): array {
		return [
			'object' => __( 'Item data (title, date…)', 'libreforge' ),
			'meta'   => __( 'Custom field', 'libreforge' ),
			'option' => __( 'Options page', 'libreforge' ),
			'token'  => __( 'Text with tokens', 'libreforge' ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	public static function formats(): array {
		return [
			'auto'     => __( 'Automatic (recommended)', 'libreforge' ),
			'text'     => __( 'Plain text', 'libreforge' ),
			'date'     => __( 'Date', 'libreforge' ),
			'number'   => __( 'Number', 'libreforge' ),
			'currency' => __( 'Money', 'libreforge' ),
			'label'    => __( 'Option label', 'libreforge' ),
			'trim'     => __( 'Short text (limit words)', 'libreforge' ),
			'html'     => __( 'Formatted text (HTML)', 'libreforge' ),
			'image'    => __( 'Image', 'libreforge' ),
		];
	}

	/**
	 * Resolves the key chosen in the "source + key" controls of widgets and tags.
	 *
	 * @param array<string, mixed> $settings
	 */
	public static function key( array $settings, string $prefix = 'key' ): string {
		switch ( $settings['source'] ?? 'object' ) {
			case 'meta':
				$key = (string) ( $settings[ $prefix . '_meta' ] ?? '' );
				return '__custom' === $key ? sanitize_key( (string) ( $settings[ $prefix . '_custom' ] ?? '' ) ) : $key;
			case 'option':
				return (string) ( $settings[ $prefix . '_option' ] ?? '' );
			case 'token':
				return (string) ( $settings[ $prefix . '_token' ] ?? '' );
			default:
				return (string) ( $settings[ $prefix . '_object' ] ?? 'title' );
		}
	}
}
