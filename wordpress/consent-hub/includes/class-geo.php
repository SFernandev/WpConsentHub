<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Geo-detection for consent behavior.
 *
 * Detection methods (in priority order):
 *   1. Cloudflare CF-IPCountry header
 *   2. Other CDN headers (Vercel, AWS CloudFront, Sucuri, etc.)
 *   3. Manual override from admin settings
 *
 * To use GeoLite2 for server-level detection without a CDN,
 * install a PHP IP lookup plugin and use the ch_geo_region filter.
 */
class CH_Geo {

	/**
	 * EU/EEA country codes (GDPR applies).
	 */
	private static $eu_countries = array(
		'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
		'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
		'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
		// EEA
		'IS', 'LI', 'NO',
		// UK GDPR
		'GB',
		// Switzerland (similar rules)
		'CH',
	);

	/**
	 * US states with comprehensive privacy laws (CCPA/CPRA + others).
	 * Used when country is US and state-level detection is available.
	 */
	private static $ccpa_states = array(
		'CA', 'CO', 'CT', 'DE', 'IN', 'IA', 'MT', 'NE', 'NH', 'NJ',
		'OR', 'TN', 'TX', 'UT', 'VA',
	);

	/**
	 * Detect visitor region.
	 *
	 * @return string 'eu' | 'ccpa' | 'other'
	 */
	public static function detect() {
		$s = get_option( 'ch_settings', ch_default_settings() );

		// If geo is disabled or manual override is set
		if ( empty( $s['geo_enabled'] ) ) {
			return '';
		}

		if ( ! empty( $s['geo_override'] ) && $s['geo_override'] !== 'auto' ) {
			return $s['geo_override'];
		}

		// Try to detect from headers
		$country = self::get_country_code();

		if ( ! $country ) {
			return apply_filters( 'ch_geo_region', $s['geo_default'] ?? 'other' );
		}

		$country = strtoupper( $country );

		// EU/EEA check
		if ( in_array( $country, self::$eu_countries, true ) ) {
			return apply_filters( 'ch_geo_region', 'eu' );
		}

		// US — assume CCPA applies (conservative approach)
		if ( $country === 'US' ) {
			// If state-level detection is available
			$state = self::get_state_code();
			if ( $state && in_array( strtoupper( $state ), self::$ccpa_states, true ) ) {
				return apply_filters( 'ch_geo_region', 'ccpa' );
			}
			// Even without state, treat US as CCPA for safety
			return apply_filters( 'ch_geo_region', 'ccpa' );
		}

		// Brazil (LGPD — similar to GDPR opt-in)
		if ( $country === 'BR' ) {
			return apply_filters( 'ch_geo_region', 'eu' );
		}

		return apply_filters( 'ch_geo_region', 'other' );
	}

	/**
	 * Get country code from CDN/proxy headers.
	 */
	private static function get_country_code() {
		$headers = array(
			'HTTP_CF_IPCOUNTRY',         // Cloudflare
			'HTTP_X_COUNTRY_CODE',       // Generic CDN
			'HTTP_X_VERCEL_IP_COUNTRY',  // Vercel
			'HTTP_CLOUDFRONT_VIEWER_COUNTRY', // AWS CloudFront
			'HTTP_X_APPENGINE_COUNTRY',  // Google App Engine
			'HTTP_X_SUCURI_COUNTRY',     // Sucuri
			'GEOIP_COUNTRY_CODE',        // Apache mod_geoip / nginx
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$code = sanitize_text_field( $_SERVER[ $header ] );
				if ( strlen( $code ) === 2 && $code !== 'XX' && $code !== 'T1' ) {
					return $code;
				}
			}
		}

		return '';
	}

	/**
	 * Get state/region code (Cloudflare only for now).
	 */
	private static function get_state_code() {
		if ( ! empty( $_SERVER['HTTP_CF_REGION_CODE'] ) ) {
			return sanitize_text_field( $_SERVER['HTTP_CF_REGION_CODE'] );
		}
		return '';
	}
}
