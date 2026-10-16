<?php
/**
 * Unit-test bootstrap for LibreForge: reuses the core stubs and adds an
 * in-memory $wpdb that understands the repository's queries.
 */

require dirname( __DIR__, 2 ) . '/libreblocks-core/tests/bootstrap.php';
require dirname( __DIR__, 2 ) . '/libreblocks-core/src/functions.php';

define( 'LIBREFORGE_DIR', dirname( __DIR__ ) . '/' );

LibreBlocks\Core\Autoloader::register( 'LibreBlocks\\Forge\\', dirname( __DIR__ ) . '/src/' );

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

function add_action( ...$args ) {}
function do_action( ...$args ) {}
function is_admin() { return false; }
function sanitize_title( $title ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( strtr( (string) $title, [ 'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c' ] ) ) ), '-' ); }
function current_time( $type, $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
function post_type_exists( $slug ) { return in_array( $slug, WPStub::$registered['post_types'] ?? [], true ); }
function taxonomy_exists( $slug ) { return in_array( $slug, WPStub::$registered['taxonomies'] ?? [], true ); }

/** In-memory replacement for the few $wpdb calls DefinitionRepository makes. */
final class FakeWpdb {
	public string $prefix = 'wp_';
	public int $insert_id = 0;
	public array $rows    = [];

	public function prepare( $sql, ...$args ) {
		return [ $sql, $args ];
	}

	public function get_results( $query, $output = null ) {
		[ $sql, $args ] = $query;

		if ( false !== strpos( $sql, 'WHERE status' ) ) {
			return array_values( array_filter( $this->rows, fn( $row ) => $row['status'] === $args[0] ) );
		}

		return array_values( array_filter( $this->rows, fn( $row ) => $row['kind'] === $args[0] ) );
	}

	public function get_row( $query, $output = null ) {
		[ $sql, $args ] = $query;

		foreach ( $this->rows as $row ) {
			$match = false !== strpos( $sql, 'WHERE id' )
				? (int) $row['id'] === (int) $args[0]
				: $row['kind'] === $args[0] && $row['slug'] === $args[1];

			if ( $match ) {
				return $row;
			}
		}

		return null;
	}

	public function insert( $table, $data ) {
		$this->insert_id = count( $this->rows ) + 1;
		$this->rows[ $this->insert_id ] = [ 'id' => $this->insert_id ] + $data;
		return 1;
	}

	public function update( $table, $data, $where ) {
		$this->rows[ $where['id'] ] = array_merge( $this->rows[ $where['id'] ], $data );
		return 1;
	}

	public function delete( $table, $where ) {
		unset( $this->rows[ $where['id'] ] );
		return 1;
	}
}

WPStub::$registered = [];
LibreBlocks\Core\Plugin::boot();
