<?php
/**
 * Admin screens built from options page definitions. All fields of a page are
 * stored together in one option: lbforge_{slug}.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Options;

use LibreBlocks\Core\Fields\Registry;
use LibreBlocks\Core\Support\Json;
use LibreBlocks\Forge\Fields\FieldGroups;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kinds\OptionsPageKind;

defined( 'ABSPATH' ) || exit;

final class OptionsPages {

	private const ACTION = 'lbforge_save_options';

	/** @var array<string, string> Hook suffix => page slug. */
	private array $hooks = [];

	public function __construct( private DefinitionRepository $repository, private Registry $fields ) {}

	public function hook(): void {
		add_action( 'admin_menu', [ $this, 'register_pages' ], 20 );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'libreblocks/tokens/allowed_options', [ $this, 'allow_in_tokens' ] );
	}

	/**
	 * Reads one value from an options page, e.g. get( 'contact', 'phone' ).
	 *
	 * @param mixed $fallback
	 * @return mixed
	 */
	public static function get( string $page, string $field, $fallback = null ) {
		$values = get_option( OptionsPageKind::option_name( $page ), [] );

		return is_array( $values ) && array_key_exists( $field, $values ) ? $values[ $field ] : $fallback;
	}

	public function register_pages(): void {
		foreach ( $this->repository->active( OptionsPageKind::ID ) as $page ) {
			$title      = $page->title();
			$menu_title = (string) $page->get( 'menu_title' ) ?: $title;
			$capability = (string) $page->get( 'capability', 'manage_options' );
			$parent     = (string) $page->get( 'parent', '' );
			$render     = fn() => $this->render( $page );
			$slug       = 'lbforge-options-' . $page->slug;

			$hook = '' === $parent
				? add_menu_page( $title, $menu_title, $capability, $slug, $render, (string) $page->get( 'icon' ), $page->get( 'position' ) )
				: add_submenu_page( $parent, $title, $menu_title, $capability, $slug, $render, $page->get( 'position' ) );

			if ( $hook ) {
				$this->hooks[ $hook ] = $page->slug;
			}
		}
	}

	public function render( Definition $page ): void {
		$values = get_option( OptionsPageKind::option_name( $page->slug ), [] );

		echo '<div class="wrap"><h1>' . esc_html( $page->title() ) . '</h1>';

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'libreforge' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		echo '<input type="hidden" name="page_slug" value="' . esc_attr( $page->slug ) . '" />';
		wp_nonce_field( self::ACTION . '_' . $page->slug );

		FieldGroups::print_form(
			'lbforge-options-' . $page->slug,
			FieldGroups::INPUT,
			$this->fields->make_all( (array) $page->get( 'fields', [] ) ),
			is_array( $values ) ? $values : []
		);

		submit_button();
		echo '</form></div>';
	}

	public function save(): void {
		$slug = isset( $_POST['page_slug'] ) ? sanitize_key( wp_unslash( $_POST['page_slug'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		$page = $this->find( $slug );

		if ( ! $page ) {
			wp_die( esc_html__( 'This options page does not exist.', 'libreforge' ), 404 );
		}

		check_admin_referer( self::ACTION . '_' . $slug );

		if ( ! current_user_can( (string) $page->get( 'capability', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'libreforge' ), 403 );
		}

		$fields = $this->fields->make_all( (array) $page->get( 'fields', [] ) );
		$input  = Json::decode( isset( $_POST[ FieldGroups::INPUT ] ) ? wp_unslash( (string) $_POST[ FieldGroups::INPUT ] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		$values = $this->fields->sanitize_values( $fields, $input );

		update_option( OptionsPageKind::option_name( $slug ), $values, (bool) $page->get( 'autoload', true ) );

		$errors = $this->fields->validate_values( $fields, $values );

		if ( $errors ) {
			set_transient( 'lbforge_field_errors_' . get_current_user_id(), array_values( $errors ), 60 );
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ?: admin_url() ) );
		exit;
	}

	public function enqueue( string $hook_suffix ): void {
		if ( isset( $this->hooks[ $hook_suffix ] ) ) {
			libreblocks()->get( 'admin' )->enqueue_fields();
		}
	}

	/**
	 * @param string[] $allowed
	 * @return string[]
	 */
	public function allow_in_tokens( array $allowed ): array {
		foreach ( $this->repository->active( OptionsPageKind::ID ) as $page ) {
			$allowed[] = OptionsPageKind::option_name( $page->slug );
		}

		return $allowed;
	}

	private function find( string $slug ): ?Definition {
		foreach ( $this->repository->active( OptionsPageKind::ID ) as $page ) {
			if ( $page->slug === $slug ) {
				return $page;
			}
		}

		return null;
	}
}
