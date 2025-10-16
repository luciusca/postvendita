<?php
/**
 * SESSION_FIX.PHP - Funzioni per gestire sessioni con Firefox
 * Questo file gestisce i problemi di compatibilità delle sessioni con Firefox su iSeries
 */

/**
 * Avvia sessione con workaround per Firefox
 */
function startSessionSafe() {
    // Se la sessione è già attiva, non fare nulla
    if (session_id() != '') {
        return true;
    }
    
    // Workaround per Firefox: forza l'uso del session ID dal cookie se presente
    if (isset($_COOKIE['PHPSESSID']) && !empty($_COOKIE['PHPSESSID'])) {
        // Valida il session ID per sicurezza
        $session_id = $_COOKIE['PHPSESSID'];
        if (preg_match('/^[a-zA-Z0-9]{26,32}$/', $session_id)) {
            session_id($session_id);
            error_log("startSessionSafe - Forcing session ID from cookie: " . $session_id);
        }
    }
    
    // Avvia la sessione
    $result = session_start();
    
    // Log per debug
   // error_log("startSessionSafe - Session started: " . (session_id() ? session_id() : 'NO ID'));
   // error_log("startSessionSafe - Session data: " . print_r($_SESSION, true));
    
    return $result;
}

/**
 * Salva e ricarica sessione (workaround per Firefox)
 */
function saveAndReloadSession() {
    $session_id = session_id();
    if ($session_id) {
        // Forza scrittura su disco
        session_write_close();
        
        // Piccola pausa per assicurare che il file sia scritto
        usleep(50000); // 50ms
        
        // Riapri la sessione con lo stesso ID
        session_id($session_id);
        session_start();
        
      //  error_log("saveAndReloadSession - Session reloaded: " . $session_id);
        return true;
    }
    return false;
}

/**
 * Verifica se il browser è Firefox
 */
function isFirefoxBrowser() {
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return (stripos($user_agent, 'firefox') !== false);
}

/**
 * Fix specifico per Firefox dopo login
 */
function fixFirefoxSession() {
    if (isFirefoxBrowser()) {
      //  error_log("fixFirefoxSession - Firefox detected, applying workaround");
        
        // Forza il refresh del cookie di sessione
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            0, // Session cookie
            $params['path'],
            $params['domain'],
            $params['secure'],
            true // httponly
        );
        
        // Salva e ricarica
        saveAndReloadSession();
    }
}
?>