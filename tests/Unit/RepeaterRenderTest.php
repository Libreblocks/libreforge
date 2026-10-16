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

	public function test_accordion_with_title_content_options_and_faq_schema(): void {
		WPStub::$meta['post'][5]['faq'] = [
			[ 'pergunta' => 'Aceita pet?', 'resposta' => 'Sim, de pequeno porte.' ],
			[ 'pergunta' => 'Tem <b>vaga</b>?', 'resposta' => 'Duas vagas.' ],
			[ 'pergunta' => '', 'resposta' => '' ],
		];

		$html = libreblocks()->context()->with(
			new WP_Post( [ 'ID' => 5, 'post_type' => 'teste' ] ),
			fn() => $this->renderer->repeater( [
				'key'         => 'faq',
				'layout'      => 'accordion',
				'single_open' => true,
				'open_first'  => true,
				'icon'        => 'plus',
				'faq_schema'  => true,
			] )
		);

		$this->assertSame( 2, substr_count( $html, '<details class="lf-accordion__item"' ), 'Empty rows are skipped.' );
		$this->assertMatchesRegularExpression( '/<details class="lf-accordion__item" name="(lf-acc-\d+)" open><summary class="lf-accordion__title"><span class="lf-accordion__text">Aceita pet\?<\/span><span class="lf-accordion__icon"/', $html );
		$this->assertStringContainsString( '<span class="lf-accordion__text">Tem &lt;b&gt;vaga&lt;/b&gt;?</span>', $html, 'Titles are escaped.' );
		$this->assertStringContainsString( '<div class="lf-accordion__content"><div class="lf-repeater__field lf-repeater__field--resposta"><p>Sim, de pequeno porte.</p></div></div>', $html, 'The title is not repeated in the content.' );
		$this->assertStringContainsString( 'lf-accordion--icon-plus', $html );
		$this->assertSame( 1, substr_count( $html, 'open><summary' ), 'Only the first item starts open.' );

		preg_match( '#<script type="application/ld\+json">(.*?)</script>#', $html, $schema );
		$data = json_decode( $schema[1], true );

		$this->assertSame( 'FAQPage', $data['@type'] );
		$this->assertSame( 'Aceita pet?', $data['mainEntity'][0]['name'] );
		$this->assertSame( 'Sim, de pequeno porte.', $data['mainEntity'][0]['acceptedAnswer']['text'] );
	}

	public function test_accordion_custom_title_and_icon_on_the_left(): void {
		WPStub::$meta['post'][5]['faq'] = [ [ 'pergunta' => 'P', 'resposta' => 'R' ] ];

		$html = libreblocks()->context()->with(
			new WP_Post( [ 'ID' => 5, 'post_type' => 'teste' ] ),
			fn() => $this->renderer->repeater( [ 'key' => 'faq', 'layout' => 'accordion', 'title_key' => 'resposta', 'icon_position' => 'left', 'format' => 'Q: {{object.meta|pergunta}}' ] )
		);

		$this->assertStringContainsString( '<summary class="lf-accordion__title"><span class="lf-accordion__icon" aria-hidden="true"></span><span class="lf-accordion__text">R</span></summary>', $html );
		$this->assertStringContainsString( '<div class="lf-accordion__content">Q: P</div>', $html );
		$this->assertStringNotContainsString( ' name="', $html, 'Several items can be open unless "only one" is on.' );
		$this->assertStringNotContainsString( 'ld+json', $html );
	}

	public function test_bare_values_for_dynamic_tags(): void {
		WPStub::$meta['post'][5]['cor'] = '#1a3cb4';

		$post = new WP_Post( [ 'ID' => 5, 'post_type' => 'teste' ] );

		$this->assertSame( '#1a3cb4', libreblocks()->context()->with( $post, fn() => $this->renderer->field( [ 'source' => 'meta', 'key' => 'cor', 'tag' => 'none' ] ) ) );
		$this->assertSame( '<span class="lf-field">#1a3cb4</span>', libreblocks()->context()->with( $post, fn() => $this->renderer->field( [ 'source' => 'meta', 'key' => 'cor', 'tag' => 'span' ] ) ) );
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
