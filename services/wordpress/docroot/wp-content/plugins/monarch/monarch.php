<?php
/*
 * Plugin Name: Monarch Plugin
 * Plugin URI: http://www.elegantthemes.com
 * Version: 1.2.3
 * Description: Social Media Plugin
 * Author: Elegant Themes
 * Author URI: http://www.elegantthemes.com
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'ET_MONARCH_PLUGIN_DIR', trailingslashit( dirname(__FILE__) ) );
define( 'ET_MONARCH_PLUGIN_URI', plugins_url('', __FILE__) );

class ET_Monarch {
	var $plugin_version = '1.2.3';
	var $db_version = '1.1';
	var $monarch_options;
	var $_options_pagename = 'et_monarch_options';
	var $menu_page;
	var $protocol;

	public static $shortcodes_count = 0;
	public static $total_follows_count = '';
	public static $follow_counts_array = '';

	private static $_this;

	function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( self::$_this ) ) {
			wp_die( sprintf( __( '%s is a singleton class and you cannot create a second instance.', 'Monarch' ),
				get_class( $this ) )
			);
		}

		self::$_this = $this;

		$this->monarch_options = $this->get_options_array();

		$this->protocol = is_ssl() ? 'https' : 'http';

		add_action( 'admin_menu', array( $this, 'add_menu_link' ) );

		add_action( 'admin_init', array( $this, 'set_post_types' ) );

		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'plugins_loaded', array( $this, 'add_localization' ) );

		// Generates the window with social networks
		add_action( 'wp_ajax_generate_modal_ajax', array( $this, 'generate_select_network_modal_window' ) );

		// Generates warning messages
		add_action( 'wp_ajax_generate_modal_warning', array( $this, 'generate_modal_warning' ) );

		// Generates shortcode
		add_action( 'wp_ajax_generate_shortcode_ajax', array( $this, 'generate_shortcode_ajax' ) );

		// Generates Stats
		add_action( 'wp_ajax_get_share_stats_graphs', array( $this, 'get_share_stats_graphs' ) );

		// Updates the stats table
		add_action( 'wp_ajax_add_stats_record_db', array( $this, 'add_stats_record_db' ) );
		add_action( 'wp_ajax_nopriv_add_stats_record_db', array( $this, 'add_stats_record_db' ) );

		// Calculates total share counts
		add_action( 'wp_ajax_get_media_shares_total', array( $this, 'get_media_shares_total' ) );
		add_action( 'wp_ajax_nopriv_get_media_shares_total', array( $this, 'get_media_shares_total' ) );
		add_action( 'wp_ajax_get_shares_single', array( $this, 'get_shares_single' ) );
		add_action( 'wp_ajax_nopriv_get_shares_single', array( $this, 'get_shares_single' ) );

		add_action( 'wp_ajax_get_shares_count', array( $this, 'get_shares_count' ) );
		add_action( 'wp_ajax_nopriv_get_shares_count', array( $this, 'get_shares_count' ) );
		add_action( 'wp_ajax_get_total_shares', array( $this, 'get_total_shares' ) );
		add_action( 'wp_ajax_nopriv_get_total_shares', array( $this, 'get_total_shares' ) );

		add_action( 'wp_ajax_get_follow_counts', array( $this, 'get_follow_counts' ) );
		add_action( 'wp_ajax_nopriv_get_follow_counts', array( $this, 'get_follow_counts' ) );

		add_action( 'wp_ajax_get_follow_total', array( $this, 'get_follow_total' ) );
		add_action( 'wp_ajax_nopriv_get_follow_total', array( $this, 'get_follow_total' ) );

		add_action( 'wp_ajax_generate_all_networks_popup', array( $this, 'generate_all_networks_popup' ) );
		add_action( 'wp_ajax_nopriv_generate_all_networks_popup', array( $this, 'generate_all_networks_popup' ) );

		// Saves settings into database
		add_action( 'wp_ajax_ajax_save_settings', array( $this, 'ajax_save_settings' ) );

		// Exports/imports settings
		add_action( 'admin_init', array( $this, 'process_settings_export' ) );
		add_action( 'admin_init', array( $this, 'process_settings_import' ) );

		add_action( 'wp_ajax_monarch_authorize_network', array( $this, 'api_generate_authorization_url' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts_styles' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );

		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		add_action( 'init', array( $this, 'add_mce_button_filters' ) );

		add_action( 'admin_head', array( $this, 'mce_add_simple_button' ) );

		add_action( 'admin_notices', array( $this, 'api_maybe_get_access_token' ) );

		// Display update notice
		add_action( 'admin_notices', array( $this, 'display_notice' ), 10 );
		add_action( 'admin_notices', array( $this, 'ignore_notice' ), 5 );

		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		add_shortcode( 'et_social_follow', array( $this, 'display_shortcode' ) );
		add_shortcode( 'et_social_share_media', array( $this, 'et_social_share_media' ) );

		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );

		add_action( 'plugins_loaded', array( $this, 'upgrade_plugin' ) );

		$plugin_file = plugin_basename( __FILE__ );
		add_filter( "plugin_action_links_{$plugin_file}", array( $this, 'add_settings_link' ) );

		$this->frontend_register_locations();

		$this->include_options();
	}

	/**
	 * Returns an instance of the object
	 *
	 * @return object
	 */
	static function get_this() {
		return self::$_this;
	}

	public static function get_options_array() {
		return get_option( 'et_monarch_options' ) ? get_option( 'et_monarch_options' ) : array();
	}

	function include_options() {
		require_once( ET_MONARCH_PLUGIN_DIR . 'includes/monarch_options.php' );

		$this->monarch_sections = $monarch_sections;
		$this->sharing_locations_options = $sharing_locations_options;
		$this->sharing_networks_options = $sharing_networks_options;
		$this->sharing_sidebar_options = $sharing_sidebar_options;
		$this->sharing_inline_options = $sharing_inline_options;
		$this->sharing_popup_options = $sharing_popup_options;
		$this->sharing_flyin_options = $sharing_flyin_options;
		$this->sharing_media_options = $sharing_media_options;
		$this->follow_networks_options = $follow_networks_options;
		$this->follow_widget_options = $follow_widget_options;
		$this->follow_shortcode_options = $follow_shortcode_options;
		$this->general_main_options = $general_main_options;
		$this->header_importexport_options = $header_importexport_options;
		$this->header_stats_options = $header_stats_options;
		$this->update_frequency = isset( $this->monarch_options[ 'general_main_update_freq' ] ) ? $this->monarch_options[ 'general_main_update_freq' ] : 0;
	}

	function display_notice() {
		$monarch_options = $this->get_options_array();
		$screen = get_current_screen();

		if ( ( in_array( $screen->base, array( 'tools_page_et_monarch_options', 'plugins' ) ) ) && ( ! isset( $monarch_options['ignore_monarch_fb_notice'] ) ) ) {
			printf( '
				<div class="update-nag">
					<p>
						%1$s %4$s | <a href="%3$s">%2$s</a>
					</p>
				</div>',
				__( 'Due to changes in Facebook\'s API, Monarch must be authorized to obtain follow/share counts from Facebook. Please get an App ID and App Secret from Facebook and save them ', 'Monarch' ),
				__( 'Hide Notice', 'Monarch' ),
				'tools_page_et_monarch_options' === $screen->base ? '?page=et_monarch_options&ignore_monarch_fb_notice=1' : '?ignore_monarch_fb_notice=1',
				sprintf( __( '<a href="%1$s" class="et_social_notice_link">here.</a>', 'Monarch' ), esc_url( admin_url( 'tools.php?page=et_monarch_options#tab_et_social_tab_content_general_main' ) ) )
			);
		}
	}

	function ignore_notice() {
		if ( isset( $_GET['ignore_monarch_fb_notice'] ) && '1' === $_GET['ignore_monarch_fb_notice'] ) {
			$new_options['ignore_monarch_fb_notice'] = '1';
			$this->update_option( $new_options );
		}
	}

	function add_mce_button_filters() {
		add_filter( 'mce_external_plugins', array( $this, 'add_mce_button' ) );
		add_filter( 'mce_buttons', array( $this, 'register_mce_button' ) );
	}

	function add_mce_button( $plugin_array ) {
		global $typenow;

		if ( empty( $typenow ) && isset( $_REQUEST['post_type'] ) ) {
			$typenow = $_REQUEST['post_type'];
		}

		if ( 'page' == $typenow ) {
			wp_enqueue_style( 'monarch-media-shortcode', ET_MONARCH_PLUGIN_URI . '/css/tinymcebutton.css', array(), $this->plugin_version );
			$plugin_array['monarch'] = ET_MONARCH_PLUGIN_URI . '/js/monarch-mce-button.js';
		}

		return $plugin_array;
	}

	function register_mce_button( $buttons ) {
		global $typenow;

		if ( empty( $typenow ) && isset( $_REQUEST['post_type'] ) ) {
			$typenow = $_REQUEST['post_type'];
		}

		if ( 'page' == $typenow ) {
			array_push( $buttons, 'on_media' );
		}

		return $buttons;
	}

	function mce_add_simple_button() {
		global $typenow;

		if ( empty( $typenow ) && isset( $_REQUEST['post_type'] ) ) {
			$typenow = $_REQUEST['post_type'];
		}

		if ( 'page' == $typenow ) {
			wp_print_scripts( 'quicktags' );

			$output = '
				<script type="text/javascript">
				/* <![CDATA[ */
					edButtons[edButtons.length] = new edButton( "ed_monarch_media"
						,"et_social_share_media"
						,"[et_social_share_media]"
						,"[/et_social_share_media]"
						,""
					);
				/* ]]> */
				</script>';

			echo $output;
		}
	}

	function register_widget() {
		require_once( ET_MONARCH_PLUGIN_DIR . 'includes/monarch-widget.php' );
		register_widget( 'MonarchWidget' );
	}

	function add_menu_link() {
		$menu_page = add_submenu_page( 'tools.php', __( 'Monarch Settings', 'Monarch' ), __( 'Monarch Settings', 'Monarch' ), 'manage_options', 'et_monarch_options', array( $this, 'options_page' ) );
		add_action( "admin_print_scripts-{$menu_page}", array( $this, 'plugin_page_js' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'plugin_page_css' ) );
	}

	function add_body_class( $body_class ) {
		$body_class[] = 'et_monarch';

		return $body_class;
	}

	/**
	 * Adds plugin localization
	 * Domain: Monarch
	 *
	 * @return void
	 */
	function add_localization() {
		load_plugin_textdomain( 'Monarch', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	// Add settings link on plugin page
	function add_settings_link( $links ) {
		$settings_link = sprintf( '<a href="tools.php?page=et_monarch_options">%1$s</a>', __( 'Settings', 'Monarch' ) );
		array_unshift( $links, $settings_link );
		return $links;
	}

	function set_post_types() {
		$default_post_types = array( 'post', 'page' );

		$custom_post_types = get_post_types( array(
			'public'   => true,
			'_builtin' => false,
		) );

		$this->monarch_post_types = array_merge( $default_post_types, $custom_post_types );
	}

	function generate_select_network_modal_window() {
		wp_verify_nonce( $_POST['network_modal_nonce'] , 'network_modal' );

		$monarch_options = $this->monarch_options;
		$sharing_networks_options = $this->sharing_networks_options;
		$follow_networks_options = $this->follow_networks_options;

		$current_area = sanitize_text_field( $_POST['area'] );

		$options_array = ${$current_area . '_networks_options'};
		$current_option_name = $current_area . '_networks_networks_sorting';

		$result = sprintf(
			'<div class="et_social_networks_modal %1$s">
				<div class="et_social_inner_container">
					<div class="et_social_modal_header">
						<h1>%3$s</h1>
						<span class="et_social_close"></span>
					</div>
					<div class="social_icons_container %2$s">',
			esc_attr( $current_area ),
			esc_attr( $current_option_name ),
			esc_html__( 'Select networks to add', 'Monarch' )
		);

		foreach( $options_array as $option) {
			if ( isset( $option['value'] ) ) {
				foreach ( $option['value'] as $network => $network_name ) {
					$networks_array = isset( $monarch_options[$current_option_name]['class'] )
						? $monarch_options[$current_option_name]['class']
						: array();

					$result .= sprintf(
						'<div class="et_social_network et_social_icon" data-name="%1$s" data-label="%3$s" data-placeholder="%5$s"%4$s%6$s%7$s%8$s%9$s%10$s>
							<span class="et_social_%1$s et_social_%2$s">
								<a href="#">
									<span class="et_social_networkname">%3$s</span>
								</a>
							</span>
						</div>',
						esc_attr( $network ),
						! in_array( $network, $networks_array ) ? 'nonselectednetwork' : 'selectednetwork',
						esc_attr( $network_name ),
						'like' == $network ? ' data-counts="false" ' : '',
						esc_attr( $option[ 'placeholder' ] ), //#5
						( 'soundcloud' == $network || 'dribbble' == $network )
							? sprintf( ' data-client_id_placeholder="%1$s"',
								'soundcloud' == $network ?
									esc_attr__( 'Client ID', 'Monarch' ) :
									esc_attr__( 'Access Token', 'Monarch' )
							)
							: '',
						( 'vkontakte' == $network || 'facebook' == $network || 'github' == $network || 'youtube' == $network || 'twitter' == $network )
							? sprintf( ' data-client_id_placeholder="%1$s"',
								'vkontakte' == $network
								? esc_attr__( 'User ID', 'Monarch' )
								: esc_attr__( 'Name', 'Monarch' ) ) : '',
						( 'soundcloud' == $network )
							? sprintf( ' data-client_name_placeholder="%1$s"', esc_attr__( 'Name', 'Monarch' ) )
							: '',
						in_array( $network, $this->get_follow_networks_with_api_support() )
							? ' data-api_support="true"'
							: '',
						( 'like' == $network || ( 'sharing' == $current_area && 'twitter' != $network ) )
							? ' data-username="false"'
							: '' //#10
					);
				}
			}
		}

		$result .= sprintf(
			'		</div>
					<div class="et_social_modal_footer">
						<a href="#" class="et_social_apply" data-area="%2$s">%1$s</a>
					</div>
				</div>
			</div>',
			esc_html__( 'Apply', 'Monarch' ),
			esc_attr( $current_option_name )
		);

		die( $result );
	}

	/**
	 * Generates modal warning window for internal messages. Works via php or via Ajax
	 * Ok_link could be a link to particular tab in dashboard, external link or empty
	 */
	function generate_modal_warning( $message = '', $ok_link = '#', $hide_close = false ) {
		$ajax_request = isset( $_POST[ 'message' ] ) ? true : false;

		if ( true === $ajax_request ){
			wp_verify_nonce( $_POST['generate_warning_nonce'] , 'generate_warning' );
		}

		$message = isset( $_POST[ 'message' ] ) ? sanitize_text_field( $_POST[ 'message' ] ) : sanitize_text_field( $message );
		$ok_link = isset( $_POST[ 'ok_link' ] ) ? $_POST[ 'ok_link' ] : $ok_link;
		$hide_close = isset( $_POST[ 'hide_close' ] ) ? (bool) $_POST[ 'hide_close' ] : (bool) $hide_close;



		$result = sprintf(
			'<div class="et_social_networks_modal et_social_warning">
				<div class="et_social_inner_container">
					<div class="et_social_modal_header">%4$s</div>
					<div class="social_icons_container">
						%1$s
					</div>
					<div class="et_social_modal_footer"><a href="%3$s" class="et_social_ok">%2$s</a></div>
				</div>
			</div>',
			esc_html( $message ),
			esc_html__( 'Ok', 'Monarch' ),
			esc_url( $ok_link ),
			false === $hide_close ? '<span class="et_social_close"></span>' : ''
		);

		if ( $ajax_request ){
			echo $result;
			die;
		} else {
			return $result;
		}
	}

	/**
	 *	Generates the shortcode based on the data received from the form via Ajax.
	 *	It's not necessary to save the settings before generating the shortcode.
	 *	After shortcode is generated it's being send to jQuery function which appends it into appropriate text field.
	 */
	function generate_shortcode_ajax() {
		wp_verify_nonce( $_POST['shortcode_nonce'] , 'generate_shortcode' );

		$shortcode_array = str_replace( array( '%5B', '%5D' ), array( '[', ']' ), $_POST[ 'options_shortcode' ] );
		parse_str( $shortcode_array, $monarch_options_shortcode );

		$result = sprintf(
			'[et_social_follow icon_style="%1$s" icon_shape="%2$s" icons_location="%3$s" col_number="%4$s"%5$s%6$s%7$s%8$s%9$s%10$s%11$s%12$s%13$s%14$s%15$s%16$s]',
			esc_attr( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_icon_style' ] ),
			esc_attr( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_icon_shape' ] ),
			esc_attr( trim( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_icons_location' ] ) ),
			esc_attr( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_col_number' ] ),
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_counts' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_counts' ] )
				? ' counts="true"'
				: '',
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_counts' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_counts' ] )
				? sprintf( ' counts_num="%1$s"', esc_attr( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_counts_num' ] ) )
				: '',
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_total' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_total' ] )
				? ' total="true"'
				: '',
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_spacing' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_spacing' ] )
				? ' spacing="true"'
				: '',
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_mobile' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_mobile' ] )
				? ' mobile="true"'
				: '',
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] )
				? ' custom_colors="true"'
				: '',
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] )
				? sprintf( ' bg_color="%1$s"', esc_attr( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_bg_color' ] ) )
				: '',
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] )
				? sprintf( ' bg_color_hover="%1$s"', esc_attr( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_bg_color_hover' ] ) )
				: '',
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] )
				? sprintf( ' icon_color="%1$s"', esc_attr( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_icon_color' ] ) )
				: '',
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_custom_colors' ] )
				? sprintf( ' icon_color_hover="%1$s"', esc_attr( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_icon_color_hover' ] ) )
				: '',
			sprintf( ' outer_color="%1$s"', esc_attr( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_outer_color' ] ) ),
			( isset( $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_network_names' ] ) && true == $monarch_options_shortcode[ 'et_social' ][ 'follow_shortcode_network_names' ] )
				? ' network_names="true"'
				: ''
		);

		die( $result );
	}

	function plugin_page_js() {
		wp_enqueue_script( 'monarch-admin-main', ET_MONARCH_PLUGIN_URI . '/js/admin.js', array( 'jquery' ), $this->plugin_version, true );
		wp_enqueue_script( 'jquery-ui-sortable', array( 'jquery' ), $this->plugin_version, true );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );

		wp_localize_script( 'monarch-admin-main', 'monarchSettings', array(
			'monarch_nonce' => wp_create_nonce( 'monarch_nonce' ),
			'ajaxurl'       => admin_url( 'admin-ajax.php', $this->protocol ),
			'like_text'     => __( 'Likes: ', 'Monarch' ),
			'share_text'    => __( 'Shares: ', 'Monarch' ),
			'shortcode_nonce' => wp_create_nonce( 'generate_shortcode' ),
			'network_modal' => wp_create_nonce( 'network_modal' ),
			'save_settings' => wp_create_nonce( 'save_settings' ),
			'get_stats' => wp_create_nonce( 'get_stats' ),
			'generate_warning' => wp_create_nonce( 'generate_warning' ),
		) );
	}

	function plugin_page_css( $hook ) {
		if ( "tools_page_{$this->_options_pagename}" !== $hook ) {
			return;
		}

		wp_enqueue_style( 'et-open-sans-700', "{$this->protocol}://fonts.googleapis.com/css?family=Open+Sans:700", array(), $this->plugin_version );
		wp_enqueue_style( 'et-monarch-admin', ET_MONARCH_PLUGIN_URI . '/css/admin.css', array(), $this->plugin_version );
	}

	function register_settings() {
		register_setting( 'et_monarch_settings_group', 'monarch_settings' );
	}

	function activate_plugin() {
		// create table to store the statistics
		$this->db_install();
	}

	/**
	 * Creates the table in wordpress database to store the plugin's data for statistics
	 */
	function db_install() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'et_social_stats';

		/*
		 * We'll set the default character set and collation for this table.
		 * If we don't do this, some characters could end up being converted
		 * to just ?'s when saved in our table.
		 */
		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			sharing_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			network varchar(20) NOT NULL,
			action varchar(10) NOT NULL,
			post_id bigint(20) NOT NULL,
			ip_address varchar(45) NOT NULL,
			media_url varchar(2083) NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$this->upgrade_plugin();
	}

	/**
	 * Checks the current DB version and performs required updates if needed.
	 */
	function upgrade_plugin() {
		$monarch_options = $this->monarch_options;
		if ( isset( $monarch_options['db_version'] ) && '1.0' === $monarch_options['db_version'] ) {
			$new_options = array();
			if ( isset( $monarch_options[ 'sharing_flyin_trigger_leave' ] ) && true == $monarch_options[ 'sharing_flyin_trigger_leave' ] ) {
				$new_options['sharing_flyin_trigger_idle'] = true;
				$new_options['sharing_flyin_idle_timeout'] = 15;
			} else {
				$new_options['sharing_flyin_trigger_idle'] = false;
			}

			if ( isset( $monarch_options[ 'sharing_popup_trigger_leave' ] ) && true == $monarch_options[ 'sharing_popup_trigger_leave' ] ) {
				$new_options['sharing_popup_trigger_idle'] = true;
				$new_options['sharing_popup_idle_timeout'] = 15;
			} else {
				$new_options['sharing_popup_trigger_idle'] = false;
			}
		}

		$new_options['db_version'] = $this->db_version;
		$this->update_option( $new_options );
	}

	/**
	 * Handles data received via ajax and invokes the add_stats_row function.
	 */
	function add_stats_record_db() {
		wp_verify_nonce( $_POST['add_stats_nonce'] , 'add_stats' );

		$stats_data_json = str_replace( '\\', '' ,  $_POST[ 'stats_data_array' ] );
		$stats_data_array = json_decode( $stats_data_json, true );
		$result = $this->add_stats_row( $stats_data_array[ 'network' ], $stats_data_array[ 'post_id' ], $_SERVER[ 'REMOTE_ADDR' ], '', $stats_data_array[ 'action' ], $stats_data_array[ 'media_url' ] );

		die( $result );
	}

	/**
	 * Inserts the data into statictics table.
	 */
	function add_stats_row( $network, $post_id, $ip_address, $sharing_date = '', $action = 'share', $media_url = '' ) {
		global $wpdb;

		$row_added = false;

		$table_name = $wpdb->prefix . 'et_social_stats';

		$sharing_date = '' !== $sharing_date ? $sharing_date : current_time( 'mysql' );

		// construct sql query to get count of like/share/follow from the same ip address
		$sql = "SELECT COUNT(*) FROM $table_name WHERE action = %s AND network= %s AND ip_address = %s";
		$sql_args = array(
			$action,
			$network,
			$ip_address
		);

		// shares and likes related to particular posts, but follows aren't,
		// therefore if the action != follows, then add the post_id into sql.
		if ( 'follow' !== $action ) {
			$sql .= " AND post_id = %d";
			$sql_args[] = $post_id;
		}

		if ( 'media' == $action ) {
			$sql .= " AND media_url like %s";
			$sql_args[] = $media_url;
		}

		// if user already liked/followed/shared it - do nothing, otherwise - add new record into DB.
		if ( 0 == $wpdb->get_var( $wpdb->prepare( $sql, $sql_args ) ) ) {
			$wpdb->insert(
				$table_name,
				array(
					'sharing_date' => sanitize_text_field( $sharing_date ),
					'network'      => sanitize_text_field( $network ),
					'action'       => sanitize_text_field( $action ),
					'post_id'      => (int) $post_id,
					'ip_address'   => sanitize_text_field( $ip_address ),
					'media_url'    => esc_url_raw( $media_url ),
				),
				array(
					'%s', // sharing_date
					'%s', // network
					'%s', // action
					'%d', // post_id
					'%s', // ip_address
					'%s', // media_url
 				)
			);

			if ( 'like' == $network ){
				//update likes in post_meta
				$this->get_likes_count( $post_id, true );
			}

			$row_added = true;
		}

	return $row_added;
	}


	/**
	* Adds meta box with Monarch options for each selected post type
	*/
	function add_meta_box() {
		$monarch_options = $this->monarch_options;

		if ( isset( $monarch_options[ 'sharing_locations_manage_locations' ] ) && '' != $monarch_options[ 'sharing_locations_manage_locations' ] ) {
			$selected_locations_array = $monarch_options[ 'sharing_locations_manage_locations' ];

			$all_post_types = ! empty( $selected_locations_array ) ? $this->monarch_post_types : array();

			foreach ( $all_post_types as $post_type ) {
				add_meta_box( 'et_monarch_settings', __( 'Monarch Settings', 'Monarch' ), array( $this, 'display_meta_box' ), $post_type );
				add_meta_box( 'et_monarch_sharing_stats', __( 'Monarch Sharing Stats', 'Monarch' ), array( $this, 'display_meta_box_stats' ), $post_type );
			}
		}
	}

	/**
	 * Generate meta box for each applicable post type
	 */
	function display_meta_box( $post ) {
		$monarch_options = $this->monarch_options;

		wp_enqueue_style( 'et_monarch_stats_styles', ET_MONARCH_PLUGIN_URI . '/css/stats-meta-styles.css' );
		wp_register_script( 'monarch-meta-js', ET_MONARCH_PLUGIN_URI . '/js/monarch-post-meta.js', array( 'jquery' ) );
		wp_enqueue_script( 'monarch-meta-js' );
		wp_localize_script( 'monarch-meta-js', 'monarchSettings', array(
			'ajaxurl'       => admin_url( 'admin-ajax.php', $this->protocol ),
			'like_text'     => __( 'Likes: ', 'Monarch' ),
			'share_text'    => __( 'Shares: ', 'Monarch' ),
		));

		wp_nonce_field( 'et_monarch_meta_box', 'et_monarch_meta_box_nonce' );

		$current_post_type = get_post_type( get_the_ID() );

		$selected_locations_array = $monarch_options[ 'sharing_locations_manage_locations' ];

		$current_page_locations = array();

		/*
		 * Determine which locations to display for particular post type
		 * if post type selected for the particular location, then location will be displayed for that post type
		 */
		foreach ( $selected_locations_array as $single_location ) {

			if ( ! ( 'media' == $single_location && 'post' != $post->post_type ) ){
				$current_option_name = 'sharing_' . $single_location . '_post_types';
				$current_option_value = ( isset( $monarch_options[ $current_option_name ] ) && '' != $monarch_options[ $current_option_name ] )
					? $monarch_options[ $current_option_name ]
					: array();
				$current_page_locations[] = $single_location;

				$current_page_location_checked[] = in_array( $current_post_type, $current_option_value )
					? true
					: false;
			}
		}

		$selected_areas = get_post_meta( get_the_ID(), '_et_monarch_override', true )
			? get_post_meta( get_the_ID(), '_et_monarch_display', true )
			: false;

		$monarch_override = '' !== get_post_meta( get_the_ID(), '_et_monarch_override', true ); ?>
		<label style="padding-bottom: 5px; display: block;" for="monarch-override-locations">
			<input type="checkbox" name="monarch-override-locations" id="monarch-override-locations" value="monarch-override-locations" <?php checked( $monarch_override ); ?> />
			<?php _e( 'Enable Monarch Settings Override', 'Monarch' ); ?>
		</label>

		<div id="monarch_settings_box"<?php if ( ! $monarch_override ) { echo ' style="display:none;"'; } ?>>
			<div style="margin: 13px 0 11px 4px;">
				<p><?php esc_html_e( 'Check or Uncheck any of the below items to override their appearance on this page', 'Monarch' ); ?></p>
			</div>

		<?php
			$i = 0;
			foreach ( $current_page_locations as $location ) {
				$checked = false;

				if ( 'none' !== $selected_areas ) {
					if ( false != $selected_areas ) {
						if ( in_array( $location, $selected_areas ) ) {
							$checked = true;
						}
					} else {
						if ( true == $current_page_location_checked[$i] ) {
							$checked = true;
						}
					}
				}

				printf(
					'<label style="padding-bottom: 5px; display: block; padding-left: 10px;" for="%1$s">
						<input type="checkbox" name="monarch-location[]" id="%1$s" value="%2$s" %3$s />
						%4$s
					</label>',
					esc_attr( "monarch-location-{$location}" ),
					esc_attr( $location ),
					checked( $checked, true, false ),
					esc_html( $location )
				);

				$i++;
			}
		?>
		</div>
	<?php
	}

	function display_meta_box_stats() {
		$post_id = get_the_ID();
		$monarch_options = $this->monarch_options;
		$display_likes = false;
		$selected_networks = array();
		$networks_list = '';

		if ( ! empty( $monarch_options[ 'sharing_networks_networks_sorting' ] ) ) {
			$i = 0;
			foreach ( $monarch_options[ 'sharing_networks_networks_sorting' ][ 'class' ] as $network ) {
				$label = $monarch_options[ 'sharing_networks_networks_sorting' ][ 'label' ][ $i ];
				$selected_networks[$label] = $network;
				$display_likes = 'like' == $network ? true : $display_likes;
				$networks_list .= sprintf( '<li class="et_monarch_network_row">%1$s</li>', $label );
				$i++;
			}
		}

		$week_stats = $this->get_individual_page_stats( $post_id, $selected_networks, false );
		$all_stats_graph = $this->generate_all_networks_stats_page( $this->get_individual_page_stats( $post_id, $selected_networks, true ), $selected_networks );

		printf( '<div class="et_monarch_stats_tabs">
					<a href="#" class="et_monarch_stats_tab tab_all_time et_monarch_active_tab">%1$s</a>
					<a href="#" class="et_monarch_stats_tab tab_past_week">%2$s</a>
				</div>
				<div class="stats_tabs_content">
					<div class="et_monarch_all_time_content et_monarch_tab_content">
						<div class="et_monarch_total">
							<p>%3$s <span>%4$s</span></p>
							%5$s
						</div>
						<div class="et_monarch_graph_container">
						%6$s
						</div>
					</div>
					<div class="et_monarch_past_week_content et_monarch_tab_content et_monarch_hidden_tab_content">
						<div class="et_monarch_total">
							<p>%7$s <span>%4$s</span></p>
							%8$s
						</div>
						<div class="et_monarch_graph_container">
							%9$s
						</div>
					</div>
				</div>
				',
				__( 'All Time Stats', 'Monarch' ),
				__( 'Past Week', 'Monarch' ),
				$this->get_total_stats( 'share', $post_id, $selected_networks ),
				__( 'Total Shares', 'Monarch' ),
				true == $display_likes
					? sprintf( '<p>%1$s <span>%2$s</span></p>', $this->get_total_stats( 'like', $post_id ), __( 'Total Likes', 'Monarch' ) )
					: '',
				$all_stats_graph,
				$week_stats[ 'total_shares_7' ],
				true == $display_likes
					? sprintf( '<p>%1$s <span>%2$s</span></p>', $week_stats[ 'total_likes_7' ], __( 'Total Likes', 'Monarch' ) )
					: '',
				$this->generate_stats_output( 7, 'day', $week_stats )
			);

	}

	/**
	 * When the post is saved, saves our custom data.
	 */
	function save_meta_box_data( $post_id ) {
		if ( ! isset( $_POST[ 'et_monarch_meta_box_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST[ 'et_monarch_meta_box_nonce' ], 'et_monarch_meta_box' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( isset( $_POST[ 'post_type' ] ) && 'page' == $_POST[ 'post_type' ] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}

		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		if ( isset( $_POST[ 'monarch-override-locations' ] ) ) {
			$selected_locations = isset( $_POST[ 'monarch-location' ] ) ? $_POST[ 'monarch-location' ] : array();
			if ( ! empty( $selected_locations ) ) {
				update_post_meta( $post_id, '_et_monarch_display', array_map( "sanitize_text_field", $selected_locations ) );
			} else {
				update_post_meta( $post_id, '_et_monarch_display', 'none' );
			}
		}

		$monarch_override_value = isset( $_POST[ 'monarch-override-locations' ] ) ? sanitize_text_field( $_POST[ 'monarch-override-locations' ] ) : '';
		update_post_meta( $post_id, '_et_monarch_override', $monarch_override_value );
	}

	function update_option( $update_array ) {
		$monarch_options = $this->monarch_options;
		$updated_options = array_merge( $monarch_options, $update_array );
		update_option( 'et_monarch_options', $updated_options );
	}

	function ajax_save_settings( $options = array() ) {
		wp_verify_nonce( $_POST['save_settings_nonce'], 'save_settings' );
		$options = $_POST['options'];
		$error_message = $this->process_and_update_options( $options );
		die( $error_message );
	}

	function process_and_update_options( $options ) {
		$monarch_options = $this->monarch_options;
		$monarch_sections = $this->monarch_sections;
		$sharing_locations_options = $this->sharing_locations_options;
		$sharing_networks_options = $this->sharing_networks_options;
		$sharing_sidebar_options = $this->sharing_sidebar_options;
		$sharing_inline_options = $this->sharing_inline_options;
		$sharing_popup_options = $this->sharing_popup_options;
		$sharing_flyin_options = $this->sharing_flyin_options;
		$sharing_media_options = $this->sharing_media_options;
		$follow_networks_options = $this->follow_networks_options;
		$follow_widget_options = $this->follow_widget_options;
		$follow_shortcode_options = $this->follow_shortcode_options;
		$general_main_options = $this->general_main_options;

		$error_message = '';
		if ( !is_array( $options ) ) {
			$processed_array = str_replace( array( '%5B', '%5D' ), array( '[', ']' ), $options );
			parse_str( $processed_array, $output );
			$array_prefix = true;
		} else {
			$output = $options;
			$array_prefix = false;
		}

		if ( isset( $monarch_sections ) ) {
			foreach ( $monarch_sections as $key => $value ) {
				$current_section = $key;
				if ( $key !== 'header' ) {
					if ( isset( $value[ 'contents' ] ) ) {
						foreach( $value[ 'contents' ] as $key => $value ) {
							$options_prefix = $current_section . '_' . $key;
							$options_array = ${$current_section . '_' . $key . '_options'};
							if ( isset( $options_array ) ) {
								foreach( $options_array as $option ) {
									if ( isset( $option[ 'name' ] ) ) {
										$current_option_name = $options_prefix . '_' . $option[ 'name' ];
									}

									switch( $option[ 'type' ] ) {
										case 'multi_select' :
										case 'checkbox_posts' :
											if ( true === $array_prefix ) {
												$monarch_options_temp[ $current_option_name ] = isset( $output['et_social'][ $current_option_name ] )
													? array_map( 'sanitize_text_field', $output['et_social'][ $current_option_name ] )
													: array();
											} else {
												$monarch_options_temp[ $current_option_name ] = isset( $output[ $current_option_name ] )
													? array_map( 'sanitize_text_field', $output[ $current_option_name ] )
													: array();
											}
										break;

										case 'select':
											if ( true === $array_prefix ) {
												$monarch_options_temp[ $current_option_name ] = isset( $output['et_social'][ $current_option_name ] )
															? sanitize_text_field( $output['et_social'][ $current_option_name ] )
															: '';
											} else {
												$monarch_options_temp[ $current_option_name ] = isset( $output[ $current_option_name ] )
															? sanitize_text_field( $output[ $current_option_name ] )
															: '';
											}
										break;

										case 'checkbox':
											if ( true === $array_prefix ) {
												$monarch_options_temp[ $current_option_name ] = isset( $output['et_social'][ $current_option_name ] )
													? in_array( $output['et_social'][ $current_option_name ], array( '1', false ) )
														? sanitize_text_field( $output['et_social'][ $current_option_name ] )
														: false
													: false;
											} else {
												$monarch_options_temp[ $current_option_name ] = isset( $output[ $current_option_name ] )
													? in_array( $output[ $current_option_name ], array( '1', false ) )
														? sanitize_text_field( $output[ $current_option_name ] )
														: false
													: false;
											}
										break;

										case 'input_field':
											if ( true === $array_prefix ) {
												if ( 'number' == $option[ 'subtype' ] ) {
													$monarch_options_temp[ $current_option_name ] = intval( stripslashes( isset( $output['et_social'][ $current_option_name ] )
															? absint( $output['et_social'][ $current_option_name ] )
															: ''
													) );
												} else {
													$monarch_options_temp[ $current_option_name ] =	isset( $output['et_social'][ $current_option_name ] )
																? sanitize_text_field( $output['et_social'][ $current_option_name ] )
																: '';
												}
											} else {
												if ( 'number' == $option[ 'subtype' ] ) {
													$monarch_options_temp[ $current_option_name ] = intval( stripslashes( isset( $output[ $current_option_name ] )
															? absint( $output[ $current_option_name ] )
															: ''
													) );
												} else {
													$monarch_options_temp[ $current_option_name ] =	isset( $output[ $current_option_name ] )
																? sanitize_text_field( $output[ $current_option_name ] )
																: '';
												}
											}
										break;

										case 'text':
										case 'color_picker':
											if ( true === $array_prefix ) {
												$monarch_options_temp[ $current_option_name ] = isset( $output['et_social'][ $current_option_name ] )
															? sanitize_text_field( $output['et_social'][ $current_option_name ] )
															: '';
											} else {
												$monarch_options_temp[ $current_option_name ] = isset( $output[ $current_option_name ] )
															? sanitize_text_field( $output[ $current_option_name ] )
															: '';
											}
										break;

										case 'sorting' :
											if ( true === $array_prefix ) {
												if ( isset( $current_option_name ) && '' != $current_option_name ) {
													if ( isset( $output['et_social'][ $current_option_name ] ) && is_array( $output['et_social'][ $current_option_name ] ) ) {
														foreach ( $output['et_social'][ $current_option_name ] as $key => $value ) {

															foreach ( $value as $_key => $_value ) {
																$value[ $_key ] = sanitize_text_field( $_value );
															}

															$output['et_social'][ $current_option_name ][ $key ] = $value;
														}

														$monarch_options_temp[ $current_option_name ] = $output['et_social'][ $current_option_name ];
													}
												}
											} else {
												if ( isset( $current_option_name ) && '' != $current_option_name ) {
													if ( isset( $output[ $current_option_name ] ) && is_array( $output[ $current_option_name ] ) ) {
														foreach ( $output[ $current_option_name ] as $key => $value ) {

															foreach ( $value as $_key => $_value ) {
																$value[ $_key ] = sanitize_text_field( $_value );
															}

															$output[ $current_option_name ][ $key ] = $value;
														}

														$monarch_options_temp[ $current_option_name ] = $output[ $current_option_name ];
													}
												}
											}
										break;

										case 'select_shape' :
										case 'select_style' :
											if ( true === $array_prefix ) {
												if ( isset( $current_option_name ) && '' != $current_option_name ) {
													$monarch_options_temp[ $current_option_name ] = sanitize_text_field( isset( $output['et_social'][ $current_option_name ] )
														? $output['et_social'][ $current_option_name ]
														: ''
													);
												}
											} else {
												if ( isset( $current_option_name ) && '' != $current_option_name ) {
													$monarch_options_temp[ $current_option_name ] = sanitize_text_field( isset( $output[ $current_option_name ] )
														? $output[ $current_option_name ]
														: ''
													);
												}
											}
										break;
									} // end switch
								} // end foreach( $options_array as $option )
							} //if ( isset( $options_array ) )
						} // end foreach( $value[ 'contents' ] as $key => $value )
					} // end if ( isset( $value[ 'contents' ] ) )
				} // end if ( $key !== 'header' )
			} // end foreach ( $monarch_sections as $key => $value )
		} //end if ( isset( $monarch_sections ) )

		$this->update_option( $monarch_options_temp );

		if ( ! empty( $monarch_options_temp[ 'sharing_locations_manage_locations' ] ) && empty( $monarch_options_temp[ 'sharing_networks_networks_sorting' ] ) ) {
			$error_message = $this->generate_modal_warning( __( 'Please select social networks in "Social Sharing / Networks" settings', 'Monarch' ), '#tab_et_social_tab_content_sharing_networks' );
		}

		return $error_message;
	}

	function options_page() {
		$monarch_options = $this->monarch_options;
		$monarch_sections = $this->monarch_sections;
		$sharing_locations_options = $this->sharing_locations_options;
		$sharing_networks_options = $this->sharing_networks_options;
		$sharing_sidebar_options = $this->sharing_sidebar_options;
		$sharing_inline_options = $this->sharing_inline_options;
		$sharing_popup_options = $this->sharing_popup_options;
		$sharing_flyin_options = $this->sharing_flyin_options;
		$sharing_media_options = $this->sharing_media_options;
		$follow_networks_options = $this->follow_networks_options;
		$follow_widget_options = $this->follow_widget_options;
		$follow_shortcode_options = $this->follow_shortcode_options;
		$general_main_options = $this->general_main_options;
		$monarch_post_types = $this->monarch_post_types;
		$header_importexport_options = $this->header_importexport_options;
		$header_stats_options = $this->header_stats_options;

		echo '
		<div id="et_social_wrapper_outer">
			<div id="et_social_wrapper" class="et_social">
				<div id="et_social_header">
					<div id="et_social_logo" class="et_social_icon_monarch et_social_icon"></div>
					<ul>';
					if ( isset( $monarch_sections[ 'header' ][ 'contents' ] ) ) {
						foreach ( $monarch_sections[ 'header' ][ 'contents' ] as $key => $value ) {
							printf( '<li><a href="#tab_et_social_tab_content_header_%1$s" id="et_social_tab_content_header_%1$s" class="et_social_icon_header_%1$s et_social_icon"><span></span></a></li>',
									esc_attr( $key )
								);
						}
					}
			echo '
					</ul>
				</div>
				<div class="clearfix"></div>

				<div id="et_social_navigation">
					<ul>';
						$menu_count = 0;
						if ( isset( $monarch_sections ) ) {
							foreach ( $monarch_sections as $key => $value ) {
								if ( $key !== 'header') {
									$current_section = $key;
									foreach( $value as $key => $value ) {
										if ( $key == 'title' ){
											printf( '<li><a href="#" class="et_social_icon_%1$s et_social_icon et_social_tab_parent"><span>%2$s</span></a>',
													esc_attr( $current_section ),
													esc_html( $value )
											);
										} else {
											printf( '<ul class="et_social_%1$s_nav">',
												esc_attr( $current_section )
											);
											foreach( $value as $key => $value ) {
												printf( '<li><a href="#tab_et_social_tab_content_%1$s_%2$s" id="et_social_tab_content_%1$s_%2$s" class="et_social_icon_%2$s et_social_icon"><span>%3$s</span></a></li>',
														esc_attr( $current_section ),
														esc_attr( $key ),
														esc_html( $value )
													);
											}
											echo '</ul></li>';
										} // end else
									} // end foreach( $value as $key => $value )
								} // end if ( $key !== 'header')
							} //end foreach ( $monarch_sections as $key => $value )
						} // end if ( isset( $monarch_sections ) )
				echo '</ul>
					</div>

					<div id="et_social_content">
						<form id="et_monarch_options" enctype="multipart/form-data">';
							settings_fields( 'et_monarch_settings_group' );

							if ( isset( $monarch_sections ) ) {
								foreach ( $monarch_sections as $key => $value ) {
									$current_section = $key;

									if ( $key !== 'header' ) {
										foreach( $value[ 'contents' ] as $key => $value ) {
											$current_location = $key;
											$options_prefix = $current_section . '_' . $key;
											$options_array = ${$current_section . '_' . $key . '_options'};
											$sidebar_section = 'sidebar' == $key ? true : false;
											printf( '<div class="et_social_tab_content et_social_tab_content_%1$s_%2$s">',
													esc_attr( $current_section ),
													esc_attr( $key )
												);
												foreach( $options_array as $option) {
													$current_option_name = '';
													if ( isset( $option[ 'name' ] ) ) {
														$current_option_name = $options_prefix . '_' . $option[ 'name' ];
													}

													$current_option_value = isset( $monarch_options[ $current_option_name ] ) ? $monarch_options[ $current_option_name ] : '';

													if ( ! isset( $monarch_options[ $current_option_name ] ) && isset( $option[ 'default' ] ) ) {
														$current_option_value = isset( $option[ 'default_' . $current_location ] ) ? $option[ 'default_' . $current_location ] : $option[ 'default' ];
													}

													switch( $option[ 'type' ] ) {

													case 'select_style' :
														printf(
															'<div class="et_social_row et_social_selection">
																<h2>%1$s</h2>',
															esc_html( $option[ 'title' ] )
														);
														foreach ( $option[ 'value' ] as $effect ){
															printf(
																'<div class="%1$s %2$s et_social_icon et_social_single_selectable %3$s">
																		<ul>
																			%4$s
																		</ul>
																		<input type="radio" class="et_social[%5$s]" name="et_social[%5$s]" value="%6$s" %7$s style="position: absolute; z-index: -1; visibility: hidden;">
																</div>',
																	true === $sidebar_section ? 'et_social_sidebar_style' : 'et_social_style',
																	true === $sidebar_section ? esc_attr( 'et_social_sidebar_' . $effect ) : esc_attr( 'et_social_' . $effect ),
																	$effect === $current_option_value ? 'et_social_selected' : '',
																	true === $sidebar_section ?
																	'<li class="et_social_sidebar_style_tile et_social_icon"></li>
																	<li class="et_social_sidebar_style_tile et_social_icon"></li>
																	<li class="et_social_sidebar_style_tile et_social_icon"></li>'
																	:
																	'<li><i class="et_social_icon"></i><div class="network_label"><div class="et_social_count"><span>100</span></div></div><span class="et_social_overlay"></span></li>
																	<li><i class="et_social_icon"></i><div class="network_label"><div class="et_social_count"><span>200</span></div></div><span class="et_social_overlay"></span></li>
																	<li><i class="et_social_icon"></i><div class="network_label"><div class="et_social_count"><span>300</span></div></div><span class="et_social_overlay"></span></li>',
																	esc_html( $current_option_name ),
																	esc_attr( $effect ),
																	checked( $current_option_value, $effect, false )
																);

														}
														echo '</div>';
													break;

													case 'select_shape' :
														printf(
															'<div class="et_social_row et_social_selection">
																<h2>%1$s</h2>',
															esc_html( $option[ 'title' ] )
														);
														foreach ( $option[ 'value' ] as $shape ){
															printf(
																'<div class="et_social_shape et_social_icon et_social_single_selectable %1$s"><div class="et_social_shape_tile et_social_icon et_social_shape_%2$s"></div><input type="radio" class="et_social[%3$s]" name="et_social[%3$s]" value="%2$s" %4$s style="position: absolute; z-index: -1; visibility: hidden;"></div>',
																	$shape === $current_option_value ? 'et_social_selected' : '',
																	esc_attr( $shape ),
																	esc_attr( $current_option_name ),
																	checked( $current_option_value, $shape, false )
														   );
														}
														echo '</div>';
													break;

													case 'multi_select' :
														echo '<div class="et_social_row et_social_selection">';
																$i = 0;
																$current_option_value = '' == $current_option_value ? array() : $current_option_value;
																foreach ( $option[ 'value' ] as $location => $location_name ){
																	printf( '<div class="et_social_location et_social_multi_selectable  et_social_icon"><div class="et_social_location_tile"><h1>%1$s</h1><div class="et_social_location_content %7$s">%8$s<div class="et_social_location_icons et_social_location_icons_%2$s">%9$s%10$s</div></div><input class="et_social_toggle" type="checkbox" id="et_social[%3$s][%6$s]" name="et_social[%3$s][]" value="%4$s" %5$s></div>',
																			esc_html( $location_name ),
																			esc_attr( $location ),
																			esc_attr( $current_option_name ),
																			esc_attr( $location ),
																			checked( in_array( $location, $current_option_value ), true, false ),
																			esc_attr( $i ),
																			( 'inline' === $location || 'media' === $location ) ? esc_attr( 'et_social_location_content_' . $location )  : '',
																			( 'popup' === $location || 'media' === $location ) ? '' : '</div>',
																			( 'popup' === $location || 'media' === $location ) ? '</div>' : '',
																			( 'media' === $location ) ? '<i class="et_social_icon_image et_social_icon"></i>' : ''
																			);
																	$i++;
																}
														echo '</div>';
													break;

													case 'select' :
														$current_option_list = isset( $option[ 'value_'. $current_location ] ) ? $option[ 'value_'. $current_location ] : $option[ 'value' ];
														printf(
															'<li class="select%3$s"%4$s><p>%1$s</p>
																<select name="et_social[%2$s]">',
																isset( $option['title_' . $current_location] ) ? esc_html( $option['title_' . $current_location] ) : esc_html( $option['title'] ),
																esc_attr( $current_option_name ),
																isset( $option[ 'display_if' ] ) ? ' et_social_hidden_option' : '',
																isset( $option[ 'display_if' ] ) ? ' data-condition="' . esc_attr( $option[ 'display_if' ] ) .  '"': ''
															);

															foreach ( $current_option_list as $actual_value => $display_value ) {
																printf( '<option value="%1$s" %2$s>%3$s</option>',
																	esc_attr( $actual_value ),
																	selected( $actual_value, $current_option_value, false ),
																	esc_html( $display_value )
																);
															}

														echo  '</select>';

														if ( isset( $option[ 'hint_text' ] ) ) {
															printf(
																'<span class="more_info et_social_icon"><span class="et_social_more_text">%1$s</span></span>',
																	esc_html( $option[ 'hint_text' ] )
															);
														}
														echo '</li>';
													break;

													case 'checkbox' :
														printf( '<li class="et_social_checkbox clearfix%5$s"%4$s%6$s><p>%1$s</p><input type="checkbox" id="et_social[%2$s]" name="et_social[%2$s]" value="1" %3$s><label for="et_social[%2$s]"></label>',
																isset( $option['title_' . $current_location] ) ? esc_html( $option['title_' . $current_location] ) : esc_html( $option['title'] ),
																esc_attr( $current_option_name ),
																checked( $current_option_value, 1, false ),
																isset( $option[ 'conditional' ] ) ? ' data-enables_1="' . esc_attr( $options_prefix . '_' . $option[ 'conditional' ] ) . '"' : '',
																isset( $option[ 'conditional' ] ) ? ' et_social_conditional' : '',
																isset( $option[ 'conditional_2' ] ) ? ' data-enables_2="' . esc_attr( $options_prefix . '_' . $option[ 'conditional_2' ] ) . '"' : ''
															);
														if ( isset( $option[ 'hint_text' ] ) ) {
															printf(
																'<span class="more_info et_social_icon"><span class="et_social_more_text">%1$s</span></span>',
																	esc_html( $option[ 'hint_text' ] )
															);
														}
														echo '</li>';
													break;

													case 'input_field' :
														printf(
															'<li class="input clearfix%4$s%7$s" %5$s><p>%1$s</p><input type="%9$s" name="et_social[%2$s]" value="%3$s"%6$s%8$s>',
																isset( $option['title_' . $current_location] ) ? esc_html( $option['title_' . $current_location] ) : esc_html( $option['title'] ),
																esc_attr( $current_option_name ),
																esc_attr( $current_option_value ),
																isset( $option[ 'display_if' ] ) ? ' et_social_hidden_option' : '',
																isset( $option[ 'display_if' ] ) ? ' data-condition="' . esc_attr( $option[ 'display_if' ] ) .  '"': '',
																'number' == $option[ 'subtype' ] ? ' placeholder="0"' : '',
																'text' == $option[ 'subtype' ] ? ' et_social_longinput' : '',
																( isset( $option['class'] )
																	? sprintf( ' class="%1$s"', esc_attr( $option['class'] ) )
																	: ''
																),
																( isset( $option['hide_contents'] )
																	? 'password'
																	: 'text'
																)
														);
														if ( isset( $option[ 'hint_text' ] ) ) {
															printf( '<span class="more_info et_social_icon"><span class="et_social_more_text">%1$s</span></span>',
																(
																	! isset( $option[ 'hint_text_with_links' ] )
																		? esc_html( $option[ 'hint_text' ] )
																		: $option[ 'hint_text' ]
																)
															);
														}
														echo '</li>';
													break;

													case 'checkbox_posts' :
														echo '<li><ul class="inline">';
														$i = 0;
														$current_option_value = '' == $current_option_value ? array() : $current_option_value;
														$post_types = ! empty( $option[ 'value' ] ) ? $option[ 'value' ] : $monarch_post_types;

														if ( isset( $option[ 'include_home' ] ) && true == $option[ 'include_home' ] ) {
															$post_types = array_merge( array( 'home' => 'home' ), $post_types );
														}

														foreach ( $post_types as $post_type ){
															printf( '<li class="et_social_checkbox"><input type="checkbox" id="et_social[%1$s][%4$s]" name="et_social[%1$s][]" value="%3$s" %2$s><label for="et_social[%1$s][%4$s]"></label><p>%3$s</p></li>',
																	esc_attr( $current_option_name ),
																	checked( in_array( $post_type, $current_option_value ), true, false ),
																	esc_attr( $post_type ),
																	esc_attr( $i )
															);
															$i++;
														}
														echo '</ul><div style="clear:both;"></div></li>';
													break;

													case 'sorting' :
														printf(
															'<button class="et_social_icon et_social_addnetwork" data-area="%2$s">%1$s</button>
																<div class="et_social_networks et_social_row et_social_sortable %3$s%4$s" id="sortable_%3$s">',
																esc_html__( 'Add Networks', 'Monarch' ),
																esc_attr( $current_section ),
																esc_attr( $current_option_name ),
																( isset( $monarch_options[ 'follow_networks_use_api' ] ) && true == $monarch_options[ 'follow_networks_use_api' ] ) ? ' et_social_api_enabled' : ''
																);

																if( isset( $monarch_options[ $current_option_name ] ) && '' != $monarch_options[ $current_option_name ] && count( $monarch_options[ $current_option_name ] ) > 0 ) {
																	$networks_count = count( $monarch_options[ $current_option_name ][ 'class' ] );
																	for( $i = 0; $i < $networks_count; $i++ ) {
																		$network_class = $current_option_value[ 'class' ][ $i ];
																		printf( '<div class="et_social_network et_social_icon ui-sortable-handle%8$s%10$s" data-name="%1$s" data-area="%2$s"><span class="et_social_%1$s" ><a href="#" class="et_social_deletenetwork"></a></span><input type="text" class="input_label" placeholder="%1$s" name="et_social[%2$s][label][%3$s]" value="%4$s">%6$s%9$s%7$s%5$s<input class="input_class" type="hidden" name="et_social[%2$s][class][%3$s]" value="%1$s" /></div>',
																				esc_attr( $network_class ),
																				esc_attr( $current_option_name ),
																				esc_attr( $i ),
																				'' !== $current_option_value[ 'label' ][ $i ] ? esc_attr( $current_option_value[ 'label' ][ $i ] ) : esc_attr( $network_class ),
																				( 'follow' == $current_section && 'like' != $current_option_value[ 'class' ][ $i ] )
																				? sprintf( '%4$s<input type="text" class="input_count" placeholder="0" name="et_social[%1$s][count][%2$s]" value="%3$s">',
																						esc_attr( $current_option_name ),
																						esc_attr( $i ),
																						isset( $current_option_value[ 'count' ][ $i ] ) ? esc_attr( $current_option_value[ 'count' ][ $i ] ) : '0',
																						in_array( $network_class, $this->get_follow_networks_with_api_support() ) ? '<p class="et_social_checkmark_holder"></p>' : ''
																				)
																				: '',
																				( ( 'twitter' == $network_class && 'follow' != $current_section ) || ( 'follow' == $current_section && 'like' != $network_class ) )
																					? sprintf( '<input type="text" class="input_name" placeholder="%1$s" name="et_social[%2$s][username][%3$s]" value="%4$s">',
																						esc_attr( $option[ 'placeholder' ] ),
																						esc_attr( $current_option_name ),
																						esc_attr( $i ),
																						esc_attr( $current_option_value[ 'username' ][ $i ] ) )
																					: '',
																				( 'follow' == $current_section && ( 'vkontakte' == $network_class || 'facebook' == $network_class || 'soundcloud' == $network_class || 'dribbble' == $network_class || 'github' == $network_class || 'youtube' == $network_class || 'twitter' == $network_class ) )
																					? sprintf( '<input type="%8$s" class="input_cid" placeholder="%4$s%5$s%6$s%7$s" name="et_social[%1$s][client_id][%2$s]" value="%3$s">',
																						esc_attr( $current_option_name ),
																						esc_attr( $i ),
																						isset( $current_option_value[ 'client_id' ][ $i ] )
																							? esc_attr( $current_option_value[ 'client_id' ][ $i ] )
																							: '',
																						'dribbble' == $network_class ? esc_attr__( 'Access Token', 'Monarch' ) : '',
																						'vkontakte' == $network_class ? esc_attr__( 'User ID', 'Monarch' ) : '',
																						'soundcloud' == $network_class ? esc_attr__( 'Client ID', 'Monarch' ) : '',
																						( 'dribbble' != $network_class && 'vkontakte' != $network_class && 'soundcloud' != $network_class )
																							? esc_attr__( 'Name', 'Monarch' )
																							: '',
																						( in_array( $network_class, array( 'soundcloud', 'dribbble' ) )
																							? 'password'
																							: 'text'
																						)
																					)
																				: '',
																				( 'follow' == $current_section && ( 'vkontakte' == $network_class || 'facebook' == $network_class || 'dribbble' == $network_class || 'github' == $network_class || 'youtube' == $network_class || 'twitter' == $network_class ) ) ? ' et_social_4_fields' : '',
																				( 'soundcloud' == $network_class )
																					? sprintf( '<input type="text" class="input_client_name" placeholder="%4$s" name="et_social[%1$s][client_name][%2$s]" value="%3$s">',
																							esc_attr( $current_option_name ),
																							esc_attr( $i ),
																							isset( $current_option_value[ 'client_name' ][ $i ] )
																								? esc_attr( $current_option_value[ 'client_name' ][ $i ] )
																								: '',
																							esc_attr__( 'Name', 'Monarch' )
																						)
																					: '',
																				( 'soundcloud' == $network_class ) ? ' et_social_5_fields' : '' //#10
																			);
																	}
																}
														echo '</div>';
													break;

													case 'section_start' :
														printf(
															'%5$s<div class="et_social_form et_social_row%2$s"%3$s%4$s>
																%1$s
																%6$s
																<ul>',
																isset( $option[ 'title' ] ) ? sprintf( '<h2>%1$s</h2>', esc_html( $option[ 'title' ] ) ) : '',
																isset( $option[ 'display_if' ] ) ? ' et_social_hidden_option' : '',
																isset( $option[ 'display_if' ] ) ? ' data-condition="' . esc_attr( $option[ 'display_if' ] ) .  '"': '',
																( isset( $current_option_name ) && '' != $current_option_name )
																	? sprintf( ' data-name="et_social[%1$s]"', esc_attr( $current_option_name ) )
																	: '',
																( isset( $option[ 'sub_section' ] ) && true == $option[ 'sub_section' ] )
																	? '<li class="et_social_auto_height">'
																	: '',
																isset( $option[ 'subtitle' ] )
																	? sprintf('<p class="et_social_section_subtitle">%1$s</p>', esc_html( $option[ 'subtitle' ] ) )
																	: ''
															);
													break;

													case 'section_end' :
														printf( '</ul></div>%1$s',
															( isset( $option[ 'sub_section' ] ) && true == $option[ 'sub_section' ] ) ? '</li>' : ''
														);
													break;

													case 'text' :
														printf(
															'<li class="et_social_auto_height">
																<textarea placeholder="%1$s" rows="%2$s" name="et_social[%4$s]"%5$s>%3$s</textarea>
															</li>',
															esc_attr( $option[ 'placeholder' ] ),
															esc_attr( $option[ 'rows' ] ),
															esc_textarea( stripslashes( $current_option_value ) ),
															esc_attr( $current_option_name ),
															( isset( $option['class'] )
																? sprintf( ' class="%1$s"', esc_attr( $option['class'] ) )
																: ''
															)
														);
													break;

													case 'shortcode' :
														printf(
															'<div class="et_social_shortcode_gen et_social_form et_social_row">
																<button class="et_social_icon" id="et_social_shortcode_button">%1$s</button>
																<span class="spinner"></span>
																<textarea placeholder="%2$s" rows="6" id="et_social_shortcode_field"></textarea>
															</div>',
																esc_html( $option[ 'button_text' ] ),
																esc_attr( $option[ 'placeholder' ] )
														);
													break;

													case 'main_title' :
														printf(
															'<div class="et_social_row et_social_selection">
																<h1>%1$s</h1>
																%2$s
															</div>',
															esc_html( $option[ 'title' ] ),
															isset( $option[ 'subtitle' ] )
																? sprintf('<p>%1$s</p>', esc_html( $option[ 'subtitle' ] ) )
																: ''
														);
													break;

													case 'note' :
														printf(
															'<div class="et_social_row et_social_note">
																<h2>%1$s</h2><p><span>%2$s</span></p>
															</div>',
															esc_html__( 'Note:', 'Monarch' ),
															esc_html( $option[ 'text' ] )
														);
													break;

													case 'color_picker' :
														printf(
															'<li class="input clearfix et_social_color_picker">
																<p>%4$s</p>
																<input class="et-monarch-color-picker" type="text" maxlength="7" placeholder="%1$s" name=et_social[%2$s] value="%3$s" />
															</li>',
															esc_attr( $option[ 'placeholder' ] ),
															esc_attr( $current_option_name ),
															esc_attr( $current_option_value ),
															esc_html( $option[ 'title' ] )
														);
													break;

													case 'button' :
														printf(
															'<li class="et_social_action_button">
																<a href="%1$s" class="et_social_icon %2$s">%3$s</a>
																<span class="spinner"></span>
															</li>',
															esc_url( $option[ 'link' ] ),
															esc_html( $option[ 'class' ] ),
															$this->api_is_network_authorized( $option[ 'action' ] )
																? __( 'Re-Authorize', 'Monarch' ) :
																esc_html( $option[ 'title' ] )
														);
													break;
												} // end switch
											} // end foreach( $options_array as $option)
											echo '</div>';
										} // end foreach( $value['contents'] as $key => $value )
									} // end if ( $key !== 'header')
								} // end foreach ( $monarch_sections as $key => $value )
							} // end if ( isset( $monarch_sections ) )
						printf(
							'<div class="et_social_row et_social_save_changes">
								<button class="et_social_icon">%1$s</button>
								<span class="spinner"></span>
							</div>
							<input type="hidden" name="action" value="save_monarch" />',
							esc_html__( 'Save Changes', 'Monarch' )
						);
				echo '</form>';
					if ( isset( $monarch_sections[ 'header' ][ 'contents' ] ) ) {
						foreach ( $monarch_sections[ 'header' ][ 'contents' ] as $key => $value ) {
							$options_array = ${'header_' . $key . '_options'};

							printf(
								'<div class="et_social_tab_content et_social_tab_content_header_%1$s et_social_header_option">',
								esc_attr( $key )
							);
							if ( isset( $options_array ) ) {
								foreach( $options_array as $option ) {
									switch( $option[ 'type' ] ) {

										case 'import_export' :
											echo '<div class="et_social_form et_social_row">
													<h1>' . esc_html( $option[ 'title' ] ) . '</h1>
													<p>' . __( 'You can either export your Monarch Settings or import settings from another install of Monarch below.', 'Monarch' ) . '</p>
												</div>
												<div class="et_social_import_form et_social_row">
													<h2>' . __( 'Export Monarch Settings', 'Monarch' ) . '</h2>
													<p class="et_social_section_subtitle">' . __( 'Export the plugin settings for this site as a .json file. This allows you to easily import the configuration into another site.', 'Monarch' ) . '</p>
													<form method="post">
														<input type="hidden" name="et_social_action" value="export_settings" />
														<p>';
															wp_nonce_field( 'et_social_export_nonce', 'et_social_export_nonce' );
													echo '<button class="et_social_icon et_social_icon_importexport" type="submit" name="submit_export" id="submit_export">' . __( 'Export', 'Monarch' ) . '</button>
														</p>
													</form>
												</div>

												<div class="et_social_form et_social_row">
													<h2>' . esc_html__( 'Import Monarch Settings', 'Monarch' ) . '</h2>
													<div class="et_social_import_form et_social_row">
														<p class="et_social_section_subtitle">' . __( 'Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.', 'Monarch' ) . '</p>
														<form method="post" enctype="multipart/form-data" action="tools.php?page=et_monarch_options#tab_et_social_tab_content_header_importexport">
															<input type="file" name="import_file"/>';
															wp_nonce_field( 'et_social_import_nonce', 'et_social_import_nonce' );
														echo '<button class="et_social_icon et_social_icon_importexport" type="submit" name="submit_import" id="submit_import">' . __( 'Import', 'Monarch' ) . '</button>
															<input type="hidden" name="et_social_action" value="import_settings" />
														</form>
													</div>
												</div>';

										break;

										case 'stats' :
											printf(
												'<h1>%1$s</h1>
												<div id="et_social_globalstats" class="et_social_row">
													<div class="et_social_globalstat et_social_globalstat_shares"><span>%2$s</span><p><span class="et_social_globalstat_label">%3$s</span></p></div>
													<div class="et_social_globalstat et_social_globalstat_likes"><span>%4$s</span><p><span class="et_social_globalstat_label">%5$s</span></p></div>
													<div class="et_social_globalstat et_social_globalstat_follows"><span>%6$s</span><p><span class="et_social_globalstat_label">%7$s</span></p></div>
												</div>
												<div class="et_social_row" id="et_social_stats_container">
												<span class="spinner"></span>
												</div>',
												esc_html( $option[ 'title' ] ),
												esc_html( $this->get_total_stats( 'share' ) ),
												esc_html__( 'Total Shares', 'Monarch' ),
												esc_html( $this->get_total_stats( 'like' ) ),
												esc_html__( 'Total Likes', 'Monarch' ),
												esc_html( $this->get_total_stats( 'follow' ) ),
												esc_html__( 'Follow Activity', 'Monarch' )
											);
										break;

									} // end switch
								} // end foreach( $options_array as $option )
							} // end if ( isset( $options_array ) )
						echo '</div><!-- .et_social_tab_content_header_ -->';
						} // end foreach ( $monarch_sections[ 'header' ][ 'contents' ] as $key => $value )
					} // end if ( isset( $monarch_sections[ 'header' ][ 'contents' ] ) )
					echo '</div>
				</div>
			</div>';
	}

	/**
	 * Detects if there is an access token for a network.
	 * @param  string $network_slug Network name.
	 * @return bool
	 */
	function api_is_network_authorized( $network_slug ) {
		$et_monarch_options = $this->monarch_options;

		$network_slug = strtolower( trim( $network_slug ) );

		return isset( $et_monarch_options['access_tokens'][$network_slug] );
	}

	/**
	 * Generates an authorization url.
	 * It also saves all settings on the plugin page due to the redirect that deletes unsaved data.
	 *
	 * Echoes json_encoded error message(s) on failure or authorization url - on success.
	 */
	function api_generate_authorization_url() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'monarch_nonce' ) ) {
			die;
		}

		$wp_error = new WP_Error();
		$error_messages = '';
		$twitter_authorized = false;

		$authorization_url = '';
		$client_id = '';
		$client_secret = '';

		$network_name = sanitize_text_field( strtolower( trim( $_POST['network_name'] ) ) );

		if ( 'twitter' == $network_name ) {
			$api_key = sanitize_text_field( $_POST['api_key'] );
			$api_secret = sanitize_text_field( $_POST['api_secret'] );
			$token = sanitize_text_field( $_POST['token'] );
			$token_secret = sanitize_text_field( $_POST['token_secret'] );
		} else {
			$client_id = sanitize_text_field( $_POST['client_id'] );
			$client_secret = sanitize_text_field( $_POST['client_secret'] );
		}

		if ( '' === $client_id && 'twitter' !== $network_name ) {
			$wp_error->add( 'missing client_id', __( 'Client ID is not set', 'Monarch' ) );
		}

		if ( '' === $client_secret && 'twitter' !== $network_name ) {
			$wp_error->add( 'missing client_secret', __( 'Client Secret is not set', 'Monarch' ) );
		}

		if ( '' === $network_name ) {
			$wp_error->add( 'missing network_name', __( 'Network Name is not set', 'Monarch' ) );
		}

		switch ( $network_name ) {
			case 'vimeo':
				$authorization_url = 'https://api.vimeo.com/oauth/authorize?response_type=code&scope=public&state=%1$s&client_id=%2$s&redirect_uri=%3$s';

				break;
			case 'instagram':
				$authorization_url = 'https://api.instagram.com/oauth/authorize/?response_type=code&scope=basic&state=%1$s&client_id=%2$s&redirect_uri=%3$s';

				break;
			case 'linkedin':
				$authorization_url = 'https://www.linkedin.com/uas/oauth2/authorization?response_type=code&scope=r_basicprofile&state=%1$s&client_id=%2$s&redirect_uri=%3$s';

				break;
			case 'twitter':
				$monarch_options = $this->monarch_options;
				$i = 0;
				$twitter_index = 0;

				foreach ( $monarch_options[ 'follow_networks_networks_sorting' ][ 'class' ] as $icon ) {
					if ( 'twitter' == $icon ) {
						$twitter_index = $i;
					}
					$i++;
				}

				$twitter_auth = ET_Monarch::get_twitter_followers( $api_key, $api_secret, $token, $token_secret, $twitter_index, true );
				if ( is_numeric( $twitter_auth ) ) {
					$twitter_authorized = true;
					$wp_error->add( 'success', __( 'Successfully authorized', 'Monarch' ) );
				} else {
					$wp_error->add( 'error', $twitter_auth );
				}

				break;
			case 'facebook':
				$authorization_url = 'https://www.facebook.com/dialog/oauth?response_type=code&scope=public_profile&state=%1$s&client_id=%2$s&redirect_uri=%3$s';

				break;
		}

		$error_messages = $wp_error->get_error_messages();

		if ( '' !== $authorization_url ) {
			if ( 'facebook' === $network_name ) {
				$redirect_url = rawurlencode( esc_url( admin_url( 'tools.php?page=et_monarch_options#tab_et_social_tab_content_general_main' ) ) );
			} else {
				$redirect_url = rawurlencode( esc_url( admin_url( 'tools.php?page=et_monarch_options#tab_et_social_tab_content_follow_networks' ) ) );
			}

			$authorization_url = sprintf(
				$authorization_url,
				"{$network_name}_" . wp_create_nonce( "et_authorize_app_{$network_name}" ),
				sanitize_text_field( $client_id ),
				$redirect_url
			);

		} else {
			if ( 'twitter' !== $network_name ) {
				$wp_error->add( 'network is not found', __( sprintf( '%1$s API is not supported in the plugin', esc_html( $network_name ) ), 'Monarch' ) );
			}

			$error_messages = $wp_error->get_error_messages();
		}

		if ( ! empty( $error_messages ) ) {
			$result = array(
				'error_message' => implode( $error_messages, '. ' ),
			);
		} else {
			$result = array(
				'authorization_url' => $authorization_url,
			);
		}

		echo json_encode( $result );

		//execute saving after echo the results, otherwise NULL will be sent as the result.
		if ( '' !== $authorization_url || $twitter_authorized ) {
			if ( isset( $_POST['options'] ) && empty( $error_messages ) || $twitter_authorized ) {
				$this->process_and_update_options( $_POST['options'] );

				if ( $twitter_authorized ) {
					$api_settings = $this->monarch_options;
					$api_settings['access_tokens']['twitter'] = 'authorized';
					$this->update_option( $api_settings );
				}
			}
		}

		die();
	}

	public static function get_twitter_followers( $api_key, $api_secret, $token, $token_secret, $index, $return_error_message = false ) {
		if ( ! class_exists( 'TwitterOAuth' ) ) {
			require_once( ET_MONARCH_PLUGIN_DIR . 'includes/twitter_auth.php' );
		}

		$tweet = new TwitterOAuth( $api_key, $api_secret, $token, $token_secret );
		$monarch_options = ET_Monarch::get_options_array();
		$twitter_name = isset( $monarch_options[ 'follow_networks_networks_sorting' ][ 'client_id' ][ $index ] ) ? $monarch_options[ 'follow_networks_networks_sorting' ][ 'client_id' ][ $index ] : '';
		$followers = $tweet->get( 'followers/ids', array('screen_name' => $twitter_name ) );
		$followers_array = json_decode( $followers, true );
		$result = 0;

		if ( empty( $followers_array['errors'] ) ) {
			$result += count( $followers_array['ids'] );

			if ( 0 !== $followers_array['next_cursor'] ) {
				$next_cursor = $followers_array['next_cursor'];

				while ( 0 !== $next_cursor ) {
					$followers = $tweet->get('followers/ids', array( 'screen_name' => $twitter_name, 'cursor' => $next_cursor ) );
					$followers_array = json_decode( $followers, true );
					$result += count( $followers_array['ids'] );
					$next_cursor = $followers_array['next_cursor'];
				}
			}
		} else {
			$result = true == $return_error_message ? $followers_array['errors'][0]['message'] : false;
		}

		return $result;
	}

	/**
	 * Gets a network access token if we've received state and code parameters back from a network.
	 * @return bool True on success
	 */
	function api_maybe_get_access_token() {
		$screen = get_current_screen();

		if ( "tools_page_{$this->_options_pagename}" !== $screen->id ) {
			return;
		}

		$api_settings = $this->monarch_options;

		// Check if a network returned authorization code
		if ( isset( $_GET['state'] ) && isset( $_GET['code'] ) ) {
			$state = sanitize_text_field( $_GET['state'] );
			$code  = sanitize_text_field( $_GET['code'] );

			$underscore_position = strpos( $state, '_' );

			// Valid nonce should have an underscore ( e.g vimeo_58787324 )
			if ( false !== $underscore_position ) {
				$network_name = substr( $state, 0, $underscore_position );

				$nonce = substr( $state, $underscore_position + 1 );

				// Check if a nonce is valid
				if ( wp_verify_nonce( $nonce, 'et_authorize_app_' . $network_name ) ) {
					switch ( $network_name ) {
						case 'vimeo':
							$access_token_url = 'https://api.vimeo.com/oauth/access_token';

							break;
						case 'instagram':
							$access_token_url = 'https://api.instagram.com/oauth/access_token';

							break;
						case 'linkedin':
							$access_token_url = 'https://www.linkedin.com/uas/oauth2/accessToken';

							break;
						case 'facebook':
							$access_token_url = 'https://graph.facebook.com/v2.3/oauth/access_token';

							break;
					}

					if ( 'facebook' === $network_name ) {
						$options_prefix = 'general_main';
						$redirect_url = admin_url( 'tools.php?page=et_monarch_options#tab_et_social_tab_content_general_main' );
					} else {
						$options_prefix = 'follow_networks';
						$redirect_url = admin_url( 'tools.php?page=et_monarch_options#tab_et_social_tab_content_follow_networks' );
					}


					// Exchange an authotization code for an access token
					$request = wp_remote_post( $access_token_url, array(
						'method'  => 'POST',
						'timeout' => 30,
						'body'    => array (
							'client_id'     => sanitize_text_field( $api_settings["{$options_prefix}_{$network_name}_id"] ),
							'client_secret' => sanitize_text_field( $api_settings["{$options_prefix}_{$network_name}_secret"] ),
							'grant_type'    => 'authorization_code',
							'code'          => sanitize_text_field( $code ),
							'redirect_uri'  => esc_url( $redirect_url ),
						),
					) );

					if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 ) {
						$response = json_decode( wp_remote_retrieve_body( $request ) );

						// If we received a valid access token, update the access_tokens option
						if ( isset( $response->access_token ) ) {
							$api_settings['access_tokens'][$network_name] = sanitize_text_field( $response->access_token );

							$this->update_option( $api_settings );

							return true;
						}
					}
				}
			}
		}

		return false;
	}

	function remove_site_specific_fields( $settings ) {
		$remove_options = array(
			'access_tokens',
			'db_version',
		);

		foreach ( $remove_options as $option ) {
			if ( isset( $settings[$option] ) ) {
				unset( $settings[$option] );
			}
		}

		return $settings;
	}

	function process_settings_export() {

		if( empty( $_POST[ 'et_social_action' ] ) || 'export_settings' != $_POST[ 'et_social_action' ] ) {
			return;
		}

		if( ! wp_verify_nonce( $_POST[ 'et_social_export_nonce' ], 'et_social_export_nonce' ) ) {
			return;
		}

		if( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$monarch_options = $this->monarch_options;

		ignore_user_abort( true );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=monarch-settings-export-' . date( 'm-d-Y' ) . '.json' );
		header( "Expires: 0" );

		echo json_encode( $this->remove_site_specific_fields( $monarch_options ) );
		exit;
	}

	function process_settings_import() {

		if( empty( $_POST[ 'et_social_action' ] ) || 'import_settings' != $_POST[ 'et_social_action' ] ) {
			return;
		}

		if( ! wp_verify_nonce( $_POST[ 'et_social_import_nonce' ], 'et_social_import_nonce' ) ) {
			return;
		}

		if( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$end_array = explode( '.', $_FILES[ 'import_file' ][ 'name' ] );
		$extension = end( $end_array );
		$import_file = $_FILES[ 'import_file' ][ 'tmp_name' ];

		if ( empty( $import_file ) ) {
			echo $this->generate_modal_warning( __( 'Please select .json file for import', 'Monarch' ) );
			return;
		}

		if ( $extension != 'json' ) {
			echo $this->generate_modal_warning( __( 'Please provide valid .json file', 'Monarch' ) );
			return;
		}

		// Retrieve the settings from the file and convert the json object to an array.
		$monarch_settings = (array) json_decode( file_get_contents( $import_file ), true );

		$error_message = $this->process_and_update_options( $monarch_settings );

		if ( !empty( $error_message ) ) {
			echo $this->generate_modal_warning( $error_message );
		} else {
			echo $this->generate_modal_warning( __( 'Options imported successfully.', 'Monarch' ), admin_url( 'tools.php?page=et_monarch_options' ), true );
		}
	}

	/**
	* function converts number >1000 into compact numbers like 1k
	*/
	public static function get_compact_number( $full_number, $network = '' ) {
		$prefix = '';

		if ( 10000 == $full_number && 'googleplus' == $network ) {
			$prefix = '&gt';
		}

		if ( 1000000 <= $full_number ) {
			$full_number = floor( $full_number / 100000 ) / 10;
			$full_number .= 'Mil';
		} elseif ( 1000 < $full_number ) {
			$full_number = floor( $full_number / 100 ) / 10;
			$full_number .= 'k';
		}

		return $prefix . $full_number;
	}

	/**
	* function converts compact numbers like 1k into full numbers like 1000
	*/
	public static function get_full_number( $compact_number ) {

		//support google+ big numbers
		if ( false !== strrpos( $compact_number, '>9999' ) ) {
			$compact_number = 10000;
		}

		if ( false !== strrpos( $compact_number, 'k' ) ) {
			$compact_number = floatval( str_replace( 'k', '', $compact_number ) ) * 1000;
		}
		if ( false !== strrpos( $compact_number, 'Mil' ) ) {
			$compact_number = floatval( str_replace( 'Mil', '', $compact_number ) ) * 1000000;
		}

		return $compact_number;
	}

	function get_individual_page_stats( $page_id = '', $networks = '', $all_stats = false ) {
		$result = '';

		if ( '' !== $page_id && '' !== $networks ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'et_social_stats';

			$sql = "SELECT * FROM $table_name WHERE ( action = %s OR action = %s ) AND post_id = %d";
			$sql_args = array(
				'share',
				'like',
				$page_id
			);

			$i = 0;
			foreach ( $networks as $network ) {
				$operator = 0 < $i ? ' OR ' : ' AND ( ';
				$sql .= "{$operator}network = %s";
				$sql_args[] = $network;
				$i++;
			}

			$sql .= ') ORDER BY sharing_date DESC;';

			$all_stats_rows = $wpdb->get_results( $wpdb->prepare( $sql, $sql_args ), ARRAY_A );

			if ( false == $all_stats ) {
				$result = $this->generate_stats_by_period( 7, 'day', $all_stats_rows );
			} else {
				$result = $all_stats_rows;
			}
		}

		return $result;
	}

	function get_total_stats( $type = '', $page_id = 'all', $networks = 'all' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'et_social_stats';

		if ( '' == $type ) {
			return 0;
		}

		$sql = "SELECT COUNT(*) FROM $table_name WHERE action = %s";
		$sql_args = array(
			$type,
		);

		if ( 'all' !== $page_id ) {
			$sql .= ' AND post_id = %s';
			$sql_args[] = $page_id;
		}

		if ( 'all' !== $networks ) {
			$i = 0;
			foreach ( $networks as $network ) {
				//do not count likes
				if ( 'like' != $network ) {
					$operator = 0 < $i ? ' OR ' : ' AND ( ';
					$sql .= "{$operator}network = %s";
					$sql_args[] = $network;
					$i++;
				}
			}

			$sql .= ');';
		}

		$total_stats = $wpdb->get_var( $wpdb->prepare( $sql, $sql_args ) );

		return $this->get_compact_number( $total_stats );
	}

	function generate_all_networks_stats_page( $stats_data, $networks ) {
		$final_array = array();
		$total_shares = 0;
		$total_likes = 0;
		if ( ! empty( $stats_data ) ) {
			foreach ( $stats_data as $id => $single_record ) {
				if ( 'like' == $single_record['network'] ) {
					$total_likes++;
				} else {
					$total_shares++;
				}
				if ( ! isset( $final_array[$single_record['network']] ) ) {
					$final_array[$single_record['network']] = 1;
				} else {
					$final_array[$single_record['network']]++;
				}
			}
		}
		$output = sprintf( '<div class="et_social_row et_social_note">'	);
		if ( ! empty( $networks ) ) {
			$output .= '<ul class="et_social_graph_alltime et_social_graph">';
				foreach ( $networks as $label => $network ) {
					$shares_count = isset( $final_array[$network] ) ? $final_array[$network] : '0';
					$output .= sprintf(
									'<li>
										<p class="et_monarch_stats_label">%4$s<span class="total_shares_count"> %3$s</span></p>
										<div value="%1$s" class="et_social_icon et_social_%2$s"></div>
									</li>',
									esc_attr( $shares_count ),
									esc_attr( $network ),
									sprintf( '%1$s %2$s',
											esc_html( $shares_count ),
											'like' == $network
															? sprintf( '%1$s', 1 == $shares_count ? __( 'Like', 'Monarch' ) : __( 'Likes', 'Monarch' ) )
															: sprintf( '%1$s', 1 == $shares_count ? __( 'Share', 'Monarch' ) : __( 'Shares', 'Monarch' ) )
											),
									esc_html( $label )
					);
				}
			$output .= '</ul>';
		}
		$output .= '</div>';
		return $output;
	}

	function get_share_stats_graphs() {
		wp_verify_nonce( $_POST['get_stats_nonce'], 'get_stats' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'et_social_stats';

		$sql = "SELECT * FROM $table_name WHERE action = %s OR action = %s ORDER BY sharing_date DESC";
		$sql_args = array(
			'share',
			'like'
		);

		$all_stats_rows = $wpdb->get_results( $wpdb->prepare( $sql, $sql_args ), ARRAY_A );

		$shares_by_days = $this->generate_stats_by_period( 30, 'day', $all_stats_rows );
		$shares_by_month = $this->generate_stats_by_period( 12, 'month', $all_stats_rows );

		//generating output results for the 7 days from the arrays with stats
		$result = $this->generate_stats_output( 7, 'day', $shares_by_days );

		//generating output results for the 30 days from the array with stats
		$result .= $this->generate_stats_output( 30, 'day', $shares_by_days );

		//generating output results for the 12 month from the array with stats
		$result .= $this->generate_stats_output( 12, 'month', $shares_by_month );

		$result .= $this->generate_all_networks_stats( $all_stats_rows );

		$result .= $this->generate_top_10_pages( $all_stats_rows );

		die( $result );
	}

	function generate_top_10_pages( $stats_data ) {
		$output = '';

		if ( ! empty( $stats_data ) ) {
			$shares_by_pages = array();

			foreach ( $stats_data as $data ) {
				if ( 'share' == $data['action'] ) {
					if ( false !== get_post_status( $data['post_id'] ) || -1 == $data['post_id'] ) {
						if ( isset( $shares_by_pages[$data['post_id']] ) ) {
							$shares_by_pages[$data['post_id']]['count']++;
						} else {
							$shares_by_pages[$data['post_id']]['page_id'] = $data['post_id'];
							$shares_by_pages[$data['post_id']]['count'] = 1;
						}
					}
				}
			}
		}
		if ( ! empty( $shares_by_pages ) ) {
			$ordered_pages = $this->sort_array( $shares_by_pages, 'count' );
			$output .= sprintf( '
								<div class="et_social_row">
									<div class="et_social_meta_info">
										<h2>%1$s</h2>
									</div>
									<ul class="et_social_top_pages_stats">
										<li class="et_social_pages_header et_social_single_page_row">
										<span class="et_social_page_title">%2$s</span>
											<span class="et_social_page_shares">%3$s</span>
										</li>',
								__( 'Highest Performing Posts', 'Monarch' ),
								__( 'Post Title', 'Monarch' ),
								__( 'Shares', 'Monarch' )
					);

			for ( $i=0; $i < 10; $i++ ) {
				if ( isset( $ordered_pages[$i] ) ) {
					$output .= sprintf( '<li class="et_social_single_page_row">
											<a href="%1$s" class="et_social_page_title" target="_blank">%2$s</a>
											<span class="et_social_page_shares">%3$s</span>
										</li>',
										'-1' != $ordered_pages[$i]['page_id'] ? esc_attr( get_permalink( $ordered_pages[$i]['page_id'] ) ) : esc_attr( get_home_url() ),
										'-1' != $ordered_pages[$i]['page_id'] ? esc_html( get_the_title( $ordered_pages[$i]['page_id'] ) ) : esc_html__( 'Homepage', 'Monarch' ),
										esc_html( $ordered_pages[$i]['count'] )
							);
				}
			}

			$output .= '</ul></div>';
		}

		return $output;
	}

	function generate_stats_by_period( $period, $day_or_month, $input_data ) {
		$shares_by = array();

		$j = 0;
		$count_total_shares = 0;
		$count_total_likes = 0;

		for( $i = 1; $i <= $period; $i++ ) {
			if ( array_key_exists( $j, $input_data ) ) {
				$count_subtotal = 1;

				while ( array_key_exists( $j, $input_data ) && strtotime( 'now' ) <= strtotime( sprintf( '+ %d %s', $i, 'day' == $day_or_month ? 'days' : 'month' ), strtotime( $input_data[ $j ][ 'sharing_date' ] ) ) ) {

					$shares_by[ $i ][ 'subtotal' ] = $count_subtotal++;

					if( 'like' == $input_data[ $j ][ 'action' ] ) {
						$count_total_likes++;
					} else {
						$count_total_shares++;
					}

					if ( array_key_exists( $i, $shares_by ) && array_key_exists( $input_data[ $j ][ 'network' ], $shares_by[ $i ] ) ) {
						$shares_by[ $i ][ $input_data[ $j ][ 'network' ] ][ 'count' ]++;
					} else {
						$shares_by[ $i ][ $input_data[ $j ][ 'network' ] ][ 'count' ] = 1;
						$shares_by[ $i ][ $input_data[ $j ][ 'network' ] ][ 'action' ] = $input_data[ $j ][ 'action' ];
					}

					$j++;
				}
			}

			// Add total counts for each period into array
			if ( 'day' == $day_or_month ) {
				if ( $i == 7 ) {
					$shares_by[ 'total_shares_7' ] = $count_total_shares;
					$shares_by[ 'total_likes_7' ] = $count_total_likes;
				}

				if ( $i == 30 ) {
					$shares_by[ 'total_shares_30' ] = $count_total_shares;
					$shares_by[ 'total_likes_30' ] = $count_total_likes;
				}
			} else {
				if ( $i == 12 ) {
					$shares_by[ 'total_shares_12' ] = $count_total_shares;
					$shares_by[ 'total_likes_12' ] = $count_total_likes;
				}
			}
		}

		return $shares_by;
	}

	function generate_stats_output( $period, $day_or_month, $data ) {
		$result = sprintf(
			'<div class="et_social_row et_social_note">
				<div class="et_social_meta_info">
					<h2>%1$s</h2>
					<p>
						<span>%2$s%3$s</span>
					</p>
				</div>
				<ul class="et_social_graph_%4$s et_social_graph">',
			sprintf( '%1$s %2$s %3$s',
				esc_html__( 'Past', 'Monarch' ),
				esc_html( $period ),
				'day' == $day_or_month
					? esc_html__( 'Days', 'Monarch' )
					: esc_html__( 'Month', 'Monarch' )
			),
			sprintf( '%1$s %2$s',
				esc_html( $data[ 'total_shares_' . $period ] ),
				esc_html__( ' Total Shares and ', 'Monarch' )
			),
			sprintf( '%1$s %2$s',
				esc_html( $data[ 'total_likes_' . $period ] ),
				esc_html__( 'Total Likes', 'Monarch' )
			),
			esc_attr( $period )
		);
		for ( $i = 1; $i <= $period ; $i++ ) {

			$result .= sprintf( '<li%1$s>',
				$period == $i ? ' class="et_social_graph_last"' : ''
			);

			if ( array_key_exists( $i, $data ) ) {

				$result .= sprintf( '<div value="%1$s">',
					esc_attr( $data[$i][ 'subtotal' ] )
				);

				foreach ( $data[ $i ] as $network => $count ) {
					$result .= sprintf( '<div type="%1$s" value="%2$s" data-action="%3$s" class="et_social_hover_item"></div>',
						esc_attr( $network ),
						esc_attr( $count[ 'count' ] ),
						esc_attr( $count[ 'action' ] )
					);
				}

				$result .= '</div>';
			} else {
				$result .= '<div value="0"></div>';
			}

			$result .= '</li>';
		}

		$result .= '</ul></div>';

		return $result;
	}

	function generate_all_networks_stats( $stats_data ) {
		$final_array = array();
		$total_shares = 0;
		$total_likes = 0;

		if ( ! empty( $stats_data ) ) {
			foreach ( $stats_data as $id => $single_record ) {
				if ( 'like' == $single_record['network'] ) {
					$total_likes++;
				} else {
					$total_shares++;
				}

				if ( ! isset( $final_array[$single_record['network']] ) ) {
					$final_array[$single_record['network']]['name'] = $single_record['network'];
					$final_array[$single_record['network']]['count'] = 1;
				} else {
					$final_array[$single_record['network']]['count']++;
				}
			}
		}

		$output = sprintf(
			'<div class="et_social_row et_social_note">
				<h2>%1$s</h2>
				<p>
					<span>%2$s%3$s</span>
				</p>',
			__( 'All time stats', 'Monarch' ),
			sprintf( '%1$s %2$s',
				esc_html( $total_shares ),
				esc_html__( ' Total Shares and ', 'Monarch' )
			),
			sprintf( '%1$s %2$s',
				esc_html( $total_likes ),
				esc_html__( 'Total Likes', 'Monarch' )
			)
		);

		if ( ! empty( $final_array ) ) {
			$sorted_array = $this->sort_array( $final_array, 'count' );
			$output .= '<ul class="et_social_graph_alltime et_social_graph">';
				foreach ( $sorted_array as $network ) {
					$output .= sprintf(
						'<li><span class="total_shares_count">%3$s</span><div value="%1$s" class="et_social_icon et_social_%2$s"></div></li>',
						esc_attr( $network['count'] ),
						esc_attr( $network['name'] ),
						sprintf( '%1$s <span class="et_social_thin">%2$s</span>',
								esc_html( $network['count'] ),
								'like' == $network['name'] ? __( 'Likes', 'Monarch' ) : __( 'Shares', 'Monarch' )
								)
					);
				}
			$output .= '</ul>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Changes the order of rows in array based on input parameters
	 * @return array
	 */
	function sort_array( $unsorted_array, $orderby, $order = SORT_DESC ) {
		$temp_array = array();
		foreach ( $unsorted_array as $ma ) {
			$temp_array[] = $ma[$orderby];
		}

		array_multisort( $temp_array, $order, $unsorted_array );

		return $unsorted_array;
	}

	public static function get_follow_link( $network, $username ) {
		$full_link = '' !== $username ? $username : '#';

		return $full_link;
	}

	function get_share_link( $network, $media_url = '', $i = 0, $post_link = '', $post_title = '' ) {
		if ( '' !== $network ) {
			$monarch_options = $this->monarch_options;

			if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
				wp_reset_postdata();
			}

			$link = '';

			if ( '' !== $post_link ) {
				$permalink = rawurlencode( $post_link );
			} else {
				$permalink = ( class_exists( 'WooCommerce' ) && is_checkout() || is_front_page() ) ? rawurlencode( get_bloginfo( 'url' ) ) : rawurlencode( get_permalink() );
			}

			if ( '' !== $post_title ) {
				$title = rawurlencode( html_entity_decode( $post_title ) );
			} else {
				$title = ( class_exists( 'WooCommerce' ) && is_checkout() || is_front_page() ) ? rawurlencode( html_entity_decode( get_bloginfo( 'name' ) ) ) : rawurlencode( html_entity_decode( get_the_title() ) );
			}

			switch ( $network ) {
				case 'facebook' :
					$link = sprintf( 'http://www.facebook.com/sharer.php?u=%1$s&t=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'twitter' :
					$link = sprintf( 'http://twitter.com/share?text=%2$s&url=%1$s&via=%3$s',
						esc_attr( $permalink ),
						esc_attr( $title ),
						( ! empty( $this->monarch_options['sharing_networks_networks_sorting']['username'][$i] )
							? $this->monarch_options['sharing_networks_networks_sorting']['username'][$i]
							: ''
						)
					);
				break;

				case 'googleplus' :
					$link = sprintf( 'https://plus.google.com/share?url=%1$s&t=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'pinterest' :
					$link = '' !== $media_url ? sprintf( 'http://www.pinterest.com/pin/create/button/?url=%1$s&media=%2$s&description=%3$s',
						esc_attr( $permalink ),
						urlencode( $media_url ),
						esc_attr( $title )
					) : '#';
				break;

				case 'stumbleupon' :
					$link = sprintf( 'http://www.stumbleupon.com/badge?url=%1$s&title=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'tumblr' :
					$link = sprintf( 'https://www.tumblr.com/share?v=3&u=%1$s&t=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'blogger' :
					$link = sprintf( 'https://www.blogger.com/blog_this.pyra?t&u=%1$s&n=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'myspace' :
					$link = sprintf( 'https://myspace.com/post?u=%1$s',
						esc_attr( $permalink )
					);
				break;

				case 'delicious' :
					$link = sprintf( 'https://delicious.com/post?url=%1$s&title=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'amazon' :
					$link = sprintf( 'http://www.amazon.com/gp/wishlist/static-add?u=%1$s&t=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'printfriendly' :
					$link = sprintf( 'http://www.printfriendly.com/print?url=%1$s&title=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'yahoomail' :
					$link = sprintf( 'http://compose.mail.yahoo.com/?body=%1$s',
						esc_attr( $permalink )
					);
				break;

				case 'gmail' :
					$link = sprintf( 'https://mail.google.com/mail/u/0/?view=cm&fs=1&su=%2$s&body=%1$s&ui=2&tf=1',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'aol' :
					$link = sprintf( 'http://webmail.aol.com/Mail/ComposeMessage.aspx?subject=%2$s&body=%1$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'newsvine' :
					$link = sprintf( 'http://www.newsvine.com/_tools/seed&save?u=%1$s&h=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'hackernews' :
					$link = sprintf( 'https://news.ycombinator.com/submitlink?u=%1$s&t=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'evernote' :
					$link = sprintf( 'http://www.evernote.com/clip.action?url=%1$s&title=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'digg' :
					$link = sprintf( 'http://digg.com/submit?url=%1$s&title=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'livejournal' :
					$link = sprintf( 'http://www.livejournal.com/update.bml?subject=%2$s&event=%1$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'friendfeed' :
					$link = sprintf( 'http://friendfeed.com/?url=%1$s&title=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'buffer' :
					$link = sprintf( 'https://bufferapp.com/add?url=%1$s&title=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'reddit' :
					$link = sprintf( 'http://www.reddit.com/submit?url=%1$s&title=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;

				case 'vkontakte' :
					$link = sprintf( 'http://vk.com/share.php?url=%1$s',
						esc_attr( $permalink )
					);
				break;

				case 'linkedin' :
					$link = sprintf( 'http://www.linkedin.com/shareArticle?mini=true&url=%1$s&title=%2$s',
						esc_attr( $permalink ),
						esc_attr( $title )
					);
				break;
			}

			return $link;
		}
	}

	/*
	 * Creates the url to "Press This" on the WordPress site
	 *
	 * @param string $wp_site_url WordPress Site Url
	 * @param string $page_url    Shared Page Url
	 * @param string $title       Shared Page Title
	 * @param string $text        Shared Page Text
	 *
	 * @return string $link       The url to create a new post using "Press This"
	 */
	function get_wordpress_share_url( $wp_site_url, $page_url, $title, $text ) {
		$link = sprintf( '%1$s/wp-admin/press-this.php?u=%2$s&t=%3$s&s=$4s',
			rawurlencode( $wp_site_url ),
			rawurlencode( $page_url ),
			rawurlencode( $title ),
			rawurlencode( $text )
		);

		return esc_url( $link );
	}

	function get_tumblr_share_url( $title, $page_url ) {
		$link = sprintf( 'http://www.tumblr.com/share?t=%1$s&u=%2$s',
			rawurlencode( $title ),
			rawurlencode( $page_url )
		);

		return esc_url( $link );
	}

	function get_pinterest_followers_count( $account_name ) {
		$count = '';

		$url = sprintf( 'http://www.pinterest.com/%1$s/likes/', $account_name );

		$meta_tags = get_meta_tags( esc_url( $url ) );
		if ( is_array( $meta_tags ) && isset( $meta_tags['pinterestapp:followers'] ) ) {
			$count = (int) $meta_tags['pinterestapp:followers'];
		}

		return $count;
	}

	public static function check_cached_counts( $post_id, $network, $type, $api = false ) {
		$monarch_options = ET_Monarch::get_options_array();
		$is_cached = false;
		$expiration = $monarch_options[ 'general_main_update_freq' ];
		if ( 'like' == $network ) {
			$is_cached = get_post_meta( $post_id, '_et_social_shares_' . $network, true ) ? true : false;
		} else {
			if ( 'share' == $type ) {
				if ( 0 < $expiration ) {
					$share_counts_array = ( $et_social_shares = get_post_meta( $post_id, '_et_social_shares_' . $network, true ) ) ? $et_social_shares : array();
					$force_update = isset( $share_counts_array['force_update'] ) ? (bool) $share_counts_array['force_update'] : false;

					if ( ! empty( $share_counts_array ) && false === $force_update && strtotime( sprintf( '+ %d hours', $expiration ), strtotime( $share_counts_array[ 'last_upd' ] ) ) > strtotime( 'now' ) ) {
						$is_cached = true;
					}
				}
			} else {
				if ( false == $api ) {
					$is_cached = true;
				} else {
					if ( ! in_array( $network, ET_Monarch::get_follow_networks_with_api_support() ) ) {
						$is_cached = true;
					}

					if ( 0 < $expiration ) {
						if ( false != get_transient( 'et_social_follow_counts_' . $network ) ) {
							$is_cached = true;
						}
					}
				}
			}
		}
		return $is_cached;
	}

	function get_shares_count( $network = '', $share_counts_num = 0, $return_div = true, $post_id = '', $url = '', $is_ajax_request = true ) {
		if ( $is_ajax_request ) {
			wp_verify_nonce( $_POST['get_share_counts_nonce'] , 'get_share_counts' );
			$count_data_json = str_replace( '\\', '' ,  $_POST[ 'share_count_array' ] );
			$count_data_array = json_decode( $count_data_json, true );
			$network = sanitize_text_field( $count_data_array[ 'network' ] );
			$share_counts_num = (int) $count_data_array[ 'min_count' ];
			$post_id = (int) $count_data_array[ 'post_id' ];
			$url = $count_data_array[ 'url' ];
		} else {
			$monarch_options = $this->monarch_options;

			if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
				wp_reset_postdata();
			}

			$post_id = '' != $post_id ? $post_id : get_the_ID();
			$url = '' != $url ? $url : get_permalink();
		}

		$share_counts_output = '';
		if ( 'like' == $network ) {
			$share_counts = $this->get_likes_count( (int) $post_id );
		} else {
			$share_counts_array = ( $et_social_shares = get_post_meta( $post_id, '_et_social_shares_' . $network, true ) ) ? $et_social_shares : array();

			if ( $this->check_cached_counts( $post_id, $network, 'share' ) ) {
				$share_counts = (int) $share_counts_array[ 'counts' ];
			} else {
				$share_counts_received = $this->get_shares_number( $network, $url, $post_id );

				if ( false === $share_counts_received ) {
					$share_counts = isset( $share_counts_array[ 'counts' ] ) ? (int) $share_counts_array[ 'counts' ] : 0;
					$share_counts_temp_array[ 'force_update' ] = true;
				} else {
					$share_counts = (int) $share_counts_received;
					$share_counts_temp_array[ 'force_update' ] = false;
				}

				$share_counts_temp_array[ 'counts' ] = (int) $share_counts;
				$share_counts_temp_array[ 'last_upd' ] = date( 'Y-m-d H:i:s' );
				update_post_meta( $post_id, '_et_social_shares_' . $network, $share_counts_temp_array );
			}
		}

		if ( $share_counts >= $share_counts_num ) {
			$share_counts_output = false == $return_div ? $share_counts : sprintf( '<div class="et_social_count"><span>%1$s</span></div>', $this->get_compact_number( (int) $share_counts, $network ) );
		}

		if ( ! $is_ajax_request ) {
			return $share_counts_output;
		} else {
			die( $share_counts_output );
		}
	}

	public static function get_likes_count( $post_id = '', $update = false ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'et_social_stats';

		if ( '' == $post_id ) {
			$monarch_options = ET_Monarch::get_options_array();

			if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
				wp_reset_postdata();
			}

			$post_id = is_singular() ? get_the_ID() : 0;
		}
		if ( get_post_meta( $post_id, '_et_social_shares_like', true ) && false == $update ) {
			$likes_array = get_post_meta( $post_id, '_et_social_shares_like', true );
			$likes_count = $likes_array[ 'counts' ];
		} else {
			$sql = "SELECT COUNT(*) FROM $table_name WHERE action = %s AND post_id = %d";
			$sql_args = array(
				'like',
				$post_id,
			);

			$likes_count = $wpdb->get_var( $wpdb->prepare( $sql, $sql_args ) );
			$likes_counts_temp_array[ 'counts' ] = (int) $likes_count;
			update_post_meta( $post_id, '_et_social_shares_like', $likes_counts_temp_array );
		}

		if ( false === $update ) {
			return ET_Monarch::get_compact_number( $likes_count );
		}
	}

	function get_total_shares() {
		wp_verify_nonce( $_POST['get_total_counts_nonce'], 'get_total_counts' );
		$monarch_options = $this->monarch_options;
		$total_shares = 0;

		if ( isset( $_POST[ 'share_total_count_array' ] ) ) {
			$total_count_data_json = str_replace( '\\', '' ,  $_POST[ 'share_total_count_array' ] );
			$total_count_data_array = json_decode( $total_count_data_json, true );
			$post_id = (int) $total_count_data_array[ 'post_id' ];
			$url = $total_count_data_array[ 'url' ];
		}

		foreach ( $monarch_options[ 'sharing_networks_networks_sorting' ][ 'class' ] as $network ) {
			if ( 'like' != $network ) { //exclude likes from the total shares count
				$total_shares += $this->get_shares_count( $network, 0, false, $post_id, $url, false );
			}
		}

		$output = $this->get_compact_number( (int) $total_shares );
		echo $output;

		die();
	}

	/**
	 * Generates the css for each location (except for the shortcodes) if custom colors defined in Monarch options.
	 * All the css generated by this function added into the <head> with id="et-social-custom-css"
	 */
	function set_custom_css() {
		$monarch_options = $this->monarch_options;
		$custom_css = '';
		if ( isset( $monarch_options[ 'sharing_locations_manage_locations' ] ) && '' != $monarch_options[ 'sharing_locations_manage_locations' ] ) {
			foreach ( $monarch_options[ 'sharing_locations_manage_locations' ] as $location ) {
				// Check whether custom colors defined for particular location, if true - generate css code for that location.
				if ( isset( $monarch_options[ 'sharing_' . $location . '_custom_colors' ] ) && true == $monarch_options[ 'sharing_' . $location . '_custom_colors' ] ) {
					if ( 'sidebar' == $location ) {
						$custom_css .= sprintf( '%1$s%2$s%3$s%4$s',
							( isset( $monarch_options[ "sharing_" . $location . "_bg_color" ] ) && '' !== $monarch_options[ "sharing_" . $location . "_bg_color" ] )
								? sprintf( '.et_monarch .et_social_sidebar_networks li, .et_monarch .et_social_mobile li { background: %1$s; }', esc_html( $monarch_options[ "sharing_" . $location . "_bg_color" ] ) )
								: '',
							( isset( $monarch_options[ "sharing_" . $location . "_bg_color_hover" ] ) && '' !== $monarch_options[ "sharing_" . $location . "_bg_color_hover" ] )
								? sprintf( ' .et_monarch .et_social_sidebar_networks .et_social_icons_container li:hover, .et_monarch .et_social_mobile .et_social_icons_container li:hover { background: %1$s !important; } .et_social_sidebar_border li { border-color: %1$s !important; }',
									esc_html( $monarch_options[ "sharing_" . $location . "_bg_color_hover" ] ) )
								: '',
							( isset( $monarch_options[ "sharing_" . $location . "_icon_color" ] ) && '' !== $monarch_options[ "sharing_" . $location . "_icon_color" ] )
								? sprintf( ' .et_monarch .et_social_sidebar_networks .et_social_icons_container li i, .et_monarch .et_social_sidebar_networks .et_social_icons_container li .et_social_count, .et_monarch .et_social_mobile .et_social_icons_container li i, .et_monarch .et_social_mobile .et_social_icons_container li .et_social_count { color: %1$s; }',
									esc_html( $monarch_options[ "sharing_" . $location . "_icon_color" ] ) )
								: '',
							( isset( $monarch_options[ "sharing_" . $location . "_icon_color_hover" ] ) && '' !== $monarch_options[ "sharing_" . $location . "_icon_color_hover" ] )
								? sprintf( ' .et_monarch .et_social_sidebar_networks .et_social_icons_container li:hover i, .et_monarch .et_social_sidebar_networks .et_social_icons_container li:hover .et_social_count, .et_monarch .et_social_mobile .et_social_icons_container li:hover i, .et_monarch .et_social_mobile .et_social_icons_container li:hover .et_social_count { color: %1$s !important; }',
									esc_html( $monarch_options[ "sharing_" . $location . "_icon_color_hover" ] ) )
								: ''
						);
					} else {
						$location_class = '.et_social_' . $location;
						$custom_css .= sprintf( '%1$s%2$s%3$s%4$s',
							( isset( $monarch_options[ "sharing_" . $location . "_bg_color" ] ) && '' !== $monarch_options["sharing_" . $location . "_bg_color"] )
								? sprintf( ' .et_monarch %1$s .et_social_circle .et_social_icons_container li i, .et_monarch %1$s li { background: %2$s; }',
									esc_html( $location_class ),
									esc_html( $monarch_options[ "sharing_" . $location . "_bg_color" ] )
								)
								: '',
							( isset( $monarch_options[ "sharing_" . $location . "_bg_color_hover" ] ) && '' !== $monarch_options["sharing_" . $location . "_bg_color_hover"] )
								? sprintf( ' .et_monarch %1$s .et_social_circle .et_social_icons_container li:hover i, .et_monarch %1$s .et_social_rounded .et_social_icons_container li:hover, .et_monarch %1$s .et_social_rectangle .et_social_icons_container li:hover { background: %2$s !important; }',
									esc_html( $location_class ),
									esc_html( $monarch_options[ "sharing_" . $location . "_bg_color_hover" ] ) )
								: '',
							( isset( $monarch_options[ "sharing_" . $location . "_icon_color" ] ) && '' !== $monarch_options["sharing_" . $location . "_icon_color"] )
								? sprintf( ' .et_monarch %1$s .et_social_icons_container li i, .et_monarch %1$s .et_social_count, .et_monarch %1$s .et_social_networkname { color: %2$s; }',
									esc_html( $location_class ),
									esc_html( $monarch_options[ "sharing_" . $location . "_icon_color" ] )
								)
								: '',
							( isset( $monarch_options[ "sharing_" . $location . "_icon_color_hover" ] ) && '' !== $monarch_options["sharing_" . $location . "_icon_color_hover"] )
								? sprintf( ' .et_monarch %1$s .et_social_icons_container li:hover i, .et_monarch %1$s .et_social_icons_container li:hover .et_social_count, .et_monarch %1$s .et_social_icons_container li:hover .et_social_networkname { color: %2$s !important; }',
									esc_html( $location_class ),
									esc_html( $monarch_options[ "sharing_" . $location . "_icon_color_hover" ] )
								)
								: ''
						);
					}
				}
			}
		}

		if ( isset( $monarch_options[ 'follow_widget_custom_colors' ] ) && true == $monarch_options[ 'follow_widget_custom_colors' ] ) {
			$custom_css .= sprintf( '%1$s%2$s%3$s%4$s',
				( isset( $monarch_options[ 'follow_widget_bg_color' ] ) && '' !== $monarch_options[ 'follow_widget_bg_color' ] )
					? sprintf( ' .et_monarch .widget_monarchwidget .et_social_networks ul li, .et_monarch .widget_monarchwidget.et_social_circle li i { background: %1$s !important; }',
						esc_html( $monarch_options[ 'follow_widget_bg_color' ] )
					)
					: '',
				( isset( $monarch_options[ 'follow_widget_bg_color_hover' ] ) && '' !== $monarch_options[ 'follow_widget_bg_color_hover' ] )
					? sprintf( ' .et_monarch .widget_monarchwidget.et_social_rounded .et_social_icons_container li:hover, .et_monarch .widget_monarchwidget.et_social_rectangle .et_social_icons_container li:hover, .et_monarch .widget_monarchwidget.et_social_circle .et_social_icons_container li:hover i.et_social_icon { background: %1$s !important; }',
						esc_html( $monarch_options[ 'follow_widget_bg_color_hover' ] )
					)
					: '',
				( isset( $monarch_options[ 'follow_widget_icon_color' ] ) && '' !== $monarch_options[ 'follow_widget_icon_color' ] )
					? sprintf( ' .et_monarch .widget_monarchwidget .et_social_icon, .et_monarch .widget_monarchwidget.et_social_networks .et_social_network_label, .et_monarch .widget_monarchwidget .et_social_sidebar_count { color: %1$s; }',
						esc_html( $monarch_options[ 'follow_widget_icon_color' ] )
					)
					: '',
				( isset( $monarch_options[ 'follow_widget_icon_color_hover' ] ) && '' !== $monarch_options[ 'follow_widget_icon_color_hover' ] )
					? sprintf( ' .et_monarch .widget_monarchwidget .et_social_icons_container li:hover .et_social_icon, .et_monarch .widget_monarchwidget.et_social_networks .et_social_icons_container li:hover .et_social_network_label, .et_monarch .widget_monarchwidget .et_social_icons_container li:hover .et_social_sidebar_count { color: %1$s !important; }',
						esc_html( $monarch_options[ 'follow_widget_icon_color_hover' ] )
					)
					: ''
			);
		}

		if ( isset( $monarch_options[ 'general_main_custom_css' ] ) ) {
			$custom_css .= ' ' . $monarch_options[ 'general_main_custom_css' ];
		}

		printf(
			'<style type="text/css" id="et-social-custom-css">
				%1$s
			</style>',
			stripslashes( $custom_css )
		);
	}

	function get_share_networks_with_api_support() {
		$networks = array(
			'facebook',
			'twitter',
			'linkedin',
			'pinterest',
			'googleplus',
			'stumbleupon',
			'vkontakte',
			'reddit',
			'buffer',
		);

		return $networks;
	}

	function get_shares_number( $social_network, $url, $post_id = '' ) {
		$result = false;

		if ( in_array( $social_network, $this->get_share_networks_with_api_support() ) ) {
			$request_url = '';
			$monarch_options = $this->monarch_options;

			$url = rawurlencode( $url );

			switch ( $social_network ) {
				case 'facebook' :
					if ( isset( $monarch_options['access_tokens']['facebook'] ) ) {
						$request_url = sprintf( 'https://graph.facebook.com/v2.3/?access_token=%1$s&id=', $monarch_options['access_tokens']['facebook'] );
					}

					break;
				case 'twitter' :
					$request_url = 'http://cdn.api.twitter.com/1/urls/count.json?url=';

					break;
				case 'linkedin' :
					$request_url = 'http://www.linkedin.com/countserv/count/share?format=json&url=';

					break;
				case 'pinterest' :
					$request_url = 'http://widgets.pinterest.com/v1/urls/count.json?url=';

					break;
				case 'googleplus' :
					$request_url = 'https://plusone.google.com/_/+1/fastbutton?url=';

					break;
				case 'stumbleupon' :
					$request_url = 'http://www.stumbleupon.com/services/1.01/badge.getinfo?url=';

					break;
				case 'vkontakte' :
					$request_url = 'https://vk.com/share.php?act=count&index=1&format=json&url=';

					break;
				case 'reddit' :
					$request_url = 'http://www.reddit.com/api/info.json?url=';

					break;
				case 'buffer' :
					$request_url = 'https://api.bufferapp.com/1/links/shares.json?url=';

					break;
			}

			$request_url .= $url;

			$theme_request = wp_remote_get( $request_url, array( 'timeout' => 30 ) );

			if ( ! is_wp_error( $theme_request ) && wp_remote_retrieve_response_code( $theme_request ) == 200 ){
				$theme_response = wp_remote_retrieve_body( $theme_request );
				if ( ! empty( $theme_response ) ) {
					if ( 'pinterest' === $social_network ) {
						$theme_response = preg_replace( '/^receiveCount\((.*)\)$/', "\\1", $theme_response );
					}

					if ( 'googleplus' === $social_network ) {
						preg_match( '/<div id="aggregateCount" class="Oy">(.*)<\/div>/', $theme_response, $matches );

						if ( is_array( $matches ) ) {
							$result = $this->get_full_number( $matches[1] );
						}
					} else if ( 'vkontakte' === $social_network ) {
						preg_match( '/VK.Share.count\(1, ([0-9]+)\);/', $theme_response, $matches);

						if ( is_array( $matches ) ) {
							$result = (int) $matches[1];
						}
					} else {
						$count_object = json_decode( $theme_response );
					}

					switch ( $social_network ) {
						case 'buffer' :
							$result = isset( $count_object->shares ) ? (int) $count_object->shares : false;

							break;
						case 'facebook' :
							$result = isset( $count_object->share->share_count ) ? (int) $count_object->share->share_count : false;

							break;
						case 'twitter' :
						case 'linkedin' :
						case 'pinterest' :
							$result = $count_object->count;

							break;
						case 'stumbleupon' :
							$result = isset( $count_object->result->views ) ? (int) $count_object->result->views : false;

							if ( false === $result && isset( $count_object->success ) && true === $count_object->success ) {
								$result = 0;
							}

							break;
						case 'reddit' :
							$score = 0;

							if ( isset( $count_object->data->children ) ) {
								foreach ( $count_object->data->children as $child ) {
									$score += (int) $child->data->score;
								}
							}

							$result = $score;

							break;
						case 'facebook' :
							$result = $count_object->share->share_count;

							break;
					}
				}
			}
		} else {
			global $wpdb;
			$monarch_options = $this->monarch_options;

			$table_name = $wpdb->prefix . 'et_social_stats';

			// construct sql query to get count of shares for the required post
			$sql = "SELECT COUNT(*) FROM $table_name WHERE action = %s AND post_id = %d AND network = %s";
			$sql_args = array(
				'share',
				$post_id,
				$social_network,
			);

			$result = $wpdb->get_var( $wpdb->prepare( $sql, $sql_args ) );
		}

		return $result;
	}

	/**
	 * Returns true if social icons should be displayed on particular page depending on user settings.
	 */
	function check_applicability( $post_types = array(), $location ) {
		$monarch_options = $this->monarch_options;

		if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
			wp_reset_postdata();
		}

		$current_post_limits = get_post_meta( get_the_ID(), '_et_monarch_override', true ) ? true : false; // check whether settings for particular post were overriden.
		$current_post_locations = true === $current_post_limits ? get_post_meta( get_the_ID(), '_et_monarch_display', true ) : array(); // get the array of enabled locations for particular post.
		$current_post_locations = 'none' === $current_post_locations ? array() : $current_post_locations;
		$display_there = false;

		if ( class_exists( 'WooCommerce' ) && is_checkout() && ( 'popup' == $location || 'flyin' == $location ) ) {
			if ( isset( $monarch_options[ 'sharing_' . $location . '_trigger_purchase' ] ) && true == $monarch_options[ 'sharing_' . $location . '_trigger_purchase' ] ) {
				$display_there = true;
			}
		}

		if ( $current_post_limits ) {
			if ( ( in_array( $location, $current_post_locations ) ) && ! ( 'inline' == $location && is_singular( 'product' ) ) ) {
				$display_there = true;
			}
		} else {
			if ( is_front_page() ) {
				if ( ( in_array( 'home', $post_types ) && 'inline' !== $location ) || ( is_page() && in_array( 'home', $post_types ) && 'inline' == $location ) ) {
					$display_there = true;
				}
			} else {
				if ( ! empty( $post_types ) && is_singular( $post_types ) && ! ( 'inline' == $location && is_singular( 'product' ) ) ) {
					$display_there = true;
				}
			}
		}

		return $display_there;
	}

	function generate_pinterest_picker(){
		$monarch_options = $this->monarch_options;
		$need_pinterest_modal = false;

		//check whether Pinterest network selected
		if ( ! empty( $monarch_options[ 'sharing_networks_networks_sorting' ] ) ) {
			foreach ( $monarch_options[ 'sharing_networks_networks_sorting' ][ 'class' ] as $network ) {
				$need_pinterest_modal = 'pinterest' == $network ? true : $need_pinterest_modal;
			}
		}

		if ( false === $need_pinterest_modal ) {
			foreach ( $monarch_options[ 'sharing_locations_manage_locations' ] as $location ) {
				if ( 'media' !== $location ) {
					if ( isset( $monarch_options['sharing_' . $location . '_display_all'] ) && true == $monarch_options['sharing_' . $location . '_display_all'] ) {
						$need_pinterest_modal = true;
					}
				}
			}
		}

		//generate Pinterest images picker only if Pinterest network selected
		if ( $need_pinterest_modal ) {

			if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
				wp_reset_postdata();
			}

			$output = sprintf(
				'<div class="et_social_pin_images_outer">
					<div class="et_social_pinterest_window">
						<div class="et_social_modal_header"><h3>%1$s</h3><span class="et_social_close"></span></div>
						<div class="et_social_pin_images" data-permalink="%2$s" data-title="%3$s" data-post_id="%4$s"></div>
					</div>
				</div>',
				esc_html__( 'Pin It on Pinterest', 'Monarch' ),
				esc_attr( get_permalink() ),
				esc_attr( get_the_title() ),
				esc_attr( get_the_ID() )
			);
			echo $output;
		}
	}

	function generate_all_networks_popup() {
		wp_verify_nonce( $_POST['generate_all_window_nonce'], 'generate_all_window' );

		$post_id = $_POST[ 'all_networks_page_id' ];
		$link = $_POST[ 'all_networks_link' ];
		$title = $_POST[ 'all_networks_title' ];
		$media = isset( $_POST[ 'all_networks_media' ] ) ? $_POST[ 'all_networks_media' ] : '';
		$for_popup = isset( $_POST[ 'is_popup' ] ) ? $_POST[ 'is_popup' ] : false;

		if ( 'true' == $for_popup ) {
			$monarch_options = $this->monarch_options;
			$selected_networks = $monarch_options[ 'sharing_networks_networks_sorting' ][ 'class' ];
			$result = $this->get_icons_list( 'popup', '', false, false, true, $post_id, $link, $title, $selected_networks );
		} else {
			$result = $this->generate_popup_content( true, $post_id, $link, $title, $media );
		}

		die( $result );
	}

	/**
	 * Returns the icons with all required classes and attributes depending on location.
	 */

	function get_icons_list( $type, $media_url = '', $is_mobile_sidebar = false, $display_all = false, $all_networks = false, $current_id = '', $permalink = '', $title = '', $exclude_array = array() ) {
		$monarch_options = $this->monarch_options;

		if ( true == $all_networks ) {
			$all_networks = $this->sharing_networks_options[1]['value'];
			$all_networks_array = array();

			foreach ( $all_networks as $network => $label ) {
				if ( ! in_array( $network, $exclude_array ) ) {
					$all_networks_array[] = $network;
				}
			}
		}

		$current_networks = true == $all_networks ? $all_networks_array : $monarch_options[ 'sharing_networks_networks_sorting' ][ 'class' ];

		//check whether social networks selected, if yes - generate list of icons, otherwise return empty string
		if ( ! isset( $monarch_options[ 'sharing_networks_networks_sorting' ] ) || empty( $monarch_options[ 'sharing_networks_networks_sorting' ] ) ) {
			$sharing_icons = '';
		} else {

			if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
				wp_reset_postdata();
			}

			$sharing_icons = empty( $exclude_array ) ? '<ul class="et_social_icons_container">' : '';
			$i = 0;

			if ( '' !== $current_id ) {
				$post_id = $current_id;
			} else {
				$post_id = is_front_page() && !is_page() ? '-1' : get_the_ID();
			}

			foreach ( $current_networks as $icon ) {
				$icon_name = true == $all_networks ? $icon : $monarch_options[ 'sharing_networks_networks_sorting' ][ 'label' ][ $i ];
				$social_type = 'like' == $icon ? 'like' : 'share';

				$is_counts_cached = false == $all_networks ? $this->check_cached_counts( get_the_ID(), $icon, 'share' ) : '';

				if ( false == $all_networks ) {
					$share_counts = true == $monarch_options[ 'sharing_' . $type . '_counts' ] ? true : false;
					$display_counts = true == $monarch_options[ 'sharing_' . $type . '_counts' ] ? ' et_social_display_count' : '';
					$min_counts = true == $monarch_options[ 'sharing_' . $type . '_counts' ] ? sprintf( 'data-min_count="%1$s"', esc_attr( $monarch_options[ 'sharing_' . $type . '_counts_num' ] ) ) : '';
					$network_name = ( isset( $monarch_options[ 'sharing_' . $type . '_network_names' ] ) && true == $monarch_options['sharing_' . $type . '_network_names'] ) ? sprintf( '<div class="et_social_networkname">%1$s</div>', $icon_name ) : '';
					$icon_label = false != $share_counts || '' != $network_name ?
						sprintf( '<div class="et_social_network_label">%1$s%2$s</div>',
							'' != $network_name ? $network_name : '',
							( true === $is_counts_cached && true === $share_counts )
								? $this->get_shares_count( $icon, $monarch_options[ 'sharing_' . $type . '_counts_num' ], true, '', '', false )
								: ''
						)
						: '';
				} else {
					$network_name = sprintf( '<div class="et_social_networkname">%1$s</div>', esc_html( $icon_name ) );
					$icon_label = sprintf( '<div class="et_social_network_label">%1$s</div>', $network_name );
				}

				// Do not add the like button on media
				if ( !( 'media' == $type && 'like' == $icon ) ) {
					switch ( $type ) {

						case 'sidebar' :
							$sharing_icons .= sprintf(
								'<li class="et_social_%1$s">
									<a href="%2$s" class="et_social_share%5$s%6$s" rel="nofollow" data-social_name="%1$s" data-post_id="%3$s" data-social_type="%4$s"%7$s>
										<i class="et_social_icon et_social_icon_%1$s"></i>
										%8$s
										%9$s
										<span class="et_social_overlay"></span>
									</a>
								</li>',
								esc_attr( $icon ),
								esc_url( $this->get_share_link( $icon, $media_url, $i ) ),
								esc_attr( $post_id ),
								esc_attr( $social_type ),
								'pinterest' == $icon ? '_pinterest' : '',
								false == $is_counts_cached ? $display_counts : '',
								false == $is_counts_cached ? $min_counts : '',
								$is_mobile_sidebar
									? sprintf(
										'<div class="et_social_network_label"><div class="et_social_networkname">%1$s</div>%2$s</div>',
										esc_html( $icon_name ),
										( true === $is_counts_cached && true === $share_counts )
											? $this->get_shares_count( $icon, $monarch_options[ 'sharing_' . $type . '_counts_num' ], true, '', '', false )
											: ''
									)
									: '',
								( true == $is_counts_cached && ! $is_mobile_sidebar )
									? $this->get_shares_count( $icon, $monarch_options[ 'sharing_' . $type . '_counts_num' ], true, '', '', false )
									: ''
							);

						break;

						case 'flyin' :
						case 'popup' :
						case 'inline' :
							$sharing_icons .= sprintf(
								'<li class="et_social_%1$s"><a href="%3$s" class="et_social_share%6$s%7$s%9$s" rel="nofollow" data-social_name="%1$s" data-post_id="%4$s" data-social_type="%5$s"%8$s><i class="et_social_icon et_social_icon_%1$s"></i>%2$s<span class="et_social_overlay"></span></a></li>',
									esc_attr( $icon ),
									$icon_label,
									esc_url( $this->get_share_link( $icon, $media_url, $i, $permalink, $title ) ),
									esc_attr( $post_id ),
									esc_attr( $social_type ),
									'pinterest' == $icon ? '_pinterest' : '',
									( false == $is_counts_cached && false == $all_networks ) ? $display_counts : '',
									( false == $is_counts_cached && false == $all_networks ) ? $min_counts : '',
									'' !== $media_url && 'pinterest' == $icon ? ' et_social_pin_all' : ''
							);
						break;

						case 'media' :
							$sharing_icons .= sprintf(
								'<li class="et_social_%1$s">
									<div data-social_link="%3$s" rel="nofollow" class="et_social_share" data-social_name="%1$s" data-social_type="%4$s" data-post_id="%5$s">
										<i class="et_social_icon et_social_icon_%1$s"></i>
										%2$s
										<span class="et_social_overlay"></span>
									</div>
								</li>',
								esc_attr( $icon ),
								false != $share_counts || '' != $network_name
									? sprintf(
										'<div class="et_social_network_label">%1$s%2$s</div>',
										'' != $network_name ? $network_name : '',
										false != $share_counts ? '<div class="et_social_count"><span></span></div>' : ''
									)
									: '',
								esc_url( $this->get_share_link( $icon, $media_url, $i ) ),
								'media',
								esc_attr( $post_id )
							);

						break;
					}
				}

				$i++;
			}

			if ( true == $display_all ) {
				switch ( $type ) {
					case 'sidebar' :
					case 'flyin' :
					case 'popup' :
					case 'inline' :
						$sharing_icons .= sprintf(
											'<li class="et_social_all_button">
												<a href="#" rel="nofollow" data-location="%1$s" data-page_id="%2$s" data-permalink="%3$s" data-title="%4$s" class="et_social_open_all">
													<i class="et_social_icon et_social_icon_all_button"></i>
													<span class="et_social_overlay"></span>
												</a>
											</li>',
											esc_attr( $type ),
											esc_attr( $post_id ),
											is_front_page() && !is_page() ? esc_url( get_bloginfo( 'url' ) ) : esc_url( get_permalink( $post_id ) ),
											is_front_page() && !is_page() ? esc_attr( get_bloginfo( 'name' ) ) : esc_attr( get_the_title( $post_id ) )
						);
						break;

					case 'media' :
						$sharing_icons .= sprintf(
											'<li class="et_social_all_button">
												<div rel="nofollow" class="et_social_open_all" data-location="%1$s" data-page_id="%2$s" data-permalink="%3$s" data-title="%4$s" data-media="%5$s">
													<i class="et_social_icon et_social_icon_all_button"></i>
													<span class="et_social_overlay"></span>
												</div>
											</li>',
											esc_attr( $type ),
											esc_attr( $post_id ),
											is_front_page() && !is_page() ? esc_url( get_bloginfo( 'url' ) ) : esc_url( get_permalink( $post_id ) ),
											is_front_page() && !is_page() ? esc_attr( get_bloginfo( 'name' ) ) : esc_attr( get_the_title( $post_id ) ),
											esc_url( $media_url )
						);
						break;
				}
			}

			$sharing_icons .= empty( $exclude_array ) ? '</ul>' : '';
		}

		return $sharing_icons;

	}

	function generate_inline_icons( $class = 'et_social_inline_top' ) {
		$monarch_options = $this->monarch_options;

		if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
			wp_reset_postdata();
		}
		$display_all_button = isset( $monarch_options[ 'sharing_inline_display_all' ] ) ? $monarch_options[ 'sharing_inline_display_all' ] : false;
		$inline_content = sprintf(
			'<div class="et_social_inline%10$s %12$s">
				<div class="et_social_networks et_social_%2$s et_social_%3$s et_social_%4$s et_social_%5$s et_social_no_animation%6$s%7$s%9$s%11$s%13$s">
					%8$s
					%1$s
				</div>
			</div>',
			$this->get_icons_list( 'inline', '', false, $display_all_button ),
			'auto' == $monarch_options[ 'sharing_inline_col_number' ]
				? 'autowidth'
				: esc_attr( $monarch_options[ 'sharing_inline_col_number' ] . 'col' ),
			esc_attr( $monarch_options[ 'sharing_inline_icon_style' ] ),
			esc_attr( $monarch_options[ 'sharing_inline_icon_shape' ] ),
			esc_attr( $monarch_options[ 'sharing_inline_icons_alignment' ] ), //#5
			true == $monarch_options[ 'sharing_inline_counts' ] ? ' et_social_withcounts' : '',
			true == $monarch_options[ 'sharing_inline_total' ] ? ' et_social_withtotalcount' : '',
			true == $monarch_options[ 'sharing_inline_total' ]
				? sprintf(
					'<div class="et_social_totalcount">
						<span class="et_social_totalcount_count et_social_total_share" data-post_id="%2$s"></span>
						<span class="et_social_totalcount_label">%1$s</span>
					</div>',
					esc_html__( 'Shares', 'Monarch' ),
					esc_attr( get_the_ID() )
				)
				: '',
			true == $monarch_options[ 'sharing_inline_spacing' ] ? ' et_social_nospace' : '',
			true == $monarch_options[ 'sharing_inline_mobile' ] ? ' et_social_mobile_off' : ' et_social_mobile_on', //#10
			true == $monarch_options[ 'sharing_inline_network_names' ] ? ' et_social_withnetworknames' : '',
			esc_attr( $class ),
			esc_attr( sprintf( ' et_social_outer_%1$s', $monarch_options[ 'sharing_inline_outer_color' ] ) ) //#13
		);

		return $inline_content;
	}

	/**
	 * The function is executed via ajax to add total share counts on media icons
	 */
	function get_media_shares_total() {
		wp_verify_nonce( $_POST['get_media_shares_total_nonce'], 'get_media_total' );

		global $wpdb;
		$monarch_options = $this->monarch_options;

		$total_counts_data = str_replace( '\\', '' ,  $_POST[ 'media_total' ] );
		$total_counts_data_array = json_decode( $total_counts_data, true );

		$table_name = $wpdb->prefix . 'et_social_stats';

		$media_url = $total_counts_data_array[ 'media_url' ];
		$post_id = $total_counts_data_array[ 'post_id' ];

		// construct sql query to get count of media shares for the required post
		$sql = "SELECT COUNT(*) FROM $table_name WHERE action = %s AND media_url like %s AND post_id = %d";
		$sql_args = array(
			'media',
			$media_url,
			$post_id
		);

		$i = 0;
		foreach ( $monarch_options[ 'sharing_networks_networks_sorting' ][ 'class' ] as $network ) {
			//do not count likes
			if ( 'like' != $network ) {
				$operator = 0 < $i ? ' OR ' : ' AND ( ';
				$sql .= "{$operator}network = %s";
				$sql_args[] = $network;
				$i++;
			}
		}

		$sql .= ');';

		$total_media_shares = $wpdb->get_var( $wpdb->prepare( $sql, $sql_args ) );

		die( $this->get_compact_number( $total_media_shares ) );

	}

	/**
	 * Retrieves share counts for specified network.
	 */
	function get_shares_single() {
		wp_verify_nonce( $_POST['get_media_shares_nonce'], 'get_media_single' );

		global $wpdb;
		$single_counts_data = str_replace( '\\', '' ,  $_POST[ 'media_single' ] );
		$single_counts_data_array = json_decode( $single_counts_data, true );

		$table_name = $wpdb->prefix . 'et_social_stats';

		$media_url = $single_counts_data_array[ 'media_url' ];
		$post_id = $single_counts_data_array[ 'post_id' ];
		$network = sanitize_text_field( $single_counts_data_array[ 'network' ] );
		$action = sanitize_text_field( $single_counts_data_array[ 'action' ] );

		// construct sql query to get count of like/share/follow
		$sql = "SELECT COUNT(*) FROM $table_name WHERE action = %s AND network = %s AND media_url like %s AND post_id = %s";
		$sql_args = array(
			$action,
			$network,
			$media_url,
			$post_id
		);
		$single_media_shares = $wpdb->get_var( $wpdb->prepare( $sql, $sql_args ) );

		die( $this->get_compact_number( $single_media_shares ) );

	}

	function generate_media_icons( $media_url = '' ) {
		$monarch_options = $this->monarch_options;
		$display_all_button = isset( $monarch_options[ 'sharing_media_display_all' ] ) ? $monarch_options[ 'sharing_media_display_all' ] : false;

		$media_icons = sprintf(
			'<div class="et_social_media et_social_media_hidden%8$s">
				<div class="et_social_networks et_social_%9$s et_social_%2$s et_social_%12$s et_social_%3$s%4$s%5$s%10$s%11$s%13$s">
					%6$s
					%1$s
				</div>
			</div>',
			$this->get_icons_list( 'media', $media_url, false, $display_all_button ),
			esc_attr( $monarch_options[ 'sharing_media_icon_style' ] ),
			esc_attr( $monarch_options[ 'sharing_media_icon_shape' ] ),
			true == $monarch_options[ 'sharing_media_counts' ] ? ' et_social_withcounts' : '',
			true == $monarch_options[ 'sharing_media_total' ] ? ' et_social_withtotalcount' : '', //#5
			true == $monarch_options[ 'sharing_media_total' ]
				? sprintf(
					'<div class="et_social_totalcount">
						<span class="et_social_totalcount_count"></span>
						<span class="et_social_totalcount_label">%1$s</span>
					</div>',
					esc_html__( 'Shares', 'Monarch' )
				)
				: '',
			true == $monarch_options[ 'sharing_media_spacing' ] ? ' et_social_nospace' : '',
			true == $monarch_options[ 'sharing_media_mobile' ] ? ' et_social_mobile_off' : ' et_social_mobile_on',
			'auto' == $monarch_options[ 'sharing_media_col_number' ]
				? 'autowidth'
				: esc_html( $monarch_options[ 'sharing_media_col_number' ] . 'col' ),
			true == $monarch_options[ 'sharing_media_spacing' ] ? ' et_social_nospace' : '', //#10
			esc_attr( sprintf( ' et_social_outer_%1$s', $monarch_options[ 'sharing_media_outer_color' ] ) ),
			esc_attr( $monarch_options[ 'sharing_media_icons_alignment' ] ),
			true == $monarch_options[ 'sharing_media_network_names' ] ? ' et_social_withnetworknames' : '' //#13
		);
		return $media_icons;
	}

	function display_sidebar() {
		$monarch_options = $this->monarch_options;

		if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
			wp_reset_postdata();
		}

		if ( $this->check_applicability( $monarch_options[ 'sharing_sidebar_post_types' ], 'sidebar' ) ) {
			$display_all_button = isset( $monarch_options[ 'sharing_sidebar_display_all' ] ) ? $monarch_options[ 'sharing_sidebar_display_all' ] : false;
			printf(
				'<div class="et_social_sidebar_networks et_social_visible_sidebar et_social_%8$s et_social_%6$s et_social_sidebar_%1$s%2$s%3$s%7$s%9$s%10$s">
					%4$s
					%5$s
					<span class="et_social_hide_sidebar et_social_icon"></span>
				</div>',
				esc_attr( $monarch_options[ 'sharing_sidebar_icon_style' ] ),
				true == $monarch_options[ 'sharing_sidebar_counts' ] ? ' et_social_sidebar_withcounts' : '',
				true == $monarch_options[ 'sharing_sidebar_total' ] ? ' et_social_withtotalcount' : '',
				true == $monarch_options[ 'sharing_sidebar_total' ]
					? sprintf(
						'<div class="et_social_totalcount et_social_%2$s">
							<span class="et_social_totalcount_count et_social_total_share" data-post_id="%3$s"></span>
							<span class="et_social_totalcount_label">%1$s</span>
						</div>',
						esc_html__( 'Shares', 'Monarch' ),
						esc_attr( $monarch_options[ 'sharing_sidebar_total_color' ] ),
						esc_attr( get_the_ID() )
					)
					: '',
				$this->get_icons_list( 'sidebar', '', false, $display_all_button ),
				esc_attr( $monarch_options[ 'sharing_sidebar_icon_shape' ] ),
				true == $monarch_options[ 'sharing_sidebar_spacing' ] ? ' et_social_space' : '',
				$monarch_options[ 'sharing_sidebar_animation' ] . ' et_social_animated',
				true == $monarch_options[ 'sharing_sidebar_mobile' ] ? ' et_social_mobile_off' : ' et_social_mobile_on',
				'right' == $monarch_options[ 'sharing_sidebar_sidebar_orientation' ] ? ' et_social_sidebar_networks_right' : '' //#10
			);

			if ( true != $monarch_options[ 'sharing_sidebar_mobile' ] ) {
				printf(
					'<div class="et_social_mobile_button"></div>
					<div class="et_social_mobile et_social_fadein">
						<div class="et_social_heading">%1$s</div>
						<span class="et_social_close"></span>
						<div class="et_social_networks et_social_simple et_social_rounded et_social_left%2$s">
							%3$s
						</div>
					</div>
					<div class="et_social_mobile_overlay"></div>',
					esc_html__( 'Share This', 'Monarch' ),
					true == $monarch_options[ 'sharing_sidebar_counts' ] ? ' et_social_withcounts' : '',
					$this->get_icons_list( 'sidebar', '', true, $display_all_button )
				);
			}
		}

	}

	function display_on_wc_page() {
		$monarch_options = $this->monarch_options;

		if ( isset( $monarch_options['sharing_inline_post_types'] ) && in_array( 'product', $monarch_options['sharing_inline_post_types'] ) ) {
			echo $this->generate_inline_icons();
		}
	}

	// add marker at the bottom of the_content() for the "Trigger at bottom of post" option.
	function trigger_bottom_mark( $content ) {
		$monarch_options = $this->monarch_options;

		if ( true == $monarch_options[ 'sharing_flyin_trigger_bottom' ] || true == $monarch_options[ 'sharing_popup_trigger_bottom' ] ) {
			if ( $this->check_applicability( $monarch_options[ 'sharing_flyin_post_types' ], 'flyin' ) || $this->check_applicability( $monarch_options[ 'sharing_popup_post_types' ], 'popup' ) ) {
				$content .= '<span class="et_social_bottom_trigger"></span>';
			}
		}

		return $content;
	}


	/**
	 * Modifies the_content to add the social icons above/below or above and below.
	 */
	function display_inline( $content ) {
		$monarch_options = $this->monarch_options;

		if ( $this->check_applicability( $monarch_options[ 'sharing_inline_post_types' ], 'inline' ) ) {
			$content = sprintf( '%1$s%2$s%3$s',
				( 'above' == $monarch_options[ 'sharing_inline_icons_location' ] || 'above_below' == $monarch_options[ 'sharing_inline_icons_location' ] )
					? $this->generate_inline_icons( 'et_social_inline_top' )
					: '',
				$content,
				( 'below' == $monarch_options[ 'sharing_inline_icons_location' ] || 'above_below' == $monarch_options[ 'sharing_inline_icons_location' ] )
					? $this->generate_inline_icons( 'et_social_inline_bottom' )
					: ''
			);
		}

		return $content;
	}

	/**
	 * Wraps all the images inside content into specific div and generates media sharing icons.
	 */
	function display_media( $content ) {
		$monarch_options = $this->monarch_options;
		if ( $this->check_applicability( $monarch_options[ 'sharing_media_post_types' ], 'media' ) ) {
			preg_match_all( '/<img [^>]*>/s', $content, $images_array );
			foreach ( $images_array[0] as $image ) {
				if ( false !== strpos( $image, 'class="ngg_' ) ) {
					continue;
				}
				preg_match( '@src="([^"]+)"@' , $image , $image_src );
				$icons = $this->generate_media_icons( $image_src[1] );
				$replacement = '<div class="et_social_media_wrapper">' . $image . $icons . '</div>';
				$content = str_replace( $image, $replacement, $content );
			}
		}

		return $content;

	}

	/**
	 * Generates the output for [et_social_share_media] shortcode.
	 */
	function et_social_share_media( $atts, $content="" ) {
		$monarch_options = $this->monarch_options;

		if ( is_page() && isset( $monarch_options[ 'sharing_networks_networks_sorting' ] ) && ! empty( $monarch_options[ 'sharing_networks_networks_sorting' ] ) ) {
			preg_match_all( '/<img [^>]*>/s', $content, $images_array );
				foreach ( $images_array[0] as $image ) {
					preg_match( '@src="([^"]+)"@' , $image , $image_src );
					$icons = $this->generate_media_icons( $image_src[1] );
					$replacement = '<div class="et_social_media_wrapper">' . $image . $icons . '</div>';
					$content = str_replace( $image, $replacement, do_shortcode( $content ) );
				}
		}

		return $content;
	}

	function display_flyin() {
		$monarch_options = $this->monarch_options;

		if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
			wp_reset_postdata();
		}

		if ( $this->check_applicability( $monarch_options['sharing_flyin_post_types'], 'flyin' ) ) {
			$display_all_button = isset( $monarch_options[ 'sharing_flyin_display_all' ] ) ? $monarch_options[ 'sharing_flyin_display_all' ] : false;

			printf(
				'<div class="et_social_flyin et_social_resize et_social_flyin_%1$s et_social_%8$s%13$s%15$s%16$s%18$s%21$s%23$s%25$s"%14$s%17$s%22$s%24$s>
					<a href="#" class="et_social_icon et_social_icon_cancel"></a>
					<div class="et_social_header">
						%2$s
						%3$s
					</div>
					<div class="et_social_networks et_social_%4$s et_social_%5$s et_social_%6$s et_social_%7$s%9$s%10$s%19$s%20$s">
						%11$s
						%12$s
					</div>
				</div>',
				esc_attr( $monarch_options[ 'sharing_flyin_icons_location' ] ),
				'' !== $monarch_options[ 'sharing_flyin_title_text' ] || '' !== $monarch_options[ 'sharing_flyin_message_text' ]
					? sprintf( '<h3>%1$s</h3>', esc_html( stripslashes( $monarch_options[ 'sharing_flyin_title_text' ] ) ) )
					: '',
				'' !== $monarch_options[ 'sharing_flyin_message_text' ] ? sprintf( '<p>%1$s</p>', esc_html( stripslashes( $monarch_options[ 'sharing_flyin_message_text' ] ) ) ) : '',
				'auto' == $monarch_options[ 'sharing_flyin_col_number' ] ? 'autowidth' : esc_html( $monarch_options[ 'sharing_flyin_col_number' ] . 'col' ),
				esc_attr( $monarch_options[ 'sharing_flyin_icon_style' ] ), //#5
				esc_attr( $monarch_options[ 'sharing_flyin_icon_shape' ] ),
				esc_attr( $monarch_options[ 'sharing_flyin_icons_alignment' ] ),
				esc_attr( $monarch_options[ 'sharing_flyin_animation' ] ),
				true == $monarch_options[ 'sharing_flyin_counts' ] ? ' et_social_withcounts' : '',
				true == $monarch_options[ 'sharing_flyin_total' ] ? ' et_social_withtotalcount' : '', //#10
				true == $monarch_options[ 'sharing_flyin_total' ]
					? sprintf(
							'<div class="et_social_totalcount et_social_dark">
								<span class="et_social_totalcount_count et_social_total_share" data-post_id="%2$s"></span>
								<span class="et_social_totalcount_label">%1$s</span>
							</div>',
							esc_html__( 'Shares', 'Monarch' ),
							esc_attr( get_the_ID() )
					)
					: '',
				$this->get_icons_list( 'flyin', '', false, $display_all_button ),
				true == $monarch_options[ 'sharing_flyin_auto_popup' ] ? ' et_social_auto_popup' : '',
				true == $monarch_options[ 'sharing_flyin_auto_popup' ]
					? sprintf( ' data-delay="%1$s"', esc_attr( $monarch_options[ 'sharing_flyin_popup_delay' ] ) )
					: '',
				true == $monarch_options[ 'sharing_flyin_trigger_bottom' ] ? ' et_social_trigger_bottom' : '', //#15
				isset( $monarch_options[ 'sharing_flyin_trigger_idle' ] ) && true == $monarch_options[ 'sharing_flyin_trigger_idle' ] ? ' et_social_trigger_idle' : '',
				true == $monarch_options[ 'sharing_flyin_cookies' ] ? ' data-cookie_duration="' . esc_attr( $monarch_options[ 'sharing_flyin_cookie_duration' ] ) . '"' : '',
				true == $monarch_options[ 'sharing_flyin_mobile' ] ? ' et_social_mobile_off' : ' et_social_mobile_on',
				true == $monarch_options[ 'sharing_flyin_network_names' ] ? ' et_social_withnetworknames' : '',
				true == $monarch_options[ 'sharing_flyin_spacing' ] ? ' et_social_nospace' : '', //#20
				( isset( $monarch_options[ 'sharing_flyin_scroll_trigger' ] ) && true == $monarch_options[ 'sharing_flyin_scroll_trigger' ] ) ? ' et_social_scroll' : '',
				( isset( $monarch_options[ 'sharing_flyin_scroll_trigger' ] ) && true == $monarch_options[ 'sharing_flyin_scroll_trigger' ] )
					? sprintf( ' data-scroll_pos="%1$s"', esc_attr( $monarch_options[ 'sharing_flyin_scroll_pos' ] ) )
					: '',
				( isset( $monarch_options[ 'sharing_flyin_trigger_purchase' ] ) && true == $monarch_options[ 'sharing_flyin_trigger_purchase' ] ) ? ' et_social_after_purchase' : '',
				( isset( $monarch_options[ 'sharing_flyin_trigger_idle' ] ) && true == $monarch_options[ 'sharing_flyin_trigger_idle' ] ) ? sprintf( ' data-idle_timeout="%1$s"', isset( $monarch_options[ 'sharing_flyin_idle_timeout' ] ) ? esc_attr( $monarch_options[ 'sharing_flyin_idle_timeout' ] ) : '15' ) : '',
				( isset( $monarch_options[ 'sharing_flyin_trigger_comment' ] ) && true == $monarch_options[ 'sharing_flyin_trigger_comment' ] ) ? ' et_social_after_comment' : '' //#25
			);
		}
	}

	function display_popup() {
		$monarch_options = $this->monarch_options;
		$output = '';

		if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
			wp_reset_postdata();
		}

		if ( $this->check_applicability( $monarch_options['sharing_popup_post_types'], 'popup' ) ) {

			$output = $this->generate_popup_content();

		}

		echo $output;
	}

	function generate_popup_content( $all_networks = false, $post_id = '', $permalink = '', $title = '', $media_url = '' ) {
		$output = '';

		if ( true == $all_networks ) {
			$display_all_button = false;
			$title_text = '';
			$message_text = '';
			$columns = '3col';
			$icon_style = 'simple';
			$icon_shape = 'rounded';
			$icon_alignment = 'left';
			$with_counts = '';
			$with_total = '';
			$animation = 'fadein';
			$total_counts = '';
			$icons_list = $this->get_icons_list( 'popup', $media_url, false, false, true, $post_id, $permalink, $title );
			$auto_popup = ' et_social_all_networks_popup';
			$auto_delay = '';
			$trigger_idle = '';
			$trigger_bottom = '';
			$cookies = '';
			$mobile = ' et_social_mobile_on';
			$network_names = ' et_social_withnetworknames';
			$spacing = '';
			$trigger_purchase = '';
			$idle_timeout = '';
			$scroll_trigger = '';
			$scroll_pos = '';
			$trigger_comment = '';
		} else {
			$monarch_options = $this->monarch_options;
			$display_all_button = isset( $monarch_options[ 'sharing_popup_display_all' ] ) ? $monarch_options[ 'sharing_popup_display_all' ] : false;
			$title_text = '' !== $monarch_options[ 'sharing_popup_title_text' ] || '' !== $monarch_options[ 'sharing_popup_message_text' ]
				? sprintf( '<h3>%1$s</h3>', esc_html( stripslashes( $monarch_options[ 'sharing_popup_title_text' ] ) ) )
				: '';
			$message_text = '' !== $monarch_options[ 'sharing_popup_message_text' ]
				? sprintf( '<p>%1$s</p>', esc_html( stripslashes( $monarch_options[ 'sharing_popup_message_text' ] ) ) )
				: '';
			$columns = 'auto' == $monarch_options[ 'sharing_popup_col_number' ] ? 'autowidth' : esc_html( $monarch_options[ 'sharing_popup_col_number' ] . 'col' );
			$icon_style = $monarch_options[ 'sharing_popup_icon_style' ];
			$icon_shape = $monarch_options[ 'sharing_popup_icon_shape' ];
			$icon_alignment = $monarch_options[ 'sharing_popup_icons_alignment' ];
			$with_counts = true == $monarch_options[ 'sharing_popup_counts' ] ? ' et_social_withcounts' : '';
			$with_total = true == $monarch_options[ 'sharing_popup_total' ] ? ' et_social_withtotalcount' : '';
			$animation = $monarch_options[ 'sharing_popup_animation' ];
			$total_counts = true == $monarch_options[ 'sharing_popup_total' ]
				? sprintf(
					'<div class="et_social_totalcount et_social_dark">
						<span class="et_social_totalcount_count et_social_total_share" data-post_id="%2$s"></span>
						<span class="et_social_totalcount_label">%1$s</span>
					</div>',
					esc_html__( 'Shares', 'Monarch' ),
					esc_attr( get_the_ID() )
				)
				: '';
			$icons_list = $this->get_icons_list( 'popup', '', false, $display_all_button );
			$auto_popup = true == $monarch_options[ 'sharing_popup_auto_popup' ] ? ' et_social_auto_popup' : '';
			$auto_delay = true == $monarch_options[ 'sharing_popup_auto_popup' ]
				? sprintf( ' data-delay="%1$s"', esc_attr( $monarch_options[ 'sharing_popup_popup_delay' ] ) )
				: '';
			$trigger_bottom = true == $monarch_options[ 'sharing_popup_trigger_bottom' ] ? ' et_social_trigger_bottom' : '';
			$trigger_idle = isset( $monarch_options[ 'sharing_popup_trigger_idle' ] ) && true == $monarch_options[ 'sharing_popup_trigger_idle' ] ? ' et_social_trigger_idle' : '';
			$cookies = true == $monarch_options[ 'sharing_popup_cookies' ] ? ' data-cookie_duration="' . esc_attr( $monarch_options[ 'sharing_popup_cookie_duration' ] ) . '"' : '';
			$mobile = true == $monarch_options[ 'sharing_popup_mobile' ] ? ' et_social_mobile_off' : ' et_social_mobile_on';
			$network_names = true == $monarch_options[ 'sharing_popup_network_names' ] ? ' et_social_withnetworknames' : '';
			$spacing = true == $monarch_options[ 'sharing_popup_spacing' ] ? ' et_social_nospace' : '';
			$trigger_purchase =	( isset( $monarch_options[ 'sharing_popup_trigger_purchase' ] ) && true == $monarch_options[ 'sharing_popup_trigger_purchase' ] ) ? ' et_social_after_purchase' : '';
			$idle_timeout =	( isset( $monarch_options[ 'sharing_popup_trigger_idle' ] ) && true == $monarch_options[ 'sharing_popup_trigger_idle' ] ) ? sprintf( ' data-idle_timeout="%1$s"', isset( $monarch_options[ 'sharing_popup_idle_timeout' ] ) ? esc_attr( $monarch_options[ 'sharing_popup_idle_timeout' ] ) : '15' ) : '';
			$scroll_trigger = ( isset( $monarch_options[ 'sharing_popup_scroll_trigger' ] ) && true == $monarch_options[ 'sharing_popup_scroll_trigger' ] ) ? ' et_social_scroll' : '';
			$scroll_pos = ( isset( $monarch_options[ 'sharing_popup_scroll_trigger' ] ) && true == $monarch_options[ 'sharing_popup_scroll_trigger' ] )
					? sprintf( ' data-scroll_pos="%1$s"', esc_attr( $monarch_options[ 'sharing_popup_scroll_pos' ] ) )
					: '';
			$trigger_comment = ( isset( $monarch_options[ 'sharing_popup_trigger_comment' ] ) && true == $monarch_options[ 'sharing_popup_trigger_comment' ] ) ? ' et_social_after_comment' : '';
		}

		$output = sprintf(
							'<div class="et_social_popup%12$s%14$s%15$s%17$s%20$s%22$s%24$s"%13$s%16$s%21$s%23$s>
								<div class="et_social_popup_content et_social_resize et_social_%9$s">
									<a href="#" class="et_social_icon et_social_icon_cancel"></a>
									<div class="et_social_header">
										%1$s
										%2$s
									</div>
									<div class="et_social_networks et_social_%3$s et_social_%4$s et_social_%5$s et_social_%6$s%7$s%8$s%18$s%19$s">
										%10$s
										%11$s
									</div>
								</div>
							</div>',
							$title_text,
							$message_text,
							$columns,
							esc_attr( $icon_style ),
							esc_attr( $icon_shape ), //#5
							esc_attr( $icon_alignment ),
							$with_counts,
							$with_total,
							esc_attr( $animation ),
							$total_counts, //#10
							$icons_list,
							$auto_popup,
							$auto_delay,
							$trigger_bottom,
							$trigger_idle, //#15
							$cookies,
							$mobile,
							$network_names,
							$spacing,
							$trigger_purchase, //#20
							$idle_timeout,
							$scroll_trigger,
							$scroll_pos,
							$trigger_comment // #24
					);

		return $output;
	}

	/**
	 * Extracts attributes from the shortcode and pass them to display_widget function.
	 */
	function display_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'col_number'       => '',
			'icon_style'       => '',
			'icon_shape'       => '',
			'icons_location'   => '',
			'counts'           => '',
			'counts_num'       => 0,
			'spacing'          => '',
			'total'            => '',
			'mobile'           => false,
			'custom_colors'    => false,
			'bg_color'         => '',
			'bg_color_hover'   => '',
			'icon_color'       => '',
			'icon_color_hover' => '',
			'outer_color'      => '',
			'network_names'    => false,
		), $atts );

		return $this->display_widget( 'shortcode', $atts );
	}

	public static function get_api_follow_counts( $network, $index = '' ) {
		$url = '';
		$result = false;
		$settings = ET_Monarch::get_options_array();

		switch ( $network ) {
			case 'vimeo' :
				if ( isset( $settings['access_tokens']['vimeo'] ) ) {
					$url = sprintf( 'https://api.vimeo.com/me/followers?access_token=%1$s', $settings['access_tokens']['vimeo'] );
				}

				break;
			case 'instagram' :
				if ( isset( $settings['access_tokens']['instagram'] ) ) {
					$url = sprintf( 'https://api.instagram.com/v1/users/self/?access_token=%1$s', $settings['access_tokens']['instagram'] );
				}

				break;
			case 'linkedin' :
				if ( isset( $settings['access_tokens']['linkedin'] ) ) {
					$url = sprintf( 'https://api.linkedin.com/v1/people/~:(num-connections)?oauth2_access_token=%1$s&format=json', $settings['access_tokens']['linkedin'] );
				}

				break;
			case 'soundcloud' :
				if ( isset( $settings['follow_networks_networks_sorting']['client_id'][$index] ) ) {
					$url = sprintf( 'http://api.soundcloud.com/users/%1$s.json?client_id=%2$s', $settings['follow_networks_networks_sorting']['client_name'][$index], $settings['follow_networks_networks_sorting']['client_id'][$index] );
				}

				break;
			case 'facebook' :
				if ( isset( $settings['access_tokens']['facebook'] ) && isset( $settings['follow_networks_networks_sorting']['client_id'][$index] ) ) {
					$url = sprintf( 'https://graph.facebook.com/v2.3/%1$s?access_token=%2$s', $settings['follow_networks_networks_sorting']['client_id'][$index], $settings['access_tokens']['facebook'] );
				}

				break;
			case 'dribbble' :
				if ( isset( $settings['follow_networks_networks_sorting']['client_id'][$index] ) ) {
					$url = sprintf( 'https://api.dribbble.com/v1/user?access_token=%1$s', $settings['follow_networks_networks_sorting']['client_id'][$index] );
				}

				break;
			case 'vkontakte' :
				if ( isset( $settings['follow_networks_networks_sorting']['client_id'][$index] ) ) {
					$url = sprintf( 'https://api.vk.com/method/friends.get?user_id=%1$s&count=1&v=5.8', $settings['follow_networks_networks_sorting']['client_id'][$index] );
				}

				break;
			case 'github' :
				if ( isset( $settings['follow_networks_networks_sorting']['client_id'][$index] ) ) {
					$url = sprintf( 'https://api.github.com/users/%1$s', $settings['follow_networks_networks_sorting']['client_id'][$index] );
				}

				break;
			case 'twitter' :
				if ( isset( $settings['follow_networks_twitter_api_key'] ) ) {
					$api_key = $settings['follow_networks_twitter_api_key'];
					$api_secret = $settings['follow_networks_twitter_api_secret'];
					$token = $settings['follow_networks_twitter_token'];
					$token_secret = $settings['follow_networks_twitter_token_secret'];
					$result = ET_Monarch::get_twitter_followers( $api_key, $api_secret, $token, $token_secret, $index, false );
				}

				break;
			case 'pinterest' :
				if ( !empty( $settings['follow_networks_networks_sorting']['username'][$index] ) ) {
					$metas = get_meta_tags( $settings['follow_networks_networks_sorting']['username'][$index] );
					$result = isset( $metas['pinterestapp:followers'] ) ? $metas['pinterestapp:followers'] : 0;

					if ( 0 === $result ) {
						$result = isset( $metas['followers'] ) ? $metas['followers'] : 0;
					}
				}

				break;
			case 'youtube' :
				if ( isset( $settings['follow_networks_networks_sorting']['client_id'][$index] ) ) {
					$url = sprintf( 'http://gdata.youtube.com/feeds/api/users/%1$s', sanitize_text_field( $settings['follow_networks_networks_sorting']['client_id'][$index] ) );
				}

				break;
		}

		$request = wp_remote_get( esc_url( $url ) );
		if ( ! is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 ) {
			$data = wp_remote_retrieve_body( $request );
			if ( 'linkedin' !== $network && 'youtube' !== $network ) {
				$data = json_decode( $data );
			}
			if ( 'youtube' == $network ) {
				$data = str_replace( 'yt:', 'yt', $data );
				$data = new SimpleXMLElement( $data );
			}
		}

		if ( isset( $data ) ) {
			switch ( $network ) {
				case 'vimeo':
					$result = $data->total;

					break;
				case 'instagram':
					$result = $data->data->counts->followed_by;

					break;
				case 'linkedin':
					$result = $data;

					break;
				case 'soundcloud':
					$result = $data->followers_count;

					break;
				case 'facebook':
					$result = isset( $data->likes ) ? $data->likes : 0;

					break;
				case 'dribbble':
					$result = $data->followers_count;

					break;
				case 'vkontakte':
					if ( isset( $data->response ) ) {
						$result = count( $data->response );
					} else {
						return -1;
					}

					break;
				case 'github':
					$result = $data->followers;

					break;
				case 'youtube':
					$result = $data->ytstatistics['subscriberCount'];

					break;
			}
		}

		return intval( $result );
	}

	/**
	 *	Public funciton: we need to use it to generate the widget's content outside the class.
	 *	This funciton is also used to generate the content for shortcode since it has similar layout.
	 *	It generates the content for widget by default.
	 */
	public static function display_widget( $type = 'widget', $shortcode_atts = '' ) {
		$monarch_options = ET_Monarch::get_options_array();

		//check whether social networks selected, if yes - process all the settings and generate the icons, otherwise generate empty container
		if ( isset( $monarch_options[ 'follow_networks_networks_sorting' ] ) && ! empty( $monarch_options[ 'follow_networks_networks_sorting' ] ) ) {

			// If we're generating content for shortcode, then shortcode attributes should be used
			$col_number = 'shortcode' == $type ? $shortcode_atts[ 'col_number' ] : $monarch_options[ 'follow_widget_col_number' ];
			$icon_style = 'shortcode' == $type ? $shortcode_atts[ 'icon_style' ] : $monarch_options[ 'follow_widget_icon_style' ];
			$icon_shape = 'shortcode' == $type ? $shortcode_atts[ 'icon_shape' ] : $monarch_options[ 'follow_widget_icon_shape' ];
			$icons_location = 'shortcode' == $type ? $shortcode_atts[ 'icons_location' ] : $monarch_options[ 'follow_widget_icons_location' ];
			$counts = 'shortcode' == $type ? $shortcode_atts[ 'counts' ] : $monarch_options[ 'follow_widget_counts' ];
			$counts_num = 'shortcode' == $type ? intval( $shortcode_atts[ 'counts_num' ] ) : intval( $monarch_options[ 'follow_widget_counts_num' ] );
			$spacing = 'shortcode' == $type ? $shortcode_atts[ 'spacing' ] : $monarch_options[ 'follow_widget_spacing' ];
			$total = 'shortcode' == $type ? $shortcode_atts[ 'total' ] : $monarch_options[ 'follow_widget_total' ];
			$outer_color = 'shortcode' == $type ? $shortcode_atts[ 'outer_color' ] : $monarch_options[ 'follow_widget_outer_color' ];
			$hide_mobile = 'shortcode' == $type ? $shortcode_atts[ 'mobile' ] : $monarch_options[ 'follow_widget_mobile' ];
			$network_names = 'shortcode' == $type ? $shortcode_atts[ 'network_names' ] : $monarch_options[ 'follow_widget_network_names' ];

			$custom_colors = false;

			if ( 'shortcode' == $type && false !== $shortcode_atts[ 'custom_colors' ] ) {
				$custom_colors = true;
				$bg_color = $shortcode_atts[ 'bg_color' ];
				$bg_color_hover = $shortcode_atts[ 'bg_color_hover' ];
				$icon_color = $shortcode_atts[ 'icon_color' ];
				$icon_color_hover = $shortcode_atts[ 'icon_color_hover' ];
				$shortcodes_count = ET_Monarch::$shortcodes_count++; // shortcodes global counter to apply color styles properly.
			}

			if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
				wp_reset_postdata();
			}

			$sharing_icons = '<ul class="et_social_icons_container">';
			$i = 0;
			$post_id = is_singular() ? get_the_ID() : 0;

			foreach ( $monarch_options[ 'follow_networks_networks_sorting' ][ 'class' ] as $icon ) {
				$icon_name = $monarch_options[ 'follow_networks_networks_sorting' ][ 'label' ][ $i ];

				$is_follows_cached = ET_Monarch::check_cached_counts( get_the_ID(), $icon, 'follow', $monarch_options[ 'follow_networks_use_api' ] );
				$follow_counts = ( true == $counts && true == $is_follows_cached ) ? ET_Monarch::get_follow_counts( $counts_num, $icon, $i, true, false ) : '';
				$network_name = true == $network_names ? sprintf( '<div class="et_social_networkname">%1$s</div>', esc_html( $icon_name ) ) : '';
				$icon_label = true == $counts || '' != $network_name
					? sprintf(
						'<div class="et_social_network_label%2$s"%3$s%4$s%5$s>%1$s%6$s</div>',
							'' != $network_name ? $network_name : '',
							( true == $counts && false == $is_follows_cached ) ? ' et_social_display_follow_counts' : '',
							( true == $counts && false == $is_follows_cached ) ? sprintf( ' data-min_count="%1$s"', esc_attr( $counts_num ) ) : '',
							( true == $counts && false == $is_follows_cached ) ? sprintf( ' data-network="%1$s"', esc_attr( $icon ) ) : '',
							( true == $counts && false == $is_follows_cached ) ? sprintf( ' data-index="%1$s"', esc_attr( $i ) ) : '',
							$follow_counts
						)
					: '';

				$sharing_icons .= sprintf(
					'<li class="et_social_%1$s">
						<a href="%5$s" class="et_social_follow" data-social_name="%1$s" data-social_type="%3$s" data-post_id="%4$s"%6$s>
							<i class="et_social_icon et_social_icon_%1$s"></i>
							%2$s
							<span class="et_social_overlay"></span>
						</a>
					</li>',
					esc_attr( $icon ),
					$icon_label,
					'like' == $icon ? 'like' : 'follow',
					esc_attr( $post_id ),
					ET_Monarch::get_follow_link( $icon, isset( $monarch_options[ 'follow_networks_networks_sorting' ][ 'username' ][ $i ] ) ? $monarch_options[ 'follow_networks_networks_sorting' ][ 'username' ][ $i ] : '' ),
					true == $monarch_options[ 'follow_networks_new_window' ] ? ' target="_blank"' : ''
				);
				$i++;
			}
			$sharing_icons .= '</ul>';

			$widget_output = sprintf(
				'<div class="et_social_networks et_social_%2$s et_social_%3$s et_social_%4$s et_social_%5$s%6$s%7$s%9$s%11$s%12$s%13$s%14$s%15$s">
					%10$s
					%8$s
					%1$s
				</div>',
				$sharing_icons,
				'auto' == $col_number ? 'autowidth' : esc_html( $col_number . 'col' ),
				esc_attr( $icon_style ),
				esc_attr( $icon_shape ),
				esc_attr( $icons_location ), //#5
				true == $counts ? ' et_social_withcounts' : '',
				true == $spacing ? ' et_social_nospace' : '',
				true == $total ? sprintf(
					'<div class="et_social_totalcount">
						<span class="et_social_totalcount_count et_social_follow_total"></span>
						<span class="et_social_totalcount_label">%1$s</span>
					</div>',
					esc_html__( 'Follows', 'Monarch' ) )
					: '',
				( 'shortcode' == $type && true == $custom_colors ) ? esc_attr( ' et_social_shortcode_' . $shortcodes_count ) : '',
				( 'shortcode' == $type && true == $custom_colors ) ?
					sprintf( '<style type="text/css">%1$s%2$s%3$s%4$s</style>',
						'' !== $bg_color
							? sprintf( '.et_monarch .et_social_shortcode_%1$s li,.et_monarch .et_social_shortcode_%1$s.et_social_circle ul li i.et_social_icon { background: %2$s; }',
								esc_html( $shortcodes_count ),
								esc_html( $bg_color )
							)
							: '',
						'' !== $bg_color_hover
							? sprintf( ' .et_monarch .et_social_shortcode_%1$s.et_social_rounded .et_social_icons_container li:hover, .et_monarch .et_social_shortcode_%1$s.et_social_rectangle .et_social_icons_container li:hover, .et_monarch .et_social_shortcode_%1$s.et_social_circle .et_social_icons_container li:hover i.et_social_icon { background: %2$s !important; }',
								esc_html( $shortcodes_count ),
								esc_html( $bg_color_hover )
							)
							: '',
						'' !== $icon_color
							? sprintf( ' .et_monarch .et_social_shortcode_%1$s .et_social_icon, .et_monarch .et_social_shortcode_%1$s .et_social_networks .et_social_network_label, .et_monarch .et_social_shortcode_%1$s .et_social_networkname, .et_monarch .et_social_shortcode_%1$s .et_social_count { color: %2$s !important; }',
								esc_html( $shortcodes_count ),
								esc_html( $icon_color )
							)
							: '',
						'' !== $icon_color_hover
							? sprintf( ' .et_monarch .et_social_shortcode_%1$s .et_social_icons_container li:hover .et_social_icon, .et_monarch .et_social_shortcode_%1$s .et_social_networks .et_social_icons_container li:hover .et_social_network_label, .et_monarch .et_social_shortcode_%1$s .et_social_icons_container li:hover .et_social_networkname, .et_monarch .et_social_rounded.et_social_shortcode_%1$s .et_social_icons_container li:hover .et_social_count, .et_monarch .et_social_rectangle.et_social_shortcode_%1$s .et_social_icons_container li:hover .et_social_count { color: %2$s !important; }',
								esc_html( $shortcodes_count ),
								esc_html( $icon_color_hover )
							)
							: ''
					)
					: '', //#10
				true == $total ? ' et_social_withtotalcount' : '',
				true == $hide_mobile ? ' et_social_mobile_off' : ' et_social_mobile_on',
				true == $network_names ? ' et_social_withnetworknames' : '',
				esc_attr( sprintf( ' et_social_outer_%1$s', $outer_color ) ),
				'widget' == $type ? ' widget_monarchwidget' : '' //#15
			);
		} else {
			$widget_output = sprintf( '<div class="et_social_networks no_networks_selected"></div>' );
		}

		return $widget_output;
	}

	public static function get_follow_networks_with_api_support() {
		$networks = array(
			'vimeo',
			'instagram',
			'linkedin',
			'soundcloud',
			'facebook',
			'dribbble',
			'vkontakte',
			'github',
			'twitter',
			'pinterest',
			'youtube',
		);

		return $networks;
	}

	public static function get_follow_counts( $counts_num = 0, $network = '', $index = '', $display = true, $is_ajax_request = true ) {

		if ( $is_ajax_request ) {
			wp_verify_nonce( $_POST['get_follow_counts_nonce'], 'get_follow_counts' );
			$count_data_json = str_replace( '\\', '' ,  $_POST[ 'follow_count_array' ] );
			$count_data_array = json_decode( $count_data_json, true );
			$network = sanitize_text_field( $count_data_array[ 'network' ] );
			$counts_num = (int) $count_data_array[ 'min_count' ];
			$index = (int) $count_data_array[ 'index' ];
		}

		$monarch_options = ET_Monarch::get_options_array();
		$networks = $monarch_options[ 'follow_networks_networks_sorting' ];

		$follow_counts_output = '';
		if ( 'like' == $network ) {
			$follow_counts = ET_Monarch::get_likes_count();
		} else {
			$update_frequency = (int) $monarch_options[ 'general_main_update_freq' ];
			$api = $monarch_options[ 'follow_networks_use_api' ];

			if ( ! in_array( $network, ET_Monarch::get_follow_networks_with_api_support() ) ) {
				$api = false;
			}

			if ( false == $api ) {
				$follow_counts = '' != ( $manual_counts = $networks[ 'count' ][ $index ] ) ? ET_Monarch::get_full_number( $manual_counts ) : 0;
			} else {
				if ( 0 == $update_frequency ){
					$follow_counts = false != ( $follow_counts_received = ET_Monarch::get_api_follow_counts( $network, $index ) ) ? $follow_counts_received : 0;
				} else {
					$follow_counts = 'none' == get_transient( 'et_social_follow_counts_' . $network ) ? 0 : (int) get_transient( 'et_social_follow_counts_' . $network );

					if ( false == $follow_counts ) {
						$follow_counts = false != ( $follow_counts_received = ET_Monarch::get_api_follow_counts( $network, $index ) ) ? (int) $follow_counts_received : 0;
						$transient_value = 0 == $follow_counts ? 'none' : (int) $follow_counts;
						set_transient( 'et_social_follow_counts_' . $network, $transient_value, 60*60*$update_frequency );
					}
				}

			}
		}

		if ( $follow_counts >= $counts_num ) {
			if ( 'like' == $network ){
				$follow_text = 1 == $follow_counts ? __( 'Like', 'Monarch' ) : __( 'Likes', 'Monarch' );
			} else {
				$follow_text = 1 == ET_Monarch::get_full_number( $follow_counts ) ? __( 'Follower', 'Monarch' ) : __( 'Followers', 'Monarch' );
			}

			$follow_counts_output = false == $display
				? ET_Monarch::get_full_number( $follow_counts )
				: sprintf(
					'<div class="et_social_count">
						<span>%1$s</span>
						<span class="et_social_count_label">%2$s</span>
					</div>',
					esc_html( ET_Monarch::get_compact_number( $follow_counts ) ),
					esc_html( $follow_text )
				);
		}

		if ( ! $is_ajax_request ) {
			return $follow_counts_output;
		} else	{
			die( $follow_counts_output );
		}
	}

	public static function get_follow_total() {
		wp_verify_nonce( $_POST['get_total_counts_nonce'], 'get_total_counts' );

		$monarch_options = ET_Monarch::get_options_array();
		$networks = $monarch_options[ 'follow_networks_networks_sorting' ];

		$total_follows_count = 0;
		$i = 0;
		foreach( $networks[ 'class' ] as $network ) {

			if ( 'like' != $network ) { //exclude likes from total share counts
				$total_follows_count += ET_Monarch::get_follow_counts( 0, $network, $i, false, false );
			}

			$i++;

		}

		echo ET_Monarch::get_compact_number( $total_follows_count );

		die();
	}

	function add_purchase_trigger() {

		echo '<div class="et_monarch_after_order"></div>';

	}

	function load_scripts_styles() {
		$monarch_options = $this->monarch_options;

		if ( isset( $monarch_options[ 'general_main_reset_postdata' ] ) && true == $monarch_options[ 'general_main_reset_postdata' ] ) {
			wp_reset_postdata();
		}

		wp_enqueue_script( 'et_monarch-idle', ET_MONARCH_PLUGIN_URI . '/js/idle-timer.min.js', array( 'jquery' ), $this->plugin_version, true );
		wp_enqueue_script( 'et_monarch-custom-js', ET_MONARCH_PLUGIN_URI . '/js/custom.js', array( 'jquery' ), $this->plugin_version, true );
		wp_enqueue_style( 'et_monarch-open-sans', esc_url_raw( "{$this->protocol}://fonts.googleapis.com/css?family=Open+Sans:400,700" ), array(), null );
		wp_enqueue_style( 'et_monarch-css', ET_MONARCH_PLUGIN_URI . '/css/style.css', array(), $this->plugin_version );
		wp_localize_script( 'et_monarch-custom-js', 'monarchSettings', array(
			'ajaxurl' => admin_url( 'admin-ajax.php', $this->protocol ),
			'pageurl' => ( is_singular( get_post_types() ) ? get_permalink() : '' ),
			'stats_nonce' => wp_create_nonce( 'add_stats' ),
			'share_counts' => wp_create_nonce( 'get_share_counts' ),
			'follow_counts' => wp_create_nonce( 'get_follow_counts' ),
			'total_counts' => wp_create_nonce( 'get_total_counts' ),
			'media_single' => wp_create_nonce( 'get_media_single' ),
			'media_total' => wp_create_nonce( 'get_media_total' ),
			'generate_all_window_nonce' => wp_create_nonce( 'generate_all_window' ),
			'no_img_message' => __( 'No images available for sharing on this page', 'Monarch' ),
		) );
	}

	function after_comment_trigger( $location ){
		$monarch_options = $this->monarch_options;
		$newurl = $location;

		if ( ( isset( $monarch_options[ 'sharing_popup_trigger_comment' ] ) && true == $monarch_options[ 'sharing_popup_trigger_comment' ] ) || ( isset( $monarch_options[ 'sharing_flyin_trigger_comment' ] ) && true == $monarch_options[ 'sharing_flyin_trigger_comment' ] ) ) {
			$newurl = substr( $location, 0, strpos( $location, '#comment' ) );
			$delimeter = false === strpos( $location, '?' ) ? '?' : '&';
			$params = 'et_monarch_popup=true';

			$newurl .= $delimeter . $params;
		}

		return $newurl;
	}

	function frontend_register_locations() {
		$monarch_options = $this->monarch_options;
		if ( ! is_admin() ) {
			add_action( 'wp_head', array( $this, 'set_custom_css' ) );
			if ( ! empty( $monarch_options[ 'sharing_locations_manage_locations' ] ) ) {
				add_action( 'wp_footer', array( $this, 'generate_pinterest_picker' ) );
				foreach ( $monarch_options[ 'sharing_locations_manage_locations' ] as $location ){
					if ( method_exists( $this, 'display_' . $location ) ) {
						if ( 'inline' == $location ) {
							add_filter( 'the_content', array( $this, 'display_inline' ) );
							add_action( 'woocommerce_after_single_product_summary', array( $this, 'display_on_wc_page' ) );
						} elseif ( 'media' == $location ) {
							add_filter( 'the_content', array( $this, 'display_media' ), 9999 );
						} else {
							if ( ( 'popup' == $location && isset( $monarch_options[ 'sharing_popup_trigger_comment' ] ) && true == $monarch_options[ 'sharing_popup_trigger_comment' ] ) || ( 'flyin' == $location && isset( $monarch_options[ 'sharing_flyin_trigger_comment' ] ) && true == $monarch_options[ 'sharing_flyin_trigger_comment' ] ) ) {
								add_filter( 'comment_post_redirect', array( $this, 'after_comment_trigger' ) );
							}

							add_filter( 'the_content', array( $this, 'trigger_bottom_mark' ), 9999 );
							add_action( 'wp_footer', array( $this, "display_{$location}" ) );
							if ( 'flyin' == $location || 'popup' == $location ) {
								if ( ( isset( $monarch_options[ 'sharing_flyin_trigger_purchase' ] ) && true == $monarch_options[ 'sharing_flyin_trigger_purchase' ] ) || ( isset( $monarch_options[ 'sharing_popup_trigger_purchase' ] ) && true == $monarch_options[ 'sharing_popup_trigger_purchase' ] ) ) {
									add_action( 'woocommerce_thankyou', array( $this, 'add_purchase_trigger' ) );
								}
							}
						}
					}
				}
			}
		}
	}

}

new ET_Monarch();