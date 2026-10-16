<?php
/**
 * Relations between content: "a broker has many properties", "a course has
 * many lessons", "students take many courses".
 *
 * Objects are written as "post:{post_type}", "term:{taxonomy}" or "user".
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model\Kinds;

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kind;

defined( 'ABSPATH' ) || exit;

final class RelationKind extends Kind {

	public const ID = 'relation';

	public const ONE_TO_ONE   = 'one_to_one';
	public const ONE_TO_MANY  = 'one_to_many';
	public const MANY_TO_MANY = 'many_to_many';

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Relations', 'libreforge' );
	}

	public function sanitize( array $input ): array {
		$type = (string) ( $input['type'] ?? self::ONE_TO_MANY );

		return [
			'title'          => self::text( $input, 'title' ),
			'parent_object'  => self::object( (string) ( $input['parent_object'] ?? '' ) ),
			'child_object'   => self::object( (string) ( $input['child_object'] ?? '' ) ),
			'type'           => in_array( $type, [ self::ONE_TO_ONE, self::ONE_TO_MANY, self::MANY_TO_MANY ], true ) ? $type : self::ONE_TO_MANY,
			'parent_label'   => self::text( $input, 'parent_label' ),
			'child_label'    => self::text( $input, 'child_label' ),
			'show_in_parent' => self::flag( $input, 'show_in_parent', true ),
			'show_in_child'  => self::flag( $input, 'show_in_child', true ),
		];
	}

	public function validate( Definition $definition, DefinitionRepository $repository ): array {
		$errors = $this->validate_slug( $definition, $repository, 64 );

		if ( '' === $definition->get( 'title', '' ) ) {
			$errors['title'] = __( 'The title is required.', 'libreforge' );
		}

		if ( '' === $definition->get( 'parent_object', '' ) ) {
			$errors['parent_object'] = __( 'Choose the parent content.', 'libreforge' );
		}

		if ( '' === $definition->get( 'child_object', '' ) ) {
			$errors['child_object'] = __( 'Choose the child content.', 'libreforge' );
		}

		return $errors;
	}

	/**
	 * "post:imovel" → [ 'post', 'imovel' ]; "user" → [ 'user', '' ].
	 *
	 * @return array{0: string, 1: string}
	 */
	public static function split( string $object ): array {
		$parts = explode( ':', $object, 2 );

		return [ $parts[0], $parts[1] ?? '' ];
	}

	/**
	 * Whether a child may belong to only one parent.
	 */
	public static function single_parent( string $type ): bool {
		return self::MANY_TO_MANY !== $type;
	}

	/**
	 * Whether a parent may have only one child.
	 */
	public static function single_child( string $type ): bool {
		return self::ONE_TO_ONE === $type;
	}

	private static function object( string $object ): string {
		if ( 'user' === $object ) {
			return 'user';
		}

		[ $kind, $subtype ] = self::split( $object );
		$subtype           = sanitize_key( $subtype );

		return in_array( $kind, [ 'post', 'term' ], true ) && '' !== $subtype ? $kind . ':' . $subtype : '';
	}
}
