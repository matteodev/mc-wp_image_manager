<?php
if (! current_user_can('manage_options')) {
    wp_die(esc_html__('Non hai i permessi per accedere a questa pagina.', 'mc-reseller-manager'));
}
$im_update = false;
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    //Controllo il nonce per la sicurezza
    if(!isset($_POST['image_manager_dev_nonce']) || !wp_verify_nonce($_POST['image_manager_dev_nonce'], 'image_manager_dev')){
        wp_die(esc_html__('Non hai i permessi per accedere a questa pagina.', 'mc-reseller-manager'));
    }
    //Aggiorno le opzioni
    $im_options = get_option( 'mc_image_manager_options' );
    $im_options['devmode'] = isset($_POST['image_manager_dev']) ? '1' : '0';
    update_option( 'mc_image_manager_options', $im_options );
    //Aggiorno il messaggio di aggiornamento
    $im_update = true;
}
?>
<div class="wrap">
    <h1>Image Manager - Impostazioni</h1>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Impostazioni di sviluppo</h3>
        </div>
        <div class="card-body">
            <?php if($im_update){ ?>
                <div class="notice notice-success is-dismissible">
                    <p>Impostazioni aggiornate con successo.</p>
                </div>
            <?php 
            } 
            ?>
            <form method="post">
                <?php 
                //Aggiungo il nonce per la sicurezza
                wp_nonce_field( 'image_manager_dev', 'image_manager_dev_nonce' );
                ?>
                <div class="form-group">
                    <input type="checkbox" 
                    class="form-control" 
                    id="image_manager_dev" 
                    name="image_manager_dev" 
                    value="<?php echo get_option( 'mc_image_manager_options' )['devmode'] ? '1' : '0'; ?>"
                    <?php echo get_option( 'mc_image_manager_options' )['devmode'] ? 'checked' : ''; ?>>
                    Abilita le opzioni di sviluppo
                    <br>
                    <p>Se abiti l'opzione e disattivi il plugin verra cancellata la tabella,le opzioni e i dati presenti nel database.</p>
                </div>
                <div class="form-group">
                    <button type="submit" class="button button-primary">Salva</button>
                </div>
            </form>
        </div>
    </div>
</div>