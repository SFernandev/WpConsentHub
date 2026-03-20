<?php
/**
 * Plugin Name:  ConsentHub
 * Plugin URI:   https://github.com/your-user/consent-hub
 * Description:  Motor de consentimiento de cookies con Google Consent Mode v2 y script blocker inteligente. Sin llamadas externas, sin SaaS, 100% self-hosted.
 * Version:      1.3.0
 * Author:       ConsentHub
 * License:      GPL-2.0-or-later
 * Text Domain:  consent-hub
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CH_VERSION', '1.3.0' );
define( 'CH_PATH', plugin_dir_path( __FILE__ ) );
define( 'CH_URL', plugin_dir_url( __FILE__ ) );
define( 'CH_BASENAME', plugin_basename( __FILE__ ) );

require_once CH_PATH . 'includes/class-frontend.php';
require_once CH_PATH . 'includes/class-admin.php';
require_once CH_PATH . 'includes/class-wp-consent.php';
require_once CH_PATH . 'includes/class-geo.php';
require_once CH_PATH . 'includes/class-database.php';
require_once CH_PATH . 'includes/class-ajax.php';
require_once CH_PATH . 'includes/class-dashboard.php';

/**
 * Load text domain for translations.
 */
function ch_load_textdomain() {
	load_plugin_textdomain( 'consent-hub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'ch_load_textdomain' );

/**
 * Initialize plugin.
 */
function ch_init() {
	CH_Frontend::init();
	CH_WP_Consent::init();
	CH_AJAX::init();

	if ( is_admin() ) {
		CH_Admin::init();
		CH_Dashboard::init();
	}
}
add_action( 'plugins_loaded', 'ch_init' );

/**
 * Activation: set default options and create database table.
 */
function ch_activate() {
	if ( get_option( 'ch_settings' ) === false ) {
		update_option( 'ch_settings', ch_default_settings() );
	}
	CH_Database::create_table();

	// Schedule daily cleanup cron
	if ( ! wp_next_scheduled( 'ch_daily_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'ch_daily_cleanup' );
	}
}
register_activation_hook( __FILE__, 'ch_activate' );

/**
 * Deactivation: remove scheduled cron.
 */
function ch_deactivate() {
	wp_clear_scheduled_hook( 'ch_daily_cleanup' );
}
register_deactivation_hook( __FILE__, 'ch_deactivate' );

/**
 * Daily cron: clean up old consent logs.
 */
function ch_cron_cleanup() {
	$s = get_option( 'ch_settings', ch_default_settings() );
	if ( ! empty( $s['logging_enabled'] ) ) {
		$days = isset( $s['logging_retention'] ) ? absint( $s['logging_retention'] ) : 90;
		CH_Database::cleanup( $days );
	}
}
add_action( 'ch_daily_cleanup', 'ch_cron_cleanup' );

/**
 * Default settings.
 */
function ch_default_settings() {
	return array(
		'position'     => 'bottom',
		'auto_show'    => true,

		// Theme
		'primary'       => '#1a1a1a',
		'primary_text'  => '#ffffff',
		'background'    => '#ffffff',
		'text_color'    => '#1a1a1a',
		'text_secondary'=> '#555555',
		'border'        => '#e0e0e0',
		'toggle_on'     => '#1a1a1a',
		'toggle_off'    => '#cccccc',
		'radius'        => '12px',

		// Categories
		'cat_analytics_label'   => __( 'Analítica', 'consent-hub' ),
		'cat_analytics_desc'    => __( 'Cookies de medición y estadísticas del sitio.', 'consent-hub' ),
		'cat_marketing_label'   => __( 'Marketing', 'consent-hub' ),
		'cat_marketing_desc'    => __( 'Cookies para campañas publicitarias y remarketing.', 'consent-hub' ),
		'cat_preferences_label' => __( 'Preferencias', 'consent-hub' ),
		'cat_preferences_desc'  => __( 'Cookies que recuerdan ajustes como idioma o región.', 'consent-hub' ),

		// Texts
		'banner_title'       => __( 'Este sitio utiliza cookies', 'consent-hub' ),
		'banner_description' => __( 'Usamos cookies para mejorar tu experiencia, analizar el tráfico y personalizar contenido. Puedes aceptar todas, rechazarlas o configurar tus preferencias.', 'consent-hub' ),
		'btn_accept'         => __( 'Aceptar todas', 'consent-hub' ),
		'btn_reject'         => __( 'Rechazar todas', 'consent-hub' ),
		'btn_customize'      => __( 'Configurar', 'consent-hub' ),
		'prefs_title'        => __( 'Preferencias de cookies', 'consent-hub' ),
		'prefs_description'  => __( 'Selecciona qué categorías de cookies deseas permitir. Las cookies funcionales son siempre necesarias.', 'consent-hub' ),
		'btn_save'           => __( 'Guardar preferencias', 'consent-hub' ),
		'revisit_label'      => __( 'Cookies', 'consent-hub' ),

		// Google Consent Mode
		'gcm_enabled'         => false,
		'gcm_mode'            => 'advanced',
		'gcm_url_passthrough' => true,
		'gcm_ads_redaction'   => true,
		'gcm_wait_update'     => 500,

		// Logging
		'logging_enabled'   => false,
		'logging_retention' => 90,

		// Blocker
		'blocker_enabled'  => false,

		// Geo
		'geo_enabled'   => false,
		'geo_override'  => 'auto',
		'geo_default'   => 'other',
		'geo_rule_eu'   => 'optin',
		'geo_rule_ccpa' => 'optout',
		'geo_rule_other'=> 'hide',
	);
}
