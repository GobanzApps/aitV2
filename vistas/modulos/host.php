<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: solo roles Administrador, Coordinador o Especialista/Técnico
=============================================*/
$idUserSesion   = (int)($_SESSION["id"] ?? 0);
$usuarioSesion  = $idUserSesion > 0 ? ControladorUser::ctrMostrarUser("id", $idUserSesion) : null;
$puedeVerDetalle = false;

if ($usuarioSesion) {
    $rolesSesion = json_decode($usuarioSesion["id_rol"] ?? '[]', true) ?: [];
    foreach ($rolesSesion as $idRol) {
        $rol = ControladorRol::ctrMostrarRol("id", (int)$idRol);
        if ($rol && (int)($rol["estado"] ?? 0) === 1) {
            $nombreRol = mb_strtolower(trim($rol["rol"]));
            if (in_array($nombreRol, ['administrador', 'coordinador', 'especialista', 'tecnico', 'técnico'], true)) {
                $puedeVerDetalle = true;
                break;
            }
        }
    }
}

if (!$puedeVerDetalle) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-shield-alt me-2"></i>
            <strong>Acceso denegado.</strong>
            Esta sección es solo para usuarios con rol
            <strong>Administrador</strong>, <strong>Coordinador</strong> o <strong>Especialista/Técnico</strong>.
            <br><br>
            <a href="index.php?ruta=inicio" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
          </div>';
    return;
}

/*=============================================
✅ ¿Puede editar/activar? (solo Admin o Soporte/Centro)
   El Coordinador/Especialista que llega aquí sin ser Admin
   ni tener Soporte/Centro ve el detalle en modo solo lectura.
=============================================*/
$puedeEditarHost     = ControladorHost::puedeVerModuloHost();
$puedeVerListadoHost = $puedeEditarHost; // puede acceder a hosts.php quien vea el módulo

/*=============================================
Cargar datos del host
=============================================*/
$idHost = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
if($idHost <= 0){
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>ID de host no válido.</div>';
    return;
}

$host = ControladorHost::ctrMostrarHost("id", $idHost);
if(!$host){
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Host no encontrado.</div>';
    return;
}

$hostTipo = !empty($host["id_host_tipo"]) ? ControladorHostTipo::ctrMostrarHostTipo("id", $host["id_host_tipo"]) : null;
$entidad  = !empty($host["id_entidad"])   ? ControladorEntidad::ctrMostrarEntidad("id", $host["id_entidad"])   : null;

$usuarioData   = ControladorUser::ctrMostrarUser("id", $host["id_user"]);
$nombreUsuario = $usuarioData ? $usuarioData["user"] : "—";

$modificacion = !empty($host["modificacion"])
    ? date("d/m/Y H:i", strtotime($host["modificacion"]))
    : "—";

$estadoActual = (int)($host["estado"] ?? 1);
$colorHost    = !empty($host["color"]) ? $host["color"] : "#0d6efd";

/* Helpers para badges */
if(!function_exists('hostTipoBadge')){
    function hostTipoBadge($hostTipo){
        if(!$hostTipo) return '<span class="text-muted">—</span>';
        $color = $hostTipo["color"] ?: "#0d6efd";
        return '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; padding:5px 10px; font-size:0.75rem;">'
             . '<i class="fas fa-server me-1"></i>'.htmlspecialchars($hostTipo["host_tipo"]).'</span>';
    }
}
if(!function_exists('hostEntidadBadge')){
    function hostEntidadBadge($entidad){
        if(!$entidad) return '<span class="text-muted">—</span>';
        $color = $entidad["color"] ?: "#0d6efd";
        return '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; padding:5px 10px; font-size:0.75rem;">'
             . '<i class="fas fa-city me-1"></i>'.htmlspecialchars($entidad["entidad"]).'</span>';
    }
}
?>

<div class="row">
    <!-- ═══════════════ COLUMNA IZQUIERDA ═══════════════ -->
    <div class="col-md-4">

        <div class="card card-primary card-outline">
            <div class="card-body box-profile">

                <div class="text-center">
                    <i class="fas fa-network-wired fa-5x" style="color:<?php echo htmlspecialchars($colorHost); ?>;"></i>
                </div>

                <h3 class="profile-username text-center mt-3"></h3>
                <p class="text-muted text-center">
                    <i class="fas fa-server me-1"></i> <?php echo htmlspecialchars($host["host"]); ?>
                </p>

                <ul class="list-group list-group-unbordered mb-3">

                    <li class="list-group-item">
                        <i class="fas fa-hashtag text-primary me-2"></i>
                        <b>ID</b>
                        <span class="float-end text-muted"><?php echo (int)$host["id"]; ?></span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-server me-2 text-primary"></i>
                        <b>Tipo de Host</b>
                        <span class="float-end text-muted"><?php echo hostTipoBadge($hostTipo); ?></span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-city me-2 text-primary"></i>
                        <b>Entidad</b>
                        <span class="float-end text-muted"><?php echo hostEntidadBadge($entidad); ?></span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-palette me-2 text-primary"></i>
                        <b>Color</b>
                        <span class="float-end">
                            <span style="display:inline-block; width:22px; height:22px; border-radius:5px; border:1px solid #ccc; background-color:<?php echo htmlspecialchars($colorHost); ?>; vertical-align:middle;"></span>
                            <small class="text-muted ms-1"><?php echo htmlspecialchars($colorHost); ?></small>
                        </span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-circle text-<?php echo $estadoActual === 1 ? 'success' : 'danger'; ?> me-2"></i>
                        <b>Estado</b>
                        <span class="float-end">
                            <?php if($estadoActual === 1): ?>
                                <span class="badge text-bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">Inactivo</span>
                            <?php endif; ?>
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
                        <span class="float-end text-muted"><?php echo htmlspecialchars($nombreUsuario); ?></span>
                    </li>

                    <!-- ═══ BOTONES DE ACCIÓN ═══ -->
                    <li class="list-group-item">
                        <div class="d-grid gap-2">

                            <?php if ($puedeEditarHost): ?>
                                <!-- Botón cambiar estado -->
                                <button type="button"
                                        id="btnToggleEstado"
                                        class="btn <?php echo $estadoActual === 1 ? 'btn-success' : 'btn-danger'; ?>"
                                        data-id="<?php echo (int)$host['id']; ?>"
                                        data-estado="<?php echo $estadoActual; ?>">
                                    <?php if($estadoActual === 1): ?>
                                        <i class="fas fa-check-circle"></i> Activo — click para desactivar
                                    <?php else: ?>
                                        <i class="fas fa-times-circle"></i> Inactivo — click para activar
                                    <?php endif; ?>
                                </button>

                                <!-- Botón editar -->
                                <button type="button"
                                        class="btn btn-warning"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditarHost">
                                    <i class="fas fa-edit"></i> Editar Host
                                </button>
                            <?php endif; ?>

                            <!-- Botón para volver (al listado o al inicio según permisos) -->
                            <?php if ($puedeVerListadoHost): ?>
                                <a href="index.php?ruta=hosts" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Volver al listado
                                </a>
                            <?php else: ?>
                                <a href="index.php?ruta=inicio" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Volver al inicio
                                </a>
                            <?php endif; ?>

                        </div>
                    </li>

                </ul>
            </div>
        </div>

    </div>

    <!-- ═══════════════ COLUMNA DERECHA ═══════════════ -->
    <div class="col-md-8">
        <div class="card card-primary card-outline">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="host-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="servicios-tab" data-bs-toggle="tab" data-bs-target="#servicios" type="button" role="tab">
                            <i class="fas fa-tools me-1"></i> Servicios
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" role="tab">
                            <i class="fas fa-folder me-1"></i> Tabs 2
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">

                    <div class="tab-pane fade show active" id="servicios" role="tabpanel">
                        <?php include "host/hservicio.php"; ?>
                    </div>

                    <div class="tab-pane fade" id="tab2" role="tabpanel">
                        <div class="alert alert-secondary d-flex align-items-center mb-0">
                            <i class="fas fa-folder-open me-2"></i>
                            <div>Contenido pendiente en esta pestaña.</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeEditarHost): ?>

<!-- ═══════════════ MODAL EDITAR HOST ═══════════════ -->
<div class="modal fade" id="modalEditarHost" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarHost" method="post">
                <input type="hidden" name="idHost" value="<?php echo (int)$host['id']; ?>">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Host</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Host <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="editarHost" id="editarHost"
                               value="<?php echo htmlspecialchars($host['host']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-server me-1 text-muted"></i> Tipo de Host</label>
                        <select id="editarIdHostTipo" name="editarIdHostTipo" placeholder="Seleccione tipo..."></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-city me-1 text-muted"></i> Entidad</label>
                        <select id="editarIdEntidad" name="editarIdEntidad" placeholder="Seleccione entidad..."></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color representativo</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" name="editarColor" id="editarColor"
                                   value="<?php echo htmlspecialchars($colorHost); ?>">
                            <input type="text" class="form-control" id="editarColorText"
                                   value="<?php echo htmlspecialchars(strtoupper($colorHost)); ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Guardar Cambios</button>
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
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 2500, timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    /* ✅ 1) Botón TOGGLE ESTADO (solo existe si $puedeEditarHost) */
    const btnToggleEstado = document.getElementById('btnToggleEstado');
    if(btnToggleEstado){
        btnToggleEstado.addEventListener('click', async function(){
            const idHost       = this.getAttribute('data-id');
            const estadoActual = parseInt(this.getAttribute('data-estado')) || 0;
            const nuevoEstado  = estadoActual === 1 ? 0 : 1;

            const originalHTML = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

            try {
                const fd = new FormData();
                fd.append('activarHost', nuevoEstado);
                fd.append('activarId',   idHost);

                const res  = await fetch('ajax/host.ajax.php', { method: 'POST', body: fd });
                const data = await res.json();

                if(data.status === 'ok'){
                    Toast.fire({ icon: 'success', title: 'Estado actualizado' });
                    setTimeout(() => location.reload(), 1200);
                } else {
                    Toast.fire({ icon: 'error', title: (data && data.mensaje) || 'Error al actualizar el estado' });
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

    /* Sincronizar color picker del modal */
    const editarColor     = document.getElementById('editarColor');
    const editarColorText = document.getElementById('editarColorText');
    if(editarColor){
        editarColor.addEventListener('input', () => {
            editarColorText.value = editarColor.value.toUpperCase();
        });
    }

    /* Tom Select (single) – carga datos y aplica valores actuales */
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

    let tsEditTipo = null, tsEditEnt = null;
    let cacheTipos = null, cacheEnts = null;

    const HOST_DATA = {
        id_host_tipo: <?php echo json_encode((string)($host['id_host_tipo'] ?? '')); ?>,
        id_entidad:   <?php echo json_encode((string)($host['id_entidad']   ?? '')); ?>
    };

    function fetchTipos(){
        if(cacheTipos) return Promise.resolve(cacheTipos);
        return fetch('ajax/host.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarHostTiposActivos','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheTipos = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheTipos; })
        .catch(() => []);
    }
    function fetchEnts(){
        if(cacheEnts) return Promise.resolve(cacheEnts);
        return fetch('ajax/host.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarEntidadesActivas','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheEnts = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheEnts; })
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
    function setTSSingle(ts, id){
        if(id === null || id === undefined || id === '') return;
        const idStr = String(id);
        if(!ts.options[idStr]) ts.addOption({ id: idStr, text: 'ID '+idStr, color: '#6c757d' });
        ts.setValue(idStr, true);
        ts.refreshOptions(false);
    }

    const modalEditarEl = document.getElementById('modalEditarHost');
    if(modalEditarEl){
        modalEditarEl.addEventListener('shown.bs.modal', async function(){
            const [tipos, ents] = await Promise.all([fetchTipos(), fetchEnts()]);

            if(!tsEditTipo){ tsEditTipo = new TomSelect('#editarIdHostTipo', Object.assign({}, tomSelectConfig)); addOptions(tsEditTipo, tipos); }
            if(!tsEditEnt) { tsEditEnt  = new TomSelect('#editarIdEntidad',  Object.assign({}, tomSelectConfig)); addOptions(tsEditEnt, ents); }

            setTSSingle(tsEditTipo, HOST_DATA.id_host_tipo);
            setTSSingle(tsEditEnt,  HOST_DATA.id_entidad);

            tsEditTipo.refreshOptions(false);
            tsEditEnt.refreshOptions(false);
        });
    }

    /* SUBMIT editar */
    const formEditarHost = document.getElementById('formEditarHost');
    if(formEditarHost){
        formEditarHost.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);

            Swal.fire({ title: 'Actualizando host...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('ajax/host.ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(r => {
                    Swal.close();
                    if(r.status === 'success'){
                        Toast.fire({ icon: 'success', title: r.mensaje || 'Host actualizado' });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Toast.fire({ icon: 'error', title: r.mensaje || 'Error al actualizar' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.close();
                    Toast.fire({ icon: 'error', title: 'Error de conexión' });
                });
        });
    }

});
</script>