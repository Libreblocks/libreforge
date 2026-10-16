<?php

use LibreBlocks\Core\Fields\Registry;
use LibreBlocks\Forge\Fields\FieldGroups;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\FieldList;
use LibreBlocks\Forge\Model\Kinds\PostTypeKind;
use LibreBlocks\Forge\Model\Kinds\TaxonomyKind;
use PHPUnit\Framework\TestCase;

/**
 * Meta fields created directly inside a post type or taxonomy.
 */
final class TypeFieldsTest extends TestCase {

	private DefinitionRepository $repository;
	private Registry $registry;
	private FieldList $fields;

	protected function setUp(): void {
		global $wpdb;

		WPStub::reset();
		$wpdb             = new FakeWpdb();
		$this->repository = new DefinitionRepository( libreblocks()->cache() );
		$this->registry   = Registry::with_defaults();
		$this->fields     = new FieldList( $this->registry );
	}

	public function test_post_type_keeps_and_validates_its_fields(): void {
		$kind   = new PostTypeKind( $this->fields );
		$config = $kind->sanitize( [
			'singular'       => 'Imóvel',
			'plural'         => 'Imóveis',
			'fields_context' => 'evil',
			'fields'         => [
				[ 'label' => 'Preço', 'name' => 'preco', 'type' => 'number' ],
				[ 'label' => 'Preço de novo', 'name' => 'preco', 'type' => 'text' ],
			],
		] );

		$this->assertSame( 'normal', $config['fields_context'] );
		$this->assertCount( 2, $config['fields'] );
		$this->assertSame( [ 'fields.1' ], array_keys( $kind->validate( new Definition( 'post_type', 'imovel', $config ), $this->repository ) ) );
	}

	public function test_type_fields_become_groups_on_their_screens(): void {
		$this->repository->save( new Definition( 'post_type', 'imovel', ( new PostTypeKind( $this->fields ) )->sanitize( [
			'singular'     => 'Imóvel',
			'plural'       => 'Imóveis',
			'fields_title' => 'Dados do imóvel',
			'fields'       => [ [ 'label' => 'Preço', 'name' => 'preco', 'type' => 'number' ] ],
		] ) ) );

		$this->repository->save( new Definition( 'taxonomy', 'cidade', ( new TaxonomyKind( $this->fields ) )->sanitize( [
			'singular' => 'Cidade',
			'plural'   => 'Cidades',
			'fields'   => [ [ 'label' => 'Foto', 'name' => 'foto', 'type' => 'media' ] ],
		] ) ) );

		// A post type without fields adds no group.
		$this->repository->save( new Definition( 'post_type', 'vazio', ( new PostTypeKind( $this->fields ) )->sanitize( [ 'singular' => 'V', 'plural' => 'Vs' ] ) ) );

		$groups = new FieldGroups( $this->repository, $this->registry );

		$post_groups = $groups->groups_for( 'post', 'imovel' );
		$this->assertCount( 1, $post_groups );
		$this->assertSame( 'Dados do imóvel', $post_groups[0]->title() );
		$this->assertSame( [ 'preco' ], array_map( fn( $field ) => $field->name, $groups->fields_of( $post_groups[0] ) ) );

		$term_groups = $groups->groups_for( 'term', 'cidade' );
		$this->assertCount( 1, $term_groups );
		$this->assertSame( 'Custom fields', $term_groups[0]->title() );

		$this->assertSame( [], $groups->groups_for( 'post', 'vazio' ) );
		$this->assertSame( [], $groups->groups_for( 'post', 'post' ) );
	}
}
