<?php
/**
 * UPLOAD_MINIMAL.PHP - Versione minima per debug errore 500
 * Ogni step Þ testato separatamente
 */

// Configurazione errori
error_reporting(E_ALL);
ini_set('display_errors', 0); // OFF per evitare HTML nell'output JSON
ini_set('log_errors', 1);

// Headers JSON obbligatori PRIMA di qualsiasi output
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Funzione per output JSON sicuro
function jsonResponse($success, $message, $data = null) {
    $response = array('success' => $success);
    
    if ($success) {
        $response['message'] = $message;
        if ($data) {
            $response = array_merge($response, $data);
        }
    } else {
        $response['error'] = $message;
    }
    
    echo json_encode($response);
    exit;
}

// Funzione per log errori
function logError($message) {
    error_log("UPLOAD_MINIMAL: " . $message);
}

// Buffer di output per catturare eventuali errori
ob_start();

try {
    logError("=== INIZIO UPLOAD DEBUG ===");
    
    // Step 1: Verifica metodo
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Metodo non consentito - richiesto POST');
    }
    logError("Step 1 OK: Metodo POST");
    
    // Step 2: Include config con gestione errori
    $config_error = '';
    set_error_handler(function($errno, $errstr) use (&$config_error) {
        $config_error = "Config Error $errno: $errstr";
    });
    
    $config_included = include_once 'config.php';
    restore_error_handler();
    
    if (!$config_included || !empty($config_error)) {
        logError("Step 2 FAIL: Config - $config_error");
        jsonResponse(false, 'Errore caricamento configurazione: ' . $config_error);
    }
    logError("Step 2 OK: Config incluso");
    
    // Step 3: Verifica funzioni base
    $required_functions = array('getDb2Connection', 'callWebDecod', 'getCurrentUser');
    foreach ($required_functions as $func) {
        if (!function_exists($func)) {
            logError("Step 3 FAIL: Funzione mancante: $func");
            jsonResponse(false, "Funzione richiesta mancante: $func");
        }
    }
    logError("Step 3 OK: Funzioni base trovate");
    
    // Step 4: Sessione e autenticazione SEMPLIFICATA
    session_start();
    
    // Per test, crea sessione fittizia se non esiste
    if (!isset($_SESSION['username'])) {
        $_SESSION['username'] = 'TEST_USER';
        $_SESSION['livello'] = 'BACKOFFICE';
        $_SESSION['expires_at'] = time() + 3600;
        logError("Step 4: Sessione test creata");
    }
    
    $current_user = getCurrentUser();
    if (!$current_user['username']) {
        jsonResponse(false, 'Utente non autenticato');
    }
    logError("Step 4 OK: Utente: " . $current_user['username']);
    
    // Step 5: Validazione parametri
    $num_ticket = isset($_POST['num_ticket']) ? (int)$_POST['num_ticket'] : 0;
    $num_cartellino = isset($_POST['num_cartellino']) ? (int)$_POST['num_cartellino'] : 0;
    $data_registrazione = isset($_POST['data_registrazione']) ? trim($_POST['data_registrazione']) : '';
    
    if ($num_ticket <= 0 || $num_ticket > 9999999) {
        jsonResponse(false, 'Numero ticket non valido: ' . $num_ticket);
    }
    
    if ($num_cartellino <= 0 || $num_cartellino > 9999999) {
        jsonResponse(false, 'Numero cartellino non valido: ' . $num_cartellino);
    }
    
    if (empty($data_registrazione)) {
        jsonResponse(false, 'Data registrazione richiesta');
    }
    
    logError("Step 5 OK: Parametri - Ticket:$num_ticket, Cartellino:$num_cartellino, Data:$data_registrazione");
    
    // Step 6: Connessione DB
    $db_error = '';
    set_error_handler(function($errno, $errstr) use (&$db_error) {
        $db_error = "DB Error $errno: $errstr";
    });
    
    $conn = getDb2Connection();
    restore_error_handler();
    
    if (!$conn) {
        logError("Step 6 FAIL: Connessione DB fallita - $db_error");
        jsonResponse(false, 'Errore connessione database: ' . $db_error);
    }
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    if (!db2_exec($conn, $set_schema_sql)) {
        $schema_error = db2_conn_errormsg($conn);
        logError("Step 6 FAIL: Schema - $schema_error");
        jsonResponse(false, 'Errore impostazione schema: ' . $schema_error);
    }
    
    logError("Step 6 OK: DB connesso e schema impostato");
    
    // Step 7: Test WEBDECOD
    $webdecod_error = '';
    set_error_handler(function($errno, $errstr) use (&$webdecod_error) {
        $webdecod_error = "WEBDECOD Error $errno: $errstr";
    });
    
    $decoded_data = callWebDecod($num_ticket);
    restore_error_handler();
    
    if (!is_array($decoded_data)) {
        logError("Step 7 WARNING: WEBDECOD non ha restituito array - Tipo: " . gettype($decoded_data) . " - $webdecod_error");
        
        // Usa fallback sicuro
        $decoded_data = array(
            'cod_produttore' => 'MANUAL',
            'rag_soc_produttore' => 'PRODUTTORE TEST - TICKET ' . $num_ticket,
            'cod_ispettore' => 'MANUAL',
            'nome_ispettore' => 'ISPETTORE TEST'
        );
        
        logError("Step 7: Fallback applicato");
    } else {
        logError("Step 7 OK: WEBDECOD ha restituito array valido");
    }
    
    // Step 8: Verifica ticket esistente
    $check_sql = "SELECT NUM_TICKET FROM WTICKET WHERE NUM_TICKET = ?";
    $check_stmt = db2_prepare($conn, $check_sql);
    
    if (!$check_stmt) {
        $prepare_error = db2_stmt_errormsg($conn);
        logError("Step 8 FAIL: Prepare check - $prepare_error");
        jsonResponse(false, 'Errore preparazione query verifica: ' . $prepare_error);
    }
    
    if (!db2_execute($check_stmt, array($num_ticket))) {
        $execute_error = db2_stmt_errormsg($check_stmt);
        logError("Step 8 FAIL: Execute check - $execute_error");
        jsonResponse(false, 'Errore esecuzione query verifica: ' . $execute_error);
    }
    
    $existing = db2_fetch_assoc($check_stmt);
    if ($existing) {
        logError("Step 8 FAIL: Ticket esistente");
        jsonResponse(false, 'Ticket giÓ esistente nel sistema');
    }
    
    logError("Step 8 OK: Ticket non esistente - OK per inserimento");
    
    // Step 9: SOLO PER TEST - Non inserire realmente
    logError("Step 9: SIMULAZIONE INSERIMENTO (non eseguito per sicurezza)");
    
    // Simula successo
    db2_close($conn);
    
    logError("=== UPLOAD DEBUG COMPLETATO CON SUCCESSO ===");
    
    // Pulizia buffer
    $output_buffer = ob_get_clean();
    if (!empty($output_buffer)) {
        logError("Output buffer non vuoto: " . substr($output_buffer, 0, 200));
    }
    
    // Risposta di successo
    jsonResponse(true, 'Test upload completato con successo', array(
        'ticket_id' => $num_ticket,
        'webdecod_data' => $decoded_data,
        'test_mode' => true,
        'steps_completed' => 9
    ));
    
} catch (Exception $e) {
    // Pulizia buffer in caso di errore
    $output_buffer = ob_get_clean();
    
    logError("EXCEPTION: " . $e->getMessage());
    logError("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    if (isset($conn)) {
        db2_close($conn);
    }
    
    jsonResponse(false, 'Errore interno: ' . $e->getMessage());
    
} catch (Error $e) {
    // Gestione errori fatali PHP
    $output_buffer = ob_get_clean();
    
    logError("FATAL ERROR: " . $e->getMessage());
    logError("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    if (isset($conn)) {
        db2_close($conn);
    }
    
    jsonResponse(false, 'Errore fatale PHP: ' . $e->getMessage());
}

// Fallback finale - non dovrebbe mai arrivare qui
logError("FALLBACK: Reached end of script unexpectedly");
jsonResponse(false, 'Errore imprevisto - fine script');
?>