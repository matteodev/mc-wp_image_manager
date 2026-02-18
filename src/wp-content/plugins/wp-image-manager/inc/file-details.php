<?php 
if( $image->owner_id != 0 ){
    $upload_dir = wp_upload_dir(); 
    $image->image_url =  $upload_dir['baseurl'] . '/' . $this->getRepo() . '/' . $image->image_url;
}
$metadati = json_decode($image->metadati,true);
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
            $latitudine = $metadati["GPS"]["Latitude"];
            $longitudine = $metadati["GPS"]["Longitude"];
        }
    }
    
    //Converto la data scatto in formato d/m/Y H:i:s
    if ( $dataScatto_raw != '' && $dataScatto_raw != 0 ) {
        $dt = DateTime::createFromFormat('Y:m:d H:i:s', $dataScatto_raw);
        $dataScatto = $dt ? $dt->format('d/m/Y H:i:s') : null;
    }
}
?>
<div class="container">
    <div class="row">
        <div class="col-md-6">
            <img src="<?php echo $image->image_url; ?>" alt="<?php echo $image->title; ?>" class="img-fluid">
        </div>

        <div class="col-md-6">
            <p class="my-1"><strong>Titolo:</strong> <?php echo $image->title; ?></p>
            <?php if( $image->description != '' ){ ?>
                <p class="my-1"><strong>Descrizione:</strong> <br><?php echo $image->description; ?></p>
            <?php } ?>
            <hr>
            <p class="my-1">
                <strong>
                    <i class="fas fa-user" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Caricato da"></i>
                </strong> 
                <?php echo ($image->owner_id == 0)? 'API':$image->owner_id; ?>
            </p>
            <p class="my-1">
                <strong>
                    <i class="fas fa-database" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Caricato il"></i>
                </strong> 
                <?php echo date('d/m/Y H:i:s', strtotime($image->created_at)); ?>
            </p>
            <?php if( $dataScatto != '' && $dataScatto != 0 ){ ?>
            <p class="my-1">
                <strong>
                    <i class="fas fa-camera" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Data scatto"></i>
                </strong> 
                <?php echo $dataScatto; ?>
            </p>
            <?php } ?>
            <?php if($marca != '' && $modello != ''){ ?>
            <p class="my-1">
                <strong>
                    <i class="fas fa-mobile" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Marca e modello"></i>
                </strong> 
                <?php echo $marca . ' ' . $modello; ?>
            </p>
            <?php } ?>
            <?php if( $peso_file != ''){ ?>
            <p class="my-1">
                <strong>
                    <i class="fas fa-file-alt" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Peso file"></i>
                </strong> 
                <?php echo round($peso_file / 1024, 2); ?> KB
            </p>
            <?php } ?>
            <?php if( $risoluzione_file != ''){ ?>
            <p class="my-1">
                <strong>
                    <i class="fas fa-image" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Risoluzione file"></i>
                </strong> 
                <?php echo $risoluzione_file; ?>
            </p>
            <?php } ?>
        </div>
    </div>
</div>