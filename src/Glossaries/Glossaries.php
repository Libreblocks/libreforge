<?php
/**
 * Feeds glossary options into any choice field that references a glossary.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Glossaries;

use LibreBlocks\Core\Fields\Field;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kinds\GlossaryKind;

defined( 'ABSPATH' ) || exit;

final class Glossaries {

	public function __construct( private DefinitionRepository $repository ) {}

	public function hook(): void {
		add_filter( 'libreblocks/fields/options', [ $this, 'options_for_field' ], 10, 2 );
	}

	/**
	 * @param array<int, array{value: string, label: string}> $options
	 * @return array<int, array{value: string, label: string}>
	 */
	public function options_for_field( array $options, Field $field ): array {
		if ( '' === $field->glossary ) {
			return $options;
		}

		return $this->options( $field->glossary ) ?? $options;
	}

	/**
	 * @return array<int, array{value: string, label: string}>|null Null when the glossary does not exist.
	 */
	public function options( string $slug ): ?array {
		foreach ( $this->repository->active( GlossaryKind::ID ) as $definition ) {
			if ( $definition->slug === $slug ) {
				return (array) $definition->get( 'options', [] );
			}
		}

		return null;
	}

	/**
	 * Label of a value, e.g. "sp" → "São Paulo". Falls back to the value itself.
	 */
	public function label( string $slug, string $value ): string {
		foreach ( $this->options( $slug ) ?? [] as $option ) {
			if ( $option['value'] === $value ) {
				return $option['label'];
			}
		}

		return $value;
	}
}
