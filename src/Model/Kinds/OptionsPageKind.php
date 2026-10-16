<?php
/**
 * Options pages: an admin screen whose fields are stored together in one option.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model\Kinds;

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\FieldList;
use LibreBlocks\Forge\Model\Kind;

defined( 'ABSPATH' ) || exit;

final class OptionsPageKind extends Kind {

	public const ID = 'options_page';

	/** Where the page can live in the admin menu. "" = its own top-level item. */
	public const PARENTS = [ '', 'libreforge', 'libreblocks', 'options-general.php', 'tools.php', 'themes.php', 'index.php', 'users.php' ];

	public function __construct( private FieldList $fields ) {}

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Options Pages', 'libreforge' );
	}

	/**
	 * Name of the wp_options row that stores the page values.
	 */
	public static function option_name( string $slug ): string {
		return 'lbforge_' . $slug;
	}

	public function sanitize( array $input ): array {
		$parent   = (string) ( $input['parent'] ?? '' );
		$position = $input['position'] ?? null;

		// Post type menus ("edit.php?post_type=house") are valid parents too.
		if ( ! in_array( $parent, self::PARENTS, true ) && ! preg_match( '/^edit\.php\?post_type=[a-z0-9_-]+$/', $parent ) ) {
			$parent = '';
		}

		return [
			'title'      => self::text( $input, 'title' ),
			'menu_title' => self::text( $input, 'menu_title' ),
			'parent'     => $parent,
			'icon'       => self::text( $input, 'icon', 'dashicons-admin-generic' ),
			'position'   => is_numeric( $position ) ? (int) $position : null,
			'capability' => sanitize_key( self::text( $input, 'capability', 'manage_options' ) ) ?: 'manage_options',
			'autoload'   => self::flag( $input, 'autoload', true ),
			'fields'     => $this->fields->sanitize( $input['fields'] ?? [] ),
		];
	}

	public function validate( Definition $definition, DefinitionRepository $repository ): array {
		$errors = $this->validate_slug( $definition, $repository, 64 );

		if ( '' === $definition->get( 'title', '' ) ) {
			$errors['title'] = __( 'The title is required.', 'libreforge' );
		}

		return $errors + $this->fields->validate( (array) $definition->get( 'fields', [] ) );
	}
}
