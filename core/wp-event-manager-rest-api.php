<?php
/**
 * File containing the class WP_Event_Manager_REST_API.
 *
 * @package wp-event-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles functionality related to the REST API.
 *
 * @since 1.33.0
 */
class WP_Event_Manager_REST_API {
	/**
	 * Sets up initial hooks.
	 *
	 * @static
	 */
	public static function init() {
		add_filter( 'rest_prepare_event_listing', array( __CLASS__, 'prepare_event_listing' ), 10, 2 );
	}

	/**
	 * Filters the event listing data for a REST API response.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post          $post     Post object.
	 * @return WP_REST_Response
	 */
	public static function prepare_event_listing( $response, $post ) {
		$current_user = wp_get_current_user();
		$data         = $response->get_data();

		$response->set_data( $data );

		return $response;
	}
}
