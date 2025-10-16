<?php

// IMPORTANTE: Configurazione PHP prima di tutto
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
ini_set('max_execution_time', 300);

// CONFIGURAZIONE SESSIONI - DEVE ESSERE PRIMA DI session_start()
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // 0 per HTTP, 1 per HTTPS
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', ''); // Vuoto per dominio corrente
ini_set('session.cookie_lifetime', 0); // Cookie di sessione
ini_set('session.use_strict_mode', 0); // Disabilita per compatibilità

// FIX PER ISERIES - decommenta se necessario
// ini_set('session.save_path', '/tmp');

// FUNZIONI SESSIONE - DEFINITE PRIMA DI TUTTO IL RESTO
/**
 * Avvia sessione in modo sicuro per tutti i browser
 */
function startSessionSafe() {
    // Se sessione già attiva, non fare nulla
    if (session_id() != '') {
        return true;
    }
    
    // Firefox fix: se c'è un cookie PHPSESSID, usalo
    if (isset($_COOKIE['PHPSESSID']) && !empty($_COOKIE['PHPSESSID'])) {
        $session_id = $_COOKIE['PHPSESSID'];
        // Valida formato session ID
        if (preg_match('/^[a-zA-Z0-9,-]{22,40}$/', $session_id)) {
            session_id($session_id);
           // error_log("startSessionSafe - Using cookie session ID: " . $session_id);
        }
    }
    
    // Avvia sessione
    $result = session_start();
    // error_log("startSessionSafe - Session started: " . session_id());
    
    return $result;
}

/**
 * Rileva se è Firefox
 */
function isFirefoxBrowser() {
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return (stripos($user_agent, 'firefox') !== false);
}

// NON avviare sessione qui - verrà avviata dove serve

// Compatibility functions per PHP 5.2/5.4
if (!function_exists('http_response_code')) {
    function http_response_code($code = NULL) {
        if ($code !== NULL) {
            switch ($code) {
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
            }
            
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . ' ' . $code . ' ' . $text);
            $GLOBALS['http_response_code'] = $code;
        } else {
            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        }
        
        return $code;
    }
}

// Array column compatibility
if (!function_exists('array_column')) {
    function array_column($array, $column_key, $index_key = null) {
        $result = array();
        foreach ($array as $arr) {
            if (!is_array($arr)) continue;
            
            if (is_null($index_key)) {
                $result[] = $arr[$column_key];
            } else {
                $result[$arr[$index_key]] = $arr[$column_key];
            }
        }
        return $result;
    }
}

// JSON error compatibility
if (!function_exists('json_last_error')) {
    define('JSON_ERROR_NONE', 0);
    define('JSON_ERROR_DEPTH', 1);
    define('JSON_ERROR_STATE_MISMATCH', 2);
    define('JSON_ERROR_CTRL_CHAR', 3);
    define('JSON_ERROR_SYNTAX', 4);
    define('JSON_ERROR_UTF8', 5);
    
    function json_last_error() {
        return JSON_ERROR_NONE;
    }
}

// Include la nuova funzione di recupero dati
require_once 'ticket_data_retrieval.php';

// Configurazione DB2
define('DB2_USER', 'PERS');
define('DB2_PASS', 'PERS');
define('DB2_HOST', 'S78CCD90');
define('DB2_SYSTEM', '200.2.20.20');
define('DB2_LIBRARY', 'WEBPVEND');

// Directory upload
define('BASE_UPLOAD_DIR', '/qntc/NAS01/Documenti/ticket/');
define('MAX_FILE_SIZE', 20971520); // 20MB in bytes

// Session timeout
define('SESSION_TIMEOUT', 3600);

// Tipi file consentiti
define('ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png,gif,bmp,tif,tiff');

// Helper function per ottenere array extensions
function getAllowedExtensions() {
    return explode(',', ALLOWED_EXTENSIONS);
}

// Connessione DB2
function getDb2Connection() {
    $user = DB2_USER;
    $pass = DB2_PASS;
    $host = DB2_HOST;
    $conn = db2_connect($host, $user, $pass);
    if (!$conn) {
        error_log('Errore DB2: ' . db2_conn_errormsg());
        return false;
    }
    return $conn;
}

// Crea directory upload per anno
function createYearUploadDir($year) {
    $year_dir = BASE_UPLOAD_DIR . $year;
    if (!file_exists($year_dir)) {
        if (!mkdir($year_dir, 0755, true)) {
            error_log("Impossibile creare directory: " . $year_dir);
            return false;
        }
    }
    return $year_dir;
}

// Verifica utente - CORRETTA
function authenticateUser($username, $password) {
    $conn = getDb2Connection();
    if (!$conn) {
        return false;
    }
    
    $set_schema_sql = 'SET SCHEMA ' . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $username_clean = strtoupper(trim($username));
    $username_escaped = str_replace("'", "''", $username_clean);
    
    $sql = "SELECT RTRIM(USERNAME) as USERNAME,
            RTRIM(PASSWORD) as PASSWORD,
            RTRIM(NOME_COMPL) as NOME_COMPLETO,
            RTRIM(LIVELLO) as LIVELLO,
            RTRIM(POLOPROD) as POLOPROD
            FROM WUTENTI
            WHERE RTRIM(USERNAME) = '" . $username_escaped . "' AND RTRIM(ATTIVO) = 'S'";
    
    $stmt = db2_exec($conn, $sql);
    if (!$stmt) {
        db2_close($conn);
        return false;
    }
    
    $user = db2_fetch_assoc($stmt);
    if ($user) {
        // Aggiorna ultimo accesso
        $update_sql = "UPDATE WUTENTI
                       SET ULTIMO_ACC = CURRENT_TIMESTAMP
                       WHERE RTRIM(USERNAME) = '" . $username_escaped . "'";
        db2_exec($conn, $update_sql);
    }
    
    db2_close($conn);
    
    if (!$user) {
        return false;
    }
    
    // Controlla password
    if ($user['PASSWORD'] === $password) {
        return array(
            'username' => $user['USERNAME'],
            'nome_completo' => $user['NOME_COMPLETO'],
            'livello' => $user['LIVELLO'],
            'poloprod' => $user['POLOPROD']
        );
    }
    
    return false;
}

// Crea sessione utente - VERSIONE SEMPLIFICATA
function createUserSession($user_data) {
    // Assicurati che la sessione sia avviata
    if (session_id() == '') {
        session_start();
    }
    
    // Salva dati utente in sessione
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['nome_completo'] = $user_data['nome_completo'];
    $_SESSION['livello'] = $user_data['livello'];
    $_SESSION['poloprod'] = isset($user_data['poloprod']) ? $user_data['poloprod'] : '';
    $_SESSION['expires_at'] = time() + SESSION_TIMEOUT;
    $_SESSION['login_time'] = time();
    
    // Log per debug
    //error_log("createUserSession - Session ID: " . session_id());
    //error_log("createUserSession - SESSION data: " . print_r($_SESSION, true));
    
    return true;
}

// Verifica sessione valida - SEMPLIFICATA
function isValidSession() {
	
	
    // DEBUG DETTAGLIATO
    $trace = debug_backtrace();
    $caller_file = isset($trace[0]['file']) ? basename($trace[0]['file']) : 'unknown';
    $caller_line = isset($trace[0]['line']) ? $trace[0]['line'] : '?';
    
    //error_log("isValidSession - Called from: $caller_file:$caller_line");
    //error_log("isValidSession - Full trace:");
    for ($i = 0; $i < min(5, count($trace)); $i++) {
        $file = isset($trace[$i]['file']) ? basename($trace[$i]['file']) : 'unknown';
        $line = isset($trace[$i]['line']) ? $trace[$i]['line'] : '?';
        $func = isset($trace[$i]['function']) ? $trace[$i]['function'] : 'unknown';
        //error_log("  $i: $file:$line in $func()");
    }
    
    // Avvia sessione se non attiva
    if (session_id() == '') {
        session_start();
    }
    
	
    if (isset($_SESSION['_validated']) && $_SESSION['_validated'] === true) {
           return true;
       }
	
    // Avvia sessione se non attiva
    if (session_id() == '') {
        session_start();
    }
	
	// DEBUG: Chi sta chiamando questa funzione?
	    $backtrace = debug_backtrace();
	    if (isset($backtrace[1])) {
	        //error_log("isValidSession - Called from: " . $backtrace[1]['function'] . " in " . $backtrace[1]['file'] . " line " . $backtrace[1]['line']);
	    }
    
	    // Log per debug
	    //error_log("isValidSession - Session ID: " . session_id());
	    //error_log("isValidSession - SESSION content: " . print_r($_SESSION, true));
    
	    // Controlla che ci sia username
	    if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
	       // error_log("isValidSession - No username in session");
	        return false;
	    }
    
    // Log per debug
    //error_log("isValidSession - Session ID: " . session_id());
    //error_log("isValidSession - SESSION content: " . print_r($_SESSION, true));
    
    // Controlla che ci sia username
    if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        //error_log("isValidSession - No username in session");
        return false;
    }
    
    // Controlla timeout
    if (isset($_SESSION['expires_at']) && time() > $_SESSION['expires_at']) {
        //error_log("isValidSession - Session expired");
        destroyUserSession();
        return false;
    }
    
    // Rinnova timeout
    $_SESSION['expires_at'] = time() + SESSION_TIMEOUT;
    
    return true;
}

// Distruggi sessione - SEMPLIFICATA
function destroyUserSession() {
    if (session_id() == '') {
        session_start();
    }
    
    // Pulisci variabili di sessione
    $_SESSION = array();
    
    // Distruggi cookie di sessione
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    
    // Distruggi sessione
    session_destroy();
}

// Ottieni dati utente corrente
function getCurrentUser() {
    startSessionSafe();
    
    return array(
        'username' => isset($_SESSION['username']) ? $_SESSION['username'] : null,
        'nome_completo' => isset($_SESSION['nome_completo']) ? $_SESSION['nome_completo'] : null,
        'livello' => isset($_SESSION['livello']) ? $_SESSION['livello'] : null,
        'poloprod' => isset($_SESSION['poloprod']) ? $_SESSION['poloprod'] : null
    );
}

// Controlla autenticazione
function requireAuth() {
    if (!isValidSession()) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(array('error' => 'Autenticazione richiesta', 'redirect' => 'login.html'));
        exit;
    }
}

// WEBDECOD functions
function callWebDecod($ticket_number) {
    //error_log("WEBDECOD: Chiamata per ticket " . $ticket_number . " - USANDO SQL");
    
    $data = callWebDecodSQL($ticket_number);
    if ($data === null) {
        error_log("WEBDECOD: SQL fallito per ticket " . $ticket_number . " - uso fallback");
        return getWebDecodFallbackLegacy($ticket_number);
    }
    
    //error_log("WEBDECOD: SQL SUCCESS per ticket " . $ticket_number);
    return $data;
}

function getWebDecodFallbackLegacy($ticket_number) {
   // error_log("WEBDECOD LEGACY FALLBACK: Dati test per ticket " . $ticket_number);
    
    $last_digit = $ticket_number % 5;
    $test_data = array(
        array('LEGTEST1', 'LEGACY TEST PRODUTTORE 1 - TICKET ' . $ticket_number, 'ISPLEG1', 'ISPETTORE LEGACY 1'),
        array('LEGTEST2', 'LEGACY TEST PRODUTTORE 2 - TICKET ' . $ticket_number, 'ISPLEG2', 'ISPETTORE LEGACY 2'),
        array('LEGTEST3', 'LEGACY TEST PRODUTTORE 3 - TICKET ' . $ticket_number, 'ISPLEG3', 'ISPETTORE LEGACY 3'),
        array('LEGTEST4', 'LEGACY TEST PRODUTTORE 4 - TICKET ' . $ticket_number, 'ISPLEG4', 'ISPETTORE LEGACY 4'),
        array('LEGTEST5', 'LEGACY TEST PRODUTTORE 5 - TICKET ' . $ticket_number, 'ISPLEG5', 'ISPETTORE LEGACY 5')
    );
    
    $selected = $test_data[$last_digit];
    return array(
        'cod_produttore' => $selected[0],
        'rag_soc_produttore' => $selected[1],
        'cod_ispettore' => $selected[2],
        'nome_ispettore' => $selected[3]
    );
}

// Logging
// Logging - VERSIONE CORRETTA CHE NON RIAPRE LA SESSIONE
function logActivity($azione, $dettagli) {
    if (empty($dettagli)) {
        $dettagli = '';
    }
    
    $conn = getDb2Connection();
    if (!$conn) {
        return;
    }
    
    $set_schema_sql = 'SET SCHEMA ' . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $sql = 'INSERT INTO WLOGATT
            (AZIONE, DETTAGLI, USERNAME, IP_ADDRESS, DATA_ORA)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)';
    
    $stmt = db2_prepare($conn, $sql);
    if ($stmt) {
        // NON chiamare getCurrentUser() che riapre la sessione!
        // Prendi l'username direttamente da $_SESSION se esiste
        $username = 'ANONIMO';
        if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
            $username = $_SESSION['username'];
        }
        
        $ip_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        db2_execute($stmt, array($azione, $dettagli, $username, $ip_addr));
    }
    
    db2_close($conn);
}
// Pulisce sessioni scadute
function cleanupExpiredSessions() {
    $conn = getDb2Connection();
    if (!$conn) {
        return;
    }
    
    $set_schema_sql = 'SET SCHEMA ' . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $sql = 'DELETE FROM WSESSIONI WHERE EXPIRES_AT < CURRENT_TIMESTAMP';
    db2_exec($conn, $sql);
    db2_close($conn);
}

// Aggiorna statistiche giornaliere
function updateDailyStats($tipo, $valore) {
    if (empty($valore)) {
        $valore = 1;
    }
    
    $conn = getDb2Connection();
    if (!$conn) {
        return;
    }
    
    $set_schema_sql = 'SET SCHEMA ' . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $today = date('Y-m-d');
    
    $check_sql = 'SELECT DATA_STAT FROM WSTATGIOR WHERE DATA_STAT = ?';
    $check_stmt = db2_prepare($conn, $check_sql);
    if (!$check_stmt) {
        db2_close($conn);
        return;
    }
    
    db2_execute($check_stmt, array($today));
    $exists = db2_fetch_assoc($check_stmt);
    
    if ($exists) {
        if ($tipo === 'upload') {
            $sql = 'UPDATE WSTATGIOR
                    SET UPLOAD_TOT = UPLOAD_TOT + 1,
                        TOTALE_MB = TOTALE_MB + ?
                    WHERE DATA_STAT = ?';
            $params = array(round($valore / 1024 / 1024, 2), $today);
        } else if ($tipo === 'ricerca') {
            $sql = 'UPDATE WSTATGIOR
                    SET RICERCA_TOT = RICERCA_TOT + 1
                    WHERE DATA_STAT = ?';
            $params = array($today);
        }
    } else {
        if ($tipo === 'upload') {
            $sql = 'INSERT INTO WSTATGIOR
                    (DATA_STAT, UPLOAD_TOT, RICERCA_TOT, TOTALE_MB)
                    VALUES (?, 1, 0, ?)';
            $params = array($today, round($valore / 1024 / 1024, 2));
        } else if ($tipo === 'ricerca') {
            $sql = 'INSERT INTO WSTATGIOR
                    (DATA_STAT, UPLOAD_TOT, RICERCA_TOT, TOTALE_MB)
                    VALUES (?, 0, 1, 0)';
            $params = array($today);
        }
    }
    
    if (isset($sql)) {
        $stmt = db2_prepare($conn, $sql);
        if ($stmt) {
            db2_execute($stmt, $params);
        }
    }
    
    db2_close($conn);
}

// Genera nome file unico
function generateUniqueFileName($original_name, $year) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $timestamp = date('YmdHis');
    $random = substr(md5(uniqid(rand(), true)), 0, 8);
    return $year . '_' . $timestamp . '_' . $random . '.' . strtolower($extension);
}

// SISTEMA DI PERMESSI
function hasPermission($action, $user_level = null) {
    if ($user_level === null) {
        $current_user = getCurrentUser();
        $user_level = $current_user['livello'];
    }
    
    $permissions = array(
        'ADMIN' => array(
            'create_ticket',
            'add_files', 
            'delete_files',
            'delete_ticket',
            'view_all_tickets',
            'admin_functions'
        ),
        'BACKOFFICE' => array(
            'create_ticket',
            'add_files',
            'delete_files',
            'view_all_tickets'
        ),
        'USER' => array(
            'create_ticket',
            'add_files_own_tickets',
            'delete_files',
            'delete_ticket',
            'view_all_tickets'
        ),
        'CQ' => array(
            'view_cq_tickets',
            'download_cq_files'
        )
    );
    
    if (!isset($permissions[$user_level])) {
        return false;
    }
    
    return in_array($action, $permissions[$user_level]);
}

function canModifyTicket($ticket_id, $user_level = null, $ticket_owner = null) {
    $current_user = getCurrentUser();
    
    if ($user_level === null) {
        $user_level = $current_user['livello'];
        $username = $current_user['username'];
    } else {
        $username = $current_user['username'];
    }
    
    if ($user_level === 'ADMIN') {
        return true;
    }
    
    if ($user_level === 'BACKOFFICE') {
        return true;
    }
    
    if ($user_level === 'USER') {
        if ($ticket_owner === null) {
            $ticket_owner = getTicketOwner($ticket_id);
        }
        return ($ticket_owner === $username);
    }
    
    if ($user_level === 'CQ') {
        return false;
    }
    
    return false;
}

function getTicketOwner($ticket_id) {
    $conn = getDb2Connection();
    if (!$conn) {
        return null;
    }
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $sql = "SELECT USERNAME_INS FROM WTICKET WHERE NUM_TICKET = ?";
    $stmt = db2_prepare($conn, $sql);
    
    if ($stmt && db2_execute($stmt, array($ticket_id))) {
        $row = db2_fetch_assoc($stmt);
        db2_close($conn);
        return $row ? trim($row['USERNAME_INS']) : null;
    }
    
    db2_close($conn);
    return null;
}

function canCQViewTicket($ticket_id, $username = null) {
    if ($username === null) {
        $current_user = getCurrentUser();
        $username = $current_user['username'];
    }
    
    $conn = getDb2Connection();
    if (!$conn) {
        error_log("canCQViewTicket: Connessione DB fallita");
        return false;
    }
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $ticket_id_safe = (int)$ticket_id;
    $username_safe = str_replace("'", "''", trim($username));
    
    $sql = "SELECT NUM_TICKET FROM WTICKET 
            WHERE NUM_TICKET = " . $ticket_id_safe . " 
            AND TRIM(NOME_ISPET) = '" . $username_safe . "'";
    
    //error_log("canCQViewTicket SQL: " . $sql);
    
    $stmt = db2_exec($conn, $sql);
    
    if (!$stmt) {
        error_log("canCQViewTicket: Query fallita - " . db2_conn_errormsg($conn));
        db2_close($conn);
        return false;
    }
    
    $row = db2_fetch_assoc($stmt);
    db2_close($conn);
    
    $result = ($row !== false);
    //error_log("canCQViewTicket: Ticket $ticket_id, User '$username', Result: " . ($result ? 'ALLOWED' : 'DENIED'));
    
    return $result;
}

function requirePermission($action, $ticket_id = null) {
    $current_user = getCurrentUser();
    
    if ($action === 'delete_ticket') {
        if ($current_user['livello'] === 'ADMIN') {
            return true;
        } elseif ($current_user['livello'] === 'USER' && $ticket_id !== null) {
            $ticket_owner = getTicketOwner($ticket_id);
            if ($ticket_owner === $current_user['username']) {
                return true;
            } else {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(array(
                    'success' => false, 
                    'error' => 'Puoi cancellare solo i tuoi ticket'
                ));
                exit;
            }
        } else {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(array(
                'success' => false, 
                'error' => 'Non hai i permessi per cancellare ticket'
            ));
            exit;
        }
    }
    
    if (!hasPermission($action)) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(array(
            'success' => false, 
            'error' => 'Permessi insufficienti per questa azione'
        ));
        exit;
    }
    
    if ($ticket_id !== null && $action !== 'delete_ticket' && !canModifyTicket($ticket_id)) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(array(
            'success' => false, 
            'error' => 'Non hai i permessi per modificare questo ticket'
        ));
        exit;
    }
    
    return true;
}

function canUserDeleteTicket($ticket_id, $username) {
    $ticket_owner = getTicketOwner($ticket_id);
    return ($ticket_owner === $username);
}

function checkPermission($required_level) {
    $user = getCurrentUser();
    $user_level = $user['livello'];
    $levels = array('CQ' => 1, 'USER' => 2, 'BACKOFFICE' => 3, 'ADMIN' => 4);
    
    if (!isset($levels[$user_level]) || !isset($levels[$required_level])) {
        return false;
    }
    
    return $levels[$user_level] >= $levels[$required_level];
}
?>