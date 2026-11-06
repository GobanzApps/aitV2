<?php
/*=============================================
members.php — Miembros del Equipo
Muestra los usuarios activos que comparten
al menos una coordinación con el usuario logueado.

Reglas (idénticas a dashboard.php / tabs2.php):
  - Administrador        → ve TODOS los usuarios activos
  - Sin coordinaciones   → ve SOLO su propia tarjeta
  - Con coordinaciones   → ve usuarios que compartan
                           al menos 1 coordinación con él

Cada tarjeta es un enlace a index.php?ruta=user&id=X
Debajo del nombre se listan TODAS las coordinaciones
del usuario (cada una con su color).
Se resalta con outline la tarjeta del usuario logueado.
=============================================*/

if (!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok") {
    return;
}

$idUserSesion = (int)($_SESSION["id"] ?? 0);
if ($idUserSesion <= 0) return;

$userSesion = ControladorUser::ctrMostrarUser("id", $idUserSesion);
if (!$userSesion) return;

/* ─── Catálogos ─── */
$todosRoles = ControladorRol::ctrMostrarRol(null, null) ?: [];
$mapaRoles  = [];
foreach ($todosRoles as $r) { $mapaRoles[(int)$r["id"]] = $r; }

$todasCoords = ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: [];
$mapaCoords  = [];
foreach ($todasCoords as $c) { $mapaCoords[(int)$c["id"]] = $c; }

/* ─── Datos del usuario logueado ─── */
$coordsSesion = array_map('intval', json_decode($userSesion["id_coordinacion"] ?? '[]', true) ?: []);
$rolesSesion  = array_map('intval', json_decode($userSesion["id_rol"] ?? '[]', true) ?: []);
$tieneCoord   = !empty($coordsSesion);

/* ─── ¿Administrador? ─── */
$esAdmin = false;
foreach ($rolesSesion as $idRol) {
    if (isset($mapaRoles[$idRol])
        && (int)$mapaRoles[$idRol]["estado"] === 1
        && strcasecmp(trim($mapaRoles[$idRol]["rol"]), "Administrador") === 0) {
        $esAdmin = true;
        break;
    }
}

/* ─── Filtrar usuarios activos ─── */
$todosUsuarios   = ControladorUser::ctrMostrarUser(null, null) ?: [];
$usuariosMostrar = [];

foreach ($todosUsuarios as $u) {
    if ((int)($u["estado"] ?? 0) !== 1) continue;

    $incluir = false;

    if ($esAdmin) {
        $incluir = true;
    } elseif (!$tieneCoord) {
        // Sin coordinación → solo él mismo
        $incluir = ((int)$u["id"] === $idUserSesion);
    } else {
        // Con coordinación → intersección no vacía
        $coordsU = array_map('intval', json_decode($u["id_coordinacion"] ?? '[]', true) ?: []);
        $incluir = !empty(array_intersect($coordsU, $coordsSesion));
    }

    if ($incluir) $usuariosMostrar[] = $u;
}

/* Garantizar que el usuario actual esté incluido */
$idsYa = array_map(function ($u) { return (int)$u["id"]; }, $usuariosMostrar);
if (!in_array($idUserSesion, $idsYa, true)) {
    $usuariosMostrar[] = $userSesion;
}

/* Total antes de recortar + recorte a 8 para el grid 4×2 original */
$totalEncontrados = count($usuariosMostrar);
$usuariosMostrar  = array_slice($usuariosMostrar, 0, 8);

/* ─── Helper: foto con fallback a ui-avatars ─── */
if (!function_exists('membersFotoUsuario')) {
    function membersFotoUsuario($u) {
        if (!empty($u["foto"])) {
            return $u["foto"];
        }
        $nombre = urlencode(!empty($u["name"]) ? $u["name"] : $u["user"]);
        return "https://ui-avatars.com/api/?name=" . $nombre
             . "&background=0d6efd&color=fff&size=128";
    }
}

/* ─── Helper: devuelve TODAS las coordinaciones del usuario ─── */
if (!function_exists('membersCoordsUsuario')) {
    function membersCoordsUsuario($u, $mapaCoords) {
        $ids = array_map('intval', json_decode($u["id_coordinacion"] ?? '[]', true) ?: []);
        $resultado = [];
        foreach ($ids as $idC) {
            if (isset($mapaCoords[$idC])) {
                $resultado[] = $mapaCoords[$idC];
            }
        }
        return $resultado;
    }
}
?>

<div class="col-md-6">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
                <i class="fas fa-users me-2"></i>
                Miembros del Equipo
            </h3>
            <div class="card-tools">
                <span class="badge text-bg-primary">
                    <?php echo $totalEncontrados; ?>
                    miembro<?php echo $totalEncontrados === 1 ? '' : 's'; ?>
                </span>
                <?php if ($esAdmin): ?>
                    <span class="badge text-bg-danger ms-1" title="Vista Administrador — todos los usuarios">
                        <i class="fas fa-user-shield me-1"></i> Admin
                    </span>
                <?php elseif ($tieneCoord): ?>
                    <span class="badge text-bg-warning ms-1" title="Solo usuarios de tus coordinaciones">
                        <i class="fas fa-sitemap me-1"></i> Por coordinación
                    </span>
                <?php else: ?>
                    <span class="badge text-bg-info ms-1" title="Sin coordinación asignada">
                        <i class="fas fa-user me-1"></i> Individual
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($usuariosMostrar)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-user-slash fa-2x mb-2 d-block"></i>
                    No hay usuarios para mostrar.
                </div>
            <?php else: ?>
                <div class="row text-center m-1">
                    <?php foreach ($usuariosMostrar as $u):
                        $idU        = (int)$u["id"];
                        $nombreMost = !empty($u["name"]) ? $u["name"] : $u["user"];
                        $foto       = membersFotoUsuario($u);
                        $esUno      = ($idU === $idUserSesion);
                        $coordsU    = membersCoordsUsuario($u, $mapaCoords);
                    ?>
                        <div class="col-3 p-2">
                            <a href="index.php?ruta=user&id=<?php echo $idU; ?>"
                               title="Ver perfil de <?php echo htmlspecialchars($nombreMost); ?>">
                                <img class="img-fluid rounded-circle shadow-sm"
                                     src="<?php echo htmlspecialchars($foto); ?>"
                                     alt="<?php echo htmlspecialchars($nombreMost); ?>"
                                     style="width:80px; height:80px; object-fit:cover;
                                            <?php echo $esUno ? 'outline: 2px solid #0d6efd; outline-offset: 2px;' : ''; ?>"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($u['user']); ?>&background=0d6efd&color=fff&size=128'">
                            </a>

                            <a class="btn fw-bold fs-7 text-secondary text-truncate w-100 p-0 mt-1"
                               href="index.php?ruta=user&id=<?php echo $idU; ?>"
                               title="<?php echo htmlspecialchars($nombreMost); ?>">
                                <?php echo htmlspecialchars($nombreMost); ?>
                                <?php if ($esUno): ?>
                                    <small class="text-primary">(tú)</small>
                                <?php endif; ?>
                            </a>

                            <?php if (!empty($coordsU)): ?>
                                <div class="d-flex flex-wrap justify-content-center gap-1 mt-1">
                                    <?php foreach ($coordsU as $coord):
                                        $colorC = $coord["color"] ?: "#0d6efd";
                                        $nombreC = $coord["coordinacion"];
                                    ?>
                                        <span class="badge"
                                              style="background-color: <?php echo htmlspecialchars($colorC); ?>;
                                                     color:#fff; font-size:0.58rem;
                                                     padding:2px 6px; max-width:100%;"
                                              title="<?php echo htmlspecialchars($nombreC); ?>">
                                            <i class="fas fa-sitemap me-1"></i><?php echo htmlspecialchars($nombreC); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="fs-8 text-muted mt-1">
                                    <i class="fas fa-minus me-1"></i>Sin coordinación
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>