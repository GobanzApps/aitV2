
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

// Catálogos de servicios e ítems usando sus identificadores reales
$catalogoServiciosReporte = [];
foreach ($tiposReporteServicio as $tipoReporte) {
    $catalogoServiciosReporte[(int)$tipoReporte['id']] = $tipoReporte['tag_tipo'];
}

$catalogoItemsReporte = [];
$serviciosPorItemReporte = [];

foreach ($tagsReporteServicio as $tagReporte) {

    $idItemReporte = (int)$tagReporte['id'];
    $catalogoItemsReporte[$idItemReporte] = $tagReporte['tag'];

    $idsTiposReporte = json_decode($tagReporte['id_tag_tipo'] ?? '[]', true);

    if (!is_array($idsTiposReporte)) {
        $idsTiposReporte = is_numeric($tagReporte['id_tag_tipo'] ?? null)
            ? [(int)$tagReporte['id_tag_tipo']]
            : [];
    }

    $idsTiposReporte = array_values(array_unique(array_filter(
        array_map('intval', $idsTiposReporte),
        function ($id) use ($catalogoServiciosReporte) {
            return $id > 0 && isset($catalogoServiciosReporte[$id]);
        }
    )));

    $serviciosPorItemReporte[$idItemReporte] = $idsTiposReporte;
}

// Construir un registro por cada ítem asociado al ticket
$registrosServiciosReporte = [];

foreach ($tserviciosReporte as $registroServicioReporte) {

    $idsTagsReporte = json_decode($registroServicioReporte['id_tag'] ?? '[]', true);

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

    $coordinacionesRegistroReporte = $coordinacionesPorTicketReporte[$idTicketReporte] ?? [];

    $nombresCoordinacionesReporte = !empty($coordinacionesRegistroReporte)
        ? implode(', ', array_unique(array_values($coordinacionesRegistroReporte)))
        : 'No asignada';

    $idsCoordinacionesRegistroReporte = array_map('intval', array_keys($coordinacionesRegistroReporte));

    foreach ($idsTagsReporte as $idTagReporte) {

        $idTagReporte = (int)$idTagReporte;

        if (!isset($mapaTagsReporte[$idTagReporte])) {
            continue;
        }

        $tagReporte = $mapaTagsReporte[$idTagReporte];
        $idsTiposReporte = $serviciosPorItemReporte[$idTagReporte] ?? [];
        $nombresServiciosReporte = [];

        foreach ($idsTiposReporte as $idTipoReporte) {
            if (isset($catalogoServiciosReporte[$idTipoReporte])) {
                $nombresServiciosReporte[] = $catalogoServiciosReporte[$idTipoReporte];
            }
        }

        $registrosServiciosReporte[] = [
            'ticket' => $idTicketReporte,
            'servicio' => !empty($nombresServiciosReporte)
                ? implode(', ', $nombresServiciosReporte)
                : 'Sin servicio',
            'servicios_ids' => $idsTiposReporte,
            'item_id' => $idTagReporte,
            'item' => $tagReporte['tag'] ?? 'Sin ítem',
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

        <!-- SELECTORES DE SERVICIOS E ÍTEMS -->
        <div class="card reporte-card selector-card mb-4">
            <div class="card-header border-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="reporte-icon"><i class="fas fa-sliders-h"></i></span>
                        <div>
                            <h2 class="h5 mb-0 fw-semibold">Explorar servicios e ítems</h2>
                            <small class="text-body-secondary">Selecciona una categoría para ver cómo se relaciona con la otra.</small>
                        </div>
                    </div>
                    <span class="selector-ayuda"><i class="fas fa-mouse-pointer me-1"></i> Selección múltiple</span>
                </div>
            </div>

            <div class="card-body pt-2">
                <div class="row g-3">
                    <div class="col-12 col-lg-5">
                        <div class="selector-panel h-100">
                            <div class="selector-panel-header">
                                <div>
                                    <div class="selector-titulo">Servicios</div>
                                    <small class="text-body-secondary">Códigos de servicio</small>
                                </div>
                                <span class="selector-contador" id="contadorServiciosSeleccionados">0 seleccionados</span>
                            </div>
                            <div class="selector-lista-servicio selector-grid-servicios">
                                <?php foreach ($catalogoServiciosReporte as $idServicioReporte => $nombreServicioReporte): ?>
                                    <label class="selector-opcion-servicio selector-opcion-codigo">
                                        <input type="checkbox" class="form-check-input filtro-servicio-checkbox" value="<?php echo (int)$idServicioReporte; ?>">
                                        <span><?php echo htmlspecialchars($nombreServicioReporte, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <?php if (empty($catalogoServiciosReporte)): ?>
                                    <div class="text-body-secondary small p-2">No hay servicios registrados.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7">
                        <div class="selector-panel h-100">
                            <div class="selector-panel-header">
                                <div>
                                    <div class="selector-titulo">Ítems</div>
                                    <small class="text-body-secondary">Equipos, componentes y otros elementos</small>
                                </div>
                                <span class="selector-contador" id="contadorItemsSeleccionados">0 seleccionados</span>
                            </div>
                            <div class="selector-lista-servicio selector-grid-items">
                                <?php foreach ($catalogoItemsReporte as $idItemReporte => $nombreItemReporte): ?>
                                    <label class="selector-opcion-servicio selector-opcion-item">
                                        <input type="checkbox" class="form-check-input filtro-item-checkbox" value="<?php echo (int)$idItemReporte; ?>">
                                        <span><?php echo htmlspecialchars($nombreItemReporte, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                                <?php if (empty($catalogoItemsReporte)): ?>
                                    <div class="text-body-secondary small p-2">No hay ítems registrados.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="selector-pie mt-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Los gráficos se actualizarán automáticamente. Puedes seleccionar varios servicios, varios ítems o combinar ambas opciones.
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
                                    data-servicios="<?php echo htmlspecialchars(json_encode($registroReporte['servicios_ids']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-item="<?php echo (int)$registroReporte['item_id']; ?>"
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


.reporte-servicio .selector-panel {
    border: 1px solid var(--rs-border);
    border-radius: 12px;
    padding: .9rem;
    background: var(--bs-tertiary-bg, var(--bs-body-bg));
}

.reporte-servicio .selector-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    margin-bottom: .75rem;
}

.reporte-servicio .selector-titulo {
    font-size: .95rem;
    font-weight: 700;
}

.reporte-servicio .selector-contador {
    flex: 0 0 auto;
    border-radius: 20px;
    padding: .25rem .6rem;
    color: var(--bs-primary);
    background: rgba(13,110,253,.10);
    font-size: .72rem;
    font-weight: 700;
}

.reporte-servicio .selector-ayuda {
    color: var(--rs-muted);
    font-size: .78rem;
}

.reporte-servicio .selector-lista-servicio {
    max-height: 245px;
    overflow-y: auto;
    padding: .15rem;
}

.reporte-servicio .selector-grid-servicios {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(76px, 1fr));
    gap: .5rem;
}

.reporte-servicio .selector-grid-items {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
    gap: .5rem;
}

.reporte-servicio .selector-opcion-servicio {
    display: flex;
    align-items: center;
    gap: .55rem;
    min-width: 0;
    min-height: 42px;
    margin: 0;
    padding: .55rem .65rem;
    border: 1px solid var(--rs-border);
    border-radius: 9px;
    background: var(--bs-body-bg);
    cursor: pointer;
    transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
}

.reporte-servicio .selector-opcion-servicio:hover {
    border-color: rgba(13,110,253,.45);
    background: rgba(13,110,253,.045);
}

.reporte-servicio .selector-opcion-servicio:has(.form-check-input:checked) {
    border-color: var(--bs-primary);
    background: rgba(13,110,253,.09);
    box-shadow: inset 0 0 0 1px rgba(13,110,253,.12);
}

.reporte-servicio .selector-opcion-servicio .form-check-input {
    margin: 0;
    flex: 0 0 auto;
    cursor: pointer;
}

.reporte-servicio .selector-opcion-servicio span {
    min-width: 0;
    overflow-wrap: anywhere;
    line-height: 1.25;
    font-size: .82rem;
    font-weight: 600;
}

.reporte-servicio .selector-opcion-codigo {
    justify-content: center;
    min-height: 48px;
    padding: .45rem .5rem;
}

.reporte-servicio .selector-opcion-codigo span {
    font-size: .9rem;
    letter-spacing: .5px;
    font-weight: 800;
}

.reporte-servicio .selector-pie {
    color: var(--rs-muted);
    font-size: .8rem;
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

    const catalogoServicios = <?php echo json_encode($catalogoServiciosReporte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const catalogoItems = <?php echo json_encode($catalogoItemsReporte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    let fechaDesdeServicio = '';
    let fechaHastaServicio = '';
    let coordinacionSeleccionadaServicio = '';
    const serviciosSeleccionados = new Set();
    const itemsSeleccionados = new Set();

    let graficoServicio = null;
    let graficoItem = null;

    const paletaServicio = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
        '#9966FF', '#FF9F40', '#8A2BE2', '#5F9EA0',
        '#D2691E', '#DC143C', '#00CED1', '#FFD700',
        '#ADFF2F', '#FF69B4', '#CD5C5C', '#20B2AA'
    ];

    function leerIdsJSON(valor) {
        try {
            const datos = JSON.parse(valor || '[]');
            if (Array.isArray(datos)) return datos.map(Number).filter(Number.isFinite);
            if (Number.isFinite(Number(datos))) return [Number(datos)];
        } catch (error) {
            if (valor && Number.isFinite(Number(valor))) return [Number(valor)];
        }
        return [];
    }

    // FILTROS PERSONALIZADOS DE DATATABLE
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {

        if (!settings.nTable || settings.nTable.id !== 'ticketsTableServicio') return true;

        const fila = settings.aoData[dataIndex]?.nTr;
        if (!fila) return true;

        const fecha = (fila.getAttribute('data-fecha') || '').substring(0, 10);

        if (fechaDesdeServicio && (!fecha || fecha < fechaDesdeServicio)) return false;
        if (fechaHastaServicio && (!fecha || fecha > fechaHastaServicio)) return false;

        if (coordinacionSeleccionadaServicio) {
            const idsCoordinaciones = leerIdsJSON(fila.getAttribute('data-coordinaciones'));
            if (!idsCoordinaciones.includes(Number(coordinacionSeleccionadaServicio))) return false;
        }

        if (serviciosSeleccionados.size > 0) {
            const idsServicios = leerIdsJSON(fila.getAttribute('data-servicios'));
            if (!idsServicios.some(id => serviciosSeleccionados.has(id))) return false;
        }

        if (itemsSeleccionados.size > 0) {
            const idItem = Number(fila.getAttribute('data-item'));
            if (!itemsSeleccionados.has(idItem)) return false;
        }

        return true;
    });

    const tablaServicio = $tabla.DataTable({
        responsive: true,
        retrieve: true,
        order: [[6, 'desc']],
        pageLength: 25,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'Todos']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            zeroRecords: 'No se encontraron registros',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        },
        drawCallback: function () {
            actualizarContadoresServicio();
            actualizarGraficosServicio();
        }
    });

    function obtenerFilasServicio(soloFiltrados) {
        const filas = [];
        const selector = soloFiltrados ? { search: 'applied' } : {};

        tablaServicio.rows(selector).every(function () {
            const nodo = this.node();
            const celdas = this.data();

            filas.push({
                ticket: $('<div>').html(celdas[1]).text().trim(),
                servicio: $('<div>').html(celdas[2]).text().trim(),
                servicios_ids: leerIdsJSON(nodo.getAttribute('data-servicios')),
                coordinacion: $('<div>').html(celdas[3]).text().trim(),
                item: $('<div>').html(celdas[4]).text().trim(),
                item_id: Number(nodo.getAttribute('data-item')),
                cantidad: parseFloat(nodo.getAttribute('data-cantidad')) || 0,
                fecha: $('<div>').html(celdas[6]).text().trim()
            });
        });

        return filas;
    }

    function actualizarContadoresServicio() {
        const filas = obtenerFilasServicio(true);
        const tickets = new Set();
        let suma = 0;

        filas.forEach(fila => {
            if (fila.ticket) tickets.add(fila.ticket);
            suma += fila.cantidad;
        });

        $('#totalRegistrosServicio').text(tablaServicio.rows().count());
        $('#visibleRegistrosServicio').text(filas.length);
        $('#ticketsUnicosServicio').text(tickets.size);
        $('#sumaCantidadesServicio').text(suma.toLocaleString('es-VE'));
        $('#contadorServiciosSeleccionados').text(serviciosSeleccionados.size + ' seleccionados');
        $('#contadorItemsSeleccionados').text(itemsSeleccionados.size + ' seleccionados');
    }

    function obtenerDistribucionServicio(tipo) {
        const resultado = {};

        obtenerFilasServicio(true).forEach(fila => {
            if (tipo === 'servicio') {
                fila.servicios_ids.forEach(idServicio => {
                    if (serviciosSeleccionados.size > 0 && !serviciosSeleccionados.has(idServicio)) return;
                    const nombre = catalogoServicios[idServicio] || catalogoServicios[String(idServicio)];
                    if (!nombre) return;
                    resultado[nombre] = (resultado[nombre] || 0) + fila.cantidad;
                });
            } else {
                const idItem = fila.item_id;
                if (itemsSeleccionados.size > 0 && !itemsSeleccionados.has(idItem)) return;
                const nombre = catalogoItems[idItem] || catalogoItems[String(idItem)];
                if (!nombre) return;
                resultado[nombre] = (resultado[nombre] || 0) + fila.cantidad;
            }
        });

        return resultado;
    }

    function crearGraficoServicio(canvasId, emptyId, datos) {
        const canvas = document.getElementById(canvasId);
        const empty = document.getElementById(emptyId);
        if (!canvas || !empty || typeof Chart === 'undefined') return null;

        const etiquetas = Object.keys(datos);
        const valores = Object.values(datos);

        if (!etiquetas.length) {
            canvas.classList.add('d-none');
            empty.classList.remove('d-none');
            empty.textContent = 'No hay datos relacionados con la selección actual.';
            return null;
        }

        canvas.classList.remove('d-none');
        empty.classList.add('d-none');

        const temaOscuro = document.documentElement.getAttribute('data-bs-theme') === 'dark';

        return new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: etiquetas,
                datasets: [{
                    data: valores,
                    backgroundColor: etiquetas.map((_, i) => paletaServicio[i % paletaServicio.length]),
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
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const porcentaje = total ? ((context.raw / total) * 100).toFixed(1) : '0.0';
                                return ` ${context.label}: ${context.raw} (${porcentaje}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    function actualizarGraficosServicio() {
        if (graficoServicio) {
            graficoServicio.destroy();
            graficoServicio = null;
        }
        if (graficoItem) {
            graficoItem.destroy();
            graficoItem = null;
        }

        const haySeleccion = serviciosSeleccionados.size > 0 || itemsSeleccionados.size > 0;

        if (!haySeleccion) {
            $('#servicioChart, #itemChart').addClass('d-none');
            $('#servicioChartEmpty, #itemChartEmpty')
                .removeClass('d-none')
                .text('Selecciona uno o más servicios o ítems para consultar su distribución.');
            return;
        }

        graficoServicio = crearGraficoServicio(
            'servicioChart', 'servicioChartEmpty', obtenerDistribucionServicio('servicio')
        );
        graficoItem = crearGraficoServicio(
            'itemChart', 'itemChartEmpty', obtenerDistribucionServicio('item')
        );
    }

    function actualizarEtiquetaFiltrosServicio() {
        const filtros = [];

        if (fechaDesdeServicio || fechaHastaServicio) {
            const desde = fechaDesdeServicio ? fechaDesdeServicio.split('-').reverse().join('/') : 'Inicio';
            const hasta = fechaHastaServicio ? fechaHastaServicio.split('-').reverse().join('/') : 'Fin';
            filtros.push(`Fecha: ${desde} - ${hasta}`);
        }

        if (coordinacionSeleccionadaServicio) {
            filtros.push('Coordinación: ' + $('#coordinacionFilterServicio option:selected').text());
        }

        if (serviciosSeleccionados.size) {
            const nombres = [...serviciosSeleccionados].map(id => catalogoServicios[id] || catalogoServicios[String(id)]).filter(Boolean);
            filtros.push('Servicios: ' + nombres.join(', '));
        }

        if (itemsSeleccionados.size) {
            const nombres = [...itemsSeleccionados].map(id => catalogoItems[id] || catalogoItems[String(id)]).filter(Boolean);
            filtros.push('Ítems: ' + nombres.join(', '));
        }

        const $badge = $('#activeFiltersServicio');
        if (!filtros.length) {
            $badge.removeClass('text-bg-primary').addClass('text-bg-secondary').text('Sin filtros activos');
        } else {
            $badge.removeClass('text-bg-secondary').addClass('text-bg-primary').text(filtros.join(' | '));
        }
    }

    function aplicarFiltrosServicio() {
        if (fechaDesdeServicio && fechaHastaServicio && fechaDesdeServicio > fechaHastaServicio) {
            const temporal = fechaDesdeServicio;
            fechaDesdeServicio = fechaHastaServicio;
            fechaHastaServicio = temporal;
            $('#fechaDesdeServicio').val(fechaDesdeServicio);
            $('#fechaHastaServicio').val(fechaHastaServicio);
        }
        tablaServicio.draw();
        actualizarEtiquetaFiltrosServicio();
    }

    $('#fechaDesdeServicio, #fechaHastaServicio').on('change input', function () {
        fechaDesdeServicio = $('#fechaDesdeServicio').val() || '';
        fechaHastaServicio = $('#fechaHastaServicio').val() || '';
        aplicarFiltrosServicio();
    });

    $('#coordinacionFilterServicio').on('change', function () {
        coordinacionSeleccionadaServicio = $(this).val() || '';
        aplicarFiltrosServicio();
    });

    $('.filtro-servicio-checkbox').on('change', function () {
        const id = Number(this.value);
        if (this.checked) serviciosSeleccionados.add(id);
        else serviciosSeleccionados.delete(id);
        aplicarFiltrosServicio();
    });

    $('.filtro-item-checkbox').on('change', function () {
        const id = Number(this.value);
        if (this.checked) itemsSeleccionados.add(id);
        else itemsSeleccionados.delete(id);
        aplicarFiltrosServicio();
    });

    $('#clearFiltersServicio').on('click', function () {
        $('#fechaDesdeServicio, #fechaHastaServicio').val('');
        $('#coordinacionFilterServicio').val('');
        $('.filtro-servicio-checkbox, .filtro-item-checkbox').prop('checked', false);

        fechaDesdeServicio = '';
        fechaHastaServicio = '';
        coordinacionSeleccionadaServicio = '';
        serviciosSeleccionados.clear();
        itemsSeleccionados.clear();

        aplicarFiltrosServicio();
    });

    function exportarExcelServicio(soloFiltrados) {
        const filas = obtenerFilasServicio(soloFiltrados);

        if (!filas.length) {
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay registros para exportar.' });
            } else {
                alert('No hay registros para exportar.');
            }
            return;
        }

        const datos = [['#', 'Ticket', 'Servicio', 'Coordinación', 'Ítem', 'Cantidad', 'Fecha']];
        filas.forEach((fila, index) => {
            datos.push([index + 1, fila.ticket, fila.servicio, fila.coordinacion, fila.item, fila.cantidad, fila.fecha]);
        });

        const libro = XLSX.utils.book_new();
        const hoja = XLSX.utils.aoa_to_sheet(datos);
        hoja['!cols'] = [
            { wch: 8 }, { wch: 15 }, { wch: 30 }, { wch: 30 },
            { wch: 30 }, { wch: 12 }, { wch: 22 }
        ];
        XLSX.utils.book_append_sheet(libro, hoja, 'Tickets por Servicios');

        const ahora = new Date();
        const marcaTiempo = ahora.getFullYear()
            + String(ahora.getMonth() + 1).padStart(2, '0')
            + String(ahora.getDate()).padStart(2, '0') + '_'
            + String(ahora.getHours()).padStart(2, '0')
            + String(ahora.getMinutes()).padStart(2, '0');

        XLSX.writeFile(libro, (soloFiltrados ? 'reporte_servicios_' : 'todos_servicios_') + marcaTiempo + '.xlsx');

        if (window.Swal) {
            Swal.fire({
                icon: 'success', title: 'Exportación exitosa',
                text: `Se exportaron ${filas.length} registros.`,
                timer: 1800, showConfirmButton: false
            });
        }
    }

    $('#exportExcelServicio').on('click', function () { exportarExcelServicio(true); });
    $('#exportAllExcelServicio').on('click', function () { exportarExcelServicio(false); });

    actualizarContadoresServicio();
    actualizarGraficosServicio();
    actualizarEtiquetaFiltrosServicio();
});
</script>
