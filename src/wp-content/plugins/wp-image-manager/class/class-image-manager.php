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
       //Creo shortcode per il frontend
       add_shortcode( 'mc_image_manager', array( $this, 'render_frontend' ) );

       //Creo una pagina nel frontend e gli assegno lo shortcode
       add_action( 'init', array( $this, 'create_frontend_page' ) );
    }

    function create_frontend_page() {
        $page_name = 'Image Manager';
        $page = get_page_by_title( $page_name, OBJECT, 'page' );
        if ( ! $page ) {
            wp_insert_post( array(
                'post_title' => $page_name,
                'post_name' => $page_name,
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_content' => '[mc_image_manager]'
            ) );
        }

        //La converto in home page 
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $page->ID );
    }

    function render_frontend() {
        ob_start();
        include plugin_dir_path( __FILE__ ) . '../frontend/image-manager.php';
        return ob_get_clean();
    }

    public function install() {
        $this->prepare_tables();
        $this->prepare_options();
        $this->prepare_repository();

        //Carico immagini di esempio
        $this->preset_images(10);
    }

    function prepare_tables(){
        $charset_collate = $this->db->get_charset_collate();
        //Preparazione della tabella per le immagini
        $sql_tb_images = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title tinytext NOT NULL,
            description text NOT NULL,
            image_url varchar(255) NOT NULL,
            metadati json NOT NULL,
            owner_id mediumint(9) NOT NULL DEFAULT 0,
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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_tb_images );
        dbDelta( $sql_tb_images_exclude );
    }

    function prepare_options(){
        $default_options = array(
            'image_dir_name' => 'image_manager_repository',
            'devmode' => false,
        );
        add_option( 'mc_image_manager_options', $default_options );
    }

    function prepare_repository(){
        $upload_dir = wp_upload_dir();
        $image_dir = $upload_dir['basedir'] . '/' . $this->getRepo();
        if ( ! is_dir( $image_dir ) ) {
            mkdir( $image_dir, 0755, true );
        }
    }

    function preset_images($limit = 10){
        //Carico X immagini di esempio prese da api esterne e le salvo nel repository, inserendo i relativi dati nella tabella
        $response = wp_remote_get( 'https://picsum.photos/v2/list?limit=' . $limit );
        if ( is_array( $response ) && ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );
            $images = json_decode( $body, true );
            foreach ( $images as $image ) {
                $image_url = $image['download_url'];
                $image_title = "Example Image " . $image['id'];
                $image_description = "Immagine di esempio";

                $image_metadati = $this->get_metadati_from_image($image_url);
                if($image_metadati === false){
                    $image_metadati = json_encode( array());
                }else{
                    $image_metadati = json_encode( $image_metadati );
                }
                
                $this->db->insert( $this->table_name, array(
                    'title' => $image_title,
                    'description' => $image_description,
                    'image_url' => $image_url,
                    'metadati' => $image_metadati,
                    'owner_id' => 0,
                    'created_at' => current_time( 'mysql' ),
                ));
            }
        }
        if ( is_wp_error( $response ) ) {
            return;
        }
    }

    function get_metadati_from_image($image_url){
        $metadati = exif_read_data($image_url, 0, true);
        return $metadati;
    }

    function getRepo() {
        $options = get_option( 'mc_image_manager_options' );
        return $options['image_dir_name'];
    }

    public function desactivate() {
        //Se la devmode è disattivata, non eseguo nessuna operazione di pulizia, per evitare di perdere dati in fase di sviluppo
        if( get_option( 'mc_image_manager_options' )['devmode'] === false ) {
            return;
        }
        //Pulisco le tabelle create durante l'installazione
        $this->db->query( "DROP TABLE IF EXISTS $this->table_name" );
        $this->db->query( "DROP TABLE IF EXISTS $this->table_name_exclude" );
        //Rimuovo la cartella del repository immagini
        $upload_dir = wp_upload_dir();
        $image_dir = $upload_dir['basedir'] . '/' . $this->getRepo();
        if ( is_dir( $image_dir ) ) {
            rmdir( $image_dir );
        }
        //Rimuovo le opzioni salvate
        delete_option( 'mc_image_manager_options' );
        //Rimuovo la pagina creata durante l'installazione
        $page_name = 'Image Manager';
        $page = get_page_by_title( $page_name, OBJECT, 'page' );
        if ( $page ) {
            wp_delete_post( $page->ID, true );
        }
    }
}