<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: solo Administrador o (Centro de Atención / Despacho)
=============================================*/
if (!ControladorMedio::puedeVerModuloMedio()) {
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
$puedeEliminarMedio = ControladorMedio::esAdminMedio();

// Procesar borrado ANTES de renderizar la tabla (el controlador revalida)
ControladorMedio::ctrBorrarMedio();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-broadcast-tower me-2"></i> Gestión de Medios
        </h3>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearMedio">
            <i class="fas fa-plus me-1"></i> Nuevo Medio
        </button>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Medio</th>
                    <th style="width: 120px;">Color</th>
                    <th>Modificado por</th>
                    <th style="width: 160px;">Última modificación</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $medios = ControladorMedio::ctrMostrarMedio(null, null);

                if($medios && is_array($medios)){
                    foreach($medios as $key => $value){

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
                                    <i class="fas fa-broadcast-tower me-1"></i> '.htmlspecialchars($value["medio"]).'
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
                            echo '<button class="btn btn-success btn-sm btnActivarMedio" idMedio="'.$value["id"].'" estadoMedio="0">
                                    <i class="fas fa-check-circle"></i> Activo
                                  </button>';
                        }else{
                            echo '<button class="btn btn-danger btn-sm btnActivarMedio" idMedio="'.$value["id"].'" estadoMedio="1">
                                    <i class="fas fa-times-circle"></i> Inactivo
                                  </button>';
                        }

                        echo '</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-warning btn-sm btnEditarMedio" idMedio="'.$value["id"].'" data-bs-toggle="modal" data-bs-target="#modalEditarMedio" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>';

                                    /* ✅ Eliminar SOLO si es Administrador */
                                    if ($puedeEliminarMedio) {
                                        echo '<button class="btn btn-danger btn-sm btnEliminarMedio" idMedio="'.$value["id"].'" nombre="'.htmlspecialchars($value["medio"]).'" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                              </button>';
                                    }

                        echo '</div>
                            </td>
                        </tr>';
                    }
                }else{
                    echo '<tr><td colspan="7" class="text-center text-muted">No hay medios registrados</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearMedio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formCrearMedio" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nuevo Medio</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Medio <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nuevoMedio" placeholder="Ej: Radio" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color representativo</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" name="nuevoColor" id="nuevoColor" value="#0d6efd" title="Elige un color">
                            <input type="text" class="form-control" id="nuevoColorText" value="#0d6efd" readonly>
                        </div>
                        <small class="text-muted">Este color se usará para representar el medio como botón.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Medio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarMedio" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarMedio" method="post">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Medio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Medio <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="editarMedio" id="editarMedio" required>
                        <input type="hidden" name="idMedio" id="idMedio">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color representativo</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" name="editarColor" id="editarColor" value="#0d6efd" title="Elige un color">
                            <input type="text" class="form-control" id="editarColorText" value="#0d6efd" readonly>
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
    CREAR MEDIO
    =============================================*/
    const formCrear = document.getElementById('formCrearMedio');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();

            const formData = new FormData(this);

            Swal.fire({
                title: 'Guardando medio...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/medio.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Medio guardado' });
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
    EDITAR MEDIO
    =============================================*/
    const formEditar = document.getElementById('formEditarMedio');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();

            const formData = new FormData(this);

            Swal.fire({
                title: 'Actualizando medio...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/medio.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Medio actualizado' });
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
    document.querySelectorAll('.btnActivarMedio').forEach(btn => {
        btn.addEventListener('click', function() {
            const idMedio = this.getAttribute('idMedio');
            const estadoMedio = this.getAttribute('estadoMedio');
            const btnElement = this;

            const datos = new FormData();
            datos.append('activarMedio', estadoMedio);
            datos.append('activarId', idMedio);

            fetch('ajax/medio.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'ok'){
                        if(estadoMedio === '1'){
                            btnElement.classList.remove('btn-danger');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                            btnElement.setAttribute('estadoMedio', '0');
                        }else{
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-danger');
                            btnElement.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                            btnElement.setAttribute('estadoMedio', '1');
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
    document.querySelectorAll('.btnEditarMedio').forEach(btn => {
        btn.addEventListener('click', function() {
            const idMedio = this.getAttribute('idMedio');

            const datos = new FormData();
            datos.append('idMedio', idMedio);

            fetch('ajax/medio.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta && respuesta.id){
                        document.getElementById('idMedio').value = respuesta.id;
                        document.getElementById('editarMedio').value = respuesta.medio;

                        const color = respuesta.color || '#0d6efd';
                        document.getElementById('editarColor').value = color;
                        document.getElementById('editarColorText').value = color.toUpperCase();
                    }else{
                        Toast.fire({ icon: 'error', title: (respuesta && respuesta.mensaje) || 'No se pudo cargar el medio' });
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
    document.querySelectorAll('.btnEliminarMedio').forEach(btn => {
        btn.addEventListener('click', function() {
            const idMedio = this.getAttribute('idMedio');
            const nombre = this.getAttribute('nombre');

            Swal.fire({
                title: '¿Eliminar medio?',
                html: `Estás a punto de eliminar <strong>${nombre}</strong>.<br>Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if(result.isConfirmed){
                    window.location = 'index.php?ruta=medio&idMedio=' + idMedio;
                }
            });
        });
    });

});
</script>