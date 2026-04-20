<?php
/**
 * Delightful Downloads Core Bundle
 * Merged for simpler maintenance.
 */

/* ===== BEGIN includes/class-dedo-cache.php ===== */
/**
 * Cache Class
 *
 * @package  	Delightful Downloads
 * @author   	Ashley Rich
 * @copyright   Copyright (c) 2014, Ashley Rich
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class DEDO_Cache {

	/**
	 * Cache Enabled
	 * Is caching enabled on the settings screen?
	 * @var boolean
	 * @access private
	 */
	private $cache_enabled;

	/**
	 * Cache Duration
	 * How long are we caching data for?
	 * @var boolean
	 * @access private
	 */
	private $cache_duration;

	/**
	 * Cache Key
	 * Unique key for data.
	 * @var string
	 * @access private
	 */
	private $key;

	/**
	 * Cached
	 * Cached flag for data.
	 * @var boolean
	 * @access private
	 */
	private $cached = false;

	/**
	 * Init
	 * @return void
	 */
	public function __construct( $key ) {

		global $dedo_options;

		if ( $dedo_options['cache'] == 1 ) {
			$this->cache_enabled = true;
			$this->cache_duration = $dedo_options['cache_duration'] * 60;
			$this->key = $key;
		}
	}
	/**
	 * Get Cache
	 * Check for cached data in transients.
	 * @param string $sql Prepared SQL statement.
	 * @return mixed Mixed result or false on failure.
	 */
	public function get() {

		if ( true === $this->cache_enabled && false !== ( $data = get_transient( $this->key ) ) ) {
			// Set cached flag
			$this->cached = true;

			return $data;
		}

		return false;
	}

	/**
	 * Set Cache
	 * Cache data in transients.
	 * @param string $sql Prepared SQL statement.
	 * @return void
	 */
	public function set( $result ) {

		if ( true === $this->cache_enabled && false === $this->cached  ) {
			// Save transient
			set_transient( $this->key, $result, $this->cache_duration );
		}
	}

}

/* ===== END includes/class-dedo-cache.php ===== */

/* ===== BEGIN includes/class-dedo-logging.php ===== */
/**
 * Logging Class
 *
 * @package  	Delightful Downloads
 * @author   	Ashley Rich
 * @copyright   Copyright (c) 2014, Ashley Rich
 * @since    	1.4
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class DEDO_Logging {

	/**
	 *	Init Logging
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function __construct() {

		// Hooks
		add_action( 'ddownload_download_before', array( $this, 'save_success' ), 10, 1 );
	}

	/**
	 * Save Success Log
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function save_success( $download_id ) {
		
		// Hook before log
		do_action( 'ddownload_save_success_before', $download_id );

		$log = array(
			'status'	=> 'success',
			'post_id'	=> $download_id
		);

		$this->insert_log( $log );

		// Hook after log
		do_action( 'ddownload_save_success_after', $download_id );
	}

	/**
	 * Save Blocked Log
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function save_blocked( $download_id ) {
		
		// Hook before log
		do_action( 'ddownload_save_blocked_before', $download_id );

		$log = array(
			'status'	=> 'blocked',
			'post_id'	=> $download_id
		);

		$this->insert_log( $log );

		// Hook after log
		do_action( 'ddownload_save_blocked_after', $download_id );
	}

	/**
	 * Save Permission Log
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function save_permission( $download_id ) {
		
		// Hook before log
		do_action( 'ddownload_save_permission_before', $download_id );

		$log = array(
			'status'	=> 'permission',
			'post_id'	=> $download_id
		);

		$this->insert_log( $log );

		// Hook after log
		do_action( 'ddownload_save_permission_after', $download_id );
	}	

	/**
	 * Insert Log
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function insert_log( $log ) {
		
		global $wpdb, $dedo_options;

		// Hook before log
		do_action( 'ddownload_insert_log_before', $log );

		if ( ! $this->table_exists() ) {
			$this->setup_table();
		}

		if ( ! $this->table_exists() ) {
			return;
		}

		// Build log array
		$defaults = array(
			'post_id'	=> 0,
			'status'	=> 'success',
			'date'		=> current_time( 'mysql' ),
			'user_id'	=> get_current_user_id(),
			'ip_address'=> dedo_download_ip(),
			'agent'		=> sanitize_text_field( ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) )	
		);

		$log = wp_parse_args( $log, $defaults );
		if ( isset( $_GET[ 'sdownload' ] ) ) { $oneday = 'ONEDAYPASS | '; } else { $oneday='DOWNLOAD | '; }

		// Are we logging events for this user role?
		if ( false === $this->role_check( $log ) ) {
			return;
		}

		// Do we have a grace period?
		if ( true === $this->grace_period( $log ) ) {
			return;
		}

		// Prepare sql query
		$sql = $wpdb->prepare( "
				INSERT INTO $wpdb->ddownload_statistics (status, post_id, date, user_id, user_ip, user_agent)
				VALUES (%s, %d, %s, %d, %s, %s)
			",
			$log['status'],
			$log['post_id'],
			$log['date'],
			$log['user_id'],
			$this->prepare_ip_address( $log['ip_address'] ),
			$oneday . $log['agent'] 
		);

		// Run and update count if successfull
		if ( $wpdb->query( $sql ) && 'success' === $log['status'] ) {
			$count = get_post_meta( $log['post_id'], '_dedo_file_count', true );
			update_post_meta( $log['post_id'], '_dedo_file_count', ++$count );
			// log and increase another counter if oneday pass download
			if ( $oneday == 'ONEDAYPASS | ' ) {
				$scount = get_post_meta( $log['post_id'], '_dedo_oneday_count', true );
				update_post_meta( $log['post_id'], '_dedo_oneday_count', ++$scount );
			}
		}
		// Hook after log
		do_action( 'ddownload_insert_log_after', $log );
	}

	/**
	 * Role Check
	 *
	 * Are we logging events for this user role?
	 *
	 * @access public
	 * @since 1.4
	 * @return boolean
	 */
	public function role_check( $log ) {
		global $dedo_options;
		
		if ( current_user_can( 'administrator' ) && !$dedo_options['log_admin_downloads'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Grace Period
	 *
	 * Has a log of the same type been logged recently?
	 *
	 * @access public
	 * @since 1.4
	 * @return boolean
	 */
	public function grace_period( $log ) {
		global $wpdb, $dedo_options;

		if ( ! $this->table_exists() ) {
			return false;
		}

		if ( $dedo_options['grace_period'] == 1 ) {
			// Check for recent log of same download and status within grace period
			$sql = $wpdb->prepare( "
				SELECT ID FROM $wpdb->ddownload_statistics
				WHERE status = %s
					AND post_id = %d
					AND date > DATE_SUB(%s, INTERVAL %d MINUTE)
					AND user_ip = %s
			",
			$log['status'],
			$log['post_id'],
			$log['date'],
			$dedo_options['grace_period_duration'],
			$this->prepare_ip_address( $log['ip_address'] ) );

			if ( $wpdb->query( $sql ) ) {
				// We have a grace period
				return true;
			}
		}

		return false;
	}

	/**
	 *
	 * @param $ip_address
	 *
	 * @return string
	 */
	/**
	 * Check whether the statistics table exists.
	 *
	 * @access public
	 * @since 1.4
	 * @return bool
	 */
	public function table_exists() {
		global $wpdb;

		$wpdb->ddownload_statistics = $wpdb->prefix . 'ddownload_statistics';
		$table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->ddownload_statistics ) );

		return ( $table === $wpdb->ddownload_statistics );
	}

	/**
	 * Create the statistics table when needed.
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function setup_table() {
		global $wpdb;

		$wpdb->ddownload_statistics = $wpdb->prefix . 'ddownload_statistics';

		$sql = "
			CREATE TABLE {$wpdb->ddownload_statistics} (
				ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				status varchar(10) NOT NULL DEFAULT 'success',
				date datetime NOT NULL,
				post_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT '0',
				user_ip varbinary(16) NOT NULL,
				user_agent varchar(255) NOT NULL,
			PRIMARY KEY  (ID)
			) DEFAULT CHARSET={$wpdb->charset};
		";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function prepare_ip_address( $ip_address ) {
		// PHP versions prior to 5.3.0 on Windows did not support the inet_pton function
		if ( ! function_exists( 'inet_pton' ) ) {
			return '';
		}

		// Some servers pass an IP range, exploding suppresses PHP Warning
		$ip_address = explode( ',', $ip_address );

		return inet_pton( $ip_address[0] );
	}

}

// Initiate the logging system
$GLOBALS['dedo_logging'] = new DEDO_Logging();

/* ===== END includes/class-dedo-logging.php ===== */

/* ===== BEGIN includes/class-dedo-statistics.php ===== */
/**
 * Statistics Class
 *
 * @package  	Delightful Downloads
 * @author   	Ashley Rich
 * @copyright   Copyright (c) 2014, Ashley Rich
 * @since    	1.4
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class DEDO_Statistics {

	/**
	 *	Init Statistics
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function __construct() {

		global $wpdb;

		// Add custom table to wpdb.
		$wpdb->ddownload_statistics = $wpdb->prefix . 'ddownload_statistics';
	}

	/**
	 * Get Logs
	 *
	 * Get logs, allows date range, type and limit to be specified.
	 *
	 * @access public
	 * @since 1.5
	 * @return string
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		extract( wp_parse_args( $args, array(
			'status'	=> false,	
			'start'		=> false,
			'end'		=> false,
			'limit'		=> false
		) ) ) ;

		$sql = "SELECT * FROM $wpdb->ddownload_statistics";

		// Add where clause
		if ( $status ) {
			$sql .= $wpdb->prepare( " WHERE status = %s", $status );
		}
		else {
			$sql .= $wpdb->prepare( " WHERE 1 = %d", 1 );
		}

		// Start date
		if ( $start ) {
			$sql .= $wpdb->prepare( " AND date >= %s", $start );
		}

		// End date
		if ( $end ) {
			$sql .= $wpdb->prepare( " AND date <= %s", $end );
		}

		// Limit
		if ( $limit ) {
			$sql .= $wpdb->prepare( " LIMIT %d", $limit );
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Count Downloads
	 *
	 * Count total downloads for all/single downloads/download. If a date range is set
	 * the statistics table is used. If not, the meta keys are used.
	 *
	 * Data is cached in transients.
	 *
	 * @access public
	 * @since 1.4
	 * @return string
	 */
	public function count_downloads( $args = array() ) {

		global $wpdb;

		// Parse arguments with defaults
		extract( wp_parse_args( $args, array(
			'days'			=> false,
			'download_id'	=> false,
			'cache'			=> true
		) ) );

		// Create cache key
		$key = 'dedo_count_days' . absint( $days ) . 'id' . absint( $download_id );

		$dedo_cache = new DEDO_Cache( $key );

		// Check for cached data
		if ( true == $cache && false !== ( $cached_data = $dedo_cache->get() ) ) {

			return $cached_data;
		}

		// Days set, convert to start date and pass to count_logs
		if ( $days ) {

			$start_date = $this->convert_days_date( $days );
			
			$result = $this->count_logs( array( 
				'download_id' => $download_id, 
				'start_date' => $start_date, 
				'status' => 'success' 
			) );
		}
		// No days set, sum up file count meta_value
		else {

			// Set query
			$sql = $wpdb->prepare( "
				SELECT SUM(meta_value)
				FROM $wpdb->postmeta
				WHERE meta_key = %s
			",
			'_dedo_file_count' );

			// Append download id
			if ( $download_id ) {

				$sql .= $wpdb->prepare( " AND post_id = %d", $download_id );
			}

			$result = $wpdb->get_var( $sql );
		}

		// Save to cache
		if ( true == $cache ) {
			
			$dedo_cache->set( $result );
		}

		return ( $result === NULL ) ? 0 : $result;
	}

	/**
	 * Count Logs
	 *
	 * Count logs from statistics table.
	 *
	 * @access public
	 * @since 1.4
	 * @return string
	 */
	public function count_logs( $args = array() ) {

		global $wpdb;

		// Parse arguments with defaults
		extract( wp_parse_args( $args, array(
			'download_id'	=> false,
			'start_date'	=> false,
			'end_date'		=> false,
			'status'		=> false
		) ) );

		// Set main SQL query
		$sql = "
			SELECT COUNT(ID)
			FROM $wpdb->ddownload_statistics
		";

		// Append where clause for status
		if ( $status ) {

			$sql .= $wpdb->prepare( " WHERE status = %s", $status );
		}
		else {

			$sql .= $wpdb->prepare( " WHERE 1 = %d", 1 );
		}

		// Append download id
		if ( $download_id ) {

			$sql .= $wpdb->prepare( " AND post_id = %d", $download_id );
		}

		// Append start date
		if ( $start_date ) {

			$sql .= $wpdb->prepare( " AND date >= %s", $start_date );
		}

		// Append end date
		if ( $end_date ) {

			$sql .= $wpdb->prepare( " AND date <= %s", $end_date );
		}

		$result = $wpdb->get_var( $sql );

		return ( $result === NULL ) ? 0 : $result;
	}

	/**
	 * Get Popular Downloads
	 *
	 * Get popular downloads and order by download count.
	 * If days supplied use statistics table, else use download meta.
	 *
	 * Selecting by days is slow. Use responsibly!
	 * 
	 * @access public
	 * @since 1.4
	 * @return array
	 */
	function get_popular_downloads( $args = array() ) {

		global $wpdb;

		// Parse arguments with defaults
		extract( wp_parse_args( $args, array(
			'days'		=> false,
			'limit'		=> 5,
			'cache'		=> true
		) ) );

		// Create cache key
		$key = 'dedo_popular_days' . absint( $days ) . 'limit' . absint( $limit );

		$dedo_cache = new DEDO_Cache( $key );

		// Check for cached data
		if ( true == $cache && false !== ( $cached_data = $dedo_cache->get() ) ) {

			return $cached_data;
		}

		// Days set, convet to start date and use statistics table
		if ( $days ) {

			$start_date = $this->convert_days_date( $days );

			$sql = $wpdb->prepare( "
				SELECT $wpdb->ddownload_statistics.post_id AS ID, COUNT( $wpdb->ddownload_statistics.ID ) AS downloads
				FROM $wpdb->ddownload_statistics
				WHERE $wpdb->ddownload_statistics.status = %s
					AND $wpdb->ddownload_statistics.date >= %s
				GROUP BY $wpdb->ddownload_statistics.post_id
				ORDER BY downloads DESC
				LIMIT %d
			",
			'success',
			$start_date,
			$limit );

			$result = $wpdb->get_results( $sql, ARRAY_A );

			// Get title for each download
			foreach ( $result as $key2 => $value ) {

				$result[$key2]['title'] = get_the_title( $result[$key2]['ID'] );
			}
		}
		// User meta_value file_count
		else {

			$sql = $wpdb->prepare( "
				SELECT $wpdb->posts.ID AS ID, $wpdb->posts.post_title AS title, $wpdb->postmeta.meta_value AS downloads
				FROM $wpdb->posts
				LEFT JOIN $wpdb->postmeta
					ON $wpdb->posts.ID = $wpdb->postmeta.post_id
				WHERE $wpdb->posts.post_type = %s
					AND $wpdb->posts.post_status = %s
					AND meta_key = %s
					AND meta_value > 0
				ORDER BY CAST( $wpdb->postmeta.meta_value AS unsigned ) DESC
				LIMIT %d
			",
			'dedo_download',
			'publish',
			'_dedo_file_count',
			$limit );

			$result = $wpdb->get_results( $sql, ARRAY_A );
		}

		// Save to cache
		if ( true == $cache ) {
			
			$dedo_cache->set( $result );
		}

		return $result;
	}

	/**
	 * Delete Logs
	 *
	 * Delete logs, oldest first.
	 *
	 * @access public
	 * @since 1.4
	 * @return string
	 */
	public function delete_logs( $args = array() ) {

		global $wpdb;

		// Parse arguments with defaults
		extract( wp_parse_args( $args, array(
			'start_date'	=> false,
			'end_date'		=> false,
			'limit'			=> false,
			'status'		=> false
		) ) );

		$sql = "
			DELETE FROM $wpdb->ddownload_statistics
			WHERE 1 = 1
		";

		// Append start date
		if ( $start_date ) {

			$sql .= $wpdb->prepare( " AND date > %s", $start_date );
		}

		// Append start date
		if ( $end_date ) {

			$sql .= $wpdb->prepare( " AND date < %s", $end_date );
		}

		// Append status
		if ( $status ) {

			$sql .= $wpdb->prepare( " AND status = %s", $status );
		}

		// Append orderby
		$sql .= " ORDER BY date ASC";

		// Append limit
		if ( $limit ) {

			$sql .= $wpdb->prepare( " LIMIT %d", $limit );
		}

		return $wpdb->query( $sql );
	}

	/**
	 * Convert Days Date
	 *
	 * Converts number of days into current date minus days.
	 *
	 * @access public
	 * @since 1.4
	 * @return string
	 */
	public function convert_days_date( $days ) {

		$now = current_time( 'timestamp' );
		$timestamp = strtotime( '-' . $days . ' days', $now );

		return date( 'Y-m-d H:i:s', $timestamp );
	}	

	/**
	 * Check whether statistics table exists.
	 *
	 * @access public
	 * @since 1.4
	 * @return bool
	 */
	public function table_exists() {
		global $wpdb;

		$table_name = isset( $wpdb->ddownload_statistics ) ? $wpdb->ddownload_statistics : $wpdb->prefix . 'ddownload_statistics';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return $found === $table_name;
	}

	/**
	 * Setup Statistics Table
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function setup_table() {
		
		global $wpdb;

		$sql = "
			CREATE TABLE $wpdb->ddownload_statistics (
				ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				status varchar(10) NOT NULL DEFAULT 'success',
				date datetime NOT NULL,
				post_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT '0',
				user_ip varbinary(16) NOT NULL,
				user_agent varchar(255) NOT NULL,
			PRIMARY KEY  (ID)
			) DEFAULT CHARSET=$wpdb->charset;
		";

		// Include our database function and run
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( $sql );
	}

	/**
	 * Empty Statistics Table
	 *
	 * @access public
	 * @since 1.4
	 * @return int/boolean (rows affected or false on error)
	 */
	public function empty_table() {
		
		global $wpdb;

		// Only admins allowed to empty table
		if ( !current_user_can( 'administrator' ) ) {
			return;
		}

		$sql = "TRUNCATE TABLE $wpdb->ddownload_statistics";

		return $wpdb->query( $sql );
	}

	/**
	 * Delete Statistics Table
	 *
	 * @access public
	 * @since 1.4
	 * @return int/boolean (rows affected or false on error)
	 */
	public function delete_table() {
		
		global $wpdb;

		// Only admins allowed to remove table
		if ( !current_user_can( 'administrator' ) ) {
			return;
		}

		$sql = "DROP TABLE IF EXISTS $wpdb->ddownload_statistics";

		return $wpdb->query( $sql );
	}	

}

// Initiate the logging system
$GLOBALS['dedo_statistics'] = new DEDO_Statistics();

/* ===== END includes/class-dedo-statistics.php ===== */

/* ===== BEGIN includes/cron.php ===== */
/**
 * Delightful Downloads Cron
 *
 * @package     Delightful Downloads
 * @subpackage  Includes/Cron
 * @since       1.3
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Register Cron Events
 *
 * @since  1.3
 */
function dedo_cron_register() {
	
	// Daily
	if ( !wp_next_scheduled( 'dedo_cron_daily' ) ) {
		wp_schedule_event( current_time( 'timestamp' ), 'daily', 'dedo_cron_daily' );
	}	

	// Weekly
	if ( !wp_next_scheduled( 'dedo_cron_weekly' ) ) {
		wp_schedule_event( current_time( 'timestamp' ), 'weekly', 'dedo_cron_weekly' );
	}	
}
add_action( 'admin_init', 'dedo_cron_register' );

/**
 * Daily Events
 *
 * @since  1.4
 */
function dedo_cron_daily() {

	global $dedo_options, $dedo_statistics;

	// Delete old logs
	if ( $dedo_options['auto_delete'] == 1 ) {

		$date = $dedo_statistics->convert_days_date( $dedo_options['auto_delete_duration'] );
		$limit = apply_filters( 'dedo_cron_delete_limit', 1000 );

		$dedo_statistics->delete_logs( array( 'end_date' => $date, 'limit' => $limit ) );
	}

}
add_action( 'dedo_cron_daily', 'dedo_cron_daily' );

/**
 * Weekly Events
 *
 * @since  1.3
 */
function dedo_cron_weekly() {
	// Run folder protection
	dedo_folder_protection();
}
add_action( 'dedo_cron_weekly', 'dedo_cron_weekly' );

/**
 * Add Cron Schedules
 *
 * @since  1.3
 */
function dedo_cron_schedules( $schedules ) {
	// Adds once weekly to the existing schedules.
 	$schedules['weekly'] = array(
 		'interval' => 604800,
 		'display' => __( 'Once Weekly' )
 	);

 	return $schedules;
}
add_filter( 'cron_schedules', 'dedo_cron_schedules' );

/* ===== END includes/cron.php ===== */

/* ===== BEGIN includes/functions.php ===== */
/**
 * Delightful Downloads Functions
 * @package     Delightful Downloads
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// Register own template for downloads
function dedo_template( $single_template ) {
    global $post;
	$wpxtheme = wp_get_theme(); // gets the current theme
	if ( 'Penguin' == $wpxtheme->name || 'Penguin' == $wpxtheme->parent_theme ) { $xpenguin = true;} else { $xpenguin=false; }
    if ( $post->post_type == 'dedo_download' ) {
        if ($xpenguin) { $single_template = dirname( __FILE__ ) . '/dedo-template-penguin.php';	} else {
			$single_template = dirname( __FILE__ ) . '/dedo-template.php';
		}
    }
    return $single_template;
}
add_filter( 'single_template', 'dedo_template' );

// k,M,G T formatieren bei großen Zahlen
function dedo_number_format_short( $n ) {
    return number_format_short( $n );
}

// colorize heat after given value and return volor value
function dedo_heatcolor($visithotness) {
	$heatmapcols = array('#A9D0F5','#A9e2f3','#a9f5f2','#b2ff99','#ccff99','#e5ff99','#ffff99','#ffe599','#ffee99','#ffcc99','#ffb299aa','#ff9999aa','#D0A9F5aa','#F5A9F2aa');
	if ( $visithotness <=3 ) $hotcolor = $heatmapcols[0];
	else if ( $visithotness <=100 ) $hotcolor = $heatmapcols[floor(floor($visithotness) / 10)];
	else if ( $visithotness <=150 ) $hotcolor = $heatmapcols[11];
	else if ( $visithotness <=200 ) $hotcolor = $heatmapcols[12];
	else if ( $visithotness >200 ) $hotcolor = $heatmapcols[13];
	return $hotcolor;	
}

// ----------------------------------- Funktionen, die in andere Plugins und themes gespiegelt sind ------------------------------------

// Converts a number into a short version, eg: 1000 -> 1k
//  gespiegelt in: delightful-downloads/includes/functions.php und foldergallery.php und wpdoodlez.php   (Aufruf als wpdoo/dedo_number_format_short)
if( !function_exists('number_format_short')) {
	function number_format_short( $n ) {
		if ( $n <= 0 ) return '<span title="0">0</span>';
		$si_prefix = array( '', 'K', 'M', 'G', 'T', 'E', 'Z', 'Y' );
		$base = 1024;
		$class = min((int)log($n , $base) , count($si_prefix) - 1);
		$short_value = $n / pow($base,$class);
		// $precis = 1, wenn Wert < 10 (einstellig) UND es ein Suffix gibt ($class > 0)
		$precis = ($short_value < 10 && $class > 0) ? 1 : 0;
		// Fügt das number_format_i18n nur hinzu, wenn es verfügbar ist
		$title = function_exists('number_format_i18n') ? number_format_i18n($n) : number_format($n, 0, ',', '.');
		return '<span title="'.$title.'">' . sprintf('%1.'.$precis.'f' , $short_value) . $si_prefix[$class] . '</span>';
	}
}

// Zeitdifferenz ermitteln und gestern/vorgestern/morgen schreiben
//   gespiegelt in: chartcodes.php, delightful-downloads/includes/functions.php, foldergallery.php, penguin/functions.php, timeclock/includes/functions.php
if( !function_exists('ago')) {
	function ago($timestamp) {
		if (empty($timestamp)) return;
		$xlang = get_bloginfo("language");
		date_default_timezone_set('Europe/Berlin');
		$now = time();
		if ($timestamp > $now) {
			$prepo = __('in', 'penguin');
			$postpo = '';
		} else {
			if ($xlang == 'de') {
				$prepo = 'vor';
				$postpo = '';
			} else {
				$prepo = '';
				$postpo = ' ' . __('ago', 'penguin');
			}
		}
		$her = date( 'd.m.Y', intval($timestamp) );
		if ($her == date('d.m.Y',$now - (24 * 3600))) {
			$hdate = __('yesterday', 'penguin');
		} else if ($her == date('d.m.Y',$now - (48 * 3600))) {
			$hdate = __('1 day before yesterday', 'penguin');
		} else if ($her == date('d.m.Y',$now + (24 * 3600))) {
			$hdate = __('tomorrow', 'penguin');
		} else if ($her == date('d.m.Y',$now + (48 * 3600))) {
			$hdate = __('1 day after tomorrow', 'penguin');
		} else {
			$hdate = $prepo . ' ' . human_time_diff(intval($timestamp), $now) . $postpo;
		}
		return $hdate;
	}
}	

// Datumbox farbig mit Wochenende SA gelb und SO rot ausgeben aus createdatum und moddatum. wird nur createdatum gesetzt, wird nur das ausgewertet.
//   gespiegelt in: chartcodes.php, delightful-downloads/includes/functions.php, foldergallery.php, penguin/functions.php
//   Parameter 1: Erstell-Unix-Timestamp | 2: Mod-Timestamp oder NULL=Erstell-Timestamp | 3: NULL=ICON anzeigen, 1=kein Icon | 4: NULL=nur Datum, 1=Datum und AGO, 2=nur AGO
//     test:     echo colordatebox( (time()-86400), NULL, NULL, 1);

	// SA orange, Sonntag rot, gestern hellgrün, heute cyan, 30T gelb, >30T grau
	if (!function_exists('getColorStyles')) {
		function getColorStyles($timestamp) {
			$days = (int)((strtotime(date('Y-m-d', $timestamp)) - strtotime(date('Y-m-d'))) / 86400);
			$bg = match (true) {
				$days === 0   => '#bfd', // heute
				$days === -1  => '#efe', // gestern
				$days < -30   => '#eee', // vergangen >30T
				$days < 0     => '#fe8', // vergangen 1–30T
				$days <= 30   => '#bdf', // zukünftig 1–30T
				default       => '#cef', // zukünftig >30T
			};
			$weekday = (int)date('N', $timestamp);
			$fg = match ($weekday) {
				6 => '#e60', // Samstag
				7 => '#f00', // Sonntag
				default => '#222',
			};
			return ['background' => $bg, 'color' => $fg];
		}
	}

if( !function_exists('colordatebox')) {
	function colordatebox($created, $modified = null, $noicon = null, $showago = null) {
		$modified = $modified ?? $created;
		// Tauschen bei Unix Filesystemen (falls modified < created)
		$unixfile = 0;
		if ($modified < $created) {
			[$created, $modified] = [$modified, $created];
			$unixfile = 1;
		}
		// Datum formatieren
		$erstelldat = str_replace(' 00:00', '', wp_date('D d. M Y H:i', $created));
		$moddat    = str_replace(' 00:00', '', wp_date('D d. M Y H:i', $modified));
		// "vor X" Strings
		$postago = ago($created);
		$modago  = ago($modified);
		// Zeitdifferenzen berechnen
		$diffmod  = $modified - $created;
		$refTime  = $unixfile ? $created : $modified;
		$diff     = time() - $refTime;
		$diffdays = floor($diff / 86400);
		// Tooltip zusammenbauen
		$erstelltitle = __("created", "penguin") . ': ' . $erstelldat . ' ' . $postago . ' ' . $diffdays . ' Tg';
		if ($diffmod !== 0) {
			$erstelltitle .= "\n" . __("modified", "penguin") . ': ' . $moddat . ' ' . $modago;
			$erstelltitle .= "\n" . __("modified after", "penguin") . ': ' . human_time_diff($created, $modified);
		}
		// Angezeigtes Datum & Icon bestimmen
		if ($diffmod > 0 && !$unixfile) {
			$newormod = '🕰️';
			if ($showago === 2) {
				$anzeigedat = $modago;
			} elseif ($showago === 1) {
				$anzeigedat = $moddat . ' ' . $modago;
			} else {
				$anzeigedat = $moddat;
			}
			$cstyles = getColorStyles($modified);
		} else {
			$newormod = '📅';
			if ($showago === 2) {
				$anzeigedat = $postago;
			} elseif ($showago === 1) {
				$anzeigedat = $erstelldat . ' ' . $postago;
			} else {
				$anzeigedat = $erstelldat;
			}
			$cstyles = getColorStyles($created);
		}
		// HTML-Ausgabe generieren
		$colordate = '<span class="newlabel" style="background-color:' . $cstyles['background'] . '">';
		if (!isset($noicon)) {
			$colordate .= $newormod;
		}
		$colordate .= '<span style="color:' . $cstyles['color'] . '" title="' . htmlspecialchars($erstelltitle, ENT_QUOTES) . '">' . $anzeigedat . '</span></span>';
		return $colordate;
	}
}


// ---------------------------------- Spiegelung Ende ------------------------------------------------------------------------


// Shortcode Styles
function dedo_get_shortcode_styles() {
	$styles = array(
	 	'infobox'		=> array(
	 		'name'			=> __( 'Infobox mit Icon, Rahmen und Details', 'delightful-downloads' ),
	 		'format'		=> '<blockquote class="%class% blockleer" style="font-size:inherit;display:flex;width:100%;padding:4px;border-radius:3px">
					<div style="display:flex;width:100%">
					<div style="display:inline-block;min-width:60px;width:60px">%icon%</div>
					<div style="display:inline-block;width:100%;min-width:70%">
					<div class="entry-meta-top">
					<div class="iconleiste noprint"> %locked% &nbsp; %adminedit%%datesymbol%%filesize%%downloadtime%%count%</div> 
					<div class="greybox">%category% %tags%</div></div>
					<h6 style="margin-top:6px"><a href="%permalink%" title="'.__( 'download details', 'delightful-downloads' ).'" rel="nofollow">
					%title%</a></h6>
					<a class="ddownload-button page-numbers"  href="%url%" title="'.__( 'download file', 'delightful-downloads' ).'" rel="nofollow">'.__( 'download file', 'delightful-downloads' ).'</a>
					 %filedate%
					<div>%description%</div></div>%thumb%</div></blockquote>'
	 	),
	 	'singlepost'		=> array(
	 		'name'			=> __( 'Infobox mit Icon, Rahmen für Post Archive', 'delightful-downloads' ),
	 		'format'		=> '<blockquote class="%class% blockleer" style="font-size:inherit;display:flex;width:100%;padding:4px;border-radius:3px">
					<div style="display:inline-block;min-width:60px;width:60px">%icon%</div>
					<div style="display:inline-block;width:100%;min-width:70%">
					<a class="ddownload-button page-numbers"  href="%url%" title="'.__( 'download file', 'delightful-downloads' ).'" rel="nofollow">
					'.__( 'download file', 'delightful-downloads' ).'</a>
					<table>
					<tr><td style="width:25%">'.__( 'Locked admin Onedaypass', 'delightful-downloads' ).'</td><td>%locked% &nbsp; %adminedit%</td></tr>
					<tr><td>'.__( 'filename', 'delightful-downloads' ).'</td><td>%filename%</td></tr>
					<tr><td>'.__( 'file size', 'delightful-downloads' ).'</td><td>%filesize%</td></tr>
					<tr><td>'.__( 'file date', 'delightful-downloads' ).'</td><td>%filedate%</td></tr>
					<tr><td>'.__( 'download time', 'delightful-downloads' ).'</td><td>%downloadtime%</td></tr>
					<tr><td>'.__( 'download count', 'delightful-downloads' ).'</td><td>%count%</td></tr>
					<tr><td colspan=2>%id3tag%</td></tr>
					</table>
					%manexcerpt%
					</div></blockquote>'
	 	),
	 	'button'		=> array(
	 		'name'			=> __( 'Button', 'delightful-downloads' ),
	 		'format'		=> '<a href="%url%" title="%text%" rel="nofollow" class="%class%">%text%</a>'
	 	),
	 	'link'			=> array(
	 		'name'			=> __( 'Link', 'delightful-downloads' ),
	 		'format'		=> '<a href="%url%" title="%text%" rel="nofollow" class="%class%">%text%</a>'
	 	),
	 	'iconlink'			=> array(
	 		'name'			=> __( 'Icon und Link', 'delightful-downloads' ),
	 		'format'		=> '%icon% &nbsp; <a href="%url%" title="%text%" rel="nofollow" class="%class%">%text%</a>'
	 	),
	 	'plain_text'	=> array(
	 		'name'			=> __( 'Plain Text', 'delightful-downloads' ),
	 		'format'		=> '%url%'
	 	)
	);
	return apply_filters( 'dedo_get_styles', $styles );
}

/**
 * Returns List Styles
 */
function dedo_get_shortcode_lists() {
	$lists = array(
	 	'title'				=> array(
	 		'name'				=> __( 'Title', 'delightful-downloads' ),
	 		'format'			=> '<a href="%url%" title="%title%" rel="nofollow" class="%class%">%title%</a>'
	 	),
	 	'title_date'		=> array(
	 		'name'				=> __( 'Title/Date)', 'delightful-downloads' ),
	 		'format'			=> '<a href="%url%" title="%title%" rel="nofollow" class="%class%">%title% (%datesymbol%)</a>'
	 	),
	 	'title_count'		=> array(
	 		'name'				=> __( 'Title/Count', 'delightful-downloads' ),
	 		'format'			=> '<a style="margin-left:30px" href="%url%" title="%title%" rel="nofollow" class="%class%">%title%</a> &nbsp; %count%'
	 	),
	 	'title_filesize'	=> array(
	 		'name'				=> __( 'Title/Filesize', 'delightful-downloads' ),
	 		'format'			=> '<a style="margin-left:30px" href="%url%" title="%title%" rel="nofollow" class="%class%">%title%</a> &nbsp; %filesize%'
	 	),
	 	'title_ext_filesize'=> array(
	 		'name'				=> __( 'Title/Extension/Filesize', 'delightful-downloads' ),
	 		'format'			=> '<a style="margin-left:30px" href="%url%" title="%title%" rel="nofollow" class="%class%">%title%</a> &nbsp; %ext% &nbsp; %filesize%'
	 	),
	 	'title_date_ext_filesize'=> array(
	 		'name'				=> __( 'Title/Date/Extension/Filesize', 'delightful-downloads' ),
	 		'format'			=> '<a style="margin-left:30px" href="%url%" title="%title%" rel="nofollow" class="%class%">%title%</a> &nbsp; %datesymbol% &nbsp; %ext% &nbsp; %filesize%'
	 	),
	 	'title_ext_filesize_count'=> array(
	 		'name'				=> __( 'Title/Date/Extension/Filesize/count', 'delightful-downloads' ),
	 		'format'			=> '<a style="margin-left:30px" href="%url%" title="%title%" rel="nofollow" class="%class%">%title%</a> &nbsp; %datesymbol% &nbsp; %ext% &nbsp; %filesize% &nbsp; %count%'
	 	),
	 	'icon_title_ext_filesize'=> array(
	 		'name'				=> __( 'Title/Icon/Category/File size', 'delightful-downloads' ),
	 		'format'			=> '<div style="display:flex;width:100%">
					<div style="display:inline-block;min-width:60px;width:60px">%icon%</div>
					<div style="display:inline-block;width:100%;min-width:70%">
					<a class="headline" href="%url%" title="'.__( 'download file', 'delightful-downloads' ).'" rel="nofollow">
					 %title%</a><br>%adminedit%
					 &nbsp;%locked% &nbsp;%category% %tags% &nbsp;
					%filesize%</div></div>'
	 	),
	 	'icon_title_ext_filesize_count_datesymbol'=> array(
	 		'name'				=> __( 'Title/Icon/Category/File size/Count/Dateago)', 'delightful-downloads' ),
	 		'format'			=> '<div style="display:flex;width:100%">
					<div style="display:inline-block;min-width:55px;width:55px">%icon%</div>
					<div style="display:inline-block;width:100%;min-width:70%;vertical-align:top;line-height:1.35em">
					<div class="entry-meta-top"><div class="iconleiste noprint">%locked% &nbsp; %adminedit%
					%dateago%%filesize%%count%</div><div class="greybox">%category% %tags%</div></div>
					<h6 style="margin-top:4px"><a href="%url%" title="'.__( 'download file', 'delightful-downloads' ).'" rel="nofollow">
					📥 %title%</a></h6>
					</div></div>'
	 	),
	 	'infoboxlist'=> array(
	 		'name'				=> __( 'Infoboxliste (Icon/Date/Extension/Filesize/count/Thumb/descript)', 'delightful-downloads' ),
	 		'format'			=> '
					<div style="display:flex;width:100%">
					<div style="display:inline-block;min-width:55px;width:55px">%icon%</div>
					<div style="display:inline-block;width:100%;min-width:70%">
					<div class="entry-meta-top"><div class="iconleiste noprint">%locked% &nbsp; %adminedit% %datesymbol%</div>
					<div class="greybox">%category% %tags%</div></div>
					<h6 style="margin-top:4px"><a href="%url%" title="'.__( 'download file', 'delightful-downloads' ).'" rel="nofollow">
					📥 %title%</a></h6>
					<div>%filename%%filedate%%filesize%%count%%downloadtime%<br>%description%</div>
					</div>%thumb%</div>%id3tag%'
	 	)
	);
	return apply_filters( 'dedo_get_lists', $lists );
}

/**
 * Shortcode Buttons
 */
function dedo_get_shortcode_buttons() {
	
	$buttons =  array(
		'accent'		=> array(
			'name'		=> __( 'theme accent', 'delightful-downloads' ),
			'class'		=> 'page-numbers'
		),
		'black'		=> array(
			'name'		=> __( 'Black', 'delightful-downloads' ),
			'class'		=> 'button-black'
		),
		'grey'		=> array(
			'name'		=> __( 'Grey', 'delightful-downloads' ),
			'class'		=> 'button-grey'
		),
		'green'		=> array(
			'name'		=> __( 'Green', 'delightful-downloads' ),
			'class'		=> 'button-green'
		),
		'red'		=> array(
			'name'		=> __( 'Red', 'delightful-downloads' ),
			'class'		=> 'button-red'
		),
	);
	return apply_filters( 'dedo_get_buttons', $buttons );
}


// Get download-time for typical internet lines
function download_times($filesize) {
	$bbreite = array (25,50,100,200,300,500,1000,16);
	$outp = array();
	foreach ($bbreite as $value) {
		$time16 = floor($filesize * 8 / ($value*1024*1024));
		$s = floor($time16%60);
		$m = floor(($time16%3600)/60);
		$h = floor(($time16%86400)/3600);
		$outp[] = ($h>0 ? $h.'h ' :'').($m>0 ? $m.'m ' :'').$s.'s@'.$value.'MBit';
	}	
	if ($s > 0) $s=1;
	$dtime = '<a class="newlabel white" title="'.implode("\n", $outp).'">🕐 '.$outp[4].'</a>';
	return $dtime;
}

// Replace Wildcards
 function dedo_search_replace_wildcards( $string, $id ) {
	global $wpdb;
 	//adminedit
 	if ( strpos( $string, '%adminedit%' ) !== false ) {
 		if(current_user_can('administrator')) {
			$datetime = new DateTime('now');
			$hashwert = md5( intval($id) + intval($datetime->format('Ymd')) );
			if (is_singular() && in_the_loop() ) {
				$oneday = '<input type="text" title="Copy '.$datetime->format('d.m.Y').' Onedaypass für heute&#10;'.$hashwert.'" class="copy-to-clipboard" style="direction:rtl;cursor:pointer;font-size:0.7em;width:80px;height:17px;margin-top:0" value="' . get_site_url() . '?sdownload=' . esc_attr( $id ) .  '&code='. $hashwert . '" readonly> &nbsp;';
				$oneday .= '<p class="newlabel" style="background-color:#fe8;display:none">' . __( 'One day pass copied to clipboard.', 'delightful-downloads' ) . '</p>';
			} else $oneday='';
			$string = str_replace( '%adminedit%', ' <a href="'. get_home_url() . '/wp-admin/post.php?post='.$id.'&action=edit"><span title="'. __( 'edit this download', 'delightful-downloads' ) . '">✏️</span></a> &nbsp; '.$oneday, $string );
		} else {
			$string = str_replace( '%adminedit%', '', $string );
		}
 	}
	// id
 	if ( strpos( $string, '%id%' ) !== false ) {
 		$string = str_replace( '%id%', $id, $string );
 	}
 	// url
 	if ( strpos( $string, '%url%' ) !== false ) {
 		$value = dedo_download_link( $id );
 		$string = str_replace( '%url%', $value, $string );
 	}
 	// title
 	if ( strpos( $string, '%title%' ) !== false ) {
 		$value = get_the_title( $id );
 		$string = str_replace( '%title%', $value, $string );
 	}
 	// Kategorie (erste)
 	if ( strpos( $string, '%category%' ) !== false ) {
		$post_terms = get_the_terms( $id, 'ddownload_category' );
		if (!empty($post_terms)) $value = '<span title="category">📂</span> <a href="'.get_term_link($post_terms[0]->slug,'ddownload_category').'">' . $post_terms[0]->name .'</a> &nbsp; '; else $value='';
		$string = str_replace( '%category%', $value, $string );
 	}
 	// Tags
 	if ( strpos( $string, '%tags%' ) !== false ) {
		$value = '';
		$post_terms = get_the_terms( $id, 'ddownload_tag' );
		if ($post_terms && !is_wp_error($post_terms)) {
			$value .='<span title="Themen">🏷️</span> ';
			foreach ($post_terms as $term) {
				$value .= '<a href="'.esc_attr( get_tag_link( $term->term_id ) ).'">'.$term->name . '</a> ';
			}
		}
		$string = str_replace( '%tags%', $value, $string );
 	}
 	// permalink single cpost
 	if ( strpos( $string, '%permalink%' ) !== false ) {
		$value = get_the_permalink($id);
 		$string = str_replace( '%permalink%', $value, $string );
 	}
 	// manual excerpt
 	if ( strpos( $string, '%manexcerpt%' ) !== false ) {
		if ( post_password_required( $id) ) {
			global $post;
			$post = get_post ( $id );
			$value = $post->post_excerpt;
		} else if (has_excerpt( $id )) $value = get_the_excerpt( $id );   // if manual excerpt exists
		else $value='';
 		$string = str_replace( '%manexcerpt%', $value, $string );
 	}
	 
	// Wenn MP3, dann ID3-Infos ausgeben
 	if ( strpos( $string, '%id3tag%' ) !== false ) {
		$mime_type = dedo_get_file_mime( get_post_meta( $id, '_dedo_file_url', true ) );
		if ($mime_type == 'audio/mpeg') {
			$mp3url = get_post_meta( $id, '_dedo_file_url', true );
			$mp3path = dedo_get_abs_path( $mp3url );
			$mp3filename = dedo_get_file_name( get_post_meta( $id, '_dedo_file_url', true ) );
			// Musicplayer
			$musifile = $mp3path;
			if (file_exists($musifile)) {
				require_once( ABSPATH . 'wp-admin/includes/media.php' );
				$musiurl = $mp3url;
				$filename = basename($musiurl);
				$meta = wp_read_audio_metadata( $musifile );
				$phtml = '<div class="timeline" style="border:1px dashed #ccc;display:grid;grid-template-columns:5fr 96px"><div>';
				// Player nur, wenn nicht password protected
				if ( !post_password_required( $id) ) $phtml .= '<audio class="noprint" controlsList="nodownload" style="width:100%" controls src="'.$musiurl.'" preload="metadata"></audio>';
				$phtml .='<span style="font-size:.9em;font-style:italic">';
				// Metadata Ausgabe auch für penguin podcasts template und für audio template --------------
				if (isset($meta['title'])) $phtml .= '🎫 <b>'.$meta['title'].'</b>'; // fa-ticket -> 🎫 (Eintrittskarte)
				if (isset($meta['artist'])) $phtml .= ' <span style="margin-left:1em">👤</span> '.$meta['artist']; // fa-user -> 👤 (Silhouette/Person)
				if (isset($meta['album'])) $phtml .= ' <span style="margin-left:1em">💿</span> ' . $meta['album']; // fa-book -> 💿 (CD/Album)
				if (isset($meta['track_number'])) $phtml .= ' <span style="margin-left:1em">#️⃣</span> ' . $meta['track_number']; // fa-hashtag -> #️⃣ (Hashtag/Nummer)
				if (isset($meta['year'])) $phtml .= ' <span style="margin-left:1em">🗓️</span> '.$meta['year']; // fa-calendar-check-o -> 🗓️ (Kalender)
				if (isset($meta['genre'])) $phtml .= ' <span style="margin-left:1em">🎼</span> ' . $meta['genre']; // fa-cubes -> 🎼 (Musikalische Noten)
				if (isset($meta['length_formatted'])) $phtml .= ' <span style="margin-left:1em">⏱️</span> ' . $meta['length_formatted']; // fa-clock-o -> ⏱️ (Stoppuhr)
				if (isset($meta['composer'])) $phtml .= ' <span style="margin-left:1em">🎶</span> ' . $meta['composer']; // fa-music -> 🎶 (Musikanimation)
				if (isset($meta['band'])) $phtml .= ' <span style="margin-left:1em">👥</span> ' . $meta['band']; // fa-users -> 👥 (Silhouetten/Gruppe)
				if (isset($meta['filesize'])) $phtml .= ' <span style="margin-left:1em">🗜️</span> ' . number_format_short($meta['filesize']); // fa-expand -> 🗜️ (Klammer/Größe/Dateigröße)
				if (isset($meta['part_of_a_set'])) $phtml .= ' <span style="margin-left:1em">⏺️</span> ' . $meta['part_of_a_set']; // fa-circle-thin -> ⏺️ (Aufnahme-Taste/Disc)
				if (isset($meta['encoder_options'])) $phtml .= ' <span style="margin-left:1em">⚙️</span> ' . $meta['encoder_options']; // fa-compress -> ⚙️ (Zahnrad/Einstellungen/Encoding)
				if (isset($meta['channelmode'])) $phtml .= ' <span style="margin-left:1em">🎙️</span> ' . $meta['channelmode']; // fa-microphone -> 🎙️ (Mikrofon/Audio)
				if (isset($meta['publisher'])) $phtml .= ' <span style="margin-left:1em">📰</span> ' . $meta['publisher']; // fa-newspaper-o -> 📰 (Zeitung/Herausgeber)
				if (isset($meta['comment'])) $phtml .= ' <span style="margin-left:1em">💬</span> ' . $meta['comment']; // fa-comments -> 💬 (Sprechblase/Kommentar)
				// $phtml .= ' <span style="margin-left:1em">🔉</span> '.basename($filename); // Alternativ für fa-file-audio-o
				if ( !post_password_required( $id) && !empty(@$meta['unsynchronised_lyric'])) $phtml .= ' <span style="margin-left:1em">📜</span> ' . $meta['unsynchronised_lyric']; // fa-text-width -> 📜 (Schriftrolle/Text)
				$phtml .= '</span></div><div>';
				if (!empty($meta['image']['data'])) $phtml .= '<img class="img-zoom" style="width:96px" src="data:'.$meta['image']['mime'].';charset=utf-8;base64,'.base64_encode($meta['image']['data']).'">';				$phtml .= '</div></div>';
			} else $phtml = '';		
			$value = $phtml;
		} else $value='';	
		$string = str_replace( '%id3tag%', $value, $string );
	}
	
	// beschreibung
 	if ( strpos( $string, '%description%' ) !== false ) {
		if ( post_password_required( $id) ) {
			global $post;
			$post = get_post ( $id );
			$value = $post->post_excerpt;
		} else $value = get_the_excerpt( $id );
 		$string = str_replace( '%description%', $value, $string );
 	}
	// post thumbnail - Beitragsbild mit img-zoom on hover
 	if ( strpos( $string, '%thumb%' ) !== false ) {
 		$value = '<div style="float:right;padding-top:0;display:inline-block;max-width:200px;border:1px none"><img class="img-zoom" style="min-height:100px;transform-origin: center right" src="' . get_the_post_thumbnail_url( $id ) . '"></div>';
 		$string = str_replace( '%thumb%', $value, $string );
 	}
 	// file-date created modified und postdatum ändern, wenn Datei per sftp neuer im Dateisystem
 	if ( strpos( $string, '%filedate%' ) !== false ) {
 		if (!empty( get_post_meta( $id, '_dedo_file_url', true ) )) {
			$fpath = dedo_get_abs_path(get_post_meta( $id, '_dedo_file_url', true));
			$value = colordatebox( filectime($fpath), filemtime($fpath) ,NULL,1);
			// Post modified Datum aktualisieren, wenn File - Anhang neuer
			if (filemtime($fpath) > get_the_modified_time('U', false, $id, true) - get_the_modified_time('Z') ) {
				$mysql_time_format= "Y-m-d H:i:s";
				$post_modified = wp_date( $mysql_time_format, filemtime($fpath) );
				$post_modified_gmt = gmdate( $mysql_time_format, ( filemtime($fpath) + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )  );
				$wpdb->query("UPDATE $wpdb->posts SET post_modified = '{$post_modified}', post_modified_gmt = '{$post_modified_gmt}'  WHERE ID = {$id}" );
			}
		} else { $value='';  }	
		$string = str_replace( '%filedate%', $value, $string );
 	}
	// datesymbol Datum, farbig mit symbol und allen created und mod date.
 	if ( strpos( $string, '%datesymbol%' ) !== false ) {
		$erstelldat = get_post_time('U', false, $id, true) - get_post_time('Z');
		$moddat = get_the_modified_time('U', false, $id, true) - get_the_modified_time('Z');
		$value = colordatebox( $erstelldat, $moddat, NULL, 1);
		$string = str_replace( '%datesymbol%', $value, $string );
 	}
	// dateago   - so viele Tage wochen her, sonntags rot, samstags orange
 	if ( strpos( $string, '%dateago%' ) !== false ) {
		$erstelldat = get_post_time('U', false, $id, true) - get_post_time('Z');
		$moddat = get_the_modified_time('U', false, $id, true) - get_the_modified_time('Z');
		$value = colordatebox( $erstelldat, $moddat, NULL, 2);
		$string = str_replace( '%dateago%', $value, $string );
 	}
 	// filesize
 	if ( strpos( $string, '%filesize%' ) !== false ) {
		$fpath = dedo_get_abs_path(get_post_meta( $id, '_dedo_file_url', true));
		$fsfrommeta = size_format( get_post_meta( $id, '_dedo_file_size', true ), 0 );
		$fsfromfile = size_format( filesize( $fpath ) );
		if (!empty( get_post_meta( $id, '_dedo_file_size', true ) )) {
			$value = '<span class="newlabel white"><span title="filesize: '.$fsfrommeta.'">💾</span>'.$fsfromfile.'</span>';
		} else { $value='';  }	
		$string = str_replace( '%filesize%', $value, $string );
 	}
 	// downloadtime
 	if ( strpos( $string, '%downloadtime%' ) !== false ) {
 		$value = download_times(intval(get_post_meta( $id, '_dedo_file_size', true )));
 		$string = str_replace( '%downloadtime%', $value, $string );
 	}
 	// downloads (count)
 	if ( strpos( $string, '%count%' ) !== false ) {
		$filedlc = get_post_meta( $id, '_dedo_file_count', true );
		$totaldownloads = dedo_total_downloads();
		if ($totaldownloads > 0) {
			$perctotal = round( (int) $filedlc / $totaldownloads * 100) ;
			// Wenn heatcolor aus penguin_mod vorhanden
			$hotcolor = dedo_heatcolor($perctotal);
		} else { $perctotal = 0; $hotcolor = '#fff'; }
		$fullcounter = number_format_i18n( $filedlc );
		$shortcounter = dedo_number_format_short($filedlc);
 		$value = '<span title="DLCounter: '.$fullcounter.' Ranking: '.$perctotal.'%" class="newlabel" style="background-color:'.$hotcolor.'" >📥 ' . $shortcounter .'</span>';
 		$string = str_replace( '%count%', $value, $string );
 	}
 	// file name
 	if ( strpos( $string, '%filename%' ) !== false ) {
 		$value = '<span title="Dateiname" class="newlabel white">📃 ' . dedo_get_file_name( get_post_meta( $id, '_dedo_file_url', true ) ).'</span>';
 		$string = str_replace( '%filename%', $value, $string );
 	}
 	// protected file
 	if ( strpos( $string, '%locked%' ) !== false ) {
 		if (post_password_required($id)) {
			$value='<span style="font-size:1.1em;color:tomato" title="Kennwortgeschützt">🔐︎</span>';
		} else {
			$value='<span style="font-size:1.1em" title="öffentlich">🔓</span>';
		}
 		$string = str_replace( '%locked%', $value, $string );
 	}
 	// file extension
 	if ( strpos( $string, '%ext%' ) !== false ) {
 		$value = '<span title="filename">📑</span> '.strtoupper( dedo_get_file_ext( get_post_meta( $id, '_dedo_file_url', true ) ) );
 		$string = str_replace( '%ext%', $value, $string );
 	}
  	// file icon
 	if ( strpos( $string, '%icon%' ) !== false ) {
 		$ffile = ( get_post_meta( $id, '_dedo_file_url', true ) );
		$value = ' <a href="'.get_the_permalink($id).'">'.dedo_get_file_icon( $ffile ).'</a>';
 		$string = str_replace( '%icon%', $value, $string );
 	}
 	// file mime
 	if ( strpos( $string, '%mime%' ) !== false ) {
 		$value = dedo_get_file_mime( get_post_meta( $id, '_dedo_file_url', true ) );
 		$string = str_replace( '%mime%', $value, $string );
 	}
 	return apply_filters( 'dedo_search_replace_wildcards', $string, $id );
 }

/**
 * Download Link * Generate download link based on provided id.
 */
function dedo_download_link( $id ) {
	global $dedo_options;
	$output = esc_html( home_url( '?' . $dedo_options['download_url'] . '=' . $id ) );
	return apply_filters( 'dedo_download_link', $output );
}

// Check for valid download
function dedo_download_valid( $download_id ) {
	$download_id = absint( $download_id );

	if ( $download = get_post( $download_id, ARRAY_A ) ) {
		
		if ( $download['post_type'] == 'dedo_download' && $download['post_status'] == 'publish' ) {
			return true;
		}
	}
	return false;
}

/**
 * Check user has permission to download file
 */
function dedo_download_permission( $options ) {
	global $dedo_options;
	// First check per-download settings, else revert to global setting
	$members_only = ( isset( $options['members_only'] ) ) ? $options['members_only'] : $dedo_options['members_only'];
	if ( $members_only ) {
		// Check user is logged in
		if ( is_user_logged_in() ) {
			return true;
		}
		else {
			return false;
		}
	}
	return true;
}

/**
 * Check if user is blocked
 */
function dedo_download_blocked( $current_agent ) {
	// Retrieve user agents
	$user_agents = dedo_get_agents();
	if ( ! $user_agents ) {
		return true;
	}
	foreach ( $user_agents as $user_agent ) {
		$current_agent = trim( strtolower( $current_agent ) );
		$user_agent    = trim( strtolower( $user_agent ) );

		if ( empty( $current_agent ) || empty( $user_agent ) ) {
			return true;
		}

		if ( false !== strpos( $current_agent, $user_agent ) ) {
			return false;
		}	
	}
	return true;
}

/**
 * Get blocked user agents
 */
function dedo_get_agents() {
	global $dedo_options;
	$crawlers = $dedo_options['block_agents'];
	if ( empty( $crawlers ) ) {
		return array();
	}
	$crawlers = explode( "\n", $crawlers );
	return $crawlers;
}

/**
 * Get users IP Address
 */
function dedo_download_ip() {
	if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		$ip_address = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
	} 
	elseif ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$ip_address = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	} 
	else {
		$ip_address = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
	}
	// letzte Stelle der IP anonymisieren (0 setzen)	
	// $ip_address = long2ip(ip2long($ip_address) & 0xFFFFFF00);
	return $ip_address;
}

/**
 * Get file mime type based on file extension
 */
function dedo_download_mime( $path ) {
	// Strip path, leave filename and extension
	$file = explode( '/', $path );
	$file = strtolower( end( $file ) );
	$filetype = wp_check_filetype( $file );	
	return $filetype['type'];
}

/**
 * Return various upload dirs/urls for Delightful Downloads.
 * @param string $return
 * @param string $upload_dir
 * @return string
 */
function dedo_get_upload_dir( $return = '', $upload_dir = '' ) {
	global $dedo_options;
	$upload_dir = ( $upload_dir === '' ? wp_upload_dir() : $upload_dir );
	$directory  = $dedo_options['upload_directory'];
	$upload_dir['path']         = trailingslashit( $upload_dir['basedir'] ) . $directory . $upload_dir['subdir'];
	$upload_dir['url']          = trailingslashit( $upload_dir['baseurl'] ) . $directory . $upload_dir['subdir'];
	$upload_dir['dedo_basedir'] = trailingslashit( $upload_dir['basedir'] ) . $directory;
	$upload_dir['dedo_baseurl'] = trailingslashit( $upload_dir['baseurl'] ) . $directory;
	switch ( $return ) {
		default:
			return $upload_dir;
			break;
		case 'path':
			return $upload_dir['path'];
			break;
		case 'url':
			return $upload_dir['url'];
			break;
		case 'subdir':
			return $upload_dir['subdir'];
			break;
		case 'basedir':
			return $upload_dir['basedir'];
			break;
		case 'baseurl':
			return $upload_dir['baseurl'];
			break;
		case 'dedo_basedir':
			return $upload_dir['dedo_basedir'];
			break;
		case 'dedo_baseurl':
			return $upload_dir['dedo_baseurl'];
			break;
	}
}

/**
 * Set the upload dir for Delightful Downloads.
 */
function dedo_set_upload_dir( $upload_dir ) {
    return dedo_get_upload_dir( '', $upload_dir );
}

/**
 * Protect uploads dir from direct access
 */
function dedo_folder_protection( $folder_protection = '' ) {
	global $dedo_options;
	// Allow custom options to be passed, set to save options if not
	$folder_protection = ( '' === $folder_protection ) ? $dedo_options['folder_protection'] : $folder_protection;
	// Get delightful downloads upload base path
	$upload_dir = dedo_get_upload_dir( 'dedo_basedir' );
	// Create upload dir if needed, return on fail. Causes fatal error on activation otherwise
	if ( !wp_mkdir_p( $upload_dir ) ) {
		return;
	}
	// Add htaccess protection if enabled, else delete it
	if ( 1 == $folder_protection ) {
		if ( !file_exists( $upload_dir . '/.htaccess' ) && wp_is_writable( $upload_dir ) ) {
			$content = "Options -Indexes\n";
			$content .= "deny from all";

			@file_put_contents( $upload_dir . '/.htaccess', $content );
		}
	}
	else {
		if ( file_exists( $upload_dir . '/.htaccess' ) && wp_is_writable( $upload_dir ) ) {
			@unlink( $upload_dir . '/.htaccess' );
		}
	}
	// Check for root index.php
	if ( !file_exists( $upload_dir . '/index.php' ) && wp_is_writable( $upload_dir ) ) {
		@file_put_contents( $upload_dir . '/index.php', '<?php' . PHP_EOL . '// You shall not pass!' );
	}
	// Check subdirs for index.php
	$subdirs = dedo_folder_scan( $upload_dir );

	foreach ( $subdirs as $subdir ) {
		if ( !file_exists( $subdir . '/index.php' ) && wp_is_writable( $subdir ) ) {
			@file_put_contents( $subdir . '/index.php', '<?php' . PHP_EOL . '// You shall not pass!' );
		}
	}
}

/**
 * Scan dir and return subdirs
 */
function dedo_folder_scan( $dir ) {
	// Check class exists
	if ( class_exists( 'RecursiveDirectoryIterator' ) ) {
		// Setup return array
		$return = array();
		$iterator = new RecursiveDirectoryIterator( $dir );
		// Loop through results and add uniques to return array
		foreach ( new RecursiveIteratorIterator( $iterator ) as $file ) {
			if ( !in_array( $file->getPath(), $return ) ) {	
				$return[] = $file->getPath();
			}
		}
		return $return;
	}
	return false;
}

/**
 * Get Downloads Filesize
 * Returns the total filesize of all files.
 */
function dedo_get_filesize( $download_id = false ) {
	global $wpdb;
	$sql = $wpdb->prepare( "
		SELECT SUM( meta_value )
		FROM $wpdb->postmeta
		WHERE meta_key = %s
	", 
	'_dedo_file_size' );
	if ( $download_id ) { $sql .= $wpdb->prepare( " AND post_id = %d", $download_id ); }
	$return = $wpdb->get_var( $sql );
	return ( NULL !== $return ) ? $return : 0;
}

/**
 * Delete All Transients
 * Deletes all transients created by Delightful Downloads
 */
function dedo_delete_all_transients() {
	global $wpdb;
	$sql = $wpdb->prepare( "
		DELETE FROM $wpdb->options
		WHERE option_name LIKE %s
		OR option_name LIKE %s
		OR option_name LIKE %s
		OR option_name LIKE %s
		", 
		'\_transient\_delightful-downloads%%', 
		'\_transient\_timeout\_delightful-downloads%%',
		'\_transient\_dedo_%%',
		'\_transient\_timeout\_dedo_%%' );
	$wpdb->query( $sql );
}

/**
 * Get Absolute Path
 * Searches various locations for download file.
 * It is always recommended that the file should be within /wp-content
 * otherwise it can't be guaranteed that the file will be found.
 * Also allows absolute path to store files outsite the document root.
 */
function dedo_get_abs_path( $requested_file ) {
	$parsed_file = parse_url( $requested_file );
	// Check for absolute path
	if ( ( !isset( $parsed_file['scheme'] ) || !in_array( $parsed_file['scheme'], array( 'http', 'https' ) ) ) && isset( $parsed_file['path'] ) && file_exists( $requested_file ) ) {
		$file = $requested_file;
	}
	// Falls within wp_content
	else if ( strpos( $requested_file, WP_CONTENT_URL ) !== false ) {
		$file_path = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $requested_file );
		$file = realpath( $file_path );
	}
	// Falls in multisite
	else if ( is_multisite() && !is_main_site() && strpos( $requested_file, network_site_url() ) !== false ) {
		$site_url = trailingslashit( site_url() );
		$file_path = str_replace( $site_url, ABSPATH, $requested_file );
		$site_url = trailingslashit( network_site_url() );
		$file_path = str_replace( $site_url, ABSPATH, $file_path );
		$file = realpath( $file_path );
	}
	// Falls within WordPress directory structure
	else if ( strpos( $requested_file, site_url() ) !== false ) {
		$site_url = trailingslashit( site_url() );
		$file_path = str_replace( $site_url, ABSPATH, $requested_file );

		$file = realpath( $file_path );
	}
	// Falls outside WordPress structure but within document root.
	else if ( strpos( $requested_file, site_url() ) && file_exists( $_SERVER['DOCUMENT_ROOT'] . $parsed_file['path'] ) ) {
		$file_path = $_SERVER['DOCUMENT_ROOT'] . $parsed_file['path'];
		
		$file = realpath( $file_path );
	}
	// Checks file exists
	if ( isset( $file ) && is_file( $file ) ) {
		return $file;
	}
	else {
		return false;
	}
}

/**
 * Get File Name
 * Strips the filename from a URL or path.
 * @param string $path File path/url of filename.
 * @return string Value of file name with extension.
 */
function dedo_get_file_name( $path ) {
	return basename( $path );
}

/**
 * Get File Mime
 * Get the file mime type from the file path using WordPress
 * built in filetype check.
 * @param string $path File path/url of filename.
 * @return string Value of file mime.
 */
function dedo_get_file_mime( $path ) {
	$file = wp_check_filetype( $path, array_merge(get_allowed_mime_types(),array('html' => 'text/html')) );
	return $file['type'];
}

/**
 * Get File Extension
 * Get the file extension from the file path using WordPress
 * built in filetype check.
 * @param string $path File path/url of filename.
 * @return string Value of file extension.
 */
function dedo_get_file_ext( $path ) {
	$file = wp_check_filetype( $path, array_merge(get_allowed_mime_types(),array('html' => 'text/html')) );
	return $file['ext'];
}

/**
 * Get File Status
 * Checks whether a file is accessible, either locally or remotely.
 * @param string $url File path/url of filename.
 * @return boolean/array.
 */
function dedo_get_file_status( $url ) {
	// Check locally
	if( $file = dedo_get_abs_path( $url ) ) {
		$type = 'local';
		$size = @filesize( $file );
	}
	else {
		$response = @get_headers( $url, 1 );
		if ( ( false === $response || 'HTTP/1.1 404 Not Found' == $response[0] || 'HTTP/1.1 403 Forbidden' == $response[0] ) || !isset( $response['Content-Length'] ) ) {		
			return false;
		}
		else {
			$type = 'remote';
			$size = $response['Content-Length'];
		}
	}
	return array(
		'type'	=> $type,
		'size'	=> $size
	);
}

/**
 * Get File Icon
 * Return the correct file icon for a file type from css sprite.
 * @param string $file url/path.
 * @return string.
 */
function dedo_get_file_icon( $file ) {
	$ext = dedo_get_file_ext( $file );
	$fmime = dedo_get_file_mime( $file );
	$icon = '<i class="ftyp ftyp-'.strtolower($ext).'" title="'.$ext.'-Datei&#10;'.$fmime.'"></i>';
	return $icon;
}

// Get total downloads counter
function dedo_total_downloads() {
	global $wpdb;
	$sql = $wpdb->prepare( "SELECT SUM(meta_value) FROM $wpdb->postmeta	WHERE meta_key = %s	", '_dedo_file_count' );
	return $wpdb->get_var( $sql );
}

/* ===== END includes/functions.php ===== */

/* ===== BEGIN includes/mime-types.php ===== */
/**
 * Delightful Downloads Mime Types
 *
 * @package     Delightful Downloads
 * @subpackage  Includes/Mime Types
 * @since       1.3
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Mime Types
 *
 * Add additioanl mime types that WordPress is allowed to upload.
 *
 * @since   1.3
 */
function dedo_mime_types( $existing_mimes ) {

	// Developer
	$existing_mimes['php']	= 'php';

	// Image editors
	$existing_mimes['psd']  = 'image/photoshop';
	$existing_mimes['ai']  	= 'application/postscript';
	$existing_mimes['eps']  = 'application/postscript';
	$existing_mimes['pxm']  = 'application/octet-stream'; // Pixelmator

	// Ebooks
	$existing_mimes['mobi']	= 'application/x-mobipocket-ebook';
	$existing_mimes['epub'] = 'application/epub+zip';

	// Misc
	$existing_mimes['json']	= 'application/json';
	$existing_mimes['exe']	= 'application/octet-stream';
	$existing_mimes['msi']	= 'application/vnd.ms-msi';
	$existing_mimes['dmg']	= 'application/x-apple-diskimage';

	return $existing_mimes;
}
add_filter( 'upload_mimes', 'dedo_mime_types' );

/* ===== END includes/mime-types.php ===== */

/* ===== BEGIN includes/options.php ===== */
/**
 * Delightful Downloads Options
 *
 * @package     Delightful Downloads
 * @subpackage  Includes/Options
 * @since       1.3
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Registered Tabs
 *
 * @since  1.3
 */
function dedo_get_tabs() {
	$tabs = apply_filters( 'dedo_settings_tabs', array(
		'general'    => __( 'General', 'delightful-downloads' ),
		'shortcodes' => __( 'Shortcodes', 'delightful-downloads' ),
		'statistics' => __( 'Statistics', 'delightful-downloads' ),
		'advanced'   => __( 'Advanced', 'delightful-downloads' ),
	) );

	$options = dedo_get_options();

	foreach ( $options as $option ) {
		if ( 'licenses' === $option['tab'] ) {
			$tabs['licenses'] = __( 'Licenses', 'delightful-downloads' );
			break;
		}
	}

	$tabs['support'] = __( 'Support', 'delightful-downloads' );

	return $tabs;
}

/**
 * Get Registered Options
 *
 * @since  1.3
 */
function dedo_get_options() {
	$options = array(
		'enable_taxonomies'   => array(
			'name'    => __( 'Categories and Tags', 'delightful-downloads' ),
			'tab'     => 'general',
			'type'    => 'radio',
			'default' => 1,
		),
		'members_only'        => array(
			'name'       => __( 'Members Only', 'delightful-downloads' ),
			'tab'        => 'general',
			'type'       => 'radio',
			'default'    => 0,
			'sub_option' => array(
				'redirect' => 0,
			),
		),
		'open_browser'        => array(
			'name'    => __( 'Open in Browser', 'delightful-downloads' ),
			'tab'     => 'general',
			'type'    => 'radio',
			'default' => 0,
		),
		'block_agents'        => array(
			'name'    => __( 'Block User Agents', 'delightful-downloads' ),
			'tab'     => 'general',
			'type'    => 'textarea',
			'default' => "Googlebot\nbingbot\nmsnbot\nyahoo! slurp\njeeves",
		),
		'default_text'        => array(
			'name'    => __( 'Default Text', 'delightful-downloads' ),
			'tab'     => 'shortcodes',
			'type'    => 'text',
			'default' => __( 'Download', 'delightful-downloads' ),
		),
		'default_style'       => array(
			'name'    => __( 'Default Style', 'delightful-downloads' ),
			'tab'     => 'shortcodes',
			'type'    => 'dropdown',
			'default' => 'infobox',
		),
		'default_button'      => array(
			'name'    => __( 'Default Button Style', 'delightful-downloads' ),
			'tab'     => 'shortcodes',
			'type'    => 'dropdown',
			'default' => 'grey',
		),
		'default_list'        => array(
			'name'    => __( 'Default List Style', 'delightful-downloads' ),
			'tab'     => 'shortcodes',
			'type'    => 'dropdown',
			'default' => 'infoboxlist',
		),
		'log_admin_downloads' => array(
			'name'    => __( 'Admin Events', 'delightful-downloads' ),
			'tab'     => 'statistics',
			'type'    => 'radio',
			'default' => 1,
		),
		'grace_period'        => array(
			'name'       => __( 'Grace Period', 'delightful-downloads' ),
			'tab'        => 'statistics',
			'type'       => 'text',
			'default'    => 1,
			'sub_option' => array(
				'duration' => 3,
			),
		),
		'auto_delete'         => array(
			'name'       => __( 'Auto Delete', 'delightful-downloads' ),
			'tab'        => 'statistics',
			'type'       => 'text',
			'default'    => 0,
			'sub_option' => array(
				'duration' => 90,
			),
		),
		'enable_css'          => array(
			'name'    => __( 'Output CSS', 'delightful-downloads' ),
			'tab'     => 'advanced',
			'type'    => 'radio',
			'default' => 1,
		),
		'cache'               => array(
			'name'       => __( 'Cache', 'delightful-downloads' ),
			'tab'        => 'advanced',
			'type'       => 'text',
			'default'    => 0,
			'sub_option' => array(
				'duration' => 5,
			),
		),
		'download_url'        => array(
			'name'    => __( 'Download Address', 'delightful-downloads' ),
			'tab'     => 'advanced',
			'type'    => 'text',
			'default' => 'ddownload',
		),
   		'download_quicklink'   => array(
			'name'    => __( 'Download Address Quicklink', 'delightful-downloads' ),
			'tab'     => 'advanced',
			'type'    => 'radio',
			'default' => 1,
		),
		'upload_directory'    => array(
			'name'    => __( 'Upload Directory', 'delightful-downloads' ),
			'tab'     => 'advanced',
			'type'    => 'text',
			'default' => 'delightful-downloads',
		),
		'folder_protection'   => array(
			'name'    => __( 'Folder Protection', 'delightful-downloads' ),
			'tab'     => 'advanced',
			'type'    => 'radio',
			'default' => 1,
		),
		'uninstall'           => array(
			'name'    => __( 'Complete Uninstall', 'delightful-downloads' ),
			'tab'     => 'advanced',
			'type'    => 'radio',
			'default' => 0,
		),
	);

	return apply_filters( 'dedo_settings_options', $options );
}

/**
 * Get Default Options
 *
 * @since  1.3
 */
function dedo_get_default_options() {
	// Get registered settings
	$options = dedo_get_options();

	// Loop through and find default value
	foreach ( $options as $key => $value ) {
		$default_options[ $key ] = $value['default'];

		// Add sub options
		if ( isset( $value['sub_option'] ) ) {
			foreach ( $value['sub_option'] as $key2 => $value2 ) {
				$default_options[ $key . '_' . $key2 ] = $value2;
			}
		}
	}

	return $default_options;
}

/* ===== END includes/options.php ===== */

/* ===== BEGIN includes/post-types.php ===== */
/**
 * Delightful Downloads Post Types
 *
 * @package     Delightful Downloads
 * @subpackage  Includes/Post Types
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Download Post Type
 *
 * @since  1.0
 */
function dedo_download_post_type() {
	$labels = array(
		'name'               => __( 'Downloads', 'delightful-downloads' ),
		'singular_name'      => __( 'Download', 'delightful-downloads' ),
		'add_new'            => __( 'Add New', 'delightful-downloads' ),
		'add_new_item'       => __( 'Add New Download', 'delightful-downloads' ),
		'edit_item'          => __( 'Edit Download', 'delightful-downloads' ),
		'new_item'           => __( 'New Download', 'delightful-downloads' ),
		'all_items'          => __( 'All Downloads', 'delightful-downloads' ),
		'view_item'          => __( 'View Download', 'delightful-downloads' ),
		'search_items'       => __( 'Search Downloads', 'delightful-downloads' ),
		'not_found'          => __( 'No downloads found', 'delightful-downloads' ),
		'not_found_in_trash' => __( 'No downloads found in Trash', 'delightful-downloads' ),
		'parent_item_colon'  => '',
		'menu_name'          => __( 'Downloads', 'delightful-downloads' ),
	);

	$args = array(
		'labels'        => apply_filters( 'dedo_ddownload_labels', $labels ),
		'public'        => true,
		// 'rewrite'       => array( 'slug' => 'ddl' ),
		'has_archive'	=> true,
		'show_ui'       => true,
		'show_in_menu'  => true,
		'menu_icon'     => 'dashicons-download',
		'capability_type' => apply_filters( 'dedo_ddownload_cap', 'post' ),
		'supports'      => apply_filters( 'dedo_ddownload_supports', array( 'title', 'editor', 'thumbnail', 'excerpt' ) ),
	);
	register_post_type( 'dedo_download', apply_filters( 'dedo_ddownload_args', $args ) );
}
add_action( 'init', 'dedo_download_post_type' );

/**
 * Download Post Type Column Headings
 *
 * @since  1.0
 */
function dedo_download_column_headings( $columns ) {
	global $dedo_options;

	$columns = array(
		'cb'           => '<input type="checkbox" />',
		'title'        => __( 'Title', 'delightful-downloads' ),
		'file'         => __( 'File', 'delightful-downloads' ),
		'filesize'     => __( 'File Size', 'delightful-downloads' ),
		'shortcode'    => __( 'Shortcode', 'delightful-downloads' ),
		'onedaypass'    => __( 'One day pass:', 'delightful-downloads' ),
		'downloads'    => '<span class="dashicons dashicons-download" title="' . __( 'Downloads', 'delightful-downloads' ) . '"></span>',
		'members_only' => '<span class="dashicons dashicons-businessperson" title="' . __( 'Members Only', 'delightful-downloads' ) . '"></span>',
		'open_browser' => '<span class="dashicons dashicons-portfolio" title="' . __( 'Open in Browser', 'delightful-downloads' ) . '"></span>',
		'date'         => __( 'Date', 'delightful-downloads' ),
		'modified'         => __( 'modified', 'delightful-downloads' ),
	);

	// If Quicklinks is enabled add to columns array
	if ( $dedo_options['download_quicklink'] ) {
		$columns_quicklink = array(
			'quicklink'    => __( 'Quick Link', 'delightful-downloads' ),
		);

		// Splice and insert after shortcode column
		$spliced = array_splice( $columns, 4 );
		$columns = array_merge( $columns, $columns_quicklink, $spliced );
	}
  	
	// If taxonomies is enabled add to columns array
	if ( $dedo_options['enable_taxonomies'] ) {
		$columns_taxonomies = array(
			'taxonomy-ddownload_category' => __( 'Categories', 'delightful-downloads' ),
			'taxonomy-ddownload_tag'      => __( 'Tags', 'delightful-downloads' ),
		);

		// Splice and insert after shortcode column
		$spliced = array_splice( $columns, 4 );
		$columns = array_merge( $columns, $columns_taxonomies, $spliced );
	}

	return $columns;
}
add_filter( 'manage_dedo_download_posts_columns', 'dedo_download_column_headings' );


/**
 * Download Post Type Column Contents
 *
 * @since  1.0
 */
function dedo_download_column_contents( $column_name, $post_id ) {
	
	// File column
	if ( $column_name == 'file' ) {
		$file_url = get_post_meta( $post_id, '_dedo_file_url', true );
		$file_path = dedo_get_abs_path($file_url);
		if (isset($_GET['aktion'])) {
		  if ( current_user_can('administrator') && $_GET['aktion'] == 'dedodelete' && $_GET['post'] == $post_id ) {
			  wp_delete_file( $file_path );
			  wp_redirect( admin_url( "edit.php?post_type=dedo_download") );
		  }	
		} 
		$file_url = dedo_get_file_name( $file_url );
		echo ( ! $file_url ) ? '<span class="blank">--</span>' : '<span style="font-weight:700">'. esc_attr( $file_url ) .'</span>';
		if (file_exists($file_path)) { 
			echo '<br><a style="color:tomato;padding-top:7px" title="'.$file_path.'" onclick="return confirm(\''.__( 'really delete attached file from server?', 'delightful-downloads' ).'\');" href ="'.admin_url( "edit.php?post_type=dedo_download&post=$post_id&aktion=dedodelete").'">' . __( 'delete file', 'delightful-downloads' ) .'</a>';
		} else { echo '<br>'.__( 'file is deleted', 'delightful-downloads' ); }
	}

	// Filesize column
	if ( $column_name == 'filesize' ) {
		$file_size = get_post_meta( $post_id, '_dedo_file_size', true );
		$file_size = ( ! $file_size ) ? 0 : size_format( $file_size, 1 );
		echo ( ! $file_size ) ? '<span class="blank">--</span>' : esc_attr( $file_size );
	}

	// Modified date column
	if ( $column_name == 'modified' ) {
		$file_datum = get_the_modified_date(get_option('date_format').' '.get_option('time_format'),$post_id);
		echo '<i title="modified">'.$file_datum.' '.ago(get_the_modified_date('U')).'</i>';
	}

	// Shortcode column
	if ( $column_name == 'shortcode' ) {
		echo '<input type="text" title="id=&quot;' . esc_attr( $post_id ) . '&quot;" class="copy-to-clipboard" value="[ddownload id=&quot;' . esc_attr( $post_id ) . '&quot;]" readonly>';
		echo '<p class="newlabel" style="background-color:#fe8;display:none">' . __( 'Shortcode copied to clipboard.', 'delightful-downloads' ) . '</p>';
	}

	// QuickLink
	if ( $column_name == 'quicklink' ) {
		global $dedo_options;
		echo '<input type="text" title="'.$dedo_options['download_url'] . '=' . esc_attr( $post_id ) . '" class="copy-to-clipboard" value="' . get_site_url() . '?' . $text = $dedo_options['download_url'] . '=' . esc_attr( $post_id ) . '" readonly>';
		echo '<p class="newlabel" style="background-color:#fe8;display:none">' . __( 'Quicklink copied to clipboard.', 'delightful-downloads' ) . '</p>';
	}
	
	// One day pass column
	if ( $column_name == 'onedaypass' ) {
		$datetime = new DateTime('now');
		$datetime2 = new DateTime('tomorrow');
		$hashwert = md5( intval($post_id) + intval($datetime->format('Ymd')) );
		$hashwertmorgen = md5( intval($post_id) + intval($datetime2->format('Ymd')) );
		echo '<input type="text" title="für '.$datetime->format('d.m.Y').' heute&#10;'.$hashwert.'" class="copy-to-clipboard" style="direction:rtl;cursor:pointer" value="' . get_site_url() . '?sdownload=' . esc_attr( $post_id ) .  '&code='. $hashwert . '" readonly>';
		echo '<input type="text" title="für '.$datetime2->format('d.m.Y').' morgen&#10;'.$hashwertmorgen.'" class="copy-to-clipboard" style="direction:rtl;cursor:pointer" value="' . get_site_url() . '?sdownload=' . esc_attr( $post_id ) .  '&code='. $hashwertmorgen . '" readonly>';
		echo '<p class="newlabel" style="background-color:#fe8;display:none">' . __( 'One day pass copied to clipboard.', 'delightful-downloads' ) . '</p>';
	}
	
	// Count column
	if ( $column_name == 'downloads' ) {
		$count = get_post_meta( $post_id, '_dedo_file_count', true );
		$count = ( ! $count ) ? 0 : number_format_i18n( $count );
		$scount = get_post_meta( $post_id, '_dedo_oneday_count', true );
		$scount = ( ! $scount ) ? 0 : number_format_i18n( $scount );
		echo esc_attr( $count ) . '<br><br><span title="onedaypass">' . esc_attr( $scount ) .'</span>';
	}

	// Members only column
	if ( 'members_only' == $column_name ) {
		$file = get_post_meta( $post_id, '_dedo_file_options', true );

		if ( isset( $file['members_only'] ) ) {
			echo ( 1 == $file['members_only'] ) ? '<span class="true" title="' . __( 'Yes', 'delightful-downloads' ) . '"></span>' : '<span class="false" title="' . __( 'No', 'delightful-downloads' ) . '"></span>';
		} else {
			echo '<span class="blank" title="' . __( 'Inherit', 'delightful-downloads' ) . '">--</span>';
		}
	}

	// Open browser column
	if ( 'open_browser' == $column_name ) {
		$file = get_post_meta( $post_id, '_dedo_file_options', true );

		if ( isset( $file['open_browser'] ) ) {
			echo ( 1 == $file['open_browser'] ) ? '<span class="true" title="' . __( 'Yes', 'delightful-downloads' ) . '"></span>' : '<span class="false" title="' . __( 'No', 'delightful-downloads' ) . '"></span>';
		} else {
			echo '<span class="blank" title="' . __( 'Inherit', 'delightful-downloads' ) . '">--</span>';
		}
	}

}
add_action( 'manage_dedo_download_posts_custom_column', 'dedo_download_column_contents', 10, 2 );

/**
 * Download Post Type Sortable Filter
 *
 * @since  1.0
 */
function dedo_download_column_sortable( $columns ) {
	$columns['filesize']  = 'filesize';
	$columns['downloads'] = 'downloads';

	return $columns;
}
add_filter( 'manage_edit-dedo_download_sortable_columns', 'dedo_download_column_sortable' );

/**
 * Download Post Type Sortable Action
 *
 * @since  1.0
 */
function dedo_download_column_orderby( $query ) {
	$orderby = $query->get( 'orderby' );

	if ( $orderby == 'filesize' ) {
		$query->set( 'meta_key', '_dedo_file_size' );
		$query->set( 'orderby', 'meta_value_num' );
	}

	if ( $orderby == 'downloads' ) {
		$query->set( 'meta_key', '_dedo_file_count' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
add_action( 'pre_get_posts', 'dedo_download_column_orderby' );

/* ===== END includes/post-types.php ===== */

/* ===== BEGIN includes/process-download.php ===== */
/**
 * Delightful Downloads Process Download
 *
 * @package     Delightful Downloads
 * @subpackage  Includes/Process Downloads and secure one day pass downloads
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Process Download
 *
 * Validate download and send file to user
 * http://www.richnetapps.com/php-download-script-with-resume-option/
 *
 * @since 1.0
 */

/**
 * Abort a download request safely even before wp_loaded.
 * Avoid wp_die() too early because themes/plugins may touch WooCommerce cart in the die template.
 */
function dedo_download_abort( $message, $title = '' ) {
	$message = (string) $message;
	$title   = (string) $title;

	if ( did_action( 'wp_loaded' ) ) {
		wp_die( $message, $title );
	}

	if ( ! headers_sent() ) {
		status_header( 400 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	}

	echo '<!DOCTYPE html><html><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><title>' . esc_html( $title ?: __( 'Download Error', 'delightful-downloads' ) ) . '</title></head><body>';
	echo wp_kses_post( wpautop( $message ) );
	echo '</body></html>';
	exit;
}

/**
 * Show the password prompt using the active theme wp_die() styling,
 * but suppress early doing_it_wrong notices (e.g. WooCommerce cart notices)
 * while the themed die template is rendered before wp_loaded.
 */
function dedo_download_password_prompt( $download_id ) {
	$download_id = (int) $download_id;
	$title       = __( 'Password Required', 'delightful-downloads' );
	$message     = get_the_password_form( $download_id );

	if ( ! did_action( 'wp_loaded' ) ) {
		add_filter( 'doing_it_wrong_trigger_error', '__return_false', 999999 );
	}

	wp_die( $message, $title );
}

// Secure download one day pass
function dedo_onedaypass_process( $download_id ) {
	global $dedo_options;

	// Check valid download
	if ( ! dedo_download_valid( $download_id ) ) {
		do_action( 'ddownload_download_invalid', $download_id );
		dedo_download_abort( __( 'Invalid download.', 'delightful-downloads' ) );
	}
	// Get file meta
	$download_url = get_post_meta( $download_id, '_dedo_file_url', true );
	$options      = get_post_meta( $download_id, '_dedo_file_options', true );

	// Disable max_execution_time
	set_time_limit( 0 );

	// Hook before download starts
	do_action( 'ddownload_download_before', $download_id );
	
    // Onedaypass prüfen
	$datetime = new DateTime('now');
	$hashwert = md5( intval($download_id) + intval($datetime->format('Ymd')) );
	if ( file_exists(dedo_get_abs_path( $download_url ) ) && $_GET['code'] == $hashwert ) { // if it match it is legit
		// $path = ABSPATH.'wp-content/uploads/delightful-downloads/2019/software.zip'; // the file made available for download via this PHP file
		$path = dedo_get_abs_path( $download_url );
		$mm_type="application/octet-stream"; // modify accordingly to the file type of $path, but in most cases no need to do so
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public");
		header("Content-Description: File Transfer");
		header("Content-Type: " . $mm_type);
		header("Content-Length: " .(string)(filesize($path)) );
		header('Content-Disposition: attachment; filename="'.basename($path).'"');
		header("Content-Transfer-Encoding: binary\n");
		readfile($path); // outputs the content of the file
		// Hook when download complete
		do_action( 'ddownload_download_complete', $download_id );
		exit();		  
	} else {
		dedo_download_abort( __( 'download not found or onedaypass invalid or expired.', 'delightful-downloads' ) ); // not legit
	}  

}


function dedo_download_process( $download_id ) {
	global $dedo_options;

	// Check valid download
	if ( ! dedo_download_valid( $download_id ) ) {
		do_action( 'ddownload_download_invalid', $download_id );
		dedo_download_abort( __( 'Invalid download.', 'delightful-downloads' ) );
	}

	// Check blocked user agents
	if ( ! dedo_download_blocked( ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) ) {
		do_action( 'ddownload_download_blocked', $download_id );
		dedo_download_abort( __( 'You are blocked from downloading this file!', 'delightful-downloads' ) );
	}

	if ( apply_filters( 'dedo_abort_download', false, $download_id ) ) {
		return;
	}

	// Get file meta
	$download_url = get_post_meta( $download_id, '_dedo_file_url', true );
	$options      = get_post_meta( $download_id, '_dedo_file_options', true );

	// Check for members only
	if ( ! dedo_download_permission( $options ) ) {
		do_action( 'ddownload_download_permission', $download_id );

		// Get redirect location
		$location = ( isset( $options['members_only_redirect'] ) ) ? $options['members_only_redirect'] : $dedo_options['members_only_redirect'];

		// Try to redirect
		if ( $location = get_permalink( $location ) ) {
			wp_redirect( $location );
			exit();
		} else {
			// Invalid page provided, show error message
			dedo_download_abort( __( 'Please login to download this file!', 'delightful-downloads' ) );
		}
	}

	// Password protected
	if ( post_password_required( $download_id ) ) {
		dedo_download_password_prompt( $download_id );
	}

	// Empty file urls not allowed
	if ( '' === $download_url ) {
		dedo_download_abort( __( 'You must attach a file to this download.', 'delightful-downloads' ) );
	}

	// Stop page caching. Cause conflicts with WP Super Cache
	define( 'DONOTCACHEPAGE', true );

	// Disable php notices, can cause corrupt downloads
	@ini_set( 'display_errors', 0 );

	// Disable compression
	if ( function_exists( 'apache_setenv' ) ) {
		@apache_setenv( 'no-gzip', 1 );
	}

	@ini_set( 'zlib.output_compression', 'Off' );

	// Close sessions, which can sometimes cause buffering errors??
	@session_write_close();

	/**
	 * Output Buffering
	 *
	 * The majority of servers work when clearing output buffering.
	 * If you get corrupt or blank downloads try the following:
	 *
	 * Disable by adding the following, to your theme's functions.php file:
	 *
	 * add_filter( 'dedo_clear_output_buffers', '__return_false' );
	 *
	 */
	if ( apply_filters( 'dedo_clear_output_buffers', true ) ) {
		do {
			@ob_end_clean();
		} while ( ob_get_level() > 0 );
	}

	// Disable max_execution_time
	set_time_limit( 0 );

	// Hook before download starts
	do_action( 'ddownload_download_before', $download_id );

	// Open in browser
	$open_browser = ( isset( $options['open_browser'] ) ) ? $options['open_browser'] : $dedo_options['open_browser'];

	if ( $open_browser ) {
		header( "Location: $download_url" );
		exit();
	}

	// Convert to path
	if ( $download_path = dedo_get_abs_path( $download_url ) ) {
		// Try to open file, else display server error
		if ( ! $file = @fopen( $download_path, 'rb' ) ) {
			// Server error
			dedo_download_abort( __( 'Server error, file cannot be opened!', 'delightful-downloads' ) );
		}

		// Set headers
		nocache_headers();
		header( "X-Robots-Tag: noindex, nofollow", true );
		header( "Content-Type: " . dedo_download_mime( $download_path ) );
		header( "Content-Description: File Transfer" );
		header( "Content-Disposition: attachment; filename=\"" . basename( $download_path ) . "\";" );
		header( "Content-Transfer-Encoding: binary" );
		header( "Content-Length: " . @filesize( $download_path ) ); // filesize causes blank downloads on Windows servers

		// Output file in chuncks
		while ( ! feof( $file ) ) {

			print fread( $file, 1024 * 1024 );
			flush();

			// Check conection, if lost close file and end loop
			if ( connection_status() != 0 ) {

				fclose( $file );
				exit();
			}
		}

		// Reached end of file, close it. Job done!
		fclose( $file );

		// Hook when download complete
		do_action( 'ddownload_download_complete', $download_id );

		// Done! Exit
		exit();
	} else {
		// No disoverable path, redirect to file
		header( "Location: $download_url" );
		exit();
	}
}

/**
 * Init handle download.
 */
function dedo_init_handle_download() {
	global $dedo_options;

	if ( isset( $_GET[ $dedo_options['download_url'] ] ) ) {
		dedo_download_process( absint( $_GET[ $dedo_options['download_url'] ] ) );
	}
	if ( isset( $_GET[ 'sdownload' ] ) ) {
		dedo_onedaypass_process( absint( $_GET[ 'sdownload' ] ) );
	}
}
add_action( 'init', 'dedo_init_handle_download', 4 );

/* ===== END includes/process-download.php ===== */

/* ===== BEGIN includes/scripts.php ===== */
/**
 * Delightful Downloads Scripts
 *
 * @package     Delightful Downloads
 * @subpackage  Includes/Scripts
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Frontend Scripts & Styles - unicode symbole für icons
 */
function dedo_enqueue_scripts( $page ) {
	global $dedo_options,$post;
	// Load css sprite for file type icons
	wp_register_style( 'filetype-style', DEDO_PLUGIN_URL . 'assets/css/filetypes.min.css' );
	if ( 'dedo_download' == get_post_type() ) wp_enqueue_style( 'filetype-style' );
	// Enqueue frontend CSS if option is enabled
	if ( ! $dedo_options['enable_css'] ) { return; }
	$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : DEDO_VERSION;
	// Register frontend CSS
	$src = DEDO_PLUGIN_URL . 'assets/css/delightful-downloads.css';
	wp_enqueue_style( 'dedo-css', $src, array(), $version, 'all' );
}
add_action( 'wp_enqueue_scripts', 'dedo_enqueue_scripts' );

/**
 * Register Admin Scripts & Styles
 */
function dedo_admin_enqueue_scripts( $page ) {
	$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : DEDO_VERSION;
	$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	// Load css sprite for file type icons
	wp_enqueue_style( 'filetye-style', DEDO_PLUGIN_URL . 'assets/css/filetypes.min.css' );

	// Register scripts
	$src = DEDO_PLUGIN_URL . 'assets/js/dedo-admin-global' . $suffix . '.js';
	wp_register_script( 'dedo-admin-js-global', $src, array( 'jquery' ), $version, true );
	$src = DEDO_PLUGIN_URL . 'assets/js/dedo-admin-legacy-logs' . $suffix . '.js';
	wp_register_script( 'dedo-admin-js-legacy-logs', $src, array( 'jquery' ), $version, true ); // 1.4 upgrade
	$src = DEDO_PLUGIN_URL . 'assets/js/dedo-admin-media-button' . $suffix . '.js';
	wp_register_script( 'dedo-admin-js-media-button', $src, array( 'jquery' ), $version, true );
	$src = DEDO_PLUGIN_URL . 'assets/js/dedo-admin-post-download' . $suffix . '.js';
	wp_register_script( 'dedo-admin-js-post-download', $src, array(
		'jquery',
		'plupload-all'
	), $version, true );
	// Register styles
	$src = DEDO_PLUGIN_URL . 'assets/css/delightful-downloads-admin.css';
	wp_register_style( 'dedo-css-admin', $src, array(), $version, 'all' );

	// Enqueue on all admin pages
	wp_enqueue_style( 'dedo-css-admin' );
	wp_enqueue_script( 'dedo-admin-js-global' );
	// JS copy to clipboard
	$src = DEDO_PLUGIN_URL . 'assets/js/copy-to-clipboard' . $suffix . '.js';
	wp_enqueue_script( 'dedo-copy-to-clipboard', $src, array(
		'jquery',
	), $version, true );
	// Enqueue on dedo_download post add/edit screen
	if ( in_array( $page, array(
			'post.php',
			'page.php',
			'post-new.php',
			'post-edit.php'
		) ) && get_post_type() == 'dedo_download'
	) {
		wp_enqueue_script( 'dedo-admin-js-post-download' );
	}

	// Enqueue on all post/edit screen
	if ( in_array( $page, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ) ) ) {
		wp_enqueue_script( 'dedo-admin-js-media-button' );
	}
}
add_action( 'admin_enqueue_scripts', 'dedo_admin_enqueue_scripts' );

/* ===== END includes/scripts.php ===== */

/* ===== BEGIN includes/shortcodes.php ===== */
/**
 * Delightful Downloads Shortcodes
 *
 * @package     Delightful Downloads
 * @subpackage  Includes/Shortcodes
 * @since       1.1
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Download Shortcode.
 * Outputs a single download based on user defined attributes.
 * @param array $atts
 * @return string
 */
function dedo_shortcode_ddownload( $atts ) {
	/**
	 * Use get option again so that WPML can filter default text
	 * for translation, get_option() is cached so should not
	 * affect performance.
	 */
	global $dedo_default_options;
	$dedo_options = wp_parse_args( get_option( 'delightful-downloads' ), $dedo_default_options );

	// filetype skript laden
	wp_enqueue_style( 'filetype-style' );

	// Attributes
	extract( shortcode_atts(
		array(
			'id' 	=> '',
			'text'	=> $dedo_options['default_text'],
			'style'	=> $dedo_options['default_style'],
			'button'=> '',
			'class'	=> ''
		), $atts, 'ddownload' )
	);

	// Validate download id
	if ( $id == '' || !dedo_download_valid( $id ) ) {
		return __( 'Invalid download ID.', 'delightful-downloads' );
	}

	// Check style against registered styles
	$registered_styles = dedo_get_shortcode_styles();

	if ( array_key_exists( $style, $registered_styles ) ) {
		$style_format = $registered_styles[ $style ]['format'];
	}
	else {
		return __( 'Invalid style attribute.', 'delightful-downloads' );
	}

	// Check button against registered buttons
	if ( $style == 'button' ) {
		$button = ( empty( $button ) ) ? $dedo_options['default_button'] : $button;
		$registered_buttons = dedo_get_shortcode_buttons();
		if ( array_key_exists( $button, $registered_buttons ) ) {
			$button_class = $registered_buttons[ $button ]['class'];
		}
		else {
			return __( 'Invalid button attribute.', 'delightful-downloads' );
		}
	}

	// Generate correct class and add user defined
	$classes = 'ddownload-' . $style; // Output style
	$classes .= ( isset( $button_class ) ) ? ' ' . $button_class : ''; // Button style
	$classes .= ' id-' . $id; // Download id
	$classes .= ' ext-' . dedo_get_file_ext( get_post_meta( $id, '_dedo_file_url', true ) ); // File extension
	$classes .= ( !empty( $class ) ) ? ' ' . $class : ''; // User defined
	// Replace text and class att
	$replace = array(
		'%text%'	=> $text,
		'%class%'	=> $classes
	);
	
	foreach ( $replace as $key => $value ) {
 		$style_format = str_replace( $key, $value, $style_format );
 	}
	// Search and replace wildcards
	$output = dedo_search_replace_wildcards( $style_format, $id );
	return apply_filters( 'dedo_shortcode_ddownload', $output, $id, $atts, $classes );
}
add_shortcode( 'ddownload', 'dedo_shortcode_ddownload' );


/**
 * Downloads List Shortcode
 * Displays a list of downloads based on user defined attributes.
 * Extended:
 *  - search (Shortcode-Attribut) + ?search=... (URL)
 *  - show_search=0|1 (Suchfeld aus-/einblenden; Standard: 1)
 *  - Suche filtert NUR innerhalb dedo_download (Titel, Content, Dateiname/_dedo_file_url)
 *  - Cache-Code entfernt (auf Wunsch)
 * @since   1.3
 */
function dedo_shortcode_ddownload_list( $atts ) {
	global $dedo_options, $dedo_statistics;

	// filetype skript laden
	wp_enqueue_style( 'filetype-style' );
	
	// Attributes
	extract( shortcode_atts(
		array(
			'limit' 				=> 0,
			'orderby'			=> 'title',
			'order'				=> 'ASC',
			'categories'		=> '',
			'tags'				=> '',
			'exclude_categories'=> '',
			'exclude_tags'		=> '',
			'relation'			=> 'AND',
			'style'				=> $dedo_options['default_list'],
			'search'			=> '',
			'show_search'		=> 1,
		), $atts, 'ddownload_list' )
	);

	// --- Suchbegriff ermitteln (URL/Formular hat Priorität) ---
	$search_term = '';
	if ( isset( $_REQUEST['search'] ) && $_REQUEST['search'] !== '' ) {
		$search_term = sanitize_text_field( wp_unslash( $_REQUEST['search'] ) );
	} elseif ( ! empty( $search ) ) {
		$search_term = sanitize_text_field( $search );
	}

	// Default query args
	$query_args = array(
		'post_type'		=> 'dedo_download',
		'post_status'	=> 'publish',
	);

	// Validate and set limit
	$limit = abs( $limit );
	$query_args['posts_per_page'] = ( 0 === $limit ) ? -1 : $limit;

	// Validate and set orderby
	if ( !in_array( strtolower( $orderby ), array( 'title', 'date', 'modified', 'count', 'filesize', 'random' ) ) ) {
		return __( 'Invalid orderby attribute.', 'delightful-downloads' );
	}
	else {
		switch ( $orderby ) {
			case 'title':
				$query_args['orderby'] = 'title';
				break;

			case 'date':
				$query_args['orderby'] = 'date';
				break;

			case 'modified':
				$query_args['orderby'] = 'modified';
				break;
				
			case 'count':
				$query_args['meta_key'] = '_dedo_file_count';
				$query_args['orderby'] = 'meta_value_num';
				break;

			case 'filesize':
				$query_args['meta_key'] = '_dedo_file_size';
				$query_args['orderby'] = 'meta_value_num';
				break;

			case 'random':
				$query_args['orderby'] = 'rand';
				break;
		}
	}

	// Validate and set order
	if ( !in_array( strtoupper( $order ), array( 'ASC', 'DESC' ) ) ) {
		return __( 'Invalid order attribute.', 'delightful-downloads' );
	}
	else {
		$query_args['order'] = strtoupper( $order);
	}

	// Validate relation
	if ( !in_array( strtoupper( $relation ), array( 'AND', 'OR' ) ) ) {
		return __( 'Invalid relation attribute.', 'delightful-downloads' );
	}
	else {
		$relation = strtoupper( $relation );
	}

	// Validate and set categories/tags
	$tax_class = '';

	if ( !empty( $categories ) || !empty( $tags ) || !empty( $exclude_categories ) || !empty( $exclude_tags ) ) {
		$query_args['tax_query'] = array(
			'relation' => $relation,
		);

		if ( !empty( $categories ) ) {
			$categories_array = explode( ',' , $categories );
			$categories_array = array_map( 'trim', $categories_array );
			$query_args['tax_query'][] = array(
				'taxonomy'	=> 'ddownload_category',
				'field'		=> 'slug',
				'terms'		=> $categories_array,
			);
			// Set taxonomy class
			$tax_class .= ' category-' . implode( ' category-', $categories_array );
		}

		if ( !empty( $tags ) ) {
			$tags_array = explode( ',' , $tags );
			$tags_array = array_map( 'trim', $tags_array );
			$query_args['tax_query'][] = array(
				'taxonomy'	=> 'ddownload_tag',
				'field'		=> 'slug',
				'terms'		=> $tags_array,
			);
			// Set taxonomy class
			$tax_class .= ' tag-' . implode( ' tag-', $tags_array );
		}

		if ( !empty( $exclude_categories ) ) {
			$exclude_categories_array = explode( ',' , $exclude_categories );
			$exclude_categories_array = array_map( 'trim', $exclude_categories_array );

			$query_args['tax_query'][] = array(
				'taxonomy'	=> 'ddownload_category',
				'field'		=> 'slug',
				'terms'		=> $exclude_categories_array,
				'operator'	=> 'NOT IN',
			);
		}

		if ( !empty( $exclude_tags ) ) {
			$exclude_tags_array = explode( ',' , $exclude_tags );
			$exclude_tags_array = array_map( 'trim', $exclude_tags_array );

			$query_args['tax_query'][] = array(
				'taxonomy'	=> 'ddownload_tag',
				'field'		=> 'slug',
				'terms'		=> $exclude_tags_array,
				'operator'	=> 'NOT IN',
			);
		}
	}

	// Check style against registered styles
	$registered_styles = dedo_get_shortcode_lists();
	$style_class       = ' list-' . $style;

	if ( array_key_exists( $style, $registered_styles ) ) {
		$style_format = $registered_styles[ $style ]['format'];
	}
	else {
		return __( $style. 'Invalid style attribute.', 'delightful-downloads' );
	}

	// --- Suche nur innerhalb dedo_download: Titel/Content ODER Datei-URL (Meta) ---
	if ( $search_term !== '' ) {
		// 1) Titel + Content (WP-Suche)
		$args_s = $query_args;
		$args_s['fields'] = 'ids';
		$args_s['posts_per_page'] = -1;
		$args_s['s'] = $search_term;
		$q_s = new WP_Query( $args_s );
		$ids_s = ( ! empty( $q_s->posts ) ) ? $q_s->posts : array();

		// 2) Datei-URL / Dateiname (Meta)
		$args_m = $query_args;
		$args_m['fields'] = 'ids';
		$args_m['posts_per_page'] = -1;
		$args_m['meta_query'] = array(
			array(
				'key'		=> '_dedo_file_url',
				'value'		=> $search_term,
				'compare'	=> 'LIKE',
			),
		);
		$q_m = new WP_Query( $args_m );
		$ids_m = ( ! empty( $q_m->posts ) ) ? $q_m->posts : array();

		$ids = array_values( array_unique( array_merge( $ids_s, $ids_m ) ) );

		// Wenn nichts gefunden: direkt "No downloads found"
		if ( empty( $ids ) ) {
			// Suchfeld trotzdem rendern (falls show_search=1)
			ob_start();
			if ( (int) $show_search === 1 ) {
				echo '<form method="get" class="ddownload-search-form">';
				foreach ( $_GET as $key => $value ) {
					if ( $key !== 'search' ) {
						echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
					}
				}
				echo '<div class="ddownload-search-field">';
				echo '<span class="ddownload-search-icon">🔍</span>';
				echo '<input type="search" name="search" class="search" value="' . esc_attr( $search_term ) . '" placeholder="Downloads durchsuchen …">';
				echo '</div>';
				echo '</form>';
			}
			$output = ob_get_clean();
			return $output . '<p>' . __( 'No downloads found.', 'delightful-downloads' ) . '</p>';
		}

		// Hauptquery auf gefundene IDs einschränken
		$query_args['post__in'] = $ids;
		// kein 's' und keine meta_query mehr im Hauptquery nötig
	}

	// Run query
	$downloads_list = new WP_Query( $query_args );

	// Begin output
	if ( $downloads_list->have_posts() ) {
		ob_start();
		$dlcount=0;
		$filecount=0;
		$tfilesize=0;
		$listfilter='';

		if (!empty($categories)) $listfilter .= '📂 '.$categories;
		if (!empty($tags)) $listfilter .= ' &nbsp;🔖 '.$tags;
		if (!empty($exclude_categories)) $listfilter .= ' &nbsp;<span title="excluded cats">🔽</span>📂 '.$exclude_categories;
		if (!empty($exclude_tags)) $listfilter .= ' &nbsp;<span title="excluded tags" style="color:tomato">🔽</span>🔖 '.$exclude_tags;
		if (!empty($search_term)) $listfilter .= ' &nbsp;🔎 <span class="ddownload-search-term">'.esc_html($search_term).'</span>';


		echo '<div class="entry-meta-top" style="text-align:center;width:100%;text-transform:uppercase">';

		// Suchfeld (immer anzeigen, außer show_search=0)
		if ( (int) $show_search === 1 ) {
			echo '<form method="get" style="display:inline-block;padding-right:2em">';
			// vorhandene GET-Parameter erhalten (page_id etc.)
			foreach ( $_GET as $key => $value ) {
				if ( $key !== 'search' ) {
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
				}
			}
			echo '🔍 <input type="search" name="search" class="search" value="' . esc_attr( $search_term ) . '" placeholder="Suchbegriff">';
			echo '</form>';
		}

		if (!empty($listfilter)) echo '<strong>'.__('downloads','delightful-downloads').'</strong> &nbsp;'.$listfilter;
		echo '</div>';

		echo '<div class="ddownloads_list' . $tax_class . $style_class . '">';
		while ( $downloads_list->have_posts() ) {
			$downloads_list->the_post();
			// Add classes
			$classes = 'id-' . get_the_ID(); // Download id
			$classes .= ' ext-' . dedo_get_file_ext( get_post_meta( get_the_ID(), '_dedo_file_url', true ) ); // File extension
			$new_style_format = str_replace( '%class%', $classes, $style_format );
			$filecount++;
			$dlcount += get_post_meta( get_the_ID(), '_dedo_file_count', true );
			$tfilesize += (int) get_post_meta( get_the_ID(), '_dedo_file_size', true );
			echo '<div style="margin-bottom:.3em;border:1px solid var(--pengcolor)"><div style="position:relative"><div style="background-color:#fffb;color:#000;font-size:1.2em;font-weight:700;position:absolute;left:8px;top:6px;z-index:99999;line-height:1em">'. $filecount.'</div></div>' . dedo_search_replace_wildcards( $new_style_format, get_the_ID() ) . '</div>';
			// Reset classes for next iteration
			unset( $classes );
			unset( $new_style_format );
		}
		echo '</div>';

		// File Statistiken, wenn limit nicht gesetzt
		if ($limit == 0 && $search_term === '') {
			$total_files = wp_count_posts( 'dedo_download' )->publish;
			echo '<div class="entry-meta-top" style="text-align:center;margin-bottom:2em">';
			if ((int) $filecount < (int) $total_files) {
				echo '📋 <b>'.$filecount.'</b> &nbsp;';
				echo '🗃️ <b>' . size_format( $tfilesize, 1 ).'</b> ';
				echo '📥 <b>'. number_format_i18n( $dlcount,0).'</b>';
			}	
			echo ' &nbsp; TOTAL <b>'.$total_files.'</b> &nbsp;'
				.'🗃️ <b>'.size_format( dedo_get_filesize(), 1 ).'</b> '
				.'️📥 <b>'.number_format_i18n(dedo_total_downloads());
			echo '</b></div>';
		}

		$output = ob_get_clean();
		wp_reset_postdata();
	}
	else {
		// Suchfeld trotzdem anzeigen (falls aktiv)
		ob_start();
		if ( (int) $show_search === 1 ) {
			echo '<form method="get" class="ddownload-search-form">';
			foreach ( $_GET as $key => $value ) {
				if ( $key !== 'search' ) {
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
				}
			}
			echo '<div class="ddownload-search-field">';
			echo '<span class="ddownload-search-icon">🔍</span>';
			echo '<input type="search" name="search" class="search" value="' . esc_attr( $search_term ) . '" placeholder="Downloads durchsuchen …">';
			echo '</div>';
			echo '</form>';
		}
		$output = ob_get_clean();
		return $output . '<p>' . __( 'No downloads found.', 'delightful-downloads' ) . '</p>';
	}

	return apply_filters( 'dedo_shortcode_ddownload_list', $output );
}
add_shortcode( 'ddownload_list', 'dedo_shortcode_ddownload_list' );


/**
 * Allow shortcodes in widgets
 */
add_filter( 'widget_text', 'do_shortcode' );

/* ===== END includes/shortcodes.php ===== */

/* ===== BEGIN includes/taxonomies.php ===== */
/**
 * Delightful Downloads Taxonomies
 *
 * @package     Delightful Downloads
 * @subpackage  Includes/Taxonomies
 * @since       1.3
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Register Download Taxonomies
 *
 * @since  1.3
 */
function dedo_download_taxonomies() {
	global $dedo_options;	

	// Register download category taxonomy
	$labels = array(
		'name'				=> __( 'Download Categories', 'delightful-downloads' ),
		'singular_name'		=> __( 'Download Category', 'delightful-downloads' ),
		'menu_name'			=> __( 'Categories', 'delightful-downloads' ),
		'all_items'			=> __( 'All Categories', 'delightful-downloads' ),
		'edit_item'			=> __( 'Edit Category', 'delightful-downloads' ),
		'view_item'			=> __( 'View Category', 'delightful-downloads' ),
		'update_item'		=> __( 'Update Category', 'delightful-downloads' ),
		'add_new_item'		=> __( 'Add New Category', 'delightful-downloads' ),
		'new_item_name'		=> __( 'New Category Name', 'delightful-downloads' ),
		'search_items'		=> __( 'Search Categories', 'delightful-downloads' ),
		'popular_items'		=> __( 'Popular Categories', 'delightful-downloads' ) 
	);

	$category_args = array(
		'labels'			=> apply_filters( 'dedo_ddownload_category_labels', $labels ),
		'public'			=> true,
		'show_in_nav_menus'	=> false,
		'show_tag_cloud'	=> false,
		'show_admin_column'	=> true,
		'hierarchical'		=> true
	);

	// Register download tag taxonomy
	$labels = array(
		'name'				=> __( 'Download Tags', 'delightful-downloads' ),
		'singular_name'		=> __( 'Download Tag', 'delightful-downloads' ),
		'menu_name'			=> __( 'Tags', 'delightful-downloads' ),
		'all_items'			=> __( 'All Tags', 'delightful-downloads' ),
		'edit_item'			=> __( 'Edit Tag', 'delightful-downloads' ),
		'view_item'			=> __( 'View Tag', 'delightful-downloads' ),
		'update_item'		=> __( 'Update Tag', 'delightful-downloads' ),
		'add_new_item'		=> __( 'Add New Tag', 'delightful-downloads' ),
		'new_item_name'		=> __( 'New Tag Name', 'delightful-downloads' ),
		'search_items'		=> __( 'Search Tags', 'delightful-downloads' ),
		'popular_items'		=> __( 'Popular Tags', 'delightful-downloads' )
	);

	$tag_args = array(
		'labels'			=> apply_filters( 'dedo_ddownload_tag_labels', $labels ),
		'public'			=> true,
		'show_in_nav_menus'	=> false,
		'show_tag_cloud'	=> false,
		'show_admin_column'	=> true,
		'hierarchical'		=> false
	);

	// Only register if enabled in settings
	if ( $dedo_options['enable_taxonomies'] ) {
		register_taxonomy( 'ddownload_category', array( 'dedo_download' ), $category_args );
		register_taxonomy( 'ddownload_tag', array( 'dedo_download' ), $tag_args );
	}
}
add_action( 'init', 'dedo_download_taxonomies', 3 );

/* ===== END includes/taxonomies.php ===== */

