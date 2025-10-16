<?php
/**
 * INDEX.PHP - Dashboard principale con controllo autenticazione
 * FIX DEFINITIVO PER FIREFOX
 * Sistema Gestione Ticket Post-Vendita
 */

// IMPORTANTE: Include init_firefox PRIMA di tutto
require_once 'init_firefox.php';

// Include config
require_once 'config.php';

// Avvia sessione con funzione sicura
startSessionSafe();

// Log per debug
//error_log("INDEX.PHP - Session ID: " . session_id());
//error_log("INDEX.PHP - Cookie PHPSESSID: " . (isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : 'NOT SET'));
//error_log("INDEX.PHP - SESSION content: " . print_r($_SESSION, true));

// CHIAMIAMO isValidSession() UNA SOLA VOLTA!
$session_valid = false;
$current_user = null;

// Verifica autenticazione - UNA SOLA VOLTA
if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    // Controlla timeout
    if (!isset($_SESSION['expires_at']) || time() <= $_SESSION['expires_at']) {
        // Sessione valida - rinnova timeout
        $_SESSION['expires_at'] = time() + SESSION_TIMEOUT;
        $session_valid = true;
        
        // Ottieni dati utente direttamente da SESSION
        $current_user = array(
            'username' => $_SESSION['username'],
            'nome_completo' => $_SESSION['nome_completo'],
            'livello' => $_SESSION['livello'],
            'poloprod' => isset($_SESSION['poloprod']) ? $_SESSION['poloprod'] : ''
        );
        
       // error_log("INDEX.PHP - Session valid for user: " . $current_user['username']);
    } else {
        error_log("INDEX.PHP - Session expired");
    }
} else {
    error_log("INDEX.PHP - No username in session");
}

// Se non valida, redirect al login
if (!$session_valid) {
    error_log("INDEX.PHP - Session not valid, redirecting to login");
    
    // Distruggi sessione prima del redirect
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
    
    header('Location: login.html');
    exit;
}

// Log accesso dashboard
//error_log("INDEX.PHP - Current user: " . print_r($current_user, true));
logActivity('DASHBOARD_ACCESS', 'Accesso alla dashboard');

// NON chiamare getCurrentUser() o isValidSession() di nuovo!
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema Gestione Ticket Post-Vendita</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-tools"></i> PostVendita
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="#" onclick="showDashboardTab()">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <?php if ($current_user['livello'] === 'BACKOFFICE' || $current_user['livello'] === 'ADMIN'): ?>
                <li class="nav-item" id="navUpload">
                    <a class="nav-link" href="#" onclick="showUploadTab()">
                        <i class="fas fa-cloud-upload-alt"></i> Carica Ticket
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="showSearchTab()">
                        <i class="fas fa-search"></i> Cerca Ticket
                    </a>
                </li>
                <?php if ($current_user['livello'] === 'ADMIN'): ?>
                <li class="nav-item" id="navAdmin">
                    <a class="nav-link" href="#" onclick="showAdminTab()">
                        <i class="fas fa-cog"></i> Amministrazione
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="navbar-nav">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle user-info" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($current_user['nome_completo']); ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">Livello: <?php echo htmlspecialchars($current_user['livello']); ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="changePassword()">
                            <i class="fas fa-key"></i> Cambia Password
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="logout(); return false;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Contenuto principale -->
<div class="container-fluid mt-4">
    
    <!-- Tab Dashboard -->
    <div id="dashboardTab" class="tab-content">
        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stats-card text-center">
                    <i class="fas fa-ticket-alt fa-2x float-end"></i>
                    <h4 id="totalTickets">-</h4>
                    <h6>Ticket Totali</h6>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card text-center">
                    <i class="fas fa-calendar-day fa-2x float-end"></i>
                    <h4 id="todayTickets">-</h4>
                    <h6>Ticket Oggi</h6>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card text-center">
                    <i class="fas fa-file-pdf fa-2x float-end"></i>
                    <h4 id="totalFiles">-</h4>
                    <h6>File Allegati</h6>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card text-center">
                    <i class="fas fa-hdd fa-2x float-end"></i>
                    <h4 id="totalSize">-</h4>
                    <h6>Spazio Utilizzato</h6>
                </div>
            </div>
        </div>
        
        <!-- Ultimi ticket e statistiche -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Ultimi Ticket Caricati</h5>
                    </div>
                    <div class="card-body">
                        <div id="recentTickets" class="table-responsive">
                            <div class="text-center p-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Caricamento...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Statistiche Mensili</h5>
                    </div>
                    <div class="card-body">
                        <div id="monthlyStats">
                            <div class="text-center p-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Caricamento...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Upload Ticket -->
    <?php if ($current_user['livello'] === 'BACKOFFICE' || $current_user['livello'] === 'ADMIN'): ?>
    <div id="uploadTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-cloud-upload-alt"></i> Carica Nuovo Ticket</h4>
            </div>
            <div class="card-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="numTicket" class="form-label">
                                    <i class="fas fa-ticket-alt"></i> Numero Ticket *
                                </label>
                                <input type="number" class="form-control" id="numTicket" min="1" max="9999999" required>
                                <div class="form-text">Numero univoco del ticket (max 7 cifre)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dataRegistrazione" class="form-label">
                                    <i class="fas fa-calendar"></i> Data Registrazione *
                                </label>
                                <input type="date" class="form-control" id="dataRegistrazione" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <br>
                                Il numero di cartellino e i dati del produttore/ispettore verranno recuperati automaticamente 
                                dal sistema tramite il numero ticket.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fileAllegati" class="form-label">
                            <i class="fas fa-paperclip"></i> File Allegati *
                        </label>
                        <input type="file" class="form-control" id="fileAllegati" multiple 
                               accept=".pdf,.jpg,.jpeg,.png,.gif,.bmp,.tif,.tiff" required>
                        <div class="form-text">
                            Formati supportati: PDF, JPG, PNG, GIF, BMP, TIF/TIFF - Max 20MB per file
                        </div>
                        <div id="filePreview" class="mt-2"></div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-secondary" onclick="resetUploadForm()">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <span id="uploadBtnText">
                                <i class="fas fa-upload"></i> Carica Ticket
                            </span>
                            <span id="uploadSpinner" class="d-none">
                                <span class="spinner-border spinner-border-sm" role="status"></span>
                                Caricamento...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab Ricerca - MANCAVA QUESTO -->
    <div id="searchTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-search"></i> Cerca Ticket</h4>
            </div>
            <div class="card-body">
                <form id="searchForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="searchTicket" class="form-label">Numero Ticket</label>
                                <input type="number" class="form-control" id="searchTicket">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="searchProduttore" class="form-label">Produttore</label>
                                <input type="text" class="form-control" id="searchProduttore">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="searchIspettore" class="form-label">Ispettore</label>
                                <input type="text" class="form-control" id="searchIspettore">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="searchPoloProduttivo" class="form-label">Polo Produttivo</label>
                                <input type="text" class="form-control" id="searchPoloProduttivo">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="searchDataDal" class="form-label">Data dal</label>
                                <input type="date" class="form-control" id="searchDataDal">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="searchDataAl" class="form-label">Data al</label>
                                <input type="date" class="form-control" id="searchDataAl">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Cerca
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetSearchForm()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Risultati ricerca -->
        <div id="searchResults" style="display: none; margin-top: 20px;">
            <div class="card">
                <div class="card-header">
                    <h5>Risultati ricerca - <span id="resultsCount">0</span></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="sortable" data-column="NUM_TICKET">Ticket <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="NUM_CARTEL">Cartellino <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="RAG_SOC_PR">Produttore <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="NOME_ISPET">Ispettore <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="DATA_REG">Data Reg. <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="NAZIONE">Nazione <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="DATA_APERTURA">Data Apertura <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-column="DATA_CONSEGNA">Data Consegna <i class="fas fa-sort"></i></th>

                                    <th>File</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody id="resultsBody">
                                <!-- Risultati qui -->
                            </tbody>
                        </table>
                    </div>
                    <nav>
                        <ul class="pagination" id="pagination">
                            <!-- Paginazione qui -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Admin - MANCAVA QUESTO -->
    <?php if ($current_user['livello'] === 'ADMIN'): ?>
    <div id="adminTab" class="tab-content" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-cog"></i> Amministrazione</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Gestione Utenti</h5>
                        <button class="btn btn-primary mb-3" onclick="showUserManagement()">
                            <i class="fas fa-user-plus"></i> Nuovo Utente
                        </button>
                        <div id="usersList">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Caricamento...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Statistiche Sistema</h5>
                        <div id="systemStats">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Caricamento...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <h5>Log Attivit�</h5>
                        <div id="activityLog">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Caricamento...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<!-- TUTTI I MODAL E SCRIPT RIMANGONO UGUALI -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
// Debug PHP values - FUORI dall'oggetto
console.log('PHP values:');
console.log('- username: <?php echo $current_user["username"]; ?>');
console.log('- nome_completo: <?php echo $current_user["nome_completo"]; ?>');
console.log('- livello: <?php echo $current_user["livello"]; ?>');

// Inizializza dati utente da PHP - oggetto SEPARATO
window.currentUser = {
    username: '<?php echo htmlspecialchars($current_user["username"]); ?>',
    nome_completo: '<?php echo htmlspecialchars($current_user["nome_completo"]); ?>',
    livello: '<?php echo htmlspecialchars($current_user["livello"]); ?>'
};

console.log('window.currentUser dopo assegnazione:', window.currentUser);

// Assicura che il tab dashboard sia visibile all'avvio
document.addEventListener('DOMContentLoaded', function() {
    console.log('User data:', window.currentUser);
    console.log('Session cookie:', document.cookie);
    // Mostra il tab dashboard di default
    showDashboardTab();
});
</script>

<script src="app.js"></script>

<script>
// Funzioni aggiuntive specifiche per questa pagina
function changePassword() {
    // Reset form
    document.getElementById('passwordForm').reset();
    
    // Mostra modal
    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
}

function savePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        showAlert('Compilare tutti i campi', 'warning');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showAlert('Le password non coincidono', 'warning');
        return;
    }
    
    if (newPassword.length < 6) {
        showAlert('La password deve essere di almeno 6 caratteri', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'change_password');
    formData.append('current_password', currentPassword);
    formData.append('new_password', newPassword);
    
    fetch('admin.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Password cambiata con successo', 'success');
            
            // Chiudi modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('passwordModal'));
            modal.hide();
        } else {
            showAlert('Errore: ' + (data.error || 'Password non cambiata'), 'danger');
        }
    })
    .catch(error => {
        console.error('Errore cambio password:', error);
        showAlert('Errore di connessione', 'danger');
    });
}
</script>

<!-- Modal per dettagli ticket -->
<div class="modal fade" id="ticketModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dettagli Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="ticketModalBody">
                <!-- Contenuto dinamico -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per cambio password -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambia Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Password Attuale</label>
                        <input type="password" class="form-control" id="currentPassword" required>
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">Nuova Password</label>
                        <input type="password" class="form-control" id="newPassword" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Conferma Password</label>
                        <input type="password" class="form-control" id="confirmPassword" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="savePassword()">Salva</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal per gestione utenti (solo admin) -->
<?php if ($current_user['livello'] === 'ADMIN'): ?>
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gestione Utente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userAction" value="create">
                    <input type="hidden" id="originalUsername" value="">
                    
                    <div class="mb-3">
                        <label for="userUsername" class="form-label">Username</label>
                        <input type="text" class="form-control" id="userUsername" required maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label for="userPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="userPassword">
                        <small class="text-muted">Lascia vuoto per mantenere la password attuale (solo in modifica)</small>
                    </div>
                    <div class="mb-3">
                        <label for="userNome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="userNome" required maxlength="50">
                    </div>
                    <div class="mb-3">
                        <label for="userLivello" class="form-label">Livello</label>
                        <select class="form-control" id="userLivello" required>
                            <option value="USER">USER</option>
                            <option value="BACKOFFICE">BACKOFFICE</option>
                            <option value="ADMIN">ADMIN</option>
                            <option value="CQ">CQ</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="userAttivo" checked>
                        <label class="form-check-label" for="userAttivo">Utente Attivo</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">Salva</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>