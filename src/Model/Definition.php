<?php
/**
 * One stored configuration: a post type, taxonomy, field group, options page or glossary.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Model;

defined( 'ABSPATH' ) || exit;

final class Definition {

	public const ACTIVE = 'active';
	public const DRAFT  = 'draft';

	/**
	 * @param array<string, mixed> $config
	 */
	public function __construct(
		public string $kind,
		public string $slug,
		public array $config = [],
		public string $status = self::ACTIVE,
		public int $id = 0,
		public string $created_at = '',
		public string $updated_at = ''
	) {}

	public function is_active(): bool {
		return self::ACTIVE === $this->status;
	}

	/**
	 * @param mixed $fallback
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		return $this->config[ $key ] ?? $fallback;
	}

	public function title(): string {
		return (string) ( $this->config['title'] ?? $this->config['plural'] ?? $this->slug );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'         => $this->id,
			'kind'       => $this->kind,
			'slug'       => $this->slug,
			'status'     => $this->status,
			'title'      => $this->title(),
			'config'     => $this->config,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
