<?php
/**
 * A kind of definition (post type, taxonomy...): how its config is cleaned and validated.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model;

defined( 'ABSPATH' ) || exit;

abstract class Kind {

	abstract public function id(): string;

	abstract public function label(): string;

	/**
	 * Turns raw input (from the REST API) into a clean config with every key present.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	abstract public function sanitize( array $input ): array;

	/**
	 * Kind-specific validation of a definition about to be saved.
	 *
	 * @return array<string, string> Field => message. Empty when valid.
	 */
	public function validate( Definition $definition, DefinitionRepository $repository ): array {
		return [];
	}

	/**
	 * Slug proposed when the user did not type one.
	 *
	 * @param array<string, mixed> $config
	 */
	public function suggest_slug( array $config ): string {
		return sanitize_key( str_replace( '-', '_', sanitize_title( (string) ( $config['title'] ?? '' ) ) ) );
	}

	public function sanitize_slug( string $slug ): string {
		return sanitize_key( $slug );
	}

	/**
	 * Shared check: slug present, unique within the kind and within the length limit.
	 *
	 * @return array<string, string>
	 */
	protected function validate_slug( Definition $definition, DefinitionRepository $repository, int $max_length ): array {
		if ( '' === $definition->slug ) {
			return [ 'slug' => __( 'The slug is required.', 'libreforge' ) ];
		}

		if ( strlen( $definition->slug ) > $max_length ) {
			/* translators: %d: maximum length */
			return [ 'slug' => sprintf( __( 'The slug must have at most %d characters.', 'libreforge' ), $max_length ) ];
		}

		$existing = $repository->find_by_slug( $this->id(), $definition->slug );

		if ( $existing && $existing->id !== $definition->id ) {
			return [ 'slug' => __( 'Another item already uses this slug.', 'libreforge' ) ];
		}

		return [];
	}

	/**
	 * True when the slug belongs to one of our own active definitions (so the
	 * WordPress registration we see is ours, not another plugin's).
	 */
	protected function is_ours( Definition $definition, DefinitionRepository $repository ): bool {
		foreach ( $repository->active( $this->id() ) as $active ) {
			if ( $active->slug === $definition->slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	protected static function text( array $input, string $key, string $fallback = '' ): string {
		return isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? sanitize_text_field( (string) $input[ $key ] ) : $fallback;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	protected static function flag( array $input, string $key, bool $fallback ): bool {
		return array_key_exists( $key, $input ) ? filter_var( $input[ $key ], FILTER_VALIDATE_BOOLEAN ) : $fallback;
	}

	/**
	 * List of slugs (post types, taxonomies, supports...), optionally restricted to $allowed.
	 *
	 * @param array<string, mixed> $input
	 * @param string[]|null        $allowed
	 * @return string[]
	 */
	protected static function keys( array $input, string $key, ?array $allowed = null, array $fallback = [] ): array {
		if ( ! array_key_exists( $key, $input ) ) {
			return $fallback;
		}

		$values = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $input[ $key ] ) ) ) );

		return null === $allowed ? $values : array_values( array_intersect( $values, $allowed ) );
	}

	/**
	 * Optional label overrides: [ 'add_new_item' => 'Add new house', ... ].
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, string>
	 */
	protected static function labels( array $input ): array {
		$labels = [];

		foreach ( (array) ( $input['labels'] ?? [] ) as $key => $value ) {
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$labels[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
			}
		}

		return $labels;
	}
}
