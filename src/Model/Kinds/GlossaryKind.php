<?php
/**
 * Glossaries: reusable lists of value/label pairs for choice fields.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model\Kinds;

use LibreBlocks\Core\Fields\Field;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kind;

defined( 'ABSPATH' ) || exit;

final class GlossaryKind extends Kind {

	public const ID = 'glossary';

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Glossaries', 'libreforge' );
	}

	public function sanitize( array $input ): array {
		$options = [];
		$seen    = [];

		foreach ( Field::normalize_options( $input['options'] ?? [] ) as $option ) {
			$value = sanitize_text_field( $option['value'] );

			if ( '' === $value || isset( $seen[ $value ] ) ) {
				continue;
			}

			$seen[ $value ] = true;
			$options[]      = [ 'value' => $value, 'label' => sanitize_text_field( $option['label'] ) ?: $value ];
		}

		return [
			'title'   => self::text( $input, 'title' ),
			'options' => $options,
		];
	}

	public function validate( Definition $definition, DefinitionRepository $repository ): array {
		$errors = $this->validate_slug( $definition, $repository, 64 );

		if ( '' === $definition->get( 'title', '' ) ) {
			$errors['title'] = __( 'The title is required.', 'libreforge' );
		}

		if ( ! $definition->get( 'options' ) ) {
			$errors['options'] = __( 'Add at least one option.', 'libreforge' );
		}

		return $errors;
	}
}
