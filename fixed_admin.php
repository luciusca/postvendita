<?php
/**
 * FIXED_ADMIN.PHP - Admin corretto per PHP 5 - VERSIONE COMPATIBILE PHP 5
 */

// Configurazione errori per debug
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers obbligatori
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Funzione sicura per output JSON - PHP 5 compatible
function safeJsonOutput($data) {
    // Pulisce qualsiasi output precedente
    if (ob_get_level()) {
        ob_clean();
    }
    echo json_encode($data);
    exit;
}

try {
    // Include config
    if (!file_exists('config.php')) {
        safeJsonOutput(array(
            'success' => false,
            'error' => 'File config.php non trovato'
        ));
    }
    
    require_once 'config.php';
    
    // Verifica funzioni base
    if (!function_exists('isValidSession')) {
        safeJsonOutput(array(
            'success' => false,
            'error' => 'Funzione isValidSession non trovata'
        ));
    }
    
    // Verifica sessione
    if (!isValidSession()) {
        safeJsonOutput(array(
            'success' => false,
            'error' => 'Sessione scaduta',
            'redirect' => 'login.html'
        ));
    }
    
    $current_user = getCurrentUser();
    if (!$current_user || $current_user['livello'] !== 'ADMIN') {
        safeJsonOutput(array(
            'success' => false,
            'error' => 'Accesso negato - permessi amministratore richiesti'
        ));
    }
    
    // Connessione DB
    $conn = getDb2Connection();
    if (!$conn) {
        safeJsonOutput(array(
            'success' => false,
            'error' => 'Errore connessione database'
        ));
    }
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    if (!db2_exec($conn, $set_schema_sql)) {
        db2_close($conn);
        safeJsonOutput(array(
            'success' => false,
            'error' => 'Errore impostazione schema database'
        ));
    }
    
    // PHP 5 compatible - usa isset invece di null coalescing
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    
    switch ($action) {
        case 'users':
            handleUsers($conn);
            break;
            
        case 'system_stats':
            handleSystemStats($conn);
            break;
            
        case 'activity_log':
            handleActivityLog($conn);
            break;
            
        default:
            db2_close($conn);
            safeJsonOutput(array(
                'success' => false,
                'error' => 'Azione non riconosciuta: ' . $action
            ));
            break;
    }
    
} catch (Exception $e) {
    error_log('FIXED_ADMIN EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    if (isset($conn)) {
        db2_close($conn);
    }
    
    safeJsonOutput(array(
        'success' => false,
        'error' => 'Errore interno del sistema',
        'details' => $e->getMessage()
    ));
}

/**
 * Gestisce richiesta lista utenti - PHP 5 compatible
 */
function handleUsers($conn) {
    try {
        $sql = "SELECT USERNAME, NOME_COMPL, LIVELLO, ATTIVO, DATA_CREAZ, ULTIMO_ACC
                FROM WUTENTI
                ORDER BY USERNAME";
        
        $stmt = db2_exec($conn, $sql);
        if (!$stmt) {
            $error = db2_conn_errormsg($conn);
            error_log('USERS SQL ERROR: ' . $error);
            db2_close($conn);
            safeJsonOutput(array(
                'success' => false,
                'error' => 'Errore query utenti: ' . $error
            ));
        }
        
        $users = array();
        while ($row = db2_fetch_assoc($stmt)) {
            if ($row) {
                // PHP 5 compatible array creation
                $user = array(
                    'USERNAME' => trim($row['USERNAME']),
                    'NOME_COMPL' => trim($row['NOME_COMPL']),
                    'LIVELLO' => trim($row['LIVELLO']),
                    'ATTIVO' => trim($row['ATTIVO']),
                    'DATA_CREAZ' => $row['DATA_CREAZ'],
                    'ULTIMO_ACC' => $row['ULTIMO_ACC']
                );
                array_push($users, $user);
            }
        }
        
        db2_close($conn);
        
        safeJsonOutput(array(
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ));
        
    } catch (Exception $e) {
        error_log('USERS EXCEPTION: ' . $e->getMessage());
        if (isset($conn)) {
            db2_close($conn);
        }
        safeJsonOutput(array(
            'success' => false,
            'error' => 'Errore recupero utenti: ' . $e->getMessage()
        ));
    }
}

/**
 * Gestisce richiesta statistiche sistema - PHP 5 compatible
 */
function handleSystemStats($conn) {
    try {
        // PHP 5 compatible array initialization
        $stats = array(
            'total_users' => 0,
            'active_users' => 0,
            'total_tickets' => 0,
            'total_files' => 0,
            'total_size_mb' => 0,
            'active_sessions' => 0
        );
        
        // Query utenti totali
        try {
            $sql = "SELECT COUNT(*) as CNT FROM WUTENTI";
            $stmt = db2_exec($conn, $sql);
            if ($stmt) {
                $row = db2_fetch_assoc($stmt);
                if ($row) {
                    $stats['total_users'] = (int)$row['CNT'];
                }
            }
        } catch (Exception $e) {
            error_log('STATS total_users error: ' . $e->getMessage());
        }
        
        // Query utenti attivi
        try {
            $sql = "SELECT COUNT(*) as CNT FROM WUTENTI WHERE ATTIVO = 'S'";
            $stmt = db2_exec($conn, $sql);
            if ($stmt) {
                $row = db2_fetch_assoc($stmt);
                if ($row) {
                    $stats['active_users'] = (int)$row['CNT'];
                }
            }
        } catch (Exception $e) {
            error_log('STATS active_users error: ' . $e->getMessage());
        }
        
        // Query ticket totali
        try {
            $sql = "SELECT COUNT(*) as CNT FROM WTICKET";
            $stmt = db2_exec($conn, $sql);
            if ($stmt) {
                $row = db2_fetch_assoc($stmt);
                if ($row) {
                    $stats['total_tickets'] = (int)$row['CNT'];
                }
            }
        } catch (Exception $e) {
            error_log('STATS total_tickets error: ' . $e->getMessage());
        }
        
        // Query file totali
        try {
            $sql = "SELECT COUNT(*) as CNT FROM WALLEGATI";
            $stmt = db2_exec($conn, $sql);
            if ($stmt) {
                $row = db2_fetch_assoc($stmt);
                if ($row) {
                    $stats['total_files'] = (int)$row['CNT'];
                }
            }
        } catch (Exception $e) {
            error_log('STATS total_files error: ' . $e->getMessage());
        }
        
        // Query sessioni attive
        try {
            $sql = "SELECT COUNT(*) as CNT FROM WSESSIONI WHERE EXPIRES_AT > CURRENT_TIMESTAMP";
            $stmt = db2_exec($conn, $sql);
            if ($stmt) {
                $row = db2_fetch_assoc($stmt);
                if ($row) {
                    $stats['active_sessions'] = (int)$row['CNT'];
                }
            }
        } catch (Exception $e) {
            error_log('STATS active_sessions error: ' . $e->getMessage());
        }
        
        // Query dimensione totale (pi¨ sicura) - PHP 5 compatible
        try {
            $sql = "SELECT COALESCE(SUM(DIMENSIONE), 0) as TOTAL_BYTES FROM WALLEGATI";
            $stmt = db2_exec($conn, $sql);
            if ($stmt) {
                $row = db2_fetch_assoc($stmt);
                if ($row) {
                    $total_bytes = (float)$row['TOTAL_BYTES'];
                    $stats['total_size_mb'] = round($total_bytes / 1024 / 1024, 2);
                }
            }
        } catch (Exception $e) {
            error_log('STATS total_size error: ' . $e->getMessage());
        }
        
        db2_close($conn);
        
        safeJsonOutput(array(
            'success' => true,
            'stats' => $stats
        ));
        
    } catch (Exception $e) {
        error_log('SYSTEM_STATS EXCEPTION: ' . $e->getMessage());
        if (isset($conn)) {
            db2_close($conn);
        }
        safeJsonOutput(array(
            'success' => false,
            'error' => 'Errore recupero statistiche: ' . $e->getMessage()
        ));
    }
}

/**
 * Gestisce richiesta log attivitÓ - PHP 5 compatible
 */
function handleActivityLog($conn) {
    try {
        // PHP 5 compatible parameter handling
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        $per_page = min(50, max(5, $per_page)); // Limita tra 5 e 50
        
        $sql = "SELECT ID_LOG, AZIONE, DETTAGLI, USERNAME, IP_ADDRESS, DATA_ORA
                FROM WLOGATT
                ORDER BY DATA_ORA DESC
                FETCH FIRST $per_page ROWS ONLY";
        
        $stmt = db2_exec($conn, $sql);
        if (!$stmt) {
            $error = db2_conn_errormsg($conn);
            error_log('ACTIVITY_LOG SQL ERROR: ' . $error);
            db2_close($conn);
            safeJsonOutput(array(
                'success' => false,
                'error' => 'Errore query log: ' . $error
            ));
        }
        
        $log_entries = array();
        while ($row = db2_fetch_assoc($stmt)) {
            if ($row) {
                // PHP 5 compatible array creation
                $entry = array(
                    'ID_LOG' => (int)$row['ID_LOG'],
                    'AZIONE' => trim($row['AZIONE']),
                    'DETTAGLI' => trim($row['DETTAGLI']),
                    'USERNAME' => trim($row['USERNAME']),
                    'IP_ADDRESS' => trim($row['IP_ADDRESS']),
                    'DATA_ORA' => $row['DATA_ORA']
                );
                array_push($log_entries, $entry);
            }
        }
        
        db2_close($conn);
        
        safeJsonOutput(array(
            'success' => true,
            'log_entries' => $log_entries,
            'total_count' => count($log_entries),
            'page' => 1,
            'per_page' => $per_page,
            'total_pages' => 1
        ));
        
    } catch (Exception $e) {
        error_log('ACTIVITY_LOG EXCEPTION: ' . $e->getMessage());
        if (isset($conn)) {
            db2_close($conn);
        }
        safeJsonOutput(array(
            'success' => false,
            'error' => 'Errore recupero log: ' . $e->getMessage()
        ));
    }
}

?>