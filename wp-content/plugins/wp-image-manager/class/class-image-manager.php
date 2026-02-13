<?php
class Image_Manager {
    private $image_dir_name;
    private $table_name;
    private $table_name_exclude;
    private $db;
    function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name = $wpdb->prefix . 'mc_images';
        $this->table_name_exclude = $wpdb->prefix . 'mc_images_exclude';
    }
    public function init() {
       
    }

    public function install() {
        $charset_collate = $this->db->get_charset_collate();
        //Preparazione della tabella per le immagini
        $sql_tb_images = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title tinytext NOT NULL,
            description text NOT NULL,
            image_url varchar(255) NOT NULL,
            metadati json NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        //Preparazione della tabella per le immagini escluse dall'utente
        $sql_tb_images_exclude = "CREATE TABLE $this->table_name_exclude (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            image_id mediumint(9) NOT NULL,
            user_id mediumint(9) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        //Salvo parametri di configurazione
         add_option( 'mc_image_manager_options', array(
            'image_dir_name' => uniqid('mc_images_'),
        ));


        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_tb_images );
        dbDelta( $sql_tb_images_exclude );

        //Creazione Repository immagini nella cartella uploads
        $upload_dir = wp_upload_dir();
        $image_dir = $upload_dir['basedir'] . '/' . $this->getRepo();
        if ( ! is_dir( $image_dir ) ) {
            mkdir( $image_dir, 0755, true );
        }

    }

    function getRepo() {
        $options = get_option( 'mc_image_manager_options' );
        if ( isset( $options['image_dir_name'] ) ) {
            return $options['image_dir_name'];
        } else {
             return '';
        }
    }

    public function uninstall() {
        //Eliminazione tabella immagini
        $this->db->query("DROP TABLE IF EXISTS $this->table_name");
        //Eliminazione tabella immagini escluse
        $this->db->query("DROP TABLE IF EXISTS $this->table_name_exclude");
        //Eliminazione repository immagini
        $upload_dir = wp_upload_dir();
        $image_dir = $upload_dir['basedir'] . '/' . $this->getRepo();
        if ( is_dir( $image_dir ) ) {
            rmdir( $image_dir );
        }
    }
}