<?php
/**
 * Delightful Downloads Functions
 * @package     Delightful Downloads
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Register own template for downloads
 */
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


/**
 * Shortcode Styles
 */
function dedo_get_shortcode_styles() {
	$styles = array(
	 	'infobox'		=> array(
	 		'name'			=> __( 'Infobox mit Icon, Rahmen und Details', 'delightful-downloads' ),
	 		'format'		=> '<div class="%class%" style="display:flex;border:1px solid #e1e1e1;width:100%;padding:4px;border-radius:3px">
					<div style="display:inline-block;min-width:60px;width:60px">%icon%</div>
					<div style="display:inline-block;width:100%;min-width:70%"><a href="%url%" title="%ext%-Datei&#10;herunterladen" rel="nofollow">
					<span class="headline">%title%</a></span><br>%adminedit%
					<abbr><i title="category" class="fa fa-folder-open"></i> %category% &nbsp;
					<i title="filename" class="fa fa-file-o"></i> %filename% &nbsp; 
					<i title="filesize" class="fa fa-expand"></i> %filesize% &nbsp;
					<i title="Downloads" class="fa fa-download"></i> %count% &nbsp; 
					<i title="erstellt/geändert" class="fa fa-calendar-o"></i> %date%</abbr>
					<br><br>%description%</div>%thumb%</div>'
	 	),
	 	'singlepost'		=> array(
	 		'name'			=> __( 'Infobox mit Icon, Rahmen für Post Archive', 'delightful-downloads' ),
	 		'format'		=> '<div class="%class%" style="display:flex;border:1px solid #e1e1e1;width:100%;padding:4px;border-radius:3px">
					<div style="display:inline-block;min-width:60px;width:60px">%icon%</div>
					<div style="display:inline-block;width:100%;min-width:70%"><a href="%url%" title="%ext%-Datei&#10;herunterladen" rel="nofollow">
					<span class="headline">'.__( 'Datei herunterladen', 'delightful-downloads' ).'</a></span><br>%adminedit%
					<abbr><i title="category" class="fa fa-folder-open"></i> %category% &nbsp;
					<i title="filename" class="fa fa-file-o"></i> %filename% &nbsp; 
					<i title="filesize" class="fa fa-expand"></i> %filesize% &nbsp;
					<i title="Downloads" class="fa fa-download"></i> %count%</abbr></div></div>'
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
 * Shortcode Buttons
 */
function dedo_get_shortcode_buttons() {
	
	$buttons =  array(
		'black'		=> array(
			'name'		=> __( 'Black', 'delightful-downloads' ),
			'class'		=> 'button-black'
		),
		'blue'		=> array(
			'name'		=> __( 'Blue', 'delightful-downloads' ),
			'class'		=> 'button-blue'
		),
		'grey'		=> array(
			'name'		=> __( 'Grey', 'delightful-downloads' ),
			'class'		=> 'button-grey'
		),
		'green'		=> array(
			'name'		=> __( 'Green', 'delightful-downloads' ),
			'class'		=> 'button-green'
		),
		'purple'	=> array(
			'name'		=> __( 'Purple', 'delightful-downloads' ),
			'class'		=> 'button-purple'
		),
		'red'		=> array(
			'name'		=> __( 'Red', 'delightful-downloads' ),
			'class'		=> 'button-red'
		),
		'yellow'	=> array(
			'name'		=> __( 'Yellow', 'delightful-downloads' ),
			'class'		=> 'button-yellow'
		)
	);
	return apply_filters( 'dedo_get_buttons', $buttons );
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
	 		'name'				=> __( 'Title (Date)', 'delightful-downloads' ),
	 		'format'			=> '<a href="%url%" title="%title% (%date%)" rel="nofollow" class="%class%">%title% (%date%)</a>'
	 	),
	 	'title_count'		=> array(
	 		'name'				=> __( 'Title (Count)', 'delightful-downloads' ),
	 		'format'			=> '<a href="%url%" title="%title% (Downloads: %count%)" rel="nofollow" class="%class%">%title% (Downloads: %count%)</a>'
	 	),
	 	'title_filesize'	=> array(
	 		'name'				=> __( 'Title (File size)', 'delightful-downloads' ),
	 		'format'			=> '<a href="%url%" title="%title% (%filesize%)" rel="nofollow" class="%class%">%title% (%filesize%)</a>'
	 	),
	 	'title_ext_filesize'=> array(
	 		'name'				=> __( 'Title (Extension, File size)', 'delightful-downloads' ),
	 		'format'			=> '<a href="%url%" title="%title% (%ext%, %filesize%)" rel="nofollow" class="%class%">%title% (%ext%, %filesize%)</a>'
	 	),
	 	'title_date_ext_filesize'=> array(
	 		'name'				=> __( 'Title (Date, Extension, File size)', 'delightful-downloads' ),
	 		'format'			=> '<a href="%url%" title="%title% (%date%, %ext%, %filesize%)" rel="nofollow" class="%class%">%title% (%date%, %ext%, %filesize%)</a>'
	 	),
	 	'title_ext_filesize_count'=> array(
	 		'name'				=> __( 'Title (Date, Extension, File size,count)', 'delightful-downloads' ),
	 		'format'			=> '<a href="%url%" title="%title% (%date%, %ext%, %filesize%, %count%x)" rel="nofollow" class="%class%">%title% (%date%, %ext%, %filesize%, %count%x)</a>'
	 	),
	 	'icon_title_ext_filesize_count'=> array(
	 		'name'				=> __( 'Title (Icon, Category, Filesize)', 'delightful-downloads' ),
	 		'format'			=> '<div style="display:flex;width:100%">
					<div style="display:inline-block;min-width:60px;width:60px">%icon%</div>
					<div style="display:inline-block;width:100%;min-width:70%"><a href="%url%" title="%ext%-Datei&#10;herunterladen" rel="nofollow">
					<span class="headline">%title%</a></span><br>%adminedit%
					<abbr><i title="category" class="fa fa-folder-open"></i> %category% &nbsp;
					<i title="filesize" class="fa fa-expand"></i> %filesize%</abbr></div></div>'
	 	),
	 	'infoboxlist'=> array(
	 		'name'				=> __( 'Infoboxliste (Icon,Date,Extension,Filesize,count,Thumb,descript)', 'delightful-downloads' ),
	 		'format'			=> '<div style="display:flex;width:100%">
					<div style="display:inline-block;min-width:60px;width:60px">%icon%</div>
					<div style="display:inline-block;width:100%;min-width:70%"><a href="%url%" title="%ext%-Datei&#10;herunterladen" rel="nofollow">
					<span class="headline">%title%</a></span><br>%adminedit%
					<abbr><i title="category" class="fa fa-folder-open"></i> %category% &nbsp;
					<i title="filename" class="fa fa-file-o"></i> %filename% &nbsp; 
					<i title="filesize" class="fa fa-expand"></i> %filesize% &nbsp;
					<i title="Downloads" class="fa fa-download"></i> %count% &nbsp; 
					<i title="erstellt/geändert" class="fa fa-calendar-o"></i> %date%</abbr>
					<div class="entry-content">%description%</div></div>%thumb%</div>'
	 	)
	);

	return apply_filters( 'dedo_get_lists', $lists );
}

/**
 * Replace Wildcards
 */
 function dedo_search_replace_wildcards( $string, $id ) {
 	//adminedit
 	if ( strpos( $string, '%adminedit%' ) !== false ) {
 		if(current_user_can('administrator')) {
			$datetime = new DateTime('now');
			$hashwert = md5( intval($id) + intval($datetime->format('Ymd')) );
			$oneday = '<input type="text" title="Copy '.$datetime->format('d.m.Y').' Onedaypass für heute&#10;'.$hashwert.'" class="copy-to-clipboard" style="direction:rtl;cursor:pointer;font-size:0.7em;width:80px;height:20px" value="' . get_site_url() . '?sdownload=' . esc_attr( $id ) .  '&code='. $hashwert . '" readonly> &nbsp; ';
			$oneday .= '<p class="description" style="display: none;">' . __( 'One day pass copied to clipboard.', 'delightful-downloads' ) . '</p>';

			$string = str_replace( '%adminedit%', ' <a href="'. get_home_url() . '/wp-admin/post.php?post='.$id.'&action=edit"><i title="'. __( 'edit this download', 'delightful-downloads' ) . '" class="fa fa-pencil"></i></a> &nbsp; '.$oneday, $string );
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
		if (!empty($post_terms)) $value = $post_terms[0]->name; else $value='';
		$string = str_replace( '%category%', $value, $string );
 	}
 	// beschreibung
 	if ( strpos( $string, '%description%' ) !== false ) {
 		$value = get_the_excerpt( $id );
 		$string = str_replace( '%description%', $value, $string );
 	}
	// post thumbnail - Beitragsbild mit img-zoom on hover
 	if ( strpos( $string, '%thumb%' ) !== false ) {
 		$value = '<div style="max-width:200px;border:1px none;float:right;"><img class="img-zoom" style="transform-origin: center right" src="' . get_the_post_thumbnail_url( $id ) . '"></div>';
 		$string = str_replace( '%thumb%', $value, $string );
 	}
	// date
 	if ( strpos( $string, '%date%' ) !== false ) {
 		// $moddate = get_the_modified_date( apply_filters( 'dedo_shortcode_date_format', '' ), $id );
 		$moddate = get_the_modified_date( 'l, d.m.Y H:i:s', $id );
		//$credate = get_the_date( apply_filters( 'dedo_shortcode_date_format', '' ), $id );
		$credate = get_the_date( 'l, d.m.Y H:i:s', $id );
		if ( $moddate != $credate ) {$value = $credate . ' | ' . $moddate;} else {$value = $credate;}
		if ( !is_user_logged_in() ) {$value = $moddate;}
		$value .= ' vor '. human_time_diff(intval(get_the_modified_date( 'U, d.m.Y H:i:s', $id )), time());
		$string = str_replace( '%date%', $value, $string );
 	}
 	// filesize
 	if ( strpos( $string, '%filesize%' ) !== false ) {
 		$value = size_format( get_post_meta( $id, '_dedo_file_size', true ), 1 );
 		$string = str_replace( '%filesize%', $value, $string );
 	}
 	// downloads
 	if ( strpos( $string, '%count%' ) !== false ) {
 		$value = number_format_i18n( get_post_meta( $id, '_dedo_file_count', true ) );
 		$string = str_replace( '%count%', $value, $string );
 	}
 	// file name
 	if ( strpos( $string, '%filename%' ) !== false ) {
 		$value = dedo_get_file_name( get_post_meta( $id, '_dedo_file_url', true ) );
 		$string = str_replace( '%filename%', $value, $string );
 	}
 	// file extension
 	if ( strpos( $string, '%ext%' ) !== false ) {
 		$value = strtoupper( dedo_get_file_ext( get_post_meta( $id, '_dedo_file_url', true ) ) );
 		$string = str_replace( '%ext%', $value, $string );
 	}
  	// file icon
 	if ( strpos( $string, '%icon%' ) !== false ) {
 		$ffile = ( get_post_meta( $id, '_dedo_file_url', true ) );
		$value = dedo_get_file_icon( $ffile );
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
 * Download Link
 * Generate download link based on provided id.
 */
function dedo_download_link( $id ) {
	global $dedo_options;
	$output = esc_html( home_url( '?' . $dedo_options['download_url'] . '=' . $id ) );
	return apply_filters( 'dedo_download_link', $output );
}

/**
 * Check for valid download
 */
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
 *
 * @since  1.3
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
 *
 * @since  1.0
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
	$ip_address = long2ip(ip2long($ip_address) & 0xFFFFFF00);
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
	$file = wp_check_filetype( $path );
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
	$file = wp_check_filetype( $path );
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
	// Load css sprite for file type icons
	wp_enqueue_style( 'filetye-style', DEDO_PLUGIN_URL . 'assets/css/filetypes.min.css' );
	$icon = '<i class="ftyp ftyp-'.strtolower($ext).'" title="'.$ext.'-Datei&#10;'.$fmime.'"></i>';
	return $icon;
}