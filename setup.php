<?php
/**
 * SETUP INIZIALE SISTEMA GESTIONE TICKET POST-VENDITA
 * Questo file crea tutte le tabelle necessarie nella libreria WEBPVEND
 */

require_once 'config.php';

// Funzione per eseguire SQL - VERSIONE MIGLIORATA come install.php
function executeSQL($conn, $sql, $description) {
    $result = db2_exec($conn, $sql);
    if (!$result) {
        $error_msg = db2_stmt_errormsg();
        
        // Se l'errore ├¿ "already exists", non ├¿ un problema
        if (strpos($error_msg, 'already exists') !== false || 
            strpos($error_msg, 'duplicate') !== false) {
            return true; // Considera come successo
        }
        
        throw new Exception("Errore $description: " . $error_msg);
    }
    return $result;
}

function createTables() {
    $conn = getDb2Connection();
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $tables_created = array();
    $errors = array();
    
    // 1. TABELLA UTENTI
    $sql_wutenti = "
    CREATE TABLE WUTENTI (
        USERNAME CHAR(10) NOT NULL,
        PASSWORD VARCHAR(50) NOT NULL,
        NOME_COMPL VARCHAR(50) NOT NULL,
        LIVELLO CHAR(10) NOT NULL DEFAULT 'USER',
        ATTIVO CHAR(1) NOT NULL DEFAULT 'S',
        ULTIMO_ACC TIMESTAMP,
        DATA_CREAZ TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT PK_WUTENTI PRIMARY KEY (USERNAME)
    )";
    
    try {
        executeSQL($conn, $sql_wutenti, "creazione tabella WUTENTI");
        $tables_created[] = 'WUTENTI creata con successo';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            $tables_created[] = 'WUTENTI gi├á esistente';
        } else {
            $errors[] = 'WUTENTI: ' . $e->getMessage();
        }
    }
    
    // 2. TABELLA SESSIONI
    $sql_wsessioni = "
    CREATE TABLE WSESSIONI (
        SESSION_ID CHAR(32) NOT NULL,
        USERNAME CHAR(10) NOT NULL,
        EXPIRES_AT TIMESTAMP NOT NULL,
        IP_ADDRESS CHAR(15),
        USER_AGENT VARCHAR(500),
        DATA_CREAZ TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT PK_WSESSIONI PRIMARY KEY (SESSION_ID)
    )";
    
    try {
        executeSQL($conn, $sql_wsessioni, "creazione tabella WSESSIONI");
        $tables_created[] = 'WSESSIONI creata con successo';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            $tables_created[] = 'WSESSIONI gi├á esistente';
        } else {
            $errors[] = 'WSESSIONI: ' . $e->getMessage();
        }
    }
    
    // 3. TABELLA TICKET PRINCIPALI
    $sql_wticket = "
    CREATE TABLE WTICKET (
        NUM_TICKET DECIMAL(7,0) NOT NULL,
        NUM_CARTEL DECIMAL(7,0) NOT NULL,
        COD_PRODUT CHAR(6) NOT NULL,
        RAG_SOC_PR VARCHAR(35) NOT NULL,
        COD_ISPETT CHAR(6) NOT NULL,
        NOME_ISPET VARCHAR(35) NOT NULL,
        DATA_REG DATE NOT NULL,
        ANNO_TICKET DECIMAL(4,0) NOT NULL,
        USERNAME_INS CHAR(10) NOT NULL,
        DATA_INS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        DATA_MOD TIMESTAMP,
        USERNAME_MOD CHAR(10),
        CONSTRAINT PK_WTICKET PRIMARY KEY (NUM_TICKET)
    )";
    
    try {
        executeSQL($conn, $sql_wticket, "creazione tabella WTICKET");
        $tables_created[] = 'WTICKET creata con successo';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            $tables_created[] = 'WTICKET gi├á esistente';
        } else {
            $errors[] = 'WTICKET: ' . $e->getMessage();
        }
    }
    
    // 4. TABELLA ALLEGATI
    $sql_wallegati = "
    CREATE TABLE WALLEGATI (
        ID_ALLEGATO INTEGER GENERATED ALWAYS AS IDENTITY,
        NUM_TICKET DECIMAL(7,0) NOT NULL,
        NOME_ORIG VARCHAR(100) NOT NULL,
        NOME_FILE VARCHAR(100) NOT NULL,
        TIPO_FILE CHAR(10) NOT NULL,
        DIMENSIONE DECIMAL(15,0) NOT NULL,
        PATH_FILE VARCHAR(200) NOT NULL,
        DATA_UPLOAD TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        USERNAME_UPL CHAR(10) NOT NULL,
        CONSTRAINT PK_WALLEGATI PRIMARY KEY (ID_ALLEGATO)
    )";
    
    try {
        executeSQL($conn, $sql_wallegati, "creazione tabella WALLEGATI");
        $tables_created[] = 'WALLEGATI creata con successo';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            $tables_created[] = 'WALLEGATI gi├á esistente';
        } else {
            $errors[] = 'WALLEGATI: ' . $e->getMessage();
        }
    }
    
    // 5. TABELLA LOG ATTIVITA
    $sql_wlogatt = "
    CREATE TABLE WLOGATT (
        ID_LOG INTEGER GENERATED ALWAYS AS IDENTITY,
        AZIONE VARCHAR(50) NOT NULL,
        DETTAGLI VARCHAR(500),
        USERNAME CHAR(10),
        IP_ADDRESS CHAR(15),
        DATA_ORA TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT PK_WLOGATT PRIMARY KEY (ID_LOG)
    )";
    
    try {
        executeSQL($conn, $sql_wlogatt, "creazione tabella WLOGATT");
        $tables_created[] = 'WLOGATT creata con successo';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            $tables_created[] = 'WLOGATT gi├á esistente';
        } else {
            $errors[] = 'WLOGATT: ' . $e->getMessage();
        }
    }
    
    // 6. TABELLA STATISTICHE GIORNALIERE
    $sql_wstatgior = "
    CREATE TABLE WSTATGIOR (
        DATA_STAT DATE NOT NULL,
        UPLOAD_TOT DECIMAL(10,0) DEFAULT 0,
        RICERCA_TOT DECIMAL(10,0) DEFAULT 0,
        TOTALE_MB DECIMAL(15,2) DEFAULT 0,
        CONSTRAINT PK_WSTATGIOR PRIMARY KEY (DATA_STAT)
    )";
    
    try {
        executeSQL($conn, $sql_wstatgior, "creazione tabella WSTATGIOR");
        $tables_created[] = 'WSTATGIOR creata con successo';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            $tables_created[] = 'WSTATGIOR gi├á esistente';
        } else {
            $errors[] = 'WSTATGIOR: ' . $e->getMessage();
        }
    }
    
    db2_close($conn);
    
    return array('created' => $tables_created, 'errors' => $errors);
}

function createIndexes() {
    $conn = getDb2Connection();
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $indexes_created = array();
    $errors = array();
    
    // Indici per WTICKET
    $indexes = array(
        'WTICKET_01' => "CREATE INDEX WTICKET_01 ON WTICKET (ANNO_TICKET)",
        'WTICKET_02' => "CREATE INDEX WTICKET_02 ON WTICKET (DATA_REG)", 
        'WTICKET_03' => "CREATE INDEX WTICKET_03 ON WTICKET (COD_PRODUT)",
        'WTICKET_04' => "CREATE INDEX WTICKET_04 ON WTICKET (COD_ISPETT)",
        'WTICKET_05' => "CREATE INDEX WTICKET_05 ON WTICKET (RAG_SOC_PR)",
        'WTICKET_06' => "CREATE INDEX WTICKET_06 ON WTICKET (NUM_CARTEL)",
        
        // Indici per WALLEGATI
        'WALLEGATI_01' => "CREATE INDEX WALLEGATI_01 ON WALLEGATI (NUM_TICKET)",
        'WALLEGATI_02' => "CREATE INDEX WALLEGATI_02 ON WALLEGATI (DATA_UPLOAD)",
        'WALLEGATI_03' => "CREATE INDEX WALLEGATI_03 ON WALLEGATI (USERNAME_UPL)",
        
        // Indici per WSESSIONI
        'WSESSIONI_01' => "CREATE INDEX WSESSIONI_01 ON WSESSIONI (USERNAME)",
        'WSESSIONI_02' => "CREATE INDEX WSESSIONI_02 ON WSESSIONI (EXPIRES_AT)",
        
        // Indici per WLOGATT
        'WLOGATT_01' => "CREATE INDEX WLOGATT_01 ON WLOGATT (USERNAME)",
        'WLOGATT_02' => "CREATE INDEX WLOGATT_02 ON WLOGATT (DATA_ORA)",
        'WLOGATT_03' => "CREATE INDEX WLOGATT_03 ON WLOGATT (AZIONE)"
    );
    
    foreach ($indexes as $index_name => $index_sql) {
        try {
            executeSQL($conn, $index_sql, "creazione indice $index_name");
            $indexes_created[] = "$index_name creato con successo";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                $indexes_created[] = "$index_name gi├á esistente";
            } else {
                $errors[] = "$index_name: " . $e->getMessage();
            }
        }
    }
    
    db2_close($conn);
    
    return array('created' => $indexes_created, 'errors' => $errors);
}

function insertDefaultData() {
    $conn = getDb2Connection();
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    $inserts_ok = array();
    $errors = array();
    
    $users = array(
        array('ADMIN', 'admin123', 'AMMINISTRATORE SISTEMA', 'ADMIN'),
        array('BACKOFF', 'back123', 'UTENTE BACKOFFICE', 'BACKOFFICE'),
        array('USER', 'user123', 'UTENTE NORMALE', 'USER'),
        array('POSTVEN', 'post123', 'UFFICIO POST-VENDITA', 'BACKOFFICE'),
        array('QUALITA', 'qual123', 'CONTROLLO QUALITA', 'USER')
    );
    
    foreach ($users as $user) {
        $sql = "INSERT INTO WUTENTI 
                (USERNAME, PASSWORD, NOME_COMPL, LIVELLO, ATTIVO) 
                VALUES (?, ?, ?, ?, 'S')";
        
        try {
            $stmt = db2_prepare($conn, $sql);
            if (db2_execute($stmt, $user)) {
                $inserts_ok[] = "Utente {$user[0]} creato con successo";
            } else {
                $errors[] = "Errore creazione utente {$user[0]}";
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'duplicate') !== false) {
                $inserts_ok[] = "Utente {$user[0]} gi├á esistente";
            } else {
                $errors[] = "Errore utente {$user[0]}: " . $e->getMessage();
            }
        }
    }
    
    db2_close($conn);
    
    return array('inserted' => $inserts_ok, 'errors' => $errors);
}

function testConnection() {
    try {
        $conn = getDb2Connection();
        $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
        
        if (db2_exec($conn, $set_schema_sql)) {
            db2_close($conn);
            return array('success' => true, 'message' => 'Connessione DB2 OK');
        } else {
            db2_close($conn);
            return array('success' => false, 'message' => 'Errore impostazione schema: ' . db2_conn_errormsg());
        }
    } catch (Exception $e) {
        return array('success' => false, 'message' => 'Errore connessione: ' . $e->getMessage());
    }
}

function createUploadDirectories() {
    $base_dir = BASE_UPLOAD_DIR;
    $current_year = date('Y');
    $dirs_created = array();
    $errors = array();
    
    // Crea directory base se non esiste
    if (!file_exists($base_dir)) {
        if (mkdir($base_dir, 0755, true)) {
            $dirs_created[] = $base_dir;
        } else {
            $errors[] = "Impossibile creare directory base: $base_dir";
            return array('created' => $dirs_created, 'errors' => $errors);
        }
    }
    
    // Crea directory per anno corrente e prossimi 2 anni
    for ($year = $current_year; $year <= $current_year + 2; $year++) {
        $year_dir = $base_dir . $year;
        if (!file_exists($year_dir)) {
            if (mkdir($year_dir, 0755)) {
                $dirs_created[] = $year_dir;
            } else {
                $errors[] = "Impossibile creare directory: $year_dir";
            }
        } else {
            $dirs_created[] = "$year_dir (gi├á esistente)";
        }
    }
    
    return array('created' => $dirs_created, 'errors' => $errors);
}

// Esecuzione setup se chiamato direttamente
if (basename($_SERVER['PHP_SELF']) == 'setup.php') {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<html><head><title>Setup Sistema Post-Vendita</title></head><body>";
    echo "<h1>Setup Sistema Gestione Ticket Post-Vendita</h1>";
    echo "<pre>";
    
    echo "\n=== TEST CONNESSIONE ===\n";
    $test_result = testConnection();
    if ($test_result['success']) {
        echo "Ô£ô " . $test_result['message'] . "\n";
    } else {
        echo "Ô£ù " . $test_result['message'] . "\n";
        echo "\nSetup interrotto per errore connessione.\n";
        echo "</pre></body></html>";
        exit;
    }
    
    echo "\n=== CREAZIONE TABELLE ===\n";
    $tables_result = createTables();
    foreach ($tables_result['created'] as $table) {
        echo "Ô£ô Tabella creata: $table\n";
    }
    foreach ($tables_result['errors'] as $error) {
        echo "Ô£ù Errore: $error\n";
    }
    
    echo "\n=== CREAZIONE INDICI ===\n";
    $indexes_result = createIndexes();
    foreach ($indexes_result['created'] as $index) {
        echo "Ô£ô Indice creato: $index\n";
    }
    foreach ($indexes_result['errors'] as $error) {
        echo "Ô£ù Errore: $error\n";
    }
    
    echo "\n=== INSERIMENTO DATI DEFAULT ===\n";
    $data_result = insertDefaultData();
    foreach ($data_result['inserted'] as $insert) {
        echo "Ô£ô $insert\n";
    }
    foreach ($data_result['errors'] as $error) {
        echo "Ô£ù Errore: $error\n";
    }
    
    echo "\n=== CREAZIONE DIRECTORY UPLOAD ===\n";
    $dirs_result = createUploadDirectories();
    foreach ($dirs_result['created'] as $dir) {
        echo "Ô£ô Directory: $dir\n";
    }
    foreach ($dirs_result['errors'] as $error) {
        echo "Ô£ù Errore: $error\n";
    }
    
    echo "\n=== SETUP COMPLETATO ===\n";
    echo "Sistema pronto per l'uso!\n";
    echo "\nUtenti di test creati:\n";
    echo "- ADMIN / admin123 (livello ADMIN)\n";
    echo "- BACKOFF / back123 (livello BACKOFFICE)\n";
    echo "- USER / user123 (livello USER)\n";
    
    echo "</pre></body></html>";
}
?>