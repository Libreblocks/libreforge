<?php

use LibreBlocks\Core\Fields\Registry;
use LibreBlocks\Forge\Fields\FieldGroups;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\FieldList;
use LibreBlocks\Forge\Model\Kinds\PostTypeKind;
use LibreBlocks\Forge\Queries\QueryRunner;
use LibreBlocks\Forge\Render\Renderer;
use LibreBlocks\Forge\Render\Values;
use LibreBlocks\Forge\Templates\Templates;
use PHPUnit\Framework\TestCase;

final class RepeaterRenderTest extends TestCase {

	private Renderer $renderer;

	protected function setUp(): void {
		global $wpdb;

		WPStub::reset();
		$wpdb       = new FakeWpdb();
		$repository = new DefinitionRepository( libreblocks()->cache() );
		$registry   = Registry::with_defaults();
		$context    = libreblocks()->context();
		$tokens     = libreblocks()->tokens();

		$repository->save( new Definition( 'post_type', 'teste', ( new PostTypeKind( new FieldList( $registry ) ) )->sanitize( [
			'singular' => 'Teste',
			'plural'   => 'Testes',
			'fields'   => [
				[ 'label' => 'FAQ', 'name' => 'faq', 'type' => 'repeater', 'fields' => [
					[ 'label' => 'Pergunta', 'name' => 'pergunta', 'type' => 'text' ],
					[ 'label' => 'Resposta', 'name' => 'resposta', 'type' => 'textarea' ],
				] ],
			],
		] ) ) );

		$this->renderer = new Renderer(
			$context,
			new Values( $context, $tokens ),
			new QueryRunner( $repository, $tokens ),
			new Templates( $context ),
			new FieldGroups( $repository, $registry )
		);
	}

	public function test_rows_are_shown_automatically_with_every_sub_field(): void {
		WPStub::$meta['post'][5]['faq'] = [
			[ 'pergunta' => 'Aceita pet?', 'resposta' => 'Sim' ],
			[ 'pergunta' => 'Tem vaga?', 'resposta' => '' ],
		];

		$html = libreblocks()->context()->with(
			new WP_Post( [ 'ID' => 5, 'post_type' => 'teste', 'post_title' => 'teste 014' ] ),
			fn() => $this->renderer->repeater( [ 'source' => 'meta', 'key' => 'faq', 'format' => '<p></p>' ] )
		);

		$this->assertStringContainsString( '<strong class="lf-repeater__field lf-repeater__field--pergunta">Aceita pet?</strong>', $html );
		$this->assertStringContainsString( '<div class="lf-repeater__field lf-repeater__field--resposta"><p>Sim</p></div>', $html );
		$this->assertSame( 2, substr_count( $html, 'lf-repeater__item' ), 'Empty sub-fields are skipped, rows are not.' );
	}

	public function test_custom_row_format_reads_sub_fields(): void {
		WPStub::$meta['post'][5]['faq'] = [ [ 'pergunta' => 'P1', 'resposta' => 'R1' ] ];

		$html = libreblocks()->context()->with(
			new WP_Post( [ 'ID' => 5, 'post_type' => 'teste' ] ),
			fn() => $this->renderer->repeater( [ 'key' => 'faq', 'format' => '<b>{{object.meta|pergunta}}</b> {{object.meta|resposta}}' ] )
		);

		$this->assertStringContainsString( '<b>P1</b> R1', $html );
	}

	public function test_explains_why_nothing_shows_on_a_page(): void {
		$html = libreblocks()->context()->with(
			new WP_Post( [ 'ID' => 9, 'post_type' => 'page', 'post_title' => 'Home' ] ),
			fn() => $this->renderer->repeater( [ 'key' => 'faq' ] )
		);

		$this->assertSame( '', $html );
		$this->assertStringContainsString( 'does not have the field "faq"', $this->renderer->hint() );
		$this->assertStringContainsString( 'Home', $this->renderer->hint() );
	}
}
