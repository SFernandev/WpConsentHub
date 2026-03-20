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

		// Add JS bridge when WP Consent API is active
		add_action( 'wp_footer', array( __CLASS__, 'output_bridge' ), 20 );
	}

	/**
	 * Set consent type to opt-in (GDPR default).
	 */
	public static function set_consent_type( $type ) {
		return 'optin';
	}

	/**
	 * Output JS bridge that syncs ConsentHub consent to WP Consent API.
	 */
	public static function output_bridge() {
		// Only output if WP Consent API is active
		if ( ! function_exists( 'wp_set_consent' ) ) return;
		?>
		<script>
		(function(){
			var map = {
				analytics: 'statistics',
				marketing: 'marketing',
				preferences: 'preferences'
			};

			function sync(consent) {
				if (!consent || !consent.categories || typeof wp_set_consent !== 'function') return;
				for (var cat in map) {
					if (consent.categories.hasOwnProperty(cat)) {
						wp_set_consent(map[cat], consent.categories[cat] ? 'allow' : 'deny');
					}
				}
				wp_set_consent('functional', 'allow');
			}

			document.addEventListener('consenthub:consent', function(e) { sync(e.detail); });
			document.addEventListener('consenthub:consent:existing', function(e) { sync(e.detail); });
			document.addEventListener('consenthub:consent:reset', function() {
				if (typeof wp_set_consent !== 'function') return;
				var cats = ['statistics', 'marketing', 'preferences'];
				for (var i = 0; i < cats.length; i++) wp_set_consent(cats[i], 'deny');
			});
		})();
		</script>
		<?php
	}
}
