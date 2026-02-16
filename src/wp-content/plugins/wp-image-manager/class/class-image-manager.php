<?php
class Image_Manager {
    private $image_dir_name;
    private $table_images;
    private $table_images_exclude;
    private $db;
    private $session_id;
    function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_images = $wpdb->prefix . 'mc_images';
        $this->table_images_exclude = $wpdb->prefix . 'mc_images_exclude';
        $this->session_id = $this->checkSession();
    }

    function checkSession(){
        //Verifico se esiste già una sessione per l'utente tramite un cookie, 
        // altrimenti ne creo una nuova

        $session_id = $this->getSessionIdFromCookie();
        if($session_id === false){
            $session_id = $this->createSessionId();
            $this->saveSessionIdInCookie($session_id);
        }
        return $session_id;
    }

    function getSessionIdFromCookie(){
        if(isset($_COOKIE['mc_image_manager_session_id'])){
            return $_COOKIE['mc_image_manager_session_id'];
        }
        return false;
    }

    function createSessionId(){
        return bin2hex(random_bytes(16));
    }

    function saveSessionIdInCookie($session_id){
        setcookie('mc_image_manager_session_id', $session_id, time() + (86400 * 30)*12, '/'); // 12 mesi
    }


    function init() {
        $this->register_shortcode();
        //Richieste AJAX
        add_action( 'wp_ajax_get_images_data', array( $this, 'getImages' ) );
        add_action( 'wp_ajax_nopriv_get_images_data', array( $this, 'getImages' ) );

        add_action( 'wp_ajax_add_image', array( $this, 'imageUploader' ) );
        add_action( 'wp_ajax_nopriv_add_image', array( $this, 'imageUploader' ) );

        add_action('wp_ajax_upload_image', array( $this, 'uploadNewImage' ));
        add_action('wp_ajax_nopriv_upload_image', array( $this, 'uploadNewImage' ));

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
                'post_content' => '[mc_image_manager style="table"]',
            ) );
        }

        //La converto in home page 
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $page->ID );
    }

    function render_frontend( $atts ) {
        ob_start();
        $style = $atts['style'] ?? 'table';
        if($style !== 'table' && $style !== 'card') {
            $style = 'table';
        }
        include_once (plugin_dir_path( __FILE__ ) . '../frontend/image-manager.php');
        return ob_get_clean();
    }

    public function install() {
        $this->prepare_tables();
        $this->prepare_options();
        $this->prepare_repository();

        //Carico immagini di esempio
        $this->preset_images(10);
    }

    function register_shortcode() {
        add_shortcode( 'mc_image_manager', array( $this, 'render_frontend' ) );
    }

    function prepare_tables(){
        $charset_collate = $this->db->get_charset_collate();
        //Preparazione della tabella per le immagini
        $sql_tb_images = "CREATE TABLE $this->table_images (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title tinytext NOT NULL,
            description text NOT NULL,
            image_url varchar(255) NOT NULL,
            metadati json NOT NULL,
            owner_id varchar(32) NOT NULL DEFAULT '',
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        //Preparazione della tabella per le immagini escluse dall'utente
        $sql_tb_images_exclude = "CREATE TABLE $this->table_images_exclude (
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
            'devmode' => true,
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
            $counter = 1;
            foreach ( $images as $image ) {
                $image_url = $image['download_url'];
                $image_title = "Example Image " . $counter++;
                $image_description = "Immagine di esempio";

                $image_metadati = $this->get_metadati_from_image($image_url);
                if($image_metadati === false){
                    $image_metadati = json_encode( array());
                }else{
                    $image_metadati = json_encode( $image_metadati );
                }
                
                $this->db->insert( $this->table_images, array(
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

    function getImages(){
        $nonce = $_POST['nonce'];
        if ( ! wp_verify_nonce( $nonce, 'get_images_data_nonce' ) ) die ( 'Nonce non valido' );
        $query  = "SELECT *, 
        DATE_FORMAT(created_at, '%d-%m-%Y %H:%i:%s') AS created_at, 
        UNIX_TIMESTAMP(created_at) AS created_at_timestamp 
        FROM $this->table_images ORDER BY title ASC";
        $images = $this->db->get_results( $query );
        $response = array();
        if ( $images ) {
            foreach($images as $image){
                if($image->owner_id != "0"){
                    $upload_dir = wp_upload_dir();
                    $image->image_url =  $upload_dir['baseurl'] . '/' . $this->getRepo() . '/' . $image->image_url;
                }
            }
            $response['data'] = $images;
        }
        wp_send_json_success( $response );
    }

    function imageUploader(){
        $nonce = $_POST['nonce'];
        if ( ! wp_verify_nonce( $nonce, 'add_image_nonce' ) ) die ( 'Nonce non valido' );
        ob_start();
        include_once( plugin_dir_path( __FILE__ ). '../inc/file-uploader.php' );
        $render = ob_get_clean();
        wp_send_json_success( array( 'page' => $render ) );
    }

    function uploadNewImage(){
        $nonce = $_POST['nonce'];
        if ( ! wp_verify_nonce( $nonce, 'add_image_nonce' ) ){
            die ( wp_send_json_error( array( 'message' => 'Parametri non validi' ) ) );
        }
        
        $titolo = esc_attr($_POST['title']);
        $descrizione = esc_attr($_POST['description']);

        //Controllo che sia stato inviato un file
        if(isset($_FILES['image-file'])){
            $image_file = $_FILES['image-file'];
        }else{
            wp_send_json_error( array( 'message' => 'Nessun file immagine selezionato' ) );
        }
        if($image_file['size'] === 0){
            wp_send_json_error( array( 'message' => 'Il file è corrotto' ) );
        }
        //Controllo che sia un file immagine
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $image_type = $finfo->file($image_file['tmp_name']);
        if(!str_contains($image_type, 'image')){
            wp_send_json_error( array( 'message' => 'Il file non è un immagine' ) );
        }

        //Check della sessione
        $this->checkSession();

        //Upload del file nel repository
        $upload_dir = wp_upload_dir();
        $image_dir = $upload_dir['basedir'] . '/' . $this->getRepo();
        //Do un nome unico al file
        $image_file["name"] = uniqid() . '.' . pathinfo($image_file['name'], PATHINFO_EXTENSION);
        $image_path = $image_dir . '/' . $image_file["name"];
        if ( ! move_uploaded_file( $image_file['tmp_name'], $image_path ) ) {
            wp_send_json_error( array( 'message' => 'Errore durante il caricamento dell\'immagine' ) );
        }
        //Ottengo i metadati dell'immagine
        $image_metadati = $this->get_metadati_from_image($image_path);
        if($image_metadati === false){
            $image_metadati = json_encode( array());
        }else{
            $image_metadati = json_encode( $image_metadati );
        }
        //Inserisco l'immagine nel database
        $result = $this->db->insert( $this->table_images, array(
            'title' => $titolo,
            'description' => $descrizione,
            'image_url' => $image_file['name'],
            'metadati' => $image_metadati,
            'owner_id' => $this->getSessionIdFromCookie(),
            'created_at' => current_time( 'mysql' ),
        ));
        if($result === false){
            wp_send_json_error( array( 'message' => 'Errore durante l\'inserimento dell\'immagine' ) );
        }
        //In caso di successo, invio la risposta
        wp_send_json_success( array( 'message' => 'Immagine inserita con successo' ) );
    }

    public function desactivate() {
        //Se la devmode è disattivata, non eseguo nessuna operazione di pulizia, per evitare di perdere dati in fase di sviluppo
        if( !get_option( 'mc_image_manager_options' )['devmode']) {
            return;
        }
        //Pulisco le tabelle create durante l'installazione
        $this->db->query( "DROP TABLE IF EXISTS $this->table_images" );
        $this->db->query( "DROP TABLE IF EXISTS $this->table_images_exclude" );
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