<?php
/**
 * LibreForge entry point.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge;

use LibreBlocks\Core\Plugin as Core;
use LibreBlocks\Forge\Admin\Screens;
use LibreBlocks\Forge\Blocks\Blocks;
use LibreBlocks\Forge\Render\Renderer;
use LibreBlocks\Forge\Render\Shortcodes;
use LibreBlocks\Forge\Render\Values;
use LibreBlocks\Forge\Rest\BlocksController;
use LibreBlocks\Forge\Content\ContentTypes;
use LibreBlocks\Forge\Fields\FieldGroups;
use LibreBlocks\Forge\Glossaries\Glossaries;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\FieldList;
use LibreBlocks\Forge\Model\Kind;
use LibreBlocks\Forge\Model\Kinds;
use LibreBlocks\Forge\Options\OptionsPages;
use LibreBlocks\Forge\Queries\QueryRunner;
use LibreBlocks\Forge\Relations\Relations;
use LibreBlocks\Forge\Relations\RelationStore;
use LibreBlocks\Forge\Rest\DefinitionsController;
use LibreBlocks\Forge\Rest\TemplatesController;
use LibreBlocks\Forge\Templates\Templates;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private const SCHEMA_VERSION = '1';

	private const RELATIONS_SCHEMA_VERSION = '1';

	private static ?Plugin $instance = null;

	private ?Core $core = null;

	private ?DefinitionRepository $repository = null;

	/** @var array<string, Kind> */
	private array $kinds = [];

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot( Core $core ): void {
		if ( $this->core ) {
			return;
		}

		$this->core       = $core;
		$this->repository = new DefinitionRepository( $core->cache() );

		$core->schema()->register_table( DefinitionRepository::TABLE, DefinitionRepository::columns(), self::SCHEMA_VERSION, 'libreforge' );
		$core->schema()->register_table( RelationStore::TABLE, RelationStore::columns(), self::RELATIONS_SCHEMA_VERSION, 'libreforge' );

		$field_list = new FieldList( $core->fields() );

		foreach ( [
			new Kinds\PostTypeKind( $field_list ),
			new Kinds\TaxonomyKind( $field_list ),
			new Kinds\FieldGroupKind( $field_list ),
			new Kinds\OptionsPageKind( $field_list ),
			new Kinds\GlossaryKind(),
			new Kinds\QueryKind(),
			new Kinds\RelationKind(),
		] as $kind ) {
			$this->kinds[ $kind->id() ] = $kind;
		}

		// Creates the table on the first request after install/update (dbDelta runs once per version).
		if ( $core->schema()->pending() ) {
			$core->schema()->install();
		}

		$glossaries = new Glossaries( $this->repository );

		$core->set( 'forge.glossaries', fn() => $glossaries );
		$core->set( 'forge.queries', fn( Core $c ) => new QueryRunner( $this->repository, $c->tokens() ) );

		( new ContentTypes( $this->repository ) )->hook();
		$glossaries->hook();
		$field_groups = new FieldGroups( $this->repository, $core->fields() );
		$core->set( 'forge.fields', fn() => $field_groups );
		$field_groups->hook();
		( new OptionsPages( $this->repository, $core->fields() ) )->hook();

		$templates = new Templates( $core->context() );
		$core->set( 'forge.templates', fn() => $templates );
		$templates->hook();

		$relations = new Relations( $this->repository, new RelationStore() );
		$core->set( 'forge.relations', fn() => $relations );
		$relations->hook();

		$core->rest()->add( DefinitionsController::class );
		$core->rest()->add( TemplatesController::class );
		$core->rest()->add( BlocksController::class );

		$core->set( 'forge.renderer', fn( Core $c ) => new Renderer(
			$c->context(),
			new Values( $c->context(), $c->tokens() ),
			$this->queries(),
			$templates,
			$field_groups
		) );

		( new Blocks( $core->get( 'forge.renderer' ), $templates, $core->context() ) )->hook();
		( new Shortcodes( $core->get( 'forge.renderer' ), $templates ) )->hook();

		// Elementor loads after plugins_loaded; its integration hooks in on its own action.
		add_action( 'elementor/init', [ $this, 'boot_elementor' ] );

		if ( is_admin() ) {
			( new Screens( $core ) )->hook();
		}

		add_action( 'init', fn() => load_plugin_textdomain( 'libreforge', false, dirname( plugin_basename( LIBREFORGE_FILE ) ) . '/languages' ), 0 );

		/**
		 * Fires when LibreForge is ready.
		 *
		 * @param Plugin $forge
		 */
		do_action( 'libreforge/loaded', $this );
	}

	public function activate(): void {
		$this->core?->schema()->install();
		ContentTypes::request_flush();
	}

	public function boot_elementor(): void {
		( new Elementor\Integration( $this->core ) )->hook();
	}

	public function renderer(): Renderer {
		return $this->core->get( 'forge.renderer' );
	}

	public function queries(): QueryRunner {
		return $this->core->get( 'forge.queries' );
	}

	/**
	 * Lets other LibreBlocks plugins store their own definitions (e.g. LibreSift filters)
	 * through the same table, REST API and validation flow.
	 */
	public function register_kind( Kind $kind ): void {
		$this->kinds[ $kind->id() ] = $kind;
	}

	public function field_list(): FieldList {
		return new FieldList( $this->core->fields() );
	}

	public function repository(): DefinitionRepository {
		return $this->repository;
	}

	/**
	 * @return array<string, Kind>
	 */
	public function kinds(): array {
		return $this->kinds;
	}

	private function __construct() {}
}
