<?php

class BCXWC_AjaxCallback {

	static function wcbc_item_ajax_callback(){
	 	$api = new BCXWC_IntegrationAPI();

	 	$data = $api->getListItemAjax(BCXWC_Helper::sanitize($_GET['q']));

	 	if($data) {
	 		echo json_encode($data);wp_die();
	 	}
	 	else {
	 		echo json_encode([]);wp_die();
	 	}
	}

	static function wcbc_check_used_item_ajax_callback(){
		global $wpdb;

		$table_name = $wpdb->prefix . 'wcbc_translasi_item';
		$bc_item = BCXWC_Helper::sanitize_int($_POST['bc_item_id']);

		$translasi = $wpdb->get_results("select wc_item_id from {$table_name} where bc_item_id = {$bc_item}");

		if($translasi) {
			$items = [];
			foreach($translasi as $t) {
				$pf = new WC_Product_Factory;
				$items[] = $pf->get_product($t->wc_item_id)->get_name();
			}

			echo json_encode([
				'status' => true,
				'data' => $items
			]);wp_die();
		}
		else {
			echo json_encode([
				'status' => false,
				'data' => $translasi
			]);wp_die();	
		}
	}

	static function wcbc_save_item_ajax_callback(){
		global $wpdb;

		$table_name = $wpdb->prefix . 'wcbc_translasi_item';
		$update = $wpdb->update($table_name, [
				'bc_item_id' => BCXWC_Helper::sanitize_int($_POST['bc_item_id']), 
				'bc_item_code' => BCXWC_Helper::sanitize($_POST['bc_item_code'])
			], 
			[
				'wc_item_id' => BCXWC_Helper::sanitize_int($_POST['wc_item_id'])
			]);

		if($update) {
			esc_html_e($_POST['bc_item_code']);wp_die();
		}

	 	echo json_encode(false);wp_die();
	}

	static function wcbc_bulk_trans_item_ajax_callback() {
		global $wpdb;

		$args = ['limit' => -1];
		$table_translasi = $wpdb->prefix . 'wcbc_translasi_item';
		$limit = 500;
		$offset = 500*(BCXWC_Helper::sanitize($_POST['iteration'])-1);
		if(BCXWC_Helper::sanitize($_POST['replace']) == 'true') {
			$items = $wpdb->get_results("select wc_item_id from {$table_translasi} order by id limit {$limit} offset {$offset}");
		}
		else {
			$lastId = BCXWC_Helper::sanitize_int($_POST['lastid']);
			$whereLastID = ($lastId AND $lastId != 0) ? 'and wc_item_id > '.$lastId : '';
			$items = $wpdb->get_results("select wc_item_id from {$table_translasi} where bc_item_id is null {$whereLastID} order by wc_item_id limit {$limit}");
		}

		$product_ids = [];
		foreach($items as $item) {
			$product_ids[] = $item->wc_item_id;
		}
		$args['include'] = $product_ids;

		$wooproduct = wc_get_products( $args );
		$keys = [];
		foreach ($wooproduct as $woo) {
			$keys[$woo->get_ID()] = BCXWC_Helper::sanitize($_POST['bulkPoint']) == 'SKU' ? $woo->get_sku() : $woo->get_name();
		}

	 	$api = new BCXWC_IntegrationAPI();

		$limit = 50;
		$offset = count($keys) / $limit;

		$translasi = [];
		for ($i=0; $i < $offset; $i++) { 
			$data_key = array_slice($keys, $limit*$i, $limit, true);
		 	$res = BCXWC_Helper::sanitize($_POST['bulkPoint']) == 'SKU' ? $api->saveBulkTransItemsBySku($data_key) : $api->saveBulkTransItemsByName($data_key);
		 	$translasi = array_merge($translasi, $res);
		 	unset($data_key);
		 	unset($res);
		}
		
		$success = array_count_values(array_column($translasi, 'success'))[1];
		echo json_encode([
			'success' => $success,
			'fail' => count($translasi) - $success,
			'data' => $translasi,
			'lastid' => end($args['include'])
		]);wp_die();
	}

	static function wcbc_get_wh_ajax_callback(){
	 	$api = new BCXWC_IntegrationAPI();

	 	$data = $api->getListWhAjax(BCXWC_Helper::sanitize($_GET['q']));

	 	if($data) {
	 		echo json_encode($data);wp_die();
	 	}
	 	else {
	 		echo json_encode([]);wp_die();
	 	}
	}

	static function wcbc_get_pricelvl_ajax_callback(){
	 	$api = new BCXWC_IntegrationAPI();

	 	$data = $api->getListPricelvlAjax(BCXWC_Helper::sanitize($_GET['q']));

	 	if($data) {
	 		echo json_encode($data);wp_die();
	 	}
	 	else {
	 		echo json_encode([]);wp_die();
	 	}
	}

	static function wcbc_get_branch_ajax_callback(){
	 	$api = new BCXWC_IntegrationAPI();

	 	$data = $api->getListBranchAjax(BCXWC_Helper::sanitize($_GET['q']));

	 	if($data) {
	 		echo json_encode($data);wp_die();
	 	}
	 	else {
	 		echo json_encode([]);wp_die();
	 	}
	}

	static function wcbc_upload_order() {
		global $wpdb;

		$table_so = $wpdb->prefix . 'wcbc_so';
		$table_sod = $wpdb->prefix . 'wcbc_sod';
		$modelSo = new BCXWC_ModelSo();

		$so_id = BCXWC_Helper::sanitize_int($_POST['so_id']);
		$so = $wpdb->get_row("select * from {$table_so} where id = {$so_id};");
		$wpdb->query("update {$table_so} set sync_status = 'PROCESS' where id = ({$so_id})");

		$so = get_object_vars($so);
		$so['xrefno'] = $so['wc_order_id'];
		$so['sods'] = [];

		$validated = true;
		$error = '';
		if($so['with_ship_cost'] == 1) {
			$table_setting = $wpdb->prefix . 'wcbc_setting';
			$itemongkir = $wpdb->get_var("select value from {$table_setting} where code = 'ITEMONGKIR'");
			if($itemongkir == '') {
				$error .= "Item Ongkir harus diisi!\r\n";
				$validated = false;
			}
		}

		$sods = $wpdb->get_results("select * from {$table_sod} where so_id = {$so['id']}");
		foreach($sods as $sod) {
			// cek jika item ongkir
			if($sod->wc_product_id != 0) {
				$sod->item_id = $modelSo->getItemid($sod->wc_product_id);
			}
			
			// cek ulang
			if(!is_null($sod->item_id)) {
				$sod->itemname = null;
				$so['sods'][] = $sod;
			}
			else {
				$error .= "Produk '".$sod->itemname."' belum di translasi!\r\n";
				$validated = false;
			}
		}

		if($validated) {
			//kirim

		 	$api = new BCXWC_IntegrationAPI();

		 	$upload = $api->postSo($so);

		 	if(is_object($upload) AND $upload->status == true) {
				$wpdb->update($table_so, ['sync_status' => 'SYNCED', 'sync_note' => '', 'sync_process_at' => current_time('mysql', 1), 'sync_at' => current_time('mysql', 1)], ['id' => $so['id']]);
	 			echo json_encode([
	 				'status' => true,
	 				'process_at' => current_time('mysql', 1)
 				]);wp_die();
		 	}
		 	else {
		 		if(is_object($upload) AND $upload->status == false) {
		 			$error = (is_object($upload->data) OR is_array($upload->data)) ? substr(json_encode($upload->data), 0, 200) : $upload->data;
		 		}
		 		else {
		 			$error = json_encode($upload);
		 		}
				$wpdb->update($table_so, ['sync_status' => 'ERROR', 'sync_note' => $error, 'sync_process_at' => current_time('mysql', 1)], ['id' => $so['id']]);
		 	}

		}
		else {
			$wpdb->update($table_so, ['sync_status' => 'ERROR', 'sync_note' => $error, 'sync_process_at' => current_time('mysql', 1)], ['id' => $so['id']]);
		}

	 	echo json_encode([
	 		'status' => false,
	 		'process_at' => current_time('mysql', 1),
	 		'data' => $error
 		]);wp_die();
	}

	static function wcbc_close_order() {
		global $wpdb;

		$table_so = $wpdb->prefix . 'wcbc_so';
		$table_so_process = $wpdb->prefix . 'wcbc_so_process';

		$id = BCXWC_Helper::sanitize_int($_POST['so_id']); // cuma penamaan nya yg so_id, sebenarnya itu id dari so_process
		$so_id = $wpdb->get_var("select so_id from {$table_so_process} where id = {$id}");
		$wpdb->query("update {$table_so_process} set sync_status = 'PROCESS' where id = {$id}");

		$validated = true;
		$error = '';

		$trxno_uploaded = $wpdb->get_var("select trxno from {$table_so} where sync_status = 'SYNCED' and id = {$so_id};");
		if(!$trxno_uploaded) {
			$error .= "Order harus di upload terlebih dahulu!";
			$validated = false;
		}

		if($validated) {

		 	$api = new BCXWC_IntegrationAPI();
		 	$post = $api->closeSo($trxno_uploaded);

		 	if(is_object($post) AND $post->status == true) {
				$wpdb->update($table_so_process, ['sync_status' => 'SYNCED', 'sync_note' => '', 'sync_process_at' => current_time('mysql', 1), 'sync_at' => current_time('mysql', 1)], ['id' => $id]);
	 			echo json_encode([
	 				'status' => true,
	 				'process_at' => current_time('mysql', 1)
 				]);wp_die();
		 	}
		 	else {
		 		if(is_object($post) AND $post->status == false) {
		 			$error = (is_object($post->data) OR is_array($post->data)) ? substr(json_encode($post->data), 0, 200) : $post->data;
		 		}
		 		else {
		 			$error = json_encode($post);
		 		}
				$wpdb->update($table_so_process, ['sync_status' => 'ERROR', 'sync_note' => $error, 'sync_process_at' => current_time('mysql', 1)], ['id' => $id]);
		 	}

		}
		else {
			$wpdb->update($table_so_process, ['sync_status' => 'ERROR', 'sync_note' => $error, 'sync_process_at' => current_time('mysql', 1)], ['id' => $id]);
		}

	 	echo json_encode([
	 		'status' => false,
	 		'process_at' => current_time('mysql', 1),
	 		'data' => $error
 		]);wp_die();
	}

	static function wcbc_check_license_ajax_callback(){
	 	$api = new BCXWC_IntegrationAPI();

	 	echo json_encode($api->checkLicense());
	 	wp_die();
	}

	static function wcbc_get_itemserv_ajax_callback(){
	 	$api = new BCXWC_IntegrationAPI();

	 	$data = $api->getListItemServAjax(BCXWC_Helper::sanitize($_GET['q']));

	 	if($data) {
	 		echo json_encode($data);wp_die();
	 	}
	 	else {
	 		echo json_encode([]);wp_die();
	 	}
	}
}
?>
