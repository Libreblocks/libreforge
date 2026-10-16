<?php
/**
 * Gutenberg blocks. Metadata lives in /blocks/<name>/block.json; rendering is
 * done on the server by the shared Renderer, so the editor shows a live preview
 * (ServerSideRender) and the front end matches it exactly.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Blocks;

use LibreBlocks\Core\Context\ObjectStack;
use LibreBlocks\Forge\Render\Renderer;
use LibreBlocks\Forge\Templates\Templates;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	public const SCRIPT = 'libreforge-blocks';

	/** True while previewing a template whose content type has no item yet. */
	private bool $missing_sample = false;

	public function __construct( private Renderer $renderer, private Templates $templates, private ObjectStack $context ) {}

	public function hook(): void {
		add_action( 'init', [ $this, 'register' ] );
		add_filter( 'block_categories_all', [ $this, 'category' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'editor_data' ] );
	}

	/**
	 * @param array<int, array<string, mixed>> $categories
	 * @return array<int, array<string, mixed>>
	 */
	public function category( array $categories ): array {
		array_unshift( $categories, [ 'slug' => 'libreforge', 'title' => 'LibreForge', 'icon' => null ] );

		return $categories;
	}

	public function register(): void {
		$asset_file = LIBREFORGE_DIR . 'build/blocks.asset.php';

		if ( ! is_file( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_register_script( self::SCRIPT, LIBREFORGE_URL . 'build/blocks.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::SCRIPT, 'libreforge', LIBREFORGE_DIR . 'languages' );
		wp_register_style( self::SCRIPT, LIBREFORGE_URL . 'build/blocks.css', [], $asset['version'] );

		$blocks = [
			'loop-grid' => [ __( 'Loop Grid', 'libreforge' ), __( 'Repeats an item template for every item of a query.', 'libreforge' ), 'loop_grid' ],
			'field'     => [ __( 'Dynamic Field', 'libreforge' ), __( 'Shows a value of the current item: title, custom field, option...', 'libreforge' ), 'field' ],
			'image'     => [ __( 'Dynamic Image', 'libreforge' ), __( 'Shows the featured image or an image field of the current item.', 'libreforge' ), 'image' ],
			'link'      => [ __( 'Dynamic Link', 'libreforge' ), __( 'A link or button to the current item, a field or any address.', 'libreforge' ), 'link' ],
			'terms'     => [ __( 'Dynamic Terms', 'libreforge' ), __( 'Lists the categories, tags or terms of the current item.', 'libreforge' ), 'terms' ],
			'repeater'  => [ __( 'Dynamic Repeater', 'libreforge' ), __( 'Repeats the blocks inside it for every row of a repeater field.', 'libreforge' ), 'repeater' ],
		];

		foreach ( $blocks as $slug => [ $title, $description, $method ] ) {
			register_block_type( LIBREFORGE_DIR . 'blocks/' . $slug, [
				'title'           => $title,
				'description'     => $description,
				'render_callback' => fn( $attributes, $content, $block ) => $this->render( $method, (array) $attributes, $block ),
			] );
		}
	}

	/**
	 * Data the block controls need (queries, templates, fields...).
	 */
	public function editor_data(): void {
		$post   = get_post();
		$source = $post && Templates::POST_TYPE === $post->post_type ? $this->templates->source( $post->ID ) : '';

		wp_add_inline_script( self::SCRIPT, 'window.libreforgeBlocks = ' . wp_json_encode( [
			'templateSource' => $source,
			'manageUrl'      => admin_url( 'admin.php?page=libreforge' ),
		] ) . ';', 'before' );
	}

	/**
	 * @param array<string, mixed> $a
	 * @param \WP_Block|null       $block
	 */
	private function render( string $method, array $a, $block = null ): string {
		$preview = $this->preview_object();

		if ( $preview ) {
			$this->context->push( $preview );
		}

		try {
			switch ( $method ) {
				case 'loop_grid':
					$html = $this->renderer->loop( [
						'query_id'       => $a['queryId'] ?? 0,
						'post_type'      => $a['postType'] ?? '',
						'per_page'       => $a['perPage'] ?? 6,
						'orderby'        => $a['orderby'] ?? 'date',
						'order'          => $a['order'] ?? 'DESC',
						'template_id'    => $a['templateId'] ?? 0,
						'columns'        => $a['columns'] ?? 3,
						'columns_tablet' => $a['columnsTablet'] ?? 2,
						'columns_mobile' => $a['columnsMobile'] ?? 1,
						'gap'            => $a['gap'] ?? 24,
						'pagination'     => $a['pagination'] ?? 'none',
						'empty_text'     => $a['emptyText'] ?? '',
						'listing_id'     => $a['listingId'] ?? '',
					] );
					break;

				case 'field':
					$html = $this->renderer->field( [
						'source'      => $a['source'] ?? 'object',
						'key'         => $a['key'] ?? 'title',
						'format'      => $a['format'] ?? 'auto',
						'date_format' => $a['dateFormat'] ?? '',
						'decimals'    => $a['decimals'] ?? 0,
						'currency'    => $a['currency'] ?? 'R$',
						'words'       => $a['words'] ?? 20,
						'tag'         => $a['tag'] ?? 'div',
						'before'      => $a['before'] ?? '',
						'after'       => $a['after'] ?? '',
						'fallback'    => $a['fallback'] ?? '',
						'link'        => ! empty( $a['link'] ),
					] );
					break;

				case 'image':
					$html = $this->renderer->image( [
						'source' => $a['source'] ?? 'featured',
						'key'    => $a['key'] ?? '',
						'size'   => $a['size'] ?? 'medium_large',
						'link'   => $a['link'] ?? 'none',
					] );
					break;

				case 'link':
					$html = $this->renderer->link( [
						'url_source' => $a['urlSource'] ?? 'item',
						'url_key'    => $a['urlKey'] ?? '',
						'text'       => $a['text'] ?? '',
						'new_tab'    => ! empty( $a['newTab'] ),
						'style'      => $a['style'] ?? 'button',
					] );
					break;

				case 'terms':
					$html = $this->renderer->terms( [
						'taxonomy'  => $a['taxonomy'] ?? 'category',
						'separator' => $a['separator'] ?? ', ',
						'link'      => ! empty( $a['link'] ),
						'limit'     => $a['limit'] ?? 0,
						'style'     => $a['style'] ?? 'text',
					] );
					break;

				case 'repeater':
					$html = $this->renderer->repeater(
						[ 'source' => $a['source'] ?? 'meta', 'key' => $a['key'] ?? '', 'tag' => $a['tag'] ?? 'div' ],
						$block instanceof \WP_Block ? fn() => $this->render_inner( $block ) : null
					);
					break;

				default:
					$html = '';
			}
		} finally {
			if ( $preview ) {
				$this->context->pop();
			}
		}

		if ( '' === $html ) {
			if ( ! $this->is_editor_preview() ) {
				return '';
			}

			$message = $this->missing_sample
				? __( 'Create at least one item of this content type to see a real preview here.', 'libreforge' )
				: __( 'Nothing to show for this item yet.', 'libreforge' );

			return '<div class="lf-empty-preview">' . esc_html( $message ) . '</div>';
		}

		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
	}

	/**
	 * The inner blocks of a repeater, rendered for the current row.
	 */
	private function render_inner( \WP_Block $block ): string {
		$html = '';

		foreach ( $block->parsed_block['innerBlocks'] ?? [] as $inner ) {
			$html .= ( new \WP_Block( $inner, $block->context ) )->render();
		}

		return $html;
	}

	/**
	 * In the editor (ServerSideRender), blocks inside an item template show a real sample item.
	 *
	 * @return mixed
	 */
	private function preview_object() {
		if ( ! $this->is_editor_preview() || $this->context->depth() > 0 ) {
			return null;
		}

		// The block renderer endpoint receives the edited post as ?post_id= but does not
		// fill global $post (only setup_postdata(), which sets global $id).
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : (int) ( $GLOBALS['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview.
		$post    = $post_id ? get_post( $post_id ) : get_post();

		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return null;
		}

		if ( Templates::POST_TYPE !== $post->post_type ) {
			return $post;
		}

		$sample               = $this->templates->sample_object( $post->ID );
		$this->missing_sample = null === $sample;

		return $sample;
	}

	private function is_editor_preview(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'edit_posts' );
	}
}
