
<?php
/*=============================================
REPORTE DE TICKETS POR SERVICIOS E ÍTEMS
Adaptado de AIT V1 a AITV2.
=============================================*/

$authReporteServicio = ControladorTicket::authTicket();

$puedeVerReporteServicio = $authReporteServicio && (
    ControladorTicket::esAdminTicket($authReporteServicio)
    || ControladorTicket::esCoordinadorTicket($authReporteServicio)
    || in_array(11, $authReporteServicio['coordsIds'] ?? [], true)
    || in_array(9, $authReporteServicio['coordsIds'] ?? [], true)
    || (($authReporteServicio['userData']['user'] ?? '') === 'agamardo')
);

if (!$puedeVerReporteServicio) {
    echo '<script>window.location.replace("index.php?ruta=inicio");</script>';
    return;
}

// Obtener información general
$tagsReporteServicio = ControladorTag::ctrMostrarTag(null, null) ?: [];
$tiposReporteServicio = ControladorTagTipo::ctrMostrarTagTipo(null, null) ?: [];
$coordinacionesReporteServicio = ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: [];
$tcoordinacionesReporteServicio = ControladorTcoordinacion::ctrMostrarTcoordinacion(null, null) ?: [];
$tserviciosReporte = ControladorTservicio::ctrMostrarTservicio(null, null) ?: [];

// Mapas para evitar consultas repetidas
$mapaTagsReporte = [];
foreach ($tagsReporteServicio as $tagReporte) {
    $mapaTagsReporte[(int)$tagReporte['id']] = $tagReporte;
}

$mapaTiposReporte = [];
foreach ($tiposReporteServicio as $tipoReporte) {
    $mapaTiposReporte[(int)$tipoReporte['id']] = $tipoReporte;
}

$mapaCoordinacionesReporte = [];
foreach ($coordinacionesReporteServicio as $coordinacionReporte) {
    $mapaCoordinacionesReporte[(int)$coordinacionReporte['id']] = $coordinacionReporte;
}

// Relacionar coordinaciones con cada ticket
$coordinacionesPorTicketReporte = [];

foreach ($tcoordinacionesReporteServicio as $relacionReporte) {

    $idTicketReporte = (int)($relacionReporte['id_ticket'] ?? 0);

    $idsCoordinacionesReporte = json_decode(
        $relacionReporte['id_coordinacion'] ?? '[]',
        true
    );

    if (!is_array($idsCoordinacionesReporte)) {
        $idsCoordinacionesReporte = is_numeric($relacionReporte['id_coordinacion'] ?? null)
            ? [(int)$relacionReporte['id_coordinacion']]
            : [];
    }

    foreach ($idsCoordinacionesReporte as $idCoordinacionReporte) {

        $idCoordinacionReporte = (int)$idCoordinacionReporte;

        if (
            $idCoordinacionReporte > 0 &&
            isset($mapaCoordinacionesReporte[$idCoordinacionReporte])
        ) {
            $nombreCoordinacionReporte =
                $mapaCoordinacionesReporte[$idCoordinacionReporte]['coordinacion'];

            $coordinacionesPorTicketReporte[$idTicketReporte][$idCoordinacionReporte] =
                $nombreCoordinacionReporte;
        }
    }
}

// Construir registros
$registrosServiciosReporte = [];

foreach ($tserviciosReporte as $registroServicioReporte) {

    $idsTagsReporte = json_decode(
        $registroServicioReporte['id_tag'] ?? '[]',
        true
    );

    if (!is_array($idsTagsReporte)) {
        $idsTagsReporte = is_numeric($registroServicioReporte['id_tag'] ?? null)
            ? [(int)$registroServicioReporte['id_tag']]
            : [];
    }

    $idTicketReporte = (int)($registroServicioReporte['id_ticket'] ?? 0);

    $fechaOriginalReporte = $registroServicioReporte['modificacion'] ?? '';
    $timestampReporte = !empty($fechaOriginalReporte)
        ? strtotime($fechaOriginalReporte)
        : false;

    $fechaIsoReporte = $timestampReporte !== false
        ? date('Y-m-d H:i:s', $timestampReporte)
        : '';

    $fechaMostrarReporte = $timestampReporte !== false
        ? date('d/m/Y H:i', $timestampReporte)
        : '—';

    $coordinacionesRegistroReporte =
        $coordinacionesPorTicketReporte[$idTicketReporte] ?? [];

    $nombresCoordinacionesReporte = !empty($coordinacionesRegistroReporte)
        ? implode(', ', array_unique(array_values($coordinacionesRegistroReporte)))
        : 'No asignada';

    $idsCoordinacionesRegistroReporte = array_map(
        'intval',
        array_keys($coordinacionesRegistroReporte)
    );

    // Un registro por cada tag asociado al servicio
    foreach ($idsTagsReporte as $idTagReporte) {

        $idTagReporte = (int)$idTagReporte;

        if (!isset($mapaTagsReporte[$idTagReporte])) {
            continue;
        }

        $tagReporte = $mapaTagsReporte[$idTagReporte];

        $nombreItemReporte = $tagReporte['tag'] ?? 'Sin ítem';

        // Un tag puede estar relacionado con varios tipos
        $idsTiposReporte = json_decode(
            $tagReporte['id_tag_tipo'] ?? '[]',
            true
        );

        if (!is_array($idsTiposReporte)) {
            $idsTiposReporte = is_numeric($tagReporte['id_tag_tipo'] ?? null)
                ? [(int)$tagReporte['id_tag_tipo']]
                : [];
        }

        $nombresServiciosReporte = [];

        foreach ($idsTiposReporte as $idTipoReporte) {

            $idTipoReporte = (int)$idTipoReporte;

            if (isset($mapaTiposReporte[$idTipoReporte])) {
                $nombresServiciosReporte[] =
                    $mapaTiposReporte[$idTipoReporte]['tag_tipo'];
            }
        }

        $nombreServicioReporte = !empty($nombresServiciosReporte)
            ? implode(', ', array_unique($nombresServiciosReporte))
            : 'Sin servicio';

        $registrosServiciosReporte[] = [
            'ticket' => $idTicketReporte,
            'servicio' => $nombreServicioReporte,
            'item' => $nombreItemReporte,
            'coordinacion' => $nombresCoordinacionesReporte,
            'coordinaciones_ids' => $idsCoordinacionesRegistroReporte,
            'cantidad' => (float)($registroServicioReporte['cantidad'] ?? 0),
            'fecha_iso' => $fechaIsoReporte,
            'fecha' => $fechaMostrarReporte
        ];
    }
}
?>

<div class="content-wrapper reporte-servicio">

    <!-- ENCABEZADO -->
    <section class="content-header px-3 pt-3 pb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h3 mb-1 fw-bold">Tickets por Servicios e Ítems</h1>
                <p class="text-body-secondary mb-0">
                    Consulta las cantidades de servicios e ítems registrados en los tickets.
                </p>
            </div>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="inicio">Inicio</a></li>
                    <li class="breadcrumb-item active">Reportes</li>
                    <li class="breadcrumb-item active">Servicios e ítems</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="content px-3 pb-4">

        <!-- FILTROS -->
        <div class="card reporte-card mb-4">

            <div class="card-header border-0">
                <div class="d-flex align-items-center gap-2">
                    <span class="reporte-icon">
                        <i class="fas fa-filter"></i>
                    </span>

                    <div>
                        <h2 class="h5 mb-0 fw-semibold">Filtros del reporte</h2>
                        <small class="text-body-secondary">
                            Filtra por fecha y coordinación.
                        </small>
                    </div>
                </div>
            </div>

            <div class="card-body">

                <div class="row align-items-end g-3">

                    <div class="col-12 col-lg-5">

                        <label class="form-label fw-semibold">Rango de fechas</label>

                        <div class="row g-2">

                            <div class="col-6">
                                <label for="fechaDesdeServicio" class="form-label small text-body-secondary mb-1">
                                    Desde
                                </label>

                                <input
                                    type="date"
                                    id="fechaDesdeServicio"
                                    class="form-control"
                                >
                            </div>

                            <div class="col-6">
                                <label for="fechaHastaServicio" class="form-label small text-body-secondary mb-1">
                                    Hasta
                                </label>

                                <input
                                    type="date"
                                    id="fechaHastaServicio"
                                    class="form-control"
                                >
                            </div>

                        </div>

                    </div>

                    <div class="col-12 col-lg-4">

                        <label for="coordinacionFilterServicio" class="form-label fw-semibold">
                            Coordinación
                        </label>

                        <select id="coordinacionFilterServicio" class="form-select">

                            <option value="">Todas las coordinaciones</option>

                            <?php foreach ($coordinacionesReporteServicio as $coordinacionReporte): ?>

                                <option value="<?php echo (int)$coordinacionReporte['id']; ?>">
                                    <?php echo htmlspecialchars($coordinacionReporte['coordinacion'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-12 col-lg-3 d-flex flex-wrap gap-2">

                        <button type="button" id="clearFiltersServicio" class="btn btn-outline-secondary">
                            <i class="fas fa-eraser me-1"></i>
                            Limpiar
                        </button>

                        <button type="button" id="exportExcelServicio" class="btn btn-success">
                            <i class="fas fa-file-excel me-1"></i>
                            Excel filtrados
                        </button>

                        <button type="button" id="exportAllExcelServicio" class="btn btn-outline-success">
                            <i class="fas fa-download me-1"></i>
                            Excel todos
                        </button>

                    </div>

                </div>

                <div class="mt-3">
                    <span id="activeFiltersServicio" class="badge rounded-pill text-bg-secondary">
                        Sin filtros activos
                    </span>
                </div>

            </div>
        </div>

        <!-- CONTADORES -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-md-3">
                <div class="reporte-stat stat-blue">
                    <div class="stat-icon">
                        <i class="fas fa-list-alt"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total registros</div>
                        <div class="stat-value" id="totalRegistrosServicio">0</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="reporte-stat stat-green">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div>
                        <div class="stat-label">Registros visibles</div>
                        <div class="stat-value" id="visibleRegistrosServicio">0</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="reporte-stat stat-orange">
                    <div class="stat-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div>
                        <div class="stat-label">Tickets únicos</div>
                        <div class="stat-value" id="ticketsUnicosServicio">0</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="reporte-stat stat-purple">
                    <div class="stat-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div>
                        <div class="stat-label">Suma cantidades</div>
                        <div class="stat-value" id="sumaCantidadesServicio">0</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- GRÁFICOS -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-lg-6">

                <div class="card reporte-card h-100">

                    <div class="card-header border-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="reporte-icon">
                                <i class="fas fa-chart-pie"></i>
                            </span>

                            <div>
                                <h2 class="h5 mb-0 fw-semibold">Distribución por Servicio</h2>
                                <small class="text-body-secondary">
                                    Suma de cantidades.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="chart-container-servicio">
                            <canvas id="servicioChart"></canvas>
                        </div>

                        <div id="servicioChartEmpty" class="text-center text-body-secondary py-3 d-none">
                            No hay datos disponibles.
                        </div>
                    </div>

                </div>

            </div>

            <div class="col-12 col-lg-6">

                <div class="card reporte-card h-100">

                    <div class="card-header border-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="reporte-icon">
                                <i class="fas fa-chart-pie"></i>
                            </span>

                            <div>
                                <h2 class="h5 mb-0 fw-semibold">Distribución por Ítem</h2>
                                <small class="text-body-secondary">
                                    Suma de cantidades.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="chart-container-servicio">
                            <canvas id="itemChart"></canvas>
                        </div>

                        <div id="itemChartEmpty" class="text-center text-body-secondary py-3 d-none">
                            No hay datos disponibles.
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- TABLA -->
        <div class="card reporte-card">

            <div class="card-header border-0">

                <div class="d-flex align-items-center gap-2">
                    <span class="reporte-icon">
                        <i class="fas fa-table"></i>
                    </span>

                    <div>
                        <h2 class="h5 mb-0 fw-semibold">
                            Listado de tickets por servicios
                        </h2>

                        <small class="text-body-secondary">
                            Detalle de servicios, ítems y cantidades registradas.
                        </small>
                    </div>
                </div>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table id="ticketsTableServicio" class="table table-hover table-striped align-middle w-100">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ticket</th>
                                <th>Servicio</th>
                                <th>Coordinación</th>
                                <th>Ítem</th>
                                <th>Cantidad</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($registrosServiciosReporte as $keyReporte => $registroReporte): ?>

                                <tr
                                    data-fecha="<?php echo htmlspecialchars($registroReporte['fecha_iso'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-coordinaciones="<?php echo htmlspecialchars(json_encode($registroReporte['coordinaciones_ids']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-cantidad="<?php echo htmlspecialchars((string)$registroReporte['cantidad'], ENT_QUOTES, 'UTF-8'); ?>"
                                >

                                    <td><?php echo $keyReporte + 1; ?></td>

                                    <td>
                                        <span class="ticket-number">
                                            #<?php echo (int)$registroReporte['ticket']; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="servicio-tag">
                                            <?php echo htmlspecialchars($registroReporte['servicio'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($registroReporte['coordinacion'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($registroReporte['item'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <span class="cantidad-badge">
                                            <?php echo htmlspecialchars((string)$registroReporte['cantidad'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>

                                    <td data-order="<?php echo htmlspecialchars($registroReporte['fecha_iso'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($registroReporte['fecha'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </section>
</div>

<style>
.reporte-servicio {
    --rs-border: var(--bs-border-color);
    --rs-card: var(--bs-body-bg);
    --rs-muted: var(--bs-secondary-color);
}

.reporte-servicio .reporte-card {
    border: 1px solid var(--rs-border);
    border-radius: 14px;
    background: var(--rs-card);
    box-shadow: 0 3px 14px rgba(0,0,0,.045);
    overflow: hidden;
}

.reporte-servicio .card-header {
    background: transparent;
    padding: 1.15rem 1.25rem .65rem;
}

.reporte-servicio .card-body {
    padding: 1.25rem;
}

.reporte-servicio .reporte-icon {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    color: #0d6efd;
    background: rgba(13,110,253,.11);
    flex: 0 0 auto;
}

.reporte-servicio .reporte-stat {
    min-height: 108px;
    padding: 1.1rem 1.15rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--rs-border);
    border-radius: 14px;
    background: var(--rs-card);
    box-shadow: 0 3px 14px rgba(0,0,0,.04);
}

.reporte-servicio .stat-icon {
    width: 49px;
    height: 49px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    font-size: 1.2rem;
    flex: 0 0 auto;
}

.reporte-servicio .stat-blue .stat-icon {
    color: #0d6efd;
    background: rgba(13,110,253,.12);
}

.reporte-servicio .stat-green .stat-icon {
    color: #198754;
    background: rgba(25,135,84,.12);
}

.reporte-servicio .stat-orange .stat-icon {
    color: #fd7e14;
    background: rgba(253,126,20,.13);
}

.reporte-servicio .stat-purple .stat-icon {
    color: #6f42c1;
    background: rgba(111,66,193,.13);
}

.reporte-servicio .stat-label {
    color: var(--rs-muted);
    font-size: .85rem;
    margin-bottom: .2rem;
}

.reporte-servicio .stat-value {
    font-size: 1.65rem;
    line-height: 1.2;
    font-weight: 750;
}

.reporte-servicio .chart-container-servicio {
    position: relative;
    width: 100%;
    height: 340px;
    max-width: 650px;
    margin: 0 auto;
}

.reporte-servicio .ticket-number {
    color: var(--bs-primary);
    font-weight: 700;
}

.reporte-servicio .servicio-tag {
    display: inline-block;
    padding: .35rem .65rem;
    border-radius: 7px;
    color: #0d6efd;
    background: rgba(13,110,253,.10);
    font-size: .85rem;
    font-weight: 600;
}

.reporte-servicio .cantidad-badge {
    display: inline-block;
    min-width: 35px;
    text-align: center;
    padding: .3rem .55rem;
    border-radius: 7px;
    color: #198754;
    background: rgba(25,135,84,.12);
    font-weight: 700;
}

.reporte-servicio .table thead th {
    white-space: nowrap;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .3px;
    color: var(--rs-muted);
    border-bottom-width: 1px;
}

.reporte-servicio .table tbody td {
    font-size: .9rem;
}

.reporte-servicio .dataTables_wrapper .dataTables_filter input,
.reporte-servicio .dataTables_wrapper .dataTables_length select {
    border: 1px solid var(--rs-border);
    border-radius: 8px;
    padding: .35rem .55rem;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.reporte-servicio .dataTables_wrapper .dataTables_filter input {
    margin-left: .4rem;
}

.reporte-servicio .dataTables_wrapper .dataTables_info {
    color: var(--rs-muted);
    font-size: .85rem;
    padding-top: 1rem;
}

.reporte-servicio .dataTables_wrapper .dataTables_paginate {
    padding-top: .7rem;
}

[data-bs-theme="dark"] .reporte-servicio .reporte-card,
[data-bs-theme="dark"] .reporte-servicio .reporte-stat {
    box-shadow: 0 3px 14px rgba(0,0,0,.16);
}

[data-bs-theme="dark"] .reporte-servicio .table-striped>tbody>tr:nth-of-type(odd)>* {
    --bs-table-bg-type: rgba(255,255,255,.025);
}

@media (max-width: 767.98px) {
    .reporte-servicio .card-body {
        padding: 1rem;
    }

    .reporte-servicio .chart-container-servicio {
        height: 290px;
    }

    .reporte-servicio .reporte-stat {
        min-height: 95px;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const $ = window.jQuery;

    if (!$ || !$.fn.DataTable) {
        console.error('No se pudo inicializar el reporte: jQuery o DataTables no están disponibles.');
        return;
    }

    const $tabla = $('#ticketsTableServicio');

    if (!$tabla.length) return;

    let fechaDesdeServicio = '';
    let fechaHastaServicio = '';
    let coordinacionSeleccionadaServicio = '';
    let graficoServicio = null;
    let graficoItem = null;

    // FILTRO PERSONALIZADO
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {

        if (!settings.nTable || settings.nTable.id !== 'ticketsTableServicio') {
            return true;
        }

        const fila = settings.aoData[dataIndex]?.nTr;

        if (!fila) return true;

        const fecha = (fila.getAttribute('data-fecha') || '').substring(0, 10);

        if (fechaDesdeServicio && (!fecha || fecha < fechaDesdeServicio)) {
            return false;
        }

        if (fechaHastaServicio && (!fecha || fecha > fechaHastaServicio)) {
            return false;
        }

        if (coordinacionSeleccionadaServicio) {

            let idsCoordinaciones = [];

            try {
                idsCoordinaciones = JSON.parse(
                    fila.getAttribute('data-coordinaciones') || '[]'
                );
            } catch (error) {
                idsCoordinaciones = [];
            }

            if (!idsCoordinaciones.includes(Number(coordinacionSeleccionadaServicio))) {
                return false;
            }
        }

        return true;
    });

    // DATATABLE
    const tablaServicio = $tabla.DataTable({

        responsive: true,
        retrieve: true,
        order: [[6, 'desc']],
        pageLength: 25,

        lengthMenu: [
            [5, 10, 25, 50, -1],
            [5, 10, 25, 50, 'Todos']
        ],

        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            zeroRecords: 'No se encontraron registros',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        },

        drawCallback: function () {
            actualizarContadoresServicio();
            actualizarGraficosServicio();
        }

    });

    // OBTENER FILAS
    function obtenerFilasServicio(soloFiltrados) {

        const filas = [];

        const selector = soloFiltrados
            ? { search: 'applied' }
            : {};

        tablaServicio.rows(selector).every(function () {

            const nodo = this.node();
            const celdas = this.data();

            filas.push({
                ticket: $('<div>').html(celdas[1]).text().trim(),
                servicio: $('<div>').html(celdas[2]).text().trim(),
                coordinacion: $('<div>').html(celdas[3]).text().trim(),
                item: $('<div>').html(celdas[4]).text().trim(),
                cantidad: parseFloat(nodo.getAttribute('data-cantidad')) || 0,
                fecha: $('<div>').html(celdas[6]).text().trim()
            });

        });

        return filas;
    }

    // CONTADORES
    function actualizarContadoresServicio() {

        const filas = obtenerFilasServicio(true);

        const tickets = new Set();

        let suma = 0;

        filas.forEach(fila => {

            if (fila.ticket) {
                tickets.add(fila.ticket);
            }

            suma += fila.cantidad;

        });

        $('#totalRegistrosServicio').text(tablaServicio.rows().count());
        $('#visibleRegistrosServicio').text(filas.length);
        $('#ticketsUnicosServicio').text(tickets.size);
        $('#sumaCantidadesServicio').text(suma.toLocaleString('es-VE'));
    }

    // DATOS PARA LOS GRÁFICOS
    function obtenerDistribucionServicio(tipo) {

        const resultado = {};

        obtenerFilasServicio(true).forEach(fila => {

            const nombre = tipo === 'servicio'
                ? fila.servicio
                : fila.item;

            const etiqueta = nombre || 'Sin identificar';

            resultado[etiqueta] = (resultado[etiqueta] || 0) + fila.cantidad;

        });

        return resultado;
    }

    const paletaServicio = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
        '#9966FF', '#FF9F40', '#8A2BE2', '#5F9EA0',
        '#D2691E', '#DC143C', '#00CED1', '#FFD700',
        '#ADFF2F', '#FF69B4', '#CD5C5C', '#20B2AA'
    ];

    function crearGraficoServicio(canvasId, emptyId, datos, tipo) {

        const canvas = document.getElementById(canvasId);
        const empty = document.getElementById(emptyId);

        if (!canvas || !empty || typeof Chart === 'undefined') {
            return null;
        }

        const etiquetas = Object.keys(datos);
        const valores = Object.values(datos);

        if (!etiquetas.length) {

            canvas.classList.add('d-none');
            empty.classList.remove('d-none');

            return null;
        }

        canvas.classList.remove('d-none');
        empty.classList.add('d-none');

        const temaOscuro =
            document.documentElement.getAttribute('data-bs-theme') === 'dark';

        return new Chart(canvas, {

            type: 'doughnut',

            data: {
                labels: etiquetas,

                datasets: [{
                    data: valores,
                    backgroundColor: etiquetas.map((_, i) =>
                        paletaServicio[i % paletaServicio.length]
                    ),
                    borderColor: temaOscuro ? '#212529' : '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 7
                }]
            },

            options: {

                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',

                plugins: {

                    legend: {
                        position: 'bottom',

                        labels: {
                            color: temaOscuro ? '#dee2e6' : '#495057',
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },

                    tooltip: {

                        callbacks: {

                            label: function (context) {

                                const total = context.dataset.data.reduce(
                                    (a, b) => a + b,
                                    0
                                );

                                const porcentaje = total
                                    ? ((context.raw / total) * 100).toFixed(1)
                                    : '0.0';

                                return ` ${context.label}: ${context.raw} (${porcentaje}%)`;
                            }

                        }

                    }

                }

            }

        });

    }

    // ACTUALIZAR GRÁFICOS
    function actualizarGraficosServicio() {

        if (graficoServicio) {
            graficoServicio.destroy();
            graficoServicio = null;
        }

        if (graficoItem) {
            graficoItem.destroy();
            graficoItem = null;
        }

        graficoServicio = crearGraficoServicio(
            'servicioChart',
            'servicioChartEmpty',
            obtenerDistribucionServicio('servicio'),
            'servicio'
        );

        graficoItem = crearGraficoServicio(
            'itemChart',
            'itemChartEmpty',
            obtenerDistribucionServicio('item'),
            'item'
        );
    }

    // ETIQUETA DE FILTROS
    function actualizarEtiquetaFiltrosServicio() {

        const filtros = [];

        if (fechaDesdeServicio || fechaHastaServicio) {

            const desde = fechaDesdeServicio
                ? fechaDesdeServicio.split('-').reverse().join('/')
                : 'Inicio';

            const hasta = fechaHastaServicio
                ? fechaHastaServicio.split('-').reverse().join('/')
                : 'Fin';

            filtros.push(`Fecha: ${desde} - ${hasta}`);
        }

        if (coordinacionSeleccionadaServicio) {

            const nombre = $('#coordinacionFilterServicio option:selected').text();

            filtros.push(`Coordinación: ${nombre}`);
        }

        const $badge = $('#activeFiltersServicio');

        if (!filtros.length) {

            $badge
                .removeClass('text-bg-primary')
                .addClass('text-bg-secondary')
                .text('Sin filtros activos');

        } else {

            $badge
                .removeClass('text-bg-secondary')
                .addClass('text-bg-primary')
                .text(filtros.join(' | '));

        }
    }

    // APLICAR FILTROS
    function aplicarFiltrosServicio() {

        if (
            fechaDesdeServicio &&
            fechaHastaServicio &&
            fechaDesdeServicio > fechaHastaServicio
        ) {

            const temporal = fechaDesdeServicio;

            fechaDesdeServicio = fechaHastaServicio;
            fechaHastaServicio = temporal;

            $('#fechaDesdeServicio').val(fechaDesdeServicio);
            $('#fechaHastaServicio').val(fechaHastaServicio);
        }

        tablaServicio.draw();
        actualizarEtiquetaFiltrosServicio();
    }

    // CAMBIO DE FECHAS
    $('#fechaDesdeServicio, #fechaHastaServicio').on('change input', function () {

        fechaDesdeServicio = $('#fechaDesdeServicio').val() || '';
        fechaHastaServicio = $('#fechaHastaServicio').val() || '';

        aplicarFiltrosServicio();
    });

    // CAMBIO DE COORDINACIÓN
    $('#coordinacionFilterServicio').on('change', function () {

        coordinacionSeleccionadaServicio = $(this).val() || '';

        aplicarFiltrosServicio();
    });

    // LIMPIAR FILTROS
    $('#clearFiltersServicio').on('click', function () {

        $('#fechaDesdeServicio, #fechaHastaServicio').val('');
        $('#coordinacionFilterServicio').val('');

        fechaDesdeServicio = '';
        fechaHastaServicio = '';
        coordinacionSeleccionadaServicio = '';

        aplicarFiltrosServicio();
    });

    // EXPORTAR EXCEL
    function exportarExcelServicio(soloFiltrados) {

        const filas = obtenerFilasServicio(soloFiltrados);

        if (!filas.length) {

            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin datos',
                    text: 'No hay registros para exportar.'
                });
            } else {
                alert('No hay registros para exportar.');
            }

            return;
        }

        const datos = [
            ['#', 'Ticket', 'Servicio', 'Coordinación', 'Ítem', 'Cantidad', 'Fecha']
        ];

        filas.forEach((fila, index) => {

            datos.push([
                index + 1,
                fila.ticket,
                fila.servicio,
                fila.coordinacion,
                fila.item,
                fila.cantidad,
                fila.fecha
            ]);

        });

        const libro = XLSX.utils.book_new();
        const hoja = XLSX.utils.aoa_to_sheet(datos);

        hoja['!cols'] = [
            { wch: 8 },
            { wch: 15 },
            { wch: 30 },
            { wch: 30 },
            { wch: 30 },
            { wch: 12 },
            { wch: 22 }
        ];

        XLSX.utils.book_append_sheet(
            libro,
            hoja,
            'Tickets por Servicios'
        );

        const ahora = new Date();

        const marcaTiempo =
            ahora.getFullYear() +
            String(ahora.getMonth() + 1).padStart(2, '0') +
            String(ahora.getDate()).padStart(2, '0') +
            '_' +
            String(ahora.getHours()).padStart(2, '0') +
            String(ahora.getMinutes()).padStart(2, '0');

        XLSX.writeFile(
            libro,
            (soloFiltrados ? 'reporte_servicios_' : 'todos_servicios_') +
            marcaTiempo +
            '.xlsx'
        );

        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Exportación exitosa',
                text: `Se exportaron ${filas.length} registros.`,
                timer: 1800,
                showConfirmButton: false
            });
        }
    }

    $('#exportExcelServicio').on('click', function () {
        exportarExcelServicio(true);
    });

    $('#exportAllExcelServicio').on('click', function () {
        exportarExcelServicio(false);
    });

    // INICIALIZACIÓN
    actualizarContadoresServicio();
    actualizarGraficosServicio();
    actualizarEtiquetaFiltrosServicio();

});
</script>
