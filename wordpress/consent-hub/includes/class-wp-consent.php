<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Integration with the WP Consent API plugin.
 *
 * Registers ConsentHub as a consent management provider and syncs
 * consent categories via JavaScript hooks.
 *
 * @see https://wordpress.org/plugins/wp-consent-api/
 */
class CH_WP_Consent {

	public static function init() {
		// Register as a consent management plugin
		add_filter( 'wp_get_consent_type', array( __CLASS__, 'set_consent_type' ) );

		// Declare compliance with the API
		$plugin = plugin_basename( CH_PATH . 'consent-hub.php' );
		add_filter( 'wp_consent_api_registered_' . $plugin, '__return_true' );

		// Enqueue JS bridge when WP Consent API is active
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_bridge' ), 20 );
	}

	/**
	 * Set consent type to opt-in (GDPR default).
	 */
	public static function set_consent_type( $type ) {
		return 'optin';
	}

	/**
	 * Enqueue the JS bridge that syncs ConsentHub consent to WP Consent API.
	 */
	public static function enqueue_bridge() {
		// Only enqueue if WP Consent API is active
		if ( ! function_exists( 'wp_set_consent' ) ) return;

		wp_enqueue_script(
			'consent-hub-wp-bridge',
			CH_URL . 'assets/wp-consent-bridge.js',
			array( 'consent-hub' ),
			CH_VERSION,
			true
		);
	}
}
