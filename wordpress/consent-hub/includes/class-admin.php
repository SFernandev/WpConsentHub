<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CH_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . CH_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=consent-hub' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Ajustes</a>' );
		return $links;
	}

	public static function add_menu() {
		add_options_page(
			'ConsentHub',
			'ConsentHub',
			'manage_options',
			'consent-hub',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue( $hook ) {
		if ( $hook !== 'settings_page_consent-hub' ) return;
		wp_enqueue_style( 'consent-hub-admin', CH_URL . 'assets/admin.css', array(), CH_VERSION );
	}

	public static function register_settings() {
		register_setting( 'ch_settings_group', 'ch_settings', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	public static function sanitize( $input ) {
		$defaults = ch_default_settings();
		$clean    = array();

		// Text fields
		$text_keys = array(
			'position', 'primary', 'primary_text', 'background', 'text_color',
			'text_secondary', 'border', 'toggle_on', 'toggle_off', 'radius',
			'cat_analytics_label', 'cat_analytics_desc',
			'cat_marketing_label', 'cat_marketing_desc',
			'cat_preferences_label', 'cat_preferences_desc',
			'banner_title', 'banner_description',
			'btn_accept', 'btn_reject', 'btn_customize',
			'prefs_title', 'prefs_description', 'btn_save', 'revisit_label',
			'gcm_mode',
			'geo_override', 'geo_default', 'geo_rule_eu', 'geo_rule_ccpa', 'geo_rule_other',
		);

		foreach ( $text_keys as $key ) {
			$clean[ $key ] = isset( $input[ $key ] )
				? sanitize_text_field( $input[ $key ] )
				: $defaults[ $key ];
		}

		// Booleans (checkbox: present = true, absent = false)
		$bool_keys = array(
			'auto_show', 'gcm_enabled', 'gcm_url_passthrough',
			'gcm_ads_redaction', 'blocker_enabled', 'geo_enabled',
		);
		foreach ( $bool_keys as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] );
		}

		// Integers
		$clean['gcm_wait_update'] = isset( $input['gcm_wait_update'] )
			? absint( $input['gcm_wait_update'] )
			: $defaults['gcm_wait_update'];

		// Validate position
		$valid_positions = array( 'bottom', 'top', 'center' );
		if ( ! in_array( $clean['position'], $valid_positions, true ) ) {
			$clean['position'] = 'bottom';
		}

		return $clean;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$s = get_option( 'ch_settings', ch_default_settings() );
		$s = wp_parse_args( $s, ch_default_settings() );
		?>
		<div class="ch-admin-wrap">
			<div class="ch-admin-header">
				<h1>ConsentHub <span class="ch-version">v<?php echo esc_html( CH_VERSION ); ?></span></h1>
				<p>Motor de consentimiento de cookies · GCM v2 · Script blocker · 100% self-hosted</p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'ch_settings_group' ); ?>

				<!-- Banner -->
				<div class="ch-section">
					<h2>Banner</h2>
					<div class="ch-field">
						<label>Posición</label>
						<select name="ch_settings[position]">
							<option value="bottom" <?php selected( $s['position'], 'bottom' ); ?>>Abajo</option>
							<option value="top" <?php selected( $s['position'], 'top' ); ?>>Arriba</option>
							<option value="center" <?php selected( $s['position'], 'center' ); ?>>Centro (modal)</option>
						</select>
					</div>
					<div class="ch-field">
						<label>Mostrar automáticamente</label>
						<input type="checkbox" name="ch_settings[auto_show]" value="1" <?php checked( $s['auto_show'] ); ?>>
						<span class="ch-hint">Si no, deberás llamar a ConsentHub.showBanner() manualmente.</span>
					</div>
				</div>

				<!-- Texts -->
				<div class="ch-section">
					<h2>Textos del banner</h2>
					<?php
					self::text_field( 'banner_title', 'Título', $s );
					self::textarea_field( 'banner_description', 'Descripción', $s );
					self::text_field( 'btn_accept', 'Botón aceptar', $s );
					self::text_field( 'btn_reject', 'Botón rechazar', $s );
					self::text_field( 'btn_customize', 'Botón configurar', $s );
					self::text_field( 'revisit_label', 'Botón revisitar', $s );
					?>
				</div>

				<div class="ch-section">
					<h2>Textos de preferencias</h2>
					<?php
					self::text_field( 'prefs_title', 'Título', $s );
					self::textarea_field( 'prefs_description', 'Descripción', $s );
					self::text_field( 'btn_save', 'Botón guardar', $s );
					?>
				</div>

				<!-- Categories -->
				<div class="ch-section">
					<h2>Categorías</h2>
					<?php
					$cats = array( 'analytics', 'marketing', 'preferences' );
					foreach ( $cats as $cat ) :
					?>
					<div class="ch-field-group">
						<h3><?php echo esc_html( ucfirst( $cat ) ); ?></h3>
						<?php
						self::text_field( 'cat_' . $cat . '_label', 'Nombre', $s );
						self::text_field( 'cat_' . $cat . '_desc', 'Descripción', $s );
						?>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Theme -->
				<div class="ch-section">
					<h2>Tema visual</h2>
					<div class="ch-color-grid">
						<?php
						self::color_field( 'primary', 'Color principal', $s );
						self::color_field( 'primary_text', 'Texto del botón', $s );
						self::color_field( 'background', 'Fondo', $s );
						self::color_field( 'text_color', 'Texto', $s );
						self::color_field( 'text_secondary', 'Texto secundario', $s );
						self::color_field( 'border', 'Bordes', $s );
						self::color_field( 'toggle_on', 'Toggle activo', $s );
						self::color_field( 'toggle_off', 'Toggle inactivo', $s );
						?>
					</div>
					<?php self::text_field( 'radius', 'Border radius', $s ); ?>
				</div>

				<!-- GCM -->
				<div class="ch-section">
					<h2>Google Consent Mode v2</h2>
					<div class="ch-field">
						<label>Activar GCM v2</label>
						<input type="checkbox" name="ch_settings[gcm_enabled]" value="1" <?php checked( $s['gcm_enabled'] ); ?>>
					</div>
					<div class="ch-field">
						<label>Modo</label>
						<select name="ch_settings[gcm_mode]">
							<option value="advanced" <?php selected( $s['gcm_mode'], 'advanced' ); ?>>Advanced (recomendado)</option>
							<option value="basic" <?php selected( $s['gcm_mode'], 'basic' ); ?>>Basic</option>
						</select>
						<span class="ch-hint">Advanced envía pings sin cookies para que Google pueda modelar conversiones.</span>
					</div>
					<div class="ch-field">
						<label>URL passthrough</label>
						<input type="checkbox" name="ch_settings[gcm_url_passthrough]" value="1" <?php checked( $s['gcm_url_passthrough'] ); ?>>
						<span class="ch-hint">Pasa GCLID/DCLID a través de enlaces internos.</span>
					</div>
					<div class="ch-field">
						<label>Ads data redaction</label>
						<input type="checkbox" name="ch_settings[gcm_ads_redaction]" value="1" <?php checked( $s['gcm_ads_redaction'] ); ?>>
					</div>
					<div class="ch-field">
						<label>Wait for update (ms)</label>
						<input type="number" name="ch_settings[gcm_wait_update]" value="<?php echo esc_attr( $s['gcm_wait_update'] ); ?>" min="0" max="10000" step="100">
						<span class="ch-hint">Tiempo que esperan los tags de Google antes de disparar si no hay respuesta.</span>
					</div>
				</div>

				<!-- Blocker -->
				<div class="ch-section">
					<h2>Script blocker inteligente</h2>
					<div class="ch-field">
						<label>Activar blocker</label>
						<input type="checkbox" name="ch_settings[blocker_enabled]" value="1" <?php checked( $s['blocker_enabled'] ); ?>>
						<span class="ch-hint">Intercepta scripts inyectados dinámicamente (Hotjar, Facebook Pixel, etc.).</span>
					</div>
				</div>

				<!-- Geo -->
				<div class="ch-section">
					<h2>Geolocalización</h2>
					<div class="ch-field">
						<label>Activar geo-detección</label>
						<input type="checkbox" name="ch_settings[geo_enabled]" value="1" <?php checked( $s['geo_enabled'] ); ?>>
						<span class="ch-hint">Detecta la región del visitante y adapta el comportamiento del banner.</span>
					</div>
					<div class="ch-field">
						<label>Detección</label>
						<select name="ch_settings[geo_override]">
							<option value="auto" <?php selected( $s['geo_override'], 'auto' ); ?>>Automática (cabeceras CDN)</option>
							<option value="eu" <?php selected( $s['geo_override'], 'eu' ); ?>>Forzar: UE/EEA</option>
							<option value="ccpa" <?php selected( $s['geo_override'], 'ccpa' ); ?>>Forzar: CCPA (EE.UU.)</option>
							<option value="other" <?php selected( $s['geo_override'], 'other' ); ?>>Forzar: Resto del mundo</option>
						</select>
						<span class="ch-hint">Auto usa Cloudflare, Vercel, CloudFront u otras cabeceras.</span>
					</div>
					<div class="ch-field">
						<label>Regla UE/EEA</label>
						<select name="ch_settings[geo_rule_eu]">
							<option value="optin" <?php selected( $s['geo_rule_eu'], 'optin' ); ?>>Opt-in (GDPR: requiere consentimiento)</option>
							<option value="optout" <?php selected( $s['geo_rule_eu'], 'optout' ); ?>>Opt-out</option>
						</select>
					</div>
					<div class="ch-field">
						<label>Regla CCPA (EE.UU.)</label>
						<select name="ch_settings[geo_rule_ccpa]">
							<option value="optout" <?php selected( $s['geo_rule_ccpa'], 'optout' ); ?>>Opt-out (permite por defecto, opción de rechazar)</option>
							<option value="optin" <?php selected( $s['geo_rule_ccpa'], 'optin' ); ?>>Opt-in</option>
						</select>
					</div>
					<div class="ch-field">
						<label>Regla resto del mundo</label>
						<select name="ch_settings[geo_rule_other]">
							<option value="hide" <?php selected( $s['geo_rule_other'], 'hide' ); ?>>Ocultar banner (permitir todo)</option>
							<option value="optin" <?php selected( $s['geo_rule_other'], 'optin' ); ?>>Opt-in</option>
							<option value="optout" <?php selected( $s['geo_rule_other'], 'optout' ); ?>>Opt-out</option>
						</select>
					</div>
					<div class="ch-field">
						<label>Fallback por defecto</label>
						<select name="ch_settings[geo_default]">
							<option value="other" <?php selected( $s['geo_default'], 'other' ); ?>>Resto del mundo</option>
							<option value="eu" <?php selected( $s['geo_default'], 'eu' ); ?>>UE (conservador)</option>
							<option value="ccpa" <?php selected( $s['geo_default'], 'ccpa' ); ?>>CCPA</option>
						</select>
						<span class="ch-hint">Si no se detecta la región, ¿qué regla aplicar?</span>
					</div>
				</div>

				<?php submit_button( 'Guardar cambios' ); ?>
			</form>
		</div>
		<?php
	}

	/* ── Field helpers ── */

	private static function text_field( $key, $label, $s ) {
		echo '<div class="ch-field">';
		echo '<label>' . esc_html( $label ) . '</label>';
		echo '<input type="text" name="ch_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[ $key ] ) . '">';
		echo '</div>';
	}

	private static function textarea_field( $key, $label, $s ) {
		echo '<div class="ch-field">';
		echo '<label>' . esc_html( $label ) . '</label>';
		echo '<textarea name="ch_settings[' . esc_attr( $key ) . ']" rows="3">' . esc_textarea( $s[ $key ] ) . '</textarea>';
		echo '</div>';
	}

	private static function color_field( $key, $label, $s ) {
		echo '<div class="ch-color-item">';
		echo '<label>' . esc_html( $label ) . '</label>';
		echo '<input type="color" name="ch_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[ $key ] ) . '">';
		echo '</div>';
	}
}
