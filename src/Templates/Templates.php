<?php
/**
 * Item templates: the design of one item of a listing (a "card"), built with
 * the block editor or Elementor and rendered once per item of a query.
 *
 * Stored as the private post type lbforge_template with two meta values:
 *   _lbforge_builder  "blocks" | "elementor"
 *   _lbforge_source   what the template shows: a post type slug, "term:{taxonomy}" or "user".
 *                     Used to pick a sample item while designing.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Templates;

use LibreBlocks\Core\Context\ObjectStack;

defined( 'ABSPATH' ) || exit;

final class Templates {

	public const POST_TYPE = 'lbforge_template';

	/** Nested listings may render templates inside templates; this stops infinite loops. */
	private const MAX_DEPTH = 4;

	/** @var int[] Templates being rendered right now. */
	private array $rendering = [];

	/** @var array<string, int> Post type => template used on single item pages. */
	private array $singles = [];

	public function __construct( private ObjectStack $context ) {}

	public function hook(): void {
		add_action( 'init', [ $this, 'register' ] );
		add_action( 'template_redirect', [ $this, 'protect_single' ] );
		add_filter( 'template_include', [ $this, 'single_template' ], 99 );
		add_filter( 'the_content', [ $this, 'single_content' ], 1 );
	}

	/**
	 * Uses $template_id as the content of every single page of $post_type.
	 */
	public function use_for_singles( string $post_type, int $template_id ): void {
		if ( $template_id > 0 ) {
			$this->singles[ $post_type ] = $template_id;
		}
	}

	/**
	 * On a single item page, the theme keeps its header, footer and title area,
	 * and the content becomes the chosen item template.
	 */
	public function single_content( $content ) {
		$post = get_post();

		if (
			! $post
			|| ! isset( $this->singles[ $post->post_type ] )
			|| $this->rendering
			|| ! is_singular( $post->post_type )
			|| ! in_the_loop()
			|| ! is_main_query()
			|| (int) $post->ID !== get_queried_object_id()
		) {
			return $content;
		}

		return $this->context->with( $post, fn() => $this->render( $this->singles[ $post->post_type ], $post ) );
	}

	public function register(): void {
		register_post_type( self::POST_TYPE, [
			'labels'              => [
				'name'          => __( 'Item templates', 'libreforge' ),
				'singular_name' => __( 'Item template', 'libreforge' ),
				'edit_item'     => __( 'Edit item template', 'libreforge' ),
				'add_new_item'  => __( 'New item template', 'libreforge' ),
			],
			'public'              => false,
			// Viewable so Elementor can load its editor preview; visitors get a 404 (protect_single()).
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'rewrite'             => false,
			'has_archive'         => false,
			'supports'            => [ 'title', 'editor', 'custom-fields', 'revisions' ],
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
		] );

		foreach ( [ '_lbforge_builder', '_lbforge_source' ] as $key ) {
			register_post_meta( self::POST_TYPE, $key, [
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id ),
			] );
		}

		add_post_type_support( self::POST_TYPE, 'elementor' );
	}

	/**
	 * Templates are building blocks, not pages: only editors may open them directly.
	 */
	public function protect_single(): void {
		if ( is_singular( self::POST_TYPE ) && ! current_user_can( 'edit_posts' ) ) {
			global $wp_query;

			$wp_query->set_404();
			status_header( 404 );
		}
	}

	/**
	 * Opening a template on the front end (Elementor editor preview) shows it
	 * with a sample item as the current object.
	 */
	public function single_template( string $template ): string {
		if ( is_singular( self::POST_TYPE ) && current_user_can( 'edit_posts' ) ) {
			return LIBREFORGE_DIR . 'templates/template-preview.php';
		}

		return $template;
	}

	/**
	 * @return \WP_Post[]
	 */
	public function all(): array {
		return get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
	}

	public function builder( int $template_id ): string {
		$builder = (string) get_post_meta( $template_id, '_lbforge_builder', true );

		return 'elementor' === $builder ? 'elementor' : 'blocks';
	}

	public function source( int $template_id ): string {
		return (string) get_post_meta( $template_id, '_lbforge_source', true ) ?: 'post';
	}

	public function edit_url( int $template_id ): string {
		if ( 'elementor' === $this->builder( $template_id ) && did_action( 'elementor/loaded' ) ) {
			return add_query_arg( [ 'post' => $template_id, 'action' => 'elementor' ], admin_url( 'post.php' ) );
		}

		return (string) get_edit_post_link( $template_id, 'raw' );
	}

	/**
	 * A real item to show while designing a template.
	 *
	 * @return \WP_Post|\WP_Term|\WP_User|null
	 */
	public function sample_object( int $template_id ) {
		$source = $this->source( $template_id );

		if ( 'user' === $source ) {
			return wp_get_current_user();
		}

		if ( 0 === strpos( $source, 'term:' ) ) {
			$terms = get_terms( [ 'taxonomy' => substr( $source, 5 ), 'number' => 1, 'hide_empty' => false ] );

			return is_array( $terms ) && $terms ? $terms[0] : null;
		}

		$posts = get_posts( [ 'post_type' => $source, 'post_status' => [ 'publish', 'draft', 'private' ], 'posts_per_page' => 1 ] );

		return $posts[0] ?? null;
	}

	/**
	 * Renders a template for one item. The caller has already put the item on the
	 * object stack (and set up global $post for posts).
	 *
	 * @param mixed $object The current item, used as block context.
	 */
	public function render( int $template_id, $object = null ): string {
		$template = get_post( $template_id );

		if ( ! $template || self::POST_TYPE !== $template->post_type ) {
			return '';
		}

		if ( in_array( $template_id, $this->rendering, true ) || count( $this->rendering ) >= self::MAX_DEPTH ) {
			return '';
		}

		$this->rendering[] = $template_id;

		try {
			if ( 'elementor' === $this->builder( $template_id ) && did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
				return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id, true );
			}

			return $this->render_blocks( $template->post_content, $object ?? $this->context->current() );
		} finally {
			array_pop( $this->rendering );
		}
	}

	/**
	 * Renders block markup with the item as block context, so core blocks
	 * (post title, featured image...) show the item instead of the page.
	 *
	 * @param mixed $object
	 */
	public function render_blocks( string $content, $object ): string {
		$context = [];

		if ( $object instanceof \WP_Post ) {
			$context = [ 'postId' => $object->ID, 'postType' => $object->post_type ];
		}

		$html = '';

		foreach ( parse_blocks( $content ) as $block ) {
			$html .= ( new \WP_Block( $block, $context ) )->render();
		}

		return $html;
	}
}
