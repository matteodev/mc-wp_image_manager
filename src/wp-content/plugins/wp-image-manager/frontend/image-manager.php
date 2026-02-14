<?php
//Carico libreria datatables 
function load_datatable_library() {
    wp_enqueue_script( 'jquery' );//Uso jQuery(integrato in WP) come dipendenza per datatables
    wp_enqueue_script( 'datatable-js', 'https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js', array( 'jquery' ), '1.10.24', true );
    wp_enqueue_style( 'datatable-css', 'https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css', array(), '1.10.24' );
}
add_action( 'wp_enqueue_scripts', 'load_datatable_library' );
?>
<table id="image-table" class="display">
    <thead>
        <tr>
            <th>Titolo</th>
            <th>Data</th>
            <th>Immagine</th>
            <th>Caricata da</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>
<script>
    jQuery(document).ready(function($) {
        $('#image-table').DataTable({
            "ajax": {
                "url": "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                "type": "POST",
                "dataSrc": "data",
                "data": {
                    "action": "get_images_data",
                    "nonce": "<?php echo wp_create_nonce( 'get_images_data_nonce' ); ?>"
                }
            },
            "paging": true,
            "searching": true,
            "ordering": true,
            "order": [[ 1, "desc" ]],
            "info": true,
            "responsive": true, 
         	"language": {
		        "url": "https://cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Italian.json"
            }   
        });
    });
</script>