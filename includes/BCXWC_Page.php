<?php

class BCXWC_Page {

	static function wcbc_check_is_logged_in() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcbc_setting';

		$is_logged_in = $wpdb->get_var("select value from {$table_name} where code = 'APIKEY'");

		if(!$is_logged_in) {
			$step = 'LOGIN';
			if(isset($_POST['bc-email']) AND isset($_POST['bc-password'])) {
				// api tidak dimasukkan ke BCXWC_IntegrationAPI karena tidak pakai bearer
				$login = wp_remote_post( 'https://provision.beecloud.id/v1/auth/logindesktop', [
					'headers' => [
						'Content-Type' => 'application/json; charset=utf-8'
					],
					'method' => 'POST',
					'timeout' => 75,				    
					'body' => json_encode([
						"username" => BCXWC_Helper::sanitize_email($_POST['bc-email']), 
						"password" => BCXWC_Helper::sanitize($_POST['bc-password'])
					])
				]);	

				if(is_array($login)) {
					$login = json_decode($login['body']);

					if($login->status) {
						if(count($login->data->db) == 1) {
							$resp_apikey = self::wcbc_save_apikey($login->data->username, $login->data->db[0]->dbname);
						}
						else {
							$step = 'DATABASE';
						}
					}
					else {
						$error = $login->data;
					}
				}
				else {
					BCXWC_Logger::error(json_encode($login));
					wp_redirect(admin_url('admin.php?page=wcbc_menu_setting&error'));
					exit;
				}
			}

			//////////////////////////////////////
			// PILIH DATABASE
			//////////////////////////////////////
			if(isset($_POST['bc-database'])) {
				$username = BCXWC_Helper::sanitize_email($_POST['bc-username']);
				$database = BCXWC_Helper::sanitize($_POST['bc-database']);
				$error = self::wcbc_save_apikey($username, $database);
			}

			if(isset($_GET['error'])) {
				$error = 'Maaf, terjadi kesalahan!';
			}

			echo "<div class='wrap'><div class='postbox'><div class='inside'>";
			if($step == 'LOGIN') {
				echo "<h1 class=\"wp-heading-inline\">Login Beecloud</h1>";
				if(isset($error)) echo '<div class="bc-alert">'.($error).'</div>';
				echo "<form method='POST' class='bc-login'>";
					echo '
						<div>
						    <label for="bc-email">Email</label>
						    <input type="email" id="bc-email" class="input" name="bc-email" autocomplete="off" value="" required>
					    </div>
						<div>
						    <label for="bc-password">Password</label>
						    <input type="password" id="bc-password" class="input" name="bc-password" autocomplete="off" value="" required>
					    </div>';
					echo '
						<p class="submit">
							<input type="submit" name="bc-login" class="button-primary woocommerce-save-button" value="Simpan">	
						</p>
						<p>tidak punya akun beecloud? daftar <a href="https://my.beecloud.id/trial" target="_blank">disini</a>.</p>';
				echo "</form>";
			}
			else if($step == 'DATABASE') {
				echo "<h1 class=\"wp-heading-inline\">Pilih Database Perusahaan</h1>";
				if(isset($error)) echo '<div class="bc-alert">'.($error).'</div>';
				echo "<form method='POST' class='bc-login'>";
					echo '<input type="hidden" name="bc-username" value="'.$login->data->username.'">';
					foreach($login->data->db as $db) {
						echo '<br><button type="submit" name="bc-database" class="button-primary woocommerce-save-button" value="'.$db->dbname.'">'.$db->cmpname.'</button><br>';
					}
				echo "</form>";
			}
			echo "</div></div></div>";

			// die untuk login
			wp_die();
		}
	}

	static function wcbc_save_apikey($username, $dbname) {
		// api tidak dimasukkan ke BCXWC_IntegrationAPI karena tidak pakai bearer
		$resp_apikey = wp_remote_post( 'https://provision.beecloud.id/v1/auth/apikeydesktop', [
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8'
			],
			'method' => 'POST',
			'timeout' => 75,				    
			'body' => json_encode(["username" => $username, "dbname" => $dbname])
		]);

		if(is_array($resp_apikey)) {
			$data = json_decode($resp_apikey['body']);
			if($data->status) {
			 	$api = new BCXWC_IntegrationAPI();

		        $plugin_path = dirname(__FILE__) . '/../bcxwc.php';
		        $plugin_header = get_plugin_data($plugin_path);
			 	$param_activateLicense = [
			 		'apikey' => $data->data,
			 		'email' => $username,
			 		'devicetype' => 'BCXWOO',
			 		'deviceinfo' => get_site_url(),
			 		'deviceversion' => $plugin_header['Version'],
			 	];
			 	$resp_activatelicense = $api->activateLicense($param_activateLicense);
			 	if(!$resp_activatelicense) {
			 		return 'Maaf, anda tidak mempunyai lisensi yang aktif, silahkan cek di <a href="https://my.beecloud.id" target="_blank">my.beecloud.id</a>';
			 	}

				global $wpdb;
				$table_name = $wpdb->prefix . 'wcbc_setting';
				$wpdb->insert($table_name, ['value' => $data->data, 'code' => 'APIKEY']);
				$wpdb->insert($table_name, ['value' => $username, 'code' => 'USERNAME']);
				$wpdb->insert($table_name, ['value' => $resp_activatelicense->cmpname, 'code' => 'CMPNAME']);
				$wpdb->insert($table_name, ['value' => $resp_activatelicense->serial_number, 'code' => 'SN']);
				$wpdb->insert($table_name, ['value' => $resp_activatelicense->expdate, 'code' => 'EXPDATE']);
				$wpdb->insert($table_name, ['value' => '', 'code' => 'WH']);
				$wpdb->insert($table_name, ['value' => '', 'code' => 'PRICELVL']);
				$wpdb->insert($table_name, ['value' => '', 'code' => 'BRANCH']);
				$wpdb->insert($table_name, ['value' => '', 'code' => 'ITEMONGKIR']);
				$wpdb->insert($table_name, ['value' => '', 'code' => 'PRICE_LASTUPDATE']);
				$wpdb->insert($table_name, ['value' => '', 'code' => 'STOCK_LASTUPDATE']);
				$wpdb->insert($table_name, ['value' => '3', 'code' => 'SYNCPER']);
				wp_redirect(admin_url('admin.php?page=wcbc_menu_setting'));
				exit;
			}
			else {
				BCXWC_Logger::error(json_encode($data->data));
				wp_redirect(admin_url('admin.php?page=wcbc_menu_setting&error'));
				exit;
			}
		}
		else {
			BCXWC_Logger::error(json_encode($resp_apikey));
			wp_redirect(admin_url('admin.php?page=wcbc_menu_setting&error'));
			exit;
		}
	}

	static function wcbc_func_view_menu_setting() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcbc_setting';

		self::wcbc_check_is_logged_in();
		self::wcbc_check_is_expired();

		if(isset($_POST['bc-logout'])) {
			$table_so = $wpdb->prefix . 'wcbc_so';
			$unsynced = $wpdb->get_var("select exists(select 1 from {$table_so} where sync_status in (\"UNSYNCED\", \"ERROR\") limit 1)");
			if($unsynced) {
				$error = 'Logout gagal, karena masih ada order yang masih belum tersinkron! bisa dicek <a href="'.admin_url('admin.php?page=wcbc_menu_order').'">disini</a>';
			}
			else {
				wcbc_uninstall();
				wcbc_on_activation();
				wp_redirect(admin_url('admin.php?page=wcbc_menu_setting'));
			}
		}

		if(isset($_POST['bc-save-setting'])) {

			if(isset($_POST['bc-wh-select2'])) {
				$wh = implode(',', BCXWC_Helper::sanitize_array($_POST['bc-wh-select2']));
				$helper = json_encode(BCXWC_Helper::sanitize_array($_POST['bc-wh-value']));
				$wpdb->update($table_name, ['value' => $wh, 'helper' => $helper], ['code' => 'WH']);
			}
			else {
				$wh_tmp = $wpdb->get_row("select * from {$table_name} where code = 'WH'");

				if($wh_tmp) {
					$wpdb->update($table_name, ['value' => '', 'helper' => ''], ['code' => 'WH']);
				}
			}

			if(isset($_POST['bc-pricelvl-select2'])) {
				$wpdb->update($table_name, [
					'value' => BCXWC_Helper::sanitize_int($_POST['bc-pricelvl-select2']), 
					'helper' => BCXWC_Helper::sanitize($_POST['bc-pricelvl-value'])
				], 
				['code' => 'PRICELVL']);
			}
			else {
				$pricelvl_tmp = $wpdb->get_row("select * from {$table_name} where code = 'PRICELVL'");
				
				if($pricelvl_tmp) {
					$wpdb->update($table_name, ['value' => '', 'helper' => ''], ['code' => 'PRICELVL']);
				}
			}

			if(isset($_POST['bc-branch-select2'])) {
				$wpdb->update($table_name, [
					'value' => BCXWC_Helper::sanitize_int($_POST['bc-branch-select2']), 
					'helper' => BCXWC_Helper::sanitize($_POST['bc-branch-value'])
				], ['code' => 'BRANCH']);
			}
			else {
				$branch_tmp = $wpdb->get_row("select * from {$table_name} where code = 'BRANCH'");
				
				if($branch_tmp) {
					$wpdb->update($table_name, ['value' => '', 'helper' => ''], ['code' => 'BRANCH']);
				}
			}

			if(isset($_POST['bc-itemongkir-select2'])) {
				$wpdb->update($table_name, [
					'value' => BCXWC_Helper::sanitize_int($_POST['bc-itemongkir-select2']), 
					'helper' => BCXWC_Helper::sanitize($_POST['bc-itemongkir-value'])
				], ['code' => 'ITEMONGKIR']);

				$table_so = $wpdb->prefix . 'wcbc_so';
				$table_sod = $wpdb->prefix . 'wcbc_sod';
				$wpdb->query("update {$table_sod} set item_id = ".BCXWC_Helper::sanitize_int($_POST['bc-itemongkir-select2'])." where exists(select 1 from {$table_so} where {$table_so}.id = {$table_sod}.so_id and sync_status <> 'SYNCED') and wc_product_id = 0;");
			}
			else {
				$branch_tmp = $wpdb->get_row("select * from {$table_name} where code = 'ITEMONGKIR'");
				
				if($branch_tmp) {
					$wpdb->update($table_name, ['value' => '', 'helper' => ''], ['code' => 'ITEMONGKIR']);
				}
			}

			if(isset($_POST['bc-period-sync'])) {
				$updated = $wpdb->update($table_name, ['value' => BCXWC_Helper::sanitize_int($_POST['bc-period-sync'])], ['code' => 'SYNCPER']);

				if($updated) {
					add_filter( 'cron_schedules', 'wcbc_add_fiveminutes_cron_interval' );
				}
			}
		}

		if(isset($_POST['bc-download-error']) OR isset($_POST['bc-download-log'])) {
			$table_name = $wpdb->prefix . 'wcbc_log';
			$isErr = isset($_POST['bc-download-error']) ? 'true' : 'false';
			$log = $wpdb->get_results("select * from {$table_name} where error is {$isErr} order by id desc limit 100");
			
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false);
			header("Content-Type: application/octet-stream");
			if($isErr == 'true') {
				header("Content-Disposition: attachment; filename=\"wcbc_error.txt\";" );
			}
			else {
				header("Content-Disposition: attachment; filename=\"wcbc_log.txt\";" );
			}
			header("Content-Transfer-Encoding: binary");

			ob_end_clean();
			echo json_encode($log);
			exit;
		}

		if(isset($_POST['bc-update-stock'])) {
			$tablesetting = $wpdb->prefix . 'wcbc_setting';
			$wpdb->update($tablesetting, ['value' => '2000-01-01'], ['code' => 'STOCK_LASTUPDATE']);

		 	$api = new BCXWC_IntegrationAPI();
			$api->updateStock();

			echo '<div class="bc-alert-success">Stock berhasil diupdate.</div>';
		}

		$wh = $wpdb->get_row("select * from {$table_name} where code = 'WH'");
		$pricelvl = $wpdb->get_row("select * from {$table_name} where code = 'PRICELVL'");
		$branch = $wpdb->get_row("select * from {$table_name} where code = 'BRANCH'");
		$period = $wpdb->get_row("select * from {$table_name} where code = 'SYNCPER'");
		$itemongkir = $wpdb->get_row("select * from {$table_name} where code = 'ITEMONGKIR'");
		$cmpname = $wpdb->get_var("select value from {$table_name} where code = 'CMPNAME'");
		$email = $wpdb->get_var("select value from {$table_name} where code = 'USERNAME'");
		$license = $wpdb->get_var("select value from {$table_name} where code = 'SN'");
		$expdate = $wpdb->get_var("select value from {$table_name} where code = 'EXPDATE'");

		$wh_opt = '';
		$wh_val = '';
		if($wh AND $wh->helper != '') {
			// die(var_dump($wh->helper));
			foreach(json_decode($wh->helper) as $key => $label) {
				$wh_opt .= "<option value='".esc_html($key)."' selected='selected'>".esc_html($label)."</option>";
				$wh_val .= "<input type='hidden' name='bc-wh-value[".esc_html($key)."]' value='".esc_html($label)."'/>";
			}
		}

		$pricelvl_opt = '';
		$pricelvl_val = '';
		if($pricelvl AND $pricelvl->value != '') {
			$pricelvl_opt = "<option value='".esc_html($pricelvl->value)."' selected='selected'>".esc_html($pricelvl->helper)."</option>";
			$pricelvl_val = esc_html($pricelvl->helper);
		}

		$branch_opt = '';
		$branch_val = '';
		if($branch AND $branch->value != '') {
			$branch_opt = "<option value='".esc_html($branch->value)."' selected='selected'>".esc_html($branch->helper)."</option>";
			$branch_val = esc_html($branch->helper);
		}

		$itemongkir_opt = '';
		$itemongkir_val = '';
		if($itemongkir AND $itemongkir->value != '') {
			$itemongkir_opt = "<option value='".esc_html($itemongkir->value)."' selected='selected'>".esc_html($itemongkir->helper)."</option>";
			$itemongkir_val = esc_html($itemongkir->helper);
		}

		$active_tab = 'setting';
		if(isset($_GET['tab'])) {
            $active_tab = BCXWC_Helper::sanitize($_GET['tab']);
        }

		echo "<div class='wrap'>";
			echo "<h1 class=\"wp-heading-inline\">Pengaturan Integrasi Beecloud</h1>";
			echo "<h2 class=\"nav-tab-wrapper\">
    				<a href=\"?page=wcbc_menu_setting&tab=setting\" class=\"nav-tab ".($active_tab == 'setting' ? 'nav-tab-active' : '')."\">Pengaturan</a>
    				<a href=\"?page=wcbc_menu_setting&tab=info_acc\" class=\"nav-tab ".($active_tab == 'info_acc' ? 'nav-tab-active' : '')."\">Informasi Akun</a>
    				<a href=\"?page=wcbc_menu_setting&tab=log\" class=\"nav-tab ".($active_tab == 'log' ? 'nav-tab-active' : '')."\">Log</a>
				</h2>";
			if(isset($error)) echo '<div class="bc-alert">'.esc_html($error).'</div>';
			if(isset($_POST['bc-save-setting'])) echo '<div class="bc-alert-success">Berhasil tersimpan.</div>';
			echo "<form method='POST'>";
				// echo '<div class="bc-col-50">';
				if($active_tab == 'setting') {
					echo '
						<div id="bc-setting" style="width:70%;float:left">
							<table class="form-table">
								<tbody>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-branch-select2">Cabang</label>
										</th>
										<td class="forminp">
											<select id="bc-branch-select2" name="bc-branch-select2" style="width:80%;max-height:30px;">'.$branch_opt.'</select>
											<input type="hidden" name="bc-branch-value" value="'.$branch_val.'"/>
											<!--<span class="description"><br>Level harga untuk update harga.</span>-->
										</td>
									</tr>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-wh-select2">Gudang</label>
										</th>
										<td class="forminp">
											<select id="bc-wh-select2" name="bc-wh-select2[]" style="width:80%;max-height:30px;" multiple="true">
												'.$wh_opt.'
											</select>
											'.$wh_val.'
											<span class="description"><br>Gudang untuk update stok.</span>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-pricelvl-select2">Level harga</label>
										</th>
										<td class="forminp">
											<select id="bc-pricelvl-select2" name="bc-pricelvl-select2" style="width:80%;max-height:30px;">'.$pricelvl_opt.'</select>
											<input type="hidden" name="bc-pricelvl-value" value="'.$pricelvl_val.'"/>
											<span class="description"><br>Level harga untuk update harga.</span>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-period-sync">Periode waktu sinkron</label>
										</th>
										<td class="forminp forminp-number">
											<input name="bc-period-sync" id="bc-period-sync" type="number" style="width:50px;" value="'.esc_html($period->value).'" class="" placeholder="" min="3" step="1"> menit
											<span class="description"><br>Periode waktu untuk update stok, update harga, dan upload order</span>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-itemongkir-select2">Item Ongkir</label>
										</th>
										<td class="forminp">
											<select id="bc-itemongkir-select2" name="bc-itemongkir-select2" style="width:80%;max-height:30px;">'.$itemongkir_opt.'</select>
											<input type="hidden" name="bc-itemongkir-value" value="'.$itemongkir_val.'"/>
											<span class="description"><br>Item yang akan digunakan sebagai ongkir di Order Penjualan.</span>
										</td>
									</tr>
								</tbody>
							</table>';
					echo '
							<p class="submit">
								<input name="bc-save-setting" class="button-primary woocommerce-save-button" type="submit" value="Simpan">		
							</p>
						</div>';
					echo '
						<input name="bc-update-stock" class="button-primary bc-btn-download" type="submit" value="Update Stock" style="float:right">		
					';

				}
				else if($active_tab == 'info_acc') {
					echo '
						<!--<div class="card bc-col-50">-->
						<div>
							<div class="bc-card-header">
								<h2>Informasi Akun Beecloud</h2>
								<input name="bc-logout" id="bc-btn-logout" class="button" type="submit" value="Logout">		
							</div>
							<table class="form-table">
								<tbody>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-branch-select2">Nama Website</label>
										</th>
										<td class="forminp">
											'.esc_html(get_bloginfo('name')).'
										</td>
									</tr>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-branch-select2">Nama Perusahaan</label>
										</th>
										<td class="forminp">
											'.esc_html($cmpname).'
										</td>
									</tr>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-branch-select2">Email</label>
										</th>
										<td class="forminp">
											'.esc_html($email).'
										</td>
									</tr>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-branch-select2">Serial Number Lisensi</label>
										</th>
										<td class="forminp">
											'.esc_html($license).'
										</td>
									</tr>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="bc-branch-select2">Masa Aktif Lisensi s/d</label>
										</th>
										<td class="forminp">
											'.esc_html(date('d F Y', strtotime($expdate))).'
										</td>
									</tr>
								</tbody>
							</table>
						</div>';
				}
				else {
					$table_name = $wpdb->prefix . 'wcbc_log';
					$log = $wpdb->get_results("select * from {$table_name} where error is false order by id desc limit 100");
					$err = $wpdb->get_results("select * from {$table_name} where error is true order by id desc limit 100");
					echo '
						<div class="card bc-col-50">
							<div>
								<div class="bc-card-header">
									<h2>Error</h2>
									<input name="bc-download-error" id="bc-btn-download-error" class="button bc-btn-download" type="submit" value="Download error.txt">		
								</div>
								<table class="form-table bc-table-log">
									<tbody>';
					foreach($err as $e) {
									echo '<tr valign="top">
											<td scope="row" class="">
												'.esc_html($e->id).'
											</td>
											<td scope="row" class="">
												'.esc_html($e->date).'
											</td>
											<td class="">
												'.esc_html(substr($e->text, 0, 200)).'
											</td>
										</tr>';
					}
					echo '			</tbody>
								</table>
							</div>
						</div>';

					echo '
						<div class="card bc-col-50">
							<div>
								<div class="bc-card-header">
									<h2>Main Log</h2>
									<input name="bc-download-log" id="bc-btn-download-log" class="button bc-btn-download" type="submit" value="Download log.txt">		
								</div>
								<table class="form-table bc-table-log">
									<tbody>';
					foreach($log as $l) {
									echo '<tr valign="top">
											<td scope="row" class="">
												'.esc_html($l->id).'
											</td>
											<td scope="row" class="">
												'.esc_html($l->date).'
											</td>
											<td class="">
												'.esc_html($l->text).'
											</td>
										</tr>';
					}
					echo '			</tbody>
								</table>
							</div>
						</div>';
				}
			echo "</form>";
		echo "</div>";
	}

	static function wcbc_func_view_menu_transitem() {

		global $wpdb;

		self::wcbc_check_is_logged_in();
		self::wcbc_check_is_expired();

		if(isset($_GET['bulk'])) {
			self::wcbc_func_view_menu_transitem_bulk();
			return;
		}

		$gridview = new BCXWC_GridView();
		$pf = new WC_Product_Factory;

		$data = [];
		$table_name = $wpdb->prefix . 'wcbc_translasi_item';
		$offset = $gridview->getPerpage() * ($gridview->get_pagenum() - 1);

		$wcbc_item_translated = isset($_POST['wcbc-item-translated']) ? BCXWC_Helper::sanitize($_POST['wcbc-item-translated']) : '';
		$where = '';
		if($wcbc_item_translated != '') {
			if($wcbc_item_translated == 'matched') {
				$where = 'where bc_item_id is not null';
			}
			else if($wcbc_item_translated == 'unmatched') {
				$where = 'where bc_item_id is null';
			}
		}

		$products = $wpdb->get_results("select bcti.* from {$table_name} bcti {$where} order by case when bc_item_id is null then bc_item_id else wc_item_id end limit {$gridview->getPerpage()} offset {$offset}");

		$count = $wpdb->get_var("select count(id) from {$table_name} {$where}");
		$gridview->setTotalItem($count);

		$gridview->setDatas($products);

		$filter = "
			<div class='alignleft actions bulkactions'>
				<form method='POST'>
					<label for='wcbc-filter-translasi-item' style='float:left;font-size: 15px;padding: 5px 10px 5px 0;'>Filter : </label>
					<select name='wcbc-item-translated' id='wcbc-filter-translasi-item'>
						<option>Semua</option>
						<option value='unmatched' ".($wcbc_item_translated == 'unmatched' ? 'selected=\"selected\"' : '').">Belum diset</option>
						<option value='matched' ".($wcbc_item_translated == 'matched' ? 'selected=\"selected\"' : '').">Sudah diset</option>
					</select>
					<input type='submit' class='button'/>
				</form>
			</div>
		";

		$gridview->setTopTableNav($filter);

		$gridview->setColumns([
			'serialid' => '#',
			//  jika lemot wcproductname bisa diganti langsung ke select join wp_posts
			// 'wcproductsku' => 'SKU pada Woocommerce', // digabung ke wcproductname
			'wcproductname' => 'Produk pada Woocommerce',
			'bc_item_code' => 'Produk pada Beecloud'
		]);

		echo "<div class='wrap'>";
			echo "<h1 class=\"wp-heading-inline\">Translasi Item <a href='".admin_url('admin.php?page=wcbc_menu_translasi_item&bulk=true')."' class='page-title-action'>Bulk Translasi</a></h1>";
			$gridview->generate();
		echo "</div>";
	}

	static function wcbc_func_view_menu_order() {
		global $wpdb;

		self::wcbc_check_is_logged_in();
		self::wcbc_check_is_expired();

		$gridview = new BCXWC_GridView();
		$pf = new WC_Product_Factory;

		$data = [];
		$table_name = $wpdb->prefix . 'wcbc_so';
		$table_process = $wpdb->prefix . 'wcbc_so_process';
		$offset = $gridview->getPerpage() * ($gridview->get_pagenum() - 1);

		$wcbc_status_so = isset($_POST['wcbc-status-so']) ? BCXWC_Helper::sanitize($_POST['wcbc-status-so']) : '';
		$where = '';
		if($wcbc_status_so != '') {
			if($wcbc_status_so == 'synced') {
				$where = 'where sync_status = \'SYNCED\'';
			}
			else if($wcbc_status_so == 'unsync') {
				$where = 'where sync_status = \'UNSYNCED\'';
			}
		}

		$orders = $wpdb->get_results("
			SELECT * FROM (
				SELECT id, wc_order_id, trxno, trxdate, total, 'ORDER' as process_type, sync_status, sync_at, sync_process_at, sync_note 
				FROM {$table_name} 
				{$where} 
				UNION ALL 
				SELECT sp.id, wc_order_id, trxno, sp.trxdate, total, sp.process_type, sp.sync_status, sp.sync_at, sp.sync_process_at, sp.sync_note 
				FROM {$table_process} sp
				JOIN {$table_name} on so_id = {$table_name}.id
				{$where}
			) as wcbc_order
			ORDER BY CASE WHEN sync_status IN('ERROR', 'UNSYNCED') THEN 1 ELSE 2 END, trxdate DESC 
			LIMIT {$gridview->getPerpage()} offset {$offset}");

		$count = $wpdb->get_var("select count(id) from {$table_name} {$where}");
		$gridview->setTotalItem($count);

		$gridview->setDatas($orders);

		$filter = "
			<div class='alignleft actions bulkactions'>
				<form method='POST'>
					<label for='wcbc-filter-so-status' style='float:left;font-size: 15px;padding: 5px 10px 5px 0;'>Filter : </label>
					<select name='wcbc-status-so' id='wcbc-filter-so-status'>
						<option>Semua</option>
						<option value='unsync' ".($wcbc_status_so == 'unsync' ? 'selected=\"selected\"' : '').">Belum Tersinkron</option>
						<option value='synced' ".($wcbc_status_so == 'synced' ? 'selected=\"selected\"' : '').">Sudah Tersinkron</option>
					</select>
					<input type='submit' class='button'/>
				</form>
			</div>
		";

		$gridview->setTopTableNav($filter);

		$gridview->setColumns([
			'serialid' => '#',
			'trxno_labelprocess' => 'No. Order',
			'wc_order_id' => 'No. Woo',
			'trxdate' => 'Tgl. Order',
			'total' => 'Total',
			'sync_status' => 'Status',
			'sync_at' => 'Tanggal Sinkron',
			'sync_process_at' => 'Terakhir Proses',
			'sync_note' => 'Keterangan',
			'action_process_sync' => 'Sinkron',
		]);

		echo "<div class='wrap'>";
			echo "<h1 class=\"wp-heading-inline\">Sinkron Beecloud</h1>";
			$gridview->generate();
		echo "</div>";
	}

	static function wcbc_check_is_expired() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcbc_setting';

		$expdate = $wpdb->get_var("select value from {$table_name} where code = 'EXPDATE'");

		if(strtotime($expdate) < strtotime(date('Y-m-d 00:00:00'))) {
			echo "<div class='wrap'><div class='postbox'><div class='inside'>";
				echo '<h2 align="center">Maaf, masa aktif lisensi Woocommerce x Beecloud anda sudah habis!</h2>';
				echo '<p align="center">Untuk proses perpanjangan lisensi bisa dibuka di <a href="https://my.beecloud.id" target="_blank">my.beecloud.id</a></p>';
				echo '<br>';
				echo '<p align="center">*Jika sudah order dan terkonfirmasi, bisa di refresh atau klik tombol dibawah ini:</p>';
				echo '<p align="center"><input name="bc-reload-expired" id="bc-reload-expired" class="button-primary woocommerce-save-button" type="submit" value="Refresh"></p>';
			echo "</div></div></div>";
			
			wp_die();
		}
	}

	static function wcbc_func_view_menu_transitem_bulk() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wcbc_translasi_item';
		$allItemCount = $wpdb->get_var("select count(id) from {$table_name}");
		$untranslatedItemCount = $wpdb->get_var("select count(id) from {$table_name} where bc_item_id is null");
		$limit = 500;
		$replaceProcessIteration = ceil($allItemCount / $limit);
		$nonReplaceProcessIteration = ceil($untranslatedItemCount / $limit);

		echo "<div class='wrap'>";
			echo "<h1 class=\"wp-heading-inline\">Bulk Translasi Item</h1>";
			echo "<form method='POST'>";
				echo '
					<div id="bc-bulk-translasi" style="width:70%;float:left">
						<table class="form-table">
							<tbody>
								<tr valign="top">
									<th scope="row" class="titledesc">
										<label for="bc-dropdown-key-bulk-trans">Translasi Berdasarkan</label>
									</th>
									<td class="forminp">
										<select id="bc-dropdown-key-bulk-trans" name="bc-dropdown-key-bulk-trans" style="width:80%;max-height:30px;">
											<option value="SKU">Kode (SKU) Produk / Item</option>
											<option value="NAME">Nama Produk / Item</option>
										</select>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row" class="titledesc">
										<label for="bc-checkbox-replace-bulk">Replace</label>
									</th>
									<td class="forminp">
										<fieldset>
											<ul style="margin:0">
												<li>
													<label><input name="bc-checkbox-replace-bulk" value="true" type="radio" checked="checked"> Ya, Replace Semua</label>
												</li>
												<li>
													<label><input name="bc-checkbox-replace-bulk" value="false" type="radio"> Tidak, Hanya yang Belum Translasi</label>
												</li>
											</ul>
										</fieldset>
									</td>
								</tr>
							</tbody>
						</table>';
				echo '
						<p class="submit">
							<a id="bc-proses-bulk" class="button-primary woocommerce-save-button" value="Proses">Proses</a>
						</p>
					</div>';
				echo '
					<input type="hidden" id="bc-replace-process-iteration" value="'.$replaceProcessIteration.'">
					<input type="hidden" id="bc-non-replace-process-iteration" value="'.$nonReplaceProcessIteration.'">
				';
			echo '</form>';
			echo '<div id="bc-log-bulk-translasi" style="float:left"></div>';
		echo "</div>";
	}	
}
?>
