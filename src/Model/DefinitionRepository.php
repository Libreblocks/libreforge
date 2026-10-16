<?php
/**
 * Storage of definitions in {prefix}lbforge_definitions.
 *
 * All active definitions are loaded with one query per request and kept in
 * the object cache; any write flushes that cache.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model;

use LibreBlocks\Core\Support\Cache;
use LibreBlocks\Core\Support\Json;

defined( 'ABSPATH' ) || exit;

final class DefinitionRepository {

	public const TABLE = 'lbforge_definitions';

	private const CACHE_GROUP = 'lbforge_definitions';

	/** @var array<string, Definition[]>|null Active definitions by kind, for this request. */
	private ?array $active = null;

	public function __construct( private Cache $cache ) {}

	public static function columns(): string {
		return "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
kind varchar(32) NOT NULL,
slug varchar(191) NOT NULL,
status varchar(20) NOT NULL DEFAULT 'active',
config longtext NOT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY kind_slug (kind,slug),
KEY kind_status (kind,status)";
	}

	/**
	 * Active definitions of a kind, in creation order.
	 *
	 * @return Definition[]
	 */
	public function active( string $kind ): array {
		if ( null === $this->active ) {
			$rows = $this->cache->remember( 'active', self::CACHE_GROUP, function () {
				global $wpdb;

				return $wpdb->get_results(
					$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE status = %s ORDER BY id ASC', Definition::ACTIVE ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					ARRAY_A
				) ?: [];
			} );

			$this->active = [];

			foreach ( $rows as $row ) {
				$definition                        = $this->hydrate( $row );
				$this->active[ $definition->kind ][] = $definition;
			}
		}

		return $this->active[ $kind ] ?? [];
	}

	/**
	 * Every definition of a kind, any status (admin listings).
	 *
	 * @return Definition[]
	 */
	public function all( string $kind ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE kind = %s ORDER BY id ASC', $kind ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		) ?: [];

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	public function find( int $id ): ?Definition {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $row ? $this->hydrate( $row ) : null;
	}

	public function find_by_slug( string $kind, string $slug ): ?Definition {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE kind = %s AND slug = %s', $kind, $slug ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Inserts or updates. Returns the saved definition (with id and timestamps).
	 */
	public function save( Definition $definition ): Definition {
		global $wpdb;

		$now  = current_time( 'mysql', true );
		$data = [
			'kind'       => $definition->kind,
			'slug'       => $definition->slug,
			'status'     => $definition->status,
			'config'     => Json::encode( $definition->config ),
			'updated_at' => $now,
		];

		if ( $definition->id > 0 ) {
			$wpdb->update( $this->table(), $data, [ 'id' => $definition->id ] );
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $this->table(), $data );
			$definition->id = (int) $wpdb->insert_id;
		}

		$this->flush( $definition->kind );

		return $this->find( $definition->id ) ?? $definition;
	}

	public function delete( Definition $definition ): void {
		global $wpdb;

		$wpdb->delete( $this->table(), [ 'id' => $definition->id ], [ '%d' ] );
		$this->flush( $definition->kind );
	}

	private function flush( string $kind ): void {
		$this->active = null;
		$this->cache->flush_group( self::CACHE_GROUP );

		/**
		 * Fires after a definition is created, updated or deleted.
		 *
		 * @param string $kind
		 */
		do_action( 'libreforge/definitions/changed', $kind );
	}

	private function table(): string {
		return libreblocks()->schema()->table( self::TABLE );
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function hydrate( array $row ): Definition {
		return new Definition(
			(string) $row['kind'],
			(string) $row['slug'],
			Json::decode( $row['config'] ?? '' ),
			(string) $row['status'],
			(int) $row['id'],
			(string) $row['created_at'],
			(string) $row['updated_at']
		);
	}
}
