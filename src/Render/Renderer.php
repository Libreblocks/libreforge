<?php
/**
 * Builder-agnostic HTML of every LibreForge dynamic element. Gutenberg blocks
 * and Elementor widgets only translate their settings and call these methods.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Render;

use LibreBlocks\Core\Context\ObjectStack;
use LibreBlocks\Core\Fields\Field;
use LibreBlocks\Forge\Fields\FieldGroups;
use LibreBlocks\Forge\Queries\QueryResult;
use LibreBlocks\Forge\Queries\QueryRunner;
use LibreBlocks\Forge\Templates\Templates;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	private const TAGS = [ 'div', 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'small' ];

	/** @var array<int, Field[]> Child fields of the repeaters being rendered (to format row values). */
	private array $row_fields = [];

	private static bool $styles_printed = false;

	private string $hint = '';

	public function __construct(
		private ObjectStack $context,
		private Values $values,
		private QueryRunner $queries,
		private Templates $templates,
		private FieldGroups $fields
	) {}

	/* ------------------------------------------------------------------ field */

	/**
	 * @param array<string, mixed> $s source, key, format (+ format options), tag, before, after,
	 *                                fallback, link (bool), class.
	 */
	public function field( array $s ): string {
		if ( ! empty( $s['object_id'] ) ) {
			return $this->for_object( (int) $s['object_id'], fn() => $this->field( array_diff_key( $s, [ 'object_id' => 1 ] ) ) );
		}

		$source = (string) ( $s['source'] ?? 'object' );
		$key    = (string) ( $s['key'] ?? 'title' );
		$value  = $this->values->get( $source, $key );
		$field  = 'meta' === $source ? $this->field_definition( $key ) : null;
		$html   = $this->is_empty( $value ) ? '' : Formatter::format( $value, $s, $field );

		if ( '' === $html ) {
			$fallback = (string) ( $s['fallback'] ?? '' );

			if ( '' === $fallback ) {
				return '';
			}

			$html = esc_html( $fallback );
		}

		$html = esc_html( (string) ( $s['before'] ?? '' ) ) . $html . esc_html( (string) ( $s['after'] ?? '' ) );

		if ( ! empty( $s['link'] ) && ( $url = $this->values->url() ) ) {
			$html = sprintf( '<a href="%s">%s</a>', esc_url( $url ), $html );
		}

		return $this->wrap( (string) ( $s['tag'] ?? 'div' ), 'lf-field ' . ( $s['class'] ?? '' ), $html );
	}

	/* ------------------------------------------------------------------ image */

	/**
	 * @param array<string, mixed> $s source (featured|meta|option), key, size, link (none|item|file),
	 *                                fallback_id, class.
	 */
	public function image( array $s ): string {
		if ( ! empty( $s['object_id'] ) ) {
			return $this->for_object( (int) $s['object_id'], fn() => $this->image( array_diff_key( $s, [ 'object_id' => 1 ] ) ) );
		}

		$source = (string) ( $s['source'] ?? 'featured' );
		$value  = 'featured' === $source
			? $this->values->get( 'object', 'featured_image' )
			: $this->values->get( $source, (string) ( $s['key'] ?? '' ) );

		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		$id = (int) $value ?: (int) ( $s['fallback_id'] ?? 0 );

		if ( ! $id ) {
			return '';
		}

		$size = (string) ( $s['size'] ?? 'medium_large' );
		$alt  = (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: (string) $this->values->get( 'object', 'title' );
		$html = wp_get_attachment_image( $id, $size, false, [ 'class' => 'lf-image__img', 'alt' => $alt ] );

		if ( ! $html ) {
			return '';
		}

		$link = (string) ( $s['link'] ?? 'none' );
		$url  = 'item' === $link || true === $link ? $this->values->url() : ( 'file' === $link ? (string) wp_get_attachment_url( $id ) : '' );

		if ( $url ) {
			$html = sprintf( '<a href="%s">%s</a>', esc_url( $url ), $html );
		}

		return $this->wrap( 'figure', 'lf-image ' . ( $s['class'] ?? '' ), $html );
	}

	/* ------------------------------------------------------------------ link / button */

	/**
	 * @param array<string, mixed> $s url_source (item|meta|option|custom), url_key, text (tokens allowed),
	 *                                new_tab, style (link|button), class.
	 */
	public function link( array $s ): string {
		if ( ! empty( $s['object_id'] ) ) {
			return $this->for_object( (int) $s['object_id'], fn() => $this->link( array_diff_key( $s, [ 'object_id' => 1 ] ) ) );
		}

		$source = (string) ( $s['url_source'] ?? 'item' );
		$key    = (string) ( $s['url_key'] ?? '' );

		switch ( $source ) {
			case 'meta':
			case 'option':
				$url = (string) $this->values->get( $source, $key );
				break;
			case 'custom':
				$url = (string) $this->values->get( 'token', $key );
				break;
			default:
				$url = $this->values->url();
		}

		// E-mail and phone fields become mailto:/tel: links.
		if ( is_email( $url ) ) {
			$url = 'mailto:' . $url;
		} elseif ( preg_match( '/^\+?[\d\s().-]{8,}$/', $url ) ) {
			$url = 'tel:' . preg_replace( '/[^\d+]/', '', $url );
		}

		if ( '' === $url ) {
			return '';
		}

		$text  = (string) $this->values->get( 'token', (string) ( $s['text'] ?? '' ) ) ?: __( 'See more', 'libreforge' );
		$class = 'lf-link' . ( 'button' === ( $s['style'] ?? 'link' ) ? ' lf-link--button wp-element-button' : '' ) . ' ' . ( $s['class'] ?? '' );
		$blank = ! empty( $s['new_tab'] ) ? ' target="_blank" rel="noopener"' : '';

		return sprintf( '<a class="%s" href="%s"%s>%s</a>', esc_attr( trim( $class ) ), esc_url( $url ), $blank, esc_html( $text ) );
	}

	/* ------------------------------------------------------------------ terms */

	/**
	 * @param array<string, mixed> $s taxonomy, separator, link (bool), limit, style (text|badges), class.
	 */
	public function terms( array $s ): string {
		if ( ! empty( $s['object_id'] ) ) {
			return $this->for_object( (int) $s['object_id'], fn() => $this->terms( array_diff_key( $s, [ 'object_id' => 1 ] ) ) );
		}

		$object = $this->context->current();

		if ( ! $object instanceof \WP_Post ) {
			return '';
		}

		$terms = get_the_terms( $object, (string) ( $s['taxonomy'] ?? 'category' ) );

		if ( ! is_array( $terms ) || ! $terms ) {
			return '';
		}

		$limit = (int) ( $s['limit'] ?? 0 );
		$terms = $limit > 0 ? array_slice( $terms, 0, $limit ) : $terms;
		$items = [];

		foreach ( $terms as $term ) {
			$name = esc_html( $term->name );
			$link = ! empty( $s['link'] ) ? get_term_link( $term ) : '';

			$items[] = $link && ! is_wp_error( $link ) ? sprintf( '<a class="lf-terms__term" href="%s">%s</a>', esc_url( $link ), $name ) : '<span class="lf-terms__term">' . $name . '</span>';
		}

		$badges    = 'badges' === ( $s['style'] ?? 'text' );
		$separator = $badges ? '' : esc_html( (string) ( $s['separator'] ?? ', ' ) );

		return $this->wrap( 'div', 'lf-terms' . ( $badges ? ' lf-terms--badges' : '' ) . ' ' . ( $s['class'] ?? '' ), implode( $separator, $items ) );
	}

	/* ------------------------------------------------------------------ repeater */

	/**
	 * Renders each row of a repeater field. While a row renders it is the current
	 * object, so dynamic fields inside read its sub-fields ({{object.meta|question}}).
	 *
	 * @param array<string, mixed> $s          source (meta|option), key, format (text with tokens, used
	 *                                         when no $render_row is given), tag (ul|ol|div), class.
	 * @param callable|null        $render_row Receives ( array $row, int $index ) and returns HTML.
	 */
	public function repeater( array $s, ?callable $render_row = null ): string {
		if ( ! empty( $s['object_id'] ) ) {
			return $this->for_object( (int) $s['object_id'], fn() => $this->repeater( array_diff_key( $s, [ 'object_id' => 1 ] ), $render_row ) );
		}

		$source = 'option' === ( $s['source'] ?? 'meta' ) ? 'option' : 'meta';
		$key    = (string) ( $s['key'] ?? '' );
		$rows   = $this->values->get( $source, $key );

		$field      = 'meta' === $source ? $this->field_definition( $key ) : null;
		$this->hint = '';

		if ( ! is_array( $rows ) || ! $rows ) {
			$this->hint = $this->missing_hint( $key, null !== $field );
			return '';
		}

		$this->row_fields[] = $field ? $field->fields : [];
		$tag               = in_array( $s['tag'] ?? 'div', [ 'ul', 'ol', 'div' ], true ) ? (string) ( $s['tag'] ?? 'div' ) : 'div';
		$item_tag          = 'div' === $tag ? 'div' : 'li';
		$html              = '';

		try {
			foreach ( array_values( $rows ) as $index => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$inner = $this->context->with( $row, function () use ( $render_row, $row, $index, $s ) {
					if ( $render_row ) {
						return (string) $render_row( $row, $index );
					}

					// An editor left "empty" may still hold tags like <p></p>.
					$format = (string) ( $s['format'] ?? '' );
					$format = '' === trim( wp_strip_all_tags( $format ) ) && false === strpos( $format, '{{' ) ? '' : $format;

					return '' === $format ? $this->auto_row( $row ) : wp_kses_post( (string) $this->values->get( 'token', $format ) );
				} );

				$html .= sprintf( '<%1$s class="lf-repeater__item">%2$s</%1$s>', $item_tag, $inner );
			}
		} finally {
			array_pop( $this->row_fields );
		}

		return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $tag, esc_attr( trim( 'lf-repeater ' . ( $s['class'] ?? '' ) ) ), $html );
	}

	/**
	 * Why the last element printed nothing (shown only to editors, e.g. in the Elementor editor).
	 */
	public function hint(): string {
		return $this->hint;
	}

	/**
	 * Every sub-field of a repeater row, the first one in bold, when no row format was written.
	 *
	 * @param array<string, mixed> $row
	 */
	private function auto_row( array $row ): string {
		$children = end( $this->row_fields ) ?: [];
		$names    = $children ? array_map( fn( Field $child ) => $child->name, array_filter( $children, fn( Field $child ) => ! in_array( $child->type, [ 'html', 'tab' ], true ) ) ) : array_keys( $row );
		$html     = '';

		foreach ( array_values( $names ) as $index => $name ) {
			$value = $row[ $name ] ?? null;

			if ( $this->is_empty( $value ) ) {
				continue;
			}

			$child = null;

			foreach ( $children as $candidate ) {
				if ( $candidate->name === $name ) {
					$child = $candidate;
				}
			}

			$tag   = 0 === $index ? 'strong' : 'div';
			$html .= sprintf( '<%1$s class="lf-repeater__field lf-repeater__field--%2$s">%3$s</%1$s>', $tag, esc_attr( sanitize_html_class( $name ) ), Formatter::format( $value, [], $child ) );
		}

		return $html;
	}

	private function missing_hint( string $key, bool $known ): string {
		$object = $this->context->current();
		$title  = (string) $this->values->builtin( $object, 'title' );

		if ( '' === $key ) {
			return __( 'Choose the repeater field.', 'libreforge' );
		}

		if ( ! $known ) {
			return sprintf(
				/* translators: 1: item title, 2: field name */
				__( 'The current item ("%1$s") does not have the field "%2$s". Use this element inside a template of the right content type, on the page of that item, or choose a specific item in "From which item".', 'libreforge' ),
				$title ?: '—',
				$key
			);
		}

		/* translators: 1: item title, 2: field name */
		return sprintf( __( 'The field "%2$s" of "%1$s" has no rows yet.', 'libreforge' ), $title ?: '—', $key );
	}

	/**
	 * Renders $render with a specific post as the current object.
	 *
	 * @param callable(): string $render
	 */
	private function for_object( int $id, callable $render ): string {
		$post = get_post( $id );

		if ( ! $post || ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $id ) ) ) {
			return '';
		}

		return $this->context->with( $post, $render );
	}

	/* ------------------------------------------------------------------ loop grid */

	/**
	 * @param array<string, mixed> $s query_id or inline (post_type, per_page, orderby, order), template_id,
	 *                                columns, columns_tablet, columns_mobile, gap, pagination
	 *                                (none|numbers|prev_next), empty_text, listing_id (stable id used by
	 *                                filters), instance, class.
	 */
	public function loop( array $s ): string {
		$config = $this->loop_query( $s );

		if ( ! $config ) {
			return $this->notice( __( 'Choose a query or a post type for this listing.', 'libreforge' ) );
		}

		$instance = sanitize_key( (string) ( $s['listing_id'] ?? '' ) )
			?: sanitize_key( (string) ( $s['instance'] ?? '' ) )
			?: substr( md5( (string) wp_json_encode( [ $config, $s['template_id'] ?? 0 ] ) ), 0, 8 );

		$s['instance'] = $instance;
		$page_var      = 'lfp_' . $instance;
		$page          = isset( $_GET[ $page_var ] ) ? max( 1, absint( $_GET[ $page_var ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result        = $this->queries->run( $config, $page, [], $instance );
		$items         = '';

		foreach ( $result->items as $item ) {
			$items .= '<div class="lf-loop__item">' . $this->render_item( $item, (int) ( $s['template_id'] ?? 0 ) ) . '</div>';
		}

		if ( $result->is_empty() ) {
			/**
			 * Filters the message of an empty listing (LibreSift shows one when filters match nothing).
			 *
			 * @param string               $message
			 * @param array<string, mixed> $settings
			 */
			$empty = (string) apply_filters( 'libreforge/loop/empty_text', (string) ( $s['empty_text'] ?? '' ), $s );
			$items = '' === $empty ? '' : '<p class="lf-loop__empty">' . esc_html( $empty ) . '</p>';
		}

		$style = sprintf(
			'--lf-cols:%d;--lf-cols-tablet:%d;--lf-cols-mobile:%d;--lf-gap:%dpx;',
			$this->clamp( $s['columns'] ?? 3, 1, 6 ),
			$this->clamp( $s['columns_tablet'] ?? 2, 1, 6 ),
			$this->clamp( $s['columns_mobile'] ?? 1, 1, 6 ),
			$this->clamp( $s['gap'] ?? 24, 0, 120 )
		);

		// The wrapper is always printed (even when empty) so filters can refresh it.
		[ $payload, $signature ] = self::sign( $s );

		return $this->styles() . sprintf(
			'<div class="%1$s" data-libreforge-loop="%2$s" data-page="%3$d" data-pages="%4$d" data-total="%5$d" data-lf-settings="%6$s" data-lf-signature="%7$s"><div class="lf-loop__grid" style="%8$s">%9$s</div>%10$s</div>',
			esc_attr( trim( 'lf-loop ' . ( $result->is_empty() ? 'lf-loop--empty ' : '' ) . ( $s['class'] ?? '' ) ) ),
			esc_attr( $instance ),
			$result->page,
			$result->pages(),
			$result->total,
			esc_attr( $payload ),
			esc_attr( $signature ),
			esc_attr( $style ),
			$items,
			$this->pagination( $result, (string) ( $s['pagination'] ?? 'none' ), $page_var )
		);
	}

	/**
	 * Listing settings travel to the browser signed, so an AJAX refresh (LibreSift)
	 * can re-render the same listing without trusting what the browser sends back.
	 *
	 * @param array<string, mixed> $settings
	 * @return array{0: string, 1: string} Base64 payload and its signature.
	 */
	public static function sign( array $settings ): array {
		$payload = base64_encode( (string) wp_json_encode( $settings ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return [ $payload, hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ) ];
	}

	/**
	 * @return array<string, mixed>|null The settings, or null when the signature does not match.
	 */
	public static function verify( string $payload, string $signature ): ?array {
		if ( ! hash_equals( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), $signature ) ) {
			return null;
		}

		$settings = json_decode( (string) base64_decode( $payload, true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		return is_array( $settings ) ? $settings : null;
	}

	/**
	 * One item: put it on the object stack (and in global $post for posts) and render the template.
	 *
	 * @param mixed $item
	 */
	public function render_item( $item, int $template_id ): string {
		global $post;

		$previous = $post;

		if ( $item instanceof \WP_Post ) {
			$post = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
			setup_postdata( $item );
		}

		try {
			return $this->context->with( $item, function () use ( $item, $template_id ) {
				if ( $template_id ) {
					return $this->templates->render( $template_id, $item );
				}

				// No template yet: a simple linked title keeps the listing useful.
				return $this->field( [ 'source' => 'object', 'key' => 'title', 'tag' => 'h3', 'link' => true ] );
			} );
		} finally {
			if ( $item instanceof \WP_Post ) {
				$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$previous ? setup_postdata( $previous ) : wp_reset_postdata();
			}
		}
	}

	/* ------------------------------------------------------------------ helpers */

	/**
	 * @param array<string, mixed> $s
	 * @return array<string, mixed>|null
	 */
	private function loop_query( array $s ): ?array {
		$query_id = (int) ( $s['query_id'] ?? 0 );

		if ( $query_id ) {
			$query = $this->queries->find( $query_id );
			return $query ? $query->config : null;
		}

		$post_type = sanitize_key( (string) ( $s['post_type'] ?? '' ) );

		if ( '' === $post_type ) {
			return null;
		}

		return [
			'type'            => 'posts',
			'post_types'      => [ $post_type ],
			'statuses'        => [ 'publish' ],
			'per_page'        => (int) ( $s['per_page'] ?? 6 ),
			'orderby'         => sanitize_key( (string) ( $s['orderby'] ?? 'date' ) ),
			'order'           => 'ASC' === ( $s['order'] ?? 'DESC' ) ? 'ASC' : 'DESC',
			'exclude_current' => true,
			'ignore_sticky'   => true,
		];
	}

	private function pagination( QueryResult $result, string $type, string $page_var ): string {
		$pages = $result->pages();

		if ( 'none' === $type || $pages < 2 ) {
			return '';
		}

		$link  = fn( int $page ) => esc_url( add_query_arg( $page_var, $page > 1 ? $page : false ) );
		$links = [];

		if ( $result->page > 1 ) {
			$links[] = sprintf( '<a class="lf-pagination__prev" href="%s">%s</a>', $link( $result->page - 1 ), esc_html__( '← Previous', 'libreforge' ) );
		}

		if ( 'numbers' === $type ) {
			for ( $page = 1; $page <= $pages; $page++ ) {
				$links[] = $page === $result->page
					? sprintf( '<span class="lf-pagination__current" aria-current="page">%d</span>', $page )
					: sprintf( '<a href="%s">%d</a>', $link( $page ), $page );
			}
		}

		if ( $result->page < $pages ) {
			$links[] = sprintf( '<a class="lf-pagination__next" href="%s">%s</a>', $link( $result->page + 1 ), esc_html__( 'Next →', 'libreforge' ) );
		}

		return '<nav class="lf-pagination" aria-label="' . esc_attr__( 'Pagination', 'libreforge' ) . '">' . implode( '', $links ) . '</nav>';
	}

	/**
	 * The definition of a field on the current object (or of a repeater sub-field inside a row).
	 */
	private function field_definition( string $name ): ?Field {
		$object = $this->context->current();

		if ( is_array( $object ) ) {
			foreach ( end( $this->row_fields ) ?: [] as $child ) {
				if ( $child->name === $name ) {
					return $child;
				}
			}

			return null;
		}

		return $this->fields->field_for( $object, $name );
	}

	/**
	 * @param mixed $value
	 */
	private function is_empty( $value ): bool {
		return null === $value || '' === $value || [] === $value || false === $value;
	}

	private function wrap( string $tag, string $class, string $html ): string {
		$tag = in_array( $tag, self::TAGS, true ) || 'figure' === $tag ? $tag : 'div';

		return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $tag, esc_attr( trim( $class ) ), $html );
	}

	/**
	 * @param mixed $value
	 */
	private function clamp( $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) $value ) );
	}

	private function notice( string $message ): string {
		// Only editors see configuration hints; visitors see nothing.
		return current_user_can( 'edit_posts' ) ? '<div class="lf-notice">' . esc_html( $message ) . '</div>' : '';
	}

	/**
	 * Grid and pagination CSS, printed once per page (tiny, no extra request).
	 */
	private function styles(): string {
		if ( self::$styles_printed ) {
			return '';
		}

		self::$styles_printed = true;

		return '<style id="libreforge-loop-css">'
			. '.lf-loop__grid{display:grid;gap:var(--lf-gap,24px);grid-template-columns:repeat(var(--lf-cols,3),minmax(0,1fr))}'
			. '@media(max-width:1024px){.lf-loop__grid{grid-template-columns:repeat(var(--lf-cols-tablet,2),minmax(0,1fr))}}'
			. '@media(max-width:767px){.lf-loop__grid{grid-template-columns:repeat(var(--lf-cols-mobile,1),minmax(0,1fr))}}'
			. '.lf-loop__item>*:first-child{margin-top:0}.lf-image{margin:0}.lf-image img{display:block;width:100%;height:auto}'
			. '.lf-pagination{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:24px}'
			. '.lf-pagination a,.lf-pagination span{padding:6px 12px;border:1px solid currentColor;border-radius:6px;text-decoration:none;opacity:.8}'
			. '.lf-pagination .lf-pagination__current{opacity:1;font-weight:700}'
			. '.lf-terms--badges{display:flex;flex-wrap:wrap;gap:6px}.lf-terms--badges .lf-terms__term{padding:2px 10px;border-radius:20px;background:rgba(0,0,0,.06);font-size:.85em;text-decoration:none}'
			. '.lf-link--button{display:inline-block;text-decoration:none}'
			. '.lf-notice{padding:16px;border:1px dashed #c3c4c7;border-radius:8px;color:#646970;text-align:center}'
			. '</style>';
	}
}
