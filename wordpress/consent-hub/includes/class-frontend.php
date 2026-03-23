<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CH_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 1 );
	}

	/**
	 * Enqueue CSS, JS, and attach inline config.
	 * wp_add_inline_script guarantees the init call runs
	 * immediately after consent-hub.min.js loads.
	 */
	public static function enqueue() {
		wp_enqueue_style(
			'consent-hub',
			CH_URL . 'assets/consent-hub.css',
			array(),
			CH_VERSION
		);

		wp_enqueue_script(
			'consent-hub',
			CH_URL . 'assets/consent-hub.min.js',
			array(),
			CH_VERSION,
			true
		);

		// Attach init call right after the engine script
		$config_js = self::build_config();
		wp_add_inline_script( 'consent-hub', $config_js, 'after' );
	}

	/**
	 * Build the ConsentHub.init() call from WP settings.
	 */
	private static function build_config() {
		$s = get_option( 'ch_settings', ch_default_settings() );
		$s = wp_parse_args( $s, ch_default_settings() );

		$config = array(
			'categories' => array(
				'analytics'   => array(
					'label'       => $s['cat_analytics_label'],
					'description' => $s['cat_analytics_desc'],
					'default'     => false,
				),
				'marketing'   => array(
					'label'       => $s['cat_marketing_label'],
					'description' => $s['cat_marketing_desc'],
					'default'     => false,
				),
				'preferences' => array(
					'label'       => $s['cat_preferences_label'],
					'description' => $s['cat_preferences_desc'],
					'default'     => false,
				),
			),
			'texts' => array(
				'banner' => array(
					'title'       => $s['banner_title'],
					'description' => $s['banner_description'],
					'acceptAll'   => $s['btn_accept'],
					'rejectAll'   => $s['btn_reject'],
					'customize'   => $s['btn_customize'],
				),
				'preferences' => array(
					'title'       => $s['prefs_title'],
					'description' => $s['prefs_description'],
					'save'        => $s['btn_save'],
					'acceptAll'   => $s['btn_accept'],
				),
				'revisit' => $s['revisit_label'],
			),
			'position' => $s['position'],
			'theme'    => array(
				'primary'       => $s['primary'],
				'primaryText'   => $s['primary_text'],
				'background'    => $s['background'],
				'text'          => $s['text_color'],
				'textSecondary' => $s['text_secondary'],
				'border'        => $s['border'],
				'toggleOn'      => $s['toggle_on'],
				'toggleOff'     => $s['toggle_off'],
				'radius'        => $s['radius'],
			),
			'autoShow' => (bool) $s['auto_show'],
			'gcm'      => array(
				'enabled'          => (bool) $s['gcm_enabled'],
				'mode'             => $s['gcm_mode'],
				'urlPassthrough'   => (bool) $s['gcm_url_passthrough'],
				'adsDataRedaction' => (bool) $s['gcm_ads_redaction'],
				'waitForUpdate'    => intval( $s['gcm_wait_update'] ),
			),
			'blocker'  => array(
				'enabled' => (bool) $s['blocker_enabled'],
			),
			'geo'      => array(
				'enabled' => (bool) $s['geo_enabled'],
				'region'  => CH_Geo::detect(),
				'rules'   => array(
					'eu'    => $s['geo_rule_eu'],
					'ccpa'  => $s['geo_rule_ccpa'],
					'other' => $s['geo_rule_other'],
				),
			),
			'logging'  => array(
				'enabled'  => (bool) $s['logging_enabled'],
				'endpoint' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ch_logging' ),
			),
		);

		$json = wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return 'ConsentHub.init(' . $json . ');';
	}
}
