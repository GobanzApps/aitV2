<?php
/*=============================================
Incluido desde vistas/modulos/ticket.php
$idTicket y $ticket YA están definidos.
=============================================*/
if(!isset($idTicket) || $idTicket <= 0){
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No se pudo identificar el ticket.</div>';
    return;
}

/*=============================================
✅ Contexto de autorización
=============================================*/
$ticketFinalizadoTS = isset($ticket) && ((int)($ticket["check"] ?? 0) === 1);

$authTS = (isset($authTicket) && $authTicket) ? $authTicket : ControladorTicket::authTicket();

/* Regla 3: Administrador o (Coordinador/Especialista asignado al ticket) */
$permisoBaseTS = false;
if ($authTS) {
    if (ControladorTicket::esAdminTicket($authTS)) {
        $permisoBaseTS = true;
    } elseif (ControladorTicket::esCoordinadorTicket($authTS)
           || ControladorTicket::esEspecialistaTicket($authTS)) {
        $permisoBaseTS = ControladorTicket::estaAsignadoAlTicket($idTicket, $authTS);
    }
}

/* Puede modificar = tiene permiso Y ticket NO finalizado (regla 1) */
$puedeModificarTS = $permisoBaseTS && !$ticketFinalizadoTS;

/*=============================================
Datos
=============================================*/
$tservicios = ControladorTservicio::ctrMostrarTservicioPorTicket($idTicket);
$tservicios = $tservicios ?: [];

$todosUsuarios = ControladorUser::ctrMostrarUser(null, null);
if(!$todosUsuarios || !is_array($todosUsuarios)) $todosUsuarios = [];
$mapaUsuarios = [];
foreach($todosUsuarios as $u){ $mapaUsuarios[(int)$u["id"]] = $u; }

$todosRoles = ControladorRol::ctrMostrarRol(null, null);
if(!$todosRoles || !is_array($todosRoles)) $todosRoles = [];
$mapaRoles = [];
foreach($todosRoles as $r){ $mapaRoles[(int)$r["id"]] = $r; }

$todosTags = ControladorTag::ctrMostrarTag(null, null);
if(!$todosTags || !is_array($todosTags)) $todosTags = [];
$mapaTags = [];
foreach($todosTags as $t){ $mapaTags[(int)$t["id"]] = $t; }

$todosHosts = ControladorHost::ctrMostrarHost(null, null);
if(!$todosHosts || !is_array($todosHosts)) $todosHosts = [];
$mapaHosts = [];
foreach($todosHosts as $h){ $mapaHosts[(int)$h["id"]] = $h; }

/*=============================================
✅ NUEVO: Validar que el ÚLTIMO status del ticket sea "En Proceso"
   para poder agregar servicios
=============================================*/
$histStatusTS = ControladorTstatus::ctrMostrarTstatusPorTicket($idTicket);
$histStatusTS = $histStatusTS ?: [];   // ORDER BY id DESC → [0] es el último

$ultimoStatusTS = null;
if(count($histStatusTS) > 0){
    $idStUlt = (int)($histStatusTS[0]["id_status"] ?? 0);
    if($idStUlt > 0){
        $stUltData = ControladorStatus::ctrMostrarStatus("id", $idStUlt);
        if($stDataUlt = $stUltData) $ultimoStatusTS = trim($stUltData["status"]);
    }
}

$ultimoStatusEsEnProceso = $ultimoStatusTS !== null
                        && mb_strtolower($ultimoStatusTS) === "en proceso";

/* ✅ Puede agregar = permiso + no finalizado + último status "En Proceso" */
$puedeAgregarTS = $puedeModificarTS && $ultimoStatusEsEnProceso;

/*=============================================
Helpers
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
    .tserv-quill-editor {
        min-height: 180px;
        border-radius: 0 0 6px 6px;
        background: #fff;
    }
    [data-bs-theme="dark"] .tserv-quill-editor { background: #1f2740; color: #dde3ee; }
    .tserv-quill-editor .ql-editor { min-height: 180px; }

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

    /* ═══════════════════════════════════════════════════════════════
       ✅ FIX RESPONSIVE: modales scrollable con <form> de por medio
       Problema: Bootstrap espera que .modal-header, .modal-body y
       .modal-footer sean hijos directos de .modal-content. Al envolver
       todo en un <form>, el .modal-body no puede scrollear y el footer
       con los botones Cancelar/Guardar se recorta en móvil.

       Solución: hacer que el <form> actúe como columna flex igual que
       .modal-content, y que SOLO el .modal-body scrollee.
       ═══════════════════════════════════════════════════════════════ */
    .modal-dialog-scrollable .modal-content > form {
        display: flex;
        flex-direction: column;
        max-height: 100%;
        overflow: hidden;
        flex: 1 1 auto;
        min-height: 0;
    }
    .modal-dialog-scrollable .modal-content > form > .modal-header,
    .modal-dialog-scrollable .modal-content > form > .modal-footer {
        flex-shrink: 0;
    }
    .modal-dialog-scrollable .modal-content > form > .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch; /* scroll suave en iOS */
    }

    /* ✅ En móvil: márgenes internos más compactos y footer con
       botones en columna para que quepan bien */
    @media (max-width: 576px) {
        .modal-dialog-scrollable .modal-content > form > .modal-footer {
            flex-direction: column;
            gap: 0.5rem;
        }
        .modal-dialog-scrollable .modal-content > form > .modal-footer .btn {
            width: 100%;
            margin: 0;
        }
        #editorNuevo, #editorEditar { min-height: 140px; }
        .tserv-quill-editor .ql-editor { min-height: 140px; }
    }
</style>

<!-- ═══════════════ CABECERA ═══════════════ -->
<div class="d-flex justify-content-between align-items-center mb-3 mt-4">
    <h5 class="mb-0">
        <i class="fas fa-tools me-2 text-primary"></i> Servicios Registrados
        <span class="badge text-bg-primary ms-2"><?php echo count($tservicios); ?></span>
    </h5>

    <?php if ($ticketFinalizadoTS): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="Ticket finalizado: no se pueden agregar servicios">
            <i class="fas fa-lock me-1"></i> Ticket finalizado
        </button>
    <?php elseif (!$permisoBaseTS): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="Solo Administrador o Coordinador/Especialista asignado">
            <i class="fas fa-lock me-1"></i> Solo lectura
        </button>
    <?php elseif (!$ultimoStatusEsEnProceso): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="El último status del ticket debe ser 'En Proceso' para agregar servicios">
            <i class="fas fa-ban me-1"></i> Requiere 'En Proceso'
        </button>
    <?php else: ?>
        <button type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalCrearTservicio">
            <i class="fas fa-plus me-1"></i> Agregar Servicio
        </button>
    <?php endif; ?>
</div>

<?php if ($ticketFinalizadoTS): ?>
    <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-lock me-2"></i>
        <div>
            <strong>Ticket finalizado.</strong>
            Los servicios registrados son de solo lectura: no se pueden agregar, editar ni eliminar.
        </div>
    </div>
<?php elseif (!$permisoBaseTS): ?>
    <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-user-shield me-2"></i>
        <div>
            <strong>No tienes permisos para modificar los servicios de este ticket.</strong><br>
            Solo el <strong>Administrador</strong> o los <strong>usuarios asignados al ticket</strong>
            con rol <strong>Coordinador</strong> o <strong>Especialista/Técnico</strong> pueden hacerlo.
        </div>
    </div>
<?php elseif (!$ultimoStatusEsEnProceso): ?>
    <div class="alert alert-warning d-flex align-items-start mb-3" role="alert">
        <i class="fas fa-hourglass-half me-2 mt-1"></i>
        <div>
            <strong>No se pueden agregar servicios todavía.</strong>
            El último status del ticket debe ser <strong>"En Proceso"</strong>.<br>
            <small class="text-muted">
                <?php if($ultimoStatusTS === null): ?>
                    El ticket no tiene ningún status registrado aún. Registra primero un status
                    <strong>"En Proceso"</strong> en la pestaña <strong>Status</strong>.
                <?php else: ?>
                    Actualmente el último status es <strong>"<?php echo htmlspecialchars($ultimoStatusTS); ?>"</strong>.
                    Cambia el ticket a <strong>"En Proceso"</strong> para poder agregar servicios.
                <?php endif; ?>
            </small>
        </div>
    </div>
<?php endif; ?>

<?php if(empty($tservicios)): ?>

    <div class="alert alert-info d-flex align-items-center mb-0">
        <i class="fas fa-info-circle me-2"></i>
        <div>Aún no hay servicios registrados en este ticket.</div>
    </div>

<?php else: ?>

    <?php foreach($tservicios as $s):

        $creador = $mapaUsuarios[(int)$s["tservicio"]] ?? null;
        $nombreCreador = tservNombreUsuario($creador);
        if($nombreCreador === null){
            $nombreCreador = "Usuario #" . $s["tservicio"];
        }
        $colorCreador  = $creador ? tservColorUsuario($creador, $mapaRoles) : "#6c757d";
        $iniCreador    = tservIniciales($nombreCreador);

        $fechaMostrar = !empty($s["modificacion"])
            ? date("d/m/Y H:i", strtotime($s["modificacion"]))
            : "—";

        $idsTags = json_decode($s["id_tag"], true);
        if(!is_array($idsTags)) $idsTags = [];

        $idsHosts = json_decode($s["id_host"], true);
        if(!is_array($idsHosts)) $idsHosts = [];
    ?>

    <article class="d-flex gap-3 mb-3 p-3 border rounded bg-body-tertiary"
             data-tservicio-id="<?php echo (int)$s["id"]; ?>">

        <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm"
             style="width: 45px; height: 45px; background-color: <?php echo htmlspecialchars($colorCreador); ?>; font-weight: 600;"
             aria-hidden="true">
            <?php echo htmlspecialchars($iniCreador); ?>
        </div>

        <div class="flex-grow-1">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h4 class="h6 mb-0">
                        <i class="fas fa-tools me-1 text-success"></i>
                        <?php echo htmlspecialchars($nombreCreador); ?>
                        <small class="text-muted ms-2">registró un servicio en este ticket</small>
                    </h4>
                    <small class="text-secondary">
                        <i class="fas fa-clock me-1"></i><?php echo $fechaMostrar; ?>
                    </small>
                </div>

                <?php if ($puedeModificarTS): ?>
                <div class="btn-group">
                    <button type="button"
                            class="btn btn-sm btn-warning btnEditarTservicio"
                            data-id="<?php echo (int)$s["id"]; ?>"
                            title="Editar">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-danger btnEliminarTservicio"
                            data-id="<?php echo (int)$s["id"]; ?>"
                            title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tags -->
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

            <!-- Hosts -->
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
                        $colorH = $hs["color"] ?: "#0d6efd";
                        echo '<span class="badge me-1 mb-1" style="background-color:'.htmlspecialchars($colorH).'; color:#fff; padding:5px 10px; font-size:0.78rem;">'
                           . '<i class="fas fa-network-wired me-1"></i>'.htmlspecialchars($hs["host"]).'</span>';
                    } else {
                        echo '<span class="badge bg-secondary me-1 mb-1">ID '.$idH.'</span>';
                    }
                endforeach; endif; ?>
            </div>

            <!-- Cantidad -->
            <div class="mt-2">
                <span class="badge text-bg-dark">
                    <i class="fas fa-hashtag me-1"></i>Cantidad: <?php echo (int)$s["cantidad"]; ?>
                </span>
            </div>

            <!-- Descripción -->
            <div class="mt-3">
                <div class="tserv-descripcion">
                    <?php echo $s["descripcion"]; ?>
                </div>
            </div>

        </div>
    </article>

    <?php endforeach; ?>

<?php endif; ?>

<?php if ($puedeAgregarTS): ?>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearTservicio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formCrearTservicio" method="post">
                <input type="hidden" name="nuevoTservicio" value="1">
                <input type="hidden" name="idTicket" value="<?php echo (int)$idTicket; ?>">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-tools me-2"></i> Agregar Servicio al Ticket
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-8">
                            <label class="form-label">
                                <i class="fas fa-tags me-1 text-muted"></i>
                                Tags <span class="text-danger">*</span>
                            </label>
                            <select id="nuevoTservTag"
                                    name="nuevoIdTag[]"
                                    multiple
                                    placeholder="Seleccione tags..."></select>
                            <small class="text-muted">Solo se muestran los tags activos. Obligatorio.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="fas fa-hashtag me-1 text-muted"></i>
                                Cantidad <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                   class="form-control"
                                   name="nuevoCantidad"
                                   id="nuevoCantidad"
                                   min="1"
                                   step="1"
                                   value="1"
                                   required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-network-wired me-1 text-muted"></i>
                                Hosts <span class="text-muted small">(opcional)</span>
                            </label>
                            <select id="nuevoTservHost"
                                    name="nuevoIdHost[]"
                                    multiple
                                    placeholder="Seleccione hosts..."></select>
                            <small class="text-muted">Solo se muestran los hosts activos. Opcional.</small>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-align-left me-1 text-muted"></i>
                                Descripción <span class="text-danger">*</span>
                            </label>
                            <div id="editorNuevo" class="tserv-quill-editor"></div>
                            <input type="hidden" name="nuevoDescripcion" id="nuevoDescripcion">
                        </div>

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

<?php endif; ?>

<?php if ($puedeModificarTS): ?>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarTservicio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formEditarTservicio" method="post">
                <input type="hidden" name="editarTservicio" value="1">
                <input type="hidden" name="idTservicio" id="idTservicio">

                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i> Editar Servicio
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-8">
                            <label class="form-label">
                                <i class="fas fa-tags me-1 text-muted"></i>
                                Tags <span class="text-danger">*</span>
                            </label>
                            <select id="editarTservTag"
                                    name="editarIdTag[]"
                                    multiple
                                    placeholder="Seleccione tags..."></select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="fas fa-hashtag me-1 text-muted"></i>
                                Cantidad <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                   class="form-control"
                                   name="editarCantidad"
                                   id="editarCantidad"
                                   min="1"
                                   step="1"
                                   required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-network-wired me-1 text-muted"></i>
                                Hosts <span class="text-muted small">(opcional)</span>
                            </label>
                            <select id="editarTservHost"
                                    name="editarIdHost[]"
                                    multiple
                                    placeholder="Seleccione hosts..."></select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-align-left me-1 text-muted"></i>
                                Descripción <span class="text-danger">*</span>
                            </label>
                            <div id="editorEditar" class="tserv-quill-editor"></div>
                            <input type="hidden" name="editarDescripcion" id="editarDescripcion">
                        </div>

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

<!-- ═══════════════ SCRIPTS (solo si hay permiso de modificar) ═══════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 2500, timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    function renderOpcion(data, escape){
        const color = data.color || '#0d6efd';
        return `<div><span class="tag-dot" style="background-color:${color};"></span><span class="option-label">${escape(data.text)}</span></div>`;
    }
    function renderItem(data, escape){
        const color = data.color || '#0d6efd';
        return `<div style="--tag-color:${color};" class="item-tag">${escape(data.text)}</div>`;
    }

    const tomSelectConfigBase = {
        plugins: ['remove_button'], persist: false, create: false,
        maxItems: null, valueField: 'id', labelField: 'text', searchField: 'text',
        render: { option: renderOpcion, item: renderItem }
    };

    const tomSelectConfigTag = Object.assign({}, tomSelectConfigBase, {
        onItemAdd: function () {
            this.setTextboxValue('');
            this.refreshOptions();
        }
    });

    const quillToolbar = [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        [{ 'align': [] }],
        ['blockquote', 'code-block'], ['link'], ['clean']
    ];

    let quillNuevo = null, quillEditar = null;
    let tsNuevoTag = null, tsNuevoHost = null;
    let tsEditarTag = null, tsEditarHost = null;
    let cacheTags = null, cacheHosts = null;
    let pendingData = null;

    function fetchTags(){
        if(cacheTags) return Promise.resolve(cacheTags);
        return fetch('ajax/tservicio.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarTagsActivos','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheTags = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheTags; })
        .catch(() => []);
    }
    function fetchHosts(){
        if(cacheHosts) return Promise.resolve(cacheHosts);
        return fetch('ajax/tservicio.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarHostsActivos','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheHosts = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheHosts; })
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

    /* MODAL CREAR (solo existe si $puedeAgregarTS) */
    const modalCrearEl = document.getElementById('modalCrearTservicio');
    if(modalCrearEl){
        modalCrearEl.addEventListener('shown.bs.modal', async function(){
            const [tags, hosts] = await Promise.all([fetchTags(), fetchHosts()]);

            if(!tsNuevoTag){
                tsNuevoTag = new TomSelect('#nuevoTservTag', Object.assign({}, tomSelectConfigTag));
                addOptions(tsNuevoTag, tags);
            }
            if(!tsNuevoHost){
                tsNuevoHost = new TomSelect('#nuevoTservHost', Object.assign({}, tomSelectConfigBase));
                addOptions(tsNuevoHost, hosts);
            }
            if(!quillNuevo){
                quillNuevo = new Quill('#editorNuevo', {
                    theme: 'snow',
                    placeholder: 'Escriba una descripción del servicio...',
                    modules: { toolbar: quillToolbar }
                });
                quillNuevo.on('text-change', function(){
                    document.getElementById('nuevoDescripcion').value = quillNuevo.root.innerHTML;
                });
            }
            tsNuevoTag.clear(true);
            tsNuevoHost.clear(true);
            document.getElementById('nuevoCantidad').value = 1;
            quillNuevo.setContents([]);
            document.getElementById('nuevoDescripcion').value = '';
            tsNuevoTag.refreshOptions(false);
            tsNuevoHost.refreshOptions(false);
        });
        modalCrearEl.addEventListener('hidden.bs.modal', function(){
            const f = document.getElementById('formCrearTservicio');
            if(f) f.reset();
        });
    }

    /* MODAL EDITAR */
    const modalEditarEl = document.getElementById('modalEditarTservicio');
    if(modalEditarEl){
        modalEditarEl.addEventListener('shown.bs.modal', async function(){
            const [tags, hosts] = await Promise.all([fetchTags(), fetchHosts()]);

            if(!tsEditarTag){
                tsEditarTag = new TomSelect('#editarTservTag', Object.assign({}, tomSelectConfigTag));
                addOptions(tsEditarTag, tags);
            }
            if(!tsEditarHost){
                tsEditarHost = new TomSelect('#editarTservHost', Object.assign({}, tomSelectConfigBase));
                addOptions(tsEditarHost, hosts);
            }
            if(!quillEditar){
                quillEditar = new Quill('#editorEditar', {
                    theme: 'snow',
                    placeholder: 'Escriba una descripción del servicio...',
                    modules: { toolbar: quillToolbar }
                });
                quillEditar.on('text-change', function(){
                    document.getElementById('editarDescripcion').value = quillEditar.root.innerHTML;
                });
            }
            if(pendingData){
                document.getElementById('idTservicio').value      = pendingData.id;
                document.getElementById('editarCantidad').value   = pendingData.cantidad || 1;
                setTSValue(tsEditarTag,  pendingData.id_tag);
                setTSValue(tsEditarHost, pendingData.id_host);
                const html = pendingData.descripcion || '';
                quillEditar.clipboard.dangerouslyPasteHTML(html);
                document.getElementById('editarDescripcion').value = quillEditar.root.innerHTML;
                pendingData = null;
            }
            tsEditarTag.refreshOptions(false);
            tsEditarHost.refreshOptions(false);
        });
    }

    /* SUBMIT CREAR */
    const formCrear = document.getElementById('formCrearTservicio');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();
            document.getElementById('nuevoDescripcion').value = quillNuevo ? quillNuevo.root.innerHTML : '';
            const formData = new FormData(this);
            Swal.fire({ title: 'Guardando servicio...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/tservicio.ajax.php', { method: 'POST', body: formData })
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
                .catch(err => { console.error(err); Swal.close(); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    }

    /* SUBMIT EDITAR */
    const formEditar = document.getElementById('formEditarTservicio');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();
            document.getElementById('editarDescripcion').value = quillEditar ? quillEditar.root.innerHTML : '';
            const formData = new FormData(this);
            Swal.fire({ title: 'Actualizando servicio...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/tservicio.ajax.php', { method: 'POST', body: formData })
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
                .catch(err => { console.error(err); Swal.close(); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    }

    /* BOTÓN EDITAR */
    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.btnEditarTservicio');
        if(!btn) return;
        e.preventDefault();
        const id = btn.getAttribute('data-id');
        if(!id) return;
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const fd = new FormData();
            fd.append('idTservicio', id);
            const res  = await fetch('ajax/tservicio.ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data && data.id){
                pendingData = data;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarTservicio')).show();
            } else {
                Toast.fire({ icon: 'error', title: 'No se pudo cargar el registro' });
            }
        } catch(err) {
            console.error(err);
            Toast.fire({ icon: 'error', title: 'Error de conexión' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });

    /* BOTÓN ELIMINAR */
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.btnEliminarTservicio');
        if(!btn) return;
        e.preventDefault();
        const id = btn.getAttribute('data-id');
        if(!id) return;

        Swal.fire({
            title: '¿Eliminar servicio?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if(result.isConfirmed){
                const fd = new FormData();
                fd.append('borrarTservicio', '1');
                fd.append('idTservicio', id);
                fetch('ajax/tservicio.ajax.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(resp => {
                        if(resp.status === 'success'){
                            Toast.fire({ icon: 'success', title: 'Eliminado' });
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            Toast.fire({ icon: 'error', title: resp.mensaje || 'Error al eliminar' });
                        }
                    })
                    .catch(err => { console.error(err); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
            }
        });
    });

});
</script>

<?php endif; ?>