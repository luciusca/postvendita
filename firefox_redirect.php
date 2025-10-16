<?php
/**
 * FIREFOX_REDIRECT.PHP - Bridge per Firefox dopo login
 * Questo file serve come ponte per assicurare che Firefox mantenga la sessione
 */

require_once 'config.php';

// Avvia sessione
startSessionSafe();

// Log
//error_log("FIREFOX_REDIRECT - Session ID: " . session_id());
//error_log("FIREFOX_REDIRECT - SESSION: " . print_r($_SESSION, true));
//error_log("FIREFOX_REDIRECT - Cookie: " . (isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : 'NOT SET'));

// Verifica che ci siano dati in sessione
if (isset($_SESSION['username'])) {
    // Sessione OK, redirect a index
    header('Location: index.php');
    exit;
} else {
    // Sessione persa, torna al login
   // error_log("FIREFOX_REDIRECT - Session lost!");
    header('Location: login.html?error=session_lost');
    exit;
}
?>