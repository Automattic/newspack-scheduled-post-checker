<?php
/**
 * Plugin Name: Newspack Scheduled Post Checker
 * Description: Checks to make sure posts haven't missed their schedule, and publishes them if needed.
 * Version: 1.0.0
 * Author: Automattic
 * Author URI: https://newspack.blog/
 * License: GPL2
 */

defined( 'ABSPATH' ) || exit;

define( 'NEWSPACK_SCHEDULED_POST_CRON_HOOK', 'newspack_scheduled_post_checker' );

/**
 * Set up the checking.
 */
function nspc_init() {
	register_deactivation_hook( __FILE__, 'nspc_deactivate' );
	if ( ! wp_next_scheduled( NEWSPACK_SCHEDULED_POST_CRON_HOOK ) ) {
		wp_schedule_event( time(), 'fivemins', NEWSPACK_SCHEDULED_POST_CRON_HOOK );
	}
}
add_action( 'init', 'nspc_init' );

/**
 * Clear the cron job when this plugin is deactivated.
 */
function nspc_deactivate() {
    wp_clear_scheduled_hook( NEWSPACK_SCHEDULED_POST_CRON_HOOK );
}

/**
 * Check to see if any posts have missed schedule, and try sending them live again if so.
 */
function nspc_run_check() {
	$time = wp_date( 'Y-m-d H:i:s' );

	$posts_with_missed_schedule = get_posts( [
		'post_status' => 'future',
		'post_type'   => 'any',
		'fields'      => 'ids',
		'date_query'  => [
			[
				'before'    => $time,
				'inclusive' => false,
			],
		],
	] );

	if ( function_exists( 'spcl_log_event' ) ) {
		$message = 'Newspack Scheduled Post Checker running';
		$data    = [ 
			'posts_with_missed_schedule' => $posts_with_missed_schedule,
			'time'                       => $time,
		];
		spcl_log_event( $message, $data );
	}

	foreach ( $posts_with_missed_schedule as $post_id ) {
		if ( function_exists( 'spcl_log_event' ) ) {
			$message = 'Trying to publish post with missed schedule';
			$data    = [
				'id' => $post_id,
			];
			spcl_log_event( $message, $data );
		}
		check_and_publish_future_post( $post_id );
	}
}
add_action( NEWSPACK_SCHEDULED_POST_CRON_HOOK, 'nspc_run_check' );

/**
 * Add a cron interval for every five minutes.
 *
 * @param array $schedules Defined cron schedules.
 * @return array Modified $schedules.
 */
function nspc_add_cron_schedule( $schedules ) {
	$schedules['fivemins'] = [
		'interval' => MINUTE_IN_SECONDS * 5,
		'display'  => 'Every 5 minutes'
	];
	return $schedules;
}
add_filter( 'cron_schedules', 'nspc_add_cron_schedule' );
