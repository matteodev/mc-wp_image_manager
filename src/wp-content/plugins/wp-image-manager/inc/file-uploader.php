<?php
$form_id = uniqid('image-upload-form-');
function getUploadMaxSize(){
    //Recupero il valore di upload_max_filesize
    $upload_max_size = ini_get('upload_max_filesize');
    //Aggiungo al suffisso la lettera B
    $upload_max_size .= 'B';
    return $upload_max_size;
}
?>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <form id="<?php echo $form_id; ?>" enctype="multipart/form-data">
                <small class="form-text text-muted mb-2">
                    Upload massimo: <?php echo getUploadMaxSize(); ?><br>
                </small>
                <div class="message"></div>
                <div class="form-group">
                    <label for="image-file">Seleziona una immagine*:</label>
                    <input type="file" class="form-control-file form-control-sm" id="image-file" name="image-file" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="image-title">Titolo*:</label>
                    <input type="text" class="form-control form-control-sm" id="image-title" name="image-title" placeholder="Inserisci il titolo dell'immagine">
                </div>
                <div class="form-group">
                    <label for="image-description">Descrizione:</label>
                    <textarea class="form-control form-control-sm" id="image-description" name="image-description" placeholder="Inserisci una descrizione dell'immagine"></textarea>
                </div>
                <button type="button" onclick="uploadImage()" class="btn btn-sm btn-primary">Carica Immagine</button>
                <p class="my-2"><span class="text-muted">* campi obbligatori</span></p>
            </form>
        </div>
    </div>
</div>
<script>
    function uploadImage(){
        var form = document.getElementById('<?php echo $form_id; ?>');
        var message = document.querySelector('#<?php echo $form_id; ?> .message');
        message.innerHTML = '';
        var formData = new FormData(form);
        if(formData.get('image-file').size === 0){
            message.innerHTML = '<div class="alert alert-warning my-2">Seleziona un file immagine</div>';
            return;
        }
        if(formData.get('image-file').size > <?php echo wp_max_upload_size(); ?>){
            message.innerHTML = '<div class="alert alert-warning my-2">Il file immagine è troppo grande</div>';
            return;
        }
        if(formData.get('image-file').type.indexOf('image') === -1){
            message.innerHTML = '<div class="alert alert-warning my-2">Seleziona un file immagine valido</div>';
            return;
        }
        if(!formData.get('image-title')){
            message.innerHTML = '<div class="alert alert-warning my-2">Inserisci un titolo per l\'immagine</div>';
            return;
        }

        formData.append('action', 'upload_image');
        formData.append('nonce', '<?php echo wp_create_nonce('add_image_nonce'); ?>');
        formData.append('title', formData.get('image-title'));
        formData.append('description', formData.get('image-description'));

        fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
            method: 'POST',
            body: formData,
        }).then(function(response) {
            return response.json();
        }).then(function(data) {
            if(data.success){
                message.innerHTML = `
                <div class="alert alert-success my-2">
                    Immagine caricata con successo
                </div>`;
            }else{
                message.innerHTML = `
                <div class="alert alert-danger my-2">
                    Errore durante il caricamento dell'immagine: ${data.data.message}
                </div>`;
            }
            form.reset();
        });
    }
</script>
