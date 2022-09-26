<?php
/**
 * Plugin Name: Clean Up Leftover Multi-Meta
 * Plugin Version: 0.1.0
 * Plugin Author: K. Adam White
 */

namespace CleanUPLeftoverMultiMeta;

/* phpcs:disable PSR1.Files.SideEffects */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/inc/cli/trait-alley-cli-bulk-task.php';
	require_once __DIR__ . '/inc/cli/class-clean-up-leftover-multi-meta.php';

	\WP_CLI::add_command( 'clean-up-leftover-multi-meta', new CLI\Clean_Up_Leftover_Multi_Meta() );
}

/**
 * Connect namespace functions to actions & hooks.
 */
function boostrap() : void {
}

boostrap();
