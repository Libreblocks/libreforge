<?php
/**
 * Registers the stored post types and taxonomies with WordPress and keeps the
 * rewrite rules in sync when they change.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Content;

use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kinds\PostTypeKind;
use LibreBlocks\Forge\Model\Kinds\TaxonomyKind;

defined( 'ABSPATH' ) || exit;

final class ContentTypes {

	private const FLUSH_FLAG = 'lbforge_flush_rewrite';

	public function __construct( private DefinitionRepository $repository ) {}

	public function hook(): void {
		// Priority 5: before most plugins look up post types on init.
		add_action( 'init', [ $this, 'register' ], 5 );
		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 99 );
		add_action( 'libreforge/definitions/changed', [ $this, 'schedule_flush' ] );
	}

	public function register(): void {
		foreach ( $this->repository->active( TaxonomyKind::ID ) as $definition ) {
			register_taxonomy( $definition->slug, (array) $definition->get( 'object_types', [] ), TaxonomyKind::to_args( $definition ) );
		}

		foreach ( $this->repository->active( PostTypeKind::ID ) as $definition ) {
			register_post_type( $definition->slug, PostTypeKind::to_args( $definition ) );

			if ( $definition->get( 'single_template' ) ) {
				libreblocks()->get( 'forge.templates' )->use_for_singles( $definition->slug, (int) $definition->get( 'single_template' ) );
			}
		}
	}

	public function schedule_flush( string $kind ): void {
		if ( in_array( $kind, [ PostTypeKind::ID, TaxonomyKind::ID ], true ) ) {
			update_option( self::FLUSH_FLAG, 1, true );
		}
	}

	/**
	 * Flushes on the request after a change, once the new types are registered.
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( self::FLUSH_FLAG ) ) {
			delete_option( self::FLUSH_FLAG );
			flush_rewrite_rules( false );
		}
	}

	public static function request_flush(): void {
		update_option( self::FLUSH_FLAG, 1, true );
	}
}
