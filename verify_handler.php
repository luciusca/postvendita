<?php
/**
 * VERIFY_HANDLER.PHP - Verifica che l'handler sia registrato correttamente
 */

echo "<h1>Verifica Handler DB2</h1>";

// 1. Browser check
echo "<h2>1. Browser</h2>";
$is_firefox = (stripos($_SERVER['HTTP_USER_AGENT'], 'firefox') !== false);
echo "<p>User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "</p>";
echo "<p><strong>Firefox: " . ($is_firefox ? "YES" : "NO") . "</strong></p>";

// 2. Include config (questo dovrebbe registrare l'handler per Firefox)
echo "<h2>2. Include config.php</h2>";
require_once 'config.php';
echo "<p>config.php incluso</p>";

// 3. Verifica handler registrato
echo "<h2>3. Handler Status</h2>";
$handlers = session_get_save_handler();
echo "<p>Current save handler: <strong>" . $handlers . "</strong></p>";

if ($handlers == 'user') {
    echo "<p style='color: green;'>✓ Custom handler registrato!</p>";
} else {
    echo "<p style='color: red;'>✗ Handler di default (files), NON sta usando DB2!</p>";
}

// 4. Test sessione
echo "<h2>4. Session Test</h2>";
startSessionSafe();
$sid = session_id();
echo "<p>Session ID: <strong>$sid</strong></p>";

// Salva test data
$_SESSION['verify_test'] = 'TEST_' . time();
$_SESSION['timestamp'] = date('Y-m-d H:i:s');

echo "<p>Data saved to session</p>";

// Forza scrittura
session_write_close();
echo "<p>session_write_close() executed</p>";

// 5. Verifica nel DB2
echo "<h2>5. Database Check</h2>";
$conn = db2_connect('S78CCD90', 'PERS', 'PERS');
if ($conn) {
    $sql = "SELECT * FROM WEBPVEND.WSESSIONS WHERE SESSION_ID = '$sid'";
    $stmt = db2_exec($conn, $sql);
    
    if ($stmt && $row = db2_fetch_assoc($stmt)) {
        echo "<p style='color: green; font-size: 20px;'>✓ SESSIONE TROVATA NEL DB2!</p>";
        echo "<p>Handler funziona correttamente!</p>";
    } else {
        echo "<p style='color: red; font-size: 20px;'>✗ SESSIONE NON NEL DB2!</p>";
        echo "<p>Handler NON funziona!</p>";
    }
    
    // Mostra tutte le sessioni
    echo "<h3>Tutte le sessioni nel DB:</h3>";
    $all_sql = "SELECT SESSION_ID, LAST_ACCESS FROM WEBPVEND.WSESSIONS ORDER BY LAST_ACCESS DESC";
    $all_stmt = db2_exec($conn, $all_sql);
    
    if ($all_stmt) {
        echo "<ul>";
        while ($all_row = db2_fetch_assoc($all_stmt)) {
            echo "<li>" . $all_row['SESSION_ID'] . " - " . $all_row['LAST_ACCESS'];
            if ($all_row['SESSION_ID'] == $sid) {
                echo " <strong>&lt;-- Current</strong>";
            }
            echo "</li>";
        }
        echo "</ul>";
    }
    
    db2_close($conn);
}

// 6. Mostra log
echo "<h2>6. Recent Logs</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 300px; overflow: auto;'>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    $lines = array_slice(file($log_file), -30);
    foreach ($lines as $line) {
        if (strpos($line, 'CONFIG') !== false || 
            strpos($line, 'DB2SessionHandler') !== false ||
            strpos($line, 'registerDB2SessionHandler') !== false ||
            strpos($line, 'startSessionSafe') !== false) {
            echo htmlspecialchars($line);
        }
    }
}
echo "</pre>";

// 7. Debug info
echo "<h2>7. Debug Info</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Session save path: " . ini_get('session.save_path') . "</p>";
echo "<p>Session save handler: " . ini_get('session.save_handler') . "</p>";

// 8. Test diretto handler
echo "<h2>8. Test Diretto Handler</h2>";
if (function_exists('registerDB2SessionHandler')) {
    echo "<p style='color: green;'>✓ Funzione registerDB2SessionHandler esiste</p>";
} else {
    echo "<p style='color: red;'>✗ Funzione registerDB2SessionHandler NON esiste!</p>";
}

if (class_exists('DB2SessionHandler')) {
    echo "<p style='color: green;'>✓ Classe DB2SessionHandler esiste</p>";
} else {
    echo "<p style='color: red;'>✗ Classe DB2SessionHandler NON esiste!</p>";
}
?>