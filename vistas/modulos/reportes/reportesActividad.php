
<?php
/*=============================================
REPORTE DE ACTIVIDADES
Adaptado de AIT V1 a AITV2.
=============================================*/

$authReporteActividad = ControladorTicket::authTicket();

$puedeVerReporteActividad = $authReporteActividad && (
    ControladorTicket::esAdminTicket($authReporteActividad)
    || ControladorTicket::esCoordinadorTicket($authReporteActividad)
    || in_array(11, $authReporteActividad['coordsIds'] ?? [], true)
    || in_array(9, $authReporteActividad['coordsIds'] ?? [], true)
    || (($authReporteActividad['userData']['user'] ?? '') === 'agamardo')
);

if (!$puedeVerReporteActividad) {
    echo '<script>window.location.replace("index.php?ruta=inicio");</script>';
    return;
}

/*=============================================
OBTENER DATOS
=============================================*/

$actividadesReporte = ControladorActividad::ctrMostrarActividad(null, null) ?: [];
$tagsReporte = ControladorTag::ctrMostrarTag(null, null) ?: [];
$tiposTagReporte = ControladorTagTipo::ctrMostrarTagTipo(null, null) ?: [];
$entidadesReporte = ControladorEntidad::ctrMostrarEntidad(null, null) ?: [];
$coordinacionesReporte = ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: [];
$usuariosReporte = ControladorUser::ctrMostrarUser(null, null) ?: [];

/*=============================================
MAPAS DE INFORMACIÓN
=============================================*/

$mapaTagsActividad = [];
foreach ($tagsReporte as $tag) {
    $mapaTagsActividad[(int)$tag['id']] = $tag;
}

$mapaTiposActividad = [];
foreach ($tiposTagReporte as $tipo) {
    $mapaTiposActividad[(int)$tipo['id']] = $tipo;
}

$mapaEntidadesActividad = [];
foreach ($entidadesReporte as $entidad) {
    $mapaEntidadesActividad[(int)$entidad['id']] = $entidad;
}

$mapaCoordinacionesActividad = [];
foreach ($coordinacionesReporte as $coordinacion) {
    $mapaCoordinacionesActividad[(int)$coordinacion['id']] = $coordinacion;
}

$mapaUsuariosActividad = [];
foreach ($usuariosReporte as $usuario) {
    $mapaUsuariosActividad[(int)$usuario['id']] = $usuario;
}

/*=============================================
HELPER PARA DECODIFICAR IDs JSON
=============================================*/

$decodificarIdsActividad = function ($valor) {

    if (is_array($valor)) {
        return array_values(array_unique(array_filter(
            array_map('intval', $valor),
            function ($id) {
                return $id > 0;
            }
        )));
    }

    $decodificado = json_decode((string)$valor, true);

    if (is_array($decodificado)) {
        return array_values(array_unique(array_filter(
            array_map('intval', $decodificado),
            function ($id) {
                return $id > 0;
            }
        )));
    }

    if (is_numeric($valor) && (int)$valor > 0) {
        return [(int)$valor];
    }

    return [];
};

/*=============================================
CONSTRUIR REGISTROS
=============================================*/

$registrosActividadReporte = [];

foreach ($actividadesReporte as $actividadReporte) {

    $idsEntidades = $decodificarIdsActividad($actividadReporte['id_entidad'] ?? '');
    $idsTags = $decodificarIdsActividad($actividadReporte['id_tag'] ?? '');
    $idsCoordinaciones = $decodificarIdsActividad($actividadReporte['id_coordinacion'] ?? '');
    $idsUsuarios = $decodificarIdsActividad($actividadReporte['id_user'] ?? '');
    $idsHosts = $decodificarIdsActividad($actividadReporte['id_host'] ?? '');

    $nombresEntidades = [];
    foreach ($idsEntidades as $id) {
        if (isset($mapaEntidadesActividad[$id])) {
            $nombresEntidades[] = $mapaEntidadesActividad[$id]['entidad'];
        }
    }

    $nombresTags = [];
    $nombresServicios = [];
    $idsServicios = [];

    foreach ($idsTags as $id) {

        if (!isset($mapaTagsActividad[$id])) {
            continue;
        }

        $tag = $mapaTagsActividad[$id];
        $nombresTags[] = $tag['tag'];

        $idsTipos = $decodificarIdsActividad($tag['id_tag_tipo'] ?? '');

        foreach ($idsTipos as $idTipo) {

            if (isset($mapaTiposActividad[$idTipo])) {

                $nombresServicios[] = $mapaTiposActividad[$idTipo]['tag_tipo'];
                $idsServicios[] = $idTipo;
            }
        }
    }

    $nombresCoordinaciones = [];
    foreach ($idsCoordinaciones as $id) {
        if (isset($mapaCoordinacionesActividad[$id])) {
            $nombresCoordinaciones[] = $mapaCoordinacionesActividad[$id]['coordinacion'];
        }
    }

    $nombresUsuarios = [];
    foreach ($idsUsuarios as $id) {
        if (isset($mapaUsuariosActividad[$id])) {
            $usuario = $mapaUsuariosActividad[$id];
            $nombresUsuarios[] = $usuario['name'] ?? $usuario['user'] ?? 'Usuario #' . $id;
        }
    }

    $nombreActividad = $actividadReporte['actividad'] ?? '';
    $descripcionActividad = strip_tags($actividadReporte['descripcion'] ?? '');

    $fechaOriginal = $actividadReporte['modificacion'] ?? '';
    $timestamp = !empty($fechaOriginal) ? strtotime($fechaOriginal) : false;

    $fechaIso = $timestamp !== false
        ? date('Y-m-d H:i:s', $timestamp)
        : '';

    $fechaMostrar = $timestamp !== false
        ? date('d/m/Y H:i', $timestamp)
        : '—';

    $registrosActividadReporte[] = [
        'id' => (int)$actividadReporte['id'],
        'actividad' => $nombreActividad,
        'descripcion' => $descripcionActividad,
        'entidades' => array_values(array_unique($nombresEntidades)),
        'entidades_ids' => $idsEntidades,
        'tags' => array_values(array_unique($nombresTags)),
        'tags_ids' => $idsTags,
        'servicios' => array_values(array_unique($nombresServicios)),
        'servicios_ids' => array_values(array_unique($idsServicios)),
        'coordinaciones' => array_values(array_unique($nombresCoordinaciones)),
        'coordinaciones_ids' => $idsCoordinaciones,
        'usuarios' => array_values(array_unique($nombresUsuarios)),
        'usuarios_ids' => $idsUsuarios,
        'hosts_ids' => $idsHosts,
        'cantidad' => (int)($actividadReporte['cantidad'] ?? 0),
        'fecha_iso' => $fechaIso,
        'fecha' => $fechaMostrar
    ];
}

/*=============================================
LISTAS PARA FILTROS
=============================================*/

$tiposActivosReporte = array_filter($tiposTagReporte, function ($tipo) {
    return (int)($tipo['estado'] ?? 0) === 1;
});

$entidadesActivasReporte = array_filter($entidadesReporte, function ($entidad) {
    return (int)($entidad['estado'] ?? 0) === 1;
});

$usuariosActivosReporte = array_filter($usuariosReporte, function ($usuario) {
    return (int)($usuario['estado'] ?? 0) === 1;
});

?>

<div class="content-wrapper reporte-actividades">

    <!-- ENCABEZADO -->
    <section class="content-header px-3 pt-3 pb-2">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

            <div>
                <h1 class="h3 mb-1 fw-bold">Reporte de Actividades</h1>
                <p class="text-body-secondary mb-0">
                    Consulta, analiza y exporta las actividades registradas en el sistema.
                </p>
            </div>

            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="inicio">Inicio</a>
                    </li>
                    <li class="breadcrumb-item active">Reportes</li>
                    <li class="breadcrumb-item active">Actividades</li>
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
                            Utiliza uno o varios filtros para consultar las actividades.
                        </small>
                    </div>

                </div>

            </div>

            <div class="card-body">

                <div class="row g-3">

                    <!-- FECHAS -->
                    <div class="col-12 col-md-6 col-xl-4">

                        <label class="form-label fw-semibold">Rango de fechas</label>

                        <div class="row g-2">

                            <div class="col-6">
                                <label for="fechaDesdeActividad" class="form-label small text-body-secondary mb-1">
                                    Desde
                                </label>

                                <input
                                    type="date"
                                    id="fechaDesdeActividad"
                                    class="form-control"
                                >
                            </div>

                            <div class="col-6">
                                <label for="fechaHastaActividad" class="form-label small text-body-secondary mb-1">
                                    Hasta
                                </label>

                                <input
                                    type="date"
                                    id="fechaHastaActividad"
                                    class="form-control"
                                >
                            </div>

                        </div>

                    </div>

                    <!-- COORDINACIÓN -->
                    <div class="col-12 col-md-6 col-xl-4">

                        <label for="coordinacionFilterActividad" class="form-label fw-semibold">
                            Coordinación
                        </label>

                        <select id="coordinacionFilterActividad" class="form-select">
                            <option value="">Todas las coordinaciones</option>

                            <?php foreach ($coordinacionesReporte as $coordinacion): ?>
                                <option value="<?php echo (int)$coordinacion['id']; ?>">
                                    <?php echo htmlspecialchars($coordinacion['coordinacion'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- SERVICIO -->
                    <div class="col-12 col-md-6 col-xl-4">

                        <label for="servicioFilterActividad" class="form-label fw-semibold">
                            Servicio
                        </label>

                        <select id="servicioFilterActividad" class="form-select">
                            <option value="">Todos los servicios</option>

                            <?php foreach ($tiposActivosReporte as $tipo): ?>
                                <option value="<?php echo (int)$tipo['id']; ?>">
                                    <?php echo htmlspecialchars($tipo['tag_tipo'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- ENTE -->
                    <div class="col-12 col-md-6 col-xl-4">

                        <label for="enteFilterActividad" class="form-label fw-semibold">
                            Ente
                        </label>

                        <select id="enteFilterActividad" class="form-select">
                            <option value="">Todos los entes</option>

                            <?php foreach ($entidadesActivasReporte as $entidad): ?>
                                <option value="<?php echo (int)$entidad['id']; ?>">
                                    <?php echo htmlspecialchars($entidad['entidad'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- USUARIO -->
                    <div class="col-12 col-md-6 col-xl-4">

                        <label for="usuarioFilterActividad" class="form-label fw-semibold">
                            Usuario
                        </label>

                        <select id="usuarioFilterActividad" class="form-select">
                            <option value="">Todos los usuarios</option>

                            <?php foreach ($usuariosActivosReporte as $usuario): ?>
                                <option value="<?php echo (int)$usuario['id']; ?>">
                                    <?php
                                    echo htmlspecialchars(
                                        $usuario['name'] ?? $usuario['user'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- BOTONES -->
                    <div class="col-12 col-md-6 col-xl-4 d-flex align-items-end flex-wrap gap-2">

                        <button type="button" id="clearFiltersActividad" class="btn btn-outline-secondary">
                            <i class="fas fa-eraser me-1"></i>
                            Limpiar
                        </button>

                        <button type="button" id="exportExcelActividad" class="btn btn-success">
                            <i class="fas fa-file-excel me-1"></i>
                            Excel
                        </button>

                        <button type="button" id="exportPdfActividad" class="btn btn-danger">
                            <i class="fas fa-file-pdf me-1"></i>
                            PDF
                        </button>

                    </div>

                </div>

                <div class="mt-3">
                    <span id="activeFiltersActividad" class="badge rounded-pill text-bg-secondary">
                        Sin filtros activos
                    </span>
                </div>

            </div>

        </div>

        <!-- CONTADORES -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-md-6 col-xl-3">

                <div class="reporte-stat stat-blue">

                    <div class="stat-icon">
                        <i class="fas fa-list-alt"></i>
                    </div>

                    <div>
                        <div class="stat-label">Total actividades</div>
                        <div class="stat-value" id="totalActividades">0</div>
                    </div>

                </div>

            </div>

            <div class="col-12 col-md-6 col-xl-3">

                <div class="reporte-stat stat-green">

                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>

                    <div>
                        <div class="stat-label">Actividades visibles</div>
                        <div class="stat-value" id="visibleActividades">0</div>
                    </div>

                </div>

            </div>

            <div class="col-12 col-md-6 col-xl-3">

                <div class="reporte-stat stat-orange">

                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>

                    <div>
                        <div class="stat-label">Coordinaciones</div>
                        <div class="stat-value" id="totalCoordinacionesActividad">0</div>
                    </div>

                </div>

            </div>

            <div class="col-12 col-md-6 col-xl-3">

                <div class="reporte-stat stat-purple">

                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>

                    <div>
                        <div class="stat-label">Usuarios involucrados</div>
                        <div class="stat-value" id="totalUsuariosActividad">0</div>
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
                                <h2 class="h5 mb-0 fw-semibold">Actividades por Coordinación</h2>
                                <small class="text-body-secondary">Distribución de actividades.</small>
                            </div>
                        </div>

                    </div>

                    <div class="card-body">
                        <div class="chart-container-actividad">
                            <canvas id="coordinacionChartActividad"></canvas>
                        </div>

                        <div id="coordinacionChartEmptyActividad" class="text-center text-body-secondary py-3 d-none">
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
                                <h2 class="h5 mb-0 fw-semibold">Servicios más utilizados</h2>
                                <small class="text-body-secondary">Distribución por servicio.</small>
                            </div>
                        </div>

                    </div>

                    <div class="card-body">
                        <div class="chart-container-actividad">
                            <canvas id="servicioChartActividad"></canvas>
                        </div>

                        <div id="servicioChartEmptyActividad" class="text-center text-body-secondary py-3 d-none">
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
                                <h2 class="h5 mb-0 fw-semibold">Entes más frecuentes</h2>
                                <small class="text-body-secondary">Distribución por ente.</small>
                            </div>
                        </div>

                    </div>

                    <div class="card-body">
                        <div class="chart-container-actividad">
                            <canvas id="enteChartActividad"></canvas>
                        </div>

                        <div id="enteChartEmptyActividad" class="text-center text-body-secondary py-3 d-none">
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
                                <h2 class="h5 mb-0 fw-semibold">Usuarios involucrados</h2>
                                <small class="text-body-secondary">Participación en actividades.</small>
                            </div>
                        </div>

                    </div>

                    <div class="card-body">
                        <div class="chart-container-actividad">
                            <canvas id="usuarioChartActividad"></canvas>
                        </div>

                        <div id="usuarioChartEmptyActividad" class="text-center text-body-secondary py-3 d-none">
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
                        <h2 class="h5 mb-0 fw-semibold">Listado de actividades</h2>
                        <small class="text-body-secondary">
                            Detalle de las actividades registradas.
                        </small>
                    </div>

                </div>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table id="actividadesTableReporte" class="table table-hover table-striped align-middle w-100">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Actividad</th>
                                <th>Coordinación</th>
                                <th>Servicios</th>
                                <th>Entes</th>
                                <th>Usuarios asignados</th>
                                <th>Cantidad</th>
                                <th>Descripción</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($registrosActividadReporte as $registro): ?>

                                <tr
                                    data-fecha="<?php echo htmlspecialchars($registro['fecha_iso'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-coordinaciones="<?php echo htmlspecialchars(json_encode($registro['coordinaciones_ids']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-servicios="<?php echo htmlspecialchars(json_encode($registro['servicios_ids']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-entidades="<?php echo htmlspecialchars(json_encode($registro['entidades_ids']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-usuarios="<?php echo htmlspecialchars(json_encode($registro['usuarios_ids']), ENT_QUOTES, 'UTF-8'); ?>"
                                >

                                    <td><?php echo $registro['id']; ?></td>

                                    <td>
                                        <span class="actividad-nombre">
                                            <?php echo htmlspecialchars($registro['actividad'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            implode(', ', $registro['coordinaciones']) ?: 'No asignada',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            implode(', ', $registro['servicios']) ?: 'Sin servicio',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            implode(', ', $registro['entidades']) ?: 'Sin ente',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            implode(', ', $registro['usuarios']) ?: 'Sin usuarios',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <span class="cantidad-badge">
                                            <?php echo $registro['cantidad']; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php
                                        $descripcion = $registro['descripcion'];
                                        echo htmlspecialchars(
                                            mb_strlen($descripcion) > 100
                                                ? mb_substr($descripcion, 0, 100) . '...'
                                                : $descripcion,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>

                                    <td data-order="<?php echo htmlspecialchars($registro['fecha_iso'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($registro['fecha'], ENT_QUOTES, 'UTF-8'); ?>
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

<!-- LIBRERÍAS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<style>
.reporte-actividades {
    --ra-border: var(--bs-border-color);
    --ra-card: var(--bs-body-bg);
    --ra-muted: var(--bs-secondary-color);
}

.reporte-actividades .reporte-card {
    border: 1px solid var(--ra-border);
    border-radius: 14px;
    background: var(--ra-card);
    box-shadow: 0 3px 14px rgba(0,0,0,.045);
    overflow: hidden;
}

.reporte-actividades .card-header {
    background: transparent;
    padding: 1.15rem 1.25rem .65rem;
}

.reporte-actividades .card-body {
    padding: 1.25rem;
}

.reporte-actividades .reporte-icon {
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

.reporte-actividades .reporte-stat {
    min-height: 108px;
    padding: 1.1rem 1.15rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--ra-border);
    border-radius: 14px;
    background: var(--ra-card);
    box-shadow: 0 3px 14px rgba(0,0,0,.04);
}

.reporte-actividades .stat-icon {
    width: 49px;
    height: 49px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    font-size: 1.2rem;
    flex: 0 0 auto;
}

.reporte-actividades .stat-blue .stat-icon {
    color: #0d6efd;
    background: rgba(13,110,253,.12);
}

.reporte-actividades .stat-green .stat-icon {
    color: #198754;
    background: rgba(25,135,84,.12);
}

.reporte-actividades .stat-orange .stat-icon {
    color: #fd7e14;
    background: rgba(253,126,20,.13);
}

.reporte-actividades .stat-purple .stat-icon {
    color: #6f42c1;
    background: rgba(111,66,193,.13);
}

.reporte-actividades .stat-label {
    color: var(--ra-muted);
    font-size: .85rem;
    margin-bottom: .2rem;
}

.reporte-actividades .stat-value {
    font-size: 1.65rem;
    line-height: 1.2;
    font-weight: 750;
}

.reporte-actividades .chart-container-actividad {
    position: relative;
    width: 100%;
    height: 330px;
    max-width: 650px;
    margin: 0 auto;
}

.reporte-actividades .actividad-nombre {
    font-weight: 650;
}

.reporte-actividades .cantidad-badge {
    display: inline-block;
    min-width: 35px;
    text-align: center;
    padding: .3rem .55rem;
    border-radius: 7px;
    color: #198754;
    background: rgba(25,135,84,.12);
    font-weight: 700;
}

.reporte-actividades .table thead th {
    white-space: nowrap;
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .3px;
    color: var(--ra-muted);
    border-bottom-width: 1px;
}

.reporte-actividades .table tbody td {
    font-size: .9rem;
}

.reporte-actividades .dataTables_wrapper .dataTables_filter input,
.reporte-actividades .dataTables_wrapper .dataTables_length select {
    border: 1px solid var(--ra-border);
    border-radius: 8px;
    padding: .35rem .55rem;
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.reporte-actividades .dataTables_wrapper .dataTables_filter input {
    margin-left: .4rem;
}

.reporte-actividades .dataTables_wrapper .dataTables_info {
    color: var(--ra-muted);
    font-size: .85rem;
    padding-top: 1rem;
}

.reporte-actividades .dataTables_wrapper .dataTables_paginate {
    padding-top: .7rem;
}

[data-bs-theme="dark"] .reporte-actividades .reporte-card,
[data-bs-theme="dark"] .reporte-actividades .reporte-stat {
    box-shadow: 0 3px 14px rgba(0,0,0,.16);
}

@media (max-width: 767.98px) {
    .reporte-actividades .card-body {
        padding: 1rem;
    }

    .reporte-actividades .chart-container-actividad {
        height: 290px;
    }

    .reporte-actividades .reporte-stat {
        min-height: 95px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const $ = window.jQuery;

    if (!$ || !$.fn.DataTable) {
        console.error('No se pudo inicializar el reporte: jQuery o DataTables no están disponibles.');
        return;
    }

    const $tabla = $('#actividadesTableReporte');

    if (!$tabla.length) return;

    let fechaDesde = '';
    let fechaHasta = '';
    let coordinacionSeleccionada = '';
    let servicioSeleccionado = '';
    let enteSeleccionado = '';
    let usuarioSeleccionado = '';

    let graficoCoordinacion = null;
    let graficoServicio = null;
    let graficoEnte = null;
    let graficoUsuario = null;

    const paleta = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
        '#9966FF', '#FF9F40', '#8A2BE2', '#5F9EA0',
        '#D2691E', '#DC143C', '#00CED1', '#FFD700',
        '#ADFF2F', '#FF69B4', '#CD5C5C', '#20B2AA',
        '#90EE90', '#FFB6C1'
    ];

    function obtenerIds(fila, atributo) {
        try {
            return JSON.parse(fila.getAttribute(atributo) || '[]').map(Number);
        } catch (error) {
            return [];
        }
    }

    // FILTRO PERSONALIZADO
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {

        if (!settings.nTable || settings.nTable.id !== 'actividadesTableReporte') {
            return true;
        }

        const fila = settings.aoData[dataIndex]?.nTr;

        if (!fila) return true;

        const fecha = (fila.getAttribute('data-fecha') || '').substring(0, 10);

        if (fechaDesde && (!fecha || fecha < fechaDesde)) {
            return false;
        }

        if (fechaHasta && (!fecha || fecha > fechaHasta)) {
            return false;
        }

        if (
            coordinacionSeleccionada &&
            !obtenerIds(fila, 'data-coordinaciones').includes(Number(coordinacionSeleccionada))
        ) {
            return false;
        }

        if (
            servicioSeleccionado &&
            !obtenerIds(fila, 'data-servicios').includes(Number(servicioSeleccionado))
        ) {
            return false;
        }

        if (
            enteSeleccionado &&
            !obtenerIds(fila, 'data-entidades').includes(Number(enteSeleccionado))
        ) {
            return false;
        }

        if (
            usuarioSeleccionado &&
            !obtenerIds(fila, 'data-usuarios').includes(Number(usuarioSeleccionado))
        ) {
            return false;
        }

        return true;
    });

    // DATATABLE
    const tabla = $tabla.DataTable({

        responsive: true,
        retrieve: true,
        order: [[0, 'desc']],
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
            actualizarContadores();
            actualizarGraficos();
            actualizarEtiquetaFiltros();
        }

    });

    // OBTENER FILAS FILTRADAS O COMPLETAS
    function obtenerFilas(soloFiltradas) {

        const filas = [];

        const selector = soloFiltradas
            ? { search: 'applied' }
            : {};

        tabla.rows(selector).every(function () {

            const celdas = this.data();

            filas.push({
                id: $('<div>').html(celdas[0]).text().trim(),
                actividad: $('<div>').html(celdas[1]).text().trim(),
                coordinacion: $('<div>').html(celdas[2]).text().trim(),
                servicios: $('<div>').html(celdas[3]).text().trim(),
                entes: $('<div>').html(celdas[4]).text().trim(),
                usuarios: $('<div>').html(celdas[5]).text().trim(),
                cantidad: $('<div>').html(celdas[6]).text().trim(),
                descripcion: $('<div>').html(celdas[7]).text().trim(),
                fecha: $('<div>').html(celdas[8]).text().trim()
            });

        });

        return filas;
    }

    // CONTADORES
    function actualizarContadores() {

        const filas = obtenerFilas(true);

        const coordinaciones = new Set();
        const usuarios = new Set();

        filas.forEach(fila => {

            fila.coordinacion.split(',').forEach(nombre => {
                if (nombre.trim() && nombre.trim() !== 'No asignada') {
                    coordinaciones.add(nombre.trim());
                }
            });

            fila.usuarios.split(',').forEach(nombre => {
                if (nombre.trim() && nombre.trim() !== 'Sin usuarios') {
                    usuarios.add(nombre.trim());
                }
            });

        });

        $('#totalActividades').text(tabla.rows().count());
        $('#visibleActividades').text(filas.length);
        $('#totalCoordinacionesActividad').text(coordinaciones.size);
        $('#totalUsuariosActividad').text(usuarios.size);
    }

    // DISTRIBUCIONES PARA LOS GRÁFICOS
    function obtenerDistribucion(atributo) {

        const resultado = {};

        tabla.rows({ search: 'applied' }).every(function () {

            const fila = this.node();

            const ids = obtenerIds(fila, atributo);

            let nombres = [];

            if (atributo === 'data-coordinaciones') {
                nombres = $('<div>').html(this.data()[2]).text().split(',');
            } else if (atributo === 'data-servicios') {
                nombres = $('<div>').html(this.data()[3]).text().split(',');
            } else if (atributo === 'data-entidades') {
                nombres = $('<div>').html(this.data()[4]).text().split(',');
            } else if (atributo === 'data-usuarios') {
                nombres = $('<div>').html(this.data()[5]).text().split(',');
            }

            nombres.forEach(nombre => {

                nombre = nombre.trim();

                if (
                    nombre &&
                    !['No asignada', 'Sin servicio', 'Sin ente', 'Sin usuarios'].includes(nombre)
                ) {
                    resultado[nombre] = (resultado[nombre] || 0) + 1;
                }

            });

        });

        return resultado;
    }

    // CREAR GRÁFICOS
    function crearGrafico(canvasId, emptyId, datos, graficoAnterior) {

        const canvas = document.getElementById(canvasId);
        const empty = document.getElementById(emptyId);

        if (!canvas || !empty || typeof Chart === 'undefined') {
            return null;
        }

        if (graficoAnterior) {
            graficoAnterior.destroy();
        }

        let etiquetas = Object.keys(datos);
        let valores = Object.values(datos);

        if (etiquetas.length > 10) {

            const ordenados = Object.entries(datos)
                .sort((a, b) => b[1] - a[1]);

            const principales = ordenados.slice(0, 10);
            const otros = ordenados.slice(10)
                .reduce((total, item) => total + item[1], 0);

            etiquetas = principales.map(item => item[0]);
            valores = principales.map(item => item[1]);

            if (otros > 0) {
                etiquetas.push('Otros');
                valores.push(otros);
            }
        }

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
                    backgroundColor: etiquetas.map((_, i) => paleta[i % paleta.length]),
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

    function actualizarGraficos() {

        graficoCoordinacion = crearGrafico(
            'coordinacionChartActividad',
            'coordinacionChartEmptyActividad',
            obtenerDistribucion('data-coordinaciones'),
            graficoCoordinacion
        );

        graficoServicio = crearGrafico(
            'servicioChartActividad',
            'servicioChartEmptyActividad',
            obtenerDistribucion('data-servicios'),
            graficoServicio
        );

        graficoEnte = crearGrafico(
            'enteChartActividad',
            'enteChartEmptyActividad',
            obtenerDistribucion('data-entidades'),
            graficoEnte
        );

        graficoUsuario = crearGrafico(
            'usuarioChartActividad',
            'usuarioChartEmptyActividad',
            obtenerDistribucion('data-usuarios'),
            graficoUsuario
        );

    }

    // ETIQUETA DE FILTROS
    function actualizarEtiquetaFiltros() {

        const filtros = [];

        if (fechaDesde || fechaHasta) {

            const desde = fechaDesde
                ? fechaDesde.split('-').reverse().join('/')
                : 'Inicio';

            const hasta = fechaHasta
                ? fechaHasta.split('-').reverse().join('/')
                : 'Fin';

            filtros.push(`Fecha: ${desde} - ${hasta}`);
        }

        const selectores = [
            ['coordinacionFilterActividad', 'Coordinación', coordinacionSeleccionada],
            ['servicioFilterActividad', 'Servicio', servicioSeleccionado],
            ['enteFilterActividad', 'Ente', enteSeleccionado],
            ['usuarioFilterActividad', 'Usuario', usuarioSeleccionado]
        ];

        selectores.forEach(([id, etiqueta, valor]) => {

            if (valor) {
                filtros.push(`${etiqueta}: ${$(`#${id} option:selected`).text()}`);
            }

        });

        const badge = $('#activeFiltersActividad');

        if (!filtros.length) {

            badge
                .removeClass('text-bg-primary')
                .addClass('text-bg-secondary')
                .text('Sin filtros activos');

        } else {

            badge
                .removeClass('text-bg-secondary')
                .addClass('text-bg-primary')
                .text(filtros.join(' | '));

        }
    }

    // APLICAR FILTROS
    function aplicarFiltros() {

        if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {

            const temporal = fechaDesde;

            fechaDesde = fechaHasta;
            fechaHasta = temporal;

            $('#fechaDesdeActividad').val(fechaDesde);
            $('#fechaHastaActividad').val(fechaHasta);
        }

        tabla.draw();
    }

    // EVENTOS
    $('#fechaDesdeActividad, #fechaHastaActividad').on('change input', function () {

        fechaDesde = $('#fechaDesdeActividad').val() || '';
        fechaHasta = $('#fechaHastaActividad').val() || '';

        aplicarFiltros();
    });

    $('#coordinacionFilterActividad').on('change', function () {
        coordinacionSeleccionada = $(this).val() || '';
        aplicarFiltros();
    });

    $('#servicioFilterActividad').on('change', function () {
        servicioSeleccionado = $(this).val() || '';
        aplicarFiltros();
    });

    $('#enteFilterActividad').on('change', function () {
        enteSeleccionado = $(this).val() || '';
        aplicarFiltros();
    });

    $('#usuarioFilterActividad').on('change', function () {
        usuarioSeleccionado = $(this).val() || '';
        aplicarFiltros();
    });

    // LIMPIAR FILTROS
    $('#clearFiltersActividad').on('click', function () {

        $('#fechaDesdeActividad, #fechaHastaActividad').val('');

        $('#coordinacionFilterActividad').val('');
        $('#servicioFilterActividad').val('');
        $('#enteFilterActividad').val('');
        $('#usuarioFilterActividad').val('');

        fechaDesde = '';
        fechaHasta = '';
        coordinacionSeleccionada = '';
        servicioSeleccionado = '';
        enteSeleccionado = '';
        usuarioSeleccionado = '';

        aplicarFiltros();
    });

    // EXPORTAR EXCEL
    $('#exportExcelActividad').on('click', function () {

        const filas = obtenerFilas(true);

        if (!filas.length) {
            alert('No hay actividades para exportar.');
            return;
        }

        const datos = [[
            'ID',
            'Actividad',
            'Coordinación',
            'Servicios',
            'Entes',
            'Usuarios asignados',
            'Cantidad',
            'Descripción',
            'Fecha'
        ]];

        filas.forEach(fila => {

            datos.push([
                fila.id,
                fila.actividad,
                fila.coordinacion,
                fila.servicios,
                fila.entes,
                fila.usuarios,
                fila.cantidad,
                fila.descripcion,
                fila.fecha
            ]);

        });

        const libro = XLSX.utils.book_new();
        const hoja = XLSX.utils.aoa_to_sheet(datos);

        hoja['!cols'] = [
            { wch: 8 },
            { wch: 35 },
            { wch: 30 },
            { wch: 30 },
            { wch: 30 },
            { wch: 35 },
            { wch: 12 },
            { wch: 50 },
            { wch: 22 }
        ];

        XLSX.utils.book_append_sheet(libro, hoja, 'Actividades');

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
            `reporte_actividades_${marcaTiempo}.xlsx`
        );

    });

    // EXPORTAR PDF
    $('#exportPdfActividad').on('click', function () {

        if (!window.jspdf || !window.jspdf.jsPDF) {
            alert('No se pudo cargar la librería para generar PDF.');
            return;
        }

        const filas = obtenerFilas(true);

        if (!filas.length) {
            alert('No hay actividades para exportar.');
            return;
        }

        const { jsPDF } = window.jspdf;

        const documento = new jsPDF('landscape');

        const datos = filas.map(fila => [
            fila.id,
            fila.actividad,
            fila.coordinacion,
            fila.servicios,
            fila.entes,
            fila.usuarios,
            fila.cantidad,
            fila.fecha
        ]);

        documento.setFontSize(15);
        documento.text('Reporte de Actividades', 14, 15);

        documento.setFontSize(9);
        documento.text(
            `Fecha de generación: ${new Date().toLocaleString('es-VE')}`,
            14,
            22
        );

        documento.autoTable({

            startY: 28,

            head: [[
                'ID',
                'Actividad',
                'Coordinación',
                'Servicios',
                'Entes',
                'Usuarios',
                'Cantidad',
                'Fecha'
            ]],

            body: datos,

            theme: 'striped',

            styles: {
                fontSize: 7,
                cellPadding: 2,
                overflow: 'linebreak'
            },

            headStyles: {
                fillColor: [41, 128, 185]
            },

            columnStyles: {
                0: { cellWidth: 12 },
                1: { cellWidth: 35 },
                2: { cellWidth: 35 },
                3: { cellWidth: 35 },
                4: { cellWidth: 35 },
                5: { cellWidth: 35 },
                6: { cellWidth: 15 },
                7: { cellWidth: 25 }
            }

        });

        const ahora = new Date();

        const marcaTiempo =
            ahora.getFullYear() +
            String(ahora.getMonth() + 1).padStart(2, '0') +
            String(ahora.getDate()).padStart(2, '0') +
            '_' +
            String(ahora.getHours()).padStart(2, '0') +
            String(ahora.getMinutes()).padStart(2, '0');

        documento.save(`reporte_actividades_${marcaTiempo}.pdf`);

    });

    // INICIALIZACIÓN
    actualizarContadores();
    actualizarGraficos();
    actualizarEtiquetaFiltros();

});
</script>
