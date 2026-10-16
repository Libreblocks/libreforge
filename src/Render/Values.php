<?php
/**
 * Reads a value from the current object (post, term, user or repeater row),
 * an options page or a token. Shared by every dynamic block and widget.
 *
 * Sources:
 *   object  built-in data: title, permalink, excerpt, content, date, modified, author,
 *           author_url, id, featured_image, featured_image_url, comment_count (posts);
 *           name, description, count, link, id (terms); display_name, description,
 *           avatar_url, link, id (users)
 *   meta    a custom field of the current object (key = field name)
 *   option  a field of an options page (key = "page_slug:field")
 *   token   any text with {{tokens}} (key = the text)
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Render;

use LibreBlocks\Core\Context\ObjectStack;
use LibreBlocks\Core\Tokens\Defaults;
use LibreBlocks\Core\Tokens\Registry as Tokens;
use LibreBlocks\Forge\Options\OptionsPages;

defined( 'ABSPATH' ) || exit;

final class Values {

	public function __construct( private ObjectStack $context, private Tokens $tokens ) {}

	/**
	 * @return mixed
	 */
	public function get( string $source, string $key, $object = null ) {
		$object = $object ?? $this->context->current();

		switch ( $source ) {
			case 'meta':
				return Defaults::meta( $object, $key );

			case 'option':
				[ $page, $field ] = array_pad( explode( ':', $key, 2 ), 2, '' );
				return '' === $field ? null : OptionsPages::get( sanitize_key( $page ), sanitize_key( $field ) );

			case 'token':
				return $this->tokens->resolve( $key );

			default:
				return $this->builtin( $object, $key );
		}
	}

	/**
	 * @param mixed $object
	 * @return mixed
	 */
	public function builtin( $object, string $key ) {
		if ( $object instanceof \WP_Post ) {
			switch ( $key ) {
				case 'id':
					return $object->ID;
				case 'title':
					return get_the_title( $object );
				case 'permalink':
					return get_permalink( $object );
				case 'excerpt':
					return get_the_excerpt( $object );
				case 'content':
					return apply_filters( 'the_content', $object->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
				case 'date':
					return get_the_date( '', $object );
				case 'modified':
					return get_the_modified_date( '', $object );
				case 'author':
					return get_the_author_meta( 'display_name', (int) $object->post_author );
				case 'author_url':
					return get_author_posts_url( (int) $object->post_author );
				case 'featured_image':
					return (int) get_post_thumbnail_id( $object );
				case 'featured_image_url':
					return (string) get_the_post_thumbnail_url( $object, 'full' );
				case 'comment_count':
					return (int) $object->comment_count;
				case 'post_type':
					$type = get_post_type_object( $object->post_type );
					return $type ? $type->labels->singular_name : $object->post_type;
			}

			return null;
		}

		if ( $object instanceof \WP_Term ) {
			switch ( $key ) {
				case 'id':
					return $object->term_id;
				case 'title':
				case 'name':
					return $object->name;
				case 'description':
				case 'excerpt':
					return $object->description;
				case 'count':
					return (int) $object->count;
				case 'link':
				case 'permalink':
					$link = get_term_link( $object );
					return is_wp_error( $link ) ? '' : $link;
			}

			return null;
		}

		if ( $object instanceof \WP_User ) {
			switch ( $key ) {
				case 'id':
					return $object->ID;
				case 'title':
				case 'display_name':
					return $object->display_name;
				case 'description':
				case 'excerpt':
					return get_the_author_meta( 'description', $object->ID );
				case 'avatar_url':
					return get_avatar_url( $object->ID, [ 'size' => 256 ] );
				case 'link':
				case 'permalink':
					return get_author_posts_url( $object->ID );
			}

			return null;
		}

		// Repeater rows and other arrays.
		return Defaults::prop( $object, $key );
	}

	/**
	 * URL of the current object (used by "link to item" options).
	 *
	 * @param mixed $object
	 */
	public function url( $object = null ): string {
		return (string) $this->builtin( $object ?? $this->context->current(), 'permalink' );
	}
}
