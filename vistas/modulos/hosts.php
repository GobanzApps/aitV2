<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: solo Administrador o (Soporte / Centro de Atención)
=============================================*/
if (!ControladorHost::puedeVerModuloHost()) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-shield-alt me-2"></i>
            <strong>Acceso denegado.</strong>
            Esta sección es solo para el rol <strong>Administrador</strong>
            o usuarios con coordinación <strong>Soporte</strong> o <strong>Centro de Atención</strong>.
            <br><br>
            <a href="index.php?ruta=inicio" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
          </div>';
    return;
}

/* ¿Puede eliminar? Solo Administrador */
$puedeEliminarHost = ControladorHost::esAdminHost();

// Procesar borrado ANTES de renderizar la tabla (el controlador revalida)
ControladorHost::ctrBorrarHost();

/*=============================================
Helpers: renderizan el tipo de host y la entidad
=============================================*/
function renderHostTipoBadge($idHostTipo){
    if(empty($idHostTipo)) return '<span class="text-muted">—</span>';
    $tipo = ControladorHostTipo::ctrMostrarHostTipo("id", $idHostTipo);
    if($tipo){
        $color = $tipo["color"] ?: "#0d6efd";
        return '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; padding:5px 10px; font-size:0.75rem;">'
             . '<i class="fas fa-server me-1"></i>'.htmlspecialchars($tipo["host_tipo"]).'</span>';
    }
    return '<span class="badge bg-secondary">ID '.htmlspecialchars($idHostTipo).'</span>';
}

function renderHostEntidadBadge($idEntidad){
    if(empty($idEntidad)) return '<span class="text-muted">—</span>';
    $ent = ControladorEntidad::ctrMostrarEntidad("id", $idEntidad);
    if($ent){
        $color = $ent["color"] ?: "#0d6efd";
        return '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; padding:5px 10px; font-size:0.75rem;">'
             . '<i class="fas fa-city me-1"></i>'.htmlspecialchars($ent["entidad"]).'</span>';
    }
    return '<span class="badge bg-secondary">ID '.htmlspecialchars($idEntidad).'</span>';
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-network-wired me-2"></i> Gestión de Hosts
        </h3>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearHost">
            <i class="fas fa-plus me-1"></i> Nuevo Host
        </button>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Host</th>
                    <th>Tipo de Host</th>
                    <th>Entidad</th>
                    <th style="width: 120px;">Color</th>
                    <th>Modificado por</th>
                    <th style="width: 160px;">Última modificación</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 180px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hosts = ControladorHost::ctrMostrarHost(null, null);

                if($hosts && is_array($hosts)){
                    foreach($hosts as $key => $value){

                        $usuarioData   = ControladorUser::ctrMostrarUser("id", $value["id_user"]);
                        $nombreUsuario = $usuarioData ? $usuarioData["user"] : "—";

                        $modificacion = !empty($value["modificacion"])
                            ? date("d/m/Y H:i", strtotime($value["modificacion"]))
                            : "—";

                        $color = !empty($value["color"]) ? $value["color"] : "#0d6efd";

                        echo '<tr>
                            <td>'.($key + 1).'</td>
                            <td>
                                <span class="badge"
                                      style="background-color:'.htmlspecialchars($color).'; color:#fff; font-size:0.9rem; padding:8px 14px;">
                                    <i class="fas fa-network-wired me-1"></i> '.htmlspecialchars($value["host"]).'
                                </span>
                            </td>
                            <td>'.renderHostTipoBadge($value["id_host_tipo"] ?? '').'</td>
                            <td>'.renderHostEntidadBadge($value["id_entidad"] ?? '').'</td>
                            <td class="text-center">
                                <span style="display:inline-block; width:32px; height:32px; border-radius:6px; border:1px solid #ccc; background-color:'.htmlspecialchars($color).';"></span>
                                <div><small class="text-muted">'.htmlspecialchars($color).'</small></div>
                            </td>
                            <td>
                                <i class="fas fa-user-circle me-1 text-muted"></i>
                                '.htmlspecialchars($nombreUsuario).'
                            </td>
                            <td><small class="text-muted">'.$modificacion.'</small></td>
                            <td>';

                        if($value["estado"] == 1){
                            echo '<button class="btn btn-success btn-sm btnActivarHost" idHost="'.$value["id"].'" estadoHost="0">
                                    <i class="fas fa-check-circle"></i> Activo
                                  </button>';
                        }else{
                            echo '<button class="btn btn-danger btn-sm btnActivarHost" idHost="'.$value["id"].'" estadoHost="1">
                                    <i class="fas fa-times-circle"></i> Inactivo
                                  </button>';
                        }

                        echo '</td>
                            <td>
                                <div class="btn-group" role="group">

                                    <!-- Editar -->
                                    <button class="btn btn-warning btn-sm btnEditarHost" idHost="'.$value["id"].'" data-bs-toggle="modal" data-bs-target="#modalEditarHost" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>

                                    <!-- Info → detalle del host -->
                                    <a href="index.php?ruta=host&id='.$value["id"].'" class="btn btn-info btn-sm" title="Ver información">
                                        <i class="fas fa-info-circle"></i>
                                    </a>';

                                    /* ✅ Eliminar SOLO si es Administrador */
                                    if ($puedeEliminarHost) {
                                        echo '<button class="btn btn-danger btn-sm btnEliminarHost" idHost="'.$value["id"].'" nombre="'.htmlspecialchars($value["host"]).'" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                              </button>';
                                    }

                        echo '</div>
                            </td>
                        </tr>';
                    }
                }else{
                    echo '<tr><td colspan="9" class="text-center text-muted">No hay hosts registrados</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearHost" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formCrearHost" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nuevo Host</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Host <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nuevoHost" placeholder="Ej: PB-AIT-001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-server me-1 text-muted"></i> Tipo de Host</label>
                        <select id="nuevoIdHostTipo" name="nuevoIdHostTipo" placeholder="Seleccione tipo..."></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-city me-1 text-muted"></i> Entidad</label>
                        <select id="nuevoIdEntidad" name="nuevoIdEntidad" placeholder="Seleccione entidad..."></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color representativo</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" name="nuevoColor" id="nuevoColor" value="#0d6efd">
                            <input type="text" class="form-control" id="nuevoColorText" value="#0D6EFD" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar Host</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarHost" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarHost" method="post">
                <input type="hidden" name="idHost" id="idHost">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Host</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Host <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="editarHost" id="editarHost" required>
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
                            <input type="color" class="form-control form-control-color" name="editarColor" id="editarColor" value="#0d6efd">
                            <input type="text" class="form-control" id="editarColorText" value="#0D6EFD" readonly>
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

    const nuevoColor = document.getElementById('nuevoColor');
    const nuevoColorText = document.getElementById('nuevoColorText');
    if(nuevoColor) nuevoColor.addEventListener('input', () => { nuevoColorText.value = nuevoColor.value.toUpperCase(); });

    const editarColor = document.getElementById('editarColor');
    const editarColorText = document.getElementById('editarColorText');
    if(editarColor) editarColor.addEventListener('input', () => { editarColorText.value = editarColor.value.toUpperCase(); });

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

    let tsNuevoTipo, tsNuevoEnt, tsEditTipo, tsEditEnt;
    let cacheTipos = null, cacheEnts = null;
    let pendingHostData = null;

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

    const modalCrearEl = document.getElementById('modalCrearHost');
    if(modalCrearEl){
        modalCrearEl.addEventListener('shown.bs.modal', async function(){
            const [tipos, ents] = await Promise.all([fetchTipos(), fetchEnts()]);
            if(!tsNuevoTipo){ tsNuevoTipo = new TomSelect('#nuevoIdHostTipo', Object.assign({}, tomSelectConfig)); addOptions(tsNuevoTipo, tipos); }
            if(!tsNuevoEnt) { tsNuevoEnt  = new TomSelect('#nuevoIdEntidad',  Object.assign({}, tomSelectConfig)); addOptions(tsNuevoEnt, ents); }
            tsNuevoTipo.refreshOptions(false);
            tsNuevoEnt.refreshOptions(false);
        });
        modalCrearEl.addEventListener('hidden.bs.modal', function(){
            if(tsNuevoTipo) tsNuevoTipo.clear(true);
            if(tsNuevoEnt)  tsNuevoEnt.clear(true);
            const f = document.getElementById('formCrearHost');
            if(f) f.reset();
        });
    }

    const modalEditarEl = document.getElementById('modalEditarHost');
    if(modalEditarEl){
        modalEditarEl.addEventListener('shown.bs.modal', async function(){
            const [tipos, ents] = await Promise.all([fetchTipos(), fetchEnts()]);
            if(!tsEditTipo){ tsEditTipo = new TomSelect('#editarIdHostTipo', Object.assign({}, tomSelectConfig)); addOptions(tsEditTipo, tipos); }
            if(!tsEditEnt) { tsEditEnt  = new TomSelect('#editarIdEntidad',  Object.assign({}, tomSelectConfig)); addOptions(tsEditEnt, ents); }

            if(pendingHostData){
                document.getElementById('idHost').value          = pendingHostData.id;
                document.getElementById('editarHost').value      = pendingHostData.host || '';
                const color = pendingHostData.color || '#0d6efd';
                document.getElementById('editarColor').value     = color;
                document.getElementById('editarColorText').value = color.toUpperCase();

                setTSSingle(tsEditTipo, pendingHostData.id_host_tipo);
                setTSSingle(tsEditEnt,  pendingHostData.id_entidad);

                pendingHostData = null;
            }
            tsEditTipo.refreshOptions(false);
            tsEditEnt.refreshOptions(false);
        });
    }

    const formCrear = document.getElementById('formCrearHost');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            Swal.fire({ title: 'Guardando host...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/host.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(r => {
                    Swal.close();
                    if(r.status === 'success'){
                        Toast.fire({ icon: 'success', title: r.mensaje || 'Host guardado' });
                        setTimeout(() => location.reload(), 1800);
                    } else {
                        Toast.fire({ icon: 'error', title: r.mensaje || 'Error al guardar' });
                    }
                })
                .catch(err => { console.error(err); Swal.close(); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    }

    const formEditar = document.getElementById('formEditarHost');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            Swal.fire({ title: 'Actualizando host...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/host.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(r => {
                    Swal.close();
                    if(r.status === 'success'){
                        Toast.fire({ icon: 'success', title: r.mensaje || 'Host actualizado' });
                        setTimeout(() => location.reload(), 1800);
                    } else {
                        Toast.fire({ icon: 'error', title: r.mensaje || 'Error al actualizar' });
                    }
                })
                .catch(err => { console.error(err); Swal.close(); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    }

    document.querySelectorAll('.btnActivarHost').forEach(btn => {
        btn.addEventListener('click', function() {
            const idHost     = this.getAttribute('idHost');
            const estadoHost = this.getAttribute('estadoHost');
            const btnElement = this;

            const datos = new FormData();
            datos.append('activarHost', estadoHost);
            datos.append('activarId',   idHost);

            fetch('ajax/host.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(r => {
                    if(r.status === 'ok'){
                        if(estadoHost === '1'){
                            btnElement.classList.remove('btn-danger');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                            btnElement.setAttribute('estadoHost', '0');
                        } else {
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-danger');
                            btnElement.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                            btnElement.setAttribute('estadoHost', '1');
                        }
                        Toast.fire({ icon: 'success', title: 'Estado actualizado' });
                    } else {
                        Toast.fire({ icon: 'error', title: r.mensaje || 'Error al actualizar el estado' });
                    }
                })
                .catch(err => { console.error(err); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    });

    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.btnEditarHost');
        if(!btn) return;
        const idHost = btn.getAttribute('idHost');
        if(!idHost) return;

        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const fd = new FormData();
            fd.append('idHost', idHost);
            const res  = await fetch('ajax/host.ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data && data.id){
                pendingHostData = data;
            } else {
                Toast.fire({ icon: 'error', title: (data && data.mensaje) || 'No se pudo cargar el host' });
            }
        } catch(err) {
            console.error(err);
            Toast.fire({ icon: 'error', title: 'Error de conexión' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });

    document.querySelectorAll('.btnEliminarHost').forEach(btn => {
        btn.addEventListener('click', function() {
            const idHost = this.getAttribute('idHost');
            const nombre = this.getAttribute('nombre');

            Swal.fire({
                title: '¿Eliminar host?',
                html: `Estás a punto de eliminar <strong>${nombre}</strong>.<br>Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if(result.isConfirmed){
                    window.location = 'index.php?ruta=hosts&idHost=' + idHost;
                }
            });
        });
    });

});
</script>