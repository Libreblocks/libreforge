<?php
/**
 * Field groups: a set of fields shown on posts, terms or users.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model\Kinds;

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\FieldList;
use LibreBlocks\Forge\Model\Kind;

defined( 'ABSPATH' ) || exit;

final class FieldGroupKind extends Kind {

	public const ID = 'field_group';

	public const OBJECTS = [ 'post', 'term', 'user' ];

	public function __construct( private FieldList $fields ) {}

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Meta Boxes', 'libreforge' );
	}

	public function sanitize( array $input ): array {
		$object = (string) ( $input['object'] ?? 'post' );

		return [
			'title'      => self::text( $input, 'title' ),
			'object'     => in_array( $object, self::OBJECTS, true ) ? $object : 'post',
			'post_types' => self::keys( $input, 'post_types' ),
			'taxonomies' => self::keys( $input, 'taxonomies' ),
			'roles'      => self::keys( $input, 'roles' ),
			'context'    => 'side' === ( $input['context'] ?? '' ) ? 'side' : 'normal',
			'priority'   => in_array( $input['priority'] ?? '', [ 'high', 'low' ], true ) ? $input['priority'] : 'default',
			'fields'     => $this->fields->sanitize( $input['fields'] ?? [] ),
		];
	}

	public function validate( Definition $definition, DefinitionRepository $repository ): array {
		$errors = $this->validate_slug( $definition, $repository, 191 );

		if ( '' === $definition->get( 'title', '' ) ) {
			$errors['title'] = __( 'The title is required.', 'libreforge' );
		}

		if ( 'post' === $definition->get( 'object' ) && ! $definition->get( 'post_types' ) ) {
			$errors['post_types'] = __( 'Choose at least one post type.', 'libreforge' );
		}

		if ( 'term' === $definition->get( 'object' ) && ! $definition->get( 'taxonomies' ) ) {
			$errors['taxonomies'] = __( 'Choose at least one taxonomy.', 'libreforge' );
		}

		return $errors + $this->fields->validate( (array) $definition->get( 'fields', [] ) );
	}
}
