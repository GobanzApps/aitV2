<?php
/*=============================================
dashboard.php
Estadísticas del usuario actual, filtradas por
sus coordinaciones o tickets asignados.
(Admin y Centro de Atención ven todo)
=============================================*/

if (!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok") {
    return;
}

$idUser = (int)($_SESSION["id"] ?? 0);
if ($idUser <= 0) return;

$userActual = ControladorUser::ctrMostrarUser("id", $idUser);
if (!$userActual) return;

/* ── Roles del usuario ── */
$todosRoles = ControladorRol::ctrMostrarRol(null, null) ?: [];
$mapaRoles  = [];
foreach ($todosRoles as $r) { $mapaRoles[(int)$r["id"]] = $r; }

/* ¿Administrador? */
$esAdmin = false;
foreach ((json_decode($userActual["id_rol"] ?? '[]', true) ?: []) as $idRol) {
    $idRol = (int)$idRol;
    if (isset($mapaRoles[$idRol]) && (int)$mapaRoles[$idRol]["estado"] === 1
        && strcasecmp($mapaRoles[$idRol]["rol"], "Administrador") === 0) {
        $esAdmin = true;
        break;
    }
}

/* Coordinaciones del usuario */
$coordsUser = array_map('intval', json_decode($userActual["id_coordinacion"] ?? '[]', true) ?: []);

/* ¿Centro de Atención? (alguna coordinación activa cuyo nombre lo contenga) */
$esCentroAtencion = false;
if (!empty($coordsUser)) {
    $todasCoords = ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: [];
    $mapaCoords  = [];
    foreach ($todasCoords as $c) { $mapaCoords[(int)$c["id"]] = $c; }

    foreach ($coordsUser as $idC) {
        if (isset($mapaCoords[$idC]) && (int)$mapaCoords[$idC]["estado"] === 1
            && strpos(mb_strtolower(trim($mapaCoords[$idC]["coordinacion"])), 'centro de atenci') !== false) {
            $esCentroAtencion = true;
            break;
        }
    }
}

/* ── Helpers locales ── */

/* ¿El usuario puede ver este ticket? */
if (!function_exists('dashboardPuedeVerTicket')) {
    function dashboardPuedeVerTicket($idTicket, $idUser, $coordsUser, $esAdmin, $esCentroAtencion)
    {
        if ($esAdmin || $esCentroAtencion) return true;

        // Coordinaciones asignadas al ticket
        $tcoords = ControladorTcoordinacion::ctrMostrarTcoordinacionPorTicket((int)$idTicket);
        if ($tcoords && is_array($tcoords)) {
            foreach ($tcoords as $tc) {
                foreach ((json_decode($tc["id_coordinacion"] ?? '[]', true) ?: []) as $idC) {
                    if (in_array((int)$idC, $coordsUser, true)) return true;
                }
            }
        }

        // Usuarios asignados al ticket
        $tusers = ControladorTuser::ctrMostrarTuserPorTicket((int)$idTicket);
        if ($tusers && is_array($tusers)) {
            foreach ($tusers as $tu) {
                foreach ((json_decode($tu["id_user"] ?? '[]', true) ?: []) as $idU) {
                    if ((int)$idU === (int)$idUser) return true;
                }
            }
        }
        return false;
    }
}

/* Nombre del último status del ticket, o null si no hay registros */
if (!function_exists('dashboardNombreUltimoStatus')) {
    function dashboardNombreUltimoStatus($idTicket)
    {
        $hist = ControladorTstatus::ctrMostrarTstatusPorTicket((int)$idTicket);
        if (!$hist || !is_array($hist) || count($hist) === 0) return null;

        $ultimo = $hist[0]; // ORDER BY id DESC
        $idSt   = (int)($ultimo["id_status"] ?? 0);
        if ($idSt <= 0) return null;

        $st = ControladorStatus::ctrMostrarStatus("id", $idSt);
        return $st ? trim($st["status"]) : null;
    }
}

/* ── Recorrido de tickets ── */
$todosTickets    = ControladorTicket::ctrMostrarTicket(null, null) ?: [];
$totalTickets    = 0; // tickets visibles
$totalResueltos  = 0; // check = 1
$totalNoResuelto = 0; // check = 0 + último status ∈ {Nuevo, Reemplazo, En Proceso}
$totalEnPausa    = 0; // check = 0 + último status ∈ {En pausa, Programado}

$statusNoResuelto = ["Nuevo", "Reemplazo", "En Proceso"];
$statusEnPausa    = ["En pausa", "Programado"];

foreach ($todosTickets as $t) {
    $idTicket = (int)($t["id"] ?? 0);
    if ($idTicket <= 0) continue;
    if (!dashboardPuedeVerTicket($idTicket, $idUser, $coordsUser, $esAdmin, $esCentroAtencion)) continue;

    $totalTickets++;

    if ((int)($t["check"] ?? 0) === 1) {
        $totalResueltos++;
    } else {
        $nombreSt = dashboardNombreUltimoStatus($idTicket);
        if ($nombreSt !== null) {
            if (in_array($nombreSt, $statusNoResuelto, true)) $totalNoResuelto++;
            if (in_array($nombreSt, $statusEnPausa,    true)) $totalEnPausa++;
        }
    }
}
?>

<!-- ═══════════════ TARJETAS DE ESTADÍSTICAS ═══════════════ -->
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-secondary small mb-1">
                    <i class="fas fa-ticket-alt me-1"></i> Tickets
                </p>
                <h3 class="mb-0 fw-bold"><?php echo $totalTickets; ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-secondary small mb-1">
                    <i class="fas fa-check-circle me-1 text-success"></i> Resuelto
                </p>
                <h3 class="mb-0 fw-bold text-success"><?php echo $totalResueltos; ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-secondary small mb-1">
                    <i class="fas fa-exclamation-triangle me-1 text-warning"></i> No Resuelto
                </p>
                <h3 class="mb-0 fw-bold text-warning"><?php echo $totalNoResuelto; ?></h3>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-6">
        <div class="card h-100">
            <div class="card-body">
                <p class="text-secondary small mb-1">
                    <i class="fas fa-pause-circle me-1 text-secondary"></i> En Pausa
                </p>
                <h3 class="mb-0 fw-bold text-secondary"><?php echo $totalEnPausa; ?></h3>
            </div>
        </div>
    </div>
</div>