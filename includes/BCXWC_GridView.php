<?php

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class BCXWC_GridView extends WP_List_Table {
	private $datas;
	private $columns;
	private $perPage = 15;
	private $serial;
	private $totalItem;
	private $topTableNav;

	public function setDatas(Array $data) {
		$this->datas = $data;
	}

	public function setColumns(Array $col) {
		$this->columns = $col;
	}

	public function setPerpage($page) {
		$this->perPage = $page;
	}

	public function setTotalItem($total) {
		$this->totalItem = $total;
	}

	public function setTopTableNav($topTableNav) {
		$this->topTableNav = $topTableNav;
	}

	public function getPerpage() {
		return $this->perPage;
	}

	public function get_columns() {
		return $this->columns;
	}

	public function column_default( $item, $column_name ) {
		return esc_html($item->$column_name);
	}

	public function column_serialid() {
		if(is_null($this->serial)) {
			$this->serial = $this->perPage * ($this->get_pagenum() - 1);
		}
		$this->serial++;
		return $this->serial;
	}

	public function column_wcproductname($item) {
		$pf = new WC_Product_Factory;
		$p = $pf->get_product($item->wc_item_id);
		$sku = $p->get_sku();
		return ($sku ? esc_html($sku).' - ' : '').esc_html($p->get_name());
	}

	// sku digabung ke wcproductname
	// public function column_wcproductsku($item) {
	// 	$pf = new WC_Product_Factory;
	// 	return esc_html($pf->get_product($item->wc_item_id)->get_sku());
	// }

	public function column_bc_item_code($item) {
		$html = '';

		$html .= "<button type='button' class='bc-editable-link'>".esc_html($item->bc_item_code ?: '(belum diset)')."</button>";
		$html .= '<span class="bc-editable-success hidden" style="color:green">&ensp;Tersimpan!</span>';
		$html .= '<div class="bc-editable-input hidden">';
		$html .= '<a class="bc-editable-cancel" href="#"><span class="dashicons dashicons-no-alt"></span></a>';
		$html .= '<select name="wcbc_select2_item" class="bc-editable-select2" style="width:50%;max-width:20em;">';
	 
		if( $item->bc_item_code ) {
			$code = $item->bc_item_code;
			$code = ( mb_strlen( $code ) > 50 ) ? mb_substr( $code, 0, 49 ) . '...' : $code;
			$html .=  '<option value="' . esc_html($item->bc_item_id) . '" selected="selected">' . esc_html($code) . '</option>';
		}
		$html .= '</select>';
		$html .= '<input type="hidden" class="bc-editable-wc_item_id" value="'.esc_html($item->wc_item_id).'">';
		$html .= '<a class="button bc-editable-submit" href="#" > Simpan </a>';
		$html .= '</div>';
	 
		echo $html;
	}

	public function column_sync_status($item) {
		$status = '';
		$label = '';
		switch($item->sync_status) {
			case 'UNSYNCED':
				$status = 'Dalam Antrian';
				$label = 'default';
				break;
			case 'PROCESS':
				$status = 'Dalam Proses';
				$label = 'primary';
				break;
			case 'SYNCED':
				$status = 'Berhasil Tersinkron';
				$label = 'success';
				break;
			case 'ERROR':
			default:
				$status = 'Gagal Tersinkron';
				$label = 'danger';
		}

		return '<span class="bc-label '.$label.'">'.$status.'</span>';
	}

	public function column_total($item) {
		return esc_html(number_format($item->total));
	}

	public function column_sync_at($item) {
		if($item->sync_at != '0000-00-00 00:00:00') {
			return esc_html($item->sync_at);
		}
		return '';
	}

	public function column_sync_process_at($item) {
		if($item->sync_process_at != '0000-00-00 00:00:00') {
			return esc_html($item->sync_process_at);
		}
		return '';
	}

	public function column_wc_order_id($item) {
		return '<b><a href="'.get_edit_post_link($item->wc_order_id).'" target="_blank">#'.esc_html($item->wc_order_id).'</a><b>';
	}

	public function column_trxno_labelprocess($item) {
		$colorClass = [
			'ORDER' => 'primary',
			'CANCEL' => 'danger',
		];
		return $item->trxno.' <span class="bc-label '.$colorClass[$item->process_type].'" style="font-size:10px">'.$item->process_type.'</span>';
	}

	public function column_action_process_sync($item) {
		if($item->sync_status == 'UNSYNCED' OR $item->sync_status == 'ERROR') {
			$ajaxAction = [
				'ORDER' => 'wcbc_upload_order',
				'CANCEL' => 'wcbc_close_order',
			];
			return '
				<a class="button" onclick="return false;" data-so="'.esc_html($item->id).'" ajax-action="'.esc_html($ajaxAction[$item->process_type]).'">
					<span class="dashicons dashicons-upload"></span>
				</a>
				<span class="bc-upload-success hidden" style="color:green">&ensp;Tersinkron!</span>
			';
		}
	}

	function extra_tablenav( $which ) {
		if($which == 'top') {
			if(!is_null($this->topTableNav)) {
				echo $this->topTableNav;
			}
		}
	}

	public function generate() {
		$columns = $this->columns;
		$hidden = [];
		$sortable = [];
		$this->_column_headers = array($columns, $hidden, $sortable);

		$this->set_pagination_args([
		    'total_items' => $this->totalItem,
		    'per_page'    => $this->perPage
		]);

		$this->items = $this->datas;
		$this->display();
	}
}
?>
