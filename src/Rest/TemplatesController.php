<?php
/**
 * Item templates for the admin app.
 *
 *   GET    libreblocks/v1/forge/templates
 *   POST   libreblocks/v1/forge/templates        { title, builder: blocks|elementor, source }
 *   DELETE libreblocks/v1/forge/templates/<id>   (moves to the trash)
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Rest;

use LibreBlocks\Core\Rest\Controller;
use LibreBlocks\Forge\Templates\Templates;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class TemplatesController extends Controller {

	public function register_routes(): void {
		$this->route( '/forge/templates', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
			],
		] );

		$this->route( '/forge/templates/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'destroy' ],
			'permission_callback' => fn( WP_REST_Request $request ) => current_user_can( 'delete_post', (int) $request['id'] ),
		] );
	}

	public function index(): WP_REST_Response {
		$templates = $this->templates();

		return new WP_REST_Response( [
			'templates' => array_map( fn( \WP_Post $post ) => $this->describe( $post ), $templates->all() ),
			'sources'   => $this->sources(),
			'elementor' => did_action( 'elementor/loaded' ) > 0,
		] );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$title   = sanitize_text_field( (string) $request['title'] );
		$builder = 'elementor' === $request['builder'] && did_action( 'elementor/loaded' ) ? 'elementor' : 'blocks';
		$source  = sanitize_text_field( (string) $request['source'] );
		$allowed = array_column( $this->sources(), 'value' );

		if ( '' === $title ) {
			return new WP_Error( 'libreblocks_invalid', __( 'Give the template a name.', 'libreforge' ), [ 'status' => 400, 'errors' => [ 'title' => __( 'Give the template a name.', 'libreforge' ) ] ] );
		}

		if ( ! in_array( $source, $allowed, true ) ) {
			$source = 'post';
		}

		$id = wp_insert_post( [
			'post_type'    => Templates::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => 'blocks' === $builder ? $this->starter_content( $source ) : '',
			'meta_input'   => array_filter( [
				'_lbforge_builder'      => $builder,
				'_lbforge_source'       => $source,
				'_elementor_edit_mode'  => 'elementor' === $builder ? 'builder' : null,
			] ),
		], true );

		if ( is_wp_error( $id ) ) {
			return $this->error( 'not_created', $id->get_error_message(), 500 );
		}

		return new WP_REST_Response( $this->describe( get_post( $id ) ), 201 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );

		if ( ! $post || Templates::POST_TYPE !== $post->post_type ) {
			return $this->error( 'not_found', __( 'Item not found.', 'libreforge' ), 404 );
		}

		wp_trash_post( $post->ID );

		return new WP_REST_Response( [ 'deleted' => true, 'id' => $post->ID ] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describe( \WP_Post $post ): array {
		$templates = $this->templates();
		$source    = $templates->source( $post->ID );

		return [
			'id'       => $post->ID,
			'title'    => get_the_title( $post ) ?: __( '(no title)', 'libreforge' ),
			'builder'  => $templates->builder( $post->ID ),
			'source'   => $source,
			'modified' => get_the_modified_date( '', $post ),
			'edit_url' => $templates->edit_url( $post->ID ),
		];
	}

	/**
	 * What a template can show: each post type, the terms of each taxonomy, or users.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function sources(): array {
		$sources = [];

		foreach ( get_post_types( [ 'show_ui' => true ], 'objects' ) as $type ) {
			if ( ! in_array( $type->name, [ 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', Templates::POST_TYPE ], true ) ) {
				$sources[] = [ 'value' => $type->name, 'label' => $type->labels->name ];
			}
		}

		foreach ( get_taxonomies( [ 'show_ui' => true ], 'objects' ) as $taxonomy ) {
			/* translators: %s: taxonomy name */
			$sources[] = [ 'value' => 'term:' . $taxonomy->name, 'label' => sprintf( __( 'Terms: %s', 'libreforge' ), $taxonomy->labels->name ) ];
		}

		$sources[] = [ 'value' => 'user', 'label' => __( 'Users', 'libreforge' ) ];

		/**
		 * What templates can show; other plugins add theirs (e.g. "table:clients").
		 *
		 * @param array<int, array{value: string, label: string}> $sources
		 */
		return (array) apply_filters( 'libreforge/templates/sources', $sources );
	}

	/**
	 * A simple card to start from, made of LibreForge blocks.
	 */
	private function starter_content( string $source ): string {
		if ( 'user' === $source || 0 === strpos( $source, 'term:' ) ) {
			return '<!-- wp:libreforge/field {"source":"object","key":"title","tag":"h3"} /-->';
		}

		return implode( "\n", [
			'<!-- wp:libreforge/image {"source":"featured","size":"medium_large","link":true} /-->',
			'<!-- wp:libreforge/field {"source":"object","key":"title","tag":"h3","link":true} /-->',
			'<!-- wp:libreforge/field {"source":"object","key":"excerpt"} /-->',
		] );
	}

	private function templates(): Templates {
		return libreblocks()->get( 'forge.templates' );
	}
}
