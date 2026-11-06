<?php
/*=============================================
tabs2.php — Estadísticas mensuales
Tickets finalizados (check=1) por día, por usuario.

Reglas de inclusión (igual que dashboard.php / allticket_core.php):
  1) Rol "Administrador"          → ve TODOS los usuarios activos
  2) Coordinación "Centro de ..." → ve TODOS los usuarios activos
  3) Usuario SIN coordinación     → ve solo su propia línea
  4) Usuario CON coordinación     → ve los usuarios que compartan
                                    al menos 1 coordinación con él

✅ FIX 2026-10-21:
  - Solo cuentan los usuarios ASIGNADOS al ticket (tabla `tuser`).
  - Se excluye al CREADOR del ticket (ticket.id_user) del conteo.
  - Tickets sin asignados no suman para nadie.

Gráfico:
  - X: días del mes actual (1..N)
  - Y: cantidad de tickets con check=1
  - Una línea por usuario, cada una con un COLOR DISTINTO
  - ✅ SIN línea "Total" (removida por requerimiento)

✅ NUEVO 2026-11-02:
  - El tooltip OMITE los usuarios que tengan 0 tickets finalizados
    ese día (via tooltip.filter).
  - Si NINGÚN usuario tiene tickets ese día, el tooltip no aparece.
  - Bonus: footer con el TOTAL del día (suma de usuarios visibles).
=============================================*/

if (!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok") {
    return;
}

$idUser = (int)($_SESSION["id"] ?? 0);
if ($idUser <= 0) return;

$userActual = ControladorUser::ctrMostrarUser("id", $idUser);
if (!$userActual) return;

/* ─── Helper defensivo: decodifica IDs (soporta "[3]" o "3" o "") ─── */
if (!function_exists('tabs2DecodificarIds')) {
    function tabs2DecodificarIds($raw) {
        if ($raw === null || $raw === '') return [];
        if (is_array($raw)) return array_map('intval', $raw);

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return array_map('intval', $decoded);
        if (is_numeric($decoded)) return [(int)$decoded];
        return [];
    }
}

/* ─── Cargar catálogos ANTES de filtrar ─── */
$todosRoles = ControladorRol::ctrMostrarRol(null, null) ?: [];
$mapaRoles  = [];
foreach ($todosRoles as $r) { $mapaRoles[(int)$r["id"]] = $r; }

$todasCoords = ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: [];
$mapaCoords  = [];
foreach ($todasCoords as $c) { $mapaCoords[(int)$c["id"]] = $c; }

/* ─── Coordinaciones y roles del usuario actual ─── */
$coordsUser     = tabs2DecodificarIds($userActual["id_coordinacion"] ?? '');
$rolesUser      = tabs2DecodificarIds($userActual["id_rol"] ?? '');
$userTieneCoord = !empty($coordsUser);

/* ─── ¿Es Administrador? ─── */
$esAdmin = false;
foreach ($rolesUser as $idRol) {
    if (isset($mapaRoles[$idRol])
        && (int)$mapaRoles[$idRol]["estado"] === 1
        && strcasecmp(trim($mapaRoles[$idRol]["rol"]), "Administrador") === 0) {
        $esAdmin = true;
        break;
    }
}

/* ─── ¿Pertenece a "Centro de Atención"? ─── */
$esCentroAtencion = false;
foreach ($coordsUser as $idC) {
    if (isset($mapaCoords[$idC])
        && (int)$mapaCoords[$idC]["estado"] === 1
        && strpos(mb_strtolower(trim($mapaCoords[$idC]["coordinacion"])), 'centro de atenci') !== false) {
        $esCentroAtencion = true;
        break;
    }
}

/* ─── Determinar usuarios a incluir ─── */
$todosUsuarios   = ControladorUser::ctrMostrarUser(null, null) ?: [];
$usuariosIncluir = [];

foreach ($todosUsuarios as $u) {
    if ((int)($u["estado"] ?? 0) !== 1) continue; // solo activos

    $incluir = false;

    if ($esAdmin || $esCentroAtencion) {
        // Bypass: Admin y Centro de Atención ven a TODOS
        $incluir = true;
    } elseif (!$userTieneCoord) {
        // Sin coordinación → solo él mismo
        $incluir = ((int)$u["id"] === $idUser);
    } else {
        // Con coordinación → intersección no vacía
        $coordsU = tabs2DecodificarIds($u["id_coordinacion"] ?? '');
        $incluir = !empty(array_intersect($coordsU, $coordsUser));
    }

    if ($incluir) $usuariosIncluir[] = $u;
}

/* Garantizar que el usuario actual esté siempre incluido */
$uidsEnSet = array_map(function ($u) { return (int)$u["id"]; }, $usuariosIncluir);
if (!in_array($idUser, $uidsEnSet, true)) {
    $usuariosIncluir[] = $userActual;
}

if (empty($usuariosIncluir)) {
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No hay usuarios activos para mostrar estadísticas.</div>';
    return;
}

/* ─── Periodo: mes actual ─── */
$ahora       = new DateTime();
$anio        = (int)$ahora->format('Y');
$mesNum      = (int)$ahora->format('n');
$diasEnMes   = (int)$ahora->format('t');

$mesesNombres = [
    1 => 'Enero',     2 => 'Febrero',   3 => 'Marzo',     4 => 'Abril',
    5 => 'Mayo',      6 => 'Junio',     7 => 'Julio',     8 => 'Agosto',
    9 => 'Septiembre',10 => 'Octubre', 11 => 'Noviembre',12 => 'Diciembre'
];
$nombreMes = $mesesNombres[$mesNum];

/*=============================================
Paleta de colores variados
→ Cada usuario recibe un color DISTINTO
=============================================*/
$paletaColores = [
    '#0d6efd', // azul
    '#dc3545', // rojo
    '#198754', // verde
    '#fd7e14', // naranja
    '#6f42c1', // púrpura
    '#20c997', // teal
    '#d63384', // rosa
    '#6610f2', // violeta
    '#0dcaf0', // cian
    '#ffc107', // ámbar
    '#17a2b8', // info
    '#e83e8c', // magenta
    '#6c757d', // gris
    '#0d9488', // emerald
    '#9333ea', // fuchsia
    '#ea580c', // naranja oscuro
    '#0891b2', // sky
    '#c026d3', // pink
    '#16a34a', // green 600
    '#e11d48'  // rose 600
];

/* ─── Estructura por usuario (color variado) ─── */
$dataPorUsuario  = [];
$coloresUsuario  = [];
$nombresUsuario  = [];

$idx = 0;
foreach ($usuariosIncluir as $u) {
    $uid = (int)$u["id"];
    $nombresUsuario[$uid] = !empty($u["name"]) ? $u["name"] : $u["user"];
    $coloresUsuario[$uid] = $paletaColores[$idx % count($paletaColores)];
    $dataPorUsuario[$uid] = array_fill(1, $diasEnMes, 0);
    $idx++;
}

/* ─── Precargar tuser por ticket (una sola vez) ─── */
$todosTusers  = ControladorTuser::ctrMostrarTuser(null, null) ?: [];
$idsPorTicket = [];
foreach ($todosTusers as $tu) {
    $tid = (int)$tu["id_ticket"];
    if (!isset($idsPorTicket[$tid])) $idsPorTicket[$tid] = [];
    foreach (tabs2DecodificarIds($tu["id_user"] ?? '') as $iu) {
        $idsPorTicket[$tid][] = $iu;
    }
}
foreach ($idsPorTicket as $tid => $ids) {
    $idsPorTicket[$tid] = array_values(array_unique($ids));
}

/* ─── Recorrer tickets finalizados del mes ─── */
$todosTickets        = ControladorTicket::ctrMostrarTicket(null, null) ?: [];
$ticketsUnicosPorDia = array_fill(1, $diasEnMes, 0);
$totalUnicosMes      = 0;

foreach ($todosTickets as $t) {
    if ((int)($t["check"] ?? 0) !== 1) continue;

    $fechaTicket = $t["fecha"] ?? '';
    if ($fechaTicket === '') continue;

    $ts = strtotime($fechaTicket);
    if ($ts === false) continue;

    if ((int)date('Y', $ts) !== $anio) continue;
    if ((int)date('n', $ts) !== $mesNum) continue;

    $dia = (int)date('j', $ts);
    $tid = (int)$t["id"];

    /*=============================================
    ✅ SOLO usuarios ASIGNADOS al ticket (tuser).
       Se elimina por completo $idsCreador
       (ticket.id_user = creador del ticket), que era
       el que sumaba +1 al Administrador por cada
       ticket finalizado.
    =============================================*/
    $idsAsignados = $idsPorTicket[$tid] ?? [];

    // Sin asignados → no cuenta para nadie
    if (empty($idsAsignados)) continue;

    // ¿Al menos un asignado está en el conjunto visible?
    $algunoEnSet = false;
    foreach ($idsAsignados as $iu) {
        if (isset($dataPorUsuario[$iu])) { $algunoEnSet = true; break; }
    }
    if (!$algunoEnSet) continue;

    // Sumar al día de cada asignado visible
    foreach ($idsAsignados as $iu) {
        if (isset($dataPorUsuario[$iu])) $dataPorUsuario[$iu][$dia]++;
    }

    $ticketsUnicosPorDia[$dia]++;
    $totalUnicosMes++;
}

/* Totales para el panel de resumen */
$totalAsignaciones = 0;
foreach ($dataPorUsuario as $data) {
    $totalAsignaciones += array_sum($data);
}

$promedioPorDia = $totalUnicosMes > 0 ? ($totalUnicosMes / $diasEnMes) : 0;

$diaMax   = 0;
$maxCount = 0;
foreach ($ticketsUnicosPorDia as $d => $c) {
    if ($c > $maxCount) { $maxCount = $c; $diaMax = $d; }
}

$topUser  = null;
$topCount = 0;
foreach ($dataPorUsuario as $uid => $data) {
    $sum = array_sum($data);
    if ($sum > $topCount) { $topCount = $sum; $topUser = $uid; }
}

/* Datasets (solo usuarios, sin línea "Total") */
$labelsDias = array_map('strval', range(1, $diasEnMes));
$datasets   = [];

foreach ($dataPorUsuario as $uid => $data) {
    $color = $coloresUsuario[$uid];
    $datasets[] = [
        'label'                => $nombresUsuario[$uid],
        'data'                 => array_values($data),
        'borderColor'          => $color,
        'backgroundColor'      => $color . '20',
        'tension'              => 0.35,
        'fill'                 => false,
        'borderWidth'          => 2,
        'pointRadius'          => 3,
        'pointHoverRadius'      => 6,
        'pointBackgroundColor' => $color,
    ];
}

/* ─── Etiqueta del "alcance" para el header ─── */
if ($esAdmin) {
    $alcanceLabel = 'Vista Administrador — todos los usuarios';
    $alcanceIcono = 'fa-user-shield';
    $alcanceColor = 'primary';
} elseif ($esCentroAtencion) {
    $alcanceLabel = 'Vista Centro de Atención — todos los usuarios';
    $alcanceIcono = 'fa-headset';
    $alcanceColor = 'success';
} elseif (!$userTieneCoord) {
    $alcanceLabel = 'Vista individual — sin coordinación';
    $alcanceIcono = 'fa-user';
    $alcanceColor = 'info';
} else {
    $alcanceLabel = 'Vista por coordinación';
    $alcanceIcono = 'fa-sitemap';
    $alcanceColor = 'warning';
}
?>
<br>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2 text-primary"></i>
                    Tickets finalizados — <?php echo $nombreMes . ' ' . $anio; ?>
                </h5>
                <div class="card-tools d-flex gap-2 flex-wrap">
                    <span class="badge text-bg-<?php echo $alcanceColor; ?>">
                        <i class="fas <?php echo $alcanceIcono; ?> me-1"></i>
                        <?php echo htmlspecialchars($alcanceLabel); ?>
                    </span>
                    <span class="badge text-bg-primary">
                        <i class="fas fa-users me-1"></i>
                        <?php echo count($usuariosIncluir); ?> usuario(s)
                    </span>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <!-- GRÁFICO -->
                    <div class="col-md-9">
                        <p class="text-center text-muted small mb-2">
                            <strong>Tickets asignados (check = 1) por día</strong>
                            <span class="text-secondary">(clic en la leyenda para ocultar/mostrar)</span>
                        </p>
                        <div class="position-relative" style="height: 340px;">
                            <canvas id="sales-chart"></canvas>
                        </div>
                    </div>

                    <!-- PANEL RESUMEN -->
                    <div class="col-md-3 border-start">
                        <p class="text-center mb-3"><strong>Resumen del mes</strong></p>

                        <div class="mb-3">
                            <small class="text-muted d-block">Tickets finalizados (únicos)</small>
                            <h4 class="fw-bold mb-0 text-success"><?php echo $totalUnicosMes; ?></h4>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block">Asignaciones totales</small>
                            <h5 class="fw-bold mb-0"><?php echo $totalAsignaciones; ?></h5>
                            <small class="text-muted">(suma por usuario)</small>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block">Promedio por día</small>
                            <h5 class="fw-bold mb-0"><?php echo number_format($promedioPorDia, 1); ?></h5>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block">Día más activo</small>
                            <h5 class="fw-bold mb-0">
                                <?php if ($maxCount > 0): ?>
                                    <?php echo $diaMax; ?> <?php echo $nombreMes; ?>
                                    <small class="text-muted">(<?php echo $maxCount; ?>)</small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </h5>
                        </div>

                        <?php if ($topUser !== null && $topCount > 0): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Top usuario</small>
                            <h6 class="fw-bold mb-0" style="color: <?php echo htmlspecialchars($coloresUsuario[$topUser]); ?>;">
                                <i class="fas fa-crown me-1"></i>
                                <?php echo htmlspecialchars($nombresUsuario[$topUser]); ?>
                            </h6>
                            <small class="text-muted"><?php echo $topCount; ?> ticket(s)</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- LISTA VISIBLE DE USUARIOS INCLUIDOS -->
                <div class="mt-3 pt-3 border-top">
                    <small class="text-muted d-block mb-2">
                        <i class="fas fa-users me-1"></i>
                        Usuarios en el gráfico (<?php echo count($usuariosIncluir); ?>):
                    </small>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($usuariosIncluir as $u):
                            $uid    = (int)$u["id"];
                            $color  = $coloresUsuario[$uid] ?? '#6c757d';
                            $nombre = $nombresUsuario[$uid] ?? $u["user"];
                            $esUserActual = ($uid === $idUser);
                        ?>
                            <span class="badge"
                                  style="background-color: <?php echo htmlspecialchars($color); ?>; color:#fff; font-size:0.75rem; padding:5px 10px; <?php echo $esUserActual ? 'outline: 2px solid #212529; outline-offset:1px;' : ''; ?>">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($nombre); ?>
                                <?php if ($esUserActual): ?>
                                    <small>(tú)</small>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- FOOTER CON MÉTRICAS -->
            <div class="card-footer">
                <div class="row">
                    <div class="col-md-3 col-6">
                        <div class="text-center border-end">
                            <span class="text-success"><i class="fas fa-check-circle"></i></span>
                            <h5 class="fw-bold mb-0"><?php echo $totalUnicosMes; ?></h5>
                            <span class="text-uppercase small text-muted">Tickets finalizados</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="text-center border-end">
                            <span class="text-info"><i class="fas fa-users"></i></span>
                            <h5 class="fw-bold mb-0"><?php echo count($usuariosIncluir); ?></h5>
                            <span class="text-uppercase small text-muted">Usuarios activos</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="text-center border-end">
                            <span class="text-warning"><i class="fas fa-chart-bar"></i></span>
                            <h5 class="fw-bold mb-0"><?php echo $totalAsignaciones; ?></h5>
                            <span class="text-uppercase small text-muted">Asignaciones</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="text-center">
                            <span class="text-primary"><i class="fas fa-calendar-day"></i></span>
                            <h5 class="fw-bold mb-0"><?php echo $maxCount > 0 ? $maxCount : '0'; ?></h5>
                            <span class="text-uppercase small text-muted">Máx. en un día</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('sales-chart');
    if (!canvas) return;

    if (typeof Chart === 'undefined') {
        canvas.parentElement.innerHTML =
            '<div class="alert alert-warning m-0">Chart.js no está cargado.</div>';
        return;
    }

    const labels   = <?php echo json_encode($labelsDias, JSON_UNESCAPED_UNICODE); ?>;
    const datasets = <?php echo json_encode($datasets, JSON_UNESCAPED_UNICODE); ?>;
    const mes      = <?php echo json_encode($nombreMes, JSON_UNESCAPED_UNICODE); ?>;

    new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 14,
                        padding: 12,
                        usePointStyle: true,
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    /* ✅ NUEVO: omitir del tooltip cualquier usuario
                       que tenga 0 tickets finalizados ese día.
                       Si TODOS son 0, el tooltip no aparece. */
                    filter: (item) => item.parsed.y > 0,

                    callbacks: {
                        title: (items) => {
                            // items siempre trae al menos 1 después del filter;
                            // el guard evita errores si Chart.js cambia en el futuro.
                            if (!items.length) return '';
                            return 'Día ' + items[0].label + ' de ' + mes;
                        },
                        label: (ctx) => {
                            const v = ctx.parsed.y;
                            return ctx.dataset.label + ': ' + v + ' ticket' + (v === 1 ? '' : 's');
                        },
                        /* ✅ NUEVO: footer con el TOTAL del día (solo suma los visibles) */
                        footer: (items) => {
                            if (!items.length) return '';
                            const total = items.reduce((sum, i) => sum + i.parsed.y, 0);
                            return 'Total del día: ' + total + ' ticket' + (total === 1 ? '' : 's');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
});
</script>