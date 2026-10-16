<?php

use LibreBlocks\Core\Fields\Field;
use LibreBlocks\Core\Fields\Registry;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kinds\QueryKind;
use LibreBlocks\Forge\Queries\QueryRunner;
use LibreBlocks\Forge\Render\Formatter;
use PHPUnit\Framework\TestCase;

final class QueryAndFormatTest extends TestCase {

	private QueryRunner $runner;
	private QueryKind $kind;

	protected function setUp(): void {
		global $wpdb;

		WPStub::reset();
		$wpdb         = new FakeWpdb();
		$this->kind   = new QueryKind();
		$this->runner = new QueryRunner( new DefinitionRepository( libreblocks()->cache() ), libreblocks()->tokens() );
	}

	private function args( array $input, int $page = 1 ): array {
		return $this->runner->build_args( $this->kind->sanitize( $input ), $page );
	}

	public function test_posts_query_with_filters_and_tokens(): void {
		WPStub::$user_id = 7;
		$_GET['cidade']  = 'sao-paulo';

		$args = $this->args( [
			'type'       => 'posts',
			'post_types' => [ 'imovel' ],
			'per_page'   => 6,
			'orderby'    => 'meta_value_num',
			'order_meta_key' => 'preco',
			'order'      => 'asc',
			'author'     => '{{user.id}}',
			'tax_rules'  => [
				[ 'taxonomy' => 'cidade', 'field' => 'slug', 'terms' => '{{url.param|cidade}}', 'operator' => 'IN' ],
				[ 'taxonomy' => 'tipo', 'field' => 'slug', 'terms' => '{{url.param|tipo}}', 'operator' => 'IN' ],
			],
			'meta_rules' => [
				[ 'key' => 'preco', 'compare' => '<=', 'value' => '500000', 'type' => 'NUMERIC' ],
				[ 'key' => 'quartos', 'compare' => 'BETWEEN', 'value' => '2, 4', 'type' => 'NUMERIC' ],
				[ 'key' => 'vazio', 'compare' => '=', 'value' => '{{url.param|nada}}' ],
				[ 'key' => 'evil', 'compare' => 'DROP TABLE', 'value' => 'x' ],
			],
		], 2 );

		$this->assertSame( [ 'imovel' ], $args['post_type'] );
		$this->assertSame( 2, $args['paged'] );
		$this->assertSame( 'ASC', $args['order'] );
		$this->assertSame( 'preco', $args['meta_key'] );
		$this->assertSame( [ 7 ], $args['author__in'] );

		// The "tipo" rule has no URL value, so it is skipped instead of matching nothing.
		$this->assertSame( [ [ 'taxonomy' => 'cidade', 'field' => 'slug', 'terms' => [ 'sao-paulo' ], 'operator' => 'IN' ] ], $args['tax_query'] );

		$this->assertSame( 'AND', $args['meta_query']['relation'] );
		$this->assertSame( [ 'key' => 'preco', 'compare' => '<=', 'type' => 'NUMERIC', 'value' => '500000' ], $args['meta_query'][0] );
		$this->assertSame( [ '2', '4' ], $args['meta_query'][1]['value'] );
		$this->assertSame( '=', $args['meta_query'][2]['compare'], 'Unknown comparisons fall back to "=".' );
		$this->assertCount( 3, array_filter( array_keys( $args['meta_query'] ), 'is_int' ) );
	}

	public function test_exclude_current_and_offset(): void {
		WPStub::$registered['singular']   = true;
		WPStub::$registered['queried_id'] = 42;

		$args = $this->args( [ 'type' => 'posts', 'per_page' => 3, 'offset' => 1, 'exclude' => '5' ], 3 );

		$this->assertSame( [ 5, 42 ], $args['post__not_in'] );
		$this->assertSame( 7, $args['offset'], 'Offset 1 + 2 full pages of 3.' );
	}

	public function test_terms_and_users_args(): void {
		$terms = $this->args( [ 'type' => 'terms', 'taxonomies' => [ 'cidade' ], 'per_page' => 10, 'orderby' => 'count', 'hide_empty' => true ], 2 );

		$this->assertSame( [ 'cidade' ], $terms['taxonomy'] );
		$this->assertSame( 10, $terms['offset'] );
		$this->assertTrue( $terms['hide_empty'] );
		$this->assertSame( 'count', $terms['orderby'] );

		$users = $this->args( [ 'type' => 'users', 'roles' => [ 'author' ], 'search' => 'ana', 'orderby' => 'post__in' ] );

		$this->assertSame( [ 'author' ], $users['role__in'] );
		$this->assertSame( '*ana*', $users['search'] );
		$this->assertSame( 'display_name', $users['orderby'], 'Orderby values of other types are rejected.' );
	}

	public function test_query_validation(): void {
		$definition = new \LibreBlocks\Forge\Model\Definition( 'query', 'q', $this->kind->sanitize( [ 'title' => 'Q', 'orderby' => 'meta_value' ] ) );

		$this->assertSame( [ 'order_meta_key' ], array_keys( $this->kind->validate( $definition, new DefinitionRepository( libreblocks()->cache() ) ) ) );
	}

	public function test_formatter(): void {
		$choice = Field::from_array( [ 'name' => 'uf', 'type' => 'select', 'options' => [ 'sp' => 'São Paulo', 'rj' => 'Rio' ] ] );

		$this->assertSame( 'São Paulo', Formatter::format( 'sp', [], $choice ) );
		$this->assertSame( 'São Paulo, Rio', Formatter::format( [ 'sp', 'rj' ], [ 'format' => 'label' ], $choice ) );
		$this->assertSame( 'R$ 350.000,50', Formatter::format( '350000.5', [ 'format' => 'currency' ] ) );
		$this->assertSame( '1.234', Formatter::format( 1234, [ 'format' => 'number' ] ) );
		$this->assertSame( '05/03/2019', Formatter::format( '2019-03-05', [ 'format' => 'date', 'date_format' => 'd/m/Y' ] ) );
		$this->assertSame( '&lt;b&gt;x&lt;/b&gt;', Formatter::format( '<b>x</b>', [] ) );
		$this->assertSame( 'one two…', Formatter::format( 'one two three', [ 'format' => 'trim', 'words' => 2 ] ) );
		$this->assertSame( 'a | b', Formatter::format( [ 'a', 'b' ], [ 'separator' => ' | ' ] ) );
	}
}
