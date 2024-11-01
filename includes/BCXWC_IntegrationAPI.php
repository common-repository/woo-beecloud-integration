<?php

class BCXWC_IntegrationAPI {
	private $apikey;
	const baseUrl = 'https://app.beecloud.id/api/';
	private $endpoint;
	private $body;

	public function __construct() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcbc_setting';
		$this->apikey = $wpdb->get_var("select value from {$table_name} where code = 'APIKEY'");
	}

	private function getUrl() {
		return self::baseUrl . $this->endpoint;
	}

	private function post() {
		return wp_remote_post( $this->getUrl(), [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'Authorization' => 'Bearer ' . $this->apikey
			],
			'method' => 'POST',
			'timeout' => 75,				    
			'body' => $this->body
		]);	
	}

	private function get() {
		return wp_remote_get( $this->getUrl(), [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'Authorization' => 'Bearer ' . $this->apikey
			],
			'method' => 'GET',
			'timeout' => 75,				    
			'body' => $this->body
		]);	
	}

	public function getListItemAjax($param) {
		$this->endpoint = 'v1/select2data/item';
		$this->body = ['q' => $param];
		$response = $this->get();

		if(is_array($response)) {
			$data = json_decode($response['body']);

			if($data->status) {
				return $data->data;
			}
			else {
				return false;
			}
		}
		else {
			return $response;
		}
	}

	public function getListItemServAjax($param) {
		$this->endpoint = 'v1/select2data/itemserv';
		$this->body = ['q' => $param];
		$response = $this->get();

		if(is_array($response)) {
			$data = json_decode($response['body']);

			if($data->status) {
				return $data->data;
			}
			else {
				return false;
			}
		}
		else {
			return $response;
		}
	}

	public function getListWhAjax($param) {
		$this->endpoint = 'v1/select2data/wh';
		$this->body = ['q' => $param];
		$response = $this->get();

		if(is_array($response)) {
			$data = json_decode($response['body']);

			if($data->status) {
				return $data->data;
			}
			else {
				return false;
			}
		}
		else {
			return $response;
		}
	}

	public function getListPricelvlAjax($param) {
		$this->endpoint = 'v1/select2data/pricelvl';
		$this->body = ['q' => $param];
		$response = $this->get();

		if(is_array($response)) {
			$data = json_decode($response['body']);

			if($data->status) {
				return $data->data;
			}
			else {
				return false;
			}
		}
		else {
			return $response;
		}
	}

	public function getListBranchAjax($param) {
		$this->endpoint = 'v1/select2data/branch';
		$this->body = ['q' => $param];
		$response = $this->get();

		if(is_array($response)) {
			$data = json_decode($response['body']);

			if($data->status) {
				return $data->data;
			}
			else {
				return false;
			}
		}
		else {
			return $response;
		}
	}

	public function getDetailItem($id) {
		$this->endpoint = 'v1/item/'.$id;
		$this->body = [];
		$response = $this->get();
		$data = json_decode($response['body']);

		if($data->status) {
			return $data->data;
		}
		else {
			return false;
		}
	}

	public function postSo($data) {
		$this->endpoint = 'v1/so';
		$this->body = json_encode(['soarray' => [$data]]);
		$response = $this->post();
		$data = json_decode($response['body']);

		return $data;
	}

	public function closeSo($trxno) {
		$this->endpoint = 'v1/so-close';
		$this->body = json_encode(['trxno' => $trxno]);
		$response = $this->post();
		$data = json_decode($response['body']);

		return $data;
	}

	public function updatePrice() {
		global $wpdb;

		$this->endpoint = 'v2/price';

		$table_name = $wpdb->prefix . 'wcbc_setting';
		$table_translasi = $wpdb->prefix . 'wcbc_translasi_item';

		$pricelvl = $wpdb->get_var("select value from {$table_name} where code = 'PRICELVL'");
		$price_lastupdate = $wpdb->get_var("select value from {$table_name} where code = 'PRICE_LASTUPDATE'");
		if($pricelvl) {
			$this->body = [
				'getpagecount' => 1,
				'pricelvl_id' => $pricelvl,
				'lastupdate' => $price_lastupdate,
				'limit' => 500
			];
			$pagecount = json_decode($this->get()['body']);
			$pagecount = $pagecount->data;
			
			unset($this->body['getpagecount']);

			$items = $wpdb->get_results("select bc_item_id from {$table_translasi} where bc_item_id is not null");
			$item_ids = [];
			foreach($items as $item) {
				$item_ids[] = $item->bc_item_id;
			}

			$update_at = strtotime($price_lastupdate);
			for($i = 1; $i <= $pagecount; $i++) {
				$this->body['page'] = $i;
				$price = json_decode($this->get()['body']);
				if($price->status) {
					foreach($price->data as $prc) {
						if(in_array($prc->item_id, $item_ids) AND $prc->price1 != 0) {
							$wc_items = $wpdb->get_results("select wc_item_id from {$table_translasi} where bc_item_id = {$prc->item_id}");
							foreach($wc_items as $item) {
								$product = wc_get_product($item->wc_item_id);

								if($product->is_type('variable')) {
									$productChild = $product->get_children();
									foreach ($productChild as $child_id) {
										$children = new WC_Product_Variation($child_id);
										$children->set_regular_price($prc->price1);
										$children->set_sale_price($prc->price1 - BCXWC_Helper::calcDiscExp($prc->price1, $prc->disc1exp));
										$children->save();
										unset($children);
									}
								}
								else {
									$product->set_regular_price($prc->price1);
									$product->set_sale_price($prc->price1 - BCXWC_Helper::calcDiscExp($prc->price1, $prc->disc1exp));
									$product->save();
								}
								unset($product);
							}
							if(strtotime($prc->updated_at) > $update_at) {
								$update_at = strtotime($prc->updated_at);
							}
						}
					}
				}
				else {
					BCXWC_Logger::error(json_encode($price->data));
				}
			}
			if($update_at != strtotime($price_lastupdate) OR !strtotime($price_lastupdate)) {
				$wpdb->update($table_name, ['value' => date('Y-m-d H:i:s', $update_at)], ['code' => 'PRICE_LASTUPDATE']);
			}
		}
	}

	public function updateStock() {
		global $wpdb;

		$this->endpoint = 'v1/stock-per-item';

		$table_name = $wpdb->prefix . 'wcbc_setting';
		$table_translasi = $wpdb->prefix . 'wcbc_translasi_item';

		$wh = $wpdb->get_var("select value from {$table_name} where code = 'WH'");
		$wh = $wh ? explode(',', $wh) : [];
		$stock_lastupdate = $wpdb->get_var("select value from {$table_name} where code = 'STOCK_LASTUPDATE'");

		$items = $wpdb->get_results("select bc_item_id from {$table_translasi} where bc_item_id is not null");
		$item_ids = [];
		foreach($items as $item) {
			$item_ids[] = $item->bc_item_id;
		}
		
		$this->body = [
			'getpagecount' => 1,
			'wh_id' => $wh,
			'item_id' => $item_ids,
			'lastupdate' => $stock_lastupdate,
			'use_pid' => true
		];
		$pagecount = json_decode($this->get()['body']);
		$pagecount = $pagecount->data; // PRADUGA JIKA GATEWAY TIMEOUT (KARENA FOR INFINITE LOOP)
		unset($this->body['getpagecount']);

		$update_at = strtotime($stock_lastupdate);
		for($i = 1; $i <= $pagecount; $i++) {
			$this->body['page'] = $i;
			$stock = json_decode($this->get()['body']);
			if($stock->status) {
				foreach($stock->data as $s) {
					if(in_array($s->item_id, $item_ids)) {
						$wc_items = $wpdb->get_results("select wc_item_id from {$table_translasi} where bc_item_id = {$s->item_id}");
						foreach($wc_items as $item) {
							$product = wc_get_product($item->wc_item_id);

							if($product->is_type('variable') AND $s->pid != '') {
								$attributes = [];
								$attr = $product->get_variation_attributes();
								$attributesFromPID = explode('_', $s->pid);
								$i = 0;
								foreach ($attr as $key => $value) {
									$key = 'attribute_'.strtolower($key);
									$attributes[$key] = $attributesFromPID[$i];
									$i++;
								}
								
								$variant_id = BCXWC_Helper::getVariationIdFromAttributes($product, $attributes);
								if($variant_id) {
									$variant = new WC_Product_Variation($variant_id);
									// $variant->set_manage_stock(true);
									$variant->set_stock_quantity($s->qty);
									$status = ($s->qty > 0) ? 'instock' : 'outofstock';
									$variant->set_stock_status($status);
									$variant->save();
									unset($variant);
								}
							}
							else {
								// $product->set_manage_stock(true);
								$product->set_stock_quantity($s->qty);
								$status = ($s->qty > 0) ? 'instock' : 'outofstock';
								$product->set_stock_status($status);
								$product->save();
							}
							unset($product);
						}
						if(strtotime($s->updated_at) > $update_at) {
							$update_at = strtotime($s->updated_at);
						}
					}
				}
			}
			else {
				BCXWC_Logger::error(json_encode($stock->data));
			}
		}
		if($update_at != strtotime($stock_lastupdate) OR !strtotime($stock_lastupdate)) {
			$wpdb->update($table_name, ['value' => date('Y-m-d H:i:s', $update_at)], ['code' => 'STOCK_LASTUPDATE']);
		}
	}

	public function activateLicense(array $body) {
		$this->apikey = $body['apikey'];
		$this->endpoint = 'v1/activatedevice';
		$this->body = json_encode($body);
		$response = $this->post();

		if(is_array($response)) {
			$data = json_decode($response['body']);

			if($data->status AND $data->data->status == true) {
				return $data->data;
			}
			else {
				BCXWC_Logger::error($response['body']);
				return false;
			}
		}
		else {
			BCXWC_Logger::error($response['body']);
			return false;
		}
	}

	public function deactivateLicense() {
		$this->endpoint = 'v1/detachdevice';

		global $wpdb;
		$table_name = $wpdb->prefix . 'wcbc_setting';
		$license = $wpdb->get_var("select value from {$table_name} where code = 'SN'");
		
		$this->body = json_encode(['license' => $license]);
		$response = $this->post();

		if(is_array($response)) {
			$data = json_decode($response['body']);

			if($data->status AND $data->data->status == true) {
				return $data->data;
			}
			else {
				BCXWC_Logger::error($response['body']);
				return false;
			}
		}
		else {
			BCXWC_Logger::error($response['body']);
			return false;
		}
	}

	public function checkLicense() {
		$this->endpoint = 'v1/checklicense';

		global $wpdb;
		$table_name = $wpdb->prefix . 'wcbc_setting';
		$license = $wpdb->get_var("select value from {$table_name} where code = 'SN'");
		
		$this->body = json_encode(['deviceinfo' => get_site_url(), 'license' => $license]);
		$response = $this->post();

		if(is_array($response)) {
			$data = json_decode($response['body']);

			if($data->status) {
				if($data->data->status) {
					$wpdb->update($table_name, ['value' => $data->data->expdate], ['code' => 'EXPDATE']);
					return true;
				}
				else {
					$username = $wpdb->get_var("select value from {$table_name} where code = 'USERNAME'");
			        $plugin_path = dirname(__FILE__) . '/../bcxwc.php';
			        $plugin_header = get_plugin_data($plugin_path);
				 	$param_activateLicense = [
				 		'apikey' => $this->apikey,
				 		'email' => $username,
				 		'devicetype' => 'WOOXBEECLOUD',
				 		'deviceinfo' => get_site_url(),
				 		'deviceversion' => $plugin_header['Version'],
				 		'reactivate' => true
				 	];
				 	return $this->activateLicense($param_activateLicense);
				}
			}
			else {
				BCXWC_Logger::error($response['body']);
				return false;
			}
		}
		else {
			BCXWC_Logger::error($response['body']);
			return false;
		}
	}

	public function saveBulkTransItemsBySku($skus) {
		$this->endpoint = 'v2/initialitem';

		global $wpdb;

		$this->body = [
			'getpagecount' => 1,
			'itemcode' => $skus
		];

		$pagecount = json_decode($this->get()['body']);
		$pagecount = $pagecount->data; // PRADUGA JIKA GATEWAY TIMEOUT (KARENA FOR INFINITE LOOP)
		unset($this->body['getpagecount']);

		$result = [];
		for($i = 1; $i <= $pagecount; $i++) {
			$this->body['page'] = $i;
			$items = json_decode($this->get()['body']);
			if($items->status) {
				foreach ($items->data->item as $item) {
					// $product_id = wc_get_product_id_by_sku($item->code);
					$product_id = array_search($item->code, $skus);

					if($product_id) {
						$table_name = $wpdb->prefix . 'wcbc_translasi_item';
						$update = $wpdb->update($table_name, [
								'bc_item_id' => $item->id, 
								'bc_item_code' => $item->code . ' - ' . $item->name1
							], 
							[
								'wc_item_id' => $product_id
							]);
						$result[] = ['ID' => $skus[$product_id], 'success' => 1];
						unset($skus[$product_id]);
					}
				}
				foreach ($skus as $sku) {
					$result[] = ['ID' => $sku, 'success' => 0];
				}
			}
		}
		return $result;
	}

	public function saveBulkTransItemsByName($names) {
		$this->endpoint = 'v2/initialitem';

		global $wpdb;

		$this->body = [
			'getpagecount' => 1,
			'itemname' => $names
		];

		$pagecount = json_decode($this->get()['body']);
		$pagecount = $pagecount->data; // PRADUGA JIKA GATEWAY TIMEOUT (KARENA FOR INFINITE LOOP)
		unset($this->body['getpagecount']);

		$result = [];
		for($i = 1; $i <= $pagecount; $i++) {
			$this->body['page'] = $i;
			$items = json_decode($this->get()['body']);
			if($items->status) {
				foreach ($items->data->item as $item) {
					$product_id = array_search($item->name1, $names);

					if($product_id) {
						$table_name = $wpdb->prefix . 'wcbc_translasi_item';
						$update = $wpdb->update($table_name, [
								'bc_item_id' => $item->id, 
								'bc_item_code' => $item->code . ' - ' . $item->name1
							], 
							[
								'wc_item_id' => $product_id
							]);
						$result[] = ['ID' => $names[$product_id], 'success' => 1];
						unset($names[$product_id]);
					}
				}
				foreach ($names as $name) {
					$result[] = ['ID' => $name, 'success' => 0];
				}
			}
		}
		return $result;
	}
}
?>
