
<?php

$authReportes = ControladorTicket::authTicket();

if (!ControladorReportes::puedeVerReportes($authReportes)) {
    echo '<script>window.location.replace("inicio");</script>';
    return;
}

/*=============================================
CATÁLOGOS
=============================================*/

$entidades = ControladorEntidad::ctrMostrarEntidad(null, null) ?: [];
$medios = ControladorMedio::ctrMostrarMedio(null, null) ?: [];
$prioridades = ControladorPrioridad::ctrMostrarPrioridad(null, null) ?: [];
$statuses = ControladorStatus::ctrMostrarStatus(null, null) ?: [];
$usuarios = ControladorUser::ctrMostrarUser(null, null) ?: [];

$mapaEntidades = [];
foreach ($entidades as $e) {
    $mapaEntidades[(int)$e["id"]] = $e["entidad"];
}

$mapaMedios = [];
foreach ($medios as $m) {
    $mapaMedios[(int)$m["id"]] = $m["medio"];
}

$mapaPrioridades = [];
foreach ($prioridades as $p) {
    $mapaPrioridades[(int)$p["id"]] = $p;
}

$mapaStatuses = [];
foreach ($statuses as $s) {
    $mapaStatuses[(int)$s["id"]] = $s;
}

$mapaUsuarios = [];
foreach ($usuarios as $u) {
    $mapaUsuarios[(int)$u["id"]] = $u["name"] ?: $u["user"];
}

/*=============================================
HISTORIAL DE ESTADOS
El modelo devuelve los registros por ID DESC.
El primero encontrado es el último estado.
=============================================*/

$historial = ControladorTstatus::ctrMostrarTstatus(null, null) ?: [];

$ultimoStatus = [];

foreach ($historial as $h) {
    $idTicket = (int)$h["id_ticket"];

    if (!isset($ultimoStatus[$idTicket])) {
        $ultimoStatus[$idTicket] = (int)$h["id_status"];
    }
}

/*=============================================
FUNCIÓN PARA RESOLVER IDS JSON
=============================================*/

$obtenerNombres = function ($valor, $mapa, $campo = null) {

    $ids = json_decode($valor ?? "[]", true);

    if (!is_array($ids)) {
        $ids = [];
    }

    $nombres = [];

    foreach ($ids as $id) {

        $registro = $mapa[(int)$id] ?? null;

        if (is_array($registro)) {
            $nombre = $registro[$campo] ?? "";
        } else {
            $nombre = $registro;
        }

        if ($nombre !== null && $nombre !== "") {
            $nombres[] = $nombre;
        }
    }

    return $nombres ? implode(", ", $nombres) : "No especificado";
};

/*=============================================
OBTENER SOLICITUDES
=============================================*/

$tickets = ControladorTicket::ctrMostrarTicket(null, null) ?: [];

$filasReporte = [];

foreach ($tickets as $ticket) {

    // Mantener el mismo criterio del reporte original.
    if ((int)($ticket["estado"] ?? 0) !== 1) {
        continue;
    }

    $id = (int)$ticket["id"];

    $idStatus = $ultimoStatus[$id] ?? 0;
    $status = $mapaStatuses[$idStatus] ?? null;

    $entidad = $obtenerNombres(
        $ticket["id_entidad"] ?? "[]",
        $mapaEntidades
    );

    $medio = $obtenerNombres(
        $ticket["id_medio"] ?? "[]",
        $mapaMedios
    );

    $prioridad = $obtenerNombres(
        $ticket["id_prioridad"] ?? "[]",
        $mapaPrioridades,
        "prioridad"
    );

    $filasReporte[] = [
        "id" => $id,
        "entidad" => $entidad,
        "solicitante" => $ticket["solicitante"] ?? "",
        "medio" => $medio,
        "prioridad" => $prioridad,
        "status" => $status["status"] ?? "Sin estado",
        "color" => $status["color"] ?? "#6c757d",
        "creador" => $mapaUsuarios[(int)($ticket["id_user"] ?? 0)] ?? "No especificado",
        "fecha" => $ticket["fecha"] ?? "",
        "check" => (int)($ticket["check"] ?? 0)
    ];
}

$totalSolicitudes = count($filasReporte);
$totalAbiertas = count(array_filter($filasReporte, fn($t) => $t["check"] === 0));
$totalFinalizadas = count(array_filter($filasReporte, fn($t) => $t["check"] === 1));

/* Opciones únicas de filtros */

$opcionesEntidad = array_unique(array_column($filasReporte, "entidad"));
$opcionesMedio = array_unique(array_column($filasReporte, "medio"));
$opcionesPrioridad = array_unique(array_column($filasReporte, "prioridad"));
$opcionesStatus = array_unique(array_column($filasReporte, "status"));

?>

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">
                <i class="fas fa-chart-pie text-primary"></i>
                Reporte de Solicitudes
            </h3>
            <p class="text-muted mb-0">
                Consulta y analiza las solicitudes registradas en AIT.
            </p>
        </div>
    </div>

    <!-- INDICADORES -->

    <div class="row">

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted">Total de solicitudes</div>
                    <h2 class="fw-bold mb-0">
                        <?= number_format($totalSolicitudes) ?>
                    </h2>
                    <i class="fas fa-ticket-alt text-primary"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted">Solicitudes abiertas</div>
                    <h2 class="fw-bold text-warning mb-0">
                        <?= number_format($totalAbiertas) ?>
                    </h2>
                    <i class="fas fa-folder-open text-warning"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted">Solicitudes finalizadas</div>
                    <h2 class="fw-bold text-success mb-0">
                        <?= number_format($totalFinalizadas) ?>
                    </h2>
                    <i class="fas fa-check-circle text-success"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- FILTROS -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-white">
            <strong>
                <i class="fas fa-filter text-primary"></i>
                Filtros de búsqueda
            </strong>
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-3 mb-3">
                    <label>Entidad</label>
                    <select id="filtroEntidad" class="form-control filtro">
                        <option value="">Todas</option>
                        <?php foreach ($opcionesEntidad as $op): ?>
                            <option value="<?= htmlspecialchars($op, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($op) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 mb-3">
                    <label>Medio</label>
                    <select id="filtroMedio" class="form-control filtro">
                        <option value="">Todos</option>
                        <?php foreach ($opcionesMedio as $op): ?>
                            <option value="<?= htmlspecialchars($op, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($op) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 mb-3">
                    <label>Prioridad</label>
                    <select id="filtroPrioridad" class="form-control filtro">
                        <option value="">Todas</option>
                        <?php foreach ($opcionesPrioridad as $op): ?>
                            <option value="<?= htmlspecialchars($op, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($op) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 mb-3">
                    <label>Estado</label>
                    <select id="filtroStatus" class="form-control filtro">
                        <option value="">Todos</option>
                        <?php foreach ($opcionesStatus as $op): ?>
                            <option value="<?= htmlspecialchars($op, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($op) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label>Buscar</label>
                    <input type="text" id="buscarSolicitud"
                           class="form-control"
                           placeholder="Ticket, solicitante, usuario...">
                </div>

                <div class="col-md-3 mb-3">
                    <label>Fecha desde</label>
                    <input type="date" id="fechaDesde" class="form-control filtro">
                </div>

                <div class="col-md-3 mb-3">
                    <label>Fecha hasta</label>
                    <input type="date" id="fechaHasta" class="form-control filtro">
                </div>

                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <button type="button" id="limpiarFiltros"
                            class="btn btn-outline-secondary w-100">
                        <i class="fas fa-eraser"></i> Limpiar filtros
                    </button>
                </div>

                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <button type="button" id="exportarExcel"
                            class="btn btn-success w-100">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                </div>

            </div>

        </div>
    </div>

    <!-- TABLA -->

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white">
            <strong>
                <i class="fas fa-table text-primary"></i>
                Listado de solicitudes
            </strong>

            <span class="badge badge-primary float-right"
                  id="contadorSolicitudes">
                <?= $totalSolicitudes ?> registros
            </span>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover table-striped"
                       id="tablaSolicitudes">

                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Entidad</th>
                            <th>Solicitante</th>
                            <th>Medio</th>
                            <th>Prioridad</th>
                            <th>Status</th>
                            <th>Creado por</th>
                            <th>Fecha</th>
                            <th>Situación</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($filasReporte as $fila): ?>

                            <tr>
                                <td>
                                    <a href="index.php?ruta=ticket&id=<?= $fila["id"] ?>">
                                        #<?= $fila["id"] ?>
                                    </a>
                                </td>

                                <td><?= htmlspecialchars($fila["entidad"]) ?></td>
                                <td><?= htmlspecialchars($fila["solicitante"]) ?></td>
                                <td><?= htmlspecialchars($fila["medio"]) ?></td>
                                <td><?= htmlspecialchars($fila["prioridad"]) ?></td>

                                <td>
                                    <span class="badge"
                                          style="background-color: <?= htmlspecialchars($fila["color"]) ?>; color:white">
                                        <?= htmlspecialchars($fila["status"]) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($fila["creador"]) ?></td>
                                <td><?= htmlspecialchars($fila["fecha"]) ?></td>

                                <td>
                                    <?php if ($fila["check"] === 1): ?>
                                        <span class="badge badge-success">Finalizada</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Abierta</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

                <div id="sinResultados" class="alert alert-info text-center d-none">
                    No se encontraron solicitudes con los filtros seleccionados.
                </div>

            </div>

        </div>
    </div>

</div>

<style>
    #tablaSolicitudes th {
        white-space: nowrap;
    }

    #tablaSolicitudes td {
        vertical-align: middle;
    }

    .card {
        border-radius: 10px;
    }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const tabla = document.querySelectorAll("#tablaSolicitudes tbody tr");
    const filtros = document.querySelectorAll(".filtro");

    function filtrarSolicitudes() {

        const entidad = document.getElementById("filtroEntidad").value.toLowerCase();
        const medio = document.getElementById("filtroMedio").value.toLowerCase();
        const prioridad = document.getElementById("filtroPrioridad").value.toLowerCase();
        const status = document.getElementById("filtroStatus").value.toLowerCase();
        const buscar = document.getElementById("buscarSolicitud").value.toLowerCase();

        const desde = document.getElementById("fechaDesde").value;
        const hasta = document.getElementById("fechaHasta").value;

        let visibles = 0;

        tabla.forEach(fila => {

            const celdas = fila.cells;

            const fecha = celdas[7].textContent.trim().substring(0, 10);

            const coincide =
                (!entidad || celdas[1].textContent.toLowerCase().includes(entidad)) &&
                (!medio || celdas[3].textContent.toLowerCase().includes(medio)) &&
                (!prioridad || celdas[4].textContent.toLowerCase().includes(prioridad)) &&
                (!status || celdas[5].textContent.toLowerCase().includes(status)) &&
                (!buscar || fila.textContent.toLowerCase().includes(buscar)) &&
                (!desde || fecha >= desde) &&
                (!hasta || fecha <= hasta);

            fila.style.display = coincide ? "" : "none";

            if (coincide) visibles++;
        });

        document.getElementById("contadorSolicitudes").textContent =
            visibles + " registros";

        document.getElementById("sinResultados").classList.toggle(
            "d-none",
            visibles !== 0
        );
    }

    filtros.forEach(filtro => {
        filtro.addEventListener("change", filtrarSolicitudes);
    });

    document.getElementById("buscarSolicitud")
        .addEventListener("input", filtrarSolicitudes);

    document.getElementById("limpiarFiltros")
        .addEventListener("click", function () {

            filtros.forEach(filtro => filtro.value = "");

            document.getElementById("buscarSolicitud").value = "";

            filtrarSolicitudes();
        });

    document.getElementById("exportarExcel")
        .addEventListener("click", function () {

            if (typeof XLSX === "undefined") {
                alert("No se pudo cargar el exportador de Excel.");
                return;
            }

            const filas = [];

            filas.push([
                "Ticket",
                "Entidad",
                "Solicitante",
                "Medio",
                "Prioridad",
                "Status",
                "Creado por",
                "Fecha",
                "Situación"
            ]);

            tabla.forEach(fila => {

                if (fila.style.display === "none") return;

                filas.push(
                    Array.from(fila.cells).map(celda =>
                        celda.textContent.trim()
                    )
                );
            });

            const hoja = XLSX.utils.aoa_to_sheet(filas);
            const libro = XLSX.utils.book_new();

            XLSX.utils.book_append_sheet(libro, hoja, "Solicitudes");

            XLSX.writeFile(libro, "Reporte_Solicitudes.xlsx");
        });

    filtrarSolicitudes();

});
</script>
