<?php
/**
 * While a template is being designed there is no real item on the page. This
 * finds one to preview with, so widgets and dynamic tags show real data:
 *
 * - LibreForge item templates: a sample item of the template's content type;
 * - Elementor Pro theme templates (single post, archive...): the item chosen in
 *   the template's "Preview settings".
 *
 * On real pages the current object is used and nothing changes.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Elementor;

use LibreBlocks\Forge\Templates\Templates;

defined( 'ABSPATH' ) || exit;

final class EditorPreview {

	/** @var array<int, mixed> Sample per template, for this request. */
	private static array $cache = [];

	/**
	 * @return mixed|null
	 */
	public static function sample() {
		$post = get_post();

		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return null;
		}

		if ( array_key_exists( $post->ID, self::$cache ) ) {
			return self::$cache[ $post->ID ];
		}

		$sample = null;

		if ( Templates::POST_TYPE === $post->post_type ) {
			$sample = libreblocks()->get( 'forge.templates' )->sample_object( $post->ID );
		} elseif ( 'elementor_library' === $post->post_type ) {
			$sample = self::theme_template_sample( $post->ID );
		}

		self::$cache[ $post->ID ] = $sample;

		return $sample;
	}

	/**
	 * Runs $render with the preview item as the current object (when there is one).
	 *
	 * @param callable(): mixed $render
	 * @return mixed
	 */
	public static function wrap( callable $render ) {
		$context = libreblocks()->context();

		if ( $context->depth() > 0 ) {
			return $render();
		}

		$sample = self::sample();

		return $sample ? $context->with( $sample, $render ) : $render();
	}

	/**
	 * The "Preview as" item of an Elementor Pro theme template.
	 *
	 * @return \WP_Post|null
	 */
	private static function theme_template_sample( int $template_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $template_id );

		if ( ! $document || ! method_exists( $document, 'get_preview_as_query_args' ) ) {
			return null;
		}

		$args = (array) $document->get_preview_as_query_args();

		if ( ! $args ) {
			return null;
		}

		$query = new \WP_Query( array_merge( $args, [ 'posts_per_page' => 1, 'no_found_rows' => true, 'ignore_sticky_posts' => true ] ) );

		return $query->posts[0] ?? null;
	}
}
