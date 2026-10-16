<?php
/**
 * Custom taxonomies.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model\Kinds;

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\FieldList;
use LibreBlocks\Forge\Model\Kind;
use LibreBlocks\Forge\Model\Reserved;

defined( 'ABSPATH' ) || exit;

final class TaxonomyKind extends Kind {

	public const ID = 'taxonomy';

	public function __construct( private FieldList $fields ) {}

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Taxonomies', 'libreforge' );
	}

	public function sanitize( array $input ): array {
		$public       = self::flag( $input, 'public', true );
		$hierarchical = self::flag( $input, 'hierarchical', true );

		return [
			'singular'             => self::text( $input, 'singular' ),
			'plural'               => self::text( $input, 'plural' ),
			'description'          => self::text( $input, 'description' ),
			'object_types'         => self::keys( $input, 'object_types' ),
			'public'               => $public,
			'publicly_queryable'   => self::flag( $input, 'publicly_queryable', $public ),
			'hierarchical'         => $hierarchical,
			'show_ui'              => self::flag( $input, 'show_ui', true ),
			'show_in_menu'         => self::flag( $input, 'show_in_menu', true ),
			'show_in_nav_menus'    => self::flag( $input, 'show_in_nav_menus', $public ),
			'show_in_rest'         => self::flag( $input, 'show_in_rest', true ),
			'rest_base'            => sanitize_key( self::text( $input, 'rest_base' ) ),
			'show_tagcloud'        => self::flag( $input, 'show_tagcloud', ! $hierarchical ),
			'show_in_quick_edit'   => self::flag( $input, 'show_in_quick_edit', true ),
			'show_admin_column'    => self::flag( $input, 'show_admin_column', true ),
			'rewrite_slug'         => sanitize_title( self::text( $input, 'rewrite_slug' ) ),
			'with_front'           => self::flag( $input, 'with_front', true ),
			'hierarchical_rewrite' => self::flag( $input, 'hierarchical_rewrite', false ),
			'labels'               => self::labels( $input ),
			// Term meta fields edited right in the taxonomy ("Custom fields" section).
			'fields'               => $this->fields->sanitize( $input['fields'] ?? [] ),
		];
	}

	public function suggest_slug( array $config ): string {
		return substr( sanitize_key( str_replace( '-', '_', sanitize_title( (string) ( $config['singular'] ?? '' ) ) ) ), 0, 32 );
	}

	public function validate( Definition $definition, DefinitionRepository $repository ): array {
		$errors = $this->validate_slug( $definition, $repository, 32 );

		if ( ! $errors && Reserved::is_reserved_taxonomy( $definition->slug ) ) {
			$errors['slug'] = __( 'This slug is reserved by WordPress. Choose another one.', 'libreforge' );
		}

		if ( ! $errors && taxonomy_exists( $definition->slug ) && ! $this->is_ours( $definition, $repository ) ) {
			$errors['slug'] = __( 'Another plugin or theme already registers a taxonomy with this slug.', 'libreforge' );
		}

		if ( '' === $definition->get( 'singular', '' ) ) {
			$errors['singular'] = __( 'The singular name is required.', 'libreforge' );
		}

		if ( '' === $definition->get( 'plural', '' ) ) {
			$errors['plural'] = __( 'The plural name is required.', 'libreforge' );
		}

		return $errors + $this->fields->validate( (array) $definition->get( 'fields', [] ) );
	}

	/**
	 * Arguments for register_taxonomy().
	 *
	 * @return array<string, mixed>
	 */
	public static function to_args( Definition $definition ): array {
		$c = $definition->config;

		$args = [
			'labels'             => Labels::taxonomy( (string) $c['singular'], (string) $c['plural'], (array) $c['labels'] ),
			'description'        => $c['description'],
			'public'             => $c['public'],
			'publicly_queryable' => $c['publicly_queryable'],
			'hierarchical'       => $c['hierarchical'],
			'show_ui'            => $c['show_ui'],
			'show_in_menu'       => $c['show_in_menu'],
			'show_in_nav_menus'  => $c['show_in_nav_menus'],
			'show_in_rest'       => $c['show_in_rest'],
			'show_tagcloud'      => $c['show_tagcloud'],
			'show_in_quick_edit' => $c['show_in_quick_edit'],
			'show_admin_column'  => $c['show_admin_column'],
			'rewrite'            => $c['public'] || $c['publicly_queryable']
				? [
					'slug'         => $c['rewrite_slug'] ?: $definition->slug,
					'with_front'   => (bool) $c['with_front'],
					'hierarchical' => (bool) $c['hierarchical_rewrite'],
				]
				: false,
		];

		if ( '' !== $c['rest_base'] ) {
			$args['rest_base'] = $c['rest_base'];
		}

		/**
		 * Filters the arguments of a LibreForge taxonomy.
		 *
		 * @param array<string, mixed> $args
		 * @param Definition           $definition
		 */
		return apply_filters( 'libreforge/taxonomy/args', $args, $definition );
	}
}
