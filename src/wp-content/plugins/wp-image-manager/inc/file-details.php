<?php 
if( $image->owner_id != 0 ){
    $upload_dir = wp_upload_dir(); 
    $image->image_url =  $upload_dir['baseurl'] . '/' . $this->getRepo() . '/' . $image->image_url;
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
            $latitudine = $metadati["GPS"]["GPSLatitude"];
            $longitudine = $metadati["GPS"]["GPSLongitude"];
        }
    }
    
    //Converto la data scatto in formato d/m/Y H:i:s
    if ( $dataScatto_raw != '' && $dataScatto_raw != 0 ) {
        $dt = DateTime::createFromFormat('Y:m:d H:i:s', $dataScatto_raw);
        $dataScatto = $dt ? $dt->format('d/m/Y H:i:s') : null;
    }

    //Converto la latitudine e longitudine in formato decimale
    if( $latitudine != '' && $longitudine != '' ){
        $latitudine_dec = gpsToDecimal(
            convertExifCoord($latitudine[0], $metadati["GPS"]["GPSLatitudeRef"]),
            convertExifCoord($latitudine[1], $metadati["GPS"]["GPSLatitudeRef"]),
            convertExifCoord($latitudine[2], $metadati["GPS"]["GPSLatitudeRef"]),
            $metadati["GPS"]["GPSLatitudeRef"]
        );
        $longitudine_dec = gpsToDecimal(
            convertExifCoord($longitudine[0], $metadati["GPS"]["GPSLongitudeRef"]),
            convertExifCoord($longitudine[1], $metadati["GPS"]["GPSLongitudeRef"]),
            convertExifCoord($longitudine[2], $metadati["GPS"]["GPSLongitudeRef"]),
            $metadati["GPS"]["GPSLongitudeRef"]
        );

        $posizione = $latitudine_dec . ',' . $longitudine_dec;
        //Ricavo l'indirizzo usando API free usando CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' . $latitudine_dec . '&lon=' . $longitudine_dec);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        $output = curl_exec($ch);
        curl_close($ch);
        $indirizzo = json_decode($output,true);
        $indirizzo = $indirizzo['display_name'];
       
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
            <?php if( $indirizzo != '' ){ ?>
            <p class="my-1">
                <strong>
                    <i class="fas fa-map-marker-alt" 
                    data-toggle="tooltip" 
                    data-placement="top" 
                    title="Posizione"></i>
                </strong> 
                <?php echo $indirizzo; ?>
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