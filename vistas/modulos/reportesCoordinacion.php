
<?php
/*=============================================
REPORTE DE TICKETS POR COORDINACIÓN
Adaptado de AIT V1 a la estructura de AITV2.
=============================================*/

$authReporteCoord = ControladorTicket::authTicket();
$puedeVerReporteCoord = $authReporteCoord && (
    ControladorTicket::esAdminTicket($authReporteCoord)
    || ControladorTicket::esCoordinadorTicket($authReporteCoord)
    || in_array(11, $authReporteCoord['coordsIds'] ?? [], true)
    || in_array(9, $authReporteCoord['coordsIds'] ?? [], true)
    || (($authReporteCoord['userData']['user'] ?? '') === 'agamardo')
);

if (!$puedeVerReporteCoord) {
    echo '<script>window.location.replace("index.php?ruta=inicio");</script>';
    return;
}

$todasCoordinacionesReporte = ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: [];
$mapaCoordinacionesReporte = [];

foreach ($todasCoordinacionesReporte as $coordinacionReporte) {
    $mapaCoordinacionesReporte[(int)$coordinacionReporte['id']] = $coordinacionReporte;
}

$tcoordinacionesReporte = ControladorTcoordinacion::ctrMostrarTcoordinacion(null, null) ?: [];
?>

<div class="content-wrapper reporte-coordinacion">
    <section class="content-header px-3 pt-3 pb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h3 mb-1 fw-bold">Tickets por Coordinación</h1>
                <p class="text-body-secondary mb-0">Consulta, filtra y exporta las coordinaciones asignadas a los tickets.</p>
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="inicio">Inicio</a></li>
                    <li class="breadcrumb-item active">Reportes</li>
                    <li class="breadcrumb-item active">Coordinación</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="content px-3 pb-4">

        <!-- FILTROS -->
        <div class="card reporte-card mb-4">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <span class="reporte-icon"><i class="fas fa-filter"></i></span>
                    <div>
                        <h2 class="h5 mb-0 fw-semibold">Filtros del reporte</h2>
                        <small class="text-body-secondary">Selecciona un rango de fechas para actualizar los resultados.</small>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row align-items-end g-3">
                    <div class="col-12 col-lg-5">
                        <label class="form-label fw-semibold">Rango de fechas</label>

                        <div class="row g-2">
                            <div class="col-6">
                                <label for="fechaDesdeCoord" class="form-label small text-body-secondary mb-1">Desde</label>
                                <input type="date" id="fechaDesdeCoord" class="form-control">
                            </div>

                            <div class="col-6">
                                <label for="fechaHastaCoord" class="form-label small text-body-secondary mb-1">Hasta</label>
                                <input type="date" id="fechaHastaCoord" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7 d-flex flex-wrap gap-2 justify-content-lg-end">
                        <button type="button" id="clearFiltersCoord" class="btn btn-outline-secondary">
                            <i class="fas fa-eraser me-1"></i> Limpiar filtros
                        </button>

                        <button type="button" id="exportExcelCoord" class="btn btn-success">
                            <i class="fas fa-file-excel me-1"></i> Exportar filtrados
                        </button>

                        <button type="button" id="exportAllExcelCoord" class="btn btn-outline-success">
                            <i class="fas fa-download me-1"></i> Exportar todos
                        </button>
                    </div>
                </div>

                <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                    <span id="activeFiltersCoord" class="badge rounded-pill text-bg-secondary">Sin filtros activos</span>
                    <small class="text-body-secondary">Los gráficos y contadores se actualizan automáticamente.</small>
                </div>
            </div>
        </div>

        <!-- ESTADÍSTICAS -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="reporte-stat stat-blue">
                    <div class="stat-icon"><i class="fas fa-list-alt"></i></div>
                    <div>
                        <div class="stat-label">Total de registros</div>
                        <div class="stat-value" id="totalRegistrosCoord">0</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="reporte-stat stat-green">
                    <div class="stat-icon"><i class="fas fa-eye"></i></div>
                    <div>
                        <div class="stat-label">Registros visibles</div>
                        <div class="stat-value" id="visibleRegistrosCoord">0</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="reporte-stat stat-orange">
                    <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                    <div>
                        <div class="stat-label">Tickets únicos</div>
                        <div class="stat-value" id="uniqueTicketsCoord">0</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRÁFICO -->
        <div class="card reporte-card mb-4">
            <div class="card-header border-0">
                <div class="d-flex align-items-center gap-2">
                    <span class="reporte-icon"><i class="fas fa-chart-pie"></i></span>
                    <div>
                        <h2 class="h5 mb-0 fw-semibold">Distribución por Coordinación</h2>
                        <small class="text-body-secondary">Cantidad de asignaciones por coordinación según los filtros aplicados.</small>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="chart-container-coord">
                    <canvas id="coordinacionChart"></canvas>
                </div>

                <div id="coordinacionChartEmpty" class="text-center text-body-secondary py-3 d-none">
                    No hay datos para mostrar con los filtros actuales.
                </div>
            </div>
        </div>

        <!-- TABLA -->
        <div class="card reporte-card">
            <div class="card-header border-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="reporte-icon"><i class="fas fa-table"></i></span>
                        <div>
                            <h2 class="h5 mb-0 fw-semibold">Listado de tickets por coordinación</h2>
                            <small class="text-body-secondary">Detalle de las coordinaciones asignadas a cada ticket.</small>
                        </div>
                    </div>

                    <span class="small text-body-secondary">
                        Registros: <strong id="tableCountCoord">0</strong>
                    </span>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table id="ticketsTableCoord" class="table table-hover table-striped align-middle w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ticket</th>
                                <th>Coordinación</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($tcoordinacionesReporte as $keyReporte => $registroReporte):

                            $idsCoordinacionesReporte = json_decode($registroReporte['id_coordinacion'] ?? '[]', true);

                            if (!is_array($idsCoordinacionesReporte)) {
                                $idsCoordinacionesReporte = [];
                            }

                            $nombresCoordinacionesReporte = [];

                            foreach ($idsCoordinacionesReporte as $idCoordinacionReporte) {
                                $idCoordinacionReporte = (int)$idCoordinacionReporte;

                                if (isset($mapaCoordinacionesReporte[$idCoordinacionReporte])) {
                                    $nombresCoordinacionesReporte[] = $mapaCoordinacionesReporte[$idCoordinacionReporte]['coordinacion'];
                                } else {
                                    $nombresCoordinacionesReporte[] = 'Coordinación #' . $idCoordinacionReporte;
                                }
                            }

                            $fechaOriginalReporte = $registroReporte['modificacion'] ?? '';
                            $timestampReporte = !empty($fechaOriginalReporte) ? strtotime($fechaOriginalReporte) : false;
                            $fechaIsoReporte = ($timestampReporte !== false) ? date('Y-m-d H:i:s', $timestampReporte) : '';
                            $fechaMostrarReporte = ($timestampReporte !== false) ? date('d/m/Y H:i', $timestampReporte) : '—';
                            $ticketReporte = (string)($registroReporte['id_ticket'] ?? '');
                        ?>

                            <tr
                                data-fecha="<?php echo htmlspecialchars($fechaIsoReporte, ENT_QUOTES, 'UTF-8'); ?>"
                                data-coordinaciones="<?php echo htmlspecialchars(json_encode($nombresCoordinacionesReporte, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <td><?php echo (int)($keyReporte + 1); ?></td>

                                <td>
                                    <span class="ticket-number">
                                        #<?php echo htmlspecialchars($ticketReporte, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if (!empty($nombresCoordinacionesReporte)): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($nombresCoordinacionesReporte as $nombreCoordBadge): ?>
                                                <span class="badge rounded-pill text-bg-primary coord-badge">
                                                    <?php echo htmlspecialchars($nombreCoordBadge, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-body-secondary">Sin asignar</span>
                                    <?php endif; ?>
                                </td>

                                <td data-order="<?php echo htmlspecialchars($fechaIsoReporte, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($fechaMostrarReporte, ENT_QUOTES, 'UTF-8'); ?>
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
.reporte-coordinacion {
    --rc-border: var(--bs-border-color);
    --rc-card: var(--bs-body-bg);
    --rc-muted: var(--bs-secondary-color);
}

.reporte-coordinacion .reporte-card {
    border: 1px solid var(--rc-border);
    border-radius: 14px;
    background: var(--rc-card);
    box-shadow: 0 3px 14px rgba(0,0,0,.045);
    overflow: hidden;
}

.reporte-coordinacion .card-header {
    background: transparent;
    padding: 1.15rem 1.25rem .65rem;
}

.reporte-coordinacion .card-body {
    padding: 1.25rem;
}

.reporte-coordinacion .reporte-icon {
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

.reporte-coordinacion .reporte-stat {
    min-height: 112px;
    padding: 1.15rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--rc-border);
    border-radius: 14px;
    background: var(--rc-card);
    box-shadow: 0 3px 14px rgba(0,0,0,.04);
}

.reporte-coordinacion .stat-icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    font-size: 1.25rem;
    flex: 0 0 auto;
}

.reporte-coordinacion .stat-blue .stat-icon {
    color: #0d6efd;
    background: rgba(13,110,253,.12);
}

.reporte-coordinacion .stat-green .stat-icon {
    color: #198754;
    background: rgba(25,135,84,.12);
}

.reporte-coordinacion .stat-orange .stat-icon {
    color: #fd7e14;
    background: rgba(253,126,20,.13);
}

.reporte-coordinacion .stat-label {
    color: var(--rc-muted);
    font-size: .88rem;
    margin-bottom: .2rem;
}

.reporte-coordinacion .stat-value {
    font-size: 1.75rem;
    line-height: 1.2;
    font-weight: 750;
    letter-spacing: -.5px;
}

.reporte-coordinacion .chart-container-coord {
    position: relative;
    width: 100%;
    max-width: 760px;
    height: 360px;
    margin: 0 auto;
}

.reporte-coordinacion .ticket-number {
    color: var(--bs-primary);
    font-weight: 700;
}

.reporte-coordinacion .coord-badge {
    font-weight: 500;
    padding: .45em .75em;
}

.reporte-coordinacion .table thead th {
    white-space: nowrap;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .35px;
    color: var(--rc-muted);
    border-bottom-width: 1px;
}

.reporte-coordinacion .table tbody td {
    font-size: .92rem;
}

.reporte-coordinacion .dataTables_wrapper .dataTables_filter input,
.reporte-coordinacion .dataTables_wrapper .dataTables_length select {
    border: 1px solid var(--rc-border);
    border-radius: 8px;
    padding: .35rem .55rem;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.reporte-coordinacion .dataTables_wrapper .dataTables_filter input {
    margin-left: .4rem;
}

.reporte-coordinacion .dataTables_wrapper .dataTables_info {
    color: var(--rc-muted);
    font-size: .85rem;
    padding-top: 1rem;
}

.reporte-coordinacion .dataTables_wrapper .dataTables_paginate {
    padding-top: .7rem;
}

.reporte-coordinacion .paginate_button .page-link {
    border-radius: 7px !important;
    margin: 0 2px;
}

[data-bs-theme="dark"] .reporte-coordinacion .reporte-card,
[data-bs-theme="dark"] .reporte-coordinacion .reporte-stat {
    box-shadow: 0 3px 14px rgba(0,0,0,.16);
}

[data-bs-theme="dark"] .reporte-coordinacion .table-striped>tbody>tr:nth-of-type(odd)>* {
    --bs-table-bg-type: rgba(255,255,255,.025);
}

@media (max-width: 767.98px) {
    .reporte-coordinacion .card-body {
        padding: 1rem;
    }

    .reporte-coordinacion .chart-container-coord {
        height: 300px;
    }

    .reporte-coordinacion .reporte-stat {
        min-height: 96px;
    }

    .reporte-coordinacion .content-header .breadcrumb {
        font-size: .8rem;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // La plantilla carga jQuery, DataTables, Chart.js y SweetAlert2 al final del documento.
    // Este código se ejecuta cuando esas librerías ya están disponibles.

    const $ = window.jQuery;

    if (!$ || !$.fn.DataTable) {
        console.error('No se pudo inicializar el reporte: jQuery o DataTables no están disponibles.');
        return;
    }

    const $tabla = $('#ticketsTableCoord');

    if (!$tabla.length) return;

    let fechaDesdeCoord = '';
    let fechaHastaCoord = '';
    let graficoCoordinacion = null;

    // FILTRO DE FECHAS
    // Compara YYYY-MM-DD directamente para evitar problemas de zona horaria.

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {

        if (!settings.nTable || settings.nTable.id !== 'ticketsTableCoord') {
            return true;
        }

        if (!fechaDesdeCoord && !fechaHastaCoord) {
            return true;
        }

        const fila = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;

        const fecha = fila ? (fila.getAttribute('data-fecha') || '').substring(0, 10) : '';

        if (!fecha) return false;

        if (fechaDesdeCoord && fecha < fechaDesdeCoord) {
            return false;
        }

        if (fechaHastaCoord && fecha > fechaHastaCoord) {
            return false;
        }

        return true;
    });

    // DATATABLE
    const tablaCoord = $tabla.DataTable({
        responsive: true,
        retrieve: true,
        order: [[1, 'desc']],
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

        columnDefs: [
            {
                targets: 0,
                searchable: false,
                orderable: false
            }
        ],

        drawCallback: function () {
            actualizarContadoresCoord();
            actualizarGraficoCoord();
        }
    });

    // OBTENER FILAS VISIBLES
    function obtenerFilasVisiblesCoord() {
        const filas = [];

        tablaCoord.rows({ search: 'applied' }).every(function () {

            const nodo = this.node();
            const celdas = this.data();

            let coordinaciones = [];

            try {
                coordinaciones = JSON.parse(nodo.dataset.coordinaciones || '[]');
            } catch (e) {}

            filas.push({
                numero: $('<div>').html(celdas[0]).text().trim(),
                ticket: $('<div>').html(celdas[1]).text().trim(),
                coordinaciones: coordinaciones,
                coordinacionTexto: $('<div>').html(celdas[2]).text().trim(),
                fecha: $('<div>').html(celdas[3]).text().trim(),
                fechaIso: nodo.dataset.fecha || ''
            });

        });

        return filas;
    }

    // OBTENER TODOS LOS REGISTROS
    function obtenerTodasFilasCoord() {

        const filas = [];

        tablaCoord.rows().every(function () {

            const celdas = this.data();

            filas.push({
                ticket: $('<div>').html(celdas[1]).text().trim(),
                coordinacionTexto: $('<div>').html(celdas[2]).text().trim(),
                fecha: $('<div>').html(celdas[3]).text().trim()
            });

        });

        return filas;
    }

    // CONTADORES
    function actualizarContadoresCoord() {
        const filas = obtenerFilasVisiblesCoord();
        const tickets = new Set();

        filas.forEach(fila => {
            if (fila.ticket) {
                tickets.add(fila.ticket);
            }
        });

        $('#totalRegistrosCoord').text(tablaCoord.rows().count());
        $('#visibleRegistrosCoord').text(filas.length);
        $('#uniqueTicketsCoord').text(tickets.size);
        $('#tableCountCoord').text(filas.length);
    }

    // GRÁFICO
    function actualizarGraficoCoord() {
        const conteo = {};

        obtenerFilasVisiblesCoord().forEach(fila => {
            if (fila.coordinaciones.length) {
                fila.coordinaciones.forEach(nombre => {
                    conteo[nombre] = (conteo[nombre] || 0) + 1;
                });
            } else {
                conteo['Sin asignar'] = (conteo['Sin asignar'] || 0) + 1;
            }
        });

        const etiquetas = Object.keys(conteo);
        const valores = Object.values(conteo);

        const canvas = document.getElementById('coordinacionChart');
        const empty = document.getElementById('coordinacionChartEmpty');

        if (!canvas || !empty || typeof Chart === 'undefined') {
            return;
        }

        if (graficoCoordinacion) {
            graficoCoordinacion.destroy();
            graficoCoordinacion = null;
        }

        if (!etiquetas.length) {
            canvas.classList.add('d-none');
            empty.classList.remove('d-none');
            return;
        }

        canvas.classList.remove('d-none');
        empty.classList.add('d-none');

        const paleta = [
            '#0d6efd', '#20c997', '#ffc107', '#fd7e14',
            '#6f42c1', '#dc3545', '#0dcaf0', '#198754',
            '#d63384', '#6c757d', '#6610f2', '#adb5bd'
        ];

        const temaOscuro = document.documentElement.getAttribute('data-bs-theme') === 'dark';

        graficoCoordinacion = new Chart(canvas, {
            type: 'doughnut',

            data: {
                labels: etiquetas,

                datasets: [{
                    data: valores,
                    backgroundColor: etiquetas.map((_, i) => paleta[i % paleta.length]),
                    borderColor: temaOscuro ? '#212529' : '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 7
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: {
                        position: 'bottom',

                        labels: {
                            color: temaOscuro ? '#dee2e6' : '#495057',
                            padding: 18,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {

                                const total = context.dataset.data.reduce((a, b) => a + b, 0);

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

    // ETIQUETA DE FILTROS
    function actualizarEtiquetaFiltrosCoord() {
        const $badge = $('#activeFiltersCoord');

        if (fechaDesdeCoord || fechaHastaCoord) {
            const desde = fechaDesdeCoord ? fechaDesdeCoord.split('-').reverse().join('/') : 'Inicio';
            const hasta = fechaHastaCoord ? fechaHastaCoord.split('-').reverse().join('/') : 'Fin';

            $badge.removeClass('text-bg-secondary').addClass('text-bg-primary').text('Fecha: ' + desde + ' - ' + hasta);
        } else {
            $badge.removeClass('text-bg-primary').addClass('text-bg-secondary').text('Sin filtros activos');
        }
    }

    // APLICAR FILTRO
    function aplicarFiltroFechaCoord() {
        // Si las fechas están invertidas, se intercambian automáticamente.
        if (fechaDesdeCoord && fechaHastaCoord && fechaDesdeCoord > fechaHastaCoord) {
            const temporal = fechaDesdeCoord;

            fechaDesdeCoord = fechaHastaCoord;
            fechaHastaCoord = temporal;

            $('#fechaDesdeCoord').val(fechaDesdeCoord);
            $('#fechaHastaCoord').val(fechaHastaCoord);
        }

        tablaCoord.draw();
        actualizarEtiquetaFiltrosCoord();
    }

    // CAMBIOS EN LAS FECHAS
    $('#fechaDesdeCoord, #fechaHastaCoord').on('change input', function () {
        fechaDesdeCoord = $('#fechaDesdeCoord').val() || '';
        fechaHastaCoord = $('#fechaHastaCoord').val() || '';
        aplicarFiltroFechaCoord();
    });

    // LIMPIAR FILTROS
    $('#clearFiltersCoord').on('click', function () {
        $('#fechaDesdeCoord, #fechaHastaCoord').val('');
        fechaDesdeCoord = '';
        fechaHastaCoord = '';
        aplicarFiltroFechaCoord();
    });

    // EXPORTAR EXCEL
    function exportarExcelCoord(soloFiltrados) {
        const filas = soloFiltrados ? obtenerFilasVisiblesCoord() : obtenerTodasFilasCoord();

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

        const datos = [ ['#', 'Ticket', 'Coordinación', 'Fecha'] ];

        filas.forEach((fila, index) => {
            datos.push([
                index + 1,
                fila.ticket,
                fila.coordinacionTexto,
                fila.fecha
            ]);
        });

        const libro = XLSX.utils.book_new();
        const hoja = XLSX.utils.aoa_to_sheet(datos);

        hoja['!cols'] = [ { wch: 8 }, { wch: 15 }, { wch: 42 }, { wch: 22 } ];

        XLSX.utils.book_append_sheet(
            libro,
            hoja,
            'Tickets por Coordinacion'
        );

        const ahora = new Date();

        const marcaTiempo =
            ahora.getFullYear() +
            String(ahora.getMonth() + 1).padStart(2, '0') +
            String(ahora.getDate()).padStart(2, '0') +
            '_' +
            String(ahora.getHours()).padStart(2, '0') +
            String(ahora.getMinutes()).padStart(2, '0');
            
        XLSX.writeFile(libro, (soloFiltrados ? 'reporte_coordinacion_' : 'todos_coordinacion_') + marcaTiempo + '.xlsx');

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

    $('#exportExcelCoord').on('click', function () {
        exportarExcelCoord(true);
    });

    $('#exportAllExcelCoord').on('click', function () {
        exportarExcelCoord(false);
    });

    // INICIALIZACIÓN
    actualizarContadoresCoord();
    actualizarGraficoCoord();
    actualizarEtiquetaFiltrosCoord();

});
</script>
