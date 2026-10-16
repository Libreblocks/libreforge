<?php
/**
 * Custom post types.
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

final class PostTypeKind extends Kind {

	public const ID = 'post_type';

	public const SUPPORTS = [ 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'comments', 'revisions', 'page-attributes', 'custom-fields', 'trackbacks', 'post-formats' ];

	public function __construct( private FieldList $fields ) {}

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Post Types', 'libreforge' );
	}

	public function sanitize( array $input ): array {
		$public = self::flag( $input, 'public', true );

		$position = $input['menu_position'] ?? null;

		return [
			'singular'            => self::text( $input, 'singular' ),
			'plural'              => self::text( $input, 'plural' ),
			'description'         => self::text( $input, 'description' ),
			'public'              => $public,
			'publicly_queryable'  => self::flag( $input, 'publicly_queryable', $public ),
			'exclude_from_search' => self::flag( $input, 'exclude_from_search', ! $public ),
			'show_ui'             => self::flag( $input, 'show_ui', true ),
			'show_in_menu'        => self::flag( $input, 'show_in_menu', true ),
			'show_in_nav_menus'   => self::flag( $input, 'show_in_nav_menus', $public ),
			'show_in_rest'        => self::flag( $input, 'show_in_rest', true ),
			'rest_base'           => sanitize_key( self::text( $input, 'rest_base' ) ),
			'hierarchical'        => self::flag( $input, 'hierarchical', false ),
			'has_archive'         => self::flag( $input, 'has_archive', true ),
			'archive_slug'        => sanitize_title( self::text( $input, 'archive_slug' ) ),
			'rewrite_slug'        => sanitize_title( self::text( $input, 'rewrite_slug' ) ),
			'with_front'          => self::flag( $input, 'with_front', true ),
			'supports'            => self::keys( $input, 'supports', self::SUPPORTS, [ 'title', 'editor', 'thumbnail' ] ),
			'taxonomies'          => self::keys( $input, 'taxonomies' ),
			'menu_icon'           => self::icon( self::text( $input, 'menu_icon', 'dashicons-admin-post' ) ),
			'menu_position'       => is_numeric( $position ) ? (int) $position : null,
			'capability_type'     => sanitize_key( self::text( $input, 'capability_type', 'post' ) ) ?: 'post',
			'delete_with_user'    => self::flag( $input, 'delete_with_user', false ),
			'labels'              => self::labels( $input ),
			// Meta fields edited right in the post type ("Custom fields" section).
			'fields'              => $this->fields->sanitize( $input['fields'] ?? [] ),
			'fields_title'        => self::text( $input, 'fields_title' ),
			'fields_context'      => 'side' === ( $input['fields_context'] ?? '' ) ? 'side' : 'normal',
			// Item template used as the content of each item's own page (0 = theme default).
			'single_template'     => absint( $input['single_template'] ?? 0 ),
		];
	}

	public function suggest_slug( array $config ): string {
		return substr( sanitize_key( str_replace( '-', '_', sanitize_title( (string) ( $config['singular'] ?? '' ) ) ) ), 0, 20 );
	}

	public function validate( Definition $definition, DefinitionRepository $repository ): array {
		$errors = $this->validate_slug( $definition, $repository, 20 );

		if ( ! $errors && Reserved::is_reserved_post_type( $definition->slug ) ) {
			$errors['slug'] = __( 'This slug is reserved by WordPress. Choose another one.', 'libreforge' );
		}

		if ( ! $errors && post_type_exists( $definition->slug ) && ! $this->is_ours( $definition, $repository ) ) {
			$errors['slug'] = __( 'Another plugin or theme already registers a post type with this slug.', 'libreforge' );
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
	 * Arguments for register_post_type().
	 *
	 * @return array<string, mixed>
	 */
	public static function to_args( Definition $definition ): array {
		$c = $definition->config;

		$rewrite = $c['public'] || $c['publicly_queryable']
			? [ 'slug' => $c['rewrite_slug'] ?: $definition->slug, 'with_front' => (bool) $c['with_front'] ]
			: false;

		$args = [
			'labels'              => Labels::post_type( (string) $c['singular'], (string) $c['plural'], (array) $c['labels'] ),
			'description'         => $c['description'],
			'public'              => $c['public'],
			'publicly_queryable'  => $c['publicly_queryable'],
			'exclude_from_search' => $c['exclude_from_search'],
			'show_ui'             => $c['show_ui'],
			'show_in_menu'        => $c['show_in_menu'],
			'show_in_nav_menus'   => $c['show_in_nav_menus'],
			'show_in_rest'        => $c['show_in_rest'],
			'hierarchical'        => $c['hierarchical'],
			'has_archive'         => $c['has_archive'] ? ( $c['archive_slug'] ?: true ) : false,
			'rewrite'             => $rewrite,
			'supports'            => $c['supports'] ?: false,
			'taxonomies'          => $c['taxonomies'],
			'menu_icon'           => $c['menu_icon'],
			'capability_type'     => $c['capability_type'],
			'map_meta_cap'        => true,
			'delete_with_user'    => $c['delete_with_user'],
		];

		if ( null !== $c['menu_position'] ) {
			$args['menu_position'] = $c['menu_position'];
		}

		if ( '' !== $c['rest_base'] ) {
			$args['rest_base'] = $c['rest_base'];
		}

		/**
		 * Filters the arguments of a LibreForge post type.
		 *
		 * @param array<string, mixed> $args
		 * @param Definition           $definition
		 */
		return apply_filters( 'libreforge/post_type/args', $args, $definition );
	}

	/**
	 * Dashicon class, or an SVG/base64 data URI or URL as accepted by register_post_type().
	 */
	private static function icon( string $icon ): string {
		if ( preg_match( '/^dashicons-[a-z0-9-]+$/', $icon ) || 0 === strpos( $icon, 'data:image/svg+xml;base64,' ) ) {
			return $icon;
		}

		return esc_url_raw( $icon ) ?: 'dashicons-admin-post';
	}
}
