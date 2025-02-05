<?php
/*
Plugin Name: Cookie Notice & Compliance for GDPR / CCPA
Description: Cookie Notice allows you to you elegantly inform users that your site uses cookies and helps you comply with GDPR, CCPA and other data privacy laws.
Version: 2.4.5
Author: Hu-manity.co
Author URI: https://hu-manity.co/
Plugin URI: https://cookie-compliance.co/
License: MIT License
License URI: https://opensource.org/licenses/MIT
Text Domain: cookie-notice
Domain Path: /languages

Cookie Notice
Copyright (C) 2022, Hu-manity.co - info@hu-manity.co

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Cookie Notice class.
 *
 * @class Cookie_Notice
 * @version	2.4.5
 */
class Cookie_Notice {

	private $status_data = [
		'status'				=> '',
		'subscription'			=> 'basic',
		'threshold_exceeded'	=> false
	];
	private $x_api_key = 'hudft60djisdusdjwek';
	private $app_host_url = 'https://app.hu-manity.co';
	private $app_login_url = 'https://app.hu-manity.co/#/en/cc2/login';
	private $app_dashboard_url = 'https://app.hu-manity.co/#/en/cc/dashboard';
	private $account_api_url = 'https://account-api.hu-manity.co';
	private $designer_api_url = 'https://designer-api.hu-manity.co';
	private $transactional_api_url = 'https://transactional-api.hu-manity.co';
	private $app_widget_url = '//cdn.hu-manity.co/hu-banner.min.js';
	private $deactivaion_url = '';
	private $network_admin = false;
	private $plugin_network_active = false;
	private static $_instance;
	public $options = [];
	public $network_options = [];

	/**
	 * @var $defaults
	 */
	public $defaults = [
		'general'	=> [
			'global_override'		=> false,
			'global_cookie'			=> false,
			'app_id'				=> '',
			'app_key'				=> '',
			'app_blocking'			=> true,
			'hide_banner'			=> false,
			'debug_mode'			=> false,
			'position'				=> 'bottom',
			'message_text'			=> '',
			'css_class'				=> '',
			'accept_text'			=> '',
			'refuse_text'			=> '',
			'refuse_opt'			=> false,
			'refuse_code'			=> '',
			'refuse_code_head'		=> '',
			'revoke_cookies'		=> false,
			'revoke_cookies_opt'	=> 'automatic',
			'revoke_message_text'	=> '',
			'revoke_text'			=> '',
			'redirection'			=> false,
			'see_more'				=> false,
			'link_target'			=> '_blank',
			'link_position'			=> 'banner',
			'time'					=> 'month',
			'time_rejected'			=> 'month',
			'hide_effect'			=> 'fade',
			'on_scroll'				=> false,
			'on_scroll_offset'		=> 100,
			'on_click'				=> false,
			'colors' => [
				'text'			=> '#fff',
				'button'		=> '#00a99d',
				'bar'			=> '#32323a',
				'bar_opacity'	=> 100
			],
			'see_more_opt' => [
				'text'		=> '',
				'link_type'	=> 'page',
				'id'		=> 0,
				'link'		=> '',
				'sync'		=> false
			],
			'script_placement'		=> 'header',
			'translate'				=> true,
			'deactivation_delete'	=> false,
			'update_version'		=> 7,
			'update_notice'			=> true,
			'update_notice_diss'	=> false,
			'update_delay_date'		=> 0,
			'update_threshold_date'	=> 0
		],
		'data'	=> [
			'status'				=> '',
			'subscription'			=> 'basic',
			'threshold_exceeded'	=> false
		],
		'version'	=> '2.4.5'
	];

	/**
	 * Disable object cloning.
	 *
	 * @return void
	 */
	public function __clone() {}

	/**
	 * Disable unserializing of the class.
	 *
	 * @return void
	 */
	public function __wakeup() {}

	/**
	 * Main plugin instance.
	 *
	 * @return object
	 */
	public static function instance() {
		if ( self::$_instance === null ) {
			self::$_instance = new self();

			add_action( 'init', [ self::$_instance, 'load_textdomain' ] );

			self::$_instance->includes();

			self::$_instance->bot_detect = new Cookie_Notice_Bot_Detect();
			self::$_instance->dashboard = new Cookie_Notice_Dashboard();
			self::$_instance->frontend = new Cookie_Notice_Frontend();
			self::$_instance->settings = new Cookie_Notice_Settings();
			self::$_instance->welcome = new Cookie_Notice_Welcome();
			self::$_instance->welcome_api = new Cookie_Notice_Welcome_API();
			self::$_instance->welcome_frontend = new Cookie_Notice_Welcome_Frontend();
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// define plugin constants
		$this->define_constants();

		// activation hooks
		register_activation_hook( __FILE__, [ $this, 'activation' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivation' ] );

		// set network data
		$this->set_network_data();

		// get options
		if ( is_multisite() ) {
			// get network options
			$this->network_options = get_site_option( 'cookie_notice_options', $this->defaults['general'] );

			if ( $this->is_network_admin() ) {
				$options = $this->network_options;
			} else {
				// settings page?
				if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === 'cookie-notice' ) {
					// get current url path
					$url_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

					if ( basename( $url_path ) === 'admin.php' ) {
						// get site options
						$options = get_option( 'cookie_notice_options', $this->defaults['general'] );
					}
				} else {
					if ( $this->is_plugin_network_active() && $this->network_options['global_override'] )
						$options = $this->network_options;
					else
						$options = get_option( 'cookie_notice_options', $this->defaults['general'] );
				}
			}
		} else
			$options = get_option( 'cookie_notice_options', $this->defaults['general'] );

		// check legacy parameters
		$options = $this->check_legacy_params( $options, [ 'refuse_opt', 'on_scroll', 'on_click', 'deactivation_delete', 'see_more' ] );

		// merge old options with new ones
		$this->options['general'] = $this->multi_array_merge( $this->defaults['general'], $options );

		if ( ! isset( $this->options['general']['see_more_opt']['sync'] ) )
			$this->options['general']['see_more_opt']['sync'] = $this->defaults['general']['see_more_opt']['sync'];

		// actions
		add_action( 'plugins_loaded', [ $this, 'set_status_data' ] );
		add_action( 'init', [ $this, 'register_shortcodes' ] );
		add_action( 'init', [ $this, 'wpsc_add_cookie' ] );
		add_action( 'init', [ $this, 'set_plugin_links' ] );
		add_action( 'admin_init', [ $this, 'update_notice' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'admin_footer', [ $this, 'deactivate_plugin_template' ] );
		add_action( 'wp_ajax_cn_dismiss_notice', [ $this, 'ajax_dismiss_admin_notice' ] );
		add_action( 'wp_ajax_cn-deactivate-plugin', [ $this, 'deactivate_plugin' ] );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		define( 'COOKIE_NOTICE_URL', plugins_url( '', __FILE__ ) );
		define( 'COOKIE_NOTICE_PATH', plugin_dir_path( __FILE__ ) );
		define( 'COOKIE_NOTICE_BASENAME', plugin_basename( __FILE__ ) );
	}

	/**
	 * Set cookie compliance status data.
	 *
	 * @return void
	 */
	public function set_status_data() {
		$default_data = $this->defaults['data'];

		if ( is_multisite() ) {
			if ( $this->is_plugin_network_active() ) {
				// network
				if ( $this->is_network_admin() ) {
					if ( $this->network_options['global_override'] )
						$status_data = get_site_option( 'cookie_notice_status', $default_data );
					else
						$status_data = $default_data;
				// site
				} else {
					if ( $this->network_options['global_override'] )
						$status_data = get_site_option( 'cookie_notice_status', $default_data );
					else
						$status_data = get_option( 'cookie_notice_status', $default_data );
				}
			} else {
				// network
				if ( $this->is_network_admin() )
					$status_data = $default_data;
				// site
				else
					$status_data = get_option( 'cookie_notice_status', $default_data );
			}
		} else
			$status_data = get_option( 'cookie_notice_status', $default_data );

		// old status format?
		if ( ! is_array( $status_data ) ) {
			// update config data
			$status_data = $this->welcome_api->get_app_config( '', true );
		} else {
			// merge database data with default data
			$status_data = array_merge( $default_data, $status_data );
		}

		if ( $status_data['threshold_exceeded'] )
			$this->options['general']['app_blocking'] = false;

		// set status data
		$this->status_data = [
			'status'				=> $this->check_status( $status_data['status'] ),
			'subscription'			=> $this->check_subscription( $status_data['subscription'] ),
			'threshold_exceeded'	=> (bool) $status_data['threshold_exceeded']
		];
	}

	/**
	 * Get cookie compliance status.
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status_data['status'];
	}

	/**
	 * Check cookie compliance status.
	 *
	 * @param string $status
	 * @return string
	 */
	public function check_status( $status ) {
		$status = sanitize_key( $status );

		return ! empty( $status ) && in_array( $status, [ 'active', 'pending' ], true ) ? $status : $this->defaults['data']['status'];
	}

	/**
	 * Get cookie compliance subscription.
	 *
	 * @return string
	 */
	public function get_subscription() {
		return $this->status_data['subscription'];
	}

	/**
	 * Check cookie compliance subscription.
	 *
	 * @param string $subscription
	 * @return string
	 */
	public function check_subscription( $subscription ) {
		$subscription = sanitize_key( $subscription );

		return ! empty( $subscription ) && in_array( $subscription, [ 'basic', 'pro' ], true ) ? $subscription : $this->defaults['data']['subscription'];
	}

	/**
	 * Check whether the current threshold is exceeded.
	 *
	 * @return bool
	 */
	public function threshold_exceeded() {
		return $this->status_data['threshold_exceeded'];
	}

	/**
	 * Get endpoint URL.
	 *
	 * @param $type
	 * @param $query
	 * @return string
	 */
	public function get_url( $type, $query = '' ) {
		if ( $type === 'login' )
			$url = $this->app_login_url;
		elseif ( $type === 'dashboard' )
			$url = $this->app_dashboard_url;
		elseif ( $type === 'widget' )
			$url = $this->app_widget_url;
		elseif ( $type === 'host' )
			$url = $this->app_host_url;
		elseif ( $type === 'account_api' )
			$url = $this->account_api_url;
		elseif ( $type === 'designer_api' )
			$url = $this->designer_api_url;
		elseif ( $type === 'transactional_api' )
			$url = $this->transactional_api_url;

		return $url . ( $query !== '' ? $query : '' );
	}

	/**
	 * Get API key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return $this->x_api_key;
	}

	/**
	 * Check whether the current request is for the network administrative interface.
	 *
	 * @return bool
	 */
	public function is_network_admin() {
		return $this->network_admin;
	}

	/**
	 * Check whether the plugin is active for the entire network.
	 *
	 * @return bool
	 */
	public function is_plugin_network_active() {
		return $this->plugin_network_active;
	}

	/**
	 * Set network data.
	 *
	 * @return void
	 */
	private function set_network_data() {
		// load plugin.php file
		if ( ! function_exists( 'is_plugin_active_for_network' ) )
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		// bypass is_network_admin() to handle AJAX requests properly.
		$this->network_admin = is_multisite() && ( is_network_admin() || ( wp_doing_ajax() && isset( $_POST['cn_network'] ) && $_POST['cn_network'] === '1' ) );

		// check whether the plugin is active for the entire network.
		$this->plugin_network_active = is_plugin_active_for_network( COOKIE_NOTICE_BASENAME );
	}

	/**
	 * Include required files.
	 *
	 * @return void
	 */
	private function includes() {
		include_once( COOKIE_NOTICE_PATH . 'includes/bot-detect.php' );
		include_once( COOKIE_NOTICE_PATH . 'includes/dashboard.php' );
		include_once( COOKIE_NOTICE_PATH . 'includes/frontend.php' );
		include_once( COOKIE_NOTICE_PATH . 'includes/functions.php' );
		include_once( COOKIE_NOTICE_PATH . 'includes/settings.php' );
		include_once( COOKIE_NOTICE_PATH . 'includes/welcome.php' );
		include_once( COOKIE_NOTICE_PATH . 'includes/welcome-api.php' );
		include_once( COOKIE_NOTICE_PATH . 'includes/welcome-frontend.php' );
	}

	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cookie-notice', false, dirname( COOKIE_NOTICE_BASENAME ) . '/languages/' );
	}

	/**
	 * Plugin activation.
	 *
	 * @global object $wpdb
	 *
	 * @param bool $networkwide
	 * @return void
	 */
	public function activation( $networkwide ) {
		// network activation?
		if ( $networkwide ) {
			// add network options
			add_site_option( 'cookie_notice_options', $this->defaults['general'] );
			add_site_option( 'cookie_notice_status', $this->defaults['data'] );
			add_site_option( 'cookie_notice_version', $this->defaults['version'] );

			global $wpdb;

			// get current site
			$current_blog_id = $wpdb->blogid;

			// get all available sites
			$blogs_ids = $wpdb->get_col( 'SELECT blog_id FROM ' . $wpdb->blogs );

			foreach ( $blogs_ids as $blog_id ) {
				// change to another site
				switch_to_blog( (int) $blog_id );

				// run current site activation process
				$this->activate_site();
			}

			// switch back to main site
			switch_to_blog( $current_blog_id );
		} else
			$this->activate_site();
	}

	/**
	 * Single site activation.
	 *
	 * @return void
	 */
	public function activate_site() {
		// add default options
		add_option( 'cookie_notice_options', $this->defaults['general'], '', false );
		add_option( 'cookie_notice_status', $this->defaults['data'], '', false );
		add_option( 'cookie_notice_version', $this->defaults['version'], '', false );
	}

	/**
	 * Plugin deactivation.
	 *
	 * @global object $wpdb
	 *
	 * @param bool $networkwide
	 * @return void
	 */
	public function deactivation( $networkwide ) {
		// network deactivation?
		if ( $networkwide ) {
			$delete = $this->options['general']['global_override'] && $this->options['general']['deactivation_delete'];

			// delete network options?
			if ( $delete ) {
				delete_site_option( 'cookie_notice_options' );
				delete_site_option( 'cookie_notice_status' );
				delete_site_option( 'cookie_notice_app_analytics' );
				delete_site_option( 'cookie_notice_app_blocking' );
				delete_site_option( 'cookie_notice_version' );
			}

			global $wpdb;

			// get current site
			$current_blog_id = $wpdb->blogid;

			// get all available sites
			$blogs_ids = $wpdb->get_col( 'SELECT blog_id FROM ' . $wpdb->blogs );

			foreach ( $blogs_ids as $blog_id ) {
				// change to another site
				switch_to_blog( (int) $blog_id );

				// run current site deactivation process
				$this->deactivate_site( $delete );
			}

			// switch back to main site
			switch_to_blog( $current_blog_id );
		} else
			$this->deactivate_site();
	}

	/**
	 * Single site deactivation.
	 *
	 * @param boolean $force_deletion
	 * @return void
	 */
	public function deactivate_site( $force_deletion = false ) {
		// delete settings?
		if ( $force_deletion || $this->options['general']['deactivation_delete'] ) {
			// delete options
			delete_option( 'cookie_notice_options' );
			delete_option( 'cookie_notice_status' );
			delete_option( 'cookie_notice_app_analytics' );
			delete_option( 'cookie_notice_app_blocking' );
			delete_option( 'cookie_notice_version' );

			// delete transient
			delete_transient( 'cookie_notice_app_cache' );
		}

		// remove wp super cache cookie
		$this->wpsc_delete_cookie();
	}

	/**
	 * Retrieve the timezone of the site as a string.
	 *
	 * @return string
	 */
	public function timezone_string() {
		if ( function_exists( 'wp_timezone_string' ) )
			return wp_timezone_string();

		$timezone_string = get_option( 'timezone_string' );

		if ( $timezone_string )
			return $timezone_string;

		$offset = (float) get_option( 'gmt_offset' );
		$hours = (int) $offset;
		$minutes = ( $offset - $hours );
		$sign = ( $offset < 0 ) ? '-' : '+';
		$abs_hour = abs( $hours );
		$abs_mins = abs( $minutes * 60 );
		$tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

		return $tz_offset;
	}

	/**
	 * Update notice.
	 *
	 * @return void
	 */
	public function update_notice() {
		if ( ! current_user_can( 'install_plugins' ) )
			return;

		// bail an ajax
		if ( wp_doing_ajax() )
			return;

		$network = $this->is_network_admin();

		$current_update = 8;

		// get current database version
		if ( $network )
			$current_db_version = get_site_option( 'cookie_notice_version', '1.0.0' );
		else
			$current_db_version = get_option( 'cookie_notice_version', '1.0.0' );

		if ( version_compare( $current_db_version, $this->defaults['version'], '<' ) && $this->options['general']['update_version'] < $current_update ) {
			// check version, if update version is lower than plugin version, set update notice to true
			$this->options['general']['update_version'] = $current_update;
			$this->options['general']['update_notice'] = true;

			if ( $network ) {
				$this->options['general']['update_notice_diss'] = false;

				// update options
				update_site_option( 'cookie_notice_options', $this->options['general'] );

				// update plugin version
				update_site_option( 'cookie_notice_version', $this->defaults['version'] );
			} else {
				// update options
				update_option( 'cookie_notice_options', $this->options['general'] );

				// update plugin version
				update_option( 'cookie_notice_version', $this->defaults['version'], false );
			}
		}

		// if visiting settings, mark notice as read
		if ( ! empty( $_GET['page'] ) && $_GET['page'] === 'cookie-notice' && ! empty( $_GET['welcome'] ) ) {
			$this->options['general']['update_notice'] = false;

			if ( $network ) {
				$this->options['general']['update_notice_diss'] = true;

				update_site_option( 'cookie_notice_options', $this->options['general'] );
			} else
				update_option( 'cookie_notice_options', $this->options['general'] );
		}

		if ( is_multisite() && ( ( $this->is_plugin_network_active() && ! $network && $this->network_options['global_override'] ) || ( $network && ! $this->is_plugin_network_active() ) ) )
			$this->options['general']['update_notice'] = false;

		// get cookie compliance status
		$status = $this->get_status();

		// show notice, if no compliance only
		if ( $this->options['general']['update_notice'] === true && empty( $status ) ) {
			$this->add_notice( '<div class="cn-notice-text"><h2>' . __( 'Two new privacy laws in the United States started January 1st 2023', 'cookie-notice' ) . '</h2><p>' . __( 'As of early 2023, two regulations in the United States went into effect: the California CPRA and the Virginia VCDPA. They expand requirements already in place in the US, such as having a Privacy Policy and allowing users to opt out of the data processing for certain purposes. If your business is located in the United States or your website has visitors from the US you may fall under these regulations. Click "Run Compliance Check" to check if your website has the functionality required by the US Privacy Laws.', 'cookie-notice' ) . '</p><p class="cn-notice-actions"><a href="' . ( $network ? network_admin_url( 'admin.php?page=cookie-notice&welcome=1' ) : admin_url( 'admin.php?page=cookie-notice&welcome=1' ) ) . '" class="button button-primary cn-button">' . __( 'Run Compliance Check', 'cookie-notice' ) . '</a> <a href="#" class="button-link cn-notice-dismiss">' . __( 'Dismiss Notice', 'cookie-notice' ) . '</a></p></div>', 'error', 'div' );
		}

		// show threshold limit warning, compliance only
		if ( $status === 'active' ) {
			// get analytics data options
			if ( $network )
				$analytics = get_site_option( 'cookie_notice_app_analytics', [] );
			else
				$analytics = get_option( 'cookie_notice_app_analytics', [] );

			if ( is_multisite() && ( ( $network && ! $this->is_plugin_network_active() && ! $this->network_options['global_override'] ) || ( ! $network && $this->is_plugin_network_active() && $this->network_options['global_override'] ) ) )
				$allow_notice = false;
			else
				$allow_notice = true;

			if ( ! empty( $analytics ) && $allow_notice ) {
				// cycle usage data
				$cycle_usage = [
					'threshold'		=> ! empty( $analytics['cycleUsage']->threshold ) ? (int) $analytics['cycleUsage']->threshold : 0,
					'visits'		=> ! empty( $analytics['cycleUsage']->visits ) ? (int) $analytics['cycleUsage']->visits : 0,
					'end_date'		=> ! empty( $analytics['cycleUsage']->endDate ) ? date_create_from_format( '!Y-m-d', $analytics['cycleUsage']->endDate ) : date_create_from_format( 'Y-m-d H:i:s', current_time( 'mysql', true ) ),
					'last_updated'	=> ! empty( $analytics['lastUpdated'] ) ? date_create_from_format( 'Y-m-d H:i:s', $analytics['lastUpdated'] ) : date_create_from_format( 'Y-m-d H:i:s', current_time( 'mysql', true ) )
				];

				// if threshold in use
				if ( $cycle_usage['threshold'] ) {
					// if threshold exceeded and there was no notice before
					if ( $cycle_usage['visits'] >= $cycle_usage['threshold'] && $cycle_usage['last_updated']->getTimestamp() < $cycle_usage['end_date']->getTimestamp() && $this->options['general']['update_threshold_date'] < $cycle_usage['end_date']->getTimestamp() ) {
						$date_format = get_option( 'date_format' );

						$upgrade_link = $this->get_url( 'dashboard', '?app-id=' . $this->options['general']['app_id'] . '&open-modal=payment' );
						$threshold = $cycle_usage['threshold'];
						$cycle_date = date_i18n( $date_format, $cycle_usage['end_date']->getTimestamp() );

						$this->add_notice( '<div class="cn-notice-text" data-delay="' . $cycle_usage['end_date']->getTimestamp() . '"><h2>' . __( 'Cookie Compliance Warning', 'cookie-notice') . '</h2><p>' . sprintf( __( 'Your website has reached the <b>%1$s visits usage limit for the Cookie Compliance Free Plan</b>. Compliance services such as Consent Record Storage, Autoblocking, and Consent Analytics have been deactivated until current usage cycle ends on %2$s.', 'cookie-notice' ), $threshold, $cycle_date ) . '<br>' . sprintf( __( 'To reactivate compliance services now, <a href="%s" target="_blank">upgrade your domain to a Pro plan.</a>', 'cookie-notice' ) . '</p></div>', $upgrade_link ), 'cn-threshold error is-dismissible', 'div' );
					}
				}
			}
		}
	}

	/**
	 * Add admin notice.
	 *
	 * @param string $html
	 * @param string $status
	 * @param string $container
	 * @return void
	 */
	private function add_notice( $html = '', $status = 'error', $container = '' ) {
		$this->notices[] = [
			'html'		=> $html,
			'status'	=> $status,
			'container'	=> ( ! empty( $container ) && in_array( $container, [ 'p', 'div' ] ) ? $container : '' )
		];

		add_action( 'admin_notices', [ $this, 'display_notice' ], 0 );
		add_action( 'network_admin_notices', [ $this, 'display_notice' ], 0 );
	}

	/**
	 * Print admin notices.
	 *
	 * @return void
	 */
	public function display_notice() {
		foreach( $this->notices as $notice ) {
			echo '
			<div id="cn-admin-notice" class="cn-notice notice notice-info ' . $notice['status'] . '">
				' . ( ! empty( $notice['container'] ) ? '<' . $notice['container'] . ' class="cn-notice-container">' : '' ) . '
				' . $notice['html'] . '
				' . ( ! empty( $notice['container'] ) ? '</' . $notice['container'] . ' class="cn-notice-container">' : '' ) . '
			</div>';
		}
	}

	/**
	 * Dismiss admin notice.
	 *
	 * @return void
	 */
	public function ajax_dismiss_admin_notice() {
		if ( ! current_user_can( 'install_plugins' ) )
			return;

		if ( wp_verify_nonce( $_REQUEST['nonce'], 'cn_dismiss_notice' ) ) {
			$notice_action = empty( $_REQUEST['notice_action'] ) || $_REQUEST['notice_action'] === 'dismiss' ? 'dismiss' : sanitize_text_field( $_REQUEST['notice_action'] );
			$network = is_multisite() && isset( $_POST['cn_network'] ) && $_POST['cn_network'] === '1';

			switch ( $notice_action ) {
				// threshold notice
				case 'threshold':
					// set delay period last cycle day
					$delay = isset( $_REQUEST['param'] ) ? (int) $_REQUEST['param'] : 0;

					$this->options['general']['update_threshold_date'] = $delay + DAY_IN_SECONDS;

					// update options
					if ( $network )
						update_site_option( 'cookie_notice_options', $this->options['general'] );
					else
						update_option( 'cookie_notice_options', $this->options['general'] );
					break;

				// delay notice
				case 'delay':
					// set delay period to 1 week from now
					$this->options['general']['update_delay_date'] = time() + 1209600;

					// update options
					if ( $network )
						update_site_option( 'cookie_notice_options', $this->options['general'] );
					else
						update_option( 'cookie_notice_options', $this->options['general'] );
					break;

				// hide notice
				case 'approve':
				default:
					$this->options['general']['update_notice'] = false;
					$this->options['general']['update_delay_date'] = 0;

					// update options
					if ( $network ) {
						$this->options['general']['update_notice_diss'] = true;

						update_site_option( 'cookie_notice_options', $this->options['general'] );
					} else
						update_option( 'cookie_notice_options', $this->options['general'] );
			}
		}

		exit;
	}

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'cookies_accepted', [ $this, 'cookies_accepted_shortcode' ] );
		add_shortcode( 'cookies_revoke', [ $this, 'cookies_revoke_shortcode' ] );
		add_shortcode( 'cookies_policy_link', [ $this, 'cookies_policy_link_shortcode' ] );
	}

	/**
	 * Register cookies accepted shortcode.
	 *
	 * @param array $args
	 * @param mixed $content
	 * @return string
	 */
	public function cookies_accepted_shortcode( $args, $content ) {
		if ( $this->cookies_accepted() ) {
			$scripts = html_entity_decode( trim( wp_kses( $content, $this->get_allowed_html() ) ) );

			if ( ! empty( $scripts ) ) {
				if ( preg_match_all( '/' . get_shortcode_regex() . '/', $content ) )
					$scripts = do_shortcode( $scripts );

				return $scripts;
			}
		}

		return '';
	}

	/**
	 * Register cookies revoke shortcode.
	 *
	 * @param array $args
	 * @param mixed $content
	 * @return string
	 */
	public function cookies_revoke_shortcode( $args, $content ) {
		// get options
		$options = $this->options['general'];

		// WPML >= 3.2
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && version_compare( ICL_SITEPRESS_VERSION, '3.2', '>=' ) )
			$options['revoke_text'] = apply_filters( 'wpml_translate_single_string', $options['revoke_text'], 'Cookie Notice', 'Revoke button text' );
		// WPML and Polylang compatibility
		elseif ( function_exists( 'icl_t' ) )
			$options['revoke_text'] = icl_t( 'Cookie Notice', 'Revoke button text', $options['revoke_text'] );

		// defaults
		$defaults = [
			'title'	=> $options['revoke_text'],
			'class'	=> $options['css_class']
		];

		// combine shortcode arguments
		$args = shortcode_atts( $defaults, $args );

		// escape class(es)
		$args['class'] = esc_attr( $args['class'] );

		if ( Cookie_Notice()->get_status() === 'active' )
			$shortcode = '<a href="#" class="cn-revoke-cookie cn-button-inline cn-revoke-inline' . ( $args['class'] !== '' ? ' ' . $args['class'] : '' ) . '" title="' . esc_attr( $args['title'] ) . '" data-hu-action="cookies-notice-revoke">' . esc_html( $args['title'] ) . '</a>';
		else
			$shortcode = '<a href="#" class="cn-revoke-cookie cn-button-inline cn-revoke-inline' . ( $args['class'] !== '' ? ' ' . $args['class'] : '' ) . '" title="' . esc_attr( $args['title'] ) . '">' . esc_html( $args['title'] ) . '</a>';

		return $shortcode;
	}

	/**
	 * Register cookies policy link shortcode.
	 *
	 * @param array $args
	 * @param string $content
	 * @return string
	 */
	public function cookies_policy_link_shortcode( $args, $content ) {
		// get options
		$options = $this->options['general'];

		// WPML >= 3.2
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && version_compare( ICL_SITEPRESS_VERSION, '3.2', '>=' ) ) {
			$options['see_more_opt']['text'] = apply_filters( 'wpml_translate_single_string', $options['see_more_opt']['text'], 'Cookie Notice', 'Privacy policy text' );
			$options['see_more_opt']['link'] = apply_filters( 'wpml_translate_single_string', $options['see_more_opt']['link'], 'Cookie Notice', 'Custom link' );
		// WPML and Polylang compatibility
		} elseif ( function_exists( 'icl_t' ) ) {
			$options['see_more_opt']['text'] = icl_t( 'Cookie Notice', 'Privacy policy text', $options['see_more_opt']['text'] );
			$options['see_more_opt']['link'] = icl_t( 'Cookie Notice', 'Custom link', $options['see_more_opt']['link'] );
		}

		if ( $options['see_more_opt']['link_type'] === 'page' ) {
			// multisite with global override?
			if ( is_multisite() && $this->is_plugin_network_active() && $this->network_options['global_override'] ) {
				// get main site id
				$main_site_id = get_main_site_id();

				// switch to main site
				switch_to_blog( $main_site_id );

				// update page id for current language if needed
				if ( function_exists( 'icl_object_id' ) )
					$options['see_more_opt']['id'] = icl_object_id( $options['see_more_opt']['id'], 'page', true );

				// get main site privacy policy link
				$permalink = get_permalink( $options['see_more_opt']['id'] );

				// restore current site
				restore_current_blog();
			} else {
				// update page id for current language if needed
				if ( function_exists( 'icl_object_id' ) )
					$options['see_more_opt']['id'] = icl_object_id( $options['see_more_opt']['id'], 'page', true );

				// get privacy policy link
				$permalink = get_permalink( $options['see_more_opt']['id'] );
			}
		}

		// defaults
		$defaults = [
			'title'	=> esc_html( $options['see_more_opt']['text'] !== '' ? $options['see_more_opt']['text'] : '&#x279c;' ),
			'link'	=> ( $options['see_more_opt']['link_type'] === 'custom' ? esc_url( $options['see_more_opt']['link'] ) : esc_url( $permalink ) ),
			'class'	=> esc_attr( $options['css_class'] )
		];

		// combine shortcode arguments
		$args = shortcode_atts( $defaults, $args );

		$shortcode = '<a href="' . $args['link'] . '" target="' . $options['link_target'] . '" id="cn-more-info" class="cn-privacy-policy-link cn-link' . ( $args['class'] !== '' ? ' ' . $args['class'] : '' ) . '">' . esc_html( $args['title'] ) . '</a>';

		return $shortcode;
	}

	/**
	 * Check if cookies are accepted.
	 *
	 * @return bool
	 */
	public static function cookies_accepted() {
		if ( Cookie_Notice()->get_status() === 'active' ) {
			$cookies = isset( $_COOKIE['hu-consent'] ) ? json_decode( stripslashes( $_COOKIE['hu-consent'] ), true ) : [];

			if ( ! empty( $cookies ) && is_array( $cookies ) ) {
				foreach( $cookies as $cookie_name => $cookie_value ) {
					switch ( $cookie_name ) {
						case 'consent':
							$cookies[$cookie_name] = (bool) $cookie_value;
							break;

						default:
							$cookies[$cookie_name] = is_array( $cookie_value ) ? array_map( 'sanitize_text_field', $cookie_value ) : sanitize_text_field( $cookie_value );
					}
				}
			}

			$result = ( is_array( $cookies ) && json_last_error() === JSON_ERROR_NONE && ! empty( $cookies['consent'] ) );
		} else
			$result = isset( $_COOKIE['cookie_notice_accepted'] ) && $_COOKIE['cookie_notice_accepted'] === 'true';

		return apply_filters( 'cn_is_cookie_accepted', $result );
	}

	/**
	 * Check if cookies are set.
	 *
	 * @return boolean
	 */
	public static function cookies_set() {
		if ( Cookie_Notice()->get_status() === 'active' )
			$result = isset( $_COOKIE['hu-consent'] );
		else
			$result = isset( $_COOKIE['cookie_notice_accepted'] );

		return apply_filters( 'cn_is_cookie_set', $result );
	}

	/**
	 * Add WP Super Cache cookie.
	 *
	 * @return void
	 */
	public function wpsc_add_cookie() {
		if ( Cookie_Notice()->get_status() === 'active' )
			do_action( 'wpsc_add_cookie', 'hu-consent' );
		else
			do_action( 'wpsc_add_cookie', 'cookie_notice_accepted' );
	}

	/**
	 * Delete WP Super Cache cookie.
	 *
	 * @return void
	 */
	public function wpsc_delete_cookie() {
		if ( Cookie_Notice()->get_status() === 'active' )
			do_action( 'wpsc_delete_cookie', 'hu-consent' );
		else
			do_action( 'wpsc_delete_cookie', 'cookie_notice_accepted' );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $page
	 * @return void
	 */
	public function admin_enqueue_scripts( $page ) {
		// plugins?
		if ( $page === 'plugins.php' ) {
			add_thickbox();

			wp_enqueue_script( 'cookie-notice-admin-plugins', COOKIE_NOTICE_URL . '/js/admin-plugins.js', [ 'jquery' ], $this->defaults['version'] );

			wp_enqueue_style( 'cookie-notice-admin-plugins', COOKIE_NOTICE_URL . '/css/admin-plugins.css', [], $this->defaults['version'] );

			wp_localize_script(
				'cookie-notice-admin-plugins',
				'cnArgsPlugins',
				[
					'deactivate'	=> __( 'Cookie Notice & Compliance - Deactivation survey', 'cookie-notice' ),
					'nonce'			=> wp_create_nonce( 'cn-deactivate-plugin' )
				]
			);
		}

		// notice js and css
		wp_enqueue_script(
			'cookie-notice-admin-notice', COOKIE_NOTICE_URL . '/js/admin-notice.js', [ 'jquery' ], Cookie_Notice()->defaults['version']
		);

		wp_localize_script(
			'cookie-notice-admin-notice',
			'cnArgsNotice',
			[
				'ajaxURL'	=> admin_url( 'admin-ajax.php' ),
				'nonce'		=> wp_create_nonce( 'cn_dismiss_notice' ),
				'network'	=> (int) $this->is_network_admin()
			]
		);

		wp_enqueue_style(
			'cookie-notice-admin-notice', COOKIE_NOTICE_URL . '/css/admin-notice.css', [], Cookie_Notice()->defaults['version']
		);
	}

	/**
	 * Set plugin links.
	 *
	 * @return void
	 */
	public function set_plugin_links() {
		// filters
		add_filter( 'plugin_action_links', [ $this, 'plugin_action_links' ], 10, 2 );
		add_filter( 'network_admin_plugin_action_links', [ $this, 'plugin_action_links' ], 10, 2 );
	}

	/**
	 * Add links to settings page.
	 *
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	public function plugin_action_links( $links, $file ) {
		if ( ! current_user_can( apply_filters( 'cn_manage_cookie_notice_cap', 'manage_options' ) ) )
			return $links;

		if ( $file === COOKIE_NOTICE_BASENAME ) {
			if ( ! empty( $links['deactivate'] ) ) {
				// link already contains class attribute?
				if ( preg_match( '/<a.*?class=(\'|")(.*?)(\'|").*?>/is', $links['deactivate'], $result ) === 1 )
					$links['deactivate'] = preg_replace( '/(<a.*?class=(?:\'|").*?)((?:\'|").*?>)/s', '$1 cn-deactivate-plugin-modal$2', $links['deactivate'] );
				else
					$links['deactivate'] = preg_replace( '/(<a.*?)>/s', '$1 class="cn-deactivate-plugin-modal">', $links['deactivate'] );

				// link already contains href attribute?
				if ( preg_match( '/<a.*?href=(\'|")(.*?)(\'|").*?>/is', $links['deactivate'], $result ) === 1 ) {
					if ( ! empty( $result[2] ) )
						$this->deactivaion_url = $result[2];
				}
			}

			// skip settings link if plugin is activated from main site
			if ( ! ( $this->is_network_admin() && ! $this->is_plugin_network_active() ) ) {
				// put settings link at start
				array_unshift( $links, sprintf( '<a href="%s">%s</a>', $this->is_network_admin() ? network_admin_url( 'admin.php?page=cookie-notice' ) : admin_url( 'admin.php?page=cookie-notice' ), __( 'Settings', 'cookie-notice' ) ) );
			}

			// get cookie compliance status
			$status = $this->get_status();

			if ( is_multisite() ) {
				$check_status = empty( $status ) && ( ( $this->is_network_admin() && $this->is_plugin_network_active() && $this->network_options['global_override'] ) || ( ! $this->is_network_admin() && ( ( $this->is_plugin_network_active() && ! $this->network_options['global_override'] ) || ! $this->is_plugin_network_active() ) ) );
			} else
				$check_status = empty( $status );

			// add upgrade link
			if ( $check_status )
				$links[] = sprintf( '<a href="%s" style="color: #20C19E; font-weight: bold;">%s</a>', $this->is_network_admin() ? network_admin_url( 'admin.php?page=cookie-notice&welcome=1' ) : admin_url( 'admin.php?page=cookie-notice&welcome=1' ), __( 'Free Upgrade', 'cookie-notice' ) );
		}

		return $links;
	}

	/**
	 * Deactivation modal HTML template.
	 *
	 * @global string $pagenow
	 *
	 * @return void
	 */
	public function deactivate_plugin_template() {
		global $pagenow;

		// display only for plugins page
		if ( $pagenow !== 'plugins.php' )
			return;

		echo '
		<div id="cn-deactivation-modal" style="display: none;">
			<div id="cn-deactivation-container">
				<div id="cn-deactivation-body">
					<div class="cn-deactivation-options">
						<p><em>' . __( "We're sorry to see you go. Could you please tell us what happened?", 'cookie-notice' ) . '</em></p>
						<ul>';

		foreach ( [
				'1'	=> __( "I couldn't figure out how to make it work.", 'cookie-notice' ),
				'2'	=> __( 'I found another plugin to use for the same task.', 'cookie-notice' ),
				'3'	=> __( 'The Cookie Compliance banner is too big.', 'cookie-notice' ),
				'4'	=> __( 'The Cookie Compliance consent choices (Silver, Gold, Platinum) are confusing.', 'cookie-notice' ),
				'5'	=> __( 'The Cookie Compliance default settings are too strict.', 'cookie-notice' ),
				'6'	=> __( 'The web application user interface is not clear to me.', 'cookie-notice' ),
				'7'	=> __( "Support isn't timely.", 'cookie-notice' ),
				'8'	=> __( 'Other', 'cookie-notice' )
		] as $option => $text ) {
			echo '
							<li><label><input type="radio" name="cn_deactivation_option" value="' . $option . '" ' . checked( '8', $option, false ) . ' />' . esc_html( $text ) . '</label></li>';
			}

		echo '
						</ul>
					</div>
					<div class="cn-deactivation-textarea">
						<textarea name="cn_deactivation_other"></textarea>
					</div>
				</div>
				<div id="cn-deactivation-footer">
					<a href="" class="button cn-deactivate-plugin-cancel">' . __( 'Cancel', 'cookie-notice' ) . '</a>
					<a href="' . $this->deactivaion_url . '" class="button button-secondary cn-deactivate-plugin-simple">' . __( 'Deactivate', 'cookie-notice' ) . '</a>
					<a href="' . $this->deactivaion_url . '" class="button button-primary right cn-deactivate-plugin-data">' . __( 'Deactivate & Submit', 'cookie-notice' ) . '</a>
					<span class="spinner"></span>
				</div>
			</div>
		</div>';
	}

	/**
	 * Send data about deactivation of the plugin.
	 *
	 * @return void
	 */
	public function deactivate_plugin() {
		// check permissions
		if ( ! current_user_can( 'install_plugins' ) || wp_verify_nonce( $_POST['nonce'], 'cn-deactivate-plugin' ) === false )
			return;

		if ( isset( $_POST['option_id'] ) ) {
			$option_id = (int) $_POST['option_id'];
			$other = esc_html( $_POST['other'] );

			// avoid fake submissions
			if ( $option_id == 8 && $other == '' )
				wp_send_json_success();

			wp_remote_post(
				'https://hu-manity.co/wp-json/api/v1/forms/',
				[
					'timeout'		=> 15,
					'blocking'		=> true,
					'headers'		=> [],
					'body'			=> [
						'id'		=> 1,
						'option'	=> $option_id,
						'other'		=> $other,
						'referrer'	=> get_site_url()
					]
				]
			);

			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * Get allowed script blocking HTML.
	 *
	 * @return array
	 */
	public function get_allowed_html() {
		return apply_filters(
			'cn_refuse_code_allowed_html',
			array_merge(
				wp_kses_allowed_html( 'post' ),
				[
					'script' => [
						'type' => [],
						'src' => [],
						'charset' => [],
						'async' => []
					],
					'noscript' => [],
					'style' => [
						'type' => []
					],
					'iframe' => [
						'src' => [],
						'height' => [],
						'width' => [],
						'frameborder' => [],
						'allowfullscreen' => []
					]
				]
			)
		);
	}

	/**
	 * Helper: convert hex color to rgb color.
	 *
	 * @param type $color
	 * @return bool|array
	 */
	public function hex2rgb( $color ) {
		if ( $color[0] == '#' )
			$color = substr( $color, 1 );

		if ( strlen( $color ) == 6 )
			list( $r, $g, $b ) = [ $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] ];
		elseif ( strlen( $color ) == 3 )
			list( $r, $g, $b ) = [ $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] ];
		else
			return false;

		$r = hexdec( $r );
		$g = hexdec( $g );
		$b = hexdec( $b );

		return [ $r, $g, $b ];
	}

	/**
	 * Helper: Convert undersocores to CamelCase.
	 *
	 * @param string $string
	 * @param bool $capitalize_first_char
	 * @return string
	 */
	public function underscores_to_camelcase( $string, $capitalize_first_char = false ) {
		$str = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $string ) ) );

		if ( ! $capitalize_first_char ) {
			$str[0] = strtolower( $str[0] );
		}

		return $str;
	}

	/**
	 * Check legacy parameters that were yes/no strings.
	 *
	 * @param array $options
	 * @param array $params
	 * @return array
	 */
	public function check_legacy_params( $options, $params ) {
		if ( is_array( $options ) ) {
			foreach ( $params as $param ) {
				if ( array_key_exists( $param, $options ) && ! is_bool( $options[$param] ) )
					$options[$param] = $options[$param] === 'yes';
			}
		}

		return $options;
	}

	/**
	 * Merge multidimensional associative arrays.
	 * Works only with strings, integers and arrays as keys. Values can be any type but they have to have same type to be kept in the final array.
	 * Every array should have the same type of elements. Only keys from $defaults array will be kept in the final array unless $siblings are not empty.
	 * $siblings examples: array( '=>', 'only_first_level', 'first_level=>second_level', 'first_key=>next_key=>sibling' ) and so on.
	 * Single '=>' means that all siblings of the highest level will be kept in the final array.
	 *
	 * @param array	$defaults Array with defaults values
	 * @param array	$array Array to merge
	 * @param boolean|array	$siblings Whether to allow "string" siblings to copy from $array if they do not exist in $defaults, false otherwise
	 * @return array
	 */
	public function multi_array_merge( $defaults, $array, $siblings = false ) {
		// make a copy for better performance and to prevent $default override in foreach
		$copy = $defaults;

		// prepare siblings for recursive deeper level
		$new_siblings = [];

		// allow siblings?
		if ( ! empty( $siblings ) && is_array( $siblings ) ) {
			foreach ( $siblings as $sibling ) {
				// highest level siblings
				if ( $sibling === '=>' ) {
					// copy all non-existent string siblings
					foreach( $array as $key => $value ) {
						if ( is_string( $key ) && ! array_key_exists( $key, $defaults ) ) {
							$defaults[$key] = null;
						}
					}
				// sublevel siblings
				} else {
					// explode siblings
					$ex = explode( '=>', $sibling );

					// copy all non-existent siblings
					foreach ( array_keys( $array[$ex[0]] ) as $key ) {
						if ( ! array_key_exists( $key, $defaults[$ex[0]] ) )
							$defaults[$ex[0]][$key] = null;
					}

					// more than one sibling child?
					if ( count( $ex ) > 1 )
						$new_siblings[$ex[0]] = [ substr_replace( $sibling, '', 0, strlen( $ex[0] . '=>' ) ) ];
					// no more sibling children
					else
						$new_siblings[$ex[0]] = false;
				}
			}
		}

		// loop through first array
		foreach ( $defaults as $key => $value ) {
			// integer key?
			if ( is_int( $key ) ) {
				$copy = array_unique( array_merge( $defaults, $array ), SORT_REGULAR );

				break;
			// string key?
			} elseif ( is_string( $key ) && isset( $array[$key] ) ) {
				// string, boolean, integer or null values?
				if ( ( is_string( $value ) && is_string( $array[$key] ) ) || ( is_bool( $value ) && is_bool( $array[$key] ) ) || ( is_int( $value ) && is_int( $array[$key] ) ) || is_null( $value ) )
					$copy[$key] = $array[$key];
				// arrays
				elseif ( is_array( $value ) && isset( $array[$key] ) && is_array( $array[$key] ) ) {
					if ( empty( $value ) )
						$copy[$key] = $array[$key];
					else
						$copy[$key] = $this->multi_array_merge( $defaults[$key], $array[$key], ( isset( $new_siblings[$key] ) ? $new_siblings[$key] : false ) );
				}
			}
		}

		return $copy;
	}

	/**
	 * Indicate if current page is the Cookie Policy page.
	 *
	 * @return bool
	 */
	public function is_cookie_policy_page() {
		$see_more = $this->options['general']['see_more_opt'];

		if ( $see_more['link_type'] !== 'page' )
			return false;

		$cp_id = $see_more['id'];
		$cp_slug = get_post_field( 'post_name', $cp_id );

		$current_page = sanitize_post( $GLOBALS['wp_the_query']->get_queried_object() );

		return $current_page->post_name === $cp_slug;
	}
}

/**
 * Initialize Cookie Notice.
 */
function Cookie_Notice() {
	static $instance;

	// first call to instance() initializes the plugin
	if ( $instance === null || ! ( $instance instanceof Cookie_Notice ) )
		$instance = Cookie_Notice::instance();

	return $instance;
}

$cookie_notice = Cookie_Notice();