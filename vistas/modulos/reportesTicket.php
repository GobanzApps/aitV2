
<?php

/* =============================================
   AUTORIZACIÓN
============================================= */
$auth = ControladorTicket::authTicket();

$puedeVerReporte = $auth && (
    ControladorTicket::esAdminTicket($auth)
    || ControladorTicket::esCoordinadorTicket($auth)
    || in_array(11, $auth['coordsIds'] ?? [], true)
    || in_array(9, $auth['coordsIds'] ?? [], true)
    || (($auth['userData']['user'] ?? '') === 'agamardo')
);

if (!$puedeVerReporte) {
    echo '<script>window.location.replace("index.php?ruta=inicio");</script>';
    return;
}

/* =============================================
   CATÁLOGOS
============================================= */
$entidades   = ControladorEntidad::ctrMostrarEntidad(null, null) ?: [];
$medios      = ControladorMedio::ctrMostrarMedio(null, null) ?: [];
$prioridades = ControladorPrioridad::ctrMostrarPrioridad(null, null) ?: [];
$statuses    = ControladorStatus::ctrMostrarStatus(null, null) ?: [];

$mapaEntidad = [];
foreach ($entidades as $e) {
    if ((int)$e['estado'] === 1) {
        $mapaEntidad[(int)$e['id']] = $e;
    }
}

$mapaMedio = [];
foreach ($medios as $m) {
    if ((int)$m['estado'] === 1) {
        $mapaMedio[(int)$m['id']] = $m;
    }
}

$mapaPrioridad = [];
foreach ($prioridades as $p) {
    if ((int)$p['estado'] === 1) {
        $mapaPrioridad[(int)$p['id']] = $p;
    }
}

$mapaStatus = [];
foreach ($statuses as $s) {
    if ((int)$s['estado'] === 1) {
        $mapaStatus[(int)$s['id']] = $s;
    }
}

/* =============================================
   TICKETS E HISTORIAL
============================================= */
$tickets = ControladorTicket::ctrMostrarTicket(null, null) ?: [];
$historial = ControladorTstatus::ctrMostrarTstatus(null, null) ?: [];

$ultimoStatusPorTicket = [];

foreach ($historial as $h) {
    $idTicket = (int)$h['id_ticket'];

    if (!isset($ultimoStatusPorTicket[$idTicket])) {
        $ultimoStatusPorTicket[$idTicket] = (int)$h['id_status'];
    }
}

/* =============================================
   FUNCIONES AUXILIARES
============================================= */
function reporteTicketIds($json) {
    $ids = json_decode($json ?? '[]', true);

    if (!is_array($ids)) {
        return [];
    }

    return array_map('intval', $ids);
}

function reporteTicketNombres($ids, $mapa, $campo) {
    $nombres = [];

    foreach ($ids as $id) {
        if (isset($mapa[$id])) {
            $nombres[] = $mapa[$id][$campo];
        }
    }

    return $nombres;
}

function reporteTicketEscapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

?>

<style>
/* =============================================
   REPORTE DE TICKETS - DISEÑO
============================================= */

.reporte-tickets {
    --rt-bg: #f4f6fb;
    --rt-card: #ffffff;
    --rt-card-soft: #f8fafc;
    --rt-text: #172033;
    --rt-muted: #718096;
    --rt-border: #e5eaf1;
    --rt-primary: #536dfe;
    --rt-primary-soft: rgba(83, 109, 254, .10);
    --rt-shadow: 0 5px 22px rgba(30, 41, 59, .045);
    --rt-input: #ffffff;
    color: var(--rt-text);
    padding-bottom: 25px;
}

body.dark-mode .reporte-tickets,
html[data-bs-theme="dark"] .reporte-tickets,
body[data-bs-theme="dark"] .reporte-tickets {
    --rt-bg: #111827;
    --rt-card: #1f2937;
    --rt-card-soft: #263244;
    --rt-text: #e5e7eb;
    --rt-muted: #9ca3af;
    --rt-border: #374151;
    --rt-primary: #8b9cff;
    --rt-primary-soft: rgba(139, 156, 255, .14);
    --rt-shadow: 0 5px 22px rgba(0, 0, 0, .12);
    --rt-input: #263244;
}

.reporte-tickets .rt-header {
    background: linear-gradient(120deg, #3449bd, #657cff);
    border-radius: 17px;
    padding: 27px 30px;
    color: #fff;
    margin-bottom: 23px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(83, 109, 254, .19);
}

.reporte-tickets .rt-header::after {
    content: "";
    position: absolute;
    width: 220px;
    height: 220px;
    right: -55px;
    top: -115px;
    border: 35px solid rgba(255,255,255,.09);
    border-radius: 50%;
}

.reporte-tickets .rt-header-content {
    position: relative;
    z-index: 1;
}

.reporte-tickets .rt-header h2 {
    font-size: 24px;
    font-weight: 750;
    margin: 0 0 7px;
    letter-spacing: -.5px;
}

.reporte-tickets .rt-header p {
    margin: 0;
    font-size: 13px;
    opacity: .85;
}

.reporte-tickets .rt-header-icon {
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,.17);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    margin-right: 15px;
}

.reporte-tickets .rt-card {
    background: var(--rt-card);
    border: 1px solid var(--rt-border);
    border-radius: 15px;
    box-shadow: var(--rt-shadow);
    margin-bottom: 20px;
    overflow: hidden;
}

.reporte-tickets .rt-card-header {
    padding: 17px 20px;
    border-bottom: 1px solid var(--rt-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.reporte-tickets .rt-card-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--rt-text);
    margin: 0;
}

.reporte-tickets .rt-card-subtitle {
    color: var(--rt-muted);
    font-size: 12px;
    margin-top: 4px;
}

.reporte-tickets .rt-card-body {
    padding: 20px;
}

.reporte-tickets .rt-section-icon {
    width: 33px;
    height: 33px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--rt-primary-soft);
    color: var(--rt-primary);
    margin-right: 10px;
}

/* ESTADÍSTICAS */

.reporte-tickets .rt-stat {
    background: var(--rt-card);
    border: 1px solid var(--rt-border);
    border-radius: 15px;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    height: 100%;
    box-shadow: var(--rt-shadow);
}

.reporte-tickets .rt-stat-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
}

.reporte-tickets .rt-stat-icon.blue {
    background: rgba(83,109,254,.12);
    color: #536dfe;
}

.reporte-tickets .rt-stat-icon.green {
    background: rgba(16,185,129,.12);
    color: #10b981;
}

.reporte-tickets .rt-stat-label {
    color: var(--rt-muted);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
}

.reporte-tickets .rt-stat-value {
    color: var(--rt-text);
    font-size: 25px;
    line-height: 1.1;
    font-weight: 750;
    letter-spacing: -.5px;
}

/* FILTROS */

.reporte-tickets .rt-filter-label {
    display: block;
    color: var(--rt-muted);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 7px;
}

.reporte-tickets .form-control,
.reporte-tickets .form-select {
    background-color: var(--rt-input);
    color: var(--rt-text);
    border: 1px solid var(--rt-border);
    border-radius: 9px;
    min-height: 41px;
    font-size: 13px;
    box-shadow: none;
}

.reporte-tickets .form-control:focus,
.reporte-tickets .form-select:focus {
    border-color: var(--rt-primary);
    box-shadow: 0 0 0 3px var(--rt-primary-soft);
}

.reporte-tickets .form-control::-webkit-calendar-picker-indicator {
    cursor: pointer;
}

.reporte-tickets .rt-btn {
    border-radius: 9px;
    min-height: 40px;
    padding: 9px 14px;
    font-size: 12px;
    font-weight: 650;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: .2s ease;
}

.reporte-tickets .rt-btn:hover {
    transform: translateY(-1px);
}

.reporte-tickets .rt-btn-primary {
    background: #536dfe;
    color: white;
    border: 1px solid #536dfe;
}

.reporte-tickets .rt-btn-primary:hover {
    background: #4058e8;
    color: white;
}

.reporte-tickets .rt-btn-outline {
    color: var(--rt-muted);
    border: 1px solid var(--rt-border);
    background: var(--rt-card);
}

.reporte-tickets .rt-btn-outline:hover {
    color: var(--rt-text);
    background: var(--rt-card-soft);
}

.reporte-tickets .rt-btn-excel {
    color: #0c9b6b;
    border: 1px solid rgba(16,185,129,.35);
    background: rgba(16,185,129,.08);
}

.reporte-tickets .rt-btn-excel:hover {
    color: #fff;
    background: #0c9b6b;
}

.reporte-tickets .rt-active-filters {
    color: var(--rt-muted);
    font-size: 12px;
    margin-top: 14px;
}

.reporte-tickets .rt-active-filters span {
    display: inline-flex;
    align-items: center;
    background: var(--rt-primary-soft);
    color: var(--rt-primary);
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: 650;
}

/* GRÁFICOS */

.reporte-tickets .rt-chart-wrapper {
    height: 260px;
    position: relative;
}

.reporte-tickets .rt-chart-empty {
    color: var(--rt-muted);
    font-size: 12px;
    text-align: center;
}

/* TABLA */

.reporte-tickets .rt-table-container {
    padding: 5px 20px 20px;
}

.reporte-tickets #solicitudesTable {
    width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0;
    color: var(--rt-text);
    font-size: 12px;
}

.reporte-tickets #solicitudesTable thead th {
    background: var(--rt-card-soft);
    color: var(--rt-muted);
    font-size: 10px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-top: 1px solid var(--rt-border);
    border-bottom: 1px solid var(--rt-border);
    padding: 13px 12px;
    white-space: nowrap;
}

.reporte-tickets #solicitudesTable tbody td {
    padding: 13px 12px;
    vertical-align: middle;
    border-bottom: 1px solid var(--rt-border);
    color: var(--rt-text);
}

.reporte-tickets #solicitudesTable tbody tr:hover {
    background: var(--rt-primary-soft);
}

.reporte-tickets #solicitudesTable tbody tr:last-child td {
    border-bottom: none;
}

.reporte-tickets .rt-ticket-link {
    color: var(--rt-primary);
    font-weight: 750;
    text-decoration: none;
}

.reporte-tickets .rt-ticket-link:hover {
    text-decoration: underline;
}

.reporte-tickets .rt-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    margin: 2px;
    white-space: nowrap;
}

.reporte-tickets .rt-empty {
    color: var(--rt-muted);
}

/* DATATABLES */

.reporte-tickets .dataTables_wrapper {
    color: var(--rt-text);
    font-size: 12px;
}

.reporte-tickets .dataTables_wrapper .dataTables_length,
.reporte-tickets .dataTables_wrapper .dataTables_filter,
.reporte-tickets .dataTables_wrapper .dataTables_info {
    color: var(--rt-muted);
    padding: 12px 0;
}

.reporte-tickets .dataTables_wrapper .dataTables_filter input,
.reporte-tickets .dataTables_wrapper .dataTables_length select {
    background: var(--rt-input);
    color: var(--rt-text);
    border: 1px solid var(--rt-border);
    border-radius: 8px;
    padding: 6px 9px;
    outline: none;
}

.reporte-tickets .dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 7px !important;
    color: var(--rt-text) !important;
    border: 1px solid transparent !important;
}

.reporte-tickets .dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--rt-primary) !important;
    color: white !important;
    border-color: var(--rt-primary) !important;
}

.reporte-tickets .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--rt-primary-soft) !important;
    color: var(--rt-text) !important;
    border-color: var(--rt-border) !important;
}

.reporte-tickets .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
    color: var(--rt-muted) !important;
}

/* RESPONSIVE */

@media (max-width: 767px) {
    .reporte-tickets .rt-header {
        padding: 22px 18px;
    }

    .reporte-tickets .rt-header h2 {
        font-size: 20px;
    }

    .reporte-tickets .rt-card-body {
        padding: 15px;
    }

    .reporte-tickets .rt-chart-wrapper {
        height: 230px;
    }

    .reporte-tickets .rt-stat-value {
        font-size: 22px;
    }
}
</style>

<div class="content-wrapper reporte-tickets">

    <section class="content pt-3">
        <div class="container-fluid">

            <!-- ENCABEZADO -->
            <div class="rt-header">
                <div class="rt-header-content d-flex align-items-center">
                    <div class="rt-header-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>

                    <div>
                        <h2>Reporte de Tickets</h2>
                        <p>Consulta, analiza y exporta la información de los tickets registrados en el sistema.</p>
                    </div>
                </div>
            </div>

            <!-- ESTADÍSTICAS -->
            <div class="row mb-2">

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="rt-stat">
                        <div class="rt-stat-icon blue">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div>
                            <div class="rt-stat-label">Total de registros</div>
                            <div class="rt-stat-value" id="totalRegistros">0</div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="rt-stat">
                        <div class="rt-stat-icon green">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div>
                            <div class="rt-stat-label">Registros visibles</div>
                            <div class="rt-stat-value" id="visibleRegistros">0</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- FILTROS -->
            <div class="rt-card">

                <div class="rt-card-header">
                    <div>
                        <h3 class="rt-card-title">
                            <i class="fas fa-sliders-h rt-section-icon"></i>
                            Filtrar información
                        </h3>
                        <div class="rt-card-subtitle">
                            Combina los filtros para obtener resultados específicos.
                        </div>
                    </div>

                    <span class="badge rounded-pill bg-primary">
                        <i class="fas fa-filter me-1"></i> Filtros
                    </span>
                </div>

                <div class="rt-card-body">

                    <div class="row g-3">

                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <label class="rt-filter-label">Ente</label>
                            <select id="filtroEntidad" class="form-select">
                                <option value="all">Todos los entes</option>
                                <?php foreach ($mapaEntidad as $e): ?>
                                    <option value="<?= (int)$e['id'] ?>">
                                        <?= reporteTicketEscapar($e['entidad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <label class="rt-filter-label">Medio</label>
                            <select id="filtroMedio" class="form-select">
                                <option value="all">Todos los medios</option>
                                <?php foreach ($mapaMedio as $m): ?>
                                    <option value="<?= (int)$m['id'] ?>">
                                        <?= reporteTicketEscapar($m['medio']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <label class="rt-filter-label">Prioridad</label>
                            <select id="filtroPrioridad" class="form-select">
                                <option value="all">Todas</option>
                                <?php foreach ($mapaPrioridad as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>">
                                        <?= reporteTicketEscapar($p['prioridad']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <label class="rt-filter-label">Estado</label>
                            <select id="filtroStatus" class="form-select">
                                <option value="all">Todos los estados</option>
                                <?php foreach ($mapaStatus as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>">
                                        <?= reporteTicketEscapar($s['status']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <label class="rt-filter-label">Desde</label>
                            <input type="date" id="fechaDesde" class="form-control">
                        </div>

                        <div class="col-xl-2 col-md-4 col-sm-6">
                            <label class="rt-filter-label">Hasta</label>
                            <input type="date" id="fechaHasta" class="form-control">
                        </div>

                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-4">

                        <button type="button" id="limpiarFiltros" class="btn rt-btn rt-btn-outline">
                            <i class="fas fa-eraser"></i>
                            Limpiar filtros
                        </button>

                        <div class="ms-auto d-flex flex-wrap gap-2">

                            <button type="button" id="exportExcelBtn" class="btn rt-btn rt-btn-excel">
                                <i class="fas fa-file-excel"></i>
                                Exportar filtrados
                            </button>

                            <button type="button" id="exportAllExcelBtn" class="btn rt-btn rt-btn-primary">
                                <i class="fas fa-download"></i>
                                Exportar todos
                            </button>

                        </div>

                    </div>

                    <div class="rt-active-filters">
                        <i class="fas fa-info-circle me-1"></i>
                        <span id="activeFiltersBadge">Sin filtros activos</span>
                    </div>

                </div>
            </div>

            <!-- GRÁFICOS -->
            <div class="d-flex align-items-center justify-content-between mb-3 mt-4">
                <div>
                    <h3 class="rt-card-title">
                        <i class="fas fa-chart-pie me-2" style="color:var(--rt-primary)"></i>
                        Análisis de tickets
                    </h3>
                    <div class="rt-card-subtitle">
                        Distribución de los registros según los filtros aplicados.
                    </div>
                </div>
            </div>

            <div class="row">

                <div class="col-xl-6 col-lg-6 mb-3">
                    <div class="rt-card h-100">
                        <div class="rt-card-header">
                            <div>
                                <h3 class="rt-card-title">Tickets por ente</h3>
                                <div class="rt-card-subtitle">Distribución según el ente solicitante</div>
                            </div>
                            <span class="rt-section-icon">
                                <i class="fas fa-building"></i>
                            </span>
                        </div>
                        <div class="rt-card-body">
                            <div class="rt-chart-wrapper">
                                <canvas id="enteChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6 mb-3">
                    <div class="rt-card h-100">
                        <div class="rt-card-header">
                            <div>
                                <h3 class="rt-card-title">Tickets por medio</h3>
                                <div class="rt-card-subtitle">Canales de recepción de solicitudes</div>
                            </div>
                            <span class="rt-section-icon">
                                <i class="fas fa-comments"></i>
                            </span>
                        </div>
                        <div class="rt-card-body">
                            <div class="rt-chart-wrapper">
                                <canvas id="medioChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6 mb-3">
                    <div class="rt-card h-100">
                        <div class="rt-card-header">
                            <div>
                                <h3 class="rt-card-title">Tickets por prioridad</h3>
                                <div class="rt-card-subtitle">Clasificación según nivel de prioridad</div>
                            </div>
                            <span class="rt-section-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </span>
                        </div>
                        <div class="rt-card-body">
                            <div class="rt-chart-wrapper">
                                <canvas id="prioridadChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6 mb-3">
                    <div class="rt-card h-100">
                        <div class="rt-card-header">
                            <div>
                                <h3 class="rt-card-title">Tickets por estado</h3>
                                <div class="rt-card-subtitle">Estado actual de las solicitudes</div>
                            </div>
                            <span class="rt-section-icon">
                                <i class="fas fa-tasks"></i>
                            </span>
                        </div>
                        <div class="rt-card-body">
                            <div class="rt-chart-wrapper">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- TABLA -->
            <div class="rt-card mt-3">

                <div class="rt-card-header">
                    <div>
                        <h3 class="rt-card-title">
                            <i class="fas fa-table rt-section-icon"></i>
                            Listado de tickets
                        </h3>
                        <div class="rt-card-subtitle">
                            Consulta detallada de las solicitudes registradas.
                        </div>
                    </div>

                    <span class="badge rounded-pill bg-light text-secondary border">
                        <i class="fas fa-database me-1"></i>
                        Registros
                    </span>
                </div>

                <div class="rt-table-container table-responsive">

                    <table id="solicitudesTable" class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Ente</th>
                                <th>Solicitante</th>
                                <th>Medio</th>
                                <th>Descripción</th>
                                <th>Prioridad</th>
                                <th>Estado</th>
                                <th>Creado por</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($tickets as $ticket): ?>

                                <?php
                                if ((int)($ticket['estado'] ?? 0) === 0) {
                                    continue;
                                }

                                $id = (int)$ticket['id'];

                                $idsEntidades = reporteTicketIds($ticket['id_entidad'] ?? '');
                                $idsMedios = reporteTicketIds($ticket['id_medio'] ?? '');
                                $idsPrioridades = reporteTicketIds($ticket['id_prioridad'] ?? '');

                                $nombresEntidades = reporteTicketNombres($idsEntidades, $mapaEntidad, 'entidad');
                                $nombresMedios = reporteTicketNombres($idsMedios, $mapaMedio, 'medio');
                                $nombresPrioridades = reporteTicketNombres($idsPrioridades, $mapaPrioridad, 'prioridad');

                                $idStatus = $ultimoStatusPorTicket[$id] ?? 0;
                                $statusActual = $mapaStatus[$idStatus] ?? null;

                                $usuario = ControladorUser::ctrMostrarUser(
                                    'id',
                                    (int)($ticket['id_user'] ?? 0)
                                );

                                $nombreUsuario = $usuario
                                    ? ($usuario['name'] ?: $usuario['user'])
                                    : 'No especificado';

                                $fecha = $ticket['fecha'] ?? '';

                                $entidadesTexto = implode(', ', $nombresEntidades);
                                $mediosTexto = implode(', ', $nombresMedios);

                                $urlTicket = 'index.php?ruta=ticket&idTicket=' . $id;
                                ?>

                                <tr
                                    data-entidades="<?= reporteTicketEscapar(json_encode($idsEntidades)) ?>"
                                    data-medios="<?= reporteTicketEscapar(json_encode($idsMedios)) ?>"
                                    data-prioridades="<?= reporteTicketEscapar(json_encode($idsPrioridades)) ?>"
                                    data-status="<?= $idStatus ?>"
                                    data-fecha="<?= reporteTicketEscapar($fecha) ?>"
                                >

                                    <td>
                                        <a class="rt-ticket-link" href="<?= reporteTicketEscapar($urlTicket) ?>">
                                            #<?= $id ?>
                                        </a>
                                    </td>

                                    <td><?= reporteTicketEscapar($entidadesTexto ?: '—') ?></td>

                                    <td><?= reporteTicketEscapar($ticket['solicitante'] ?? '') ?></td>

                                    <td><?= reporteTicketEscapar($mediosTexto ?: '—') ?></td>

                                    <td><?= reporteTicketEscapar($ticket['descripcion'] ?? '') ?></td>

                                    <td>
                                        <?php foreach ($idsPrioridades as $idP): ?>
                                            <?php if (isset($mapaPrioridad[$idP])): ?>
                                                <?php $p = $mapaPrioridad[$idP]; ?>
                                                <span class="rt-badge" style="background:<?= reporteTicketEscapar($p['color'] ?: '#536dfe') ?>20;color:<?= reporteTicketEscapar($p['color'] ?: '#536dfe') ?>">
                                                    <?= reporteTicketEscapar($p['prioridad']) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </td>

                                    <td>
                                        <?php if ($statusActual): ?>
                                            <span class="rt-badge" style="background:<?= reporteTicketEscapar($statusActual['color'] ?: '#6c757d') ?>20;color:<?= reporteTicketEscapar($statusActual['color'] ?: '#6c757d') ?>">
                                                <?= reporteTicketEscapar($statusActual['status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="rt-badge" style="background:rgba(108,117,125,.12);color:#9299a2">
                                                Sin estado
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= reporteTicketEscapar($nombreUsuario) ?></td>

                                    <td><?= reporteTicketEscapar($fecha) ?></td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>
            </div>

        </div>
    </section>
</div>

<!-- LIBRERÍAS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(document).ready(function () {

    const tabla = $('#solicitudesTable').DataTable({
        retrieve: true,
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'Todos']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
        }
    });

    let graficos = {};

    const colores = [
        '#536dfe', '#10b981', '#f59e0b', '#ec4899',
        '#06b6d4', '#8b5cf6', '#f97316', '#14b8a6',
        '#e11d48', '#64748b', '#84cc16', '#a855f7'
    ];

    /* =============================================
       TEMA CLARO / OSCURO
    ============================================= */
    function esModoOscuro() {
        return document.body.classList.contains('dark-mode')
            || document.documentElement.getAttribute('data-bs-theme') === 'dark'
            || document.body.getAttribute('data-bs-theme') === 'dark';
    }

    function coloresTema() {
        return esModoOscuro()
            ? {
                texto: '#cbd5e1',
                borde: 'rgba(148,163,184,.18)',
                fondo: '#1f2937'
            }
            : {
                texto: '#64748b',
                borde: 'rgba(148,163,184,.22)',
                fondo: '#ffffff'
            };
    }

    /* =============================================
       FILTROS
    ============================================= */
    function obtenerFiltros() {
        return {
            entidad: $('#filtroEntidad').val(),
            medio: $('#filtroMedio').val(),
            prioridad: $('#filtroPrioridad').val(),
            status: $('#filtroStatus').val(),
            desde: $('#fechaDesde').val(),
            hasta: $('#fechaHasta').val()
        };
    }

    function filaCoincide(tr, filtros) {

        const entidades = JSON.parse(tr.dataset.entidades || '[]');
        const medios = JSON.parse(tr.dataset.medios || '[]');
        const prioridades = JSON.parse(tr.dataset.prioridades || '[]');

        if (filtros.entidad !== 'all' && !entidades.includes(Number(filtros.entidad))) {
            return false;
        }

        if (filtros.medio !== 'all' && !medios.includes(Number(filtros.medio))) {
            return false;
        }

        if (filtros.prioridad !== 'all' && !prioridades.includes(Number(filtros.prioridad))) {
            return false;
        }

        if (filtros.status !== 'all' && Number(tr.dataset.status) !== Number(filtros.status)) {
            return false;
        }

        const fecha = (tr.dataset.fecha || '').substring(0, 10);

        if (filtros.desde && fecha && fecha < filtros.desde) {
            return false;
        }

        if (filtros.hasta && fecha && fecha > filtros.hasta) {
            return false;
        }

        return true;
    }

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {

        if (settings.nTable.id !== 'solicitudesTable') {
            return true;
        }

        const tr = tabla.row(dataIndex).node();

        return filaCoincide(tr, obtenerFiltros());
    });

    function obtenerFilasVisibles() {
        return tabla.rows({ search: 'applied' }).nodes().toArray();
    }

    /* =============================================
       CONTADORES
    ============================================= */
    function actualizarContadores() {
        $('#totalRegistros').text(tabla.rows().count());
        $('#visibleRegistros').text(tabla.rows({ search: 'applied' }).count());
    }

    function actualizarIndicadorFiltros() {

        const f = obtenerFiltros();
        const activos = [];

        if (f.entidad !== 'all') activos.push('Ente');
        if (f.medio !== 'all') activos.push('Medio');
        if (f.prioridad !== 'all') activos.push('Prioridad');
        if (f.status !== 'all') activos.push('Estado');
        if (f.desde || f.hasta) activos.push('Fecha');

        const badge = $('#activeFiltersBadge');

        if (activos.length) {
            badge.text('Activos: ' + activos.join(' · '));
        } else {
            badge.text('Sin filtros activos');
        }
    }

    /* =============================================
       GRÁFICOS
    ============================================= */
    function contarColumna(filas, columna) {

        const conteo = {};

        filas.forEach(tr => {

            const valor = $(tr).find('td').eq(columna).text().trim() || 'Sin datos';

            valor.split(',').forEach(nombre => {

                nombre = nombre.trim();

                if (nombre) {
                    conteo[nombre] = (conteo[nombre] || 0) + 1;
                }
            });
        });

        return conteo;
    }

    function actualizarGrafico(id, datos) {

        let labels = Object.keys(datos);
        let valores = Object.values(datos);

        if (!labels.length) {
            labels = ['Sin datos'];
            valores = [0];
        }

        const tema = coloresTema();

        if (graficos[id]) {

            graficos[id].data.labels = labels;
            graficos[id].data.datasets[0].data = valores;
            graficos[id].data.datasets[0].backgroundColor =
                labels.map((_, i) => valores[i] === 0 && labels[i] === 'Sin datos'
                    ? '#94a3b8'
                    : colores[i % colores.length]);

            graficos[id].options.plugins.legend.labels.color = tema.texto;
            graficos[id].options.plugins.tooltip.backgroundColor = tema.fondo;
            graficos[id].options.plugins.tooltip.titleColor = tema.texto;
            graficos[id].options.plugins.tooltip.bodyColor = tema.texto;

            graficos[id].update();
            return;
        }

        graficos[id] = new Chart(document.getElementById(id), {

            type: 'doughnut',

            data: {
                labels: labels,
                datasets: [{
                    data: valores,
                    backgroundColor: labels.map((_, i) => valores[i] === 0 && labels[i] === 'Sin datos'
                        ? '#94a3b8'
                        : colores[i % colores.length]),
                    borderWidth: 3,
                    borderColor: tema.fondo,
                    hoverOffset: 7
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',

                plugins: {

                    legend: {
                        position: 'bottom',
                        labels: {
                            color: tema.texto,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 18,
                            boxWidth: 8,
                            font: {
                                size: 11,
                                family: 'Arial'
                            }
                        }
                    },

                    tooltip: {
                        backgroundColor: tema.fondo,
                        titleColor: tema.texto,
                        bodyColor: tema.texto,
                        borderColor: tema.borde,
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 8,

                        callbacks: {
                            label: function (context) {

                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const valor = context.raw;
                                const porcentaje = total ? (valor / total * 100).toFixed(1) : 0;

                                return ` ${context.label}: ${valor} (${porcentaje}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    function actualizarGraficos() {

        const filas = obtenerFilasVisibles();

        actualizarGrafico('enteChart', contarColumna(filas, 1));
        actualizarGrafico('medioChart', contarColumna(filas, 3));
        actualizarGrafico('prioridadChart', contarColumna(filas, 5));
        actualizarGrafico('statusChart', contarColumna(filas, 6));
    }

    /* =============================================
       ACTUALIZACIÓN GENERAL
    ============================================= */
    function aplicarFiltros() {

        tabla.draw();

        actualizarContadores();
        actualizarIndicadorFiltros();
        actualizarGraficos();
    }

    $('#filtroEntidad, #filtroMedio, #filtroPrioridad, #filtroStatus, #fechaDesde, #fechaHasta')
        .on('change', aplicarFiltros);

    $('#limpiarFiltros').on('click', function () {

        $('#filtroEntidad, #filtroMedio, #filtroPrioridad, #filtroStatus').val('all');
        $('#fechaDesde, #fechaHasta').val('');

        aplicarFiltros();
    });

    /* =============================================
       EXPORTAR EXCEL
    ============================================= */
    function exportarExcel(soloFiltrados) {

        const filas = soloFiltrados
            ? obtenerFilasVisibles()
            : tabla.rows().nodes().toArray();

        if (!filas.length) {
            alert('No hay registros para exportar.');
            return;
        }

        const datos = [[
            'Ticket',
            'Ente',
            'Solicitante',
            'Medio',
            'Descripción',
            'Prioridad',
            'Estado',
            'Creado por',
            'Fecha'
        ]];

        filas.forEach(tr => {

            const celdas = $(tr).find('td');

            datos.push([
                celdas.eq(0).text().trim(),
                celdas.eq(1).text().trim(),
                celdas.eq(2).text().trim(),
                celdas.eq(3).text().trim(),
                celdas.eq(4).text().trim(),
                celdas.eq(5).text().trim(),
                celdas.eq(6).text().trim(),
                celdas.eq(7).text().trim(),
                celdas.eq(8).text().trim()
            ]);
        });

        const libro = XLSX.utils.book_new();
        const hoja = XLSX.utils.aoa_to_sheet(datos);

        hoja['!cols'] = [
            { wch: 12 },
            { wch: 30 },
            { wch: 25 },
            { wch: 20 },
            { wch: 50 },
            { wch: 20 },
            { wch: 20 },
            { wch: 25 },
            { wch: 22 }
        ];

        XLSX.utils.book_append_sheet(libro, hoja, 'Tickets');

        const fecha = new Date().toISOString().replace(/[-:T]/g, '').substring(0, 12);

        XLSX.writeFile(
            libro,
            (soloFiltrados ? 'reporte_tickets_' : 'todos_tickets_') + fecha + '.xlsx'
        );
    }

    $('#exportExcelBtn').on('click', function () {
        exportarExcel(true);
    });

    $('#exportAllExcelBtn').on('click', function () {
        exportarExcel(false);
    });

    /* =============================================
       EVENTOS
    ============================================= */
    tabla.on('draw', function () {
        actualizarContadores();
        actualizarGraficos();
    });

    aplicarFiltros();

    // Actualiza los colores de los gráficos cuando cambia el tema.
    const observadorTema = new MutationObserver(function () {
        actualizarGraficos();
    });

    observadorTema.observe(document.body, {
        attributes: true,
        attributeFilter: ['class', 'data-bs-theme']
    });

    observadorTema.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
    });

});
</script>
