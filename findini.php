<?php
echo "PHP.ini caricato: " . php_ini_loaded_file() . "\n";
echo "Directory aggiuntive: " . php_ini_scanned_dir() . "\n";
echo "\nLimiti ATTUALI:\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";

// Mostra se le impostazioni possono essere cambiate
$changeable = array(
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time')
);

foreach($changeable as $setting => $value) {
    $info = ini_get_all($setting);
    echo "$setting: $value (modificabile: " . ($info[$setting]['access'] & 7 ? 'SI' : 'NO') . ")\n";
}
?>