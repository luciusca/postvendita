<?php
/**
 * CHECK_FIREFOX.PHP - Verifica rapida se Firefox usa DB2
 */

require_once 'config.php';

echo "<h1>Check Firefox Configuration</h1>";

// 1. Rileva browser
echo "<h2>1. Browser Detection</h2>";
echo "<p>User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "</p>";
echo "<p><strong>Is Firefox: " . (isFirefoxBrowser() ? "YES ✓" : "NO") . "</strong></p>";

// 2. Verifica handler
echo "<h2>2. Session Handler</h2>";
if (isFirefoxBrowser()) {
    echo "<p style='color: blue;'>Firefox detected - should use DB2 handler</p>";
    
    if (function_exists('registerDB2SessionHandler')) {
        echo "<p style='color: green;'>✓ DB2 handler function exists</p>";
    } else {
        echo "<p style='color: red;'>✗ DB2 handler function NOT found!</p>";
        echo "<p>Check that session_handler_db2.php exists</p>";
    }
} else {
    echo "<p>Not Firefox - using default file handler</p>";
}

// 3. Test sessione
echo "<h2>3. Session Test</h2>";
startSessionSafe();
$sid = session_id();
echo "<p>Session ID: " . $sid . "</p>";

// Salva test data
$_SESSION['test_check'] = 'CHECK_' . time();
echo "<p>Test data saved to session</p>";

// 4. Se Firefox, controlla DB2
if (isFirefoxBrowser()) {
    echo "<h2>4. DB2 Verification</h2>";
    
    // Forza scrittura
    session_write_close();
    echo "<p>session_write_close() executed</p>";
    
    // Controlla nel DB
    $conn = getDb2Connection();
    if ($conn) {
        $sql = "SET SCHEMA " . DB2_LIBRARY;
        db2_exec($conn, $sql);
        
        $check_sql = "SELECT * FROM WSESSIONS WHERE SESSION_ID = '" . $sid . "'";
        $stmt = db2_exec($conn, $check_sql);
        
        if ($stmt && $row = db2_fetch_assoc($stmt)) {
            echo "<p style='color: green; font-size: 18px;'>✓ SESSION FOUND IN DB2!</p>";
            echo "<p>Session Data Length: " . strlen($row['SESSION_DATA']) . " bytes</p>";
            echo "<p>Expires At: " . $row['EXPIRES_AT'] . "</p>";
            echo "<p>Last Access: " . $row['LAST_ACCESS'] . "</p>";
        } else {
            echo "<p style='color: red; font-size: 18px;'>✗ SESSION NOT IN DB2!</p>";
            echo "<p>This means the DB2 handler is NOT working!</p>";
        }
        
        db2_close($conn);
    }
}

// 5. Mostra log recenti
echo "<h2>5. Recent Logs</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 200px; overflow: auto;'>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    $lines = array_slice(file($log_file), -20);
    foreach ($lines as $line) {
        if (strpos($line, 'DB2') !== false || 
            strpos($line, 'Firefox') !== false ||
            strpos($line, 'CONFIG') !== false ||
            strpos($line, 'startSessionSafe') !== false) {
            echo htmlspecialchars($line);
        }
    }
}
echo "</pre>";

// 6. Istruzioni
echo "<h2>6. Next Steps</h2>";
if (isFirefoxBrowser()) {
    echo "<p><strong>You are using Firefox.</strong></p>";
    echo "<p>If the session is NOT in DB2, the handler registration is failing.</p>";
    echo "<p>Check the logs above for errors.</p>";
    echo "<p><a href='login.html'>Try Login Now</a></p>";
} else {
    echo "<p>Open this page in Firefox to test the DB2 handler.</p>";
}
?>