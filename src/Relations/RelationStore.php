<?php
/**
 * Links between related objects, in one table for every relation:
 * {prefix}lbforge_relations (relation_id, parent_id, child_id, position).
 *
 * Cardinality is enforced when saving: in "one to many" a child keeps a single
 * parent, in "one to one" both sides keep a single link.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Relations;

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\Kinds\RelationKind;

defined( 'ABSPATH' ) || exit;

final class RelationStore {

	public const TABLE = 'lbforge_relations';

	public static function columns(): string {
		return "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
relation_id bigint(20) unsigned NOT NULL,
parent_id bigint(20) unsigned NOT NULL,
child_id bigint(20) unsigned NOT NULL,
position int(11) NOT NULL DEFAULT 0,
PRIMARY KEY  (id),
KEY parent (relation_id,parent_id),
KEY child (relation_id,child_id)";
	}

	/**
	 * @return int[] Child IDs in the saved order.
	 */
	public function children( int $relation_id, int $parent_id ): array {
		global $wpdb;

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			'SELECT child_id FROM ' . $this->table() . ' WHERE relation_id = %d AND parent_id = %d ORDER BY position ASC, id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$relation_id,
			$parent_id
		) ) );
	}

	/**
	 * @return int[]
	 */
	public function parents( int $relation_id, int $child_id ): array {
		global $wpdb;

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			'SELECT parent_id FROM ' . $this->table() . ' WHERE relation_id = %d AND child_id = %d ORDER BY id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$relation_id,
			$child_id
		) ) );
	}

	/**
	 * Replaces the children of a parent (in the given order).
	 *
	 * @param int[] $children
	 */
	public function set_children( Definition $relation, int $parent_id, array $children ): void {
		global $wpdb;

		$type     = (string) $relation->get( 'type' );
		$children = array_values( array_unique( array_filter( array_map( 'absint', $children ) ) ) );

		if ( RelationKind::single_child( $type ) ) {
			$children = array_slice( $children, 0, 1 );
		}

		$wpdb->delete( $this->table(), [ 'relation_id' => $relation->id, 'parent_id' => $parent_id ] );

		foreach ( $children as $position => $child_id ) {
			// A child that may only have one parent leaves its previous parent.
			if ( RelationKind::single_parent( $type ) ) {
				$wpdb->delete( $this->table(), [ 'relation_id' => $relation->id, 'child_id' => $child_id ] );
			}

			$wpdb->insert( $this->table(), [
				'relation_id' => $relation->id,
				'parent_id'   => $parent_id,
				'child_id'    => $child_id,
				'position'    => $position,
			] );
		}
	}

	/**
	 * Replaces the parents of a child.
	 *
	 * @param int[] $parents
	 */
	public function set_parents( Definition $relation, int $child_id, array $parents ): void {
		global $wpdb;

		$type    = (string) $relation->get( 'type' );
		$parents = array_values( array_unique( array_filter( array_map( 'absint', $parents ) ) ) );

		if ( RelationKind::single_parent( $type ) ) {
			$parents = array_slice( $parents, 0, 1 );
		}

		$wpdb->delete( $this->table(), [ 'relation_id' => $relation->id, 'child_id' => $child_id ] );

		foreach ( $parents as $parent_id ) {
			// In one to one, the parent leaves its previous child.
			if ( RelationKind::single_child( $type ) ) {
				$wpdb->delete( $this->table(), [ 'relation_id' => $relation->id, 'parent_id' => $parent_id ] );
			}

			$wpdb->insert( $this->table(), [
				'relation_id' => $relation->id,
				'parent_id'   => $parent_id,
				'child_id'    => $child_id,
				'position'    => count( $this->children( $relation->id, $parent_id ) ),
			] );
		}
	}

	/**
	 * Removes every link of an object that was deleted.
	 *
	 * @param int[] $as_parent Relation IDs where the object is the parent side.
	 * @param int[] $as_child  Relation IDs where the object is the child side.
	 */
	public function forget( int $object_id, array $as_parent, array $as_child ): void {
		global $wpdb;

		foreach ( $as_parent as $relation_id ) {
			$wpdb->delete( $this->table(), [ 'relation_id' => $relation_id, 'parent_id' => $object_id ] );
		}

		foreach ( $as_child as $relation_id ) {
			$wpdb->delete( $this->table(), [ 'relation_id' => $relation_id, 'child_id' => $object_id ] );
		}
	}

	private function table(): string {
		return libreblocks()->schema()->table( self::TABLE );
	}
}
