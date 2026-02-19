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
        $this->set_ajax_actions();
        //Creo una pagina nel frontend e gli assegno lo shortcode
        add_action( 'init', array( $this, 'create_frontend_page' ) );
        //Configuro menu backend
        add_action( 'admin_menu', array( $this, 'prepare_backend' ) );
    }

    function set_ajax_actions(){
        //Richieste per ottenere le immagini
        add_action( 'wp_ajax_get_images_data', array( $this, 'getImages' ) );
        add_action( 'wp_ajax_nopriv_get_images_data', array( $this, 'getImages' ) );

        //Richieste per caricare la modale
        add_action( 'wp_ajax_add_image', array( $this, 'imageUploader' ) );
        add_action( 'wp_ajax_nopriv_add_image', array( $this, 'imageUploader' ) );
        
        //Richieste per caricare un'immagine
        add_action('wp_ajax_upload_image', array( $this, 'uploadNewImage' ));
        add_action('wp_ajax_nopriv_upload_image', array( $this, 'uploadNewImage' ));

        //Richieste per nascondere le immagini selezionate
        add_action('wp_ajax_hide_selected_images', array( $this, 'hideSelectedImages' ));
        add_action('wp_ajax_nopriv_hide_selected_images', array( $this, 'hideSelectedImages' ));

        //Richieste per ripristinare le immagini nascoste
        add_action('wp_ajax_restore_selected_images', array( $this, 'restoreSelectedImages' ));
        add_action('wp_ajax_nopriv_restore_selected_images', array( $this, 'restoreSelectedImages' ));

        //Richieste per ottenere i dettagli di un immagine
        add_action('wp_ajax_get_image_detail', array( $this, 'getImageDetail' ));
        add_action('wp_ajax_nopriv_get_image_detail', array( $this, 'getImageDetail' ));
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
            image_ids json NOT NULL,
            owner_id varchar(32) NOT NULL DEFAULT '',
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

    function prepare_backend(){
        //Aggiungo submenu nel menu tool
        add_submenu_page(
            'tools.php',
            'Image Manager',
            'Image Manager',
            'manage_options',
            'image-manager',
            array( $this, 'backend_settings_page' ),
            20
        );
    }

    function backend_settings_page(){
        include_once (plugin_dir_path( __FILE__ ) . '../backend/settings.php');
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
        //Check della sessione
        $this->checkSession();
        $session_id = $this->getSessionIdFromCookie();

        $query ="SELECT i.*, DATE_FORMAT(i.created_at, '%d-%m-%Y %H:%i:%s') AS created_at, 
        UNIX_TIMESTAMP(i.created_at) AS created_at_timestamp FROM $this->table_images i 
        ORDER BY i.title ASC";

        $images = $this->db->get_results( $query );
        $response = array();

        //Estraggo immagini nascoste dell'utente
        $query ="SELECT image_ids FROM $this->table_images_exclude WHERE owner_id = %s";
        $hidden_images = $this->db->get_results( $this->db->prepare( $query, $session_id ) );
        if($hidden_images){
            $hidden_images = json_decode($hidden_images[0]->image_ids, true);
        }else{
            $hidden_images = array();
        }

        if ( $images ) {
            foreach($images as $key => $image){
                if($image->owner_id != "0"){
                    $upload_dir = wp_upload_dir();
                    $image->image_url =  $upload_dir['baseurl'] . '/' . $this->getRepo() . '/' . $image->image_url;
                }
                $response['images'][] = $image;
            }

            if(count($hidden_images) > 0){
                $response['hidden_images'] = $hidden_images;
            }else{
                $response['hidden_images'] = array();
            }
        }else{
            $response['images'] = array();
            $response['hidden_images'] = array();
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

    function hideSelectedImages(){
        $nonce = $_POST['nonce'];
        if ( ! wp_verify_nonce( $nonce, 'fetch_selected_images_nonce' ) ) die ( 'Nonce non valido' );
      
        //Sanizzo la POST
        $selectedImagesToHide = array_map('esc_attr', $_POST['selected_images']);
        if(empty($selectedImagesToHide)){
            wp_send_json_error( array( 'message' => 'Nessuna immagine selezionata' ) );
        }
        
        //Check della sessione
        $session_id = $this->checkSession();

        //Nascondo le immagini selezionate
        //Se l'utente ha già immagini nascoste, aggiorno la lista
        $query ="SELECT image_ids FROM $this->table_images_exclude WHERE owner_id = %s";
        $hidden_images = $this->db->get_results( $this->db->prepare( $query, $session_id ) );
        if($hidden_images){
            $hidden_images = json_decode( $hidden_images[0]->image_ids, true );
            $selectedImagesToHide = array_unique( array_merge( $hidden_images, $selectedImagesToHide ) );

            $result = $this->db->update( $this->table_images_exclude, array(
                'image_ids' => json_encode( $selectedImagesToHide ),
            ), array(
                'owner_id' => $session_id,
            ));
        }else{
            //Se no inserisco
            $result = $this->db->insert( $this->table_images_exclude, array(
                'image_ids' => json_encode( $selectedImagesToHide ),
                'owner_id' => $session_id,
                'created_at' => current_time( 'mysql' ),
            ));
        }
        
        if($result === false){
            wp_send_json_error( array( 'message' => 'Errore durante l\'aggiornamento delle immagini nascoste' ) );
        }
        //In caso di successo, invio la risposta
        wp_send_json_success( array( 'message' => 'Immagini nascoste con successo' ) );
    }

    function restoreSelectedImages(){
        $nonce = $_POST['nonce'];
        if ( ! wp_verify_nonce( $nonce, 'fetch_selected_images_nonce' ) ) die ( 'Nonce non valido' );
      
        //Check della sessione
        $session_id = $this->checkSession();

        //Sanizzo la POST
        $selectedImagesToHide = array_map('esc_attr', $_POST['selected_images']);
        if(empty($selectedImagesToHide)){
            wp_send_json_error( array( 'message' => 'Nessuna immagine selezionata' ) );
        }

        //Recupero la lista di immagini nascoste
        $query ="SELECT image_ids FROM $this->table_images_exclude WHERE owner_id = %s";
        $rows = $this->db->get_results( $this->db->prepare( $query, $session_id ) );
        
        $hidden = [];
        if ( $rows && !empty($rows[0]->image_ids) ) {
            $decoded = json_decode($rows[0]->image_ids, true);
            $hidden = is_array($decoded) ? $decoded : [];
        }

        //Uso array_values per ricomporre l'array con indici consecutivi
        $new_hidden = array_values(array_unique(array_diff($hidden, $selectedImagesToHide)));
        $selectedImagesToHide = $new_hidden;

        //Aggiorno la lista di immagini nascoste
        $result = $this->db->update( $this->table_images_exclude, array(
            'image_ids' => json_encode( $selectedImagesToHide ),
        ), array(
            'owner_id' => $session_id,
        ));

        
        if($result === false){
            wp_send_json_error( array( 'message' => 'Errore durante il ripristino delle immagini nascoste' ) );
        }
        //In caso di successo, invio la risposta
        wp_send_json_success( array( 'message' => 'Immagini ripristinate con successo' ) );
    }

    function getImageDetail(){
        $nonce = $_POST['nonce'];
        if ( ! wp_verify_nonce( $nonce, 'get_image_detail_nonce' ) ) die ( 'Nonce non valido' );
      
        //Check della sessione
        $session_id = $this->checkSession();
        //Sanizzo la POST
        $image_id = esc_attr($_POST['image_id']);
        if(empty($image_id)){
            wp_send_json_error( array( 'message' => 'Nessuna immagine selezionata' ) );
        }
        //Recupero i dettagli dell'immagine
        $query ="SELECT * FROM $this->table_images WHERE id = %s";
        $rows = $this->db->get_results( $this->db->prepare( $query, $image_id ) );
        if(empty($rows)){
            wp_send_json_error( array( 'message' => 'Immagine non trovata' ) );
        }
        //Preparo la modale
        ob_start();
        $image = $rows[0];
        include_once( plugin_dir_path( __FILE__ ). '../inc/file-details.php' );
        $render = ob_get_clean();
        wp_send_json_success( array( 'page' => $render ) );
    }

    function gpsToDecimal($deg, $min, $sec, $ref) {
        // Formula: gradi + minuti/60 + secondi/3600
        $decimal = $deg + ($min / 60) + ($sec / 3600);
        
        // Sud e Ovest devono essere negativi
        if ($ref == 'S' || $ref == 'W') {
            $decimal = $decimal * -1;
        }
        
        return round($decimal, 6); // Ritorna 6 decimali per alta precisione
    }

    function convertExifCoord($coordPart, $ref) {
        if (is_string($coordPart)) {
            $parts = explode('/', $coordPart);
            if (count($parts) == 2) {
                return $parts[0] / $parts[1];
            }
        }
        return $coordPart;
    }

    function metadatiAnalyser($metadati){
        $result = array(
            'data_scatto' => '',
            'marca' => '',
            'modello' => '',
            'indirizzo' => '',
            'meteo' => '',
            'meteo_today' => '',
            'peso_file' => '',
            'risoluzione' => '',
        );

        if( isset($metadati["FILE"]["FileSize"]) ){
            //Converto in MB da Byte
            $result['peso_file'] = number_format($metadati["FILE"]["FileSize"] / 1024 / 1024, 2) . ' MB';
        }
        if( isset($metadati["COMPUTED"]["Width"]) || isset($metadati["COMPUTED"]["Height"]) ){
            $result ['risoluzione'] = $metadati["COMPUTED"]["Width"] . 'x' . $metadati["COMPUTED"]["Height"];
        }

        //Verifico le sectionsFound
        if( isset($metadati["FILE"]["SectionsFound"]) ){
            $sections = explode(',',$metadati["FILE"]["SectionsFound"]);
            foreach( $sections as $key => $section ){
                if( trim($section) == 'EXIF' ){
                    $dataScatto_raw = $metadati["EXIF"]["DateTimeOriginal"];
                }
                if( trim($section) == 'IFD0' ){
                    $marca = $metadati["IFD0"]["Make"];
                    $modello = $metadati["IFD0"]["Model"];
                }
                if( trim($section) == 'GPS' ){
                    $latitudine = $metadati["GPS"]["GPSLatitude"];
                    $longitudine = $metadati["GPS"]["GPSLongitude"];
                }
            }
            
            //Converto la data scatto in formato d/m/Y H:i:s
            if ( $dataScatto_raw != '' && $dataScatto_raw != 0 ) {
                $dt = DateTime::createFromFormat('Y:m:d H:i:s', $dataScatto_raw);
                $result['data_scatto'] = $dt ? $dt->format('d/m/Y H:i:s') : "";
                //Formato che richiede API Meteo
                $dataScattoMeteo = $dt ? $dt->format('Y-m-d') : "";
                $dataScattoMeteoOra = $dt ? $dt->format('Y-m-d\TH:i') : "";
            }

            //Converto la latitudine e longitudine in formato decimale
            if( $latitudine != '' && $longitudine != '' ){
                $latitudine_dec = $this->gpsToDecimal(
                    $this->convertExifCoord($latitudine[0], $metadati["GPS"]["GPSLatitudeRef"]),
                    $this->convertExifCoord($latitudine[1], $metadati["GPS"]["GPSLatitudeRef"]),
                    $this->convertExifCoord($latitudine[2], $metadati["GPS"]["GPSLatitudeRef"]),
                    $metadati["GPS"]["GPSLatitudeRef"]
                );
                $longitudine_dec = $this->gpsToDecimal(
                    $this->convertExifCoord($longitudine[0], $metadati["GPS"]["GPSLongitudeRef"]),
                    $this->convertExifCoord($longitudine[1], $metadati["GPS"]["GPSLongitudeRef"]),
                    $this->convertExifCoord($longitudine[2], $metadati["GPS"]["GPSLongitudeRef"]),
                    $metadati["GPS"]["GPSLongitudeRef"]
                );

                $result['indirizzo'] = $this->getAddressFromPosition( array(
                    'latitudine_dec' => $latitudine_dec,
                    'longitudine_dec' => $longitudine_dec,
                ));


                if($result['data_scatto'] != ""){
                    $result["meteo"] = $this->getMeteoFromPosition( array(
                        'latitudine_dec' => $latitudine_dec,
                        'longitudine_dec' => $longitudine_dec,
                        'date' => $dataScattoMeteo,
                        'date_hour' => $dataScattoMeteoOra,
                    ));
                }
                //Aggiungo meteo di oggi
                $result["meteo_today"] = $this->getMeteoFromPosition( array(
                    'latitudine_dec' => $latitudine_dec,
                    'longitudine_dec' => $longitudine_dec
                ));
            
            }
        }

        return $result;
    }

    function getAddressFromPosition( $params = array() ){
        if(!isset($params['latitudine_dec']) || !isset($params['longitudine_dec'])){
            return '';
        }
        $latitudine_dec = $params['latitudine_dec'];
        $longitudine_dec = $params['longitudine_dec'];

        //Ricavo l'indirizzo usando API free usando CURL
        $url = 'https://nominatim.openstreetmap.org/reverse?format=json';
        $url .= '&lat=' . $latitudine_dec . '&lon=' . $longitudine_dec;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        $output = curl_exec($ch);
        curl_close($ch);
        $indirizzo = json_decode($output,true);
        if( isset($indirizzo['display_name']) ){
            return $indirizzo['display_name'];
        }
        return '';   
    }

    function getMeteoFromPosition( $params ){
        if(!isset($params['latitudine_dec']) || !isset($params['longitudine_dec'])){
            return '';
        }
        $latitudine_dec = $params['latitudine_dec'];
        $longitudine_dec = $params['longitudine_dec'];

        if(isset($params['date'])){ 
            $url = 'https://historical-forecast-api.open-meteo.com/v1/forecast?';
            $url .= "start_date=" . $params['date'] . "&end_date=" . $params['date'];
            $url .= "&start_hour=" . $params['date_hour'] . "&end_hour=" . $params['date_hour'];
        }else{
            $url = 'https://api.open-meteo.com/v1/forecast?';
            $url .= 'current_weather=true';
        }
        $url .= '&latitude=' . $latitudine_dec;
        $url .= '&longitude=' . $longitudine_dec;
        $url .= '&timezone=auto';
        $url .= '&daily=weathercode,temperature_2m_max,temperature_2m_min';

        $url = trim($url);
        
        
        //Ricavo il meteo del giorno dello scatto usando API free
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        $output = curl_exec($ch);
        curl_close($ch);
        $meteo = json_decode($output,true);
        if(isset($params['date'])){
            $unit = $meteo["daily_units"]["temperature_2m_max"];
            $temperatura_max = $meteo['daily']['temperature_2m_max'][0];
            $temperatura_min = $meteo['daily']['temperature_2m_min'][0];
            $temperatura = number_format(($temperatura_max + $temperatura_min) / 2, 1);
            $meteo_code = $meteo['daily']['weathercode'][0];
        }else{
            $unit = $meteo["current_weather_units"]["temperature"];
            $temperatura = $meteo['current_weather']['temperature'];
            $meteo_code = $meteo['current_weather']['weathercode'];
        }
   
        //Converto il codice meteo in testo in italiano
        switch( $meteo_code ){
            case 0:
                $meteo = ' Soleggiato';
            break;
            case 1:
                $meteo = ' Soleggiato con nuvole sparse';
            break;
            case 2:
                $meteo = ' Nuvoloso';
            break;
            case 3:
                $meteo = ' Nuvoloso con nuvole sparse';
            break;
            case 45:
            case 48:
                $meteo = ' Nuvoloso con nebbia';
            break;
            case 51:
            case 53:
            case 55:
                $meteo = ' Nuvoloso con pioggia leggera';
            break;
            case 56:
            case 57:
                $meteo = ' Nuvoloso con pioggia intensa';
            break;
            case 61:
            case 63:
            case 65:
                $meteo = ' Nuvoloso con pioggia';
            break;
            default:
                $meteo = '';
            break;

        }
        if( $meteo != '' ){
            return $meteo . ' (' . $temperatura . $unit . ')';
        }else {
            return '';
        }
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