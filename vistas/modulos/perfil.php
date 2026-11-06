<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

// Obtener los datos actuales del usuario logueado
$usuarioActual = ControladorUser::ctrMostrarUser("id", $_SESSION["id"]);

if(!$usuarioActual){
    echo '<div class="alert alert-danger">No se pudieron cargar los datos del usuario.</div>';
    return;
}

// Foto por defecto
$foto = !empty($usuarioActual["foto"])
    ? $usuarioActual["foto"]
    : "https://ui-avatars.com/api/?name=".urlencode($usuarioActual["user"])."&background=0d6efd&color=fff&size=128";

// Formatear último login
$ultimoLogin = !empty($usuarioActual["ultimo_login"])
    ? date("d/m/Y H:i", strtotime($usuarioActual["ultimo_login"]))
    : "Nunca";

/*=============================================
Helpers para resolver IDs → datos con color
=============================================*/

function perfilDecodificarIds($json){
    if(empty($json)) return [];
    $ids = json_decode($json, true);
    return is_array($ids) ? array_map('intval', $ids) : [];
}

function perfilResolverCoordinaciones($ids){
    if(empty($ids)) return [];
    $todas = ControladorCoordinacion::ctrMostrarCoordinacion(null, null);
    if(!$todas || !is_array($todas)) return [];

    $mapa = [];
    foreach($todas as $c){ $mapa[(int)$c["id"]] = $c; }

    $resultado = [];
    foreach($ids as $id){
        if(isset($mapa[$id])){
            $resultado[] = [
                "nombre" => $mapa[$id]["coordinacion"],
                "color"  => $mapa[$id]["color"] ?: "#0d6efd",
                "estado" => (int)$mapa[$id]["estado"]
            ];
        }
    }
    return $resultado;
}

function perfilResolverRoles($ids){
    if(empty($ids)) return [];
    $todos = ControladorRol::ctrMostrarRol(null, null);
    if(!$todos || !is_array($todos)) return [];

    $mapa = [];
    foreach($todos as $r){ $mapa[(int)$r["id"]] = $r; }

    $resultado = [];
    foreach($ids as $id){
        if(isset($mapa[$id])){
            $resultado[] = [
                "nombre" => $mapa[$id]["rol"],
                "color"  => $mapa[$id]["color"] ?: "#0d6efd",
                "estado" => (int)$mapa[$id]["estado"]
            ];
        }
    }
    return $resultado;
}

/* NUEVO: Resolver Entidades */
function perfilResolverEntidades($ids){
    if(empty($ids)) return [];
    $todas = ControladorEntidad::ctrMostrarEntidad(null, null);
    if(!$todas || !is_array($todas)) return [];

    $mapa = [];
    foreach($todas as $e){ $mapa[(int)$e["id"]] = $e; }

    $resultado = [];
    foreach($ids as $id){
        if(isset($mapa[$id])){
            $resultado[] = [
                "nombre" => $mapa[$id]["entidad"],
                "color"  => $mapa[$id]["color"] ?: "#0d6efd",
                "estado" => (int)$mapa[$id]["estado"]
            ];
        }
    }
    return $resultado;
}

function perfilRenderBadge($texto, $color, $estado = 1, $icono = 'fa-tag'){
    $color = htmlspecialchars($color ?: '#0d6efd');
    $texto = htmlspecialchars($texto);
    $opacity = $estado === 1 ? '1' : '0.55';
    $extra   = $estado === 1 ? '' : ' <small><em>(inactivo)</em></small>';
    return '<span class="perfil-badge" style="background-color:'.$color.'; opacity:'.$opacity.';">'
         . '<i class="fas '.$icono.' me-1"></i> '.$texto.$extra
         . '</span>';
}

$coordsIds     = perfilDecodificarIds($usuarioActual["id_coordinacion"] ?? '');
$rolesIds      = perfilDecodificarIds($usuarioActual["id_rol"] ?? '');
$entidadesIds  = perfilDecodificarIds($usuarioActual["id_entidad"] ?? '');   // ← NUEVO

$coordsData    = perfilResolverCoordinaciones($coordsIds);
$rolesData     = perfilResolverRoles($rolesIds);
$entidadesData = perfilResolverEntidades($entidadesIds);                     // ← NUEVO
?>

<style>
    .perfil-badge {
        display: inline-block;
        color: #fff;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        margin: 3px 4px 3px 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .perfil-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    }
    .perfil-badge small { font-size: 0.72rem; }
    .perfil-section-title {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        font-weight: 600;
        margin: 14px 0 6px 0;
        display: flex;
        align-items: center;
    }
    .perfil-section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e9ecef;
        margin-left: 10px;
    }
</style>

<!-- ═══════════════════════════════════════════════════════════
     PERFIL DE USUARIO
     ═══════════════════════════════════════════════════════════ -->

<div class="row">
    <!-- ─────────── COLUMNA IZQUIERDA: Tarjeta del perfil ─────────── -->
    <div class="col-md-4">

        <!-- Card con foto y datos básicos -->
        <div class="card card-primary card-outline">
            <div class="card-body box-profile">
                <div class="text-center">
                    <img class="profile-user-img img-fluid img-circle"
                         src="<?php echo htmlspecialchars($foto); ?>"
                         alt="User profile picture"
                         style="width:120px; height:120px; object-fit:cover; border:3px solid #dee2e6;"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($usuarioActual["user"]); ?>&background=0d6efd&color=fff&size=128';">
                </div>

                <?php if(!empty($usuarioActual["name"])): ?>
                    <h3 class="profile-username text-center mt-3">
                        <?php echo htmlspecialchars($usuarioActual["name"]); ?>
                    </h3>
                    <p class="text-muted text-center">
                        <i class="fas fa-at me-1"></i> <?php echo htmlspecialchars($usuarioActual["user"]); ?>
                    </p>
                <?php else: ?>
                    <h3 class="profile-username text-center mt-3">
                        <?php echo htmlspecialchars($usuarioActual["user"]); ?>
                    </h3>
                    <p class="text-muted text-center">
                        <i class="fas fa-user-shield me-1"></i> Usuario del sistema
                    </p>
                <?php endif; ?>

                <ul class="list-group list-group-unbordered mb-3">
                    <li class="list-group-item">
                        <i class="fas fa-id-card text-primary me-2"></i>
                        <b>Nombre</b>
                        <span class="float-end text-muted">
                            <?php echo htmlspecialchars($usuarioActual["name"] ?: "—"); ?>
                        </span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-envelope text-primary me-2"></i>
                        <b>Email</b>
                        <span class="float-end text-muted">
                            <?php echo htmlspecialchars($usuarioActual["email"] ?: "—"); ?>
                        </span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-phone text-primary me-2"></i>
                        <b>Teléfono</b>
                        <span class="float-end text-muted">
                            <?php echo htmlspecialchars($usuarioActual["phone"] ?: "—"); ?>
                        </span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-clock text-primary me-2"></i>
                        <b>Último login</b>
                        <span class="float-end text-muted"><?php echo $ultimoLogin; ?></span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-circle text-<?php echo $usuarioActual["estado"] == 1 ? "success" : "danger"; ?> me-2"></i>
                        <b>Estado</b>
                        <span class="float-end">
                            <?php if($usuarioActual["estado"] == 1): ?>
                                <span class="badge text-bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">Inactivo</span>
                            <?php endif; ?>
                        </span>
                    </li>
                </ul>

                <!-- ─────── BOTONES DE ACCIÓN ─────── -->
                <div class="d-grid gap-2">
                    <button type="button"
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#modalEditarPerfil">
                        <i class="fas fa-user-edit me-1"></i> Editar Perfil
                    </button>
                    <a href="salir" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>

        <!-- ═══════ CARD: Coordinaciones, Roles y Entidades ═══════ -->
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-id-badge me-2"></i> Asignaciones
                </h3>
            </div>
            <div class="card-body">

                <!-- ───── Coordinaciones ───── -->
                <div class="perfil-section-title">
                    <i class="fas fa-sitemap me-2 text-primary"></i>
                    Coordinaciones
                    <?php if(!empty($coordsData)): ?>
                        <span class="badge text-bg-primary ms-2"><?php echo count($coordsData); ?></span>
                    <?php endif; ?>
                </div>
                <?php if(!empty($coordsData)): ?>
                    <div>
                        <?php foreach($coordsData as $c): ?>
                            <?php echo perfilRenderBadge($c["nombre"], $c["color"], $c["estado"], 'fa-sitemap'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i> Sin coordinaciones asignadas</p>
                <?php endif; ?>

                <!-- ───── Roles ───── -->
                <div class="perfil-section-title">
                    <i class="fas fa-user-tag me-2 text-success"></i>
                    Roles
                    <?php if(!empty($rolesData)): ?>
                        <span class="badge text-bg-success ms-2"><?php echo count($rolesData); ?></span>
                    <?php endif; ?>
                </div>
                <?php if(!empty($rolesData)): ?>
                    <div>
                        <?php foreach($rolesData as $r): ?>
                            <?php echo perfilRenderBadge($r["nombre"], $r["color"], $r["estado"], 'fa-user-tag'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i> Sin roles asignados</p>
                <?php endif; ?>

                <!-- ───── Entidades (NUEVO) ───── -->
                <div class="perfil-section-title">
                    <i class="fas fa-city me-2 text-warning"></i>
                    Entidades
                    <?php if(!empty($entidadesData)): ?>
                        <span class="badge text-bg-warning ms-2"><?php echo count($entidadesData); ?></span>
                    <?php endif; ?>
                </div>
                <?php if(!empty($entidadesData)): ?>
                    <div>
                        <?php foreach($entidadesData as $e): ?>
                            <?php echo perfilRenderBadge($e["nombre"], $e["color"], $e["estado"], 'fa-city'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i> Sin entidades asignadas</p>
                <?php endif; ?>

            </div>
        </div>

    </div>

    <!-- ─────────── COLUMNA DERECHA: Cambiar contraseña ─────────── -->
    <div class="col-md-8">

        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-key me-2"></i> Cambiar Contraseña
                </h3>
            </div>

            <div class="card-body">
                <form id="formCambiarPassword" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-lock me-1 text-muted"></i> Contraseña actual
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   class="form-control"
                                   name="passwordActual"
                                   id="passwordActual"
                                   placeholder="Ingresa tu contraseña actual"
                                   required>
                            <button type="button" class="btn btn-outline-secondary togglePassword" data-target="passwordActual">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-key me-1 text-muted"></i> Nueva contraseña
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   class="form-control"
                                   name="nuevaPassword"
                                   id="nuevaPassword"
                                   placeholder="Solo letras y números, mín. 4 caracteres"
                                   required>
                            <button type="button" class="btn btn-outline-secondary togglePassword" data-target="nuevaPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="progress mt-2" style="height:6px;">
                            <div id="passwordStrengthBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                        </div>
                        <small id="passwordStrengthText" class="text-muted"></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-check-double me-1 text-muted"></i> Confirmar nueva contraseña
                        </label>
                        <div class="input-group">
                            <input type="password"
                                   class="form-control"
                                   name="confirmarPassword"
                                   id="confirmarPassword"
                                   placeholder="Repite la nueva contraseña"
                                   required>
                            <button type="button" class="btn btn-outline-secondary togglePassword" data-target="confirmarPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small id="passwordMatchText" class="text-muted"></small>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo me-1"></i> Limpiar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Actualizar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Card informativa -->
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle me-2"></i> Información
                </h3>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>La nueva contraseña debe tener <strong>al menos 4 caracteres</strong>.</li>
                    <li>Solo se permiten <strong>letras y números</strong> (sin espacios ni caracteres especiales).</li>
                    <li>Después de cambiarla, deberás usar la nueva en tu próximo inicio de sesión.</li>
                    <li>Las contraseñas se guardan <strong>encriptadas</strong> con bcrypt en la base de datos.</li>
                    <li>Las <strong>asignaciones</strong> de coordinación, rol y entidad solo pueden ser modificadas por un administrador desde la sección <a href="user">Usuarios</a>.</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Editar Perfil (name, email, phone)
     ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarPerfil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarPerfil" autocomplete="off">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i> Editar Perfil
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-user text-muted me-1"></i> Usuario
                        </label>
                        <input type="text"
                               class="form-control"
                               value="<?php echo htmlspecialchars($usuarioActual["user"]); ?>"
                               disabled
                               readonly>
                        <small class="text-muted">El nombre de usuario no se puede cambiar.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-id-card text-muted me-1"></i> Nombre completo
                        </label>
                        <input type="text"
                               class="form-control"
                               name="perfilName"
                               id="perfilName"
                               value="<?php echo htmlspecialchars($usuarioActual["name"] ?? ''); ?>"
                               placeholder="Ej: Juan Pérez"
                               maxlength="150">
                        <small class="text-muted">Opcional. Solo letras, espacios, tildes y guiones.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-envelope text-muted me-1"></i> Email <span class="text-danger">*</span>
                        </label>
                        <input type="email"
                               class="form-control"
                               name="perfilEmail"
                               id="perfilEmail"
                               value="<?php echo htmlspecialchars($usuarioActual["email"] ?? ''); ?>"
                               placeholder="correo@ejemplo.com"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-phone text-muted me-1"></i> Teléfono
                        </label>
                        <input type="text"
                               class="form-control"
                               name="perfilPhone"
                               id="perfilPhone"
                               value="<?php echo htmlspecialchars($usuarioActual["phone"] ?? ''); ?>"
                               placeholder="+58 000-0000000"
                               maxlength="25">
                        <small class="text-muted">Opcional. Números, +, espacios, guiones y paréntesis.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     SCRIPTS DE LA VISTA
     ═══════════════════════════════════════════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ────────────────────────────────────────────────────────
    // 1. Mostrar/Ocultar contraseña (ojo)
    // ────────────────────────────────────────────────────────
    document.querySelectorAll('.togglePassword').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if(input.type === 'password'){
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }else{
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // ────────────────────────────────────────────────────────
    // 2. Medidor de fuerza de contraseña
    // ────────────────────────────────────────────────────────
    const nuevaPassword = document.getElementById('nuevaPassword');
    const barra = document.getElementById('passwordStrengthBar');
    const texto = document.getElementById('passwordStrengthText');

    if(nuevaPassword){
        nuevaPassword.addEventListener('input', function() {
            const valor = this.value;
            let fuerza = 0;

            if(valor.length >= 4) fuerza++;
            if(valor.length >= 8) fuerza++;
            if(/[A-Z]/.test(valor)) fuerza++;
            if(/[0-9]/.test(valor)) fuerza++;
            if(/[^a-zA-Z0-9]/.test(valor)) fuerza++;

            barra.className = 'progress-bar';
            barra.style.width = (fuerza * 20) + '%';

            const niveles = [
                { txt: '', color: '' },
                { txt: 'Muy débil', color: 'bg-danger' },
                { txt: 'Débil', color: 'bg-danger' },
                { txt: 'Aceptable', color: 'bg-warning' },
                { txt: 'Fuerte', color: 'bg-info' },
                { txt: 'Muy fuerte', color: 'bg-success' }
            ];

            if(fuerza > 0){
                barra.classList.add(niveles[fuerza].color);
                texto.textContent = 'Seguridad: ' + niveles[fuerza].txt;
                texto.className = 'text-muted';
            }else{
                barra.style.width = '0%';
                texto.textContent = '';
            }
        });
    }

    // ────────────────────────────────────────────────────────
    // 3. Verificar que las contraseñas coincidan
    // ────────────────────────────────────────────────────────
    const confirmar = document.getElementById('confirmarPassword');
    const matchText = document.getElementById('passwordMatchText');

    function verificarCoincidencia(){
        if(!confirmar || !nuevaPassword) return;
        if(confirmar.value === ''){
            matchText.textContent = '';
            return;
        }
        if(confirmar.value === nuevaPassword.value){
            matchText.textContent = '✅ Las contraseñas coinciden';
            matchText.className = 'text-success';
        }else{
            matchText.textContent = '❌ Las contraseñas no coinciden';
            matchText.className = 'text-danger';
        }
    }

    if(confirmar) confirmar.addEventListener('input', verificarCoincidencia);
    if(nuevaPassword) nuevaPassword.addEventListener('input', verificarCoincidencia);

    // ────────────────────────────────────────────────────────
    // 4. Enviar formulario CAMBIAR CONTRASEÑA vía AJAX
    // ────────────────────────────────────────────────────────
    const form = document.getElementById('formCambiarPassword');

    if(form){
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const passwordActual = document.getElementById('passwordActual').value.trim();
            const nueva = nuevaPassword.value.trim();
            const confirma = confirmar.value.trim();

            if(!passwordActual || !nueva || !confirma){
                Swal.fire({ icon: 'warning', title: 'Campos incompletos', text: 'Todos los campos son obligatorios.' });
                return;
            }
            if(nueva !== confirma){
                Swal.fire({ icon: 'error', title: 'No coinciden', text: 'La nueva contraseña y la confirmación no coinciden.' });
                return;
            }
            if(nueva.length < 4){
                Swal.fire({ icon: 'error', title: 'Contraseña muy corta', text: 'Debe tener al menos 4 caracteres.' });
                return;
            }
            if(!/^[a-zA-Z0-9]+$/.test(nueva)){
                Swal.fire({ icon: 'error', title: 'Caracteres inválidos', text: 'Solo se permiten letras y números.' });
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Actualizando...';

            const datos = new FormData();
            datos.append('cambiarPassword', '1');
            datos.append('passwordActual', passwordActual);
            datos.append('nuevaPassword', nueva);
            datos.append('confirmarPassword', confirma);

            fetch('ajax/user.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'success'){
                        Swal.fire({
                            icon: 'success',
                            title: '¡Contraseña actualizada!',
                            text: respuesta.mensaje,
                            confirmButtonText: 'Entendido'
                        });
                        form.reset();
                        barra.style.width = '0%';
                        texto.textContent = '';
                        matchText.textContent = '';
                    }else{
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: respuesta.mensaje || 'No se pudo actualizar la contraseña.'
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Intenta de nuevo.' });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHTML;
                });
        });
    }

    // ────────────────────────────────────────────────────────
    // 5. Enviar formulario EDITAR PERFIL vía AJAX
    // ────────────────────────────────────────────────────────
    const formPerfil = document.getElementById('formEditarPerfil');

    if(formPerfil){
        formPerfil.addEventListener('submit', function(e) {
            e.preventDefault();

            const name  = document.getElementById('perfilName').value.trim();
            const email = document.getElementById('perfilEmail').value.trim();
            const phone = document.getElementById('perfilPhone').value.trim();

            // Validaciones cliente
            if(email === ''){
                Swal.fire({ icon: 'warning', title: 'Email obligatorio', text: 'El correo electrónico es obligatorio.' });
                return;
            }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if(!emailRegex.test(email)){
                Swal.fire({ icon: 'error', title: 'Email inválido', text: 'Ingresa un correo electrónico válido.' });
                return;
            }
            if(name !== '' && !/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-\']+$/.test(name)){
                Swal.fire({ icon: 'error', title: 'Nombre inválido', text: 'El nombre solo puede contener letras, espacios y guiones.' });
                return;
            }
            if(phone !== '' && !/^[0-9\+\s\-\(\)]{6,25}$/.test(phone)){
                Swal.fire({ icon: 'error', title: 'Teléfono inválido', text: 'Revisa el formato del teléfono.' });
                return;
            }

            const submitBtn = formPerfil.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';

            const datos = new FormData();
            datos.append('editarPerfil', '1');
            datos.append('perfilName',  name);
            datos.append('perfilEmail', email);
            datos.append('perfilPhone', phone);

            fetch('ajax/user.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'success'){
                        // Cerrar modal
                        const modalEl = document.getElementById('modalEditarPerfil');
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if(modalInstance) modalInstance.hide();

                        Swal.fire({
                            icon: 'success',
                            title: '¡Perfil actualizado!',
                            text: respuesta.mensaje,
                            confirmButtonText: 'Entendido'
                        }).then(() => {
                            // Recargar para reflejar los nuevos datos
                            location.reload();
                        });
                    }else{
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: respuesta.mensaje || 'No se pudo actualizar el perfil.'
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Intenta de nuevo.' });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHTML;
                });
        });
    }

});
</script>