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
$ticketFinalizadoTE = isset($ticket) && ((int)($ticket["check"] ?? 0) === 1);

$authTE = (isset($authTicket) && $authTicket) ? $authTicket : ControladorTicket::authTicket();

/* Regla 4: Administrador o (Coordinador/Especialista asignado al ticket) */
$permisoBaseTE = false;
if ($authTE) {
    if (ControladorTicket::esAdminTicket($authTE)) {
        $permisoBaseTE = true;
    } elseif (ControladorTicket::esCoordinadorTicket($authTE)
           || ControladorTicket::esEspecialistaTicket($authTE)) {
        $permisoBaseTE = ControladorTicket::estaAsignadoAlTicket($idTicket, $authTE);
    }
}

/* Puede modificar = tiene permiso Y ticket NO finalizado (regla 1) */
$puedeModificarTE = $permisoBaseTE && !$ticketFinalizadoTE;

/*=============================================
Datos
=============================================*/
$evidencias       = ControladorTevidencia::ctrMostrarTevidenciaPorTicket($idTicket);
$evidencias       = $evidencias ?: [];
$totalEvidencias  = count($evidencias);
$limiteEvidencias = 2;
$limiteAlcanzado  = $totalEvidencias >= $limiteEvidencias;

/* Puede subir = permiso + no finalizado + no límite alcanzado */
$puedeSubirTE = $puedeModificarTE && !$limiteAlcanzado;

/* Usuarios + Roles */
$todosUsuarios = ControladorUser::ctrMostrarUser(null, null);
if(!$todosUsuarios || !is_array($todosUsuarios)) $todosUsuarios = [];
$mapaUsuarios = [];
foreach($todosUsuarios as $u){ $mapaUsuarios[(int)$u["id"]] = $u; }

$todosRoles = ControladorRol::ctrMostrarRol(null, null);
if(!$todosRoles || !is_array($todosRoles)) $todosRoles = [];
$mapaRoles = [];
foreach($todosRoles as $r){ $mapaRoles[(int)$r["id"]] = $r; }

/*=============================================
Helpers
=============================================*/
if(!function_exists('tevidColorUsuario')){
    function tevidColorUsuario($usuario, $mapaRoles){
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

if(!function_exists('tevidIniciales')){
    function tevidIniciales($nombre){
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

if(!function_exists('tevidNombreUsuario')){
    function tevidNombreUsuario($usuario){
        if(!$usuario) return null;
        return !empty($usuario["name"]) ? $usuario["name"] : $usuario["user"];
    }
}
?>

<style>
    .tevid-card {
        border: 1px solid #e3e8f0;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        transition: box-shadow .2s ease, transform .15s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .tevid-card:hover {
        box-shadow: 0 8px 22px rgba(13,110,253,0.12);
        transform: translateY(-2px);
    }
    [data-bs-theme="dark"] .tevid-card { background: #1f2740; border-color: #2c3550; }

    .tevid-thumb {
        position: relative; width: 100%; aspect-ratio: 4 / 3;
        overflow: hidden; background: #f1f3f6; cursor: zoom-in;
    }
    [data-bs-theme="dark"] .tevid-thumb { background: #16203a; }
    .tevid-thumb img {
        width: 100%; height: 100%; object-fit: cover; display: block;
        transition: transform .35s ease;
    }
    .tevid-card:hover .tevid-thumb img { transform: scale(1.05); }

    .tevid-thumb__overlay {
        position: absolute; inset: 0; background: rgba(0,0,0,0.45); color: #fff;
        opacity: 0; display: flex; align-items: center; justify-content: center;
        gap: 8px; font-weight: 600; transition: opacity .2s ease;
    }
    .tevid-thumb:hover .tevid-thumb__overlay { opacity: 1; }

    .tevid-card__footer {
        padding: 10px 12px; display: flex; align-items: center; gap: 10px;
        flex: 1; justify-content: space-between;
    }
    .tevid-meta { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .tevid-meta__avatar {
        width: 32px; height: 32px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 600; font-size: 0.75rem; flex-shrink: 0;
    }
    .tevid-meta__info { min-width: 0; line-height: 1.15; }
    .tevid-meta__name {
        font-size: 0.82rem; font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tevid-meta__time { font-size: 0.72rem; color: #8a94a6; }

    .tevid-dropzone {
        border: 2px dashed #b9c4d4; border-radius: 10px;
        padding: 24px 16px; text-align: center; background: #fafbfd;
        cursor: pointer; transition: all .2s ease;
    }
    .tevid-dropzone:hover, .tevid-dropzone.dragover { border-color: #0d6efd; background: #eff5ff; }
    [data-bs-theme="dark"] .tevid-dropzone { background: #1a2238; border-color: #38456a; }
    [data-bs-theme="dark"] .tevid-dropzone:hover,
    [data-bs-theme="dark"] .tevid-dropzone.dragover { background: #1f2b4d; border-color: #6ea8fe; }
    .tevid-dropzone__icon { font-size: 2rem; color: #0d6efd; margin-bottom: 6px; }

    .tevid-preview-wrap { position: relative; display: inline-block; margin-top: 12px; }
    .tevid-preview {
        max-width: 100%; max-height: 240px; border-radius: 10px;
        box-shadow: 0 3px 12px rgba(0,0,0,0.15);
    }

    #modalVerEvidencia .modal-body {
        background: #0c0f16; display: flex; justify-content: center;
        align-items: center; padding: 0; min-height: 65vh;
    }
    #modalVerEvidencia img { max-width: 100%; max-height: 85vh; object-fit: contain; }
</style>

<!-- ═══════════════ CABECERA ═══════════════ -->
<div class="d-flex justify-content-between align-items-center mb-3 mt-4 flex-wrap gap-2">
    <h5 class="mb-0">
        <i class="fas fa-images me-2 text-primary"></i> Evidencias Fotográficas
        <span class="badge text-bg-primary ms-2"><?php echo $totalEvidencias; ?> / <?php echo $limiteEvidencias; ?></span>
    </h5>

    <?php if ($ticketFinalizadoTE): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="Ticket finalizado: no se pueden agregar evidencias">
            <i class="fas fa-lock me-1"></i> Ticket finalizado
        </button>
    <?php elseif (!$permisoBaseTE): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="Solo Administrador o Coordinador/Especialista asignado">
            <i class="fas fa-lock me-1"></i> Solo lectura
        </button>
    <?php elseif ($limiteAlcanzado): ?>
        <button type="button" class="btn btn-secondary btn-sm" disabled
                title="Límite alcanzado: elimina una evidencia para subir otra">
            <i class="fas fa-ban me-1"></i> Límite alcanzado
        </button>
    <?php else: ?>
        <button type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalCrearTevidencia">
            <i class="fas fa-cloud-upload-alt me-1"></i> Subir Evidencia
        </button>
    <?php endif; ?>
</div>

<?php if ($ticketFinalizadoTE): ?>
    <div class="alert alert-success d-flex align-items-start mb-3" role="alert">
        <i class="fas fa-lock me-2 mt-1"></i>
        <div>
            <strong>Ticket finalizado.</strong>
            Las evidencias fotográficas son de solo lectura: no se pueden agregar ni eliminar.
        </div>
    </div>
<?php elseif (!$permisoBaseTE): ?>
    <div class="alert alert-warning d-flex align-items-start mb-3" role="alert">
        <i class="fas fa-user-shield me-2 mt-1"></i>
        <div>
            <strong>No tienes permisos para modificar las evidencias de este ticket.</strong><br>
            Solo el <strong>Administrador</strong> o los <strong>usuarios asignados al ticket</strong>
            con rol <strong>Coordinador</strong> o <strong>Especialista/Técnico</strong> pueden hacerlo.
        </div>
    </div>
<?php elseif ($limiteAlcanzado): ?>
    <div class="alert alert-warning d-flex align-items-start mb-3" role="alert">
        <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
        <div>
            <strong>Has alcanzado el límite de <?php echo $limiteEvidencias; ?> fotos por ticket.</strong><br>
            Si deseas subir otra imagen, primero debes <strong>eliminar una</strong> de las existentes.
        </div>
    </div>
<?php endif; ?>

<?php if(empty($evidencias)): ?>

    <div class="alert alert-info d-flex align-items-center mb-0">
        <i class="fas fa-info-circle me-2"></i>
        <div>Aún no hay evidencias fotográficas registradas en este ticket.</div>
    </div>

<?php else: ?>

    <div class="row g-3">
        <?php foreach($evidencias as $ev):
            $creador       = $mapaUsuarios[(int)$ev["tevidencia"]] ?? null;
            $nombreCreador = tevidNombreUsuario($creador);
            if($nombreCreador === null){
                $nombreCreador = "Usuario #" . $ev["tevidencia"];
            }
            $colorCreador = $creador ? tevidColorUsuario($creador, $mapaRoles) : "#6c757d";
            $iniCreador   = tevidIniciales($nombreCreador);

            $fechaMostrar = !empty($ev["fecha"])
                ? date("d/m/Y H:i", strtotime($ev["fecha"]))
                : "—";

            $rutaFoto = htmlspecialchars($ev["foto"]);
        ?>
        <div class="col-md-6">
            <div class="tevid-card">

                <div class="tevid-thumb"
                     data-bs-toggle="modal"
                     data-bs-target="#modalVerEvidencia"
                     data-img-src="<?php echo $rutaFoto; ?>"
                     data-img-title="Evidencia #<?php echo (int)$ev["id"]; ?> — <?php echo htmlspecialchars($nombreCreador); ?>">
                    <img src="<?php echo $rutaFoto; ?>"
                         alt="Evidencia #<?php echo (int)$ev["id"]; ?>"
                         loading="lazy"
                         onerror="this.src='https://ui-avatars.com/api/?name=Error&background=dc3545&color=fff&size=400';">
                    <div class="tevid-thumb__overlay">
                        <i class="fas fa-search-plus"></i>
                        <span>Ver imagen completa</span>
                    </div>
                </div>

                <div class="tevid-card__footer">
                    <div class="tevid-meta">
                        <div class="tevid-meta__avatar"
                             style="background-color: <?php echo htmlspecialchars($colorCreador); ?>;"
                             aria-hidden="true">
                            <?php echo htmlspecialchars($iniCreador); ?>
                        </div>
                        <div class="tevid-meta__info">
                            <div class="tevid-meta__name">
                                <i class="fas fa-user-circle me-1 text-muted"></i>
                                <?php echo htmlspecialchars($nombreCreador); ?>
                            </div>
                            <div class="tevid-meta__time">
                                <i class="far fa-clock me-1"></i><?php echo $fechaMostrar; ?>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <!-- Ver siempre disponible -->
                        <button type="button"
                                class="btn btn-sm btn-info btnVerTevidencia"
                                data-id="<?php echo (int)$ev["id"]; ?>"
                                data-src="<?php echo $rutaFoto; ?>"
                                title="Ver imagen completa">
                            <i class="fas fa-expand"></i>
                        </button>

                        <?php if ($puedeModificarTE): ?>
                        <button type="button"
                                class="btn btn-sm btn-danger btnEliminarTevidencia"
                                data-id="<?php echo (int)$ev["id"]; ?>"
                                title="Eliminar evidencia">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php if ($puedeSubirTE): ?>

<!-- ═══════════════ MODAL CREAR (SUBIR) ═══════════════ -->
<div class="modal fade" id="modalCrearTevidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formCrearTevidencia" method="post" enctype="multipart/form-data">
                <input type="hidden" name="nuevoTevidencia" value="1">
                <input type="hidden" name="idTicket" value="<?php echo (int)$idTicket; ?>">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-cloud-upload-alt me-2"></i> Subir Evidencia Fotográfica
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-image me-1 text-muted"></i>
                            Imagen <span class="text-danger">*</span>
                        </label>

                        <div class="tevid-dropzone" id="tevidDropzone">
                            <input type="file"
                                   id="tevidInputFile"
                                   name="nuevaFoto"
                                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                   hidden>
                            <div class="tevid-dropzone__icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <div class="fw-semibold">Haz clic aquí o arrastra una imagen</div>
                            <small class="text-muted d-block mt-1">
                                JPG, PNG, GIF o WEBP · Máx. <strong>5 MB</strong>
                            </small>
                        </div>

                        <div id="tevidPreviewWrap" class="tevid-preview-wrap d-none w-100 text-center">
                            <img id="tevidPreview" class="tevid-preview" src="" alt="Previsualización">
                            <button type="button"
                                    id="tevidQuitarPreview"
                                    class="btn btn-sm btn-danger position-absolute"
                                    style="top:8px; right:8px;"
                                    title="Quitar imagen">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            La imagen se guardará con un nombre aleatorio y único.
                        </small>
                    </div>

                    <div class="alert alert-light border small mb-0">
                        <i class="fas fa-clock me-1 text-primary"></i>
                        Límite actual: <strong><?php echo $totalEvidencias; ?> / <?php echo $limiteEvidencias; ?></strong>
                        evidencias en este ticket.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="tevidBtnGuardar">
                        <i class="fas fa-save me-1"></i> Guardar Evidencia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ═══════════════ MODAL VER (siempre disponible) ═══════════════ -->
<div class="modal fade" id="modalVerEvidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fas fa-image me-2"></i>
                    <span id="tevidVerTitulo">Evidencia</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <img id="tevidVerImagen" src="" alt="Evidencia">
            </div>
            <div class="modal-footer border-secondary">
                <a id="tevidVerDescargar" href="" download class="btn btn-outline-light btn-sm">
                    <i class="fas fa-download me-1"></i> Descargar
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ SCRIPTS (siempre corren; verifican existencia) ═══════════════ -->
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

    const MAX_MB  = 5;
    const MAX_BYTES = MAX_MB * 1024 * 1024;
    const MIMES_PERMITIDOS = ['image/jpeg','image/jpg','image/pjpeg','image/png','image/gif','image/webp'];
    const EXT_PERMITIDAS   = ['jpg','jpeg','png','gif','webp'];

    /* ─── Subir evidencia (solo si el modal existe) ─── */
    const dropzone    = document.getElementById('tevidDropzone');
    const inputFile   = document.getElementById('tevidInputFile');
    const previewWrap = document.getElementById('tevidPreviewWrap');
    const previewImg  = document.getElementById('tevidPreview');
    const btnQuitar   = document.getElementById('tevidQuitarPreview');
    const formCrear   = document.getElementById('formCrearTevidencia');

    function resetPreview(){
        if(inputFile) inputFile.value = '';
        if(previewImg) previewImg.src = '';
        if(previewWrap) previewWrap.classList.add('d-none');
        if(dropzone) dropzone.classList.remove('d-none');
    }
    function validarArchivo(file){
        if(!file) return {ok:false, msg:'No se seleccionó archivo'};
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if(!EXT_PERMITIDAS.includes(ext)) return {ok:false, msg:'Solo se permiten imágenes (jpg, png, gif, webp)'};
        if(!MIMES_PERMITIDOS.includes(file.type)) return {ok:false, msg:'El archivo no es una imagen válida'};
        if(file.size > MAX_BYTES) return {ok:false, msg:'La imagen supera los ' + MAX_MB + ' MB permitidos'};
        return {ok:true};
    }
    function mostrarPreview(file){
        const reader = new FileReader();
        reader.onload = function(e){
            previewImg.src = e.target.result;
            previewWrap.classList.remove('d-none');
            dropzone.classList.add('d-none');
        };
        reader.readAsDataURL(file);
    }

    if(dropzone && inputFile){
        dropzone.addEventListener('click', () => inputFile.click());
        ['dragenter','dragover'].forEach(ev =>
            dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.add('dragover'); })
        );
        ['dragleave','drop'].forEach(ev =>
            dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.remove('dragover'); })
        );
        dropzone.addEventListener('drop', e => {
            const f = e.dataTransfer.files[0];
            if(!f) return;
            const val = validarArchivo(f);
            if(!val.ok){ Toast.fire({icon:'error', title: val.msg}); return; }
            const dt = new DataTransfer();
            dt.items.add(f);
            inputFile.files = dt.files;
            mostrarPreview(f);
        });
    }
    if(inputFile){
        inputFile.addEventListener('change', function(){
            const f = this.files[0];
            if(!f) return;
            const val = validarArchivo(f);
            if(!val.ok){ Toast.fire({icon:'error', title: val.msg}); resetPreview(); return; }
            mostrarPreview(f);
        });
    }
    if(btnQuitar){
        btnQuitar.addEventListener('click', function(e){ e.stopPropagation(); resetPreview(); });
    }

    const modalCrearEl = document.getElementById('modalCrearTevidencia');
    if(modalCrearEl){
        modalCrearEl.addEventListener('hidden.bs.modal', function(){
            if(formCrear) formCrear.reset();
            resetPreview();
        });
    }

    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();
            const f = inputFile ? inputFile.files[0] : null;
            if(!f){ Toast.fire({ icon:'warning', title:'Selecciona una imagen primero' }); return; }
            const val = validarArchivo(f);
            if(!val.ok){ Toast.fire({ icon:'error', title: val.msg }); return; }

            const formData = new FormData(this);
            const btnGuardar = document.getElementById('tevidBtnGuardar');
            const originalHTML = btnGuardar ? btnGuardar.innerHTML : '';
            if(btnGuardar){
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Subiendo...';
            }

            Swal.fire({ title: 'Subiendo evidencia...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('ajax/tevidencia.ajax.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(resp => {
                    Swal.close();
                    if(resp.status === 'success'){
                        Toast.fire({ icon:'success', title: resp.mensaje || 'Evidencia guardada' });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Toast.fire({ icon:'error', title: resp.mensaje || 'Error al guardar' });
                    }
                })
                .catch(err => {
                    console.error(err); Swal.close();
                    Toast.fire({ icon:'error', title:'Error de conexión con el servidor' });
                })
                .finally(() => {
                    if(btnGuardar){ btnGuardar.disabled = false; btnGuardar.innerHTML = originalHTML; }
                });
        });
    }

    /* ─── Lightbox (siempre disponible) ─── */
    const modalVerEl   = document.getElementById('modalVerEvidencia');
    const verImagen    = document.getElementById('tevidVerImagen');
    const verTitulo    = document.getElementById('tevidVerTitulo');
    const verDescargar = document.getElementById('tevidVerDescargar');

    if(modalVerEl){
        modalVerEl.addEventListener('show.bs.modal', function(event){
            const trigger = event.relatedTarget;
            if(!trigger) return;
            const src   = trigger.getAttribute('data-img-src') || trigger.getAttribute('data-src');
            const title = trigger.getAttribute('data-img-title') || 'Evidencia';
            if(src){
                verImagen.src = src;
                verTitulo.textContent = title;
                if(verDescargar) verDescargar.href = src;
            }
        });
        modalVerEl.addEventListener('hidden.bs.modal', function(){
            verImagen.src = '';
        });
    }

    document.addEventListener('click', function(e){
        const btn = e.target.closest('.btnVerTevidencia');
        if(!btn) return;
        e.preventDefault();
        const src = btn.getAttribute('data-src');
        if(!src) return;
        verImagen.src = src;
        verTitulo.textContent = 'Evidencia #' + btn.getAttribute('data-id');
        if(verDescargar) verDescargar.href = src;
        bootstrap.Modal.getOrCreateInstance(modalVerEl).show();
    });

    /* ─── Eliminar (solo si el botón existe) ─── */
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.btnEliminarTevidencia');
        if(!btn) return;
        e.preventDefault();
        const id = btn.getAttribute('data-id');
        if(!id) return;

        Swal.fire({
            title: '¿Eliminar evidencia?',
            html: `Se eliminará <strong>tanto la imagen del servidor como el registro</strong> de la base de datos.<br>Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        }).then((result) => {
            if(!result.isConfirmed) return;
            const fd = new FormData();
            fd.append('borrarTevidencia', '1');
            fd.append('idTevidencia', id);
            fetch('ajax/tevidencia.ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(resp => {
                    if(resp.status === 'success'){
                        Toast.fire({ icon:'success', title: resp.mensaje || 'Eliminado' });
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Toast.fire({ icon:'error', title: resp.mensaje || 'Error al eliminar' });
                    }
                })
                .catch(err => { console.error(err); Toast.fire({ icon:'error', title:'Error de conexión' }); });
        });
    });

});
</script>