<?php
/**
 * CLI command to remove problematic rows from the meta table.
 */

namespace CleanUPLeftoverMultiMeta\CLI;

use Alley_CLI_Bulk_Task;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;
use WP_Post;

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
	 * [--verbose]
	 * : Log additional output.
	 *
	 * @param array $args       Args as string array
	 * @param array $args_assoc Args as associative array
	 */
	public function __invoke( $args, $args_assoc ) : void {
		$post_type = $args_assoc['post-type'] ?? 'post';
		$dry_run   = $args_assoc['dry-run'] ?? false;
		$verbose   = $args_assoc['verbose'] ?? false;

		WP_CLI::log( "Analyzing meta for $post_type" );

		$registered_meta = array_merge(
			get_registered_meta_keys( 'post', $post_type ),
			get_registered_meta_keys( 'post', '' )
		);
		if ( $verbose ) {
			// phpcs:ignore
			WP_CLI::log( "Registered meta keys for $post_type: " . print_r( array_keys( $registered_meta ), true ) );
		}

		$query_args = [
			'post_type'   => $post_type,
		];

		$this->bulk_task(
			$query_args,
			function( WP_Post $post ) use ( $registered_meta, $post_type, $dry_run, $verbose ) {
				global $wpdb;

				foreach ( $registered_meta as $meta_key => $meta_registration ) {
					if ( ( $meta_registration['single'] ?? true ) === false ) {
						continue;
					}
					if ( ( $meta_registration['show_in_rest'] ?? true ) === false ) {
						continue;
					}
					$extant_meta = $wpdb->get_results(
						$wpdb->prepare(
							"select meta_id,meta_value,meta_key from {$wpdb->postmeta} where meta_key=%s and post_id=%d",
							$meta_key,
							$post->ID
						)
					);
					if ( count( $extant_meta ) <= 1 ) {
						continue;
					}
					if ( $verbose ) {
						WP_CLI::log( "Processing $post_type id {$post->ID} $meta_key" );

						// phpcs:ignore
						Utils\format_items( 'table', $extant_meta, 'meta_id,meta_value,meta_key' );
					}
					foreach ( array_slice( $extant_meta, 1 ) as $idx => $meta ) {
						if ( $verbose ) {
							WP_CLI::log( sprintf( 'Discarding row with ID %d as a duplicate', $meta->meta_id ) );
						}
						if ( $dry_run ) {
							WP_CLI::log( "Would delete {$meta_key} meta {$meta->meta_id} (Value '{$meta->meta_value}') for {$post_type} {$post->ID}" );
						} else {
							WP_CLI::log( "Deleting {$meta_key} meta {$meta->meta_id} (Value '{$meta->meta_value}') for {$post_type} {$post->ID}" );
							$rows_affected = $wpdb->delete(
								$wpdb->postmeta,
								[ 'meta_id' => $meta->meta_id ]
							);
							if ( 0 < $rows_affected ) {
								WP_CLI::success( "Deleted {$meta_key} meta {$meta->meta_id} (same as {$meta_key} meta {$extant_meta[0]->meta_id}) for {$post_type} {$post->ID}" );
							} elseif ( 0 === $rows_affected ) {
								WP_CLI::warning( "No rows affected when trying to delete {$meta->meta_id} for {$post_type} {$post->ID}" );
							} elseif ( false === $rows_affected ) {
								WP_CLI::error( "Errors encountered when trying to delete {$meta->meta_id} for {$post_type} {$post->ID}" );
							}
						}
					}
				}
			}
		);

		WP_CLI::log( 'Flushing the object cache.' );
		wp_cache_flush();
	}
}
