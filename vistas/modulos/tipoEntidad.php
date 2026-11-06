<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: solo Administrador o (Centro de Atención / Despacho)
=============================================*/
if (!ControladorTipoEntidad::puedeVerModuloTipoEntidad()) {
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
$puedeEliminarTipoEntidad = ControladorTipoEntidad::esAdminTipoEntidad();

// Procesar borrado ANTES de renderizar la tabla (el controlador revalida)
ControladorTipoEntidad::ctrBorrarTipoEntidad();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-building me-2"></i> Gestión de Tipos de Entidad
        </h3>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearTipoEntidad">
            <i class="fas fa-plus me-1"></i> Nuevo Tipo de Entidad
        </button>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Tipo de Entidad</th>
                    <th style="width: 120px;">Color</th>
                    <th>Modificado por</th>
                    <th style="width: 160px;">Última modificación</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tipos = ControladorTipoEntidad::ctrMostrarTipoEntidad(null, null);

                if($tipos && is_array($tipos)){
                    foreach($tipos as $key => $value){

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
                                    <i class="fas fa-building me-1"></i> '.htmlspecialchars($value["tipo_entidad"]).'
                                </span>
                            </td>
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
                            echo '<button class="btn btn-success btn-sm btnActivarTipoEntidad" idTipoEntidad="'.$value["id"].'" estadoTipoEntidad="0">
                                    <i class="fas fa-check-circle"></i> Activo
                                  </button>';
                        }else{
                            echo '<button class="btn btn-danger btn-sm btnActivarTipoEntidad" idTipoEntidad="'.$value["id"].'" estadoTipoEntidad="1">
                                    <i class="fas fa-times-circle"></i> Inactivo
                                  </button>';
                        }

                        echo '</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-warning btn-sm btnEditarTipoEntidad" idTipoEntidad="'.$value["id"].'" data-bs-toggle="modal" data-bs-target="#modalEditarTipoEntidad" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>';

                                    /* ✅ Eliminar SOLO si es Administrador */
                                    if ($puedeEliminarTipoEntidad) {
                                        echo '<button class="btn btn-danger btn-sm btnEliminarTipoEntidad" idTipoEntidad="'.$value["id"].'" nombre="'.htmlspecialchars($value["tipo_entidad"]).'" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                              </button>';
                                    }

                        echo '</div>
                            </td>
                        </tr>';
                    }
                }else{
                    echo '<tr><td colspan="7" class="text-center text-muted">No hay tipos de entidad registrados</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearTipoEntidad" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formCrearTipoEntidad" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nuevo Tipo de Entidad</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Tipo de Entidad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nuevoTipoEntidad" placeholder="Ej: Alcaldía" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color representativo</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" name="nuevoColor" id="nuevoColor" value="#0d6efd" title="Elige un color">
                            <input type="text" class="form-control" id="nuevoColorText" value="#0D6EFD" readonly>
                        </div>
                        <small class="text-muted">Este color se usará para representar el tipo de entidad como botón.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Tipo de Entidad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarTipoEntidad" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarTipoEntidad" method="post">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Tipo de Entidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Tipo de Entidad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="editarTipoEntidad" id="editarTipoEntidad" required>
                        <input type="hidden" name="idTipoEntidad" id="idTipoEntidad">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color representativo</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" name="editarColor" id="editarColor" value="#0d6efd" title="Elige un color">
                            <input type="text" class="form-control" id="editarColorText" value="#0D6EFD" readonly>
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
    CREAR TIPO DE ENTIDAD
    =============================================*/
    const formCrear = document.getElementById('formCrearTipoEntidad');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();

            const formData = new FormData(this);

            Swal.fire({
                title: 'Guardando tipo de entidad...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/tipo_entidad.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Tipo de entidad guardado' });
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
    EDITAR TIPO DE ENTIDAD
    =============================================*/
    const formEditar = document.getElementById('formEditarTipoEntidad');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();

            const formData = new FormData(this);

            Swal.fire({
                title: 'Actualizando tipo de entidad...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/tipo_entidad.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Tipo de entidad actualizado' });
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
    document.querySelectorAll('.btnActivarTipoEntidad').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTipoEntidad = this.getAttribute('idTipoEntidad');
            const estadoTipoEntidad = this.getAttribute('estadoTipoEntidad');
            const btnElement = this;

            const datos = new FormData();
            datos.append('activarTipoEntidad', estadoTipoEntidad);
            datos.append('activarId', idTipoEntidad);

            fetch('ajax/tipo_entidad.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'ok'){
                        if(estadoTipoEntidad === '1'){
                            btnElement.classList.remove('btn-danger');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                            btnElement.setAttribute('estadoTipoEntidad', '0');
                        }else{
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-danger');
                            btnElement.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                            btnElement.setAttribute('estadoTipoEntidad', '1');
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
    document.querySelectorAll('.btnEditarTipoEntidad').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTipoEntidad = this.getAttribute('idTipoEntidad');

            const datos = new FormData();
            datos.append('idTipoEntidad', idTipoEntidad);

            fetch('ajax/tipo_entidad.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta && respuesta.id){
                        document.getElementById('idTipoEntidad').value = respuesta.id;
                        document.getElementById('editarTipoEntidad').value = respuesta.tipo_entidad;

                        const color = respuesta.color || '#0d6efd';
                        document.getElementById('editarColor').value = color;
                        document.getElementById('editarColorText').value = color.toUpperCase();
                    }else{
                        Toast.fire({ icon: 'error', title: (respuesta && respuesta.mensaje) || 'No se pudo cargar el tipo de entidad' });
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
    document.querySelectorAll('.btnEliminarTipoEntidad').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTipoEntidad = this.getAttribute('idTipoEntidad');
            const nombre = this.getAttribute('nombre');

            Swal.fire({
                title: '¿Eliminar tipo de entidad?',
                html: `Estás a punto de eliminar <strong>${nombre}</strong>.<br>Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if(result.isConfirmed){
                    window.location = 'index.php?ruta=tipoEntidad&idTipoEntidad=' + idTipoEntidad;
                }
            });
        });
    });

});
</script>