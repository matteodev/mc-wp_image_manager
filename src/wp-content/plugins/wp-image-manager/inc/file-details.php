<?php 
$metadati = "";
if( $image->owner_id != 0 ){
    $upload_dir = wp_upload_dir(); 
    $image->image_url =  $upload_dir['baseurl'] . '/' . $this->getRepo() . '/' . $image->image_url;
}
if( $image->metadati != '' ){
    $metadati = json_decode($image->metadati,true);
}
$info = $this->metadatiAnalyser($metadati);
?>
<div class="container-fluid" style="max-height: 80vh; overflow-y: auto;">
    <div class="row">
        <div class="col-md-5">
            <img src="<?php echo $image->image_url; ?>" alt="<?php echo $image->title; ?>" style="max-width: 70%; height: auto;">
        </div>

        <div class="col-md-7 px-0">
            <p class="my-2"><strong>Titolo:</strong> <?php echo $image->title; ?></p>
            <?php if( $image->description != '' ){ ?>
                <p class="my-2"><strong>Descrizione:</strong> <br><?php echo $image->description; ?></p>
            <?php } ?>
            <hr>
            <p class="my-2">
                <strong>
                    <i class="fas fa-user" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Caricato da"></i>
                </strong> 
                <?php echo ($image->owner_id == 0)? 'API':$image->owner_id; ?>
            </p>
            <p class="my-2">
                <strong>
                    <i class="fas fa-database" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Caricato il"></i>
                </strong> 
                <?php echo date('d/m/Y H:i:s', strtotime($image->created_at)); ?>
            </p>
            <?php if( $info['data_scatto'] != ''){ ?>
            <p class="my-2">
                <strong>
                    <i class="fas fa-camera" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Data scatto"></i>
                </strong> 
                <?php echo $info['data_scatto']; ?>
            </p>
            <?php } ?>
            <?php if($info['marca'] != '' && $info['modello'] != ''){ ?>
            <p class="my-2">
                <strong>
                    <i class="fas fa-mobile" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Marca e modello"></i>
                </strong> 
                <?php echo $info['marca'] . ' ' . $info['modello']; ?>
            </p>
            <?php } ?>
            <?php if( $info['indirizzo'] != '' ){ ?>
            <p class="my-2">
                <strong>
                    <i class="fas fa-map-marker-alt" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Posizione"></i>
                </strong> 
                <?php echo $info['indirizzo']; ?>
            </p>
            <?php } ?>
            <?php if( $info['meteo'] != '' ){ ?>
            <p class="my-2">
                <strong>
                    <i class="fas fa-thermometer-three-quarters" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Meteo"></i>
                </strong> 
                <?php echo $info['meteo']; ?> <span><small>Giorno dello scatto</small></span><br>
                <small>NB: La temperatura è una media tra la minima e la massima.</small>
            </p>
            <?php } ?>
            <?php if($info["meteo_today"] != ''){ ?>
            <p class="my-2">
                <strong>
                    <i class="fas fa-thermometer-three-quarters" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Meteo oggi"></i>
                </strong> 
                <?php echo $info["meteo_today"]; ?> <span><small>Adesso</small></span><br>
            </p>
            <?php } ?>
            <?php if( $info['peso_file'] != ''){ ?>
            <p class="my-2">
                <strong>
                    <i class="fas fa-file-alt" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Peso file"></i>
                </strong> 
                <?php echo $info['peso_file']; ?>
            </p>
            <?php } ?>
            <?php if( $info['risoluzione'] != ''){ ?>
            <p class="my-2">
                <strong>
                    <i class="fas fa-image" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Risoluzione file"></i>
                </strong> 
                <?php echo $info['risoluzione']; ?> px
            </p>
            <?php } ?>
        </div>
    </div>
</div>