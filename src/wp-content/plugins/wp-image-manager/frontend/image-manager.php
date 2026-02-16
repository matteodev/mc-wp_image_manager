<?php
wp_enqueue_script( 'jquery' );
wp_enqueue_style( 'bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css' );
wp_enqueue_style( 'font-awesome-css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css' );
wp_enqueue_style('image-manager-css', plugin_dir_url( __FILE__ ) . 'style.css' );
?>
<div class="loading-spinner">
    <div class="spinner-border text-primary" role="status"></div>
</div>
<div class="image-manager-panel">
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="data-search" class="form-label">Ricerca</label>
            <input type="text" onkeyup="filterData()" id="data-search" class="form-control form-control-sm" placeholder="Filtra per titolo..." >
        </div>
        <div class="col-md-4">
            <label for="image-sort" class="form-label">Ordina per</label>
            <select id="image-sort" class="form-control form-control-sm" onchange="orderData(this.value)">
                <option value="title_asc">Titolo (A-Z)</option>
                <option value="title_desc">Titolo (Z-A)</option>
                <option value="created_at_desc">Data (nuove prima)</option>
                <option value="created_at_asc">Data (vecchie prima)</option>
                <option value="random">A caso</option>
            </select>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-secondary btn-sm mt-4" onclick="changeView(im_style)">Cambia Visualizzazione</button>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-12">
            <div id="pagination"></div>
        </div>
    </div>
</div>
<div class="image-manager-layout"></div>
<script>
    var im_grid;
    var im_style = '<?php echo $style; ?>';
    var itemPerPage = 5;
    var currentPage = 1;

    jQuery(document).ready(function() {
        //Rimuovo il titolo del post creato automaticamente da WordPress
        jQuery('.wp-block-post-title').remove();

        jQuery.post( "<?php echo admin_url( 'admin-ajax.php' ); ?>", {
            action: "get_images_data",
            nonce: "<?php echo wp_create_nonce( 'get_images_data_nonce' ); ?>"
        }, function(response) {
            if(response.success) {
                im_grid = response.data;
                //Imposto la paginazione
                setPagination(im_grid.data.length, itemPerPage);
                //Mostro la prima pagina
                changePage(currentPage, itemPerPage);
                //Mostro il layout
                jQuery('.image-manager-panel, .image-manager-layout').css('display', 'block');
                //Nascondo lo spinner di caricamento
                jQuery('.loading-spinner').css('display', 'none');
            } else {
                console.error('Errore nel recupero dei dati');
            }

            //Resetto campi di ricerca e ordinamento
            jQuery('.image-manager-panel #data-search').val('');
            jQuery('.image-manager-panel #image-sort').val(jQuery('.image-manager-panel #image-sort option:first').val());
        });
    });

    function setPagination(totalItems, itemsPerPage) {
        var totalPages = Math.ceil(totalItems / itemsPerPage);
        var paginationHtml = '<nav><ul class="pagination justify-content-center">';
        for (var i = 1; i <= totalPages; i++) {
            paginationHtml += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="changePage(${i}, ${itemsPerPage})">${i}</a>
            </li>`;
        }
        paginationHtml += '</ul></nav>';
        jQuery('.image-manager-panel #pagination').html(paginationHtml);
    }
    
    function changePage(page, itemsPerPage) {
        currentPage = page;
        var startIndex = (page - 1) * itemsPerPage;
        var endIndex = startIndex + itemsPerPage;
        var pageData = im_grid.data.slice(startIndex, endIndex);
        if(im_style === 'table') {
            renderTable(pageData);
        } 
        if(im_style === 'card') {
            renderCards(pageData);
        }
    }

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
        data.forEach(function(item) {
            var owner = item.owner_id == 0 ? 'API' : item.owner_id;
            tableHtml += `
            <tr>
                <td>${item.title}</td>
                <td>${item.created_at}</td>
                <td><img src="${item.image_url}" alt="${item.title}" style="max-width: 100px;"></td>
                <td>${owner}</td>
            </tr>`;
        });
        tableHtml += '</tbody></table>';
        jQuery('.image-manager-layout').html(tableHtml);
    }
        
    function renderCards(data) {
        var cardHtml = '<div class="row">';
        data.forEach(function(item) {
            var owner = item.owner_id == 0 ? 'API' : item.owner_id;
            cardHtml += `
            <div class="col-md-4 mb-4">
                <div class="card">
                    <img src="${item.image_url}" alt="${item.title}" class="card-img-top">
                    <div class="card-body">
                        <h6 class="card-title">${item.title}</h6>
                        <h5 class="card-text">${item.description}</h5>
                        <p class="text-muted"><i class="fas fa-user"></i> ${owner}</p>
                        <p class="text-muted"><i class="fas fa-calendar-alt"></i> ${item.created_at}</p>
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
        // Se il campo di ricerca è vuoto, mostra tutti i dati con la paginazione corrente
        if(searchTerm === '') {
            changePage(currentPage, itemPerPage);
        }
    }

    function orderData(value) {
        switch(value) {
            case 'created_at_desc':
                im_grid.data.sort((a, b) => b.created_at_timestamp - a.created_at_timestamp);
            break;
            case 'created_at_asc':
                im_grid.data.sort((a, b) => a.created_at_timestamp - b.created_at_timestamp);
            break;
            case 'title_asc':
                im_grid.data.sort((a, b) => a.title.localeCompare(b.title));
            break;
            case 'title_desc':
                im_grid.data.sort((a, b) => b.title.localeCompare(a.title));
            break;
            case 'random':
                im_grid.data.sort(() => Math.random() - 0.5);
            break;
        }
        //Richiamo la pagina corrente per applicare l'ordinamento
        changePage(currentPage, itemPerPage);
    }

    function changeView(style){
        if(style==undefined) style='<?php echo $style; ?>';
        if(style === 'table') {
            im_style = 'card';
            changePage(currentPage, itemPerPage);
        } else {
            im_style = 'table';
            changePage(currentPage, itemPerPage);
        }
    }
</script>