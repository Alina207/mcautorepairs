<?php
namespace SiteGround_Central\Helper;

use SiteGround_Central\Updater\Updater;
use SiteGround_Central\Installer\Installer;
use SiteGround_Central\Importer\Importer;
use SiteGround_Central\Hooks\Hooks;
use SiteGround_Central\Pages\Themes;
use SiteGround_Central\Pages\Plugins;
use SiteGround_Central\Pages\Dashboard;
use SiteGround_Central\Pages\Wizard;
use SiteGround_Central\Rest\Rest;

/**
 * Helper functions and main initialization class.
 */
class Helper {

	/**
	 * List of pages where we will hide all notices.
	 *
	 * @var array List of pages.
	 *
	 * @since 1.0.0
	 */
	private $pages_without_notices = array(
		'edit.php',
		'post-new.php',
		'edit-tags.php',
		'themes.php',
		'nav-menus.php',
		'widgets.php',
		'edit-comments.php',
		'tools.php',
		'import.php',
		'export.php',
		'options-general.php',
		'options-writing.php',
		'options-reading.php',
		'options-discussion.php',
		'options-media.php',
		'options-permalink.php',
		'privacy.php',
		'update-core.php',
		'upload.php',
		'media-new.php',
		'theme-editor.php',
		'plugin-editor.php',
		'users.php',
		'user-new.php',
		'profile.php',
	);

	/**
	 * List of custom pages where we will hide all notices.
	 *
	 * @var array List of pages.
	 *
	 * @since 1.0.0
	 */
	private $siteground_pages = array(
		'custom-dashboard.php',
		'sg-cachepress',
		'sg-plugin-install.php',
		'caching',
		'ssl',
		'php-check',
	);

	/**
	 * Some plugins use custom post types and all_admin_notices to add pages and tabs to their admin page.
	 * We add them to that list so all plugin menus are shown as expected.
	 *
	 * @var array
	 *
	 * @since 2.0.4
	 */
	private $skip_notice_removal = array(
		// WooCommerce Memberships.
		'wc_user_membership',
		'wc_membership_plan',
		// EDD
		'download',
	);

	/**
	 * Create a new helper.
	 */
	public function __construct() {

		// Load the plugin textdomain.
		add_action( 'after_setup_theme', array( $this, 'load_textdomain' ), 9999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'hide_errors_and_notices' ) );
		add_action( 'wp_ajax_siteground_wizard_install_plugin', array( 'SiteGround_Central\Installer\Installer', 'install_from_dashboard' ) );
		add_action( 'wp_ajax_siteground_wizard_activate_plugin', array( 'SiteGround_Central\Installer\Installer', 'activate_from_dashboard' ) );

		// Initialize the Wizard.
		new Wizard();

		// Initialize Updater.
		new Updater();

		// Initialize the REST API.
		new Rest();

		// Add additional hooks to change plugins and themes behaviour.
		new Hooks();

		// Initialize the custom pages if not a multisite installation.
		if ( ! \is_multisite() ) {
			new Dashboard();
			new Themes();
			new Plugins();
		}
	}

	/**
	 * Hide all errors and notices on our custom dashboard.
	 *
	 * @since  1.0.0
	 */
	public function hide_errors_and_notices() {
		global $pagenow;

		if (
			( isset( $_GET['page'] ) && in_array( wp_unslash( $_GET['page'] ), $this->siteground_pages ) ) ||
			in_array( $pagenow, $this->pages_without_notices )
		) {
			remove_all_actions( 'network_admin_notices' );
			remove_all_actions( 'user_admin_notices' );
			remove_all_actions( 'admin_notices' );

			// Hide all error on our dashboard.
			if (
				isset( $_GET['page'] ) &&
				'custom-dashboard.php' === $_GET['page']
			) {
				error_reporting( 0 );
			}

			// Exit if we have known pages that need all_admin_notices/admin_notices for showing admin menus.
			if (
				isset( $_GET['post_type'] ) &&
				in_array( $_GET['post_type'], $this->skip_notice_removal )
			) {
				// Add EDD admin header.
				if (
					\is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) &&
					function_exists( 'edd_admin_header' )
				) {
					add_action( 'admin_notices', 'edd_admin_header', 1 );
				}
				return;
			}

			remove_all_actions( 'all_admin_notices' );
		}
	}

	/**
	 * Load the plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'siteground-wizard',
			false,
			'wordpress-starter/languages'
		);
	}

	/**
	 * Try to decode json string.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $maybe_json Maybe json string.
	 *
	 * @return json|false         Decoded json on success, false on failure.
	 */
	public static function maybe_json_decode( $maybe_json ) {
		$decoded_string = json_decode( $maybe_json, true );

		// Return decoded json.
		if ( json_last_error() === 0 ) {
			return $decoded_string;
		}

		// Json is invalid.
		return false;
	}

	/**
	 * Retrieve the server ip address.
	 *
	 * @since  1.0.0
	 *
	 * @return string $ip_address The server IP address.
	 */
	public static function get_ip_address() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip_address = $_SERVER['HTTP_CLIENT_IP']; // WPCS: sanitization ok.
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR']; // WPCS: sanitization ok.
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED'] ) ) {
			$ip_address = $_SERVER['HTTP_X_FORWARDED']; // WPCS: sanitization ok.
		} elseif ( ! empty( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
			$ip_address = $_SERVER['HTTP_FORWARDED_FOR']; // WPCS: sanitization ok.
		} elseif ( ! empty( $_SERVER['HTTP_FORWARDED'] ) ) {
			$ip_address = $_SERVER['HTTP_FORWARDED']; // WPCS: sanitization ok.
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address = $_SERVER['REMOTE_ADDR']; // WPCS: sanitization ok.
		} else {
			$ip_address = 'UNKNOWN';
		}

		return sanitize_text_field( wp_unslash( $ip_address ) );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'siteground-wizard-admin',
			\SiteGround_Central\URL . '/assets/css/admin.css',
			array(),
			\SiteGround_Central\VERSION,
			'all'
		);

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'siteground-wizard-dashboard',
			\SiteGround_Central\URL . '/assets/js/admin.js',
			array( 'jquery' ), // Dependencies.
			\SiteGround_Central\VERSION
		);

	}

	/**
	 * Checks if there are any updates available.
	 *
	 * @since  1.0.0
	 *
	 * @return bool True is any, false otherwise.
	 */
	public static function updates_available() {
		$themes             = get_theme_updates();
		$plugins            = get_plugin_updates();
		$core               = get_core_updates();
		$translations       = wp_get_translation_updates();
		$hide_notifications = get_option( 'siteground_wizard_hide_notifications', 'no' );
		$old_hash           = get_option( 'updates_available' );
		$new_hash           = md5( serialize( $themes ) . serialize( $plugins ) . serialize( $core[0]->response ) . serialize( $translations ) );

		// Check for new updates if the notifications are hidden.
		if ( 'yes' === $hide_notifications ) {
			// Display the update notice if there is a new update.
			if ( $old_hash !== $new_hash ) {
				return true;
			}
			// Hide the notice if the updates are the same
			// like when the notice section has been hidden.
			return false;
		}

		if (
			empty( $themes ) &&
			empty( $plugins ) &&
			empty( $translations ) &&
			'upgrade' !== $core[0]->response
		) {
			return false;
		}

		return true;
	}

	/**
	 * Send stats to siteground api.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $event The event that happend.
	 */
	public static function send_statistics( $event ) {
		$response = wp_remote_post(
			'https://wpwizardapi.siteground.com/statistics',
			array(
				'method'   => 'POST',
				'timeout'  => 45,
				'blocking' => true,
				'headers'  => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body' => json_encode(
					array( 'event' => $event )
				),
			)
		);
	}

	/**
	 * Checks if it's a shop website.
	 *
	 * @since  1.0.0
	 *
	 * @return boolean True/False
	 */
	public static function is_shop() {
		if (
			\is_plugin_active( 'woocommerce/woocommerce.php' ) &&
			'Storefront' === wp_get_theme()->Name
		) {
			return true;
		}

		return false;
	}
}
