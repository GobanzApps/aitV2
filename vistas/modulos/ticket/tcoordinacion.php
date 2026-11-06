<?php
/*=============================================
Este archivo se incluye desde vistas/modulos/ticket.php
por lo que $idTicket y $ticket YA están definidos.
=============================================*/
if(!isset($idTicket) || $idTicket <= 0){
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No se pudo identificar el ticket.</div>';
    return;
}

/*=============================================
✅ Contexto de autorización (espejo del backend)
=============================================*/
$ticketFinalizadoTcoord = isset($ticket) && ((int)($ticket["check"] ?? 0) === 1);

$authTcoord = (isset($authTicket) && $authTicket) ? $authTicket : ControladorTicket::authTicket();

$esAdminTcoord          = ControladorTicket::esAdminTicket($authTcoord);
$esCoordinadorTcoord    = ControladorTicket::esCoordinadorTicket($authTcoord);
$esEspecialistaTcoord   = ControladorTicket::esEspecialistaTicket($authTcoord);
$esCentroAtencionTcoord = ControladorTicket::esCentroAtencionTicket($authTcoord);

// Agregar:      Admin || Coordinador || Especialista   (y ticket NO finalizado)
// Editar/Elim:  Admin || Coordinador de Centro de Atención (y ticket NO finalizado)
$puedeAgregarTcoord        = !$ticketFinalizadoTcoord
                           && ($esAdminTcoord || $esCoordinadorTcoord || $esEspecialistaTcoord);

$puedeEditarEliminarTcoord = !$ticketFinalizadoTcoord
                           && ($esAdminTcoord || $esCentroAtencionTcoord);

/*=============================================
Datos
=============================================*/
$tcoordinaciones = ControladorTcoordinacion::ctrMostrarTcoordinacionPorTicket($idTicket);
$tcoordinaciones = $tcoordinaciones ?: [];

$todosUsuarios = ControladorUser::ctrMostrarUser(null, null);
if(!$todosUsuarios || !is_array($todosUsuarios)) $todosUsuarios = [];
$mapaUsuarios = [];
foreach($todosUsuarios as $u){ $mapaUsuarios[(int)$u["id"]] = $u; }

$todosRoles = ControladorRol::ctrMostrarRol(null, null);
if(!$todosRoles || !is_array($todosRoles)) $todosRoles = [];
$mapaRoles = [];
foreach($todosRoles as $r){ $mapaRoles[(int)$r["id"]] = $r; }

$todasCoordinaciones = ControladorCoordinacion::ctrMostrarCoordinacion(null, null);
if(!$todasCoordinaciones || !is_array($todasCoordinaciones)) $todasCoordinaciones = [];
$mapaCoordinaciones = [];
foreach($todasCoordinaciones as $c){ $mapaCoordinaciones[(int)$c["id"]] = $c; }

/*=============================================
Helpers
=============================================*/
if(!function_exists('tcoordColorUsuario')){
    function tcoordColorUsuario($usuario, $mapaRoles){
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

if(!function_exists('tcoordIniciales')){
    function tcoordIniciales($nombre){
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

if(!function_exists('tcoordNombreUsuario')){
    function tcoordNombreUsuario($usuario){
        if(!$usuario) return null;
        return !empty($usuario["name"]) ? $usuario["name"] : $usuario["user"];
    }
}
?>

<!-- ═══════════════════════════════════════════════════════════
     SECCIÓN COORDINACIONES ASIGNADAS (tcoordinacion)
     ═══════════════════════════════════════════════════════════ -->

<div class="d-flex justify-content-between align-items-center mb-3 mt-4">
    <h5 class="mb-0">
        <i class="fas fa-sitemap me-2 text-primary"></i> Coordinaciones Asignadas
        <span class="badge text-bg-primary ms-2"><?php echo count($tcoordinaciones); ?></span>
    </h5>

    <?php if ($ticketFinalizadoTcoord): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="Ticket finalizado: no se pueden agregar coordinaciones">
            <i class="fas fa-lock me-1"></i> Ticket finalizado
        </button>
    <?php elseif (!$puedeAgregarTcoord): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="Solo Administrador, Coordinador o Especialista/Técnico">
            <i class="fas fa-lock me-1"></i> Solo lectura
        </button>
    <?php else: ?>
        <button type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalCrearTcoordinacion">
            <i class="fas fa-plus me-1"></i> Agregar Coordinaciones
        </button>
    <?php endif; ?>
</div>

<?php if ($ticketFinalizadoTcoord): ?>
    <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-lock me-2"></i>
        <div>
            <strong>Ticket finalizado.</strong>
            Las asignaciones de coordinaciones son de solo lectura: no se pueden agregar, editar ni eliminar.
        </div>
    </div>
<?php elseif (!$puedeAgregarTcoord && !$puedeEditarEliminarTcoord): ?>
    <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-user-shield me-2"></i>
        <div>
            <strong>No tienes permisos para modificar las coordinaciones asignadas.</strong><br>
            Para <strong>agregar</strong>: Administrador, Coordinador o Especialista/Técnico.<br>
            Para <strong>editar/eliminar</strong>: Administrador o Coordinador de Centro de Atención.
        </div>
    </div>
<?php endif; ?>

<?php if(empty($tcoordinaciones)): ?>

    <div class="alert alert-info d-flex align-items-center mb-0">
        <i class="fas fa-info-circle me-2"></i>
        <div>Aún no hay coordinaciones asignadas a este ticket.</div>
    </div>

<?php else: ?>

    <?php foreach($tcoordinaciones as $t):

        $creador = $mapaUsuarios[(int)$t["tcoordinacion"]] ?? null;
        $nombreCreador = tcoordNombreUsuario($creador);
        if($nombreCreador === null){
            $nombreCreador = "Usuario #" . $t["tcoordinacion"];
        }
        $colorCreador = $creador ? tcoordColorUsuario($creador, $mapaRoles) : "#6c757d";
        $iniCreador   = tcoordIniciales($nombreCreador);

        $fechaMostrar = !empty($t["modificacion"])
            ? date("d/m/Y H:i", strtotime($t["modificacion"]))
            : "—";

        $idsAsignados = json_decode($t["id_coordinacion"], true);
        if(!is_array($idsAsignados)) $idsAsignados = [];
    ?>

    <article class="d-flex gap-3 mb-3 p-3 border rounded bg-body-tertiary" data-tcoordinacion-id="<?php echo (int)$t["id"]; ?>">
        <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm"
             style="width: 45px; height: 45px; background-color: <?php echo htmlspecialchars($colorCreador); ?>; font-weight: 600;"
             aria-hidden="true">
            <?php echo htmlspecialchars($iniCreador); ?>
        </div>

        <div class="flex-grow-1">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h4 class="h6 mb-0">
                        <i class="fas fa-sitemap me-1 text-success"></i>
                        <?php echo htmlspecialchars($nombreCreador); ?>
                        <small class="text-muted ms-2">asignó coordinaciones a este ticket</small>
                    </h4>
                    <small class="text-secondary">
                        <i class="fas fa-clock me-1"></i><?php echo $fechaMostrar; ?>
                    </small>
                </div>

                <?php if ($puedeEditarEliminarTcoord): ?>
                <div class="btn-group">
                    <button type="button"
                            class="btn btn-sm btn-warning btnEditarTcoordinacion"
                            data-id="<?php echo (int)$t["id"]; ?>"
                            title="Editar">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-danger btnEliminarTcoordinacion"
                            data-id="<?php echo (int)$t["id"]; ?>"
                            title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-2">
                <?php if(empty($idsAsignados)): ?>
                    <span class="text-muted"><i class="fas fa-sitemap me-1"></i>Sin coordinaciones</span>
                <?php else:
                    foreach($idsAsignados as $idC):
                        $idC = (int)$idC;
                        $c   = $mapaCoordinaciones[$idC] ?? null;

                        if($c){
                            $nombreC = $c["coordinacion"];
                            $colorC  = $c["color"] ?: "#0d6efd";
                            echo '<span class="badge me-1 mb-1" style="background-color:'.htmlspecialchars($colorC).'; color:#fff; padding:6px 10px; font-size:0.8rem;">'
                               . '<i class="fas fa-sitemap me-1"></i>'.htmlspecialchars($nombreC)
                               . '</span>';
                        } else {
                            echo '<span class="badge bg-secondary me-1 mb-1">ID '.$idC.'</span>';
                        }
                    endforeach;
                endif; ?>
            </div>

        </div>
    </article>

    <?php endforeach; ?>

<?php endif; ?>

<?php if ($puedeAgregarTcoord): ?>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearTcoordinacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formCrearTcoordinacion" method="post">
                <input type="hidden" name="nuevoTcoordinacion" value="1">
                <input type="hidden" name="idTicket" value="<?php echo (int)$idTicket; ?>">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-sitemap me-2"></i> Agregar Coordinaciones al Ticket
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-sitemap me-1 text-muted"></i>
                            Coordinaciones <span class="text-danger">*</span>
                        </label>
                        <select id="nuevoTcoordIdCoordinacion"
                                name="nuevoIdCoordinacion[]"
                                multiple
                                placeholder="Seleccione coordinaciones..."></select>
                        <small class="text-muted">Solo se muestran las coordinaciones activas.</small>
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

<?php if ($puedeEditarEliminarTcoord): ?>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarTcoordinacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formEditarTcoordinacion" method="post">
                <input type="hidden" name="editarTcoordinacion" value="1">
                <input type="hidden" name="idTcoordinacion" id="idTcoordinacion">

                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-sitemap me-2"></i> Editar Asignación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-sitemap me-1 text-muted"></i>
                            Coordinaciones <span class="text-danger">*</span>
                        </label>
                        <select id="editarTcoordIdCoordinacion"
                                name="editarIdCoordinacion[]"
                                multiple
                                placeholder="Seleccione coordinaciones..."></select>
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

    function renderOpcion(data, escape){
        const color = data.color || '#0d6efd';
        return `<div><span class="tag-dot" style="background-color:${color};"></span><span class="option-label">${escape(data.text)}</span></div>`;
    }
    function renderItem(data, escape){
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

    let tsNuevoCoord  = null;
    let tsEditarCoord = null;
    let cacheCoords   = null;
    let pendingData   = null;

    function fetchCoordinaciones(){
        if(cacheCoords) return Promise.resolve(cacheCoords);
        return fetch('ajax/tcoordinacion.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarCoordinacionesActivas','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheCoords = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheCoords; })
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
            if(!ts.options[id]){
                ts.addOption({ id: id, text: 'ID '+id, color: '#6c757d' });
            }
        });
        ts.setValue(ids, true);
        ts.refreshOptions(false);
    }

    /* MODAL CREAR */
    const modalCrearEl = document.getElementById('modalCrearTcoordinacion');
    if(modalCrearEl){
        modalCrearEl.addEventListener('shown.bs.modal', async () => {
            if(!tsNuevoCoord){
                const coords = await fetchCoordinaciones();
                tsNuevoCoord = new TomSelect('#nuevoTcoordIdCoordinacion', Object.assign({}, tomSelectConfig));
                addOptions(tsNuevoCoord, coords);
            }
            tsNuevoCoord.refreshOptions(false);
        });

        modalCrearEl.addEventListener('hidden.bs.modal', () => {
            if(tsNuevoCoord) tsNuevoCoord.clear(true);
            const f = document.getElementById('formCrearTcoordinacion');
            if(f) f.reset();
        });
    }

    /* MODAL EDITAR */
    const modalEditarEl = document.getElementById('modalEditarTcoordinacion');
    if(modalEditarEl){
        modalEditarEl.addEventListener('shown.bs.modal', async () => {
            if(!tsEditarCoord){
                const coords = await fetchCoordinaciones();
                tsEditarCoord = new TomSelect('#editarTcoordIdCoordinacion', Object.assign({}, tomSelectConfig));
                addOptions(tsEditarCoord, coords);
            }
            if(pendingData){
                document.getElementById('idTcoordinacion').value = pendingData.id;
                setTSValue(tsEditarCoord, pendingData.id_coordinacion);
                pendingData = null;
            }
            tsEditarCoord.refreshOptions(false);
        });
    }

    /* SUBMIT CREAR */
    const formCrear = document.getElementById('formCrearTcoordinacion');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);

            Swal.fire({
                title: 'Guardando asignación...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/tcoordinacion.ajax.php', { method: 'POST', body: formData })
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

    /* SUBMIT EDITAR */
    const formEditar = document.getElementById('formEditarTcoordinacion');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);

            Swal.fire({
                title: 'Actualizando asignación...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/tcoordinacion.ajax.php', { method: 'POST', body: formData })
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

    /* BOTÓN EDITAR */
    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.btnEditarTcoordinacion');
        if(!btn) return;

        e.preventDefault();
        const id = btn.getAttribute('data-id');
        if(!id) return;

        const modalEl = document.getElementById('modalEditarTcoordinacion');
        if(!modalEl) return;

        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const fd = new FormData();
            fd.append('idTcoordinacion', id);

            const res  = await fetch('ajax/tcoordinacion.ajax.php', { method: 'POST', body: fd });
            const data = await res.json();

            if(data && data.id){
                pendingData = data;
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
                Toast.fire({ icon: 'error', title: (data && data.mensaje) || 'No se pudo cargar la asignación' });
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
        const btn = e.target.closest('.btnEliminarTcoordinacion');
        if(!btn) return;

        e.preventDefault();
        const id = btn.getAttribute('data-id');
        if(!id) return;

        Swal.fire({
            title: '¿Eliminar asignación?',
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
                fd.append('borrarTcoordinacion', '1');
                fd.append('idTcoordinacion', id);

                fetch('ajax/tcoordinacion.ajax.php', { method: 'POST', body: fd })
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