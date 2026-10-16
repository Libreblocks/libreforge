<?php
/**
 * LibreForge entry point.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge;

use LibreBlocks\Core\Plugin as Core;
use LibreBlocks\Forge\Admin\Screens;
use LibreBlocks\Forge\Content\ContentTypes;
use LibreBlocks\Forge\Fields\FieldGroups;
use LibreBlocks\Forge\Glossaries\Glossaries;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\FieldList;
use LibreBlocks\Forge\Model\Kind;
use LibreBlocks\Forge\Model\Kinds;
use LibreBlocks\Forge\Options\OptionsPages;
use LibreBlocks\Forge\Rest\DefinitionsController;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private const SCHEMA_VERSION = '1';

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

		$field_list = new FieldList( $core->fields() );

		foreach ( [
			new Kinds\PostTypeKind( $field_list ),
			new Kinds\TaxonomyKind( $field_list ),
			new Kinds\FieldGroupKind( $field_list ),
			new Kinds\OptionsPageKind( $field_list ),
			new Kinds\GlossaryKind(),
		] as $kind ) {
			$this->kinds[ $kind->id() ] = $kind;
		}

		// Creates the table on the first request after install/update (dbDelta runs once per version).
		if ( $core->schema()->pending() ) {
			$core->schema()->install();
		}

		$glossaries = new Glossaries( $this->repository );

		$core->set( 'forge.glossaries', fn() => $glossaries );

		( new ContentTypes( $this->repository ) )->hook();
		$glossaries->hook();
		( new FieldGroups( $this->repository, $core->fields() ) )->hook();
		( new OptionsPages( $this->repository, $core->fields() ) )->hook();

		$core->rest()->add( DefinitionsController::class );

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
