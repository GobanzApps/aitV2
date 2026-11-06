<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: solo rol "Administrador"
(se ejecuta ANTES de procesar el borrado)
=============================================*/
$idUserActual = (int)($_SESSION["id"] ?? 0);
$esAdminView  = false;
if ($idUserActual > 0) {
    $uActual = ControladorUser::ctrMostrarUser("id", $idUserActual);
    if ($uActual) {
        $rolesIdsView = json_decode($uActual["id_rol"] ?? '[]', true);
        if (!is_array($rolesIdsView)) $rolesIdsView = [];
        foreach ($rolesIdsView as $idRView) {
            $rolView = ControladorRol::ctrMostrarRol("id", (int)$idRView);
            if ($rolView
                && (int)($rolView["estado"] ?? 0) === 1
                && strcasecmp(trim($rolView["rol"]), "Administrador") === 0) {
                $esAdminView = true;
                break;
            }
        }
    }
}
if (!$esAdminView) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-shield-alt me-2"></i>
            <strong>Acceso denegado.</strong>
            Esta sección es exclusiva del rol <strong>Administrador</strong>.
            <br><br>
            <a href="index.php?ruta=inicio" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
          </div>';
    return;
}

// Procesar borrado ANTES de renderizar la tabla
ControladorTagTipo::ctrBorrarTagTipo();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-tags me-2"></i> Gestión de Tipos de Tag
        </h3>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearTagTipo">
            <i class="fas fa-plus me-1"></i> Nuevo Tipo de Tag
        </button>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Tipo de Tag</th>
                    <th>Descripción</th>
                    <th style="width: 120px;">Color</th>
                    <th>Modificado por</th>
                    <th style="width: 160px;">Última modificación</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tipos = ControladorTagTipo::ctrMostrarTagTipo(null, null);

                if($tipos && is_array($tipos)){
                    foreach($tipos as $key => $value){

                        $usuarioData = ControladorUser::ctrMostrarUser("id", $value["id_user"]);
                        $nombreUsuario = $usuarioData ? $usuarioData["user"] : "—";

                        $modificacion = !empty($value["modificacion"])
                            ? date("d/m/Y H:i", strtotime($value["modificacion"]))
                            : "—";

                        $color = !empty($value["color"]) ? $value["color"] : "#0d6efd";

                        // ✅ Descripción: truncar a 60 chars con tooltip del texto completo
                        $descripcionCompleta = trim($value["descripcion"] ?? '');
                        if($descripcionCompleta !== ''){
                            $descripcionCorta = mb_strlen($descripcionCompleta) > 60
                                ? mb_substr($descripcionCompleta, 0, 60) . '…'
                                : $descripcionCompleta;
                            $descripcionHtml = '<span title="'.htmlspecialchars($descripcionCompleta).'">'
                                             . htmlspecialchars($descripcionCorta)
                                             . '</span>';
                        } else {
                            $descripcionHtml = '<span class="text-muted fst-italic">—</span>';
                        }

                        echo '<tr>
                            <td>'.($key + 1).'</td>
                            <td>
                                <span class="badge"
                                      style="background-color:'.htmlspecialchars($color).'; color:#fff; font-size:0.9rem; padding:8px 14px;">
                                    <i class="fas fa-tags me-1"></i> '.htmlspecialchars($value["tag_tipo"]).'
                                </span>
                            </td>
                            <td>'.$descripcionHtml.'</td>
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
                            echo '<button class="btn btn-success btn-sm btnActivarTagTipo" idTagTipo="'.$value["id"].'" estadoTagTipo="0">
                                    <i class="fas fa-check-circle"></i> Activo
                                  </button>';
                        }else{
                            echo '<button class="btn btn-danger btn-sm btnActivarTagTipo" idTagTipo="'.$value["id"].'" estadoTagTipo="1">
                                    <i class="fas fa-times-circle"></i> Inactivo
                                  </button>';
                        }

                        echo '</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-warning btn-sm btnEditarTagTipo" idTagTipo="'.$value["id"].'" data-bs-toggle="modal" data-bs-target="#modalEditarTagTipo" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm btnEliminarTagTipo" idTagTipo="'.$value["id"].'" nombre="'.htmlspecialchars($value["tag_tipo"]).'" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>';
                    }
                } else {
                    echo '<tr><td colspan="8" class="text-center text-muted">No hay tipos de tag registrados</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearTagTipo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formCrearTagTipo" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nuevo Tipo de Tag</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Tipo de Tag <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nuevoTagTipo" placeholder="Ej: Hardware, Software, Red" required>
                    </div>

                    <!-- ✅ NUEVO CAMPO: Descripción -->
                    <div class="mb-3">
                        <label class="form-label">
                            Descripción
                            <span class="text-muted small">(opcional)</span>
                        </label>
                        <textarea class="form-control"
                                  name="nuevoDescripcion"
                                  id="nuevoDescripcion"
                                  rows="3"
                                  maxlength="500"
                                  placeholder="Describa brevemente el tipo de tag..."></textarea>
                        <small class="text-muted">Máximo 500 caracteres.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Color representativo</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" name="nuevoColor" id="nuevoColor" value="#0d6efd" title="Elige un color">
                            <input type="text" class="form-control" id="nuevoColorText" value="#0d6efd" readonly>
                        </div>
                        <small class="text-muted">Este color se usará para representar el tipo de tag como botón.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Tipo de Tag
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarTagTipo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarTagTipo" method="post">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Tipo de Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Tipo de Tag <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="editarTagTipo" id="editarTagTipo" required>
                        <input type="hidden" name="idTagTipo" id="idTagTipo">
                    </div>

                    <!-- ✅ NUEVO CAMPO: Descripción -->
                    <div class="mb-3">
                        <label class="form-label">
                            Descripción
                            <span class="text-muted small">(opcional)</span>
                        </label>
                        <textarea class="form-control"
                                  name="editarDescripcion"
                                  id="editarDescripcion"
                                  rows="3"
                                  maxlength="500"
                                  placeholder="Describa brevemente el tipo de tag..."></textarea>
                        <small class="text-muted">Máximo 500 caracteres.</small>
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

    /* CREAR TIPO DE TAG */
    const formCrear = document.getElementById('formCrearTagTipo');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            Swal.fire({ title: 'Guardando tipo de tag...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/tag_tipo.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Tipo de tag guardado' });
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

    /* EDITAR TIPO DE TAG */
    const formEditar = document.getElementById('formEditarTagTipo');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            Swal.fire({ title: 'Actualizando tipo de tag...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/tag_tipo.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Tipo de tag actualizado' });
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

    /* ACTIVAR / DESACTIVAR */
    document.querySelectorAll('.btnActivarTagTipo').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTagTipo = this.getAttribute('idTagTipo');
            const estadoTagTipo = this.getAttribute('estadoTagTipo');
            const btnElement = this;

            const datos = new FormData();
            datos.append('activarTagTipo', estadoTagTipo);
            datos.append('activarId', idTagTipo);

            fetch('ajax/tag_tipo.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'ok'){
                        if(estadoTagTipo === '1'){
                            btnElement.classList.remove('btn-danger');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                            btnElement.setAttribute('estadoTagTipo', '0');
                        }else{
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-danger');
                            btnElement.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                            btnElement.setAttribute('estadoTagTipo', '1');
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

    /* CARGAR DATOS EN MODAL EDITAR */
    document.querySelectorAll('.btnEditarTagTipo').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTagTipo = this.getAttribute('idTagTipo');

            const datos = new FormData();
            datos.append('idTagTipo', idTagTipo);

            fetch('ajax/tag_tipo.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta && respuesta.id){
                        document.getElementById('idTagTipo').value = respuesta.id;
                        document.getElementById('editarTagTipo').value = respuesta.tag_tipo;

                        // ✅ Cargar descripción
                        document.getElementById('editarDescripcion').value = respuesta.descripcion || '';

                        const color = respuesta.color || '#0d6efd';
                        document.getElementById('editarColor').value = color;
                        document.getElementById('editarColorText').value = color.toUpperCase();
                    }else{
                        Toast.fire({ icon: 'error', title: (respuesta && respuesta.mensaje) || 'No se pudo cargar el tipo de tag' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Toast.fire({ icon: 'error', title: 'Error de conexión' });
                });
        });
    });

    /* ELIMINAR */
    document.querySelectorAll('.btnEliminarTagTipo').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTagTipo = this.getAttribute('idTagTipo');
            const nombre = this.getAttribute('nombre');

            Swal.fire({
                title: '¿Eliminar tipo de tag?',
                html: `Estás a punto de eliminar <strong>${nombre}</strong>.<br>Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if(result.isConfirmed){
                    window.location = 'index.php?ruta=tag-tipo&idTagTipo=' + idTagTipo;
                }
            });
        });
    });

});
</script>