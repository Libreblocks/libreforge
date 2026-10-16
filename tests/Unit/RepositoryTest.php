<?php

use LibreBlocks\Forge\Glossaries\Glossaries;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Core\Fields\Field;
use PHPUnit\Framework\TestCase;

final class RepositoryTest extends TestCase {

	private DefinitionRepository $repository;

	protected function setUp(): void {
		global $wpdb;

		WPStub::reset();
		$wpdb             = new FakeWpdb();
		$this->repository = new DefinitionRepository( libreblocks()->cache() );
	}

	public function test_crud_round_trip_with_json_config(): void {
		$saved = $this->repository->save( new Definition( 'glossary', 'estados', [ 'title' => 'Estados', 'options' => [ [ 'value' => 'sp', 'label' => 'São Paulo' ] ] ] ) );

		$this->assertSame( 1, $saved->id );
		$this->assertSame( 'Estados', $saved->title() );
		$this->assertStringContainsString( 'São Paulo', $GLOBALS['wpdb']->rows[1]['config'], 'Config is stored as readable JSON.' );

		$saved->status = Definition::DRAFT;
		$this->repository->save( $saved );

		$this->assertSame( [], $this->repository->active( 'glossary' ), 'Drafts are not active.' );
		$this->assertCount( 1, $this->repository->all( 'glossary' ) );

		$this->repository->delete( $saved );
		$this->assertNull( $this->repository->find( 1 ) );
	}

	public function test_active_cache_is_flushed_on_save(): void {
		$this->repository->save( new Definition( 'glossary', 'a', [ 'title' => 'A' ] ) );
		$this->assertCount( 1, $this->repository->active( 'glossary' ) );

		usleep( 10 );
		$this->repository->save( new Definition( 'glossary', 'b', [ 'title' => 'B' ] ) );
		$this->assertCount( 2, $this->repository->active( 'glossary' ) );
	}

	public function test_glossaries_feed_choice_fields(): void {
		$this->repository->save( new Definition( 'glossary', 'estados', [ 'options' => [ [ 'value' => 'sp', 'label' => 'São Paulo' ] ] ] ) );

		$glossaries = new Glossaries( $this->repository );
		$field      = new Field( 'uf', 'select', 'UF', '', false, null, [], 'estados' );

		$this->assertSame( [ [ 'value' => 'sp', 'label' => 'São Paulo' ] ], $glossaries->options_for_field( [], $field ) );
		$this->assertSame( 'São Paulo', $glossaries->label( 'estados', 'sp' ) );
		$this->assertSame( 'xx', $glossaries->label( 'estados', 'xx' ) );
		$this->assertSame( [ [ 'value' => 'keep', 'label' => 'Keep' ] ], $glossaries->options_for_field( [ [ 'value' => 'keep', 'label' => 'Keep' ] ], new Field( 'n', 'select', '', '', false, null, [], 'missing' ) ) );
	}
}
