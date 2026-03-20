<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CH_Dashboard {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_ch_chart_data', array( __CLASS__, 'ajax_chart_data' ) );
		add_action( 'wp_ajax_ch_logs_page', array( __CLASS__, 'ajax_logs_page' ) );
		add_action( 'wp_ajax_ch_export_csv', array( __CLASS__, 'ajax_export_csv' ) );
	}

	public static function add_menu() {
		add_menu_page(
			'ConsentHub',
			'ConsentHub',
			'manage_options',
			'consent-hub-dashboard',
			array( __CLASS__, 'render_page' ),
			'dashicons-chart-bar',
			25
		);
	}

	public static function enqueue( $hook ) {
		if ( $hook !== 'toplevel_page_consent-hub-dashboard' ) return;

		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true );
		wp_enqueue_style( 'consent-hub-admin', CH_URL . 'assets/admin.css', array(), CH_VERSION );
		wp_enqueue_style( 'consent-hub-dashboard', CH_URL . 'assets/dashboard.css', array(), CH_VERSION );
		wp_enqueue_script( 'consent-hub-dashboard', CH_URL . 'assets/dashboard.js', array( 'chart-js' ), CH_VERSION, true );
		wp_enqueue_script( 'consent-hub-admin', CH_URL . 'assets/admin.js', array(), CH_VERSION, true );

		$dash_data = self::get_dashboard_data();
		$dash_data['ajax_url'] = admin_url( 'admin-ajax.php' );
		$dash_data['nonce']    = wp_create_nonce( 'ch_dashboard' );
		wp_localize_script( 'consent-hub-dashboard', 'chDashboard', $dash_data );
	}

	public static function get_dashboard_data() {
		return array(
			'totals'     => CH_Database::get_totals(),
			'total_logs' => CH_Database::get_total_logs(),
			'last_log'   => CH_Database::get_last_log_time(),
			'chart'      => self::build_chart_data( 7 ),
		);
	}

	/**
	 * Convert a UTC datetime string to Europe/Madrid timezone.
	 */
	public static function to_madrid( $utc_datetime ) {
		if ( empty( $utc_datetime ) ) return $utc_datetime;
		$dt = new \DateTime( $utc_datetime, new \DateTimeZone( 'UTC' ) );
		$dt->setTimezone( new \DateTimeZone( 'Europe/Madrid' ) );
		return $dt->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Render a partially masked IP with blurred last octet.
	 */
	public static function render_masked_ip( $ip ) {
		if ( $ip === '—' || empty( $ip ) ) return esc_html( '—' );
		// Split at the last dot: visible part + masked part
		$parts = explode( '.', $ip );
		if ( count( $parts ) < 4 ) return esc_html( $ip );
		$visible = esc_html( $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.' );
		$masked  = '<span class="ch-ip-blur">' . esc_html( $parts[3] ) . '</span>';
		return $visible . $masked;
	}

	/**
	 * Build chart data for a given number of days.
	 */
	public static function build_chart_data( $days ) {
		$trends = CH_Database::get_trends( $days );
		$dates = $accepted = $rejected = $partial = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$dates[] = date( 'j M', strtotime( $date ) );
			$accepted[ $date ] = 0;
			$rejected[ $date ] = 0;
			$partial[ $date ]  = 0;
		}

		foreach ( $trends as $trend ) {
			$date = substr( $trend->date, 0, 10 );
			if ( isset( $accepted[ $date ] ) ) {
				if ( $trend->consent_type === 'accepted' )    $accepted[ $date ] = (int) $trend->count;
				elseif ( $trend->consent_type === 'rejected' ) $rejected[ $date ] = (int) $trend->count;
				elseif ( $trend->consent_type === 'partial' )  $partial[ $date ]  = (int) $trend->count;
			}
		}

		return array(
			'labels'   => $dates,
			'accepted' => array_values( $accepted ),
			'rejected' => array_values( $rejected ),
			'partial'  => array_values( $partial ),
		);
	}

	/**
	 * AJAX: return chart data for a given period.
	 */
	public static function ajax_chart_data() {
		check_ajax_referer( 'ch_dashboard', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 7;
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) $days = 7;

		wp_send_json_success( self::build_chart_data( $days ) );
	}

	/**
	 * AJAX: return paginated logs (with optional filters).
	 */
	public static function ajax_logs_page() {
		check_ajax_referer( 'ch_dashboard', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 10;
		if ( ! in_array( $per_page, array( 10, 25, 50 ), true ) ) $per_page = 10;

		$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset = ( $page - 1 ) * $per_page;

		// Read and sanitize filter params
		$consent_type = isset( $_GET['consent_type'] ) ? sanitize_text_field( wp_unslash( $_GET['consent_type'] ) ) : '';
		$geo_region   = isset( $_GET['geo_region'] )   ? sanitize_text_field( wp_unslash( $_GET['geo_region'] ) )   : '';
		$date_from    = isset( $_GET['date_from'] )    ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) )    : '';
		$date_to      = isset( $_GET['date_to'] )      ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) )      : '';

		// Validate against whitelists
		if ( ! in_array( $consent_type, array( 'accepted', 'rejected', 'partial', '' ), true ) ) $consent_type = '';
		if ( ! in_array( $geo_region,   array( 'eu', 'ccpa', 'other', '' ), true ) )              $geo_region   = '';

		// Validate date format (YYYY-MM-DD)
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) $date_from = '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) )   $date_to   = '';

		$has_filters = $consent_type || $geo_region || $date_from || $date_to;

		if ( $has_filters ) {
			$result = CH_Database::get_filtered_logs( array(
				'per_page'     => $per_page,
				'offset'       => $offset,
				'consent_type' => $consent_type,
				'geo_region'   => $geo_region,
				'date_from'    => $date_from,
				'date_to'      => $date_to,
			) );
			$logs  = $result['rows'];
			$total = $result['total'];
		} else {
			$total = CH_Database::get_total_logs();
			$logs  = CH_Database::get_recent_logs( $per_page, $offset );
		}

		$type_labels = array(
			'accepted' => __( 'Aceptado', 'consent-hub' ),
			'rejected' => __( 'Rechazado', 'consent-hub' ),
			'partial'  => __( 'Parcial', 'consent-hub' ),
		);

		$rows = array();
		foreach ( $logs as $log ) {
			$cats = json_decode( $log->categories, true );
			$rows[] = array(
				'id'        => sprintf( 'CH-%08X', $log->id ),
				'ip'        => ! empty( $log->ip_partial ) ? $log->ip_partial : '—',
				'type'      => $log->consent_type,
				'label'     => isset( $type_labels[ $log->consent_type ] ) ? $type_labels[ $log->consent_type ] : $log->consent_type,
				'cats'      => is_array( $cats ) && ! empty( $cats ) ? implode( ', ', $cats ) : '—',
				'region'    => strtoupper( $log->geo_region ?: 'other' ),
				'date'      => self::to_madrid( $log->created_at ),
			);
		}

		wp_send_json_success( array(
			'rows'       => $rows,
			'total'      => $total,
			'pages'      => ceil( $total / $per_page ),
			'page'       => $page,
			'per_page'   => $per_page,
		) );
	}

	/**
	 * AJAX: export all logs as a CSV file download.
	 */
	public static function ajax_export_csv() {
		check_ajax_referer( 'ch_dashboard', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );

		$logs = CH_Database::get_all_logs();

		$filename = 'consent-hub-logs-' . date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$type_labels = array(
			'accepted' => 'Aceptado',
			'rejected' => 'Rechazado',
			'partial'  => 'Parcial',
		);

		$output = fopen( 'php://output', 'w' );

		// BOM for Excel UTF-8 compatibility
		fputs( $output, "\xEF\xBB\xBF" );

		fputcsv( $output, array( 'Consent ID', 'IP', 'Estado', 'Categorías', 'Región', 'Fecha' ) );

		foreach ( $logs as $log ) {
			$cats     = json_decode( $log->categories, true );
			$cats_str = is_array( $cats ) && ! empty( $cats ) ? implode( ', ', $cats ) : '';

			fputcsv( $output, array(
				sprintf( 'CH-%08X', $log->id ),
				! empty( $log->ip_partial ) ? $log->ip_partial : '',
				isset( $type_labels[ $log->consent_type ] ) ? $type_labels[ $log->consent_type ] : $log->consent_type,
				$cats_str,
				strtoupper( $log->geo_region ?: 'OTHER' ),
				self::to_madrid( $log->created_at ),
			) );
		}

		fclose( $output );
		wp_die();
	}

	/**
	 * Render the unified admin page (sidebar + content).
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// Ensure table exists and has latest schema (adds ip_partial if missing)
		CH_Database::ensure_table();
		$table_ok = CH_Database::table_exists();

		$s          = get_option( 'ch_settings', ch_default_settings() );
		$s          = wp_parse_args( $s, ch_default_settings() );
		$data       = self::get_dashboard_data();
		$totals     = $data['totals'];
		$total_logs = $data['total_logs'];
		$last_log   = $data['last_log'];
		$recent     = CH_Database::get_recent_logs( 10 );
		?>
		<div class="wrap ch-admin-shell">

			<!-- ═══ SIDEBAR ═══ -->
			<div class="ch-sidebar">
				<div class="ch-sidebar-brand">
					<div class="ch-brand-name">
						<span class="ch-brand-icon">🔒</span>
						ConsentHub
					</div>
					<div class="ch-brand-version">v<?php echo esc_html( CH_VERSION ); ?></div>
				</div>

				<nav class="ch-sidebar-nav">
					<div class="ch-nav-group">
						<div class="ch-nav-group-label"><?php esc_html_e( 'General', 'consent-hub' ); ?></div>
						<div class="ch-nav-item active" data-section="dashboard">
							<span class="ch-nav-icon dashicons dashicons-chart-bar"></span>
							Dashboard
						</div>
						<div class="ch-nav-item" data-section="banner">
							<span class="ch-nav-icon dashicons dashicons-edit-page"></span>
							<?php esc_html_e( 'Banner y Textos', 'consent-hub' ); ?>
						</div>
						<div class="ch-nav-item" data-section="categories">
							<span class="ch-nav-icon dashicons dashicons-tag"></span>
							<?php esc_html_e( 'Categorías', 'consent-hub' ); ?>
						</div>
						<div class="ch-nav-item" data-section="appearance">
							<span class="ch-nav-icon dashicons dashicons-art"></span>
							<?php esc_html_e( 'Apariencia', 'consent-hub' ); ?>
						</div>
					</div>

					<div class="ch-nav-group">
						<div class="ch-nav-group-label"><?php esc_html_e( 'Compliance', 'consent-hub' ); ?></div>
						<div class="ch-nav-item" data-section="gcm">
							<span class="ch-nav-icon dashicons dashicons-search"></span>
							<?php esc_html_e( 'Google Consent Mode', 'consent-hub' ); ?>
						</div>
						<div class="ch-nav-item" data-section="blocker">
							<span class="ch-nav-icon dashicons dashicons-shield"></span>
							<?php esc_html_e( 'Script Blocker', 'consent-hub' ); ?>
						</div>
						<div class="ch-nav-item" data-section="geo">
							<span class="ch-nav-icon dashicons dashicons-admin-site-alt3"></span>
							<?php esc_html_e( 'Geolocalización', 'consent-hub' ); ?>
						</div>
					</div>

					<div class="ch-nav-group">
						<div class="ch-nav-group-label"><?php esc_html_e( 'Datos', 'consent-hub' ); ?></div>
						<div class="ch-nav-item" data-section="logging">
							<span class="ch-nav-icon dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Registro', 'consent-hub' ); ?>
						</div>
					</div>
				</nav>
			</div>

			<!-- ═══ CONTENT ═══ -->
			<div class="ch-content">

				<?php if ( ! $table_ok ) : ?>
				<div class="notice notice-error"><p>
					<?php esc_html_e( 'Error: no se pudo crear la tabla de registros. Desactiva y reactiva el plugin.', 'consent-hub' ); ?>
				</p></div>
				<?php endif; ?>

				<?php if ( empty( $s['logging_enabled'] ) ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'El registro de consentimientos está desactivado. Actívalo en la sección Registro para empezar a recopilar datos.', 'consent-hub' ); ?>
				</p></div>
				<?php endif; ?>

				<!-- ── FORM wraps all settings sections ── -->
				<form method="post" action="options.php" id="ch-settings-form">
					<?php settings_fields( 'ch_settings_group' ); ?>

				<!-- ═══ DASHBOARD ═══ -->
				<div class="ch-section" data-section="dashboard">
					<div class="ch-page-header">
						<h1>Dashboard</h1>
						<p><?php esc_html_e( 'Vista general de ConsentHub', 'consent-hub' ); ?></p>
					</div>

					<div class="ch-row">
						<!-- Status Card -->
						<div class="ch-card">
							<div class="ch-card-header"><h2><?php esc_html_e( 'Estado del sitio', 'consent-hub' ); ?></h2></div>
							<div class="ch-card-body">
								<div class="ch-status-item">
									<span class="ch-status-label"><?php esc_html_e( 'Cookie banner', 'consent-hub' ); ?></span>
									<span class="ch-badge ch-badge-active"><?php esc_html_e( 'Activo', 'consent-hub' ); ?></span>
								</div>
								<div class="ch-status-item">
									<span class="ch-status-label"><?php esc_html_e( 'Google Consent Mode', 'consent-hub' ); ?></span>
									<?php if ( ! empty( $s['gcm_enabled'] ) ) : ?>
										<span class="ch-badge ch-badge-active"><?php echo esc_html( ucfirst( $s['gcm_mode'] ) ); ?></span>
									<?php else : ?>
										<span class="ch-badge ch-badge-inactive"><?php esc_html_e( 'Inactivo', 'consent-hub' ); ?></span>
									<?php endif; ?>
								</div>
								<div class="ch-status-item">
									<span class="ch-status-label"><?php esc_html_e( 'Script Blocker', 'consent-hub' ); ?></span>
									<span class="ch-badge <?php echo ! empty( $s['blocker_enabled'] ) ? 'ch-badge-active' : 'ch-badge-inactive'; ?>">
										<?php echo ! empty( $s['blocker_enabled'] ) ? esc_html__( 'Activo', 'consent-hub' ) : esc_html__( 'Inactivo', 'consent-hub' ); ?>
									</span>
								</div>
								<div class="ch-status-item">
									<span class="ch-status-label"><?php esc_html_e( 'Geo-targeting', 'consent-hub' ); ?></span>
									<span class="ch-badge <?php echo ! empty( $s['geo_enabled'] ) ? 'ch-badge-active' : 'ch-badge-inactive'; ?>">
										<?php echo ! empty( $s['geo_enabled'] ) ? esc_html__( 'Activo', 'consent-hub' ) : esc_html__( 'Inactivo', 'consent-hub' ); ?>
									</span>
								</div>
								<div class="ch-status-item">
									<span class="ch-status-label"><?php esc_html_e( 'Versión', 'consent-hub' ); ?></span>
									<span><?php echo esc_html( CH_VERSION ); ?></span>
								</div>
							</div>
						</div>

						<!-- Logging Card -->
						<div class="ch-card">
							<div class="ch-card-header"><h2><?php esc_html_e( 'Registro y categorías', 'consent-hub' ); ?></h2></div>
							<div class="ch-card-body">
								<div class="ch-stat-grid">
									<div class="ch-stat-block">
										<span class="ch-stat-number"><?php echo esc_html( $total_logs ); ?></span>
										<span class="ch-stat-text"><?php esc_html_e( 'Total registros', 'consent-hub' ); ?></span>
									</div>
									<div class="ch-stat-block">
										<span class="ch-stat-number">3</span>
										<span class="ch-stat-text"><?php esc_html_e( 'Categorías', 'consent-hub' ); ?></span>
									</div>
								</div>
								<div class="ch-status-item">
									<span class="ch-status-label"><?php esc_html_e( 'Registro', 'consent-hub' ); ?></span>
									<span class="ch-badge <?php echo ! empty( $s['logging_enabled'] ) ? 'ch-badge-active' : 'ch-badge-inactive'; ?>">
										<?php echo ! empty( $s['logging_enabled'] ) ? esc_html__( 'Activo', 'consent-hub' ) : esc_html__( 'Inactivo', 'consent-hub' ); ?>
									</span>
								</div>
								<div class="ch-status-item">
									<span class="ch-status-label"><?php esc_html_e( 'Retención', 'consent-hub' ); ?></span>
									<span><?php echo esc_html( $s['logging_retention'] ); ?> <?php esc_html_e( 'días', 'consent-hub' ); ?></span>
								</div>
								<div class="ch-status-item">
									<span class="ch-status-label"><?php esc_html_e( 'Último registro', 'consent-hub' ); ?></span>
									<span><?php echo $last_log ? esc_html( self::to_madrid( $last_log ) ) : esc_html__( 'Ninguno', 'consent-hub' ); ?></span>
								</div>
							</div>
						</div>
					</div>

					<!-- Charts -->
					<div class="ch-row">
						<div class="ch-card">
							<div class="ch-card-header"><h2><?php esc_html_e( 'Tendencias de consentimiento', 'consent-hub' ); ?></h2></div>
							<div class="ch-card-body">
								<div class="ch-donut-wrap"><canvas id="consentDonut"></canvas></div>
								<div class="ch-donut-legend">
									<div class="ch-legend-item"><span class="ch-legend-dot ch-dot-accepted"></span> <?php esc_html_e( 'Aceptados', 'consent-hub' ); ?> <strong><?php echo esc_html( $totals['accepted'] ); ?></strong></div>
									<div class="ch-legend-item"><span class="ch-legend-dot ch-dot-rejected"></span> <?php esc_html_e( 'Rechazados', 'consent-hub' ); ?> <strong><?php echo esc_html( $totals['rejected'] ); ?></strong></div>
									<div class="ch-legend-item"><span class="ch-legend-dot ch-dot-partial"></span> <?php esc_html_e( 'Parciales', 'consent-hub' ); ?> <strong><?php echo esc_html( $totals['partial'] ); ?></strong></div>
								</div>
							</div>
						</div>
						<div class="ch-card">
							<div class="ch-card-header ch-card-header-flex">
								<h2><?php esc_html_e( 'Tendencia por periodo', 'consent-hub' ); ?></h2>
								<div class="ch-period-selector">
									<button type="button" class="ch-period-btn active" data-days="7">7d</button>
									<button type="button" class="ch-period-btn" data-days="30">30d</button>
									<button type="button" class="ch-period-btn" data-days="90">90d</button>
								</div>
							</div>
							<div class="ch-card-body">
								<div class="ch-line-chart-wrap"><canvas id="consentChart"></canvas></div>
							</div>
						</div>
					</div>

					<!-- Recent Logs -->
					<div class="ch-row ch-row-full">
						<div class="ch-card" id="ch-logs-card">
							<div class="ch-card-header ch-card-header-flex">
								<h2><?php esc_html_e( 'Registros recientes', 'consent-hub' ); ?></h2>
								<div class="ch-logs-header-actions">
									<button type="button" class="ch-btn-export" id="ch-export-csv"><?php esc_html_e( 'Exportar CSV', 'consent-hub' ); ?></button>
									<div class="ch-per-page-selector">
										<label><?php esc_html_e( 'Mostrar', 'consent-hub' ); ?>
											<select id="ch-logs-per-page">
												<option value="10" selected>10</option>
												<option value="25">25</option>
												<option value="50">50</option>
											</select>
										</label>
									</div>
								</div>
							</div>
							<div class="ch-filters" id="ch-log-filters">
								<select id="ch-filter-type" class="ch-filter-select">
									<option value=""><?php esc_html_e( 'Todos los estados', 'consent-hub' ); ?></option>
									<option value="accepted"><?php esc_html_e( 'Aceptado', 'consent-hub' ); ?></option>
									<option value="rejected"><?php esc_html_e( 'Rechazado', 'consent-hub' ); ?></option>
									<option value="partial"><?php esc_html_e( 'Parcial', 'consent-hub' ); ?></option>
								</select>
								<select id="ch-filter-region" class="ch-filter-select">
									<option value=""><?php esc_html_e( 'Todas las regiones', 'consent-hub' ); ?></option>
									<option value="eu">EU</option>
									<option value="ccpa">CCPA</option>
									<option value="other">OTHER</option>
								</select>
								<input type="date" id="ch-filter-from" class="ch-filter-input" placeholder="<?php esc_attr_e( 'Desde', 'consent-hub' ); ?>">
								<input type="date" id="ch-filter-to" class="ch-filter-input" placeholder="<?php esc_attr_e( 'Hasta', 'consent-hub' ); ?>">
								<button type="button" class="ch-btn-filter" id="ch-filter-apply"><?php esc_html_e( 'Filtrar', 'consent-hub' ); ?></button>
								<button type="button" class="ch-btn-filter-clear" id="ch-filter-clear"><?php esc_html_e( 'Limpiar', 'consent-hub' ); ?></button>
							</div>
							<div class="ch-card-body ch-card-body-table">
								<table class="widefat ch-logs-table">
									<thead><tr>
										<th><?php esc_html_e( 'Consent ID', 'consent-hub' ); ?></th>
										<th><?php esc_html_e( 'IP', 'consent-hub' ); ?></th>
										<th><?php esc_html_e( 'Estado', 'consent-hub' ); ?></th>
										<th><?php esc_html_e( 'Categorías', 'consent-hub' ); ?></th>
										<th><?php esc_html_e( 'Región', 'consent-hub' ); ?></th>
										<th><?php esc_html_e( 'Fecha/Hora', 'consent-hub' ); ?></th>
									</tr></thead>
									<tbody id="ch-logs-tbody">
									<?php if ( ! empty( $recent ) ) : foreach ( $recent as $log ) :
										$cats = json_decode( $log->categories, true );
										$cats_str = is_array( $cats ) && ! empty( $cats ) ? implode( ', ', $cats ) : '—';
										$type_labels = array( 'accepted' => __( 'Aceptado', 'consent-hub' ), 'rejected' => __( 'Rechazado', 'consent-hub' ), 'partial' => __( 'Parcial', 'consent-hub' ) );
										$ip_display = ! empty( $log->ip_partial ) ? $log->ip_partial : '—';
									?>
									<tr>
										<td><code><?php echo esc_html( sprintf( 'CH-%08X', $log->id ) ); ?></code></td>
										<td class="ch-ip-cell"><?php echo self::render_masked_ip( $ip_display ); ?></td>
										<td><span class="ch-badge ch-badge-<?php echo esc_attr( $log->consent_type ); ?>"><?php echo esc_html( $type_labels[ $log->consent_type ] ?? $log->consent_type ); ?></span></td>
										<td><?php echo esc_html( $cats_str ); ?></td>
										<td><?php echo esc_html( strtoupper( $log->geo_region ?: 'other' ) ); ?></td>
										<td><?php echo esc_html( self::to_madrid( $log->created_at ) ); ?></td>
									</tr>
									<?php endforeach; else : ?>
									<tr><td colspan="6"><?php esc_html_e( 'Sin datos', 'consent-hub' ); ?></td></tr>
									<?php endif; ?>
									</tbody>
								</table>
							</div>
							<div class="ch-card-footer ch-pagination" id="ch-logs-pagination">
								<span class="ch-pagination-info" id="ch-pagination-info"></span>
								<div class="ch-pagination-buttons" id="ch-pagination-buttons"></div>
							</div>
						</div>
					</div>
				</div>

				<!-- ═══ BANNER Y TEXTOS ═══ -->
				<div class="ch-section" data-section="banner">
					<div class="ch-page-header">
						<h1><?php esc_html_e( 'Banner y Textos', 'consent-hub' ); ?></h1>
						<p><?php esc_html_e( 'Configura el banner de cookies y sus textos', 'consent-hub' ); ?></p>
					</div>
					<?php CH_Admin::render_banner_section( $s ); ?>
				</div>

				<!-- ═══ CATEGORÍAS ═══ -->
				<div class="ch-section" data-section="categories">
					<div class="ch-page-header">
						<h1><?php esc_html_e( 'Categorías', 'consent-hub' ); ?></h1>
						<p><?php esc_html_e( 'Configura las categorías de cookies', 'consent-hub' ); ?></p>
					</div>
					<?php CH_Admin::render_categories_section( $s ); ?>
				</div>

				<!-- ═══ APARIENCIA ═══ -->
				<div class="ch-section" data-section="appearance">
					<div class="ch-page-header">
						<h1><?php esc_html_e( 'Apariencia', 'consent-hub' ); ?></h1>
						<p><?php esc_html_e( 'Personaliza colores y estilo visual del banner', 'consent-hub' ); ?></p>
					</div>
					<?php CH_Admin::render_appearance_section( $s ); ?>
				</div>

				<!-- ═══ GOOGLE CONSENT MODE ═══ -->
				<div class="ch-section" data-section="gcm">
					<div class="ch-page-header">
						<h1><?php esc_html_e( 'Google Consent Mode v2', 'consent-hub' ); ?></h1>
						<p><?php esc_html_e( 'Integración con Google Consent Mode', 'consent-hub' ); ?></p>
					</div>
					<?php CH_Admin::render_gcm_section( $s ); ?>
				</div>

				<!-- ═══ SCRIPT BLOCKER ═══ -->
				<div class="ch-section" data-section="blocker">
					<div class="ch-page-header">
						<h1><?php esc_html_e( 'Script Blocker', 'consent-hub' ); ?></h1>
						<p><?php esc_html_e( 'Intercepta scripts hasta que el usuario dé consentimiento', 'consent-hub' ); ?></p>
					</div>
					<?php CH_Admin::render_blocker_section( $s ); ?>
				</div>

				<!-- ═══ GEOLOCALIZACIÓN ═══ -->
				<div class="ch-section" data-section="geo">
					<div class="ch-page-header">
						<h1><?php esc_html_e( 'Geolocalización', 'consent-hub' ); ?></h1>
						<p><?php esc_html_e( 'Adapta el banner según la región del visitante', 'consent-hub' ); ?></p>
					</div>
					<?php CH_Admin::render_geo_section( $s ); ?>
				</div>

				<!-- ═══ REGISTRO ═══ -->
				<div class="ch-section" data-section="logging">
					<div class="ch-page-header">
						<h1><?php esc_html_e( 'Registro', 'consent-hub' ); ?></h1>
						<p><?php esc_html_e( 'Configuración del registro de consentimientos', 'consent-hub' ); ?></p>
					</div>
					<?php CH_Admin::render_logging_section( $s ); ?>
				</div>

				<!-- Save Button (hidden on Dashboard via JS) -->
				<div class="ch-save-row">
					<?php submit_button( __( 'Guardar cambios', 'consent-hub' ) ); ?>
				</div>

				</form>
			</div><!-- .ch-content -->
		</div><!-- .ch-admin-shell -->
		<?php
	}
}
