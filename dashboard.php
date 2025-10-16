<?php
// dashboard.php - Pagina principale dashboard
// Compatible con PHP 5.2 - Versione modulare con caricamento on-demand

require_once 'dashboard_config.php';



require_once 'init_firefox.php';
require_once 'config.php';

// Avvia sessione
startSessionSafe();

// Verifica manuale senza chiamare isValidSession
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(array('error' => 'Autenticazione richiesta', 'redirect' => 'login.html'));
    exit;
}

// Verifica timeout
if (isset($_SESSION['expires_at']) && $_SESSION['expires_at'] < time()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(array('error' => 'Sessione scaduta', 'redirect' => 'login.html'));
    exit;
}


// Il controllo IP è già fatto in dashboard_config.php
// L'accesso è già bloccato se l'IP non è autorizzato

// Per ora commentiamo l'autenticazione per test
// require_once 'config.php';
// requireAuth();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Aziendale</title>
    
    <!-- jQuery 1.x per compatibilità con PHP 5.2 / browser vecchi -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            padding: 10px;
        }

        .dashboard-header {
            background: white;
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }

        .date-selector {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .date-selector select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 13px;
        }

        .current-date {
            color: #7f8c8d;
            font-size: 13px;
            font-weight: 500;
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .kpi-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            border-top: 3px solid #3498db;
            position: relative;
        }

        .kpi-card.loading {
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .kpi-title {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .kpi-value {
            font-size: 22px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .kpi-subtitle {
            font-size: 10px;
            color: #95a5a6;
        }

        .kpi-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
        }

        .trend-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .trend-up {
            background: #d4edda;
            color: #27ae60;
        }

        .trend-down {
            background: #f8d7da;
            color: #e74c3c;
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }

        /* Stili per sezione bottoni modulari */
        .module-selector {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            text-align: center;
        }

        .module-selector-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .module-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .module-btn {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .module-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .module-btn.active {
            background: #27ae60;
        }

        .module-btn.loading {
            background: #95a5a6;
            cursor: not-allowed;
        }

        .module-btn .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 10px;
        }

        /* Container per moduli dinamici */
        .dynamic-module-container {
            margin: 20px 0;
        }

        .module-loading-overlay {
            background: rgba(255,255,255,0.9);
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
        }

        .module-loading-overlay .loading-text {
            margin-top: 10px;
            color: #2c3e50;
            font-size: 14px;
        }

        /* Stili per tabella portafoglio ordini */
        .portafoglio-container {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        .portafoglio-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .portafoglio-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }

        .portafoglio-info {
            font-size: 12px;
            color: #7f8c8d;
        }

        .portafoglio-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .portafoglio-table thead {
            background: #34495e;
            color: white;
        }

        .portafoglio-table thead th {
            padding: 8px 4px;
            text-align: right;
            font-weight: 600;
            font-size: 10px;
            white-space: nowrap;
            border-right: 1px solid #2c3e50;
        }

        .portafoglio-table thead th:first-child {
            text-align: left;
            padding-left: 10px;
        }

        .portafoglio-table thead th:last-child {
            border-right: none;
        }

        .portafoglio-table tbody tr {
            border-bottom: 1px solid #ecf0f1;
        }

        .portafoglio-table tbody tr:hover {
            background: #f8f9fa;
        }

        .portafoglio-table tbody td {
            padding: 6px 4px;
            text-align: right;
            border-right: 1px solid #ecf0f1;
        }

        .portafoglio-table tbody td:first-child {
            text-align: left;
            padding-left: 10px;
            font-weight: 600;
            color: #2c3e50;
        }

        .portafoglio-table tbody td:last-child {
            border-right: none;
        }

        .portafoglio-table .totale-row {
            background: #ecf0f1;
            font-weight: bold;
        }

        .portafoglio-table .totale-row td {
            border-top: 2px solid #34495e;
            padding: 8px 4px;
        }

        .valore-positivo {
            color: #27ae60;
        }

        .valore-negativo {
            color: #e74c3c;
        }

        .periodo-header {
            background: #2c3e50 !important;
        }

        .colonna-magazzino {
            background: #f5f6fa;
        }
        
        /* Stili per tabella modelli/nazioni */
        .modelli-nazioni-container {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        .modelli-nazioni-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .modelli-nazioni-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }

        .modelli-nazioni-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .modelli-nazioni-table thead {
            background: #34495e;
            color: white;
        }

        .modelli-nazioni-table thead th {
            padding: 8px 4px;
            text-align: right;
            font-weight: 600;
            font-size: 10px;
            white-space: nowrap;
            border-right: 1px solid #2c3e50;
        }

        .modelli-nazioni-table thead th:first-child {
            text-align: left;
            padding-left: 10px;
        }

        .modelli-nazioni-table thead th:last-child {
            border-right: none;
            background: #e74c3c;
        }

        .modelli-nazioni-table tbody tr {
            border-bottom: 1px solid #ecf0f1;
        }

        .modelli-nazioni-table tbody tr:hover {
            background: #f8f9fa;
        }

        .modelli-nazioni-table tbody td {
            padding: 6px 4px;
            text-align: right;
            border-right: 1px solid #ecf0f1;
        }

        .modelli-nazioni-table tbody td:first-child {
            text-align: left;
            padding-left: 10px;
            font-weight: 600;
            color: #2c3e50;
        }

        .modelli-nazioni-table tbody td:last-child {
            border-right: none;
            font-weight: bold;
            background: #fff5f5;
        }

        .modelli-nazioni-table .totale-row {
            background: #ecf0f1;
            font-weight: bold;
        }

        .modelli-nazioni-table .totale-row td {
            border-top: 2px solid #34495e;
            padding: 8px 4px;
        }

        .modelli-nazioni-table .totale-row td:last-child {
            background: #34495e;
            color: white;
        }

        .modelli-nazioni-table .altri-modelli-row {
            background: #f5f6fa;
            font-style: italic;
        }
        
        /* Stili per produzione tappezzieri */
        .produzione-container {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        .produzione-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .produzione-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }

        .produzione-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .produzione-table thead {
            background: #34495e;
            color: white;
        }

        .produzione-table thead th {
            padding: 8px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            border-right: 1px solid #2c3e50;
        }

        .produzione-table thead th:last-child {
            text-align: right;
            border-right: none;
        }

        .produzione-table tbody tr {
            border-bottom: 1px solid #ecf0f1;
        }

        .produzione-table tbody tr:hover {
            background: #f8f9fa;
        }

        .produzione-table tbody td {
            padding: 6px 8px;
            text-align: left;
        }

        .produzione-table tbody td:last-child {
            text-align: right;
            font-weight: 600;
        }

        .produzione-table .loc-totale {
            background: #f5f6fa;
            font-weight: bold;
        }

        .produzione-table .totale-generale {
            background: #ecf0f1;
            font-weight: bold;
            font-size: 13px;
        }

        .produzione-table .totale-generale td {
            padding: 10px 8px;
            border-top: 2px solid #34495e;
        }
		
		<!-- 1. AGGIUNGI QUESTO STILE NEL TAG <style> -->
		.fornitore-selector {
		    background: #fff;
		    padding: 10px 15px;
		    margin: 10px 0;
		    border-radius: 5px;
		    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
		    display: none; /* Nascosto inizialmente */
		}

		.fornitore-selector select {
		    padding: 6px 10px;
		    border: 1px solid #ddd;
		    border-radius: 4px;
		    background: white;
		    cursor: pointer;
		    font-size: 13px;
		    margin-left: 10px;
		}

		.fornitore-info {
		    display: inline-block;
		    margin-left: 15px;
		    padding: 5px 10px;
		    background: #e8f5e9;
		    border-radius: 4px;
		    font-size: 12px;
		    color: #27ae60;
		}
    </style>
</head>
<body>
    <!-- Header -->
    <div class="dashboard-header">
        <div class="company-name">📊 Dashboard Aziendale</div>
        <div class="date-selector">
            <select id="periodSelector">
                <option value="Oggi">Oggi</option>
                <option value="Questa Settimana">Questa Settimana</option>
                <option value="Mese" selected>Mese</option>
                <option value="1 Trimestre">1° Trimestre</option>
                <option value="2 Trimestre">2° Trimestre</option>
                <option value="3 Trimestre">3° Trimestre</option>
                <option value="4 Trimestre">4° Trimestre</option>
                <option value="Anno">Anno</option>
            </select>
            <button id="refreshButton" style="padding: 6px 15px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; margin-left: 10px;">
                🔄 Aggiorna dati
            </button>
            <span class="current-date"><?php echo date('d/m/Y'); ?></span>
        </div>
    </div>
    
    <!-- Periodo visualizzato -->
    <div style="background: #fff; padding: 8px 20px; margin-bottom: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
        <span style="font-size: 13px; color: #7f8c8d;">Periodo visualizzato: </span>
        <strong id="periodo-display" style="font-size: 14px; color: #2c3e50;">-</strong>
    </div>

    <!-- KPI Cards per Fatturato -->
    <div id="fatturato-container" class="dashboard-container">
        <!-- Cards verranno popolate via JavaScript -->
        <div class="kpi-card loading">
            <div class="loading-spinner"></div>
        </div>
    </div>
    
    <!-- Separatore sezione -->
    <div style="background: #fff; padding: 10px 20px; margin: 20px 0 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
        <h3 style="font-size: 16px; color: #2c3e50; margin: 0;">📦 Ordinato</h3>
    </div>
    
    <!-- KPI Cards per Ordinato -->
    <div id="ordinato-container" class="dashboard-container">
        <!-- Cards verranno popolate via JavaScript -->
        <div class="kpi-card loading">
            <div class="loading-spinner"></div>
        </div>
    </div>
    
    <!-- Portafoglio Ordini -->
    <div class="portafoglio-container">
        <div class="portafoglio-header">

      <div class="portafoglio-title">📋 PORTAFOGLIO ORDINI IN LISTA A VALORE ALLA DATA DEL: <span id="data-rilascio">--/--/----</span> ore <span id="ora-aggiornamento">--:--</span></div>
	        <div class="portafoglio-info">
                <span style="color: #e74c3c; font-weight: bold;">Valore magazzino NON incluso in totale portafoglio</span>
            </div>
        </div>
        <div id="portafoglio-table-container">
            <!-- Tabella verrà popolata via JavaScript -->
            <div class="loading-spinner" style="margin: 20px auto;"></div>
        </div>
    </div>
    
    <!-- NUOVA SEZIONE: Selettore Moduli -->
    <div class="module-selector">
        <div class="module-selector-title">📈 Seleziona i report aggiuntivi da visualizzare</div>
        <div class="module-buttons">
            <button class="module-btn" onclick="toggleModule('modelli-posti')" id="btn-modelli-posti">
                📊 Venduto modelli a posti
            </button>
            <button class="module-btn" onclick="toggleModule('modelli-valore')" id="btn-modelli-valore">
                💰 Venduto modelli a valore
            </button>
            <button class="module-btn" onclick="toggleModule('produzione-tappezzieri')" id="btn-produzione-tappezzieri">
                🛋️ Produzione Tappezzieri
            </button>
        </div>
    </div>
    
    <!-- Container per moduli dinamici -->
    <div id="dynamic-modules" class="dynamic-module-container">
        <!-- I moduli verranno caricati qui dinamicamente -->
    </div>

    <!-- Overlay di caricamento -->
    <div class="module-loading-overlay" id="moduleLoadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Caricamento in corso...</div>
    </div>

    <!-- Messaggio di errore/successo -->
    <div id="message-container"></div>

  <script>
      // Variabili globali
      var currentPeriodo = 'Mese';
      var activeModules = {};  // Traccia i moduli attivi
    
      // Variabili globali per fornitori
      var fornitori_loaded = false;
      var fornitori_list = [];
    
      // Funzione per formattare euro
      function formatEuro(value) {
          return '€ ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
      }
    
      // FUNZIONI FORNITORI
    
      // Funzione per caricare lista fornitori
      function loadFornitori() {
          if (fornitori_loaded) {
              // Se già caricati, popola direttamente le select
              populateFornitoriSelects();
              return;
          }
        
          $.ajax({
              url: 'get_fornitori.php',
              type: 'GET',
              data: {
                  periodo: currentPeriodo
              },
              dataType: 'json',
              success: function(data) {
                  console.log('Fornitori caricati:', data);
                
                  if (data.error) {
                      console.log('Errore caricamento fornitori:', data.error);
                      return;
                  }
                
                  if (data.fornitori && data.fornitori.length > 0) {
                      fornitori_list = data.fornitori;
                      fornitori_loaded = true;
                      populateFornitoriSelects();
                  }
              },
              error: function(xhr, status, error) {
                  console.log('Errore AJAX fornitori:', error);
              }
          });
      }

      // Funzione per popolare le select dei fornitori
      function populateFornitoriSelects() {
          // Popola select posti
          var selectPosti = $('#fornitore-select-posti');
          if (selectPosti.length) {
              selectPosti.empty();
              for (var i = 0; i < fornitori_list.length; i++) {
                  var fornitore = fornitori_list[i];
                  var optionText = fornitore.codice === '*ALL*' ? 
                                 fornitore.ragione_sociale : 
                                 fornitore.codice + ' - ' + fornitore.ragione_sociale;
                  selectPosti.append('<option value="' + fornitore.codice + '">' + optionText + '</option>');
              }
            
              // Mostra il selettore se era nascosto
              $('#fornitore-selector-posti').show();
          }
        
          // Popola select valore
          var selectValore = $('#fornitore-select-valore');
          if (selectValore.length) {
              selectValore.empty();
              for (var i = 0; i < fornitori_list.length; i++) {
                  var fornitore = fornitori_list[i];
                  var optionText = fornitore.codice === '*ALL*' ? 
                                 fornitore.ragione_sociale : 
                                 fornitore.codice + ' - ' + fornitore.ragione_sociale;
                  selectValore.append('<option value="' + fornitore.codice + '">' + optionText + '</option>');
              }
            
              // Mostra il selettore se era nascosto
              $('#fornitore-selector-valore').show();
          }
      }

      // Funzione per applicare filtro fornitore (QUESTA ERA LA FUNZIONE MANCANTE!)
      function applyFornitoreFilter(tipo) {
          var selectedFornitore, infoSpan;
        
          if (tipo === 'posti') {
              selectedFornitore = $('#fornitore-select-posti').val();
              infoSpan = $('#fornitore-info-posti');
          } else if (tipo === 'valore') {
              selectedFornitore = $('#fornitore-select-valore').val();
              infoSpan = $('#fornitore-info-valore');
          } else {
              console.log('Tipo filtro non valido:', tipo);
              return;
          }
        
          console.log('Applicando filtro fornitore', tipo, ':', selectedFornitore);
        
          // Mostra info fornitore selezionato
          if (selectedFornitore && selectedFornitore !== '*ALL*') {
              // Trova il nome del fornitore
              var nomeFornitore = 'Fornitore sconosciuto';
              for (var i = 0; i < fornitori_list.length; i++) {
                  if (fornitori_list[i].codice === selectedFornitore) {
                      nomeFornitore = fornitori_list[i].ragione_sociale;
                      break;
                  }
              }
              infoSpan.text('Filtrato per: ' + selectedFornitore + ' - ' + nomeFornitore).show();
          } else {
              infoSpan.hide();
          }
        
          // Ricarica i dati con il filtro
          if (tipo === 'posti') {
              loadModelliPostiWithFilter(selectedFornitore);
          } else if (tipo === 'valore') {
              loadModelliValoreWithFilter(selectedFornitore);
          }
      }

      // Funzione per caricare modelli posti con filtro fornitore
      function loadModelliPostiWithFilter(fornitore) {
          $('#modelli-posti-table-container').html('<div class="loading-spinner" style="margin: 20px auto;"></div>');
        
          var requestData = {
              periodo: currentPeriodo
          };
        
          if (fornitore && fornitore !== '*ALL*') {
              requestData.fornitore = fornitore;
          }
        
          $.ajax({
              url: 'get_modelli_posti.php',
              type: 'GET',
              data: requestData,
              dataType: 'json',
              timeout: 120000,
              success: function(data) {
                  console.log('Dati modelli/posti con filtro ricevuti:', data);
                
                  if (data.error) {
                      showError('Modelli/Posti: ' + data.error);
                      $('#modelli-posti-table-container').html('<div class="error-message">Errore nel caricamento dati</div>');
                      return;
                  }
                
                  renderModelliPostiTable(data);
              },
              error: function(xhr, status, error) {
                  showError('Errore nel caricamento modelli/posti: ' + error);
                  $('#modelli-posti-table-container').html('<div class="error-message">Errore nel caricamento dati</div>');
              }
          });
      }

      // Funzione per caricare modelli valore con filtro fornitore  
      function loadModelliValoreWithFilter(fornitore) {
          $('#modelli-valore-table-container').html('<div class="loading-spinner" style="margin: 20px auto;"></div>');
        
          var requestData = {
              periodo: currentPeriodo
          };
        
          if (fornitore && fornitore !== '*ALL*') {
              requestData.fornitore = fornitore;
          }
        
          $.ajax({
              url: 'get_modelli_valore.php',
              type: 'GET',
              data: requestData,
              dataType: 'json',
              timeout: 120000,
              success: function(data) {
                  console.log('Dati modelli/valore con filtro ricevuti:', data);
                
                  if (data.error) {
                      showError('Modelli/Valore: ' + data.error);
                      $('#modelli-valore-table-container').html('<div class="error-message">Errore nel caricamento dati</div>');
                      return;
                  }
                
                  renderModelliValoreTable(data);
              },
              error: function(xhr, status, error) {
                  showError('Errore nel caricamento modelli/valore: ' + error);
                  $('#modelli-valore-table-container').html('<div class="error-message">Errore nel caricamento dati</div>');
              }
          });
      }
    
      // FUNZIONI DASHBOARD PRINCIPALI
    
      // Funzione per refresh completo dei dati
      function refreshAllData() {
          // Mostra overlay di caricamento
          $('#moduleLoadingOverlay').fadeIn();
        
          // Ricarica dati base
          loadFatturato();
          loadOrdinato();
          loadPortafoglioOrdini();
        
          // Ricarica solo i moduli attivi
          if (activeModules['modelli-posti']) {
              loadModelliPosti();
          }
          if (activeModules['modelli-valore']) {
              loadModelliValore();
          }
          if (activeModules['produzione-tappezzieri']) {
              loadProduzioneTappezzieri();
          }
        
          // Nascondi overlay dopo un attimo
          setTimeout(function() {
              $('#moduleLoadingOverlay').fadeOut();
              showSuccess('Dati aggiornati con successo!');
          }, 1000);
      }
    
      // Funzione per toggle moduli
      function toggleModule(moduleName) {
          var btn = $('#btn-' + moduleName);
          var moduleContainer = $('#module-' + moduleName);
        
          if (activeModules[moduleName]) {
              // Rimuovi modulo
              moduleContainer.slideUp(400, function() {
                  $(this).remove();
              });
              btn.removeClass('active');
              activeModules[moduleName] = false;
          } else {
              // Carica modulo
              loadModule(moduleName);
              btn.addClass('active');
              activeModules[moduleName] = true;
          }
      }
    
      // Funzione per caricare un modulo
      function loadModule(moduleName) {
          // Mostra overlay di caricamento
          $('#moduleLoadingOverlay').fadeIn();
        
          // Container per il modulo
          var moduleHtml = '<div id="module-' + moduleName + '" style="display:none;">';
        
          switch(moduleName) {
              case 'modelli-posti':
                  moduleHtml += '<div class="modelli-nazioni-container">';
                  moduleHtml += '<div class="modelli-nazioni-header">';
                  moduleHtml += '<div class="modelli-nazioni-title">📊 Venduto Modelli per Nazione (Posti) - Periodo: <span id="periodo-modelli-posti">--</span></div>';
                  moduleHtml += '<div class="portafoglio-info">';
                  moduleHtml += '<span style="color: #7f8c8d; font-size: 12px;">Quantità in posti</span>';
                  moduleHtml += '<span id="fornitore-info-posti" class="fornitore-info" style="display:none;"></span>';
                  moduleHtml += '</div>';
                  moduleHtml += '</div>';
                  moduleHtml += '<div class="fornitore-selector" id="fornitore-selector-posti">';
                  moduleHtml += '<label style="font-weight: 600; color: #2c3e50;">Filtra per fornitore:</label>';
                  moduleHtml += '<select id="fornitore-select-posti">';
                  moduleHtml += '<option value="*ALL*">Caricamento fornitori...</option>';
                  moduleHtml += '</select>';
                  moduleHtml += '<button onclick="applyFornitoreFilter(\'posti\')" style="margin-left: 10px; padding: 5px 15px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">Applica filtro</button>';
                  moduleHtml += '</div>';
                  moduleHtml += '<div id="modelli-posti-table-container">';
                  moduleHtml += '<div class="loading-spinner" style="margin: 20px auto;"></div>';
                  moduleHtml += '</div>';
                  moduleHtml += '</div>';
                  break;
                
              case 'modelli-valore':
                  moduleHtml += '<div class="modelli-nazioni-container">';
                  moduleHtml += '<div class="modelli-nazioni-header">';
                  moduleHtml += '<div class="modelli-nazioni-title">💰 Venduto Modelli per Nazione (Valore) - Periodo: <span id="periodo-modelli-valore">--</span></div>';
                  moduleHtml += '<div class="portafoglio-info">';
                  moduleHtml += '<span style="color: #7f8c8d; font-size: 12px;">Valori in Euro</span>';
                  moduleHtml += '<span id="fornitore-info-valore" class="fornitore-info" style="display:none;"></span>';
                  moduleHtml += '</div>';
                  moduleHtml += '</div>';
                  moduleHtml += '<div class="fornitore-selector" id="fornitore-selector-valore">';
                  moduleHtml += '<label style="font-weight: 600; color: #2c3e50;">Filtra per fornitore:</label>';
                  moduleHtml += '<select id="fornitore-select-valore">';
                  moduleHtml += '<option value="*ALL*">Caricamento fornitori...</option>';
                  moduleHtml += '</select>';
                  moduleHtml += '<button onclick="applyFornitoreFilter(\'valore\')" style="margin-left: 10px; padding: 5px 15px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">Applica filtro</button>';
                  moduleHtml += '</div>';
                  moduleHtml += '<div id="modelli-valore-table-container">';
                  moduleHtml += '<div class="loading-spinner" style="margin: 20px auto;"></div>';
                  moduleHtml += '</div>';
                  moduleHtml += '</div>';
                  break;
                
              case 'produzione-tappezzieri':
                  moduleHtml += '<div class="produzione-container">';
                  moduleHtml += '<div class="produzione-header">';
                  moduleHtml += '<div class="produzione-title">🛋️ Produzione tappezzieri</div>';
                  moduleHtml += '<div class="portafoglio-info">';
                  moduleHtml += '<span style="color: #7f8c8d; font-size: 12px;">Dati in tempo reale</span>';
                  moduleHtml += '</div>';
                  moduleHtml += '</div>';
                  moduleHtml += '<div id="produzione-table-container">';
                  moduleHtml += '<div class="loading-spinner" style="margin: 20px auto;"></div>';
                  moduleHtml += '</div>';
                  moduleHtml += '</div>';
                  break;
          }
        
          moduleHtml += '</div>';
        
          // Aggiungi al DOM
          $('#dynamic-modules').append(moduleHtml);
        
          // Anima e carica dati
          $('#module-' + moduleName).slideDown(400, function() {
              // Carica dati specifici del modulo
              switch(moduleName) {
                  case 'modelli-posti':
                      loadModelliPosti();
                      break;
                  case 'modelli-valore':
                      loadModelliValore();
                      break;
                  case 'produzione-tappezzieri':
                      loadProduzioneTappezzieri();
                      break;
              }
          });
        
          // Nascondi overlay
          setTimeout(function() {
              $('#moduleLoadingOverlay').fadeOut();
          }, 500);
      }
    
      // Funzione per caricare dati fatturato
      function loadFatturato() {
          $('#fatturato-container').html('<div class="kpi-card loading"><div class="loading-spinner"></div></div>');
        
          $.ajax({
              url: 'get_fatturato.php',
              type: 'GET',
              data: {
                  periodo: currentPeriodo
              },
              dataType: 'json',
              success: function(data) {
                  console.log('Dati fatturato ricevuti:', data);
                
                  if (data.error) {
                      showError('Fatturato: ' + data.error);
                      if (data.debug) {
                          console.log('Debug info:', data.debug);
                          showError(data.error + ' - Controlla la console per dettagli');
                      }
                      return;
                  }
                
                  renderFatturatoCards(data);
              },
              error: function(xhr, status, error) {
                  showError('Errore nel caricamento fatturato: ' + error);
              }
          });
      }
    
      // Funzione per caricare dati ordinato
      function loadOrdinato() {
          $('#ordinato-container').html('<div class="kpi-card loading"><div class="loading-spinner"></div></div>');
        
          $.ajax({
              url: 'get_ordinato.php',
              type: 'GET',
              data: {
                  periodo: currentPeriodo
              },
              dataType: 'json',
              success: function(data) {
                  console.log('Dati ordinato ricevuti:', data);
                
                  if (data.error) {
                      showError('Ordinato: ' + data.error);
                      if (data.debug) {
                          console.log('Debug info:', data.debug);
                          showError(data.error + ' - Controlla la console per dettagli');
                      }
                      return;
                  }
                
                  renderOrdinatoCards(data);
              },
              error: function(xhr, status, error) {
                  showError('Errore nel caricamento ordinato: ' + error);
              }
          });
      }
    
      // Funzione per renderizzare le cards del fatturato
      function renderFatturatoCards(data) {
          var html = '';
        
          // Aggiorna periodo display
          if (data.periodo_display) {
              $('#periodo-display').text(data.periodo_display);
          }
        
          // Top 6 nazioni - mostra le prime 4 come cards separate
          if (data.top_6 && data.top_6.length > 0) {
              for (var i = 0; i < Math.min(4, data.top_6.length); i++) {
                  var nazione = data.top_6[i];
                  var borderColor = i === 0 ? '#27ae60' : (i === 1 ? '#3498db' : (i === 2 ? '#f39c12' : '#9b59b6'));
                
                  html += '<div class="kpi-card" style="border-top-color: ' + borderColor + ';">';
                  html += '<div class="kpi-title">Fatturato ' + nazione.nome + ' ' + nazione.flag + '</div>';
                  html += '<div class="kpi-value">€ ' + nazione.fatturato_fmt + '</div>';
                  html += '<div class="kpi-subtitle">Periodo: ' + currentPeriodo + '</div>';
                
                  html += '<div class="kpi-trend">';
                  if (nazione.percentuale) {
                      html += '<span class="trend-badge trend-up" style="margin-right: 5px;">' + nazione.percentuale + '% del tot</span>';
                  }
                  if (nazione.variazione_anno_prec !== undefined && nazione.variazione_anno_prec !== null) {
                      var trendClass = nazione.trend === 'up' ? 'trend-up' : 'trend-down';
                      var trendSymbol = nazione.trend === 'up' ? '↑' : '↓';
                      html += '<span class="trend-badge ' + trendClass + '">' + trendSymbol + ' ' + Math.abs(nazione.variazione_anno_prec) + '% vs ' + (parseInt(currentPeriodo === 'Anno' ? new Date().getFullYear() : new Date().getFullYear()) - 1) + '</span>';
                  }
                  html += '</div>';
                
                  html += '</div>';
              }
          }
        
          // Card totale
          html += '<div class="kpi-card" style="border-top-color: #e74c3c;">';
          html += '<div class="kpi-title">Fatturato Totale</div>';
          html += '<div class="kpi-value">€ ' + data.totale_fmt + '</div>';
          html += '<div class="kpi-subtitle">Periodo: ' + currentPeriodo + '</div>';
        
          if (data.variazione_periodo !== undefined) {
              var trendClass = data.trend === 'up' ? 'trend-up' : 'trend-down';
              var trendSymbol = data.trend === 'up' ? '↑' : '↓';
              html += '<div class="kpi-trend">';
              html += '<span class="trend-badge ' + trendClass + '">' + trendSymbol + ' ' + Math.abs(data.variazione_periodo) + '%</span>';
              html += '<span style="font-size: 10px; color: #95a5a6; margin-left: 5px;">vs anno prec. (€ ' + (data.fatturato_anno_prec_fmt || '0') + ')</span>';
              html += '</div>';
          }
        
          html += '</div>';
        
          // Se ci sono più di 4 nazioni nel top 6, mostra le altre in una card riassuntiva
          if (data.top_6 && data.top_6.length > 4) {
              html += '<div class="kpi-card" style="border-top-color: #95a5a6;">';
              html += '<div class="kpi-title">Altre Nazioni Top</div>';
              html += '<div style="margin-top: 10px;">';
              for (var j = 4; j < data.top_6.length; j++) {
                  var altraNazione = data.top_6[j];
                  html += '<div style="font-size: 12px; margin: 5px 0;">';
                  html += altraNazione.flag + ' ' + altraNazione.nome + ': <strong>€ ' + altraNazione.fatturato_fmt + '</strong>';
                  if (altraNazione.variazione_anno_prec !== undefined && altraNazione.variazione_anno_prec !== null) {
                      var simbolo = altraNazione.trend === 'up' ? '↑' : '↓';
                      var colore = altraNazione.trend === 'up' ? '#27ae60' : '#e74c3c';
                      html += ' <span style="color: ' + colore + '; font-size: 11px;">(' + simbolo + Math.abs(altraNazione.variazione_anno_prec) + '%)</span>';
                  }
                  html += '</div>';
              }
              html += '</div>';
              html += '</div>';
          }
        
          $('#fatturato-container').html(html);
      }
    
      // Funzione per renderizzare le cards dell'ordinato
      function renderOrdinatoCards(data) {
          var html = '';
        
          // Top 6 nazioni - mostra le prime 4 come cards separate
          if (data.top_6 && data.top_6.length > 0) {
              for (var i = 0; i < Math.min(4, data.top_6.length); i++) {
                  var nazione = data.top_6[i];
                  var borderColor = i === 0 ? '#27ae60' : (i === 1 ? '#3498db' : (i === 2 ? '#f39c12' : '#9b59b6'));
                
                  html += '<div class="kpi-card" style="border-top-color: ' + borderColor + ';">';
                  html += '<div class="kpi-title">Ordinato ' + nazione.nome + ' ' + nazione.flag + '</div>';
                  html += '<div class="kpi-value">€ ' + nazione.ordinato_fmt + '</div>';
                  html += '<div class="kpi-subtitle">Periodo: ' + currentPeriodo + '</div>';
                
                  html += '<div class="kpi-trend">';
                  if (nazione.percentuale) {
                      html += '<span class="trend-badge trend-up" style="margin-right: 5px;">' + nazione.percentuale + '% del tot</span>';
                  }
                  if (nazione.variazione_anno_prec !== undefined && nazione.variazione_anno_prec !== null) {
                      var trendClass = nazione.trend === 'up' ? 'trend-up' : 'trend-down';
                      var trendSymbol = nazione.trend === 'up' ? '↑' : '↓';
                      html += '<span class="trend-badge ' + trendClass + '">' + trendSymbol + ' ' + Math.abs(nazione.variazione_anno_prec) + '% vs ' + (parseInt(currentPeriodo === 'Anno' ? new Date().getFullYear() : new Date().getFullYear()) - 1) + '</span>';
                  }
                  html += '</div>';
                
                  html += '</div>';
              }
          }
        
          // Card totale
          html += '<div class="kpi-card" style="border-top-color: #e67e22;">';
          html += '<div class="kpi-title">Ordinato Totale</div>';
          html += '<div class="kpi-value">€ ' + data.totale_fmt + '</div>';
          html += '<div class="kpi-subtitle">Periodo: ' + currentPeriodo + '</div>';
        
          if (data.variazione_periodo !== undefined) {
              var trendClass = data.trend === 'up' ? 'trend-up' : 'trend-down';
              var trendSymbol = data.trend === 'up' ? '↑' : '↓';
              html += '<div class="kpi-trend">';
              html += '<span class="trend-badge ' + trendClass + '">' + trendSymbol + ' ' + Math.abs(data.variazione_periodo) + '%</span>';
              html += '<span style="font-size: 10px; color: #95a5a6; margin-left: 5px;">vs anno prec. (€ ' + (data.ordinato_anno_prec_fmt || '0') + ')</span>';
              html += '</div>';
          }
        
          html += '</div>';
        
          // Se ci sono più di 4 nazioni nel top 6, mostra le altre in una card riassuntiva
          if (data.top_6 && data.top_6.length > 4) {
              html += '<div class="kpi-card" style="border-top-color: #95a5a6;">';
              html += '<div class="kpi-title">Altre Nazioni Top (Ordinato)</div>';
              html += '<div style="margin-top: 10px;">';
              for (var j = 4; j < data.top_6.length; j++) {
                  var altraNazione = data.top_6[j];
                  html += '<div style="font-size: 12px; margin: 5px 0;">';
                  html += altraNazione.flag + ' ' + altraNazione.nome + ': <strong>€ ' + altraNazione.ordinato_fmt + '</strong>';
                  if (altraNazione.variazione_anno_prec !== undefined && altraNazione.variazione_anno_prec !== null) {
                      var simbolo = altraNazione.trend === 'up' ? '↑' : '↓';
                      var colore = altraNazione.trend === 'up' ? '#27ae60' : '#e74c3c';
                      html += ' <span style="color: ' + colore + '; font-size: 11px;">(' + simbolo + Math.abs(altraNazione.variazione_anno_prec) + '%)</span>';
                  }
                  html += '</div>';
              }
              html += '</div>';
              html += '</div>';
          }
        
          $('#ordinato-container').html(html);
      }
    
      // Funzione per caricare portafoglio ordini
      function loadPortafoglioOrdini() {
          $('#portafoglio-table-container').html('<div class="loading-spinner" style="margin: 20px auto;"></div>');
        
          $.ajax({
              url: 'get_portafoglio_ordini.php',
              type: 'GET',
              dataType: 'json',
              success: function(data) {
                  console.log('Dati portafoglio ordini ricevuti:', data);
                
                  if (data.error) {
                      showError('Portafoglio ordini: ' + data.error);
                      if (data.debug) {
                          console.log('Debug info:', data.debug);
                      }
                      return;
                  }
                
                  renderPortafoglioTable(data);
              },
              error: function(xhr, status, error) {
                  showError('Errore nel caricamento portafoglio ordini: ' + error);
              }
          });
      }
    
      // Funzione per renderizzare tabella portafoglio ordini
      function renderPortafoglioTable(data) {
	      console.log('Data ricevuti in renderPortafoglioTable:', data);
	       console.log('Ora aggiornamento:', data.ora_aggiornamento);
          // Aggiorna data rilascio
          if (data.data_rilascio) {
              $('#data-rilascio').text(data.data_rilascio);
          }
		  
		  // Aggiorna ora aggiornamento
		  if (data.ora_aggiornamento) {
		      $('#ora-aggiornamento').text(data.ora_aggiornamento);
		      console.log('Ora aggiornamento impostata:', data.ora_aggiornamento);
		  } else {
		      console.log('Ora aggiornamento non presente nei dati');
		  }
        
          // Costruisci tabella
          var html = '<table class="portafoglio-table">';
        
          // Header
          html += '<thead>';
          html += '<tr>';
          html += '<th>Nazioni</th>';
          html += '<th>Lent. Sped</th>';
          html += '<th>Lent NO sp</th>';
          html += '<th>Lent mq BL</th>';
          html += '<th>Val.Mg.Mat</th>';
          html += '<th>Mag.Mat.BL</th>';
          html += '<th style="background: #e74c3c;">Totale</th>';
        
          // Header periodi dinamici
          if (data.periodi_header && data.periodi_header.length > 0) {
              for (var i = 0; i < data.periodi_header.length; i++) {
                  html += '<th class="periodo-header">' + data.periodi_header[i] + '</th>';
              }
          }
        
          html += '</tr>';
          html += '</thead>';
        
          // Body
          html += '<tbody>';
        
          // Righe nazioni
          if (data.nazioni && data.nazioni.length > 0) {
              for (var j = 0; j < data.nazioni.length; j++) {
                  var nazione = data.nazioni[j];
                  html += '<tr>';
                  html += '<td>' + nazione.flag + ' ' + nazione.nome + '</td>';
                
                  // Valori magazzino
                  html += '<td>' + formatNumeroTabella(nazione.lent_sped) + '</td>';
                  html += '<td>' + formatNumeroTabella(nazione.lent_no_sped) + '</td>';
                  html += '<td>' + formatNumeroTabella(nazione.lent_blocc) + '</td>';
                  html += '<td class="colonna-magazzino">' + formatNumeroTabella(nazione.val_mg_mat) + '</td>';
                  html += '<td class="colonna-magazzino">' + formatNumeroTabella(nazione.mag_mat_bl) + '</td>';
                
                  // Totale
                  html += '<td style="font-weight: bold; background: #fff5f5;">' + 
                          formatNumeroTabella(nazione.totale) + '</td>';
                
                  // Valori periodi
                  if (data.periodi_raw) {
                      for (var k = 0; k < data.periodi_raw.length; k++) {
                          var periodo_key = data.periodi_raw[k];
                          var valore = nazione.periodi[periodo_key] || 0;
                          var classe = valore > 0 ? 'valore-positivo' : '';
                          html += '<td class="' + classe + '">' + formatNumeroTabella(valore) + '</td>';
                      }
                  }
                
                  html += '</tr>';
              }
          }
        
          // Riga totali
          html += '<tr class="totale-row">';
          html += '<td>Totale</td>';
          html += '<td>' + formatNumeroTabella(data.totali.lent_sped) + '</td>';
          html += '<td>' + formatNumeroTabella(data.totali.lent_no_sped) + '</td>';
          html += '<td>' + formatNumeroTabella(data.totali.lent_blocc) + '</td>';
          html += '<td class="colonna-magazzino">' + formatNumeroTabella(data.totali.val_mg_mat) + '</td>';
          html += '<td class="colonna-magazzino">' + formatNumeroTabella(data.totali.mag_mat_bl) + '</td>';
        
          // Totale generale
          html += '<td style="font-weight: bold; background: #34495e; color: white;">' + 
                  formatNumeroTabella(data.totali.totale) + '</td>';
        
          // Totali periodi
          if (data.periodi_raw) {
              for (var m = 0; m < data.periodi_raw.length; m++) {
                  var periodo_tot_key = data.periodi_raw[m];
                  var totale_periodo = data.totali.periodi[periodo_tot_key] || 0;
                  html += '<td>' + formatNumeroTabella(totale_periodo) + '</td>';
              }
          }
        
          html += '</tr>';
        
          // Riga aggiuntiva per note/sequenza se necessario
          if (data.nazioni && data.nazioni.length > 0) {
              var colspan = 7 + (data.periodi_header ? data.periodi_header.length : 0);
              html += '<tr style="background: #ecf0f1; font-size: 9px;">';
              html += '<td colspan="' + colspan + '" style="text-align: right; padding: 5px; color: #7f8c8d;">';
              html += 'Seque..';
              html += '</td>';
              html += '</tr>';
          }
        
          html += '</tbody>';
          html += '</table>';
        
          $('#portafoglio-table-container').html(html);
      }
    
      // Funzione per caricare modelli per nazione A POSTI
      function loadModelliPosti() {
          // Carica fornitori se non già fatto
          if (!fornitori_loaded) {
              loadFornitori();
          }
        
          // Carica dati con filtro attuale
          var selectPosti = $('#fornitore-select-posti');
          var fornitore = selectPosti.length ? selectPosti.val() : '*ALL*';
          loadModelliPostiWithFilter(fornitore);
      }

      // Funzione per caricare modelli per nazione A VALORE
      function loadModelliValore() {
          // Carica fornitori se non già fatto
          if (!fornitori_loaded) {
              loadFornitori();
          }
        
          // Carica dati con filtro attuale
          var selectValore = $('#fornitore-select-valore');
          var fornitore = selectValore.length ? selectValore.val() : '*ALL*';
          loadModelliValoreWithFilter(fornitore);
      }
    
      // Funzione per renderizzare tabella modelli/posti
      function renderModelliPostiTable(data) {
          // Aggiorna periodo nel titolo
          if (data.periodo_display) {
              $('#periodo-modelli-posti').text(data.periodo_display);
          }
        
          // Costruisci tabella
          var html = '<table class="modelli-nazioni-table">';
        
          // Header
          html += '<thead>';
          html += '<tr>';
          html += '<th>Modello</th>';
          html += '<th>🇮🇹 Italia</th>';
          html += '<th>🇫🇷 Francia</th>';
          html += '<th>🇺🇸 USA</th>';
          html += '<th>🇧🇪 Belgio</th>';
          html += '<th>🇨🇳 Cina</th>';
          html += '<th>🇵🇹 Portogallo</th>';
          html += '<th>🌍 Altro</th>';
          html += '<th>Totale</th>';
          html += '</tr>';
          html += '</thead>';
        
          // Body
          html += '<tbody>';
        
          // Righe modelli
          if (data.modelli && data.modelli.length > 0) {
              for (var i = 0; i < data.modelli.length; i++) {
                  var modello = data.modelli[i];
                
                  // Controlla se è la riga "Altri modelli"
                  var rowClass = '';
                  if (modello.modello === 'Altri modelli') {
                      rowClass = ' class="altri-modelli-row"';
                  }
                
                  html += '<tr' + rowClass + '>';
                  html += '<td>' + modello.modello + '</td>';
                  html += '<td>' + (modello.italia_fmt || '') + '</td>';
                  html += '<td>' + (modello.francia_fmt || '') + '</td>';
                  html += '<td>' + (modello.usa_fmt || '') + '</td>';
                  html += '<td>' + (modello.belgio_fmt || '') + '</td>';
                  html += '<td>' + (modello.cina_fmt || '') + '</td>';
                  html += '<td>' + (modello.portogallo_fmt || '') + '</td>';
                  html += '<td>' + (modello.altro_fmt || '') + '</td>';
                  html += '<td>' + modello.totale_fmt + '</td>';
                  html += '</tr>';
              }
          } else {
              html += '<tr>';
              html += '<td colspan="9" style="text-align: center; padding: 20px;">Nessun dato disponibile per il periodo selezionato</td>';
              html += '</tr>';
          }
        
          // Riga totali
          if (data.totali_nazioni) {
              html += '<tr class="totale-row">';
              html += '<td>Totale</td>';
              html += '<td>' + data.totali_nazioni.italia_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.francia_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.usa_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.belgio_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.cina_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.portogallo_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.altro_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.totale_fmt + '</td>';
              html += '</tr>';
          }
        
          html += '</tbody>';
          html += '</table>';
        
          $('#modelli-posti-table-container').html(html);
      }
    
      // Funzione per renderizzare tabella modelli/valore  
      function renderModelliValoreTable(data) {
          // Aggiorna periodo nel titolo
          if (data.periodo_display) {
              $('#periodo-modelli-valore').text(data.periodo_display);
          }
        
          // Costruisci tabella
          var html = '<table class="modelli-nazioni-table">';
        
          // Header
          html += '<thead>';
          html += '<tr>';
          html += '<th>Modello</th>';
          html += '<th>🇮🇹 Italia</th>';
          html += '<th>🇫🇷 Francia</th>';
          html += '<th>🇺🇸 USA</th>';
          html += '<th>🇧🇪 Belgio</th>';
          html += '<th>🇨🇳 Cina</th>';
          html += '<th>🇵🇹 Portogallo</th>';
          html += '<th>🌍 Altro</th>';
          html += '<th>Totale</th>';
          html += '</tr>';
          html += '</thead>';
        
          // Body
          html += '<tbody>';
        
          // Righe modelli
          if (data.modelli && data.modelli.length > 0) {
              for (var i = 0; i < data.modelli.length; i++) {
                  var modello = data.modelli[i];
                
                  // Controlla se è la riga "Altri modelli"
                  var rowClass = '';
                  if (modello.modello === 'Altri modelli') {
                      rowClass = ' class="altri-modelli-row"';
                  }
                
                  html += '<tr' + rowClass + '>';
                  html += '<td>' + modello.modello + '</td>';
                  html += '<td>' + (modello.italia_fmt || '') + '</td>';
                  html += '<td>' + (modello.francia_fmt || '') + '</td>';
                  html += '<td>' + (modello.usa_fmt || '') + '</td>';
                  html += '<td>' + (modello.belgio_fmt || '') + '</td>';
                  html += '<td>' + (modello.cina_fmt || '') + '</td>';
                  html += '<td>' + (modello.portogallo_fmt || '') + '</td>';
                  html += '<td>' + (modello.altro_fmt || '') + '</td>';
                  html += '<td>' + modello.totale_fmt + '</td>';
                  html += '</tr>';
              }
          } else {
              html += '<tr>';
              html += '<td colspan="9" style="text-align: center; padding: 20px;">Nessun dato disponibile per il periodo selezionato</td>';
              html += '</tr>';
          }
        
          // Riga totali
          if (data.totali_nazioni) {
              html += '<tr class="totale-row">';
              html += '<td>Totale</td>';
              html += '<td>' + data.totali_nazioni.italia_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.francia_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.usa_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.belgio_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.cina_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.portogallo_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.altro_fmt + '</td>';
              html += '<td>' + data.totali_nazioni.totale_fmt + '</td>';
              html += '</tr>';
          }
        
          html += '</tbody>';
          html += '</table>';
        
          $('#modelli-valore-table-container').html(html);
      }
    
      // Funzione per caricare produzione tappezzieri (solo se il modulo è attivo)
      function loadProduzioneTappezzieri() {
          $('#produzione-table-container').html('<div class="loading-spinner" style="margin: 20px auto;"></div>');
        
          $.ajax({
              url: 'get_produzione_tappezzieri.php',
              type: 'GET',
              dataType: 'json',
              timeout: 60000, // 1 minuto di timeout
              success: function(data) {
                  console.log('Dati produzione tappezzieri ricevuti:', data);
                
                  if (data.error) {
                      showError('Produzione tappezzieri: ' + data.error);
                      if (data.debug) {
                          console.log('Debug info:', data.debug);
                      }
                      $('#produzione-table-container').html('<div class="error-message">Errore nel caricamento dati</div>');
                      return;
                  }
                
                  renderProduzioneTappezzieri(data);
              },
              error: function(xhr, status, error) {
                  showError('Errore nel caricamento produzione tappezzieri: ' + error);
                  $('#produzione-table-container').html('<div class="error-message">Errore nel caricamento dati</div>');
              }
          });
      }
    
      // Funzione per renderizzare produzione tappezzieri
      function renderProduzioneTappezzieri(data) {
          // Costruisci tabella
          var html = '<table class="produzione-table">';
        
          // Header
          html += '<thead>';
          html += '<tr>';
          html += '<th style="width: 10%;">Loc</th>';
          html += '<th style="width: 70%;">Fornitore</th>';
          html += '<th style="width: 20%;">Posti</th>';
          html += '</tr>';
          html += '</thead>';
        
          // Body
          html += '<tbody>';
        
          // Per ogni localizzazione
          if (data.localizzazioni && data.localizzazioni.length > 0) {
              for (var i = 0; i < data.localizzazioni.length; i++) {
                  var loc = data.localizzazioni[i];
                
                  // Gestisci localizzazione NULL o vuota
                  var locDisplay = loc.localizzazione;
                  if (!locDisplay || locDisplay === 'NULL' || locDisplay === 'null') {
                      locDisplay = 'N';
                  }
                
                  // Righe fornitori per questa localizzazione
                  if (loc.fornitori && loc.fornitori.length > 0) {
                      for (var j = 0; j < loc.fornitori.length; j++) {
                          var fornitore = loc.fornitori[j];
                        
                          // Salta fornitori con codice NULL o vuoto
                          if (!fornitore.codice || fornitore.codice === 'NULL' || fornitore.codice === 'null') {
                              continue;
                          }
                        
                          html += '<tr>';
                          html += '<td>' + locDisplay + '</td>';
                          html += '<td>' + fornitore.codice + ' - ' + (fornitore.ragione_sociale || 'N/D') + '</td>';
                          html += '<td>' + (fornitore.posti_fmt || '0') + '</td>';
                          html += '</tr>';
                      }
                  }
                
                  // Totale localizzazione
                  if (loc.totale && loc.totale > 0) {
                      html += '<tr class="loc-totale">';
                      html += '<td>' + locDisplay + '</td>';
                      html += '<td>Totale ' + (loc.localizzazione_nome || locDisplay) + '</td>';
                      html += '<td>' + (loc.totale_fmt || '0') + '</td>';
                      html += '</tr>';
                  }
                
                  // Riga vuota tra localizzazioni (tranne l'ultima)
                  if (i < data.localizzazioni.length - 1) {
                      html += '<tr style="height: 10px;"><td colspan="3" style="border: none;"></td></tr>';
                  }
              }
          } else {
              html += '<tr>';
              html += '<td colspan="3" style="text-align: center; padding: 20px;">Nessun dato disponibile</td>';
              html += '</tr>';
          }
        
          // Totale generale
          if (data.totale_generale && data.totale_generale > 0) {
              html += '<tr class="totale-generale">';
              html += '<td colspan="2">Totale generale</td>';
              html += '<td>' + (data.totale_generale_fmt || '0') + '</td>';
              html += '</tr>';
          }
        
          html += '</tbody>';
          html += '</table>';
        
          $('#produzione-table-container').html(html);
      }
    
      // Funzione helper per formattare numeri nella tabella
      function formatNumeroTabella(valore) {
          if (valore === 0 || valore === null || valore === undefined) {
              return '';
          }
          // Formatta con separatore migliaia e 2 decimali se necessario
          var num = parseFloat(valore);
          if (num % 1 !== 0) {
              // Ha decimali
              return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
          } else {
              // Numero intero
              return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
          }
      }
    
      // Funzione per mostrare errori
      function showError(message) {
          $('#message-container').html('<div class="error-message">' + message + '</div>');
          setTimeout(function() {
              $('#message-container').empty();
          }, 5000);
      }
    
      // Funzione per mostrare successo
      function showSuccess(message) {
          $('#message-container').html('<div class="success-message">' + message + '</div>');
          setTimeout(function() {
              $('#message-container').empty();
          }, 3000);
      }
    
      // Event handlers
      $(document).ready(function() {
          // Test: verifica che le funzioni siano caricate
          console.log('Dashboard caricata. Funzioni disponibili:');
          console.log('- applyFornitoreFilter:', typeof applyFornitoreFilter);
          console.log('- loadFornitori:', typeof loadFornitori);
        
          // Carica dati base sempre visibili
          loadFatturato();
          loadOrdinato();
          loadPortafoglioOrdini();
        
          // Gestione cambio periodo
          $('#periodSelector').change(function() {
              currentPeriodo = $(this).val();
              loadFatturato();
              loadOrdinato();
            
              // Ricarica solo i moduli attivi
              if (activeModules['modelli-posti']) {
                  loadModelliPosti();
              }
              if (activeModules['modelli-valore']) {
                  loadModelliValore();
              }
          });
        
          // Gestione pulsante refresh
          $('#refreshButton').click(function() {
              refreshAllData();
          });
        
          // Refresh automatico ogni 5 minuti SOLO per dati base
         // setInterval(function() {
         //     loadFatturato();
          //    loadOrdinato();
          //    loadPortafoglioOrdini();
            
              // Refresh moduli attivi solo se visibili
          //    if (activeModules['modelli-posti']) {
          //        loadModelliPosti();
          //    }
          //    if (activeModules['modelli-valore']) {
          //        loadModelliValore();
           //   }
           //   if (activeModules['produzione-tappezzieri']) {
           //       loadProduzioneTappezzieri();
           //   }
         // }, 300000);
      });
      </script>
</body>
</html>