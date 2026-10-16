<?php

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\FieldList;
use LibreBlocks\Forge\Model\Kinds\FieldGroupKind;
use LibreBlocks\Forge\Model\Kinds\GlossaryKind;
use LibreBlocks\Forge\Model\Kinds\OptionsPageKind;
use LibreBlocks\Forge\Model\Kinds\PostTypeKind;
use LibreBlocks\Forge\Model\Kinds\TaxonomyKind;
use LibreBlocks\Core\Fields\Registry;
use PHPUnit\Framework\TestCase;

final class KindsTest extends TestCase {

	private DefinitionRepository $repository;
	private FieldList $fields;

	protected function setUp(): void {
		global $wpdb;

		WPStub::reset();
		WPStub::$registered = [];
		$wpdb               = new FakeWpdb();
		$this->repository   = new DefinitionRepository( libreblocks()->cache() );
		$this->fields       = new FieldList( Registry::with_defaults() );
	}

	private function definition( $kind, string $slug, array $input ): Definition {
		return new Definition( $kind->id(), $slug, $kind->sanitize( $input ) );
	}

	public function test_post_type_defaults_and_args(): void {
		$kind       = new PostTypeKind( $this->fields );
		$definition = $this->definition( $kind, 'imovel', [ 'singular' => 'Imóvel', 'plural' => 'Imóveis', 'supports' => [ 'title', 'evil' ], 'menu_icon' => 'javascript:alert(1)' ] );

		$this->assertSame( [], $kind->validate( $definition, $this->repository ) );
		$this->assertSame( [ 'title' ], $definition->config['supports'] );

		$args = PostTypeKind::to_args( $definition );

		$this->assertSame( 'Imóveis', $args['labels']['name'] );
		$this->assertSame( 'Add Imóvel', $args['labels']['add_new_item'] );
		$this->assertSame( 'Nothing found in imóveis.', $args['labels']['not_found'] );
		$this->assertSame( [ 'slug' => 'imovel', 'with_front' => true ], $args['rewrite'] );
		$this->assertTrue( $args['show_in_rest'] );
		$this->assertSame( 'dashicons-admin-post', $args['menu_icon'] );
		$this->assertArrayNotHasKey( 'menu_position', $args );
	}

	public function test_private_post_type_has_no_rewrite(): void {
		$definition = $this->definition( new PostTypeKind( $this->fields ), 'lead', [ 'singular' => 'Lead', 'plural' => 'Leads', 'public' => false ] );
		$args       = PostTypeKind::to_args( $definition );

		$this->assertFalse( $args['rewrite'] );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertTrue( $args['exclude_from_search'] );
	}

	public function test_post_type_slug_rules(): void {
		$kind = new PostTypeKind( $this->fields );

		$this->assertArrayHasKey( 'slug', $kind->validate( $this->definition( $kind, 'page', [ 'singular' => 'a', 'plural' => 'b' ] ), $this->repository ) );
		$this->assertArrayHasKey( 'slug', $kind->validate( $this->definition( $kind, 'year', [ 'singular' => 'a', 'plural' => 'b' ] ), $this->repository ) );
		$this->assertArrayHasKey( 'slug', $kind->validate( $this->definition( $kind, str_repeat( 'a', 21 ), [ 'singular' => 'a', 'plural' => 'b' ] ), $this->repository ) );
		$this->assertSame( [ 'singular', 'plural' ], array_keys( $kind->validate( $this->definition( $kind, 'ok', [] ), $this->repository ) ) );

		WPStub::$registered['post_types'] = [ 'product' ];
		$this->assertArrayHasKey( 'slug', $kind->validate( $this->definition( $kind, 'product', [ 'singular' => 'a', 'plural' => 'b' ] ), $this->repository ) );

		$this->assertSame( 'imovel_de_luxo', $kind->suggest_slug( [ 'singular' => 'Imóvel de luxo' ] ) );
	}

	public function test_slug_must_be_unique_per_kind(): void {
		$kind  = new TaxonomyKind( $this->fields );
		$first = $this->repository->save( $this->definition( $kind, 'cidade', [ 'singular' => 'Cidade', 'plural' => 'Cidades' ] ) );

		$this->assertSame( [], $kind->validate( $first, $this->repository ), 'Saving again keeps the same slug.' );
		$this->assertArrayHasKey( 'slug', $kind->validate( $this->definition( $kind, 'cidade', [ 'singular' => 'C', 'plural' => 'Cs' ] ), $this->repository ) );

		// Same slug in another kind is fine.
		$glossary = new GlossaryKind();
		$this->assertSame( [], $glossary->validate( $this->definition( $glossary, 'cidade', [ 'title' => 'Cidades', 'options' => "sp : São Paulo" ] ), $this->repository ) );
	}

	public function test_taxonomy_args(): void {
		$definition = $this->definition( new TaxonomyKind( $this->fields ), 'tag_imovel', [ 'singular' => 'Etiqueta', 'plural' => 'Etiquetas', 'hierarchical' => false, 'object_types' => [ 'imovel' ] ] );
		$args       = TaxonomyKind::to_args( $definition );

		$this->assertFalse( $args['hierarchical'] );
		$this->assertTrue( $args['show_tagcloud'], 'Flat taxonomies show the tag cloud by default.' );
		$this->assertSame( 'tag_imovel', $args['rewrite']['slug'] );
		$this->assertSame( [ 'imovel' ], $definition->config['object_types'] );
	}

	public function test_field_group_validation(): void {
		$kind = new FieldGroupKind( $this->fields );

		$definition = $this->definition( $kind, 'dados', [
			'title'      => 'Dados do imóvel',
			'object'     => 'post',
			'post_types' => [ 'imovel' ],
			'fields'     => [
				[ 'label' => 'Preço', 'name' => 'preco', 'type' => 'number', 'step' => 0.01 ],
				[ 'label' => 'Preço 2', 'name' => 'preco', 'type' => 'number' ],
				[ 'label' => 'Sem nome', 'name' => '', 'type' => 'text' ],
				[ 'label' => 'Aba', 'type' => 'tab' ],
				[ 'label' => 'Lock', 'name' => '_edit_lock', 'type' => 'text' ],
				[ 'label' => 'X', 'name' => 'x', 'type' => 'nope' ],
				[ 'label' => 'Cômodos', 'name' => 'comodos', 'type' => 'repeater', 'fields' => [
					[ 'label' => 'A', 'name' => 'a', 'type' => 'text' ],
					[ 'label' => 'B', 'name' => 'a', 'type' => 'text' ],
				] ],
			],
		] );

		$errors = $kind->validate( $definition, $this->repository );

		$this->assertSame( [ 'fields.1', 'fields.2', 'fields.4', 'fields.5', 'fields.6.fields.1' ], array_keys( $errors ) );
		$this->assertSame( 0.01, $definition->config['fields'][0]['step'] );

		$missing = $kind->validate( $this->definition( $kind, 'x', [ 'title' => 'X', 'object' => 'term' ] ), $this->repository );
		$this->assertSame( [ 'taxonomies' ], array_keys( $missing ) );
	}

	public function test_field_list_sanitizes_markup_and_conditions(): void {
		$clean = $this->fields->sanitize( [
			[
				'label'       => '<script>x</script>Preço',
				'name'        => 'preço-total',
				'type'        => 'select',
				'options'     => [ [ 'value' => '<b>a</b>', 'label' => '<i>A</i>' ] ],
				'width'       => '13%',
				'content'     => '<p>ok</p><script>bad</script>',
				'placeholder' => '<b>Digite</b>',
				'conditions'  => [ 'relation' => 'or', 'rules' => [ [ 'field' => 'kind', 'operator' => 'DROP', 'value' => 'x' ], 'junk' ] ],
			],
		] )[0];

		$this->assertSame( 'xPreço', $clean['label'] );
		$this->assertSame( 'preo_total', $clean['name'] );
		$this->assertSame( [ [ 'value' => 'a', 'label' => 'A' ] ], $clean['options'] );
		$this->assertSame( '100%', $clean['width'] );
		$this->assertSame( '<p>ok</p>', $clean['content'] );
		$this->assertSame( 'Digite', $clean['placeholder'] );
		$this->assertSame( [ 'relation' => 'OR', 'rules' => [ [ 'field' => 'kind', 'operator' => '=', 'value' => 'x' ] ] ], $clean['conditions'] );
	}

	public function test_options_page_parent_whitelist(): void {
		$kind = new OptionsPageKind( $this->fields );

		$this->assertSame( '', $kind->sanitize( [ 'parent' => 'javascript:x' ] )['parent'], 'Unknown parents fall back to an own top-level menu.' );
		$this->assertSame( 'libreforge', $kind->sanitize( [ 'parent' => 'libreforge' ] )['parent'] );
		$this->assertSame( 'edit.php?post_type=imovel', $kind->sanitize( [ 'parent' => 'edit.php?post_type=imovel' ] )['parent'] );
		$this->assertSame( '', $kind->sanitize( [ 'parent' => '' ] )['parent'] );
		$this->assertSame( 'lbforge_contato', OptionsPageKind::option_name( 'contato' ) );
	}

	public function test_glossary_options_from_text(): void {
		$config = ( new GlossaryKind() )->sanitize( [ 'title' => 'Estados', 'options' => "sp : São Paulo\nrj : Rio de Janeiro\nsp : Duplicado\n\nmg" ] );

		$this->assertSame( [
			[ 'value' => 'sp', 'label' => 'São Paulo' ],
			[ 'value' => 'rj', 'label' => 'Rio de Janeiro' ],
			[ 'value' => 'mg', 'label' => 'mg' ],
		], $config['options'] );
	}
}
