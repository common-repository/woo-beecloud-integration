<?php

class BCXWC_Logger {

	static function error($error) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wcbc_log';
		$wpdb->insert($table_name, ['date' => date('Y-m-d H:i:s'), 'error' => TRUE, 'text' => $error]);
	}

	static function log($log) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wcbc_log';
		$wpdb->insert($table_name, ['date' => date('Y-m-d H:i:s'), 'error' => FALSE, 'text' => $log]);
	}

}
?>
