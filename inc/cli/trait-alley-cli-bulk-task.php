
<?php
/**
 * Chunk up the task when you need to iterate over many posts.
 *
 * For instance, to iterate over every post on the site and add post meta:
 *
 *     $this->bulk_task( function( $post ) {
 *         update_post_meta( $post->ID, 'some_meta', 'some value' );
 *     } );
 *
 * To do the same thing, but only the "post" post type, you might:
 *
 *     $this->bulk_task( [ 'post_type' => 'post' ], function( $post ) {
 *         update_post_meta( $post->ID, 'some_meta', 'some value' );
 *     } );
 *
 * Posts are iterated by ID, so changing data is relatively safe. For instance,
 *
 *     $this->bulk_task( 'wp_delete_post' );
 *
 * would iterate fine. Comparing this to normal pagination, if you delete posts
 * or change the query criteria, and you aren't paginating in reverse, you'd
 * end up missing ~half of your posts.
 *
 * If your class has a method `stop_the_insanity()` available to prevent memory
 * leaks, it will be called after each chunk. For an example, see
 * {link https://github.com/Automattic/vip-mu-plugins-public/blob/master/vip-helpers/vip-wp-cli.php#L5-L23}
 *
 * @author Matthew Boynes, Alley Interactive
 * @license GPLv2
 * @codingStandardsIgnoreFile
 */
trait Alley_CLI_Bulk_Task {

	/**
	 * Store the current WP_Query object hash for bulk tasks.
	 *
	 * @var string
	 */
	protected $bulk_task_object_hash;

	/**
	 * Store the last max ID for bulk task pagination.
	 *
	 * @var integer
	 */
	protected $bulk_task_min_id;

	/**
	 * Manipulate the WHERE clause of a bulk task query to paginate by ID.
	 *
	 * This checks the object hash to ensure that we don't manipulate any other
	 * queries that might run during a bulk task.
	 *
	 * @param  string $where The current $where clause.
	 * @param  WP_Query &$query WP_Query object.
	 * @return string WHERE clause with our pagination added.
	 */
	public function bulk_task_posts_where( $where, $query ) {
		if ( spl_object_hash( $query ) === $this->bulk_task_object_hash ) {
			return "AND {$GLOBALS['wpdb']->posts}.ID > {$this->bulk_task_min_id} {$where}";
		}
		return $where;
	}

	/**
	 * Loop through any number of posts efficiently with a callback, and output
	 * the progress.
	 *
	 * @param  array $args {
	 *     Optional. WP_Query args. Some have overridden defaults, and some are
	 *     fixed. Anything not mentioned below will operate as normal.
	 *
	 *     @type string $post_type Defaults to 'any'.
	 *     @type string $post_status Defaults to 'any'.
	 *     @type int $posts_per_page Defaults to 100.
	 *     @type bool $suppress_filters Always false.
	 *     @type bool $ignore_sticky_posts Always true.
	 *     @type int $paged Always 1.
	 *     @type string $orderby Always 'ID'.
	 *     @type string $order Always 'ASC'.
	 * }
	 * @param  callable $callable Required. Callback function to invoke for each
	 *                            post. The callable will be passed a WP_Post
	 *                            object.
	 */
	protected function bulk_task( $args, $callable = null ) {
		// $args is optional, so if it's callable, assume it replaces $callable.
		if ( is_callable( $args ) ) {
			$callable = $args;
			$args = array();
		}

		// Ensure that we have a callable.
		if ( ! is_callable( $callable ) ) {
			WP_CLI::error( 'You must pass a callable to `bulk_task()`' );
		}

		$args = wp_parse_args( $args, array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => 100,
		) );

		// Force some arguments and don't let them get overridden.
		$args['suppress_filters']    = false;
		$args['ignore_sticky_posts'] = true;
		$args['paged']               = 1;
		$args['orderby']             = 'ID';
		$args['order']               = 'ASC';

		// Ensure $bulk_task_min_id always starts at 0.
		$this->bulk_task_min_id = 0;
		$current_page = 0;

		// Handle pagination.
		add_filter( 'posts_where', array( $this, 'bulk_task_posts_where' ), 9999, 2 );

		// Output the empty status.
		$this->do_bulk_status();
		echo "\n";

		// All systems go.
		do {
			// Build the query object, but don't run it without the object hash.
			$query = new WP_Query();

			// Store the unique object hash to ensure we only manipulate this
			// query in `bulk_taks_posts_where()`.
			$this->bulk_task_object_hash = spl_object_hash( $query );

			// Run the query
			$query->query( $args );

			// Invoke the callable over every post
			array_walk( $query->posts, $callable );

			// Update our min ID for the next query
			$this->bulk_task_min_id = max( wp_list_pluck( $query->posts, 'ID' ) );

			// Contain memory leaks
			if ( method_exists( $this, 'stop_the_insanity' ) ) {
				$this->stop_the_insanity();
			}

			// Update the status
			$this->do_bulk_status( ++$current_page, $query->max_num_pages + $current_page - 1 );
		} while ( $query->found_posts && $query->max_num_pages > 1 );
		echo "\n";

		$this->bulk_task_min_id = null;
	}

	/**
	 * Output the status of a bulk task.
	 *
	 * This includes a progress bar, page/total pages, and a rough approximation
	 * of the time remaining based on the average number of seconds per page
	 * that the task has taken.
	 *
	 * @param  integer $page Current page number.
	 * @param  integer $max  Total number of pages to process.
	 */
	protected function do_bulk_status( $page = 0, $max = 0 ) {
		static $start;
		if ( ! $start || ! $page ) {
			$start = microtime( true );
		}
		if ( ! $page || ! $max ) {
			return;
		}
		$seconds_per_page = ( microtime( true ) - $start ) / $page;
		printf(
			'%s%' . ( strlen( $max ) + 2 ) . "d/%d complete; %s remaining\r",
			$this->progress_bar( $page / $max ),
			$page,
			$max,
			date( 'H:i:s', ( $max - $page ) * $seconds_per_page )
		);
	}

	/**
	 * Get a progress bar given a percent completion.
	 *
	 * This is a bit nicer than WP_CLI's progress bar and it fits nicely with
	 * the bulk task status.
	 *
	 * @param  float $percent Percent complete, from 0.00 - 1.00.
	 * @return string
	 */
	protected function progress_bar( $percent ) {
		return sprintf( '  [%-50s]  ', str_repeat( '#', floor( $percent * 50 ) ) );
	}
}
