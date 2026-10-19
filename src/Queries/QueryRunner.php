<?php
/**
 * Turns a query config (from the Query Builder or a block/widget) into WordPress
 * query arguments, resolves tokens and runs it.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Queries;

use LibreBlocks\Core\Tokens\Registry as Tokens;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kinds\QueryKind;

defined( 'ABSPATH' ) || exit;

final class QueryRunner {

	public function __construct( private DefinitionRepository $repository, private Tokens $tokens ) {}

	public function find( int $id ): ?Definition {
		foreach ( $this->repository->active( QueryKind::ID ) as $query ) {
			if ( $query->id === $id ) {
				return $query;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $config   Sanitized QueryKind config.
	 * @param array<string, mixed> $override Extra WordPress args merged last (filters use this).
	 */
	public function run( array $config, int $page = 1, array $override = [], string $query_id = '' ): QueryResult {
		$page = max( 1, $page );
		$type = (string) ( $config['type'] ?? 'posts' );
		$args = $this->build_args( $config, $page );

		/**
		 * Filters the WordPress arguments of a LibreForge query right before it runs.
		 * LibreSift hooks here to apply the visitor's filters.
		 *
		 * @param array<string, mixed> $args
		 * @param array<string, mixed> $config
		 * @param string               $query_id Saved query id or listing instance id.
		 */
		$args = (array) apply_filters( 'libreforge/query/args', array_replace_recursive( $args, $override ), $config, $query_id );

		/**
		 * Lets other plugins run their own query types (e.g. content tables).
		 *
		 * @param QueryResult|null     $result
		 * @param array<string, mixed> $config
		 * @param int                  $page
		 * @param array<string, mixed> $args   The WordPress-style args after "libreforge/query/args".
		 */
		$custom = apply_filters( 'libreforge/query/run', null, $config, $page, $args );

		if ( $custom instanceof QueryResult ) {
			return $custom;
		}

		switch ( $type ) {
			case 'terms':
				return $this->run_terms( $args, $page );
			case 'users':
				return $this->run_users( $args, $page );
			default:
				return $this->run_posts( $args, $page );
		}
	}

	/**
	 * @param array<string, mixed> $config
	 * @return array<string, mixed>
	 */
	public function build_args( array $config, int $page = 1 ): array {
		$type     = (string) ( $config['type'] ?? 'posts' );
		$per_page = (int) ( $config['per_page'] ?? 10 );
		$offset   = (int) ( $config['offset'] ?? 0 );
		$orderby  = (string) ( $config['orderby'] ?? '' );
		$order    = (string) ( $config['order'] ?? 'DESC' );
		$meta_key = (string) ( $config['order_meta_key'] ?? '' );
		$include  = $this->ids( $config['include'] ?? '' );
		$exclude  = $this->ids( $config['exclude'] ?? '' );
		$search   = $this->text( $config['search'] ?? '' );
		$meta     = $this->meta_query( (array) ( $config['meta_rules'] ?? [] ), (string) ( $config['meta_relation'] ?? 'AND' ) );

		if ( 'terms' === $type ) {
			$args = [
				'taxonomy'   => (array) ( $config['taxonomies'] ?? [ 'category' ] ),
				'hide_empty' => ! empty( $config['hide_empty'] ),
				'orderby'    => $orderby ?: 'name',
				'order'      => $order,
				'number'     => $per_page > 0 ? $per_page : 0,
				'offset'     => $per_page > 0 ? $offset + ( $page - 1 ) * $per_page : $offset,
				'include'    => $include,
				'exclude'    => $exclude,
				'search'     => $search,
			];

			$parent = $this->text( $config['parent'] ?? '' );

			if ( '' !== $parent ) {
				$args['parent'] = (int) $parent;
			}
		} elseif ( 'users' === $type ) {
			$args = [
				'role__in' => (array) ( $config['roles'] ?? [] ),
				'orderby'  => $orderby ?: 'display_name',
				'order'    => $order,
				'number'   => $per_page > 0 ? $per_page : -1,
				'offset'   => $per_page > 0 ? $offset + ( $page - 1 ) * $per_page : $offset,
				'include'  => $include,
				'exclude'  => $exclude,
			];

			if ( '' !== $search ) {
				$args['search'] = '*' . $search . '*';
			}
		} else {
			$args = [
				'post_type'           => (array) ( $config['post_types'] ?? [ 'post' ] ),
				'post_status'         => (array) ( $config['statuses'] ?? [ 'publish' ] ),
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'orderby'             => $orderby ?: 'date',
				'order'               => $order,
				'ignore_sticky_posts' => ! empty( $config['ignore_sticky'] ),
				's'                   => $search,
				'post__in'            => $include,
				'post__not_in'        => $exclude,
			];

			// WP_Query ignores "paged" when "offset" is set, so the offset is added per page.
			if ( $offset > 0 && $per_page > 0 ) {
				$args['offset'] = $offset + ( $page - 1 ) * $per_page;
			}

			$author = $this->text( $config['author'] ?? '' );

			if ( '' !== $author ) {
				$args['author__in'] = $this->ids( $author );
			}

			if ( ! empty( $config['exclude_current'] ) && is_singular() ) {
				$args['post__not_in'][] = get_queried_object_id();
			}

			$tax = $this->tax_query( (array) ( $config['tax_rules'] ?? [] ), (string) ( $config['tax_relation'] ?? 'AND' ) );

			if ( $tax ) {
				$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			}
		}

		$this->apply_relation( $args, $config, $type );

		if ( $meta ) {
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( in_array( $orderby, [ 'meta_value', 'meta_value_num' ], true ) && '' !== $meta_key ) {
			$args['meta_key'] = $meta_key; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}

		return array_filter( $args, fn( $value ) => [] !== $value && '' !== $value );
	}

	/**
	 * Limits the results to the items related to the current object. With no related
	 * items the query returns nothing (not everything).
	 *
	 * @param array<string, mixed> $args
	 * @param array<string, mixed> $config
	 */
	private function apply_relation( array &$args, array $config, string $type ): void {
		$relation = (string) ( $config['relation'] ?? '' );

		if ( '' === $relation ) {
			return;
		}

		$side = 'parents' === ( $config['relation_side'] ?? '' ) ? 'parents' : 'children';
		$ids  = $this->ids( '{{relation.' . $side . '|' . $relation . '}}' );
		$key  = 'posts' === $type ? 'post__in' : 'include';

		if ( ! empty( $args[ $key ] ) ) {
			$ids = array_values( array_intersect( (array) $args[ $key ], $ids ) );
		}

		$args[ $key ] = $ids ?: [ 0 ];
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function run_posts( array $args, int $page ): QueryResult {
		$query = new \WP_Query( $args );

		return new QueryResult( 'posts', $query->posts, (int) $query->found_posts, $page, (int) ( $args['posts_per_page'] ?? 10 ) );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function run_terms( array $args, int $page ): QueryResult {
		$terms = get_terms( $args );
		$count = wp_count_terms( array_diff_key( $args, array_flip( [ 'number', 'offset', 'orderby', 'order' ] ) ) );

		return new QueryResult(
			'terms',
			is_wp_error( $terms ) ? [] : array_values( $terms ),
			is_wp_error( $count ) ? 0 : (int) $count,
			$page,
			(int) ( $args['number'] ?? 0 )
		);
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function run_users( array $args, int $page ): QueryResult {
		$query = new \WP_User_Query( $args + [ 'count_total' => true ] );

		return new QueryResult( 'users', (array) $query->get_results(), (int) $query->get_total(), $page, (int) ( $args['number'] ?? -1 ) );
	}

	/**
	 * @param array<int, array<string, string>> $rules
	 * @return array<int|string, mixed>
	 */
	private function tax_query( array $rules, string $relation ): array {
		$query = [];

		foreach ( $rules as $rule ) {
			$terms = in_array( $rule['operator'], [ 'EXISTS', 'NOT EXISTS' ], true ) ? [] : $this->list( $rule['terms'] );

			if ( ! $terms && ! in_array( $rule['operator'], [ 'EXISTS', 'NOT EXISTS' ], true ) ) {
				continue; // An empty token (e.g. no URL parameter) means "don't filter".
			}

			$query[] = [
				'taxonomy' => $rule['taxonomy'],
				'field'    => $rule['field'],
				'terms'    => 'term_id' === $rule['field'] ? array_map( 'absint', $terms ) : $terms,
				'operator' => $rule['operator'],
			];
		}

		return count( $query ) > 1 ? [ 'relation' => $relation ] + $query : $query;
	}

	/**
	 * @param array<int, array<string, string>> $rules
	 * @return array<int|string, mixed>
	 */
	private function meta_query( array $rules, string $relation ): array {
		$query = [];

		foreach ( $rules as $rule ) {
			$clause = [ 'key' => $rule['key'], 'compare' => $rule['compare'], 'type' => $rule['type'] ];

			if ( ! in_array( $rule['compare'], [ 'EXISTS', 'NOT EXISTS' ], true ) ) {
				$value = $this->tokens->resolve( $rule['value'] );

				if ( in_array( $rule['compare'], [ 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ], true ) ) {
					$value = $this->list( $value );
				}

				if ( null === $value || '' === $value || [] === $value ) {
					continue; // An empty token means "don't filter".
				}

				$clause['value'] = $value;
			}

			$query[] = $clause;
		}

		return count( $query ) > 1 ? [ 'relation' => $relation ] + $query : $query;
	}

	/**
	 * @param mixed $value
	 */
	private function text( $value ): string {
		$value = $this->tokens->resolve( $value );

		return is_array( $value ) ? implode( ',', $value ) : trim( (string) $value );
	}

	/**
	 * "1, 2, {{user.id}}" → [ '1', '2', '7' ].
	 *
	 * @param mixed $value
	 * @return string[]
	 */
	private function list( $value ): array {
		$value = $this->tokens->resolve( $value );
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );

		return array_values( array_filter( array_map( fn( $item ) => trim( (string) $item ), $items ), 'strlen' ) );
	}

	/**
	 * @param mixed $value
	 * @return int[]
	 */
	private function ids( $value ): array {
		return array_values( array_filter( array_map( 'absint', $this->list( $value ) ) ) );
	}
}
