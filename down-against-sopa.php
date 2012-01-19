<?php
/*
Plugin Name: Down Against SOPA ES
Plugin URI: http://downagainstsopa.com
Description: Esta es una modificación del Down Against Sopa, pero en español y con el tema de http://www.anieto2k.com/sopa.html que es más bonito. 
Version: 1.0.1
Author: Ten-321 Enterprises modificado por Richzendy de http://Richzendy.org
Author URI: http://ten-321.com
License: GPL3
*/
/*  Copyright 2012  Ten-321 Enterprises and Chris Tidd  (email : contact@ctidd.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/    
?>
<?php
/**
 * Determine if the SOPA message should be shown and, if so, do it.
 */
function sopa_redirect() {
	/* Don't redirect if this is the admin area - somewhat redundant, but helpful nonetheless */
	if ( is_admin() )
		return;
		
	$sopa_opts = get_sopa_options();
	$blackout_dates = array_map( 'trim', explode( ',', $sopa_opts['blackout_dates'] ) );
	
	$cookiename = 'seen_sopa_blackout';
	if ( array_key_exists( 'cookie_hash', $sopa_opts ) )
		$cookiename .= '_' . $sopa_opts['cookie_hash'];
	
	/* Don't redirect if they've already seen the blackout page this session */
	if ( isset( $_COOKIE ) && array_key_exists( $cookiename, $_COOKIE ) && empty( $sopa_opts['no_cookie'] ) ) {
		/*wp_die( 'The cookie is already set' );*/
		return;
	}
	/* Don't redirect if this isn't the home page or front page */
	if ( ! is_front_page() && ! is_home() && empty( $sopa_opts['all_pages'] ) ) {
		if ( ! empty( $sopa_opts['page_id'] ) && ! is_page( $sopa_opts['page_id'] ) ) {
			/*wp_die( 'This is not the home/front page' );*/
			return;
		}
	}
	
	// On January 23, 2012 redirect traffic to the protest page.
	if ( in_array( date( 'Y-m-d' ), $blackout_dates ) ) {
		$cookiename = 'seen_sopa_blackout';
		if ( array_key_exists( 'cookie_hash', $sopa_opts ) )
			$cookiename .= '_' . $sopa_opts['cookie_hash'];
		// Meta refresh is the only redirect technique I found consistent enough. It has drawbacks, but it's reliable and simple.
		/*wp_safe_redirect( plugins_url( 'stop-sopa.php', __FILE__ ) );*/
		if ( empty( $sopa_opts['page_id'] ) || ! is_numeric( $sopa_opts['page_id'] ) ) {
			if ( empty( $sopa_opts['no_cookie'] ) )
				setcookie( $cookiename, 1, 0, '/' );
			wp_safe_redirect( plugins_url( 'stop-sopa.php', __FILE__ ), 307 );
		} else if ( is_page( $sopa_opts['page_id'] ) ) {
			if ( empty( $sopa_opts['no_cookie'] ) )
				setcookie( $cookiename, 1, 0, '/' );
			include_once( plugin_dir_path( __FILE__ ) . 'stop-sopa.php' );
		} else {
			wp_safe_redirect( get_permalink( $sopa_opts['page_id'] ), 307 );
		}
		die();
	}
}
add_action( 'template_redirect', 'sopa_redirect', 99 );

/**
 * Retrieve the options
 */
function get_sopa_options() {
	$sopa_opts = get_option( 'sopa_blackout_dates', '2012-01-23,2012-01-18' );
	if ( ! is_array( $sopa_opts ) )
		$sopa_opts = array( 'blackout_dates' => $sopa_opts );
	
	$sopa_opts = array_merge( array(
		'blackout_dates' => '2012-01-23,2012-01-18',
		'backlinks'      => 1,
		'all_pages'      => 1,
		'no_cookie'      => 0,
		'page_id'        => redirect,
		'site_link'      => null,
	), $sopa_opts );
	
	return $sopa_opts;
}

/**
 * Add the SOPA options page to the administration menu
 */
function add_sopa_options_page() {
	add_submenu_page( 'options-general.php', __( 'SOPA Blackout Options' ), __( 'SOPA Options' ), 'manage_options', 'sopa_options_page', 'sopa_options_page_callback' );
	add_action( 'admin_init', 'register_sopa_options' );
}
add_action( 'admin_menu', 'add_sopa_options_page' );

/**
 * Whitelist the SOPA options and set up the options page
 */
function register_sopa_options() {
	register_setting( 'sopa_options_page', 'sopa_blackout_dates', 'sanitize_sopa_opts' );
	add_settings_section( 'sopa_options_section', __( 'SOPA Blackout Options' ), 'sopa_options_section_callback', 'sopa_options_page' );
	add_settings_field( 'sopa_blackout_dates', __( 'Blackout Options:' ), 'sopa_options_field_callback', 'sopa_options_page', 'sopa_options_section' );
}

/**
 * Sanitize the updated SOPA options
 * @param array $input the value of the options
 * @return array the sanitized values
 */
function sanitize_sopa_opts( $input ) {
	$input['backlinks'] = array_key_exists( 'backlinks', $input ) && '1' === $input['backlinks'] ? 1 : 0;
	$input['all_pages'] = array_key_exists( 'all_pages', $input ) && '1' === $input['all_pages'] ? 1 : 0;
	$input['no_cookie'] = array_key_exists( 'no_cookie', $input ) && '1' === $input['no_cookie'] ? 1 : 0;
	if ( empty( $input['page_id'] ) )
		$input['page_id'] = sopa_create_blank_page();
	$input['cookie_hash'] = md5( time() );
	
	return $input;
}

/**
 * Create a new blank page to use as the placeholder
 */
function sopa_create_blank_page() {
	return wp_insert_post( array( 
		'comment_status' => 'closed',
		'pint_status'    => 'closed',
		'post_title'     => __( 'Stop SOPA' ),
		'post_content'   => __( 'This is a placeholder page for this website\'s Stop SOPA message.' ),
		'post_type'      => 'page',
		'post_status'    => 'publish',
	) );
}

/**
 * Output the options page HTML
 */
function sopa_options_page_callback() {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( 'You do not have sufficient permissions to view this page.' );
?>
<div class="wrap">
	<h2><?php _e( 'SOPA Blackout Options' ) ?></h2>
    <form method="post" action="options.php">
    <?php settings_fields( 'sopa_options_page' ) ?>
    <?php do_settings_sections( 'sopa_options_page' ) ?>
    <p><input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>"/></p>
    </form>
</div>
<?php
}

/**
 * Output the message to be displayed at the top of the options section
 */
function sopa_options_section_callback() {
	_e( '<p>Please choose the date(s) on which you would like the SOPA Blackout redirect to occur.</p>' );
	_e( '<p><em>Saving these options will reset all of the SOPA cookies, so visitors will see the SOPA message again even if they have already seen it.</em></p>' );
}

/**
 * Output the HTML for the options form elements
 */
function sopa_options_field_callback() {
	$sopa_opts = get_sopa_options();
	$blackout_dates = array_map( 'trim', explode( ',', $sopa_opts['blackout_dates'] ) );
	$blackout_dates = implode( ', ', $blackout_dates );
?>
<p><label for="sopa_blackout_dates_dates"><strong><?php _e( 'Blackout dates:' ) ?></strong></label><br/>
	<input class="widefat" type="text" value="<?php echo $blackout_dates ?>" name="sopa_blackout_dates[blackout_dates]" id="sopa_blackout_dates_dates"/><br />
<em><?php _e( 'Please enter the dates in YYYY-MM-DD format. Separate multiple dates with commas.' ) ?></em></p>
<p><label for="sopa_hide_backlinks"><strong><?php _e( 'Remove backlinks to plugin sponsors?' ) ?></strong></label>
	<input type="checkbox" name="sopa_blackout_dates[backlinks]" id="sopa_hide_backlinks" value="1"<?php checked( 1, $sopa_opts['backlinks'] ) ?>/></p>
<p><label for="sopa_all_pages"><strong><?php _e( 'Show the SOPA message to visitors the first time they visit your site, no matter which page they land on?' ) ?></strong></label>
	<input type="checkbox" name="sopa_blackout_dates[all_pages]" id="sopa_all_pages" value="1"<?php checked( 1, $sopa_opts['all_pages'] ) ?>/><br />
<em><?php _e( 'By default, only the front page and posts "home" page show the SOPA message. If a visitor lands on an internal page, they won\'t see the SOPA message until they visit the home or front page. Check the box above to replace all pages on your site with the message.' ) ?></em></p><p><em><?php _e( 'If you have the option above checked, but do not check the option below, visitors will only see the message once. Once they click through to visit your site, they will no longer see the SOPA message.' ) ?></em></p>
<p><label for="sopa_no_cookie"><strong><?php _e( 'Don\'t allow visitors to view the regular site when the SOPA message is active:' ) ?></strong></label>
	<input type="checkbox" name="sopa_blackout_dates[no_cookie]" id="sopa_no_cookie" value="1"<?php checked( 1, $sopa_opts['no_cookie'] ) ?>/><br />
<em><?php _e( 'By default, after a visitor has seen the SOPA message, all other visits to your site (including clicking the "Continue to site" link) will show the regular content. If you check the box above, they will see the SOPA message every time they visit your site (as long as it\'s active).' ) ?></em></p>
<p><label for="sopa_blackout_dates[site_link]"><strong><?php _e( 'Link to the following page with the "Continue to site" link' ) ?></strong></label></<br />
<?php
	wp_dropdown_pages( array(
		'name'             => 'sopa_blackout_dates[site_link]',
		'echo'             => 1,
		'show_option_none' => 'Link to the site home page',
		'selected'         => $sopa_opts['site_link'],
	) );
?>
<p><label for="sopa_blackout_dates[page_id]"><strong><?php _e( 'Use the following page for the SOPA message:' ) ?></strong></label><br/>
<?php
	$pages = wp_dropdown_pages( array( 
		'name'             => 'sopa_blackout_dates[page_id]',
		'echo'             => 0,
		'show_option_none' => 'Create a new page (recommended)',
		'selected'         => $sopa_opts['page_id'],
	) );
	$pages = str_replace( '</select>', '<option value="redirect"' . selected( $sopa_opts['page_id'], 'redirect', false ) . '>Redirect to the PHP file (not recommended)</option></select>', $pages );
	echo $pages;
?>
    </select><br />
<em><?php _e( 'This page will be used as a placeholder for the SOPA message. If anyone tries to visit a page that is supposed to redirect to the SOPA message, they will be redirected to the address of the page selected above, and the Stop SOPA message will be displayed there.</em></p><p><em>If you choose "Create a new page", a new blank page will automatically be created with a title of "Stop SOPA". That page will be excluded automatically from any calls to wp_list_pages() and will be automatically removed when the plugin is deactivated.' ) ?></em></p>
<?php
}

/**
 * Attempt to keep the blank SOPA placeholder page from showing up in 
 * 		auto-generated menus
 */
function exclude_sopa_page( $excludes ) {
	$sopa_opts = get_sopa_options();
	
	if ( ! empty( $sopa_opts['page_id'] ) && __( 'Stop SOPA' ) == get_the_title( $sopa_opts['page_id'] ) )
		$excludes[] = $sopa_opts['page_id'];
	
	return $excludes;
}
add_filter( 'wp_list_pages_excludes', 'exclude_sopa_page', 99 );

/**
 * Perform deactivation actions
 * Remove the placeholder page if the user created a new page for this plugin
 * Delete the options from the database
 */
function remove_sopa_placeholder() {
	$sopa_opts = get_sopa_options();
	
	if ( ! empty( $sopa_opts['page_id'] ) && __( 'Stop SOPA' ) == get_the_title( $sopa_opts['page_id'] ) )
		wp_delete_post( $sopa_opts['page_id'], true );
	
	delete_option( 'sopa_blackout_dates' );
}
register_deactivation_hook( __FILE__, 'remove_sopa_placeholder' );
?>
