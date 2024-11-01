<?php

class BCXWC_Task {

	static function wcbc_process_sync() {
		global $wpdb;

	 	$api = new BCXWC_IntegrationAPI();
	 	if(!$api->checkLicense()) {
	 		wp_die();
	 	}

		$table_so = $wpdb->prefix . 'wcbc_so';
		$table_sod = $wpdb->prefix . 'wcbc_sod';
		$table_so_process = $wpdb->prefix . 'wcbc_so_process';
		$sos = $wpdb->get_results("
				SELECT id, so_id, process_type FROM (
					SELECT id, '' as so_id, 'ORDER' AS process_type, updated_at FROM {$table_so} WHERE sync_status IN (\"UNSYNCED\", \"ERROR\")
					UNION ALL 
					SELECT id, so_id, process_type, updated_at FROM {$table_so_process} WHERE sync_status IN (\"UNSYNCED\", \"ERROR\")
				) AS wcbc_so
				ORDER BY updated_at ASC LIMIT 15;
		");
		$modelSo = new BCXWC_ModelSo();

		$so_ids = '';
		$so_process_ids = '';
		$so_process_datas = [];
		foreach($sos as $key => $so) {
			if($so->process_type == 'ORDER') {
				if($so_ids != '') {
					$so_ids .= ', ';
				}
				$so_ids .= '"'.$so->id.'"';
			}
			else {
				unset($sos[$key]);
				$so_process_datas[$so->id] = ['so_id' => $so->so_id, 'type' => $so->process_type];
				if($so_process_ids != '') {
					$so_process_ids .= ', ';
				}
				$so_process_ids .= '"'.$so->id.'"';	
			}
		}

		if($so_ids != '') {
			$wpdb->query("update {$table_so} set sync_status = 'PROCESS' where id in ({$so_ids})");
			$sos = $wpdb->get_results("select * from {$table_so} where id in ({$so_ids})");
		}

		if($so_process_ids != '') {
			$wpdb->query("update {$table_so_process} set sync_status = 'PROCESS' where id in ({$so_process_ids})");
		}

		// proses ORDER
		foreach($sos as $so) {
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

			 	$upload = $api->postSo($so);

			 	if(is_object($upload) AND $upload->status == true) {
					$wpdb->update($table_so, ['sync_status' => 'SYNCED', 'sync_note' => '', 'sync_process_at' => current_time('mysql', 1), 'sync_at' => current_time('mysql', 1)], ['id' => $so['id']]);
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
		}

		// proses selain ORDER
		foreach($so_process_datas as $id => $data) {
			$validated = true;
			$error = '';

			// PROSES CANCEL
			if($data['type'] == 'CANCEL') {
				$trxno_uploaded = $wpdb->get_var("select trxno from {$table_so} where sync_status = 'SYNCED' and id = {$data['so_id']};");
				if(!$trxno_uploaded) {
					$error .= "Order harus di upload terlebih dahulu!";
					$validated = false;
				}
				if($validated) {
					$post = $api->closeSo($trxno_uploaded);
				 	if(is_object($post) AND $post->status == true) {
						$wpdb->update($table_so_process, ['sync_status' => 'SYNCED', 'sync_note' => '', 'sync_process_at' => current_time('mysql', 1), 'sync_at' => current_time('mysql', 1)], ['id' => $id]);
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
			}

			if(!$validated) {
				$wpdb->update($table_so_process, ['sync_status' => 'ERROR', 'sync_note' => $error, 'sync_process_at' => current_time('mysql', 1)], ['id' => $id]);
			}
		}
	}

	static function wcbc_update_stock_price() {
	 	$api = new BCXWC_IntegrationAPI();
	 	if(!$api->checkLicense()) {
	 		wp_die();
	 	}
		$api->updateStock();
		$api->updatePrice();
	}
}
?>
