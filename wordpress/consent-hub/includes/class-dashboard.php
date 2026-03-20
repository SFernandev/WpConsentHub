<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CH_Dashboard {

	/**
	 * Initialize dashboard.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Add dashboard menu item (before settings).
	 */
	public static function add_menu() {
		add_menu_page(
			'ConsentHub Dashboard',
			'ConsentHub',
			'manage_options',
			'consent-hub-dashboard',
			array( __CLASS__, 'render_page' ),
			'dashicons-chart-bar',
			25
		);

		// Add settings as submenu
		add_submenu_page(
			'consent-hub-dashboard',
			'Ajustes',
			'Ajustes',
			'manage_options',
			'consent-hub',
			array( 'CH_Admin', 'render_page' )
		);
	}

	/**
	 * Enqueue dashboard assets.
	 */
	public static function enqueue( $hook ) {
		if ( $hook !== 'toplevel_page_consent-hub-dashboard' ) return;

		// Chart.js from CDN
		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_style(
			'consent-hub-dashboard',
			CH_URL . 'assets/dashboard.css',
			array(),
			CH_VERSION
		);

		wp_enqueue_script(
			'consent-hub-dashboard',
			CH_URL . 'assets/dashboard.js',
			array( 'chart-js' ),
			CH_VERSION,
			true
		);

		wp_localize_script( 'consent-hub-dashboard', 'chDashboard', self::get_dashboard_data() );
	}

	/**
	 * Get dashboard data for JavaScript.
	 */
	public static function get_dashboard_data() {
		$totals = CH_Database::get_totals();
		$trends = CH_Database::get_trends( 7 );

		// Format trends for Chart.js
		$dates = array();
		$accepted = array();
		$rejected = array();
		$partial = array();

		// Initialize 7 days
		for ( $i = 6; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$dates[] = date( 'j M', strtotime( $date ) );
			$accepted[ $date ] = 0;
			$rejected[ $date ] = 0;
			$partial[ $date ] = 0;
		}

		// Populate with actual data
		foreach ( $trends as $trend ) {
			$date = substr( $trend->date, 0, 10 );
			if ( isset( $accepted[ $date ] ) ) {
				if ( $trend->consent_type === 'accepted' ) {
					$accepted[ $date ] = (int) $trend->count;
				} elseif ( $trend->consent_type === 'rejected' ) {
					$rejected[ $date ] = (int) $trend->count;
				} elseif ( $trend->consent_type === 'partial' ) {
					$partial[ $date ] = (int) $trend->count;
				}
			}
		}

		return array(
			'totals' => $totals,
			'total_logs' => CH_Database::get_total_logs(),
			'last_log' => CH_Database::get_last_log_time(),
			'chart' => array(
				'labels' => $dates,
				'accepted' => array_values( $accepted ),
				'rejected' => array_values( $rejected ),
				'partial' => array_values( $partial ),
			),
		);
	}

	/**
	 * Render dashboard page.
	 */
	public static function render_page() {
		$data = self::get_dashboard_data();
		$totals = $data['totals'];
		$total_logs = $data['total_logs'];
		$last_log = $data['last_log'];
		?>
		<div class="wrap consent-hub-dashboard-wrap">
			<h1>ConsentHub Dashboard</h1>

			<div class="ch-grid-2">
				<!-- Metrics Cards -->
				<div class="ch-metric-card">
					<div class="ch-metric-label">Aceptados</div>
					<div class="ch-metric-value" style="color: #27ae60;">
						<?php echo esc_html( $totals['accepted'] ); ?>
					</div>
				</div>

				<div class="ch-metric-card">
					<div class="ch-metric-label">Rechazados</div>
					<div class="ch-metric-value" style="color: #e74c3c;">
						<?php echo esc_html( $totals['rejected'] ); ?>
					</div>
				</div>

				<div class="ch-metric-card">
					<div class="ch-metric-label">Parciales</div>
					<div class="ch-metric-value" style="color: #f39c12;">
						<?php echo esc_html( $totals['partial'] ); ?>
					</div>
				</div>

				<div class="ch-metric-card">
					<div class="ch-metric-label">Total</div>
					<div class="ch-metric-value">
						<?php echo esc_html( $total_logs ); ?>
					</div>
				</div>
			</div>

			<!-- Chart -->
			<div class="ch-section">
				<h2>Últimos 7 días</h2>
				<div class="ch-chart-container">
					<canvas id="consentChart" width="400" height="200"></canvas>
				</div>
			</div>

			<!-- Summary Table -->
			<div class="ch-section">
				<h2>Resumen</h2>
				<table class="widefat">
					<thead>
						<tr>
							<th>Tipo</th>
							<th>Cantidad</th>
							<th>Porcentaje</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$total = $total_logs;
						if ( $total > 0 ) {
							foreach ( array( 'accepted', 'rejected', 'partial' ) as $type ) {
								$count = $totals[ $type ];
								$pct = round( ( $count / $total ) * 100, 1 );
								?>
								<tr>
									<td><?php echo esc_html( ucfirst( $type ) ); ?></td>
									<td><?php echo esc_html( $count ); ?></td>
									<td><?php echo esc_html( $pct ); ?>%</td>
								</tr>
								<?php
							}
						} else {
							?>
							<tr>
								<td colspan="3">Sin datos</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</div>

			<!-- Info -->
			<div class="ch-section ch-info">
				<p>
					<strong>Último registro:</strong>
					<?php
					if ( $last_log ) {
						echo esc_html( $last_log );
					} else {
						echo 'Ninguno aún';
					}
					?>
				</p>
				<p>
					<strong>Plugin:</strong> ConsentHub <?php echo esc_html( CH_VERSION ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
