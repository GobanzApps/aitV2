<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

// Obtener el ID del ticket
$idTicket = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
if($idTicket <= 0){
    echo '<div class="alert alert-danger">ID de ticket no válido.</div>';
    return;
}

/* ═══════════════════════════════════════════════════════════
   ✅ Autorización de acceso al detalle del ticket
   ═══════════════════════════════════════════════════════════ */
$authTicket = ControladorTicket::authTicket();

if (!$authTicket) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Sesión no válida.</div>';
    return;
}

$tieneRolPermitido = ControladorTicket::esAdminTicket($authTicket)
                  || ControladorTicket::esCoordinadorTicket($authTicket)
                  || ControladorTicket::esUsuarioTicket($authTicket)
                  || ControladorTicket::esEspecialistaTicket($authTicket);

if (!$tieneRolPermitido) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No tienes permisos para acceder a la gestión de tickets.
          </div>';
    return;
}

$autorizacion = ControladorTicket::puedeVerTicketEspecifico($idTicket, $authTicket);
if (empty($autorizacion["ok"])) {
    echo '<div class="alert alert-warning">
            <i class="fas fa-lock me-2"></i>
            <strong>Acceso denegado:</strong> ' . htmlspecialchars($autorizacion["msg"]) . '
            <br><br>
            <a href="index.php?ruta=tickets" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver al listado
            </a>
          </div>';
    return;
}

/* ═══════════════════════════════════════════════════════════
   Cargar datos del ticket
   ═══════════════════════════════════════════════════════════ */
$ticket = ControladorTicket::ctrMostrarTicket("id", $idTicket);
if(!$ticket){
    echo '<div class="alert alert-danger">Ticket no encontrado.</div>';
    return;
}

$puedeModificar = ControladorTicket::puedeModificarTickets($authTicket);

$usuarioData = ControladorUser::ctrMostrarUser("id", $ticket["id_user"]);
$nombreUsuario = $usuarioData ? $usuarioData["user"] : "—";

$modificacion = !empty($ticket["modificacion"])
    ? date("d/m/Y H:i", strtotime($ticket["modificacion"]))
    : "—";

$checkActual = (int)($ticket["check"] ?? 0);

/* ═══════════════════════════════════════════════════════════
   ✅ NUEVO: ¿Puede generar el PDF del ticket?
   Reglas (deben coincidir con las de ticket-pdf.php):
     - Administrador                          → SIEMPRE
     - Coordinación "Centro de Atención"      → SIEMPRE   ← NUEVO
     - Alguna coordinación del usuario
       asignada al ticket                     → sí
   Solo aplica cuando el ticket está finalizado (check = 1).
   ═══════════════════════════════════════════════════════════ */
$puedeGenerarPDF = false;
if ($checkActual === 1 && $authTicket) {
    if (ControladorTicket::esAdminTicket($authTicket)
        || ControladorTicket::esCentroAtencionTicket($authTicket)
        || ControladorTicket::coordinacionAsignadaAlTicket($idTicket, $authTicket)) {
        $puedeGenerarPDF = true;
    }
}

$historialActual = ControladorTstatus::ctrMostrarTstatusPorTicket($idTicket);
$ultimoStatusActual = null;
if($historialActual && is_array($historialActual) && count($historialActual) > 0){
    $idSt = (int)($historialActual[0]["id_status"] ?? 0);
    if($idSt > 0){
        $stData = ControladorStatus::ctrMostrarStatus("id", $idSt);
        if($stData) $ultimoStatusActual = trim($stData["status"]);
    }
}

/* ═══════════════════════════════════════════════════════════
   ✅ Reglas adicionales para finalizar el ticket
   - Si el último status es "Resuelto" → debe existir al menos 1 servicio
   ═══════════════════════════════════════════════════════════ */
$totalServiciosTicket = 0;
$serviciosDelTicket   = ControladorTservicio::ctrMostrarTservicioPorTicket($idTicket);
if($serviciosDelTicket && is_array($serviciosDelTicket)){
    $totalServiciosTicket = count($serviciosDelTicket);
}

$ultimoStatusEsResuelto = $ultimoStatusActual !== null
                       && mb_strtolower(trim($ultimoStatusActual)) === "resuelto";

$bloqueoFinalizarSinServicios = ($checkActual === 0
                              && $puedeModificar
                              && $ultimoStatusEsResuelto
                              && $totalServiciosTicket <= 0);

/* ═══════════════════════════════════════════════════════════
   ✅ Preparamos los datos del Informe Completo (WhatsApp)
   Solo se preparan si el ticket está finalizado (check = 1)
   ═══════════════════════════════════════════════════════════ */
$whatsappData = null;
if ($checkActual === 1) {

    // ── Base URL absoluta (soporta despliegue en subcarpeta) ──
    $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
        $scriptDir = '';
    }
    $baseUrl = $protocol . '://' . $host . rtrim($scriptDir, '/\\') . '/';

    // ── Entidades ──
    $waEntidades = [];
    foreach ((json_decode($ticket['id_entidad'] ?? '[]', true) ?: []) as $idE) {
        $e = ControladorEntidad::ctrMostrarEntidad("id", (int)$idE);
        if ($e) $waEntidades[] = $e['entidad'];
    }

    // ── Medios ──
    $waMedios = [];
    foreach ((json_decode($ticket['id_medio'] ?? '[]', true) ?: []) as $idM) {
        $m = ControladorMedio::ctrMostrarMedio("id", (int)$idM);
        if ($m) $waMedios[] = $m['medio'];
    }

    // ── Creador ──
    $waCreador = 'No especificado';
    $creatorUser = ControladorUser::ctrMostrarUser("id", (int)($ticket['id_user'] ?? 0));
    if ($creatorUser) {
        $waCreador = !empty($creatorUser['name']) ? $creatorUser['name'] : $creatorUser['user'];
    }

    // ── Coordinaciones asignadas ──
    $waCoordinaciones = [];
    $tcoordsWA = ControladorTcoordinacion::ctrMostrarTcoordinacionPorTicket($idTicket);
    if ($tcoordsWA && is_array($tcoordsWA)) {
        foreach ($tcoordsWA as $tc) {
            $ids = json_decode($tc['id_coordinacion'] ?? '[]', true) ?: [];
            foreach ($ids as $idC) {
                $c = ControladorCoordinacion::ctrMostrarCoordinacion("id", (int)$idC);
                if ($c) $waCoordinaciones[] = $c['coordinacion'];
            }
        }
    }
    $waCoordinaciones = array_values(array_unique($waCoordinaciones));

    // ── Usuarios asignados ──
    $waUsuarios = [];
    $tusersWA = ControladorTuser::ctrMostrarTuserPorTicket($idTicket);
    if ($tusersWA && is_array($tusersWA)) {
        foreach ($tusersWA as $tu) {
            $ids = json_decode($tu['id_user'] ?? '[]', true) ?: [];
            foreach ($ids as $idU) {
                $u = ControladorUser::ctrMostrarUser("id", (int)$idU);
                if ($u) $waUsuarios[] = !empty($u['name']) ? $u['name'] : $u['user'];
            }
        }
    }
    $waUsuarios = array_values(array_unique($waUsuarios));

    // ── Servicios (con tags y hosts resueltos) ──
    $waServicios = [];
    $tservsWA = ControladorTservicio::ctrMostrarTservicioPorTicket($idTicket);
    if ($tservsWA && is_array($tservsWA)) {
        foreach ($tservsWA as $srv) {
            $tagsNombres = [];
            foreach ((json_decode($srv['id_tag'] ?? '[]', true) ?: []) as $idT) {
                $tag = ControladorTag::ctrMostrarTag("id", (int)$idT);
                if ($tag) $tagsNombres[] = $tag['tag'];
            }
            $hostsNombres = [];
            foreach ((json_decode($srv['id_host'] ?? '[]', true) ?: []) as $idH) {
                $host = ControladorHost::ctrMostrarHost("id", (int)$idH);
                if ($host) $hostsNombres[] = $host['host'];
            }
            $desc = html_entity_decode(strip_tags($srv['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
            $desc = preg_replace('/\s+/', ' ', $desc);
            $desc = trim($desc);

            $waServicios[] = [
                'servicio'    => implode(', ', $tagsNombres),
                'item'        => implode(', ', $hostsNombres),
                'descripcion' => $desc,
                'cantidad'    => $srv['cantidad']
            ];
        }
    }

    // ── URL del ticket ──
    $urlTicket = $baseUrl . 'index.php?ruta=ticket&id=' . $idTicket;

    // ── Empaquetar (SIN prioridad, SIN modificación, SIN docs, SIN evidencias) ──
    $whatsappData = [
        'id'             => (int)$ticket['id'],
        'entidades'      => $waEntidades,
        'solicitante'    => $ticket['solicitante'],
        'descripcion'    => $ticket['descripcion'],
        'medios'         => $waMedios,
        'creador'        => $waCreador,
        'fecha'          => !empty($ticket['fecha']) ? date('d/m/Y H:i', strtotime($ticket['fecha'])) : '—',
        'coordinaciones' => $waCoordinaciones,
        'usuarios'       => $waUsuarios,
        'servicios'      => $waServicios,
        'urlTicket'      => $urlTicket
    ];
}

/*=============================================
Helper: renderiza badges entidad/medio/prioridad
=============================================*/
function renderBadgesTicket($jsonIds, $tipo){
    $ids = json_decode($jsonIds, true);
    if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

    $html = '';
    if($tipo === 'entidad'){
        $lista = ControladorEntidad::ctrMostrarEntidad(null, null);
        $campo = 'entidad';
        $icono = 'fa-city';
    } elseif($tipo === 'medio'){
        $lista = ControladorMedio::ctrMostrarMedio(null, null);
        $campo = 'medio';
        $icono = 'fa-broadcast-tower';
    } else {
        $lista = ControladorPrioridad::ctrMostrarPrioridad(null, null);
        $campo = 'prioridad';
        $icono = 'fa-flag';
    }

    $mapa = [];
    if($lista && is_array($lista)){
        foreach($lista as $item){ $mapa[(int)$item["id"]] = $item; }
    }

    foreach($ids as $id){
        if(isset($mapa[(int)$id])){
            $item  = $mapa[(int)$id];
            $color = $item["color"] ?: "#0d6efd";
            $html .= '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.8rem; padding:6px 10px;">'
                  . '<i class="fas '.$icono.' me-1"></i>'.htmlspecialchars($item[$campo]).'</span>';
        }
    }
    return $html ?: '<span class="text-muted">—</span>';
}
?>

<div class="row">
    <!-- ─────────── COLUMNA IZQUIERDA ─────────── -->
    <div class="col-md-4">

        <div class="card card-primary card-outline">
            <div class="card-body box-profile">
                <div class="text-center">
                    <i class="fas fa-ticket-alt fa-5x text-primary"></i>
                </div>

                <h3 class="profile-username text-center mt-3"></h3>
                <p class="text-muted text-center">
                    <i class="fas fa-hashtag me-1"></i> Ticket #<?php echo $ticket["id"]; ?>
                </p>

                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <i class="fas fa-user text-primary me-2"></i>
                        <b>Solicitante</b>
                        <span class="float-end text-muted">
                            <?php echo htmlspecialchars($ticket["solicitante"]); ?>
                        </span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-circle text-<?php echo $ticket["estado"] == 1 ? "success" : "danger"; ?> me-2"></i>
                        <b>Estado</b>
                        <span class="float-end d-flex gap-1">
                            <?php if($ticket["estado"] == 1): ?>
                                <span class="badge text-bg-success">
                                    <i class="fas fa-check-circle me-1"></i>Activo
                                </span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">
                                    <i class="fas fa-times-circle me-1"></i>Inactivo
                                </span>
                            <?php endif; ?>

                            <?php if($checkActual === 1): ?>
                                <span class="badge text-bg-primary" title="Ticket finalizado (check = 1)">
                                    <i class="fas fa-lock me-1"></i>Finalizado
                                </span>
                            <?php else: ?>
                                <span class="badge text-bg-warning" title="Ticket abierto (check = 0)">
                                    <i class="fas fa-lock-open me-1"></i>Abierto
                                </span>
                            <?php endif; ?>
                        </span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-city me-2 text-primary"></i>
                        <b>Entidad(es)</b>
                        <span class="float-end text-muted">
                            <?php echo renderBadgesTicket($ticket["id_entidad"] ?? '', 'entidad'); ?>
                        </span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-broadcast-tower me-2 text-success"></i>
                        <b>Medio(s)</b>
                        <span class="float-end text-muted">
                            <?php echo renderBadgesTicket($ticket["id_medio"] ?? '', 'medio'); ?>
                        </span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-flag me-2 text-warning"></i>
                        <b>Prioridad(es)</b>
                        <span class="float-end text-muted">
                            <?php echo renderBadgesTicket($ticket["id_prioridad"] ?? '', 'prioridad'); ?>
                        </span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-clock text-primary me-2"></i>
                        <b>Última modificación</b>
                        <span class="float-end text-muted"><?php echo $modificacion; ?></span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-user-edit text-primary me-2"></i>
                        <b>Modificado por</b>
                        <span class="float-end text-muted">
                            <?php echo htmlspecialchars($nombreUsuario); ?>
                        </span>
                    </li>

                    <!-- ═══ BOTONES DE ACCIÓN ═══ -->
                    <li class="list-group-item">
                        <div class="d-grid gap-2">

                            <?php if ($puedeModificar): ?>
                                <!-- Toggle check: SIEMPRE visible (permite reabrir) -->
                                <button type="button"
                                        id="btnToggleCheck"
                                        class="btn <?php echo $checkActual === 1 ? 'btn-success' : 'btn-warning'; ?>"
                                        data-check="<?php echo $checkActual; ?>"
                                        data-id="<?php echo (int)$ticket['id']; ?>"
                                        data-ultimo-status="<?php echo htmlspecialchars($ultimoStatusActual ?: ''); ?>"
                                        data-total-servicios="<?php echo (int)$totalServiciosTicket; ?>">
                                    <?php if($checkActual === 1): ?>
                                        <i class="fas fa-lock"></i> Finalizado — click para reabrir
                                    <?php else: ?>
                                        <i class="fas fa-lock-open"></i> Abierto — click para finalizar
                                    <?php endif; ?>
                                </button>

                                <?php if ($bloqueoFinalizarSinServicios): ?>
                                    <div class="alert alert-warning small py-2 px-2 mb-0 d-flex align-items-start gap-2">
                                        <i class="fas fa-exclamation-triangle mt-1"></i>
                                        <div>
                                            Para finalizar el ticket (status <strong>Resuelto</strong>)
                                            debes registrar <strong>al menos 1 servicio</strong>.
                                            <a href="javascript:void(0)" class="alert-link fw-bold"
                                               onclick="(function(){var t=document.getElementById('servicios-tab'); if(t) t.click();})();">
                                                Ir a Servicios →
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Editar: SOLO si el ticket NO está finalizado -->
                                <?php if ($checkActual !== 1): ?>
                                    <button type="button"
                                            class="btn btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarDetalle">
                                        <i class="fas fa-edit"></i> Editar Ticket
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- ✅ PDF + WhatsApp SOLO si el ticket está finalizado -->
                            <?php if ($checkActual === 1): ?>

                                <?php /* ✅ NUEVO: PDF solo si tiene permiso (Admin | Centro de Atención | Coord. asignada) */ ?>
                                <?php if ($puedeGenerarPDF): ?>
                                    <a href="index.php?ruta=ticket-pdf&id=<?php echo (int)$idTicket; ?>"
                                       target="_blank"
                                       class="btn btn-danger"
                                       title="Generar PDF del ticket">
                                        <i class="fas fa-file-pdf me-1"></i> Generar PDF
                                    </a>
                                <?php endif; ?>

                                <button type="button"
                                        id="btnWhatsAppCompleto"
                                        class="btn btn-success">
                                    <i class="fab fa-whatsapp me-1"></i> Informe Completo
                                </button>
                            <?php endif; ?>

                        </div>
                    </li>
                </ul>

            </div>
        </div>

        <!-- Descripción -->
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-align-left me-2"></i> Descripción del Ticket
                </h3>
            </div>
            <div class="card-body">
                <p class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($ticket["descripcion"]); ?></p>
            </div>
        </div>

        <!-- Adjuntos -->
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paperclip me-2"></i> Archivos Adjuntos
                </h3>
            </div>
            <div class="card-body">
                <?php include "ticket/tdoc.php"; ?>
            </div>
        </div>

    </div>

    <!-- ─────────── COLUMNA DERECHA ─────────── -->
    <div class="col-md-8">
        <div class="card card-primary card-outline">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="profile-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" type="button" role="tab">Status</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="servicios-tab" data-bs-toggle="tab" data-bs-target="#servicios" type="button" role="tab">Servicios</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="asignaciones-tab" data-bs-toggle="tab" data-bs-target="#asignaciones" type="button" role="tab">Asignaciones</button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="status" role="tabpanel"><?php include "ticket/tstatus.php"; ?></div>
                    <div class="tab-pane fade" id="servicios" role="tabpanel">
                        <?php include "ticket/tservicio.php"; include "ticket/tevidencia.php"; ?>
                    </div>
                    <div class="tab-pane fade" id="asignaciones" role="tabpanel">
                        <?php include "ticket/tuser.php"; include "ticket/tcoordinacion.php"; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeModificar && $checkActual !== 1): ?>
<!-- ═══════════════ MODAL EDITAR DETALLE ═══════════════ -->
<div class="modal fade" id="modalEditarDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formEditarDetalle" method="post">
                <input type="hidden" name="editarTicketDetalle" value="1">
                <input type="hidden" name="idTicket" value="<?php echo (int)$ticket['id']; ?>">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i> Editar Ticket #<?php echo (int)$ticket['id']; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Solicitante <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="detalleSolicitante" id="detalleSolicitante"
                                   value="<?php echo htmlspecialchars($ticket['solicitante']); ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-circle me-1 text-muted"></i> Estado</label>
                            <select class="form-select" name="detalleEstado" id="detalleEstado">
                                <option value="1" <?php echo (int)$ticket['estado'] === 1 ? 'selected' : ''; ?>>Activo</option>
                                <option value="0" <?php echo (int)$ticket['estado'] === 0 ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-city me-1 text-muted"></i> Entidad(es)</label>
                            <select id="detalleIdEntidad" name="detalleIdEntidad[]" multiple placeholder="Seleccione entidades..."></select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-broadcast-tower me-1 text-muted"></i> Medio(s)</label>
                            <select id="detalleIdMedio" name="detalleIdMedio[]" multiple placeholder="Seleccione medios..."></select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-flag me-1 text-muted"></i> Prioridad(es)</label>
                            <select id="detalleIdPrioridad" name="detalleIdPrioridad[]" multiple placeholder="Seleccione prioridades..."></select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Descripción <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="detalleDescripcion" id="detalleDescripcion" rows="4" maxlength="5000" required><?php echo htmlspecialchars($ticket['descripcion']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════ SCRIPTS DEL DETALLE ═══════════════ -->
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

    /* ═══════════════════════════════════════════════════════
       ✅ Botón TOGGLE CHECK
       ═══════════════════════════════════════════════════════ */
    const btnToggleCheck = document.getElementById('btnToggleCheck');
    if(btnToggleCheck){
        btnToggleCheck.addEventListener('click', async function(){
            const idTicket    = this.getAttribute('data-id');
            const checkActual = parseInt(this.getAttribute('data-check')) || 0;
            const nuevoCheck  = checkActual === 1 ? 0 : 1;

            if (nuevoCheck === 1) {
                const ultimoStatus = (this.getAttribute('data-ultimo-status') || '').trim();
                const permitidos   = ['resuelto', 'suspendido'];
                if (!permitidos.includes(ultimoStatus.toLowerCase())) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No se puede finalizar',
                        html: `El último status del ticket debe ser <strong>Resuelto</strong> o <strong>Suspendido</strong>.<br>` +
                              `Actualmente es: <strong>${ultimoStatus || 'sin status registrado'}</strong>.`
                    });
                    return;
                }

                if (ultimoStatus.toLowerCase() === 'resuelto') {
                    const totalServicios = parseInt(this.getAttribute('data-total-servicios')) || 0;
                    if (totalServicios <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No se puede finalizar',
                            html: `El último status es <strong>Resuelto</strong>.<br>` +
                                  `Debes registrar <strong>al menos 1 servicio</strong> en la pestaña <strong>Servicios</strong> antes de finalizar el ticket.`
                        });
                        return;
                    }
                }
            }

            const originalHTML = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

            try {
                const fd = new FormData();
                fd.append('cambiarCheckTicket', '1');
                fd.append('idTicket', idTicket);
                fd.append('nuevoCheck', nuevoCheck);

                const res  = await fetch('ajax/ticket.ajax.php', { method: 'POST', body: fd });
                const data = await res.json();

                if(data.status === 'success'){
                    Toast.fire({ icon: 'success', title: data.mensaje });
                    setTimeout(() => location.reload(), 1200);
                } else {
                    Toast.fire({ icon: 'error', title: data.mensaje || 'Error al cambiar el check' });
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                }
            } catch(err) {
                console.error(err);
                Toast.fire({ icon: 'error', title: 'Error de conexión' });
                this.disabled = false;
                this.innerHTML = originalHTML;
            }
        });
    }

    /* ═══════════════════════════════════════════════════════
       Modal editar detalle — Tom Select (solo si el modal existe)
       ═══════════════════════════════════════════════════════ */
    function renderOpcion(data, escape) {
        const color = data.color || '#0d6efd';
        return `<div><span class="tag-dot" style="background-color:${color};"></span><span class="option-label">${escape(data.text)}</span></div>`;
    }
    function renderItem(data, escape) {
        const color = data.color || '#0d6efd';
        return `<div style="--tag-color:${color};" class="item-tag">${escape(data.text)}</div>`;
    }
    const tomSelectConfig = {
        plugins: ['remove_button'],
        persist: false,
        create: false,
        maxItems: null,
        valueField: 'id',
        labelField: 'text',
        searchField: 'text',
        render: { option: renderOpcion, item: renderItem }
    };

    let tsDetEnt = null, tsDetMed = null, tsDetPri = null;
    let cacheEnt = null, cacheMed = null, cachePri = null;

    function fetchEntidades(){
        if(cacheEnt) return Promise.resolve(cacheEnt);
        return fetch('ajax/ticket.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarEntidadesActivas','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheEnt = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheEnt; })
        .catch(() => []);
    }
    function fetchMedios(){
        if(cacheMed) return Promise.resolve(cacheMed);
        return fetch('ajax/ticket.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarMediosActivos','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheMed = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheMed; })
        .catch(() => []);
    }
    function fetchPrioridades(){
        if(cachePri) return Promise.resolve(cachePri);
        return fetch('ajax/ticket.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarPrioridadesActivas','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cachePri = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cachePri; })
        .catch(() => []);
    }

    function addOptionsToTS(ts, data){
        data.forEach(item => {
            if(!ts.options[String(item.id)]){
                ts.addOption({ id: String(item.id), text: item.texto, color: item.color });
            }
        });
        ts.refreshOptions(false);
    }
    function setTSValue(ts, jsonIds){
        let ids = [];
        try { ids = JSON.parse(jsonIds || '[]'); } catch(e){ ids = []; }
        if(!Array.isArray(ids)) ids = [];
        ids = ids.map(String);
        ids.forEach(id => {
            if(!ts.options[id]) ts.addOption({ id: id, text: 'ID '+id, color: '#6c757d' });
        });
        ts.setValue(ids, true);
        ts.refreshOptions(false);
    }

    const TICKET_DATA = {
        id_entidad:   <?php echo json_encode($ticket['id_entidad']   ?? '[]'); ?>,
        id_medio:     <?php echo json_encode($ticket['id_medio']     ?? '[]'); ?>,
        id_prioridad: <?php echo json_encode($ticket['id_prioridad'] ?? '[]'); ?>
    };

    const modalDetalleEl = document.getElementById('modalEditarDetalle');
    if(modalDetalleEl){
        modalDetalleEl.addEventListener('shown.bs.modal', async function(){
            const [ent, med, pri] = await Promise.all([fetchEntidades(), fetchMedios(), fetchPrioridades()]);

            if(!tsDetEnt){
                tsDetEnt = new TomSelect('#detalleIdEntidad',   Object.assign({}, tomSelectConfig));
                addOptionsToTS(tsDetEnt, ent);
                setTSValue(tsDetEnt, TICKET_DATA.id_entidad);
            }
            if(!tsDetMed){
                tsDetMed = new TomSelect('#detalleIdMedio',     Object.assign({}, tomSelectConfig));
                addOptionsToTS(tsDetMed, med);
                setTSValue(tsDetMed, TICKET_DATA.id_medio);
            }
            if(!tsDetPri){
                tsDetPri = new TomSelect('#detalleIdPrioridad', Object.assign({}, tomSelectConfig));
                addOptionsToTS(tsDetPri, pri);
                setTSValue(tsDetPri, TICKET_DATA.id_prioridad);
            }

            tsDetEnt.refreshOptions(false);
            tsDetMed.refreshOptions(false);
            tsDetPri.refreshOptions(false);
        });
    }

    const formEditarDetalle = document.getElementById('formEditarDetalle');
    if(formEditarDetalle){
        formEditarDetalle.addEventListener('submit', function(e){
            e.preventDefault();

            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';

            Swal.fire({
                title: 'Actualizando ticket...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/ticket.ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(resp => {
                    Swal.close();
                    if(resp.status === 'success'){
                        Toast.fire({ icon: 'success', title: resp.mensaje || 'Ticket actualizado' });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Toast.fire({ icon: 'error', title: resp.mensaje || 'Error al actualizar' });
                        btn.disabled = false;
                        btn.innerHTML = originalHTML;
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.close();
                    Toast.fire({ icon: 'error', title: 'Error de conexión con el servidor' });
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                });
        });
    }

    /* ═══════════════════════════════════════════════════════
       ✅ INFORME COMPLETO POR WHATSAPP
       ═══════════════════════════════════════════════════════ */
    <?php if ($whatsappData !== null): ?>
    const WA_DATA = <?php echo json_encode($whatsappData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function buildWhatsappMessage(data) {
        let msg = "📋 *INFORME COMPLETO DE SERVICIO TÉCNICO - AIT*\n";
        msg += "━━━━━━━━━━━━━\n\n";
        msg += "🆔 *Ticket:* #" + data.id + "\n";
        msg += "🏢 *Dependencia:* " + (data.entidades.length ? data.entidades.join(', ') : 'No especificado') + "\n";
        msg += "👤 *Solicitante:* " + data.solicitante + "\n";
        msg += "📝 *Descripción:*\n" + data.descripcion + "\n\n";
        msg += "📡 *Medio:* " + (data.medios.length ? data.medios.join(', ') : 'No especificado') + "\n";
        msg += "👨‍💻 *Creado por:* " + data.creador + "\n";
        msg += "📅 *Fecha de Creación:* " + data.fecha + "\n";
        msg += "✅ *Estado:* FINALIZADO\n\n";

        msg += "👥 *COORDINACION ASIGNADA:*\n";
        if (data.coordinaciones.length > 0) {
            data.coordinaciones.forEach(function(c){ msg += "   • " + c + "\n"; });
        } else {
            msg += "   • Ninguna\n";
        }
        msg += "\n";

        msg += "👥 *ESPECIALISTAS ASIGNADOS:*\n";
        if (data.usuarios.length > 0) {
            data.usuarios.forEach(function(u){ msg += "   • " + u + "\n"; });
        } else {
            msg += "   • Ninguno\n";
        }
        msg += "\n";

        msg += "🔧 *SERVICIOS Y DETALLES:*\n";
        msg += "━━━━━━━━━━━━━\n";
        if (data.servicios.length > 0) {
            data.servicios.forEach(function(s){
                msg += "\n   📌 *" + (s.servicio || 'Servicio') + (s.item ? ' - ' + s.item : '') + "*\n";
                msg += "      📦 Cantidad: " + s.cantidad + "\n";
                msg += "      📄 Descripción: " + s.descripcion + "\n";
            });
        } else {
            msg += "   • Ningún servicio registrado\n";
        }
        msg += "\n";

        msg += "━━━━━━━━━━━━━\n";
        msg += "📌 *Este informe es generado automáticamente por el Sistema de Gestión de Tickets AIT.*\n";
        msg += "🔗 *Ticket en línea:* " + data.urlTicket;

        return msg;
    }

    const btnWA = document.getElementById('btnWhatsAppCompleto');
    if (btnWA) {
        btnWA.addEventListener('click', function(e){
            e.preventDefault();
            try {
                const mensaje = buildWhatsappMessage(WA_DATA);
                const encoded = encodeURIComponent(mensaje);
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                const whatsappUrl = isMobile
                    ? "https://api.whatsapp.com/send?text=" + encoded
                    : "https://web.whatsapp.com/send?text=" + encoded;
                window.open(whatsappUrl, "_blank");
            } catch(err) {
                console.error('[WhatsApp Informe]', err);
                Toast.fire({ icon: 'error', title: 'No se pudo generar el informe' });
            }
        });
    }
    <?php endif; ?>

});
</script>