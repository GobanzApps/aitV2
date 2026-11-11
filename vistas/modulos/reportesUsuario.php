
<?php
/*=============================================
REPORTE DE TICKETS POR USUARIO
Adaptado de AIT V1 a AITV2.
=============================================*/

$authReporteUsuario = ControladorTicket::authTicket();

$puedeVerReporteUsuario = $authReporteUsuario && (
    ControladorTicket::esAdminTicket($authReporteUsuario)
    || ControladorTicket::esCoordinadorTicket($authReporteUsuario)
    || in_array(11, $authReporteUsuario['coordsIds'] ?? [], true)
    || in_array(9, $authReporteUsuario['coordsIds'] ?? [], true)
    || (($authReporteUsuario['userData']['user'] ?? '') === 'agamardo')
);

if (!$puedeVerReporteUsuario) {
    echo '<script>window.location.replace("index.php?ruta=inicio");</script>';
    return;
}

$asignacionesUsuariosReporte = ControladorTuser::ctrMostrarTuser(null, null) ?: [];

$registrosUsuarioReporte = [];

foreach ($asignacionesUsuariosReporte as $asignacionReporte) {

    $idsUsuariosReporte = json_decode($asignacionReporte['id_user'] ?? '[]', true);

    // Compatibilidad con registros antiguos que pudieran guardar un ID individual.
    if (!is_array($idsUsuariosReporte)) {
        $idsUsuariosReporte = is_numeric($asignacionReporte['id_user'] ?? null)
            ? [(int)$asignacionReporte['id_user']]
            : [];
    }

    $fechaOriginalReporte = $asignacionReporte['modificacion'] ?? '';
    $timestampReporte = !empty($fechaOriginalReporte)
        ? strtotime($fechaOriginalReporte)
        : false;

    $fechaIsoReporte = $timestampReporte !== false
        ? date('Y-m-d H:i:s', $timestampReporte)
        : '';

    $fechaMostrarReporte = $timestampReporte !== false
        ? date('d/m/Y H:i', $timestampReporte)
        : '—';

    foreach ($idsUsuariosReporte as $idUsuarioReporte) {

        $idUsuarioReporte = (int)$idUsuarioReporte;

        if ($idUsuarioReporte <= 0) {
            continue;
        }

        $datosUsuarioReporte = ControladorUser::ctrMostrarUser('id', $idUsuarioReporte);

        $nombreUsuarioReporte = $datosUsuarioReporte
            ? ($datosUsuarioReporte['name'] ?? $datosUsuarioReporte['user'] ?? 'Usuario #' . $idUsuarioReporte)
            : 'Usuario #' . $idUsuarioReporte;

        $registrosUsuarioReporte[] = [
            'ticket' => $asignacionReporte['id_ticket'] ?? '',
            'usuario' => $nombreUsuarioReporte,
            'fecha_iso' => $fechaIsoReporte,
            'fecha' => $fechaMostrarReporte
        ];
    }
}
?>

<div class="content-wrapper reporte-usuario">

    <!-- ENCABEZADO -->
    <section class="content-header px-3 pt-3 pb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h3 mb-1 fw-bold">Tickets por Usuario</h1>
                <p class="text-body-secondary mb-0">
                    Consulta y analiza los tickets asignados a cada usuario.
                </p>
            </div>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="inicio">Inicio</a></li>
                    <li class="breadcrumb-item active">Reportes</li>
                    <li class="breadcrumb-item active">Usuario</li>
                </ol>
            </nav>
        </div>
    </section>

    <section class="content px-3 pb-4">

        <!-- FILTROS -->
        <div class="card reporte-card mb-4">
            <div class="card-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <span class="reporte-icon">
                        <i class="fas fa-filter"></i>
                    </span>

                    <div>
                        <h2 class="h5 mb-0 fw-semibold">Filtros del reporte</h2>
                        <small class="text-body-secondary">
                            Selecciona un rango de fechas para actualizar los resultados.
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
                                <label for="fechaDesdeUsuario" class="form-label small text-body-secondary mb-1">
                                    Desde
                                </label>
                                <input type="date" id="fechaDesdeUsuario" class="form-control">
                            </div>

                            <div class="col-6">
                                <label for="fechaHastaUsuario" class="form-label small text-body-secondary mb-1">
                                    Hasta
                                </label>
                                <input type="date" id="fechaHastaUsuario" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7 d-flex flex-wrap gap-2 justify-content-lg-end">

                        <button type="button" id="clearFiltersUsuario" class="btn btn-outline-secondary">
                            <i class="fas fa-eraser me-1"></i>
                            Limpiar filtros
                        </button>

                        <button type="button" id="exportExcelUsuario" class="btn btn-success">
                            <i class="fas fa-file-excel me-1"></i>
                            Exportar filtrados
                        </button>

                        <button type="button" id="exportAllExcelUsuario" class="btn btn-outline-success">
                            <i class="fas fa-download me-1"></i>
                            Exportar todos
                        </button>

                    </div>
                </div>

                <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                    <span id="activeFiltersUsuario" class="badge rounded-pill text-bg-secondary">
                        Sin filtros activos
                    </span>

                    <small class="text-body-secondary">
                        Los gráficos y contadores se actualizan automáticamente.
                    </small>
                </div>
            </div>
        </div>

        <!-- ESTADÍSTICAS -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-md-4">
                <div class="reporte-stat stat-blue">
                    <div class="stat-icon">
                        <i class="fas fa-list-alt"></i>
                    </div>

                    <div>
                        <div class="stat-label">Total registros</div>
                        <div class="stat-value" id="totalRegistrosUsuario">0</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="reporte-stat stat-green">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>

                    <div>
                        <div class="stat-label">Registros visibles</div>
                        <div class="stat-value" id="visibleRegistrosUsuario">0</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="reporte-stat stat-orange">
                    <div class="stat-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>

                    <div>
                        <div class="stat-label">Tickets únicos</div>
                        <div class="stat-value" id="uniqueTicketsUsuario">0</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- GRÁFICO -->
        <div class="card reporte-card mb-4">

            <div class="card-header border-0">
                <div class="d-flex align-items-center gap-2">
                    <span class="reporte-icon">
                        <i class="fas fa-chart-pie"></i>
                    </span>

                    <div>
                        <h2 class="h5 mb-0 fw-semibold">Distribución por Usuario</h2>
                        <small class="text-body-secondary">
                            Cantidad de tickets asignados a cada usuario.
                        </small>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="chart-container-usuario">
                    <canvas id="usuarioChart"></canvas>
                </div>

                <div id="usuarioChartEmpty" class="text-center text-body-secondary py-3 d-none">
                    No hay datos para mostrar con los filtros actuales.
                </div>
            </div>

        </div>

        <!-- TABLA -->
        <div class="card reporte-card">

            <div class="card-header border-0">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

                    <div class="d-flex align-items-center gap-2">
                        <span class="reporte-icon">
                            <i class="fas fa-table"></i>
                        </span>

                        <div>
                            <h2 class="h5 mb-0 fw-semibold">
                                Listado de tickets por usuario
                            </h2>
                            <small class="text-body-secondary">
                                Detalle de los usuarios asignados a cada ticket.
                            </small>
                        </div>
                    </div>

                    <span class="small text-body-secondary">
                        Registros: <strong id="tableCountUsuario">0</strong>
                    </span>

                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">

                    <table id="ticketsTableUsuario" class="table table-hover table-striped align-middle w-100">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ticket</th>
                                <th>Usuario</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($registrosUsuarioReporte as $keyReporte => $registroReporte): ?>

                                <tr data-fecha="<?php echo htmlspecialchars($registroReporte['fecha_iso'], ENT_QUOTES, 'UTF-8'); ?>">

                                    <td><?php echo $keyReporte + 1; ?></td>

                                    <td>
                                        <span class="ticket-number">
                                            #<?php echo htmlspecialchars((string)$registroReporte['ticket'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="usuario-avatar">
                                                <i class="fas fa-user"></i>
                                            </span>

                                            <span class="fw-medium">
                                                <?php echo htmlspecialchars($registroReporte['usuario'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
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
.reporte-usuario {
    --ru-border: var(--bs-border-color);
    --ru-card: var(--bs-body-bg);
    --ru-muted: var(--bs-secondary-color);
}

.reporte-usuario .reporte-card {
    border: 1px solid var(--ru-border);
    border-radius: 14px;
    background: var(--ru-card);
    box-shadow: 0 3px 14px rgba(0,0,0,.045);
    overflow: hidden;
}

.reporte-usuario .card-header {
    background: transparent;
    padding: 1.15rem 1.25rem .65rem;
}

.reporte-usuario .card-body {
    padding: 1.25rem;
}

.reporte-usuario .reporte-icon {
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

.reporte-usuario .reporte-stat {
    min-height: 112px;
    padding: 1.15rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--ru-border);
    border-radius: 14px;
    background: var(--ru-card);
    box-shadow: 0 3px 14px rgba(0,0,0,.04);
}

.reporte-usuario .stat-icon {
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    font-size: 1.25rem;
    flex: 0 0 auto;
}

.reporte-usuario .stat-blue .stat-icon {
    color: #0d6efd;
    background: rgba(13,110,253,.12);
}

.reporte-usuario .stat-green .stat-icon {
    color: #198754;
    background: rgba(25,135,84,.12);
}

.reporte-usuario .stat-orange .stat-icon {
    color: #fd7e14;
    background: rgba(253,126,20,.13);
}

.reporte-usuario .stat-label {
    color: var(--ru-muted);
    font-size: .88rem;
    margin-bottom: .2rem;
}

.reporte-usuario .stat-value {
    font-size: 1.75rem;
    line-height: 1.2;
    font-weight: 750;
    letter-spacing: -.5px;
}

.reporte-usuario .chart-container-usuario {
    position: relative;
    width: 100%;
    max-width: 760px;
    height: 360px;
    margin: 0 auto;
}

.reporte-usuario .ticket-number {
    color: var(--bs-primary);
    font-weight: 700;
}

.reporte-usuario .usuario-avatar {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: #0d6efd;
    background: rgba(13,110,253,.11);
    flex: 0 0 auto;
}

.reporte-usuario .table thead th {
    white-space: nowrap;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .35px;
    color: var(--ru-muted);
    border-bottom-width: 1px;
}

.reporte-usuario .table tbody td {
    font-size: .92rem;
}

.reporte-usuario .dataTables_wrapper .dataTables_filter input,
.reporte-usuario .dataTables_wrapper .dataTables_length select {
    border: 1px solid var(--ru-border);
    border-radius: 8px;
    padding: .35rem .55rem;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.reporte-usuario .dataTables_wrapper .dataTables_filter input {
    margin-left: .4rem;
}

.reporte-usuario .dataTables_wrapper .dataTables_info {
    color: var(--ru-muted);
    font-size: .85rem;
    padding-top: 1rem;
}

.reporte-usuario .dataTables_wrapper .dataTables_paginate {
    padding-top: .7rem;
}

.reporte-usuario .paginate_button .page-link {
    border-radius: 7px !important;
    margin: 0 2px;
}

[data-bs-theme="dark"] .reporte-usuario .reporte-card,
[data-bs-theme="dark"] .reporte-usuario .reporte-stat {
    box-shadow: 0 3px 14px rgba(0,0,0,.16);
}

[data-bs-theme="dark"] .reporte-usuario .table-striped>tbody>tr:nth-of-type(odd)>* {
    --bs-table-bg-type: rgba(255,255,255,.025);
}

@media (max-width: 767.98px) {
    .reporte-usuario .card-body {
        padding: 1rem;
    }

    .reporte-usuario .chart-container-usuario {
        height: 300px;
    }

    .reporte-usuario .reporte-stat {
        min-height: 96px;
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

    const $tabla = $('#ticketsTableUsuario');

    if (!$tabla.length) return;

    let fechaDesdeUsuario = '';
    let fechaHastaUsuario = '';
    let graficoUsuario = null;

    // FILTRO DE FECHAS
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {

        if (!settings.nTable || settings.nTable.id !== 'ticketsTableUsuario') {
            return true;
        }

        if (!fechaDesdeUsuario && !fechaHastaUsuario) {
            return true;
        }

        const fila = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;

        const fecha = fila
            ? (fila.getAttribute('data-fecha') || '').substring(0, 10)
            : '';

        if (!fecha) return false;

        if (fechaDesdeUsuario && fecha < fechaDesdeUsuario) {
            return false;
        }

        if (fechaHastaUsuario && fecha > fechaHastaUsuario) {
            return false;
        }

        return true;
    });

    // DATATABLE
    const tablaUsuario = $tabla.DataTable({

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
            actualizarContadoresUsuario();
            actualizarGraficoUsuario();
        }
    });

    // OBTENER REGISTROS VISIBLES
    function obtenerFilasVisiblesUsuario() {

        const filas = [];

        tablaUsuario.rows({ search: 'applied' }).every(function () {

            const nodo = this.node();
            const celdas = this.data();

            filas.push({
                ticket: $('<div>').html(celdas[1]).text().trim(),
                usuario: $('<div>').html(celdas[2]).text().trim(),
                fecha: $('<div>').html(celdas[3]).text().trim()
            });

        });

        return filas;
    }

    // OBTENER TODOS LOS REGISTROS
    function obtenerTodasFilasUsuario() {

        const filas = [];

        tablaUsuario.rows().every(function () {

            const celdas = this.data();

            filas.push({
                ticket: $('<div>').html(celdas[1]).text().trim(),
                usuario: $('<div>').html(celdas[2]).text().trim(),
                fecha: $('<div>').html(celdas[3]).text().trim()
            });

        });

        return filas;
    }

    // CONTADORES
    function actualizarContadoresUsuario() {

        const filas = obtenerFilasVisiblesUsuario();
        const tickets = new Set();

        filas.forEach(fila => {
            if (fila.ticket) {
                tickets.add(fila.ticket);
            }
        });

        $('#totalRegistrosUsuario').text(tablaUsuario.rows().count());
        $('#visibleRegistrosUsuario').text(filas.length);
        $('#uniqueTicketsUsuario').text(tickets.size);
        $('#tableCountUsuario').text(filas.length);
    }

    // GRÁFICO
    function actualizarGraficoUsuario() {

        const conteo = {};

        obtenerFilasVisiblesUsuario().forEach(fila => {

            const usuario = fila.usuario || 'Sin asignar';

            conteo[usuario] = (conteo[usuario] || 0) + 1;

        });

        const etiquetas = Object.keys(conteo);
        const valores = Object.values(conteo);

        const canvas = document.getElementById('usuarioChart');
        const empty = document.getElementById('usuarioChartEmpty');

        if (!canvas || !empty || typeof Chart === 'undefined') {
            return;
        }

        if (graficoUsuario) {
            graficoUsuario.destroy();
            graficoUsuario = null;
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
            '#d63384', '#6c757d', '#6610f2', '#adb5bd',
            '#e83e8c', '#17a2b8', '#795548', '#607d8b'
        ];

        const temaOscuro =
            document.documentElement.getAttribute('data-bs-theme') === 'dark';

        graficoUsuario = new Chart(canvas, {

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
    function actualizarEtiquetaFiltrosUsuario() {

        const $badge = $('#activeFiltersUsuario');

        if (fechaDesdeUsuario || fechaHastaUsuario) {

            const desde = fechaDesdeUsuario
                ? fechaDesdeUsuario.split('-').reverse().join('/')
                : 'Inicio';

            const hasta = fechaHastaUsuario
                ? fechaHastaUsuario.split('-').reverse().join('/')
                : 'Fin';

            $badge
                .removeClass('text-bg-secondary')
                .addClass('text-bg-primary')
                .text('Fecha: ' + desde + ' - ' + hasta);

        } else {

            $badge
                .removeClass('text-bg-primary')
                .addClass('text-bg-secondary')
                .text('Sin filtros activos');

        }
    }

    // APLICAR FILTRO
    function aplicarFiltroFechaUsuario() {

        if (
            fechaDesdeUsuario &&
            fechaHastaUsuario &&
            fechaDesdeUsuario > fechaHastaUsuario
        ) {

            const temporal = fechaDesdeUsuario;

            fechaDesdeUsuario = fechaHastaUsuario;
            fechaHastaUsuario = temporal;

            $('#fechaDesdeUsuario').val(fechaDesdeUsuario);
            $('#fechaHastaUsuario').val(fechaHastaUsuario);
        }

        tablaUsuario.draw();
        actualizarEtiquetaFiltrosUsuario();
    }

    // CAMBIOS DE FECHA
    $('#fechaDesdeUsuario, #fechaHastaUsuario').on('change input', function () {

        fechaDesdeUsuario = $('#fechaDesdeUsuario').val() || '';
        fechaHastaUsuario = $('#fechaHastaUsuario').val() || '';

        aplicarFiltroFechaUsuario();
    });

    // LIMPIAR FILTROS
    $('#clearFiltersUsuario').on('click', function () {

        $('#fechaDesdeUsuario, #fechaHastaUsuario').val('');

        fechaDesdeUsuario = '';
        fechaHastaUsuario = '';

        aplicarFiltroFechaUsuario();
    });

    // EXPORTAR EXCEL
    function exportarExcelUsuario(soloFiltrados) {

        const filas = soloFiltrados
            ? obtenerFilasVisiblesUsuario()
            : obtenerTodasFilasUsuario();

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
            ['#', 'Ticket', 'Usuario', 'Fecha']
        ];

        filas.forEach((fila, index) => {

            datos.push([
                index + 1,
                fila.ticket,
                fila.usuario,
                fila.fecha
            ]);

        });

        const libro = XLSX.utils.book_new();
        const hoja = XLSX.utils.aoa_to_sheet(datos);

        hoja['!cols'] = [
            { wch: 8 },
            { wch: 15 },
            { wch: 35 },
            { wch: 22 }
        ];

        XLSX.utils.book_append_sheet(
            libro,
            hoja,
            'Tickets por Usuario'
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
            (soloFiltrados ? 'reporte_usuario_' : 'todos_usuario_') +
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

    $('#exportExcelUsuario').on('click', function () {
        exportarExcelUsuario(true);
    });

    $('#exportAllExcelUsuario').on('click', function () {
        exportarExcelUsuario(false);
    });

    // INICIALIZACIÓN
    actualizarContadoresUsuario();
    actualizarGraficoUsuario();
    actualizarEtiquetaFiltrosUsuario();

});
</script>
