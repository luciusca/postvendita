<?php
/**
 * SESSION_HANDLER_DB2.PHP - Gestione sessioni su database DB2
 * Versione FINALE che funziona con iSeries
 */

class DB2SessionHandler {
    
    private $conn = null;
    private $schema_set = false;
    
    public function open($save_path, $session_name) {
      //  error_log("DB2SessionHandler::open called");
        
        // USA CONNESSIONE DIRETTA
        $this->conn = db2_connect('S78CCD90', 'PERS', 'PERS');
        
        if (!$this->conn) {
          //  error_log("DB2SessionHandler::open - Connection failed: " . db2_conn_errormsg());
            return false;
        }
        
        // IMPOSTA SCHEMA A WEBPVEND - CONFERMATO CHE FUNZIONA DAL TEST
        $set_schema = "SET SCHEMA WEBPVEND";
        $result = db2_exec($this->conn, $set_schema);
        
        if ($result) {
            $this->schema_set = true;
          //  error_log("DB2SessionHandler::open - Schema set to WEBPVEND successfully");
        } else {
          //  error_log("DB2SessionHandler::open - Failed to set schema: " . db2_stmt_errormsg());
            $this->schema_set = false;
        }
        
        return true;
    }
    
    public function close() {
       // error_log("DB2SessionHandler::close called");
        if ($this->conn) {
            db2_close($this->conn);
            $this->conn = null;
            $this->schema_set = false;
        }
        return true;
    }
    
    public function read($session_id) {
    //    error_log("DB2SessionHandler::read called for session: " . $session_id);
        
        if (!$this->conn) {
            $this->open('', '');
        }
        
        // Riassicura che lo schema sia WEBPVEND
        if (!$this->schema_set) {
            $set_schema = "SET SCHEMA WEBPVEND";
            $result = db2_exec($this->conn, $set_schema);
            if ($result) {
                $this->schema_set = true;
            }
        }
        
        $session_id_safe = str_replace("'", "''", $session_id);
        
        // Usa query SENZA schema perché abbiamo già impostato WEBPVEND
        $sql = "SELECT SESSION_DATA FROM WSESSIONS WHERE SESSION_ID = '$session_id_safe'";
        
    //    error_log("DB2SessionHandler::read - SQL: " . $sql);
        
        $stmt = db2_exec($this->conn, $sql);
        
        if (!$stmt) {
     //       error_log("DB2SessionHandler::read - Query failed: " . db2_stmt_errormsg());
            
            // Se fallisce, prova con schema esplicito
            $sql2 = "SELECT SESSION_DATA FROM WEBPVEND.WSESSIONS WHERE SESSION_ID = '$session_id_safe'";
     //       error_log("DB2SessionHandler::read - Retrying with full name: " . $sql2);
            $stmt = db2_exec($this->conn, $sql2);
            
            if (!$stmt) {
     //           error_log("DB2SessionHandler::read - Still failed: " . db2_stmt_errormsg());
                return '';
            }
        }
        
        if ($row = db2_fetch_assoc($stmt)) {
     //       error_log("DB2SessionHandler::read - Found session data");
            return $row['SESSION_DATA'];
        }
        
    //    error_log("DB2SessionHandler::read - No session found");
        return '';
    }
    
    public function write($session_id, $session_data) {
    //    error_log("DB2SessionHandler::write called for session: " . $session_id);
        
        if (empty($session_data)) {
    //        error_log("DB2SessionHandler::write - Empty data, skipping");
            return true;
        }
        
   //     error_log("DB2SessionHandler::write - Data to save: " . substr($session_data, 0, 100) . "...");
        
        if (!$this->conn) {
            $this->open('', '');
        }
        
        // Riassicura che lo schema sia WEBPVEND
        if (!$this->schema_set) {
            $set_schema = "SET SCHEMA WEBPVEND";
            $result = db2_exec($this->conn, $set_schema);
            if ($result) {
                $this->schema_set = true;
            }
        }
        
        $session_id_safe = str_replace("'", "''", $session_id);
        $session_data_safe = str_replace("'", "''", $session_data);
        
        // Prima verifica se esiste - USA QUERY SENZA SCHEMA
        $check_sql = "SELECT COUNT(*) as CNT FROM WSESSIONS WHERE SESSION_ID = '$session_id_safe'";
        
    //    error_log("DB2SessionHandler::write - Check SQL: " . $check_sql);
        
        $check_stmt = db2_exec($this->conn, $check_sql);
        
        if (!$check_stmt) {
      //      error_log("DB2SessionHandler::write - Check failed, trying with full name");
            // Prova con nome completo
            $check_sql = "SELECT COUNT(*) as CNT FROM WEBPVEND.WSESSIONS WHERE SESSION_ID = '$session_id_safe'";
            $check_stmt = db2_exec($this->conn, $check_sql);
            
            if (!$check_stmt) {
      //          error_log("DB2SessionHandler::write - Check failed: " . db2_stmt_errormsg());
                return false;
            }
        }
        
        $check_row = db2_fetch_assoc($check_stmt);
        $exists = ($check_row && $check_row['CNT'] > 0);
        
        if ($exists) {
            // UPDATE - prima prova senza schema
            $sql = "UPDATE WSESSIONS 
                    SET SESSION_DATA = '$session_data_safe',
                        EXPIRES_AT = CURRENT_TIMESTAMP + 1 HOURS,
                        LAST_ACCESS = CURRENT_TIMESTAMP
                    WHERE SESSION_ID = '$session_id_safe'";
            
      //      error_log("DB2SessionHandler::write - Updating existing session");
        } else {
            // INSERT - prima prova senza schema
            $sql = "INSERT INTO WSESSIONS (SESSION_ID, SESSION_DATA, EXPIRES_AT, LAST_ACCESS) 
                    VALUES ('$session_id_safe', '$session_data_safe', 
                            CURRENT_TIMESTAMP + 1 HOURS, CURRENT_TIMESTAMP)";
            
      //      error_log("DB2SessionHandler::write - Inserting new session");
        }
        
        $result = db2_exec($this->conn, $sql);
        
        if (!$result) {
      //      error_log("DB2SessionHandler::write - First attempt failed, trying with full name");
            
            // Riprova con nome completo
            if ($exists) {
                $sql = "UPDATE WEBPVEND.WSESSIONS 
                        SET SESSION_DATA = '$session_data_safe',
                            EXPIRES_AT = CURRENT_TIMESTAMP + 1 HOURS,
                            LAST_ACCESS = CURRENT_TIMESTAMP
                        WHERE SESSION_ID = '$session_id_safe'";
            } else {
                $sql = "INSERT INTO WEBPVEND.WSESSIONS (SESSION_ID, SESSION_DATA, EXPIRES_AT, LAST_ACCESS) 
                        VALUES ('$session_id_safe', '$session_data_safe', 
                                CURRENT_TIMESTAMP + 1 HOURS, CURRENT_TIMESTAMP)";
            }
            
            $result = db2_exec($this->conn, $sql);
            
            if (!$result) {
       //         error_log("DB2SessionHandler::write - Execute failed: " . db2_stmt_errormsg());
                return false;
            }
        }
        
      //  error_log("DB2SessionHandler::write - Session saved successfully");
        return true;
    }
    
    public function destroy($session_id) {
     //   error_log("DB2SessionHandler::destroy called for session: " . $session_id);
        
        if (!$this->conn) {
            $this->open('', '');
        }
        
        // Riassicura che lo schema sia WEBPVEND
        if (!$this->schema_set) {
            $set_schema = "SET SCHEMA WEBPVEND";
            $result = db2_exec($this->conn, $set_schema);
            if ($result) {
                $this->schema_set = true;
            }
        }
        
        $session_id_safe = str_replace("'", "''", $session_id);
        
        // Prima prova senza schema
        $sql = "DELETE FROM WSESSIONS WHERE SESSION_ID = '$session_id_safe'";
        
        $result = db2_exec($this->conn, $sql);
        
        if (!$result) {
            // Riprova con nome completo
            $sql = "DELETE FROM WEBPVEND.WSESSIONS WHERE SESSION_ID = '$session_id_safe'";
            $result = db2_exec($this->conn, $sql);
        }
        
    //    error_log("DB2SessionHandler::destroy - " . ($result ? "Success" : "Failed: " . db2_stmt_errormsg()));
        return true;
    }
    
    public function gc($maxlifetime) {
   //     error_log("DB2SessionHandler::gc called");
        
        if (!$this->conn) {
            $this->open('', '');
        }
        
        // Riassicura che lo schema sia WEBPVEND
        if (!$this->schema_set) {
            $set_schema = "SET SCHEMA WEBPVEND";
            $result = db2_exec($this->conn, $set_schema);
            if ($result) {
                $this->schema_set = true;
            }
        }
        
        // Prima prova senza schema
        $sql = "DELETE FROM WSESSIONS WHERE EXPIRES_AT < CURRENT_TIMESTAMP";
        
        $result = db2_exec($this->conn, $sql);
        
        if (!$result) {
            // Riprova con nome completo
            $sql = "DELETE FROM WEBPVEND.WSESSIONS WHERE EXPIRES_AT < CURRENT_TIMESTAMP";
            db2_exec($this->conn, $sql);
        }
        
        return true;
    }
}

/**
 * Registra handler DB2 per le sessioni
 */
function registerDB2SessionHandler() {
   // error_log("registerDB2SessionHandler - Starting registration");
    
    $handler = new DB2SessionHandler();
    
    $result = session_set_save_handler(
        array($handler, 'open'),
        array($handler, 'close'),
        array($handler, 'read'),
        array($handler, 'write'),
        array($handler, 'destroy'),
        array($handler, 'gc')
    );
    
    register_shutdown_function('session_write_close');
    
//    error_log("registerDB2SessionHandler - Handler registered: " . ($result ? "SUCCESS" : "FAILED"));
    
    return $result;
}
?>