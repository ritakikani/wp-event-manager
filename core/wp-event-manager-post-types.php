<?php
/**
 * WP_Event_Manager_Content class.
 */

class WP_Event_Manager_Post_Types {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  2.5
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  2.5
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
	/**
	 * Constructor
	 */

	public function __construct() {

		add_action( 'init', array( $this, 'register_post_types' ), 0 );

		add_filter( 'admin_head', array( $this, 'admin_head' ) );
		add_filter( 'the_content', array( $this, 'event_content' ) );
		add_action( 'event_manager_check_for_expired_events', array( $this, 'check_for_expired_events' ) );
		add_action( 'event_manager_delete_old_previews', array( $this, 'delete_old_previews' ) );

		add_action( 'pending_to_publish', array( $this, 'set_event_expiry_date' ) );
		add_action( 'preview_to_publish', array( $this, 'set_event_expiry_date' ) );
		add_action( 'draft_to_publish', array( $this, 'set_event_expiry_date' ) );
		add_action( 'auto-draft_to_publish', array( $this, 'set_event_expiry_date' ) );
		add_action( 'expired_to_publish', array( $this, 'set_event_expiry_date' ) );
		
		add_action( 'wp_footer', array( $this, 'output_structured_data' ) );
		
		add_action( 'wp_head', array( $this, 'noindex_expired_cancelled_event_listings' ) );

		add_filter( 'display_event_description', 'wptexturize'        );
		add_filter( 'display_event_description', 'convert_smilies'    );
		add_filter( 'display_event_description', 'convert_chars'      );
		add_filter( 'display_event_description', 'wpautop'            );
		add_filter( 'display_event_description', 'shortcode_unautop'  );
		add_filter( 'display_event_description', 'prepend_attachment' );

		if ( ! empty( $GLOBALS['wp_embed'] ) ) {
   			add_filter( 'display_event_description', array( $GLOBALS['wp_embed'], 'run_shortcode' ), 8 );
     		add_filter( 'display_event_description', array( $GLOBALS['wp_embed'], 'autoembed' ), 8 );
   		}
		
		add_action( 'event_manager_registration_details_email', array( $this, 'registration_details_email' ) );
		add_action( 'event_manager_registration_details_url', array( $this, 'registration_details_url' ) );		

		add_filter( 'wp_insert_post_data', array( $this, 'fix_post_name' ), 10, 2 );
		add_action( 'add_post_meta', array( $this, 'maybe_add_geolocation_data' ), 10, 3 );
		add_action( 'update_post_meta', array( $this, 'update_post_meta' ), 10, 4 );
		add_action( 'wp_insert_post', array( $this, 'maybe_add_default_meta_data' ), 10, 2 );		
		
		add_action( 'parse_query', array( $this, 'add_feed_query_args' ) );

		// WP ALL Import
		add_action( 'pmxi_saved_post', array( $this, 'pmxi_saved_post' ), 10, 1 );
 
        //view count action
        add_action( 'set_single_listing_view_count', array( $this, 'set_single_listing_view_count' ));
	}

	/**
	 * register_post_types function.
	 *
	 * @access public
	 * @return void
	 */

	public function register_post_types() {

		if ( post_type_exists( "event_listing" ) )

			return;

		$admin_capability = 'manage_event_listings';
		$permalink_structure = WP_Event_Manager_Post_Types::get_permalink_structure();

		/**
		 * Taxonomies
		 */

		if ( get_option( 'event_manager_enable_categories' ) ) {

			$singular  = __( 'Event category', 'wp-event-manager' );

			$plural    = __( 'Event categories', 'wp-event-manager' );

			if ( current_theme_supports( 'event-manager-templates' ) ) {

				$rewrite   = array(

					'slug'         => $permalink_structure['category_rewrite_slug'],

					'with_front'   => false,

					'hierarchical' => false
				);

				$public    = true;

			} else {

				$rewrite   = false;

				$public    = false;
			}

			register_taxonomy( "event_listing_category",

			apply_filters( 'register_taxonomy_event_listing_category_object_type', array( 'event_listing' ) ),

	       	 	apply_filters( 'register_taxonomy_event_listing_category_args', array(

		            'hierarchical' 			=> true,

		            'update_count_callback' => '_update_post_term_count',

		            'label' 				=> $plural,

		            'labels' => array(

						'name'              => $plural,

						'singular_name'     => $singular,

						'menu_name'         => ucwords( $plural ),

						'search_items'      => sprintf( __( 'Search %s', 'wp-event-manager' ), $plural ),

						'all_items'         => sprintf( __( 'All %s', 'wp-event-manager' ), $plural ),

						'parent_item'       => sprintf( __( 'Parent %s', 'wp-event-manager' ), $singular ),

						'parent_item_colon' => sprintf( __( 'Parent %s:', 'wp-event-manager' ), $singular ),

						'edit_item'         => sprintf( __( 'Edit %s', 'wp-event-manager' ), $singular ),

						'update_item'       => sprintf( __( 'Update %s', 'wp-event-manager' ), $singular ),

						'add_new_item'      => sprintf( __( 'Add New %s', 'wp-event-manager' ), $singular ),

						'new_item_name'     => sprintf( __( 'New %s Name', 'wp-event-manager' ),  $singular )

	            	),

		            'show_ui' 				=> true,

		            'public' 	     		=> $public,

		            'capabilities'			=> array(

		            	'manage_terms' 		=> $admin_capability,

		            	'edit_terms' 		=> $admin_capability,

		            	'delete_terms' 		=> $admin_capability,

		            	'assign_terms' 		=> $admin_capability,

		            ),

		            'rewrite' 				=> $rewrite,

		        ) )

		    );

		}

		if ( get_option( 'event_manager_enable_event_types' ) ) {

		        $singular  = __( 'Event type', 'wp-event-manager' );

				$plural    = __( 'Event types', 'wp-event-manager' );

			if ( current_theme_supports( 'event-manager-templates' ) ) {

				$rewrite   = array(

					'slug'         => $permalink_structure['type_rewrite_slug'],

					'with_front'   => false,

					'hierarchical' => false

				);

				$public    = true;

			} else {

				$rewrite   = false;

				$public    = false;

			}

			register_taxonomy( "event_listing_type",

			apply_filters( 'register_taxonomy_event_listing_type_object_type', array( 'event_listing' ) ),

		        apply_filters( 'register_taxonomy_event_listing_type_args', array(

		            'hierarchical' 			=> true,

		            'label' 				=> $plural,

		            'labels' => array(

	                    'name' 				=> $plural,

	                    'singular_name' 	=> $singular,

	                    'menu_name'         => ucwords( $plural ),

	                    'search_items' 		=> sprintf( __( 'Search %s', 'wp-event-manager' ), $plural ),

	                    'all_items' 		=> sprintf( __( 'All %s', 'wp-event-manager' ), $plural ),

	                    'parent_item' 		=> sprintf( __( 'Parent %s', 'wp-event-manager' ), $singular ),

	                    'parent_item_colon' => sprintf( __( 'Parent %s:', 'wp-event-manager' ), $singular ),

	                    'edit_item' 		=> sprintf( __( 'Edit %s', 'wp-event-manager' ), $singular ),

	                    'update_item' 		=> sprintf( __( 'Update %s', 'wp-event-manager' ), $singular ),

	                    'add_new_item' 		=> sprintf( __( 'Add New %s', 'wp-event-manager' ), $singular ),

	                    'new_item_name' 	=> sprintf( __( 'New %s Name', 'wp-event-manager' ),  $singular )
	            	),

		            'show_ui' 				=> true,

		            'public' 			    => $public,

		            'capabilities'			=> array(

		            	'manage_terms' 		=> $admin_capability,

		            	'edit_terms' 		=> $admin_capability,

		            	'delete_terms' 		=> $admin_capability,

		            	'assign_terms' 		=> $admin_capability,
		            ),

		           'rewrite' 				=> $rewrite,
		        ) )
		    );
	    }

	    /**
		 * Post types
		 */

		$singular  = __( 'Event', 'wp-event-manager' );

		$plural    = __( 'Events', 'wp-event-manager' );

		
		/**
		 * Set whether to add archive page support when registering the event listing post type.
		 *
		 * @since 2.5
		 *
		 * @param bool $enable_event_archive_page
		 */
		if ( apply_filters( 'event_manager_enable_event_archive_page', current_theme_supports( 'event-manager-templates' ) ) ) {
			$has_archive = _x( 'events', 'Post type archive slug - resave permalinks after changing this', 'wp-event-manager' );
		} else {
			$has_archive = false;
		}

		$rewrite     = array(
		    
			'slug'       => $permalink_structure['event_rewrite_slug'],

			'with_front' => false,

			'feeds'      => true,

			'pages'      => false
		);

		register_post_type( "event_listing",

			apply_filters( "register_post_type_event_listing", array(

				'labels' => array(

					'name' 					=> $plural,

					'singular_name' 		=> $singular,

					'menu_name'             => __( 'Event Listings', 'wp-event-manager' ),

					'all_items'             => sprintf( __( 'All %s', 'wp-event-manager' ), $plural ),

					'add_new' 				=> __( 'Add New', 'wp-event-manager' ),

					'add_new_item' 			=> sprintf( __( 'Add %s', 'wp-event-manager' ), $singular ),

					'edit' 					=> __( 'Edit', 'wp-event-manager' ),

					'edit_item' 			=> sprintf( __( 'Edit %s', 'wp-event-manager' ), $singular ),

					'new_item' 				=> sprintf( __( 'New %s', 'wp-event-manager' ), $singular ),

					'view' 					=> sprintf( __( 'View %s', 'wp-event-manager' ), $singular ),

					'view_item' 			=> sprintf( __( 'View %s', 'wp-event-manager' ), $singular ),

					'search_items' 			=> sprintf( __( 'Search %s', 'wp-event-manager' ), $plural ),

					'not_found' 			=> sprintf( __( 'No %s found', 'wp-event-manager' ), $plural ),

					'not_found_in_trash' 	=> sprintf( __( 'No %s found in trash', 'wp-event-manager' ), $plural ),

					'parent' 				=> sprintf( __( 'Parent %s', 'wp-event-manager' ), $singular ),
					
					'featured_image'        => __( 'Organizer Logo', 'wp-event-manage' ),
					
					'set_featured_image'    => __( 'Set organizer logo', 'wp-event-manage' ),
					
					'remove_featured_image' => __( 'Remove organizer logo', 'wp-event-manage' ),
					
					'use_featured_image'    => __( 'Use as organizer logo', 'wp-event-manage' ),
				),

				'description' => sprintf( __( 'This is where you can create and manage %s.', 'wp-event-manager' ), $plural ),

				'public' 				=> true,

				'show_ui' 				=> true,

				'capability_type' 		=> 'event_listing',

				'map_meta_cap'          => true,

				'publicly_queryable' 	=> true,

				'exclude_from_search' 	=> false,

				'hierarchical' 			=> false,

				'rewrite' 				=> $rewrite,

				'query_var' 			=> true,

					'supports' 				=> array( 'title', 'editor', 'custom-fields', 'publicize' , 'thumbnail'),

				'has_archive' 			=> $has_archive,

				'show_in_nav_menus' 	=> false,

				'menu_icon' => 'dashicons-calendar' // It's use to display event listing icon at admin site. 
			) )
		);

		/**
		 * Feeds
		 */

		add_feed( 'event_feed', array( $this, 'event_feed' ) );

		/**
		 * Post status
		 */

		register_post_status( 'expired', array(

			'label'                     => _x( 'Expired', 'post status', 'wp-event-manager' ),

			'public'                    => true,

			'exclude_from_search'       => true,

			'show_in_admin_all_list'    => true,

			'show_in_admin_status_list' => true,

			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'wp-event-manager' )
		) );

		register_post_status( 'preview', array(

			'public'                    => true,

			'exclude_from_search'       => true,

			'show_in_admin_all_list'    => true,

			'show_in_admin_status_list' => true,
	
			'label_count'               => _n_noop( 'Preview <span class="count">(%s)</span>', 'Preview <span class="count">(%s)</span>', 'wp-event-manager' )
		) );
	}

	/**
	 * Change label
	 */

	public function admin_head() {

		global $menu;

		$plural     = __( 'Event Listings', 'wp-event-manager' );

		$count_events = wp_count_posts( 'event_listing', 'readable' );

		if ( ! empty( $menu ) && is_array( $menu ) ) {

			foreach ( $menu as $key => $menu_item ) {

				if ( strpos( $menu_item[0], $plural ) === 0 ) {

					if ( $order_count = $count_events->pending ) {

						$menu[ $key ][0] .= " <span class='awaiting-mod update-plugins count-$order_count'><span class='pending-count'>" . number_format_i18n( $count_events->pending ) . "</span></span>" ;
					}

					break;
				}
			}
		}
	}

	/**
	 * Add extra content when showing event content
	 */

	public function event_content( $content ) {

		global $post;

		if ( ! is_singular( 'event_listing' ) || ! in_the_loop() ) {

			return $content;
		}

		remove_filter( 'the_content', array( $this, 'event_content' ) );

		if ( 'event_listing' === $post->post_type ) {

			ob_start();

			do_action( 'event_content_start' );

			get_event_manager_template_part( 'content-single', 'event_listing' );

			do_action( 'event_content_end' );

			$content = ob_get_clean();
		}

		add_filter( 'the_content', array( $this, 'event_content' ) );

		return apply_filters( 'event_manager_single_event_content', $content, $post );
	}

	/**
	 * Event listing feeds
	 */

	public function event_feed() {

		$query_args = array(

			'post_type'           => 'event_listing',

			'post_status'         => 'publish',

			'ignore_sticky_posts' => 1,

			'posts_per_page'      => isset( $_GET['posts_per_page'] ) ? absint( $_GET['posts_per_page'] ) : 10,

			'tax_query'           => array(),

			'meta_query'          => array()
		);		

		if ( ! empty( $_GET['search_location'] ) ) {

			$location_meta_keys = array( 'geolocation_formatted_address', '_event_location', 'geolocation_state_long' );

			$location_search    = array( 'relation' => 'OR' );

			foreach ( $location_meta_keys as $meta_key ) {

				$location_search[] = array(

					'key'     => $meta_key,

					'value'   => sanitize_text_field( $_GET['search_location'] ),

					'compare' => 'like'
				);
			}
			
			$query_args['meta_query'][] = $location_search;
		}
		
		if ( ! empty( $_GET['search_datetimes'] ) ) 
		{
			if($_GET['search_datetimes'] == 'datetime_today')
			{	
				$datetime=date('Y-m-d');
				
				$date_search[] = array(
						'key'     => '_event_start_date',
						'value'   => $datetime,
						'compare' => 'LIKE',
					);
			}
			elseif( $_GET['search_datetimes'] == 'datetime_tomorrow' )
			{ 
				$datetime=date('Y-m-d',strtotime("+1 day")); 
				
				$date_search[] = array(
						'key'     => '_event_start_date',
						'value'   => $datetime,
						'compare' => 'LIKE',
					);
			}
			elseif( $_GET['search_datetimes'] == 'datetime_thisweek')
			{					
				$year=date('Y');
				$weekNumber=date('W');                 
                $dates[0]= date('Y-m-d', strtotime($year.'W'.str_pad($weekNumber, 2, 0, STR_PAD_LEFT)));
                $dates[1] = date('Y-m-d', strtotime($year.'W'.str_pad($weekNumber, 2, 0, STR_PAD_LEFT).' +6 days'));				

				$date_search[] = array(
					'key'     => '_event_start_date',
					'value'   => $dates,
					'compare' => 'BETWEEN',
					'type'    => 'date'
				);
			} 
			elseif( $_GET['search_datetimes'] =='datetime_thisweekend' )
			{
				$saturday_date=date('Y-m-d', strtotime('this Saturday', time()));
				$sunday_date=date('Y-m-d', strtotime('this Saturday +1 day', time()));
                $dates[0]= $saturday_date;
                $dates[1]= $sunday_date;
                
			    $date_search[] = array(
						'key'     => '_event_start_date',
						'value'   => $dates,
					    'compare' => 'BETWEEN',
					    'type'    => 'date'
					);
			} 
			elseif( $_GET['search_datetimes'] =='datetime_thismonth')
			{	
                $dates[0]= date('Y-m-d', strtotime('first day of this month', time()));
                $dates[1] = date('Y-m-d', strtotime('last day of this month', time()));				

				$date_search[] = array(
						'key'     => '_event_start_date',
						'value'   => $dates,
					    'compare' => 'BETWEEN',
					    'type'    => 'date'
					);
			}
			elseif( $_GET['search_datetimes'] =='datetime_thisyear')
			{
				$dates[0]= date('Y-m-d', strtotime('first day of january', time()));
                $dates[1] = date('Y-m-d', strtotime('last day of december', time()));	

				$date_search[] = array(
						'key'     => '_event_start_date',
						'value'   => $dates,
					    'compare' => 'BETWEEN',
					    'type'    => 'date'
					);
			}
			elseif( $_GET['search_datetimes'] =='datetime_nextweek')
			{
			    $year=date('Y');
				$weekNumber=date('W')+1;                 
                $dates[0]= date('Y-m-d', strtotime($year.'W'.str_pad($weekNumber, 2, 0, STR_PAD_LEFT)));
                $dates[1] = date('Y-m-d', strtotime($year.'W'.str_pad($weekNumber, 2, 0, STR_PAD_LEFT).' +6 days'));	
               
				$date_search[] = array(
					'key'     => '_event_start_date',
					'value'   => $dates,
					'compare' => 'BETWEEN',
					'type'    => 'date'
				);		    
			
			}
			elseif( $_GET['search_datetimes'] =='datetime_nextweekend')
			{
				$next_saturday_date=date('Y-m-d', strtotime('next Saturday', time()));
				$next_sunday_date=date('Y-m-d', strtotime('next Saturday +1 day', time()));
                $dates[0]= $next_saturday_date;
                $dates[1]= $next_sunday_date;               
                
			    $date_search[] = array(
						'key'     => '_event_start_date',
						'value'   => $dates,
					    'compare' => 'BETWEEN',
					    'type'    => 'date'
					);
			} 
			elseif( $_GET['search_datetimes'] =='datetime_nextmonth')
			{
				$dates[0]= date('Y-m-d', strtotime('first day of next month', time()));
                $dates[1] = date('Y-m-d', strtotime('last day of next month', time()));	
                
				$date_search[] = array(
						'key'     => '_event_start_date',
						'value'   => $dates,
					    'compare' => 'BETWEEN',
					    'type'    => 'date'
					);
			}
			elseif( $_GET['search_datetimes'] =='datetime_nextyear')
			{
			    $year=date('Y')+1;
			    $dates[0]= date('Y-m-d', strtotime('first day of January ' . $year, time()));
                $dates[1] = date('Y-m-d', strtotime('last day of december '. $year, time()));              

				$date_search[] = array(
						'key'     => '_event_start_date',
						'value'   => $dates,
					    'compare' => 'BETWEEN',
					    'type'    => 'date'
					);
			}

			$query_args['meta_query'][] = $date_search;
		}
		
		if ( ! empty( $_GET['search_ticket_prices'] ) ) {
		    
			if($_GET['search_ticket_prices'] =='ticket_price_paid')
			{  
			  $ticket_price_value='paid';     
			}
			else if ( $_GET['search_ticket_prices'] =='ticket_price_free')
			{
			  $ticket_price_value='free';
			}
			$ticket_search[] = array(
							'key'     => '_event_ticket_options',
							'value'   => $ticket_price_value,
							'compare' => '=',
						);
			$query_args['meta_query'][] = $ticket_search;			
		}
		
		if ( ! empty( $_GET['search_event_types'] ) ) {
		    
			$cats     = explode( ',', sanitize_text_field( $_GET['search_event_types'] ) ) + array( 0 );

			$field    = is_numeric( $cats ) ? 'term_id' : 'slug';

			$operator = 'all' === get_option( 'event_manager_event_type_filter_type', 'all' ) && sizeof( $args['search_event_types'] ) > 1 ? 'AND' : 'IN';

			$query_args['tax_query'][] = array(

				'taxonomy'         => 'event_listing_type',

				'field'            => $field,

				'terms'            => $cats,

				'include_children' => $operator !== 'AND' ,

				'operator'         => $operator
			);
		}
		
		if ( ! empty( $_GET['search_categories'] ) ) {

			$cats     = explode( ',', sanitize_text_field( $_GET['search_categories'] ) ) + array( 0 );

			$field    = is_numeric( $cats ) ? 'term_id' : 'slug';

			$operator = 'all' === get_option( 'event_manager_category_filter_type', 'all' ) && sizeof( $args['search_categories'] ) > 1 ? 'AND' : 'IN';

			$query_args['tax_query'][] = array(

				'taxonomy'         => 'event_listing_category',

				'field'            => $field,

				'terms'            => $cats,

				'include_children' => $operator !== 'AND' ,

				'operator'         => $operator
			);
		}
		if ( $event_manager_keyword = sanitize_text_field( $_GET['search_keywords'] ) ) {

			$query_args['s'] = $event_manager_keyword;
			
			add_filter( 'posts_search', 'get_event_listings_keyword_search' );
		}
		
		if ( empty( $query_args['meta_query'] ) ) {

			unset( $query_args['meta_query'] );
		}

		if ( empty( $query_args['tax_query'] ) ) {

			unset( $query_args['tax_query'] );
		}

		query_posts( apply_filters( 'event_feed_args', $query_args ) );

		add_action( 'rss2_ns', array( $this, 'event_feed_namespace' ) );

		add_action( 'rss2_item', array( $this, 'event_feed_item' ) );

		do_feed_rss2( false );
		remove_filter( 'posts_search', 'get_event_listings_keyword_search' );
	}
	
	/**
	 * In order to make sure that the feed properly queries the 'event_listing' type
	 *
	 * @param WP_Query $wp
	 */
	public function add_feed_query_args( $wp ) {
		
		// Let's leave if not the event feed
		if ( ! isset( $wp->query_vars['feed'] ) || 'event_feed' !== $wp->query_vars['feed'] ) {
			return;
		}
		
		// Leave if not a feed.
		if ( false === $wp->is_feed ) {
			return;
		}
		
		// If the post_type was already set, let's get out of here.
		if ( isset( $wp->query_vars['post_type'] ) && ! empty( $wp->query_vars['post_type'] ) ) {
			return;
		}
		
		$wp->query_vars['post_type'] = 'event_listing';
	}
	

	/**
	 * Add a custom namespace to the event feed
	 */

	public function event_feed_namespace() {

		echo 'xmlns:event_listing="' .  site_url() . '"' . "\n";
	}

	/**
	 * Add custom data to the event feed
	 */

	public function event_feed_item() {

		$post_id  = get_the_ID();

		$location = get_event_location( $post_id );

		$event_type = get_event_type( $post_id );

		$ticket_price  = get_event_ticket_option( $post_id );

		$organizer  = get_organizer_name( $post_id );

		if ( $location ) {

			echo "<event_listing:location><![CDATA[" . esc_html( $location ) . "]]></event_listing:location>\n";
		}

		if (  isset($event_type->name)  ) {
			
			echo "<event_listing:event_type><![CDATA[" . esc_html( $event_type->name ) . "]]></event_listing:event_type>\n";
		}

		if ( $ticket_price ) {

			echo "<event_listing:ticket_price><![CDATA[" . esc_html( $ticket_price ) . "]]></event_listing:ticket_price>\n";
		}

		if ( $organizer ) {

			echo "<event_listing:organizer><![CDATA[" . esc_html( $organizer ) . "]]></event_listing:organizer>\n";
		}
	}

	/**
	 * Expire events
	 */

	public function check_for_expired_events() {

		global $wpdb;
		
		// Change status to expired

		$event_ids = $wpdb->get_col( $wpdb->prepare( "

			SELECT postmeta.post_id FROM {$wpdb->postmeta} as postmeta

			LEFT JOIN {$wpdb->posts} as posts ON postmeta.post_id = posts.ID

			WHERE postmeta.meta_key = '_event_expiry_date'

			AND postmeta.meta_value > 0

			AND postmeta.meta_value < %s

			AND posts.post_status = 'publish'

			AND posts.post_type = 'event_listing'

		", date( 'Y-m-d', current_time( 'timestamp' ) ) ) );

		if ( $event_ids ) {

			foreach ( $event_ids as $event_id ) {

				$event_data       = array();

				$event_data['ID'] = $event_id;

				$event_data['post_status'] = 'expired';

				wp_update_post( $event_data );
			}
		}
		
		// Delete old expired events	
		$return_flag=absint( get_option( 'event_manager_delete_expired_events' ) ) == 1 ? true : false;
		if ( apply_filters( 'event_manager_delete_expired_events', $return_flag ) ) {

			$event_ids = $wpdb->get_col( $wpdb->prepare( "

				SELECT posts.ID FROM {$wpdb->posts} as posts

				WHERE posts.post_type = 'event_listing'

				AND posts.post_modified < %s

				AND posts.post_status = 'expired'

			", date( 'Y-m-d', strtotime( '-' . apply_filters( 'event_manager_delete_expired_events_days', 30 ) . ' days', current_time( 'timestamp' ) ) ) ) );
			
			if ( $event_ids ) {

				foreach ( $event_ids as $event_id ) {

					wp_trash_post( $event_id );
				}
			}
		}
	}
	
	/**
	 * Delete old previewed events after 30 days to keep the DB clean
	 */

	public function delete_old_previews() {

		global $wpdb;

		// Delete old expired events

		$event_ids = $wpdb->get_col( $wpdb->prepare( "

			SELECT posts.ID FROM {$wpdb->posts} as posts

			WHERE posts.post_type = 'event_listing'

			AND posts.post_modified < %s

			AND posts.post_status = 'preview'

		", date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) ) ) );

		if ( $event_ids ) {

			foreach ( $event_ids as $event_id ) {

				wp_delete_post( $event_id, true );
			}
		}
	}

	/**
	 * Set expirey date when event status changes
	 */

	public function set_event_expiry_date( $post ) {
		if ( $post->post_type !== 'event_listing' ) {
			return;
		}
		// See if it is already set
		if ( metadata_exists( 'post', $post->ID, '_event_expiry_date' ) ) {
			
			$expires = get_post_meta( $post->ID, '_event_expiry_date', true );
			
			if ( $expires && strtotime( $expires ) < current_time( 'timestamp' ) ) {
				
				update_post_meta( $post->ID, '_event_expiry_date', '' );
			}
		}
		
		// No metadata set so we can generate an expiry date
		// See if the user has set the expiry manually:
		if ( ! empty( $_POST[ '_event_expiry_date' ] ) ) {
			update_post_meta( $post->ID, '_event_expiry_date', date( 'Y-m-d', strtotime( sanitize_text_field( $_POST[ '_event_expiry_date' ] ) ) ) );
			// No manual setting? Lets generate a date
		} elseif (false == isset( $expires ) ){
			$expires = get_event_expiry_date( $post->ID );
			update_post_meta( $post->ID, '_event_expiry_date', $expires );
			// In case we are saving a post, ensure post data is updated so the field is not overridden
			if ( isset( $_POST[ '_event_expiry_date' ] ) ) {
				
				$_POST[ '_event_expiry_date' ] = $expires;
			}
		}
	}

	    /**
	    * Set post view on the single listing page
	    * @param  array $post	 
	    */

	    function set_single_listing_view_count($post) 
	    {     
	       //get the user role. 

		    if ( is_user_logged_in() ) 
		     {
			     $role=get_event_manager_current_user_role();  

		         $current_user = wp_get_current_user();

			  if ( $role !='Administrator' && ($post->post_author!=$current_user->ID ) )
	                  { 
	                   	$this->set_post_views($post->ID);
	                  }   		 
		      }
		      else
		      {			  
			  $this->set_post_views($post->ID);
		      }        
	    }

    /**
	 * This function is use to set the counts the event views and attendees views.
     * This function also used at attendees dashboard file.
	 * @param  int $post_id	 
	*/

	public function set_post_views($post_id) 
    {
		    $count_key = '_view_count';
            $count = get_post_meta($post_id, $count_key, true);

            if($count=='' || $count==null)
            {
                $count = 0;
                delete_post_meta($post_id, $count_key);
                add_post_meta($post_id, $count_key, '0');
            }
            else
            {
                $count++;
                update_post_meta($post_id, $count_key, $count);
            }
	}
	
	/**
	 * The registration content when the registration method is an email
	 */
	public function registration_details_email( $register ) {
		get_event_manager_template( 'event-registration-email.php', array( 'register' => $register ) );
	}

	/**
	 * The registration content when the registration method is a url
	 */
	public function registration_details_url( $register ) {
		get_event_manager_template( 'event-registration-url.php', array( 'register' => $register ) );
	}

	/**
	 * Fix post name when wp_update_post changes it
	 * @param  array $data
	 * @return array
	 */

	public function fix_post_name( $data, $postarr ) {

		 if ( 'event_listing' === $data['post_type'] && 'pending' === $data['post_status'] && ! current_user_can( 'publish_posts' ) ) {

				$data['post_name'] = $postarr['post_name'];
		 }
		 return $data;
	}

	/**
	 * Generate location data if a post is added
	 * @param  int $post_id
	 * @param  array $post
	 */

	public function maybe_add_geolocation_data( $object_id, $meta_key, $_meta_value ) {

		if ( '_event_location' !== $meta_key || 'event_listing' !== get_post_type( $object_id ) ) {

			return;
		}
		do_action( 'event_manager_event_location_edited', $object_id, $_meta_value );
	}

	/**
	 * Triggered when updating meta on a event listing.
	 *
	 * @param int    $meta_id
	 * @param int    $object_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 */
	public function update_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'event_listing' === get_post_type( $object_id ) ) {
			switch ( $meta_key ) {
				case '_event_location':
					$this->maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value );
					break;
				case '_featured':
					$this->maybe_update_menu_order( $meta_id, $object_id, $meta_key, $meta_value );
					break;
			}
		}
	}


	
	/**
	 * Generate location data if a post is updated
	 */

	public function maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $_meta_value ) {

		if ( '_event_location' !== $meta_key || 'event_listing' !== get_post_type( $object_id ) ) {
		    
			return;
		}
		do_action( 'event_manager_event_location_edited', $object_id, $_meta_value );
	}

	/**
	 * Maybe set menu_order if the featured status of a event is changed
	 */

	public function maybe_update_menu_order( $meta_id, $object_id, $meta_key, $_meta_value ) {

		if ( '_featured' !== $meta_key || 'event_listing' !== get_post_type( $object_id ) ) {

			return;
		}

		global $wpdb;

		if ( '1' == $_meta_value ) {

			$wpdb->update( $wpdb->posts, array( 'menu_order' => -1 ), array( 'ID' => $object_id ) );

		} else {

			$wpdb->update( $wpdb->posts, array( 'menu_order' => 0 ), array( 'ID' => $object_id, 'menu_order' => -1 ) );
		}
		
		clean_post_cache( $object_id );
	}

	/**
	 * 
	 */

	public function maybe_generate_geolocation_data( $meta_id, $object_id, $meta_key, $_meta_value ) {

		$this->maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $_meta_value );
	}

	/**
	 * Maybe set default meta data for event listings
	 * @param  int $post_id
	 * @param  WP_Post $post
	*/

	public function maybe_add_default_meta_data( $post_id, $post = '' ) {

		if ( empty( $post ) || 'event_listing' === $post->post_type ) {

			add_post_meta( $post_id, '_cancelled', 0, true );

			add_post_meta( $post_id, '_featured', 0, true );
		}
	}

	/**
	 * After importing via WP ALL Import, add default meta data
	 * @param  int $post_id
	 */

	public function pmxi_saved_post( $post_id ) {

		if ( 'event_listing' === get_post_type( $post_id ) ) {

			$this->maybe_add_default_meta_data( $post_id );

			if ( ! WP_Event_Manager_Geocode::has_location_data( $post_id ) && ( $location = get_post_meta( $post_id, '_event_location', true ) ) ) {

				WP_Event_Manager_Geocode::generate_location_data( $post_id, $location );
			}
		}
	}

	
	/**
	 * When deleting a event, delete its attachments
	 * @param  int $post_id
	 */
	public function before_delete_event( $post_id ) {
    	if ( 'event_listing' === get_post_type( $post_id ) ) {
			$attachments = get_children( array(
		        'post_parent' => $post_id,
		        'post_type'   => 'attachment'
		    ) );

			if ( $attachments ) {
				foreach ( $attachments as $attachment ) {
					wp_delete_attachment( $attachment->ID );
					@unlink( get_attached_file( $attachment->ID ) );
				}
			}
		}
	}
	
	/**
	 * Add noindex for expired and filled event listings.
	 */
	public function noindex_expired_cancelled_event_listings() {
		if ( ! is_single() ) {
			return;
		}
		$post = get_post();
		if ( ! $post || 'event_listing' !== $post->post_type ) {
			return;
		}
		if ( event_manager_allow_indexing_event_listing() ) {
			return;
		}
		wp_no_robots();
	}
	
	/**
	 * output_structured_data
	 * @since 1.8
	 */
	public function output_structured_data() {
		if ( ! is_single() ) {
			return;
		}
		if ( ! event_manager_output_event_listing_structured_data() ) {
			return;
		}
		$structured_data = event_manager_get_event_listing_structured_data();
		if ( ! empty( $structured_data ) ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $structured_data ) . '</script>';
		}
	}

		/**
	 * Retrieves permalink settings.
	 *
	 * @see https://github.com/woocommerce/woocommerce/blob/3.0.8/includes/wc-core-functions.php#L1573
	 * @since 2.5
	 * @return array
	 */
	public static function get_permalink_structure() {
		// Switch to the site's default locale, bypassing the active user's locale.
		if ( function_exists( 'switch_to_locale' ) && did_action( 'admin_init' ) ) {
			switch_to_locale( get_locale() );
		}

		$permalinks = wp_parse_args(
			(array) get_option( 'wpem_permalinks', array() ),
			array(
				'event_base'      => '',
				'category_base' => '',
				'type_base'     => '',
			)
		);

		// Ensure rewrite slugs are set.
		$permalinks['event_rewrite_slug']      = untrailingslashit( empty( $permalinks['event_base'] ) ? _x( 'event', 'Event permalink - resave permalinks after changing this', 'wp-event-manager' ) : $permalinks['event_base'] );
		$permalinks['category_rewrite_slug'] = untrailingslashit( empty( $permalinks['category_base'] ) ? _x( 'event-category', 'Event category slug - resave permalinks after changing this', 'wp-event-manager' ) : $permalinks['category_base'] );
		$permalinks['type_rewrite_slug']     = untrailingslashit( empty( $permalinks['type_base'] ) ? _x( 'event-type', 'Event type slug - resave permalinks after changing this', 'wp-event-manager' ) : $permalinks['type_base'] );

		// Restore the original locale.
		if ( function_exists( 'restore_current_locale' ) && did_action( 'admin_init' ) ) {
			restore_current_locale();
		}
		return $permalinks;
	}
}