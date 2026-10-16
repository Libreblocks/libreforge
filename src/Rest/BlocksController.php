<?php
/**
 * GET libreblocks/v1/forge/blocks-data — choices for block and widget controls
 * (queries, templates, fields). Open to anyone who can edit posts, not only admins.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Rest;

use LibreBlocks\Core\Rest\Controller;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\Kinds\OptionsPageKind;
use LibreBlocks\Forge\Model\Kinds\QueryKind;
use LibreBlocks\Forge\Plugin;
use LibreBlocks\Forge\Templates\Templates;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class BlocksController extends Controller {

	public function register_routes(): void {
		$this->route( '/forge/blocks-data', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'data' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );
	}

	public function data(): WP_REST_Response {
		return new WP_REST_Response( self::collect() );
	}

	/**
	 * Also used by the Elementor integration to fill its select controls.
	 *
	 * @return array<string, mixed>
	 */
	public static function collect(): array {
		$forge      = Plugin::instance();
		$repository = $forge->repository();
		$templates  = libreblocks()->get( 'forge.templates' );
		$groups     = libreblocks()->get( 'forge.fields' );

		$post_types = [];

		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$post_types[] = [ 'value' => $type->name, 'label' => $type->labels->name ];
			}
		}

		$taxonomies = [];

		foreach ( get_taxonomies( [ 'show_ui' => true ], 'objects' ) as $taxonomy ) {
			$taxonomies[] = [ 'value' => $taxonomy->name, 'label' => $taxonomy->labels->name ];
		}

		$fields = [];

		foreach ( $groups->all_groups() as $group ) {
			$where = 'post' === $group->get( 'object' ) ? (array) $group->get( 'post_types', [] ) : [ (string) $group->get( 'object' ) ];

			foreach ( $groups->fields_of( $group ) as $field ) {
				if ( in_array( $field->type, [ 'html', 'tab' ], true ) ) {
					continue;
				}

				$fields[] = [
					'value'    => $field->name,
					'label'    => $field->label ?: $field->name,
					'type'     => $field->type,
					'object'   => (string) $group->get( 'object' ),
					'where'    => $where,
					'children' => 'repeater' === $field->type ? array_map( fn( $child ) => [ 'value' => $child->name, 'label' => $child->label ?: $child->name, 'type' => $child->type ], $field->fields ) : [],
				];
			}
		}

		$options = [];

		foreach ( $repository->active( OptionsPageKind::ID ) as $page ) {
			foreach ( (array) $page->get( 'fields', [] ) as $field ) {
				if ( ! empty( $field['name'] ) ) {
					$options[] = [ 'value' => $page->slug . ':' . $field['name'], 'label' => $page->title() . ' → ' . ( $field['label'] ?: $field['name'] ), 'type' => $field['type'] ];
				}
			}
		}

		return [
			'queries'    => array_map( fn( Definition $query ) => [ 'value' => $query->id, 'label' => $query->title(), 'type' => $query->get( 'type' ) ], $repository->active( QueryKind::ID ) ),
			'templates'  => array_map( fn( \WP_Post $post ) => [ 'value' => $post->ID, 'label' => get_the_title( $post ) ?: '#' . $post->ID, 'source' => $templates->source( $post->ID ), 'edit_url' => $templates->edit_url( $post->ID ) ], $templates->all() ),
			'post_types' => $post_types,
			'taxonomies' => $taxonomies,
			'fields'     => $fields,
			'options'    => $options,
			'sizes'      => array_map( fn( $size ) => [ 'value' => $size, 'label' => $size ], array_merge( get_intermediate_image_sizes(), [ 'full' ] ) ),
			'urls'       => [
				'queries'   => admin_url( 'admin.php?page=libreforge-queries' ),
				'templates' => admin_url( 'admin.php?page=libreforge-templates' ),
			],
		];
	}
}
