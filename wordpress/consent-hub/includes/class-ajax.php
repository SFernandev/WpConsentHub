<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CH_AJAX {

	/**
	 * Initialize AJAX handlers.
	 */
	public static function init() {
		// Log consent from frontend (no auth required, only nonce)
		add_action( 'wp_ajax_nopriv_ch_log_consent', array( __CLASS__, 'log_consent' ) );
		add_action( 'wp_ajax_ch_log_consent', array( __CLASS__, 'log_consent' ) );

		// Diagnostic endpoint (admin only)
		add_action( 'wp_ajax_ch_debug', array( __CLASS__, 'debug_status' ) );

		// Add nonce to frontend config
		add_filter( 'ch_frontend_config', array( __CLASS__, 'add_nonce_to_config' ) );
	}

	/**
	 * Debug endpoint: check logging status (admin only).
	 */
	public static function debug_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$settings = get_option( 'ch_settings', array() );
		$table_exists = CH_Database::table_exists();
		$table_name = CH_Database::table_name();
		$logs_count = $table_exists ? CH_Database::get_total_logs() : 'N/A';

		wp_send_json_success( array(
			'table_name'      => $table_name,
			'table_exists'    => $table_exists,
			'logging_enabled' => ! empty( $settings['logging_enabled'] ),
			'logs_count'      => $logs_count,
			'settings_raw'    => $settings,
		) );
	}

	/**
	 * Add AJAX nonce to frontend config.
	 *
	 * @param array $config
	 * @return array
	 */
	public static function add_nonce_to_config( $config ) {
		$config['logging_nonce'] = wp_create_nonce( 'ch_logging' );
		return $config;
	}

	/**
	 * AJAX endpoint: log consent event.
	 *
	 * POST data:
	 * - type: 'accepted' | 'rejected' | 'partial'
	 * - categories: ['analytics', 'marketing', 'preferences'] (for 'partial')
	 * - nonce: CSRF token
	 */
	public static function log_consent() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ch_logging' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'consent-hub' ) ), 403 );
		}

		// Get settings to check if logging is enabled
		$settings = get_option( 'ch_settings', array() );
		if ( empty( $settings['logging_enabled'] ) ) {
			wp_send_json_success();
		}

		// Validate type
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		if ( ! in_array( $type, array( 'accepted', 'rejected', 'partial' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid consent type', 'consent-hub' ) ), 400 );
		}

		// Parse categories
		$categories = array();
		if ( isset( $_POST['categories'] ) ) {
			$raw = wp_unslash( $_POST['categories'] );
			if ( is_string( $raw ) ) {
				$parsed = json_decode( $raw, true );
				if ( is_array( $parsed ) ) {
					$categories = array_map( 'sanitize_text_field', $parsed );
				}
			}
		}

		// Get region (client-detected or from config)
		$region = isset( $_POST['region'] ) ? sanitize_text_field( $_POST['region'] ) : 'other';

		// Log it
		CH_Database::log( $type, $categories, $region );

		wp_send_json_success( array( 'message' => __( 'Logged', 'consent-hub' ) ) );
	}
}
