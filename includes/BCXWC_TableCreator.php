<?php

class BCXWC_TableCreator {

	function wcbc_create_translasi_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wcbc_translasi_item';
		$query      = "
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id         		BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				wc_item_id    	BIGINT(20)   NOT NULL,
				bc_item_id    	BIGINT(20)   ,
				bc_item_code    VARCHAR(255)   ,
				updated_at 		TIMESTAMP NOT NULL DEFAULT NOW() ON UPDATE NOW(),
				primary key (id)
			) {$charset};
		";

		dbDelta( $query );

		// initiation fill the existed product
		$wooproduct = wc_get_products(['limit' => -1]);
		BCXWC_Logger::log(count($wooproduct));
		foreach ($wooproduct as $woo) {
			$id = $woo->get_id();
			$data = $wpdb->get_results("SELECT id FROM $table_name WHERE wc_item_id = {$id}");

			if(count($data) < 1) {
				$wpdb->insert($table_name, ['wc_item_id' => $id, 'bc_item_id' => null]);
			}
			else {
				BCXWC_Logger::log("Produk ".$id. " sudah ada!");
			}
		}
	}

	function wcbc_create_so_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wcbc_so';
		$query      = "
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id         		BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				wc_order_id		BIGINT,
				trxno    		VARCHAR(25)   NOT NULL,
				updated_at    	TIMESTAMP , 
				trxdate    		TIMESTAMP ,
				bp_id    		BIGINT(20)  NOT NULL,
				crc_id    		BIGINT(20)  NOT NULL,
				branch_id    	BIGINT(20)   ,
				excrate    		DECIMAL(20,4)  NOT NULL DEFAULT 0,
				fisrate    		DECIMAL(20,4)  NOT NULL DEFAULT 0,
				taxed    		BOOLEAN  NOT NULL DEFAULT FALSE,
				taxinc    		BOOLEAN  NOT NULL DEFAULT FALSE,
				billaddr    	TEXT  NOT NULL ,
				shipaddr    	TEXT  NOT NULL ,
				note    		TEXT  ,
				subtotal    	DECIMAL(20,4)  NOT NULL DEFAULT 0,
				discexp    		VARCHAR(25) ,
				discamt    		DECIMAL(20,4)  NOT NULL DEFAULT 0,
				taxamt    		DECIMAL(20,4)  NOT NULL DEFAULT 0,
				total    		DECIMAL(20,4)  NOT NULL DEFAULT 0,
				basesubtotal    DECIMAL(20,4)  NOT NULL DEFAULT 0,
				basediscamt	    DECIMAL(20,4)  NOT NULL DEFAULT 0,
				basetaxamt	    DECIMAL(20,4)  NOT NULL DEFAULT 0,
				basefistaxamt	DECIMAL(20,4)  NOT NULL DEFAULT 0,
				basetotal		DECIMAL(20,4)  NOT NULL DEFAULT 0,
				sync_status		VARCHAR(10) NOT NULL DEFAULT 'UNSYNCED',
				sync_note		TEXT,
				sync_at 		TIMESTAMP,
				sync_process_at TIMESTAMP,
				with_ship_cost	BOOLEAN   NOT NULL DEFAULT FALSE,
				primary key (id)
			) {$charset};
		"; // sync_status => 'UNSYNCED', 'PROCESS', 'SYNCED', 'ERROR'

		dbDelta( $query );
	}

	function wcbc_create_sod_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wcbc_sod';
		$query      = "
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id         		BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				so_id    		BIGINT(20)  NOT NULL,
				dno				BIGINT NOT NULL,
				itemname    	VARCHAR(125) ,
				item_id    		BIGINT(20) ,
				wc_product_id	BIGINT(20)  NOT NULL,
				qty    			DECIMAL(20,4)  NOT NULL DEFAULT 0,
				pid    			VARCHAR(30) DEFAULT '',
				unit			BIGINT ,
				listprice    	DECIMAL(20,4)  NOT NULL DEFAULT 0,
				baseprice    	DECIMAL(20,4)  NOT NULL DEFAULT 0,
				discexp    		VARCHAR(25) ,
				discamt    		DECIMAL(20,4)  NOT NULL DEFAULT 0,
				totaldiscamt    DECIMAL(20,4)  NOT NULL DEFAULT 0,
				disc2amt    	DECIMAL(20,4)  NOT NULL DEFAULT 0,
				totaldisc2amt   DECIMAL(20,4)  NOT NULL DEFAULT 0,
				subtotal    	DECIMAL(20,4)  NOT NULL DEFAULT 0,
				basesubtotal    DECIMAL(20,4)  NOT NULL DEFAULT 0,
				dnote    		TEXT  ,
				tax_code    	VARCHAR(25) ,
				taxableamt    	DECIMAL(20,4)  NOT NULL DEFAULT 0,
				taxamt    		DECIMAL(20,4)  NOT NULL DEFAULT 0,
				totaltaxamt    	DECIMAL(20,4)  NOT NULL DEFAULT 0,
				basetotaltaxamt DECIMAL(20,4)  NOT NULL DEFAULT 0,
				basefistotaltaxamt    DECIMAL(20,4)  NOT NULL DEFAULT 0,
				primary key (id)
			) {$charset};
		";

		dbDelta( $query );
	}

	function wcbc_create_setting_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wcbc_setting';
		$query      = "
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id         		BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				code    		VARCHAR(25)   NOT NULL,
				value    		TEXT ,
				helper    		TEXT ,
				primary key (id)
			) {$charset};
		";

		dbDelta( $query );
	}

	function wcbc_create_log() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wcbc_log';
		$query      = "
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id         		BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				date    		TIMESTAMP   NOT NULL,
				error    		BOOLEAN DEFAULT FALSE,
				text    		TEXT ,
				primary key (id)
			) {$charset};
		";

		dbDelta( $query );
	}

	function wcbc_create_so_process_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'wcbc_so_process';
		$query      = "
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id         		BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
				so_id			BIGINT,
				process_type	VARCHAR(10),
				updated_at    	TIMESTAMP , 
				trxdate    		TIMESTAMP ,
				sync_status		VARCHAR(10) NOT NULL DEFAULT 'UNSYNCED',
				sync_note		TEXT,
				sync_at 		TIMESTAMP,
				sync_process_at TIMESTAMP,
				primary key (id)
			) {$charset};
		"; // sync_status => 'UNSYNCED', 'PROCESS', 'SYNCED', 'ERROR'
		// process_type => 'CANCEL', 'COMPLETE', 'REFUND'
		dbDelta( $query );
	}
}
?>
