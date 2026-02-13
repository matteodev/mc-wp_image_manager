<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $wpdb;
$table_name = $wpdb->prefix . 'mc_images';
$table_name_exclude = $wpdb->prefix . 'mc_images_exclude';
//Eliminazione tabella immagini
$wpdb->query("DROP TABLE IF EXISTS $table_name");
//Eliminazione tabella immagini escluse
$wpdb->query("DROP TABLE IF EXISTS $table_name_exclude");
//Eliminazione repository immagini
$upload_dir = wp_upload_dir();
$image_dir = $upload_dir['basedir'] . '/' . get_option( 'mc_image_manager_options' )['image_dir_name'];
if ( is_dir( $image_dir ) ) {
    rmdir( $image_dir );
}
//Eliminazione opzioni di configurazione
delete_option( 'mc_image_manager_options' );
?>
