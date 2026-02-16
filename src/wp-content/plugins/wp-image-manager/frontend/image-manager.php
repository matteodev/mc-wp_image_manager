<?php
wp_enqueue_script( 'jquery' );
wp_enqueue_style( 'bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css' );
wp_enqueue_style( 'font-awesome-css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css' );
wp_enqueue_style('image-manager-css', plugin_dir_url( __FILE__ ) . 'style.css' );
?>
<div class="image-manager-panel">
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="text" onkeyup="filterData()" id="data-search" class="form-control" placeholder="Filtra per titolo..." >
        </div>
        <div class="col-md-4">
            <select id="image-sort" class="form-control">
                <option value="created_at_desc">Data (nuove prima)</option>
                <option value="created_at_asc">Data (vecchie prima)</option>
                <option value="title_asc">Titolo (A-Z)</option>
                <option value="title_desc">Titolo (Z-A)</option>
                <option value="random">A caso</option>
            </select>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-primary" onclick="changeView(im_style)">Cambia Visualizzazione</button>
        </div>
    </div>
</div>
<div class="image-manager-layout"></div>
<script>
    var im_grid;
    var im_style = '<?php echo $style; ?>';
    jQuery(document).ready(function() {
        //Rimuovo il titolo del post creato automaticamente da WordPress
        jQuery('.wp-block-post-title').remove();

        jQuery.post( "<?php echo admin_url( 'admin-ajax.php' ); ?>", {
            action: "get_images_data",
            nonce: "<?php echo wp_create_nonce( 'get_images_data_nonce' ); ?>"
        }, function(response) {
            if(response.success) {
                im_grid = response.data;
                <?php if($style === 'table' ) { ?>
                    renderTable(im_grid.data);
                <?php } else if( $style === 'card' ) { ?>
                    renderCards(im_grid.data);
                <?php } ?>
            } else {
                console.error('Errore nel recupero dei dati');
            }
        });
    });
    function renderTable(data) {
        var tableHtml = `
        <table class="table">
            <thead>
                <tr>
                    <th>Titolo</th>
                    <th>Data</th>
                    <th>Immagine</th>
                    <th>Caricata da</th>
                </tr>
            </thead>
        <tbody>`;
        data.forEach(function(image) {
            var owner = image.owner_id == 0 ? 'API' : image.owner_id; // Sostituisci con il nome utente se necessario
            tableHtml += `
            <tr>
                <td>${image.title}</td>
                <td>${image.created_at}</td>
                <td><img src="${image.image_url}" alt="${image.title}" style="max-width: 100px;"></td>
                <td>${owner}</td>
            </tr>`;
        });
        tableHtml += '</tbody></table>';
        jQuery('.image-manager-layout').html(tableHtml);
    }
        
    function renderCards(data) {
        var cardHtml = '<div class="row">';
        data.forEach(function(image) {
            var owner = image.owner_id == 0 ? 'API' : image.owner_id; // Sostituisci con il nome utente se necessario
            cardHtml += `
            <div class="col-md-4 mb-4">
                <div class="card">
                    <img src="${image.image_url}" alt="${image.title}" class="card-img-top">
                    <div class="card-body">
                        <h6 class="card-title">${image.title}</h6>
                        <h5 class="card-text">${image.description}</h5>
                        <p class="text-muted"><i class="fas fa-user"></i> ${owner}</p>
                        <p class="text-muted"><i class="fas fa-calendar-alt"></i> ${image.created_at}</p>
                    </div>
                </div>
            </div>`;
        });
        cardHtml += '</div>';
        jQuery('.image-manager-layout').html(cardHtml);
    }

    function filterData() {
        var searchTerm = jQuery('#data-search').val().toLowerCase();
        // Filtra i dati in base al titolo
        var filteredData = im_grid.data.filter(function(newData) {
            return newData.title.toLowerCase().includes(searchTerm);
        });
        if(im_style === 'table') {
            renderTable(filteredData);
        }
        if(im_style === 'card') {
            renderCards(filteredData);
        }
    }

    function changeView(style){
        if(style==undefined) style='<?php echo $style; ?>';
        if(style === 'table') {
            renderCards(im_grid.data);
            im_style = 'card';
        } else {
            renderTable(im_grid.data);
            im_style = 'table';
        }
    }
</script>