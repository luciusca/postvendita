<?php
// NON includere nulla, NON avviare sessioni
// Leggi solo il cookie
header('Content-Type: application/json; charset=UTF-8');

// Controlla solo se il cookie esiste
$has_cookie = isset($_COOKIE['PHPSESSID']) && !empty($_COOKIE['PHPSESSID']);

echo json_encode(array(
    'success' => true,
    'authenticated' => $has_cookie,
    'valid' => $has_cookie
));
?>