<?php
/**
 * TICKET_MANAGEMENT.PHP - Gestione avanzata ticket - VERSIONE CORRETTA
 * Funzionalità: Aggiungi file, Cancella file, Cancella ticket
 * Sistema Gestione Ticket Post-Vendita - PHP 5.2 Compatible
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Funzione sicura per output JSON
function safeJsonResponse($success, $message, $data = null) {
    if (ob_get_level()) {
        ob_clean();
    }
    $response = array('success' => $success);
    if ($success) {
        $response['message'] = $message;
        if ($data && is_array($data)) {
            $response = array_merge($response, $data);
        }
    } else {
        $response['error'] = $message;
    }
    echo json_encode($response);
    exit;
}

try {
    // Verifica autenticazione
    requireAuth();
    
    // Verifica metodo
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        safeJsonResponse(false, 'Metodo non consentito');
    }
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'add_files':
            handleAddFiles();
            break;
        case 'delete_file':
            handleDeleteFile();
            break;
        case 'delete_ticket':
            handleDeleteTicket();
            break;
        default:
            safeJsonResponse(false, 'Azione non riconosciuta: ' . $action);
            break;
    }
    
} catch (Exception $e) {
    error_log('TICKET_MANAGEMENT ERROR: ' . $e->getMessage());
    safeJsonResponse(false, 'Errore interno del sistema');
}

/**
 * Gestisce cancellazione completa di un ticket con tutti gli allegati
 */
function handleDeleteTicket() {
    $ticket_id = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
    
    if ($ticket_id <= 0) {
        safeJsonResponse(false, 'ID ticket non valido');
    }
    
    $current_user = getCurrentUser();
    
    // Verifica permessi CORRETTI
    if ($current_user['livello'] === 'ADMIN') {
        // ADMIN può cancellare qualsiasi ticket
        error_log("DELETE_TICKET: ADMIN {$current_user['username']} cancella ticket $ticket_id");
    } elseif ($current_user['livello'] === 'USER') {
        // USER può cancellare solo i propri ticket
        $ticket_owner = getTicketOwner($ticket_id);
        if ($ticket_owner !== $current_user['username']) {
            error_log("DELETE_TICKET: USER {$current_user['username']} tentativo non autorizzato su ticket $ticket_id (owner: $ticket_owner)");
            safeJsonResponse(false, 'Puoi cancellare solo i tuoi ticket');
        }
        error_log("DELETE_TICKET: USER {$current_user['username']} cancella proprio ticket $ticket_id");
    } else {
        // BACKOFFICE e CQ non possono cancellare ticket
        error_log("DELETE_TICKET: Utente {$current_user['username']} livello {$current_user['livello']} non autorizzato");
        safeJsonResponse(false, 'Non hai i permessi per cancellare ticket');
    }
    
    $conn = getDb2Connection();
    if (!$conn) {
        safeJsonResponse(false, 'Errore connessione database');
    }
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    // Verifica che il ticket esista
    $check_sql = "SELECT NUM_TICKET, RAG_SOC_PR, USERNAME_INS FROM WTICKET WHERE NUM_TICKET = ?";
    $check_stmt = db2_prepare($conn, $check_sql);
    if (!db2_execute($check_stmt, array($ticket_id))) {
        db2_close($conn);
        safeJsonResponse(false, 'Errore verifica ticket');
    }
    
    $ticket_info = db2_fetch_assoc($check_stmt);
    if (!$ticket_info) {
        db2_close($conn);
        safeJsonResponse(false, 'Ticket non trovato');
    }
    
    // Doppia verifica per USER
    if ($current_user['livello'] === 'USER') {
        if (trim($ticket_info['USERNAME_INS']) !== $current_user['username']) {
            db2_close($conn);
            safeJsonResponse(false, 'Questo ticket non ti appartiene');
        }
    }
    
    // Recupera tutti i file allegati
    $files_sql = "SELECT ID_ALLEGATO, PATH_FILE, NOME_ORIG FROM WALLEGATI WHERE NUM_TICKET = ?";
    $files_stmt = db2_prepare($conn, $files_sql);
    $files_to_delete = array();
    
    if (db2_execute($files_stmt, array($ticket_id))) {
        while ($file_row = db2_fetch_assoc($files_stmt)) {
            $files_to_delete[] = $file_row;
        }
    }
    
    // Inizia transazione
    db2_autocommit($conn, FALSE);
    
    try {
        // Cancella tutti gli allegati dal database
        $delete_files_sql = "DELETE FROM WALLEGATI WHERE NUM_TICKET = ?";
        $delete_files_stmt = db2_prepare($conn, $delete_files_sql);
        if (!db2_execute($delete_files_stmt, array($ticket_id))) {
            throw new Exception('Errore cancellazione allegati dal database');
        }
        
        // Cancella il ticket principale
        $delete_ticket_sql = "DELETE FROM WTICKET WHERE NUM_TICKET = ?";
        $delete_ticket_stmt = db2_prepare($conn, $delete_ticket_sql);
        if (!db2_execute($delete_ticket_stmt, array($ticket_id))) {
            throw new Exception('Errore cancellazione ticket dal database');
        }
        
        // Commit transazione database
        db2_commit($conn);
        db2_autocommit($conn, TRUE);
        
        // Cancella file fisici (dopo il commit per sicurezza)
        $files_deleted = 0;
        foreach ($files_to_delete as $file) {
            $file_path = BASE_UPLOAD_DIR . trim($file['PATH_FILE']);
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    $files_deleted++;
                } else {
                    error_log("Warning: Impossibile cancellare file fisico: $file_path");
                }
            }
        }
        
        // Log attività
        logActivity('DELETE_TICKET', "Ticket: $ticket_id, Produttore: " . trim($ticket_info['RAG_SOC_PR']) . ", Files cancellati: " . count($files_to_delete));
        
        db2_close($conn);
        
        safeJsonResponse(true, "Ticket #$ticket_id cancellato completamente", array(
            'ticket_id' => $ticket_id,
            'files_deleted' => count($files_to_delete),
            'physical_files_deleted' => $files_deleted
        ));
        
    } catch (Exception $e) {
        db2_rollback($conn);
        db2_autocommit($conn, TRUE);
        db2_close($conn);
        throw $e;
    }
}

/**
 * Gestisce cancellazione di un singolo file
 */
function handleDeleteFile() {
    $file_id = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    
    if ($file_id <= 0) {
        safeJsonResponse(false, 'ID file non valido');
    }
    
    $conn = getDb2Connection();
    if (!$conn) {
        safeJsonResponse(false, 'Errore connessione database');
    }
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    // Recupera informazioni file e ticket
    $sql = "SELECT a.*, t.USERNAME_INS 
            FROM WALLEGATI a 
            INNER JOIN WTICKET t ON a.NUM_TICKET = t.NUM_TICKET 
            WHERE a.ID_ALLEGATO = ?";
    
    $stmt = db2_prepare($conn, $sql);
    if (!db2_execute($stmt, array($file_id))) {
        db2_close($conn);
        safeJsonResponse(false, 'Errore recupero informazioni file');
    }
    
    $file_info = db2_fetch_assoc($stmt);
    if (!$file_info) {
        db2_close($conn);
        safeJsonResponse(false, 'File non trovato');
    }
    
    $ticket_id = (int)$file_info['NUM_TICKET'];
    $current_user = getCurrentUser();
    
    // Verifica permessi per cancellazione file
    if (!hasPermission('delete_files')) {
        db2_close($conn);
        safeJsonResponse(false, 'Non hai i permessi per cancellare file');
    }
    
    // Verifica aggiuntiva per USER: può cancellare solo file dei propri ticket
    if ($current_user['livello'] === 'USER') {
        if (trim($file_info['USERNAME_INS']) !== $current_user['username']) {
            db2_close($conn);
            safeJsonResponse(false, 'Puoi cancellare solo file dei tuoi ticket');
        }
    }
    
    // Inizia transazione
    db2_autocommit($conn, FALSE);
    
    try {
        // Cancella record dal database
        $delete_sql = "DELETE FROM WALLEGATI WHERE ID_ALLEGATO = ?";
        $delete_stmt = db2_prepare($conn, $delete_sql);
        
        if (!db2_execute($delete_stmt, array($file_id))) {
            throw new Exception('Errore cancellazione record file dal database');
        }
        
        // Cancella file fisico
        $file_path = BASE_UPLOAD_DIR . trim($file_info['PATH_FILE']);
        if (file_exists($file_path)) {
            if (!unlink($file_path)) {
                error_log("Warning: Impossibile cancellare file fisico: $file_path");
            }
        }
        
        // Commit transazione
        db2_commit($conn);
        db2_autocommit($conn, TRUE);
        
        // Log attività
        logActivity('DELETE_FILE', "File ID: $file_id, Ticket: $ticket_id, File: " . trim($file_info['NOME_ORIG']));
        
        db2_close($conn);
        
        safeJsonResponse(true, "File '" . trim($file_info['NOME_ORIG']) . "' cancellato con successo", array(
            'file_id' => $file_id,
            'ticket_id' => $ticket_id
        ));
        
    } catch (Exception $e) {
        db2_rollback($conn);
        db2_autocommit($conn, TRUE);
        db2_close($conn);
        throw $e;
    }
}

/**
 * Gestisce aggiunta di nuovi file a ticket esistente
 */
function handleAddFiles() {
    // Implementazione per aggiungere file (opzionale per ora)
    safeJsonResponse(false, 'Funzione aggiungi file non ancora implementata');
}
?>