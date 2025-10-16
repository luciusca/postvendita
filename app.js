/**
 * APP.JS - JavaScript principale per Sistema Gestione Ticket Post-Vendita
 * VERSIONE CORRETTA - Rimuove campo cartellino dal form upload
 * Versione corretta per PHP 5 e iSeries - PARTE 1 di 2
 */

// Variabili globali
var currentUser = window.currentUser || null;
var currentPage = 1;
var totalPages = 1;
var sortColumn = 'DATA_REG';
var sortDirection = 'DESC';
var searchResults = [];
var searchParams = {};



document.addEventListener('DOMContentLoaded', function() {
    // Se currentUser � gi� definito (da index.php), salta il check
    if (window.currentUser) {
        currentUser = window.currentUser;
        console.log('User data loaded from PHP:', currentUser);
        updateUserInterface();
        
        // Carica dati dashboard SOLO se il tab dashboard � visibile
        var dashboardTab = document.getElementById('dashboardTab');
        if (dashboardTab && dashboardTab.style.display !== 'none') {
            // Piccolo delay solo per Firefox
            if (navigator.userAgent.toLowerCase().indexOf('firefox') > -1) {
                setTimeout(function() {
                    loadDashboardData();
                }, 100);
            } else {
                loadDashboardData();
            }
        }
        setupEventListeners();
    } else {
        // Altrimenti verifica autenticazione (per altre pagine)
        checkAuthentication();
        setupEventListeners();
    }
});

// ===========================================
// GESTIONE AUTENTICAZIONE E INIZIALIZZAZIONE
// ===========================================

function checkAuthentication() {
    // DISABILITATO TEMPORANEAMENTE PER FIX FIREFOX
    console.log('checkAuthentication disabled for Firefox fix');
    return;
    
    /* CODICE ORIGINALE COMMENTATO
    fetch('check_session.php')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data.authenticated) {
                window.location.href = 'login.html';
                return;
            }
            
            currentUser = data.user;
            updateUserInterface();
            loadDashboardData();
        })
        .catch(function(error) {
            console.error('Errore verifica sessione:', error);
            window.location.href = 'login.html';
        });
    */
}

function updateUserInterface() {
    console.log('Updating UI with user:', currentUser);
    
    // Aggiorna elementi UI se esistono
    var userNameEl = document.getElementById('currentUser');
    var userRepartoEl = document.getElementById('currentReparto');
    
    if (userNameEl) {
        userNameEl.textContent = currentUser.nome_completo || currentUser.username;
    }
    if (userRepartoEl) {
        userRepartoEl.textContent = 'Livello: ' + currentUser.livello;
    }
    
    // Mostra/nascondi elementi in base al livello solo se gli elementi esistono
    var navUpload = document.getElementById('navUpload');
    var navAdmin = document.getElementById('navAdmin');
    
    if (navUpload && (currentUser.livello === 'BACKOFFICE' || currentUser.livello === 'ADMIN')) {
        navUpload.style.display = 'block';
    }
    if (navAdmin && currentUser.livello === 'ADMIN') {
        navAdmin.style.display = 'block';
    }
}

function setupEventListeners() {
    // Upload form
    var uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', handleUpload);
    }
    
    var fileInput = document.getElementById('fileAllegati');
    if (fileInput) {
        fileInput.addEventListener('change', previewFiles);
    }
    
    // Search form
    var searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', handleSearch);
    }
    
    // Auto-set data di oggi per nuovi ticket
    var dataReg = document.getElementById('dataRegistrazione');
    if (dataReg) {
        dataReg.value = new Date().toISOString().split('T')[0];
    }
    
    // Sorting headers
    var sortableHeaders = document.querySelectorAll('.sortable');
    for (var i = 0; i < sortableHeaders.length; i++) {
        sortableHeaders[i].addEventListener('click', function() {
            var column = this.dataset.column;
            if (sortColumn === column) {
                sortDirection = sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                sortColumn = column;
                sortDirection = 'ASC';
            }
            updateSortIcons();
            performSearch();
        });
    }
}

// ===========================================
// GESTIONE DASHBOARD
// ===========================================

function loadDashboardData() {
    console.log('Loading dashboard data...');
    
    fetch('dashboard_data.php')
        .then(function(response) {
            console.log('Dashboard response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            console.log('Dashboard data received:', data);
            if (data.success) {
                updateDashboardStats(data.stats);
                updateRecentTickets(data.recent_tickets);
                updateMonthlyStats(data.monthly_stats);
            } else {
                console.error('Dashboard data error:', data.error);
                showAlert('Errore caricamento dashboard: ' + (data.error || 'Errore sconosciuto'), 'warning');
                showDashboardError('Errore caricamento dati');
            }
        })
        .catch(function(error) {
            console.error('Errore caricamento dashboard:', error);
            showAlert('Errore di connessione durante il caricamento della dashboard', 'danger');
            showDashboardError('Errore di connessione');
        });
}

function showDashboardError(message) {
    var recentTickets = document.getElementById('recentTickets');
    var monthlyStats = document.getElementById('monthlyStats');
    
    if (recentTickets) {
        recentTickets.innerHTML = '<div class="alert alert-warning">' + message + '</div>';
    }
    if (monthlyStats) {
        monthlyStats.innerHTML = '<div class="alert alert-warning">' + message + '</div>';
    }
}

function updateDashboardStats(stats) {
    console.log('Updating dashboard stats:', stats);
    
    var totalTicketsEl = document.getElementById('totalTickets');
    var todayTicketsEl = document.getElementById('todayTickets');
    var totalFilesEl = document.getElementById('totalFiles');
    var totalSizeEl = document.getElementById('totalSize');
    
    if (totalTicketsEl) totalTicketsEl.textContent = stats.total_tickets || '0';
    if (todayTicketsEl) todayTicketsEl.textContent = stats.today_tickets || '0';
    if (totalFilesEl) totalFilesEl.textContent = stats.total_files || '0';
    if (totalSizeEl) totalSizeEl.textContent = formatFileSize(stats.total_size || 0);
}

function updateRecentTickets(tickets) {
    console.log('Updating recent tickets:', tickets);
    
    var container = document.getElementById('recentTickets');
    if (!container) {
        console.error('Container recentTickets not found');
        return;
    }
    
    if (!tickets || tickets.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-4">Nessun ticket recente</p>';
        return;
    }
    
    var tableHtml = '<table class="table table-sm table-hover"><thead><tr><th>Ticket</th><th>Produttore</th><th>Data</th><th>File</th></tr></thead><tbody>';
    
    for (var i = 0; i < tickets.length; i++) {
        var ticket = tickets[i];
        tableHtml += '<tr onclick="viewTicketDetails(' + ticket.NUM_TICKET + ')" style="cursor: pointer;" class="ticket-row">';
        tableHtml += '<td><strong>#' + ticket.NUM_TICKET + '</strong></td>';
        tableHtml += '<td>' + escapeHtml(ticket.RAG_SOC_PR) + '</td>';
        tableHtml += '<td>' + formatDate(ticket.DATA_REG) + '</td>';
        tableHtml += '<td><i class="fas fa-paperclip"></i> ' + (ticket.FILE_COUNT || 0) + '</td>';
        tableHtml += '</tr>';
    }
    
    tableHtml += '</tbody></table>';
    container.innerHTML = tableHtml;
}

function updateMonthlyStats(monthlyData) {
    console.log('Updating monthly stats:', monthlyData);
    
    var container = document.getElementById('monthlyStats');
    if (!container) {
        console.error('Container monthlyStats not found');
        return;
    }
    
    if (!monthlyData || !monthlyData.mensili || monthlyData.mensili.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-4">Nessun dato disponibile</p>';
        return;
    }
    
    var statsHtml = '<div class="row">';
    var maxItems = Math.min(6, monthlyData.mensili.length);
    
    for (var i = 0; i < maxItems; i++) {
        var mese = monthlyData.mensili[i];
        var progressWidth = Math.min(100, (mese.ticket_count / 10) * 100);
        
        statsHtml += '<div class="col-12 mb-2">';
        statsHtml += '<div class="d-flex justify-content-between align-items-center">';
        statsHtml += '<span class="fw-bold">' + mese.mese_nome + ' ' + mese.anno + '</span>';
        statsHtml += '<span class="badge bg-primary">' + mese.ticket_count + '</span>';
        statsHtml += '</div>';
        statsHtml += '<div class="progress mt-1" style="height: 4px;">';
        statsHtml += '<div class="progress-bar" style="width: ' + progressWidth + '%"></div>';
        statsHtml += '</div>';
        statsHtml += '</div>';
    }
    
    statsHtml += '</div>';
    container.innerHTML = statsHtml;
}

// ===========================================
// GESTIONE UPLOAD - MODIFICATA PER RIMUOVERE CARTELLINO
// ===========================================

// ===========================================
// GESTIONE UPLOAD - MODIFICATA PER RIMUOVERE CARTELLINO
// ===========================================
function handleUpload(e) {
    e.preventDefault();
    
    console.log('=== INIZIO UPLOAD ===');
    
    var formData = new FormData();
    
    // Raccoglie dati form - CARTELLINO RIMOSSO
    var numTicket = document.getElementById('numTicket').value;
    var dataRegistrazione = document.getElementById('dataRegistrazione').value;
    var files = document.getElementById('fileAllegati').files;
    
    console.log('Dati form:', {
        numTicket: numTicket,
        dataRegistrazione: dataRegistrazione,
        filesCount: files.length
    });
    
    // Validazione client-side - CARTELLINO NON PI� RICHIESTO
    if (!numTicket || !dataRegistrazione) {
        showAlert('Compilare tutti i campi obbligatori', 'warning');
        return;
    }
    
    if (files.length === 0) {
        showAlert('Selezionare almeno un file', 'warning');
        return;
    }
    
    // Verifica dimensione file
    var totalSize = 0;
    for (var i = 0; i < files.length; i++) {
        totalSize += files[i].size;
        console.log('File ' + i + ':', files[i].name, 'Size:', files[i].size);
        if (files[i].size > 20 * 1024 * 1024) { // 20MB
            showAlert('File "' + files[i].name + '" troppo grande (max 20MB)', 'warning');
            return;
        }
    }
    
    // Aggiunge dati al form - CARTELLINO RIMOSSO
    formData.append('num_ticket', numTicket);
    formData.append('data_registrazione', dataRegistrazione);
    
    // Aggiunge file
    for (var i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }
    
    console.log('FormData pronto per invio');
    
    // Mostra loading
    setUploadLoading(true);
    
    // Invia richiesta
    fetch('upload.php', {
        method: 'POST',
        credentials: 'same-origin',  // AGGIUNGI QUESTA LINEA
        body: formData
    })
    .then(function(response) {
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        
        // Prima leggi come text per debug
        return response.text();
    })
    .then(function(text) {
        console.log('Response text (first 500 chars):', text.substring(0, 500));
        console.log('Response text length:', text.length);
        
        setUploadLoading(false);
        
        // Controlla se � HTML
        if (text.charAt(0) === '<') {
            console.error('Response starts with <, probably HTML error');
            showAlert('Errore: il server ha restituito HTML invece di JSON', 'danger');
            return;
        }
        
        // Prova a parsare come JSON
        try {
            var data = JSON.parse(text);
            console.log('Parsed data:', data);
            
            if (data.success) {
                var message = 'Ticket #' + data.ticket_id + ' caricato con successo! (' + data.files_count + ' file)';
                if (data.cartellino_recuperato) {
                    message += ' Cartellino ' + data.cartellino_recuperato + ' recuperato automaticamente.';
                }
                showAlert(message, 'success');
                resetUploadForm();
                
                // Ricarica dashboard
                setTimeout(function() {
                    loadDashboardData();
                }, 1000);
            } else {
                showAlert('Errore upload: ' + (data.error || 'Errore sconosciuto'), 'danger');
            }
        } catch(e) {
            console.error('Errore parsing JSON:', e);
            console.error('Testo completo:', text);
            showAlert('Errore nel parsing della risposta del server', 'danger');
        }
    })
    .catch(function(error) {
        setUploadLoading(false);
        console.error('=== ERRORE UPLOAD ===');
        console.error('Error:', error);
        console.error('Error message:', error.message);
        showAlert('Errore di connessione durante l\'upload', 'danger');
    });
}





function previewFiles() {
    var files = document.getElementById('fileAllegati').files;
    var preview = document.getElementById('filePreview');
    
    if (files.length === 0) {
        preview.innerHTML = '';
        return;
    }
    
    var html = '<div class="mt-2"><strong>File selezionati:</strong></div>';
    var totalSize = 0;
    
    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        var size = file.size;
        totalSize += size;
        
        var icon = getFileIcon(file.name);
        var sizeFormatted = formatFileSize(size);
        var statusClass = '';
        var statusIcon = '';
        
        if (size > 20 * 1024 * 1024) {
            statusClass = 'text-danger';
            statusIcon = '<i class="fas fa-exclamation-triangle"></i> ';
        }
        
        html += '<div class="file-info d-flex align-items-center ' + statusClass + '">';
        html += '<i class="' + icon + '"></i>';
        html += '<div class="flex-grow-1">';
        html += '<div class="fw-bold">' + statusIcon + escapeHtml(file.name) + '</div>';
        html += '<small class="text-muted">' + sizeFormatted + '</small>';
        html += '</div>';
        html += '</div>';
    }
    
    html += '<div class="mt-2"><small class="text-muted">Totale: ' + formatFileSize(totalSize) + '</small></div>';
    preview.innerHTML = html;
}

function setUploadLoading(loading) {
    var btn = document.getElementById('uploadBtn');
    var btnText = document.getElementById('uploadBtnText');
    var btnSpinner = document.getElementById('uploadSpinner');
    
    if (loading) {
        btn.disabled = true;
        btnText.classList.add('d-none');
        btnSpinner.classList.remove('d-none');
    } else {
        btn.disabled = false;
        btnText.classList.remove('d-none');
        btnSpinner.classList.add('d-none');
    }
}

function resetUploadForm() {
    document.getElementById('uploadForm').reset();
    document.getElementById('filePreview').innerHTML = '';
    
    // Reimposta data di oggi
    var dataReg = document.getElementById('dataRegistrazione');
    if (dataReg) {
        dataReg.value = new Date().toISOString().split('T')[0];
    }
}

// ===========================================
// GESTIONE RICERCA
// ===========================================

function handleSearch(e) {
    e.preventDefault();
    
    // Raccoglie parametri di ricerca
    searchParams = {
        num_ticket: document.getElementById('searchTicket').value,
        rag_sociale: document.getElementById('searchProduttore').value,
        nome_ispettore: document.getElementById('searchIspettore').value,
        polo_produttivo: document.getElementById('searchPoloProduttivo').value,
        data_dal: document.getElementById('searchDataDal').value,
        page: 1,
        per_page: 20,
        sort_column: sortColumn,
        sort_direction: sortDirection
    };
    
    performSearch();
}

function performSearch() {
    // Costruisce query string
    var params = [];
    for (var key in searchParams) {
        if (searchParams[key]) {
            params.push(encodeURIComponent(key) + '=' + encodeURIComponent(searchParams[key]));
        }
    }
    
    params.push('page=' + currentPage);
    params.push('sort_column=' + sortColumn);
    params.push('sort_direction=' + sortDirection);
    
    var queryString = params.join('&');
    
    // Mostra loading
    showSearchLoading(true);
    
    fetch('search.php?' + queryString)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            showSearchLoading(false);
            
            if (data.success) {
                displaySearchResults(data);
                updatePagination(data);
                document.getElementById('searchResults').style.display = 'block';
                document.getElementById('resultsCount').textContent = data.total_count + ' risultati';
            } else {
                showAlert('Errore ricerca: ' + (data.error || 'Errore sconosciuto'), 'danger');
            }
        })
        .catch(function(error) {
            showSearchLoading(false);
            console.error('Errore ricerca:', error);
            showAlert('Errore di connessione durante la ricerca', 'danger');
        });
}

function displaySearchResults(data) {
    var tbody = document.getElementById('resultsBody');
    
    if (!data.data || data.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Nessun risultato trovato</td></tr>';
        return;
    }
    
    var html = '';
    for (var i = 0; i < data.data.length; i++) {
        var ticket = data.data[i];
        
        html += '<tr onclick="viewTicketDetails(' + ticket.NUM_TICKET + ')" style="cursor: pointer;" class="ticket-row">';
        html += '<td><strong>#' + ticket.NUM_TICKET + '</strong></td>';
        html += '<td>' + ticket.NUM_CARTEL + '</td>';
        html += '<td title="' + escapeHtml(ticket.RAG_SOC_PR) + '">' + truncateText(ticket.RAG_SOC_PR, 25) + '</td>';
        html += '<td title="' + escapeHtml(ticket.NOME_ISPET) + '">' + truncateText(ticket.NOME_ISPET, 20) + '</td>';
        html += '<td>' + formatDate(ticket.DATA_REG) + '</td>';
        
        // TRE NUOVE COLONNE
        html += '<td>' + (ticket.NAZIONE ? escapeHtml(ticket.NAZIONE) : '-') + '</td>';
        html += '<td>' + (ticket.DATA_APERTURA ? formatDate(ticket.DATA_APERTURA) : '-') + '</td>';
        html += '<td>' + (ticket.DATA_CONSEGNA ? formatDate(ticket.DATA_CONSEGNA) : '-') + '</td>';
        
        html += '<td>';
        html += '<span class="badge bg-primary">' + (ticket.FILE_COUNT || 0) + '</span> ';
        html += '<small class="text-muted">(' + formatFileSize(ticket.TOTAL_SIZE || 0) + ')</small>';
        html += '</td>';
        html += '<td>';
        html += '<div class="btn-group btn-group-sm">';
        html += '<button class="btn btn-outline-primary" onclick="event.stopPropagation(); viewTicketDetails(' + ticket.NUM_TICKET + ')" title="Dettagli">';
        html += '<i class="fas fa-eye"></i>';
        html += '</button>';
        html += '<button class="btn btn-outline-success" onclick="event.stopPropagation(); downloadTicketZip(' + ticket.NUM_TICKET + ')" title="Download ZIP">';
        html += '<i class="fas fa-download"></i>';
        html += '</button>';
        html += '</div>';
        html += '</td>';
        html += '</tr>';
    }
    
    tbody.innerHTML = html;
    searchResults = data.data; // Memorizza per export
}
/**
 * APP.JS - PARTE 2 di 2
 * Continuazione del file JavaScript principale
 */

function updatePagination(data) {
    var pagination = document.getElementById('pagination');
    
    if (data.total_pages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    var html = '';
    
    // Pulsante Previous
    if (data.page > 1) {
        html += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' + (data.page - 1) + ')">�</a></li>';
    } else {
        html += '<li class="page-item disabled"><span class="page-link">�</span></li>';
    }
    
    // Numeri di pagina
    var startPage = Math.max(1, data.page - 2);
    var endPage = Math.min(data.total_pages, data.page + 2);
    
    if (startPage > 1) {
        html += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(1)">1</a></li>';
        if (startPage > 2) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for (var i = startPage; i <= endPage; i++) {
        if (i === data.page) {
            html += '<li class="page-item active"><span class="page-link">' + i + '</span></li>';
        } else {
            html += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' + i + ')">' + i + '</a></li>';
        }
    }
    
    if (endPage < data.total_pages) {
        if (endPage < data.total_pages - 1) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        html += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' + data.total_pages + ')">' + data.total_pages + '</a></li>';
    }
    
    // Pulsante Next
    if (data.page < data.total_pages) {
        html += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' + (data.page + 1) + ')">�</a></li>';
    } else {
        html += '<li class="page-item disabled"><span class="page-link">�</span></li>';
    }
    
    pagination.innerHTML = html;
    
    // Aggiorna variabili globali
    currentPage = data.page;
    totalPages = data.total_pages;
}

function changePage(page) {
    currentPage = page;
    performSearch();
}

function updateSortIcons() {
    // Reset tutti gli icon
    var sortableIcons = document.querySelectorAll('.sortable i');
    for (var i = 0; i < sortableIcons.length; i++) {
        sortableIcons[i].className = 'fas fa-sort';
    }
    
    // Aggiorna icon della colonna attiva
    var activeHeader = document.querySelector('[data-column="' + sortColumn + '"] i');
    if (activeHeader) {
        activeHeader.className = sortDirection === 'ASC' ? 'fas fa-sort-up' : 'fas fa-sort-down';
    }
}

function showSearchLoading(show) {
    var tbody = document.getElementById('resultsBody');
    if (show) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';
    }
}

function resetSearchForm() {
    document.getElementById('searchForm').reset();
    document.getElementById('searchResults').style.display = 'none';
    searchParams = {};
    currentPage = 1;
}

// ===========================================
// GESTIONE TAB E NAVIGAZIONE - CORREZIONE PRINCIPALE
// ===========================================

function showUploadTab() {
    hideAllTabs();
    var uploadTab = document.getElementById('uploadTab');
    if (uploadTab) {
        uploadTab.style.display = 'block';
    }
    updateActiveNav('navUpload');
    
    // Reset form quando si apre il tab
    if (document.getElementById('uploadForm')) {
        resetUploadForm();
    }
}

function showSearchTab() {
    hideAllTabs();
    var searchTab = document.getElementById('searchTab');
    if (searchTab) {
        searchTab.style.display = 'block';
    }
    updateActiveNav(null);
}

function showAdminTab() {
    hideAllTabs();
    var adminTab = document.getElementById('adminTab');
    if (adminTab) {
        adminTab.style.display = 'block';
    }
    updateActiveNav('navAdmin');
    
    // Carica dati admin - ORA LA FUNZIONE ESISTE
    loadAdminData();
}

// CORREZIONE: Aggiunta funzione showDashboardTab mancante
function showDashboardTab() {
    hideAllTabs();
    var dashboardTab = document.getElementById('dashboardTab');
    if (dashboardTab) {
        dashboardTab.style.display = 'block';
    }
    updateActiveNav(null);
    
    // Carica i dati della dashboard quando si mostra il tab
    loadDashboardData();
}

function hideAllTabs() {
    var tabs = document.querySelectorAll('.tab-content');
    for (var i = 0; i < tabs.length; i++) {
        tabs[i].style.display = 'none';
    }
}

function updateActiveNav(activeId) {
    var navLinks = document.querySelectorAll('.nav-link');
    for (var i = 0; i < navLinks.length; i++) {
        navLinks[i].classList.remove('active');
    }
    
    if (activeId) {
        var activeLink = document.querySelector('#' + activeId + ' .nav-link');
        if (activeLink) activeLink.classList.add('active');
    } else {
        // Attiva il primo link (Dashboard) se nessun ID specifico
        var dashboardLink = document.querySelector('.navbar-nav .nav-link');
        if (dashboardLink) dashboardLink.classList.add('active');
    }
}

// ===========================================
// GESTIONE DETTAGLI TICKET
// ===========================================

function viewTicketDetails(ticketId) {
    console.log('=== DEBUG viewTicketDetails ===');
    console.log('Ticket ID:', ticketId);
    console.log('URL chiamata:', 'search.php?action=detail&ticket_id=' + ticketId);
    
    // Usa XMLHttpRequest per debug migliore
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'search.php?action=detail&ticket_id=' + ticketId, true);
    xhr.withCredentials = true;
    
    xhr.onreadystatechange = function() {
        console.log('ReadyState:', xhr.readyState, 'Status:', xhr.status);
        
        if (xhr.readyState === 4) {
            console.log('Response completa:');
            console.log('- Status:', xhr.status);
            console.log('- StatusText:', xhr.statusText);
            console.log('- ResponseText:', xhr.responseText);
            console.log('- ResponseType:', xhr.responseType);
            
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    console.log('Parsed JSON:', data);
                    
                    if (data.success) {
                        displayTicketModal(data.data);
                    } else {
                        showAlert('Errore: ' + (data.error || 'Errore sconosciuto'), 'danger');
                    }
                } catch(e) {
                    console.error('Errore parsing JSON:', e);
                    console.error('Testo ricevuto:', xhr.responseText);
                    showAlert('Errore parsing risposta', 'danger');
                }
            } else {
                showAlert('Errore HTTP: ' + xhr.status, 'danger');
            }
        }
    };
    
    xhr.onerror = function() {
        console.error('XHR Error:', xhr);
        showAlert('Errore di connessione', 'danger');
    };
    
    xhr.send();
}
   
   window.viewTicketDetails = viewTicketDetails;

/**
 * Funzione displayTicketModal CORRETTA con fix per pulsante preview
 */
function displayTicketModal(ticketData) {
	
	
    if (!currentUser) {
           if (window.currentUser) {
               currentUser = window.currentUser;
               console.log('currentUser recuperato da window');
           } else {
               // Fallback: usa dati di default per ADMIN
               currentUser = {
                   username: 'ADMIN',
                   nome_completo: 'AMMINISTRATORE SISTEMA', 
                   livello: 'ADMIN'
               };
               console.warn('currentUser non disponibile, uso fallback ADMIN');
           }
       }
    
	
    var ticket = ticketData.ticket;
    var files = ticketData.files;
    var stats = ticketData.stats;
    
    // LOGICA PERMESSI CORRETTA
    var canDelete = false;
    var canModify = false;
    
    if (currentUser.livello === 'ADMIN') {
        canDelete = true;
        canModify = true;
    } else if (currentUser.livello === 'USER' && ticket.USERNAME_INS === currentUser.username) {
        canDelete = true;
        canModify = true;
    } else if (currentUser.livello === 'BACKOFFICE' && ticket.USERNAME_INS === currentUser.username) {
        canDelete = false;
        canModify = true;
    }
    
    var modalContent = '<div class="row">';
    modalContent += '<div class="col-md-6">';
    modalContent += '<h6 class="fw-bold">Informazioni Ticket</h6>';
    modalContent += '<table class="table table-sm">';
    modalContent += '<tr><td><strong>Numero Ticket:</strong></td><td>#' + ticket.NUM_TICKET + '</td></tr>';
    //modalContent += '<tr><td><strong>Numero Cartellino:</strong></td><td>' + ticket.NUM_CARTEL + '</td></tr>';
	
	modalContent += '<tr><td><strong>Numero Cartellino:</strong></td><td>' + ticket.NUM_CARTEL + ' <button type="button" class="btn btn-sm btn-outline-info ms-2" onclick="window.open(\'http://cq.chateau-dax.com/productviewer?barcode=' + ticket.NUM_CARTEL + '&token=68d145c0-3a60-8321-b0f8-099c8b9f370a\', \'_blank\')" title="Visualizza nel Product Viewer"><i class="fas fa-external-link-alt"></i>Foto Imballo</button></td></tr>';
	
    modalContent += '<tr><td><strong>Data Registrazione:</strong></td><td>' + formatDate(ticket.DATA_REG) + '</td></tr>';
    modalContent += '<tr><td><strong>Anno:</strong></td><td>' + ticket.ANNO_TICKET + '</td></tr>';
    modalContent += '<tr><td><strong>Nazione:</strong></td><td>' + (ticket.NAZIONE ? escapeHtml(ticket.NAZIONE) : '-') + '</td></tr>';
    modalContent += '<tr><td><strong>Data Apertura:</strong></td><td>' + (ticket.DATA_APERTURA ? formatDate(ticket.DATA_APERTURA) : '-') + '</td></tr>';
    modalContent += '<tr><td><strong>Data Consegna:</strong></td><td>' + (ticket.DATA_CONSEGNA ? formatDate(ticket.DATA_CONSEGNA) : '-') + '</td></tr>';
    modalContent += '</table>';
    modalContent += '</div>';
    
    modalContent += '<div class="col-md-6">';
    modalContent += '<h6 class="fw-bold">Informazioni Produttore/Ispettore</h6>';
    modalContent += '<table class="table table-sm">';
    modalContent += '<tr><td><strong>Cod. Produttore:</strong></td><td>' + ticket.COD_PRODUT + '</td></tr>';
    modalContent += '<tr><td><strong>Ragione Sociale:</strong></td><td>' + escapeHtml(ticket.RAG_SOC_PR) + '</td></tr>';
    modalContent += '<tr><td><strong>Cod. Ispettore:</strong></td><td>' + ticket.COD_ISPETT + '</td></tr>';
    modalContent += '<tr><td><strong>Nome Ispettore:</strong></td><td>' + escapeHtml(ticket.NOME_ISPET) + '</td></tr>';
    modalContent += '</table>';
    modalContent += '</div>';
    modalContent += '</div>';
    
    modalContent += '<div class="row mt-3">';
    modalContent += '<div class="col-12">';
    modalContent += '<h6 class="fw-bold">Statistiche</h6>';
    modalContent += '<div class="row">';
    modalContent += '<div class="col-md-4">';
    modalContent += '<div class="text-center p-2 bg-light rounded">';
    modalContent += '<div class="h5 mb-0 text-primary">' + stats.file_count + '</div>';
    modalContent += '<small>File Allegati</small>';
    modalContent += '</div>';
    modalContent += '</div>';
    modalContent += '<div class="col-md-4">';
    modalContent += '<div class="text-center p-2 bg-light rounded">';
    modalContent += '<div class="h5 mb-0 text-success">' + formatFileSize(stats.total_size) + '</div>';
    modalContent += '<small>Dimensione Totale</small>';
    modalContent += '</div>';
    modalContent += '</div>';
    modalContent += '<div class="col-md-4">';
    modalContent += '<div class="text-center p-2 bg-light rounded">';
    modalContent += '<div class="h5 mb-0 text-info">' + formatDate(ticket.DATA_INS) + '</div>';
    modalContent += '<small>Data Caricamento</small>';
    modalContent += '</div>';
    modalContent += '</div>';
    modalContent += '</div>';
    modalContent += '</div>';
    modalContent += '</div>';
	
	

	
	
    
    // PULSANTI GESTIONE - SOLO SE AUTORIZZATO
    if (canModify || canDelete) {
        modalContent += '<div class="row mt-4">';
        modalContent += '<div class="col-12">';
        modalContent += '<h6 class="fw-bold">Gestione Ticket</h6>';
        modalContent += '<div class="btn-group" role="group">';
        
        if (canModify) {
            modalContent += '<button type="button" class="btn btn-outline-primary" ' +
                          'onclick="showAddFilesModal(' + ticket.NUM_TICKET + ', ' + ticket.NUM_TICKET + ')" ' +
                          'title="Aggiungi nuovi file al ticket">' +
                          '<i class="fas fa-plus"></i> Aggiungi File</button>';
        }
        
        if (canDelete) {
            modalContent += '<button type="button" class="btn btn-outline-danger ms-2" ' +
                          'onclick="confirmDeleteTicket(' + ticket.NUM_TICKET + ', ' + ticket.NUM_TICKET + ')" ' +
                          'title="Cancella completamente il ticket">' +
                          '<i class="fas fa-trash"></i> Cancella Ticket</button>';
        }
        
        modalContent += '</div>';
        modalContent += '</div>';
        modalContent += '</div>';
    }
    
    if (files && files.length > 0) {
        modalContent += '<div class="row mt-3">';
        modalContent += '<div class="col-12">';
        modalContent += '<h6 class="fw-bold">File Allegati</h6>';
        modalContent += '<div class="table-responsive">';
        modalContent += '<table class="table table-sm table-hover">';
        modalContent += '<thead>';
        modalContent += '<tr>';
        modalContent += '<th>Nome File</th>';
        modalContent += '<th>Tipo</th>';
        modalContent += '<th>Dimensione</th>';
        modalContent += '<th>Data Upload</th>';
        modalContent += '<th>Utente</th>';
        modalContent += '<th>Azioni</th>';
        modalContent += '</tr>';
        modalContent += '</thead>';
        modalContent += '<tbody>';
        
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var icon = getFileIcon(file.NOME_ORIG);
            
            var fileType = '';
            if (file.TIPO_FILE) {
                fileType = String(file.TIPO_FILE).toString().trim().replace(/\s+/g, '').toUpperCase();
            }
            
            var isImage = false;
            var isPdf = false;
            
            if (fileType === 'PDF') {
                isPdf = true;
            }
            
            var imageTypes = ['JPG', 'JPEG', 'PNG', 'GIF', 'BMP', 'TIF', 'TIFF'];
            if (imageTypes.indexOf(fileType) !== -1) {
                isImage = true;
            }
            
            if (!isImage && !isPdf && file.NOME_ORIG) {
                var fileExtension = file.NOME_ORIG.split('.').pop();
                if (fileExtension) {
                    fileExtension = fileExtension.toUpperCase();
                    if (fileExtension === 'PDF') {
                        isPdf = true;
                    } else if (['JPG', 'JPEG', 'PNG', 'GIF', 'BMP', 'TIF', 'TIFF'].indexOf(fileExtension) !== -1) {
                        isImage = true;
                    }
                }
            }
            
            var supportsPreview = isImage || isPdf;
            
            modalContent += '<tr>';
            modalContent += '<td>';
            modalContent += '<i class="' + icon + '"></i> ';
            modalContent += escapeHtml(file.NOME_ORIG);
            modalContent += '</td>';
            modalContent += '<td><span class="badge bg-secondary">' + fileType + '</span></td>';
            modalContent += '<td>' + formatFileSize(file.DIMENSIONE) + '</td>';
            modalContent += '<td>' + formatDateTime(file.DATA_UPLOAD) + '</td>';
            modalContent += '<td>' + escapeHtml(file.USERNAME_UPL) + '</td>';
            modalContent += '<td>';
            modalContent += '<div class="btn-group btn-group-sm">';
            
            if (supportsPreview) {
                modalContent += '<button class="btn btn-outline-info" onclick="previewFile(' + file.ID_ALLEGATO + ', \'' + escapeHtml(fileType) + '\')" title="Anteprima">';
                modalContent += '<i class="fas fa-eye"></i>';
                modalContent += '</button>';
            }
            
            modalContent += '<button class="btn btn-outline-primary" onclick="downloadFile(' + file.ID_ALLEGATO + ')" title="Download">';
            modalContent += '<i class="fas fa-download"></i>';
            modalContent += '</button>';
            
            // PULSANTE CANCELLA FILE - SOLO SE AUTORIZZATO
            if (canModify) {
                modalContent += '<button class="btn btn-outline-danger" ' +
                              'onclick="confirmDeleteFile(' + file.ID_ALLEGATO + ', \'' + escapeHtml(file.NOME_ORIG) + '\', ' + ticket.NUM_TICKET + ')" ' +
                              'title="Cancella file">';
                modalContent += '<i class="fas fa-trash"></i>';
                modalContent += '</button>';
            }
            
            modalContent += '</div>';
            modalContent += '</td>';
            modalContent += '</tr>';
        }
        
        modalContent += '</tbody>';
        modalContent += '</table>';
        modalContent += '</div>';
        
        modalContent += '<div class="mt-2">';
        modalContent += '<button class="btn btn-success" onclick="downloadTicketZip(' + ticket.NUM_TICKET + ')">';
        modalContent += '<i class="fas fa-file-archive"></i> Download Tutti i File (ZIP)';
        modalContent += '</button>';
        modalContent += '</div>';
        
        modalContent += '</div>';
        modalContent += '</div>';
    } else {
        modalContent += '<div class="alert alert-warning mt-3">Nessun file allegato trovato per questo ticket.</div>';
    }
    
    document.getElementById('ticketModalBody').innerHTML = modalContent;
    
    var modal = new bootstrap.Modal(document.getElementById('ticketModal'));
    modal.show();
}



// Funzione helper per ottenere session ID dal cookie
function getSessionIdFromCookie() {
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        if (cookie.indexOf('PHPSESSID=') === 0) {
            return cookie.substring(10);
        }
    }
    return null;
}




// ===========================================
// GESTIONE DOWNLOAD E PREVIEW
// ===========================================

function downloadFile(fileId) {
    // Aggiungi session ID per Firefox
    var url = 'download.php?id=' + fileId;
    if (navigator.userAgent.indexOf('Firefox') !== -1) {
        // Ottieni session ID dal cookie se disponibile
        var sessionId = getSessionIdFromCookie();
        if (sessionId) {
            url += '&sid=' + sessionId;
        }
    }
    window.open(url, '_blank');
}

function downloadTicketZip(ticketId) {
    window.open('download.php?action=zip&ticket=' + ticketId, '_blank');
}

// Funzione previewFile CORRETTA
function previewFile(fileId, fileType) {
    console.log('previewFile chiamata con:', {fileId: fileId, fileType: fileType});
    
    var previewUrl = 'download.php?action=preview&id=' + fileId;
	
	
	
// Aggiungi session ID per Firefox
    if (navigator.userAgent.indexOf('Firefox') !== -1) {
        var sessionId = getSessionIdFromCookie();
        if (sessionId) {
            previewUrl += '&sid=' + sessionId;
        }
    }
	
	
	
    
    // Pulizia e normalizzazione del tipo file
    var cleanFileType = '';
    if (fileType) {
        cleanFileType = String(fileType).trim().replace(/\s+/g, '').toUpperCase();
    }
    
    var isImage = ['JPG', 'JPEG', 'PNG', 'GIF', 'BMP', 'TIF', 'TIFF'].indexOf(cleanFileType) !== -1;
    var isPdf = (cleanFileType === 'PDF');
    
    console.log('Preview logic:', {
        originalType: fileType,
        cleanType: cleanFileType,
        isImage: isImage,
        isPdf: isPdf,
        previewUrl: previewUrl
    });
    
    // Crea modal per anteprima
    var previewModal = document.createElement('div');
    previewModal.className = 'modal fade';
    previewModal.id = 'previewModal_' + fileId;
    
    var modalContent = '<div class="modal-dialog modal-xl">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<h5 class="modal-title">' +
        '<i class="fas fa-eye"></i> Anteprima File (' + cleanFileType + ')' +
        '</h5>' +
        '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
        '</div>' +
        '<div class="modal-body text-center" id="previewContent_' + fileId + '">' +
        '<div class="d-flex justify-content-center">' +
        '<div class="spinner-border text-primary" role="status">' +
        '<span class="visually-hidden">Caricamento...</span>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '<div class="modal-footer">' +
        '<button type="button" class="btn btn-primary" onclick="downloadFile(' + fileId + ')">' +
        '<i class="fas fa-download"></i> Scarica File' +
        '</button>' +
        '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>' +
        '</div>' +
        '</div>' +
        '</div>';
    
    previewModal.innerHTML = modalContent;
    document.body.appendChild(previewModal);
    
    var modal = new bootstrap.Modal(previewModal);
    modal.show();
    
    // Carica il contenuto dell'anteprima
    var contentDiv = document.getElementById('previewContent_' + fileId);
    
    if (isImage) {
        console.log('Caricamento preview immagine...');
        // Anteprima immagine
        var img = new Image();
        img.onload = function() {
            console.log('Immagine caricata con successo');
            contentDiv.innerHTML = '<img src="' + previewUrl + '" class="img-fluid" alt="Anteprima" style="max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">';
        };
        img.onerror = function() {
            console.error('Errore caricamento immagine');
            contentDiv.innerHTML = '<div class="alert alert-danger">' +
                '<i class="fas fa-exclamation-triangle"></i> ' +
                'Errore durante il caricamento dell\'immagine' +
                '</div>';
        };
        img.src = previewUrl;
        
    } else if (isPdf) {
        console.log('Caricamento preview PDF...');
        // Anteprima PDF usando iframe
        contentDiv.innerHTML = '<iframe src="' + previewUrl + '" style="width: 100%; height: 70vh; border: 1px solid #ddd; border-radius: 8px;" frameborder="0">' +
            '<p>Il tuo browser non supporta la visualizzazione PDF. <a href="' + previewUrl + '" target="_blank">Clicca qui per aprire il file</a></p>' +
            '</iframe>';
            
    } else {
        console.log('Tipo file non supportato per preview:', cleanFileType);
        // Tipo file non supportato per anteprima
        contentDiv.innerHTML = '<div class="alert alert-info">' +
            '<i class="fas fa-info-circle"></i> ' +
            'Anteprima non disponibile per questo tipo di file (' + cleanFileType + ').' +
            '<br><br>' +
            '<button class="btn btn-primary" onclick="downloadFile(' + fileId + ')">' +
            '<i class="fas fa-download"></i> Scarica per visualizzare' +
            '</button>' +
            '</div>';
    }
    
    // Rimuovi modal dal DOM quando viene chiuso
    previewModal.addEventListener('hidden.bs.modal', function() {
        if (document.body.contains(previewModal)) {
            document.body.removeChild(previewModal);
        }
    });
}

// ===========================================
// UTILITY FUNCTIONS
// ===========================================

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    var k = 1024;
    var sizes = ['B', 'KB', 'MB', 'GB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}


// CORREZIONE: Funzione formatDateTime per gestire correttamente i timestamp DB2/iSeries
function formatDateTime(dateString) {
    if (!dateString || dateString === null || dateString === undefined || dateString === '') {
        return 'N/A';
    }
    
    var dateStr = String(dateString).trim();
    if (dateStr === '' || dateStr === 'null' || dateStr === 'undefined') {
        return 'N/A';
    }
    
    console.log('DEBUG formatDateTime - Input ricevuto:', dateStr);
    
    try {
        var date;
        
        // Gestisce formato DB2 timestamp: 2025-07-25-10.05.23.885723
        if (dateStr.match(/^\d{4}-\d{2}-\d{2}-\d{2}\.\d{2}\.\d{2}/)) {
            console.log('DEBUG: Riconosciuto formato DB2, iniziando parsing...');
            
            // CORREZIONE: Parsing pi� robusto per formato DB2
            // Trova l'ultimo trattino che separa data da ora
            var lastDashIndex = dateStr.lastIndexOf('-');
            console.log('DEBUG: Ultimo trattino trovato all\'indice:', lastDashIndex);
            
            if (lastDashIndex >= 10) { // Il trattino deve essere almeno alla posizione 10 (dopo YYYY-MM-DD)
                var datePart = dateStr.substring(0, lastDashIndex);
                var timePart = dateStr.substring(lastDashIndex + 1);
                
                console.log('DEBUG: Data part:', datePart);
                console.log('DEBUG: Time part:', timePart);
                
                // Converte la parte temporale: "10.05.23.885723" -> "10:05:23.885"
                var timeComponents = timePart.split('.');
                console.log('DEBUG: Time components:', timeComponents);
                
                if (timeComponents.length >= 3) {
                    // Ricostruisce: ore:minuti:secondi
                    var formattedTime = timeComponents[0] + ':' + timeComponents[1] + ':' + timeComponents[2];
                    console.log('DEBUG: Base time formatted:', formattedTime);
                    
                    // Aggiunge millisecondi se presenti (primi 3 caratteri dei microsecondi)
                    if (timeComponents.length > 3 && timeComponents[3]) {
                        var microseconds = String(timeComponents[3]);
                        var milliseconds = microseconds.substring(0, 3);
                        // Pad con zeri se necessario
                        while (milliseconds.length < 3) {
                            milliseconds += '0';
                        }
                        formattedTime += '.' + milliseconds;
                        console.log('DEBUG: Time with milliseconds:', formattedTime);
                    }
                    
                    var isoString = datePart + 'T' + formattedTime;
                    console.log('DEBUG: ISO string finale:', isoString);
                    
                    date = new Date(isoString);
                    console.log('DEBUG: Oggetto Date creato:', date);
                    console.log('DEBUG: Date.getTime():', date.getTime());
                    console.log('DEBUG: isNaN(date.getTime()):', isNaN(date.getTime()));
                    
                    // Verifica immediata se la data � valida
                    if (isNaN(date.getTime())) {
                        console.error('DEBUG: Data non valida, tentativo semplificato...');
                        // Prova senza millisecondi
                        var simpleTime = timeComponents[0] + ':' + timeComponents[1] + ':' + timeComponents[2];
                        var simpleIso = datePart + 'T' + simpleTime;
                        console.log('DEBUG: ISO semplificato:', simpleIso);
                        date = new Date(simpleIso);
                        console.log('DEBUG: Data semplificata:', date, 'Valid:', !isNaN(date.getTime()));
                    }
                } else {
                    console.error('DEBUG: Componenti time insufficienti:', timeComponents.length);
                    return 'N/A';
                }
            } else {
                console.error('DEBUG: LastDashIndex non valido:', lastDashIndex);
                return 'N/A';
            }
        } else {
            console.log('DEBUG: Formato non DB2, uso parsing standard');
            // Prova formato standard
            date = new Date(dateStr);
        }
        
        // Verifica finale che la data sia valida
        if (isNaN(date.getTime())) {
            console.warn('DEBUG: DateTime finale non valido:', dateString, 'Date object:', date);
            return 'N/A';
        }
        
        console.log('DEBUG: Parsing completato con successo, Date:', date);
        var result = date.toLocaleDateString('it-IT') + ' ' + date.toLocaleTimeString('it-IT', {
            hour: '2-digit',
            minute: '2-digit'
        });
        console.log('DEBUG: Risultato finale:', result);
        return result;
        
    } catch (error) {
        console.error('DEBUG: Errore durante il parsing:', error);
        console.error('DEBUG: Stack trace:', error.stack);
        return 'N/A';
    }
}

// CORREZIONE AGGIUNTIVA: Anche la funzione formatDate ha lo stesso problema
function formatDate(dateString) {
    if (!dateString || dateString === null || dateString === undefined || dateString === '') {
        return 'N/A';
    }
    
    var dateStr = String(dateString).trim();
    if (dateStr === '' || dateStr === 'null' || dateStr === 'undefined') {
        return 'N/A';
    }
    
    try {
        var date;
        
        // Gestisce formato numerico YYYYMMDD (es: 20240517)
        if (dateStr.match(/^\d{8}$/)) {
            var year = dateStr.substring(0, 4);
            var month = dateStr.substring(4, 6);
            var day = dateStr.substring(6, 8);
            var isoString = year + '-' + month + '-' + day;
            date = new Date(isoString);
            
            if (isNaN(date.getTime())) {
                console.warn('Data numerica non valida:', dateString);
                return 'N/A';
            }
            
            return date.toLocaleDateString('it-IT');
        }
        
        // Gestisce formato DB2 timestamp: 2025-07-25-14.52.34.126472
        if (dateStr.match(/^\d{4}-\d{2}-\d{2}-\d{2}\.\d{2}\.\d{2}/)) {
            console.log('Parsing DB2 date:', dateStr);
            
            // CORREZIONE: Usa la stessa logica robusta della formatDateTime
            var lastDashIndex = dateStr.lastIndexOf('-');
            if (lastDashIndex >= 10) {
                var datePart = dateStr.substring(0, lastDashIndex);
                var timePart = dateStr.substring(lastDashIndex + 1);
                
                var timeComponents = timePart.split('.');
                if (timeComponents.length >= 3) {
                    var formattedTime = timeComponents[0] + ':' + timeComponents[1] + ':' + timeComponents[2];
                    
                    if (timeComponents.length > 3 && timeComponents[3]) {
                        var microseconds = String(timeComponents[3]);
                        var milliseconds = microseconds.substring(0, 3).padEnd(3, '0');
                        formattedTime += '.' + milliseconds;
                    }
                    
                    var isoString = datePart + 'T' + formattedTime;
                    date = new Date(isoString);
                    
                    if (isNaN(date.getTime())) {
                        var simpleTime = timeComponents[0] + ':' + timeComponents[1] + ':' + timeComponents[2];
                        var simpleIso = datePart + 'T' + simpleTime;
                        date = new Date(simpleIso);
                    }
                } else {
                    return 'N/A';
                }
            } else {
                return 'N/A';
            }
        } else {
            // Prova formato standard
            date = new Date(dateStr);
        }
        
        // Verifica che la data sia valida
        if (isNaN(date.getTime())) {
            console.warn('Data non valida ricevuta dopo parsing:', dateString);
            return 'N/A';
        }
        
        return date.toLocaleDateString('it-IT');
    } catch (error) {
        console.error('Errore parsing data:', dateString, error);
        return 'N/A';
    }
}




function getFileIcon(filename) {
    var ext = filename.split('.').pop().toLowerCase();
    switch (ext) {
        case 'pdf': return 'fas fa-file-pdf text-danger';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'bmp': return 'fas fa-file-image text-primary';
        case 'tif':
        case 'tiff': return 'fas fa-file-image text-info';
        default: return 'fas fa-file text-secondary';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function truncateText(text, maxLength) {
    if (!text) return '';
    if (text.length <= maxLength) return escapeHtml(text);
    return escapeHtml(text.substring(0, maxLength)) + '...';
}

function showAlert(message, type) {
    // Crea alert temporaneo
    var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show position-fixed" ' +
        'style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">' +
        '<i class="fas fa-' + (type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle') + '"></i> ' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
        '</div>';
    
    // Rimuovi alert precedenti
    var existingAlerts = document.querySelectorAll('.alert.position-fixed');
    for (var i = 0; i < existingAlerts.length; i++) {
        existingAlerts[i].remove();
    }
    
    // Aggiungi nuovo alert
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-remove dopo 5 secondi
    setTimeout(function() {
        var alert = document.querySelector('.alert.position-fixed');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Funzioni di gestione permessi (stub functions se non esistono)
function canUserAddFiles() {
    if (!currentUser) return false;
    return ['ADMIN', 'BACKOFFICE', 'USER'].indexOf(currentUser.livello) !== -1;
}

function canUserDeleteFiles() {
	if (!currentUser) return false;
    
	    if (currentUser.livello === 'ADMIN') {
	        return true;
	    }
    
	    if (currentUser.livello === 'USER' && ticket && ticket.USERNAME_INS === currentUser.username) {
	        return true;
	    }
	    if (currentUser.livello === 'BACKOFFICE' && ticket && ticket.USERNAME_INS === currentUser.username) {
	        return true;
	    }
	
	    return false;
}

function canUserDeleteTickets() {
    if (!currentUser) return false;
	if (currentUser.livello === 'ADMIN') {
	        return true;
	    }
	
    if (currentUser.livello === 'USER' && ticket && ticket.USERNAME_INS === currentUser.username) {
        return true;
    }
    if (currentUser.livello === 'BACKOFFICE' && ticket && ticket.USERNAME_INS === currentUser.username) {
        return true;
    }
	 return false;
}

// Export risultati ricerca
function exportResults() {
    if (!searchResults || searchResults.length === 0) {
        showAlert('Nessun risultato da esportare', 'warning');
        return;
    }
    
    // Prepara dati per export CSV - MODIFICATO per includere le nuove colonne
    var csv = 'Ticket,Cartellino,Produttore,Ispettore,Data Registrazione,Nazione,Data Apertura,Data Consegna,File,Dimensione\n';
    for (var i = 0; i < searchResults.length; i++) {
        var ticket = searchResults[i];
        csv += '"' + ticket.NUM_TICKET + '","' + ticket.NUM_CARTEL + '","' + ticket.RAG_SOC_PR + '","' + ticket.NOME_ISPET + '","' + ticket.DATA_REG + '","' + (ticket.NAZIONE || '') + '","' + (ticket.DATA_APERTURA || '') + '","' + (ticket.DATA_CONSEGNA || '') + '","' + ticket.FILE_COUNT + '","' + formatFileSize(ticket.TOTAL_SIZE) + '"\n';
    }
    
    // Download CSV
    var blob = new Blob([csv], { type: 'text/csv' });
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'risultati_ricerca_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showAlert('Export completato', 'success');
}

// ===========================================
// GESTIONE EVENTI GLOBALI
// ===========================================

// Gestione errori di rete globali
window.addEventListener('online', function() {
    showAlert('Connessione ristabilita', 'success');
});

window.addEventListener('offline', function() {
    showAlert('Connessione persa. Verificare la rete.', 'warning');
});

// Aggiornamento automatico sessione ogni 10 minuti
// Con delay iniziale per Firefox
var isFirefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;
var initialDelay = isFirefox ? 60000 : 10000; // 60 secondi per Firefox, 10 per altri

setTimeout(function() {
    setInterval(function() {
        fetch('check_session.php')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.authenticated && !data.valid) {
                    showAlert('Sessione scaduta. Verrai reindirizzato al login.', 'warning');
                    setTimeout(function() {
                        window.location.href = 'login.html';
                    }, 3000);
                }
            })
            .catch(function(error) {
                console.error('Errore verifica sessione automatica:', error);
            });
    }, 10 * 60 * 1000); // 10 minuti
}, initialDelay);


// Conferma prima di uscire se ci sono modifiche non salvate
window.addEventListener('beforeunload', function(e) {
    var uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        var numTicket = document.getElementById('numTicket').value;
        var files = document.getElementById('fileAllegati').files;
        
        if (numTicket || files.length > 0) {
            e.preventDefault();
            e.returnValue = 'Ci sono modifiche non salvate. Vuoi davvero uscire?';
        }
    }
});

// Inizializza tooltips Bootstrap se presenti
document.addEventListener('DOMContentLoaded', function() {
	
 // Assicurati che currentUser sia sempre disponibile
    if (!currentUser && window.currentUser) {
        currentUser = window.currentUser;
        console.log('currentUser recuperato da window:', currentUser);
    }
    
    // Se currentUser � ancora null, c'� un problema serio
    if (!currentUser) {
        console.error('ERRORE CRITICO: currentUser non disponibile');
        // Prova a recuperarlo dalla sessione
        if (typeof getCurrentUser === 'function') {
            currentUser = getCurrentUser();
        }
    }
    var tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    for (var i = 0; i < tooltipTriggerList.length; i++) {
        new bootstrap.Tooltip(tooltipTriggerList[i]);
    }
});


function logout() {
    if (confirm('Sei sicuro di voler uscire?')) {
        // Chiamata POST per logout sicuro
        fetch('login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=logout'
        })
        .then(function(response) {
            // Indipendentemente dalla risposta, reindirizza al login
            window.location.href = 'login.html';
        })
        .catch(function(error) {
            console.error('Errore logout:', error);
            // Anche in caso di errore, reindirizza al login
            window.location.href = 'login.html';
        });
    }
}


// ===========================================
// GESTIONE AMMINISTRAZIONE - DA AGGIUNGERE AL TUO APP.JS
// ===========================================

function loadAdminData() {
    if (!currentUser || currentUser.livello !== 'ADMIN') {
        console.log('User non admin, skip admin data');
        return;
    }
    
    console.log('Loading admin data...');
    
    // Carica lista utenti
    loadUsersList();
    
    // Carica statistiche sistema
    loadSystemStats();
    
    // Carica log attivit�
    loadActivityLog();
}

function loadUsersList() {
    console.log('Loading users list...');
    
    fetch('admin.php?step=6&action=users')
        .then(function(response) {
            console.log('Users response status:', response.status);
            
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(function(text) {
            console.log('Raw response text length:', text.length);
            
            try {
                var data = JSON.parse(text);
                console.log('Parsed users data:', data);
                
                if (data.success) {
                    displayUsersList(data.users);
                } else {
                    document.getElementById('usersList').innerHTML = 
                        '<div class="alert alert-danger">Errore: ' + 
                        (data.error || 'Errore sconosciuto') + '</div>';
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw text causing error:', text.substring(0, 500));
                document.getElementById('usersList').innerHTML = 
                    '<div class="alert alert-danger">Errore parsing risposta: ' + 
                    parseError.message + '</div>';
            }
        })
        .catch(function(error) {
            console.error('Errore caricamento utenti:', error);
            document.getElementById('usersList').innerHTML = 
                '<div class="alert alert-danger">Errore di connessione: ' + 
                error.message + '</div>';
        });
}

function loadSystemStats() {
    console.log('Loading system stats...');
    
    fetch('admin.php?step=5&action=system_stats')
        .then(function(response) {
            console.log('System stats response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(function(text) {
            console.log('Raw stats response text length:', text.length);
            
            try {
                var data = JSON.parse(text);
                console.log('Parsed system stats data:', data);
                
                if (data.success) {
                    displaySystemStats(data.stats);
                } else {
                    document.getElementById('systemStats').innerHTML = 
                        '<div class="alert alert-danger">Errore: ' + 
                        (data.error || 'Errore sconosciuto') + '</div>';
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw text causing error:', text.substring(0, 500));
                document.getElementById('systemStats').innerHTML = 
                    '<div class="alert alert-danger">Errore parsing risposta: ' + 
                    parseError.message + '</div>';
            }
        })
        .catch(function(error) {
            console.error('Errore caricamento statistiche:', error);
            document.getElementById('systemStats').innerHTML = 
                '<div class="alert alert-danger">Errore di connessione: ' + 
                error.message + '</div>';
        });
}

function loadActivityLog() {
    console.log('Loading activity log...');
    
    fetch('admin.php?step=4&action=activity_log&per_page=20')
        .then(function(response) {
            console.log('Activity log response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(function(text) {
            console.log('Raw log response text length:', text.length);
            
            try {
                var data = JSON.parse(text);
                console.log('Parsed activity log data:', data);
                
                if (data.success) {
                    displayActivityLog(data.log_entries);
                } else {
                    document.getElementById('activityLog').innerHTML = 
                        '<div class="alert alert-danger">Errore: ' + 
                        (data.error || 'Errore sconosciuto') + '</div>';
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw text causing error:', text.substring(0, 500));
                document.getElementById('activityLog').innerHTML = 
                    '<div class="alert alert-danger">Errore parsing risposta: ' + 
                    parseError.message + '</div>';
            }
        })
        .catch(function(error) {
            console.error('Errore caricamento log:', error);
            document.getElementById('activityLog').innerHTML = 
                '<div class="alert alert-danger">Errore di connessione: ' + 
                error.message + '</div>';
        });
}

function displaySystemStats(stats) {
    var container = document.getElementById('systemStats');
    
    if (!container) {
        console.error('Container systemStats not found');
        return;
    }
    
    if (!stats) {
        container.innerHTML = '<div class="alert alert-warning">Nessuna statistica disponibile</div>';
        return;
    }
    
    var html = '<div class="row">';
    html += '<div class="col-md-6 mb-3">';
    html += '<div class="card border-primary">';
    html += '<div class="card-body text-center">';
    html += '<h5 class="card-title text-primary">' + (stats.total_users || 0) + '</h5>';
    html += '<p class="card-text">Utenti Totali</p>';
    html += '</div></div></div>';
    
    html += '<div class="col-md-6 mb-3">';
    html += '<div class="card border-success">';
    html += '<div class="card-body text-center">';
    html += '<h5 class="card-title text-success">' + (stats.active_users || 0) + '</h5>';
    html += '<p class="card-text">Utenti Attivi</p>';
    html += '</div></div></div>';
    
    html += '<div class="col-md-6 mb-3">';
    html += '<div class="card border-info">';
    html += '<div class="card-body text-center">';
    html += '<h5 class="card-title text-info">' + (stats.total_tickets || 0) + '</h5>';
    html += '<p class="card-text">Ticket Totali</p>';
    html += '</div></div></div>';
    
    html += '<div class="col-md-6 mb-3">';
    html += '<div class="card border-warning">';
    html += '<div class="card-body text-center">';
    html += '<h5 class="card-title text-warning">' + (stats.active_sessions || 0) + '</h5>';
    html += '<p class="card-text">Sessioni Attive</p>';
    html += '</div></div></div>';
    html += '</div>';
    
    html += '<div class="mt-3">';
    html += '<h6>Spazio Utilizzato: <span class="badge bg-secondary">' + 
            (stats.total_size_mb || 0) + ' MB</span></h6>';
    html += '</div>';
    
    container.innerHTML = html;
}

function displayActivityLog(logEntries) {
    var container = document.getElementById('activityLog');
    
    if (!container) {
        console.error('Container activityLog not found');
        return;
    }
    
    if (!logEntries || logEntries.length === 0) {
        container.innerHTML = '<div class="alert alert-info">Nessun log di attivit� disponibile</div>';
        return;
    }
    
    var html = '<div class="table-responsive">';
    html += '<table class="table table-sm table-hover">';
    html += '<thead><tr>';
    html += '<th>Data/Ora</th>';
    html += '<th>Azione</th>';
    html += '<th>Utente</th>';
    html += '<th>Dettagli</th>';
    html += '</tr></thead><tbody>';
    
    for (var i = 0; i < logEntries.length; i++) {
        var entry = logEntries[i];
        html += '<tr>';
        html += '<td><small>' + formatDateTime(entry.DATA_ORA) + '</small></td>';
        html += '<td><span class="badge bg-primary">' + escapeHtml(entry.AZIONE) + '</span></td>';
        html += '<td>' + escapeHtml(entry.USERNAME || 'Sistema') + '</td>';
        html += '<td><small>' + escapeHtml(entry.DETTAGLI || '-') + '</small></td>';
        html += '</tr>';
    }
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function displayUsersList(users) {
    var container = document.getElementById('usersList');
    
    if (!container) {
        console.error('Container usersList not found');
        return;
    }
    
    if (!users || users.length === 0) {
        container.innerHTML = '<p class="text-muted">Nessun utente trovato</p>';
        return;
    }
    
    var html = '<div class="table-responsive">';
    html += '<table class="table table-sm table-hover">';
    html += '<thead><tr>';
    html += '<th>Username</th>';
    html += '<th>Nome</th>';
    html += '<th>Livello</th>';
    html += '<th>Stato</th>';
    html += '<th>Azioni</th>';
    html += '</tr></thead><tbody>';
    
    for (var i = 0; i < users.length; i++) {
        var user = users[i];
        var statusBadge = user.ATTIVO === 'S' ? 
            '<span class="badge bg-success">Attivo</span>' : 
            '<span class="badge bg-danger">Disattivo</span>';
        
        html += '<tr>';
        html += '<td><strong>' + escapeHtml(user.USERNAME) + '</strong></td>';
        html += '<td>' + escapeHtml(user.NOME_COMPL) + '</td>';
        html += '<td><span class="badge bg-primary">' + user.LIVELLO + '</span></td>';
        html += '<td>' + statusBadge + '</td>';
        html += '<td>';
        html += '<button class="btn btn-sm btn-outline-primary" onclick="editUser(\'' + user.USERNAME + '\')">';
        html += '<i class="fas fa-edit"></i>';
        html += '</button>';
        html += '</td>';
        html += '</tr>';
    }
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function showUserManagement() {
    // Reset form
    document.getElementById('userForm').reset();
    document.getElementById('userAction').value = 'create';
    document.getElementById('originalUsername').value = '';
    
    // Mostra modal
    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}

function editUser(username) {
    // Carica dati utente e mostra modal di modifica
    fetch('admin.php?action=user_detail&username=' + encodeURIComponent(username))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                // Popola form con dati utente
                document.getElementById('userUsername').value = data.user.USERNAME;
                document.getElementById('userPassword').value = ''; // Non mostrare password
                document.getElementById('userNome').value = data.user.NOME_COMPL;
                document.getElementById('userLivello').value = data.user.LIVELLO;
                document.getElementById('userAttivo').checked = data.user.ATTIVO === 'S';
                
                document.getElementById('userAction').value = 'edit';
                document.getElementById('originalUsername').value = username;
                
                // Mostra modal
                var modal = new bootstrap.Modal(document.getElementById('userModal'));
                modal.show();
            } else {
                showAlert('Errore caricamento utente: ' + (data.error || 'Errore sconosciuto'), 'danger');
            }
        })
        .catch(function(error) {
            console.error('Errore caricamento utente:', error);
            showAlert('Errore di connessione', 'danger');
        });
}

function saveUser() {
    var formData = new FormData();
    
    formData.append('action', document.getElementById('userAction').value);
    formData.append('username', document.getElementById('userUsername').value);
    formData.append('password', document.getElementById('userPassword').value);
    formData.append('nome_completo', document.getElementById('userNome').value);
    formData.append('livello', document.getElementById('userLivello').value);
    formData.append('attivo', document.getElementById('userAttivo').checked ? 'S' : 'N');
    formData.append('original_username', document.getElementById('originalUsername').value);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showAlert('Utente salvato con successo', 'success');
            
            // Chiudi modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
            modal.hide();
            
            // Ricarica lista utenti
            loadUsersList();
        } else {
            showAlert('Errore salvataggio: ' + (data.error || 'Errore sconosciuto'), 'danger');
        }
    })
    .catch(function(error) {
        console.error('Errore salvataggio utente:', error);
        showAlert('Errore di connessione', 'danger');
    });
}

// Funzioni di gestione permessi corrette
function canUserAddFiles() {
    if (!currentUser) return false;
    return ['ADMIN', 'BACKOFFICE', 'USER'].indexOf(currentUser.livello) !== -1;
}

function canUserDeleteFiles() {
    if (!currentUser) return false;
    return ['ADMIN', 'BACKOFFICE'].indexOf(currentUser.livello) !== -1;
}

function canUserDeleteTickets() {
    if (!currentUser) return false;
    return currentUser.livello === 'ADMIN';
}

/**
 * CORREZIONE 2: Funzioni modali per gestione ticket - MANCANTI
 * Queste funzioni sono richiamate dai pulsanti ma non esistono nel codice
 */

function showAddFilesModal(ticketId, ticketNumber) {
    if (!canUserAddFiles()) {
        showAlert('Non hai i permessi per aggiungere file', 'warning');
        return;
    }
    
    // Reset form
    if (document.getElementById('addFilesForm')) {
        document.getElementById('addFilesForm').reset();
    }
    
    // Imposta ticket ID nei campi hidden
    if (document.getElementById('addFilesTicketId')) {
        document.getElementById('addFilesTicketId').value = ticketId;
    }
    
    if (document.getElementById('addFilesTicketNumber')) {
        document.getElementById('addFilesTicketNumber').textContent = '#' + ticketNumber;
    }
    
    // Mostra modal (se esiste nel HTML)
    var modal = document.getElementById('addFilesModal');
    if (modal) {
        var bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    } else {
        showAlert('Modal aggiungi file non trovato', 'warning');
    }
}

function confirmDeleteTicket(ticketId, ticketNumber) {
    if (!canUserDeleteTickets()) {
        showAlert('Non hai i permessi per cancellare ticket', 'warning');
        return;
    }
    
    var message = 'Sei sicuro di voler cancellare COMPLETAMENTE il ticket #' + ticketNumber + '?\n\n' +
                  'Questa azione canceller�:\n' +
                  '- Il record del ticket\n' +
                  '- Tutti i file allegati\n' +
                  '- QUESTA OPERAZIONE NON PU� ESSERE ANNULLATA';
    
    if (confirm(message)) {
        deleteTicket(ticketId, ticketNumber);
    }
}

function confirmDeleteFile(fileId, fileName, ticketId) {
    if (!canUserDeleteFiles()) {
        showAlert('Non hai i permessi per cancellare file', 'warning');
        return;
    }
    
    var message = 'Sei sicuro di voler cancellare il file "' + fileName + '"?\n\n' +
                  'Questa operazione non pu� essere annullata.';
    
    if (confirm(message)) {
        deleteFile(fileId, fileName, ticketId);
    }
}

/**
 * CORREZIONE 3: Funzioni di cancellazione effettive - MANCANTI
 */

function deleteTicket(ticketId, ticketNumber) {
    if (!ticketId || ticketId <= 0) {
        showAlert('ID ticket non valido', 'danger');
        return;
    }
    
    // Crea form data
    var formData = new FormData();
    formData.append('action', 'delete_ticket');
    formData.append('ticket_id', ticketId);
    
    // Disabilita pulsante durante l'operazione
    var deleteButton = document.querySelector('[onclick*="confirmDeleteTicket(' + ticketId + '"]');
    if (deleteButton) {
        deleteButton.disabled = true;
        deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    
    // Chiamata AJAX
    fetch('ticket_management.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        // Riabilita pulsante
        if (deleteButton) {
            deleteButton.disabled = false;
            deleteButton.innerHTML = '<i class="fas fa-trash"></i>';
        }
        
        if (data.success) {
            showAlert('Ticket #' + ticketNumber + ' cancellato con successo', 'success');
            
            // Chiudi modal dettagli se aperto
            var detailModal = bootstrap.Modal.getInstance(document.getElementById('ticketModal'));
            if (detailModal) {
                detailModal.hide();
            }
            
            // Ricarica dati
            setTimeout(function() {
                loadDashboardData();
                // Se siamo in ricerca, ricarica anche quella
                if (document.getElementById('searchResults').style.display !== 'none') {
                    performSearch();
                }
            }, 1000);
            
        } else {
            showAlert('Errore cancellazione ticket: ' + (data.error || 'Errore sconosciuto'), 'danger');
        }
    })
    .catch(function(error) {
        // Riabilita pulsante
        if (deleteButton) {
            deleteButton.disabled = false;
            deleteButton.innerHTML = '<i class="fas fa-trash"></i>';
        }
        
        console.error('Errore cancellazione ticket:', error);
        showAlert('Errore di connessione durante la cancellazione', 'danger');
    });
}

function deleteFile(fileId, fileName, ticketId) {
    if (!fileId || fileId <= 0) {
        showAlert('ID file non valido', 'danger');
        return;
    }
    
    // Crea form data
    var formData = new FormData();
    formData.append('action', 'delete_file');
    formData.append('file_id', fileId);
    
    // Disabilita pulsante durante l'operazione
    var deleteButton = document.querySelector('[onclick*="confirmDeleteFile(' + fileId + '"]');
    if (deleteButton) {
        deleteButton.disabled = true;
        deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    
    // Chiamata AJAX
    fetch('ticket_management.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        // Riabilita pulsante
        if (deleteButton) {
            deleteButton.disabled = false;
            deleteButton.innerHTML = '<i class="fas fa-trash"></i>';
        }
        
        if (data.success) {
            showAlert('File "' + fileName + '" cancellato con successo', 'success');
            
            // Ricarica dettagli ticket per aggiornare la lista file
            viewTicketDetails(ticketId);
            
        } else {
            showAlert('Errore cancellazione file: ' + (data.error || 'Errore sconosciuto'), 'danger');
        }
    })
    .catch(function(error) {
        // Riabilita pulsante
        if (deleteButton) {
            deleteButton.disabled = false;
            deleteButton.innerHTML = '<i class="fas fa-trash"></i>';
        }
        
        console.error('Errore cancellazione file:', error);
        showAlert('Errore di connessione durante la cancellazione', 'danger');
    });
}