<?php
/*
 * Plugin Name: Beecloud WooCommerce Integration
 * Version:     1.24.032501
 * Author:      Beecloud
 * Author URI:  https://beecloud.id
 * Description: Akuntansi Beecloud di WooCommerce
 * 
 * WC tested up to: 8.6.1
 */



if( ! class_exists( 'BCXWC_GridView' ) ) {
    require_once( 'includes/BCXWC_GridView.php' );
}
if( ! class_exists( 'BCXWC_IntegrationAPI' ) ) {
    require_once( 'includes/BCXWC_IntegrationAPI.php' );
}
if( ! class_exists( 'BCXWC_ModelSo' ) ) {
    require_once( 'includes/BCXWC_ModelSo.php' );
}
if( ! class_exists( 'BCXWC_AjaxCallback' ) ) {
    require_once( 'includes/BCXWC_AjaxCallback.php' );
}
if( ! class_exists( 'BCXWC_TableCreator' ) ) {
    require_once( 'includes/BCXWC_TableCreator.php' );
}
if( ! class_exists( 'BCXWC_Page' ) ) {
    require_once( 'includes/BCXWC_Page.php' );
}
if( ! class_exists( 'BCXWC_Logger' ) ) {
    require_once( 'includes/BCXWC_Logger.php' );
}
if( ! class_exists( 'BCXWC_Task' ) ) {
    require_once( 'includes/BCXWC_Task.php' );
}
if( ! class_exists( 'BCXWC_Helper' ) ) {
    require_once( 'includes/BCXWC_Helper.php' );
}


///////////////////////////////////////////////////////////////////////////////////////////////////////



/////////////////////////////////
// TABLE CREATION
/////////////////////////////////
register_activation_hook( __FILE__, 'wcbc_on_activation' );
function wcbc_on_activation() {
	$woocommerce = class_exists( 'WooCommerce' );
	if(!$woocommerce) {
		deactivate_plugins( basename( __FILE__ ) );
		wp_die("Maaf, Plugin woocommerce tidak ditemukan.");
	}
	
	$tc = new BCXWC_TableCreator();
	$tc->wcbc_create_log(); // buat table log
	$tc->wcbc_create_translasi_table(); // buat table untuk translasi item bc dan woo
	$tc->wcbc_create_so_table(); // buat table so
	$tc->wcbc_create_sod_table(); // buat table sod
	$tc->wcbc_create_setting_table(); // buat table setting
	$tc->wcbc_create_so_process_table(); // buat table setting
}

register_deactivation_hook( __FILE__, 'wcbc_uninstall');
function wcbc_uninstall() {
	// if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	// 	exit;
	// }

	global $wpdb;

	$api = new BCXWC_IntegrationAPI();
	$api->deactivateLicense();
	
	$wpdb->query( "DROP TABLE {$wpdb->prefix}wcbc_translasi_item;" );
	$wpdb->query( "DROP TABLE {$wpdb->prefix}wcbc_so;" );
	// $wpdb->query( "ALTER TABLE {$wpdb->prefix}wcbc_so AUTO_INCREMENT = 1;" );
	$wpdb->query( "DROP TABLE {$wpdb->prefix}wcbc_sod;" );
	$wpdb->query( "DROP TABLE {$wpdb->prefix}wcbc_setting;" );
	$wpdb->query( "DROP TABLE {$wpdb->prefix}wcbc_log;" );
	$wpdb->query( "DROP TABLE {$wpdb->prefix}wcbc_so_process;" );
}



/////////////////////////////////////
// SCHEDULER / CRON
/////////////////////////////////////
add_filter( 'cron_schedules', 'wcbc_add_cron_interval' );
function wcbc_add_cron_interval( $schedules ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'wcbc_setting';

	$per = $wpdb->get_var("select value from {$table_name} where code = 'SYNCPER'");
	if($per) {
	    $schedules['wcbc_cron_schedule'] = array(
	        'interval' => $per * 60,
	        'display'  => esc_html__( 'Periode Sinkron Beecloud Woo' ),
	    );
	}
 
    return $schedules;
}

if ( ! wp_next_scheduled( 'wcbc_cron_event' ) ) {
    wp_schedule_event( time(), 'wcbc_cron_schedule', 'wcbc_cron_event' );
}

add_action('wcbc_cron_event', 'wcbc_cron_do');
function wcbc_cron_do() {
	BCXWC_Task::wcbc_process_sync();
	BCXWC_Task::wcbc_update_stock_price();
}


/////////////////////////////////////
// NOTICES
/////////////////////////////////////
add_action('admin_notices', 'wcbc_notices');
function wcbc_notices() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'wcbc_setting';

	$authkey = $wpdb->get_var("select value from {$table_name} where code = 'APIKEY'");
	if(!$authkey) {
		echo '<div class="error notice is-dismissible"><p>Beecloud Woo - Anda belum melakukan login! silahkan login <a href="'.admin_url('admin.php?page=wcbc_menu_setting').'">disini</a></p></div>';
	}

	$pricelvl = $wpdb->get_var("select value from {$table_name} where code = 'PRICELVL'");
	if($authkey AND (!$pricelvl OR $pricelvl == '') AND (isset($_GET['page']) AND $_GET['page'] != 'wcbc_menu_setting')) {
		echo '<div class="error notice is-dismissible"><p>Beecloud Woo - Anda belum memilih Level harga (dibutuhkan untuk update harga) ! silahkan isi <a href="'.admin_url('admin.php?page=wcbc_menu_setting').'">disini</a></p></div>';
	}
}


/////////////////////////////////////
// WEBHOOK CREATE ORDER
/////////////////////////////////////
add_action( 'woocommerce_checkout_order_processed', 'wcbc_queueing_so');
function wcbc_queueing_so( $order_id ){
	global $wpdb;

	$table_so = $wpdb->prefix . 'wcbc_so';
	$table_sod = $wpdb->prefix . 'wcbc_sod';

	$modelSo = new BCXWC_ModelSo;
	$dataSo = $modelSo->parseSo($order_id);
	$wpdb->insert($table_so, $dataSo);
	$so_id = $wpdb->insert_id;

	$dataSods = $modelSo->parseSod($so_id, $order_id);
	foreach($dataSods as $dataSod) {
		$wpdb->insert($table_sod, $dataSod);
		BCXWC_Logger::error($wpdb->last_error);
	}
}


/////////////////////////////////////
// WEBHOOK TRANSLASI PRODUK
/////////////////////////////////////
add_action( 'transition_post_status', 'wcbc_create_product_hook', 10, 3 );
function wcbc_create_product_hook( $new_status, $old_status, $post ) {
 
    global $post;
	global $wpdb;
	$table_name = $wpdb->prefix . 'wcbc_translasi_item';
 
    if ( 'publish' !== $new_status or 'publish' === $old_status ) return;
 
    if ( $post->post_type !== 'product' ) return;

    $id = $wpdb->get_var("select id from $table_name where wc_item_id = ".$post->ID);

 	if(!$id) 
	 	$wpdb->insert($table_name, ['wc_item_id' => $post->ID, 'bc_item_id' => null]);
}

add_action( 'before_delete_post', 'wcbc_delete_product_hook' );
function wcbc_delete_product_hook($post_id) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'wcbc_translasi_item';

	$wpdb->delete($table_name, ['wc_item_id' => $post_id]);
}


/////////////////////////////////////
// WEBHOOK TRANSISI STATUS ORDER
/////////////////////////////////////
add_action( 'woocommerce_order_status_cancelled', 'wcbc_close_so' ); // cancel order
function wcbc_close_so($post_id) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'wcbc_so';
	$so = $wpdb->get_var("select id from $table_name where wc_order_id = ".$post_id);
	if($so) {
		$table_process = $wpdb->prefix . 'wcbc_so_process';
		$process = $wpdb->get_var("select id from $table_process where so_id = $so and process_type = 'CANCEL' and sync_status <> 'SYNCED' limit 1");
		if(!$process) {
			$wpdb->insert($table_process, [
				'so_id' => $so, 
				'process_type' => 'CANCEL', 
				'trxdate' => current_time('mysql', 1)
			]);
		}
	}
}



/////////////////////////////////
// ADDING MENU
/////////////////////////////////
add_action( 'admin_menu', 'wcbc_add_menu' );
function wcbc_add_menu() {
	add_menu_page( 'Beecloud Woo', 'Beecloud Woo', 'manage_woocommerce', 'wcbc_menu_main', null, plugin_dir_url( __FILE__ ) . 'img/woo-menu-logo.png', 58.833 );

	add_submenu_page( 'wcbc_menu_main', 'Sinkron Beecloud', 'Sinkron Beecloud', 'manage_woocommerce', 'wcbc_menu_order', ['BCXWC_Page', 'wcbc_func_view_menu_order'] );

	add_submenu_page( 'wcbc_menu_main', 'Translasi Item', 'Translasi Item', 'manage_woocommerce', 'wcbc_menu_translasi_item', ['BCXWC_Page', 'wcbc_func_view_menu_transitem'] );

	add_submenu_page( 'wcbc_menu_main', 'Pengaturan', 'Pengaturan', 'manage_woocommerce', 'wcbc_menu_setting', ['BCXWC_Page', 'wcbc_func_view_menu_setting'] );

	remove_submenu_page('wcbc_menu_main', 'wcbc_menu_main');
}


/////////////////////////////////
// AJAX CALL
/////////////////////////////////
add_action( 'wp_ajax_wcbc_translasi_item', ['BCXWC_AjaxCallback', 'wcbc_item_ajax_callback'] ); // ajax cari item di bc
add_action( 'wp_ajax_wcbc_translasi_item_save', ['BCXWC_AjaxCallback', 'wcbc_save_item_ajax_callback'] ); // ajax save translasi item
add_action( 'wp_ajax_wcbc_get_wh', ['BCXWC_AjaxCallback', 'wcbc_get_wh_ajax_callback'] ); // ajax cari gudang di bc
add_action( 'wp_ajax_wcbc_get_pricelvl', ['BCXWC_AjaxCallback', 'wcbc_get_pricelvl_ajax_callback'] ); // ajax cari level harga di bc
add_action( 'wp_ajax_wcbc_get_branch', ['BCXWC_AjaxCallback', 'wcbc_get_branch_ajax_callback'] ); // ajax cari branch di bc
add_action( 'wp_ajax_wcbc_get_itemserv', ['BCXWC_AjaxCallback', 'wcbc_get_itemserv_ajax_callback'] ); // ajax cari item jasa di bc
add_action( 'wp_ajax_wcbc_upload_order', ['BCXWC_AjaxCallback', 'wcbc_upload_order'] ); // ajax upload so ke bc
add_action( 'wp_ajax_wcbc_close_order', ['BCXWC_AjaxCallback', 'wcbc_close_order'] ); // ajax close so di bc
add_action( 'wp_ajax_wcbc_check_used_item', ['BCXWC_AjaxCallback', 'wcbc_check_used_item_ajax_callback'] ); // ajax cek translasi cek jika sudah digunakan
add_action( 'wp_ajax_wcbc_check_license', ['BCXWC_AjaxCallback', 'wcbc_check_license_ajax_callback'] ); // ajax cek lisensi jika sudah di perpanjang
add_action( 'wp_ajax_wcbc_bulk_translasi_item', ['BCXWC_AjaxCallback', 'wcbc_bulk_trans_item_ajax_callback'] ); // ajax cari item di bc


/////////////////////////////////
// ENQUEUE SCRIPTS
/////////////////////////////////
add_action( 'admin_enqueue_scripts', 'wcbc_select2_enqueue' );
function wcbc_select2_enqueue(){
	wp_enqueue_style('wcbc_select2_style', plugin_dir_url( __FILE__ ) . 'css/select2.min.css', null, '4.0.3' );
	wp_enqueue_style('wcbc_main_style', plugin_dir_url( __FILE__ ) . 'css/wcbc_main.css', null, '1.1.5'); 
	
	wp_enqueue_script('wcbc_select2', plugin_dir_url( __FILE__ ) . 'js/select2.min.js', array('jquery'), '4.0.3' );
	wp_enqueue_script('wcbc_main', plugin_dir_url( __FILE__ ) . 'js/wcbc_main.js', array( 'jquery', 'select2' ), '1.1.53'); 
}


/////////////////////////////////
// COMPABILITY WITH HPOS WOO
/////////////////////////////////
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

?>
