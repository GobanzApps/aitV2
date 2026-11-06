<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: acceso al módulo de documentos
   → Administrador
   → Coordinación: Archivo, Planificación, Administración,
     Bienes, Centro de Atención o Despacho
=============================================*/
if (!ControladorDoc::puedeVerModuloDoc()) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-shield-alt me-2"></i>
            <strong>Acceso denegado.</strong>
            Esta sección es solo para el rol <strong>Administrador</strong>
            o usuarios con coordinación <strong>Archivo</strong>, <strong>Planificación</strong>,
            <strong>Administración</strong>, <strong>Bienes</strong>,
            <strong>Centro de Atención</strong> o <strong>Despacho</strong>.
            <br><br>
            <a href="index.php?ruta=inicio" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
          </div>';
    return;
}

/* ─── Flags de permisos ─── */
$puedeModificarDoc = ControladorDoc::puedeModificarDocs();
$puedeEliminarDoc  = ControladorDoc::puedeEliminarDocs();
$puedeVerPrivados  = ControladorDoc::puedeVerDocsPrivados();

// Procesar borrado (el controlador ya revalida: solo Admin)
ControladorDoc::ctrBorrarDoc();

/*=============================================
Helpers: renderizan JSONs → badges
=============================================*/
function renderDocTiposBadges($jsonIds, $mapaTipos){
    $ids = json_decode($jsonIds, true);
    if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

    $html = '';
    foreach($ids as $id){
        if(isset($mapaTipos[(int)$id])){
            $t = $mapaTipos[(int)$id];
            $color = $t["color"] ?: "#0d6efd";
            $esPrivado = strcasecmp(trim($t["doc_tipo"]), "Privado") === 0;
            $icono = $esPrivado ? 'fa-lock' : 'fa-file-alt';
            $html .= '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.75rem; padding:5px 9px;">'
                  . '<i class="fas '.$icono.' me-1"></i>'.htmlspecialchars($t["doc_tipo"]).'</span>';
        }
    }
    return $html ?: '<span class="text-muted">—</span>';
}

function renderTicketsBadges($jsonIds){
    $ids = json_decode($jsonIds, true);
    if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

    $tickets = ControladorTicket::ctrMostrarTicket(null, null);
    $mapa = [];
    if($tickets && is_array($tickets)){
        foreach($tickets as $t){ $mapa[(int)$t["id"]] = $t; }
    }

    $html = '';
    foreach($ids as $id){
        if(isset($mapa[(int)$id])){
            $t = $mapa[(int)$id];
            $html .= '<span class="badge" style="background-color:#0d6efd; color:#fff; margin:2px; font-size:0.75rem; padding:5px 9px;">'
                  . '<i class="fas fa-ticket-alt me-1"></i>Ticket #'.$t["id"].'</span>';
        } else {
            $html .= '<span class="badge bg-secondary me-1 mb-1">ID '.$id.'</span>';
        }
    }
    return $html ?: '<span class="text-muted">—</span>';
}

function renderEntidadesDocBadges($jsonIds){
    $ids = json_decode($jsonIds, true);
    if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

    $entidades = ControladorEntidad::ctrMostrarEntidad(null, null);
    $mapa = [];
    if($entidades && is_array($entidades)){
        foreach($entidades as $e){ $mapa[(int)$e["id"]] = $e; }
    }

    $html = '';
    foreach($ids as $id){
        if(isset($mapa[(int)$id])){
            $e = $mapa[(int)$id];
            $color = $e["color"] ?: "#0d6efd";
            $html .= '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.75rem; padding:5px 9px;">'
                  . '<i class="fas fa-city me-1"></i>'.htmlspecialchars($e["entidad"]).'</span>';
        } else {
            $html .= '<span class="badge bg-secondary me-1 mb-1">ID '.$id.'</span>';
        }
    }
    return $html ?: '<span class="text-muted">—</span>';
}

/*=============================================
Mapa de tipos (para detectar "Privado")
=============================================*/
$mapaTiposParaPrivados = [];
$tiposRaw = ControladorDocTipo::ctrMostrarDocTipo(null, null) ?: [];
foreach($tiposRaw as $t){ $mapaTiposParaPrivados[(int)$t["id"]] = $t; }

/*=============================================
Obtener docs y filtrar privados (según permiso)
=============================================*/
$documentos         = ControladorDoc::ctrMostrarDoc(null, null);
$documentosVisibles = [];

if($documentos && is_array($documentos)){
    foreach($documentos as $d){
        $esPrivado = ControladorDoc::esDocumentoPrivado($d["id_doc_tipo"] ?? '', $mapaTiposParaPrivados);
        if($esPrivado && !$puedeVerPrivados) continue;
        $documentosVisibles[] = $d;
    }
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-file-contract me-2"></i> Gestión de Documentos
            <span class="badge text-bg-primary ms-2"><?php echo count($documentosVisibles); ?></span>
        </h3>

        <?php if ($puedeModificarDoc): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearDoc">
                <i class="fas fa-plus me-1"></i> Nuevo Documento
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Documento</th>
                    <th>Tipo(s)</th>
                    <th>Ticket(s)</th>
                    <th>Remitente(s)</th>
                    <th>Destinatario(s)</th>
                    <th>Asunto</th>
                    <th>Emisión</th>
                    <th>Fecha Recibido/Entregado</th>
                    <th style="width: 120px;">Adjunto</th>
                    <th>Modificado por</th>
                    <th style="width: 160px;">Última modificación</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($documentosVisibles)): ?>
                    <tr>
                        <td colspan="14" class="text-center text-muted py-4">
                            <?php if(empty($documentos)): ?>
                                <i class="fas fa-folder-open fa-2x d-block mb-2 text-secondary"></i>
                                No hay documentos registrados
                            <?php else: ?>
                                <i class="fas fa-lock fa-2x d-block mb-2 text-warning"></i>
                                Los documentos existentes son <strong>privados</strong> y no tienes permiso para verlos.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach($documentosVisibles as $key => $value):

                        $usuarioData   = ControladorUser::ctrMostrarUser("id", $value["id_user"]);
                        $nombreUsuario = $usuarioData ? $usuarioData["user"] : "—";

                        $modificacion = !empty($value["modificacion"])
                            ? date("d/m/Y H:i", strtotime($value["modificacion"]))
                            : "—";

                        $emisionFmt = !empty($value["emision"])
                            ? date("d/m/Y", strtotime($value["emision"]))
                            : "—";

                        /* ✅ NUEVO: fecha de registro (recibido/entregado) */
                        $fechaRegistroRaw = $value["fecha_registro"] ?? '';
                        $fechaRegistroISO = !empty($fechaRegistroRaw)
                            ? date("Y-m-d", strtotime($fechaRegistroRaw))
                            : '';
                        $fechaRegistroFmt = !empty($fechaRegistroRaw)
                            ? date("d/m/Y", strtotime($fechaRegistroRaw))
                            : "—";

                        $adjunto = $value["adjunto"] ?? '';
                        $ext     = $adjunto ? strtolower(pathinfo($adjunto, PATHINFO_EXTENSION)) : '';
                        $nombreDescarga = $value["doc"] . ($ext ? '.' . $ext : '');

                        $esPrivadoFila = ControladorDoc::esDocumentoPrivado(
                            $value["id_doc_tipo"] ?? '',
                            $mapaTiposParaPrivados
                        );

                        echo '<tr>
                            <td>'.($key + 1).'</td>
                            <td>
                                <span class="badge" style="background-color:#0d6efd; color:#fff; font-size:0.9rem; padding:8px 14px;">
                                    <i class="fas fa-file-contract me-1"></i> '.htmlspecialchars($value["doc"]).'
                                </span>';
                                if($esPrivadoFila){
                                    echo '<span class="badge bg-dark ms-1" title="Documento privado">
                                            <i class="fas fa-lock"></i>
                                          </span>';
                                }
                        echo '</td>
                            <td>'.renderDocTiposBadges($value["id_doc_tipo"] ?? '', $mapaTiposParaPrivados).'</td>
                            <td>'.renderTicketsBadges($value["id_ticket"] ?? '').'</td>
                            <td>'.renderEntidadesDocBadges($value["remitente"] ?? '').'</td>
                            <td>'.renderEntidadesDocBadges($value["destinatario"] ?? '').'</td>
                            <td><small>'.htmlspecialchars($value["asunto"]).'</small></td>
                            <td><small class="text-muted">'.$emisionFmt.'</small></td>
                            <td data-order="'.htmlspecialchars($fechaRegistroISO).'">
                                <small class="text-muted">
                                    <i class="fas fa-calendar-check me-1"></i>'.$fechaRegistroFmt.'
                                </small>
                            </td>
                            <td>';

                        if(!empty($adjunto)){
                            if($ext === 'pdf'){
                                echo '<a href="'.htmlspecialchars($adjunto).'"
                                         target="_blank"
                                         rel="noopener noreferrer"
                                         class="btn btn-sm btn-danger"
                                         title="Ver PDF en nueva pestaña">
                                        <i class="fas fa-file-pdf"></i> Ver PDF
                                      </a>';
                            } else {
                                echo '<a href="'.htmlspecialchars($adjunto).'"
                                         download="'.htmlspecialchars($nombreDescarga).'"
                                         target="_blank"
                                         class="btn btn-sm btn-info"
                                         title="Descargar '.htmlspecialchars($nombreDescarga).'">
                                        <i class="fas fa-download"></i> '.strtoupper($ext).'
                                      </a>';
                            }
                        } else {
                            echo '<span class="text-muted">—</span>';
                        }

                        echo '</td>
                            <td>
                                <i class="fas fa-user-circle me-1 text-muted"></i>
                                '.htmlspecialchars($nombreUsuario).'
                            </td>
                            <td><small class="text-muted">'.$modificacion.'</small></td>
                            <td>';

                        if ($puedeModificarDoc) {
                            if($value["estado"] == 1){
                                echo '<button class="btn btn-success btn-sm btnActivarDoc" idDoc="'.$value["id"].'" estadoDoc="0">
                                        <i class="fas fa-check-circle"></i> Activo
                                      </button>';
                            } else {
                                echo '<button class="btn btn-danger btn-sm btnActivarDoc" idDoc="'.$value["id"].'" estadoDoc="1">
                                        <i class="fas fa-times-circle"></i> Inactivo
                                      </button>';
                            }
                        } else {
                            if($value["estado"] == 1){
                                echo '<span class="badge text-bg-success"><i class="fas fa-check-circle me-1"></i>Activo</span>';
                            } else {
                                echo '<span class="badge text-bg-danger"><i class="fas fa-times-circle me-1"></i>Inactivo</span>';
                            }
                        }

                        echo '</td>
                            <td>
                                <div class="btn-group" role="group">';

                                if ($puedeModificarDoc) {
                                    echo '<button class="btn btn-warning btn-sm btnEditarDoc" idDoc="'.$value["id"].'" data-bs-toggle="modal" data-bs-target="#modalEditarDoc" title="Editar">
                                            <i class="fas fa-pencil-alt"></i>
                                          </button>';
                                }

                                if ($puedeEliminarDoc) {
                                    echo '<button class="btn btn-danger btn-sm btnEliminarDoc" idDoc="'.$value["id"].'" nombre="'.htmlspecialchars($value["doc"]).'" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                          </button>';
                                }

                        echo '</div>
                            </td>
                        </tr>';
                    endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($puedeModificarDoc): ?>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearDoc" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formCrearDoc" method="post" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nuevo Documento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-8">
                            <label class="form-label">Nombre del Documento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nuevoDoc" placeholder="Ej: ANZ-SV-PS-036" required>
                            <small class="text-muted">Identificador del documento (se usa también para nombrar la descarga).</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Fecha de Emisión <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="nuevoEmision" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <!-- ✅ NUEVO: Fecha Recibido/Entregado -->
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="fas fa-calendar-check me-1 text-muted"></i>
                                Fecha Recibido/Entregado <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" name="nuevoFechaRegistro" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-file-alt me-1 text-muted"></i> Tipo(s) de Documento</label>
                            <select id="nuevoIdDocTipo" name="nuevoIdDocTipo[]" multiple placeholder="Seleccione tipos..."></select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-ticket-alt me-1 text-muted"></i> Ticket(s)</label>
                            <select id="nuevoIdTicket" name="nuevoIdTicket[]" multiple placeholder="Seleccione tickets..."></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-paper-plane me-1 text-muted"></i> Remitente(s)</label>
                            <select id="nuevoRemitente" name="nuevoRemitente[]" multiple placeholder="Seleccione remitentes..."></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-inbox me-1 text-muted"></i> Destinatario(s)</label>
                            <select id="nuevoDestinatario" name="nuevoDestinatario[]" multiple placeholder="Seleccione destinatarios..."></select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Asunto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nuevoAsunto" maxlength="255" placeholder="Breve descripción del documento" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Adjunto <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="nuevoAdjunto" id="nuevoAdjunto"
                                   accept=".doc,.docx,.pdf,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.txt,.csv,.rtf" required>
                            <small class="text-muted">
                                Solo documentos (DOC, DOCX, PDF, XLS, XLSX, PPT, PPTX, ODT, ODS, ODP, TXT, CSV, RTF). Máx. 15 MB.
                            </small>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarDoc" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formEditarDoc" method="post" enctype="multipart/form-data">
                <input type="hidden" name="idDoc" id="idDoc">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-8">
                            <label class="form-label">Nombre del Documento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="editarDoc" id="editarDoc" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Fecha de Emisión <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="editarEmision" id="editarEmision" required>
                        </div>

                        <!-- ✅ NUEVO: Fecha Recibido/Entregado -->
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="fas fa-calendar-check me-1 text-muted"></i>
                                Fecha Recibido/Entregado <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" name="editarFechaRegistro" id="editarFechaRegistro" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-file-alt me-1 text-muted"></i> Tipo(s) de Documento</label>
                            <select id="editarIdDocTipo" name="editarIdDocTipo[]" multiple placeholder="Seleccione tipos..."></select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-ticket-alt me-1 text-muted"></i> Ticket(s)</label>
                            <select id="editarIdTicket" name="editarIdTicket[]" multiple placeholder="Seleccione tickets..."></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-paper-plane me-1 text-muted"></i> Remitente(s)</label>
                            <select id="editarRemitente" name="editarRemitente[]" multiple placeholder="Seleccione remitentes..."></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-inbox me-1 text-muted"></i> Destinatario(s)</label>
                            <select id="editarDestinatario" name="editarDestinatario[]" multiple placeholder="Seleccione destinatarios..."></select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Asunto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="editarAsunto" id="editarAsunto" maxlength="255" required>
                        </div>

                        <div class="col-md-12">
                            <div id="currentFileInfo" class="mb-2"></div>
                            <label class="form-label">Reemplazar Adjunto <span class="text-muted small">(opcional)</span></label>
                            <input type="file" class="form-control" name="editarAdjunto" id="editarAdjunto"
                                   accept=".doc,.docx,.pdf,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.txt,.csv,.rtf">
                            <small class="text-muted">
                                Si dejas este campo vacío, se conserva el archivo actual. Máx. 15 MB.
                            </small>
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

    const MAX_BYTES  = 15 * 1024 * 1024;
    const EXT_OK     = ['doc','docx','pdf','xls','xlsx','ppt','pptx','odt','ods','odp','txt','csv','rtf'];

    function validarArchivoCliente(file){
        if(!file) return { ok: false, msg: 'Debe adjuntar un archivo' };
        if(file.size > MAX_BYTES) return { ok: false, msg: 'El archivo supera los 15 MB' };
        const ext = file.name.split('.').pop().toLowerCase();
        if(!EXT_OK.includes(ext)) return { ok: false, msg: 'Extensión "' + ext + '" no permitida' };
        return { ok: true };
    }

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

    let tsNuevoDocTipo, tsNuevoTicket, tsNuevoRemitente, tsNuevoDestinatario;
    let tsEditarDocTipo, tsEditarTicket, tsEditarRemitente, tsEditarDestinatario;
    let tsNuevoInitialized  = false;
    let tsEditarInitialized = false;

    let cacheDocTipos  = null;
    let cacheTickets   = null;
    let cacheEntidades = null;

    function fetchDocTipos(){
        if(cacheDocTipos) return Promise.resolve(cacheDocTipos);
        return fetch('ajax/doc.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarDocTiposActivos','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheDocTipos = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheDocTipos; })
        .catch(() => []);
    }

    function fetchTickets(){
        if(cacheTickets) return Promise.resolve(cacheTickets);
        return fetch('ajax/doc.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarTicketsActivos','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheTickets = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheTickets; })
        .catch(() => []);
    }

    function fetchEntidades(){
        if(cacheEntidades) return Promise.resolve(cacheEntidades);
        return fetch('ajax/doc.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarEntidadesActivas','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheEntidades = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheEntidades; })
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

    async function initCrearTS(){
        if(tsNuevoInitialized) return;
        const [tipos, tickets, ents] = await Promise.all([fetchDocTipos(), fetchTickets(), fetchEntidades()]);

        tsNuevoDocTipo      = new TomSelect('#nuevoIdDocTipo',      Object.assign({}, tomSelectConfig));
        tsNuevoTicket       = new TomSelect('#nuevoIdTicket',       Object.assign({}, tomSelectConfig));
        tsNuevoRemitente    = new TomSelect('#nuevoRemitente',      Object.assign({}, tomSelectConfig));
        tsNuevoDestinatario = new TomSelect('#nuevoDestinatario',   Object.assign({}, tomSelectConfig));

        addOptions(tsNuevoDocTipo,      tipos);
        addOptions(tsNuevoTicket,       tickets);
        addOptions(tsNuevoRemitente,    ents);
        addOptions(tsNuevoDestinatario, ents);

        tsNuevoInitialized = true;
    }

    async function initEditarTS(){
        if(tsEditarInitialized) return;
        const [tipos, tickets, ents] = await Promise.all([fetchDocTipos(), fetchTickets(), fetchEntidades()]);

        tsEditarDocTipo      = new TomSelect('#editarIdDocTipo',      Object.assign({}, tomSelectConfig));
        tsEditarTicket       = new TomSelect('#editarIdTicket',       Object.assign({}, tomSelectConfig));
        tsEditarRemitente    = new TomSelect('#editarRemitente',      Object.assign({}, tomSelectConfig));
        tsEditarDestinatario = new TomSelect('#editarDestinatario',   Object.assign({}, tomSelectConfig));

        addOptions(tsEditarDocTipo,      tipos);
        addOptions(tsEditarTicket,       tickets);
        addOptions(tsEditarRemitente,    ents);
        addOptions(tsEditarDestinatario, ents);

        tsEditarInitialized = true;
    }

    const modalCrearEl = document.getElementById('modalCrearDoc');
    if(modalCrearEl){
        modalCrearEl.addEventListener('shown.bs.modal', () => {
            initCrearTS().then(() => {
                if(tsNuevoDocTipo)      tsNuevoDocTipo.refreshOptions(false);
                if(tsNuevoTicket)       tsNuevoTicket.refreshOptions(false);
                if(tsNuevoRemitente)    tsNuevoRemitente.refreshOptions(false);
                if(tsNuevoDestinatario) tsNuevoDestinatario.refreshOptions(false);
            });
        });
        modalCrearEl.addEventListener('hidden.bs.modal', () => {
            if(tsNuevoDocTipo)      tsNuevoDocTipo.clear(true);
            if(tsNuevoTicket)       tsNuevoTicket.clear(true);
            if(tsNuevoRemitente)    tsNuevoRemitente.clear(true);
            if(tsNuevoDestinatario) tsNuevoDestinatario.clear(true);
            const f = document.getElementById('formCrearDoc');
            if(f) f.reset();
        });
    }

    let pendingDocData = null;

    const modalEditarEl = document.getElementById('modalEditarDoc');
    if(modalEditarEl){
        modalEditarEl.addEventListener('shown.bs.modal', async () => {
            await initEditarTS();

            if(pendingDocData){
                const d = pendingDocData;

                document.getElementById('idDoc').value        = d.id;
                document.getElementById('editarDoc').value    = d.doc || '';
                document.getElementById('editarAsunto').value = d.asunto || '';
                document.getElementById('editarEmision').value= d.emision ? d.emision.substring(0,10) : '';

                /* ✅ NUEVO: poblar Fecha Recibido/Entregado */
                document.getElementById('editarFechaRegistro').value = d.fecha_registro
                    ? d.fecha_registro.substring(0,10)
                    : '';

                setTSValue(tsEditarDocTipo,      d.id_doc_tipo);
                setTSValue(tsEditarTicket,       d.id_ticket);
                setTSValue(tsEditarRemitente,    d.remitente);
                setTSValue(tsEditarDestinatario, d.destinatario);

                const infoBox = document.getElementById('currentFileInfo');
                if(d.adjunto){
                    const ext = d.adjunto.split('.').pop().toLowerCase();
                    const nombreDescarga = d.doc + '.' + ext;
                    infoBox.innerHTML = `
                        <div class="alert alert-info py-2 mb-0">
                            <i class="fas fa-paperclip me-1"></i>
                            <strong>Archivo actual:</strong>
                            <a href="${d.adjunto}" download="${nombreDescarga}" target="_blank" class="ms-2">
                                <i class="fas fa-download"></i> ${nombreDescarga}
                            </a>
                        </div>`;
                } else {
                    infoBox.innerHTML = '';
                }

                pendingDocData = null;
            }

            if(tsEditarDocTipo)      tsEditarDocTipo.refreshOptions(false);
            if(tsEditarTicket)       tsEditarTicket.refreshOptions(false);
            if(tsEditarRemitente)    tsEditarRemitente.refreshOptions(false);
            if(tsEditarDestinatario) tsEditarDestinatario.refreshOptions(false);
        });
    }

    const formCrear = document.getElementById('formCrearDoc');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();

            const fileInput = document.getElementById('nuevoAdjunto');
            const check = validarArchivoCliente(fileInput.files[0]);
            if(!check.ok){
                Toast.fire({ icon: 'error', title: check.msg });
                return;
            }

            const formData = new FormData(this);

            Swal.fire({
                title: 'Guardando documento...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/doc.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Documento guardado' });
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

    const formEditar = document.getElementById('formEditarDoc');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();

            const fileInput = document.getElementById('editarAdjunto');
            if(fileInput.files.length > 0){
                const check = validarArchivoCliente(fileInput.files[0]);
                if(!check.ok){
                    Toast.fire({ icon: 'error', title: check.msg });
                    return;
                }
            }

            const formData = new FormData(this);

            Swal.fire({
                title: 'Actualizando documento...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/doc.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Documento actualizado' });
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

    document.querySelectorAll('.btnActivarDoc').forEach(btn => {
        btn.addEventListener('click', function() {
            const idDoc     = this.getAttribute('idDoc');
            const estadoDoc = this.getAttribute('estadoDoc');
            const btnElement = this;

            const datos = new FormData();
            datos.append('activarDoc', estadoDoc);
            datos.append('activarId',  idDoc);

            fetch('ajax/doc.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'ok'){
                        if(estadoDoc === '1'){
                            btnElement.classList.remove('btn-danger');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                            btnElement.setAttribute('estadoDoc', '0');
                        }else{
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-danger');
                            btnElement.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                            btnElement.setAttribute('estadoDoc', '1');
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

    document.querySelectorAll('.btnEditarDoc').forEach(btn => {
        btn.addEventListener('click', function() {
            const idDoc = this.getAttribute('idDoc');

            const datos = new FormData();
            datos.append('idDoc', idDoc);

            fetch('ajax/doc.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta && respuesta.id){
                        pendingDocData = respuesta;
                    }else{
                        Toast.fire({ icon: 'error', title: (respuesta && respuesta.mensaje) || 'No se pudo cargar el documento' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Toast.fire({ icon: 'error', title: 'Error de conexión' });
                });
        });
    });

    document.querySelectorAll('.btnEliminarDoc').forEach(btn => {
        btn.addEventListener('click', function() {
            const idDoc  = this.getAttribute('idDoc');
            const nombre = this.getAttribute('nombre');

            Swal.fire({
                title: '¿Eliminar documento?',
                html: `Estás a punto de eliminar <strong>${nombre}</strong>.<br>
                       Se borrará también el archivo adjunto.<br>
                       Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if(result.isConfirmed){
                    window.location = 'index.php?ruta=doc&idDoc=' + idDoc;
                }
            });
        });
    });

});
</script>