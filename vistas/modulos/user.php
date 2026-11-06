<?php
/*=============================================
user.php — Detalle del Usuario (solo lectura)
=============================================*/
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/* ─── 1) ID del usuario desde la URL ─── */
$idUser = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
if($idUser <= 0){
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>ID de usuario no válido.</div>';
    return;
}

/*=============================================
✅ GUARD: Admin | Centro de Atención (no admins) | Coordinador (misma coord)
=============================================*/
if (!ControladorUser::puedeVerUsuario($idUser)) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-shield-alt me-2"></i>
            <strong>Acceso denegado.</strong>
            No tienes permisos para ver el detalle de este usuario.
            <br><br>
            <a href="index.php?ruta=inicio" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
          </div>';
    return;
}

/* ─── 2) Datos del usuario ─── */
$usuario = ControladorUser::ctrMostrarUser("id", $idUser);
if(!$usuario){
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Usuario no encontrado.</div>';
    return;
}

/* ─── 3) Catálogos relacionados ─── */
$todosRoles  = ControladorRol::ctrMostrarRol(null, null) ?: [];
$mapaRoles   = [];
foreach($todosRoles as $r){ $mapaRoles[(int)$r["id"]] = $r; }

$todasCoords = ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: [];
$mapaCoords  = [];
foreach($todasCoords as $c){ $mapaCoords[(int)$c["id"]] = $c; }

$todasEnts   = ControladorEntidad::ctrMostrarEntidad(null, null) ?: [];
$mapaEnts    = [];
foreach($todasEnts as $e){ $mapaEnts[(int)$e["id"]] = $e; }

/* ─── 4) Variables de apoyo ─── */
$estadoActual  = (int)($usuario["estado"] ?? 1);
$nombreMostrar = !empty($usuario["name"]) ? $usuario["name"] : $usuario["user"];
$foto = !empty($usuario["foto"])
    ? $usuario["foto"]
    : "https://ui-avatars.com/api/?name=".urlencode($usuario["user"])."&background=0d6efd&color=fff&size=128";

$ultimoLogin = !empty($usuario["ultimo_login"])
    ? date("d/m/Y H:i", strtotime($usuario["ultimo_login"]))
    : "Nunca";

/* ─── 5) Helper para badges ─── */
if(!function_exists('userBadgesFromIds')){
    function userBadgesFromIds($jsonIds, $mapa, $campo, $icono){
        $ids = json_decode($jsonIds ?? '[]', true);
        if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

        $html = '';
        foreach($ids as $id){
            $id = (int)$id;
            if(isset($mapa[$id])){
                $item  = $mapa[$id];
                $color = $item["color"] ?: "#0d6efd";
                $html .= '<span class="badge me-1 mb-1" style="background-color:'.htmlspecialchars($color).'; color:#fff; padding:5px 10px; font-size:0.75rem;">'
                       . '<i class="fas '.$icono.' me-1"></i>'.htmlspecialchars($item[$campo]).'</span>';
            } else {
                $html .= '<span class="badge bg-secondary me-1 mb-1">ID '.$id.'</span>';
            }
        }
        return $html ?: '<span class="text-muted">—</span>';
    }
}
?>

<div class="row">
    <!-- ═══════════════ COLUMNA IZQUIERDA ═══════════════ -->
    <div class="col-md-4">

        <div class="card card-primary card-outline">
            <div class="card-body box-profile">

                <div class="text-center">
                    <img src="<?php echo htmlspecialchars($foto); ?>"
                         alt="Foto de <?php echo htmlspecialchars($usuario["user"]); ?>"
                         class="rounded-circle shadow"
                         style="width:120px; height:120px; object-fit:cover;"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($usuario['user']); ?>&background=0d6efd&color=fff&size=128';">
                </div>

                <h3 class="profile-username text-center mt-3">
                    <?php echo htmlspecialchars($nombreMostrar); ?>
                </h3>
                <p class="text-muted text-center">
                    <i class="fas fa-at me-1"></i> <?php echo htmlspecialchars($usuario["user"]); ?>
                </p>

                <ul class="list-group list-group-unbordered mb-3">

                    <li class="list-group-item">
                        <i class="fas fa-hashtag text-primary me-2"></i>
                        <b>ID</b>
                        <span class="float-end text-muted"><?php echo (int)$usuario["id"]; ?></span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-envelope text-primary me-2"></i>
                        <b>Email</b>
                        <span class="float-end text-muted">
                            <?php echo htmlspecialchars($usuario["email"] ?: "—"); ?>
                        </span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-phone text-primary me-2"></i>
                        <b>Teléfono</b>
                        <span class="float-end text-muted">
                            <?php echo htmlspecialchars($usuario["phone"] ?: "—"); ?>
                        </span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-sitemap text-primary me-2"></i>
                        <b>Coordinación(es)</b>
                        <div class="mt-1">
                            <?php echo userBadgesFromIds($usuario["id_coordinacion"] ?? '', $mapaCoords, "coordinacion", "fa-sitemap"); ?>
                        </div>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-user-tag text-primary me-2"></i>
                        <b>Rol(es)</b>
                        <div class="mt-1">
                            <?php echo userBadgesFromIds($usuario["id_rol"] ?? '', $mapaRoles, "rol", "fa-user-tag"); ?>
                        </div>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-city text-primary me-2"></i>
                        <b>Entidad(es)</b>
                        <div class="mt-1">
                            <?php echo userBadgesFromIds($usuario["id_entidad"] ?? '', $mapaEnts, "entidad", "fa-city"); ?>
                        </div>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-circle text-<?php echo $estadoActual === 1 ? 'success' : 'danger'; ?> me-2"></i>
                        <b>Estado</b>
                        <span class="float-end">
                            <?php if($estadoActual === 1): ?>
                                <span class="badge text-bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">Inactivo</span>
                            <?php endif; ?>
                        </span>
                    </li>

                    <li class="list-group-item">
                        <i class="fas fa-sign-in-alt text-primary me-2"></i>
                        <b>Último login</b>
                        <span class="float-end text-muted"><?php echo $ultimoLogin; ?></span>
                    </li>

                    <!-- ÚNICO BOTÓN: volver -->
                    <li class="list-group-item">
                        <div class="d-grid gap-2">
                            <?php if (ControladorUser::puedeVerListadoUsers()): ?>
                                <a href="index.php?ruta=users" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Volver al listado
                                </a>
                            <?php else: ?>
                                <a href="index.php?ruta=inicio" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Volver al inicio
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>

                </ul>
            </div>
        </div>

    </div>

    <!-- ═══════════════ COLUMNA DERECHA ═══════════════ -->
    <div class="col-md-8">
        <div class="card card-primary card-outline">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="user-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="servicios-tab" data-bs-toggle="tab" data-bs-target="#servicios" type="button" role="tab">
                            <i class="fas fa-tools me-1"></i> Servicios
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" role="tab">
                            <i class="fas fa-folder me-1"></i> Tabs 2
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content">

                    <div class="tab-pane fade show active" id="servicios" role="tabpanel">
                        <?php include "user/uservicio.php"; ?>
                    </div>

                    <div class="tab-pane fade" id="tab2" role="tabpanel">
                        <div class="alert alert-secondary d-flex align-items-center mb-0">
                            <i class="fas fa-folder-open me-2"></i>
                            <div>Contenido pendiente en esta pestaña.</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>