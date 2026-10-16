<?php
/**
 * Shows field groups on post, term and user screens, registers their meta and saves the values.
 *
 * The form itself is the shared React field renderer from LibreBlocks Core:
 * PHP prints a mount point with the field configs and current values, and a
 * hidden input that the renderer keeps filled with the edited values as JSON.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Fields;

use LibreBlocks\Core\Fields\Field;
use LibreBlocks\Core\Fields\Registry;
use LibreBlocks\Core\Support\Json;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kinds\FieldGroupKind;
use LibreBlocks\Forge\Model\Kinds\PostTypeKind;
use LibreBlocks\Forge\Model\Kinds\TaxonomyKind;

defined( 'ABSPATH' ) || exit;

final class FieldGroups {

	public const INPUT = 'libreforge_fields';

	private const NOTICE_TRANSIENT = 'lbforge_field_errors_';

	public function __construct( private DefinitionRepository $repository, private Registry $fields ) {}

	public function hook(): void {
		add_action( 'init', [ $this, 'register_meta' ], 20 );

		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );

		add_action( 'admin_init', [ $this, 'hook_term_screens' ] );
		add_action( 'admin_init', [ $this, 'hook_admin_columns' ] );
		add_action( 'created_term', [ $this, 'save_term' ], 10, 3 );
		add_action( 'edited_term', [ $this, 'save_term' ], 10, 3 );

		add_action( 'show_user_profile', [ $this, 'render_user' ] );
		add_action( 'edit_user_profile', [ $this, 'render_user' ] );
		add_action( 'personal_options_update', [ $this, 'save_user' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_user' ] );

		add_action( 'admin_notices', [ $this, 'print_errors' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Field groups plus the fields defined directly inside post types and
	 * taxonomies, which behave as one extra group attached to that type.
	 *
	 * @return Definition[]
	 */
	public function all_groups(): array {
		$groups = $this->repository->active( FieldGroupKind::ID );

		foreach ( $this->repository->active( PostTypeKind::ID ) as $type ) {
			if ( $type->get( 'fields' ) ) {
				$groups[] = new Definition( FieldGroupKind::ID, 'pt_' . $type->slug, [
					'title'      => (string) $type->get( 'fields_title' ) ?: __( 'Custom fields', 'libreforge' ),
					'object'     => 'post',
					'post_types' => [ $type->slug ],
					'context'    => (string) $type->get( 'fields_context', 'normal' ),
					'priority'   => 'high',
					'fields'     => (array) $type->get( 'fields' ),
				] );
			}
		}

		foreach ( $this->repository->active( TaxonomyKind::ID ) as $taxonomy ) {
			if ( $taxonomy->get( 'fields' ) ) {
				$groups[] = new Definition( FieldGroupKind::ID, 'tax_' . $taxonomy->slug, [
					'title'      => __( 'Custom fields', 'libreforge' ),
					'object'     => 'term',
					'taxonomies' => [ $taxonomy->slug ],
					'fields'     => (array) $taxonomy->get( 'fields' ),
				] );
			}
		}

		return $groups;
	}

	/**
	 * @return Definition[]
	 */
	public function groups_for( string $object, string $subtype = '' ): array {
		return array_values( array_filter(
			$this->all_groups(),
			function ( Definition $group ) use ( $object, $subtype ) {
				if ( $group->get( 'object' ) !== $object ) {
					return false;
				}

				switch ( $object ) {
					case 'post':
						return in_array( $subtype, (array) $group->get( 'post_types', [] ), true );
					case 'term':
						return in_array( $subtype, (array) $group->get( 'taxonomies', [] ), true );
					default:
						return true;
				}
			}
		) );
	}

	/**
	 * Definition of the field $name shown for $object (used to format values:
	 * option labels, dates...). Options pages are looked up with object "options:{slug}".
	 *
	 * @param mixed $object WP_Post, WP_Term or WP_User.
	 */
	public function field_for( $object, string $name ): ?Field {
		if ( $object instanceof \WP_Post ) {
			$groups = $this->groups_for( 'post', $object->post_type );
		} elseif ( $object instanceof \WP_Term ) {
			$groups = $this->groups_for( 'term', $object->taxonomy );
		} elseif ( $object instanceof \WP_User ) {
			$groups = $this->groups_for( 'user' );
		} else {
			return null;
		}

		foreach ( $groups as $group ) {
			foreach ( $this->fields_of( $group ) as $field ) {
				if ( $field->name === $name ) {
					return $field;
				}
			}
		}

		return null;
	}

	/**
	 * @return Field[]
	 */
	public function fields_of( Definition $group ): array {
		return $this->fields->make_all( (array) $group->get( 'fields', [] ) );
	}

	/* ------------------------------------------------------------------ meta */

	public function register_meta(): void {
		foreach ( $this->all_groups() as $group ) {
			$object    = (string) $group->get( 'object' );
			$subtypes  = 'post' === $object ? (array) $group->get( 'post_types', [] ) : ( 'term' === $object ? (array) $group->get( 'taxonomies', [] ) : [ '' ] );
			$meta_type = 'post' === $object ? 'post' : ( 'term' === $object ? 'term' : 'user' );

			foreach ( $this->fields_of( $group ) as $field ) {
				$type = $this->fields->get( $field->type );

				if ( ! $type->stores_value() ) {
					continue;
				}

				$schema = $type->schema( $field, $this->fields );

				foreach ( $subtypes as $subtype ) {
					register_meta( $meta_type, $field->name, [
						'object_subtype'    => $subtype,
						'type'              => is_string( $schema['type'] ) ? $schema['type'] : 'string',
						'description'       => $field->label,
						'single'            => true,
						'show_in_rest'      => false === $field->arg( 'show_in_rest', true ) ? false : [ 'schema' => $schema ],
						'sanitize_callback' => fn( $value ) => $type->sanitize( $value, $field, $this->fields ),
						'auth_callback'     => fn( $allowed, $key, $object_id ) => $this->can_edit( $meta_type, (int) $object_id ),
					] );
				}
			}
		}
	}

	/* ------------------------------------------------------------------ posts */

	/**
	 * @param string   $post_type
	 * @param \WP_Post $post
	 */
	public function add_meta_boxes( $post_type, $post = null ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		foreach ( $this->groups_for( 'post', (string) $post_type ) as $group ) {
			add_meta_box(
				'lbforge-' . $group->slug,
				esc_html( $group->title() ),
				fn() => $this->render_form( $group, 'post', (int) $post->ID ),
				$post_type,
				(string) $group->get( 'context', 'normal' ),
				(string) $group->get( 'priority', 'default' )
			);
		}
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_post( $post_id, $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->save( $this->groups_for( 'post', $post->post_type ), 'post', (int) $post_id );
	}

	/* ------------------------------------------------------------------ admin columns */

	/**
	 * Adds a column to the post list for every field marked "Show as a column".
	 */
	public function hook_admin_columns(): void {
		$columns = [];

		foreach ( $this->all_groups() as $group ) {
			if ( 'post' !== $group->get( 'object' ) ) {
				continue;
			}

			foreach ( $this->fields_of( $group ) as $field ) {
				if ( $field->arg( 'admin_column' ) && $this->fields->get( $field->type )->stores_value() ) {
					foreach ( (array) $group->get( 'post_types', [] ) as $post_type ) {
						$columns[ $post_type ][ $field->name ] = $field;
					}
				}
			}
		}

		foreach ( $columns as $post_type => $fields ) {
			add_filter( "manage_{$post_type}_posts_columns", function ( $existing ) use ( $fields ) {
				$date = $existing['date'] ?? null;
				unset( $existing['date'] );

				foreach ( $fields as $name => $field ) {
					$existing[ 'lbforge_' . $name ] = esc_html( $field->label ?: $name );
				}

				return null === $date ? $existing : $existing + [ 'date' => $date ];
			} );

			add_action( "manage_{$post_type}_posts_custom_column", function ( $column, $post_id ) use ( $fields ) {
				$name = substr( (string) $column, 8 );

				if ( 0 === strpos( (string) $column, 'lbforge_' ) && isset( $fields[ $name ] ) ) {
					echo ColumnValue::render( $fields[ $name ], get_post_meta( (int) $post_id, $name, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ColumnValue.
				}
			}, 10, 2 );
		}
	}

	/* ------------------------------------------------------------------ terms */

	public function hook_term_screens(): void {
		$taxonomies = [];

		foreach ( $this->all_groups() as $group ) {
			if ( 'term' === $group->get( 'object' ) ) {
				$taxonomies = array_merge( $taxonomies, (array) $group->get( 'taxonomies', [] ) );
			}
		}

		foreach ( array_unique( $taxonomies ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', fn( $taxonomy_slug ) => $this->render_term_add( (string) $taxonomy_slug ) );
			add_action( $taxonomy . '_edit_form_fields', fn( $term ) => $this->render_term_edit( $term ) );
		}
	}

	private function render_term_add( string $taxonomy ): void {
		foreach ( $this->groups_for( 'term', $taxonomy ) as $group ) {
			echo '<div class="form-field lbforge-term-group"><h3>' . esc_html( $group->title() ) . '</h3>';
			$this->render_form( $group, 'term', 0 );
			echo '</div>';
		}
	}

	/**
	 * @param \WP_Term $term
	 */
	private function render_term_edit( $term ): void {
		foreach ( $this->groups_for( 'term', $term->taxonomy ) as $group ) {
			echo '<tr class="form-field lbforge-term-group"><th scope="row">' . esc_html( $group->title() ) . '</th><td>';
			$this->render_form( $group, 'term', (int) $term->term_id );
			echo '</td></tr>';
		}
	}

	/**
	 * @param int    $term_id
	 * @param int    $tt_id
	 * @param string $taxonomy
	 */
	public function save_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$this->save( $this->groups_for( 'term', (string) $taxonomy ), 'term', (int) $term_id );
	}

	/* ------------------------------------------------------------------ users */

	/**
	 * @param \WP_User $user
	 */
	public function render_user( $user ): void {
		foreach ( $this->user_groups( $user ) as $group ) {
			echo '<h2>' . esc_html( $group->title() ) . '</h2><table class="form-table" role="presentation"><tr><td>';
			$this->render_form( $group, 'user', (int) $user->ID );
			echo '</td></tr></table>';
		}
	}

	/**
	 * @param int $user_id
	 */
	public function save_user( $user_id ): void {
		$user = get_userdata( (int) $user_id );

		if ( $user && current_user_can( 'edit_user', $user_id ) ) {
			$this->save( $this->user_groups( $user ), 'user', (int) $user_id );
		}
	}

	/**
	 * @return Definition[]
	 */
	private function user_groups( \WP_User $user ): array {
		return array_values( array_filter(
			$this->groups_for( 'user' ),
			fn( Definition $group ) => ! $group->get( 'roles' ) || array_intersect( (array) $group->get( 'roles' ), $user->roles )
		) );
	}

	/* ------------------------------------------------------------------ shared */

	/**
	 * Prints the mount point, the hidden JSON input and the nonce for one group.
	 */
	public function render_form( Definition $group, string $meta_type, int $object_id ): void {
		$fields = $this->fields_of( $group );
		$values = [];

		foreach ( $fields as $field ) {
			if ( $this->fields->get( $field->type )->stores_value() && $object_id && metadata_exists( $meta_type, $object_id, $field->name ) ) {
				$values[ $field->name ] = get_metadata( $meta_type, $object_id, $field->name, true );
			}
		}

		self::print_form( 'lbforge-' . $group->slug, self::INPUT . '[' . $group->slug . ']', $fields, $values );
		wp_nonce_field( 'lbforge_save_' . $group->slug, '_lbforge_nonce_' . $group->slug, false );
	}

	/**
	 * Markup consumed by the LibreBlocks fields renderer.
	 *
	 * @param Field[]              $fields
	 * @param array<string, mixed> $values
	 */
	public static function print_form( string $id, string $input_name, array $fields, array $values ): void {
		$config = [
			'fields' => array_map( fn( Field $field ) => $field->to_array(), $fields ),
			'values' => (object) $values,
			'input'  => $id . '-input',
		];

		printf(
			'<div class="libreblocks-fields" data-libreblocks-fields="%1$s"></div><input type="hidden" id="%2$s" name="%3$s" value="%4$s" />',
			esc_attr( Json::encode( $config ) ),
			esc_attr( $id . '-input' ),
			esc_attr( $input_name ),
			esc_attr( Json::encode( (object) $values ) )
		);
	}

	/**
	 * Sanitizes, validates and stores the posted values of each group.
	 *
	 * @param Definition[] $groups
	 */
	private function save( array $groups, string $meta_type, int $object_id ): void {
		$errors = [];

		foreach ( $groups as $group ) {
			$nonce = '_lbforge_nonce_' . $group->slug;

			if (
				! isset( $_POST[ self::INPUT ][ $group->slug ], $_POST[ $nonce ] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce ] ) ), 'lbforge_save_' . $group->slug )
			) {
				continue;
			}

			$input  = Json::decode( wp_unslash( (string) $_POST[ self::INPUT ][ $group->slug ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
			$fields = $this->fields_of( $group );
			$values = $this->fields->sanitize_values( $fields, $input );

			$errors += $this->fields->validate_values( $fields, $values );

			foreach ( $values as $name => $value ) {
				if ( $this->fields->get( $this->field_type( $fields, $name ) )->is_empty( $value ) && ! is_bool( $value ) ) {
					delete_metadata( $meta_type, $object_id, $name );
				} else {
					update_metadata( $meta_type, $object_id, $name, $value );
				}
			}
		}

		if ( $errors ) {
			set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), array_values( $errors ), 60 );
		}
	}

	/**
	 * Validation problems are saved anyway (so nothing typed is lost) and reported on the next screen.
	 */
	public function print_errors(): void {
		$key    = self::NOTICE_TRANSIENT . get_current_user_id();
		$errors = get_transient( $key );

		if ( ! $errors ) {
			return;
		}

		delete_transient( $key );

		echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Some fields need attention:', 'libreforge' ) . '</strong></p><ul>';

		foreach ( (array) $errors as $error ) {
			echo '<li>' . esc_html( (string) $error ) . '</li>';
		}

		echo '</ul></div>';
	}

	/**
	 * Loads the field renderer only on screens that show at least one group.
	 */
	public function enqueue_assets(): void {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$needed = false;

		switch ( $screen->base ) {
			case 'post':
				$needed = (bool) $this->groups_for( 'post', (string) $screen->post_type );
				break;
			case 'term':
			case 'edit-tags':
				$needed = (bool) $this->groups_for( 'term', (string) $screen->taxonomy );
				break;
			case 'profile':
			case 'user-edit':
				$needed = (bool) $this->groups_for( 'user' );
				break;
		}

		if ( $needed ) {
			libreblocks()->get( 'admin' )->enqueue_fields();
		}
	}

	/**
	 * @param Field[] $fields
	 */
	private function field_type( array $fields, string $name ): string {
		foreach ( $fields as $field ) {
			if ( $field->name === $name ) {
				return $field->type;
			}
		}

		return 'text';
	}

	private function can_edit( string $meta_type, int $object_id ): bool {
		switch ( $meta_type ) {
			case 'term':
				return current_user_can( 'edit_term', $object_id );
			case 'user':
				return current_user_can( 'edit_user', $object_id );
			default:
				return current_user_can( 'edit_post', $object_id );
		}
	}
}
