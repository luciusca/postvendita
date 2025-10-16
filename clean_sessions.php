<?php
/**
 * CLEAN_SESSIONS.PHP - Pulizia manuale sessioni DB2
 */

require_once 'config.php';

echo "<h1>Gestione Sessioni DB2</h1>";

// Connessione diretta
$conn = db2_connect('S78CCD90', 'PERS', 'PERS');

if (!$conn) {
    die("Errore connessione DB2");
}

// Azione richiesta
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

if ($action == 'delete' && isset($_GET['id'])) {
    // Cancella sessione specifica
    $id_safe = str_replace("'", "''", $_GET['id']);
    $delete_sql = "DELETE FROM WEBPVEND.WSESSIONS WHERE SESSION_ID = '$id_safe'";
    $result = db2_exec($conn, $delete_sql);
    
    if ($result) {
        echo "<p style='color: green;'>Sessione " . htmlspecialchars($_GET['id']) . " cancellata</p>";
    } else {
        echo "<p style='color: red;'>Errore cancellazione: " . db2_stmt_errormsg() . "</p>";
    }
}

if ($action == 'deleteall') {
    // Cancella TUTTE le sessioni
    $delete_all = "DELETE FROM WEBPVEND.WSESSIONS";
    $result = db2_exec($conn, $delete_all);
    
    if ($result) {
        echo "<p style='color: green;'>TUTTE le sessioni cancellate</p>";
    }
}

if ($action == 'deleteold') {
    // Cancella sessioni vecchie
    $delete_old = "DELETE FROM WEBPVEND.WSESSIONS WHERE EXPIRES_AT < CURRENT_TIMESTAMP";
    $result = db2_exec($conn, $delete_old);
    
    if ($result) {
        echo "<p style='color: green;'>Sessioni scadute cancellate</p>";
    }
}

// Lista sessioni
echo "<h2>Sessioni nel Database</h2>";

$sql = "SELECT SESSION_ID, EXPIRES_AT, LAST_ACCESS, 
        SUBSTR(SESSION_DATA, 1, 50) as DATA_PREVIEW 
        FROM WEBPVEND.WSESSIONS 
        ORDER BY LAST_ACCESS DESC";

$stmt = db2_exec($conn, $sql);

if ($stmt) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr>";
    echo "<th>Session ID</th>";
    echo "<th>Expires At</th>";
    echo "<th>Last Access</th>";
    echo "<th>Data Preview</th>";
    echo "<th>Azioni</th>";
    echo "</tr>";
    
    $count = 0;
    while ($row = db2_fetch_assoc($stmt)) {
        echo "<tr>";
        echo "<td>" . $row['SESSION_ID'] . "</td>";
        echo "<td>" . $row['EXPIRES_AT'] . "</td>";
        echo "<td>" . $row['LAST_ACCESS'] . "</td>";
        echo "<td>" . htmlspecialchars($row['DATA_PREVIEW']) . "...</td>";
        echo "<td>";
        echo "<a href='?action=delete&id=" . $row['SESSION_ID'] . "' ";
        echo "onclick='return confirm(\"Cancellare questa sessione?\")'>Cancella</a>";
        echo "</td>";
        echo "</tr>";
        $count++;
    }
    
    if ($count == 0) {
        echo "<tr><td colspan='5'>Nessuna sessione trovata</td></tr>";
    } else {
        echo "<tr><td colspan='5'><strong>Totale: $count sessioni</strong></td></tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Errore query: " . db2_stmt_errormsg() . "</p>";
}

db2_close($conn);

// Azioni
echo "<hr>";
echo "<h3>Azioni</h3>";
echo "<p>";
echo "<a href='?action=list'>Aggiorna Lista</a> | ";
echo "<a href='?action=deleteold' onclick='return confirm(\"Cancellare sessioni scadute?\")'>Cancella Scadute</a> | ";
echo "<a href='?action=deleteall' onclick='return confirm(\"Cancellare TUTTE le sessioni?\")' style='color: red;'>Cancella TUTTE</a>";
echo "</p>";

// Info sessione corrente
echo "<hr>";
echo "<h3>Sessione Corrente</h3>";
if (session_id() == '') {
    session_start();
}
echo "<p>Session ID attuale: <strong>" . session_id() . "</strong></p>";
echo "<p>Browser: " . (isFirefoxBrowser() ? "Firefox" : "Altro") . "</p>";
if (!empty($_SESSION)) {
    echo "<p>Dati sessione:</p>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "<p>Sessione vuota</p>";
}
?>