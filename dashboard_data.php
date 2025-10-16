<?php
// IMPORTANTE: init_firefox DEVE essere PRIMA di config.php!
require_once 'init_firefox.php';
require_once 'config.php';

// Funzione array_column per PHP 5 compatibility
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

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Avvia sessione
startSessionSafe();

//error_log("SEARCH.PHP - Session ID: " . session_id());
//error_log("SEARCH.PHP - Username in session: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'NOT SET'));
//error_log("SEARCH.PHP - Full session: " . print_r($_SESSION, true));


// Usa isValidSession() invece della verifica manuale
if (!isValidSession()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(array('error' => 'Sessione non valida', 'redirect' => 'login.html'));
    exit;
}

try {
    $conn = getDb2Connection();
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $response = array(
        'success' => true,
        'stats' => getDashboardStats($conn),
        'recent_tickets' => getRecentTickets($conn),
        'monthly_stats' => getMonthlyStats($conn)
    );
    
    db2_close($conn);
    
    // Log attività - usa direttamente $_SESSION invece di chiamare funzioni
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'ANONIMO';
    $conn2 = getDb2Connection();
    if ($conn2) {
        $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
        db2_exec($conn2, $set_schema_sql);
        $sql = "INSERT INTO WLOGATT (AZIONE, DETTAGLI, USERNAME, IP_ADDRESS, DATA_ORA) 
                VALUES ('DASHBOARD_VIEW', 'Visualizzazione dashboard', ?, ?, CURRENT_TIMESTAMP)";
        $stmt = db2_prepare($conn2, $sql);
        if ($stmt) {
            $ip_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
            db2_execute($stmt, array($username, $ip_addr));
        }
        db2_close($conn2);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Errore dashboard data: ' . $e->getMessage());
    
    echo json_encode(array(
        'success' => false,
        'error' => 'Errore nel caricamento dei dati'
    ));
}
/**
 * Ottiene statistiche generali
 */
/**
 * Ottiene statistiche generali con filtro polo produttivo
 */
/**
 * DASHBOARD FUNCTIONS - Versione con QUERY DIRETTE (senza prepared statements)
 * Sostituire le 3 funzioni in dashboard_data.php
 */

/**
 * Ottiene statistiche generali - VERSIONE QUERY DIRETTE
 */
function getDashboardStats($conn) {
    $current_user = getCurrentUser();
    $is_cq_user = ($current_user['livello'] === 'CQ');
    $user_polo = isset($current_user['poloprod']) ? trim($current_user['poloprod']) : '';
    
    //error_log("DASHBOARD: User = " . $current_user['username'] . ", Livello = " . $current_user['livello'] . ", Polo = '" . $user_polo . "'");
    
    $stats = array(
        'total_tickets' => 0,
        'today_tickets' => 0,
        'total_files' => 0,
        'total_size' => 0
    );
    
    try {
        // Escape dei valori per query dirette
        $user_polo_escaped = str_replace("'", "''", $user_polo);
        $username_escaped = str_replace("'", "''", $current_user['username']);
        
        // Costruisce filtri
        $polo_join = '';
        $polo_filter = '';
        $cq_filter = '';
        
        if (!empty($user_polo) && $user_polo !== ' ') {
            $polo_join = ' INNER JOIN WEBANCQ0F cq ON t.COD_ISPETT = cq.WUTECQ';
            $polo_filter = " AND TRIM(cq.WPOLOPR) = '" . $user_polo_escaped . "'";
            //error_log("DASHBOARD: Applying polo filter = '" . $user_polo . "' (direct query)");
        }
        
        if ($is_cq_user) {
            $cq_filter = " AND TRIM(t.NOME_ISPET) = '" . $username_escaped . "'";
        }
        
        // QUERY 1: Ticket totali
        $sql = "SELECT COUNT(*) as TOTAL_COUNT FROM WTICKET t" . $polo_join . 
               " WHERE 1=1" . $polo_filter . $cq_filter;
        
        //error_log("DASHBOARD: Direct SQL = " . $sql);
        
        $stmt = db2_exec($conn, $sql);
        if ($stmt) {
            $row = db2_fetch_assoc($stmt);
            $stats['total_tickets'] = isset($row['TOTAL_COUNT']) ? (int)$row['TOTAL_COUNT'] : 0;
            //error_log("DASHBOARD: Total tickets = " . $stats['total_tickets']);
        } else {
            error_log("DASHBOARD ERROR: Total tickets direct query failed - " . db2_conn_errormsg($conn));
        }
        
        // QUERY 2: Ticket di oggi
        $sql = "SELECT COUNT(*) as TODAY_COUNT FROM WTICKET t" . $polo_join . 
               " WHERE DATE(t.DATA_INS) = CURRENT_DATE" . $polo_filter . $cq_filter;
        
        $stmt = db2_exec($conn, $sql);
        if ($stmt) {
            $row = db2_fetch_assoc($stmt);
            $stats['today_tickets'] = isset($row['TODAY_COUNT']) ? (int)$row['TODAY_COUNT'] : 0;
            //error_log("DASHBOARD: Today tickets = " . $stats['today_tickets']);
        } else {
            error_log("DASHBOARD ERROR: Today tickets direct query failed - " . db2_conn_errormsg($conn));
        }
        
        // QUERY 3: File totali e dimensione 
        $sql = "SELECT COUNT(*) as FILE_COUNT, COALESCE(SUM(a.DIMENSIONE), 0) as TOTAL_SIZE_BYTES 
                FROM WALLEGATI a 
                INNER JOIN WTICKET t ON a.NUM_TICKET = t.NUM_TICKET" . $polo_join . 
                " WHERE 1=1" . $polo_filter . $cq_filter;
        
        $stmt = db2_exec($conn, $sql);
        if ($stmt) {
            $row = db2_fetch_assoc($stmt);
            $stats['total_files'] = isset($row['FILE_COUNT']) ? (int)$row['FILE_COUNT'] : 0;
            $stats['total_size'] = isset($row['TOTAL_SIZE_BYTES']) ? (int)$row['TOTAL_SIZE_BYTES'] : 0;
           // error_log("DASHBOARD: Files = " . $stats['total_files'] . ", Size = " . $stats['total_size']);
        } else {
            error_log("DASHBOARD ERROR: Files direct query failed - " . db2_conn_errormsg($conn));
        }
        
    } catch (Exception $e) {
        error_log('DASHBOARD EXCEPTION: ' . $e->getMessage());
    }
    
    //error_log("DASHBOARD: Final stats - Tickets: " . $stats['total_tickets'] . 
    //          ", Today: " . $stats['today_tickets'] . 
    //          ", Files: " . $stats['total_files'] . 
    //          ", Size: " . $stats['total_size']);
    
    return $stats;
}

/**
 * Ottiene ultimi ticket caricati - VERSIONE QUERY DIRETTE
 */
function getRecentTickets($conn) {
    $current_user = getCurrentUser();
    $is_cq_user = ($current_user['livello'] === 'CQ');
    $user_polo = isset($current_user['poloprod']) ? trim($current_user['poloprod']) : '';
    
    //error_log("RECENT TICKETS: User = " . $current_user['username'] . ", Livello = " . $current_user['livello'] . ", Polo = '" . $user_polo . "'");
    
    $tickets = array();
    
    try {
        // Escape dei valori per query dirette
        $user_polo_escaped = str_replace("'", "''", $user_polo);
        $username_escaped = str_replace("'", "''", $current_user['username']);
        
        // Costruisce filtri
        $polo_join = '';
        $polo_filter = '';
        $cq_filter = '';
        
        if (!empty($user_polo) && $user_polo !== ' ') {
            $polo_join = ' INNER JOIN WEBANCQ0F cq ON t.COD_ISPETT = cq.WUTECQ';
            $polo_filter = " AND TRIM(cq.WPOLOPR) = '" . $user_polo_escaped . "'";
      //      error_log("RECENT TICKETS: Applying polo filter = '" . $user_polo . "' (direct query)");
        }
        
        if ($is_cq_user) {
            $cq_filter = " AND TRIM(t.NOME_ISPET) = '" . $username_escaped . "'";
        }
        
        // Query per ticket recenti
        $sql = "SELECT t.NUM_TICKET, t.NUM_CARTEL, t.RAG_SOC_PR, t.NOME_ISPET, 
                       t.DATA_REG, t.DATA_INS,
                       (SELECT COUNT(*) FROM WALLEGATI a WHERE a.NUM_TICKET = t.NUM_TICKET) as FILE_COUNT
                FROM WTICKET t" . $polo_join . " 
                WHERE 1=1" . $polo_filter . $cq_filter . "
                ORDER BY t.DATA_INS DESC 
                FETCH FIRST 10 ROWS ONLY";
        
        //error_log("RECENT TICKETS: Direct SQL = " . $sql);
        
        $stmt = db2_exec($conn, $sql);
        if ($stmt) {
            while ($row = db2_fetch_assoc($stmt)) {
                if ($row) {
                    $tickets[] = array(
                        'NUM_TICKET' => (int)$row['NUM_TICKET'],
                        'NUM_CARTEL' => (int)$row['NUM_CARTEL'],
                        'RAG_SOC_PR' => trim($row['RAG_SOC_PR']),
                        'NOME_ISPET' => trim($row['NOME_ISPET']),
                        'DATA_REG' => $row['DATA_REG'],
                        'DATA_INS' => $row['DATA_INS'],
                        'FILE_COUNT' => (int)$row['FILE_COUNT']
                    );
                }
            }
        } else {
            error_log("RECENT TICKETS ERROR: Direct query failed - " . db2_conn_errormsg($conn));
        }
        
    } catch (Exception $e) {
        error_log('RECENT TICKETS EXCEPTION: ' . $e->getMessage());
    }
    
    //error_log("RECENT TICKETS: Found " . count($tickets) . " recent tickets");
    
    return $tickets;
}

/**
 * Ottiene statistiche mensili - VERSIONE QUERY DIRETTE
 */
function getMonthlyStats($conn) {
    $current_user = getCurrentUser();
    $is_cq_user = ($current_user['livello'] === 'CQ');
    $user_polo = isset($current_user['poloprod']) ? trim($current_user['poloprod']) : '';
    
   // error_log("MONTHLY STATS: User = " . $current_user['username'] . ", Livello = " . $current_user['livello'] . ", Polo = '" . $user_polo . "'");
    
    $monthly = array();
    
    try {
        // Escape dei valori per query dirette
        $user_polo_escaped = str_replace("'", "''", $user_polo);
        $username_escaped = str_replace("'", "''", $current_user['username']);
        
        // Costruisce filtri
        $polo_join = '';
        $polo_filter = '';
        $cq_filter = '';
        
        if (!empty($user_polo) && $user_polo !== ' ') {
            $polo_join = ' INNER JOIN WEBANCQ0F cq ON t.COD_ISPETT = cq.WUTECQ';
            $polo_filter = " AND TRIM(cq.WPOLOPR) = '" . $user_polo_escaped . "'";
     //       error_log("MONTHLY STATS: Applying polo filter = '" . $user_polo . "' (direct query)");
        }
        
        if ($is_cq_user) {
            $cq_filter = " AND TRIM(t.NOME_ISPET) = '" . $username_escaped . "'";
        }
        
        // Statistiche ultimi 6 mesi
        $sql = "SELECT 
                    YEAR(t.DATA_INS) as anno,
                    MONTH(t.DATA_INS) as mese,
                    COUNT(*) as ticket_count,
                    COALESCE(SUM(
                        (SELECT COUNT(*) FROM WALLEGATI a WHERE a.NUM_TICKET = t.NUM_TICKET)
                    ), 0) as file_count
                FROM WTICKET t" . $polo_join . " 
                WHERE t.DATA_INS >= CURRENT_DATE - 6 MONTHS" . $polo_filter . $cq_filter . "
                GROUP BY YEAR(t.DATA_INS), MONTH(t.DATA_INS)
                ORDER BY anno DESC, mese DESC";
        
       // error_log("MONTHLY STATS: Direct SQL = " . $sql);
        
        $stmt = db2_exec($conn, $sql);
        if ($stmt) {
            while ($row = db2_fetch_assoc($stmt)) {
                if ($row) {
                    $mese_nome = getMonthName($row['mese']);
                    
                    $monthly[] = array(
                        'anno' => (int)$row['anno'],
                        'mese' => (int)$row['mese'],
                        'mese_nome' => $mese_nome,
                        'ticket_count' => (int)$row['ticket_count'],
                        'file_count' => (int)$row['file_count']
                    );
                }
            }
        } else {
            error_log("MONTHLY STATS ERROR: Direct query failed - " . db2_conn_errormsg($conn));
        }
        
        // Statistiche giornaliere ultima settimana (solo per ADMIN/BACKOFFICE senza filtro polo)
        $weekly_stats = array();
        if (!$is_cq_user && (empty($user_polo) || $user_polo === ' ')) {
        //    error_log("MONTHLY STATS: Loading weekly stats (no polo filter)");
            
            $weekly_sql = "SELECT DATA_STAT, UPLOAD_TOT, RICERCA_TOT, TOTALE_MB
                          FROM WSTATGIOR 
                          WHERE DATA_STAT >= CURRENT_DATE - 7 DAYS
                          ORDER BY DATA_STAT DESC";
            
            $weekly_stmt = db2_exec($conn, $weekly_sql);
            if ($weekly_stmt) {
                while ($row = db2_fetch_assoc($weekly_stmt)) {
                    if ($row) {
                        $weekly_stats[] = array(
                            'data' => $row['DATA_STAT'],
                            'upload' => (int)$row['UPLOAD_TOT'],
                            'ricerche' => (int)$row['RICERCA_TOT'],
                            'mb' => (float)$row['TOTALE_MB']
                        );
                    }
                }
            } else {
                error_log("MONTHLY STATS ERROR: Weekly stats failed - " . db2_conn_errormsg($conn));
            }
        } else {
      //      error_log("MONTHLY STATS: Weekly stats skipped (CQ user or polo filter active)");
        }
        
     //   error_log("MONTHLY STATS: Found " . count($monthly) . " monthly records, " . count($weekly_stats) . " weekly records");
        
        return array(
            'mensili' => $monthly,
            'settimanali' => $weekly_stats
        );
        
    } catch (Exception $e) {
        error_log('MONTHLY STATS EXCEPTION: ' . $e->getMessage());
        return array('mensili' => array(), 'settimanali' => array());
    }
}
/**
 * Ottiene nome del mese in italiano
 */
function getMonthName($month) {
    $mesi = array(
        1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo',
        4 => 'Aprile', 5 => 'Maggio', 6 => 'Giugno',
        7 => 'Luglio', 8 => 'Agosto', 9 => 'Settembre',
        10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
    );
    
    return isset($mesi[$month]) ? $mesi[$month] : 'N/A';
}



/**
 * Aggiungi queste funzioni al file dashboard_data.php se non sono gi├ô presenti
 */
function convertDb2TimestampToISO($db2_timestamp) {
    if (!$db2_timestamp || trim($db2_timestamp) === '') {
        return null;
    }
    
    $timestamp_str = trim($db2_timestamp);
    
    // Pattern per timestamp DB2: YYYY-MM-DD-HH.MM.SS.microseconds
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})-(\d{2})\.(\d{2})\.(\d{2})\.?(\d{0,6})?$/', $timestamp_str, $matches)) {
        $year = $matches[1];
        $month = $matches[2];
        $day = $matches[3];
        $hour = $matches[4];
        $minute = $matches[5];
        $second = $matches[6];
        $microseconds = isset($matches[7]) ? str_pad($matches[7], 6, '0') : '000000';
        
        // Converti in formato ISO 8601
        return $year . '-' . $month . '-' . $day . 'T' . $hour . ':' . $minute . ':' . $second . '.' . substr($microseconds, 0, 3) . 'Z';
    }
    
    // Se non ├× nel formato DB2, prova a vedere se ├× gi├ô in un formato valido
    if (strtotime($timestamp_str) !== false) {
        return date('c', strtotime($timestamp_str));
    }
    
    return null;
}

function convertDb2DateToISO($db2_date) {
    if (!$db2_date || trim($db2_date) === '') {
        return null;
    }
    
    $date_str = trim($db2_date);
    
    // Prova vari formati di data
    $formats = array('d/m/y', 'd/m/Y', 'Y-m-d', 'm/d/Y', 'm/d/y');
    
    foreach ($formats as $format) {
        $date_obj = DateTime::createFromFormat($format, $date_str);
        if ($date_obj !== false) {
            return $date_obj->format('Y-m-d');
        }
    }
    
    // Fallback con strtotime
    if (strtotime($date_str) !== false) {
        return date('Y-m-d', strtotime($date_str));
    }
    
    return null;
}
?>