<?php
/**
 * Items returned by a query plus pagination numbers.
 *
 * @package LibreBlocks\Forge
 */

namespace LibreBlocks\Forge\Queries;

defined( 'ABSPATH' ) || exit;

final class QueryResult {

	/**
	 * @param array<int, mixed> $items WP_Post, WP_Term or WP_User objects.
	 */
	public function __construct(
		public string $type,
		public array $items,
		public int $total,
		public int $page,
		public int $per_page
	) {}

	public function pages(): int {
		if ( $this->per_page <= 0 ) {
			return 1;
		}

		return max( 1, (int) ceil( $this->total / $this->per_page ) );
	}

	public function is_empty(): bool {
		return ! $this->items;
	}
}
