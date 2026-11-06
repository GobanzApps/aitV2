<?php
/*=============================================
hservicio.php
Muestra los servicios (tservicio) en los que el host actual ($idHost)
aparece dentro del JSON id_host, con el MISMO diseño visual que
tservicio.php (tarjetas article, no tabla).

Se incluye desde vistas/modulos/host.php
=============================================*/

if(!isset($idHost) || $idHost <= 0){
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No se pudo identificar el host.</div>';
    return;
}

/*=============================================
1) Servicios del host (filtrado en PHP por JSON)
=============================================*/
$todosServicios = ControladorTservicio::ctrMostrarTservicio(null, null);
$todosServicios = $todosServicios ?: [];

$serviciosDelHost = [];
foreach($todosServicios as $s){
    $idsHosts = json_decode($s["id_host"] ?? '[]', true);
    if(!is_array($idsHosts)) $idsHosts = [];
    $idsHosts = array_map('intval', $idsHosts);
    if(in_array((int)$idHost, $idsHosts, true)){
        $serviciosDelHost[] = $s;
    }
}

/*=============================================
2) Mapas para resolver IDs → datos
=============================================*/
$todosTickets  = ControladorTicket::ctrMostrarTicket(null, null) ?: [];
$mapaTickets   = [];
foreach($todosTickets as $t){ $mapaTickets[(int)$t["id"]] = $t; }

$todosTags = ControladorTag::ctrMostrarTag(null, null) ?: [];
$mapaTags  = [];
foreach($todosTags as $t){ $mapaTags[(int)$t["id"]] = $t; }

$todosHosts = ControladorHost::ctrMostrarHost(null, null) ?: [];
$mapaHosts  = [];
foreach($todosHosts as $h){ $mapaHosts[(int)$h["id"]] = $h; }

$todosUsuarios = ControladorUser::ctrMostrarUser(null, null) ?: [];
$mapaUsuarios  = [];
foreach($todosUsuarios as $u){ $mapaUsuarios[(int)$u["id"]] = $u; }

$todosRoles = ControladorRol::ctrMostrarRol(null, null) ?: [];
$mapaRoles  = [];
foreach($todosRoles as $r){ $mapaRoles[(int)$r["id"]] = $r; }

/*=============================================
3) Helpers (mismos nombres que tservicio.php)
=============================================*/
if(!function_exists('tservColorUsuario')){
    function tservColorUsuario($usuario, $mapaRoles){
        $ids = json_decode($usuario["id_rol"] ?? '[]', true);
        if(!is_array($ids) || empty($ids)) return "#6c757d";
        foreach($ids as $idRol){
            $idRol = (int)$idRol;
            if(isset($mapaRoles[$idRol]) && (int)$mapaRoles[$idRol]["estado"] === 1){
                return $mapaRoles[$idRol]["color"] ?: "#0d6efd";
            }
        }
        return "#6c757d";
    }
}

if(!function_exists('tservIniciales')){
    function tservIniciales($nombre){
        $nombre = trim((string)$nombre);
        if($nombre === '') return '?';
        $partes = preg_split('/\s+/', $nombre);
        $ini = '';
        foreach(array_slice($partes, 0, 2) as $p){
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $ini ?: '?';
    }
}

if(!function_exists('tservNombreUsuario')){
    function tservNombreUsuario($usuario){
        if(!$usuario) return null;
        return !empty($usuario["name"]) ? $usuario["name"] : $usuario["user"];
    }
}
?>

<style>
    /* ─── Réplica de los estilos de tservicio.php ─── */
    .tserv-descripcion {
        background: rgba(13,110,253,0.04);
        border-left: 3px solid #0d6efd;
        padding: 10px 14px;
        border-radius: 6px;
        font-size: 0.92rem;
        line-height: 1.55;
        word-break: break-word;
    }
    .tserv-descripcion img { max-width: 100%; height: auto; }
    .tserv-descripcion p:last-child { margin-bottom: 0; }
    [data-bs-theme="dark"] .tserv-descripcion { background: rgba(110,168,254,0.08); }

    /* Chip del host actual resaltado */
    .hserv-host-current {
        outline: 2px solid #212529;
        outline-offset: 1px;
    }
    [data-bs-theme="dark"] .hserv-host-current { outline-color: #fff; }
</style>

<!-- ═══════════════ CABECERA ═══════════════ -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="fas fa-tools me-2 text-primary"></i> Servicios del Host
        <span class="badge text-bg-primary ms-2"><?php echo count($serviciosDelHost); ?></span>
    </h5>
</div>

<?php if(empty($serviciosDelHost)): ?>

    <div class="alert alert-info d-flex align-items-center mb-0">
        <i class="fas fa-info-circle me-2"></i>
        <div>Este host no ha sido incluido en ningún servicio registrado.</div>
    </div>

<?php else: ?>

    <?php foreach($serviciosDelHost as $s):

        $idTicketS = (int)($s["id_ticket"] ?? 0);
        $ticket    = $mapaTickets[$idTicketS] ?? null;

        /* Creador */
        $creador = $mapaUsuarios[(int)($s["tservicio"] ?? 0)] ?? null;
        $nombreCreador = tservNombreUsuario($creador);
        if($nombreCreador === null){
            $nombreCreador = "Usuario #" . $s["tservicio"];
        }
        $colorCreador  = $creador ? tservColorUsuario($creador, $mapaRoles) : "#6c757d";
        $iniCreador    = tservIniciales($nombreCreador);

        /* Fecha */
        $fechaMostrar = !empty($s["modificacion"])
            ? date("d/m/Y H:i", strtotime($s["modificacion"]))
            : "—";

        /* Tags / Hosts */
        $idsTags = json_decode($s["id_tag"], true);
        if(!is_array($idsTags)) $idsTags = [];

        $idsHosts = json_decode($s["id_host"], true);
        if(!is_array($idsHosts)) $idsHosts = [];
    ?>

    <article class="d-flex gap-3 mb-3 p-3 border rounded bg-body-tertiary"
             data-tservicio-id="<?php echo (int)$s["id"]; ?>">

        <!-- Avatar del creador (igual que tservicio.php) -->
        <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm"
             style="width: 45px; height: 45px; background-color: <?php echo htmlspecialchars($colorCreador); ?>; font-weight: 600;"
             aria-hidden="true">
            <?php echo htmlspecialchars($iniCreador); ?>
        </div>

        <div class="flex-grow-1">

            <!-- ─── Cabecera: nombre + ticket + acción ─── -->
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h4 class="h6 mb-0">
                        <i class="fas fa-tools me-1 text-success"></i>
                        <?php echo htmlspecialchars($nombreCreador); ?>
                        <small class="text-muted ms-2">registró un servicio</small>
                    </h4>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                        <?php if($ticket): ?>
                            <a href="index.php?ruta=ticket&id=<?php echo $idTicketS; ?>"
                               class="badge text-bg-primary text-decoration-none"
                               title="Ir al ticket">
                                <i class="fas fa-ticket-alt me-1"></i>Ticket #<?php echo $idTicketS; ?>
                            </a>
                        <?php else: ?>
                            <span class="badge bg-secondary">
                                <i class="fas fa-ticket-alt me-1"></i>Ticket #<?php echo $idTicketS; ?>
                            </span>
                        <?php endif; ?>
                        <small class="text-secondary">
                            <i class="fas fa-clock me-1"></i><?php echo $fechaMostrar; ?>
                        </small>
                    </div>
                </div>

                <!-- Acción: ver el ticket (la edición/eliminación se hace allá) -->
                <div class="btn-group">
                    <?php if($ticket): ?>
                        <a href="index.php?ruta=ticket&id=<?php echo $idTicketS; ?>"
                           class="btn btn-sm btn-info"
                           title="Ver y gestionar el servicio en el ticket">
                            <i class="fas fa-eye me-1"></i> Ver ticket
                        </a>
                    <?php else: ?>
                        <span class="text-muted small">Ticket no disponible</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ─── Tags ─── -->
            <div class="mt-2">
                <small class="text-muted fw-bold">
                    <i class="fas fa-tags me-1"></i>Tags:
                </small>
                <?php if(empty($idsTags)): ?>
                    <span class="text-muted ms-1">—</span>
                <?php else: foreach($idsTags as $idT):
                    $idT = (int)$idT;
                    $tg  = $mapaTags[$idT] ?? null;
                    if($tg){
                        $colorT = $tg["color"] ?: "#0d6efd";
                        echo '<span class="badge me-1 mb-1" style="background-color:'.htmlspecialchars($colorT).'; color:#fff; padding:5px 10px; font-size:0.78rem;">'
                           . '<i class="fas fa-tag me-1"></i>'.htmlspecialchars($tg["tag"]).'</span>';
                    } else {
                        echo '<span class="badge bg-secondary me-1 mb-1">ID '.$idT.'</span>';
                    }
                endforeach; endif; ?>
            </div>

            <!-- ─── Hosts (host actual resaltado) ─── -->
            <div class="mt-2">
                <small class="text-muted fw-bold">
                    <i class="fas fa-network-wired me-1"></i>Hosts:
                </small>
                <?php if(empty($idsHosts)): ?>
                    <span class="text-muted ms-1 fst-italic">Ninguno</span>
                <?php else: foreach($idsHosts as $idH):
                    $idH = (int)$idH;
                    $hs  = $mapaHosts[$idH] ?? null;
                    if($hs){
                        $colorH   = $hs["color"] ?: "#0d6efd";
                        $esActual = ($idH === (int)$idHost);
                        $extraCls = $esActual ? ' hserv-host-current' : '';
                        $titulo   = $esActual ? ' (host actual)' : '';
                        echo '<span class="badge me-1 mb-1'.$extraCls.'" style="background-color:'.htmlspecialchars($colorH).'; color:#fff; padding:5px 10px; font-size:0.78rem;" title="'.htmlspecialchars($hs["host"]).$titulo.'">'
                           . '<i class="fas fa-network-wired me-1"></i>'.htmlspecialchars($hs["host"]).'</span>';
                    } else {
                        echo '<span class="badge bg-secondary me-1 mb-1">ID '.$idH.'</span>';
                    }
                endforeach; endif; ?>
            </div>

            <!-- ─── Cantidad ─── -->
            <div class="mt-2">
                <span class="badge text-bg-dark">
                    <i class="fas fa-hashtag me-1"></i>Cantidad: <?php echo (int)$s["cantidad"]; ?>
                </span>
            </div>

            <!-- ─── Descripción (HTML de Quill) ─── -->
            <div class="mt-3">
                <div class="tserv-descripcion">
                    <?php echo $s["descripcion"]; ?>
                </div>
            </div>

        </div>
    </article>

    <?php endforeach; ?>

<?php endif; ?>