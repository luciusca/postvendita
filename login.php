<?php
/**
 * LOGIN.PHP - Gestione autenticazione utenti
 * Sistema Gestione Ticket Post-Vendita - FIX FIREFOX
 */

// IMPORTANTE: Include init_firefox PRIMA di tutto
require_once 'init_firefox.php';

// Include config
require_once 'config.php';



// Avvia sessione usando la funzione safe
startSessionSafe();

// Gestione logout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    
    startSessionSafe(); // Avvia sessione per avere i dati
    
    if (isValidSession()) {
        $current_user = getCurrentUser();
        $session_id = session_id();
        
        // Log attività
        logActivity('LOGOUT', "Logout utente: " . $current_user['username']);
        
        // Se Firefox, cancella dal DB2
        if (isFirefoxBrowser() && $session_id) {
            error_log("LOGIN.PHP LOGOUT - Deleting Firefox session from DB2: " . $session_id);
            $conn = db2_connect('S78CCD90', 'PERS', 'PERS');
            if ($conn) {
                $delete_sql = "DELETE FROM WEBPVEND.WSESSIONS WHERE SESSION_ID = '" . $session_id . "'";
                $result = db2_exec($conn, $delete_sql);
                if ($result) {
                    error_log("LOGIN.PHP LOGOUT - Session deleted from DB2");
                } else {
                    error_log("LOGIN.PHP LOGOUT - Failed to delete from DB2: " . db2_stmt_errormsg());
                }
                db2_close($conn);
            }
        }
    }
    
    destroyUserSession();
    
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('success' => true, 'message' => 'Logout completato'));
    exit;
}

// Gestione logout GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    
    if (isValidSession()) {
        $current_user = getCurrentUser();
        logActivity('LOGOUT', "Logout utente: " . $current_user['username']);
    }
    
    destroyUserSession();
    
    header('Location: login.html');
    exit;
}

// Headers per JSON response (solo se non è Firefox con submit normale)
$is_firefox_submit = isset($_POST['firefox_submit']) || isset($_GET['firefox']);

if (!$is_firefox_submit) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!$is_firefox_submit) {
        http_response_code(405);
        echo json_encode(array('success' => false, 'error' => 'Metodo non consentito'));
    } else {
        header('Location: login.html');
    }
    exit;
}

// Pulizia sessioni scadute
cleanupExpiredSessions();

try {
    // Recupera e valida input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Validazione input
    if (empty($username)) {
        if ($is_firefox_submit) {
            header('Location: login.html?error=username');
            exit;
        }
        echo json_encode(array('success' => false, 'error' => 'Username richiesto'));
        exit;
    }
    
    if (empty($password)) {
        if ($is_firefox_submit) {
            header('Location: login.html?error=password');
            exit;
        }
        echo json_encode(array('success' => false, 'error' => 'Password richiesta'));
        exit;
    }
    
    if (strlen($username) > 10) {
        if ($is_firefox_submit) {
            header('Location: login.html?error=username_long');
            exit;
        }
        echo json_encode(array('success' => false, 'error' => 'Username troppo lungo'));
        exit;
    }
    
    if (strlen($password) > 50) {
        if ($is_firefox_submit) {
            header('Location: login.html?error=password_long');
            exit;
        }
        echo json_encode(array('success' => false, 'error' => 'Password troppo lunga'));
        exit;
    }
    
    // Verifica rate limiting
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    if (isRateLimited($ip_address)) {
        logActivity('LOGIN_BLOCKED', "Troppi tentativi per IP: $ip_address");
        if ($is_firefox_submit) {
            header('Location: login.html?error=rate_limit');
            exit;
        }
        echo json_encode(array('success' => false, 'error' => 'Troppi tentativi. Riprovare tra 5 minuti.'));
        exit;
    }
    
    // Tentativo di autenticazione
    $user_data = authenticateUser($username, $password);
    
    if ($user_data) {
        // Login riuscito - crea sessione
        if (createUserSession($user_data)) {
            
            // Se è Firefox con submit normale (NON AJAX)
            if ($is_firefox_submit) {
                //error_log("LOGIN.PHP - Firefox normal submit detected");
                
                // FIX CRITICO PER FIREFOX: Forza scrittura sessione MULTIPLA
                // Questo assicura che la sessione sia salvata nel DB2
                
                // Prima scrittura
                session_write_close();
                
                // Riapri e verifica
                session_start();
                
                // Verifica che i dati ci siano ancora
                if (!isset($_SESSION['username'])) {
                    // Reinserisci i dati se persi
                    $_SESSION['username'] = $user_data['username'];
                    $_SESSION['nome_completo'] = $user_data['nome_completo'];
                    $_SESSION['livello'] = $user_data['livello'];
                    $_SESSION['poloprod'] = isset($user_data['poloprod']) ? $user_data['poloprod'] : '';
                    $_SESSION['expires_at'] = time() + 3600;
                    $_SESSION['login_time'] = time();
                }
                
                // Seconda scrittura forzata
                session_write_close();
                
                // NUOVO: Verifica diretta nel DB2 che la sessione sia salvata
                $session_id = session_id();
                if ($session_id && isFirefoxBrowser()) {
                    $conn = db2_connect('S78CCD90', 'PERS', 'PERS');
                    if ($conn) {
                        db2_exec($conn, "SET SCHEMA WEBPVEND");
                        
                        // Verifica che la sessione sia nel DB
                        $check_sql = "SELECT SESSION_DATA FROM WSESSIONS WHERE SESSION_ID = '$session_id'";
                        $check_stmt = db2_exec($conn, $check_sql);
                        
                        if (!$check_stmt || !db2_fetch_assoc($check_stmt)) {
                            // Se non c'è, inseriscila manualmente
                            //error_log("LOGIN.PHP - Session not in DB, forcing insert");
                            
                            $session_data = session_encode();
                            $session_data_safe = str_replace("'", "''", $session_data);
                            
                            $insert_sql = "INSERT INTO WSESSIONS (SESSION_ID, SESSION_DATA, EXPIRES_AT, LAST_ACCESS) 
                                         VALUES ('$session_id', '$session_data_safe', 
                                                CURRENT_TIMESTAMP + 1 HOURS, CURRENT_TIMESTAMP)";
                            
                            $result = db2_exec($conn, $insert_sql);
                            if (!$result) {
                                // Prova update se esiste già
                                $update_sql = "UPDATE WSESSIONS 
                                             SET SESSION_DATA = '$session_data_safe',
                                                 EXPIRES_AT = CURRENT_TIMESTAMP + 1 HOURS,
                                                 LAST_ACCESS = CURRENT_TIMESTAMP
                                             WHERE SESSION_ID = '$session_id'";
                                db2_exec($conn, $update_sql);
                            }
                        }
                        
                        db2_close($conn);
                    }
                }
                
                // Attendi un attimo per assicurare scrittura DB2
                usleep(100000); // 100ms invece di 500ms
                
                logActivity('LOGIN_SUCCESS', "Firefox user: $username");
                resetRateLimit($ip_address);
                
                // REDIRECT DIRETTO
                header('Location: index.php');
                exit;
            }
            
            // Per altri browser o Firefox con AJAX (che non dovrebbe succedere)
            // IMPORTANTE: Per Firefox, forza scrittura sessione su DB2
            if (isFirefoxBrowser()) {
                //error_log("LOGIN.PHP - Firefox AJAX detected (should not happen)");
                session_write_close();
                session_start();
            }
            
            // Verifica finale
            if (isset($_SESSION['username'])) {
                //error_log("LOGIN.PHP - Login SUCCESS for " . $_SESSION['username']);
                //error_log("LOGIN.PHP - Session ID finale: " . session_id());
                
                logActivity('LOGIN_SUCCESS', "Utente: $username, Livello: {$user_data['livello']}");
                resetRateLimit($ip_address);
                
                // Risposta JSON per browser normali
                echo json_encode(array(
                    'success' => true,
                    'user' => array(
                        'username' => $user_data['username'],
                        'nome_completo' => $user_data['nome_completo'],
                        'livello' => $user_data['livello']
                    ),
                    'redirect' => 'index.php'
                ));
                exit;
            } else {
                // Sessione non salvata correttamente
                //error_log("LOGIN.PHP - Session save failed!");
                if ($is_firefox_submit) {
                    header('Location: login.html?error=session');
                } else {
                    echo json_encode(array('success' => false, 'error' => 'Errore salvataggio sessione'));
                }
                exit;
            }
        } else {
            // Errore creazione sessione
            logActivity('LOGIN_ERROR', "Errore sessione per utente: $username");
            if ($is_firefox_submit) {
                header('Location: login.html?error=internal');
            } else {
                echo json_encode(array('success' => false, 'error' => 'Errore interno del sistema'));
            }
        }
        
    } else {
        // Login fallito
        logActivity('LOGIN_FAILED', "Tentativo fallito per utente: $username da IP: $ip_address");
        incrementRateLimit($ip_address);
        if ($is_firefox_submit) {
            header('Location: login.html?error=invalid');
        } else {
            echo json_encode(array('success' => false, 'error' => 'Username o password non corretti'));
        }
    }
    
} catch (Exception $e) {
    //error_log('Errore login: ' . $e->getMessage());
    logActivity('LOGIN_EXCEPTION', $e->getMessage());
    if ($is_firefox_submit) {
        header('Location: login.html?error=exception');
    } else {
        echo json_encode(array('success' => false, 'error' => 'Errore interno del sistema'));
    }
}

/**
 * Verifica rate limiting per IP
 */
function isRateLimited($ip_address) {
    $conn = getDb2Connection();
    if (!$conn) return false;
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $ip_escaped = str_replace("'", "''", $ip_address);
    
    $sql = "SELECT COUNT(*) as count FROM WLOGATT 
            WHERE IP_ADDRESS = '$ip_escaped' 
            AND AZIONE = 'LOGIN_FAILED' 
            AND DATA_ORA > CURRENT_TIMESTAMP - 5 MINUTES";
    
    $stmt = db2_exec($conn, $sql);
    $result = db2_fetch_assoc($stmt);
    
    db2_close($conn);
    
    return ($result && $result['count'] >= 5);
}

/**
 * Incrementa contatore rate limiting
 */
function incrementRateLimit($ip_address) {
    // Il rate limiting viene gestito automaticamente dal log degli errori
}

/**
 * Reset rate limiting per IP
 */
function resetRateLimit($ip_address) {
    $conn = getDb2Connection();
    if (!$conn) return;
    
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $ip_escaped = str_replace("'", "''", $ip_address);
    
    $sql = "DELETE FROM WLOGATT 
            WHERE IP_ADDRESS = '$ip_escaped' 
            AND AZIONE = 'LOGIN_FAILED'";
    
    db2_exec($conn, $sql);
    db2_close($conn);
}

?>