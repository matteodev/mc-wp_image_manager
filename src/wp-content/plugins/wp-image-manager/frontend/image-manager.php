<?php
wp_enqueue_script( 'jquery' );
wp_enqueue_script( 'bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js' );
wp_enqueue_style( 'bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css' );
wp_enqueue_style( 'font-awesome-css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css' );
wp_enqueue_style('image-manager-css', plugin_dir_url( __FILE__ ) . 'style.css' );
?>
<div class="loading-spinner text-center">
    <div class="spinner-border text-primary" role="status"></div>
</div>
<div class="image-manager-panel">
    <div class="row mb-3">
        <div class="col-md-12 mb-2">ID: <?php echo $this->session_id; ?></div>
        <div class="col-md-3">
            <label for="data-search" class="form-label">Ricerca</label>
            <input type="text" onkeyup="filterData()" id="data-search" class="form-control form-control-sm" placeholder="Filtra per titolo..." >
        </div>
        <div class="col-md-3">
            <label for="image-sort" class="form-label">Ordina per</label>
            <select id="image-sort" class="form-control form-control-sm" onchange="orderData(this.value)">
                <option value="title_asc">Titolo (A-Z)</option>
                <option value="title_desc">Titolo (Z-A)</option>
                <option value="created_at_desc">Data (nuove prima)</option>
                <option value="created_at_asc">Data (vecchie prima)</option>
                <option value="random">A caso</option>
            </select>
        </div>
        <div id="list-actions" class="col-md-6 pt-2 text-right">
            <button class="btn btn-secondary btn-sm mt-4" onclick="changeView(im_style)">Cambia Visualizzazione</button>
            <button class="btn btn-primary btn-sm mt-4" onclick="addImage()">Aggiungi immagine</button>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-12">
            <div id="pagination"></div>
        </div>
    </div>
</div>
<div class="image-manager-layout"></div>
<div class="image-manager-modal modal"></div>
<script>
    var im_grid = [];
    var im_grid_images = [];
    var im_grid_hidden_images = [];
    var selectedImagesToHide = [];
    var im_style = '<?php echo $style; ?>';
    var itemPerPage = 5;
    var currentPage = 1;
    var currentView = 'visible';
    
    jQuery(document).ready(function() {
        //Rimuovo il titolo del post creato automaticamente da WordPress
        jQuery('.wp-block-post-title').remove();

        //Avvio ImageManager
        loadImageManager();
    });

    function loading(){
        //Inizializzo il layout
        jQuery('.image-manager-layout').html('');
        //Nascondo il layout
        jQuery('.image-manager-panel, .image-manager-layout').css('display', 'none');
        //Mostro lo spinner di caricamento
        jQuery('.loading-spinner').css('display', 'block');
    }

    function loadImageManager(){
        loading();
        //Recupero dal server le immagini
        jQuery.post( "<?php echo admin_url( 'admin-ajax.php' ); ?>", {
            action: "get_images_data",
            nonce: "<?php echo wp_create_nonce( 'get_images_data_nonce' ); ?>"
        }, function(response) {
            if(response.success) {
                im_grid_images = response.data.images;
                im_grid_hidden_images = response.data.hidden_images;

                if(currentView === 'visible'){
                    showGrid('visible');
                }
                else{
                    showGrid('hidden');
                }
        
                //Mostro il layout
                jQuery('.image-manager-panel, .image-manager-layout').css('display', 'block');
                //Nascondo lo spinner di caricamento
                jQuery('.loading-spinner').css('display', 'none');
            } else {
                jQuery('.image-manager-layout').html(
                    `<div class="alert alert-danger" role="alert">Errore durante il recupero dei dati</div>`
                );
                //Nascondo lo spinner di caricamento
                jQuery('.loading-spinner').css('display', 'none');
                //Mostro il layout
                jQuery('.image-manager-layout').css('display', 'block');
            }

            //Resetto campi di ricerca e ordinamento
            jQuery('.image-manager-panel #data-search').val('');
            jQuery('.image-manager-panel #image-sort').val(jQuery('.image-manager-panel #image-sort option:first').val());
        
        });
    }

    function showGrid(type){
        currentView = type;
        im_grid = im_grid_images.filter(function(image) {
            switch(type){
                case 'hidden':
                    return im_grid_hidden_images.includes(image.id);
                case 'visible':
                    return !im_grid_hidden_images.includes(image.id);
                default:
                    return !im_grid_hidden_images.includes(image.id);
            }
        });

        //Imposto la paginazione
        setPagination(im_grid.length, itemPerPage);
        //Mostro la prima pagina
        changePage(1, itemPerPage);
    }

    function setPagination(totalItems, itemsPerPage) {
        var totalPages = Math.ceil(totalItems / itemsPerPage);
        var paginationHtml = '<nav><ul class="pagination justify-content-center my-2">';
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
        var pageData = im_grid.slice(startIndex, endIndex);
        if(im_style === 'table') {
            renderTable(pageData);
        } 
        if(im_style === 'card') {
            renderCards(pageData);
        }

        //Se ci sono immagini nascoste mostro il bottone
        jQuery('.image-manager-panel #image-grid-btn').remove();
        if(im_grid_hidden_images.length > 0 && currentView === 'visible'){
            jQuery('#list-actions').append(
                `<button id="image-grid-btn" class="btn btn-secondary btn-sm mt-4" onclick="showGrid('hidden')">Vedi immagini nascoste</button>`
            );
        }
        if(currentView === 'hidden'){
            jQuery('#list-actions').append(
                `<button id="image-grid-btn" class="btn btn-secondary btn-sm mt-4" onclick="showGrid('visible')">Torna indietro</button>`
            );
        }
    }

    function renderTable(data) {
        if(data.length == 0) {
            jQuery('.image-manager-layout').html(
                `<div class="alert alert-warning" role="alert">Nessuna immagine trovata</div>`
            );
            return;
        }
        var checkAll = "";
        var checked = "";
        var bgClass = "";

        if(selectedImagesToHide.length == im_grid.length ) {
            checkAll = "checked";
        }

        var tableHtml = `
        <table class="table">
            <thead>
                <tr>
                    <th scope="col"><input type="checkbox" id="select-all" ${checkAll}></th>
                    <th scope="col">Titolo</th>
                    <th scope="col">Data</th>
                    <th scope="col">Immagine</th>
                    <th scope="col">Caricata da</th>
                </tr>
            </thead>
        <tbody>`;
        data.forEach(function(item) {
            var owner = item.owner_id == 0 ? 'API' : item.owner_id;

            //Se l'immagine è stata selezionata per essere nascosta, preparo la grafica
            if(selectedImagesToHide.includes(item.id)) {
                checked = "checked";
                bgClass = 'bg-info';
            }else{
                checked = "";
                bgClass = "";
            }

            tableHtml += `
            <tr id="image-${item.id}" class="${bgClass}">
                <td><input type="checkbox" class="image-checkbox" value="${item.id}" ${checked}></td>
                <td>${item.title}</td>
                <td>${item.created_at}</td>
                <td><img class="img-item" onclick="openDetail('${item.id}')" src="${item.image_url}" alt="${item.title}" style="max-width: 100px;"></td>
                <td>${owner}</td>
            </tr>`;
        });
        tableHtml += '</tbody></table>';
        jQuery('.image-manager-layout').html(tableHtml);

        //Seleziona tutto
        jQuery('.image-manager-layout #select-all').on('change', function() {
            if(jQuery(this).is(':checked')) {
                is_checked = true;
                jQuery('.image-manager-layout .image-checkbox').prop('checked', true);
                jQuery('.image-manager-layout tr').addClass('bg-info');
            } else {
                is_checked = false;
                jQuery('.image-manager-layout .image-checkbox').prop('checked', false);
                jQuery('.image-manager-layout tr').removeClass('bg-info');
            }
            selectToHide("all",is_checked);
        });
        //Selezione singola
        jQuery('.image-manager-layout .image-checkbox').on('change', function() {
            if(jQuery(this).is(':checked')) {
                is_checked = true;
                jQuery('.image-manager-layout tr#image-' + this.value).addClass('bg-info');
            } else {
                is_checked = false;
                jQuery('.image-manager-layout tr#image-' + this.value).removeClass('bg-info');
            }
            selectToHide(this.value, is_checked);
        });
    }

    function renderCards(data) {
        if(data.length == 0) {
            jQuery('.image-manager-layout').html(
                `<div class="alert alert-warning" role="alert">Nessuna immagine trovata</div>`
            );
            return;
        }
        var checkAll = "";
        var checked = "";
        var bgClass = "";
        var cardHtml = '<div class="row">';

        if(selectedImagesToHide.length == im_grid.length) {
            checkAll = "checked";
        }

        cardHtml += `
            <div class="col-md-12 mb-4">
                <input type="checkbox" id="select-all" ${checkAll}> Seleziona tutto
            </div>
        `;
        data.forEach(function(item) {
            var owner = item.owner_id == 0 ? 'API' : item.owner_id;

            //Se l'immagine è stata selezionata per essere nascosta, preparo la grafica
            if(selectedImagesToHide.includes(item.id)) {
                checked = "checked";
                bgClass = 'bg-info';
            } else {
                checked = "";
                bgClass = "";
            }

            cardHtml += `
            <div class="col-md-4 mb-4">
                <div id="image-${item.id}" class="card h-100">
                    <div class="card-img-overlay">
                        <input type="checkbox" class="image-checkbox" value="${item.id}" ${checked}>
                    </div>
                    <img onclick="openDetail('${item.id}')" src="${item.image_url}" alt="${item.title}" class="card-img-top img-item">
                    <div class="card-body ${bgClass}">
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

        //Seleziona tutto
        jQuery('.image-manager-layout #select-all').on('change', function() {
            if(jQuery(this).is(':checked')) {
                is_checked = true;
                jQuery('.image-manager-layout .image-checkbox').prop('checked', true);
                jQuery('.image-manager-layout .card .card-body').addClass('bg-info');
            } else {
                is_checked = false;
                jQuery('.image-manager-layout .image-checkbox').prop('checked', false);
                jQuery('.image-manager-layout .card .card-body').removeClass('bg-info');
            }
            selectToHide("all",is_checked);
        });

        //Selezione singola
        jQuery('.image-manager-layout .image-checkbox').on('change', function() {
            if(jQuery(this).is(':checked')) {
                is_checked = true;
                jQuery('.image-manager-layout .card#image-' + this.value + ' .card-body').addClass('bg-info');
            } else {
                is_checked = false;
                jQuery('.image-manager-layout .card#image-' + this.value + ' .card-body').removeClass('bg-info');
            }
            selectToHide(this.value, is_checked);
        });
    }

    function selectToHide(imageId, checked) {
        if(checked) {
            if(imageId === "all") {
                im_grid.forEach(function(item) {
                    selectedImagesToHide.push(item.id);
                });
            } else {
                selectedImagesToHide.push(imageId);
            }
        } else {
            if(imageId === "all") {
                selectedImagesToHide = [];
            } else {
                selectedImagesToHide = selectedImagesToHide.filter(id => id !== imageId);
            }
        }

        //Se ci sono immagini selezionate per essere nascoste, mostro il pulsante
        jQuery('.image-manager-panel button#hide-selected-images').remove();

        if(selectedImagesToHide.length > 0) {
            if(selectedImagesToHide.length == 1) label = "immagine";
            else label = "immagini";

            if(currentView === 'visible') to_do = "Nascondi";
            else to_do = "Ripristina";

            jQuery('.image-manager-panel #list-actions').append(`
            <button id="hide-selected-images" class="btn btn-secondary btn-sm mt-4" 
            onclick="fetchSelectedImages()">${to_do} ${selectedImagesToHide.length} ${label}</button>
            `);
        }
    }

    function fetchSelectedImages() {
        //Identifico l'azione da eseguire in base alla visualizzazione corrente
        if(currentView === 'visible') {
            action = "hide_selected_images";
        } 
        if(currentView === 'hidden') {
            action = "restore_selected_images";
        }

        jQuery.post("<?php echo admin_url('admin-ajax.php'); ?>", {
            action: action,
            nonce:"<?php echo wp_create_nonce('fetch_selected_images_nonce'); ?>",
            selected_images: selectedImagesToHide
        }).done(function(response) {
            if(response.success) {
                //Carico nuovamente l'image manager per aggiornare la griglia
                loadImageManager();
                //Svuoto la lista delle immagini selezionate per essere nascoste
                selectedImagesToHide = [];
                //Rimuovo il pulsante per nascondere le immagini selezionate
                jQuery('.image-manager-panel button#hide-selected-images').remove();
            } else {
                alert('Si è verificato un errore durante l\'operazione.');
            }
        }).fail(function() {
            alert('Si è verificato un errore durante l\'operazione.');
        })
    }

    function openDetail(imageId) {
        jQuery.post("<?php echo admin_url('admin-ajax.php'); ?>", {
            action: "get_image_detail",
            nonce:"<?php echo wp_create_nonce('get_image_detail_nonce'); ?>",
            image_id: imageId
        }).done(function(response) {
            if(response.success) {
                //Preparo la modale
                jQuery('.image-manager-modal').html(`
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content p-4">
                            <div class="modal-header">
                                <h5 class="modal-title">Dettagli immagine</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                ${response.data.page}
                            </div>
                        </div>
                    </div>
                `)
                //Mostro la modale
                jQuery('.image-manager-modal').modal('show');
            } else {
                alert('Si è verificato un errore durante l\'operazione.');
            }
        }).fail(function() {
            alert('Si è verificato un errore durante l\'operazione.');
        })
    }

    function filterData() {
        var searchTerm = jQuery('#data-search').val().toLowerCase();
        // Filtra i dati in base al titolo
        var filteredData = im_grid.filter(function(newData) {
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
                im_grid.sort((a, b) => b.created_at_timestamp - a.created_at_timestamp);
            break;
            case 'created_at_asc':
                im_grid.sort((a, b) => a.created_at_timestamp - b.created_at_timestamp);
            break;
            case 'title_asc':
                im_grid.sort((a, b) => a.title.localeCompare(b.title));
            break;
            case 'title_desc':
                im_grid.sort((a, b) => b.title.localeCompare(a.title));
            break;
            case 'random':
                im_grid.sort(() => Math.random() - 0.5);
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

    function addImage() {
       jQuery.post( "<?php echo admin_url( 'admin-ajax.php' ); ?>", {
            action: "add_image",
            nonce: "<?php echo wp_create_nonce( 'add_image_nonce' ); ?>"
        }, function(response) {
            if(response.success) {
                //Preparo la modale
                jQuery('.image-manager-modal').html(`
                    <div class="modal-dialog" role="document">
                        <div class="modal-content p-4">
                            <div class="modal-header">
                                <h5 class="modal-title">Caricamento immagine</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                ${response.data.page}
                            </div>
                        </div>
                    </div>
                `);
                jQuery('.image-manager-modal').modal('show');

                //Ricarico la griglia di immagini dopo il caricamento
                jQuery('.image-manager-modal').on('hidden.bs.modal', function () {
                    loadImageManager();
                });
            } else {
                alert('Si è verificato un errore');
            }
        });
    }

</script>