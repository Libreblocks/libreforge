<?php
/**
 * Relations on edit screens (a picker box on each side), saving, cleanup when
 * an object is deleted, and the tokens {{relation.children|slug}} / {{relation.parents|slug}}.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Relations;

use LibreBlocks\Core\Context\ObjectStack;
use LibreBlocks\Core\Fields\Field;
use LibreBlocks\Core\Support\Json;
use LibreBlocks\Core\Tokens\Registry as Tokens;
use LibreBlocks\Core\Tokens\Token;
use LibreBlocks\Forge\Fields\FieldGroups;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kinds\RelationKind;

defined( 'ABSPATH' ) || exit;

final class Relations {

	private const INPUT = 'libreforge_relations';
	private const NONCE = '_lbforge_relations_nonce';

	public function __construct( private DefinitionRepository $repository, private RelationStore $store ) {}

	public function hook(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_post' ], 20, 2 );
		add_action( 'admin_init', [ $this, 'hook_term_screens' ] );
		add_action( 'edited_term', [ $this, 'save_term' ], 10, 3 );
		add_action( 'created_term', [ $this, 'save_term' ], 10, 3 );
		add_action( 'show_user_profile', [ $this, 'render_user' ] );
		add_action( 'edit_user_profile', [ $this, 'render_user' ] );
		add_action( 'personal_options_update', [ $this, 'save_user' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_user' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

		add_action( 'deleted_post', fn( $post_id, $post = null ) => $post ? $this->forget( 'post:' . $post->post_type, (int) $post_id ) : null, 10, 2 );
		add_action( 'delete_term', fn( $term_id, $tt_id, $taxonomy ) => $this->forget( 'term:' . $taxonomy, (int) $term_id ), 10, 3 );
		add_action( 'deleted_user', fn( $user_id ) => $this->forget( 'user', (int) $user_id ) );

		add_action( 'libreblocks/tokens/register', [ $this, 'register_tokens' ] );
	}

	/**
	 * @return Definition[]
	 */
	public function all(): array {
		return $this->repository->active( RelationKind::ID );
	}

	public function find( string $slug ): ?Definition {
		foreach ( $this->all() as $relation ) {
			if ( $relation->slug === $slug || (string) $relation->id === $slug ) {
				return $relation;
			}
		}

		return null;
	}

	/**
	 * Related IDs of an object: its children ("children") or its parents ("parents").
	 *
	 * @return int[]
	 */
	public function related( Definition $relation, string $direction, int $object_id ): array {
		return 'parents' === $direction ? $this->store->parents( $relation->id, $object_id ) : $this->store->children( $relation->id, $object_id );
	}

	/**
	 * Relations where $object ("post:imovel") is on one side, with the side and its picker.
	 *
	 * @return array<int, array{relation: Definition, side: string}>
	 */
	public function for_object( string $object ): array {
		$found = [];

		foreach ( $this->all() as $relation ) {
			if ( $relation->get( 'parent_object' ) === $object && $relation->get( 'show_in_parent', true ) ) {
				$found[] = [ 'relation' => $relation, 'side' => 'parent' ];
			}

			if ( $relation->get( 'child_object' ) === $object && $relation->get( 'show_in_child', true ) ) {
				$found[] = [ 'relation' => $relation, 'side' => 'child' ];
			}
		}

		return $found;
	}

	/* ------------------------------------------------------------------ screens */

	/**
	 * @param string   $post_type
	 * @param \WP_Post $post
	 */
	public function add_meta_boxes( $post_type, $post = null ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		foreach ( $this->for_object( 'post:' . $post_type ) as $item ) {
			add_meta_box(
				'lbforge-relation-' . $item['relation']->id . '-' . $item['side'],
				esc_html( $this->box_title( $item['relation'], $item['side'] ) ),
				fn() => $this->render_picker( $item['relation'], $item['side'], (int) $post->ID ),
				$post_type,
				'side'
			);
		}
	}

	public function hook_term_screens(): void {
		$taxonomies = [];

		foreach ( $this->all() as $relation ) {
			foreach ( [ 'parent_object', 'child_object' ] as $key ) {
				[ $kind, $subtype ] = RelationKind::split( (string) $relation->get( $key ) );

				if ( 'term' === $kind ) {
					$taxonomies[] = $subtype;
				}
			}
		}

		foreach ( array_unique( $taxonomies ) as $taxonomy ) {
			add_action( $taxonomy . '_edit_form_fields', function ( $term ) use ( $taxonomy ) {
				foreach ( $this->for_object( 'term:' . $taxonomy ) as $item ) {
					echo '<tr class="form-field"><th scope="row">' . esc_html( $this->box_title( $item['relation'], $item['side'] ) ) . '</th><td>';
					$this->render_picker( $item['relation'], $item['side'], (int) $term->term_id );
					echo '</td></tr>';
				}
			} );
		}
	}

	/**
	 * @param \WP_User $user
	 */
	public function render_user( $user ): void {
		foreach ( $this->for_object( 'user' ) as $item ) {
			echo '<h2>' . esc_html( $this->box_title( $item['relation'], $item['side'] ) ) . '</h2><table class="form-table" role="presentation"><tr><td>';
			$this->render_picker( $item['relation'], $item['side'], (int) $user->ID );
			echo '</td></tr></table>';
		}
	}

	public function enqueue(): void {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$object = '';

		if ( 'post' === $screen->base ) {
			$object = 'post:' . $screen->post_type;
		} elseif ( 'term' === $screen->base ) {
			$object = 'term:' . $screen->taxonomy;
		} elseif ( in_array( $screen->base, [ 'profile', 'user-edit' ], true ) ) {
			$object = 'user';
		}

		if ( $object && $this->for_object( $object ) ) {
			libreblocks()->get( 'admin' )->enqueue_fields();
		}
	}

	/**
	 * A picker of the objects on the other side, using the shared field renderer.
	 */
	public function render_picker( Definition $relation, string $side, int $object_id ): void {
		$other            = 'parent' === $side ? (string) $relation->get( 'child_object' ) : (string) $relation->get( 'parent_object' );
		[ $kind, $subtype ] = RelationKind::split( $other );
		$type             = (string) $relation->get( 'type' );
		$multiple         = 'parent' === $side ? ! RelationKind::single_child( $type ) : ! RelationKind::single_parent( $type );
		$name             = 'related';
		$args             = [ 'multiple' => $multiple ];

		if ( 'post' === $kind ) {
			$args['post_type'] = [ $subtype ];
		} elseif ( 'term' === $kind ) {
			$args['taxonomy'] = [ $subtype ];
		}

		$field = new Field( $name, ( 'user' === $kind ? 'user' : $kind ) . '_picker', '', $this->help( $relation, $side ), false, null, [], '', [], '100%', [], $args );
		$ids   = $object_id ? $this->related( $relation, 'parent' === $side ? 'children' : 'parents', $object_id ) : [];

		FieldGroups::print_form(
			'lbforge-rel-' . $relation->id . '-' . $side,
			self::INPUT . '[' . $relation->id . '][' . $side . ']',
			[ $field ],
			[ $name => $multiple ? $ids : ( $ids[0] ?? '' ) ]
		);

		wp_nonce_field( 'lbforge_relations', self::NONCE, false );
	}

	/* ------------------------------------------------------------------ saving */

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_post( $post_id, $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->save( 'post:' . $post->post_type, (int) $post_id );
	}

	/**
	 * @param int    $term_id
	 * @param int    $tt_id
	 * @param string $taxonomy
	 */
	public function save_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		if ( current_user_can( 'edit_term', $term_id ) ) {
			$this->save( 'term:' . $taxonomy, (int) $term_id );
		}
	}

	/**
	 * @param int $user_id
	 */
	public function save_user( $user_id ): void {
		if ( current_user_can( 'edit_user', $user_id ) ) {
			$this->save( 'user', (int) $user_id );
		}
	}

	private function save( string $object, int $object_id ): void {
		if ( ! isset( $_POST[ self::INPUT ], $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'lbforge_relations' ) ) {
			return;
		}

		$posted = (array) wp_unslash( $_POST[ self::INPUT ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and cast to IDs below.

		foreach ( $this->for_object( $object ) as $item ) {
			$relation = $item['relation'];
			$side     = $item['side'];

			if ( ! isset( $posted[ $relation->id ][ $side ] ) ) {
				continue;
			}

			$value = Json::decode( (string) $posted[ $relation->id ][ $side ] )['related'] ?? [];
			$ids   = array_map( 'absint', is_array( $value ) ? $value : [ $value ] );

			if ( 'parent' === $side ) {
				$this->store->set_children( $relation, $object_id, $ids );
			} else {
				$this->store->set_parents( $relation, $object_id, $ids );
			}
		}
	}

	private function forget( string $object, int $object_id ): void {
		$as_parent = [];
		$as_child  = [];

		foreach ( $this->all() as $relation ) {
			if ( $relation->get( 'parent_object' ) === $object ) {
				$as_parent[] = $relation->id;
			}

			if ( $relation->get( 'child_object' ) === $object ) {
				$as_child[] = $relation->id;
			}
		}

		if ( $as_parent || $as_child ) {
			$this->store->forget( $object_id, $as_parent, $as_child );
		}
	}

	/* ------------------------------------------------------------------ tokens */

	public function register_tokens( Tokens $tokens ): void {
		$tokens->add( new Token(
			'relation.children',
			fn( $slug, ObjectStack $context ) => $this->token( (string) $slug, 'children', $context ),
			__( 'Related children of the current item', 'libreforge' ),
			__( 'Relation slug', 'libreforge' )
		) );

		$tokens->add( new Token(
			'relation.parents',
			fn( $slug, ObjectStack $context ) => $this->token( (string) $slug, 'parents', $context ),
			__( 'Related parents of the current item', 'libreforge' ),
			__( 'Relation slug', 'libreforge' )
		) );
	}

	/**
	 * @return int[]
	 */
	private function token( string $slug, string $direction, ObjectStack $context ): array {
		$relation = $this->find( $slug );
		$id       = ObjectStack::describe( $context->current() )['id'];

		return $relation && $id ? $this->related( $relation, $direction, (int) $id ) : [];
	}

	/* ------------------------------------------------------------------ labels */

	private function box_title( Definition $relation, string $side ): string {
		$label = 'parent' === $side ? (string) $relation->get( 'child_label' ) : (string) $relation->get( 'parent_label' );

		return '' !== $label ? $label : $relation->title();
	}

	private function help( Definition $relation, string $side ): string {
		$type = (string) $relation->get( 'type' );

		if ( 'parent' === $side ) {
			return RelationKind::single_child( $type ) ? __( 'Choose one item.', 'libreforge' ) : __( 'Choose one or more items.', 'libreforge' );
		}

		return RelationKind::single_parent( $type ) ? __( 'Choose one item.', 'libreforge' ) : __( 'Choose one or more items.', 'libreforge' );
	}
}
