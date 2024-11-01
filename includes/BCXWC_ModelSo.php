<?php

class BCXWC_ModelSo {

	private function getNewNo($date = null) {
		global $wpdb;

		if(is_null($date)) {
			$date = date('ymd');
		}
		
		$table_name = $wpdb->prefix . 'wcbc_so';
		$lastId = $wpdb->get_var("select max(id) from {$table_name}") + 1;
		$no = '';
		for($i = 1; $i <= (4 - strlen($lastId)); $i++) {
			$no .= '0';
		}
		$no .= $lastId;

		return $this->getNoCode() . '/' . (date('ymdHi', strtotime($date))) . '/' . $no;
	}

	public function getItemid($wc_product_id) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wcbc_translasi_item';
		$item_id = $wpdb->get_var("select bc_item_id from {$table_name} where wc_item_id = {$wc_product_id}");
		return $item_id;
	}

	private function getNoCode() {
		return 'WOO';
	}

	private function isTaxed() {
		$options = new WC_Admin_Settings;
		$taxed = $options->get_option('woocommerce_calc_taxes');

		if($taxed == 'yes') {
			return true;
		}
		return false;
	}

	public function parseSo($id) {
		global $wpdb;
		$wc_order = new WC_Order($id);
		$order_data = $wc_order->get_data();

		$table_name = $wpdb->prefix . 'wcbc_setting';
		$branch = $wpdb->get_var("select value from {$table_name} where code = 'BRANCH'");

		$result = [];
		$result['wc_order_id'] = $id;
		$order_date = get_date_from_gmt($wc_order->get_date_created()->format('Y-m-d H:i:s'));
		$result['trxno'] = $this->getNewNo($order_date);
		$result['trxdate'] = $order_date;
		$result['bp_id'] = 1;
		$result['crc_id'] = 1;
		$result['branch_id'] = $branch; // sementara v1
		$result['taxed'] = $this->isTaxed();
		$result['taxinc'] = $this->isTaxed() AND $order_data['prices_include_tax'];
		$result['billaddr'] = implode("\r\n", $order_data['billing']);
		$result['shipaddr'] = implode("\r\n", $order_data['shipping']);
		$result['note'] = '';
		$result['subtotal'] = $order_data['total'] - $order_data['total_tax'];
		$result['basesubtotal'] = $result['subtotal'];
		$result['taxamt'] = $order_data['total_tax'];
		$result['basetaxamt'] = $result['taxamt'];
		$result['basefistaxamt'] = $result['taxamt'];
		$result['total'] = $order_data['total'];
		$result['basetotal'] = $result['total'];
		$result['with_ship_cost'] = $wc_order->get_shipping_total() ? true : false;

		return $result;
	}

	public function getDiscount($coupons) {
		$discount = [];
		foreach($coupons as $coupon) {
		    $coupon_post_obj = get_page_by_title($coupon, OBJECT, 'shop_coupon');
		    $coupon_id = $coupon_post_obj->ID;

		    $coupon_obj = new WC_Coupon($coupon_id);
		    $coupon_data = $coupon_obj->get_data();

		    $discount[$coupon] = [
		    	'include_product_id' => $coupon_data['product_ids'],
		    	'exclude_product_id' => $coupon_data['excluded_product_ids'],
		    	'is_percent' => ($coupon_data['discount_type'] == 'percent' ?: false),
		    	'amount' => $coupon_data['amount'],
		    ];
		}

		return $discount;
	}

	public function parseSod($so_id, $order_id) {
		$wc_order = new WC_Order($order_id);
		$order_data = $wc_order->get_items();

		$discount = $this->getDiscount($wc_order->get_coupon_codes());
		$decimals = wc_get_price_decimals();

		$result = [];
		$dno = 1;
		foreach($order_data as $detail) {
			$data = $detail->get_data();

			$sod = [];
			$sod['so_id'] = $so_id;
			$sod['dno'] = $dno;
			$sod['itemname'] = $data['name'];
			$sod['wc_product_id'] = $data['product_id'];
			$sod['item_id'] = $this->getItemid($data['product_id']);

			$product = $detail->get_product();
			if($product->is_type('variation')) {
				$variation_attributes = $product->get_variation_attributes();
				// asort($variation_attributes);
				$sod['pid'] = implode('_', $variation_attributes);
			}

			$product_data = $product->get_data();

			$sod['qty'] = $data['quantity'];
			$sod['listprice'] = $product_data['price'];
			$sod['baseprice'] = $wc_order->get_data()['prices_include_tax'] ? number_format(($data['subtotal'] / $sod['qty']), $decimals, '.', '') : $product_data['price'];
			$sod['dnote'] = 'Product WOO : '.$data['name'];

			$discexp = '';
			$discamt = 0;
			$tmpPrice = $sod['baseprice'];
			foreach($discount as $disc) {
				if((empty($disc['include_product_id']) OR (!empty($disc['include_product_id']) AND in_array($data['product_id'], $disc['include_product_id']))) AND !in_array($data['product_id'], $disc['exclude_product_id'])) {
					if($discexp != '') {
						$discexp .= ' + ';
					}
					$discexp .= $disc['amount'] . ($disc['is_percent'] ? '%' : '');

					$tmpDisc = $disc['is_percent'] ? $tmpPrice * $disc['amount'] / 100 : $disc['amount'];
					$discamt += $tmpDisc;
					$tmpPrice -= $tmpDisc;
				}
			}

			$sod['discexp'] = $discexp;
			$sod['discamt'] = $discamt;
			$sod['totaldiscamt'] = $discamt * $sod['qty'];
			$sod['subtotal'] = $sod['qty'] * ($sod['baseprice'] - $sod['discamt']);
			$sod['subtotal'] = number_format($sod['subtotal'], $decimals, '.', '');
			$sod['basesubtotal'] = $sod['subtotal'];

			$taxes = $wc_order->get_items('tax');
			foreach($taxes as $tax) {
				$sod['tax_code'] = $tax->get_label();
				break; // sementara cuma 1
			}
			$sod['taxableamt'] = ($this->isTaxed() AND !is_null($sod['tax_code'])) ? $sod['subtotal'] : 0;
			$sod['taxamt'] = $this->isTaxed() ? $data['total_tax'] / $sod['qty'] : 0;
			$sod['totaltaxamt'] = $this->isTaxed() ? $data['total_tax'] : 0;
			$sod['basetotaltaxamt'] = $sod['totaltaxamt'];
			$sod['basefistotaltaxamt'] = $sod['totaltaxamt'];

			$dno++;

			$result[] = $sod;
		}

		if($wc_order->get_shipping_total() != 0) {
			$result[] = $this->getShippingDetail($wc_order, $so_id, $dno);
		}

		return $result;
	}

	public function getShippingDetail($wc_order, $so_id, $dno) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcbc_setting';
		$itemongkir = $wpdb->get_row("select * from {$table_name} where code = 'ITEMONGKIR'");

		$itemongkir_id = 0;
		$itemongkir_name = 'ONGKIR';
		if($itemongkir AND $itemongkir->value != '') {
			$itemongkir_id = $itemongkir->value;
			$itemongkir_name = $itemongkir->helper;
		}

		$sod = [];
		$sod['so_id'] = $so_id;
		$sod['dno'] = $dno;
		$sod['itemname'] = $itemongkir_name;
		$sod['wc_product_id'] = 0;
		$sod['item_id'] = $itemongkir_id;

		$sod['qty'] = 1;
		$sod['listprice'] = $wc_order->get_shipping_total();
		$sod['baseprice'] = $sod['listprice'];
		$sod['dnote'] = 'Item Ongkir';

		$sod['discexp'] = '';
		$sod['discamt'] = 0;
		$sod['totaldiscamt'] = 0;
		$sod['subtotal'] = $sod['listprice'];
		$sod['basesubtotal'] = $sod['subtotal'];

		$sod['taxableamt'] = 0;
		$sod['taxamt'] = 0;
		$sod['totaltaxamt'] = 0;
		$sod['basetotaltaxamt'] = $sod['totaltaxamt'];
		$sod['basefistotaltaxamt'] = $sod['totaltaxamt'];

		return $sod;
	}
}
?>
