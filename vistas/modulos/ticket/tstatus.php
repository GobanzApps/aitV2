<?php
/*=============================================
Incluido desde vistas/modulos/ticket.php
$idTicket y $ticket YA están definidos.
=============================================*/
if(!isset($idTicket) || $idTicket <= 0){
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No se pudo identificar el ticket.</div>';
    return;
}

/* ═══════════════════════════════════════════════════════════
   Contexto de autorización
   ═══════════════════════════════════════════════════════════ */
$authTs              = (isset($authTicket) && $authTicket) ? $authTicket : ControladorTicket::authTicket();
$esAdminTs           = ControladorTicket::esAdminTicket($authTs);
$esCentroTs          = ControladorTicket::esCentroAtencionTicket($authTs);
$esAdminOCentroTs    = $esAdminTs || $esCentroTs;
$ticketFinalizadoTs  = ((int)($ticket["check"] ?? 0) === 1);

$estaAsignadoTs      = $authTs ? ControladorTicket::estaAsignadoAlTicket($idTicket, $authTs) : false;

/* Usuario con rol "Usuario" y SIN coordinación → solo lectura */
$esUsuarioSinCoordTs = $authTs
    && ControladorTicket::esUsuarioTicket($authTs)
    && empty($authTs['coordsIds']);

/* Permiso base (mismo orden que el backend) */
$permisoBaseTs = false;
if ($authTs) {
    if ($esAdminOCentroTs) {
        $permisoBaseTs = true;
    } elseif ($esUsuarioSinCoordTs) {
        $permisoBaseTs = false;
    } elseif ($estaAsignadoTs) {
        $permisoBaseTs = true;
    }
}

$puedeModificarTs = $permisoBaseTs && !$ticketFinalizadoTs;

/* Banderilla para el banner del Coordinador con coord asignada sin tuser */
$esCoordConCoordAsignadaTs = false;
if (!$permisoBaseTs && !$esUsuarioSinCoordTs && $authTs) {
    if (ControladorTicket::esCoordinadorTicket($authTs)
        && ControladorTicket::coordinacionAsignadaAlTicket($idTicket, $authTs)) {
        $esCoordConCoordAsignadaTs = true;
    }
}

/*=============================================
Datos
=============================================*/
$tstatuses = ControladorTstatus::ctrMostrarTstatusPorTicket($idTicket);
$tstatuses = $tstatuses ?: [];

$todosUsuarios = ControladorUser::ctrMostrarUser(null, null);
if(!$todosUsuarios || !is_array($todosUsuarios)) $todosUsuarios = [];
$mapaUsuarios = [];
foreach($todosUsuarios as $u){ $mapaUsuarios[(int)$u["id"]] = $u; }

$todosRoles = ControladorRol::ctrMostrarRol(null, null);
if(!$todosRoles || !is_array($todosRoles)) $todosRoles = [];
$mapaRoles = [];
foreach($todosRoles as $r){ $mapaRoles[(int)$r["id"]] = $r; }

$todosStatus = ControladorStatus::ctrMostrarStatus(null, null);
if(!$todosStatus || !is_array($todosStatus)) $todosStatus = [];
$mapaStatus = [];
foreach($todosStatus as $s){ $mapaStatus[(int)$s["id"]] = $s; }

$statusNotificables = [];
if ($esAdminOCentroTs) {
    foreach($todosStatus as $s){
        if(in_array($s["status"], ["Nuevo", "Reemplazo"], true)){
            $statusNotificables[] = (int)$s["id"];
        }
    }
}

/* ═══════════════════════════════════════════════════════════
   ✅ Statuses que EXIGEN comentario:
      TODOS excepto: "Suspendido", "En Proceso" y "Nuevo"
      (la misma regla se valida en el backend)
   ═══════════════════════════════════════════════════════════ */
$statusesRequierenComentario = [];
$statusesSinComentario       = ["suspendido", "en proceso", "nuevo"];
foreach($todosStatus as $s){
    $nombreLower = mb_strtolower(trim($s["status"]));
    if(!in_array($nombreLower, $statusesSinComentario, true)){
        $statusesRequierenComentario[] = (int)$s["id"];
    }
}

/* Regla 2: filtrar N/R del listado para quien no es Admin/Centro */
$tstatusesFiltrados = [];
foreach($tstatuses as $t){
    $idSt    = (int)($t["id_status"] ?? 0);
    $stData  = $mapaStatus[$idSt] ?? null;
    $esNotif = $stData && in_array($stData["status"], ["Nuevo", "Reemplazo"]);

    if($esNotif && !$esAdminOCentroTs) continue;

    $tstatusesFiltrados[] = $t;
}

/* ID del último status visible del ticket */
$idUltimoTstatus = !empty($tstatusesFiltrados) ? (int)$tstatusesFiltrados[0]["id"] : 0;

/*=============================================
Helpers
=============================================*/
if(!function_exists('tstatusColorUsuario')){
    function tstatusColorUsuario($usuario, $mapaRoles){
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

if(!function_exists('tstatusIniciales')){
    function tstatusIniciales($nombre){
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

if(!function_exists('tstatusNombreUsuario')){
    function tstatusNombreUsuario($usuario){
        if(!$usuario) return null;
        return !empty($usuario["name"]) ? $usuario["name"] : $usuario["user"];
    }
}

if(!function_exists('tstatusTiempoTranscurrido')){
    function tstatusTiempoTranscurrido($desde, $hasta){
        if(empty($desde) || empty($hasta)) return '—';
        $seg = strtotime($hasta) - strtotime($desde);
        if($seg < 0) $seg = 0;
        $min = floor($seg / 60);
        if($min < 1)  return 'menos de 1 min';
        if($min < 60) return $min . ' min';
        $hrs     = floor($min / 60);
        $minRest = $min % 60;
        if($hrs < 24){
            return $hrs . ' h' . ($minRest > 0 ? ' ' . $minRest . ' min' : '');
        }
        $d       = floor($hrs / 24);
        $hrsRest = $hrs % 24;
        return $d . ' d' . ($hrsRest > 0 ? ' ' . $hrsRest . ' h' : '');
    }
}

if(!function_exists('tstatusTiempoDesdeAhora')){
    function tstatusTiempoDesdeAhora($desde){
        if(empty($desde)) return '—';
        return tstatusTiempoTranscurrido($desde, date('Y-m-d H:i:s'));
    }
}

/* ═══════════════════════════════════════════════════════════
   Datos para el botón WhatsApp (comunes al ticket)
   ═══════════════════════════════════════════════════════════ */
$whatsappEntidades = [];
foreach ((json_decode($ticket["id_entidad"] ?? '[]', true) ?: []) as $idE) {
    $ent = ControladorEntidad::ctrMostrarEntidad("id", (int)$idE);
    if ($ent) $whatsappEntidades[] = $ent["entidad"];
}

$whatsappPrioridades = [];
foreach ((json_decode($ticket["id_prioridad"] ?? '[]', true) ?: []) as $idP) {
    $pri = ControladorPrioridad::ctrMostrarPrioridad("id", (int)$idP);
    if ($pri) $whatsappPrioridades[] = $pri["prioridad"];
}

$whatsappCreador = null;
if (!empty($ticket["id_user"])) {
    $creatorUser = $mapaUsuarios[(int)$ticket["id_user"]] ?? null;
    if ($creatorUser) {
        $whatsappCreador = !empty($creatorUser["name"]) ? $creatorUser["name"] : $creatorUser["user"];
    }
}

$whatsappEntidadesStr   = empty($whatsappEntidades) ? "—" : implode(", ", $whatsappEntidades);
$whatsappPrioridadesStr = empty($whatsappPrioridades) ? "—" : implode(", ", $whatsappPrioridades);
$whatsappCreadorStr     = $whatsappCreador ?: "—";
$whatsappFechaStr       = !empty($ticket["fecha"]) ? date("d/m/Y H:i", strtotime($ticket["fecha"])) : "—";
$whatsappTicketId       = (int)$ticket["id"];
$whatsappSolicitante    = $ticket["solicitante"] ?? "—";
?>

<style>
    .tstatus-comment {
        display: flex;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 14px;
        background: linear-gradient(135deg, #f8f9ff 0%, #eef3ff 100%);
        border: 1px solid rgba(13,110,253,0.15);
        position: relative;
        transition: box-shadow .2s ease, transform .15s ease;
        overflow: hidden;
    }
    .tstatus-comment:hover {
        box-shadow: 0 6px 18px rgba(13,110,253,0.12);
        transform: translateY(-1px);
    }
    .tstatus-comment::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, #0d6efd, #6ea8fe);
    }
    .tstatus-comment__icon {
        flex-shrink: 0;
        width: 38px; height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0d6efd, #4d8bfd);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        box-shadow: 0 4px 10px rgba(13,110,253,0.35);
    }
    .tstatus-comment__body { flex: 1; min-width: 0; }
    .tstatus-comment__header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }
    .tstatus-comment__label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #0d6efd;
    }
    .tstatus-comment__time {
        font-size: 0.72rem;
        color: #8a94a6;
        margin-left: auto;
    }
    .tstatus-comment__text {
        color: #2c3345;
        line-height: 1.5;
        font-size: 0.92rem;
        white-space: pre-wrap;
        word-break: break-word;
    }
    [data-bs-theme="dark"] .tstatus-comment {
        background: linear-gradient(135deg, #1b2233 0%, #1f2740 100%);
        border-color: rgba(110,168,254,0.2);
    }
    [data-bs-theme="dark"] .tstatus-comment__text { color: #dde3ee; }
    [data-bs-theme="dark"] .tstatus-comment__time { color: #98a1b3; }

    .tstatus-readtime {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: #0f5132;
        background: rgba(25,135,84,0.12);
        border: 1px solid rgba(25,135,84,0.25);
        border-radius: 999px;
        padding: 1px 8px;
        margin-left: 4px;
    }
    [data-bs-theme="dark"] .tstatus-readtime {
        color: #75d9a3;
        background: rgba(25,135,84,0.18);
        border-color: rgba(25,135,84,0.35);
    }

    .tstatus-pendingtime {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: #7a5300;
        background: rgba(255,193,7,0.18);
        border: 1px solid rgba(255,193,7,0.45);
        border-radius: 999px;
        padding: 1px 8px;
        margin-left: 4px;
    }
    [data-bs-theme="dark"] .tstatus-pendingtime {
        color: #ffce4d;
        background: rgba(255,193,7,0.16);
        border-color: rgba(255,193,7,0.4);
    }

    /* Chip "sin cambios hace X" (status N/R ya aceptado) */
    .tstatus-waitingtime {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: #055160;
        background: rgba(13,202,240,0.15);
        border: 1px solid rgba(13,202,240,0.40);
        border-radius: 999px;
        padding: 1px 8px;
        margin-left: 4px;
    }
    [data-bs-theme="dark"] .tstatus-waitingtime {
        color: #6ee7f5;
        background: rgba(13,202,240,0.18);
        border-color: rgba(13,202,240,0.35);
    }

    .tstatus-finalized-alert {
        border-left: 4px solid #198754;
        background: rgba(25,135,84,0.06);
    }
    [data-bs-theme="dark"] .tstatus-finalized-alert {
        background: rgba(25,135,84,0.12);
    }
</style>

<!-- ═══════════════════════════════════════════════════════════
     SECCIÓN STATUS REGISTRADOS
     ═══════════════════════════════════════════════════════════ -->

<div class="d-flex justify-content-between align-items-center mb-3 mt-4">
    <h5 class="mb-0">
        <i class="fas fa-tasks me-2 text-primary"></i> Registros de Status
        <span class="badge text-bg-primary ms-2"><?php echo count($tstatusesFiltrados); ?></span>
    </h5>

    <?php if ($ticketFinalizadoTs): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="Ticket finalizado: no se pueden agregar statuses">
            <i class="fas fa-lock me-1"></i> Ticket finalizado
        </button>
    <?php elseif (!$permisoBaseTs): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="Solo Administrador, Centro de Atención o usuarios asignados al ticket">
            <i class="fas fa-lock me-1"></i> Solo lectura
        </button>
    <?php else: ?>
        <button type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalCrearTstatus">
            <i class="fas fa-plus me-1"></i> Agregar Status
        </button>
    <?php endif; ?>
</div>

<?php if ($ticketFinalizadoTs): ?>
    <div class="alert alert-success d-flex align-items-center mb-3 tstatus-finalized-alert" role="alert">
        <i class="fas fa-lock me-2"></i>
        <div>
            <strong>Ticket finalizado.</strong>
            No se pueden agregar, editar ni eliminar registros de status.
        </div>
    </div>
<?php elseif ($esUsuarioSinCoordTs && !$esAdminOCentroTs): ?>
    <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-user-lock me-2"></i>
        <div>
            <strong>Modo solo lectura.</strong>
            Tienes el rol <strong>Usuario</strong> y <strong>no tienes ninguna coordinación asignada</strong>,
            por lo que no puedes modificar los statuses de este ticket.<br>
            <small class="text-muted">
                Si necesitas modificar el historial, solicita a un administrador que te asigne una coordinación.
            </small>
        </div>
    </div>
<?php elseif (!$permisoBaseTs && $esCoordConCoordAsignadaTs): ?>
    <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-eye me-2"></i>
        <div>
            <strong>Modo solo lectura.</strong>
            Estás viendo este ticket porque tu <strong>coordinación está asignada</strong>,
            pero para <strong>agregar o editar statuses</strong> es necesario que tu
            <strong>usuario sea agregado al ticket</strong> (pestaña Asignaciones → Usuarios).
        </div>
    </div>
<?php elseif (!$permisoBaseTs): ?>
    <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-user-shield me-2"></i>
        <div>
            <strong>No tienes permisos para modificar los statuses de este ticket.</strong><br>
            Solo el <strong>Administrador</strong>, <strong>Centro de Atención</strong>
            o los <strong>usuarios asignados al ticket</strong> pueden hacerlo.
        </div>
    </div>
<?php endif; ?>

<?php if(empty($tstatusesFiltrados)): ?>

    <div class="alert alert-info d-flex align-items-center mb-0">
        <i class="fas fa-info-circle me-2"></i>
        <div>
            <?php if($ticketFinalizadoTs): ?>
                No hay registros de status en este ticket.
            <?php else: ?>
                Aún no hay registros de status en este ticket.
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>

    <?php foreach($tstatusesFiltrados as $t):

        $creador = $mapaUsuarios[(int)$t["tstatus"]] ?? null;
        $nombreCreador = tstatusNombreUsuario($creador);
        if($nombreCreador === null){
            $nombreCreador = "Usuario #" . $t["tstatus"];
        }
        $colorCreador  = $creador ? tstatusColorUsuario($creador, $mapaRoles) : "#6c757d";
        $iniCreador    = tstatusIniciales($nombreCreador);

        $fechaCreacion = !empty($t["fecha"])
            ? date("d/m/Y H:i", strtotime($t["fecha"]))
            : (!empty($t["modificacion"]) ? date("d/m/Y H:i", strtotime($t["modificacion"])) : "—");

        $fechaComentario = !empty($t["modificacion"])
            ? date("d/m/Y H:i", strtotime($t["modificacion"]))
            : "—";

        $idStatus    = (int)$t["id_status"];
        $statusData  = $mapaStatus[$idStatus] ?? null;

        $esNotificable = $statusData && in_array($statusData["status"], ["Nuevo", "Reemplazo"]);
        $estadoNotif   = (int)($t["estado"] ?? 0);
        $checkNotif    = (int)($t["check"]  ?? 0);

        $idUserNotif  = (int)($t["id_user"] ?? 0);
        $usuarioNotif = $idUserNotif > 0 ? ($mapaUsuarios[$idUserNotif] ?? null) : null;

        $nombreEspecialista = "—";
        if ($usuarioNotif) {
            $nombreEspecialista = tstatusNombreUsuario($usuarioNotif) ?: "—";
        } elseif ($idUserNotif > 0) {
            $nombreEspecialista = "Usuario #" . $idUserNotif;
        }

        $tiempoLectura = '';
        if($checkNotif === 1 && !empty($t["fecha_check"]) && !empty($t["fecha"])){
            $tiempoLectura = tstatusTiempoTranscurrido($t["fecha"], $t["fecha_check"]);
        }

        $tiempoPendiente = '';
        if($checkNotif === 0 && !empty($t["fecha"])){
            $tiempoPendiente = tstatusTiempoDesdeAhora($t["fecha"]);
        }

        /* ¿Este registro es el último visible del ticket? */
        $esUltimoTstatus = ((int)$t["id"] === $idUltimoTstatus);

        /* Chip "sin cambios hace X" */
        $tiempoSinCambios = '';
        if ($esNotificable
            && $checkNotif === 1
            && $esUltimoTstatus
            && !$ticketFinalizadoTs
            && !empty($t["fecha_check"])) {
            $tiempoSinCambios = tstatusTiempoDesdeAhora($t["fecha_check"]);
        }

        /* Reglas de visibilidad por fila */
        $mostrarBotonEstado   = $esNotificable && !$ticketFinalizadoTs && $esAdminOCentroTs;
        $mostrarBotonEditar   = $puedeModificarTs && (!$esNotificable || $esAdminOCentroTs);
        $mostrarBotonEliminar = !$ticketFinalizadoTs && $esAdminTs;

        /* WhatsApp: SOLO Administrador o Centro de Atención */
        $puedeVerWhatsappTstatus = $esAdminOCentroTs
                                && $esNotificable
                                && !$ticketFinalizadoTs;
    ?>

    <article class="d-flex gap-3 mb-3 p-3 border rounded bg-body-tertiary" data-tstatus-id="<?php echo (int)$t["id"]; ?>">
        <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm"
             style="width: 45px; height: 45px; background-color: <?php echo htmlspecialchars($colorCreador); ?>; font-weight: 600;"
             aria-hidden="true">
            <?php echo htmlspecialchars($iniCreador); ?>
        </div>

        <div class="flex-grow-1">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h4 class="h6 mb-0">
                        <i class="fas fa-tasks me-1 text-success"></i>
                        <?php echo htmlspecialchars($nombreCreador); ?>
                        <small class="text-muted ms-2">registró un status en este ticket</small>
                    </h4>
                    <small class="text-secondary">
                        <i class="fas fa-clock me-1"></i><?php echo $fechaCreacion; ?>
                    </small>
                </div>

                <?php if($mostrarBotonEstado || $puedeVerWhatsappTstatus || $mostrarBotonEditar || $mostrarBotonEliminar): ?>
                <div class="d-flex gap-2 flex-wrap">

                    <?php if($mostrarBotonEstado): ?>
                        <button type="button"
                                class="btn btn-sm btnToggleEstadoTstatus <?php echo $estadoNotif === 0 ? 'btn-success' : 'btn-secondary'; ?>"
                                data-id="<?php echo (int)$t["id"]; ?>"
                                data-estado="<?php echo $estadoNotif; ?>"
                                title="<?php echo $estadoNotif === 0 ? 'Desactivar notificación' : 'Activar notificación'; ?>">
                            <?php if($estadoNotif === 0): ?>
                                <i class="fas fa-check-circle"></i> Activo
                            <?php else: ?>
                                <i class="fas fa-times-circle"></i> Desactivado
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>

                    <?php if($puedeVerWhatsappTstatus): ?>
                        <button type="button"
                                class="btn btn-sm btn-success btnWhatsappTstatus"
                                data-ticket="<?php echo $whatsappTicketId; ?>"
                                data-solicitante="<?php echo htmlspecialchars($whatsappSolicitante); ?>"
                                data-entidades="<?php echo htmlspecialchars($whatsappEntidadesStr); ?>"
                                data-prioridades="<?php echo htmlspecialchars($whatsappPrioridadesStr); ?>"
                                data-creador="<?php echo htmlspecialchars($whatsappCreadorStr); ?>"
                                data-fecha="<?php echo htmlspecialchars($whatsappFechaStr); ?>"
                                data-especialista="<?php echo htmlspecialchars($nombreEspecialista); ?>"
                                title="Compartir resumen del ticket por WhatsApp (solo Admin / Centro de Atención)">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </button>
                    <?php endif; ?>

                    <?php if($mostrarBotonEditar || $mostrarBotonEliminar): ?>
                    <div class="btn-group">
                        <?php if($mostrarBotonEditar): ?>
                        <button type="button"
                                class="btn btn-sm btn-warning btnEditarTstatus"
                                data-id="<?php echo (int)$t["id"]; ?>"
                                title="Editar">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <?php endif; ?>

                        <?php if($mostrarBotonEliminar): ?>
                        <button type="button"
                                class="btn btn-sm btn-danger btnEliminarTstatus"
                                data-id="<?php echo (int)$t["id"]; ?>"
                                title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endif; ?>
            </div>

            <div class="mt-2">
                <?php if($statusData): ?>
                    <span class="badge"
                          style="background-color:<?php echo htmlspecialchars($statusData["color"] ?: "#0d6efd"); ?>; color:#fff; padding:7px 14px; font-size:0.85rem;">
                        <i class="fas fa-tasks me-1"></i><?php echo htmlspecialchars($statusData["status"]); ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary">ID <?php echo $idStatus; ?></span>
                <?php endif; ?>
            </div>

            <?php if($esNotificable && $usuarioNotif): ?>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-bell me-1 text-warning"></i>
                        Notificar a:
                        <strong><?php echo htmlspecialchars(tstatusNombreUsuario($usuarioNotif)); ?></strong>

                        <?php if($checkNotif === 1): ?>
                            <span class="badge text-bg-success ms-1" style="font-size:0.65rem;">
                                <i class="fas fa-check"></i> Leída
                            </span>

                            <?php if($tiempoLectura !== ''): ?>
                                <span class="tstatus-readtime" title="Tiempo transcurrido desde su creación hasta ser aceptada">
                                    <i class="far fa-hourglass-half"></i>
                                    en <?php echo htmlspecialchars($tiempoLectura); ?>
                                </span>
                            <?php endif; ?>

                            <?php if($tiempoSinCambios !== ''): ?>
                                <span class="tstatus-waitingtime"
                                      data-desde="<?php echo htmlspecialchars($t["fecha_check"]); ?>"
                                      title="Tiempo transcurrido desde que fue aceptada, sin cambiar a otro status">
                                    <i class="fas fa-hourglass-end"></i>
                                    <span class="tstatus-waitingtime__text">hace <?php echo htmlspecialchars($tiempoSinCambios); ?> sin cambios</span>
                                </span>
                            <?php endif; ?>

                        <?php else: ?>
                            <span class="badge text-bg-warning ms-1" style="font-size:0.65rem;">
                                <i class="fas fa-clock"></i> Pendiente
                            </span>
                            <?php if($tiempoPendiente !== ''): ?>
                                <span class="tstatus-pendingtime"
                                      data-desde="<?php echo htmlspecialchars($t["fecha"]); ?>"
                                      title="Tiempo desde que se creó la notificación">
                                    <i class="far fa-hourglass-half"></i>
                                    <span class="tstatus-pendingtime__text">hace <?php echo htmlspecialchars($tiempoPendiente); ?></span>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </small>
                </div>
            <?php endif; ?>

            <?php if(!empty($t["comentario"])): ?>
                <div class="mt-3">
                    <div class="tstatus-comment">
                        <div class="tstatus-comment__icon">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        <div class="tstatus-comment__body">
                            <div class="tstatus-comment__header">
                                <span class="tstatus-comment__label">Comentario</span>
                                <span class="tstatus-comment__time">
                                    <i class="far fa-clock me-1"></i><?php echo $fechaComentario; ?>
                                </span>
                            </div>
                            <div class="tstatus-comment__text"><?php echo htmlspecialchars($t["comentario"]); ?></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-2">
                    <small class="text-muted fst-italic">
                        <i class="fas fa-comment-slash me-1"></i>Sin comentario
                    </small>
                </div>
            <?php endif; ?>

        </div>
    </article>

    <?php endforeach; ?>

<?php endif; ?>

<?php if ($puedeModificarTs): ?>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearTstatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formCrearTstatus" method="post">
                <input type="hidden" name="nuevoTstatus" value="1">
                <input type="hidden" name="idTicket" value="<?php echo (int)$idTicket; ?>">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-tasks me-2"></i> Agregar Status al Ticket
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-tasks me-1 text-muted"></i>
                            Status <span class="text-danger">*</span>
                        </label>
                        <select id="nuevoTstatusIdStatus"
                                name="nuevoIdStatus"
                                placeholder="Seleccione un status..."></select>
                        <small class="text-muted">
                            Solo se muestran los status activos.
                            <?php if(!$esAdminOCentroTs): ?>
                                <strong>Nuevo</strong> y <strong>Reemplazo</strong> solo están disponibles para Administrador y Centro de Atención.
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="mb-3 d-none" id="wrapUsuarioNotif">
                        <label class="form-label">
                            <i class="fas fa-bell me-1 text-warning"></i>
                            Usuario a notificar <span class="text-danger">*</span>
                        </label>
                        <select id="nuevoIdUserNotif"
                                name="nuevoIdUserNotif"
                                placeholder="Seleccione el usuario..."></select>
                        <small class="text-muted">
                            Solo se muestran usuarios activos. El número a la derecha indica
                            los tickets <strong>sin finalizar</strong> que tiene asignados.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-comment-alt me-1 text-muted"></i>
                            Comentario
                            <span id="nuevoComentarioBadge" class="text-muted small">(opcional)</span>
                        </label>
                        <textarea class="form-control"
                                  name="nuevoComentario"
                                  id="nuevoComentario"
                                  rows="4"
                                  maxlength="2000"
                                  placeholder="Escriba un comentario sobre este cambio de status..."></textarea>
                        <small class="text-muted">
                            Máximo 2000 caracteres.
                            <span id="nuevoComentarioHint" class="d-block mt-1"></span>
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarTstatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formEditarTstatus" method="post">
                <input type="hidden" name="editarTstatus" value="1">
                <input type="hidden" name="idTstatus" id="idTstatus">

                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i> Editar Registro de Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-tasks me-1 text-muted"></i>
                            Status <span class="text-danger">*</span>
                        </label>
                        <select id="editarTstatusIdStatus"
                                name="editarIdStatus"
                                placeholder="Seleccione un status..."></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-comment-alt me-1 text-muted"></i>
                            Comentario
                            <span id="editarComentarioBadge" class="text-muted small">(opcional)</span>
                        </label>
                        <textarea class="form-control"
                                  name="editarComentario"
                                  id="editarComentario"
                                  rows="4"
                                  maxlength="2000"
                                  placeholder="Escriba un comentario..."></textarea>
                        <small class="text-muted">
                            Máximo 2000 caracteres.
                            <span id="editarComentarioHint" class="d-block mt-1"></span>
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ═══════════════ SCRIPTS ═══════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    const STATUS_NOTIFICABLES         = <?php echo json_encode($statusNotificables); ?>;
    /* Statuses que exigen comentario (todos excepto Suspendido, En Proceso y Nuevo) */
    const STATUS_REQUIEREN_COMENTARIO = <?php echo json_encode($statusesRequierenComentario); ?>;

    /* Helpers para exigir/liberar el comentario según el status seleccionado */
    function aplicarRequeridoComentario(idStatus, textarea, badge, hint){
        if(!textarea || !badge) return;

        const idNum = parseInt(idStatus) || 0;
        if(idNum <= 0){
            textarea.required = false;
            badge.textContent = '(opcional)';
            badge.className   = 'text-muted small';
            if(hint){ hint.textContent = ''; hint.className = 'd-block mt-1'; }
            return;
        }

        const requiere = STATUS_REQUIEREN_COMENTARIO.includes(idNum);
        textarea.required = requiere;

        if(requiere){
            badge.textContent = '(obligatorio)';
            badge.className   = 'text-danger small fw-bold';
            if(hint){
                hint.innerHTML = '<i class="fas fa-info-circle me-1"></i> Este status requiere comentario.';
                hint.className = 'd-block mt-1 text-danger';
            }
        } else {
            badge.textContent = '(opcional)';
            badge.className   = 'text-muted small';
            if(hint){
                hint.innerHTML = '<i class="fas fa-info-circle me-1"></i> El comentario es opcional para este status.';
                hint.className = 'd-block mt-1 text-muted';
            }
        }
    }

    function actualizarComentarioNuevo(){
        const select   = document.getElementById('nuevoTstatusIdStatus');
        const textarea = document.getElementById('nuevoComentario');
        const badge    = document.getElementById('nuevoComentarioBadge');
        const hint     = document.getElementById('nuevoComentarioHint');
        if(!select) return;
        aplicarRequeridoComentario(select.value, textarea, badge, hint);
    }

    function actualizarComentarioEditar(){
        const select   = document.getElementById('editarTstatusIdStatus');
        const textarea = document.getElementById('editarComentario');
        const badge    = document.getElementById('editarComentarioBadge');
        const hint     = document.getElementById('editarComentarioHint');
        if(!select) return;
        aplicarRequeridoComentario(select.value, textarea, badge, hint);
    }

    /* ═══════════════════════════════════════════════════════════
       Botón WhatsApp (comparte resumen del ticket)
       ═══════════════════════════════════════════════════════════ */
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btnWhatsappTstatus');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        const ticketId     = btn.getAttribute('data-ticket')     || '';
        const solicitante  = btn.getAttribute('data-solicitante')|| '—';
        const entidades    = btn.getAttribute('data-entidades')  || '—';
        const prioridades  = btn.getAttribute('data-prioridades')|| '—';
        const creador      = btn.getAttribute('data-creador')    || '—';
        const fecha        = btn.getAttribute('data-fecha')      || '—';
        const especialista = btn.getAttribute('data-especialista')|| '—';

        const baseUrl = window.location.origin + window.location.pathname;
        const urlTicket = baseUrl + '?ruta=ticket&id=' + ticketId;

        let mensaje = "📋 *RESUMEN DEL TICKET - AIT*\n";
        mensaje += "━━━━━━━━━━━━━\n\n";
        mensaje += "🆔 *Ticket:* #" + ticketId + "\n";
        mensaje += "👤 *Solicitante:* " + solicitante + "\n";
        mensaje += "🏢 *Entidad(es):* " + entidades + "\n";
        mensaje += "⚡ *Prioridad(es):* " + prioridades + "\n";
        mensaje += "👨‍💻 *Creado por:* " + creador + "\n";
        mensaje += "📅 *Fecha de creación:* " + fecha + "\n";
        mensaje += "🔔 *Especialista asignado:* " + especialista + "\n\n";
        mensaje += "━━━━━━━━━━━━━\n";
        mensaje += "🔗 *Ticket en línea:* " + urlTicket;

        const encoded = encodeURIComponent(mensaje);

        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const whatsappUrl = isMobile
            ? "https://api.whatsapp.com/send?text=" + encoded
            : "https://web.whatsapp.com/send?text=" + encoded;

        window.open(whatsappUrl, "_blank");
    });

    /*=============================================
    Tom Select – render con color (SINGLE SELECT)
    =============================================*/
    function renderOpcion(data, escape){
        const color = data.color || '#0d6efd';
        return `<div><span class="tag-dot" style="background-color:${color};"></span><span class="option-label">${escape(data.text)}</span></div>`;
    }
    function renderItem(data, escape){
        const color = data.color || '#0d6efd';
        return `<div style="--tag-color:${color};" class="item-tag">${escape(data.text)}</div>`;
    }

    const tomSelectConfig = {
        persist: false,
        create: false,
        maxItems: 1,
        valueField: 'id',
        labelField: 'text',
        searchField: 'text',
        render: { option: renderOpcion, item: renderItem }
    };

    let tsNuevoStatus    = null;
    let tsEditarStatus   = null;
    let tsNuevoUserNotif = null;
    let cacheStatus      = null;
    let pendingData      = null;

    function fetchStatus(){
        if(cacheStatus) return Promise.resolve(cacheStatus);
        return fetch('ajax/tstatus.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarStatusActivos','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheStatus = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheStatus; })
        .catch(() => []);
    }

    function addOptions(ts, data){
        data.forEach(item => {
            if(!ts.options[String(item.id)]){
                ts.addOption({ id: String(item.id), text: item.texto, color: item.color });
            }
        });
        ts.refreshOptions(false);
    }

    function setTSSingleValue(ts, id){
        if(id === null || id === undefined || id === '') return;
        const idStr = String(id);
        if(!ts.options[idStr]){
            ts.addOption({ id: idStr, text: 'ID '+idStr, color: '#6c757d' });
        }
        ts.setValue(idStr, true);
        ts.refreshOptions(false);
    }

    function createRemoteUserSelect(){
        if(tsNuevoUserNotif) return tsNuevoUserNotif;

        const state = {
            currentQuery: '',
            currentPage: 0,
            hasMore: true,
            isLoading: false
        };

        function fetchUsersPage(page, query){
            const fd = new FormData();
            fd.append('listarUsuariosConTicketsPaginado', '1');
            fd.append('page', page);
            fd.append('q', query || '');
            return fetch('ajax/tuser.ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .catch(() => ({ status: 'error', data: [], has_more: false }));
        }

        tsNuevoUserNotif = new TomSelect('#nuevoIdUserNotif', {
            valueField: 'id',
            labelField: 'texto',
            searchField: 'texto',
            maxItems: 1,
            create: false,
            persist: false,
            loadThrottle: 300,

            load: function(query, callback){
                if(query !== state.currentQuery){
                    state.currentQuery = query;
                    state.currentPage  = 0;
                    state.hasMore      = true;
                }
                if(!state.hasMore){
                    callback();
                    return;
                }
                state.isLoading = true;
                state.currentPage++;
                fetchUsersPage(state.currentPage, query).then(resp => {
                    state.isLoading = false;
                    const items = (resp.status === 'ok' && Array.isArray(resp.data)) ? resp.data : [];
                    callback(items);
                    state.hasMore = !!resp.has_more;
                });
            },

            render: {
                option: function(data, escape){
                    const color = data.color || '#0d6efd';
                    const count = data.ticket_count || 0;
                    const badgeColor = count > 4 ? '#dc3545' : '#198754';
                    const badgeTitle = count === 1
                        ? '1 ticket sin finalizar'
                        : count + ' tickets sin finalizar';
                    return `<div class="d-flex justify-content-between align-items-center"
                                 style="gap:10px; width:100%;">
                        <div class="d-flex align-items-center" style="min-width:0; flex:1;">
                            <span class="tag-dot" style="background-color:${color};"></span>
                            <span class="option-label text-truncate">${escape(data.texto)}</span>
                        </div>
                        <span class="badge"
                              style="background-color:${badgeColor}; color:#fff;
                                     font-size:0.7rem; min-width:28px; text-align:center;"
                              title="${badgeTitle}">
                            ${count}
                        </span>
                    </div>`;
                },
                item: function(data, escape){
                    const color = data.color || '#0d6efd';
                    const count = data.ticket_count || 0;
                    const badgeColor = count > 4 ? '#dc3545' : '#198754';
                    return `<div class="item-tag" style="--tag-color:${color};">
                        ${escape(data.texto)}
                        <span class="badge ms-1"
                              style="background-color:${badgeColor}; font-size:0.6rem;">
                            ${count}
                        </span>
                    </div>`;
                }
            }
        });

        tsNuevoUserNotif.load('');

        tsNuevoUserNotif.on('dropdown_open', function(){
            setTimeout(() => {
                const dropdown = tsNuevoUserNotif.dropdown_content;
                if(!dropdown || dropdown.dataset.scrollBound === '1') return;
                dropdown.dataset.scrollBound = '1';

                dropdown.addEventListener('scroll', function(){
                    if(this.scrollTop + this.clientHeight < this.scrollHeight - 30) return;
                    if(!state.hasMore || state.isLoading) return;

                    state.isLoading = true;
                    state.currentPage++;

                    const q = state.currentQuery;
                    fetchUsersPage(state.currentPage, q).then(resp => {
                        state.isLoading = false;
                        const items = (resp.status === 'ok' && Array.isArray(resp.data))
                            ? resp.data
                            : [];
                        items.forEach(item => {
                            if(!tsNuevoUserNotif.options[String(item.id)]){
                                tsNuevoUserNotif.addOption(item);
                            }
                        });
                        tsNuevoUserNotif.refreshOptions(false);
                        state.hasMore = !!resp.has_more;
                    }).catch(() => { state.isLoading = false; });
                });
            }, 100);
        });

        tsNuevoUserNotif.resetRemote = function(){
            state.currentQuery = '';
            state.currentPage  = 0;
            state.hasMore      = true;
            state.isLoading    = false;
            tsNuevoUserNotif.clearOptions();
            tsNuevoUserNotif.load('');
        };

        return tsNuevoUserNotif;
    }

    /*=============================================
    Chips en vivo cada 60 s
    =============================================*/
    function formatearTiempoDesde(fechaStr){
        if(!fechaStr) return '—';
        const fecha = new Date(fechaStr.replace(' ', 'T'));
        const ahora = new Date();
        let diffSeg = Math.floor((ahora - fecha) / 1000);
        if(diffSeg < 0) diffSeg = 0;

        const min = Math.floor(diffSeg / 60);
        if(min < 1)  return 'menos de 1 min';
        if(min < 60) return min + ' min';

        const hrs     = Math.floor(min / 60);
        const minRest = min % 60;
        if(hrs < 24){
            return hrs + ' h' + (minRest > 0 ? ' ' + minRest + ' min' : '');
        }

        const d       = Math.floor(hrs / 24);
        const hrsRest = hrs % 24;
        return d + ' d' + (hrsRest > 0 ? ' ' + hrsRest + ' h' : '');
    }

    function refrescarTiemposPendientes(){
        document.querySelectorAll('.tstatus-pendingtime').forEach(function(el){
            const desde = el.getAttribute('data-desde');
            if(!desde) return;
            const txt = el.querySelector('.tstatus-pendingtime__text');
            if(!txt) return;
            txt.textContent = 'hace ' + formatearTiempoDesde(desde);
        });
    }

    function refrescarTiemposSinCambios(){
        document.querySelectorAll('.tstatus-waitingtime').forEach(function(el){
            const desde = el.getAttribute('data-desde');
            if(!desde) return;
            const txt = el.querySelector('.tstatus-waitingtime__text');
            if(!txt) return;
            txt.textContent = 'hace ' + formatearTiempoDesde(desde) + ' sin cambios';
        });
    }

    function refrescarTodosLosTiempos(){
        refrescarTiemposPendientes();
        refrescarTiemposSinCambios();
    }

    refrescarTodosLosTiempos();
    let intervalTiempos = setInterval(refrescarTodosLosTiempos, 60000);

    document.addEventListener('visibilitychange', function(){
        if(document.hidden){
            clearInterval(intervalTiempos);
        } else {
            refrescarTodosLosTiempos();
            intervalTiempos = setInterval(refrescarTodosLosTiempos, 60000);
        }
    });

    /*=============================================
    MODAL CREAR
    =============================================*/
    const modalCrearEl = document.getElementById('modalCrearTstatus');
    if(modalCrearEl){
        modalCrearEl.addEventListener('shown.bs.modal', async () => {
            if(!tsNuevoStatus){
                const data = await fetchStatus();
                tsNuevoStatus = new TomSelect('#nuevoTstatusIdStatus', Object.assign({}, tomSelectConfig));
                addOptions(tsNuevoStatus, data);

                tsNuevoStatus.on('change', function(value){
                    const wrap  = document.getElementById('wrapUsuarioNotif');
                    const idNum = parseInt(value);

                    if(STATUS_NOTIFICABLES.includes(idNum)){
                        wrap.classList.remove('d-none');
                        createRemoteUserSelect();
                    } else {
                        wrap.classList.add('d-none');
                        if(tsNuevoUserNotif) tsNuevoUserNotif.clear(true);
                    }

                    actualizarComentarioNuevo();
                });
            }

            if(tsNuevoUserNotif && typeof tsNuevoUserNotif.resetRemote === 'function'){
                tsNuevoUserNotif.resetRemote();
            }

            tsNuevoStatus.refreshOptions(false);

            /* Al abrir el modal, el comentario arranca como opcional (sin status) */
            actualizarComentarioNuevo();
        });

        modalCrearEl.addEventListener('hidden.bs.modal', () => {
            if(tsNuevoStatus)    tsNuevoStatus.clear(true);
            if(tsNuevoUserNotif) tsNuevoUserNotif.clear(true);
            const wrap = document.getElementById('wrapUsuarioNotif');
            if(wrap) wrap.classList.add('d-none');
            const f = document.getElementById('formCrearTstatus');
            if(f) f.reset();

            actualizarComentarioNuevo();
        });
    }

    /*=============================================
    MODAL EDITAR
    =============================================*/
    const modalEditarEl = document.getElementById('modalEditarTstatus');
    if(modalEditarEl){
        modalEditarEl.addEventListener('shown.bs.modal', async () => {
            if(!tsEditarStatus){
                const data = await fetchStatus();
                tsEditarStatus = new TomSelect('#editarTstatusIdStatus', Object.assign({}, tomSelectConfig));
                addOptions(tsEditarStatus, data);

                tsEditarStatus.on('change', function(){
                    actualizarComentarioEditar();
                });
            }
            if(pendingData){
                document.getElementById('idTstatus').value        = pendingData.id;
                document.getElementById('editarComentario').value = pendingData.comentario || '';
                setTSSingleValue(tsEditarStatus, pendingData.id_status);
                pendingData = null;

                /* El setValue es silencioso, así que forzamos el chequeo */
                actualizarComentarioEditar();
            }
            tsEditarStatus.refreshOptions(false);
        });
    }

    /*=============================================
    SUBMIT CREAR
    =============================================*/
    const formCrear = document.getElementById('formCrearTstatus');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();

            /* Validación explícita por si el navegador omite el required */
            const idStatusSel   = parseInt(document.getElementById('nuevoTstatusIdStatus').value) || 0;
            const comentarioVal = (document.getElementById('nuevoComentario')?.value || '').trim();
            if(idStatusSel > 0
               && STATUS_REQUIEREN_COMENTARIO.includes(idStatusSel)
               && comentarioVal === ''){
                Toast.fire({ icon: 'warning', title: 'El comentario es obligatorio para este status' });
                document.getElementById('nuevoComentario')?.focus();
                return;
            }

            const formData = new FormData(this);

            Swal.fire({
                title: 'Guardando registro...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/tstatus.ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(resp => {
                    Swal.close();
                    if(resp.status === 'success'){
                        Toast.fire({ icon: 'success', title: resp.mensaje || 'Guardado' });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Toast.fire({ icon: 'error', title: resp.mensaje || 'Error al guardar' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.close();
                    Toast.fire({ icon: 'error', title: 'Error de conexión con el servidor' });
                });
        });
    }

    /*=============================================
    SUBMIT EDITAR
    =============================================*/
    const formEditar = document.getElementById('formEditarTstatus');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();

            /* Validación explícita por si el navegador omite el required */
            const idStatusSel   = parseInt(document.getElementById('editarTstatusIdStatus').value) || 0;
            const comentarioVal = (document.getElementById('editarComentario')?.value || '').trim();
            if(idStatusSel > 0
               && STATUS_REQUIEREN_COMENTARIO.includes(idStatusSel)
               && comentarioVal === ''){
                Toast.fire({ icon: 'warning', title: 'El comentario es obligatorio para este status' });
                document.getElementById('editarComentario')?.focus();
                return;
            }

            const formData = new FormData(this);

            Swal.fire({
                title: 'Actualizando registro...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/tstatus.ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(resp => {
                    Swal.close();
                    if(resp.status === 'success'){
                        Toast.fire({ icon: 'success', title: resp.mensaje || 'Actualizado' });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Toast.fire({ icon: 'error', title: resp.mensaje || 'Error al actualizar' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.close();
                    Toast.fire({ icon: 'error', title: 'Error de conexión con el servidor' });
                });
        });
    }

    /*=============================================
    BOTÓN ACTIVAR / DESACTIVAR
    =============================================*/
    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.btnToggleEstadoTstatus');
        if(!btn) return;

        e.preventDefault();
        const id            = btn.getAttribute('data-id');
        const estadoActual  = parseInt(btn.getAttribute('data-estado')) || 0;
        const nuevoEstado   = estadoActual === 1 ? 0 : 1;

        if(!id) return;

        btn.disabled = true;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const fd = new FormData();
            fd.append('cambiarEstadoTstatus', '1');
            fd.append('idTstatus', id);
            fd.append('nuevoEstado', nuevoEstado);

            const res  = await fetch('ajax/tstatus.ajax.php', { method: 'POST', body: fd });
            const data = await res.json();

            if(data.status === 'success'){
                Toast.fire({ icon: 'success', title: data.mensaje || 'Estado actualizado' });
                setTimeout(() => location.reload(), 1200);
            } else {
                Toast.fire({ icon: 'error', title: data.mensaje || 'Error al actualizar' });
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        } catch(err) {
            console.error(err);
            Toast.fire({ icon: 'error', title: 'Error de conexión' });
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });

    /*=============================================
    BOTÓN EDITAR
    =============================================*/
    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.btnEditarTstatus');
        if(!btn) return;

        e.preventDefault();
        const id = btn.getAttribute('data-id');
        if(!id) return;

        const modalEl = document.getElementById('modalEditarTstatus');
        if(!modalEl) return;

        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const fd = new FormData();
            fd.append('idTstatus', id);

            const res  = await fetch('ajax/tstatus.ajax.php', { method: 'POST', body: fd });
            const data = await res.json();

            if(data && data.id){
                pendingData = data;
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
                Toast.fire({ icon: 'error', title: (data && data.mensaje) || 'No se pudo cargar el registro' });
            }
        } catch(err) {
            console.error(err);
            Toast.fire({ icon: 'error', title: 'Error de conexión' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });

    /*=============================================
    BOTÓN ELIMINAR
    =============================================*/
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.btnEliminarTstatus');
        if(!btn) return;

        e.preventDefault();
        const id = btn.getAttribute('data-id');
        if(!id) return;

        Swal.fire({
            title: '¿Eliminar registro?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if(result.isConfirmed){
                const fd = new FormData();
                fd.append('borrarTstatus', '1');
                fd.append('idTstatus', id);

                fetch('ajax/tstatus.ajax.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(resp => {
                        if(resp.status === 'success'){
                            Toast.fire({ icon: 'success', title: 'Eliminado' });
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            Toast.fire({ icon: 'error', title: resp.mensaje || 'Error al eliminar' });
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Toast.fire({ icon: 'error', title: 'Error de conexión' });
                    });
            }
        });
    });

});
</script>