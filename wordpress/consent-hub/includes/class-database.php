<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CH_Database {

	/**
	 * Get table name with WordPress prefix.
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ch_consent_log';
	}

	/**
	 * Create the consent log table.
	 */
	public static function create_table() {
		global $wpdb;
		$table = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			consent_type VARCHAR(20) NOT NULL,
			categories VARCHAR(255) NOT NULL,
			geo_region VARCHAR(10),
			ip_partial VARCHAR(39),
			ip_hash VARCHAR(64),
			user_agent_hash VARCHAR(64),
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			INDEX (consent_type),
			INDEX (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Check if the consent log table exists.
	 */
	public static function table_exists() {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
	}

	/**
	 * Ensure table exists, create if missing.
	 */
	public static function ensure_table() {
		if ( ! self::table_exists() ) {
			self::create_table();
		} else {
			self::maybe_add_ip_partial_column();
		}
	}

	/**
	 * Add ip_partial column if it doesn't exist (migration for existing installs).
	 */
	private static function maybe_add_ip_partial_column() {
		global $wpdb;
		$table = self::table_name();
		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'ip_partial'" );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN ip_partial VARCHAR(39) AFTER geo_region" );
		}
	}

	/**
	 * Drop the consent log table.
	 */
	public static function drop_table() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Log a consent event.
	 *
	 * @param string $type 'accepted', 'rejected', or 'partial'
	 * @param array  $categories e.g. ['analytics', 'marketing', 'preferences']
	 * @param string $region e.g. 'eu', 'ccpa', 'other'
	 */
	public static function log( $type, $categories = array(), $region = 'other' ) {
		global $wpdb;
		self::ensure_table();
		$table = self::table_name();

		// Store partial IP (first 3 octets) + hash for privacy
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip_parts = explode( '.', $ip );
		$ip_partial = '';
		if ( count( $ip_parts ) >= 3 ) {
			$ip_partial = $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2] . '.***';
			$ip_parts[3] = '0';
		}
		$ip_masked = implode( '.', $ip_parts );
		$ip_hash = hash( 'sha256', $ip_masked );

		// Hash user agent
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$ua_hash = hash( 'sha256', $ua );

		$wpdb->insert( $table, array(
			'consent_type'   => sanitize_text_field( $type ),
			'categories'     => wp_json_encode( $categories ),
			'geo_region'     => sanitize_text_field( $region ),
			'ip_partial'     => $ip_partial,
			'ip_hash'        => $ip_hash,
			'user_agent_hash'=> $ua_hash,
			'created_at'     => current_time( 'mysql', true ),
		), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	/**
	 * Get consent metrics for the last N days.
	 *
	 * @param int $days Default 7
	 * @return array
	 */
	public static function get_trends( $days = 7 ) {
		global $wpdb;
		$table = self::table_name();

		// UTC date math
		$sql = $wpdb->prepare(
			"SELECT
				DATE(CONVERT_TZ(created_at, '+00:00', @@session.time_zone)) as date,
				consent_type,
				COUNT(*) as count
			FROM {$table}
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
			GROUP BY DATE(CONVERT_TZ(created_at, '+00:00', @@session.time_zone)), consent_type
			ORDER BY date ASC",
			$days
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get total consent counts by type.
	 *
	 * @return array
	 */
	public static function get_totals() {
		global $wpdb;
		$table = self::table_name();

		$sql = "SELECT
			consent_type,
			COUNT(*) as count
		FROM {$table}
		GROUP BY consent_type";

		$results = $wpdb->get_results( $sql );

		$totals = array(
			'accepted' => 0,
			'rejected' => 0,
			'partial'  => 0,
		);

		foreach ( $results as $row ) {
			if ( isset( $totals[ $row->consent_type ] ) ) {
				$totals[ $row->consent_type ] = (int) $row->count;
			}
		}

		return $totals;
	}

	/**
	 * Get count of unique pages visited (based on referrer grouping).
	 *
	 * @return int
	 */
	public static function get_total_logs() {
		global $wpdb;
		$table = self::table_name();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		return (int) $count;
	}

	/**
	 * Get last log timestamp.
	 *
	 * @return string|null ISO 8601 UTC timestamp
	 */
	public static function get_last_log_time() {
		global $wpdb;
		$table = self::table_name();
		$time = $wpdb->get_var( "SELECT created_at FROM {$table} ORDER BY created_at DESC LIMIT 1" );
		return $time;
	}

	/**
	 * Get recent consent logs.
	 *
	 * @param int $limit Default 10
	 * @return array
	 */
	public static function get_recent_logs( $limit = 10, $offset = 0 ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id, consent_type, categories, geo_region, ip_partial, created_at
			 FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$limit,
			$offset
		) );
	}

	/**
	 * Clean up old logs (older than N days).
	 *
	 * @param int $days Default 90
	 */
	public static function cleanup( $days = 90 ) {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		) );
	}
}
