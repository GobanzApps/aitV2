<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: solo Administrador o (Centro de Atención / Despacho)
=============================================*/
if (!ControladorEntidad::puedeVerModuloEntidad()) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-shield-alt me-2"></i>
            <strong>Acceso denegado.</strong>
            Esta sección es solo para el rol <strong>Administrador</strong>
            o usuarios con coordinación <strong>Centro de Atención</strong> o <strong>Despacho</strong>.
            <br><br>
            <a href="index.php?ruta=inicio" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
          </div>';
    return;
}

/* ¿Puede eliminar? Solo Administrador */
$puedeEliminarEntidad = ControladorEntidad::esAdminEntidad();

// Procesar borrado ANTES de renderizar la tabla (el controlador revalida)
ControladorEntidad::ctrBorrarEntidad();

// Helper: renderiza los tags de tipo_entidad (JSON de IDs) con color
function renderTiposEntidadTags($jsonIds){
    $ids = json_decode($jsonIds, true);
    if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

    $tipos = ControladorTipoEntidad::ctrMostrarTipoEntidad(null, null);
    $mapa = [];
    if($tipos && is_array($tipos)){
        foreach($tipos as $t){ $mapa[$t["id"]] = $t; }
    }

    $html = '';
    foreach($ids as $id){
        if(isset($mapa[$id])){
            $t = $mapa[$id];
            $color = $t["color"] ?: "#0d6efd";
            $html .= '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.75rem; padding:5px 9px;">'
                  . '<i class="fas fa-building me-1"></i>'.htmlspecialchars($t["tipo_entidad"]).'</span>';
        }
    }
    return $html ?: '<span class="text-muted">—</span>';
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-city me-2"></i> Gestión de Entidades
        </h3>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearEntidad">
            <i class="fas fa-plus me-1"></i> Nueva Entidad
        </button>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Entidad</th>
                    <th>Tipo(s) de Entidad</th>
                    <th style="width: 120px;">Color</th>
                    <th>Modificado por</th>
                    <th style="width: 160px;">Última modificación</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $entidades = ControladorEntidad::ctrMostrarEntidad(null, null);

                if($entidades && is_array($entidades)){
                    foreach($entidades as $key => $value){

                        // Obtener nombre del usuario que creó/editó
                        $usuarioData = ControladorUser::ctrMostrarUser("id", $value["id_user"]);
                        $nombreUsuario = $usuarioData ? $usuarioData["user"] : "—";

                        // Formatear fecha modificación
                        $modificacion = !empty($value["modificacion"])
                            ? date("d/m/Y H:i", strtotime($value["modificacion"]))
                            : "—";

                        // Color seguro
                        $color = !empty($value["color"]) ? $value["color"] : "#0d6efd";

                        echo '<tr>
                            <td>'.($key + 1).'</td>
                            <td>
                                <span class="badge"
                                      style="background-color:'.htmlspecialchars($color).'; color:#fff; font-size:0.9rem; padding:8px 14px;">
                                    <i class="fas fa-city me-1"></i> '.htmlspecialchars($value["entidad"]).'
                                </span>
                            </td>
                            <td>'.renderTiposEntidadTags($value["tipo_entidad"] ?? '').'</td>
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
                            echo '<button class="btn btn-success btn-sm btnActivarEntidad" idEntidad="'.$value["id"].'" estadoEntidad="0">
                                    <i class="fas fa-check-circle"></i> Activo
                                  </button>';
                        }else{
                            echo '<button class="btn btn-danger btn-sm btnActivarEntidad" idEntidad="'.$value["id"].'" estadoEntidad="1">
                                    <i class="fas fa-times-circle"></i> Inactivo
                                  </button>';
                        }

                        echo '</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-warning btn-sm btnEditarEntidad" idEntidad="'.$value["id"].'" data-bs-toggle="modal" data-bs-target="#modalEditarEntidad" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>';

                                    /* ✅ Eliminar SOLO si es Administrador */
                                    if ($puedeEliminarEntidad) {
                                        echo '<button class="btn btn-danger btn-sm btnEliminarEntidad" idEntidad="'.$value["id"].'" nombre="'.htmlspecialchars($value["entidad"]).'" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                              </button>';
                                    }

                        echo '</div>
                            </td>
                        </tr>';
                    }
                }else{
                    echo '<tr><td colspan="8" class="text-center text-muted">No hay entidades registradas</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearEntidad" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formCrearEntidad" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nueva Entidad</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nombre de la Entidad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nuevaEntidad" placeholder="Ej: Alcaldía de Caracas" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-building me-1 text-muted"></i> Tipo(s) de Entidad
                            </label>
                            <select id="nuevoTipoEntidad" name="nuevoTipoEntidad[]" multiple placeholder="Seleccione tipos de entidad..."></select>
                            <small class="text-muted">Solo se muestran los tipos de entidad activos.</small>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Color representativo</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" name="nuevoColor" id="nuevoColor" value="#0d6efd" title="Elige un color">
                                <input type="text" class="form-control" id="nuevoColorText" value="#0D6EFD" readonly>
                            </div>
                            <small class="text-muted">Este color se usará para representar la entidad como botón.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Entidad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarEntidad" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formEditarEntidad" method="post">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Entidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nombre de la Entidad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="editarEntidad" id="editarEntidad" required>
                            <input type="hidden" name="idEntidad" id="idEntidad">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-building me-1 text-muted"></i> Tipo(s) de Entidad
                            </label>
                            <select id="editarTipoEntidad" name="editarTipoEntidad[]" multiple placeholder="Seleccione tipos de entidad..."></select>
                            <small class="text-muted">Solo se muestran los tipos de entidad activos.</small>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Color representativo</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" name="editarColor" id="editarColor" value="#0d6efd" title="Elige un color">
                                <input type="text" class="form-control" id="editarColorText" value="#0D6EFD" readonly>
                            </div>
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

<!-- ═══════════════ SCRIPTS ESPECÍFICOS ═══════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    /*=============================================
    TOAST REUTILIZABLE
    =============================================*/
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

    // Sincronizar color picker → texto (crear)
    const nuevoColor = document.getElementById('nuevoColor');
    const nuevoColorText = document.getElementById('nuevoColorText');
    if(nuevoColor){
        nuevoColor.addEventListener('input', function(){
            nuevoColorText.value = this.value.toUpperCase();
        });
    }

    // Sincronizar color picker → texto (editar)
    const editarColor = document.getElementById('editarColor');
    const editarColorText = document.getElementById('editarColorText');
    if(editarColor){
        editarColor.addEventListener('input', function(){
            editarColorText.value = this.value.toUpperCase();
        });
    }

    /*=============================================
    Helpers de Tom Select
    =============================================*/
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
        render: {
            option: renderOpcion,
            item: renderItem
        }
    };

    let tsNuevoTipoEntidad, tsEditarTipoEntidad;

    tsNuevoTipoEntidad  = new TomSelect('#nuevoTipoEntidad',  Object.assign({}, tomSelectConfig));
    tsEditarTipoEntidad = new TomSelect('#editarTipoEntidad', Object.assign({}, tomSelectConfig));

    /*=============================================
    Cargar tipos de entidad activos
    =============================================*/
    fetch('ajax/user.ajax.php', {
        method: 'POST',
        body: (() => { const fd = new FormData(); fd.append('listarTiposEntidadActivos','1'); return fd; })()
    })
    .then(res => res.json())
    .then(resp => {
        if(resp.status === 'ok' && Array.isArray(resp.data)){
            resp.data.forEach(item => {
                tsNuevoTipoEntidad.addOption({ id: item.id, text: item.texto, color: item.color });
                tsEditarTipoEntidad.addOption({ id: item.id, text: item.texto, color: item.color });
            });
            tsNuevoTipoEntidad.refreshOptions(false);
            tsEditarTipoEntidad.refreshOptions(false);
        }
    })
    .catch(err => console.error('Error cargando tipos de entidad', err));

    /*=============================================
    CREAR ENTIDAD
    =============================================*/
    const formCrear = document.getElementById('formCrearEntidad');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();

            const formData = new FormData(this);

            Swal.fire({
                title: 'Guardando entidad...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/entidad.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Entidad guardada' });
                        setTimeout(() => location.reload(), 1800);
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error al guardar' });
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
    EDITAR ENTIDAD
    =============================================*/
    const formEditar = document.getElementById('formEditarEntidad');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();

            const formData = new FormData(this);

            Swal.fire({
                title: 'Actualizando entidad...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/entidad.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Entidad actualizada' });
                        setTimeout(() => location.reload(), 1800);
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error al actualizar' });
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
    ACTIVAR / DESACTIVAR
    =============================================*/
    document.querySelectorAll('.btnActivarEntidad').forEach(btn => {
        btn.addEventListener('click', function() {
            const idEntidad = this.getAttribute('idEntidad');
            const estadoEntidad = this.getAttribute('estadoEntidad');
            const btnElement = this;

            const datos = new FormData();
            datos.append('activarEntidad', estadoEntidad);
            datos.append('activarId', idEntidad);

            fetch('ajax/entidad.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'ok'){
                        if(estadoEntidad === '1'){
                            btnElement.classList.remove('btn-danger');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                            btnElement.setAttribute('estadoEntidad', '0');
                        }else{
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-danger');
                            btnElement.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                            btnElement.setAttribute('estadoEntidad', '1');
                        }
                        Toast.fire({ icon: 'success', title: 'Estado actualizado' });
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error al actualizar el estado' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Toast.fire({ icon: 'error', title: 'Error de conexión' });
                });
        });
    });

    /*=============================================
    CARGAR DATOS EN MODAL EDITAR
    =============================================*/
    document.querySelectorAll('.btnEditarEntidad').forEach(btn => {
        btn.addEventListener('click', function() {
            const idEntidad = this.getAttribute('idEntidad');

            const datos = new FormData();
            datos.append('idEntidad', idEntidad);

            fetch('ajax/entidad.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta && respuesta.id){
                        document.getElementById('idEntidad').value = respuesta.id;
                        document.getElementById('editarEntidad').value = respuesta.entidad;

                        const color = respuesta.color || '#0d6efd';
                        document.getElementById('editarColor').value = color;
                        document.getElementById('editarColorText').value = color.toUpperCase();

                        // Cargar tipos de entidad seleccionados
                        let tipos = [];
                        try { tipos = JSON.parse(respuesta.tipo_entidad || '[]'); } catch(e){ tipos = []; }
                        if(!Array.isArray(tipos)) tipos = [];
                        tipos.forEach(id => {
                            id = String(id);
                            if(!tsEditarTipoEntidad.options[id]){
                                // Si por alguna razón no existe en la lista (p.ej. está inactivo),
                                // lo agregamos como opción gris para no perder el dato.
                                tsEditarTipoEntidad.addOption({ id: id, text: 'ID '+id, color: '#6c757d' });
                            }
                        });
                        tsEditarTipoEntidad.setValue(tipos.map(String), true);
                    }else{
                        Toast.fire({ icon: 'error', title: (respuesta && respuesta.mensaje) || 'No se pudo cargar la entidad' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Toast.fire({ icon: 'error', title: 'Error de conexión' });
                });
        });
    });

    /*=============================================
    ELIMINAR (solo Administrador — el botón no existe si no es admin)
    =============================================*/
    document.querySelectorAll('.btnEliminarEntidad').forEach(btn => {
        btn.addEventListener('click', function() {
            const idEntidad = this.getAttribute('idEntidad');
            const nombre = this.getAttribute('nombre');

            Swal.fire({
                title: '¿Eliminar entidad?',
                html: `Estás a punto de eliminar <strong>${nombre}</strong>.<br>Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if(result.isConfirmed){
                    window.location = 'index.php?ruta=entidad&idEntidad=' + idEntidad;
                }
            });
        });
    });

});
</script>