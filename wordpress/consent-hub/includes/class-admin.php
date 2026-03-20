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
		$url = admin_url( 'admin.php?page=consent-hub' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Ajustes', 'consent-hub' ) . '</a>' );
		return $links;
	}

	public static function add_menu() {
		// Menu is registered by CH_Dashboard.
		// This is kept for the settings page slug 'consent-hub' to work.
	}

	public static function enqueue( $hook ) {
		if ( $hook !== 'consenthub_page_consent-hub' ) return;
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
			'auto_show', 'logging_enabled', 'gcm_enabled', 'gcm_url_passthrough',
			'gcm_ads_redaction', 'blocker_enabled', 'geo_enabled',
		);
		foreach ( $bool_keys as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] );
		}

		// Integers
		$clean['gcm_wait_update'] = isset( $input['gcm_wait_update'] )
			? absint( $input['gcm_wait_update'] )
			: $defaults['gcm_wait_update'];

		$clean['logging_retention'] = isset( $input['logging_retention'] )
			? absint( $input['logging_retention'] )
			: $defaults['logging_retention'];

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
				<p><?php esc_html_e( 'Motor de consentimiento de cookies · GCM v2 · Script blocker · 100% self-hosted', 'consent-hub' ); ?></p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'ch_settings_group' ); ?>

				<!-- Banner -->
				<div class="ch-section">
					<h2><?php esc_html_e( 'Banner', 'consent-hub' ); ?></h2>
					<div class="ch-field">
						<label><?php esc_html_e( 'Posición', 'consent-hub' ); ?></label>
						<select name="ch_settings[position]">
							<option value="bottom" <?php selected( $s['position'], 'bottom' ); ?>><?php esc_html_e( 'Abajo', 'consent-hub' ); ?></option>
							<option value="top" <?php selected( $s['position'], 'top' ); ?>><?php esc_html_e( 'Arriba', 'consent-hub' ); ?></option>
							<option value="center" <?php selected( $s['position'], 'center' ); ?>><?php esc_html_e( 'Centro (modal)', 'consent-hub' ); ?></option>
						</select>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Mostrar automáticamente', 'consent-hub' ); ?></label>
						<input type="checkbox" name="ch_settings[auto_show]" value="1" <?php checked( $s['auto_show'] ); ?>>
						<span class="ch-hint"><?php esc_html_e( 'Si no, deberás llamar a ConsentHub.showBanner() manualmente.', 'consent-hub' ); ?></span>
					</div>
				</div>

				<!-- Texts -->
				<div class="ch-section">
					<h2><?php esc_html_e( 'Textos del banner', 'consent-hub' ); ?></h2>
					<?php
					self::text_field( 'banner_title', __( 'Título', 'consent-hub' ), $s );
					self::textarea_field( 'banner_description', __( 'Descripción', 'consent-hub' ), $s );
					self::text_field( 'btn_accept', __( 'Botón aceptar', 'consent-hub' ), $s );
					self::text_field( 'btn_reject', __( 'Botón rechazar', 'consent-hub' ), $s );
					self::text_field( 'btn_customize', __( 'Botón configurar', 'consent-hub' ), $s );
					self::text_field( 'revisit_label', __( 'Botón revisitar', 'consent-hub' ), $s );
					?>
				</div>

				<div class="ch-section">
					<h2><?php esc_html_e( 'Textos de preferencias', 'consent-hub' ); ?></h2>
					<?php
					self::text_field( 'prefs_title', __( 'Título', 'consent-hub' ), $s );
					self::textarea_field( 'prefs_description', __( 'Descripción', 'consent-hub' ), $s );
					self::text_field( 'btn_save', __( 'Botón guardar', 'consent-hub' ), $s );
					?>
				</div>

				<!-- Categories -->
				<div class="ch-section">
					<h2><?php esc_html_e( 'Categorías', 'consent-hub' ); ?></h2>
					<?php
					$cats = array( 'analytics', 'marketing', 'preferences' );
					foreach ( $cats as $cat ) :
					?>
					<div class="ch-field-group">
						<h3><?php echo esc_html( ucfirst( $cat ) ); ?></h3>
						<?php
						self::text_field( 'cat_' . $cat . '_label', __( 'Nombre', 'consent-hub' ), $s );
						self::text_field( 'cat_' . $cat . '_desc', __( 'Descripción', 'consent-hub' ), $s );
						?>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Theme -->
				<div class="ch-section">
					<h2><?php esc_html_e( 'Tema visual', 'consent-hub' ); ?></h2>
					<div class="ch-color-grid">
						<?php
						self::color_field( 'primary', __( 'Color principal', 'consent-hub' ), $s );
						self::color_field( 'primary_text', __( 'Texto del botón', 'consent-hub' ), $s );
						self::color_field( 'background', __( 'Fondo', 'consent-hub' ), $s );
						self::color_field( 'text_color', __( 'Texto', 'consent-hub' ), $s );
						self::color_field( 'text_secondary', __( 'Texto secundario', 'consent-hub' ), $s );
						self::color_field( 'border', __( 'Bordes', 'consent-hub' ), $s );
						self::color_field( 'toggle_on', __( 'Toggle activo', 'consent-hub' ), $s );
						self::color_field( 'toggle_off', __( 'Toggle inactivo', 'consent-hub' ), $s );
						?>
					</div>
					<?php self::text_field( 'radius', __( 'Border radius', 'consent-hub' ), $s ); ?>
				</div>

				<!-- GCM -->
				<div class="ch-section">
					<h2><?php esc_html_e( 'Google Consent Mode v2', 'consent-hub' ); ?></h2>
					<div class="ch-field">
						<label><?php esc_html_e( 'Activar GCM v2', 'consent-hub' ); ?></label>
						<input type="checkbox" name="ch_settings[gcm_enabled]" value="1" <?php checked( $s['gcm_enabled'] ); ?>>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Modo', 'consent-hub' ); ?></label>
						<select name="ch_settings[gcm_mode]">
							<option value="advanced" <?php selected( $s['gcm_mode'], 'advanced' ); ?>><?php esc_html_e( 'Advanced (recomendado)', 'consent-hub' ); ?></option>
							<option value="basic" <?php selected( $s['gcm_mode'], 'basic' ); ?>><?php esc_html_e( 'Basic', 'consent-hub' ); ?></option>
						</select>
						<span class="ch-hint"><?php esc_html_e( 'Advanced envía pings sin cookies para que Google pueda modelar conversiones.', 'consent-hub' ); ?></span>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'URL passthrough', 'consent-hub' ); ?></label>
						<input type="checkbox" name="ch_settings[gcm_url_passthrough]" value="1" <?php checked( $s['gcm_url_passthrough'] ); ?>>
						<span class="ch-hint"><?php esc_html_e( 'Pasa GCLID/DCLID a través de enlaces internos.', 'consent-hub' ); ?></span>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Ads data redaction', 'consent-hub' ); ?></label>
						<input type="checkbox" name="ch_settings[gcm_ads_redaction]" value="1" <?php checked( $s['gcm_ads_redaction'] ); ?>>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Wait for update (ms)', 'consent-hub' ); ?></label>
						<input type="number" name="ch_settings[gcm_wait_update]" value="<?php echo esc_attr( $s['gcm_wait_update'] ); ?>" min="0" max="10000" step="100">
						<span class="ch-hint"><?php esc_html_e( 'Tiempo que esperan los tags de Google antes de disparar si no hay respuesta.', 'consent-hub' ); ?></span>
					</div>
				</div>

				<!-- Blocker -->
				<div class="ch-section">
					<h2><?php esc_html_e( 'Script blocker inteligente', 'consent-hub' ); ?></h2>
					<div class="ch-field">
						<label><?php esc_html_e( 'Activar blocker', 'consent-hub' ); ?></label>
						<input type="checkbox" name="ch_settings[blocker_enabled]" value="1" <?php checked( $s['blocker_enabled'] ); ?>>
						<span class="ch-hint"><?php esc_html_e( 'Intercepta scripts inyectados dinámicamente (Hotjar, Facebook Pixel, etc.).', 'consent-hub' ); ?></span>
					</div>
				</div>

				<!-- Logging -->
				<div class="ch-section">
					<h2><?php esc_html_e( 'Registro de consentimientos', 'consent-hub' ); ?></h2>
					<div class="ch-field">
						<label><?php esc_html_e( 'Activar logging', 'consent-hub' ); ?></label>
						<input type="checkbox" name="ch_settings[logging_enabled]" value="1" <?php checked( $s['logging_enabled'] ); ?>>
						<span class="ch-hint"><?php esc_html_e( 'Guarda un registro local de cada consentimiento (aceptado, rechazado, parcial).', 'consent-hub' ); ?></span>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Retención (días)', 'consent-hub' ); ?></label>
						<input type="number" name="ch_settings[logging_retention]" value="<?php echo esc_attr( $s['logging_retention'] ); ?>" min="7" max="365" step="1">
						<span class="ch-hint"><?php esc_html_e( 'Los registros más antiguos se eliminan automáticamente. Mínimo 7, máximo 365.', 'consent-hub' ); ?></span>
					</div>
				</div>

				<!-- Geo -->
				<div class="ch-section">
					<h2><?php esc_html_e( 'Geolocalización', 'consent-hub' ); ?></h2>
					<div class="ch-field">
						<label><?php esc_html_e( 'Activar geo-detección', 'consent-hub' ); ?></label>
						<input type="checkbox" name="ch_settings[geo_enabled]" value="1" <?php checked( $s['geo_enabled'] ); ?>>
						<span class="ch-hint"><?php esc_html_e( 'Detecta la región del visitante y adapta el comportamiento del banner.', 'consent-hub' ); ?></span>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Detección', 'consent-hub' ); ?></label>
						<select name="ch_settings[geo_override]">
							<option value="auto" <?php selected( $s['geo_override'], 'auto' ); ?>><?php esc_html_e( 'Automática (cabeceras CDN)', 'consent-hub' ); ?></option>
							<option value="eu" <?php selected( $s['geo_override'], 'eu' ); ?>><?php esc_html_e( 'Forzar: UE/EEA', 'consent-hub' ); ?></option>
							<option value="ccpa" <?php selected( $s['geo_override'], 'ccpa' ); ?>><?php esc_html_e( 'Forzar: CCPA (EE.UU.)', 'consent-hub' ); ?></option>
							<option value="other" <?php selected( $s['geo_override'], 'other' ); ?>><?php esc_html_e( 'Forzar: Resto del mundo', 'consent-hub' ); ?></option>
						</select>
						<span class="ch-hint"><?php esc_html_e( 'Auto usa Cloudflare, Vercel, CloudFront u otras cabeceras.', 'consent-hub' ); ?></span>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Regla UE/EEA', 'consent-hub' ); ?></label>
						<select name="ch_settings[geo_rule_eu]">
							<option value="optin" <?php selected( $s['geo_rule_eu'], 'optin' ); ?>><?php esc_html_e( 'Opt-in (GDPR: requiere consentimiento)', 'consent-hub' ); ?></option>
							<option value="optout" <?php selected( $s['geo_rule_eu'], 'optout' ); ?>><?php esc_html_e( 'Opt-out', 'consent-hub' ); ?></option>
						</select>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Regla CCPA (EE.UU.)', 'consent-hub' ); ?></label>
						<select name="ch_settings[geo_rule_ccpa]">
							<option value="optout" <?php selected( $s['geo_rule_ccpa'], 'optout' ); ?>><?php esc_html_e( 'Opt-out (permite por defecto, opción de rechazar)', 'consent-hub' ); ?></option>
							<option value="optin" <?php selected( $s['geo_rule_ccpa'], 'optin' ); ?>><?php esc_html_e( 'Opt-in', 'consent-hub' ); ?></option>
						</select>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Regla resto del mundo', 'consent-hub' ); ?></label>
						<select name="ch_settings[geo_rule_other]">
							<option value="hide" <?php selected( $s['geo_rule_other'], 'hide' ); ?>><?php esc_html_e( 'Ocultar banner (permitir todo)', 'consent-hub' ); ?></option>
							<option value="optin" <?php selected( $s['geo_rule_other'], 'optin' ); ?>><?php esc_html_e( 'Opt-in', 'consent-hub' ); ?></option>
							<option value="optout" <?php selected( $s['geo_rule_other'], 'optout' ); ?>><?php esc_html_e( 'Opt-out', 'consent-hub' ); ?></option>
						</select>
					</div>
					<div class="ch-field">
						<label><?php esc_html_e( 'Fallback por defecto', 'consent-hub' ); ?></label>
						<select name="ch_settings[geo_default]">
							<option value="other" <?php selected( $s['geo_default'], 'other' ); ?>><?php esc_html_e( 'Resto del mundo', 'consent-hub' ); ?></option>
							<option value="eu" <?php selected( $s['geo_default'], 'eu' ); ?>><?php esc_html_e( 'UE (conservador)', 'consent-hub' ); ?></option>
							<option value="ccpa" <?php selected( $s['geo_default'], 'ccpa' ); ?>><?php esc_html_e( 'CCPA', 'consent-hub' ); ?></option>
						</select>
						<span class="ch-hint"><?php esc_html_e( 'Si no se detecta la región, ¿qué regla aplicar?', 'consent-hub' ); ?></span>
					</div>
				</div>

				<?php submit_button( __( 'Guardar cambios', 'consent-hub' ) ); ?>
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
