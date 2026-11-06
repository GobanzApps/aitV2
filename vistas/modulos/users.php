<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: Admin | Centro de Atención | Coordinador | Especialista/Técnico
=============================================*/
if (!ControladorUser::puedeVerListadoUsers()) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-shield-alt me-2"></i>
            <strong>Acceso denegado.</strong>
            Esta sección es solo para usuarios con rol <strong>Administrador</strong>,
            con coordinación <strong>Centro de Atención</strong>,
            con rol <strong>Coordinador</strong>
            o con rol <strong>Especialista/Técnico</strong>.
            <br><br>
            <a href="index.php?ruta=inicio" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
          </div>';
    return;
}

/* ✅ ¿Es admin? → controla visibilidad de botones CRUD y modales */
$esAdminView = ControladorUser::esAdminUserModulo();

// Procesar borrado ANTES de renderizar la tabla (con guard interno de admin)
ControladorUser::ctrBorrarUser();

// Helper para renderizar tags de coordinación/rol/entidad en la tabla
function renderTags($jsonIds, $tipo){
    $ids = json_decode($jsonIds, true);
    if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

    $html = '';
    if($tipo === 'coordinacion'){
        $lista = ControladorCoordinacion::ctrMostrarCoordinacion(null, null);
        $mapa = [];
        if($lista && is_array($lista)){
            foreach($lista as $item){ $mapa[$item["id"]] = $item; }
        }
        foreach($ids as $id){
            if(isset($mapa[$id])){
                $c = $mapa[$id];
                $color = $c["color"] ?: "#0d6efd";
                $html .= '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.75rem; padding:5px 9px;">'.htmlspecialchars($c["coordinacion"]).'</span>';
            }
        }
    } elseif($tipo === 'rol') {
        $lista = ControladorRol::ctrMostrarRol(null, null);
        $mapa = [];
        if($lista && is_array($lista)){
            foreach($lista as $item){ $mapa[$item["id"]] = $item; }
        }
        foreach($ids as $id){
            if(isset($mapa[$id])){
                $r = $mapa[$id];
                $color = $r["color"] ?: "#0d6efd";
                $html .= '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.75rem; padding:5px 9px;">'.htmlspecialchars($r["rol"]).'</span>';
            }
        }
    } else { // entidad
        $lista = ControladorEntidad::ctrMostrarEntidad(null, null);
        $mapa = [];
        if($lista && is_array($lista)){
            foreach($lista as $item){ $mapa[$item["id"]] = $item; }
        }
        foreach($ids as $id){
            if(isset($mapa[$id])){
                $e = $mapa[$id];
                $color = $e["color"] ?: "#0d6efd";
                $html .= '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.75rem; padding:5px 9px;">'.htmlspecialchars($e["entidad"]).'</span>';
            }
        }
    }
    return $html ?: '<span class="text-muted">—</span>';
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-users me-2"></i> Gestión de Usuarios
            <span class="badge text-bg-primary ms-2">
                <?php
                $totalVisibles = count(ControladorUser::usuariosVisiblesParaListado());
                echo $totalVisibles;
                ?>
            </span>
        </h3>

        <?php if ($esAdminView): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUser">
                <i class="fas fa-plus me-1"></i> Nuevo Usuario
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th style="width: 60px;">#</th>
                    <th style="width: 80px;">Foto</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Coordinación</th>
                    <th>Rol</th>
                    <th>Entidad</th>
                    <th style="width: 130px;">Último Login</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                /* ✅ SOLO usuarios visibles según reglas del módulo */
                $usuarios = ControladorUser::usuariosVisiblesParaListado();

                if($usuarios && is_array($usuarios)){
                    foreach($usuarios as $key => $value){
                        $foto = !empty($value["foto"]) ? $value["foto"] : "https://ui-avatars.com/api/?name=".urlencode($value["user"])."&background=0d6efd&color=fff&size=64";
                        $ultimoLogin = !empty($value["ultimo_login"]) ? date("d/m/Y H:i", strtotime($value["ultimo_login"])) : "Nunca";
                        echo '<tr>
                            <td>'.($key + 1).'</td>
                            <td><img src="'.htmlspecialchars($foto).'" class="rounded-circle" style="width:40px; height:40px; object-fit:cover;" onerror="this.src=\'https://ui-avatars.com/api/?name='.urlencode($value["user"]).'&background=0d6efd&color=fff&size=64\'"></td>
                            <td><strong>'.(!empty($value["name"]) ? htmlspecialchars($value["name"]) : '<span class="text-muted"><em>Sin nombre</em></span>').'</strong></td>
                            <td><span class="text-muted">@</span>'.htmlspecialchars($value["user"]).'</td>
                            <td>'.htmlspecialchars($value["email"]).'</td>
                            <td>'.htmlspecialchars($value["phone"]).'</td>
                            <td>'.renderTags($value["id_coordinacion"] ?? '', 'coordinacion').'</td>
                            <td>'.renderTags($value["id_rol"] ?? '', 'rol').'</td>
                            <td>'.renderTags($value["id_entidad"] ?? '', 'entidad').'</td>
                            <td><small class="text-muted">'.$ultimoLogin.'</small></td>
                            <td>';

                        /* ✅ Estado: botón si es admin, badge si no */
                        if ($esAdminView) {
                            if($value["estado"] == 1){
                                echo '<button class="btn btn-success btn-sm btnActivarUser" idUser="'.$value["id"].'" estadoUser="0"><i class="fas fa-check-circle"></i> Activo</button>';
                            }else{
                                echo '<button class="btn btn-danger btn-sm btnActivarUser" idUser="'.$value["id"].'" estadoUser="1"><i class="fas fa-times-circle"></i> Inactivo</button>';
                            }
                        } else {
                            if($value["estado"] == 1){
                                echo '<span class="badge text-bg-success"><i class="fas fa-check-circle me-1"></i>Activo</span>';
                            }else{
                                echo '<span class="badge text-bg-danger"><i class="fas fa-times-circle me-1"></i>Inactivo</span>';
                            }
                        }

                        echo '</td>
                            <td>
                                <div class="btn-group" role="group">';

                                if ($esAdminView) {
                                    echo '<button class="btn btn-warning btn-sm btnEditarUser" idUser="'.$value["id"].'" title="Editar"><i class="fas fa-pencil-alt"></i></button>';
                                }

                                /* Info → siempre visible para los 4 grupos autorizados */
                                echo '<a href="index.php?ruta=user&id='.$value["id"].'" class="btn btn-info btn-sm" title="Ver información"><i class="fas fa-info-circle"></i></a>';

                                if ($esAdminView) {
                                    echo '<button class="btn btn-danger btn-sm btnEliminarUser" idUser="'.$value["id"].'" usuario="'.htmlspecialchars($value["user"]).'" foto="'.htmlspecialchars($value["foto"]).'" title="Eliminar"><i class="fas fa-trash"></i></button>';
                                }

                        echo '</div>
                            </td>
                        </tr>';
                    }
                }else{
                    echo '<tr><td colspan="12" class="text-center text-muted">No hay usuarios para mostrar</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($esAdminView): ?>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formCrearUser" method="post" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Nuevo Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre completo</label>
                            <input type="text" class="form-control" name="nuevoName" placeholder="Ej: Juan Pérez">
                            <small class="text-muted">Opcional. Solo letras, espacios y guiones.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nuevoUser" placeholder="Solo letras y números" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="nuevoPassword" placeholder="Solo letras y números" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="nuevoEmail" placeholder="correo@ejemplo.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="nuevoPhone" placeholder="+58 000-0000000">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-sitemap me-1 text-muted"></i> Coordinaciones
                            </label>
                            <select id="nuevoIdCoordinacion" name="nuevoIdCoordinacion[]" multiple placeholder="Seleccione coordinaciones..."></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-user-tag me-1 text-muted"></i> Roles
                            </label>
                            <select id="nuevoIdRol" name="nuevoIdRol[]" multiple placeholder="Seleccione roles..."></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-city me-1 text-muted"></i> Entidades
                            </label>
                            <select id="nuevoIdEntidad" name="nuevoIdEntidad[]" multiple placeholder="Seleccione entidades..."></select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Foto de perfil</label>
                            <input type="file" class="form-control" name="nuevaFoto" accept="image/jpeg,image/png">
                            <small class="text-muted">JPG o PNG (máx. 2MB)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formEditarUser" method="post" enctype="multipart/form-data">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i> Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre completo</label>
                            <input type="text" class="form-control" name="editarName" id="editarName" placeholder="Ej: Juan Pérez">
                            <small class="text-muted">Opcional. Solo letras, espacios y guiones.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="editarUser" id="editarUser" required>
                            <input type="hidden" name="idUser" id="idUser">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña</label>
                            <input type="password" class="form-control" name="editarPassword" placeholder="Dejar en blanco para no cambiar">
                            <input type="hidden" name="passwordActual" id="passwordActual">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="editarEmail" id="editarEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="editarPhone" id="editarPhone">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-sitemap me-1 text-muted"></i> Coordinaciones
                            </label>
                            <select id="editarIdCoordinacion" name="editarIdCoordinacion[]" multiple placeholder="Seleccione coordinaciones..."></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-user-tag me-1 text-muted"></i> Roles
                            </label>
                            <select id="editarIdRol" name="editarIdRol[]" multiple placeholder="Seleccione roles..."></select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-city me-1 text-muted"></i> Entidades
                            </label>
                            <select id="editarIdEntidad" name="editarIdEntidad[]" multiple placeholder="Seleccione entidades..."></select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Foto de perfil</label>
                            <input type="file" class="form-control" name="editarFoto" accept="image/jpeg,image/png">
                            <input type="hidden" name="fotoActual" id="fotoActual">
                            <div class="mt-2">
                                <img id="previewFoto" src="" class="rounded-circle" style="width:60px; height:60px; object-fit:cover;">
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

<?php endif; ?>

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

    /*=============================================
    VARIABLES DE ESTADO
    =============================================*/
    let pendingUserData = null;
    let isFetchingUser = false;

    let tsNuevoCoord, tsNuevoRol, tsNuevoEntidad;
    let tsEditarCoord, tsEditarRol, tsEditarEntidad;
    let tsNuevoInitialized  = false;
    let tsEditarInitialized = false;

    let cacheCoord = null, cacheRol = null, cacheEnt = null;

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
        render: { option: renderOpcion, item: renderItem }
    };

    /*=============================================
    Fetches con caché
    =============================================*/
    function fetchCoordinaciones() {
        if (cacheCoord) return Promise.resolve(cacheCoord);
        return fetch('ajax/user.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarCoordinacionesActivas','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheCoord = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheCoord; })
        .catch(() => []);
    }

    function fetchRoles() {
        if (cacheRol) return Promise.resolve(cacheRol);
        return fetch('ajax/user.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarRolesActivos','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheRol = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheRol; })
        .catch(() => []);
    }

    function fetchEntidades() {
        if (cacheEnt) return Promise.resolve(cacheEnt);
        return fetch('ajax/user.ajax.php', {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('listarEntidadesActivas','1'); return fd; })()
        })
        .then(r => r.json())
        .then(r => { cacheEnt = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheEnt; })
        .catch(() => []);
    }

    /*=============================================
    Aplicar opciones a una instancia Tom Select
    =============================================*/
    function addOptionsToTS(tsInstance, data) {
        data.forEach(item => {
            if (!tsInstance.options[String(item.id)]) {
                tsInstance.addOption({ id: String(item.id), text: item.texto, color: item.color });
            }
        });
        tsInstance.refreshOptions(false);
    }

    /*=============================================
    Poblar un Tom Select con IDs
    =============================================*/
    function setTSValue(tsInstance, jsonIds) {
        let ids = [];
        try { ids = JSON.parse(jsonIds || '[]'); } catch(e){ ids = []; }
        if (!Array.isArray(ids)) ids = [];
        ids = ids.map(String);

        ids.forEach(id => {
            if (!tsInstance.options[id]) {
                tsInstance.addOption({ id: id, text: 'ID '+id, color: '#6c757d' });
            }
        });
        tsInstance.setValue(ids, true);
        tsInstance.refreshOptions(false);
    }

    /*=============================================
    Inicializar Tom Select del modal CREAR
    =============================================*/
    async function initCrearTS() {
        if (tsNuevoInitialized) return;

        const [coords, roles, ents] = await Promise.all([
            fetchCoordinaciones(), fetchRoles(), fetchEntidades()
        ]);

        tsNuevoCoord   = new TomSelect('#nuevoIdCoordinacion', Object.assign({}, tomSelectConfig));
        tsNuevoRol     = new TomSelect('#nuevoIdRol',          Object.assign({}, tomSelectConfig));
        tsNuevoEntidad = new TomSelect('#nuevoIdEntidad',      Object.assign({}, tomSelectConfig));

        addOptionsToTS(tsNuevoCoord, coords);
        addOptionsToTS(tsNuevoRol, roles);
        addOptionsToTS(tsNuevoEntidad, ents);

        tsNuevoInitialized = true;
    }

    /*=============================================
    Inicializar Tom Select del modal EDITAR
    =============================================*/
    async function initEditarTS() {
        if (tsEditarInitialized) return;

        const [coords, roles, ents] = await Promise.all([
            fetchCoordinaciones(), fetchRoles(), fetchEntidades()
        ]);

        tsEditarCoord   = new TomSelect('#editarIdCoordinacion', Object.assign({}, tomSelectConfig));
        tsEditarRol     = new TomSelect('#editarIdRol',          Object.assign({}, tomSelectConfig));
        tsEditarEntidad = new TomSelect('#editarIdEntidad',      Object.assign({}, tomSelectConfig));

        addOptionsToTS(tsEditarCoord, coords);
        addOptionsToTS(tsEditarRol, roles);
        addOptionsToTS(tsEditarEntidad, ents);

        tsEditarInitialized = true;
    }

    /*=============================================
    MODAL CREAR
    =============================================*/
    const modalCrearEl = document.getElementById('modalCrearUser');
    if (modalCrearEl) {
        modalCrearEl.addEventListener('shown.bs.modal', function() {
            initCrearTS().then(() => {
                if (tsNuevoCoord)   tsNuevoCoord.refreshOptions(false);
                if (tsNuevoRol)     tsNuevoRol.refreshOptions(false);
                if (tsNuevoEntidad) tsNuevoEntidad.refreshOptions(false);
            });
        });

        modalCrearEl.addEventListener('hidden.bs.modal', function() {
            if (tsNuevoCoord)   tsNuevoCoord.clear(true);
            if (tsNuevoRol)     tsNuevoRol.clear(true);
            if (tsNuevoEntidad) tsNuevoEntidad.clear(true);
            const form = document.getElementById('formCrearUser');
            if (form) form.reset();
        });
    }

    /*=============================================
    MODAL EDITAR
    =============================================*/
    const modalEditarEl = document.getElementById('modalEditarUser');
    if (modalEditarEl) {
        modalEditarEl.addEventListener('shown.bs.modal', async function() {
            await initEditarTS();

            if (pendingUserData) {
                const d = pendingUserData;

                document.getElementById('idUser').value         = d.id;
                document.getElementById('editarName').value     = d.name || '';
                document.getElementById('editarUser').value     = d.user || '';
                document.getElementById('editarEmail').value    = d.email || '';
                document.getElementById('editarPhone').value    = d.phone || '';
                document.getElementById('passwordActual').value = d.password || '';
                document.getElementById('fotoActual').value     = d.foto || '';

                const preview = document.getElementById('previewFoto');
                preview.src = d.foto || 'https://ui-avatars.com/api/?name='+encodeURIComponent(d.user)+'&background=0d6efd&color=fff&size=64';

                setTSValue(tsEditarCoord,   d.id_coordinacion);
                setTSValue(tsEditarRol,     d.id_rol);
                setTSValue(tsEditarEntidad, d.id_entidad);

                pendingUserData = null;
            }

            if (tsEditarCoord)   tsEditarCoord.refreshOptions(false);
            if (tsEditarRol)     tsEditarRol.refreshOptions(false);
            if (tsEditarEntidad) tsEditarEntidad.refreshOptions(false);
        });
    }

    /*=============================================
    ⚡ EVENT DELEGATION
    =============================================*/
    document.addEventListener('click', async function(e) {

        /* 1. BOTÓN EDITAR */
        const btnEdit = e.target.closest('.btnEditarUser');
        if (btnEdit) {
            e.preventDefault();
            e.stopPropagation();

            if (isFetchingUser) return;

            const idUser = btnEdit.getAttribute('idUser');
            if (!idUser) return;

            isFetchingUser = true;
            const originalHTML = btnEdit.innerHTML;
            btnEdit.disabled = true;
            btnEdit.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const fd = new FormData();
                fd.append('idUser', idUser);

                const res = await fetch('ajax/user.ajax.php', { method: 'POST', body: fd });
                const respuesta = await res.json();

                if (respuesta && respuesta.id) {
                    pendingUserData = respuesta;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarUser')).show();
                } else {
                    Toast.fire({ icon: 'error', title: (respuesta && respuesta.mensaje) || 'No se pudo cargar el usuario' });
                }
            } catch(err) {
                console.error('[EditarUser]', err);
                Toast.fire({ icon: 'error', title: 'Error de conexión' });
            } finally {
                isFetchingUser = false;
                btnEdit.disabled = false;
                btnEdit.innerHTML = originalHTML;
            }
            return;
        }

        /* 2. BOTÓN ACTIVAR / DESACTIVAR */
        const btnActivar = e.target.closest('.btnActivarUser');
        if (btnActivar) {
            e.preventDefault();
            e.stopPropagation();

            const idUser     = btnActivar.getAttribute('idUser');
            const estadoUser = btnActivar.getAttribute('estadoUser');
            if (!idUser) return;

            const fd = new FormData();
            fd.append('activarUser', estadoUser);
            fd.append('activarId',   idUser);

            try {
                const res = await fetch('ajax/user.ajax.php', { method: 'POST', body: fd });
                const respuesta = await res.json();

                if (respuesta && respuesta.status === 'ok') {
                    if (estadoUser === '1') {
                        btnActivar.classList.remove('btn-danger');
                        btnActivar.classList.add('btn-success');
                        btnActivar.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                        btnActivar.setAttribute('estadoUser', '0');
                    } else {
                        btnActivar.classList.remove('btn-success');
                        btnActivar.classList.add('btn-danger');
                        btnActivar.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                        btnActivar.setAttribute('estadoUser', '1');
                    }
                    Toast.fire({ icon: 'success', title: 'Estado actualizado' });
                } else {
                    Toast.fire({ icon: 'error', title: (respuesta && respuesta.mensaje) || 'Error al actualizar el estado' });
                }
            } catch(err) {
                console.error('[ActivarUser]', err);
                Toast.fire({ icon: 'error', title: 'Error de conexión' });
            }
            return;
        }

        /* 3. BOTÓN ELIMINAR */
        const btnEliminar = e.target.closest('.btnEliminarUser');
        if (btnEliminar) {
            e.preventDefault();
            e.stopPropagation();

            const idUser  = btnEliminar.getAttribute('idUser');
            const usuario = btnEliminar.getAttribute('usuario');
            const foto    = btnEliminar.getAttribute('foto');
            if (!idUser) return;

            Swal.fire({
                title: '¿Eliminar usuario?',
                html: `Estás a punto de eliminar a <strong>${usuario}</strong>.<br>Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location = 'index.php?ruta=users&idUser=' + idUser + '&usuario=' + encodeURIComponent(usuario) + '&fotoUsuario=' + encodeURIComponent(foto || '');
                }
            });
            return;
        }
    });

});
</script>