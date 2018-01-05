<?php
/*
Plugin Name: Bullhorn Staffing and Recruitment Job Listing and CV/Resume uploader.
Plugin URI: https://github.com/pbearne/Bullhorn2WP
Description: This plugin adds Bullhorn jobs to a custom post (Job Listings) for front-end display. New and deleted jobs are synchronized every hour with Bullhorn. This is a 'pull' process only - any job created locally is not pushed to Bullhorn! Any theme developed on top of this plugin should have archive-job-listing.php and single-job-listing.php template files if a special layout is needed. Pull requests are accepted at https://github.com/pbearne/Bullhorn2WP
Version: 2.5.0
Author: Paul Bearne
Author URI: https://github.com/pbearne/
License: GPL2
Text Domain: bh-staffing-job-listing-and-cv-upload-for-wp
Domain Path: /languages
*/

/*
WP CRON SYNC: A new WP Cron Schedule was added (every 10 minutes), which is used to run the job sync (Bullhorn->WP CPT). The scheduling of WP Cron requires user interaction on the WordPress installation, if a WP Cron job is scheduled to run every 10 minutes but no pages have been loaded for 15 minutes, the job will be run immediately during the next page load.
WP CPT: This plugin adds the custom post type bullhornjoblisting, with a URL slug of /open-positions/ with archive turned on at that address and single job listings displayed at /open-positions/{post-slug}.
SYNCHRONIZING: Jobs are pulled in to the custom post type, and additional information is stored as meta data (jobOrderID, createdDate, employmentType, and employmentType).
BULLHORN: The Bullhorn API has a limit of 20 posts per request, this plugin currently loops through 1 entry at a time to avoid this limit and make the code simpler (but perhaps slower than some alternatives).
THEME DISPLAY: This plugin should be accompanied by two template files in the active theme's root directory.
LIMITS: WP Cron sync schedule should not be shortened, elongate as necessary. Each run of the synchronizer will only handle 500 posts at a time. If there are more than 500 needing deletion this will span multiple synchronization runs. There is also currently a limit of 120 jobs set, this plugin may support over 1000 but could suffer from unexpected behavior at anything above the current limit. Unlike the post limit this will not be surpassed during immediately preceeding runs of the synchronization; 120 of the newest jobs are pulled from Bullhorn, and only as newer jobs are added will they be pulled into WordPress. Therefore: There will end up being more than 120 jobs listed if more than 120 have existed and have yet to be deleted over the span of time in which this plugin was running.
DISABLING: If you disable this plugin and want to restore the original /open-positions page, go to Settings > Permalinks and click Save Changes after disabling (this will refresh the WP Rewrite cache).
*/

require_once ABSPATH . 'wp-admin/includes/plugin.php';

class Bullhorn_2_WP {

	static $post_type_job_listing = 'bullhornjoblisting';
	static $post_type_application = 'bullhornapplication';
	static $taxonomy_listing_category = 'bullhorn_category';
	static $taxonomy_listing_state = 'bullhorn_state';
	static $taxonomy_listing_type = null;
	static $taxonomy_listing_skills = 'bullhorn_skills';
	static $taxonomy_listing_certifications = 'bullhorn_certifications';


	static $mode = 'plugin';

	public function __construct() {

		if ( is_plugin_active( 'wp-job-manager/wp-job-manager.php' ) ) {
			self::$mode = 'wp-job-manager-addon';

			self::$post_type_job_listing  = 'job_listing';
			self::$post_type_application  = 'bullhornapplication';
			self::$taxonomy_listing_state = 'bullhorn_state';

			self::$taxonomy_listing_type = 'job_listing_type';

			if ( '1' === get_option( 'job_manager_enable_categories' ) ) {
				self::$taxonomy_listing_category = 'job_listing_category';
			}
		}

		self::load();

		add_action( 'plugins_loaded', array( __CLASS__, 'bullhorn_load_plugin_textdomain' ) );
		register_activation_hook( __FILE__, array( __CLASS__, 'bullhorn_activation_hook' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'bullhorn_deactivation_hook' ) );
		
		add_action( 'admin_notices', array( __CLASS__, 'bullhorn_deprecated_plugin_notice' ) );
		
	}

	
	public static function bullhorn_deprecated_plugin_notice(){
	printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr(  'warning' ),
		wp_kses_post( '<strong>The "Bullhorn Staffing and Recruitment Job Listing and CV/Resume uploader" plugin has been completely re-written. The newly written version will be installed by the next update <a href="https://matadorjobs.com/legacy-installs/">Learn more why did this here</a>. </strong> <br />We have tried to make sure that the upgrade is seamless but suggest you upgrade a dev site first.<br />
 A pro version of the plugin is now available at <a href="https://matadorjobs.com/product/pricing/">MatadorJobs.com</a>.<br /> The Matador plugin is packed full of <a href="https://matadorjobs.com/product/features/">new features</a>, including support for Google Jobs Search and automatic reconnection to the Bullhorn API, many more shortcode options and <a href="https://matadorjobs.com/product/add-ons/">add-ons</a>.')
	);
}

	function bullhorn_load_plugin_textdomain() {
		load_plugin_textdomain( 'bh-staffing-job-listing-and-cv-upload-for-wp', false, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	public static function load() {

		require_once 'bullhorn-connection.php';
		require_once 'settings.php';
		require_once 'cron.php';
		require_once 'shortcodes.php';
		require_once 'bullhorn-cv.php';
		require_once 'application-email.php';
		require_once 'custom-post-type.php';

		if ( 'wp-job-manager-addon' === self::$mode ) {
			require_once 'wp-job-manager-addon.php';
			require_once 'wp-job-manager-custom-post-type.php';
		}

		new Bullhorn_Settings;
		new \bullhorn_2_wp\Shortcodes;
		new Bullhorn_Extended_Connection;
		new Appication_Email;

		if ( 'plugin' === self::$mode ) {
			new Bullhorn_Custom_Post_Type;
		} else if ( 'wp-job-manager-addon' === self::$mode ) {
			new Bullhorn_WP_Job_Manager_Addon;
			new Bullhorn_WP_Job_Manager_Custom_Post_Type;
		}
	}

	/**
	 * flush rewrtie on activation
	 */
	public static function bullhorn_activation_hook() {
		flush_rewrite_rules();
	}

	/**
	 * remove cron on deactivation
	 * flush rewrtie on deactivation
	 */
	public static function bullhorn_deactivation_hook() {
		wp_clear_scheduled_hook( 'bullhorn_event' );
		wp_clear_scheduled_hook( 'bullhorn_appication_sync' );
		flush_rewrite_rules();
	}

}

new Bullhorn_2_WP;
