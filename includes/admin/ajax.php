<?php
/**
 * Delightful Downloads Ajax
 *
 * @package     Delightful Downloads
 * @subpackage  Admin/Ajax
 * @since       1.0
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Process Ajax upload file
 *
 * @since  1.0
 */
function dedo_download_upload_ajax() {
	
	if ( !check_ajax_referer( 'dedo_download_upload', false, false ) ) {

		// Echo error message
		die( '{ "jsonrpc" : "2.0", "error" : {"code": 500, "message": "' . __( 'Failed security checks!', 'delightful-downloads' ) . '" } }' );
	}

	// Set upload dir
	add_filter( 'upload_dir', 'dedo_set_upload_dir' );
	
	// Upload the file
	$file = wp_handle_upload( $_FILES['async-upload'], array( 'test_form'=> true, 'action' => 'dedo_download_upload' ) );
	
	// Check for success
	if ( isset( $file['url'] ) ) {
		// Post ID
		$post_id = $_REQUEST['post_id'];
	
		// Add/update post meta
		update_post_meta( $post_id, '_dedo_file_url', $file['url'] );
		update_post_meta( $post_id, '_dedo_file_size', $_FILES['async-upload']['size'] );
	
		// Echo success response
		die( '{"jsonrpc" : "2.0", "file" : {"url": "' . $file['url'] . '"}}' );
	}	
	else {
		// Echo error message
		die( '{"jsonrpc" : "2.0", "error" : {"code": 500, "message": "' . $file['error'] . '"}, "details" : "' . $file['error'] . '"}' );
	}
}
add_action( 'wp_ajax_dedo_download_upload', 'dedo_download_upload_ajax' );

/**
 * File browser Ajax endpoint
 *
 * @since  1.7
 */
function dedo_file_browser_ajax() {
	if ( ! check_ajax_referer( 'dedo_file_browser', 'nonce', false ) || ! current_user_can( apply_filters( 'dedo_cap_add_new', 'edit_pages' ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Failed security check!', 'delightful-downloads' ) ), 403 );
	}

	$root = wp_normalize_path( trailingslashit( dedo_get_upload_dir( 'basedir' ) ) );
	$relative = isset( $_REQUEST['dir'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['dir'] ) ) : '';
	$relative = ltrim( wp_normalize_path( $relative ), '/' );
	$relative = str_replace( array( '../', '..\\' ), '', $relative );

	$current = wp_normalize_path( trailingslashit( $root . $relative ) );

	if ( 0 !== strpos( $current, $root ) || ! is_dir( $current ) || ! is_readable( $current ) ) {
		wp_send_json_error( array( 'message' => __( 'Directory not available.', 'delightful-downloads' ) ), 400 );
	}

	$directories = array();
	$files = array();
	$entries = @scandir( $current );

	if ( false === $entries ) {
		wp_send_json_error( array( 'message' => __( 'Directory could not be read.', 'delightful-downloads' ) ), 500 );
	}

	foreach ( $entries as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}

		$full_path = wp_normalize_path( $current . $entry );
		if ( is_dir( $full_path ) ) {
			$directories[] = array(
				'name' => $entry,
				'path' => ltrim( $relative . $entry . '/', '/' ),
			);
			continue;
		}

		if ( is_file( $full_path ) ) {
			$files[] = array(
				'name' => $entry,
				'path' => ltrim( $relative . $entry, '/' ),
				'url'  => trailingslashit( dedo_get_upload_dir( 'baseurl' ) ) . ltrim( str_replace( '\\', '/', $relative . $entry ), '/' ),
			);
		}
	}

	usort( $directories, function( $a, $b ) {
		return strnatcasecmp( $a['name'], $b['name'] );
	} );
	usort( $files, function( $a, $b ) {
		return strnatcasecmp( $a['name'], $b['name'] );
	} );

	$parent = '';
	if ( '' !== $relative ) {
		$parent = dirname( untrailingslashit( $relative ) );
		$parent = '.' === $parent ? '' : trailingslashit( $parent );
	}

	wp_send_json_success( array(
		'current' => $relative,
		'parent' => $parent,
		'directories' => $directories,
		'files' => $files,
	) );
}
add_action( 'wp_ajax_dedo_file_browser', 'dedo_file_browser_ajax' );
