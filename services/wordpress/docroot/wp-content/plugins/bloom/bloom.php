<?php
/*
 * Plugin Name: Bloom
 * Plugin URI: http://www.elegantthemes.com/plugins/bloom/
 * Version: 1.0.5
 * Description: A simple, comprehensive and beautifully constructed email opt-in plugin built to help you quickly grow your mailing list.
 * Author: Elegant Themes
 * Author URI: http://www.elegantthemes.com
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'ET_BLOOM_PLUGIN_DIR', trailingslashit( dirname(__FILE__) ) );
define( 'ET_BLOOM_PLUGIN_URI', plugins_url('', __FILE__) );

if ( ! class_exists( 'ET_Dashboard' ) ) {
	require_once( ET_BLOOM_PLUGIN_DIR . 'dashboard/dashboard.php' );
}

class ET_Bloom extends ET_Dashboard {
	var $plugin_version = '1.0.5';
	var $db_version = '1.0';
	var $_options_pagename = 'et_bloom_options';
	var $menu_page;
	var $protocol;

	private static $_this;

	function __construct() {
		// Don't allow more than one instance of the class
		if ( isset( self::$_this ) ) {
			wp_die( sprintf( __( '%s is a singleton class and you cannot create a second instance.', 'bloom' ),
				get_class( $this ) )
			);
		}

		self::$_this = $this;

		$this->protocol = is_ssl() ? 'https' : 'http';

		add_action( 'admin_menu', array( $this, 'add_menu_link' ) );

		add_action( 'plugins_loaded', array( $this, 'add_localization' ) );

		add_filter( 'et_bloom_import_sub_array', array( $this, 'import_settings' ) );
		add_filter( 'et_bloom_import_array', array( $this, 'import_filter' ) );
		add_filter( 'et_bloom_export_exclude', array( $this, 'filter_export_settings' ) );
		add_filter( 'et_bloom_save_button_class', array( $this, 'save_btn_class' ) );

		// generate home tab in dashboard
		add_action( 'et_bloom_after_header_options', array( $this, 'generate_home_tab' ) );

		add_action( 'et_bloom_after_main_options', array( $this, 'generate_premade_templates' ) );

		add_action( 'et_bloom_after_save_button', array( $this, 'add_next_button') );

		$plugin_file = plugin_basename( __FILE__ );
		add_filter( "plugin_action_links_{$plugin_file}", array( $this, 'add_settings_link' ) );


		$dashboard_args = array(
			'et_dashboard_options_pagename'  => $this->_options_pagename,
			'et_dashboard_plugin_name'       => 'bloom',
			'et_dashboard_save_button_text'  => 	__( 'Save & Exit', 'bloom' ),
			'et_dashboard_plugin_class_name' => 'et_bloom',
			'et_dashboard_options_path'      => ET_BLOOM_PLUGIN_DIR . '/dashboard/includes/options.php',
			'et_dashboard_options_page'      => 'toplevel_page',
		);

		parent::__construct( $dashboard_args );

		// Register save settings function for ajax request
		add_action( 'wp_ajax_et_bloom_save_settings', array( $this, 'bloom_save_settings' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts_styles' ) );

		add_action( 'wp_ajax_reset_options_page', array( $this, 'reset_options_page' ) );

		add_action( 'wp_ajax_bloom_remove_optin', array( $this, 'remove_optin' ) );

		add_action( 'wp_ajax_bloom_duplicate_optin', array( $this, 'duplicate_optin' ) );

		add_action( 'wp_ajax_bloom_add_variant', array( $this, 'add_variant' ) );

		add_action( 'wp_ajax_bloom_home_tab_tables', array( $this, 'home_tab_tables' ) );

		add_action( 'wp_ajax_bloom_toggle_optin_status', array( $this, 'toggle_optin_status' ) );

		add_action( 'wp_ajax_bloom_authorize_account', array( $this, 'authorize_account' ) );

		add_action( 'wp_ajax_bloom_reset_accounts_table', array( $this, 'reset_accounts_table' ) );

		add_action( 'wp_ajax_bloom_generate_mailing_lists', array( $this, 'generate_mailing_lists' ) );

		add_action( 'wp_ajax_bloom_generate_new_account_fields', array( $this, 'generate_new_account_fields' ) );

		add_action( 'wp_ajax_bloom_generate_accounts_list', array( $this, 'generate_accounts_list' ) );

		add_action( 'wp_ajax_bloom_generate_current_lists', array( $this, 'generate_current_lists' ) );

		add_action( 'wp_ajax_bloom_generate_edit_account_page', array( $this, 'generate_edit_account_page' ) );

		add_action( 'wp_ajax_bloom_save_account_tab', array( $this, 'save_account_tab' ) );

		add_action( 'wp_ajax_bloom_ab_test_actions', array( $this, 'ab_test_actions' ) );

		add_action( 'wp_ajax_bloom_get_stats_graph_ajax', array( $this, 'get_stats_graph_ajax' ) );

		add_action( 'wp_ajax_bloom_refresh_optins_stats_table', array( $this, 'refresh_optins_stats_table' ) );

		add_action( 'wp_ajax_bloom_reset_stats', array( $this, 'reset_stats' ) );

		add_action( 'wp_ajax_bloom_pick_winner_optin', array( $this, 'pick_winner_optin' ) );

		add_action( 'wp_ajax_bloom_clear_stats', array( $this, 'clear_stats' ) );

		add_action( 'wp_ajax_bloom_get_premade_values', array( $this, 'get_premade_values' ) );
		add_action( 'wp_ajax_bloom_generate_premade_grid', array( $this, 'generate_premade_grid' ) );

		add_action( 'wp_ajax_bloom_display_preview', array( $this, 'display_preview' ) );

		add_action( 'wp_ajax_bloom_handle_stats_adding', array( $this, 'handle_stats_adding' ) );
		add_action( 'wp_ajax_nopriv_bloom_handle_stats_adding', array( $this, 'handle_stats_adding' ) );

		add_action( 'wp_ajax_bloom_subscribe', array( $this, 'subscribe' ) );
		add_action( 'wp_ajax_nopriv_bloom_subscribe', array( $this, 'subscribe' ) );

		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		add_action( 'after_setup_theme', array( $this, 'register_image_sizes' ) );

		add_shortcode( 'et_bloom_inline', array( $this, 'display_inline_shortcode' ) );
		add_shortcode( 'et_bloom_locked', array( $this, 'display_locked_shortcode' ) );

		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );
		add_action( 'bloom_lists_auto_refresh', array( $this, 'perform_auto_refresh' ) );
		add_action( 'bloom_stats_auto_refresh', array( $this, 'perform_stats_refresh' ) );

		$this->frontend_register_locations();

		foreach ( array('post.php','post-new.php') as $hook ) {
			add_action( "admin_head-$hook", array( $this, 'tiny_mce_vars' ) );
			add_action( "admin_head-$hook", array( $this, 'add_mce_button_filters' ) );
		}

	}

	function activate_plugin() {
		// schedule lists auto update daily
		wp_schedule_event( time(), 'daily', 'bloom_lists_auto_refresh' );

		//install the db for stats
		$this->db_install();
	}

	function deactivate_plugin() {
		// remove lists auto updates from wp cron if plugin deactivated
		wp_clear_scheduled_hook( 'bloom_lists_auto_refresh' );
		wp_clear_scheduled_hook( 'bloom_stats_auto_refresh' );
	}

	function define_page_name() {
		return $this->_options_pagename;
	}

	/**
	 * Returns an instance of the object
	 *
	 * @return object
	 */
	static function get_this() {
		return self::$_this;
	}

	function add_menu_link() {
		$menu_page = add_menu_page( __( 'Bloom', 'bloom' ), __( 'Bloom', 'bloom' ), 'manage_options', 'et_bloom_options', array( $this, 'options_page' ) );
		add_submenu_page( 'et_bloom_options', __( 'Optin Forms', 'bloom' ), __( 'Optin Forms', 'bloom' ), 'manage_options', 'et_bloom_options' );
		add_submenu_page( 'et_bloom_options', __( 'Email Accounts', 'bloom' ), __( 'Email Accounts', 'bloom' ), 'manage_options', 'admin.php?page=et_bloom_options#tab_et_dashboard_tab_content_header_accounts' );
		add_submenu_page( 'et_bloom_options', __( 'Statistics', 'bloom' ), __( 'Statistics', 'bloom' ), 'manage_options', 'admin.php?page=et_bloom_options#tab_et_dashboard_tab_content_header_stats' );
		add_submenu_page( 'et_bloom_options', __( 'Import & Export', 'bloom' ), __( 'Import & Export', 'bloom' ), 'manage_options', 'admin.php?page=et_bloom_options#tab_et_dashboard_tab_content_header_importexport' );
	}

	function add_body_class( $body_class ) {
		$body_class[] = 'et_bloom';

		return $body_class;
	}

	function save_btn_class() {
		return 'et_dashboard_custom_save';
	}

	/**
	 * Adds plugin localization
	 * Domain: bloom
	 *
	 * @return void
	 */
	function add_localization() {
		load_plugin_textdomain( 'bloom', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	// Add settings link on plugin page
	function add_settings_link( $links ) {
		$settings_link = sprintf( '<a href="admin.php?page=et_bloom_options">%1$s</a>', __( 'Settings', 'bloom' ) );
		array_unshift( $links, $settings_link );
		return $links;
	}

	function options_page() {
		ET_Bloom::generate_options_page( $this->generate_optin_id() );
	}

	function import_settings() {
		return true;
	}

	function bloom_save_settings() {
		ET_Bloom::dashboard_save_settings();
	}

	function filter_export_settings( $options ) {
		$updated_array = array_merge( $options, array( 'accounts' ) );
		return $updated_array;
	}

	/**
	 *
	 * Adds the "Next" button into the Bloom dashboard via ET_Dashboard action.
	 * @return prints the data on screen
	 *
	 */
	function add_next_button() {
		printf( '
			<div class="et_dashboard_row et_dashboard_next_design">
				<button class="et_dashboard_icon">%1$s</button>
			</div>',
			__( 'Next: Design Your Optin', 'bloom' )
		);

		printf( '
			<div class="et_dashboard_row et_dashboard_next_display">
				<button class="et_dashboard_icon">%1$s</button>
			</div>',
			__( 'Next: Display Settings', 'bloom' )
		);

		printf( '
			<div class="et_dashboard_row et_dashboard_next_customize">
				<button class="et_dashboard_icon" data-selected_layout="layout_1">%1$s</button>
			</div>',
			__( 'Next: Customize', 'bloom' )
		);

		printf( '
			<div class="et_dashboard_row et_dashboard_next_shortcode">
				<button class="et_dashboard_icon">%1$s</button>
			</div>',
			__( 'Generate Shortcode', 'bloom' )
		);
	}

	/**
	 * Retrieves the Bloom options from DB and makes it available outside the class
	 * @return array
	 */
	public static function get_bloom_options() {
		return get_option( 'et_bloom_options' ) ? get_option( 'et_bloom_options' ) : array();
	}

	/**
	 * Updates the Bloom options outside the class
	 * @return void
	 */
	public static function update_bloom_options( $update_array ) {
		$dashboard_options = ET_Bloom::get_bloom_options();

		$updated_options = array_merge( $dashboard_options, $update_array );
		update_option( 'et_bloom_options', $updated_options );
	}

	/**
	 * Filters the options_array before importing data. Function generates new IDs for imported options to avoid replacement of existing ones.
	 * Filter is used in ET_Dashboard class
	 * @return array
	 */
	function import_filter( $options_array ) {
		$updated_array = array();
		$new_id = $this->generate_optin_id( false );

		foreach ( $options_array as $key => $value ) {
			$updated_array['optin_' . $new_id] = $options_array[$key];

			//reset accounts settings and make all new optins inactive
			$updated_array['optin_' . $new_id]['email_provider'] = 'empty';
			$updated_array['optin_' . $new_id]['account_name'] = 'empty';
			$updated_array['optin_' . $new_id]['email_list'] = 'empty';
			$updated_array['optin_' . $new_id]['optin_status'] = 'inactive';
			$new_id++;
		}

		return $updated_array;
	}

	function add_mce_button_filters() {
		add_filter( 'mce_external_plugins', array( $this, 'add_mce_button' ) );
		add_filter( 'mce_buttons', array( $this, 'register_mce_button' ) );
	}

	function add_mce_button( $plugin_array ) {
		global $typenow;

		wp_enqueue_style( 'bloom-shortcodes', ET_BLOOM_PLUGIN_URI . '/css/tinymcebutton.css', array(), $this->plugin_version );
		$plugin_array['bloom'] = ET_BLOOM_PLUGIN_URI . '/js/bloom-mce-buttons.js';


		return $plugin_array;
	}

	function register_mce_button( $buttons ) {
		global $typenow;

		array_push( $buttons, 'bloom_button' );

		return $buttons;
	}


	/**
	 * Pass locked_optins and inline_optins lists to tiny-MCE script
	 */
	function tiny_mce_vars() {
		$options_array = ET_Bloom::get_bloom_options();
		$locked_array = array();
		$inline_array = array();
		if ( ! empty( $options_array ) ) {
			foreach ( $options_array as $optin_id => $details ) {
				if ( 'accounts' !== $optin_id ) {
					if ( isset( $details['optin_status'] ) && 'active' === $details['optin_status'] && empty( $details['child_of'] ) ) {
						if ( 'inline' == $details['optin_type'] ) {
							$inline_array = array_merge( $inline_array, array( $optin_id => $details['optin_name'] ) );
						}

						if ( 'locked' == $details['optin_type'] ) {
							$locked_array = array_merge( $locked_array, array( $optin_id => $details['optin_name'] ) );
						}
					}
				}
			}
		}

		if ( empty( $locked_array ) ) {
			$locked_array = array(
				'empty' => __( 'No optins available', 'bloom' ),
			);
		}

		if ( empty( $inline_array ) ) {
			$inline_array = array(
				'empty' => __( 'No optins available', 'bloom' ),
			);
		}
	?>

	<!-- TinyMCE Shortcode Plugin -->
	<script type='text/javascript'>
		var bloom = {
			'locked_optins' : '<?php echo json_encode( $locked_array ); ?>',
			'inline_optins' : '<?php echo json_encode( $inline_array ); ?>',
			'bloom_tooltip' : '<?php _e( "insert bloom Opt-In", "bloom" ); ?>',
			'inline_text'   : '<?php _e( "Inline Opt-In", "bloom" ); ?>',
			'locked_text'   : '<?php _e( "Locked Content Opt-In", "bloom" ); ?>'
		}
	</script>
	<!-- TinyMCE Shortcode Plugin -->
<?php
	}

	function db_install() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'et_bloom_stats';

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
			record_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			record_type varchar(3) NOT NULL,
			optin_id varchar(20) NOT NULL,
			list_id varchar(100) NOT NULL,
			ip_address varchar(45) NOT NULL,
			page_id varchar(20) NOT NULL,
			removed_flag boolean NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$db_version = array(
			'db_version' => $this->db_version,
		);
		ET_Bloom::update_option( $db_version );
	}

	function register_image_sizes() {
		add_image_size( 'bloom_image', 610 );
	}

	/**
	 * Generates the Bloom's Home, Stats, Accounts tabs. Hooked to Dashboard class
	 */
	function generate_home_tab( $option, $dashboard_settings = array() ) {
		switch ( $option['type'] ) {
			case 'home' :
				printf( '
					<div class="et_dashboard_row et_dashboard_new_optin">
						<h1>%2$s</h1>
						<button class="et_dashboard_icon">%1$s</button>
						<input type="hidden" name="action" value="new_optin" />
					</div>' ,
					esc_html__( 'new optin', 'bloom' ),
					esc_html__( 'Active Optins', 'bloom' )
				);
				printf( '
					<div class="et_dashboard_row et_dashboard_optin_select">
						<h3>%1$s</h3>
						<span class="et_dashboard_icon et_dashboard_close_button"></span>
						<ul>
							<li class="et_dashboard_optin_type et_dashboard_optin_add et_dashboard_optin_type_popup" data-type="pop_up">
								<h6>%2$s</h6>
								<div class="optin_select_grey">
									<div class="optin_select_blue">
									</div>
								</div>
							</li>
							<li class="et_dashboard_optin_type et_dashboard_optin_add et_dashboard_optin_type_flyin" data-type="flyin">
								<h6>%3$s</h6>
								<div class="optin_select_grey"></div>
								<div class="optin_select_blue"></div>
							</li>
							<li class="et_dashboard_optin_type et_dashboard_optin_add et_dashboard_optin_type_below" data-type="below_post">
								<h6>%4$s</h6>
								<div class="optin_select_grey"></div>
								<div class="optin_select_blue"></div>
							</li>
							<li class="et_dashboard_optin_type et_dashboard_optin_add et_dashboard_optin_type_inline" data-type="inline">
								<h6>%5$s</h6>
								<div class="optin_select_grey"></div>
								<div class="optin_select_blue"></div>
								<div class="optin_select_grey"></div>
							</li>
							<li class="et_dashboard_optin_type et_dashboard_optin_add et_dashboard_optin_type_locked" data-type="locked">
								<h6>%6$s</h6>
								<div class="optin_select_grey"></div>
								<div class="optin_select_blue"></div>
								<div class="optin_select_grey"></div>
							</li>
							<li class="et_dashboard_optin_type et_dashboard_optin_add et_dashboard_optin_type_widget" data-type="widget">
								<h6>%7$s</h6>
								<div class="optin_select_grey"></div>
								<div class="optin_select_blue"></div>
								<div class="optin_select_grey_small"></div>
								<div class="optin_select_grey_small last"></div>
							</li>
						</ul>
					</div>',
					esc_html__( 'select optin type to begin', 'bloom' ),
					esc_html__( 'pop up', 'bloom' ),
					esc_html__( 'fly in', 'bloom' ),
					esc_html__( 'below post', 'bloom' ),
					esc_html__( 'inline', 'bloom' ),
					esc_html__( 'locked content', 'bloom' ),
					esc_html__( 'widget', 'bloom' )
				);

				$this->display_home_tab_tables();
			break;

			case 'account' :
				printf( '
					<div class="et_dashboard_row et_dashboard_new_account_row">
						<h1>%2$s</h1>
						<button class="et_dashboard_icon">%1$s</button>
						<input type="hidden" name="action" value="new_account" />
					</div>' ,
					esc_html__( 'new account', 'bloom' ),
					esc_html__( 'My Accounts', 'bloom' )
				);

				$this->display_accounts_table();
			break;

			case 'edit_account' :
				echo '<div id="et_dashboard_edit_account_tab"></div>';
			break;

			case 'stats' :
				printf( '
					<div class="et_dashboard_row et_dashboard_stats_row">
						<h1>%1$s</h1>
						<div class="et_bloom_stats_controls">
							<button class="et_dashboard_icon et_bloom_clear_stats">%2$s</button>
							<span class="et_dashboard_confirmation">%4$s</span>
							<button class="et_dashboard_icon et_bloom_refresh_stats">%3$s</button>
						</div>
					</div>
					<span class="et_bloom_stats_spinner"></span>
					<div class="et_dashboard_stats_contents"></div>',
					esc_html( $option['title'] ),
					esc_html__( 'Clear Stats', 'bloom' ),
					esc_html__( 'Refresh Stats', 'bloom' ),
					sprintf(
						'%1$s<span class="et_dashboard_confirm_stats">%2$s</span><span class="et_dashboard_cancel_delete">%3$s</span>',
						esc_html__( 'Remove all the stats data?', 'bloom' ),
						esc_html__( 'Yes', 'bloom' ),
						esc_html__( 'No', 'bloom' )
					)
				);
			break;
		}
	}

	/**
	 * Generates tab for the premade layouts selection
	 */
	function generate_premade_templates( $option ) {
		switch ( $option['type'] ) {
			case 'premade_templates' :
				echo '<div class="et_bloom_premade_grid"><span class="spinner et_bloom_premade_spinner"></span></div>';
				break;
			case 'preview_optin' :
				printf( '
					<div class="et_dashboard_row et_dashboard_preview">
						<button class="et_dashboard_icon">%1$s</button>
					</div>',
					esc_html__( 'Preview', 'bloom' )
				);
				break;
		}
	}

	function generate_premade_grid() {
		wp_verify_nonce( $_POST['bloom_premade_nonce'] , 'bloom_premade' );

		require_once( ET_BLOOM_PLUGIN_DIR . 'includes/premade-layouts.php' );
		$output = '';

		if ( isset( $all_layouts ) ) {
			$i = 0;

			$output .= '<div class="et_bloom_premade_grid">';

			foreach( $all_layouts as $layout_id => $layout_options ) {
				$output .= sprintf( '
					<div class="et_bloom_premade_item%2$s et_bloom_premade_id_%1$s" data-layout="%1$s">
						<div class="et_bloom_premade_item_inner">
							<img src="%3$s" alt="" />
						</div>
					</div>',
					esc_attr( $layout_id ),
					0 == $i ? ' et_bloom_layout_selected' : '',
					esc_attr( ET_BLOOM_PLUGIN_URI . '/images/thumb_' . $layout_id . '.svg' )
				);
				$i++;
			}

			$output .= '</div>';
		}

		die( $output );
	}

	/**
	 * Gets the layouts data, converts it to json string and passes back to js script to fill the form with predefined values
	 */
	function get_premade_values() {
		wp_verify_nonce( $_POST['bloom_premade_nonce'] , 'bloom_premade' );

		$premade_data_json = str_replace( '\\', '' ,  $_POST['premade_data_array'] );
		$premade_data = json_decode( $premade_data_json, true );
		$layout_id = $premade_data['id'];

		require_once( ET_BLOOM_PLUGIN_DIR . 'includes/premade-layouts.php' );

		if ( isset( $all_layouts[$layout_id] ) ) {
			$options_set = json_encode( $all_layouts[$layout_id] );
		}

		die( $options_set );
	}

	/**
	 * Generates output for the Stats tab
	 */
	function generate_stats_tab() {
		$options_array = ET_Bloom::get_bloom_options();

		$output = sprintf( '
			<div class="et_dashboard_stats_contents et_dashboard_stats_ready">
				<div class="et_dashboard_all_time_stats">
					<h3>%1$s</h3>
					%2$s
				</div>
				<div class="et_dashboard_optins_stats et_dashboard_optins_all_table">
					<div class="et_dashboard_optins_list">
						%3$s
					</div>
				</div>
				<div class="et_dashboard_optins_stats et_dashboard_lists_stats_graph">
					<div class="et_bloom_graph_header">
						<h3>%6$s</h3>
						<div class="et_bloom_graph_controls">
							<a href="#" class="et_bloom_graph_button et_bloom_active_button" data-period="30">%7$s</a>
							<a href="#" class="et_bloom_graph_button" data-period="12">%8$s</a>
							<select class="et_bloom_graph_select_list">%9$s</select>
						</div>
					</div>
					%5$s
				</div>
				<div class="et_dashboard_optins_stats et_dashboard_lists_stats">
					%4$s
				</div>
				%10$s
			</div>',
			esc_html__( 'Overview', 'bloom' ),
			$this->generate_all_time_stats(),
			$this->generate_optins_stats_table( 'conversion_rate', true ),
			( ! empty( $options_array['accounts'] ) )
				? sprintf(
					'<div class="et_dashboard_optins_list">
						%1$s
					</div>',
					$this->generate_lists_stats_table( 'count', true )
				)
				: '',
			$this->generate_lists_stats_graph( 30, 'day', '' ), // #5
			esc_html__( 'New sign ups', 'bloom' ),
			esc_html__( 'Last 30 days', 'bloom' ),
			esc_html__( 'Last 12 month', 'bloom' ),
			$this->generate_all_lists_select(),
			$this->generate_pages_stats() // #10
		);

		return $output;
	}

	/**
	 * Generates the stats tab and passes it to jQuery
	 * @return string
	 */
	function reset_stats() {
		wp_verify_nonce( $_POST['bloom_stats_nonce'] , 'bloom_stats' );
		$force_update = ! empty( $_POST['bloom_force_upd_stats'] ) ? sanitize_text_field( $_POST['bloom_force_upd_stats'] ) : '';

		if ( get_option( 'et_bloom_stats_cache' ) && 'true' !== $force_update ) {
			$output = get_option( 'et_bloom_stats_cache' );
		} else {
			$output = $this->generate_stats_tab();
			update_option( 'et_bloom_stats_cache', $output );
		}

		if ( ! wp_get_schedule( 'bloom_stats_auto_refresh' ) ) {
			wp_schedule_event( time(), 'daily', 'bloom_stats_auto_refresh' );
		}

		die( $output );
	}

	/**
	 * Update Stats and save it into WP DB
	 * @return void
	 */
	function perform_stats_refresh() {
		$fresh_stats = $output = $this->generate_stats_tab();
		update_option( 'et_bloom_stats_cache', $fresh_stats );
	}

	/**
	 * Removes all the stats data from DB
	 * @return void
	 */
	function clear_stats() {
		wp_verify_nonce( $_POST['bloom_stats_nonce'] , 'bloom_stats' );

		global $wpdb;

		$table_name = $wpdb->prefix . 'et_bloom_stats';

		// construct sql query to mark removed options as removed in stats DB
		$sql = "TRUNCATE TABLE $table_name";

		$wpdb->query( $sql );
	}

	/**
	 * Generates the Lists menu for Lists stats graph
	 * @return string
	 */
	function generate_all_lists_select() {
		$options_array = ET_Bloom::get_bloom_options();
		$output = sprintf( '<option value="all">%1$s</option>', __( 'All lists', 'bloom' ) );

		if ( ! empty( $options_array['accounts'] ) ) {
			foreach ( $options_array['accounts'] as $service => $accounts ) {
				foreach ( $accounts as $name => $details ) {
					if ( ! empty( $details['lists'] ) ) {
						foreach ( $details['lists'] as $id => $list_data ) {
							$output .= sprintf(
								'<option value="%2$s">%1$s</option>',
								esc_html( $service . ' - ' . $list_data['name'] ),
								esc_attr( $service . '_' . $id )
							);
						}
					}
				}
			}
		}

		return $output;
	}

	/**
	 * Generates the Overview part of stats page
	 * @return string
	 */
	function generate_all_time_stats( $empty_stats = false ) {

		$conversion_rate = $this->conversion_rate( 'all' );

		$all_subscribers = $this->calculate_subscribers( 'all' );

		$growth_rate = $this->calculate_growth_rate( 'all' );

		$ouptut = sprintf(
			'<div class="et_dashboard_stats_container">
				<div class="all_stats_column conversion_rate">
					<span class="value">%1$s</span>
					<span class="caption">%2$s</span>
				</div>
				<div class="all_stats_column subscribers">
					<span class="value">%3$s</span>
					<span class="caption">%4$s</span>
				</div>
				<div class="all_stats_column growth_rate">
					<span class="value">%5$s<span>/%7$s</span></span>
					<span class="caption">%6$s</span>
				</div>
				<div style="clear: both;"></div>
			</div>',
			$conversion_rate . '%',
			__( 'Conversion Rate', 'bloom' ),
			$all_subscribers,
			__( 'Subscribers', 'bloom' ),
			$growth_rate,
			__( 'Subscriber Growth', 'bloom' ),
			__( 'week', 'bloom' )
		);

		return $ouptut;
	}

	/**
	 * Generates the stats table with optins
	 * @return string
	 */
	function generate_optins_stats_table( $orderby = 'conversion_rate', $include_header = false ) {
		$options_array = ET_Bloom::get_bloom_options();
		$optins_count = 0;
		$output = '';
		$total_impressions = 0;
		$total_conversions = 0;

		foreach ( $options_array as $optin_id => $value ) {
			if ( 'accounts' !== $optin_id && 'db_version' !== $optin_id ) {
				if ( 0 === $optins_count ) {
					if ( true == $include_header ) {
						$output .= sprintf(
							'<ul>
								<li data-table="optins">
									<div class="et_dashboard_table_name et_dashboard_table_column et_table_header">%1$s</div>
									<div class="et_dashboard_table_impressions et_dashboard_table_column et_dashboard_icon et_dashboard_sort_button" data-order_by="impressions">%2$s</div>
									<div class="et_dashboard_table_conversions et_dashboard_table_column et_dashboard_icon et_dashboard_sort_button" data-order_by="conversions">%3$s</div>
									<div class="et_dashboard_table_rate et_dashboard_table_column et_dashboard_icon et_dashboard_sort_button active_sorting" data-order_by="conversion_rate">%4$s</div>
									<div style="clear: both;"></div>
								</li>
							</ul>',
							__( 'My Optins', 'bloom' ),
							__( 'Impressions', 'bloom' ),
							__( 'Conversions', 'bloom' ),
							__( 'Conversion Rate', 'bloom' )
						);
					}

					$output .= '<ul class="et_dashboard_table_contents">';
				}

				$total_impressions += $impressions = $this->stats_count( $optin_id, 'imp' );
				$total_conversions += $conversions = $this->stats_count( $optin_id, 'con' );

				$unsorted_optins[$optin_id] = array(
					'name'            => $value['optin_name'],
					'impressions'     => $impressions,
					'conversions'     => $conversions,
					'conversion_rate' => $this->conversion_rate( $optin_id, $conversions, $impressions ),
					'type'            => $value['optin_type'],
					'status'          => $value['optin_status'],
					'child_of'        => $value['child_of'],
				);
				$optins_count++;

			}
		}

		if ( ! empty( $unsorted_optins ) ) {
			$sorted_optins = $this->sort_array( $unsorted_optins, $orderby );

			foreach ( $sorted_optins as $id => $details ) {
				if ( '' !== $details['child_of'] ) {
					$status = $options_array[$details['child_of']]['optin_status'];
				} else {
					$status = $details['status'];
				}

				$output .= sprintf(
					'<li class="et_dashboard_optins_item et_dashboard_parent_item">
						<div class="et_dashboard_table_name et_dashboard_table_column et_dashboard_icon et_dashboard_type_%5$s et_dashboard_status_%6$s">%1$s</div>
						<div class="et_dashboard_table_impressions et_dashboard_table_column">%2$s</div>
						<div class="et_dashboard_table_conversions et_dashboard_table_column">%3$s</div>
						<div class="et_dashboard_table_rate et_dashboard_table_column">%4$s</div>
						<div style="clear: both;"></div>
					</li>',
					esc_html( $details['name'] ),
					esc_html( $details['impressions'] ),
					esc_html( $details['conversions'] ),
					esc_html( $details['conversion_rate'] ) . '%',
					esc_attr( $details['type'] ),
					esc_attr( $status )
				);
			}
		}

		if ( 0 < $optins_count ) {
			$output .= sprintf(
				'<li class="et_dashboard_optins_item_bottom_row">
					<div class="et_dashboard_table_name et_dashboard_table_column"></div>
					<div class="et_dashboard_table_impressions et_dashboard_table_column">%1$s</div>
					<div class="et_dashboard_table_conversions et_dashboard_table_column">%2$s</div>
					<div class="et_dashboard_table_rate et_dashboard_table_column">%3$s</div>
				</li>',
				$this->get_compact_number( $total_impressions ),
				$this->get_compact_number( $total_conversions ),
				( 0 !== $total_impressions )
					? round( ( $total_conversions * 100 ) / $total_impressions, 1 ) . '%'
					: '0%'
			);
			$output .= '</ul>';
		}

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

	/**
	 * Generates the highest converting pages table
	 * @return string
	 */
	function generate_pages_stats() {
		$all_pages_id = $this->get_all_stats_pages();
		$con_by_pages = array();
		$output = '';

		if ( empty( $all_pages_id ) ) {
			return;
		}

		foreach( $all_pages_id as $page ) {
			$con_by_pages[$page['page_id']] = $this->get_unique_optins_by_page( $page['page_id'] );
		}

		if ( ! empty( $con_by_pages ) ) {
			foreach ( $con_by_pages as $page_id => $optins ) {
				$unique_optins = array();
				foreach( $optins as $optin_id ) {
					if ( ! in_array( $optin_id, $unique_optins ) ) {
						$unique_optins[] = $optin_id;
						$rate_by_pages[$page_id][] = array(
							$optin_id => $this->conversion_rate( $optin_id, '0', '0', $page_id ),
						);
					}
				}
			}

			$i = 0;

			foreach ( $rate_by_pages as $page_id => $rate ) {
				$page_rate = 0;
				$rates_count = 0;
				$optins_data = array();
				$j = 0;

				foreach ( $rate as $current_optin ) {
					foreach ( $current_optin as $optin_id => $current_rate ) {
						$page_rate = $page_rate + $current_rate;
						$rates_count++;

						$optins_data[$j] = array(
							'optin_id' => $optin_id,
							'optin_rate' => $current_rate,
						);

					}
					$j++;
				}

				$average_rate = 0 != $rates_count ? round( $page_rate / $rates_count, 1 ) : 0;
				$rate_by_pages_unsorted[$i]['page_id'] = $page_id;
				$rate_by_pages_unsorted[$i]['page_rate'] = $average_rate;
				$rate_by_pages_unsorted[$i]['optins_data'] = $this->sort_array( $optins_data, 'optin_rate', $order = SORT_DESC );

				$i++;
			}

			$rate_by_pages_sorted = $this->sort_array( $rate_by_pages_unsorted, 'page_rate', $order = SORT_DESC );
			$output = '';

			if ( ! empty( $rate_by_pages_sorted ) ) {
				$options_array = ET_Bloom::get_bloom_options();
				$table_contents = '<ul>';

				for ( $i = 0; $i < 5; $i++ ) {
					if ( ! empty( $rate_by_pages_sorted[$i] ) ) {
						$table_contents .= sprintf(
							'<li class="et_table_page_row">
								<div class="et_dashboard_table_name et_dashboard_table_column et_table_page_row">%1$s</div>
								<div class="et_dashboard_table_pages_rate et_dashboard_table_column">%2$s</div>
								<div style="clear: both;"></div>
							</li>',
							-1 == $rate_by_pages_sorted[$i]['page_id']
								? __( 'Homepage', 'bloom' )
								: esc_html( get_the_title( $rate_by_pages_sorted[$i]['page_id'] ) ),
							esc_html( $rate_by_pages_sorted[$i]['page_rate'] ) . '%'
						);
						foreach ( $rate_by_pages_sorted[$i]['optins_data'] as $optin_details ) {
							if ( isset( $options_array[$optin_details['optin_id']]['child_of'] ) && '' !== $options_array[$optin_details['optin_id']]['child_of'] ) {
								$status = $options_array[$options_array[$optin_details['optin_id']]['child_of']]['optin_status'];
							} else {
								$status = isset( $options_array[$optin_details['optin_id']]['optin_status'] ) ? $options_array[$optin_details['optin_id']]['optin_status'] : 'inactive';
							}

							$table_contents .= sprintf(
								'<li class="et_table_optin_row et_dashboard_optins_item">
									<div class="et_dashboard_table_name et_dashboard_table_column et_dashboard_icon et_dashboard_type_%3$s et_dashboard_status_%4$s">%1$s</div>
									<div class="et_dashboard_table_pages_rate et_dashboard_table_column">%2$s</div>
									<div style="clear: both;"></div>
								</li>',
								( isset( $options_array[$optin_details['optin_id']]['optin_name'] ) )
									? esc_html( $options_array[$optin_details['optin_id']]['optin_name'] )
									: '',
								esc_html( $optin_details['optin_rate'] ) . '%',
								( isset( $options_array[$optin_details['optin_id']]['optin_type'] ) )
									? esc_attr( $options_array[$optin_details['optin_id']]['optin_type'] )
									: '',
								esc_attr( $status )
							);
						}
					}
				}

				$table_contents .= '</ul>';

				$output = sprintf(
					'<div class="et_dashboard_optins_stats et_dashboard_pages_stats">
						<div class="et_dashboard_optins_list">
							<ul>
								<li>
									<div class="et_dashboard_table_name et_dashboard_table_column et_table_header">%1$s</div>
									<div class="et_dashboard_table_pages_rate et_dashboard_table_column et_table_header">%2$s</div>
									<div style="clear: both;"></div>
								</li>
							</ul>
							%3$s
						</div>
					</div>',
					__( 'Highest converting pages', 'bloom' ),
					__( 'Conversion rate', 'bloom' ),
					$table_contents
				);
			}
		}

		return $output;
	}

	/**
	 * Generates the stats table with lists
	 * @return string
	 */
	function generate_lists_stats_table( $orderby = 'count', $include_header = false ) {
		$options_array = ET_Bloom::get_bloom_options();
		$optins_count = 0;
		$output = '';
		$total_subscribers = 0;

		if ( ! empty( $options_array['accounts'] ) ) {
			foreach ( $options_array['accounts'] as $service => $accounts ) {
				foreach ( $accounts as $name => $details ) {
					if ( ! empty( $details['lists'] ) ) {
						foreach ( $details['lists'] as $id => $list_data ) {
							if ( 0 === $optins_count ) {
								if ( true == $include_header ) {
									$output .= sprintf(
										'<ul>
											<li data-table="lists">
												<div class="et_dashboard_table_name et_dashboard_table_column et_table_header">%1$s</div>
												<div class="et_dashboard_table_impressions et_dashboard_table_column et_dashboard_icon et_dashboard_sort_button" data-order_by="service">%2$s</div>
												<div class="et_dashboard_table_rate et_dashboard_table_column et_dashboard_icon et_dashboard_sort_button active_sorting" data-order_by="count">%3$s</div>
												<div class="et_dashboard_table_conversions et_dashboard_table_column et_dashboard_icon et_dashboard_sort_button" data-order_by="growth">%4$s</div>
												<div style="clear: both;"></div>
											</li>
										</ul>',
										esc_html__( 'My Lists', 'bloom' ),
										esc_html__( 'Provider', 'bloom' ),
										esc_html__( 'Subscribers', 'bloom' ),
										esc_html__( 'Growth Rate', 'bloom' )
									);
								}

								$output .= '<ul class="et_dashboard_table_contents">';
							}

							$total_subscribers += $list_data['subscribers_count'];

							$unsorted_array[] = array(
								'name'    => $list_data['name'],
								'service' => $service,
								'count'   => $list_data['subscribers_count'],
								'growth'  => $list_data['growth_week'],
							);

							$optins_count++;
						}
					}
				}
			}
		}

		if ( ! empty( $unsorted_array ) ) {
			$order = 'service' == $orderby ? SORT_ASC : SORT_DESC;

			$sorted_array = $this->sort_array( $unsorted_array, $orderby, $order );

			foreach ( $sorted_array as $single_list ) {
				$output .= sprintf(
					'<li class="et_dashboard_optins_item et_dashboard_parent_item">
						<div class="et_dashboard_table_name et_dashboard_table_column">%1$s</div>
						<div class="et_dashboard_table_conversions et_dashboard_table_column">%2$s</div>
						<div class="et_dashboard_table_rate et_dashboard_table_column">%3$s</div>
						<div class="et_dashboard_table_impressions et_dashboard_table_column">%4$s/%5$s</div>
						<div style="clear: both;"></div>
					</li>',
					esc_html( $single_list['name'] ),
					esc_html( $single_list['service'] ),
					'ontraport' == $single_list['service'] ? esc_html__( 'n/a', 'bloom' ) : esc_html( $single_list['count'] ),
					esc_html( $single_list['growth'] ),
					esc_html__( 'week', 'bloom' )
				);
			}
		}

		if ( 0 < $optins_count ) {
			$output .= sprintf(
				'<li class="et_dashboard_optins_item_bottom_row">
					<div class="et_dashboard_table_name et_dashboard_table_column"></div>
					<div class="et_dashboard_table_conversions et_dashboard_table_column"></div>
					<div class="et_dashboard_table_rate et_dashboard_table_column">%1$s</div>
					<div class="et_dashboard_table_impressions et_dashboard_table_column">%2$s/%3$s</div>
				</li>',
				esc_html( $total_subscribers ),
				esc_html( $this->calculate_growth_rate( 'all' ) ),
				esc_html__( 'week', 'bloom' )
			);
			$output .= '</ul>';
		}

		return $output;
	}

	/**
	 * Calculates the conversion rate for the optin
	 * Can calculate rate for removed/existing optins and for particular pages.
	 * @return int
	 */
	function conversion_rate( $optin_id, $con_data = '0', $imp_data = '0', $page_id = 'all' ) {
		$conversion_rate = 0;

		$current_conversion = '0' === $con_data ? $this->stats_count( $optin_id, 'con', $page_id ) : $con_data;
		$current_impression = '0' === $imp_data ? $this->stats_count( $optin_id, 'imp', $page_id ) : $imp_data;

		if ( 0 < $current_impression ) {
			$conversion_rate = 	( $current_conversion * 100 )/$current_impression;
		}

		$conversion_rate_output = round( $conversion_rate, 1 );

		return $conversion_rate_output;
	}

	/**
	 * Calculates the conversions/impressions count for the optin
	 * Can calculate conversions for particular pages.
	 * @return int
	 */
	function stats_count( $optin_id, $type = 'imp', $page_id = 'all' ) {
		global $wpdb;

		$stats_count = 0;
		$optin_id = 'all' == $optin_id ? '*' : $optin_id;

		$table_name = $wpdb->prefix . 'et_bloom_stats';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
			// construct sql query to get all the conversions from db
			$sql = "SELECT COUNT(*) FROM $table_name WHERE record_type = %s AND optin_id = %s";
			$sql_args = array(
				sanitize_text_field( $type ),
				sanitize_text_field( $optin_id )
			);

			if ( 'all' !== $page_id ) {
				$sql .= " AND page_id = %s";
				$sql_args[] = sanitize_text_field( $page_id );
			}

			// cache the data from conversions table
			$stats_count = $wpdb->get_var( $wpdb->prepare( $sql, $sql_args ) );
		}

		return $stats_count;
	}

	function get_conversions() {
		global $wpdb;
		$conversions = array();

		$table_name = $wpdb->prefix . 'et_bloom_stats';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
			// construct sql query to get all the conversions from db
			$sql = "SELECT * FROM $table_name WHERE record_type = 'con' ORDER BY record_date DESC";

			// cache the data from conversions table
			$conversions = $wpdb->get_results( $sql, ARRAY_A );
		}

		return $conversions;
	}

	function get_all_stats_pages() {
		global $wpdb;

		$all_pages = array();

		$table_name = $wpdb->prefix . 'et_bloom_stats';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
			// construct sql query to get all the conversions from db
			$sql = "SELECT DISTINCT page_id FROM $table_name";

			// cache the data from conversions table
			$all_pages = $wpdb->get_results( $sql, ARRAY_A );
		}

		return $all_pages;
	}

	function get_unique_optins_by_page( $page_id ) {
		global $wpdb;

		$all_optins = array();
		$all_optins_final = array();

		$table_name = $wpdb->prefix . 'et_bloom_stats';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
			// construct sql query to get all the conversions from db
			$sql = "SELECT DISTINCT optin_id FROM $table_name where page_id = %s";
			$sql_args = array( sanitize_text_field( $page_id ) );

			// cache the data from conversions table
			$all_optins = $wpdb->get_results( $wpdb->prepare( $sql, $sql_args ), ARRAY_A );
		}
		if ( ! empty( $all_optins ) ) {
			foreach( $all_optins as $optin ) {
				$all_optins_final[] = $optin['optin_id'];
			}
		}
		return $all_optins_final;
	}

	/**
	 * Calculates growth rate of the list. list_id should be provided in following format: <service>_<list_id>
	 * @return int
	 */
	function calculate_growth_rate( $list_id ) {
		$list_id = 'all' == $list_id ? '' : $list_id;

		$stats = $this->generate_stats_by_period( 28, 'day', $this->get_conversions(), $list_id );
		$total_subscribers = $stats['total_subscribers_28'];
		$oldest_record = -1;

		for ( $i = 28; $i > 0; $i-- ) {
			if ( !empty( $stats[$i] ) ) {
				if ( -1 === $oldest_record ) {
					$oldest_record = $i;
				}
			}
		}

		if ( -1 === $oldest_record ) {
			$growth_rate = 0;
		} else {
			$weeks_count = round( ( $oldest_record ) / 7, 0 );
			$weeks_count = 0 == $weeks_count ? 1 : $weeks_count;
			$growth_rate = round( $total_subscribers / $weeks_count, 0 );
		}

		return $growth_rate;
	}

	/**
	 * Calculates all the subscribers using data from accounts
	 * @return string
	 */
	function calculate_subscribers( $period, $service = '', $account_name = '', $list_id = '' ) {
		$options_array = ET_Bloom::get_bloom_options();
		$subscribers_count = 0;

		if ( 'all' === $period ) {
			if ( ! empty( $options_array['accounts']) ) {
				foreach ( $options_array['accounts'] as $service => $accounts ) {
					foreach ( $accounts as $name => $details ) {
						foreach( $details['lists'] as $id => $list_details ) {
							if ( ! empty( $list_details['subscribers_count'] ) ) {
								$subscribers_count += $list_details['subscribers_count'];
							}
						}
					}
				}
			}
		}

		return $this->get_compact_number( $subscribers_count );
	}

	/**
	 * Generates output for the lists stats graph.
	 */
	function generate_lists_stats_graph( $period, $day_or_month, $list_id = '' ) {
		$all_stats_rows = $this->get_conversions();

		$stats = $this->generate_stats_by_period( $period, $day_or_month, $all_stats_rows, $list_id );

		$output = $this->generate_stats_graph_output( $period, $day_or_month, $stats );

		return $output;
	}

	/**
	 * Generates stats array by specified period and using provided data.
	 * @return array
	 */
	function generate_stats_by_period( $period, $day_or_month, $input_data, $list_id = '' ) {
		$subscribers = array();

		$j = 0;
		$count_subscribers = 0;

		for( $i = 1; $i <= $period; $i++ ) {
			if ( array_key_exists( $j, $input_data ) ) {
				$count_subtotal = 1;

				while ( array_key_exists( $j, $input_data ) && strtotime( 'now' ) <= strtotime( sprintf( '+ %d %s', $i, 'day' == $day_or_month ? 'days' : 'month' ), strtotime( $input_data[ $j ][ 'record_date' ] ) ) ) {

					if ( '' === $list_id || ( '' !== $list_id && $list_id === $input_data[$j]['list_id'] ) ) {
						$subscribers[$i]['subtotal'] = $count_subtotal++;

						$count_subscribers++;

						if ( array_key_exists( $i, $subscribers ) && array_key_exists( $input_data[$j]['list_id'], $subscribers[$i] ) ) {
							$subscribers[$i][$input_data[$j]['list_id']]['count']++;
						} else {
							$subscribers[$i][$input_data[$j]['list_id']]['count'] = 1;
						}
					}

					$j++;
				}
			}

			// Add total counts for each period into array
			if ( 'day' == $day_or_month ) {
				if ( $i == $period ) {
					$subscribers[ 'total_subscribers_' . $period ] = $count_subscribers;
				}
			} else {
				if ( $i == 12 ) {
					$subscribers[ 'total_subscribers_12' ] = $count_subscribers;
				}
			}
		}

		return $subscribers;
	}

	/**
	 * Generated the output for lists graph. Period and data array are required
	 * @return string
	 */
	function generate_stats_graph_output( $period, $day_or_month, $data ) {
		$result = '<div class="et_dashboard_lists_stats_graph_container">';
		$result .= sprintf(
			'<ul class="et_bloom_graph_%1$s et_bloom_graph">',
			esc_attr( $period )
		);
		$bars_count = 0;

		for ( $i = 1; $i <= $period ; $i++ ) {
			$result .= sprintf( '<li%1$s>',
				$period == $i ? ' class="et_bloom_graph_last"' : ''
			);

			if ( array_key_exists( $i, $data ) ) {
				$result .= sprintf( '<div value="%1$s" class="et_bloom_graph_bar">',
					esc_attr( $data[$i]['subtotal'] )
				);

				$bars_count++;

				$result .= '</div>';
			} else {
				$result .= '<div value="0"></div>';
			}

			$result .= '</li>';
		}

		$result .= '</ul>';

		if ( 0 < $bars_count ) {
			$per_day = round( $data['total_subscribers_' . $period] / $bars_count, 0 );
		} else {
			$per_day = 0;
		}

		$result .= sprintf(
			'<div class="et_bloom_overall">
				<span class="total_signups">%1$s | </span>
				<span class="signups_period">%2$s</span>
			</div>',
			sprintf(
				'%1$s %2$s',
				esc_html( $data['total_subscribers_' . $period] ),
				esc_html__( 'New Signups', 'bloom' )
			),
			sprintf(
				'%1$s %2$s %3$s',
				esc_html( $per_day ),
				esc_html__( 'Per', 'bloom' ),
				'day' == $day_or_month ? esc_html__( 'Day', 'bloom' ) : esc_html__( 'Month', 'bloom' )
			)
		);

		$result .= '</div>';

		return $result;
	}

	/**
	 * Generates the lists stats graph and passes it to jQuery
	 */
	function get_stats_graph_ajax() {
		wp_verify_nonce( $_POST['bloom_stats_nonce'] , 'bloom_stats' );
		$list_id = ! empty( $_POST['bloom_list'] ) ? sanitize_text_field( $_POST['bloom_list'] ) : '';
		$period = ! empty( $_POST['bloom_period'] ) ? sanitize_text_field( $_POST['bloom_period'] ) : '';

		$day_or_month = '30' == $period ? 'day' : 'month';
		$list_id = 'all' == $list_id ? '' : $list_id;

		$output = $this->generate_lists_stats_graph( $period, $day_or_month, $list_id );

		die( $output );
	}

	/**
	 * Generates the optins stats table and passes it to jQuery
	 */
	function refresh_optins_stats_table() {
		wp_verify_nonce( $_POST['bloom_stats_nonce'] , 'bloom_stats' );
		$orderby = ! empty( $_POST['bloom_orderby'] ) ? sanitize_text_field( $_POST['bloom_orderby'] ) : '';
		$table = ! empty( $_POST['bloom_stats_table'] ) ? sanitize_text_field( $_POST['bloom_stats_table'] ) : '';

		if ( 'optins' === $table ) {
			$output = $this->generate_optins_stats_table( $orderby );
		}
		if ( 'lists' === $table ) {
			$output = $this->generate_lists_stats_table( $orderby );
		}

		die( $output );
	}

	/**
	 * Converts number >1000 into compact numbers like 1k
	 */
	public static function get_compact_number( $full_number ) {
		if ( 1000000 <= $full_number ) {
			$full_number = floor( $full_number / 100000 ) / 10;
			$full_number .= 'Mil';
		} elseif ( 1000 < $full_number ) {
			$full_number = floor( $full_number / 100 ) / 10;
			$full_number .= 'k';
		}

		return $full_number;
	}

	/**
	 * Converts compact numbers like 1k into full numbers like 1000
	 */
	public static function get_full_number( $compact_number ) {
		if ( false !== strrpos( $compact_number, 'k' ) ) {
			$compact_number = floatval( str_replace( 'k', '', $compact_number ) ) * 1000;
		}
		if ( false !== strrpos( $compact_number, 'Mil' ) ) {
			$compact_number = floatval( str_replace( 'Mil', '', $compact_number ) ) * 1000000;
		}

		return $compact_number;
	}

	/**
	 * Generates the fields set for new account based on service and passes it to jQuery
	 */
	function generate_new_account_fields() {
		wp_verify_nonce( $_POST['accounts_tab_nonce'] , 'accounts_tab' );
		$service = ! empty( $_POST['bloom_service'] ) ? sanitize_text_field( $_POST['bloom_service'] ) : '';

		if ( 'empty' == $service ) {
			echo '<ul class="et_dashboard_new_account_fields"><li></li></ul>';
		} else {
			$form_fields = $this->generate_new_account_form( $service );

			printf(
				'<ul class="et_dashboard_new_account_fields">
					<li class="select et_dashboard_select_account">
						%3$s
						<button class="et_dashboard_icon authorize_service new_account_tab" data-service="%2$s">%1$s</button>
						<span class="spinner"></span>
					</li>
				</ul>',
				esc_html__( 'Authorize', 'bloom' ),
				esc_attr( $service ),
				$form_fields
			);
		}

		die();
	}

	/**
	 * Generates the fields set for account editing form based on service and account name and passes it to jQuery
	 */
	function generate_edit_account_page(){
		wp_verify_nonce( $_POST['accounts_tab_nonce'] , 'accounts_tab' );
		$edit_account = ! empty( $_POST['bloom_edit_account'] ) ? sanitize_text_field( $_POST['bloom_edit_account'] ) : '';
		$account_name = ! empty( $_POST['bloom_account_name'] ) ? sanitize_text_field( $_POST['bloom_account_name'] ) : '';
		$service = ! empty( $_POST['bloom_service'] ) ? sanitize_text_field( $_POST['bloom_service'] ) : '';

		echo '<div id="et_dashboard_edit_account_tab">';

		printf(
			'<div class="et_dashboard_row et_dashboard_new_account_row">
				<h1>%1$s</h1>
				<p>%2$s</p>
			</div>',
			( 'true' == $edit_account )
				? esc_html( $account_name )
				: esc_html__( 'New Account Setup', 'bloom' ),
			( 'true' == $edit_account )
				? esc_html__( 'You can view and re-authorize this account’s settings below', 'bloom' )
				: esc_html__( 'Setup a new email marketing service account below', 'bloom' )
		);

		if ( 'true' == $edit_account ) {
			$form_fields = $this->generate_new_account_form( $service, $account_name, false );

			printf(
				'<div class="et_dashboard_form et_dashboard_row">
					<h2>%1$s</h2>
					<div style="clear:both;"></div>
					<ul class="et_dashboard_new_account_fields et_dashboard_edit_account_fields">
						<li class="select et_dashboard_select_account">
							%2$s
							<button class="et_dashboard_icon authorize_service new_account_tab" data-service="%7$s" data-account_name="%4$s">%3$s</button>
							<span class="spinner"></span>
						</li>
					</ul>
					%5$s
					<button class="et_dashboard_icon save_account_tab" data-service="%7$s">%6$s</button>
				</div>',
				esc_html( $service ),
				$form_fields,
				esc_html__( 'Re-Authorize', 'bloom' ),
				esc_attr( $account_name ),
				$this->display_currrent_lists( $service, $account_name ),
				esc_html__( 'save & exit', 'bloom' ),
				esc_attr( $service )
			);
		} else {
			printf(
				'<div class="et_dashboard_form et_dashboard_row">
					<h2>%1$s</h2>
					<div style="clear:both;"></div>
					<ul>
						<li class="select et_dashboard_select_provider_new">
							<p>Select Email Provider</p>
							<select>
								<option value="empty" selected>%2$s</option>
								<option value="mailchimp">%3$s</option>
								<option value="aweber">%4$s</option>
								<option value="constant_contact">%5$s</option>
								<option value="campaign_monitor">%6$s</option>
								<option value="madmimi">%7$s</option>
								<option value="icontact">%8$s</option>
								<option value="getresponse">%9$s</option>
								<option value="sendinblue">%10$s</option>
								<option value="mailpoet">%11$s</option>
								<option value="ontraport">%13$s</option>
								<option value="feedblitz">%14$s</option>
								<option value="infusionsoft">%15$s</option>
							</select>
						</li>
					</ul>
					<ul class="et_dashboard_new_account_fields"><li></li></ul>
					<button class="et_dashboard_icon save_account_tab">%12$s</button>
				</div>',
				esc_html__( 'New account settings', 'bloom' ),
				esc_html__( 'Select One...', 'bloom' ),
				esc_html__( 'MailChimp', 'bloom' ),
				esc_html__( 'AWeber', 'bloom' ),
				esc_html__( 'Constant Contact', 'bloom' ),
				esc_html__( 'Campaign Monitor', 'bloom' ),
				esc_html__( 'Mad Mimi', 'bloom' ),
				esc_html__( 'iContact', 'bloom' ),
				esc_html__( 'GetResponse', 'bloom' ),
				esc_html__( 'Sendinblue', 'bloom' ),
				esc_html__( 'MailPoet', 'bloom' ),
				esc_html__( 'save & exit', 'bloom' ),
				esc_html__( 'Ontraport', 'bloom' ),
				esc_html__( 'Feedblitz', 'bloom' ),
				esc_html__( 'Infusionsoft', 'bloom' ) // #15
			);
		}

		echo '</div>';

		die();
	}

	/**
	 * Generates the list of Lists for specific account and passes it to jQuery
	 */
	function generate_current_lists() {
		wp_verify_nonce( $_POST['accounts_tab_nonce'] , 'accounts_tab' );
		$service = ! empty( $_POST['bloom_service'] ) ? sanitize_text_field( $_POST['bloom_service'] ) : '';
		$name = ! empty( $_POST['bloom_upd_name'] ) ? sanitize_text_field( $_POST['bloom_upd_name'] ) : '';

		echo $this->display_currrent_lists( $service, $name );

		die();
	}

	/**
	 * Generates the list of Lists for specific account
	 * @return string
	 */
	function display_currrent_lists( $service = '', $name = '' ) {
		$options_array = ET_Bloom::get_bloom_options();
		$all_lists = array();
		$name = str_replace( array( '"', "'" ), '', stripslashes( $name ) );

		if ( ! empty( $options_array['accounts'][$service][$name]['lists'] ) ) {
			foreach ( $options_array['accounts'][$service][$name]['lists'] as $id => $list_details ) {
				$all_lists[] = $list_details['name'];
			}
		}

		$output = sprintf(
			'<div class="et_dashboard_row et_dashboard_new_account_lists">
				<h2>%1$s</h2>
				<div style="clear:both;"></div>
				<p>%2$s</p>
			</div>',
			esc_html__( 'Account Lists', 'bloom' ),
			! empty( $all_lists )
				? implode( ', ', array_map( 'esc_html', $all_lists ) )
				: __( 'No lists available for this account', 'bloom' )
		);

		return $output;
	}

	/**
	 * Saves the account data during editing/creating account
	 */
	function save_account_tab() {
		wp_verify_nonce( $_POST['accounts_tab_nonce'] , 'accounts_tab' );
		$service = ! empty( $_POST['bloom_service'] ) ? sanitize_text_field( $_POST['bloom_service'] ) : '';
		$name = ! empty( $_POST['bloom_account_name'] ) ? sanitize_text_field( $_POST['bloom_account_name'] ) : '';

		$options_array = ET_Bloom::get_bloom_options();

		if ( ! isset( $options_array['accounts'][$service][$name] ) ) {
			$this->update_account( $service, $name, array(
				'lists' => array(),
				'is_authorized' => 'false',
			) );
		}

		die();
	}

	/**
	 * Generates and displays the table with all accounts for Accounts tab
	 */
	function display_accounts_table(){
		$options_array = ET_Bloom::get_bloom_options();

		echo '<div class="et_dashboard_accounts_content">';
		if( ! empty( $options_array['accounts'] ) ) {
			foreach ( $options_array['accounts'] as $service => $details ) {
				if ( ! empty( $details ) ) {
					$optins_count = 0;
					$output = '';
					printf(
						'<div class="et_dashboard_row et_dashboard_accounts_title">
							<span class="et_dashboard_service_logo_%1$s"></span>
						</div>',
						esc_attr( $service )
					);
					foreach ( $details as $account_name => $value ) {
						if ( 0 === $optins_count ) {
							$output .= sprintf(
								'<div class="et_dashboard_optins_list">
									<ul>
										<li>
											<div class="et_dashboard_table_acc_name et_dashboard_table_column et_dashboard_table_header">%1$s</div>
											<div class="et_dashboard_table_subscribers et_dashboard_table_column et_dashboard_table_header">%2$s</div>
											<div class="et_dashboard_table_growth_rate et_dashboard_table_column et_dashboard_table_header">%3$s</div>
											<div class="et_dashboard_table_actions et_dashboard_table_column"></div>
											<div style="clear: both;"></div>
										</li>',
								esc_html__( 'Account name', 'bloom' ),
								esc_html__( 'Subscribers', 'bloom' ),
								esc_html__( 'Growth rate', 'bloom' )
							);
						}

						$output .= sprintf(
							'<li class="et_dashboard_optins_item" data-account_name="%1$s" data-service="%2$s">
								<div class="et_dashboard_table_acc_name et_dashboard_table_column">%3$s</div>
								<div class="et_dashboard_table_subscribers et_dashboard_table_column"></div>
								<div class="et_dashboard_table_growth_rate et_dashboard_table_column"></div>',
							esc_attr( $account_name ),
							esc_attr( $service ),
							esc_html( $account_name )
						);

						$output .= sprintf(	'
								<div class="et_dashboard_table_actions et_dashboard_table_column">
									<span class="et_dashboard_icon_edit_account et_optin_button et_dashboard_icon" title="%8$s" data-account_name="%1$s" data-service="%2$s"></span>
									<span class="et_dashboard_icon_delete et_optin_button et_dashboard_icon" title="%4$s"><span class="et_dashboard_confirmation">%5$s</span></span>
									%3$s
									<span class="et_dashboard_icon_indicator_%7$s et_optin_button et_dashboard_icon" title="%6$s"></span>
								</div>
								<div style="clear: both;"></div>
							</li>',
							esc_attr( $account_name ),
							esc_attr( $service ),
							( isset( $value['is_authorized'] ) && 'true' == $value['is_authorized'] )
								? sprintf( '
									<span class="et_dashboard_icon_update_lists et_optin_button et_dashboard_icon" title="%1$s" data-account_name="%2$s" data-service="%3$s">
										<span class="spinner"></span>
									</span>',
									esc_attr__( 'Update Lists', 'bloom' ),
									esc_attr( $account_name ),
									esc_attr( $service )
								)
								: '',
							__( 'Remove account', 'bloom' ),
							sprintf(
								'%1$s<span class="et_dashboard_confirm_delete" data-optin_id="%4$s" data-remove_account="true">%2$s</span><span class="et_dashboard_cancel_delete">%3$s</span>',
								esc_html__( 'Remove this account from list?', 'bloom' ),
								esc_html__( 'Yes', 'bloom' ),
								esc_html__( 'No', 'bloom' ),
								esc_attr( $account_name )
							), //#5
							( isset( $value['is_authorized'] ) && 'true' == $value['is_authorized'] )
								? esc_html__( 'Authorized', 'bloom' )
								: esc_html__( 'Not Authorized', 'bloom' ),
							( isset( $value['is_authorized'] ) && 'true' == $value['is_authorized'] )
								? 'check'
								: 'dot',
							esc_html__( 'Edit account', 'bloom' )
						);

						if ( isset( $value['lists'] ) && ! empty( $value['lists'] ) ) {
							foreach ( $value['lists'] as $id => $list ) {
								$output .= sprintf( '
									<li class="et_dashboard_lists_row">
										<div class="et_dashboard_table_acc_name et_dashboard_table_column">%1$s</div>
										<div class="et_dashboard_table_subscribers et_dashboard_table_column">%2$s</div>
										<div class="et_dashboard_table_growth_rate et_dashboard_table_column">%3$s / %4$s</div>
										<div class="et_dashboard_table_actions et_dashboard_table_column"></div>
									</li>',
									esc_html( $list['name'] ),
									'ontraport' == $service ? esc_html__( 'n/a', 'bloom' ) : esc_html( $list['subscribers_count'] ),
									esc_html( $list['growth_week'] ),
									esc_html__( 'week', 'bloom' )
								);
							}
						} else {
							$output .= sprintf(
								'<li class="et_dashboard_lists_row">
									<div class="et_dashboard_table_acc_name et_dashboard_table_column">%1$s</div>
									<div class="et_dashboard_table_subscribers et_dashboard_table_column"></div>
									<div class="et_dashboard_table_growth_rate et_dashboard_table_column"></div>
									<div class="et_dashboard_table_actions et_dashboard_table_column"></div>
								</li>',
								esc_html__( 'No lists available', 'bloom' )
							);
						}

						$optins_count++;
					}

					echo $output;
					echo '
						</ul>
					</div>';
				}
			}
		}
		echo '</div>';
	}

	/**
	 * Displays tables of Active and Inactive optins on homepage
	 */
	function display_home_tab_tables() {

		$options_array = ET_Bloom::get_bloom_options();

		echo '<div class="et_dashboard_home_tab_content">';

		$this->generate_optins_list( $options_array, 'active' );

		$this->generate_optins_list( $options_array, 'inactive' );

		echo '</div>';

	}

	/**
	 * Generates tables of Active and Inactive optins on homepage and passes it to jQuery
	 */
	function home_tab_tables() {
		wp_verify_nonce( $_POST['home_tab_nonce'] , 'home_tab' );
		$this->display_home_tab_tables();
		die();
	}

	/**
	 * Generates accounts tables and passes it to jQuery
	 */
	function reset_accounts_table() {
		wp_verify_nonce( $_POST['accounts_tab_nonce'] , 'accounts_tab' );
		$this->display_accounts_table();
		die();
	}

	/**
	 * Generates optins table for homepage. Can generate table for active or inactive optins
	 */
	function generate_optins_list( $options_array = array(), $status = 'active' ) {
		$optins_count = 0;
		$output = '';
		$total_impressions = 0;
		$total_conversions = 0;

		foreach ( $options_array as $optin_id => $value ) {
			if ( isset( $value['optin_status'] ) && $status === $value['optin_status'] && empty( $value['child_of'] ) ) {
				$child_row = '';

				if ( 0 === $optins_count ) {

					$output .= sprintf(
						'<div class="et_dashboard_optins_list">
							<ul>
								<li>
									<div class="et_dashboard_table_name et_dashboard_table_column">%1$s</div>
									<div class="et_dashboard_table_impressions et_dashboard_table_column">%2$s</div>
									<div class="et_dashboard_table_conversions et_dashboard_table_column">%3$s</div>
									<div class="et_dashboard_table_rate et_dashboard_table_column">%4$s</div>
									<div class="et_dashboard_table_actions et_dashboard_table_column"></div>
									<div style="clear: both;"></div>
								</li>',
						esc_html__( 'Optin Name', 'bloom' ),
						esc_html__( 'Impressions', 'bloom' ),
						esc_html__( 'Conversions', 'bloom' ),
						esc_html__( 'Conversion Rate', 'bloom' )
					);
				}

				if ( ! empty( $value['child_optins'] ) && 'active' == $status ) {
					$optins_data = array();

					foreach( $value['child_optins'] as $id ) {
						$total_impressions += $impressions = $this->stats_count( $id, 'imp' );
						$total_conversions += $conversions = $this->stats_count( $id, 'con' );

						$optins_data[] = array(
							'name'        => $options_array[$id]['optin_name'],
							'id'          => $id,
							'rate'        => $this->conversion_rate( $id, $conversions, $impressions ),
							'impressions' => $impressions,
							'conversions' => $conversions,
						);
					}

					$child_optins_data = $this->sort_array( $optins_data, 'rate', SORT_DESC );

					$child_row = '<ul class="et_dashboard_child_row">';

					foreach( $child_optins_data as $child_details ) {
						$child_row .= sprintf(
							'<li class="et_dashboard_optins_item et_dashboard_child_item" data-optin_id="%1$s">
								<div class="et_dashboard_table_name et_dashboard_table_column">%2$s</div>
								<div class="et_dashboard_table_impressions et_dashboard_table_column">%3$s</div>
								<div class="et_dashboard_table_conversions et_dashboard_table_column">%4$s</div>
								<div class="et_dashboard_table_rate et_dashboard_table_column">%5$s</div>
								<div class="et_dashboard_table_actions et_dashboard_table_column">
									<span class="et_dashboard_icon_edit et_optin_button et_dashboard_icon" title="%8$s" data-parent_id="%9$s"><span class="spinner"></span></span>
									<span class="et_dashboard_icon_delete et_optin_button et_dashboard_icon" title="%6$s"><span class="et_dashboard_confirmation">%7$s</span></span>
								</div>
								<div style="clear: both;"></div>
							</li>',
							esc_attr( $child_details['id'] ),
							esc_html( $child_details['name'] ),
							esc_html( $child_details['impressions'] ),
							esc_html( $child_details['conversions'] ),
							esc_html( $child_details['rate'] . '%' ), // #5
							esc_attr__( 'Delete Optin', 'bloom' ),
							sprintf(
								'%1$s<span class="et_dashboard_confirm_delete" data-optin_id="%4$s" data-parent_id="%5$s">%2$s</span>
								<span class="et_dashboard_cancel_delete">%3$s</span>',
								esc_html__( 'Delete this optin?', 'bloom' ),
								esc_html__( 'Yes', 'bloom' ),
								esc_html__( 'No', 'bloom' ),
								esc_attr( $child_details['id'] ),
								esc_attr( $optin_id )
							),
							esc_attr__( 'Edit Optin', 'bloom' ),
							esc_attr( $optin_id ) // #9
						);
					}

					$child_row .= sprintf(
						'<li class="et_dashboard_add_variant et_dashboard_optins_item">
							<a href="#" class="et_dashboard_add_var_button">%1$s</a>
							<div class="child_buttons_right">
								<a href="#" class="et_dashboard_start_test%5$s" data-parent_id="%4$s">%2$s</a>
								<a href="#" class="et_dashboard_end_test" data-parent_id="%4$s">%3$s</a>
							</div>
						</li>',
						esc_html__( 'Add variant', 'bloom' ),
						( isset( $value['test_status'] ) && 'active' == $value['test_status'] ) ? esc_html__( 'Pause test', 'bloom' ) : esc_html__( 'Start test', 'bloom' ),
						esc_html__( 'End & pick winner', 'bloom' ),
						esc_attr( $optin_id ),
						( isset( $value['test_status'] ) && 'active' == $value['test_status'] ) ? ' et_dashboard_pause_test' : ''
					);

					$child_row .= '</ul>';
				}

				$total_impressions += $impressions = $this->stats_count( $optin_id, 'imp' );
				$total_conversions += $conversions = $this->stats_count( $optin_id, 'con' );

				$output .= sprintf(
					'<li class="et_dashboard_optins_item et_dashboard_parent_item" data-optin_id="%1$s">
						<div class="et_dashboard_table_name et_dashboard_table_column et_dashboard_icon et_dashboard_type_%13$s">%2$s</div>
						<div class="et_dashboard_table_impressions et_dashboard_table_column">%3$s</div>
						<div class="et_dashboard_table_conversions et_dashboard_table_column">%4$s</div>
						<div class="et_dashboard_table_rate et_dashboard_table_column">%5$s</div>
						<div class="et_dashboard_table_actions et_dashboard_table_column">
							<span class="et_dashboard_icon_edit et_optin_button et_dashboard_icon" title="%10$s"><span class="spinner"></span></span>
							<span class="et_dashboard_icon_delete et_optin_button et_dashboard_icon" title="%9$s"><span class="et_dashboard_confirmation">%12$s</span></span>
							<span class="et_dashboard_icon_duplicate duplicate_id_%1$s et_optin_button et_dashboard_icon" title="%8$s"><span class="spinner"></span></span>
							<span class="et_dashboard_icon_%11$s et_dashboard_toggle_status et_optin_button et_dashboard_icon%16$s" data-toggle_to="%11$s" data-optin_id="%1$s" title="%7$s"><span class="spinner"></span></span>
							%14$s
							%6$s
						</div>
						<div style="clear: both;"></div>
						%15$s
					</li>',
					esc_attr( $optin_id ),
					esc_html( $value['optin_name'] ),
					esc_html( $impressions ),
					esc_html( $conversions ),
					esc_html( $this->conversion_rate( $optin_id, $conversions, $impressions ) . '%' ), // #5
					( 'locked' === $value['optin_type'] || 'inline' === $value['optin_type'] )
						? sprintf(
							'<span class="et_dashboard_icon_shortcode et_optin_button et_dashboard_icon" title="%1$s" data-type="%2$s"></span>',
							esc_attr__( 'Generate shortcode', 'bloom' ),
							esc_attr( $value['optin_type'] )
						)
						: '',
					'active' === $status ? esc_html__( 'Make Inactive', 'bloom' ) : esc_html__( 'Make Active', 'bloom' ),
					esc_attr__( 'Duplicate', 'bloom' ),
					esc_attr__( 'Delete Optin', 'bloom' ),
					esc_attr__( 'Edit Optin', 'bloom' ), //#10
					'active' === $status ? 'inactive' : 'active',
					sprintf(
						'%1$s<span class="et_dashboard_confirm_delete" data-optin_id="%4$s">%2$s</span>
						<span class="et_dashboard_cancel_delete">%3$s</span>',
						esc_html__( 'Delete this optin?', 'bloom' ),
						esc_html__( 'Yes', 'bloom' ),
						esc_html__( 'No', 'bloom' ),
						esc_attr( $optin_id )
					),
					esc_attr( $value['optin_type'] ),
					( 'active' === $status )
						? sprintf(
							'<span class="et_dashboard_icon_abtest et_optin_button et_dashboard_icon%2$s" title="%1$s"></span>',
							esc_attr__( 'A/B Testing', 'bloom' ),
							( '' != $child_row ) ? ' active_child_optins' : ''
						)
						: '',
					$child_row, //#15
					( 'empty' == $value['email_provider'] || ( 'custom_html' !== $value['email_provider'] && 'empty' == $value['email_list'] ) )
						? ' et_bloom_no_account'
						: '' //#16
				);
				$optins_count++;
			}
		}

		if ( 'active' === $status && 0 < $optins_count ) {
			$output .= sprintf(
				'<li class="et_dashboard_optins_item_bottom_row">
					<div class="et_dashboard_table_name et_dashboard_table_column"></div>
					<div class="et_dashboard_table_impressions et_dashboard_table_column">%1$s</div>
					<div class="et_dashboard_table_conversions et_dashboard_table_column">%2$s</div>
					<div class="et_dashboard_table_rate et_dashboard_table_column">%3$s</div>
					<div class="et_dashboard_table_actions et_dashboard_table_column"></div>
				</li>',
				esc_html( $this->get_compact_number( $total_impressions ) ),
				esc_html( $this->get_compact_number( $total_conversions ) ),
				( 0 !== $total_impressions )
					? esc_html( round( ( $total_conversions * 100 ) / $total_impressions, 1 ) . '%' )
					: '0%'
			);
		}

		if ( 0 < $optins_count ) {
			if ( 'inactive' === $status ) {
				printf( '
					<div class="et_dashboard_row">
						<h1>%1$s</h1>
					</div>',
					esc_html__( 'Inactive Optins', 'bloom' )
				);
			}

			echo $output . '</ul></div>';
		}
	}

	function add_admin_body_class( $classes ) {
		return "$classes et_bloom";
	}

	function register_scripts( $hook ) {

		wp_enqueue_style( 'et-bloom-menu-icon', ET_BLOOM_PLUGIN_URI . '/css/bloom-menu.css', array(), $this->plugin_version );

		if ( "toplevel_page_{$this->_options_pagename}" !== $hook ) {
			return;
		}

		add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ) );
		wp_enqueue_script( 'et_bloom-uniform-js', ET_BLOOM_PLUGIN_URI . '/js/jquery.uniform.min.js', array( 'jquery' ), $this->plugin_version, true );
		wp_enqueue_style( 'et-open-sans-700', "{$this->protocol}://fonts.googleapis.com/css?family=Open+Sans:700", array(), $this->plugin_version );
		wp_enqueue_style( 'et-bloom-css', ET_BLOOM_PLUGIN_URI . '/css/admin.css', array(), $this->plugin_version );
		wp_enqueue_style( 'et_bloom-preview-css', ET_BLOOM_PLUGIN_URI . '/css/style.css', array(), $this->plugin_version );
		wp_enqueue_script( 'et-bloom-js', ET_BLOOM_PLUGIN_URI . '/js/admin.js', array( 'jquery' ), $this->plugin_version, true );
		wp_localize_script( 'et-bloom-js', 'bloom_settings', array(
			'bloom_nonce'          => wp_create_nonce( 'bloom_nonce' ),
			'ajaxurl'              => admin_url( 'admin-ajax.php', $this->protocol ),
			'reset_options'        => wp_create_nonce( 'reset_options' ),
			'remove_option'        => wp_create_nonce( 'remove_option' ),
			'duplicate_option'     => wp_create_nonce( 'duplicate_option' ),
			'home_tab'             => wp_create_nonce( 'home_tab' ),
			'toggle_status'        => wp_create_nonce( 'toggle_status' ),
			'optin_type_title'     => __( 'select optin type to begin', 'bloom' ),
			'shortcode_text'       => __( 'Shortcode for this optin:', 'bloom' ),
			'get_lists'            => wp_create_nonce( 'get_lists' ),
			'add_account'          => wp_create_nonce( 'add_account' ),
			'accounts_tab'         => wp_create_nonce( 'accounts_tab' ),
			'retrieve_lists'       => wp_create_nonce( 'retrieve_lists' ),
			'ab_test'              => wp_create_nonce( 'ab_test' ),
			'bloom_stats'          => wp_create_nonce( 'bloom_stats_nonce' ),
			'redirect_url'         => rawurlencode( admin_url( 'admin.php?page=' . $this->_options_pagename, $this->protocol ) ),
			'authorize_text'       => __( 'Authorize', 'bloom' ),
			'reauthorize_text'     => __( 'Re-Authorize', 'bloom' ),
			'no_account_name_text' => __( 'Account name is not defined', 'bloom' ),
			'ab_test_pause_text'   => __( 'Pause test', 'bloom' ),
			'ab_test_start_text'   => __( 'Start test', 'bloom' ),
			'bloom_premade_nonce'  => wp_create_nonce( 'bloom_premade' ),
			'preview_nonce'        => wp_create_nonce( 'bloom_preview' ),
			'no_account_text'      => __( 'You Have Not Added An Email List. Before your opt-in can be activated, you must first add an account and select an email list. You can save and exit, but the opt-in will remain inactive until an account is added.', 'bloom' ),
			'add_account_button'   => __( 'Add An Account', 'bloom' ),
			'save_inactive_button' => __( 'Save As Inactive', 'bloom' ),
			'cannot_activate_text' => __( 'You Have Not Added An Email List. Before your opt-in can be activated, you must first add an account and select an email list.', 'bloom' ),
			'save_settings'        => wp_create_nonce( 'save_settings' ),
		) );
	}

	/**
	 * Generates unique ID for new set of options
	 * @return string or int
	 */
	function generate_optin_id( $full_id = true ) {

		$options_array = ET_Bloom::get_bloom_options();
		$form_id = (int) 0;

		if( ! empty( $options_array ) ) {
			foreach ( $options_array as $key => $value) {
				$keys_array[] = (int) str_replace( 'optin_', '', $key );
			}

			$form_id = max( $keys_array ) + 1;
		}

		$result = true === $full_id ? (string) 'optin_' . $form_id : (int) $form_id;

		return $result;

	}

	/**
	 * Generates options page for specific optin ID
	 * @return string
	 */
	function reset_options_page() {
		wp_verify_nonce( $_POST['reset_options_nonce'] , 'reset_options' );

		$optin_id = ! empty( $_POST['reset_optin_id'] )
			? sanitize_text_field( $_POST['reset_optin_id'] )
			: $this->generate_optin_id();
		$additional_options = '';

		ET_Bloom::generate_options_page( $optin_id );

		die();
	}

	/**
	 * Handles "Duplicate" button action
	 * @return string
	 */
	function duplicate_optin() {
		wp_verify_nonce( $_POST['duplicate_option_nonce'] , 'duplicate_option' );
		$duplicate_optin_id = ! empty( $_POST['duplicate_optin_id'] ) ? sanitize_text_field( $_POST['duplicate_optin_id'] ) : '';
		$duplicate_optin_type = ! empty( $_POST['duplicate_optin_type'] ) ? sanitize_text_field( $_POST['duplicate_optin_type'] ) : '';

		$this->perform_option_duplicate( $duplicate_optin_id, $duplicate_optin_type, false );

		die();
	}

	/**
	 * Handles "Add Variant" button action
	 * @return string
	 */
	function add_variant() {
		wp_verify_nonce( $_POST['duplicate_option_nonce'] , 'duplicate_option' );
		$duplicate_optin_id = ! empty( $_POST['duplicate_optin_id'] ) ? sanitize_text_field( $_POST['duplicate_optin_id'] ) : '';

		$variant_id = $this->perform_option_duplicate( $duplicate_optin_id, '', true );

		die( $variant_id );
	}

	/**
	 * Toggles testing status
	 * @return void
	 */
	function ab_test_actions() {
		wp_verify_nonce( $_POST['ab_test_nonce'] , 'ab_test' );
		$parent_id = ! empty( $_POST['parent_id'] ) ? sanitize_text_field( $_POST['parent_id'] ) : '';
		$action = ! empty( $_POST['test_action'] ) ? sanitize_text_field( $_POST['test_action'] ) : '';
		$options_array = ET_Bloom::get_bloom_options();
		$update_test_status[$parent_id] = $options_array[$parent_id];

		switch( $action ) {
			case 'start' :
				$update_test_status[$parent_id]['test_status'] = 'active';
				$result = 'ok';
			break;
			case 'pause' :
				$update_test_status[$parent_id]['test_status'] = 'inactive';
				$result = 'ok';
			break;

			case 'end' :
				$result = $this->generate_end_test_modal( $parent_id );
			break;
		}

		ET_Bloom::update_option( $update_test_status );

		die( $result );
	}

	/**
	 * Generates modal window for the pick winner option
	 * @return string
	 */
	function generate_end_test_modal( $parent_id ) {
		$options_array = ET_Bloom::get_bloom_options();
		$test_optins = $options_array[$parent_id]['child_optins'];
		$test_optins[] = $parent_id;
		$output = '';

		if ( ! empty( $test_optins ) ) {
			foreach( $test_optins as $id ) {
				$optins_data[] = array(
					'name' => $options_array[$id]['optin_name'],
					'id' => $id,
					'rate' => $this->conversion_rate( $id ),
				);
			}

			$optins_data = $this->sort_array( $optins_data, 'rate', SORT_DESC );

			$table = sprintf(
				'<div class="end_test_table">
					<ul data-optins_set="%3$s" data-parent_id="%4$s">
						<li class="et_test_table_header">
							<div class="et_dashboard_table_column">%1$s</div>
							<div class="et_dashboard_table_column et_test_conversion">%2$s</div>
						</li>',
				esc_html__( 'Optin name', 'bloom' ),
				esc_html__( 'Conversion rate', 'bloom' ),
				esc_attr( implode( '#', $test_optins ) ),
				esc_attr( $parent_id )
			);

			foreach( $optins_data as $single ) {
				$table .= sprintf(
					'<li class="et_dashboard_content_row" data-optin_id="%1$s">
						<div class="et_dashboard_table_column">%2$s</div>
						<div class="et_dashboard_table_column et_test_conversion">%3$s</div>
					</li>',
					esc_attr( $single['id'] ),
					esc_html( $single['name'] ),
					esc_html( $single['rate'] . '%' )
				);
			}

			$table .= '</ul></div>';

			$output = sprintf(
				'<div class="et_dashboard_networks_modal et_dashboard_end_test">
					<div class="et_dashboard_inner_container">
						<div class="et_dashboard_modal_header">
							<span class="modal_title">%1$s</span>
							<span class="et_dashboard_close"></span>
						</div>
						<div class="dashboard_icons_container">
							%3$s
						</div>
						<div class="et_dashboard_modal_footer">
							<a href="#" class="et_dashboard_ok et_dashboard_warning_button">%2$s</a>
						</div>
					</div>
				</div>',
				esc_html__( 'Choose an optin', 'bloom' ),
				esc_html__( 'cancel', 'bloom' ),
				$table
			);
		}

		return $output;
	}

	/**
	 * Handles "Pick winner" function. Replaces the content of parent optin with the content of "winning" optin.
	 * Updates options and stats accordingly.
	 * @return void
	 */
	function pick_winner_optin() {
		wp_verify_nonce( $_POST['remove_option_nonce'] , 'remove_option' );

		$winner_id = ! empty( $_POST['winner_id'] ) ? sanitize_text_field( $_POST['winner_id'] ) : '';
		$optins_set = ! empty( $_POST['optins_set'] ) ? sanitize_text_field( $_POST['optins_set'] ) : '';
		$parent_id = ! empty( $_POST['parent_id'] ) ? sanitize_text_field( $_POST['parent_id'] ) : '';

		$options_array = ET_Bloom::get_bloom_options();
		$temp_array = $options_array[$winner_id];

		$temp_array['test_status'] = 'inactive';
		$temp_array['child_optins'] = array();
		$temp_array['child_of'] = '';
		$temp_array['next_optin'] = '-1';
		$temp_array['display_on'] = $options_array[$parent_id]['display_on'];
		$temp_array['post_types'] = $options_array[$parent_id]['post_types'];
		$temp_array['post_categories'] = $options_array[$parent_id]['post_categories'];
		$temp_array['pages_exclude'] = $options_array[$parent_id]['pages_exclude'];
		$temp_array['pages_include'] = $options_array[$parent_id]['pages_include'];
		$temp_array['posts_exclude'] = $options_array[$parent_id]['posts_exclude'];
		$temp_array['posts_include'] = $options_array[$parent_id]['posts_include'];
		$temp_array['email_provider'] = $options_array[$parent_id]['email_provider'];
		$temp_array['account_name'] = $options_array[$parent_id]['account_name'];
		$temp_array['email_list'] = $options_array[$parent_id]['email_list'];
		$temp_array['custom_html'] = $options_array[$parent_id]['custom_html'];

		$updated_array[$parent_id] = $temp_array;

		if ( $parent_id != $winner_id ){
			$this->update_stats_for_winner( $parent_id, $winner_id );
		}

		$optins_set = explode( '#', $optins_set );
		foreach ( $optins_set as $optin_id ) {
			if ( $parent_id != $optin_id ) {
				$this->perform_optin_removal( $optin_id, false, '', '', false );
			}
		}

		ET_Bloom::update_option( $updated_array );
	}

	/**
	 * Updates stats table when A/B testing finished winner optin selected
	 * @return void
	 */
	function update_stats_for_winner( $optin_id, $winner_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'et_bloom_stats';

		$this->remove_optin_from_db( $optin_id );

		$sql = "UPDATE $table_name SET optin_id = %s WHERE optin_id = %s AND removed_flag <> 1";

		$sql_args = array(
			$optin_id,
			$winner_id
		);

		$wpdb->query( $wpdb->prepare( $sql, $sql_args ) );
	}

	/**
	 * Performs duplicating of optin. Can duplicate parent optin as well as child optin based on $is_child parameter
	 * @return string
	 */
	function perform_option_duplicate( $duplicate_optin_id, $duplicate_optin_type = '', $is_child = false ) {
		$new_optin_id = $this->generate_optin_id();
		$suffix = true == $is_child ? '_child' : '_copy';

		if ( '' !== $duplicate_optin_id ) {
			$options_array = ET_Bloom::get_bloom_options();
			$new_option[$new_optin_id] = $options_array[$duplicate_optin_id];
			$new_option[$new_optin_id]['optin_name'] = $new_option[$new_optin_id]['optin_name'] . $suffix;
			$new_option[$new_optin_id]['optin_status'] = 'active';

			if ( true == $is_child ) {
				$new_option[$new_optin_id]['child_of'] = $duplicate_optin_id;
				$updated_optin[$duplicate_optin_id] = $options_array[$duplicate_optin_id];
				unset( $new_option[$new_optin_id]['child_optins'] );
				$updated_optin[$duplicate_optin_id]['child_optins'] = isset( $options_array[$duplicate_optin_id]['child_optins'] ) ? array_merge( $options_array[$duplicate_optin_id]['child_optins'], array( $new_optin_id ) ) : array( $new_optin_id );
				ET_Bloom::update_option( $updated_optin );
			} else {
				$new_option[$new_optin_id]['optin_type'] = $duplicate_optin_type;
				unset( $new_option[$new_optin_id]['child_optins'] );
			}

			if ( 'breakout_edge' === $new_option[$new_optin_id]['edge_style'] && 'pop_up' !== $duplicate_optin_type ) {
				$new_option[$new_optin_id]['edge_style'] = 'basic_edge';
			}

			if ( ! ( 'flyin' === $duplicate_optin_type || 'pop_up' === $duplicate_optin_type ) ) {
				unset( $new_option[$new_optin_id]['display_on'] );
			}

			ET_Bloom::update_option( $new_option );

			return $new_optin_id;
		}
	}

	/**
	 * Handles optin/account removal function called via jQuery
	 */
	function remove_optin() {
		wp_verify_nonce( $_POST['remove_option_nonce'] , 'remove_option' );

		$optin_id = ! empty( $_POST['remove_optin_id'] ) ? sanitize_text_field( $_POST['remove_optin_id'] ) : '';
		$is_account = ! empty( $_POST['is_account'] ) ? sanitize_text_field( $_POST['is_account'] ) : '';
		$service = ! empty( $_POST['service'] ) ? sanitize_text_field( $_POST['service'] ) : '';
		$parent_id = ! empty( $_POST['parent_id'] ) ? sanitize_text_field( $_POST['parent_id'] ) : '';

		$this->perform_optin_removal( $optin_id, $is_account, $service, $parent_id );

		die();
	}

	/**
	 * Performs removal of optin or account. Can remove parent optin, child optin or account
	 * @return void
	 */
	function perform_optin_removal( $optin_id, $is_account = false, $service = '', $parent_id = '', $remove_child = true ) {
		$options_array = ET_Bloom::get_bloom_options();

		if ( '' !== $optin_id ) {
			if ( 'true' == $is_account ) {
				if ( '' !== $service ) {
					if ( isset( $options_array['accounts'][$service][$optin_id] ) ){
						unset( $options_array['accounts'][$service][$optin_id] );

						foreach ( $options_array as $id => $details ) {
							if ( 'accounts' !== $id ) {
								if ( $optin_id == $details['account_name'] ) {
									$options_array[$id]['email_provider'] = 'empty';
									$options_array[$id]['account_name'] = 'empty';
									$options_array[$id]['email_list'] = 'empty';
									$options_array[$id]['optin_status'] = 'inactive';
								}
							}
						}

						ET_Bloom::update_option( $options_array );
					}
				}
			} else {
				if ( '' != $parent_id ) {
					$updated_array[$parent_id] = $options_array[$parent_id];
					$new_child_optins = array();

					foreach( $updated_array[$parent_id]['child_optins'] as $child ) {
						if ( $child != $optin_id ) {
							$new_child_optins[] = $child;
						}
					}

					$updated_array[$parent_id]['child_optins'] = $new_child_optins;

					// change test status to 'inactive' if there is no child options after removal.
					if ( empty( $new_child_optins ) ) {
						$updated_array[$parent_id]['test_status'] = 'inactive';
					}

					ET_Bloom::update_option( $updated_array );
				} else {
					if ( ! empty( $options_array[$optin_id]['child_optins'] ) && true == $remove_child ) {
						foreach( $options_array[$optin_id]['child_optins'] as $single_optin ) {
							ET_Bloom::remove_option( $single_optin );
							$this->remove_optin_from_db( $single_optin );
						}
					}
				}

				ET_Bloom::remove_option( $optin_id );
				$this->remove_optin_from_db( $optin_id );
			}
		}
	}

	/**
	 * Remove the optin data from stats tabel.
	 */
	function remove_optin_from_db( $optin_id ) {
		if ( '' !== $optin_id ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'et_bloom_stats';

			// construct sql query to mark removed options as removed in stats DB
			$sql = "DELETE FROM $table_name WHERE optin_id = %s";

			$sql_args = array(
				$optin_id,
			);

			$wpdb->query( $wpdb->prepare( $sql, $sql_args ) );
		}
	}

	/**
	 * Toggles status of optin from active to inactive and vice versa
	 * @return void
	 */
	function toggle_optin_status() {
		wp_verify_nonce( $_POST['toggle_status_nonce'] , 'toggle_status' );
		$optin_id = ! empty( $_POST['status_optin_id'] ) ? sanitize_text_field( $_POST['status_optin_id'] ) : '';
		$toggle_to = ! empty( $_POST['status_new'] ) ? sanitize_text_field( $_POST['status_new'] ) : '';

		if ( '' !== $optin_id ) {
			$options_array = ET_Bloom::get_bloom_options();
			$update_option[$optin_id] = $options_array[$optin_id];
			$update_option[$optin_id]['optin_status'] = 'active' === $toggle_to ? 'active' : 'inactive';

			ET_Bloom::update_option( $update_option );
		}

		die();
	}

	/**
	 * Adds new account into DB.
	 * @return void
	 */
	function add_new_account() {
		wp_verify_nonce( $_POST['add_account_nonce'] , 'add_account' );
		$service = ! empty( $_POST['bloom_service'] ) ? sanitize_text_field( $_POST['bloom_service'] ) : '';
		$name = ! empty( $_POST['bloom_account_name'] ) ? sanitize_text_field( $_POST['bloom_account_name'] ) : '';
		$new_account = array();

		if ( '' !== $service && '' !== $name ) {
			$options_array = ET_Bloom::get_bloom_options();
			$new_account['accounts'] = isset( $options_array['accounts'] ) ? $options_array['accounts'] : array();
			$new_account['accounts'][$service][$name] = array();
			ET_Bloom::update_option( $new_account );
		}
	}

	/**
	 * Updates the account details in DB.
	 * @return void
	 */
	function update_account( $service, $name, $data_array = array() ) {
		if ( '' !== $service && '' !== $name ) {
			$name = str_replace( array( '"', "'" ), '', stripslashes( $name ) );
			$options_array = ET_Bloom::get_bloom_options();
			$new_account['accounts'] = isset( $options_array['accounts'] ) ? $options_array['accounts'] : array();
			$new_account['accounts'][$service][$name] = isset( $new_account['accounts'][$service][$name] )
				? array_merge( $new_account['accounts'][$service][$name], $data_array )
				: $data_array;

			ET_Bloom::update_option( $new_account );
		}
	}

	/**
	 * Used to sync the accounts data. Executed by wp_cron daily.
	 * In case of errors adds record to WP log
	 */
	function perform_auto_refresh() {
		$options_array = ET_Bloom::get_bloom_options();
		if ( isset( $options_array['accounts'] ) ) {
			foreach ( $options_array['accounts'] as $service => $account ) {
				foreach ( $account as $name => $details ) {
					if ( 'true' == $details['is_authorized'] ) {
						switch ( $service ) {
							case 'mailchimp' :
								$error_message = $this->get_mailchimp_lists( $details['api_key'], $name );
							break;

							case 'constant_contact' :
								$error_message = $this->get_constant_contact_lists( $details['api_key'], $details['token'], $name );
							break;

							case 'madmimi' :
								$error_message = $this->get_madmimi_lists( $details['username'], $details['api_key'], $name );
							break;

							case 'icontact' :
								$error_message = $this->get_icontact_lists( $details['client_id'], $details['username'], $details['password'], $name );
							break;

							case 'getresponse' :
								$error_message = $this->get_getresponse_lists( $details['api_key'], $name );
							break;

							case 'sendinblue' :
								$error_message = $this->get_sendinblue_lists( $details['api_key'], $name );
							break;

							case 'mailpoet' :
								$error_message = $this->get_mailpoet_lists( $name );
							break;

							case 'aweber' :
								$error_message = $this->get_aweber_lists( $details['api_key'], $name );
							break;

							case 'campaign_monitor' :
								$error_message = $this->get_campaign_monitor_lists( $details['api_key'], $name );
							break;

							case 'ontraport' :
								$error_message = $this->get_ontraport_lists( $details['api_key'], $details['client_id'], $name );
							break;

							case 'feedblitz' :
								$error_message = $this->get_feedblitz_lists( $details['api_key'], $name );
							break;

							case 'infusionsoft' :
								$error_message = $this->get_infusionsoft_lists( $details['client_id'], $details['api_key'], $name );
							break;
						}
					}

					$result = 'success' == $error_message
						? ''
						: 'bloom_error: ' . $service . ' ' . $name . ' ' . __( 'Authorization failed: ', 'bloom' ) . $error_message;

					// Log errors into WP log for troubleshooting
					if ( '' !== $result ) {
						error_log( $result );
					}
				}
			}
		}
	}

	/**
	 * Handles accounts authorization. Basically just executes specific function based on service and returns error message.
	 * Supports authorization of new accounts and re-authorization of existing accounts.
	 * @return string
	 */
	function authorize_account() {
		wp_verify_nonce( $_POST['get_lists_nonce'] , 'get_lists' );
		$service = ! empty( $_POST['bloom_upd_service'] ) ? sanitize_text_field( $_POST['bloom_upd_service'] ) : '';
		$name = ! empty( $_POST['bloom_upd_name'] ) ? sanitize_text_field( $_POST['bloom_upd_name'] ) : '';
		$update_existing = ! empty( $_POST['bloom_account_exists'] ) ? sanitize_text_field( $_POST['bloom_account_exists'] ) : '';

		if ( 'true' == $update_existing ) {
			$options_array = ET_Bloom::get_bloom_options();
			$accounts_data = $options_array['accounts'];

			$api_key = ! empty( $accounts_data[$service][$name]['api_key'] ) ? $accounts_data[$service][$name]['api_key'] : '';
			$token = ! empty( $accounts_data[$service][$name]['token'] ) ? $accounts_data[$service][$name]['token'] : '';
			$app_id = ! empty( $accounts_data[$service][$name]['client_id'] ) ? $accounts_data[$service][$name]['client_id'] : '';
			$username = ! empty( $accounts_data[$service][$name]['username'] ) ? $accounts_data[$service][$name]['username'] : '';
			$password = ! empty( $accounts_data[$service][$name]['password'] ) ? $accounts_data[$service][$name]['password'] : '';
		} else {
			$api_key = ! empty( $_POST['bloom_api_key'] ) ? sanitize_text_field( $_POST['bloom_api_key'] ) : '';
			$token = ! empty( $_POST['bloom_constant_token'] ) ? sanitize_text_field( $_POST['bloom_constant_token'] ) : '';
			$app_id = ! empty( $_POST['bloom_client_id'] ) ? sanitize_text_field( $_POST['bloom_client_id'] ) : '';
			$username = ! empty( $_POST['bloom_username'] ) ? sanitize_text_field( $_POST['bloom_username'] ) : '';
			$password = ! empty( $_POST['bloom_password'] ) ? sanitize_text_field( $_POST['bloom_password'] ) : '';
		}

		$error_message = '';

		switch ( $service ) {
			case 'mailchimp' :
				$error_message = $this->get_mailchimp_lists( $api_key, $name );
			break;

			case 'constant_contact' :
				$error_message = $this->get_constant_contact_lists( $api_key, $token, $name );
			break;

			case 'madmimi' :
				$error_message = $this->get_madmimi_lists( $username, $api_key, $name );
			break;

			case 'icontact' :
				$error_message = $this->get_icontact_lists( $app_id, $username, $password, $name );
			break;

			case 'getresponse' :
				$error_message = $this->get_getresponse_lists( $api_key, $name );
			break;

			case 'sendinblue' :
				$error_message = $this->get_sendinblue_lists( $api_key, $name );
			break;

			case 'mailpoet' :
				$error_message = $this->get_mailpoet_lists( $name );
			break;

			case 'aweber' :
				$error_message = $this->get_aweber_lists( $api_key, $name );
			break;

			case 'campaign_monitor' :
				$error_message = $this->get_campaign_monitor_lists( $api_key, $name );
			break;

			case 'ontraport' :
				$error_message = $this->get_ontraport_lists( $api_key, $app_id, $name );
			break;

			case 'feedblitz' :
				$error_message = $this->get_feedblitz_lists( $api_key, $name );
			break;

			case 'infusionsoft' :
				$error_message = $this->get_infusionsoft_lists( $app_id, $api_key, $name );
			break;
		}

		$result = 'success' == $error_message ?
			$error_message
			: __( 'Authorization failed: ', 'bloom' ) . $error_message;

		die( $result );
	}

	/**
	 * Handles subscribe action and sends the success or error message to jQuery.
	 */
	function subscribe() {
		wp_verify_nonce( $_POST['subscribe_nonce'] , 'subscribe' );

		$subscribe_data_json = str_replace( '\\', '' ,  $_POST[ 'subscribe_data_array' ] );
		$subscribe_data_array = json_decode( $subscribe_data_json, true );

		$service = sanitize_text_field( $subscribe_data_array['service'] );
		$account_name = sanitize_text_field( $subscribe_data_array['account_name'] );
		$name = isset( $subscribe_data_array['name'] ) ? sanitize_text_field( $subscribe_data_array['name'] ) : '';
		$last_name = isset( $subscribe_data_array['last_name'] ) ? sanitize_text_field( $subscribe_data_array['last_name'] ) : '';
		$email = sanitize_email( $subscribe_data_array['email'] );
		$list_id = sanitize_text_field( $subscribe_data_array['list_id'] );
		$page_id = sanitize_text_field( $subscribe_data_array['page_id'] );
		$optin_id = sanitize_text_field( $subscribe_data_array['optin_id'] );
		$result = '';

		if ( is_email( $email ) ) {
			$options_array = ET_Bloom::get_bloom_options();

			switch ( $service ) {
				case 'mailchimp' :
					$api_key = $options_array['accounts'][$service][$account_name]['api_key'];
					$error_message = $this->subscribe_mailchimp( $api_key, $list_id, $email, $name, $last_name );
					break;

				case 'constant_contact' :
					$api_key = $options_array['accounts'][$service][$account_name]['api_key'];
					$token = $options_array['accounts'][$service][$account_name]['token'];
					$error_message = $this->subscribe_constant_contact( $email, $api_key, $token, $list_id, $name, $last_name );
					break;

				case 'madmimi' :
					$api_key = $options_array['accounts'][$service][$account_name]['api_key'];
					$username = $options_array['accounts'][$service][$account_name]['username'];
					$error_message = $this->subscribe_madmimi( $username, $api_key, $list_id, $email, $name, $last_name );
					break;

				case 'icontact' :
					$app_id = $options_array['accounts'][$service][$account_name]['client_id'];
					$username = $options_array['accounts'][$service][$account_name]['username'];
					$password = $options_array['accounts'][$service][$account_name]['password'];
					$folder_id = $options_array['accounts'][$service][$account_name]['lists'][$list_id]['folder_id'];
					$account_id = $options_array['accounts'][$service][$account_name]['lists'][$list_id]['account_id'];
					$error_message = $this->subscribe_icontact( $app_id, $username, $password, $folder_id, $account_id, $list_id, $email, $name, $last_name );
					break;

				case 'getresponse' :
					$api_key = $options_array['accounts'][$service][$account_name]['api_key'];
					$error_message = $this->subscribe_get_response( $list_id, $email, $api_key, $name );
					break;

				case 'sendinblue' :
					$api_key = $options_array['accounts'][$service][$account_name]['api_key'];
					$error_message = $this->subscribe_sendinblue( $api_key, $email, $list_id, $name, $last_name );
					break;

				case 'mailpoet' :
					$error_message = $this->subscribe_mailpoet( $list_id, $email, $name, $last_name );
					break;

				case 'aweber' :
					$error_message = $this->subscribe_aweber( $list_id, $account_name, $email, $name );
					break;

				case 'campaign_monitor' :
					$api_key = $options_array['accounts'][$service][$account_name]['api_key'];
					$error_message = $this->subscribe_campaign_monitor( $api_key, $email, $list_id, $name );
					break;

				case 'ontraport' :
					$app_id = $options_array['accounts'][$service][$account_name]['client_id'];
					$api_key = $options_array['accounts'][$service][$account_name]['api_key'];
					$error_message = $this->subscribe_ontraport( $app_id, $api_key, $name, $email, $list_id, $last_name );
					break;

				case 'feedblitz' :
					$api_key = $options_array['accounts'][$service][$account_name]['api_key'];
					$error_message = $this->subscribe_feedblitz( $api_key, $list_id, $name, $email, $last_name );
					break;

				case 'infusionsoft' :
					$api_key = $options_array['accounts'][$service][$account_name]['api_key'];
					$app_id = $options_array['accounts'][$service][$account_name]['client_id'];
					$error_message = $this->subscribe_infusionsoft( $api_key, $app_id, $list_id, $email, $name, $last_name );
					break;
			}
		} else {
			$error_message = __( 'Invalid email', 'bloom' );
		}

		if ( 'success' == $error_message ) {
			ET_Bloom::add_stats_record( 'con', $optin_id, $page_id, $service . '_' . $list_id );
			$result = json_encode( array( 'success' => $error_message ) );
		} else {
			$result = json_encode( array( 'error' => $error_message ) );
		}

		die( $result );
	}

	/**
	 * Retrieves the lists via Infusionsoft API and updates the data in DB.
	 * @return string
	 */

	function get_infusionsoft_lists( $app_id, $api_key, $name ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'bloom' );
		}

		if ( ! class_exists( 'iSDK' ) ) {
			require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/infusionsoft/isdk.php' );
		}

		$lists = array();

		try {
			$infusion_app = new iSDK();
			$infusion_app->cfgCon( $app_id, $api_key, 'throw' );
		} catch( iSDKException $e ){
			$error_message = $e->getMessage();
		}

		if ( empty( $error_message ) ) {
			$need_request = true;
			$page = 0;
			$all_lists = array();

			while ( true == $need_request ) {
				$error_message = 'success';
				$lists_data = $infusion_app->dsQuery( 'ContactGroup', 1000, $page, array( 'Id' => '%' ), array( 'Id', 'GroupName' ) );
				$all_lists = array_merge( $all_lists, $lists_data );

				if ( 1000 > count( $lists_data ) ) {
					$need_request = false;
				} else {
					$page++;
				}
			}
		}

		if ( ! empty( $all_lists ) ) {
			foreach( $all_lists as $list ) {
				$group_query = '%' . $list['Id'] . '%';
				$subscribers_count = $infusion_app->dsCount( 'Contact', array( 'Groups' => $group_query ) );
				$lists[$list['Id']]['name'] = sanitize_text_field( $list['GroupName'] );
				$lists[$list['Id']]['subscribers_count'] = sanitize_text_field( $subscribers_count );
				$lists[$list['Id']]['growth_week'] = sanitize_text_field( $this->calculate_growth_rate( 'infusionsoft_' . $list['Id'] ) );
			}

			$this->update_account( 'infusionsoft', sanitize_text_field( $name ), array(
				'lists'         => $lists,
				'api_key'       => sanitize_text_field( $api_key ),
				'client_id'     => sanitize_text_field( $app_id ),
				'is_authorized' => 'true',
			) );
		}

		return $error_message;
	}

	/**
	 * Subscribes to Infusionsoft list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_infusionsoft( $api_key, $app_id, $list_id, $email, $name = '', $last_name = '' ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'bloom' );
		}

		if ( ! class_exists( 'iSDK' ) ) {
			require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/infusionsoft/isdk.php' );
		}

		try {
			$infusion_app = new iSDK();
			$infusion_app->cfgCon( $app_id, $api_key, 'throw' );
		} catch( iSDKException $e ){
			$error_message = $e->getMessage();
		}

		if ( empty( $error_message ) ) {
			$contact_data = $infusion_app->dsQuery( 'Contact', 1, 0, array( 'Email' => $email ), array( 'Id', 'Groups' ) );
			if ( 0 < count( $contact_data ) ) {
				if ( false === strpos( $contact_data[0]['Groups'], $list_id ) ) {
					$infusion_app->grpAssign( $contact_data[0]['Id'], $list_id );
					$error_message = 'success';
				} else {
					$error_message = __( 'Already subscribed', 'bloom' );
				}
			} else {
				$contact_details = array(
					'FirstName' => $name,
					'LastName'  => $last_name,
					'Email'     => $email,
				);

				$new_contact_id = $infusion_app->dsAdd( 'Contact', $contact_details );
				$infusion_app->grpAssign( $new_contact_id, $list_id );

				$error_message = 'success';
			}
		}

		return $error_message;
	}

	/**
	 * Retrieves the lists via MailChimp API and updates the data in DB.
	 * @return string
	 */
	function get_mailchimp_lists( $api_key = '', $name = '' ) {
		$lists = array();

		$error_message = '';

		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'bloom' );
		}

		if ( ! class_exists( 'MailChimp_Bloom' ) ) {
			require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/mailchimp/mailchimp.php' );
		}

		if ( false === strpos( $api_key, '-' ) ) {
			$error_message = __( 'invalid API key', 'bloom' );
		} else {
			$mailchimp = new MailChimp_Bloom( $api_key );

			$retval = $mailchimp->call( 'lists/list' );

			if ( ! empty( $retval ) && empty( $retval['errors'] ) ) {
				$error_message = 'success';

				if ( ! empty( $retval['data'] ) ) {
					foreach ( $retval['data'] as $list ) {
						$lists[$list['id']]['name'] = sanitize_text_field( $list['name'] );
						$lists[$list['id']]['subscribers_count'] = sanitize_text_field( $list['stats']['member_count'] );
						$lists[$list['id']]['growth_week'] = sanitize_text_field( $this->calculate_growth_rate( 'mailchimp_' . $list['id'] ) );
					}
				}
				$this->update_account( 'mailchimp', sanitize_text_field( $name ), array(
					'lists'         => $lists,
					'api_key'       => sanitize_text_field( $api_key ),
					'is_authorized' => 'true',
				) );
			} else {
				if ( ! empty( $retval['errors'] ) ) {
					$errors = '';
					foreach( $retval['errors'] as $error ) {
						$errors .= $error . ' ';
					}
					$error_message = $errors;
				}

				if ( '' !== $error_message ) {
					$error_message = sprintf( '%1$s: %2$s',
						esc_html__( 'Additional Information: ' ),
						$error_message
					);
				}

				$error_message = sprintf( '%1$s. %2$s',
					esc_html__( 'An error occured during API request. Make sure API Key is correct', 'bloom' ),
					$error_message
				);
			}
		}

		return $error_message;
	}

	/**
	 * Subscribes to Mailchimp list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_mailchimp( $api_key, $list_id, $email, $name = '', $last_name = '' ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return;
		}

		if ( ! class_exists( 'MailChimp_Bloom' ) ) {
			require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/mailchimp/mailchimp.php' );
		}

		$mailchimp = new MailChimp_Bloom( $api_key );

		$email = array( 'email' => $email );

		$merge_vars = array(
			'FNAME' => $name,
			'LNAME' => $last_name,
		);

		$retval =  $mailchimp->call( 'lists/subscribe', array(
			'id'         => $list_id,
			'email'      => $email,
			'merge_vars' => $merge_vars,
		));

		if ( isset( $retval['error'] ) ) {
			if ( '214' == $retval['code'] ) {
				$error_message = str_replace( 'Click here to update your profile.', '', $retval['error'] );
			} else {
				$error_message = $retval['error'];
			}
		} else {
			$error_message = 'success';
		}

		return $error_message;
	}

	/**
	 * Retrieves the lists via Constant Contact API and updates the data in DB.
	 * @return string
	 */
	function get_constant_contact_lists( $api_key, $token, $name ) {
		$lists = array();

		$request_url = esc_url_raw( 'https://api.constantcontact.com/v2/lists?api_key=' . $api_key );

		$theme_request = wp_remote_get( $request_url, array(
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );

		$response_code = wp_remote_retrieve_response_code( $theme_request );

		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ){
			$theme_response = wp_remote_retrieve_body( $theme_request );
			if ( ! empty( $theme_response ) ) {
				$error_message = 'success';

				$response = json_decode( $theme_response, true );

				foreach ( $response as $key => $value ) {
					if ( isset( $value['id'] ) ) {
						$lists[$value['id']]['name'] = sanitize_text_field( $value['name'] );
						$lists[$value['id']]['subscribers_count'] = sanitize_text_field( $value['contact_count'] );
						$lists[$value['id']]['growth_week'] = sanitize_text_field( $this->calculate_growth_rate( 'constant_contact_' . $value['id'] ) );
					}
				}

				$this->update_account( 'constant_contact', sanitize_text_field( $name ), array(
					'lists'         => $lists,
					'api_key'       => sanitize_text_field( $api_key ),
					'token'         => sanitize_text_field( $token ),
					'is_authorized' => 'true',
				) );
			} else {
				$error_message .= __( 'empty response', 'bloom' );
			}
		} else {
			if ( is_wp_error( $theme_request ) ) {
				$error_message = $theme_request->get_error_message();
			} else {
				switch ( $response_code ) {
					case '401' :
						$error_message = __( 'Invalid Token', 'bloom' );
						break;

					case '403' :
						$error_message = __( 'Invalid API key', 'bloom' );
						break;

					default :
						$error_message = $response_code;
						break;
				}
			}
		}

		return $error_message;
	}

	/**
	 * Subscribes to Constant Contact list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_constant_contact( $email, $api_key, $token, $list_id, $name = '', $last_name = '' ) {
		$request_url = esc_url_raw( 'https://api.constantcontact.com/v2/contacts?email=' . $email . '&api_key=' . $api_key );
		$error_message = '';

		$theme_request = wp_remote_get( $request_url, array(
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );
		$response_code = wp_remote_retrieve_response_code( $theme_request );

		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ){
			$theme_response = wp_remote_retrieve_body( $theme_request );
			$response = json_decode( $theme_response, true );

			if ( empty( $response['results'] ) ) {
				$request_url = esc_url_raw( 'https://api.constantcontact.com/v2/contacts?api_key=' . $api_key );
				$body_request = '{"email_addresses":[{"email_address": "' . $email . '" }], "lists":[{"id": "' . $list_id . '"}], "first_name": "' . $name . '", "last_name" : "' . $last_name .'" }';
				$theme_request = wp_remote_post( $request_url, array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'content-type' => 'application/json',
					),
					'body' => $body_request,
				) );
				$response_code = wp_remote_retrieve_response_code( $theme_request );
				if ( ! is_wp_error( $theme_request ) && $response_code == 201 ) {
					$error_message = 'success';
				} else {
					if ( is_wp_error( $theme_request ) ) {
						$error_message = $theme_request->get_error_message();
					} else {
						switch ( $response_code ) {
							case '409' :
								$error_message = __( 'Already subscribed', 'bloom' );
								break;

							default :
								$error_message = $response_code;
								break;
						}
					}
				}
			} else {
				$error_message = __( 'Already subscribed', 'bloom' );
			}
		} else {
			if ( is_wp_error( $theme_request ) ) {
				$error_message = $theme_request->get_error_message();
			} else {
				switch ( $response_code ) {
					case '401' :
						$error_message = __( 'Invalid Token', 'bloom' );
						break;

					case '403' :
						$error_message = __( 'Invalid API key', 'bloom' );
						break;

					default :
						$error_message = $response_code;
						break;
				}
			}
		}

		return $error_message;
	}


	/**
	 * Retrieves the lists via Campaign Monitor API and updates the data in DB.
	 * @return string
	 */
	function get_campaign_monitor_lists( $api_key, $name ) {
		require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/createsend-php-4.0.2/csrest_clients.php' );
		require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/createsend-php-4.0.2/csrest_lists.php' );

		$auth = array(
			'api_key' => $api_key,
		);

		$request_url = esc_url_raw( 'https://api.createsend.com/api/v3.1/clients.json?pretty=true' );
		$all_clients_id = array();
		$all_lists = array();

		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'bloom' );
		}

		// Get cURL resource
		$curl = curl_init();
		// Set some options
		curl_setopt_array( $curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL            => $request_url,
			CURLOPT_SSL_VERIFYPEER => FALSE, //we need this option since we perform request to https
			CURLOPT_USERPWD        => $api_key . ':x'
		) );
		// Send the request & save response to $resp
		$resp = curl_exec( $curl );
		$httpCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		// Close request to clear up some resources
		curl_close( $curl );

		$clients_array = json_decode( $resp, true );

		if ( '200' == $httpCode ) {
			$error_message = 'success';

			foreach( $clients_array as $client => $client_details ) {
				$all_clients_id[] = $client_details['ClientID'];
			}

			if ( ! empty( $all_clients_id ) ) {
				foreach( $all_clients_id as $client ) {
					$wrap = new CS_REST_Clients( $client,  $auth );
					$lists_data = $wrap->get_lists();

					foreach ( $lists_data->response as $list => $single_list ) {
						$all_lists[$single_list->ListID]['name'] = $single_list->Name;

						$wrap_stats = new CS_REST_Lists( $single_list->ListID, $auth );
						$result_stats = $wrap_stats->get_stats();
						$all_lists[$single_list->ListID]['subscribers_count'] = sanitize_text_field( $result_stats->response->TotalActiveSubscribers );
						$all_lists[$single_list->ListID]['growth_week'] = sanitize_text_field( $this->calculate_growth_rate( 'campaign_monitor_' . $single_list->ListID ) );
					}
				}
			}

			$this->update_account( 'campaign_monitor', sanitize_text_field( $name ), array(
				'api_key'       => sanitize_text_field( $api_key ),
				'lists'         => $all_lists,
				'is_authorized' => 'true',
			) );
		} else {
			if ( '401' == $httpCode ) {
				$error_message = __( 'invalid API key', 'bloom' );
			} else {
				$error_message = $httpCode;
			}
		}

		return $error_message;
	}

	/**
	 * Subscribes to Campaign Monitor list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_campaign_monitor( $api_key, $email, $list_id, $name = '' ) {
		require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/createsend-php-4.0.2/csrest_subscribers.php' );
		$auth = array(
			'api_key' => $api_key,
		);
		$wrap = new CS_REST_Subscribers( $list_id, $auth);
		$is_subscribed = $wrap->get( $email );

		if ( $is_subscribed->was_successful() ) {
			$error_message = __( 'Already subscribed', 'bloom' );
		} else {
			$result = $wrap->add( array(
				'EmailAddress' => $email,
				'Name'         => $name,
				'Resubscribe'  => false,
			) );
			if( $result->was_successful() ) {
				$error_message = 'success';
			} else {
				$error_message = $result->response->message;
			}
		}

		return $error_message;
	}

	/**
	 * Retrieves the lists via Mad Mimi API and updates the data in DB.
	 * @return string
	 */
	function get_madmimi_lists( $username, $api_key, $name ) {
		$lists = array();

		$request_url = esc_url_raw( 'https://api.madmimi.com/audience_lists/lists.json?username=' . rawurlencode( $username ) . '&api_key=' . $api_key );

		$theme_request = wp_remote_get( $request_url, array( 'timeout' => 30 ) );

		$response_code = wp_remote_retrieve_response_code( $theme_request );

		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ){
			$theme_response = json_decode( wp_remote_retrieve_body( $theme_request ), true );
			if ( ! empty( $theme_response ) ) {
				$error_message = 'success';

				foreach ( $theme_response as $list_data ) {
					$lists[$list_data['id']]['name'] = $list_data['name'];
					$lists[$list_data['id']]['subscribers_count'] = $list_data['list_size'];
					$lists[$list_data['id']]['growth_week'] = $this->calculate_growth_rate( 'madmimi_' . $list_data['id'] );
				}

				$this->update_account( 'madmimi', $name, array(
					'api_key' => esc_html( $api_key ),
					'username' => esc_html( $username ),
					'lists' => $lists,
					'is_authorized' => esc_html( 'true' ),
				) );

			} else {
				$error_message = __( 'Please make sure you have at least 1 list in your account and try again', 'bloom' );
			}
		} else {
			if ( is_wp_error( $theme_request ) ) {
				$error_message = $theme_request->get_error_message();
			} else {
				switch ( $response_code ) {
					case '401' :
						$error_message = __( 'Invalid Username or API key', 'bloom' );
						break;

					default :
						$error_message = $response_code;
						break;
				}
			}
		}

		return $error_message;
	}

	/**
	 * Subscribes to Mad Mimi list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_madmimi( $username, $api_key, $list_id, $email, $name = '', $last_name = '' ) {
		// check whether the user already subscribed
		$check_user_url = esc_url_raw( 'https://api.madmimi.com/audience_members/' . rawurlencode( $email ) . '/lists.json?username=' . rawurlencode( $username ) . '&api_key=' . $api_key );

		$check_user_request = wp_remote_get( $check_user_url, array( 'timeout' => 30 ) );

		$check_user_response_code = wp_remote_retrieve_response_code( $check_user_request );

		if ( ! is_wp_error( $check_user_request ) && $check_user_response_code == 200 ){
			$check_user_response = json_decode( wp_remote_retrieve_body( $check_user_request ), true );

			// if user is not subscribed yet - try to subscribe
			if ( empty( $check_user_response ) ) {
				$request_url = esc_url_raw( 'https://api.madmimi.com/audience_lists/' . $list_id . '/add?email=' . rawurlencode( $email ) . '&first_name=' . $name . '&last_name=' . $last_name . '&username=' . rawurlencode( $username ) . '&api_key=' . $api_key );

				$theme_request = wp_remote_post( $request_url, array( 'timeout' => 30 ) );

				$response_code = wp_remote_retrieve_response_code( $theme_request );

				if ( ! is_wp_error( $theme_request ) && $response_code == 200 ){
					$error_message = 'success';
				} else {
					if ( is_wp_error( $theme_request ) ) {
						$error_message = $theme_request->get_error_message();
					} else {
						switch ( $response_code ) {
							case '401' :
								$error_message = __( 'Invalid Username or API key', 'bloom' );
								break;
							case '400' :
								$error_message = wp_remote_retrieve_body( $theme_request );
								break;

							default :
								$error_message = $response_code;
								break;
						}
					}
				}
			} else {
				$error_message = __( 'Already subscribed', 'bloom' );
			}
		} else {
			if ( is_wp_error( $theme_request ) ) {
				$error_message = $theme_request->get_error_message();
			} else {
				switch ( $response_code ) {
					case '401' :
						$error_message = __( 'Invalid Username or API key', 'bloom' );
						break;
					default :
						$error_message = $response_code;
						break;
				}
			}
		}

		return $error_message;
	}

	/**
	 * Retrieves the lists via iContact API and updates the data in DB.
	 * @return string
	 */
	function get_icontact_lists( $app_id, $username, $password, $name ) {
		$lists = array();
		$account_id = '';
		$folder_id = '';

		$request_account_id_url = esc_url_raw( 'https://app.icontact.com/icp/a/' );

		$account_data = $this->icontacts_remote_request( $request_account_id_url, $app_id, $username, $password );

		if ( is_array( $account_data ) ) {
			$account_id = $account_data['accounts'][0]['accountId'];

			if ( '' !== $account_id ) {
				$request_folder_id_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c' );

				$folder_data = $this->icontacts_remote_request( $request_folder_id_url, $app_id, $username, $password );

				if ( is_array( $folder_data ) ) {
					$folder_id = $folder_data['clientfolders'][0]['clientFolderId'];

					$request_lists_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/lists' );
					$lists_data = $this->icontacts_remote_request( $request_lists_url, $app_id, $username, $password );

					if ( is_array( $lists_data ) ) {
						$error_message = 'success';
						foreach ( $lists_data['lists'] as $single_list ) {
							$lists[$single_list['listId']]['name'] = $single_list['name'];
							$lists[$single_list['listId']]['account_id'] = $account_id;
							$lists[$single_list['listId']]['folder_id'] = $folder_id;

							//request for subscribers
							$request_contacts_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/contacts?status=total&listId=' . $single_list['listId'] );
							$subscribers_data = $this->icontacts_remote_request( $request_contacts_url, $app_id, $username, $password );
							$total_subscribers = isset( $subscribers_data['total'] ) ? $subscribers_data['total'] : 0;

							$lists[$single_list['listId']]['subscribers_count'] = $total_subscribers;
							$lists[$single_list['listId']]['growth_week'] = $this->calculate_growth_rate( 'icontact_' . $single_list['listId'] );
						}

						$this->update_account( 'icontact', $name, array(
							'client_id'     => esc_html( $app_id ),
							'username'      => esc_html( $username ),
							'password'      => esc_html( $password ),
							'lists'         => $lists,
							'is_authorized' => esc_html( 'true' ),
						) );
					} else {
						$error_message = $lists_data;
					}
				} else {
					$error_message = $folder_data;
				}
			} else {
				$error_message = __( 'Account ID is not defined', 'bloom' );
			}
		} else {
			$error_message = $account_data;
		}

		return $error_message;
	}

	/**
	 * Subscribes to iContact list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_icontact( $app_id, $username, $password, $folder_id, $account_id, $list_id, $email, $name = '', $last_name = '' ) {
		$check_subscription_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/contacts?email=' . rawurlencode( $email ) );
		$is_subscribed = $this->icontacts_remote_request( $check_subscription_url, $app_id, $username, $password );
		if ( is_array( $is_subscribed ) ) {
			if ( empty( $is_subscribed['contacts'] ) ) {
				$add_body = '[{
					"email":"' . $email .'",
					"firstName":"' . $name . '",
					"lastName":"' . $last_name . '",
					"status":"normal"
				}]';
				$add_subscriber_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/contacts/' );

				$added_account = $this->icontacts_remote_request( $add_subscriber_url, $app_id, $username, $password, true, $add_body );
				if ( is_array( $added_account ) ) {
					if ( ! empty( $added_account['contacts'][0]['contactId'] ) ) {
						$map_contact = '[{
							"contactId":' . $added_account['contacts'][0]['contactId'] . ',
							"listId":' . $list_id . ',
							"status":"normal"
						}]';
						$map_subscriber_url = esc_url_raw( 'https://app.icontact.com/icp/a/' . $account_id . '/c/' . $folder_id . '/subscriptions/' );

						$add_to_list = $this->icontacts_remote_request( $map_subscriber_url, $app_id, $username, $password, true, $map_contact );
					}
					$error_message = 'success';
				} else {
					$error_message = $added_account;
				}
			} else {
				$error_message = __( 'Already subscribed', 'bloom' );
			}
		} else {
			$error_message = $is_subscribed;
		}

		return $error_message;
	}

	/**
	 * Executes remote request to iContacts API
	 * @return string
	 */
	function icontacts_remote_request( $request_url, $app_id, $username, $password, $is_post = false, $body = '' ) {
		if ( false === $is_post ) {
			$theme_request = wp_remote_get( $request_url, array(
				'timeout' => 30,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'Api-Version'  => '2.0',
					'Api-AppId'    => $app_id,
					'Api-Username' => $username,
					'API-Password' => $password,
				)
			) );
		} else {
			$theme_request = wp_remote_post( $request_url, array(
				'timeout' => 30,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'Api-Version'  => '2.0',
					'Api-AppId'    => $app_id,
					'Api-Username' => $username,
					'API-Password' => $password,
				),
				'body' => $body,
			) );
		}

		$response_code = wp_remote_retrieve_response_code( $theme_request );
		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ){
			$theme_response = wp_remote_retrieve_body( $theme_request );
			if ( ! empty( $theme_response ) ) {
				$error_message = json_decode( wp_remote_retrieve_body( $theme_request ), true );
			} else {
				$error_message = __( 'empty response', 'bloom' );
			}
		} else {
			if ( is_wp_error( $theme_request ) ) {
				$error_message = $theme_request->get_error_message();
			} else {
				switch ( $response_code ) {
					case '401' :
						$error_message = __( 'Invalid App ID, Username or Password', 'bloom' );
						break;

					default :
						$error_message = $response_code;
						break;
				}
			}
		}

		return $error_message;
	}

	/**
	 * Retrieves the lists via GetResponse API and updates the data in DB.
	 * @return string
	 */
	function get_getresponse_lists( $api_key, $name ) {
		$lists = array();

		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'bloom' );
		}

		if ( ! class_exists( 'GetResponse' ) ) {
			require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/getresponse/getresponseapi.class.php' );
		}

		$api = new GetResponse( $api_key );

		$campaigns = (array) $api->getCampaigns();

		if ( ! empty( $campaigns ) ) {
			$error_message = 'success';

			foreach( $campaigns as $id => $details ) {
				$lists[$id]['name'] = $details->name;
				$contacts = (array) $api->getContacts( array( $id ) );

				$total_contacts = count( $contacts );
				$lists[$id]['subscribers_count'] = $total_contacts;

				$lists[$id]['growth_week'] = $this->calculate_growth_rate( 'getresponse_' . $id );
			}

			$this->update_account( 'getresponse', $name, array(
				'api_key' => esc_html( $api_key ),
				'lists' => $lists,
				'is_authorized' => esc_html( 'true' ),
			) );
		} else {
			$error_message = __( 'Invalid API key or something went wrong during Authorization request', 'bloom' );
		}

		return $error_message;
	}

	/**
	 * Subscribes to GetResponse list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_get_response( $list, $email, $api_key, $name = '-' ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return;
		}

		require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/getresponse/jsonrpcclient.php' );
		$api_url = 'http://api2.getresponse.com';

		$name = '' == $name ? '-' : $name;

		$client = new jsonRPCClient( $api_url );
		$result = $client->add_contact(
			$api_key,
			array(
				'campaign' => $list,
				'name'     => $name,
				'email'    => $email,
			)
		);

		if ( isset( $result['result']['queued'] ) && 1 == $result['result']['queued'] ) {
			$result = 'success';
		} else {
			if ( isset( $result['error']['message'] ) ) {
				$result = $result['error']['message'];
			} else {
				$result = 'unknown error';
			}
		}

		return $result;
	}

	/**
	 * Retrieves the lists via Sendinblue API and updates the data in DB.
	 * @return string
	 */
	function get_sendinblue_lists( $api_key, $name ) {
		$lists = array();

		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'bloom' );
		}

		if ( ! class_exists( 'Mailin' ) ) {
			require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/sendinblue-v2.0/mailin.php' );
		}

		$mailin = new Mailin( 'https://api.sendinblue.com/v2.0', $api_key );
		$page = 1;
		$page_limit = 50;
		$all_lists = array();
		$need_request = true;

		while ( true == $need_request ) {
			$lists_array = $mailin->get_lists( $page, $page_limit );
			$all_lists = array_merge( $all_lists, $lists_array );
			if ( 50 > count( $lists_array ) ) {
				$need_request = false;
			} else {
				$page++;
			}
		}

		if ( ! empty( $all_lists ) ) {
			if ( isset( $all_lists['code'] ) && 'success' === $all_lists['code'] ) {
				$error_message = 'success';

				if ( ! empty( $all_lists['data']['lists'] ) ) {
					foreach( $all_lists['data']['lists'] as $single_list ) {
						$lists[$single_list['id']]['name'] = $single_list['name'];

						$total_contacts = isset( $single_list['total_subscribers'] ) ? $single_list['total_subscribers'] : 0;
						$lists[$single_list['id']]['subscribers_count'] = $total_contacts;

						$lists[$single_list['id']]['growth_week'] = $this->calculate_growth_rate( 'sendinblue_' . $single_list['id'] );
					}
				}

				$this->update_account( 'sendinblue', $name, array(
					'api_key'       => esc_html( $api_key ),
					'lists'         => $lists,
					'is_authorized' => esc_html( 'true' ),
				) );
			} else {
				$error_message = $all_lists['message'];
			}
		} else {
			$error_message = __( 'Invalid API key or something went wrong during Authorization request', 'bloom' );
		}

		return $error_message;
	}

	/**
	 * Subscribes to Sendinblue list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_sendinblue( $api_key, $email, $list_id, $name, $last_name = '' ) {
		if ( ! function_exists( 'curl_init' ) ) {
			return __( 'curl_init is not defined ', 'bloom' );
		}

		if ( ! class_exists( 'Mailin' ) ) {
			require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/sendinblue-v2.0/mailin.php' );
		}

		$mailin = new Mailin( 'https://api.sendinblue.com/v2.0', $api_key );
		$user = $mailin->get_user( $email );
		if ( 'failure' == $user['code'] ) {
			$attributes = array(
				"NAME"    => $name,
				"SURNAME" => $last_name,
			);
			$blacklisted = 0;
			$listid = array( $list_id );
			$listid_unlink = array();
			$blacklisted_sms = 0;

			$result = $mailin->create_update_user( $email, $attributes, $blacklisted, $listid, $listid_unlink, $blacklisted_sms );

			if ( 'success' == $result['code'] ) {
				$error_message = 'success';
			} else {
				if ( ! empty( $result['message'] ) ) {
					$error_message = $result['message'];
				} else {
					$error_message = __( 'Unknown error', 'bloom' );
				}
			}
		} else {
			$error_message = __( 'Already subscribed', 'bloom' );
		}

		return $error_message;
	}

	/**
	 * Retrieves the lists from MailPoet table and updates the data in DB.
	 * @return string
	 */
	function get_mailpoet_lists( $name ) {
		$lists = array();

		global $wpdb;
		$table_name = $wpdb->prefix . 'wysija_list';
		$table_users = $wpdb->prefix . 'wysija_user_list';

		if ( ! class_exists( 'WYSIJA' ) ) {
			$error_message = __( 'MailPoet plugin is not installed or not activated', 'bloom' );
		} else {
			$list_model = WYSIJA::get( 'list', 'model' );
			$all_lists_array = $list_model->get( array( 'name', 'list_id' ), array( 'is_enabled' => '1' ) );

			$error_message = 'success';

			if ( ! empty( $all_lists_array ) ) {
				foreach ( $all_lists_array as $list_details ) {
					$lists[$list_details['list_id']]['name'] = $list_details['name'];

					$user_model = WYSIJA::get( 'user_list', 'model' );
					$all_subscribers_array = $user_model->get( array( 'user_id' ), array( 'list_id' => $list_details['list_id'] ) );

					$subscribers_count = count( $all_subscribers_array );
					$lists[$list_details['list_id']]['subscribers_count'] = $subscribers_count;

					$lists[$list_details['list_id']]['growth_week'] = $this->calculate_growth_rate( 'mailpoet_' . $list_details['list_id'] );
				}
			}

			$this->update_account( 'mailpoet', $name, array(
				'lists'         => $lists,
				'is_authorized' => esc_html( 'true' ),
			) );
		}

		return $error_message;
	}

	/**
	 * Subscribes to MailPoet list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_mailpoet( $list_id, $email, $name = '', $last_name = '' ) {
		global $wpdb;
		$table_user = $wpdb->prefix . 'wysija_user';
		$table_user_lists = $wpdb->prefix . 'wysija_user_list';

		if ( ! class_exists( 'WYSIJA' ) ) {
			$error_message = __( 'MailPoet plugin is not installed or not activated', 'bloom' );
		} else {
			$sql_count = "SELECT COUNT(*) FROM $table_user WHERE email = %s";
			$sql_args = array(
				$email,
			);

			$subscribers_count = $wpdb->get_var( $wpdb->prepare( $sql_count, $sql_args ) );

			if ( 0 == $subscribers_count ) {

				$new_user = array(
					'user'      => array(
						'email'     => $email,
						'firstname' => $name,
						'lastname'  => $last_name
					),

					'user_list' => array( 'list_ids' => array( $list_id ) )
				);

				$mailpoet_class = WYSIJA::get( 'user', 'helper' );
				$error_message = $mailpoet_class->addSubscriber( $new_user );
				$error_message = is_int( $error_message ) ? 'success' : $error_message;
			} else {
				$error_message = __( 'Already Subscribed', 'bloom' );
			}
		}

		return $error_message;
	}

	/**
	 * Retrieves the lists via AWeber API and updates the data in DB.
	 * @return string
	 */
	function get_aweber_lists( $api_key, $name ) {
		$options_array = ET_Bloom::get_bloom_options();
		$lists = array();

		if ( ! isset( $options_array['accounts']['aweber'][$name]['consumer_key'] ) || ( $api_key != $options_array['accounts']['aweber'][$name]['api_key'] ) ) {
			$error_message = $this->aweber_authorization( $api_key, $name );
		} else {
			$error_message = 'success';
		}

		if ( 'success' === $error_message ) {
			if ( ! class_exists( 'AWeberAPI' ) ) {
				require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/aweber/aweber_api.php' );
			}

			$account = $this->get_aweber_account( $name );

			if ( $account ) {
				$aweber_lists = $account->lists;
				if ( isset( $aweber_lists ) ) {
					foreach ( $aweber_lists as $list ) {
						$lists[$list->id]['name'] = $list->name;

						$total_subscribers = $list->total_subscribers;
						$lists[$list->id]['subscribers_count'] = $total_subscribers;

						$lists[$list->id]['growth_week'] = $this->calculate_growth_rate( 'aweber_' . $list->id );
					}
				}
			}

			$this->update_account( 'aweber', $name, array( 'lists' => $lists ) );
		}

		return $error_message;
	}

	/**
	 * Subscribes to Aweber list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_aweber( $list_id, $account_name, $email, $name = '' ) {
		if ( ! class_exists( 'AWeberAPI' ) ) {
			require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/aweber/aweber_api.php' );
		}

		$account = $this->get_aweber_account( $account_name );

		if ( ! $account ) {
			$error_message = __( 'Aweber: Wrong configuration data', 'bloom' );
		}

		try {
			$list_url = "/accounts/{$account->id}/lists/{$list_id}";
			$list = $account->loadFromUrl( $list_url );

			$new_subscriber = $list->subscribers->create(
				array(
					'email' => $email,
					'name'  => $name,
				)
			);

			$error_message = 'success';
		} catch ( Exception $exc ) {
			$error_message = $exc->message;
		}

		return $error_message;
	}

	/**
	 * Retrieves the tokens from AWeber
	 * @return string
	 */
	function aweber_authorization( $api_key, $name ) {

		if ( ! class_exists( 'AWeberAPI' ) ) {
			require_once( ET_BLOOM_PLUGIN_DIR . 'subscription/aweber/aweber_api.php' );
		}

		try {
			$auth = AWeberAPI::getDataFromAweberID( $api_key );

			if ( ! ( is_array( $auth ) && 4 === count( $auth ) ) ) {
				$error_message = __( 'Authorization code is invalid. Try regenerating it and paste in the new code.', 'bloom' );
			} else {
				$error_message = 'success';
				list( $consumer_key, $consumer_secret, $access_key, $access_secret ) = $auth;

				$this->update_account( 'aweber', $name, array(
					'api_key'         => esc_html( $api_key ),
					'consumer_key'    => $consumer_key,
					'consumer_secret' => $consumer_secret,
					'access_key'      => $access_key,
					'access_secret'   => $access_secret,
					'is_authorized'   => esc_html( 'true' ),
				) );
			}
		} catch ( AWeberAPIException $exc ) {
			$error_message = sprintf(
				'<p>%4$s</p>
				<ul>
					<li>%5$s: %1$s</li>
					<li>%6$s: %2$s</li>
					<li>%7$s: %3$s</li>
				</ul>',
				esc_html( $exc->type ),
				esc_html( $exc->message ),
				esc_html( $exc->documentation_url ),
				esc_html__( 'AWeberAPIException.', 'bloom' ),
				esc_html__( 'Type', 'bloom' ),
				esc_html__( 'Message', 'bloom' ),
				esc_html__( 'Documentation', 'bloom' )
			);
		}

		return $error_message;
	}

	/**
	 * Creates Aweber account using the data saved to plugin's database.
	 * @return object or false
	 */
	function get_aweber_account( $name ) {
		if ( ! class_exists( 'AWeberAPI' ) ) {
			require_once( get_template_directory() . '/includes/subscription/aweber/aweber_api.php' );
		}

		$options_array = ET_Bloom::get_bloom_options();
		$account = false;

		if ( isset( $options_array['accounts']['aweber'][$name] ) ) {
			$consumer_key = $options_array['accounts']['aweber'][$name]['consumer_key'];
			$consumer_secret = $options_array['accounts']['aweber'][$name]['consumer_secret'];
			$access_key = $options_array['accounts']['aweber'][$name]['access_key'];
			$access_secret = $options_array['accounts']['aweber'][$name]['access_secret'];

			try {
				// Aweber requires curl extension to be enabled
				if ( ! function_exists( 'curl_init' ) ) {
					return false;
				}

				$aweber = new AWeberAPI( $consumer_key, $consumer_secret );

				if ( ! $aweber ) {
					return false;
				}

				$account = $aweber->getAccount( $access_key, $access_secret );
			} catch ( Exception $exc ) {
				return false;
			}
		}

		return $account;
	}

	/**
	 * Retrieves the lists via feedblitz API and updates the data in DB.
	 * @return string
	 */
	function get_feedblitz_lists( $api_key, $name ) {
		$lists = array();

		$request_url = esc_url_raw( 'https://api.feedblitz.com/f.api/syndications?key=' . $api_key );

		$theme_request = wp_remote_get( $request_url, array( 'timeout' => 30, 'sslverify' => false ) );

		$response_code = wp_remote_retrieve_response_code( $theme_request );

		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ){
			$theme_response = $this->xml_to_array( wp_remote_retrieve_body( $theme_request ) );

			if ( ! empty( $theme_response ) ) {
				if ( 'ok' == $theme_response['rsp']['@attributes']['stat'] ) {
					$error_message = 'success';
					$lists_array = $theme_response['syndications']['syndication'];

					if ( ! empty( $lists_array ) ) {
						foreach( $lists_array as $list_data ) {
							$lists[$list_data['id']]['name'] = $list_data['name'];
							$lists[$list_data['id']]['subscribers_count'] = $list_data['subscribersummary']['subscribers'];

							$lists[$list_data['id']]['growth_week'] = $this->calculate_growth_rate( 'feedblitz_' . $list_data['id'] );
						}
					}

					$this->update_account( 'feedblitz', $name, array(
						'api_key'       => esc_html( $api_key ),
						'lists'         => $lists,
						'is_authorized' => esc_html( 'true' ),
					) );
				} else {
					$error_message = isset( $theme_response['rsp']['err']['@attributes']['msg'] ) ? $theme_response['rsp']['err']['@attributes']['msg'] : __( 'Unknown error', 'bloom' );
				}

			} else {
				$error_message = __( 'empty response', 'bloom' );
			}
		} else {
			if ( is_wp_error( $theme_request ) ) {
				$error_message = $theme_request->get_error_message();
			} else {
				$error_message = $response_code;
			}
		}

		return $error_message;

	}

	/**
	 * Subscribes to feedblitz list. Returns either "success" string or error message.
	 * @return string
	 */
	function subscribe_feedblitz( $api_key, $list_id, $name, $email = '', $last_name = '' ) {
		$request_url = esc_url_raw( 'https://www.feedblitz.com/f?SimpleApiSubscribe&key=' . $api_key . '&email=' . rawurlencode( $email ) . '&listid=' . $list_id . '&FirstName=' . $name . '&LastName=' . $last_name );
		$theme_request = wp_remote_get( $request_url, array( 'timeout' => 30, 'sslverify' => false ) );

		$response_code = wp_remote_retrieve_response_code( $theme_request );

		if ( ! is_wp_error( $theme_request ) && $response_code == 200 ){
			$theme_response = $this->xml_to_array( wp_remote_retrieve_body( $theme_request ) );
			if ( ! empty( $theme_response ) ) {
				if ( 'ok' == $theme_response['rsp']['@attributes']['stat'] ) {
					if ( empty( $theme_response['rsp']['success']['@attributes']['msg'] ) ) {
						$error_message = 'success';
					} else {
						$error_message = $theme_response['rsp']['success']['@attributes']['msg'];
					}
				} else {
					$error_message = isset( $theme_response['rsp']['err']['@attributes']['msg'] ) ? $theme_response['rsp']['err']['@attributes']['msg'] : __( 'Unknown error', 'bloom' );
				}
			} else {
				$error_message = __( 'empty response', 'bloom' );
			}
		} else {
			if ( is_wp_error( $theme_request ) ) {
				$error_message = $theme_request->get_error_message();
			} else {
				$error_message = $response_code;
			}
		}

		return $error_message;
	}

	/**
	 * Retrieves the lists via OntraPort API and updates the data in DB.
	 * @return string
	 */
	function get_ontraport_lists( $api_key, $app_id, $name ) {
		$appid = $app_id;
		$key = $api_key;
		$lists = array();
		$list_id_array = array();

		// get sequences (lists)
		$req_type = "fetch_sequences";
		$postargs = "appid=" . $appid . "&key=" . $key . "&reqType=" . $req_type;
		$request = "https://api.ontraport.com/cdata.php";
		$result = $this->ontraport_request( $postargs, $request );
		$lists_array = $this->xml_to_array( $result );
		$lists_id = simplexml_load_string( $result );

		foreach ( $lists_id->sequence as $value ) {
			$list_id_array[] = (int) $value->attributes()->id;
		}

		if ( is_array( $lists_array ) ) {
			$error_message = 'success';
			if ( ! empty( $lists_array['sequence'] ) ) {
				$sequence_array = is_array( $lists_array['sequence'] )
					? $lists_array['sequence']
					: $lists_array;

				$i = 0;

				foreach( $sequence_array as $id => $list_name ) {
					$lists[$list_id_array[$i]]['name'] = $list_name;

					// we cannot get amount of subscribers for each sequence due to API limitations, so set it to 0.
					$lists[$list_id_array[$i]]['subscribers_count'] = 0;

					$lists[$list_id_array[$i]]['growth_week'] = $this->calculate_growth_rate( 'ontraport_' . $list_id_array[$i] );
					$i++;
				}
			}
			$this->update_account( 'ontraport', $name, array(
				'api_key'       => esc_html( $api_key ),
				'client_id'     => esc_html( $app_id ),
				'lists'         => $lists,
				'is_authorized' => esc_html( 'true' ),
			) );
		} else {
			$error_message = $lists_array;
		}

		return $error_message;
	}

	function subscribe_ontraport( $app_id, $api_key, $name, $email, $list_id, $last_name = '' ) {
$data_check = <<<STRING
<search><equation>
<field>Email</field>
<op>e</op>
<value>
STRING;
$data_check .= $email;
$data_check .= <<<STRING
</value>
</equation>
</search>
STRING;

		$data_check = urlencode( urlencode( $data_check ) );
		$reqType_search = "search";
		$postargs_search = "appid=" . $app_id . "&key=" . $api_key . "&reqType=" . $reqType_search . "&data=" . $data_check;
		$result_search = $this->ontraport_request( $postargs_search );
		$user_array_search = $this->xml_to_array( $result_search );

		//make sure that user is not subscribed yet
		if ( empty( $user_array_search ) ) {
// Construct contact data in XML format
$data = <<<STRING
<contact>
<Group_Tag name="Contact Information">
<field name="First Name">
STRING;
$data .= $name;
$data .= <<<STRING
</field>
<field name="Last Name">
STRING;
$data .= $last_name;
$data .= <<<STRING
</field>
<field name="Email">
STRING;
$data .= $email;
$data .= <<<STRING
</field>
</Group_Tag>
<Group_Tag name="Sequences and Tags">
<field name="Contact Tags"></field>
<field name="Sequences">*/*
STRING;
$data .= $list_id;
$data .= <<<STRING
*/*</field>
</Group_Tag>
</contact>
STRING;

			$data = urlencode( urlencode( $data ) );
			$reqType = "add";
			$postargs = "appid=" . $app_id . "&key=" . $api_key . "&return_id=1&reqType=" . $reqType . "&data=" . $data;

			$result = $this->ontraport_request( $postargs );
			$user_array = $this->xml_to_array( $result );

			if ( isset( $user_array['status'] ) && 'Success' == $user_array['status'] ) {
				$error_message = 'success';
			} else {
				$error_message = __( 'Error occured during subscription', 'bloom' );
			}
		} else {
			$error_message = __( 'Already Subscribed', 'bloom' );
		}

		return $error_message;
	}

	/**
	 * Performs the request to OntraPort API and handles the response
	 * @return xml
	 */
	function ontraport_request( $postargs ) {
		if ( ! function_exists( 'curl_init' ) ) {
			$response =  __( 'curl_init is not defined ', 'bloom' );
		} else {
			$response = '';
			$httpCode = '';
			// Get cURL resource
			$curl = curl_init();
			// Set some options
			curl_setopt_array( $curl, array(
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_HEADER         => FALSE,
				CURLOPT_URL            => "https://api.ontraport.com/cdata.php",
				CURLOPT_POST           => TRUE,
				CURLOPT_POSTFIELDS     => $postargs,
				CURLOPT_SSL_VERIFYPEER => FALSE, //we need this option since we perform request to https
			) );
			// Send the request & save response to $resp
			$response = curl_exec( $curl );
			$httpCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
			// Close request to clear up some resources
			curl_close( $curl );

			if ( 200 == $httpCode ) {
				$response = $response;
			} else {
				$response = $httpCode;
			}
		}

		return $response;
	}

	/**
	 * Converts xml data to array
	 * @return array
	 */
	function xml_to_array( $xml_data ) {
		$xml = simplexml_load_string( $xml_data );
		$json = json_encode( $xml );
		$array = json_decode( $json, true );

		return $array;
	}

	/**
	 * Generates output for the "Form Integration" options.
	 * @return string
	 */
	function generate_accounts_list() {
		wp_verify_nonce( $_POST['retrieve_lists_nonce'] , 'retrieve_lists' );
		$service = !empty( $_POST['bloom_service'] ) ? sanitize_text_field( $_POST['bloom_service'] ) : '';
		$optin_id = !empty( $_POST['bloom_optin_id'] ) ? sanitize_text_field( $_POST['bloom_optin_id'] ) : '';
		$new_account = !empty( $_POST['bloom_add_account'] ) ? sanitize_text_field( $_POST['bloom_add_account'] ) : '';

		$options_array = ET_Bloom::get_bloom_options();
		$current_account = isset( $options_array[$optin_id]['account_name'] ) ? $options_array[$optin_id]['account_name'] : 'empty';

		$available_accounts = array();

		if ( isset( $options_array['accounts'] ) ) {
			if ( isset( $options_array['accounts'][$service] ) ) {
				foreach ( $options_array['accounts'][$service] as $account_name => $details ) {
					$available_accounts[] = $account_name;
				}
			}
		}

		if ( ! empty( $available_accounts ) && '' === $new_account ) {
			printf(
				'<li class="select et_dashboard_select_account">
					<p>%1$s</p>
					<select name="et_dashboard[account_name]" data-service="%4$s">
						<option value="empty" %3$s>%2$s</option>
						<option value="add_new">%5$s</option>',
				__( 'Select Account', 'bloom' ),
				__( 'Select One...', 'bloom' ),
				selected( 'empty', $current_account, false ),
				esc_attr( $service ),
				__( 'Add Account', 'bloom' )
			);

			if ( ! empty( $available_accounts ) ) {
				foreach ( $available_accounts as $account ) {
					printf( '<option value="%1$s" %3$s>%2$s</option>',
						esc_attr( $account ),
						esc_html( $account ),
						selected( $account, $current_account, false )
					);
				}
			}

			printf( '
					</select>
				</li>' );
		} else {
			$form_fields = $this->generate_new_account_form( $service );

			printf(
				'<li class="select et_dashboard_select_account et_dashboard_new_account">
					%3$s
					<button class="et_dashboard_icon authorize_service" data-service="%2$s">%1$s</button>
					<span class="spinner"></span>
				</li>',
				__( 'Add Account', 'bloom' ),
				esc_attr( $service ),
				$form_fields
			);
		}

		die();
	}

	/**
	 * Generates fields for the account authorization form based on the service
	 * @return string
	 */
	function generate_new_account_form( $service, $account_name = '', $display_name = true ) {
		$field_values = '';

		if ( '' !== $account_name ) {
			$options_array = ET_Bloom::get_bloom_options();
			$field_values = $options_array['accounts'][$service][$account_name];
		}

		$form_fields = sprintf(
			'<div class="account_settings_fields" data-service="%1$s">',
			esc_attr( $service )
		);

		if ( true === $display_name ) {
			$form_fields .= sprintf( '
				<div class="et_dashboard_account_row">
					<label for="%1$s">%2$s</label>
					<input type="text" value="%3$s" id="%1$s">%4$s
				</div>',
				esc_attr( 'name_' . $service ),
				__( 'Account Name', 'bloom' ),
				esc_attr( $account_name ),
				ET_Bloom::generate_hint( __( 'Enter the name for your account', 'bloom' ), true )
			);
		}

		switch ( $service ) {
			case 'madmimi' :

				$form_fields .= sprintf( '
					<div class="et_dashboard_account_row">
						<label for="%1$s">%3$s</label>
						<input type="password" value="%5$s" id="%1$s">%7$s
					</div>
					<div class="et_dashboard_account_row">
						<label for="%2$s">%4$s</label>
						<input type="password" value="%6$s" id="%2$s">%7$s
					</div>',
					esc_attr( 'username_' . $service ),
					esc_attr( 'api_key_' . $service ),
					__( 'Username', 'bloom' ),
					__( 'API key', 'bloom' ),
					( '' !== $field_values && isset( $field_values['username'] ) ) ? esc_html( $field_values['username'] ) : '',
					( '' !== $field_values && isset( $field_values['api_key'] ) ) ? esc_html( $field_values['api_key'] ) : '',
					ET_Bloom::generate_hint( sprintf(
						'<a href="http://www.elegantthemes.com/plugins/bloom/documentation/accounts/" target="_blank">%1$s</a>',
						__( 'Click here for more information', 'bloom' )
						), false
					)
				);

			break;

			case 'mailchimp' :
			case 'constant_contact' :
			case 'getresponse' :
			case 'sendinblue' :
			case 'campaign_monitor' :
			case 'feedblitz' :

				$form_fields .= sprintf( '
					<div class="et_dashboard_account_row">
						<label for="%1$s">%2$s</label>
						<input type="password" value="%3$s" id="%1$s">%4$s
					</div>',
					esc_attr( 'api_key_' .  $service ),
					__( 'API key', 'bloom' ),
					( '' !== $field_values && isset( $field_values['api_key'] ) ) ? esc_attr( $field_values['api_key'] ) : '',
					ET_Bloom::generate_hint( sprintf(
						'<a href="http://www.elegantthemes.com/plugins/bloom/documentation/accounts/" target="_blank">%1$s</a>',
						__( 'Click here for more information', 'bloom' )
						), false
					)
				);

				$form_fields .= ( 'constant_contact' == $service ) ?
					sprintf(
						'<div class="et_dashboard_account_row">
							<label for="%1$s">%2$s</label>
							<input type="password" value="%3$s" id="%1$s">%4$s
						</div>',
						esc_attr( 'token_' . $service ),
						__( 'Token', 'bloom' ),
						( '' !== $field_values && isset( $field_values['token'] ) ) ? esc_attr( $field_values['token'] ) : '',
						ET_Bloom::generate_hint( sprintf(
							'<a href="http://www.elegantthemes.com/plugins/bloom/documentation/accounts/" target="_blank">%1$s</a>',
							__( 'Click here for more information', 'bloom' )
						), false )
					)
					: '';

			break;

			case 'aweber' :
				$app_id = 'e233dabd';
				$aweber_auth_endpoint = 'https://auth.aweber.com/1.0/oauth/authorize_app/' . $app_id;

				$form_fields .= sprintf( '
					<div class="et_dashboard_account_row et_dashboard_aweber_row">%1$s%2$s</div>',
					sprintf(
						__( 'Step 1: <a href="%1$s" target="_blank">Generate authorization code</a><br/>', 'bloom' ),
						esc_url( $aweber_auth_endpoint )
					),
					sprintf( '
						%2$s
						<input type="password" value="%3$s" id="%1$s">',
						esc_attr( 'api_key_' . $service ),
						__( 'Step 2: Paste in the authorization code and click "Authorize" button: ', 'bloom' ),
						( '' !== $field_values && isset( $field_values['api_key'] ) )
							? esc_attr( $field_values['api_key'] )
							: ''
					)
				);
			break;

			case 'icontact' :
				$form_fields .= sprintf('
					<div class="et_dashboard_account_row">%1$s</div>',
					sprintf( '
						<div class="et_dashboard_account_row">
							<label for="%1$s">%4$s</label>
							<input type="password" value="%7$s" id="%1$s">%10$s
						</div>
						<div class="et_dashboard_account_row">
							<label for="%2$s">%5$s</label>
							<input type="password" value="%8$s" id="%2$s">%10$s
						</div>
						<div class="et_dashboard_account_row">
							<label for="%3$s">%6$s</label>
							<input type="password" value="%9$s" id="%3$s">%10$s
						</div>',
						esc_attr( 'client_id_' . $service ),
						esc_attr( 'username_' .  $service ),
						esc_attr( 'password_' . $service ),
						__( 'App ID', 'bloom' ),
						__( 'Username', 'bloom' ),
						__( 'Password', 'bloom' ),
						( '' !== $field_values && isset( $field_values['client_id'] ) ) ? esc_html( $field_values['client_id'] ) : '',
						( '' !== $field_values && isset( $field_values['username'] ) ) ? esc_html( $field_values['username'] ) : '',
						( '' !== $field_values && isset( $field_values['password'] ) ) ? esc_html( $field_values['password'] ) : '',
						ET_Bloom::generate_hint( sprintf(
							'<a href="http://www.elegantthemes.com/plugins/bloom/documentation/accounts/" target="_blank">%1$s</a>',
							__( 'Click here for more information', 'bloom' )
						), false )
					)
				);
			break;

			case 'ontraport' :
				$form_fields .= sprintf('
					<div class="et_dashboard_account_row">
						<label for="%1$s">%3$s</label>
						<input type="password" value="%5$s" id="%1$s">%7$s
					</div>
					<div class="et_dashboard_account_row">
						<label for="%2$s">%4$s</label>
						<input type="password" value="%6$s" id="%2$s">%7$s
					</div>',
					esc_attr( 'api_key_' . $service ),
					esc_attr( 'client_id_' . $service ),
					__( 'API key', 'bloom' ),
					__( 'APP ID', 'bloom' ),
					( '' !== $field_values && isset( $field_values['api_key'] ) ) ? esc_attr( $field_values['api_key'] ) : '',
					( '' !== $field_values && isset( $field_values['client_id'] ) ) ? esc_attr( $field_values['client_id'] ) : '',
					ET_Bloom::generate_hint( sprintf(
						'<a href="http://www.elegantthemes.com/plugins/bloom/documentation/accounts/" target="_blank">%1$s</a>',
						__( 'Click here for more information', 'bloom' )
					), false )
				);
			break;

			case 'infusionsoft' :
				$form_fields .= sprintf( '
					<div class="et_dashboard_account_row">
						<label for="%1$s">%3$s</label>
						<input type="password" value="%5$s" id="%1$s">%7$s
					</div>
					<div class="et_dashboard_account_row">
						<label for="%2$s">%4$s</label>
						<input type="password" value="%6$s" id="%2$s">%7$s
					</div>',
					esc_attr( 'api_key_' . $service ),
					esc_attr( 'client_id_' . $service ),
					__( 'API Key', 'bloom' ),
					__( 'Application name', 'bloom' ),
					( '' !== $field_values && isset( $field_values['api_key'] ) ) ? esc_attr( $field_values['api_key'] ) : '',
					( '' !== $field_values && isset( $field_values['client_id'] ) ) ? esc_attr( $field_values['client_id'] ) : '',
					ET_Bloom::generate_hint( sprintf(
						'<a href="http://www.elegantthemes.com/plugins/bloom/documentation/accounts/" target="_blank">%1$s</a>',
						__( 'Click here for more information', 'bloom' )
					), false )
				);
			break;
		}

		$form_fields .= '</div>';

		return $form_fields;
	}

	/**
	 * Retrieves lists for specific account from Plugin options.
	 * @return string
	 */
	function retrieve_accounts_list( $service, $accounts_list = array() ) {
		$options_array = ET_Bloom::get_bloom_options();
		if ( isset( $options_array['accounts'] ) ) {
			if ( isset( $options_array['accounts'][$service] ) ) {
				foreach ( $options_array['accounts'][$service] as $account_name => $details ) {
					$accounts_list[$account_name] = $account_name;
				}
			}
		}

		return $accounts_list;
	}

	/**
	 * Generates the list of "Lists" for selected account in the Dashboard. Returns the generated form to jQuery.
	 */
	function generate_mailing_lists( $service = '', $account_name = '' ) {
		wp_verify_nonce( $_POST['retrieve_lists_nonce'] , 'retrieve_lists' );
		$account_for = ! empty( $_POST['bloom_account_name'] ) ? sanitize_text_field( $_POST['bloom_account_name'] ) : '';
		$service = ! empty( $_POST['bloom_service'] ) ? sanitize_text_field( $_POST['bloom_service'] ) : '';
		$optin_id = ! empty( $_POST['bloom_optin_id'] ) ? sanitize_text_field( $_POST['bloom_optin_id'] ) : '';

		$options_array = ET_Bloom::get_bloom_options();
		$current_email_list = isset( $options_array[$optin_id] ) ? $options_array[$optin_id]['email_list'] : 'empty';

		$available_lists = array();

		if ( isset( $options_array['accounts'] ) ) {
			if ( isset( $options_array['accounts'][$service] ) ) {
				foreach ( $options_array['accounts'][$service] as $account_name => $details ) {
					if ( $account_for == $account_name ) {
						if ( isset( $details['lists'] ) ) {
							$available_lists = $details['lists'];
						}
					}
				}
			}
		}

		printf( '
			<li class="select et_dashboard_select_list">
				<p>%1$s</p>
				<select name="et_dashboard[email_list]">
					<option value="empty" %3$s>%2$s</option>',
			__( 'Select Email List', 'bloom' ),
			__( 'Select One...', 'bloom' ),
			selected( 'empty', $current_email_list, false )
		);

		if ( ! empty( $available_lists ) ) {
			foreach ( $available_lists as $list_id => $list_details ) {
				printf( '<option value="%1$s" %3$s>%2$s</option>',
					esc_attr( $list_id ),
					esc_html( $list_details['name'] ),
					selected( $list_id, $current_email_list, false )
				);
			}
		}

		printf( '
				</select>
			</li>' );

		die();
	}


/**-------------------------**/
/** 		Front end		**/
/**-------------------------**/

	function load_scripts_styles() {
		wp_enqueue_script( 'et_bloom-uniform-js', ET_BLOOM_PLUGIN_URI . '/js/jquery.uniform.min.js', array( 'jquery' ), $this->plugin_version, true );
		wp_enqueue_script( 'et_bloom-custom-js', ET_BLOOM_PLUGIN_URI . '/js/custom.js', array( 'jquery' ), $this->plugin_version, true );
		wp_enqueue_script( 'et_bloom-idle-timer-js', ET_BLOOM_PLUGIN_URI . '/js/idle-timer.min.js', array( 'jquery' ), $this->plugin_version, true );
		wp_enqueue_style( 'et_bloom-open-sans', esc_url_raw( "{$this->protocol}://fonts.googleapis.com/css?family=Open+Sans:400,700" ), array(), null );
		wp_enqueue_style( 'et_bloom-css', ET_BLOOM_PLUGIN_URI . '/css/style.css', array(), $this->plugin_version );
		wp_localize_script( 'et_bloom-custom-js', 'bloomSettings', array(
			'ajaxurl'         => admin_url( 'admin-ajax.php', $this->protocol ),
			'pageurl'         => ( is_singular( get_post_types() ) ? get_permalink() : '' ),
			'stats_nonce'     => wp_create_nonce( 'update_stats' ),
			'subscribe_nonce' => wp_create_nonce( 'subscribe' ),
		) );
	}

	/**
	 * Generates the array of all taxonomies supported by Bloom.
	 * Bloom fully supports only taxonomies from ET themes.
	 * @return array
	 */
	function get_supported_taxonomies( $post_types ) {
		$taxonomies = array();

		if ( ! empty( $post_types ) ) {
			foreach( $post_types as $single_type ) {
				if ( 'post' != $single_type ) {
					$taxonomies[] = $this->get_tax_slug( $single_type );
				}
			}
		}

		return $taxonomies;
	}

	/**
	 * Returns the slug for supported taxonomy based on post type.
	 * Returns empty string if taxonomy is not supported
	 * Bloom fully supports only taxonomies from ET themes.
	 * @return string
	 */
	function get_tax_slug( $post_type ) {
		$theme_name = wp_get_theme();
		$taxonomy = '';

		switch ( $post_type ) {
			case 'project' :
				$taxonomy = 'project_category';

			break;

			case 'product' :
				$taxonomy = 'product_cat';

				break;

			case 'listing' :
				if ( 'Explorable' == $theme_name ) {
					$taxonomy = 'listing_type';
				} else {
					$taxonomy = 'listing_category';
				}

				break;

			case 'event' :
				$taxonomy = 'event_category';

				break;

			case 'gallery' :
				$taxonomy = 'gallery_category';

				break;

			case 'post' :
				$taxonomy = 'category';

				break;
		}

		return $taxonomy;
	}

	/**
	 * Returns true if form should be displayed on particular page depending on user settings.
	 * @return bool
	 */
	function check_applicability( $optin_id ) {
		$options_array = ET_Bloom::get_bloom_options();

		$display_there = false;

		$optin_type = $options_array[$optin_id]['optin_type'];

		$current_optin_limits = array(
			'post_types'        => $options_array[$optin_id]['post_types'],
			'categories'        => $options_array[$optin_id]['post_categories'],
			'on_cat_select'     => isset( $options_array[$optin_id]['display_on'] ) && in_array( 'category', $options_array[$optin_id]['display_on'] ) ? true : false,
			'pages_exclude'     => is_array( $options_array[$optin_id]['pages_exclude'] ) ? $options_array[$optin_id]['pages_exclude'] : explode( ',', $options_array[$optin_id]['pages_exclude'] ),
			'pages_include'     => is_array( $options_array[$optin_id]['pages_include'] ) ? $options_array[$optin_id]['pages_include'] : explode( ',', $options_array[$optin_id]['pages_include'] ),
			'posts_exclude'     => is_array( $options_array[$optin_id]['posts_exclude'] ) ? $options_array[$optin_id]['posts_exclude'] : explode( ',', $options_array[$optin_id]['posts_exclude'] ),
			'posts_include'     => is_array( $options_array[$optin_id]['posts_include'] ) ? $options_array[$optin_id]['posts_include'] : explode( ',', $options_array[$optin_id]['posts_include'] ),
			'on_tag_select'     => isset( $options_array[$optin_id]['display_on'] ) && in_array( 'tags', $options_array[$optin_id]['display_on'] )
				? true
				: false,
			'on_archive_select' => isset( $options_array[$optin_id]['display_on'] ) && in_array( 'archive', $options_array[$optin_id]['display_on'] )
				? true
				: false,
			'homepage_select'   => isset( $options_array[$optin_id]['display_on'] ) && in_array( 'home', $options_array[$optin_id]['display_on'] )
				? true
				: false,
			'everything_select' => isset( $options_array[$optin_id]['display_on'] ) && in_array( 'everything', $options_array[$optin_id]['display_on'] )
				? true
				: false,
			'auto_select'       => isset( $options_array[$optin_id]['post_categories']['auto_select'] )
				? $options_array[$optin_id]['post_categories']['auto_select']
				: false,
			'previously_saved'  => isset( $options_array[$optin_id]['post_categories']['previously_saved'] )
				? explode( ',', $options_array[$optin_id]['post_categories']['previously_saved'] )
				: false,
		);

		unset( $current_optin_limits['categories']['previously_saved'] );

		$tax_to_check = $this->get_supported_taxonomies( $current_optin_limits['post_types'] );

		if ( ( 'flyin' == $optin_type || 'pop_up' == $optin_type ) && true == $current_optin_limits['everything_select'] ) {
			if ( is_singular() ) {
				if ( ( is_singular( 'page' ) && ! in_array( get_the_ID(), $current_optin_limits['pages_exclude'] ) ) || ( ! is_singular( 'page' ) && ! in_array( get_the_ID(), $current_optin_limits['posts_exclude'] ) ) ) {
					$display_there = true;
				}
			} else {
				$display_there = true;
			}
		} else {
			if ( is_archive() && ( 'flyin' == $optin_type || 'pop_up' == $optin_type ) ) {
				if ( true == $current_optin_limits['on_archive_select'] ) {
					$display_there = true;
				} else {
					if ( ( ( is_category( $current_optin_limits['categories'] ) || ( ! empty( $tax_to_check ) && is_tax( $tax_to_check, $current_optin_limits['categories'] ) ) ) && true == $current_optin_limits['on_cat_select'] ) || ( is_tag() && true == $current_optin_limits['on_tag_select'] ) ) {
						$display_there = true;
					}
				}
			} else {
				$page_id = ( is_front_page() && !is_page() ) ? 'homepage' : get_the_ID();
				$current_post_type = 'homepage' == $page_id ? 'home' : get_post_type( $page_id );

				if ( is_singular() || ( 'home' == $current_post_type && ( 'flyin' == $optin_type || 'pop_up' == $optin_type ) ) ) {
					if ( in_array( $page_id, $current_optin_limits['pages_include'] ) || in_array( (int) $page_id, $current_optin_limits['posts_include'] ) ) {
						$display_there = true;
					}

					if ( true == $current_optin_limits['homepage_select'] && is_front_page() ) {
						$display_there = true;
					}
				}

				if ( ! empty( $current_optin_limits['post_types'] ) && is_singular( $current_optin_limits['post_types'] ) ) {

					switch ( $current_post_type ) {
						case 'page' :
						case 'home' :
							if ( ( 'home' == $current_post_type && ( 'flyin' == $optin_type || 'pop_up' == $optin_type ) ) || 'home' != $current_post_type ) {
								if ( ! in_array( $page_id, $current_optin_limits['pages_exclude'] ) ) {
									$display_there = true;
								}
							}
							break;

						default :
							$taxonomy_slug = $this->get_tax_slug( $current_post_type );

							if ( ! in_array( $page_id, $current_optin_limits['posts_exclude'] ) ) {
								if ( '' != $taxonomy_slug ) {
									$categories = get_the_terms( $page_id, $taxonomy_slug );
									$post_cats = array();
									if ( $categories ) {
										foreach ( $categories as $category ) {
											$post_cats[] = $category->term_id;
										}
									}

									foreach ( $post_cats as $single_cat ) {
										if ( in_array( $single_cat, $current_optin_limits['categories'] ) ) {
											$display_there = true;
										}
									}

									if ( false === $display_there && 1 == $current_optin_limits['auto_select'] ) {
										foreach ( $post_cats as $single_cat ) {
											if ( ! in_array( $single_cat, $current_optin_limits['previously_saved'] ) ) {
												$display_there = true;
											}
										}
									}
								} else {
									$display_there = true;
								}
							}

							break;
					}
				}
			}
		}

		return $display_there;
	}

	/**
	 * Calculates and returns the ID of optin which should be displayed if A/B testing is enabled
	 * @return string
	 */
	public static function choose_form_ab_test( $optin_id, $optins_set, $update_option = true ) {
		$chosen_form = $optin_id;

		if( ! empty( $optins_set[$optin_id]['child_optins'] ) && 'active' == $optins_set[$optin_id]['test_status'] ) {
			$chosen_form = ( '-1' != $optins_set[$optin_id]['next_optin'] || empty( $optins_set[$optin_id]['next_optin'] ) )
				? $optins_set[$optin_id]['next_optin']
				: $optin_id;

			if ( '-1' == $optins_set[$optin_id]['next_optin'] ) {
				$next_optin = $optins_set[$optin_id]['child_optins'][0];
			} else {
				$child_forms_count = count( $optins_set[$optin_id]['child_optins'] );

				for ( $i = 0; $i < $child_forms_count; $i++ ) {
					if ( $optins_set[$optin_id]['next_optin'] == $optins_set[$optin_id]['child_optins'][$i] ) {
						$current_optin_number = $i;
					}
				}

				if ( ( $child_forms_count - 1 ) == $current_optin_number ) {
					$next_optin = '-1';
				} else {
					$next_optin = $optins_set[$optin_id]['child_optins'][$current_optin_number + 1];
				}

			}
			if ( true === $update_option ) {
				$update_test_optin[$optin_id] = $optins_set[$optin_id];
				$update_test_optin[$optin_id]['next_optin'] = $next_optin;
				ET_Bloom::update_bloom_options( $update_test_optin );
			}
		}

		return $chosen_form;
	}

	/**
	 * Handles the stats adding request via jQuery
	 * @return void
	 */
	function handle_stats_adding() {
		wp_verify_nonce( $_POST['update_stats_nonce'] , 'update_stats' );
		$stats_data_json = str_replace( '\\', '' ,  $_POST[ 'stats_data_array' ] );
		$stats_data_array = json_decode( $stats_data_json, true );

		ET_Bloom::add_stats_record( $stats_data_array['type'], $stats_data_array['optin_id'], $stats_data_array['page_id'], $stats_data_array['list_id'] );

		die();

	}

	/**
	 * Adds the record to stats table. Either conversion or impression for specific list on specific form on specific page.
	 * @return void
	 */
	public static function add_stats_record( $type, $optin_id, $page_id, $list_id ) {
		global $wpdb;

		$row_added = false;

		$table_name = $wpdb->prefix . 'et_bloom_stats';

		$record_date = current_time( 'mysql' );
		$ip_address  = $_SERVER[ 'REMOTE_ADDR' ];

		$wpdb->insert(
			$table_name,
			array(
				'record_date'  => sanitize_text_field( $record_date ),
				'optin_id'     => sanitize_text_field( $optin_id ),
				'record_type'  => sanitize_text_field( $type ),
				'page_id'      => (int) $page_id,
				'list_id'      => sanitize_text_field( $list_id ),
				'ip_address'   => sanitize_text_field( $ip_address ),
				'removed_flag' => (int) 0,
			),
			array(
				'%s', // record_date
				'%s', // optin_id
				'%s', // record_type
				'%d', // page_id
				'%s', // list_id
				'%s', // ip_address
				'%d', // removed_flag
			)
		);

		$row_added = true;

	return $row_added;
	}

	// add marker at the bottom of the_content() for the "Trigger at bottom of post" option.
	function trigger_bottom_mark( $content ) {
		$content .= '<span class="et_bloom_bottom_trigger"></span>';
		return $content;
	}

	/**
	 * Generates the content for the optin.
	 * @return string
	 */
	public static function generate_form_content( $optin_id, $page_id, $details = array() ) {
		if ( empty( $details ) ) {
			$all_optins = ET_Bloom::get_bloom_options();
			$details = $all_optins[$optin_id];
		}

		$hide_img_mobile_class = isset( $details['hide_mobile'] ) && '1' == $details['hide_mobile'] ? 'et_bloom_hide_mobile' : '';
		$image_animation_class = isset( $details['image_animation'] )
			? esc_attr( ' et_bloom_image_' .  $details['image_animation'] )
			: 'et_bloom_image_no_animation';
		$image_class = $hide_img_mobile_class . $image_animation_class . ' et_bloom_image';

		// Translate all strings if WPML is enabled
		if ( function_exists ( 'icl_translate' ) ) {
			$optin_title      = icl_translate( 'bloom', 'optin_title_' . $optin_id, $details['optin_title'] );
			$optin_message    = icl_translate( 'bloom', 'optin_message_' . $optin_id, $details['optin_message'] );
			$email_text       = icl_translate( 'bloom', 'email_text_' . $optin_id, $details['email_text'] );
			$first_name_text  = icl_translate( 'bloom', 'name_text_' . $optin_id, $details['name_text'] );
			$single_name_text = icl_translate( 'bloom', 'single_name_text_' . $optin_id, $details['single_name_text'] );
			$last_name_text   = icl_translate( 'bloom', 'last_name_' . $optin_id, $details['last_name'] );
			$button_text      = icl_translate( 'bloom', 'button_text_' . $optin_id, $details['button_text'] );
			$success_text     = icl_translate( 'bloom', 'success_message_' . $optin_id, $details['success_message'] );
			$footer_text      = icl_translate( 'bloom', 'footer_text_' . $optin_id, $details['footer_text'] );
		} else {
			$optin_title      = $details['optin_title'];
			$optin_message    = $details['optin_message'];
			$email_text       = $details['email_text'];
			$first_name_text  = $details['name_text'];
			$single_name_text = $details['single_name_text'];
			$last_name_text   = $details['last_name'];
			$button_text      = $details['button_text'];
			$success_text     = $details['success_message'];
			$footer_text      = $details['footer_text'];
		}

		$formatted_title = '&lt;h2&gt;&nbsp;&lt;/h2&gt;' != $details['optin_title']
			? str_replace( '&nbsp;', '', $optin_title )
			: '';
		$formatted_message = '' != $details['optin_message'] ? $optin_message : '';
		$formatted_footer = '' != $details['footer_text']
			? sprintf(
				'<div class="et_bloom_form_footer">
					<p>%1$s</p>
				</div>',
				stripslashes( esc_html( $footer_text ) )
			)
			: '';

		$is_single_name = ( isset( $details['display_name'] ) && '1' == $details['display_name'] ) ? false : true;

		$output = sprintf( '
			<div class="et_bloom_form_container_wrapper clearfix">
				<div class="et_bloom_header_outer">
					<div class="et_bloom_form_header%1$s%13$s">
						%2$s
						%3$s
						%4$s
					</div>
				</div>
				<div class="et_bloom_form_content%5$s%6$s%7$s%12$s"%11$s>
					%8$s
					<div class="et_bloom_success_container">
						<span class="et_bloom_success_checkmark"></span>
					</div>
					<h2 class="et_bloom_success_message">%9$s</h2>
					%10$s
				</div>
			</div>
			<span class="et_bloom_close_button"></span>',
			( 'right' == $details['image_orientation'] || 'left' == $details['image_orientation'] ) && 'widget' !== $details['optin_type']
				? sprintf( ' split%1$s', 'right' == $details['image_orientation']
					? ' image_right'
					: '' )
				: '',
			( ( 'above' == $details['image_orientation'] || 'right' == $details['image_orientation'] || 'left' == $details['image_orientation'] ) && 'widget' !== $details['optin_type'] ) || ( 'above' == $details['image_orientation_widget'] && 'widget' == $details['optin_type'] )
				? sprintf(
					'%1$s',
					empty( $details['image_url']['id'] )
						? sprintf(
							'<img src="%1$s" alt="%2$s" %3$s>',
							esc_attr( $details['image_url']['url'] ),
							esc_attr( wp_strip_all_tags( html_entity_decode( $formatted_title ) ) ),
							'' !== $image_class
								? sprintf( 'class="%1$s"', esc_attr( $image_class ) )
								: ''
						)
						: wp_get_attachment_image( $details['image_url']['id'], 'bloom_image', false, array( 'class' => $image_class ) )
				)
				: '',
			( '' !== $formatted_title || '' !== $formatted_message )
				? sprintf(
					'<div class="et_bloom_form_text">
						%1$s%2$s
					</div>',
					stripslashes( html_entity_decode( $formatted_title, ENT_QUOTES, 'UTF-8' ) ),
					stripslashes( html_entity_decode( $formatted_message, ENT_QUOTES, 'UTF-8' ) )
				)
				: '',
			( 'below' == $details['image_orientation'] && 'widget' !== $details['optin_type'] ) || ( isset( $details['image_orientation_widget'] ) && 'below' == $details['image_orientation_widget'] && 'widget' == $details['optin_type'] )
				? sprintf(
					'%1$s',
					empty( $details['image_url']['id'] )
						? sprintf(
							'<img src="%1$s" alt="%2$s" %3$s>',
							esc_attr( $details['image_url']['url'] ),
							esc_attr( wp_strip_all_tags( html_entity_decode( $formatted_title ) ) ),
							'' !== $image_class ? sprintf( 'class="%1$s"', esc_attr( $image_class ) ) : ''
						)
						: wp_get_attachment_image( $details['image_url']['id'], 'bloom_image', false, array( 'class' => $image_class ) )
					)
				: '', //#5
			( 'no_name' == $details['name_fields'] && ! ET_Bloom::is_only_name_support( $details['email_provider'] ) ) || ( ET_Bloom::is_only_name_support( $details['email_provider'] ) && $is_single_name )
				? ' et_bloom_1_field'
				: sprintf(
					' et_bloom_%1$s_fields',
					'first_last_name' == $details['name_fields'] && ! ET_Bloom::is_only_name_support( $details['email_provider'] )
						? '3'
						: '2'
				),
			'inline' == $details['field_orientation'] && 'bottom' == $details['form_orientation'] && 'widget' !== $details['optin_type']
				? ' et_bloom_bottom_inline'
				: '',
			( 'stacked' == $details['field_orientation'] && 'bottom' == $details['form_orientation'] ) || 'widget' == $details['optin_type']
				? ' et_bloom_bottom_stacked'
				: '',
			'custom_html' == $details['email_provider']
				? stripslashes( html_entity_decode( $details['custom_html'] ) )
				: sprintf( '
					%1$s
					<form method="post" class="clearfix">
						%3$s
						<p class="et_bloom_popup_input et_bloom_subscribe_email">
							<input placeholder="%2$s">
						</p>
						<button data-optin_id="%4$s" data-service="%5$s" data-list_id="%6$s" data-page_id="%7$s" data-account="%8$s" class="et_bloom_submit_subscription">
							<span class="et_bloom_subscribe_loader"></span>
							<span class="et_bloom_button_text et_bloom_button_text_color_%10$s">%9$s</span>
						</button>
					</form>',
					'basic_edge' == $details['edge_style'] || '' == $details['edge_style']
						? ''
						: ET_Bloom::get_the_edge_code( $details['edge_style'], 'widget' == $details['optin_type'] ? 'bottom' : $details['form_orientation'] ),
					'' != $email_text ? stripslashes( esc_attr( $email_text ) ) : esc_html__( 'Email', 'bloom' ),
					( 'no_name' == $details['name_fields'] && ! ET_Bloom::is_only_name_support( $details['email_provider'] ) ) || ( ET_Bloom::is_only_name_support( $details['email_provider'] ) && $is_single_name )
						? ''
						: sprintf(
							'<p class="et_bloom_popup_input et_bloom_subscribe_name">
								<input placeholder="%1$s%2$s" maxlength="50">
							</p>%3$s',
							'first_last_name' == $details['name_fields']
								? sprintf(
									'%1$s',
									'' != $first_name_text
										? stripslashes( esc_attr( $first_name_text ) )
										: esc_html__( 'First Name', 'bloom' )
								)
								: '',
							( 'first_last_name' != $details['name_fields'] )
								? sprintf( '%1$s', '' != $single_name_text
									? stripslashes( esc_attr( $single_name_text ) )
									: esc_html__( 'Name', 'bloom' ) ) : '',
							'first_last_name' == $details['name_fields'] && ! ET_Bloom::is_only_name_support( $details['email_provider'] )
								? sprintf( '
									<p class="et_bloom_popup_input et_bloom_subscribe_last">
										<input placeholder="%1$s" maxlength="50">
									</p>',
									'' != $last_name_text ? stripslashes( esc_attr( $last_name_text ) ) : esc_html__( 'Last Name', 'bloom' )
								)
								: ''
						),
					esc_attr( $optin_id ),
					esc_attr( $details['email_provider'] ), //#5
					esc_attr( $details['email_list'] ),
					esc_attr( $page_id ),
					esc_attr( $details['account_name'] ),
					'' != $button_text ? stripslashes( esc_html( $button_text ) ) :  esc_html__( 'SUBSCRIBE!', 'bloom' ),
					isset( $details['button_text_color'] ) ? esc_attr( $details['button_text_color'] ) : '' // #10
				), //#9
			'' != $success_text
				? stripslashes( esc_html( $success_text ) )
				: esc_html__( 'You have Successfully Subscribed!', 'bloom' ), //#10
			$formatted_footer,
			'custom_html' == $details['email_provider']
				? sprintf(
					' data-optin_id="%1$s" data-service="%2$s" data-list_id="%3$s" data-page_id="%4$s" data-account="%5$s"',
					esc_attr( $optin_id ),
					'custom_form',
					'custom_form',
					esc_attr( $page_id ),
					'custom_form'
				)
				: '',
			'custom_html' == $details['email_provider'] ? ' et_bloom_custom_html_form' : '',
			isset( $details['header_text_color'] )
				? sprintf(
					' et_bloom_header_text_%1$s',
					esc_attr( $details['header_text_color'] )
				)
				: ' et_bloom_header_text_dark' //#14
		);

		return $output;
	}

	/**
	 * Checks whether network supports only First Name
	 * @return string
	 */
	public static function is_only_name_support( $service ) {
		$single_name_networks = array(
			'aweber',
			'getresponse'
		);
		$result = in_array( $service, $single_name_networks );

		return $result;
	}

	/**
	 * Generates the svg code for edges
	 * @return bool
	 */
	public static function get_the_edge_code( $style, $orientation ) {
		$output = '';
		switch ( $style ) {
			case 'wedge_edge' :
				$output = sprintf(
					'<svg class="triangle et_bloom_default_edge" xmlns="http://www.w3.org/2000/svg" version="1.1" width="%2$s" height="%3$s" viewBox="0 0 100 100" preserveAspectRatio="none">
						<path d="%1$s" fill=""></path>
					</svg>',
					'bottom' == $orientation ? 'M0 0 L50 100 L100 0 Z' : 'M0 0 L0 100 L100 50 Z',
					'bottom' == $orientation ? '100%' : '20',
					'bottom' == $orientation ? '20' : '100%'
				);

				//if right or left orientation selected we still need to generate bottom edge to support responsive design
				if ( 'bottom' !== $orientation ) {
					$output .= sprintf(
						'<svg class="triangle et_bloom_responsive_edge" xmlns="http://www.w3.org/2000/svg" version="1.1" width="%2$s" height="%3$s" viewBox="0 0 100 100" preserveAspectRatio="none">
							<path d="%1$s" fill=""></path>
						</svg>',
						'M0 0 L50 100 L100 0 Z',
						'100%',
						'20'
					);
				}

				break;
			case 'curve_edge' :
				$output = sprintf(
					'<svg class="curve et_bloom_default_edge" xmlns="http://www.w3.org/2000/svg" version="1.1" width="%2$s" height="%3$s" viewBox="0 0 100 100" preserveAspectRatio="none">
						<path d="%1$s"></path>
					</svg>',
					'bottom' == $orientation ? 'M0 0 C40 100 60 100 100 0 Z' : 'M0 0 C0 0 100 50 0 100 z',
					'bottom' == $orientation ? '100%' : '20',
					'bottom' == $orientation ? '20' : '100%'
				);

				//if right or left orientation selected we still need to generate bottom edge to support responsive design
				if ( 'bottom' !== $orientation ) {
					$output .= sprintf(
						'<svg class="curve et_bloom_responsive_edge" xmlns="http://www.w3.org/2000/svg" version="1.1" width="%2$s" height="%3$s" viewBox="0 0 100 100" preserveAspectRatio="none">
							<path d="%1$s"></path>
						</svg>',
						'M0 0 C40 100 60 100 100 0 Z',
						'100%',
						'20'
					);
				}

				break;
		}

		return $output;
	}

	/**
	 * Displays the Flyin content on front-end.
	 */
	function display_flyin() {
		$optins_set = $this->flyin_optins;

		if ( ! empty( $optins_set ) ) {
			foreach( $optins_set as $optin_id => $details ) {
				if ( $this->check_applicability( $optin_id ) ) {
					$display_optin_id = ET_Bloom::choose_form_ab_test( $optin_id, $optins_set );

					if ( $display_optin_id != $optin_id ) {
						$all_optins = ET_Bloom::get_bloom_options();
						$optin_id = $display_optin_id;
						$details = $all_optins[$optin_id];
					}

					if ( is_singular() || is_front_page() ) {
						$page_id = is_front_page() ? -1 : get_the_ID();
					} else {
						$page_id = 0;
					}

					printf(
						'<div class="et_bloom_flyin et_bloom_optin et_bloom_resize et_bloom_flyin_%6$s et_bloom_%5$s%17$s%1$s%2$s%18$s%19$s%20$s%22$s"%3$s%4$s%16$s%21$s>
							<div class="et_bloom_form_container%7$s%8$s%9$s%10$s%12$s%13$s%14$s%15$s%23$s%24$s%25$s">
								%11$s
							</div>
						</div>',
						true == $details['post_bottom'] ? ' et_bloom_trigger_bottom' : '',
						isset( $details['trigger_idle'] ) && true == $details['trigger_idle'] ? ' et_bloom_trigger_idle' : '',
						isset( $details['trigger_auto'] ) && true == $details['trigger_auto']
							? sprintf( ' data-delay="%1$s"', esc_attr( $details['load_delay'] ) )
							: '',
						true == $details['session']
							? ' data-cookie_duration="' . esc_attr( $details['session_duration'] ) . '"'
							: '',
						esc_attr( $optin_id ), // #5
						esc_attr( $details['flyin_orientation'] ),
						'bottom' !== $details['form_orientation'] && 'custom_html' !== $details['email_provider']
							? sprintf(
								' et_bloom_form_%1$s',
								esc_attr( $details['form_orientation'] )
							)
							: ' et_bloom_form_bottom',
						'basic_edge' == $details['edge_style'] || '' == $details['edge_style']
							? ''
							: sprintf( ' with_edge %1$s', esc_attr( $details['edge_style'] ) ),
						( 'no_border' !== $details['border_orientation'] )
							? sprintf(
								' et_bloom_with_border et_bloom_border_%1$s%2$s',
								esc_attr( $details['border_style'] ),
								esc_attr( ' et_bloom_border_position_' . $details['border_orientation'] )
							)
							: '',
						( 'rounded' == $details['corner_style'] ) ? ' et_bloom_rounded_corners' : '', //#10
						ET_Bloom::generate_form_content( $optin_id, $page_id ),
						'bottom' == $details['form_orientation'] && ( 'no_image' == $details['image_orientation'] || 'above' == $details['image_orientation'] || 'below' == $details['image_orientation'] ) && 'stacked' == $details['field_orientation']
							? ' et_bloom_stacked_flyin'
							: '',
						( 'rounded' == $details['field_corner'] ) ? ' et_bloom_rounded' : '',
						'light' == $details['text_color'] ? ' et_bloom_form_text_light' : ' et_bloom_form_text_dark',
						isset( $details['load_animation'] )
							? sprintf(
								' et_bloom_animation_%1$s',
								esc_attr( $details['load_animation'] )
							)
							: ' et_bloom_animation_no_animation', //#15
						isset( $details['trigger_idle'] ) && true == $details['trigger_idle']
							? sprintf( ' data-idle_timeout="%1$s"', esc_attr( $details['idle_timeout'] ) )
							: '',
						isset( $details['trigger_auto'] ) && true == $details['trigger_auto']
							? ' et_bloom_auto_popup'
							: '',
						isset( $details['comment_trigger'] ) && true == $details['comment_trigger']
							? ' et_bloom_after_comment'
							: '',
						isset( $details['purchase_trigger'] ) && true == $details['purchase_trigger']
							? ' et_bloom_after_purchase'
							: '', //#20
						isset( $details['trigger_scroll'] ) && true == $details['trigger_scroll']
							? ' et_bloom_scroll'
							: '',
						isset( $details['trigger_scroll'] ) && true == $details['trigger_scroll']
							? sprintf( ' data-scroll_pos="%1$s"', esc_attr( $details['scroll_pos'] ) )
							: '',
						isset( $details['hide_mobile_optin'] ) && true == $details['hide_mobile_optin']
							? ' et_bloom_hide_mobile_optin'
							: '',
						( 'no_name' == $details['name_fields'] && ! ET_Bloom::is_only_name_support( $details['email_provider'] ) ) || ( ET_Bloom::is_only_name_support( $details['email_provider'] ) && $is_single_name )
							? ' et_flyin_1_field'
							: sprintf(
								' et_flyin_%1$s_fields',
								'first_last_name' == $details['name_fields'] && ! ET_Bloom::is_only_name_support( $details['email_provider'] )
									? '3'
									: '2'
							),
						'inline' == $details['field_orientation'] && 'bottom' == $details['form_orientation']
							? ' et_bloom_flyin_bottom_inline'
							: '', //#25
						'stacked' == $details['field_orientation'] && 'bottom' == $details['form_orientation'] && ( 'right' == $details['image_orientation'] || 'left' == $details['image_orientation'] )
							? ' et_bloom_flyin_bottom_stacked'
							: '' //#26
					);
				}
			}
		}
	}

	/**
	 * Displays the PopUp content on front-end.
	 */
	function display_popup() {
		$optins_set = $this->popup_optins;

		if ( ! empty( $optins_set ) ) {
			foreach( $optins_set as $optin_id => $details ) {
				if ( $this->check_applicability( $optin_id ) ) {
					$display_optin_id = ET_Bloom::choose_form_ab_test( $optin_id, $optins_set );

					if ( $display_optin_id != $optin_id ) {
						$all_optins = ET_Bloom::get_bloom_options();
						$optin_id = $display_optin_id;
						$details = $all_optins[$optin_id];
					}

					if ( is_singular() || is_front_page() ) {
						$page_id = is_front_page() ? -1 : get_the_ID();
					} else {
						$page_id = 0;
					}

					printf(
						'<div class="et_bloom_popup et_bloom_optin et_bloom_resize et_bloom_%5$s%15$s%1$s%2$s%16$s%17$s%18$s%20$s"%3$s%4$s%14$s%19$s>
							<div class="et_bloom_form_container et_bloom_popup_container%6$s%7$s%8$s%9$s%11$s%12$s%13$s">
								%10$s
							</div>
						</div>',
						true == $details['post_bottom'] ? ' et_bloom_trigger_bottom' : '',
						isset( $details['trigger_idle'] ) && true == $details['trigger_idle']
							? ' et_bloom_trigger_idle'
							: '',
						isset( $details['trigger_auto'] ) && true == $details['trigger_auto']
							? sprintf( ' data-delay="%1$s"', esc_attr( $details['load_delay'] ) )
							: '',
						true == $details['session']
							? ' data-cookie_duration="' . esc_attr( $details['session_duration'] ) . '"'
							: '',
						esc_attr( $optin_id ), // #5
						'bottom' !== $details['form_orientation'] && 'custom_html' !== $details['email_provider']
							? sprintf( ' et_bloom_form_%1$s',  esc_attr( $details['form_orientation'] ) )
							: ' et_bloom_form_bottom',
						'basic_edge' == $details['edge_style'] || '' == $details['edge_style']
							? ''
							: sprintf( ' with_edge %1$s', esc_attr( $details['edge_style'] ) ),
						( 'no_border' !== $details['border_orientation'] )
							? sprintf(
								' et_bloom_with_border et_bloom_border_%1$s%2$s',
								esc_attr( $details['border_style'] ),
								esc_attr( ' et_bloom_border_position_' . $details['border_orientation'] )
							)
							: '',
						( 'rounded' == $details['corner_style'] ) ? ' et_bloom_rounded_corners' : '',
						ET_Bloom::generate_form_content( $optin_id, $page_id ), //#10
						( 'rounded' == $details['field_corner'] ) ? ' et_bloom_rounded' : '',
						'light' == $details['text_color'] ? ' et_bloom_form_text_light' : ' et_bloom_form_text_dark',
						isset( $details['load_animation'] )
							? sprintf( ' et_bloom_animation_%1$s', esc_attr( $details['load_animation'] ) )
							: ' et_bloom_animation_no_animation',
						isset( $details['trigger_idle'] ) && true == $details['trigger_idle']
							? sprintf( ' data-idle_timeout="%1$s"', esc_attr( $details['idle_timeout'] ) )
							: '',
						isset( $details['trigger_auto'] ) && true == $details['trigger_auto'] ? ' et_bloom_auto_popup' : '', //#15
						isset( $details['comment_trigger'] ) && true == $details['comment_trigger'] ? ' et_bloom_after_comment' : '',
						isset( $details['purchase_trigger'] ) && true == $details['purchase_trigger'] ? ' et_bloom_after_purchase' : '',
						isset( $details['trigger_scroll'] ) && true == $details['trigger_scroll'] ? ' et_bloom_scroll' : '',
						isset( $details['trigger_scroll'] ) && true == $details['trigger_scroll']
							? sprintf( ' data-scroll_pos="%1$s"', esc_attr( $details['scroll_pos'] ) )
							: '',
						( isset( $details['hide_mobile_optin'] ) && true == $details['hide_mobile_optin'] )
							? ' et_bloom_hide_mobile_optin'
							: '' //#20
					);
				}
			}
		}
	}

	function display_preview() {
		wp_verify_nonce( $_POST['bloom_preview_nonce'] , 'bloom_preview' );

		$options = $_POST['preview_options'];
		$processed_string = str_replace( array( '%5B', '%5D' ), array( '[', ']' ), $options );
		parse_str( $processed_string, $processed_array );
		$details = $processed_array['et_dashboard'];
		$fonts_array = array();

		if ( ! isset( $fonts_array[$details['header_font']] ) && isset( $details['header_font'] ) ) {
			$fonts_array[] = $details['header_font'];
		}
		if ( ! isset( $fonts_array[$details['body_font']] ) && isset( $details['body_font'] ) ) {
			$fonts_array[] = $details['body_font'];
		}

		$popup_array['popup_code'] = $this->generate_preview_popup( $details );
		$popup_array['popup_css'] = '<style id="et_bloom_preview_css">' . ET_Bloom::generate_custom_css( '.et_bloom .et_bloom_preview_popup', $details ) . '</style>';
		$popup_array['fonts'] = $fonts_array;

		die( json_encode( $popup_array ) );
	}

	/**
	 * Displays the PopUp preview in dashboard.
	 */
	function generate_preview_popup( $details ) {
		$output = '';
		$output = sprintf(
			'<div class="et_bloom_popup et_bloom_animated et_bloom_preview_popup et_bloom_optin">
				<div class="et_bloom_form_container et_bloom_animation_fadein et_bloom_popup_container%1$s%2$s%3$s%4$s%5$s%6$s">
					%7$s
				</div>
			</div>',
			'bottom' !== $details['form_orientation'] && 'custom_html' !== $details['email_provider'] && 'widget' !== $details['optin_type']
				? sprintf( ' et_bloom_form_%1$s',  esc_attr( $details['form_orientation'] ) )
				: ' et_bloom_form_bottom',
			'basic_edge' == $details['edge_style'] || '' == $details['edge_style']
				? ''
				: sprintf( ' with_edge %1$s', esc_attr( $details['edge_style'] ) ),
			( 'no_border' !== $details['border_orientation'] )
				? sprintf(
					' et_bloom_with_border et_bloom_border_%1$s%2$s',
					esc_attr( $details['border_style'] ),
					esc_attr( ' et_bloom_border_position_' . $details['border_orientation'] )
				)
				: '',
			( 'rounded' == $details['corner_style'] ) ? ' et_bloom_rounded_corners' : '',
			( 'rounded' == $details['field_corner'] ) ? ' et_bloom_rounded' : '',
			'light' == $details['text_color'] ? ' et_bloom_form_text_light' : ' et_bloom_form_text_dark',
			ET_Bloom::generate_form_content( 0, 0, $details )
		);

		return $output;
	}

	/**
	 * Modifies the_content to add the form below content.
	 */
	function display_below_post( $content ) {
		$optins_set = $this->below_post_optins;

		if ( ! empty( $optins_set ) && ! is_singular( 'product' ) ) {
			foreach( $optins_set as $optin_id => $details ) {
				if ( $this->check_applicability( $optin_id ) ) {
					$content .= '<div class="et_bloom_below_post">' . $this->generate_inline_form( $optin_id, $details ) . '</div>';
				}
			}
		}

		return $content;
	}

	/**
	 * Display the form on woocommerce product page.
	 */
	function display_on_wc_page() {
		$optins_set = $this->below_post_optins;

		if ( ! empty( $optins_set ) ) {
			foreach( $optins_set as $optin_id => $details ) {
				if ( $this->check_applicability( $optin_id ) ) {
					echo $this->generate_inline_form( $optin_id, $details );
				}
			}
		}
	}

	/**
	 * Generates the content for inline form. Used to generate "Below content", "Inilne" and "Locked content" forms.
	 */
	function generate_inline_form( $optin_id, $details, $update_stats = true ) {
		$output = '';

		$page_id = get_the_ID();
		$list_id = $details['email_provider'] . '_' . $details['email_list'];
		$custom_css_output = '';

		$all_optins = ET_Bloom::get_bloom_options();
		$display_optin_id = ET_Bloom::choose_form_ab_test( $optin_id, $all_optins );

		if ( $display_optin_id != $optin_id ) {
			$optin_id = $display_optin_id;
			$details = $all_optins[$optin_id];
		}
		if ( true === $update_stats ) {
			ET_Bloom::add_stats_record( 'imp', $optin_id, $page_id, $list_id );
		}
		if ( 'below_post' !== $details['optin_type'] ) {
			$custom_css = ET_Bloom::generate_custom_css( '.et_bloom .et_bloom_' . $display_optin_id, $details );
			$custom_css_output = '' !== $custom_css ? sprintf( '<style type="text/css">%1$s</style>', $custom_css ) : '';
		}

		$output .= sprintf(
			'<div class="et_bloom_inline_form et_bloom_optin et_bloom_%1$s%9$s">
				%10$s
				<div class="et_bloom_form_container et_bloom_popup_container%3$s%4$s%5$s%6$s%7$s%8$s%11$s">
					%2$s
				</div>
			</div>',
			esc_attr( $optin_id ),
			ET_Bloom::generate_form_content( $optin_id, $page_id ),
			'basic_edge' == $details['edge_style'] || '' == $details['edge_style']
				? ''
				: sprintf( ' with_edge %1$s', esc_attr( $details['edge_style'] ) ),
			( 'no_border' !== $details['border_orientation'] )
				? sprintf(
					' et_bloom_border_%1$s%2$s',
					esc_attr( $details['border_style'] ),
					'full' !== $details['border_orientation']
						? ' et_bloom_border_position_' . $details['border_orientation']
						: ''
				)
				: '',
			( 'rounded' == $details['corner_style'] ) ? ' et_bloom_rounded_corners' : '', //#5
			( 'rounded' == $details['field_corner'] ) ? ' et_bloom_rounded' : '',
			'light' == $details['text_color'] ? ' et_bloom_form_text_light' : ' et_bloom_form_text_dark',
			'bottom' !== $details['form_orientation'] && 'custom_html' !== $details['email_provider']
				? sprintf(
					' et_bloom_form_%1$s',
					esc_html( $details['form_orientation'] )
				)
				: ' et_bloom_form_bottom',
			( isset( $details['hide_mobile_optin'] ) && true == $details['hide_mobile_optin'] )
				? ' et_bloom_hide_mobile_optin'
				: '',
			$custom_css_output, //#10
			( 'no_name' == $details['name_fields'] && ! ET_Bloom::is_only_name_support( $details['email_provider'] ) ) || ( ET_Bloom::is_only_name_support( $details['email_provider'] ) && $is_single_name )
				? ' et_bloom_inline_1_field'
				: sprintf(
					' et_bloom_inline_%1$s_fields',
					'first_last_name' == $details['name_fields'] && ! ET_Bloom::is_only_name_support( $details['email_provider'] )
						? '3'
						: '2'
				)
		);

		return $output;
	}

	/**
	 * Displays the Inline shortcode on front-end.
	 */
	function display_inline_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'optin_id' => '',
		), $atts );
		$optin_id = $atts['optin_id'];

		$optins_set = ET_Bloom::get_bloom_options();
		$selected_optin = isset( $optins_set[$optin_id] ) ? $optins_set[$optin_id] : '';
		$output = '';

		if ( '' !== $selected_optin && 'active' == $selected_optin['optin_status'] && 'inline' == $selected_optin['optin_type'] && empty( $selected_optin['child_of'] ) ) {
			$output = $this->generate_inline_form( $optin_id, $selected_optin );
		}

		return $output;
	}

	/**
	 * Displays the "locked content" shortcode on front-end.
	 */
	function display_locked_shortcode( $atts, $content=null ) {
		$atts = shortcode_atts( array(
			'optin_id' => '',
		), $atts );
		$optin_id = $atts['optin_id'];
		$optins_set = ET_Bloom::get_bloom_options();
		$selected_optin = isset( $optins_set[$optin_id] ) ? $optins_set[$optin_id] : '';
		if ( '' == $selected_optin ) {
			$output = $content;
		} else {
			$form = '';
			$page_id = get_the_ID();
			$list_id = 'custom_html' == $selected_optin['email_provider'] ? 'custom_html' : $selected_optin['email_provider'] . '_' . $selected_optin['email_list'];

			if ( '' !== $selected_optin && 'active' == $selected_optin['optin_status'] && 'locked' == $selected_optin['optin_type'] && empty( $selected_optin['child_of'] ) ) {
				$form = $this->generate_inline_form( $optin_id, $selected_optin, false );
			}

			$output = sprintf(
				'<div class="et_bloom_locked_container et_bloom_%4$s" data-page_id="%3$s" data-optin_id="%4$s" data-list_id="%5$s">
					<div class="et_bloom_locked_content" style="display: none;">
						%1$s
					</div>
					<div class="et_bloom_locked_form">
						%2$s
					</div>
				</div>',
				$content,
				$form,
				esc_attr( $page_id ),
				esc_attr( $optin_id ),
				esc_attr( $list_id )
			);
		}

		return $output;
	}

	function register_widget() {
		require_once( ET_BLOOM_PLUGIN_DIR . 'includes/bloom-widget.php' );
		register_widget( 'BloomWidget' );
	}

	/**
	 * Displays the Widget content on front-end.
	 */
	public static function display_widget( $optin_id ) {
		$optins_set = ET_Bloom::get_bloom_options();
		$selected_optin = isset( $optins_set[$optin_id] ) ? $optins_set[$optin_id] : '';
		$output = '';

		if ( '' !== $selected_optin && 'active' == $optins_set[$optin_id]['optin_status'] && empty( $optins_set[$optin_id]['child_of'] ) ) {

			$display_optin_id = ET_Bloom::choose_form_ab_test( $optin_id, $optins_set );

			if ( $display_optin_id != $optin_id ) {
				$optin_id = $display_optin_id;
				$selected_optin = $optins_set[$optin_id];
			}

			if ( is_singular() || is_front_page() ) {
				$page_id = is_front_page() ? -1 : get_the_ID();
			} else {
				$page_id = 0;
			}

			$list_id = $selected_optin['email_provider'] . '_' . $selected_optin['email_list'];

			$custom_css = ET_Bloom::generate_custom_css( '.et_bloom .et_bloom_' . $display_optin_id, $selected_optin );
			$custom_css_output = '' !== $custom_css ? sprintf( '<style type="text/css">%1$s</style>', $custom_css ) : '';

			ET_Bloom::add_stats_record( 'imp', $optin_id, $page_id, $list_id );

			$output = sprintf(
				'<div class="et_bloom_widget_content et_bloom_optin et_bloom_%7$s">
					%8$s
					<div class="et_bloom_form_container et_bloom_popup_container%2$s%3$s%4$s%5$s%6$s">
						%1$s
					</div>
				</div>',
				ET_Bloom::generate_form_content( $optin_id, $page_id ),
				'basic_edge' == $selected_optin['edge_style'] || '' == $selected_optin['edge_style']
					? ''
					: sprintf( ' with_edge %1$s', esc_attr( $selected_optin['edge_style'] ) ),
				( 'no_border' !== $selected_optin['border_orientation'] )
					? sprintf(
						' et_bloom_border_%1$s%2$s',
						$selected_optin['border_style'],
						'full' !== $selected_optin['border_orientation']
							? ' et_bloom_border_position_' . $selected_optin['border_orientation']
							: ''
					)
					: '',
				( 'rounded' == $selected_optin['corner_style'] ) ? ' et_bloom_rounded_corners' : '', //#5
				( 'rounded' == $selected_optin['field_corner'] ) ? ' et_bloom_rounded' : '',
				'light' == $selected_optin['text_color'] ? ' et_bloom_form_text_light' : ' et_bloom_form_text_dark',
				esc_attr( $optin_id ),
				$custom_css_output //#8
			);
		}

		return $output;
	}

	/**
	 * Returns list of widget optins to generate select option in widget settings
	 * @return array
	 */
	public static function widget_optins_list() {
		$optins_set = ET_Bloom::get_bloom_options();
		$output = array(
			'empty' => __( 'Select optin', 'bloom' ),
		);

		if ( ! empty( $optins_set ) ) {
			foreach( $optins_set as $optin_id => $details ) {
				if ( isset( $details['optin_status'] ) && 'active' === $details['optin_status'] && empty( $details['child_of'] ) ) {
					if ( 'widget' == $details['optin_type'] ) {
						$output = array_merge( $output, array( $optin_id => $details['optin_name'] ) );
					}
				}
			}
		} else {
			$output = array(
				'empty' => __( 'No Widget optins created yet', 'bloom' ),
			);
		}

		return $output;
	}

	function set_custom_css() {
		$options_array = ET_Bloom::get_bloom_options();
		$custom_css = '';
		$font_functions = ET_Bloom::load_fonts_class();
		$fonts_array = array();

		foreach( $options_array as $id => $single_optin ) {
			if ( 'accounts' != $id && 'db_version' != $id && isset( $single_optin['optin_type'] ) ) {
				if ( 'inactive' !== $single_optin['optin_status'] ) {
					$current_optin_id = ET_Bloom::choose_form_ab_test( $id, $options_array, false );
					$single_optin = $options_array[$current_optin_id];

					if ( ( ( 'flyin' == $single_optin['optin_type'] || 'pop_up' == $single_optin['optin_type'] || 'below_post' == $single_optin['optin_type'] ) && $this->check_applicability ( $id ) ) && ( isset( $single_optin['custom_css'] ) || isset( $single_optin['form_bg_color'] ) || isset( $single_optin['header_bg_color'] ) || isset( $single_optin['form_button_color'] ) || isset( $single_optin['border_color'] ) ) ) {
						$form_class = '.et_bloom .et_bloom_' . $current_optin_id;

						$custom_css .= ET_Bloom::generate_custom_css( $form_class, $single_optin );
					}

					if ( ! isset( $fonts_array[$single_optin['header_font']] ) && isset( $single_optin['header_font'] ) ) {
						$fonts_array[] = $single_optin['header_font'];
					}

					if ( ! isset( $fonts_array[$single_optin['body_font']] ) && isset( $single_optin['body_font'] ) ) {
						$fonts_array[] = $single_optin['body_font'];
					}
				}
			}
		}

		if ( ! empty( $fonts_array ) ) {
			$font_functions->et_gf_enqueue_fonts( $fonts_array );
		}

		if ( '' != $custom_css ) {
			printf(
				'<style type="text/css" id="et-bloom-custom-css">
					%1$s
				</style>',
				stripslashes( $custom_css )
			);
		}
	}

	/**
	 * Generated the output for custom css with specified class based on input option
	 * @return string
	 */
	public static function generate_custom_css( $form_class, $single_optin = array() ) {
		$font_functions = ET_Bloom::load_fonts_class();
		$custom_css = '';

		if ( isset( $single_optin['form_bg_color'] ) && '' !== $single_optin['form_bg_color'] ) {
			$custom_css .= $form_class . ' .et_bloom_form_content { background-color: ' . $single_optin['form_bg_color'] . ' !important; } ';

			if ( 'zigzag_edge' === $single_optin['edge_style'] ) {
				$custom_css .=
					$form_class . ' .zigzag_edge .et_bloom_form_content:before { background: linear-gradient(45deg, transparent 33.33%, ' . $single_optin['form_bg_color'] . ' 33.333%, ' . $single_optin['form_bg_color'] . ' 66.66%, transparent 66.66%), linear-gradient(-45deg, transparent 33.33%, ' . $single_optin['form_bg_color'] . ' 33.33%, ' . $single_optin['form_bg_color'] . ' 66.66%, transparent 66.66%) !important; background-size: 20px 40px !important; } ' .
					$form_class . ' .zigzag_edge.et_bloom_form_right .et_bloom_form_content:before, ' . $form_class . ' .zigzag_edge.et_bloom_form_left .et_bloom_form_content:before { background-size: 40px 20px !important; }
					@media only screen and ( max-width: 767px ) {' .
						$form_class . ' .zigzag_edge.et_bloom_form_right .et_bloom_form_content:before, ' . $form_class . ' .zigzag_edge.et_bloom_form_left .et_bloom_form_content:before { background: linear-gradient(45deg, transparent 33.33%, ' . $single_optin['form_bg_color'] . ' 33.333%, ' . $single_optin['form_bg_color'] . ' 66.66%, transparent 66.66%), linear-gradient(-45deg, transparent 33.33%, ' . $single_optin['form_bg_color'] . ' 33.33%, ' . $single_optin['form_bg_color'] . ' 66.66%, transparent 66.66%) !important; background-size: 20px 40px !important; } ' .
					'}';
			}
		}

		if ( isset( $single_optin['header_bg_color'] ) && '' !== $single_optin['header_bg_color'] ) {
			$custom_css .= $form_class .  ' .et_bloom_form_container .et_bloom_form_header { background-color: ' . $single_optin['header_bg_color'] . ' !important; } ';

			switch ( $single_optin['edge_style'] ) {
				case 'curve_edge' :
					$custom_css .= $form_class . ' .curve_edge .curve { fill: ' . $single_optin['header_bg_color'] . '} ';
					break;

				case 'wedge_edge' :
					$custom_css .= $form_class . ' .wedge_edge .triangle { fill: ' . $single_optin['header_bg_color'] . '} ';
					break;

				case 'carrot_edge' :
					$custom_css .=
						$form_class . ' .carrot_edge .et_bloom_form_content:before { border-top-color: ' . $single_optin['header_bg_color'] . ' !important; } ' .
						$form_class . ' .carrot_edge.et_bloom_form_right .et_bloom_form_content:before, ' . $form_class . ' .carrot_edge.et_bloom_form_left .et_bloom_form_content:before { border-top-color: transparent !important; border-left-color: ' . $single_optin['header_bg_color'] . ' !important; }
						@media only screen and ( max-width: 767px ) {' .
							$form_class . ' .carrot_edge.et_bloom_form_right .et_bloom_form_content:before, ' . $form_class . ' .carrot_edge.et_bloom_form_left .et_bloom_form_content:before { border-top-color: ' . $single_optin['header_bg_color'] . ' !important; border-left-color: transparent !important; }
						}';
					break;
			}

			if ( 'dashed' === $single_optin['border_style'] ) {
				if ( 'breakout_edge' !== $single_optin['edge_style'] ) {
					$custom_css .= $form_class . ' .et_bloom_form_container { background-color: ' . $single_optin['header_bg_color'] . ' !important; } ';
				} else {
					$custom_css .= $form_class . ' .et_bloom_header_outer { background-color: ' . $single_optin['header_bg_color'] . ' !important; } ';
				}
			}
		}

		if ( isset( $single_optin['form_button_color'] ) && '' !== $single_optin['form_button_color'] ) {
			$custom_css .= $form_class .  ' .et_bloom_form_content button { background-color: ' . $single_optin['form_button_color'] . ' !important; } ';
		}

		if ( isset( $single_optin['border_color'] ) && '' !== $single_optin['border_color'] && 'no_border' !== $single_optin['border_orientation'] ) {
			if ( 'breakout_edge' === $single_optin['edge_style'] ) {
				switch ( $single_optin['border_style'] ) {
					case 'letter' :
						$custom_css .= $form_class .  ' .breakout_edge.et_bloom_border_letter .et_bloom_header_outer { background: repeating-linear-gradient( 135deg, ' . $single_optin['border_color'] . ', ' . $single_optin['border_color'] . ' 10px, #fff 10px, #fff 20px, #f84d3b 20px, #f84d3b 30px, #fff 30px, #fff 40px ) !important; } ';
						break;

					case 'double' :
						$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_double .et_bloom_form_header { -moz-box-shadow: inset 0 0 0 6px ' . $single_optin['header_bg_color'] . ', inset 0 0 0 8px ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 0 0 6px ' . $single_optin['header_bg_color'] . ', inset 0 0 0 8px ' . $single_optin['border_color'] . '; box-shadow: inset 0 0 0 6px ' . $single_optin['header_bg_color'] . ', inset 0 0 0 8px ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';

						switch ( $single_optin['border_orientation'] ) {
							case 'top' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_double.et_bloom_border_position_top .et_bloom_form_header { -moz-box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'right' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_double.et_bloom_border_position_right .et_bloom_form_header { -moz-box-shadow: inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'bottom' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_double.et_bloom_border_position_bottom .et_bloom_form_header { -moz-box-shadow: inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'left' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_double.et_bloom_border_position_left .et_bloom_form_header { -moz-box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'top_bottom' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_double.et_bloom_border_position_top_bottom .et_bloom_form_header { -moz-box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . ', inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . ', inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . ', inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'left_right' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_double.et_bloom_border_position_left_right .et_bloom_form_header { -moz-box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . ', inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . ', inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . ', inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
						}
						break;

					case 'inset' :
						$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_inset .et_bloom_form_header { -moz-box-shadow: inset 0 0 0 3px ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 0 0 3px ' . $single_optin['border_color'] . '; box-shadow: inset 0 0 0 3px ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';

						switch ( $single_optin['border_orientation'] ) {
							case 'top' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_inset.et_bloom_border_position_top .et_bloom_form_header { -moz-box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'right' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_inset.et_bloom_border_position_right .et_bloom_form_header { -moz-box-shadow: inset -3px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset -3px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset -3px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'bottom' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_inset.et_bloom_border_position_bottom .et_bloom_form_header { -moz-box-shadow: inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'left' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_inset.et_bloom_border_position_left .et_bloom_form_header { -moz-box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'top_bottom' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_inset.et_bloom_border_position_top_bottom .et_bloom_form_header { -moz-box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . ', inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . ', inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . ', inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'left_right' :
								$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_inset.et_bloom_border_position_left_right .et_bloom_form_header { -moz-box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . ', inset -3px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . ', inset -3px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . ', inset -3px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
						}
						break;

					case 'solid' :
						$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_solid .et_bloom_form_header { border-color: ' . $single_optin['border_color'] . ' !important } ';
						break;

					case 'dashed' :
						$custom_css .= $form_class . ' .breakout_edge.et_bloom_border_dashed .et_bloom_form_header { border-color: ' . $single_optin['border_color'] . ' !important } ';
						break;
				}
			} else {
				switch ( $single_optin['border_style'] ) {
					case 'letter' :
						$custom_css .= $form_class .  ' .et_bloom_border_letter { background: repeating-linear-gradient( 135deg, ' . $single_optin['border_color'] . ', ' . $single_optin['border_color'] . ' 10px, #fff 10px, #fff 20px, #f84d3b 20px, #f84d3b 30px, #fff 30px, #fff 40px ) !important; } ';
						break;

					case 'double' :
						$custom_css .= $form_class . ' .et_bloom_border_double { -moz-box-shadow: inset 0 0 0 6px ' . $single_optin['header_bg_color'] . ', inset 0 0 0 8px ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 0 0 6px ' . $single_optin['header_bg_color'] . ', inset 0 0 0 8px ' . $single_optin['border_color'] . '; box-shadow: inset 0 0 0 6px ' . $single_optin['header_bg_color'] . ', inset 0 0 0 8px ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';

						switch ( $single_optin['border_orientation'] ) {
							case 'top' :
								$custom_css .= $form_class . ' .et_bloom_border_double.et_bloom_border_position_top { -moz-box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'right' :
								$custom_css .= $form_class . ' .et_bloom_border_double.et_bloom_border_position_right { -moz-box-shadow: inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'bottom' :
								$custom_css .= $form_class . ' .et_bloom_border_double.et_bloom_border_position_bottom { -moz-box-shadow: inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'left' :
								$custom_css .= $form_class . ' .et_bloom_border_double.et_bloom_border_position_left { -moz-box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'top_bottom' :
								$custom_css .= $form_class . ' .et_bloom_border_double.et_bloom_border_position_top_bottom { -moz-box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . ', inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . ', inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 8px 0 0 ' . $single_optin['border_color'] . ', inset 0 -6px 0 0 ' . $single_optin['header_bg_color'] . ', inset 0 -8px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
								break;

							case 'left_right' :
								$custom_css .= $form_class . ' .et_bloom_border_double.et_bloom_border_position_left_right { -moz-box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . ', inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . ', inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset 8px 0 0 0 ' . $single_optin['border_color'] . ', inset -6px 0 0 0 ' . $single_optin['header_bg_color'] . ', inset -8px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['border_color'] . '; } ';
						}
						break;

					case 'inset' :
						$custom_css .= $form_class . ' .et_bloom_border_inset { -moz-box-shadow: inset 0 0 0 3px ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 0 0 3px ' . $single_optin['border_color'] . '; box-shadow: inset 0 0 0 3px ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';

						switch ( $single_optin['border_orientation'] ) {
							case 'top' :
								$custom_css .= $form_class . ' .et_bloom_border_inset.et_bloom_border_position_top { -moz-box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'right' :
								$custom_css .= $form_class . ' .et_bloom_border_inset.et_bloom_border_position_right { -moz-box-shadow: inset -3px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset -3px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset -3px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'bottom' :
								$custom_css .= $form_class . ' .et_bloom_border_inset.et_bloom_border_position_bottom { -moz-box-shadow: inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'left' :
								$custom_css .= $form_class . ' .et_bloom_border_inset.et_bloom_border_position_left { -moz-box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'top_bottom' :
								$custom_css .= $form_class . ' .et_bloom_border_inset.et_bloom_border_position_top_bottom { -moz-box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . ', inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . ', inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 0 3px 0 0 ' . $single_optin['border_color'] . ', inset 0 -3px 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
								break;

							case 'left_right' :
								$custom_css .= $form_class . ' .et_bloom_border_inset.et_bloom_border_position_left_right { -moz-box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . ', inset -3px 0 0 0 ' . $single_optin['border_color'] . '; -webkit-box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . ', inset -3px 0 0 0 ' . $single_optin['border_color'] . '; box-shadow: inset 3px 0 0 0 ' . $single_optin['border_color'] . ', inset -3px 0 0 0 ' . $single_optin['border_color'] . '; border-color: ' . $single_optin['header_bg_color'] . '; } ';
						}
						break;

					case 'solid' :
						$custom_css .= $form_class . ' .et_bloom_border_solid { border-color: ' . $single_optin['border_color'] . ' !important } ';
						break;

					case 'dashed' :
						$custom_css .= $form_class . ' .et_bloom_border_dashed .et_bloom_form_container_wrapper { border-color: ' . $single_optin['border_color'] . ' !important } ';
						break;
				}
			}
		}

		$custom_css .= isset( $single_optin['form_button_color'] ) && '' !== $single_optin['form_button_color'] ? $form_class .  ' .et_bloom_form_content button { background-color: ' . $single_optin['form_button_color'] . ' !important; } ' : '';
		$custom_css .= isset( $single_optin['header_font'] ) ? $font_functions->et_gf_attach_font( $single_optin['header_font'], $form_class . ' h2, ' . $form_class . ' h2 span, ' . $form_class . ' h2 strong' ) : '';
		$custom_css .= isset( $single_optin['body_font'] ) ? $font_functions->et_gf_attach_font( $single_optin['body_font'], $form_class . ' p, ' . $form_class . ' p span, ' . $form_class . ' p strong, ' . $form_class . ' form input, ' . $form_class . ' form button span' ) : '';

		$custom_css .= isset( $single_optin['custom_css'] ) ? ' ' . $single_optin['custom_css'] : '';

		return $custom_css;
	}

	/**
	 * Modifies the URL of post after commenting to trigger the popup after comment
	 * @return string
	 */
	function after_comment_trigger( $location ){
		$newurl = $location;
		$newurl = substr( $location, 0, strpos( $location, '#comment' ) );
		$delimeter = false === strpos( $location, '?' ) ? '?' : '&';
		$params = 'et_bloom_popup=true';

		$newurl .= $delimeter . $params;

		return $newurl;
	}

	/**
	 * Generated content for purchase trigger
	 * @return string
	 */
	function add_purchase_trigger() {
		echo '<div class="et_bloom_after_order"></div>';
	}

	/**
	 * Adds appropriate actions for Flyin, Popup, Below Content to wp_footer,
	 * Adds custom_css function to wp_head
	 * Adds trigger_bottom_mark to the_content filter for Flyin and Popup
	 * Creates arrays with optins for for Flyin, Popup, Below Content to improve the performance during forms displaying
	 */
	function frontend_register_locations() {
		$options_array = ET_Bloom::get_bloom_options();

		if ( ! is_admin() && ! empty( $options_array ) ) {
			add_action( 'wp_head', array( $this, 'set_custom_css' ) );

			$flyin_count = 0;
			$popup_count = 0;
			$below_count = 0;
			$after_comment = 0;
			$after_purchase = 0;

			foreach ( $options_array as $optin_id => $details ) {
				if ( 'accounts' !== $optin_id ) {
					if ( isset( $details['optin_status'] ) && 'active' === $details['optin_status'] && empty( $details['child_of'] ) ) {
						switch( $details['optin_type'] ) {
							case 'flyin' :
								if ( 0 === $flyin_count ) {
									add_action( 'wp_footer', array( $this, "display_flyin" ) );
									$flyin_count++;
								}

								if ( 0 === $after_comment && isset( $details['comment_trigger'] ) && true == $details['comment_trigger'] ) {
									add_filter( 'comment_post_redirect', array( $this, 'after_comment_trigger' ) );
									$after_comment++;
								}

								if ( 0 === $after_purchase && isset( $details['purchase_trigger'] ) && true == $details['purchase_trigger'] ) {
									add_action( 'woocommerce_thankyou', array( $this, 'add_purchase_trigger' ) );
									$after_purchase++;
								}

								$this->flyin_optins[$optin_id] = $details;
								break;

							case 'pop_up' :
								if ( 0 === $popup_count ) {
									add_action( 'wp_footer', array( $this, "display_popup" ) );
									$popup_count++;
								}

								if ( 0 === $after_comment && isset( $details['comment_trigger'] ) && true == $details['comment_trigger'] ) {
									add_filter( 'comment_post_redirect', array( $this, 'after_comment_trigger' ) );
									$after_comment++;
								}

								if ( 0 === $after_purchase && isset( $details['purchase_trigger'] ) && true == $details['purchase_trigger'] ) {
									add_action( 'woocommerce_thankyou', array( $this, 'add_purchase_trigger' ) );
									$after_purchase++;
								}

								$this->popup_optins[$optin_id] = $details;
								break;

							case 'below_post' :
								if ( 0 === $below_count ) {
									add_filter( 'the_content', array( $this, 'display_below_post' ), 9999 );
									add_action( 'woocommerce_after_single_product_summary', array( $this, 'display_on_wc_page' ) );
									$below_count++;
								}

								$this->below_post_optins[$optin_id] = $details;
								break;
						}
					}
				}
			}

			if ( 0 < $flyin_count || 0 < $popup_count ) {
				add_filter( 'the_content', array( $this, 'trigger_bottom_mark' ), 9999 );
			}
		}
	}

}

new ET_Bloom();