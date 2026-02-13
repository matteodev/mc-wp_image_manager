<?php
/**
 * Plugin Name: WP Image Manager
 * Plugin URI: https://github.com/matteodev/wp-image-manager
 * Description:Realizzazione di una web application di pubblicazione delle immagini.
 * Version: 1.0.0
 * Author: Matteo Papparella
 * Author URI: https://matcode.dev
 * License: GPL2
 * Text Domain: mc-wp-image-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class/class-image-manager.php';
$image_manager = new Image_Manager();
$image_manager->init();

//Hooks per installazione e disinstallazione
register_activation_hook( __FILE__, array( $image_manager, 'install' ) );
register_deactivation_hook( __FILE__, array( $image_manager, 'desactivate' ) );
