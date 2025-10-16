<?php
/**
 * INIT_FIREFOX.PHP - DEVE essere incluso PER PRIMO in ogni file PHP
 * Questo file registra l'handler DB2 per Firefox PRIMA di tutto il resto
 */

// NON fare nulla se già inizializzato
if (defined('FIREFOX_HANDLER_INITIALIZED')) {
    return;
}

define('FIREFOX_HANDLER_INITIALIZED', true);

// Rileva Firefox
$is_firefox = (stripos($_SERVER['HTTP_USER_AGENT'], 'firefox') !== false);

if ($is_firefox) {
   // error_log("INIT_FIREFOX - Firefox detected on " . $_SERVER['REQUEST_URI']);
    
    // Carica handler DB2
    $handler_file = dirname(__FILE__) . '/session_handler_db2.php';
    
    if (file_exists($handler_file)) {
     //   error_log("INIT_FIREFOX - Loading DB2 handler");
        require_once $handler_file;
        
        // Crea e registra handler IMMEDIATAMENTE
        $handler = new DB2SessionHandler();
        
        $result = session_set_save_handler(
            array($handler, 'open'),
            array($handler, 'close'),
            array($handler, 'read'),
            array($handler, 'write'),
            array($handler, 'destroy'),
            array($handler, 'gc')
        );
        
        // Registra shutdown function
        register_shutdown_function('session_write_close');
        
       // error_log("INIT_FIREFOX - DB2 handler registered: " . ($result ? "SUCCESS" : "FAILED"));
        
        // Verifica
        $current_handler = ini_get('session.save_handler');
      //  error_log("INIT_FIREFOX - Current handler: " . $current_handler);
        
    } else {
     //   error_log("INIT_FIREFOX - ERROR: session_handler_db2.php not found at " . $handler_file);
    }
} else {
  //  error_log("INIT_FIREFOX - Not Firefox, using default handler");
}
?>