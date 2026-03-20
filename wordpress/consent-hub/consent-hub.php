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
	// Create consent log table
	CH_Database::create_table();
}
register_activation_hook( __FILE__, 'ch_activate' );

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
		'cat_analytics_label'   => 'Analítica',
		'cat_analytics_desc'    => 'Cookies de medición y estadísticas del sitio.',
		'cat_marketing_label'   => 'Marketing',
		'cat_marketing_desc'    => 'Cookies para campañas publicitarias y remarketing.',
		'cat_preferences_label' => 'Preferencias',
		'cat_preferences_desc'  => 'Cookies que recuerdan ajustes como idioma o región.',

		// Texts
		'banner_title'       => 'Este sitio utiliza cookies',
		'banner_description' => 'Usamos cookies para mejorar tu experiencia, analizar el tráfico y personalizar contenido. Puedes aceptar todas, rechazarlas o configurar tus preferencias.',
		'btn_accept'         => 'Aceptar todas',
		'btn_reject'         => 'Rechazar todas',
		'btn_customize'      => 'Configurar',
		'prefs_title'        => 'Preferencias de cookies',
		'prefs_description'  => 'Selecciona qué categorías de cookies deseas permitir. Las cookies funcionales son siempre necesarias.',
		'btn_save'           => 'Guardar preferencias',
		'revisit_label'      => 'Cookies',

		// Google Consent Mode
		'gcm_enabled'         => false,
		'gcm_mode'            => 'advanced',
		'gcm_url_passthrough' => true,
		'gcm_ads_redaction'   => true,
		'gcm_wait_update'     => 500,

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
