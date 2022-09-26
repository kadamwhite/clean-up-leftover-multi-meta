<?php
/**
 * CLI command to remove problematic rows from the meta table.
 */

namespace CleanUPLeftoverMultiMeta\CLI;

use Alley_CLI_Bulk_Task;
use WP_CLI;
use WP_CLI_Command;

/**
 * Provide a command to remove problematic rows from the meta table.
 */
class Clean_Up_Leftover_Multi_Meta extends WP_CLI_Command {
	use Alley_CLI_Bulk_Task;

	/**
	 * Iterate through items in a post type, inspect the meta values registered for
	 * that post type, and remove duplicate rows for items listed as "single".
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Dry run: make no changes to live data.
	 *
	 * [--post-type=<posttype>]
	 * : Slug of post type for which to analyze and clean up meta. Defaults to "post".
	 *
	 * @param array $args       Args as string array
	 * @param array $args_assoc Args as associative array
	 */
	public function __invoke( $args, $args_assoc ) : void {
		$post_type = $args_assoc['post-type'] ?? 'post';
		$dry_run   = $args_assoc['dry-run'] ?? false;

		WP_CLI::log( "Analyzing meta for $post_type" );

		$results = [
			'total'   => 0,
			'errored' => [],
			'updated' => [],
		];

		$this->bulk_task(
			[
				'post_type'   => $post_type,
			],
			function( WP_Post $post ) use ( &$results, $post_type, $dry_run ) {
				$results['total'] += 1;

				WP_CLI::log( "Processing $post_type id {$post->ID}" );
			}
		);
	}
}
