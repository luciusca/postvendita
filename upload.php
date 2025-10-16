<?php
/**
 * UPLOAD.PHP - Gestione upload ticket e allegati (PHP 5 compatible)
 * VERSIONE CORRETTA - Usa SQL invece di webpvend/webdecod e recupera cartellino automaticamente
 * Sistema Gestione Ticket Post-Vendita
 */

// Configurazione errori per debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // OFF per evitare HTML nell'output JSON
ini_set('log_errors', 1);
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
// ini_set('max_execution_time', 300);

require_once 'init_firefox.php';
require_once 'config.php';


// Avvia sessione
startSessionSafe();


// USA isValidSession() INVECE DELLA VERIFICA MANUALE
if (!isValidSession()) {
    echo json_encode(array('success' => false, 'error' => 'Sessione non valida'));
    exit;
}


// Ottieni dati utente
$current_user = getCurrentUser();

// NON chiamare requireAuth()! Verifica manuale
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(array('error' => 'Autenticazione richiesta', 'redirect' => 'login.html'));
    exit;
}



header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Funzione sicura per output JSON - PHP 5 compatible con UTF-8 fix
function safeJsonResponse($success, $message, $data = null) {
    // Pulisce qualsiasi output precedente
    if (ob_get_level()) {
        ob_clean();
    }
    
    $response = array('success' => $success);
    if ($success) {
        $response['message'] = cleanUtf8String($message);
        if ($data && is_array($data)) {
            $data = cleanUtf8Array($data);
            $response = array_merge($response, $data);
        }
    } else {
        $response['error'] = cleanUtf8String($message);
    }
    
    echo json_encode($response);
    exit;
}

// Funzione per pulire stringhe UTF-8 - PHP 5 compatible
function cleanUtf8String($str) {
    if (!is_string($str)) {
        return $str;
    }
    
    // Rimuove caratteri non UTF-8 validi
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    
    // Fallback se mb_convert_encoding non Þ disponibile
    if (!$str) {
        $str = utf8_encode($str);
    }
    
    return $str;
}

// Funzione per pulire array UTF-8 - PHP 5 compatible
function cleanUtf8Array($array) {
    if (!is_array($array)) {
        return cleanUtf8String($array);
    }
    
    $cleaned = array();
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $cleaned[cleanUtf8String($key)] = cleanUtf8Array($value);
        } else {
            $cleaned[cleanUtf8String($key)] = cleanUtf8String($value);
        }
    }
    
    return $cleaned;
}

try {
    // Verifica autenticazione e permessi
    if (!function_exists('requireAuth')) {
        safeJsonResponse(false, 'Funzione requireAuth non trovata');
    }
    
    requireAuth();
    $current_user = getCurrentUser();

    if (!checkPermission('BACKOFFICE')) {
        safeJsonResponse(false, 'Permessi insufficienti');
    }

    // Verifica metodo POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        safeJsonResponse(false, 'Metodo non consentito');
    }

    // Recupera e valida dati del ticket - CARTELLINO RIMOSSO
    $num_ticket = isset($_POST['num_ticket']) ? (int)$_POST['num_ticket'] : 0;
    $data_registrazione = isset($_POST['data_registrazione']) ? $_POST['data_registrazione'] : '';

    // Validazione input - CARTELLINO NON PIU' RICHIESTO
    if ($num_ticket <= 0 || $num_ticket > 9999999) {
        safeJsonResponse(false, 'Numero ticket non valido (1-9999999)');
    }

    if (empty($data_registrazione) || !validateDate($data_registrazione)) {
        safeJsonResponse(false, 'Data registrazione non valida');
    }

    // Verifica file allegati
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        safeJsonResponse(false, 'Almeno un file allegato Þ richiesto');
    }

    $conn = getDb2Connection();
    if (!$conn) {
        safeJsonResponse(false, 'Errore connessione database');
    }
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    if (!db2_exec($conn, $set_schema_sql)) {
        db2_close($conn);
        safeJsonResponse(false, 'Errore impostazione schema database');
    }

    // Verifica se il ticket esiste giÓ
    $check_sql = "SELECT NUM_TICKET FROM WTICKET WHERE NUM_TICKET = ?";
    $check_stmt = db2_prepare($conn, $check_sql);
    if (!$check_stmt) {
        db2_close($conn);
        safeJsonResponse(false, 'Errore preparazione query verifica');
    }
    
    if (!db2_execute($check_stmt, array($num_ticket))) {
        db2_close($conn);
        safeJsonResponse(false, 'Errore esecuzione query verifica');
    }

    $existing = db2_fetch_assoc($check_stmt);
    if ($existing) {
        db2_close($conn);
        error_log("UPLOAD: Ticket $num_ticket già esistente nel database");
        safeJsonResponse(false, "Ticket #$num_ticket già inserito nel sistema. Utilizzare un numero diverso.");
    }

    // NUOVO: RECUPERO DATI TRAMITE SQL INVECE DI WEBDECOD
  //  error_log("UPLOAD: Recupero dati tramite SQL per ticket: $num_ticket");
    
    // Verifica che la funzione esista
    if (!function_exists('getTicketDataBySQL')) {
        error_log("UPLOAD ERROR: Funzione getTicketDataBySQL non trovata");
        db2_close($conn);
        safeJsonResponse(false, 'Funzione recupero dati non disponibile');
    }
    
    $decoded_data = getTicketDataBySQL($num_ticket);

    if ($decoded_data === false || !is_array($decoded_data)) {
        error_log("UPLOAD ERROR: getTicketDataBySQL ha fallito per ticket: $num_ticket - La query SQL non ha trovato dati");
        
        // IMPORTANTE: Segnala che i dati reali non sono stati trovati
        $use_fallback = true;
        $decoded_data = array(
            'num_cartellino' => 9999999, // Numero cartellino che indica fallback
            'cod_produttore' => 'NOTFND',  // Max 6 caratteri
            'rag_soc_produttore' => 'DATI NON TROVATI - TK' . $num_ticket, // Max 35 caratteri
            'cod_ispettore' => 'NOTFND',   // Max 6 caratteri
            'nome_ispettore' => 'DATI NON TROVATI'  // Max 35 caratteri
        );
        
        // Tronca le stringhe per sicurezza - rispetta limiti DB2
        $decoded_data['cod_produttore'] = substr($decoded_data['cod_produttore'], 0, 6);
        $decoded_data['rag_soc_produttore'] = substr($decoded_data['rag_soc_produttore'], 0, 35);
        $decoded_data['cod_ispettore'] = substr($decoded_data['cod_ispettore'], 0, 6);
        $decoded_data['nome_ispettore'] = substr($decoded_data['nome_ispettore'], 0, 35);
        
        error_log("UPLOAD: ATTENZIONE - Ticket $num_ticket non trovato nel sistema principale, uso dati fallback");
    } else {
        $use_fallback = false;
    //    error_log("UPLOAD: SUCCESS - Dati reali recuperati per ticket: $num_ticket");
    }

    // Estrae il numero cartellino dai dati recuperati
    $num_cartellino = isset($decoded_data['num_cartellino']) ? (int)$decoded_data['num_cartellino'] : ($num_ticket + 1000);
    
    // Verifica che i campi essenziali esistano - PHP 5 compatible
    $required_fields = array('cod_produttore', 'rag_soc_produttore', 'cod_ispettore', 'nome_ispettore');
    foreach ($required_fields as $field) {
        if (!isset($decoded_data[$field])) {
            error_log("UPLOAD ERROR: Campo mancante in decoded_data: $field");
            $decoded_data[$field] = 'MANUAL_' . strtoupper($field);
        }
        // Assicura che non siano vuoti - PHP 5 compatible
        $trimmed_value = trim($decoded_data[$field]);
        if (empty($trimmed_value)) {
            $decoded_data[$field] = 'MANUAL_' . strtoupper($field);
        }
        
        // Pulisce UTF-8 per evitare errori json_encode e tronca per limiti DB
        $decoded_data[$field] = cleanUtf8String($decoded_data[$field]);
        
        // Applica limiti lunghezza campi DB2
        if ($field === 'cod_produttore' || $field === 'cod_ispettore') {
            $decoded_data[$field] = substr($decoded_data[$field], 0, 6);
        } else if ($field === 'rag_soc_produttore' || $field === 'nome_ispettore') {
            $decoded_data[$field] = substr($decoded_data[$field], 0, 35);
        }
    }

    // error_log("UPLOAD: Dati recuperati OK - Cartellino: $num_cartellino, CodProd: " . $decoded_data['cod_produttore'] . 
    //          ", RagSoc: " . $decoded_data['rag_soc_produttore']);

    // Anno per directory
    $anno_ticket = (int)date('Y', strtotime($data_registrazione));

    // Crea directory per l'anno se non esiste
    $upload_dir = createYearUploadDir($anno_ticket);
	
	
	
	    if (!$upload_dir) {
			
        db2_close($conn);
        safeJsonResponse(false, 'Impossibile creare directory upload');
    }

    // Inizia transazione
    db2_autocommit($conn, FALSE);

    try {
        // Inserisce record ticket principale - CARTELLINO RECUPERATO AUTOMATICAMENTE
        $ticket_sql = "INSERT INTO WTICKET
                       (NUM_TICKET, NUM_CARTEL, COD_PRODUT, RAG_SOC_PR, COD_ISPETT, NOME_ISPET,
                        DATA_REG, ANNO_TICKET, USERNAME_INS, DATA_INS)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

        $ticket_stmt = db2_prepare($conn, $ticket_sql);
        if (!$ticket_stmt) {
            throw new Exception('Errore preparazione query ticket: ' . db2_stmt_errormsg($conn));
        }

        // PHP 5 compatible array creation
        $ticket_params = array(
            $num_ticket,
            $num_cartellino, // CARTELLINO RECUPERATO AUTOMATICAMENTE
            $decoded_data['cod_produttore'],
            $decoded_data['rag_soc_produttore'],
            $decoded_data['cod_ispettore'],
            $decoded_data['nome_ispettore'],
            $data_registrazione,
            $anno_ticket,
            $current_user['username']
        );

        // error_log("UPLOAD: Parametri ticket preparati - Count: " . count($ticket_params));

        if (!db2_execute($ticket_stmt, $ticket_params)) {
            $db_error = db2_stmt_errormsg($ticket_stmt);
            error_log("UPLOAD DB ERROR: " . $db_error);
            throw new Exception('Errore inserimento ticket: ' . $db_error);
        }

        // error_log("UPLOAD: Ticket inserito con successo");

        // Processa i file allegati
        $files_uploaded = 0;
        $total_size = 0;
        $allowed_extensions = getAllowedExtensions();
        $files = $_FILES['files'];
        $file_count = count($files['name']);

        // error_log("UPLOAD: Processando $file_count file");

        for ($i = 0; $i < $file_count; $i++) {
            // Salta file vuoti
            if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
                error_log("UPLOAD: Saltando file $i - Nome: " . $files['name'][$i] . ", Errore: " . $files['error'][$i]);
                continue;
            }

            $original_name = cleanUtf8String($files['name'][$i]);
            $tmp_name = $files['tmp_name'][$i];
            $file_size = $files['size'][$i];

            //error_log("UPLOAD: Processando file: $original_name, Size: $file_size");

            // Validazioni file
            if ($file_size > MAX_FILE_SIZE) {
                throw new Exception("File '$original_name' troppo grande (max 20MB)");
            }

            if ($file_size === 0) {
                throw new Exception("File '$original_name' Þ vuoto");
            }

            // Verifica estensione
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed_extensions)) {
                throw new Exception("File '$original_name' ha estensione non consentita");
            }

            // Genera nome file unico
            $unique_filename = generateUniqueFileName($original_name, $anno_ticket);
            $destination_path = $upload_dir . '/' . $unique_filename;
            $relative_path = $anno_ticket . '/' . $unique_filename;

            // error_log("UPLOAD: Spostando file da $tmp_name a $destination_path");

            // Sposta il file
            if (!move_uploaded_file($tmp_name, $destination_path)) {
                throw new Exception("Errore spostamento file '$original_name'");
            }

            // error_log("UPLOAD: File spostato con successo");

            // Inserisce record allegato
            $file_sql = "INSERT INTO WALLEGATI
                         (NUM_TICKET, NOME_ORIG, NOME_FILE, TIPO_FILE, DIMENSIONE, PATH_FILE, DATA_UPLOAD, USERNAME_UPL)
                         VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";

            $file_stmt = db2_prepare($conn, $file_sql);
            if (!$file_stmt) {
                // Se fallisce, rimuovi il file fisico
                unlink($destination_path);
                throw new Exception("Errore preparazione query allegato '$original_name': " . db2_stmt_errormsg($conn));
            }

            // PHP 5 compatible array creation
            $file_params = array(
                $num_ticket,
                $original_name,
                $unique_filename,
                strtoupper($extension),
                $file_size,
                $relative_path,
                $current_user['username']
            );

            if (!db2_execute($file_stmt, $file_params)) {
                // Se fallisce, rimuovi il file fisico
                unlink($destination_path);
                $db_error = db2_stmt_errormsg($file_stmt);
                error_log("UPLOAD FILE DB ERROR: " . $db_error);
                throw new Exception("Errore inserimento allegato '$original_name': " . $db_error);
            }

            $files_uploaded++;
            $total_size += $file_size;
            // error_log("UPLOAD: File $original_name inserito nel DB");
        }

        // Verifica che almeno un file sia stato caricato
        if ($files_uploaded === 0) {
            throw new Exception('Nessun file valido caricato');
        }

        // Commit transazione
        db2_commit($conn);
        db2_autocommit($conn, TRUE);

        // error_log("UPLOAD: Transazione completata - Ticket: $num_ticket, Files: $files_uploaded, Cartellino recuperato: $num_cartellino");

        // Log attivitÓ
        logActivity('TICKET_UPLOAD', 
                   "Ticket: $num_ticket, Cartellino: $num_cartellino (auto), File: $files_uploaded, Size: " . 
                   round($total_size/1024/1024, 2) . "MB");

        // Aggiorna statistiche
        updateDailyStats('upload', $total_size);

        // Risposta di successo - PHP 5 compatible
        $response_data = array(
            'ticket_id' => $num_ticket,
            'cartellino_recuperato' => $num_cartellino,
            'files_count' => $files_uploaded,
            'total_size' => $total_size,
            'data_source' => $use_fallback ? 'fallback' : 'sql_query',
            'warning' => $use_fallback ? 'Dati non trovati nel sistema principale' : null
        );
        
        if ($use_fallback) {
            $message = "Ticket $num_ticket caricato con successo ($files_uploaded file). ATTENZIONE: Dati produttore/ispettore non trovati nel sistema - verificare e aggiornare manualmente.";
        } else {
            $message = "Ticket $num_ticket caricato con successo ($files_uploaded file). Cartellino $num_cartellino recuperato automaticamente.";
        }
        
        safeJsonResponse(true, $message, $response_data);

    } catch (Exception $e) {
        // Rollback in caso di errore
        db2_rollback($conn);
        db2_autocommit($conn, TRUE);

        // Log errore dettagliato
        error_log('UPLOAD EXCEPTION: ' . $e->getMessage());
        error_log('UPLOAD TRACE: ' . $e->getTraceAsString());

        db2_close($conn);
        
        // Assicura che il messaggio di errore sia UTF-8 valido
        $error_message = cleanUtf8String($e->getMessage());
        safeJsonResponse(false, $error_message);
    }

    db2_close($conn);

} catch (Exception $e) {
    if (isset($conn)) {
        db2_close($conn);
    }

    // Log errore generale
    error_log('UPLOAD GENERAL ERROR: ' . $e->getMessage());
    error_log('UPLOAD GENERAL TRACE: ' . $e->getTraceAsString());

    safeJsonResponse(false, 'Errore interno del sistema: ' . $e->getMessage());
}

/**
 * Validazione data per PHP 5
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

?>