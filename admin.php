<?php
/**
 * ADMIN.PHP - Versione finale basata sui test funzionanti
 * Sistema Gestione Ticket Post-Vendita
 * COMPATIBILE PHP 5.2
 */

// Headers prima di tutto
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Configurazione errori
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// IMPORTANTE: init_firefox PRIMA di config
require_once 'init_firefox.php';
require_once 'config.php';

// Funzione safe output
function safeJsonOutput($data) {
    if (ob_get_level()) {
        ob_clean();
    }
    echo json_encode($data);
    exit;
}

try {
    // Avvia sessione USANDO LA FUNZIONE CORRETTA
    startSessionSafe();
    
    // Verifica autenticazione manualmente - NON chiamare isValidSession()!
    if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        safeJsonOutput(array('success' => false, 'error' => 'Autenticazione richiesta', 'redirect' => 'login.html'));
    }
    
    // Verifica timeout
    if (isset($_SESSION['expires_at']) && $_SESSION['expires_at'] < time()) {
        safeJsonOutput(array('success' => false, 'error' => 'Sessione scaduta', 'redirect' => 'login.html'));
    }
    
    // Rinnova timeout
    $_SESSION['expires_at'] = time() + SESSION_TIMEOUT;
    
    // Ottieni dati utente DIRETTAMENTE dalla sessione
    $current_user = array(
        'username' => $_SESSION['username'],
        'nome_completo' => $_SESSION['nome_completo'],
        'livello' => $_SESSION['livello'],
        'poloprod' => isset($_SESSION['poloprod']) ? $_SESSION['poloprod'] : ''
    );
    
    // Verifica che sia ADMIN
    if ($current_user['livello'] !== 'ADMIN') {
        safeJsonOutput(array('success' => false, 'error' => 'Permessi insufficienti - solo ADMIN'));
    }
    
    // DB Connection
    $conn = getDb2Connection();
    if (!$conn) {
        safeJsonOutput(array('success' => false, 'error' => 'Connessione DB fallita'));
    }
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    if (!db2_exec($conn, $set_schema_sql)) {
        $error = db2_conn_errormsg($conn);
        db2_close($conn);
        safeJsonOutput(array('success' => false, 'error' => 'Schema error: ' . $error));
    }
    
    // Action
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    
    switch ($action) {
        case 'users':
            handleUsers($conn);
            break;
            
        case 'user_detail':
            handleUserDetail($conn);
            break;
            
        case 'create':
        case 'edit':
            handleUserSave($conn, $action);
            break;
            
        case 'system_stats':
            handleSystemStats($conn);
            break;
            
        case 'activity_log':
            handleActivityLog($conn);
            break;
            
        case 'change_password':
            handleChangePassword($conn, $current_user);
            break;
            
        default:
            db2_close($conn);
            safeJsonOutput(array('success' => false, 'error' => 'Azione non riconosciuta: ' . $action));
            break;
    }
    
    db2_close($conn);
    
} catch (Exception $e) {
    error_log('ADMIN ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    if (isset($conn)) {
        db2_close($conn);
    }
    
    safeJsonOutput(array(
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage()
    ));
}
/**
 * Gestisce lista utenti - TESTATO E FUNZIONANTE 
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
            throw new Exception('Errore query utenti: ' . $error);
        }
        
        $users = array();
        while ($row = db2_fetch_assoc($stmt)) {
            if ($row) {
                $users[] = array(
                    'USERNAME' => trim($row['USERNAME']),
                    'NOME_COMPL' => trim($row['NOME_COMPL']),
                    'LIVELLO' => trim($row['LIVELLO']),
                    'ATTIVO' => trim($row['ATTIVO']),
                    'DATA_CREAZ' => $row['DATA_CREAZ'],
                    'ULTIMO_ACC' => $row['ULTIMO_ACC']
                );
            }
        }
        
        logActivity('ADMIN_VIEW_USERS', 'Visualizzazione lista utenti');
        
        safeJsonOutput(array(
            'success' => true,
            'users' => $users
        ));
        
    } catch (Exception $e) {
        throw new Exception('Errore users: ' . $e->getMessage());
    }
}

/**
 * Gestisce dettaglio utente singolo
 */
function handleUserDetail($conn) {
    $username = isset($_GET['username']) ? trim($_GET['username']) : '';
    
    if (empty($username)) {
        safeJsonOutput(array('success' => false, 'error' => 'Username richiesto'));
    }
    
    try {
        $sql = "SELECT * FROM WUTENTI WHERE USERNAME = ?";
        $stmt = db2_prepare($conn, $sql);
        
        if (!db2_execute($stmt, array($username))) {
            throw new Exception('Errore query dettaglio utente');
        }
        
        $user = db2_fetch_assoc($stmt);
        if (!$user) {
            safeJsonOutput(array('success' => false, 'error' => 'Utente non trovato'));
        }
        
        // Rimuovi password per sicurezza
        unset($user['PASSWORD']);
        
        // Trim campi stringa
        foreach ($user as $key => $value) {
            if (is_string($value)) {
                $user[$key] = trim($value);
            }
        }
        
        logActivity('ADMIN_VIEW_USER', "Visualizzazione dettagli utente: $username");
        
        safeJsonOutput(array(
            'success' => true,
            'user' => $user
        ));
        
    } catch (Exception $e) {
        throw new Exception('Errore dettaglio utente: ' . $e->getMessage());
    }
}

/**
 * Gestisce salvataggio/creazione utente
 * VERSIONE COMPATIBILE PHP 5.2 (senza finally)
 */
function handleUserSave($conn, $action) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        safeJsonOutput(array('success' => false, 'error' => 'Metodo non consentito'));
    }
    
    // Recupera dati POST
    $username = isset($_POST['username']) ? trim(strtoupper($_POST['username'])) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $nome_completo = isset($_POST['nome_completo']) ? trim($_POST['nome_completo']) : '';
    $livello = isset($_POST['livello']) ? trim($_POST['livello']) : '';
    $attivo = isset($_POST['attivo']) ? trim($_POST['attivo']) : 'S';
    $original_username = isset($_POST['original_username']) ? trim($_POST['original_username']) : '';
    
    // Validazione
    if (empty($username) || strlen($username) > 10) {
        safeJsonOutput(array('success' => false, 'error' => 'Username non valido (max 10 caratteri)'));
    }
    
    if ($action === 'create' && empty($password)) {
        safeJsonOutput(array('success' => false, 'error' => 'Password richiesta per nuovo utente'));
    }
    
    if (!empty($password) && strlen($password) > 50) {
        safeJsonOutput(array('success' => false, 'error' => 'Password troppo lunga (max 50 caratteri)'));
    }
    
    if (empty($nome_completo) || strlen($nome_completo) > 50) {
        safeJsonOutput(array('success' => false, 'error' => 'Nome completo non valido (max 50 caratteri)'));
    }
    
    if (!in_array($livello, array('USER', 'BACKOFFICE', 'ADMIN', 'CQ'))) {
        safeJsonOutput(array('success' => false, 'error' => 'Livello non valido'));
    }
    
    if (!in_array($attivo, array('S', 'N'))) {
        $attivo = 'S';
    }
    
    try {
        db2_autocommit($conn, FALSE);
        
        if ($action === 'create') {
            // Verifica username non esistente
            $check_sql = "SELECT USERNAME FROM WUTENTI WHERE USERNAME = ?";
            $check_stmt = db2_prepare($conn, $check_sql);
            
            if (db2_execute($check_stmt, array($username))) {
                $existing = db2_fetch_assoc($check_stmt);
                if ($existing) {
                    db2_rollback($conn);
                    db2_autocommit($conn, TRUE); // Ripristina autocommit
                    safeJsonOutput(array('success' => false, 'error' => 'Username già esistente'));
                }
            }
            
            // Inserisce nuovo utente
            $insert_sql = "INSERT INTO WUTENTI 
                          (USERNAME, PASSWORD, NOME_COMPL, LIVELLO, ATTIVO) 
                          VALUES (?, ?, ?, ?, ?)";
            
            $insert_stmt = db2_prepare($conn, $insert_sql);
            $params = array($username, $password, $nome_completo, $livello, $attivo);
            
            if (!db2_execute($insert_stmt, $params)) {
                db2_rollback($conn);
                db2_autocommit($conn, TRUE); // Ripristina autocommit
                throw new Exception('Errore inserimento utente');
            }
            
            logActivity('ADMIN_CREATE_USER', "Creato utente: $username, Livello: $livello");
            
        } else { // edit
            if (empty($original_username)) {
                db2_rollback($conn);
                db2_autocommit($conn, TRUE); // Ripristina autocommit
                safeJsonOutput(array('success' => false, 'error' => 'Username originale richiesto per modifica'));
            }
            
            // Costruisce query di aggiornamento
            if (!empty($password)) {
                $update_sql = "UPDATE WUTENTI 
                              SET USERNAME = ?, PASSWORD = ?, NOME_COMPL = ?, LIVELLO = ?, ATTIVO = ? 
                              WHERE USERNAME = ?";
                $params = array($username, $password, $nome_completo, $livello, $attivo, $original_username);
            } else {
                $update_sql = "UPDATE WUTENTI 
                              SET USERNAME = ?, NOME_COMPL = ?, LIVELLO = ?, ATTIVO = ? 
                              WHERE USERNAME = ?";
                $params = array($username, $nome_completo, $livello, $attivo, $original_username);
            }
            
            $update_stmt = db2_prepare($conn, $update_sql);
            
            if (!db2_execute($update_stmt, $params)) {
                db2_rollback($conn);
                db2_autocommit($conn, TRUE); // Ripristina autocommit
                throw new Exception('Errore aggiornamento utente');
            }
            
            logActivity('ADMIN_EDIT_USER', "Modificato utente: $original_username -> $username, Livello: $livello");
        }
        
        db2_commit($conn);
        db2_autocommit($conn, TRUE); // Ripristina autocommit
        
        safeJsonOutput(array(
            'success' => true,
            'message' => $action === 'create' ? 'Utente creato con successo' : 'Utente aggiornato con successo',
            'username' => $username
        ));
        
    } catch (Exception $e) {
        db2_rollback($conn);
        db2_autocommit($conn, TRUE); // Ripristina autocommit anche in caso di errore
        throw new Exception('Errore salvataggio utente: ' . $e->getMessage());
    }
}

/**
 * Gestisce statistiche sistema - CON QUERY SEPARATE TESTATE
 */
function handleSystemStats($conn) {
    try {
        $stats = array(
            'total_users' => 0,
            'active_users' => 0,
            'total_tickets' => 0,
            'total_files' => 0,
            'total_size_mb' => 0,
            'active_sessions' => 0
        );
        
        // Query separate - come nel test che funziona
        $queries = array(
            'total_users' => "SELECT COUNT(*) as CNT FROM WUTENTI",
            'active_users' => "SELECT COUNT(*) as CNT FROM WUTENTI WHERE ATTIVO = 'S'",
            'total_tickets' => "SELECT COUNT(*) as CNT FROM WTICKET",
            'total_files' => "SELECT COUNT(*) as CNT FROM WALLEGATI",
            'active_sessions' => "SELECT COUNT(*) as CNT FROM WSESSIONI WHERE EXPIRES_AT > CURRENT_TIMESTAMP"
        );
        
        foreach ($queries as $key => $sql) {
            try {
                $stmt = db2_exec($conn, $sql);
                if ($stmt) {
                    $row = db2_fetch_assoc($stmt);
                    if ($row) {
                        $stats[$key] = (int)$row['CNT'];
                    }
                } else {
                    $error = db2_conn_errormsg($conn);
                    error_log("STATS ERROR ($key): " . $error);
                }
            } catch (Exception $e) {
                error_log("STATS EXCEPTION ($key): " . $e->getMessage());
            }
        }
        
        // Query dimensione separata
        try {
            $sql = "SELECT COALESCE(SUM(DIMENSIONE), 0) / 1024 / 1024 as SIZE_MB FROM WALLEGATI";
            $stmt = db2_exec($conn, $sql);
            if ($stmt) {
                $row = db2_fetch_assoc($stmt);
                if ($row) {
                    $stats['total_size_mb'] = round((float)$row['SIZE_MB'], 2);
                }
            }
        } catch (Exception $e) {
            error_log('Error total_size_mb: ' . $e->getMessage());
        }
        
        logActivity('ADMIN_VIEW_STATS', 'Visualizzazione statistiche sistema');
        
        safeJsonOutput(array(
            'success' => true,
            'stats' => $stats
        ));
        
    } catch (Exception $e) {
        throw new Exception('Errore system_stats: ' . $e->getMessage());
    }
}

/**
 * Gestisce log attività - TESTATO DB FUNZIONANTE
 */
function handleActivityLog($conn) {
    try {
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        $per_page = min(50, max(5, $per_page));
        
        $sql = "SELECT ID_LOG, AZIONE, DETTAGLI, USERNAME, IP_ADDRESS, DATA_ORA 
                FROM WLOGATT 
                ORDER BY DATA_ORA DESC 
                FETCH FIRST $per_page ROWS ONLY";
        
        $stmt = db2_exec($conn, $sql);
        if (!$stmt) {
            $error = db2_conn_errormsg($conn);
            error_log('ACTIVITY_LOG SQL ERROR: ' . $error);
            throw new Exception('Errore query log: ' . $error);
        }
        
        $log_entries = array();
        while ($row = db2_fetch_assoc($stmt)) {
            if ($row) {
                $log_entries[] = array(
                    'ID_LOG' => (int)$row['ID_LOG'],
                    'AZIONE' => trim($row['AZIONE']),
                    'DETTAGLI' => trim($row['DETTAGLI']),
                    'USERNAME' => trim($row['USERNAME']),
                    'IP_ADDRESS' => trim($row['IP_ADDRESS']),
                    'DATA_ORA' => $row['DATA_ORA']
                );
            }
        }
        
        logActivity('ADMIN_VIEW_LOG', "Visualizzazione log attività");
        
        safeJsonOutput(array(
            'success' => true,
            'log_entries' => $log_entries,
            'total_count' => count($log_entries),
            'page' => 1,
            'per_page' => $per_page,
            'total_pages' => 1
        ));
        
    } catch (Exception $e) {
        throw new Exception('Errore activity_log: ' . $e->getMessage());
    }
}

/**
 * Gestisce cambio password
 */
function handleChangePassword($conn, $current_user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        safeJsonOutput(array('success' => false, 'error' => 'Metodo non consentito'));
    }
    
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    
    if (empty($current_password) || empty($new_password)) {
        safeJsonOutput(array('success' => false, 'error' => 'Password corrente e nuova richieste'));
    }
    
    if (strlen($new_password) < 6) {
        safeJsonOutput(array('success' => false, 'error' => 'Nuova password troppo corta (min 6 caratteri)'));
    }
    
    try {
        // USA $current_user passato invece di chiamare getCurrentUser()
        $username = $current_user['username'];
        
        // Verifica password corrente
        $check_sql = "SELECT PASSWORD FROM WUTENTI WHERE USERNAME = ?";
        $check_stmt = db2_prepare($conn, $check_sql);
        
        if (!db2_execute($check_stmt, array($username))) {
            throw new Exception('Errore verifica utente');
        }
        
        $user_data = db2_fetch_assoc($check_stmt);
        if (!$user_data || trim($user_data['PASSWORD']) !== $current_password) {
            safeJsonOutput(array('success' => false, 'error' => 'Password corrente non corretta'));
        }
        
        // Aggiorna password
        $update_sql = "UPDATE WUTENTI SET PASSWORD = ? WHERE USERNAME = ?";
        $update_stmt = db2_prepare($conn, $update_sql);
        
        if (!db2_execute($update_stmt, array($new_password, $username))) {
            throw new Exception('Errore aggiornamento password');
        }
        
        // Log attività senza chiamare funzioni che riaprono la sessione
        $log_conn = getDb2Connection();
        if ($log_conn) {
            db2_exec($log_conn, "SET SCHEMA " . DB2_LIBRARY);
            $log_sql = "INSERT INTO WLOGATT (AZIONE, DETTAGLI, USERNAME, IP_ADDRESS, DATA_ORA) 
                        VALUES ('PASSWORD_CHANGE', ?, ?, ?, CURRENT_TIMESTAMP)";
            $log_stmt = db2_prepare($log_conn, $log_sql);
            if ($log_stmt) {
                $ip_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
                db2_execute($log_stmt, array("Cambio password per utente: $username", $username, $ip_addr));
            }
            db2_close($log_conn);
        }
        
        safeJsonOutput(array(
            'success' => true,
            'message' => 'Password cambiata con successo'
        ));
        
    } catch (Exception $e) {
        throw new Exception('Errore cambio password: ' . $e->getMessage());
    }
}
?>