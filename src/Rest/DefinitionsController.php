<?php
/**
 * CRUD of definitions for the admin app.
 *
 *   GET    libreblocks/v1/forge/definitions?kind=post_type
 *   POST   libreblocks/v1/forge/definitions          { kind, slug?, status?, config }
 *   GET    libreblocks/v1/forge/definitions/<id>
 *   PUT    libreblocks/v1/forge/definitions/<id>     { slug?, status?, config }
 *   DELETE libreblocks/v1/forge/definitions/<id>
 *   GET    libreblocks/v1/forge/environment          data for the editors' selectors
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Rest;

use LibreBlocks\Core\Rest\Controller;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\Kind;
use LibreBlocks\Forge\Model\Kinds\FieldGroupKind;
use LibreBlocks\Forge\Model\Kinds\GlossaryKind;
use LibreBlocks\Forge\Model\Kinds\OptionsPageKind;
use LibreBlocks\Forge\Model\Kinds\PostTypeKind;
use LibreBlocks\Forge\Model\Kinds\QueryKind;
use LibreBlocks\Forge\Model\Kinds\RelationKind;
use LibreBlocks\Forge\Model\Kinds\TaxonomyKind;
use LibreBlocks\Forge\Modules\Catalog;
use LibreBlocks\Forge\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class DefinitionsController extends Controller {

	public function register_routes(): void {
		$this->route( '/forge/definitions', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'can_manage' ],
				'args'                => [ 'kind' => [ 'type' => 'string', 'required' => true ] ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
		] );

		$this->route( '/forge/definitions/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'destroy' ],
				'permission_callback' => [ $this, 'can_manage' ],
			],
		] );

		$this->route( '/forge/overview', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'overview' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		$this->route( '/forge/queries/preview', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'preview_query' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );

		$this->route( '/forge/environment', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'environment' ],
			'permission_callback' => [ $this, 'can_manage' ],
		] );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function index( WP_REST_Request $request ) {
		$kind = $this->kind( (string) $request['kind'] );

		if ( ! $kind ) {
			return $this->error( 'unknown_kind', __( 'Unknown definition kind.', 'libreforge' ) );
		}

		return new WP_REST_Response( array_map(
			fn( Definition $definition ) => $definition->to_array() + [ 'summary' => $this->summary( $definition ) ],
			$this->forge()->repository()->all( $kind->id() )
		) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$definition = $this->forge()->repository()->find( (int) $request['id'] );

		return $definition ? new WP_REST_Response( $definition->to_array() ) : $this->not_found();
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$kind = $this->kind( (string) $request['kind'] );

		if ( ! $kind ) {
			return $this->error( 'unknown_kind', __( 'Unknown definition kind.', 'libreforge' ) );
		}

		$config = $kind->sanitize( (array) $request['config'] );
		$slug   = $kind->sanitize_slug( (string) $request['slug'] ) ?: $kind->suggest_slug( $config );

		return $this->persist( $kind, new Definition( $kind->id(), $slug, $config, $this->status( $request ) ), 201 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function update( WP_REST_Request $request ) {
		$definition = $this->forge()->repository()->find( (int) $request['id'] );
		$kind       = $definition ? $this->kind( $definition->kind ) : null;

		if ( ! $definition || ! $kind ) {
			return $this->not_found();
		}

		if ( $request->has_param( 'config' ) ) {
			$definition->config = $kind->sanitize( (array) $request['config'] );
		}

		if ( $request->has_param( 'slug' ) ) {
			$definition->slug = $kind->sanitize_slug( (string) $request['slug'] );
		}

		if ( $request->has_param( 'status' ) ) {
			$definition->status = $this->status( $request );
		}

		return $this->persist( $kind, $definition, 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy( WP_REST_Request $request ) {
		$definition = $this->forge()->repository()->find( (int) $request['id'] );

		if ( ! $definition ) {
			return $this->not_found();
		}

		$this->forge()->repository()->delete( $definition );

		return new WP_REST_Response( [ 'deleted' => true, 'id' => $definition->id ] );
	}

	/**
	 * Runs an unsaved query config and returns the first items, for the editor preview.
	 */
	public function preview_query( WP_REST_Request $request ): WP_REST_Response {
		$config = $this->forge()->kinds()[ QueryKind::ID ]->sanitize( (array) $request['config'] );
		$result = $this->forge()->queries()->run( array_merge( $config, [ 'per_page' => min( 10, max( 1, (int) $config['per_page'] ) ) ] ) );
		$items  = [];

		foreach ( $result->items as $item ) {
			if ( $item instanceof \WP_Post ) {
				$items[] = [ 'id' => $item->ID, 'label' => get_the_title( $item ) ?: '#' . $item->ID, 'meta' => get_post_type_object( $item->post_type )->labels->singular_name ?? $item->post_type ];
			} elseif ( $item instanceof \WP_Term ) {
				$items[] = [ 'id' => $item->term_id, 'label' => $item->name, 'meta' => $item->taxonomy ];
			} elseif ( $item instanceof \WP_User ) {
				$items[] = [ 'id' => $item->ID, 'label' => $item->display_name, 'meta' => implode( ', ', $item->roles ) ];
			}
		}

		return new WP_REST_Response( [
			'total' => $result->total,
			'items' => $items,
			'args'  => $this->forge()->queries()->build_args( $config ),
		] );
	}

	/**
	 * Counts per kind and the module catalog, for the dashboard.
	 */
	public function overview(): WP_REST_Response {
		$counts = [];

		foreach ( array_keys( $this->forge()->kinds() ) as $kind ) {
			$all             = $this->forge()->repository()->all( $kind );
			$counts[ $kind ] = [
				'total'  => count( $all ),
				'active' => count( array_filter( $all, fn( Definition $definition ) => $definition->is_active() ) ),
			];
		}

		$recent = [];

		foreach ( $this->forge()->repository()->active( PostTypeKind::ID ) as $type ) {
			$recent[] = [
				'id'    => $type->id,
				'title' => $type->title(),
				'icon'  => (string) $type->get( 'menu_icon' ),
				'items' => $this->post_count( $type->slug ),
				'url'   => admin_url( 'edit.php?post_type=' . $type->slug ),
			];
		}

		return new WP_REST_Response( [
			'counts'     => $counts,
			'post_types' => $recent,
			'modules'    => Catalog::all(),
		] );
	}

	/**
	 * Numbers shown on each card of the lists.
	 *
	 * @return array<string, mixed>
	 */
	private function summary( Definition $definition ): array {
		$repository = $this->forge()->repository();

		switch ( $definition->kind ) {
			case PostTypeKind::ID:
				$taxonomies = (array) $definition->get( 'taxonomies', [] );

				foreach ( $repository->active( TaxonomyKind::ID ) as $taxonomy ) {
					if ( in_array( $definition->slug, (array) $taxonomy->get( 'object_types', [] ), true ) ) {
						$taxonomies[] = $taxonomy->slug;
					}
				}

				$groups = array_filter(
					$repository->active( FieldGroupKind::ID ),
					fn( Definition $group ) => 'post' === $group->get( 'object' ) && in_array( $definition->slug, (array) $group->get( 'post_types', [] ), true )
				);

				return [
					'fields'       => count( (array) $definition->get( 'fields', [] ) ),
					'items'        => $this->post_count( $definition->slug ),
					'taxonomies'   => count( array_unique( $taxonomies ) ),
					'field_groups' => count( $groups ),
					'list_url'     => post_type_exists( $definition->slug ) ? admin_url( 'edit.php?post_type=' . $definition->slug ) : '',
				];

			case TaxonomyKind::ID:
				$terms = taxonomy_exists( $definition->slug ) ? wp_count_terms( [ 'taxonomy' => $definition->slug, 'hide_empty' => false ] ) : 0;

				return [
					'fields'       => count( (array) $definition->get( 'fields', [] ) ),
					'terms'        => is_wp_error( $terms ) ? 0 : (int) $terms,
					'object_types' => (array) $definition->get( 'object_types', [] ),
				];

			case FieldGroupKind::ID:
			case OptionsPageKind::ID:
				return [ 'fields' => count( (array) $definition->get( 'fields', [] ) ) ];

			case GlossaryKind::ID:
				return [ 'options' => count( (array) $definition->get( 'options', [] ) ) ];
		}

		return [];
	}

	private function post_count( string $post_type ): int {
		if ( ! post_type_exists( $post_type ) ) {
			return 0;
		}

		$counts = (array) wp_count_posts( $post_type );

		return (int) array_sum( array_intersect_key( $counts, array_flip( [ 'publish', 'draft', 'pending', 'future', 'private' ] ) ) );
	}

	public function environment(): WP_REST_Response {
		$post_types = [];

		foreach ( get_post_types( [ 'show_ui' => true ], 'objects' ) as $type ) {
			// Item templates are LibreForge's own building blocks, not content.
			if ( \LibreBlocks\Forge\Templates\Templates::POST_TYPE !== $type->name ) {
				$post_types[] = [ 'value' => $type->name, 'label' => $type->labels->singular_name ?: $type->label ];
			}
		}

		$taxonomies = [];

		foreach ( get_taxonomies( [ 'show_ui' => true ], 'objects' ) as $taxonomy ) {
			$taxonomies[] = [ 'value' => $taxonomy->name, 'label' => $taxonomy->labels->singular_name ?: $taxonomy->label ];
		}

		$roles = [];

		foreach ( wp_roles()->get_names() as $value => $label ) {
			$roles[] = [ 'value' => $value, 'label' => translate_user_role( $label ) ];
		}

		$glossaries = array_map(
			fn( Definition $glossary ) => [ 'value' => $glossary->slug, 'label' => $glossary->title() ],
			$this->forge()->repository()->active( GlossaryKind::ID )
		);

		$parents = [
			[ 'value' => '', 'label' => __( 'Own top-level menu', 'libreforge' ) ],
			[ 'value' => 'libreforge', 'label' => __( 'LibreForge menu', 'libreforge' ) ],
			[ 'value' => 'options-general.php', 'label' => __( 'Settings', 'libreforge' ) ],
			[ 'value' => 'tools.php', 'label' => __( 'Tools', 'libreforge' ) ],
			[ 'value' => 'themes.php', 'label' => __( 'Appearance', 'libreforge' ) ],
			[ 'value' => 'users.php', 'label' => __( 'Users', 'libreforge' ) ],
		];

		foreach ( $post_types as $type ) {
			if ( ! in_array( $type['value'], [ 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ], true ) ) {
				/* translators: %s: post type name */
				$parents[] = [ 'value' => 'edit.php?post_type=' . $type['value'], 'label' => sprintf( __( 'Menu of: %s', 'libreforge' ), $type['label'] ) ];
			}
		}

		$queries = array_map(
			fn( Definition $query ) => [ 'value' => $query->id, 'label' => $query->title(), 'type' => $query->get( 'type' ) ],
			$this->forge()->repository()->active( QueryKind::ID )
		);

		// Objects a relation can connect: each post type, each taxonomy, users.
		$objects = [];

		foreach ( $post_types as $type ) {
			if ( ! in_array( $type['value'], [ 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ], true ) ) {
				$objects[] = [ 'value' => 'post:' . $type['value'], 'label' => $type['label'] ];
			}
		}

		foreach ( $taxonomies as $taxonomy ) {
			/* translators: %s: taxonomy name */
			$objects[] = [ 'value' => 'term:' . $taxonomy['value'], 'label' => sprintf( __( 'Terms: %s', 'libreforge' ), $taxonomy['label'] ) ];
		}

		$objects[] = [ 'value' => 'user', 'label' => __( 'Users', 'libreforge' ) ];

		$relations = array_map(
			fn( Definition $relation ) => [ 'value' => $relation->slug, 'label' => $relation->title(), 'parent' => $relation->get( 'parent_object' ), 'child' => $relation->get( 'child_object' ) ],
			$this->forge()->repository()->active( RelationKind::ID )
		);

		$templates = array_map(
			fn( \WP_Post $post ) => [ 'value' => $post->ID, 'label' => get_the_title( $post ) ?: '#' . $post->ID, 'source' => libreblocks()->get( 'forge.templates' )->source( $post->ID ) ],
			libreblocks()->get( 'forge.templates' )->all()
		);

		return new WP_REST_Response( [
			'templates'      => $templates,
			'objects'        => $objects,
			'relations'      => $relations,
			'queries'        => $queries,
			'post_types'     => $post_types,
			'taxonomies'     => $taxonomies,
			'roles'          => $roles,
			'glossaries'     => $glossaries,
			'field_types'    => libreblocks()->fields()->describe(),
			'parents'        => $parents,
			'option_prefix'  => OptionsPageKind::option_name( '' ),
		] );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	private function persist( Kind $kind, Definition $definition, int $status ) {
		$errors = $kind->validate( $definition, $this->forge()->repository() );

		if ( $errors ) {
			return new WP_Error( 'libreblocks_invalid', __( 'Please fix the highlighted fields.', 'libreforge' ), [ 'status' => 400, 'errors' => $errors ] );
		}

		$saved = $this->forge()->repository()->save( $definition );

		return new WP_REST_Response( $saved->to_array(), $status );
	}

	private function status( WP_REST_Request $request ): string {
		return Definition::DRAFT === $request['status'] ? Definition::DRAFT : Definition::ACTIVE;
	}

	private function kind( string $id ): ?Kind {
		return $this->forge()->kinds()[ $id ] ?? null;
	}

	private function not_found(): WP_Error {
		return $this->error( 'not_found', __( 'Item not found.', 'libreforge' ), 404 );
	}

	private function forge(): Plugin {
		return Plugin::instance();
	}
}
