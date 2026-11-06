<?php
/*=============================================
tickets.php — Gestión de Tickets
  - Solo roles Administrador, Coordinador, Especialista/Técnico
  - Admin + Centro/Despacho: ven TODOS (inclusive inactivos)
  - Coordinador: ve tickets donde su coordinación está asignada
  - Especialista: ve tickets donde él está asignado (tuser)
  - Tickets inactivos: omitidos para no-admin/centro/despacho
  - ✅ El ticket más reciente aparece PRIMERO (data-order [[0, 'desc']])
=============================================*/

if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/* ── 1) Guard de roles ── */
if (!ControladorTicket::puedeVerSeccionTickets()) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No tienes permisos para acceder a la gestión de tickets.
          </div>';
    return;
}

$authTicket     = ControladorTicket::authTicket();
$puedeModificar = ControladorTicket::puedeModificarTickets($authTicket);
$puedeEliminar  = ControladorTicket::puedeEliminarTickets($authTicket);
$puedeVerTodos  = ControladorTicket::puedeVerTodosLosTickets($authTicket);
$esAdmin        = ControladorTicket::esAdminTicket($authTicket);
$esCoordinador  = ControladorTicket::esCoordinadorTicket($authTicket);
$idUserAuth     = (int)$authTicket['id'];
$coordsUserAuth = $authTicket['coordsIds'];

/* ── 2) Borrado (guard en el controlador) ── */
ControladorTicket::ctrBorrarTicket();

/*=============================================
Helper: pinta tags con color desde un JSON de IDs
=============================================*/
function renderTagsTicket($jsonIds, $tipo){
    $ids = json_decode($jsonIds, true);
    if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

    $mapa = []; $campo = '';

    if($tipo === 'entidad'){
        $lista = ControladorEntidad::ctrMostrarEntidad(null, null);
        $campo = 'entidad';
    } elseif($tipo === 'medio'){
        $lista = ControladorMedio::ctrMostrarMedio(null, null);
        $campo = 'medio';
    } else {
        $lista = ControladorPrioridad::ctrMostrarPrioridad(null, null);
        $campo = 'prioridad';
    }

    if($lista && is_array($lista)){
        foreach($lista as $item){ $mapa[(int)$item["id"]] = $item; }
    }

    $html = '';
    foreach($ids as $id){
        if(isset($mapa[(int)$id])){
            $item  = $mapa[(int)$id];
            $color = $item["color"] ?: "#0d6efd";
            $html .= '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.75rem; padding:5px 9px;">'
                  . htmlspecialchars($item[$campo]) . '</span>';
        }
    }
    return $html ?: '<span class="text-muted">—</span>';
}

/*=============================================
Precarga de status + últimos status por ticket
=============================================*/
$mapaStatusGlobal = [];
$todosStatusGlobal = ControladorStatus::ctrMostrarStatus(null, null);
if($todosStatusGlobal && is_array($todosStatusGlobal)){
    foreach($todosStatusGlobal as $st){ $mapaStatusGlobal[(int)$st["id"]] = $st; }
}

$ultimoStatusPorTicket = [];
$todosTstatusGlobal = ControladorTstatus::ctrMostrarTstatus(null, null);
if($todosTstatusGlobal && is_array($todosTstatusGlobal)){
    foreach($todosTstatusGlobal as $ts){
        $tid = (int)$ts["id_ticket"];
        if(isset($ultimoStatusPorTicket[$tid])) continue;
        $idSt = (int)($ts["id_status"] ?? 0);
        if(isset($mapaStatusGlobal[$idSt])){
            $ultimoStatusPorTicket[$tid] = trim($mapaStatusGlobal[$idSt]["status"]);
        }
    }
}

/*=============================================
✅ Total de servicios por ticket
=============================================*/
$totalServiciosPorTicket = [];
$todosTserviciosGlobal = ControladorTservicio::ctrMostrarTservicio(null, null);
if($todosTserviciosGlobal && is_array($todosTserviciosGlobal)){
    foreach($todosTserviciosGlobal as $tsv){
        $tid = (int)$tsv["id_ticket"];
        $totalServiciosPorTicket[$tid] = ($totalServiciosPorTicket[$tid] ?? 0) + 1;
    }
}

/*=============================================
Filtrado de tickets según reglas
=============================================*/
$todosTickets    = ControladorTicket::ctrMostrarTicket(null, null) ?: [];
$ticketsVisibles = [];

foreach ($todosTickets as $t) {
    $idT     = (int)$t["id"];
    $estadoT = (int)$t["estado"];

    /* Admin + Centro + Despacho → ven TODO */
    if ($puedeVerTodos) {
        $ticketsVisibles[] = $t;
        continue;
    }

    /* Omitir inactivos para el resto */
    if ($estadoT !== 1) continue;

    $asignado = false;

    /* Coordinador → por coordinación asignada */
    if ($esCoordinador) {
        $tcoords = ControladorTcoordinacion::ctrMostrarTcoordinacionPorTicket($idT);
        if ($tcoords && is_array($tcoords)) {
            foreach ($tcoords as $tc) {
                $ids = json_decode($tc["id_coordinacion"] ?? '[]', true) ?: [];
                foreach ($ids as $idC) {
                    if (in_array((int)$idC, $coordsUserAuth, true)) {
                        $asignado = true;
                        break 2;
                    }
                }
            }
        }
    }

    /* Cualquier usuario asignado directo (tuser) */
    if (!$asignado) {
        $tusers = ControladorTuser::ctrMostrarTuserPorTicket($idT);
        if ($tusers && is_array($tusers)) {
            foreach ($tusers as $tu) {
                $ids = json_decode($tu["id_user"] ?? '[]', true) ?: [];
                foreach ($ids as $idU) {
                    if ((int)$idU === $idUserAuth) {
                        $asignado = true;
                        break 2;
                    }
                }
            }
        }
    }

    if ($asignado) $ticketsVisibles[] = $t;
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-ticket-alt me-2"></i> Gestión de Tickets
            <span class="badge text-bg-primary ms-2"><?php echo count($ticketsVisibles); ?></span>
        </h3>
        <?php if ($puedeModificar): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearTicket">
                <i class="fas fa-plus me-1"></i> Nuevo Ticket
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <!--
          ✅ data-order='[[0, "desc"]]' hace que el ticket más reciente
          (mayor ID) aparezca primero.
          Además cada <td> del Ticket # lleva data-order con el ID numérico
          para que el orden natural siga siendo correcto aunque cambie
          el texto mostrado (ej: prefijos, íconos).
        -->
        <table class="table table-bordered table-hover table-striped align-middle tablas"
               style="width:100%"
               data-order='[[0, "desc"]]'>
            <thead class="table-dark">
                <tr>
                    <th>Ticket #</th>
                    <th>Entidades</th>
                    <th>Solicitante</th>
                    <th>Medio(s)</th>
                    <th>Prioridad(es)</th>
                    <th>Descripción</th>
                    <th>Última modificación</th>
                    <th>Estado</th>
                    <th>Check</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ticketsVisibles)): ?>
                    <!-- Dejar vacío para que DataTables muestre el mensaje sin romper columnas -->
                <?php else: ?>
                    <?php foreach ($ticketsVisibles as $value):
                        $idT     = (int)$value["id"];
                        $estadoT = (int)$value["estado"];
                        $checkT  = (int)($value["check"] ?? 0);

                        $modificacion = !empty($value["modificacion"])
                            ? date("d/m/Y H:i", strtotime($value["modificacion"]))
                            : "—";

                        $descCorta = mb_strlen($value["descripcion"]) > 60
                            ? mb_substr($value["descripcion"], 0, 60) . '…'
                            : $value["descripcion"];

                        $ultimoStatus    = $ultimoStatusPorTicket[$idT] ?? null;
                        $totalServiciosT = (int)($totalServiciosPorTicket[$idT] ?? 0);
                    ?>
                    <tr>
                        <!-- 1. Ticket # (data-order con ID numérico para sort robusto) -->
                        <td data-order="<?php echo $idT; ?>">
                            <a href="index.php?ruta=ticket&id=<?php echo $idT; ?>"
                               class="badge text-bg-primary text-decoration-none">
                                <i class="fas fa-ticket-alt me-1"></i>Ticket #<?php echo $idT; ?>
                            </a>
                        </td>
                        <!-- 2. Entidades -->
                        <td><?php echo renderTagsTicket($value["id_entidad"] ?? '', 'entidad'); ?></td>
                        <!-- 3. Solicitante -->
                        <td><i class="fas fa-user me-1 text-muted"></i><?php echo htmlspecialchars($value["solicitante"]); ?></td>
                        <!-- 4. Medios -->
                        <td><?php echo renderTagsTicket($value["id_medio"] ?? '', 'medio'); ?></td>
                        <!-- 5. Prioridades -->
                        <td><?php echo renderTagsTicket($value["id_prioridad"] ?? '', 'prioridad'); ?></td>
                        <!-- 6. Descripción -->
                        <td><span title="<?php echo htmlspecialchars($value["descripcion"]); ?>"><?php echo htmlspecialchars($descCorta); ?></span></td>
                        <!-- 7. Última modificación -->
                        <td><small class="text-muted"><?php echo $modificacion; ?></small></td>
                        <!-- 8. Estado -->
                        <td>
                            <?php if ($puedeModificar): ?>
                                <button class="btn btn-<?php echo $estadoT === 1 ? 'success' : 'danger'; ?> btn-sm btnActivarTicket"
                                        idTicket="<?php echo $idT; ?>"
                                        estadoTicket="<?php echo $estadoT === 1 ? '0' : '1'; ?>">
                                    <i class="fas fa-<?php echo $estadoT === 1 ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $estadoT === 1 ? 'Activo' : 'Inactivo'; ?>
                                </button>
                            <?php else: ?>
                                <span class="badge text-bg-<?php echo $estadoT === 1 ? 'success' : 'danger'; ?>">
                                    <i class="fas fa-<?php echo $estadoT === 1 ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $estadoT === 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <!-- 9. Check -->
                        <td>
                            <?php if ($puedeModificar): ?>
                                <button class="btn btn-<?php echo $checkT === 1 ? 'success' : 'warning'; ?> btn-sm btnCheckTicket"
                                        idTicket="<?php echo $idT; ?>"
                                        estadoCheck="<?php echo $checkT === 1 ? '0' : '1'; ?>"
                                        data-ultimo-status="<?php echo htmlspecialchars($ultimoStatus ?: ''); ?>"
                                        data-total-servicios="<?php echo $totalServiciosT; ?>"
                                        title="<?php echo $checkT === 1 ? 'Click para reabrir' : 'Click para finalizar (requiere último status Resuelto o Suspendido; si es Resuelto, debe tener ≥1 servicio)'; ?>">
                                    <i class="fas fa-<?php echo $checkT === 1 ? 'lock' : 'lock-open'; ?>"></i>
                                    <?php echo $checkT === 1 ? 'Finalizado' : 'Abierto'; ?>
                                </button>
                            <?php else: ?>
                                <span class="badge text-bg-<?php echo $checkT === 1 ? 'success' : 'warning'; ?>">
                                    <i class="fas fa-<?php echo $checkT === 1 ? 'lock' : 'lock-open'; ?>"></i>
                                    <?php echo $checkT === 1 ? 'Finalizado' : 'Abierto'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <!-- 10. Acciones -->
                        <td>
                            <div class="btn-group" role="group">
                                <?php if ($puedeModificar): ?>
                                    <button class="btn btn-warning btn-sm btnEditarTicket"
                                            idTicket="<?php echo $idT; ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarTicket"
                                            title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                <?php endif; ?>

                                <a href="index.php?ruta=ticket&id=<?php echo $idT; ?>"
                                   class="btn btn-info btn-sm" title="Ver información">
                                    <i class="fas fa-info-circle"></i>
                                </a>

                                <?php if ($puedeEliminar): ?>
                                    <button class="btn btn-danger btn-sm btnEliminarTicket"
                                            idTicket="<?php echo $idT; ?>"
                                            nombre="<?php echo htmlspecialchars($value["solicitante"]); ?>"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($puedeModificar): ?>

    <!-- ═══════════════ MODAL CREAR ═══════════════ -->
    <div class="modal fade" id="modalCrearTicket" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="formCrearTicket" method="post">
                    <input type="hidden" name="nuevoTicket" value="1">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nuevo Ticket</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Solicitante <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nuevoSolicitante" placeholder="Ej: Juan Pérez" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label"><i class="fas fa-city me-1 text-muted"></i> Entidad(es)</label>
                                <select id="nuevoIdEntidad" name="nuevoIdEntidad[]" multiple placeholder="Seleccione entidades..."></select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label"><i class="fas fa-broadcast-tower me-1 text-muted"></i> Medio(s)</label>
                                <select id="nuevoIdMedio" name="nuevoIdMedio[]" multiple placeholder="Seleccione medios..."></select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label"><i class="fas fa-flag me-1 text-muted"></i> Prioridad(es)</label>
                                <select id="nuevoIdPrioridad" name="nuevoIdPrioridad[]" multiple placeholder="Seleccione prioridades..."></select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Descripción <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="nuevoDescripcion" rows="4" maxlength="5000" placeholder="Describa el ticket..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Guardar Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══════════════ MODAL EDITAR ═══════════════ -->
    <div class="modal fade" id="modalEditarTicket" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="formEditarTicket" method="post">
                    <input type="hidden" name="editarTicket" value="1">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Ticket</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Solicitante <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="editarSolicitante" id="editarSolicitante" required>
                                <input type="hidden" name="idTicket" id="idTicket">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label"><i class="fas fa-city me-1 text-muted"></i> Entidad(es)</label>
                                <select id="editarIdEntidad" name="editarIdEntidad[]" multiple placeholder="Seleccione entidades..."></select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label"><i class="fas fa-broadcast-tower me-1 text-muted"></i> Medio(s)</label>
                                <select id="editarIdMedio" name="editarIdMedio[]" multiple placeholder="Seleccione medios..."></select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label"><i class="fas fa-flag me-1 text-muted"></i> Prioridad(es)</label>
                                <select id="editarIdPrioridad" name="editarIdPrioridad[]" multiple placeholder="Seleccione prioridades..."></select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Descripción <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="editarDescripcion" id="editarDescripcion" rows="4" maxlength="5000" required></textarea>
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

<!-- ═══════════════ SCRIPTS ESPECÍFICOS ═══════════════ -->
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
        render: { option: renderOpcion, item: renderItem }
    };

    let tsNuevoEntidad, tsNuevoMedio, tsNuevoPrioridad;
    let tsEditarEntidad, tsEditarMedio, tsEditarPrioridad;
    let tsNuevoInitialized  = false;
    let tsEditarInitialized = false;
    let pendingTicketData   = null;

    let cacheEnt = null, cacheMed = null, cachePri = null;

    function fetchEntidades(){
        if(cacheEnt) return Promise.resolve(cacheEnt);
        return fetch('ajax/ticket.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarEntidadesActivas','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheEnt = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheEnt; })
        .catch(() => []);
    }
    function fetchMedios(){
        if(cacheMed) return Promise.resolve(cacheMed);
        return fetch('ajax/ticket.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarMediosActivos','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheMed = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheMed; })
        .catch(() => []);
    }
    function fetchPrioridades(){
        if(cachePri) return Promise.resolve(cachePri);
        return fetch('ajax/ticket.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarPrioridadesActivas','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cachePri = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cachePri; })
        .catch(() => []);
    }

    function addOptionsToTS(ts, data){
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
        const [ent, med, pri] = await Promise.all([fetchEntidades(), fetchMedios(), fetchPrioridades()]);
        tsNuevoEntidad   = new TomSelect('#nuevoIdEntidad',   Object.assign({}, tomSelectConfig));
        tsNuevoMedio     = new TomSelect('#nuevoIdMedio',     Object.assign({}, tomSelectConfig));
        tsNuevoPrioridad = new TomSelect('#nuevoIdPrioridad', Object.assign({}, tomSelectConfig));
        addOptionsToTS(tsNuevoEntidad,   ent);
        addOptionsToTS(tsNuevoMedio,     med);
        addOptionsToTS(tsNuevoPrioridad, pri);
        tsNuevoInitialized = true;
    }
    async function initEditarTS(){
        if(tsEditarInitialized) return;
        const [ent, med, pri] = await Promise.all([fetchEntidades(), fetchMedios(), fetchPrioridades()]);
        tsEditarEntidad   = new TomSelect('#editarIdEntidad',   Object.assign({}, tomSelectConfig));
        tsEditarMedio     = new TomSelect('#editarIdMedio',     Object.assign({}, tomSelectConfig));
        tsEditarPrioridad = new TomSelect('#editarIdPrioridad', Object.assign({}, tomSelectConfig));
        addOptionsToTS(tsEditarEntidad,   ent);
        addOptionsToTS(tsEditarMedio,     med);
        addOptionsToTS(tsEditarPrioridad, pri);
        tsEditarInitialized = true;
    }

    const modalCrearEl = document.getElementById('modalCrearTicket');
    if(modalCrearEl){
        modalCrearEl.addEventListener('shown.bs.modal', () => {
            initCrearTS().then(() => {
                if(tsNuevoEntidad)   tsNuevoEntidad.refreshOptions(false);
                if(tsNuevoMedio)     tsNuevoMedio.refreshOptions(false);
                if(tsNuevoPrioridad) tsNuevoPrioridad.refreshOptions(false);
            });
        });
        modalCrearEl.addEventListener('hidden.bs.modal', () => {
            if(tsNuevoEntidad)   tsNuevoEntidad.clear(true);
            if(tsNuevoMedio)     tsNuevoMedio.clear(true);
            if(tsNuevoPrioridad) tsNuevoPrioridad.clear(true);
            const f = document.getElementById('formCrearTicket');
            if(f) f.reset();
        });
    }

    const modalEditarEl = document.getElementById('modalEditarTicket');
    if(modalEditarEl){
        modalEditarEl.addEventListener('shown.bs.modal', async function(){
            await initEditarTS();
            if(pendingTicketData){
                const d = pendingTicketData;
                document.getElementById('idTicket').value          = d.id;
                document.getElementById('editarSolicitante').value = d.solicitante;
                document.getElementById('editarDescripcion').value = d.descripcion;
                setTSValue(tsEditarEntidad,   d.id_entidad);
                setTSValue(tsEditarMedio,     d.id_medio);
                setTSValue(tsEditarPrioridad, d.id_prioridad);
                pendingTicketData = null;
            }
            if(tsEditarEntidad)   tsEditarEntidad.refreshOptions(false);
            if(tsEditarMedio)     tsEditarMedio.refreshOptions(false);
            if(tsEditarPrioridad) tsEditarPrioridad.refreshOptions(false);
        });
    }

    /* ═══════════════════════════════════════════════════════
       ✅ CREAR TICKET → Redirige al detalle del nuevo ticket
       ═══════════════════════════════════════════════════════ */
    const formCrear = document.getElementById('formCrearTicket');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            Swal.fire({ title: 'Guardando ticket...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/ticket.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Ticket guardado' });

                        // ✅ Redirige al detalle del ticket recién creado
                        setTimeout(() => {
                            if (respuesta.idTicket) {
                                window.location.href = 'index.php?ruta=ticket&id=' + respuesta.idTicket;
                            } else {
                                // Fallback (no debería ocurrir): recarga normal
                                location.reload();
                            }
                        }, 1200);
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error al guardar' });
                    }
                })
                .catch(err => { console.error(err); Swal.close(); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    }

    const formEditar = document.getElementById('formEditarTicket');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(this);
            Swal.fire({ title: 'Actualizando ticket...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            fetch('ajax/ticket.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Ticket actualizado' });
                        setTimeout(() => location.reload(), 1800);
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error al actualizar' });
                    }
                })
                .catch(err => { console.error(err); Swal.close(); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    }

    /* Activar / Desactivar (estado) */
    document.querySelectorAll('.btnActivarTicket').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTicket     = this.getAttribute('idTicket');
            const estadoTicket = this.getAttribute('estadoTicket');
            const btnElement   = this;
            const datos = new FormData();
            datos.append('activarTicket', estadoTicket);
            datos.append('activarId',     idTicket);
            fetch('ajax/ticket.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'ok'){
                        if(estadoTicket === '1'){
                            btnElement.classList.remove('btn-danger');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                            btnElement.setAttribute('estadoTicket', '0');
                        }else{
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-danger');
                            btnElement.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                            btnElement.setAttribute('estadoTicket', '1');
                        }
                        Toast.fire({ icon: 'success', title: 'Estado actualizado' });
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error al actualizar' });
                    }
                })
                .catch(err => { console.error(err); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    });

    /* Cambiar CHECK con validación de último status + servicios */
    document.querySelectorAll('.btnCheckTicket').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTicket   = this.getAttribute('idTicket');
            const nuevoCheck = this.getAttribute('estadoCheck');
            const btnElement = this;

            if (nuevoCheck === '1') {
                const ultimoStatus = (this.getAttribute('data-ultimo-status') || '').trim();
                const permitidos   = ['resuelto', 'suspendido'];
                if (!permitidos.includes(ultimoStatus.toLowerCase())) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No se puede finalizar',
                        html: `El último status del ticket debe ser <strong>Resuelto</strong> o <strong>Suspendido</strong>.<br>` +
                              `Actualmente es: <strong>${ultimoStatus || 'sin status registrado'}</strong>.`
                    });
                    return;
                }

                if (ultimoStatus.toLowerCase() === 'resuelto') {
                    const totalServicios = parseInt(this.getAttribute('data-total-servicios')) || 0;
                    if (totalServicios <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No se puede finalizar',
                            html: `El último status del ticket <strong>#${idTicket}</strong> es <strong>Resuelto</strong>.<br>` +
                                  `Debe tener al menos <strong>1 servicio registrado</strong> antes de finalizar.`
                        });
                        return;
                    }
                }
            }

            const datos = new FormData();
            datos.append('cambiarCheckTicket', '1');
            datos.append('idTicket', idTicket);
            datos.append('nuevoCheck', nuevoCheck);

            fetch('ajax/ticket.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'success'){
                        const checkAplicado = parseInt(respuesta.check);
                        if(checkAplicado === 1){
                            btnElement.classList.remove('btn-warning');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-lock"></i> Finalizado';
                            btnElement.setAttribute('estadoCheck', '0');
                            btnElement.setAttribute('title', 'Click para reabrir');
                        }else{
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-warning');
                            btnElement.innerHTML = '<i class="fas fa-lock-open"></i> Abierto';
                            btnElement.setAttribute('estadoCheck', '1');
                            btnElement.setAttribute('title', 'Click para finalizar (requiere último status Resuelto o Suspendido; si es Resuelto, debe tener ≥1 servicio)');
                        }
                        Toast.fire({ icon: 'success', title: respuesta.mensaje });
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error' });
                    }
                })
                .catch(err => { console.error(err); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    });

    /* Cargar datos en modal editar */
    document.querySelectorAll('.btnEditarTicket').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTicket = this.getAttribute('idTicket');
            const datos = new FormData();
            datos.append('idTicket', idTicket);
            fetch('ajax/ticket.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta && respuesta.id){
                        pendingTicketData = respuesta;
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'No se pudo cargar el ticket' });
                    }
                })
                .catch(err => { console.error(err); Toast.fire({ icon: 'error', title: 'Error de conexión' }); });
        });
    });

    /* Eliminar → ruta correcta (plural) */
    document.querySelectorAll('.btnEliminarTicket').forEach(btn => {
        btn.addEventListener('click', function() {
            const idTicket = this.getAttribute('idTicket');
            const nombre   = this.getAttribute('nombre');
            Swal.fire({
                title: '¿Eliminar ticket?',
                html: `Estás a punto de eliminar el ticket de <strong>${nombre}</strong>.<br>Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if(result.isConfirmed){
                    window.location = 'index.php?ruta=tickets&idTicket=' + idTicket;
                }
            });
        });
    });

});
</script>