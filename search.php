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

// Usa la funzione isValidSession() che � gi� definita in config.php
if (!isValidSession()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(array('error' => 'Sessione non valida', 'redirect' => 'login.html'));
    exit;
}
// isValidSession() gi� rinnova il timeout automaticamente

// Verifica metodo GET o POST
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'error' => 'Metodo non consentito'));
    exit;
}

try {
    $conn = getDb2Connection();
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    // Controlla se � una richiesta di dettaglio
    if (isset($_GET['action']) && $_GET['action'] === 'detail') {
        handleTicketDetail($conn);
        exit;
    }
    
    // Altrimenti � una ricerca normale
    handleSearch($conn);
    
} catch (Exception $e) {
    if (isset($conn)) {
        db2_close($conn);
    }
    //error_log('Errore search: ' . $e->getMessage());
    echo json_encode(array(
        'success' => false,
        'error' => 'Errore durante la ricerca: ' . $e->getMessage()
    ));
}

/**
 * Gestisce la ricerca normale
 */
function handleSearch($conn) {
    // Recupera parametri di ricerca
    $search_params = array(
        'num_ticket' => getParam('num_ticket'),
        'rag_sociale' => getParam('rag_sociale'),
        'polo_produttivo' => getParam('polo_produttivo'),
        'nome_ispettore' => getParam('nome_ispettore'),
        'data_dal' => getParam('data_dal'),
        'data_al' => getParam('data_al'),
        'page' => max(1, (int)getParam('page', 1)),
        'per_page' => min(60, max(10, (int)getParam('per_page', 20))),
        'sort_column' => getParam('sort_column', 'DATA_REG'),
        'sort_direction' => strtoupper(getParam('sort_direction', 'DESC'))
    );

    // Validazione parametri
    if (!empty($search_params['data_dal']) && !validateDate($search_params['data_dal'])) {
        echo json_encode(array('success' => false, 'error' => 'Data dal non valida'));
        return;
    }

    if (!empty($search_params['data_al']) && !validateDate($search_params['data_al'])) {
        echo json_encode(array('success' => false, 'error' => 'Data al non valida'));
        return;
    }

    if (!in_array($search_params['sort_direction'], array('ASC', 'DESC'))) {
        $search_params['sort_direction'] = 'DESC';
    }

    $allowed_sort_columns = array('NUM_TICKET', 'NUM_CARTEL', 'RAG_SOC_PR', 'NOME_ISPET', 'DATA_REG', 'DATA_INS');
    if (!in_array($search_params['sort_column'], $allowed_sort_columns)) {
        $search_params['sort_column'] = 'DATA_REG';
    }

    // Esegue ricerca
    $search_results = performSearch($conn, $search_params);

    // Log attivit�
    logActivity('SEARCH', 'Ricerca con parametri: ' . json_encode($search_params));
    updateDailyStats('ricerca', 0);

    echo json_encode(array(
        'success' => true,
        'data' => $search_results['data'],
        'total_count' => $search_results['total_count'],
        'page' => $search_params['page'],
        'per_page' => $search_params['per_page'],
        'total_pages' => $search_results['total_pages'],
        'search_params' => $search_params
    ));
}

/**
 * Gestisce richiesta dettaglio ticket specifico
 */
function handleTicketDetail($conn) {
    $ticket_id = (int)getParam('ticket_id');
    if ($ticket_id <= 0) {
        echo json_encode(array('success' => false, 'error' => 'ID ticket non valido'));
        return;
    }

    $ticket_detail = getTicketDetail($conn, $ticket_id);
    if ($ticket_detail) {
        echo json_encode(array('success' => true, 'data' => $ticket_detail));
    } else {
        echo json_encode(array('success' => false, 'error' => 'Ticket non trovato'));
    }
}

/**
 * Esegue la ricerca con paginazione
 */
function performSearch($conn, $params) {
    // Ottiene utente corrente per verificare livello CQ
    $current_user = getCurrentUser();
    $is_cq_user = ($current_user['livello'] === 'CQ');
    $user_polo = isset($current_user['poloprod']) ? trim($current_user['poloprod']) : '';
    
    //error_log("SEARCH: User=" . $current_user['username'] . ", Level=" . $current_user['livello'] . ", Polo='" . $user_polo . "'");
    
    // Costruisce query base
    $base_sql = "SELECT t.NUM_TICKET, t.NUM_CARTEL, t.COD_PRODUT, t.RAG_SOC_PR,
                        t.COD_ISPETT, t.NOME_ISPET, t.DATA_REG, t.DATA_INS, t.USERNAME_INS
                 FROM WTICKET t";
    
    // JOIN per polo produttivo se necessario
    if (!empty($user_polo) && $user_polo !== ' ') {
        $base_sql .= " INNER JOIN WEBPVEND.WEBANCQ0F cq ON t.COD_ISPETT = cq.WUTECQ";
    }
    
    // Query per conteggio file
    $file_count_sql = "SELECT NUM_TICKET, COUNT(*) as FILE_COUNT,
                              COALESCE(SUM(DIMENSIONE), 0) as TOTAL_SIZE
                       FROM WALLEGATI GROUP BY NUM_TICKET";
    
    $file_counts = array();
    $file_stmt = db2_exec($conn, $file_count_sql);
    if ($file_stmt) {
        while ($file_row = db2_fetch_assoc($file_stmt)) {
            $ticket_num = (int)$file_row['NUM_TICKET'];
            $file_counts[$ticket_num] = array(
                'FILE_COUNT' => (int)$file_row['FILE_COUNT'],
                'TOTAL_SIZE' => (int)$file_row['TOTAL_SIZE']
            );
        }
    }
    
    // Costruisce WHERE con query diretta (no prepared statements quando c'� JOIN)
    $where_parts = array();
    
    // FILTRO CQ
    if ($is_cq_user) {
        $username_safe = str_replace("'", "''", trim($current_user['username']));
        $where_parts[] = "TRIM(t.NOME_ISPET) = '" . $username_safe . "'";
    }
    
    // FILTRO POLO
    if (!empty($user_polo) && $user_polo !== ' ') {
        $polo_safe = str_replace("'", "''", trim($user_polo));
        $where_parts[] = "TRIM(cq.WPOLOPR) = '" . $polo_safe . "'";
    }
    
    // FILTRO NUMERO TICKET
    if (!empty($params['num_ticket'])) {
        $numero = (int)$params['num_ticket'];
        $where_parts[] = "(t.NUM_TICKET = " . $numero . " OR t.NUM_CARTEL = " . $numero . ")";
    }
    
    // FILTRO RAGIONE SOCIALE
    if (!empty($params['rag_sociale'])) {
        $rag_safe = str_replace("'", "''", strtoupper(trim($params['rag_sociale'])));
        $where_parts[] = "UPPER(TRIM(t.RAG_SOC_PR)) LIKE '%" . $rag_safe . "%'";
    }
    
    // FILTRO NOME ISPETTORE
    if (!empty($params['nome_ispettore'])) {
        $isp_safe = str_replace("'", "''", strtoupper(trim($params['nome_ispettore'])));
        $where_parts[] = "UPPER(TRIM(t.NOME_ISPET)) LIKE '%" . $isp_safe . "%'";
    }
    
    // FILTRO POLO PRODUTTIVO
    if (!empty($params['polo_produttivo'])) {
        $polo_safe = str_replace("'", "''", strtoupper(trim($params['polo_produttivo'])));
        $where_parts[] = "EXISTS (SELECT 1 FROM WEBPVEND.WUTENTI u WHERE TRIM(t.NOME_ISPET) = TRIM(u.USERNAME) AND UPPER(TRIM(u.POLOPROD)) LIKE '%" . $polo_safe . "%')";
    }
    
    // FILTRO DATE
    if (!empty($params['data_dal'])) {
        $where_parts[] = "t.DATA_REG >= '" . $params['data_dal'] . "'";
    }
    
    if (!empty($params['data_al'])) {
        $where_parts[] = "t.DATA_REG <= '" . $params['data_al'] . "'";
    }
    
    // Costruisce WHERE clause
    $where_clause = '';
    if (!empty($where_parts)) {
        $where_clause = " WHERE " . implode(" AND ", $where_parts);
    }
    
    // Query per conteggio totale
    $count_sql = "SELECT COUNT(*) as TOTAL FROM WTICKET t";
    if (!empty($user_polo) && $user_polo !== ' ') {
        $count_sql .= " INNER JOIN WEBPVEND.WEBANCQ0F cq ON t.COD_ISPETT = cq.WUTECQ";
    }
    $count_sql .= $where_clause;
    
    //error_log("COUNT SQL: " . $count_sql);
    
    // Esegue conteggio con query diretta
    $count_stmt = db2_exec($conn, $count_sql);
    $total_count = 0;
    
    if ($count_stmt) {
        $count_row = db2_fetch_assoc($count_stmt);
        $total_count = (int)$count_row['TOTAL'];
    } else {
        //error_log("COUNT ERROR: " . db2_conn_errormsg($conn));
    }
    
    // Calcola paginazione
    $total_pages = ceil($total_count / $params['per_page']);
    $offset = ($params['page'] - 1) * $params['per_page'];
    
    // Query principale con ordinamento e paginazione
    $main_sql = $base_sql . $where_clause .
                " ORDER BY " . $params['sort_column'] . " " . $params['sort_direction'] .
                " OFFSET $offset ROWS FETCH NEXT " . $params['per_page'] . " ROWS ONLY";
    
    //error_log("MAIN SQL: " . $main_sql);
    
    // Esegue query principale con query diretta
    $main_stmt = db2_exec($conn, $main_sql);
    
    $results = array();
    if ($main_stmt) {
        while ($row = db2_fetch_assoc($main_stmt)) {
            $ticket_num = (int)$row['NUM_TICKET'];
            
            // Ottiene conteggio file da array preparato
            $file_info = isset($file_counts[$ticket_num]) ? $file_counts[$ticket_num] :
                         array('FILE_COUNT' => 0, 'TOTAL_SIZE' => 0);
            
            $results[] = array(
                'NUM_TICKET' => $ticket_num,
                'NUM_CARTEL' => (int)$row['NUM_CARTEL'],
                'COD_PRODUT' => trim($row['COD_PRODUT']),
                'RAG_SOC_PR' => trim($row['RAG_SOC_PR']),
                'COD_ISPETT' => trim($row['COD_ISPETT']),
                'NOME_ISPET' => trim($row['NOME_ISPET']),
                'DATA_REG' => $row['DATA_REG'],
                'DATA_INS' => $row['DATA_INS'],
                'USERNAME_INS' => trim($row['USERNAME_INS']),
                'FILE_COUNT' => $file_info['FILE_COUNT'],
                'TOTAL_SIZE' => $file_info['TOTAL_SIZE']
            );
        }
    } else {
       // error_log("MAIN ERROR: " . db2_conn_errormsg($conn));
    }
    
    // QUERY OTTIMIZZATE - Recupero i dati aggiuntivi SOLO per i ticket trovati
    if (!empty($results)) {
        // Crea lista di NUM_TICKET da cercare
        $ticket_ids = array();
        foreach ($results as $result) {
            $ticket_ids[] = $result['NUM_TICKET'];
        }
        $ticket_list = implode(',', $ticket_ids);
        
        // Query per recuperare NAZIONE da PRODCHFI/TICKETT - SOLO per i ticket trovati
        $nazione_sql = "SELECT TNUMERO, TCDNAZ FROM PRODCHFI.TICKETT WHERE TNUMERO IN (" . $ticket_list . ")";
        $nazione_data = array();
        $nazione_stmt = db2_exec($conn, $nazione_sql);
        if ($nazione_stmt) {
            while ($nazione_row = db2_fetch_assoc($nazione_stmt)) {
                $ticket_num = (int)$nazione_row['TNUMERO'];
                $nazione_data[$ticket_num] = trim($nazione_row['TCDNAZ']);
            }
        } else {
            error_log("NAZIONE SQL ERROR: " . db2_stmt_errormsg());
            error_log("NAZIONE SQL: " . $nazione_sql);
        }
        
        // Query per recuperare DATA APERTURA TICKET (TTIPMOV = 'AP') - SOLO per i ticket trovati
        $data_ap_sql = "SELECT TNUMERO, TDATMOV FROM PRODCHFI.TICKETR WHERE TNUMERO IN (" . $ticket_list . ") AND TTIPMOV = 'AP'";
        $data_apertura = array();
        $data_ap_stmt = db2_exec($conn, $data_ap_sql);
        if ($data_ap_stmt) {
            while ($ap_row = db2_fetch_assoc($data_ap_stmt)) {
                $ticket_num = (int)$ap_row['TNUMERO'];
                $data_apertura[$ticket_num] = $ap_row['TDATMOV'];
                //error_log("DATA_AP: Ticket=" . $ticket_num . ", TDATMOV=" . $ap_row['TDATMOV']);
            }
        } else {
            error_log("DATA_AP SQL ERROR: " . db2_stmt_errormsg());
        }
        
        // Query per recuperare DATA CONSEGNA CLIENTE (TTIPMOV = 'CP') - SOLO per i ticket trovati
        $data_cp_sql = "SELECT TNUMERO, TDATMOV FROM PRODCHFI.TICKETR WHERE TNUMERO IN (" . $ticket_list . ") AND TTIPMOV = 'CP'";
        $data_consegna = array();
        $data_cp_stmt = db2_exec($conn, $data_cp_sql);
        if ($data_cp_stmt) {
            while ($cp_row = db2_fetch_assoc($data_cp_stmt)) {
                $ticket_num = (int)$cp_row['TNUMERO'];
                $data_consegna[$ticket_num] = $cp_row['TDATMOV'];
                //error_log("DATA_CP: Ticket=" . $ticket_num . ", TDATMOV=" . $cp_row['TDATMOV']);
            }
        } else {
            error_log("DATA_CP SQL ERROR: " . db2_stmt_errormsg());
        }
        
        // Aggiungi i nuovi campi ai risultati
        for ($i = 0; $i < count($results); $i++) {
            $ticket_num = $results[$i]['NUM_TICKET'];
            $results[$i]['NAZIONE'] = isset($nazione_data[$ticket_num]) ? $nazione_data[$ticket_num] : '';
            $results[$i]['DATA_APERTURA'] = isset($data_apertura[$ticket_num]) ? $data_apertura[$ticket_num] : null;
            $results[$i]['DATA_CONSEGNA'] = isset($data_consegna[$ticket_num]) ? $data_consegna[$ticket_num] : null;
        }
    }
    
    return array(
        'data' => $results,
        'total_count' => $total_count,
        'total_pages' => $total_pages
    );
}

/**
 * Ottiene parametro da GET o POST
 */
function getParam($name, $default = null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return isset($_POST[$name]) ? trim($_POST[$name]) : $default;
    } else {
        return isset($_GET[$name]) ? trim($_GET[$name]) : $default;
    }
}

/**
 * Validazione data
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}


/**
 * Funzione per convertire timestamp DB2 in formato ISO per JavaScript
 * DB2 formato: 2025-07-23-14.52.34.126472
 * ISO formato:  2025-07-23T14:52:34.126Z
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
    
    // Se non � nel formato DB2, prova a vedere se � gi� in un formato valido
    if (strtotime($timestamp_str) !== false) {
        return date('c', strtotime($timestamp_str));
    }
    
    return null;
}

/**
 * Funzione per convertire date DB2 in formato ISO per JavaScript
 * DB2 formato date: 23/07/25 o simili
 */
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

/**
 * Ottiene dettaglio completo di un ticket - VERSIONE CORRETTA CON CONVERSIONE DATE DB2
 */
function getTicketDetail($conn, $ticket_id) {
	
	//error_log("=== getTicketDetail START ===");
	//    error_log("Ticket ID richiesto: " . $ticket_id);
    
	
    // Verifica permessi CQ prima di procedere
    $current_user = getCurrentUser();
    if ($current_user['livello'] === 'CQ') {
        if (!canCQViewTicket($ticket_id, $current_user['username'])) {
            error_log("getTicketDetail: CQ user " . $current_user['username'] . " denied for ticket " . $ticket_id);
            return false;
        }
    }
    
    // Query ticket principale - usa query diretta
    $ticket_id_safe = (int)$ticket_id;
    $ticket_sql = "SELECT * FROM WTICKET WHERE NUM_TICKET = " . $ticket_id_safe;
    
    $ticket_stmt = db2_exec($conn, $ticket_sql);
    if (!$ticket_stmt) {
       // error_log("getTicketDetail: Query ticket fallita - " . db2_conn_errormsg($conn));
        return false;
    }
    
    $ticket = db2_fetch_assoc($ticket_stmt);
    if (!$ticket) {
       // error_log("getTicketDetail: Ticket " . $ticket_id . " non trovato");
        return false;
    }
	
	//error_log("Ticket trovato: " . print_r($ticket, true));

    // Query allegati - usa query diretta
    $files_sql = "SELECT * FROM WALLEGATI WHERE NUM_TICKET = " . $ticket_id_safe . " ORDER BY DATA_UPLOAD";
    $files_stmt = db2_exec($conn, $files_sql);
    
    $files = array();
    if ($files_stmt) {
        while ($file_row = db2_fetch_assoc($files_stmt)) {
            $files[] = array(
                'ID_ALLEGATO' => (int)$file_row['ID_ALLEGATO'],
                'NOME_ORIG' => trim($file_row['NOME_ORIG']),
                'NOME_FILE' => trim($file_row['NOME_FILE']),
                'TIPO_FILE' => trim($file_row['TIPO_FILE']),
                'DIMENSIONE' => (int)$file_row['DIMENSIONE'],
                'PATH_FILE' => trim($file_row['PATH_FILE']),
                'DATA_UPLOAD' => convertDb2TimestampToISO($file_row['DATA_UPLOAD']),
                'USERNAME_UPL' => trim($file_row['USERNAME_UPL'])
            );
        }
    }

    // Query per NAZIONE da PRODCHFI/TICKETT
    $nazione_sql = "SELECT TCDNAZ FROM PRODCHFI.TICKETT WHERE TNUMERO = " . $ticket_id_safe;
    $nazione_stmt = db2_exec($conn, $nazione_sql);
    $nazione = '';
    if ($nazione_stmt) {
        $nazione_row = db2_fetch_assoc($nazione_stmt);
        if ($nazione_row) {
            $nazione = trim($nazione_row['TCDNAZ']);
        }
    }
    
    // Query per DATA APERTURA TICKET (TTIPMOV = 'AP')
    $data_ap_sql = "SELECT TDATMOV FROM PRODCHFI.TICKETR WHERE TNUMERO = " . $ticket_id_safe . " AND TTIPMOV = 'AP'";
    $data_ap_stmt = db2_exec($conn, $data_ap_sql);
    $data_apertura = null;
    if ($data_ap_stmt) {
        $ap_row = db2_fetch_assoc($data_ap_stmt);
        if ($ap_row) {
            $data_apertura = $ap_row['TDATMOV'];
        }
    }
    
    // Query per DATA CONSEGNA CLIENTE (TTIPMOV = 'CP')
    $data_cp_sql = "SELECT TDATMOV FROM PRODCHFI.TICKETR WHERE TNUMERO = " . $ticket_id_safe . " AND TTIPMOV = 'CP'";
    $data_cp_stmt = db2_exec($conn, $data_cp_sql);
    $data_consegna = null;
    if ($data_cp_stmt) {
        $cp_row = db2_fetch_assoc($data_cp_stmt);
        if ($cp_row) {
            $data_consegna = $cp_row['TDATMOV'];
        }
    }
    // Calcola dimensione totale files
    $total_size = 0;
    foreach ($files as $file) {
        $total_size += (int)$file['DIMENSIONE'];
    }

    // Formatta risultato con conversione date
    $result = array(
        'ticket' => array(
            'NUM_TICKET' => (int)$ticket['NUM_TICKET'],
            'NUM_CARTEL' => (int)$ticket['NUM_CARTEL'],
            'COD_PRODUT' => trim($ticket['COD_PRODUT']),
            'RAG_SOC_PR' => trim($ticket['RAG_SOC_PR']),
            'COD_ISPETT' => trim($ticket['COD_ISPETT']),
            'NOME_ISPET' => trim($ticket['NOME_ISPET']),
            'DATA_REG' => convertDb2DateToISO($ticket['DATA_REG']),
            'ANNO_TICKET' => (int)$ticket['ANNO_TICKET'],
            'USERNAME_INS' => trim($ticket['USERNAME_INS']),
            'DATA_INS' => convertDb2TimestampToISO($ticket['DATA_INS']),
            'DATA_MOD' => convertDb2TimestampToISO($ticket['DATA_MOD']),
            'USERNAME_MOD' => $ticket['USERNAME_MOD'] ? trim($ticket['USERNAME_MOD']) : null,
            'NAZIONE' => $nazione,
            'DATA_APERTURA' => $data_apertura,
            'DATA_CONSEGNA' => $data_consegna
        ),
        'files' => $files,
        'stats' => array(
            'file_count' => count($files),
            'total_size' => $total_size
        )
    );

   // error_log("getTicketDetail: Returning ticket " . $ticket_id . " with " . count($files) . " files");
    
    return $result;
}



db2_close($conn);

?>