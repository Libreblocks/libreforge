<?php

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kinds\RelationKind;
use LibreBlocks\Forge\Relations\RelationStore;
use PHPUnit\Framework\TestCase;

final class RelationsTest extends TestCase {

	private RelationStore $store;

	protected function setUp(): void {
		global $wpdb;

		WPStub::reset();
		$wpdb        = new FakeWpdb();
		$this->store = new RelationStore();
	}

	private function relation( string $type, int $id = 1 ): Definition {
		return new Definition( 'relation', 'r' . $id, ( new RelationKind() )->sanitize( [ 'title' => 'R', 'parent_object' => 'post:corretor', 'child_object' => 'post:imovel', 'type' => $type ] ), 'active', $id );
	}

	public function test_one_to_many_moves_a_child_to_its_new_parent(): void {
		$relation = $this->relation( 'one_to_many' );

		$this->store->set_children( $relation, 10, [ 100, 101, 102 ] );
		$this->store->set_children( $relation, 20, [ 101 ] );

		$this->assertSame( [ 100, 102 ], $this->store->children( 1, 10 ), 'Property 101 left broker 10.' );
		$this->assertSame( [ 101 ], $this->store->children( 1, 20 ) );
		$this->assertSame( [ 20 ], $this->store->parents( 1, 101 ) );

		// From the child side, only one parent is kept.
		$this->store->set_parents( $relation, 100, [ 30, 40 ] );
		$this->assertSame( [ 30 ], $this->store->parents( 1, 100 ) );
	}

	public function test_many_to_many_keeps_every_link_and_order(): void {
		$relation = $this->relation( 'many_to_many' );

		$this->store->set_children( $relation, 1, [ 7, 5, 9, 5 ] );
		$this->store->set_children( $relation, 2, [ 5 ] );

		$this->assertSame( [ 7, 5, 9 ], $this->store->children( 1, 1 ), 'Order kept, duplicates removed.' );
		$this->assertSame( [ 1, 2 ], $this->store->parents( 1, 5 ) );
	}

	public function test_one_to_one_on_both_sides(): void {
		$relation = $this->relation( 'one_to_one' );

		$this->store->set_children( $relation, 1, [ 50, 51 ] );
		$this->assertSame( [ 50 ], $this->store->children( 1, 1 ) );

		$this->store->set_parents( $relation, 60, [ 1 ] );
		$this->assertSame( [ 60 ], $this->store->children( 1, 1 ), 'Parent 1 now points only to 60.' );
		$this->assertSame( [], $this->store->parents( 1, 50 ) );
	}

	public function test_relations_are_isolated_and_forgotten(): void {
		$a = $this->relation( 'many_to_many', 1 );
		$b = $this->relation( 'many_to_many', 2 );

		$this->store->set_children( $a, 1, [ 5 ] );
		$this->store->set_children( $b, 1, [ 6 ] );
		$this->assertSame( [ 5 ], $this->store->children( 1, 1 ) );
		$this->assertSame( [ 6 ], $this->store->children( 2, 1 ) );

		$this->store->forget( 5, [], [ 1 ] );
		$this->assertSame( [], $this->store->children( 1, 1 ) );
		$this->assertSame( [ 6 ], $this->store->children( 2, 1 ) );
	}

	public function test_relation_kind_rules(): void {
		$kind   = new RelationKind();
		$config = $kind->sanitize( [ 'parent_object' => 'post:', 'child_object' => 'evil:x', 'type' => 'all' ] );

		$this->assertSame( '', $config['parent_object'] );
		$this->assertSame( '', $config['child_object'] );
		$this->assertSame( 'one_to_many', $config['type'] );
		$this->assertSame( 'user', $kind->sanitize( [ 'parent_object' => 'user' ] )['parent_object'] );
		$this->assertSame( [ 'title', 'parent_object', 'child_object' ], array_keys( array_diff_key( $kind->validate( new Definition( 'relation', 'x', $config ), new DefinitionRepository( libreblocks()->cache() ) ), [] ) ) );
		$this->assertTrue( RelationKind::single_parent( 'one_to_many' ) );
		$this->assertFalse( RelationKind::single_child( 'one_to_many' ) );
	}
}
