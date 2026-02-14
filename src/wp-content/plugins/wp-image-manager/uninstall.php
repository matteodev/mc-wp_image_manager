<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $wpdb;
$table_images = $wpdb->prefix . 'mc_images';
$table_images_exclude = $wpdb->prefix . 'mc_images_exclude';
//Eliminazione tabella immagini
$wpdb->query("DROP TABLE IF EXISTS $table_images");
//Eliminazione tabella immagini escluse
$wpdb->query("DROP TABLE IF EXISTS $table_images_exclude");
//Eliminazione repository immagini
$upload_dir = wp_upload_dir();
$image_dir = $upload_dir['basedir'] . '/' . get_option( 'mc_image_manager_options' )['image_dir_name'];
if ( is_dir( $image_dir ) ) {
    rmdir( $image_dir );
}
//Eliminazione opzioni di configurazione
delete_option( 'mc_image_manager_options' );
//Eliminazione pagina creata durante l'installazione
$page_name = 'Image Manager';
$page = get_page_by_title( $page_name, OBJECT, 'page' );
if ( $page ) {
    wp_delete_post( $page->ID, true );
}
?>
