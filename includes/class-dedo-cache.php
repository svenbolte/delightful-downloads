<?php
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