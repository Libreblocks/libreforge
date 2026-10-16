<?php
/**
 * Saved queries ("Query Builder"): posts, terms or users, reusable by listings and filters.
 *
 * Text values may contain tokens such as {{user.id}} or {{url.param|city}};
 * they are resolved at run time by the QueryRunner.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model\Kinds;

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kind;

defined( 'ABSPATH' ) || exit;

final class QueryKind extends Kind {

	public const ID = 'query';

	public const TYPES = [ 'posts', 'terms', 'users' ];

	public const META_COMPARE = [ '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' ];

	public const META_TYPES = [ 'CHAR', 'NUMERIC', 'DECIMAL', 'DATE', 'DATETIME' ];

	private const ORDERBY = [
		'posts' => [ 'date', 'modified', 'title', 'menu_order', 'rand', 'comment_count', 'meta_value', 'meta_value_num', 'post__in', 'ID' ],
		'terms' => [ 'name', 'count', 'term_order', 'term_id', 'include', 'meta_value', 'meta_value_num' ],
		'users' => [ 'display_name', 'registered', 'post_count', 'ID', 'include', 'meta_value', 'meta_value_num' ],
	];

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Queries', 'libreforge' );
	}

	public function sanitize( array $input ): array {
		$type    = in_array( $input['type'] ?? '', self::TYPES, true ) ? $input['type'] : 'posts';
		$orderby = (string) ( $input['orderby'] ?? '' );
		$number  = $input['per_page'] ?? 10;

		return [
			'title'           => self::text( $input, 'title' ),
			'type'            => $type,
			// Posts.
			'post_types'      => self::keys( $input, 'post_types', null, [ 'post' ] ),
			'statuses'        => self::keys( $input, 'statuses', [ 'publish', 'draft', 'pending', 'future', 'private', 'any' ], [ 'publish' ] ),
			'exclude_current' => self::flag( $input, 'exclude_current', true ),
			'ignore_sticky'   => self::flag( $input, 'ignore_sticky', true ),
			'author'          => self::text( $input, 'author' ),
			// Terms.
			'taxonomies'      => self::keys( $input, 'taxonomies', null, [ 'category' ] ),
			'hide_empty'      => self::flag( $input, 'hide_empty', false ),
			'parent'          => self::text( $input, 'parent' ),
			// Users.
			'roles'           => self::keys( $input, 'roles' ),
			// Shared.
			'per_page'        => is_numeric( $number ) ? max( -1, min( 500, (int) $number ) ) : 10,
			'offset'          => max( 0, (int) ( $input['offset'] ?? 0 ) ),
			'orderby'         => in_array( $orderby, self::ORDERBY[ $type ], true ) ? $orderby : self::ORDERBY[ $type ][0],
			'order'           => 'ASC' === strtoupper( (string) ( $input['order'] ?? '' ) ) ? 'ASC' : 'DESC',
			'order_meta_key'  => sanitize_key( self::text( $input, 'order_meta_key' ) ),
			'search'          => self::text( $input, 'search' ),
			'include'         => self::text( $input, 'include' ),
			'exclude'         => self::text( $input, 'exclude' ),
			'tax_relation'    => 'OR' === ( $input['tax_relation'] ?? '' ) ? 'OR' : 'AND',
			'tax_rules'       => $this->tax_rules( $input['tax_rules'] ?? [] ),
			'meta_relation'   => 'OR' === ( $input['meta_relation'] ?? '' ) ? 'OR' : 'AND',
			'meta_rules'      => $this->meta_rules( $input['meta_rules'] ?? [] ),
		];
	}

	public function validate( Definition $definition, DefinitionRepository $repository ): array {
		$errors = $this->validate_slug( $definition, $repository, 64 );

		if ( '' === $definition->get( 'title', '' ) ) {
			$errors['title'] = __( 'The title is required.', 'libreforge' );
		}

		if ( in_array( $definition->get( 'orderby' ), [ 'meta_value', 'meta_value_num' ], true ) && '' === $definition->get( 'order_meta_key', '' ) ) {
			$errors['order_meta_key'] = __( 'Choose the field used to sort.', 'libreforge' );
		}

		return $errors;
	}

	/**
	 * @param mixed $rules
	 * @return array<int, array{taxonomy: string, field: string, terms: string, operator: string}>
	 */
	private function tax_rules( $rules ): array {
		$clean = [];

		foreach ( is_array( $rules ) ? $rules : [] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['taxonomy'] ) ) {
				continue;
			}

			$operator = strtoupper( (string) ( $rule['operator'] ?? 'IN' ) );

			$clean[] = [
				'taxonomy' => sanitize_key( (string) $rule['taxonomy'] ),
				'field'    => in_array( $rule['field'] ?? '', [ 'term_id', 'slug', 'name' ], true ) ? $rule['field'] : 'term_id',
				'terms'    => sanitize_text_field( (string) ( $rule['terms'] ?? '' ) ),
				'operator' => in_array( $operator, [ 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' ], true ) ? $operator : 'IN',
			];
		}

		return $clean;
	}

	/**
	 * @param mixed $rules
	 * @return array<int, array{key: string, compare: string, value: string, type: string}>
	 */
	private function meta_rules( $rules ): array {
		$clean = [];

		foreach ( is_array( $rules ) ? $rules : [] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['key'] ) ) {
				continue;
			}

			$compare = strtoupper( (string) ( $rule['compare'] ?? '=' ) );
			$type    = strtoupper( (string) ( $rule['type'] ?? 'CHAR' ) );

			$clean[] = [
				'key'     => sanitize_key( (string) $rule['key'] ),
				'compare' => in_array( $compare, self::META_COMPARE, true ) ? $compare : '=',
				'value'   => sanitize_text_field( (string) ( $rule['value'] ?? '' ) ),
				'type'    => in_array( $type, self::META_TYPES, true ) ? $type : 'CHAR',
			];
		}

		return $clean;
	}
}
