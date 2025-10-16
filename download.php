<?php
/**
 * DOWNLOAD.PHP - Download file allegati con preview migliorata
 * Sistema Gestione Ticket Post-Vendita - VERSIONE COMPATIBILE PHP 5.2
 */

require_once 'init_firefox.php';
require_once 'config.php';

// Per Firefox, accetta anche il session ID via GET come fallback
if (isset($_GET['sid']) && !isset($_COOKIE['PHPSESSID'])) {
    session_id($_GET['sid']);
}

// Avvia sessione
startSessionSafe();

// Verifica autenticazione
if (!isValidSession()) {
    // Per download, non restituire JSON ma un messaggio HTML
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<html><body><h1>Errore</h1><p>Sessione scaduta. <a href="login.html">Effettua il login</a></p></body></html>';
    exit;
}
// Verifica metodo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    die('Metodo non consentito');
}

try {
    // Recupera parametri
    $file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $ticket_id = isset($_GET['ticket']) ? (int)$_GET['ticket'] : 0;
    $action = isset($_GET['action']) ? $_GET['action'] : 'download';
    
    // Validazione parametri
    if ($file_id <= 0 && $ticket_id <= 0) {
        header('HTTP/1.1 400 Bad Request');
        die('Parametri non validi');
    }
    
    $conn = getDb2Connection();
    $set_schema_sql = "SET SCHEMA " . DB2_LIBRARY;
    db2_exec($conn, $set_schema_sql);
    
    if ($action === 'download' && $file_id > 0) {
        // Download singolo file
        downloadSingleFile($conn, $file_id);
        
    } elseif ($action === 'zip' && $ticket_id > 0) {
        // Download tutti i file di un ticket come ZIP
        downloadTicketAsZip($conn, $ticket_id);
        
    } elseif ($action === 'preview' && $file_id > 0) {
        // Preview file - VERSIONE CORRETTA E MIGLIORATA
        handlePreviewFile($conn, $file_id);
        
    } else {
        header('HTTP/1.1 400 Bad Request');
        die('Azione non valida');
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        db2_close($conn);
    }
    
    error_log('Errore download: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    die('Errore interno del server');
}

/**
 * Download di un singolo file
 */
function downloadSingleFile($conn, $file_id) {
    // Recupera informazioni file
    $sql = "SELECT a.*, t.NUM_TICKET 
            FROM WALLEGATI a 
            INNER JOIN WTICKET t ON a.NUM_TICKET = t.NUM_TICKET 
            WHERE a.ID_ALLEGATO = ?";
    
    $stmt = db2_prepare($conn, $sql);
    
    if (!db2_execute($stmt, array($file_id))) {
        header('HTTP/1.1 404 Not Found');
        die('File non trovato');
    }
    
    $file_info = db2_fetch_assoc($stmt);
    
    if (!$file_info) {
        header('HTTP/1.1 404 Not Found');
        die('File non trovato');
    }
    
    // Costruisce path completo
    $file_path = BASE_UPLOAD_DIR . trim($file_info['PATH_FILE']);
    
    // Verifica esistenza file
    if (!file_exists($file_path)) {
        header('HTTP/1.1 404 Not Found');
        die('File fisico non trovato');
    }
    
    // Verifica sicurezza path (prevenire directory traversal)
    $real_base = realpath(BASE_UPLOAD_DIR);
    $real_file = realpath($file_path);
    
    if (!$real_file || strpos($real_file, $real_base) !== 0) {
        header('HTTP/1.1 403 Forbidden');
        die('Accesso negato');
    }
    
    // Log download
    $current_user = getCurrentUser();
    logActivity('FILE_DOWNLOAD', "File ID: $file_id, Ticket: {$file_info['NUM_TICKET']}, File: {$file_info['NOME_ORIG']}");
    
    // Imposta headers per download
    $original_name = trim($file_info['NOME_ORIG']);
    $file_size = filesize($file_path);
    $mime_type = getMimeType($file_path, $original_name);
    
    // Headers sicurezza
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Headers download
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . $file_size);
    header('Content-Disposition: attachment; filename="' . sanitizeFilename($original_name) . '"');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Pulisce output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Invia file
    readfile($file_path);
    
    db2_close($conn);
    exit;
}

/**
 * Download di tutti i file di un ticket come ZIP
 */
function downloadTicketAsZip($conn, $ticket_id) {
    // Verifica se ZipArchive è disponibile
    if (!class_exists('ZipArchive')) {
        header('HTTP/1.1 500 Internal Server Error');
        die('Funzionalità ZIP non disponibile');
    }
    
    // Recupera informazioni ticket e file
    $ticket_sql = "SELECT NUM_TICKET, RAG_SOC_PR FROM WTICKET WHERE NUM_TICKET = ?";
    $ticket_stmt = db2_prepare($conn, $ticket_sql);
    
    if (!db2_execute($ticket_stmt, array($ticket_id))) {
        header('HTTP/1.1 404 Not Found');
        die('Ticket non trovato');
    }
    
    $ticket_info = db2_fetch_assoc($ticket_stmt);
    
    if (!$ticket_info) {
        header('HTTP/1.1 404 Not Found');
        die('Ticket non trovato');
    }
    
    // Recupera file allegati
    $files_sql = "SELECT ID_ALLEGATO, NOME_ORIG, PATH_FILE, DIMENSIONE 
                  FROM WALLEGATI 
                  WHERE NUM_TICKET = ? 
                  ORDER BY DATA_UPLOAD";
    
    $files_stmt = db2_prepare($conn, $files_sql);
    
    if (!db2_execute($files_stmt, array($ticket_id))) {
        header('HTTP/1.1 500 Internal Server Error');
        die('Errore recupero file');
    }
    
    $files = array();
    while ($file_row = db2_fetch_assoc($files_stmt)) {
        $files[] = $file_row;
    }
    
    if (empty($files)) {
        header('HTTP/1.1 404 Not Found');
        die('Nessun file trovato per questo ticket');
    }
    
    // Crea file ZIP temporaneo
    $temp_zip = tempnam(sys_get_temp_dir(), 'ticket_' . $ticket_id . '_');
    $zip = new ZipArchive();
    
    if ($zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        header('HTTP/1.1 500 Internal Server Error');
        die('Errore creazione ZIP');
    }
    
    $files_added = 0;
    
    foreach ($files as $file) {
        $file_path = BASE_UPLOAD_DIR . trim($file['PATH_FILE']);
        
        // Verifica esistenza file
        if (!file_exists($file_path)) {
            continue;
        }
        
        // Verifica sicurezza path
        $real_base = realpath(BASE_UPLOAD_DIR);
        $real_file = realpath($file_path);
        
        if (!$real_file || strpos($real_file, $real_base) !== 0) {
            continue;
        }
        
        // Aggiunge file al ZIP con nome originale
        $original_name = trim($file['NOME_ORIG']);
        
        // Gestisce duplicati aggiungendo numero progressivo
        $zip_name = $original_name;
        $counter = 1;
        while ($zip->locateName($zip_name) !== false) {
            $path_info = pathinfo($original_name);
            $zip_name = $path_info['filename'] . '_' . $counter . '.' . $path_info['extension'];
            $counter++;
        }
        
        if ($zip->addFile($file_path, $zip_name)) {
            $files_added++;
        }
    }
    
    if ($files_added === 0) {
        $zip->close();
        unlink($temp_zip);
        header('HTTP/1.1 404 Not Found');
        die('Nessun file valido trovato');
    }
    
    // Aggiunge file informativo
    $info_content = "TICKET #" . $ticket_info['NUM_TICKET'] . "\n";
    $info_content .= "Produttore: " . trim($ticket_info['RAG_SOC_PR']) . "\n";
    $info_content .= "Data creazione ZIP: " . date('d/m/Y H:i:s') . "\n";
    $info_content .= "File inclusi: " . $files_added . "\n\n";
    $info_content .= "Sistema Gestione Ticket Post-Vendita\n";
    
    $zip->addFromString('_INFO_TICKET.txt', $info_content);
    
    $zip->close();
    
    // Log download
    $current_user = getCurrentUser();
    logActivity('TICKET_ZIP_DOWNLOAD', "Ticket: $ticket_id, File: $files_added");
    
    // Imposta headers per download ZIP
    $zip_filename = 'Ticket_' . $ticket_id . '_' . date('Ymd') . '.zip';
    $zip_size = filesize($temp_zip);
    
    // Headers sicurezza
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Headers download
    header('Content-Type: application/zip');
    header('Content-Length: ' . $zip_size);
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Pulisce output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Invia file ZIP
    readfile($temp_zip);
    
    // Pulizia
    unlink($temp_zip);
    db2_close($conn);
    exit;
}

/**
 * Gestione preview file (immagini e PDF) - VERSIONE COMPATIBILE PHP 5.2
 */
function handlePreviewFile($conn, $file_id) {
    try {
        if ($file_id <= 0) {
            header('HTTP/1.1 400 Bad Request');
            die('ID file non valido');
        }
        
        //error_log("PREVIEW: Richiesta preview per file ID: $file_id");
        
        // Recupera informazioni file con logging esteso
        $sql = "SELECT NOME_ORIG, PATH_FILE, TIPO_FILE, DIMENSIONE FROM WALLEGATI WHERE ID_ALLEGATO = ?";
        $stmt = db2_prepare($conn, $sql);
        
        if (!db2_execute($stmt, array($file_id))) {
            error_log("PREVIEW ERROR: Query fallita per file ID $file_id");
            header('HTTP/1.1 404 Not Found');
            die('File non trovato');
        }
        
        $file_info = db2_fetch_assoc($stmt);
        
        if (!$file_info) {
            error_log("PREVIEW ERROR: Nessun record trovato per file ID $file_id");
            header('HTTP/1.1 404 Not Found');
            die('File non trovato');
        }
        
        // DEBUG: Log completo delle informazioni file
        //error_log("PREVIEW DEBUG: File info - Nome: " . $file_info['NOME_ORIG'] . 
        //          ", Tipo: '" . $file_info['TIPO_FILE'] . "'" .
        //          ", Path: " . $file_info['PATH_FILE'] .
        //          ", Size: " . $file_info['DIMENSIONE']);
        
        // CORREZIONE PRINCIPALE: Pulizia e normalizzazione del tipo file - PHP 5.2 compatible
        $file_type_raw = isset($file_info['TIPO_FILE']) ? $file_info['TIPO_FILE'] : '';
        $file_type = strtoupper(trim(str_replace(array(' ', "\t", "\n", "\r"), '', $file_type_raw)));
        
        error_log("PREVIEW DEBUG: Tipo file - Raw: '$file_type_raw', Pulito: '$file_type'");
        
        // FALLBACK: Se il tipo dal DB non è affidabile, estrai dall'estensione
        if (empty($file_type) || strlen($file_type) > 10) {
            $filename = $file_info['NOME_ORIG'];
            $extension = '';
            if (strrpos($filename, '.') !== false) {
                $extension = strtoupper(substr($filename, strrpos($filename, '.') + 1));
            }
            
            if (!empty($extension)) {
                $file_type = $extension;
                error_log("PREVIEW FALLBACK: Tipo estratto da estensione: '$file_type'");
            }
        }
        
        // Lista estesa dei tipi supportati per preview
        $preview_image_types = array('JPG', 'JPEG', 'PNG', 'GIF', 'BMP', 'TIF', 'TIFF');
        $preview_pdf_types = array('PDF');
        
        $is_image = in_array($file_type, $preview_image_types);
        $is_pdf = in_array($file_type, $preview_pdf_types);
        $supports_preview = $is_image || $is_pdf;
        
       // error_log("PREVIEW LOGIC: Tipo='$file_type', IsImage=" . ($is_image ? 'true' : 'false') . 
       //           ", IsPdf=" . ($is_pdf ? 'true' : 'false') . 
       //           ", SupportsPreview=" . ($supports_preview ? 'true' : 'false'));
        
        if (!$supports_preview) {
            error_log("PREVIEW ERROR: Tipo '$file_type' non supportato per preview");
            header('HTTP/1.1 400 Bad Request');
            die('Preview non disponibile per questo tipo di file: ' . $file_type);
        }
        
        // Costruisce path completo
        $file_path = BASE_UPLOAD_DIR . trim($file_info['PATH_FILE']);
        
       // error_log("PREVIEW DEBUG: Percorso file completo: $file_path");
        
        // Verifica esistenza e sicurezza
        if (!file_exists($file_path)) {
            error_log("PREVIEW ERROR: File fisico non trovato: $file_path");
            header('HTTP/1.1 404 Not Found');
            die('File fisico non trovato');
        }
        
        $real_base = realpath(BASE_UPLOAD_DIR);
        $real_file = realpath($file_path);
        
        if (!$real_file || strpos($real_file, $real_base) !== 0) {
            error_log("PREVIEW ERROR: Accesso negato per motivi di sicurezza - Base: $real_base, File: $real_file");
            header('HTTP/1.1 403 Forbidden');
            die('Accesso negato');
        }
        
        // Log dell'attività
        $current_user = getCurrentUser();
        logActivity('FILE_PREVIEW', "File ID: $file_id, File: {$file_info['NOME_ORIG']}, Tipo: $file_type");
        
        // Determina MIME type per preview
        $mime_type = getPreviewMimeType($file_path, $file_info['NOME_ORIG'], $file_type);
        
       // error_log("PREVIEW SUCCESS: Serving file - MIME: $mime_type, Size: " . filesize($file_path));
        
        // Headers per preview inline
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($file_path));
        header('Content-Disposition: inline; filename="' . sanitizeFilename($file_info['NOME_ORIG']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        
        // Headers aggiuntivi per PDF
        if ($is_pdf) {
            header('X-Frame-Options: SAMEORIGIN'); // Permette iframe nella stessa origine
        }
        
        // Pulisce output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Invia file
        readfile($file_path);
        
        db2_close($conn);
        exit;
        
    } catch (Exception $e) {
        if (isset($conn)) {
            db2_close($conn);
        }
        
        error_log('PREVIEW EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        header('HTTP/1.1 500 Internal Server Error');
        die('Errore interno del server durante preview');
    }
}

/**
 * Determina MIME type sicuro per download
 */
function getMimeType($file_path, $filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // MIME types sicuri basati su estensione
    $safe_mime_types = array(
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff'
    );
    
    if (isset($safe_mime_types[$extension])) {
        return $safe_mime_types[$extension];
    }
    
    // Fallback: usa finfo se disponibile
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        
        // Verifica che sia un MIME type sicuro
        $safe_mime_prefixes = array('image/', 'application/pdf');
        foreach ($safe_mime_prefixes as $prefix) {
            if (strpos($mime_type, $prefix) === 0) {
                return $mime_type;
            }
        }
    }
    
    // Default sicuro
    return 'application/octet-stream';
}

/**
 * Funzione per determinare MIME type per preview - VERSIONE COMPATIBILE PHP 5.2
 */
function getPreviewMimeType($file_path, $filename, $file_type) {
    $file_type_clean = strtolower(trim($file_type));
    
    // MIME types per preview - mappatura estesa
    $preview_mime_types = array(
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff'
    );
    
    if (isset($preview_mime_types[$file_type_clean])) {
     //   error_log("PREVIEW MIME: Mapped '$file_type_clean' to '{$preview_mime_types[$file_type_clean]}'");
        return $preview_mime_types[$file_type_clean];
    }
    
    // Fallback: usa finfo se disponibile (PHP 5.2 compatible)
    if (function_exists('finfo_open') && function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime_type = finfo_file($finfo, $file_path);
            finfo_close($finfo);
            
            if ($mime_type) {
          //      error_log("PREVIEW MIME: finfo detected '$mime_type' for '$filename'");
                
                // Verifica che sia un MIME type sicuro per preview
                $safe_mime_prefixes = array('image/', 'application/pdf');
                foreach ($safe_mime_prefixes as $prefix) {
                    if (strpos($mime_type, $prefix) === 0) {
                        return $mime_type;
                    }
                }
            }
        }
    }
    
   // error_log("PREVIEW MIME: Fallback to octet-stream for '$filename' (type: $file_type)");
    
    // Default sicuro
    return 'application/octet-stream';
}

/**
 * Sanitizza nome file per download sicuro - PHP 5.2 compatible
 */
function sanitizeFilename($filename) {
    // Rimuove caratteri pericolosi - PHP 5.2 compatible
    $filename = preg_replace('/[^a-zA-Z0-9._\-\s]/', '', $filename);
    
    // Limita lunghezza
    if (strlen($filename) > 200) {
        $path_info = pathinfo($filename);
        $name = substr($path_info['filename'], 0, 190);
        $filename = $name . '.' . $path_info['extension'];
    }
    
    // Rimuove spazi multipli
    $filename = preg_replace('/\s+/', ' ', $filename);
    
    return trim($filename);
}
?>