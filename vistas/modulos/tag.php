<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: solo Administrador o (Centro de Atención / Despacho)
=============================================*/
if (!ControladorTag::puedeVerModuloTag()) {
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
$puedeEliminarTag = ControladorTag::esAdminTag();

// Procesar borrado ANTES de renderizar la tabla (el controlador revalida)
ControladorTag::ctrBorrarTag();

/*=============================================
✅ Helper: renderiza los tipos de tag (JSON de IDs) como badges.
   ═══ NUEVO ═══  Añade `title` con la descripción del tipo
   (para que el navegador muestre el tooltip al hacer hover).
=============================================*/
function renderTagTiposTags($jsonIds){
    $ids = json_decode($jsonIds, true);
    if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

    $tipos = ControladorTagTipo::ctrMostrarTagTipo(null, null);
    $mapa = [];
    if($tipos && is_array($tipos)){
        foreach($tipos as $t){ $mapa[(int)$t["id"]] = $t; }
    }

    $html = '';
    foreach($ids as $id){
        $id = (int)$id;
        if(isset($mapa[$id])){
            $t           = $mapa[$id];
            $color       = $t["color"] ?: "#0d6efd";
            $descripcion = trim($t["descripcion"] ?? '');

            $titleAttr   = '';
            $cursorStyle = '';
            if($descripcion !== ''){
                $titleAttr   = ' title="'.htmlspecialchars($descripcion, ENT_QUOTES).'"';
                $cursorStyle = ' cursor:help;';
            }

            $html .= '<span class="badge"'.$titleAttr.' style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.72rem; padding:5px 9px;'.$cursorStyle.'">'
                  . '<i class="fas fa-tags me-1"></i>'.htmlspecialchars($t["tag_tipo"]).'</span>';
        }
    }
    return $html ?: '<span class="text-muted">—</span>';
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-tags me-2"></i> Gestión de Tags
        </h3>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearTag">
            <i class="fas fa-plus me-1"></i> Nuevo Tag
        </button>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Tag</th>
                    <th>Tipo(s) de Tag</th>
                    <th style="width: 120px;">Color</th>
                    <th>Modificado por</th>
                    <th style="width: 160px;">Última modificación</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tags = ControladorTag::ctrMostrarTag(null, null);

                if($tags && is_array($tags)){
                    foreach($tags as $key => $value){

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
                                    <i class="fas fa-tag me-1"></i> '.htmlspecialchars($value["tag"]).'
                                </span>
                            </td>
                            <td>'.renderTagTiposTags($value["id_tag_tipo"] ?? '').'</td>
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
                            echo '<button class="btn btn-success btn-sm btnActivarTag" idTag="'.$value["id"].'" estadoTag="0">
                                    <i class="fas fa-check-circle"></i> Activo
                                  </button>';
                        }else{
                            echo '<button class="btn btn-danger btn-sm btnActivarTag" idTag="'.$value["id"].'" estadoTag="1">
                                    <i class="fas fa-times-circle"></i> Inactivo
                                  </button>';
                        }

                        echo '</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-warning btn-sm btnEditarTag" idTag="'.$value["id"].'" data-bs-toggle="modal" data-bs-target="#modalEditarTag" title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>';

                                    /* ✅ Eliminar SOLO si es Administrador */
                                    if ($puedeEliminarTag) {
                                        echo '<button class="btn btn-danger btn-sm btnEliminarTag" idTag="'.$value["id"].'" nombre="'.htmlspecialchars($value["tag"]).'" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                              </button>';
                                    }

                        echo '</div>
                            </td>
                        </tr>';
                    }
                } else {
                    echo '<tr><td colspan="8" class="text-center text-muted">No hay tags registrados</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearTag" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formCrearTag" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nuevo Tag</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-12">
                            <label class="form-label">Nombre del Tag <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nuevoTag" placeholder="Ej: Urgente" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-tags me-1 text-muted"></i> Tipo(s) de Tag
                            </label>
                            <select id="nuevoIdTagTipo" name="nuevoIdTagTipo[]" multiple placeholder="Buscar tipo de tag..."></select>
                            <small class="text-muted">
                                Solo se muestran los tipos de tag activos. Escribe para filtrar por nombre o descripción.
                            </small>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Color representativo</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" name="nuevoColor" id="nuevoColor" value="#0d6efd" title="Elige un color">
                                <input type="text" class="form-control" id="nuevoColorText" value="#0D6EFD" readonly>
                            </div>
                            <small class="text-muted">Este color se usará para representar el tag como botón.</small>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Tag
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarTag" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formEditarTag" method="post">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-12">
                            <label class="form-label">Nombre del Tag <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="editarTag" id="editarTag" required>
                            <input type="hidden" name="idTag" id="idTag">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-tags me-1 text-muted"></i> Tipo(s) de Tag
                            </label>
                            <select id="editarIdTagTipo" name="editarIdTagTipo[]" multiple placeholder="Buscar tipo de tag..."></select>
                            <small class="text-muted">
                                Solo se muestran los tipos de tag activos. Escribe para filtrar por nombre o descripción.
                            </small>
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
    ✅ NUEVO: TomSelect con REMOTE DATA + PAGINACIÓN
       para los tipos de tag.
       - Carga 10 opciones por página desde el backend.
       - Búsqueda por nombre O descripción (el filtrado lo hace el backend).
       - Scroll infinito al fondo del dropdown.
       - Cada opción se pinta en 2 columnas:
           izquierda: nombre del tipo de tag
           derecha:   descripción (truncada)
    =============================================*/
    function createRemoteTagTipoSelect(selector){
        const state = {
            currentQuery: '',
            currentPage: 0,
            hasMore: true,
            isLoading: false
        };

        function fetchPage(page, query){
            const fd = new FormData();
            fd.append('listarTagTiposPaginado', '1');
            fd.append('page', page);
            fd.append('q', query || '');
            return fetch('ajax/tag.ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .catch(() => ({ status: 'error', data: [], has_more: false }));
        }

        const ts = new TomSelect(selector, {
            plugins: ['remove_button'],
            persist: false,
            create: false,
            maxItems: null,
            valueField: 'id',
            labelField: 'texto',
            searchField: 'texto',
            loadThrottle: 300,
            preload: true,

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
                fetchPage(state.currentPage, query).then(resp => {
                    state.isLoading = false;
                    const items = (resp.status === 'ok' && Array.isArray(resp.data)) ? resp.data : [];
                    callback(items);
                    state.hasMore = !!resp.has_more;
                });
            },

            render: {
                /* 2 columnas: nombre (izquierda) | descripción (derecha) */
                option: function(data, escape){
                    const color = data.color || '#0d6efd';
                    const desc  = data.descripcion || '';
                    const descMostrar = desc ? escape(desc) : '—';
                    const descTitle   = desc ? `title="${escape(desc)}"` : '';
                    return `<div class="d-flex justify-content-between align-items-center gap-2" style="width:100%;">
                        <div class="d-flex align-items-center" style="min-width:0; flex:1;">
                            <span class="tag-dot" style="background-color:${color};"></span>
                            <span class="option-label text-truncate">${escape(data.texto)}</span>
                        </div>
                        <small class="text-muted text-truncate"
                               style="max-width:55%; text-align:right; font-size:0.78rem;"
                               ${descTitle}>
                            ${descMostrar}
                        </small>
                    </div>`;
                },
                item: function(data, escape){
                    const color = data.color || '#0d6efd';
                    return `<div style="--tag-color:${color};" class="item-tag">${escape(data.texto)}</div>`;
                }
            }
        });

        /* Scroll infinito dentro del dropdown */
        ts.on('dropdown_open', function(){
            setTimeout(() => {
                const dropdown = ts.dropdown_content;
                if(!dropdown || dropdown.dataset.scrollBound === '1') return;
                dropdown.dataset.scrollBound = '1';

                dropdown.addEventListener('scroll', function(){
                    if(this.scrollTop + this.clientHeight < this.scrollHeight - 30) return;
                    if(!state.hasMore || state.isLoading) return;

                    state.isLoading = true;
                    state.currentPage++;

                    const q = state.currentQuery;
                    fetchPage(state.currentPage, q).then(resp => {
                        state.isLoading = false;
                        const items = (resp.status === 'ok' && Array.isArray(resp.data))
                            ? resp.data
                            : [];
                        items.forEach(item => {
                            if(!ts.options[String(item.id)]){
                                ts.addOption(item);
                            }
                        });
                        ts.refreshOptions(false);
                        state.hasMore = !!resp.has_more;
                    }).catch(() => { state.isLoading = false; });
                });
            }, 100);
        });

        ts.resetRemote = function(){
            state.currentQuery = '';
            state.currentPage  = 0;
            state.hasMore      = true;
            state.isLoading    = false;
        };

        return ts;
    }

    /* ✅ Precarga los tipos ya asignados a un tag (para el modal Editar).
       Pide al backend la info completa por IDs, así aunque no estén en la
       primera página (o estén inactivos), se muestran correctamente. */
    async function preloadSelectedTagTipos(ts, jsonIds){
        let ids = [];
        try { ids = JSON.parse(jsonIds || '[]'); } catch(e){ ids = []; }
        if(!Array.isArray(ids)) ids = [];
        ids = ids.map(String);

        if(ids.length === 0) return;

        const fd = new FormData();
        fd.append('obtenerTagTiposPorIds', '1');
        ids.forEach(id => fd.append('ids[]', id));

        try {
            const res  = await fetch('ajax/tag.ajax.php', { method: 'POST', body: fd });
            const resp = await res.json();
            if(resp.status === 'ok' && Array.isArray(resp.data)){
                resp.data.forEach(item => {
                    const itemId = String(item.id);
                    if(!ts.options[itemId]){
                        ts.addOption({
                            id:          itemId,
                            texto:       item.texto,
                            color:       item.color,
                            descripcion: item.descripcion || ''
                        });
                    }
                });
            }
        } catch(err) {
            console.error('[preloadSelectedTagTipos]', err);
        }

        ts.setValue(ids, true);
        ts.refreshOptions(false);
    }

    let tsNuevoTagTipo  = null;
    let tsEditarTagTipo = null;
    let pendingTagData  = null;

    /*=============================================
    MODAL CREAR
    =============================================*/
    const modalCrearEl = document.getElementById('modalCrearTag');
    if(modalCrearEl){
        modalCrearEl.addEventListener('shown.bs.modal', () => {
            if(!tsNuevoTagTipo){
                tsNuevoTagTipo = createRemoteTagTipoSelect('#nuevoIdTagTipo');
            } else if(typeof tsNuevoTagTipo.resetRemote === 'function'){
                tsNuevoTagTipo.resetRemote();
            }
            tsNuevoTagTipo.refreshOptions(false);
        });

        modalCrearEl.addEventListener('hidden.bs.modal', () => {
            if(tsNuevoTagTipo) tsNuevoTagTipo.clear(true);
            const f = document.getElementById('formCrearTag');
            if(f) f.reset();
        });
    }

    /*=============================================
    MODAL EDITAR
    =============================================*/
    const modalEditarEl = document.getElementById('modalEditarTag');
    if(modalEditarEl){
        modalEditarEl.addEventListener('shown.bs.modal', async () => {
            if(!tsEditarTagTipo){
                tsEditarTagTipo = createRemoteTagTipoSelect('#editarIdTagTipo');
            }
            if(pendingTagData){
                const d = pendingTagData;

                document.getElementById('idTag').value         = d.id;
                document.getElementById('editarTag').value     = d.tag || '';

                const color = d.color || '#0d6efd';
                document.getElementById('editarColor').value     = color;
                document.getElementById('editarColorText').value = color.toUpperCase();

                await preloadSelectedTagTipos(tsEditarTagTipo, d.id_tag_tipo);

                pendingTagData = null;
            }
            tsEditarTagTipo.refreshOptions(false);
        });
    }

    /*=============================================
    CREAR TAG
    =============================================*/
    const formCrear = document.getElementById('formCrearTag');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            Swal.fire({ title: 'Guardando tag...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/tag.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Tag guardado' });
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
    EDITAR TAG
    =============================================*/
    const formEditar = document.getElementById('formEditarTag');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            Swal.fire({ title: 'Actualizando tag...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/tag.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Tag actualizado' });
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
    document.querySelectorAll('.btnActivarTag').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTag = this.getAttribute('idTag');
            const estadoTag = this.getAttribute('estadoTag');
            const btnElement = this;

            const datos = new FormData();
            datos.append('activarTag', estadoTag);
            datos.append('activarId', idTag);

            fetch('ajax/tag.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'ok'){
                        if(estadoTag === '1'){
                            btnElement.classList.remove('btn-danger');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                            btnElement.setAttribute('estadoTag', '0');
                        }else{
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-danger');
                            btnElement.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                            btnElement.setAttribute('estadoTag', '1');
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
    document.querySelectorAll('.btnEditarTag').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTag = this.getAttribute('idTag');

            const datos = new FormData();
            datos.append('idTag', idTag);

            fetch('ajax/tag.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta && respuesta.id){
                        pendingTagData = respuesta;
                    }else{
                        Toast.fire({ icon: 'error', title: (respuesta && respuesta.mensaje) || 'No se pudo cargar el tag' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Toast.fire({ icon: 'error', title: 'Error de conexión' });
                });
        });
    });

    /*=============================================
    ELIMINAR (solo Administrador)
    =============================================*/
    document.querySelectorAll('.btnEliminarTag').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTag = this.getAttribute('idTag');
            const nombre = this.getAttribute('nombre');

            Swal.fire({
                title: '¿Eliminar tag?',
                html: `Estás a punto de eliminar <strong>${nombre}</strong>.<br>Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if(result.isConfirmed){
                    window.location = 'index.php?ruta=tag&idTag=' + idTag;
                }
            });
        });
    });

});
</script>