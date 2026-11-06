<?php
/*=============================================
sidebar.php  —  versión optimizada
  ✔ Carga auth UNA sola vez (caché estático de authTicket)
    → ~90 consultas SQL  →  3 consultas
  ✔ Evita la transición de AdminLTE en el primer paint
  ✔ Oculta secciones vacías
=============================================*/

$rutaActual = isset($_GET["ruta"]) ? $_GET["ruta"] : "inicio";

/* Helper: ' active' si la ruta actual está en la lista */
if (!function_exists('sidebarActivo')) {
    function sidebarActivo($rutas, $rutaActual) {
        $rutas = is_array($rutas) ? $rutas : [$rutas];
        return in_array($rutaActual, $rutas, true) ? ' active' : '';
    }
}

/*=============================================
✅ Auth UNA sola vez (caché estático)
   Este objeto ya trae:
     - rolesNombres  (minúsculas, solo activos)
     - coordsNombres (minúsculas, solo activas)
   Reutilizarlo evita que cada guard recargue
   user + roles + coordinaciones por su cuenta.
=============================================*/
$sbAuth   = ControladorTicket::authTicket();
$sbRoles  = $sbAuth['rolesNombres']  ?? [];
$sbCoords = $sbAuth['coordsNombres'] ?? [];

/* Sin sesión válida: no renderizar sidebar */
if (!$sbAuth) {
    return;
}

$sbEsAdmin = in_array('administrador', $sbRoles, true);

/* Helpers locales sobre los datos cacheados */
$sbCoordContiene = function (string $needle) use ($sbCoords): bool {
    foreach ($sbCoords as $c) {
        if (strpos($c, $needle) !== false) return true;
    }
    return false;
};
$sbTieneRol = function (array $roles) use ($sbRoles): bool {
    foreach ($roles as $r) {
        if (in_array(mb_strtolower($r), $sbRoles, true)) return true;
    }
    return false;
};

/*=============================================
✅ Guards equivalentes a los Controlador::puedeVerModuloX()
   (mismo resultado, sin recargar la BD)
=============================================*/

/* Tickets: Admin | Coordinador | Usuario | Especialista/Técnico */
$sbVerTickets = $sbTieneRol([
    'Administrador', 'Coordinador', 'Usuario', 'Especialista', 'Tecnico', 'Técnico'
]);

/* Actividad: Admin | Coordinador | Especialista/Técnico */
$sbVerActividad = $sbTieneRol([
    'Administrador', 'Coordinador', 'Especialista', 'Tecnico', 'Técnico'
]);

/* Users: Admin | Centro de Atención | Coordinador | Especialista */
$sbVerUsers = $sbEsAdmin
           || $sbCoordContiene('centro de atenci')
           || $sbTieneRol(['Coordinador', 'Especialista', 'Tecnico', 'Técnico']);

/* Hosts / Host-tipo: Admin | Soporte | Centro de Atención */
$sbVerHosts = $sbEsAdmin
           || in_array('soporte', $sbCoords, true)
           || $sbCoordContiene('centro de atenci');

$sbVerHostTipo = $sbVerHosts;

/* Doc: Admin | Archivo|Planificaci|Administraci|Bienes|Centro atenci|Despacho */
$sbVerDoc = $sbEsAdmin
         || $sbCoordContiene('archivo')
         || $sbCoordContiene('planificaci')
         || $sbCoordContiene('administraci')
         || $sbCoordContiene('bienes')
         || $sbCoordContiene('centro de atenci')
         || $sbCoordContiene('despacho');

/* Catálogos: Admin | Centro de Atención | Despacho */
$sbVerCatalogos = $sbEsAdmin
               || $sbCoordContiene('centro de atenci')
               || in_array('despacho', $sbCoords, true);

$sbVerTag         = $sbVerCatalogos;
$sbVerMedio       = $sbVerCatalogos;
$sbVerPrioridad   = $sbVerCatalogos;
$sbVerStatus      = $sbVerCatalogos;
$sbVerEntidad     = $sbVerCatalogos;
$sbVerDocTipo     = $sbVerCatalogos;
$sbVerTipoEntidad = $sbVerCatalogos;

/* Visibilidad de secciones (oculta headers vacíos) */
$sbShowOrganizacion    = ($sbVerUsers || $sbEsAdmin);
$sbShowInfraestructura = (bool)$sbVerHosts;
$sbShowDocumentos      = (bool)$sbVerDoc;
$sbShowCatalogos       = ($sbVerTag || $sbVerMedio || $sbVerPrioridad || $sbVerStatus || $sbVerEntidad);
$sbShowAjustes         = ($sbVerHostTipo || $sbVerDocTipo || $sbVerTipoEntidad);
?>

<!-- ═══════════════════════════════════════════════════════
     Anti-flicker: deshabilitar transiciones del sidebar
     durante el primer paint (AdminLTE 4 anima el sidebar
     al aplicar sidebar-collapse/etc. vía JS después del
     DOMContentLoaded). Se quitan ~100 ms tras window.load.
     ═══════════════════════════════════════════════════════ -->
<script>
(function () {
    try {
        if (document.body) {
            document.body.classList.add('sb-no-anim');
        } else {
            document.addEventListener('DOMContentLoaded', function () {
                document.body.classList.add('sb-no-anim');
            });
        }
        var remove = function () {
            setTimeout(function () {
                if (document.body) document.body.classList.remove('sb-no-anim');
            }, 100);
        };
        if (document.readyState === 'complete') remove();
        else window.addEventListener('load', remove, { once: true });
    } catch (e) { /* noop */ }
})();
</script>
<style>
    body.sb-no-anim .app-sidebar,
    body.sb-no-anim .app-sidebar *,
    body.sb-no-anim .app-wrapper,
    body.sb-no-anim .app-main,
    body.sb-no-anim .app-header,
    body.sb-no-anim .app-footer {
        transition: none !important;
        animation: none !important;
    }
</style>

<style>
    /* ─── Section headers del sidebar ─── */
    .sidebar-menu .nav-header {
        font-size: 0.70rem;
        letter-spacing: 1.2px;
        font-weight: 700;
        opacity: 0.55;
        padding: 14px 1rem 6px 1rem;
        text-transform: uppercase;
    }
    .sidebar-menu .nav-header:first-of-type {
        padding-top: 6px;
    }

    /* ─── Link activo ─── */
    .sidebar-menu .nav-link.active {
        background-color: rgba(13, 110, 253, 0.18) !important;
        border-left: 3px solid #0d6efd;
        font-weight: 600;
        padding-left: calc(1rem - 3px);
    }

    /* ─── Hover sutil ─── */
    .sidebar-menu .nav-link:not(.active):hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .login-logo-img {
        max-height: 50px;
        width: auto;
        display: block;
        margin: 0 auto;
    }
</style>

<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">

    <!-- ═══ Brand ═══ -->
    <div class="sidebar-brand">
        <a href="#" class="login-brand">
            <img src="vistas/img/plantilla/dark-mode-logoGOB.png" alt="MiPanel" class="login-logo-img">
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation">

                <!-- ═══════════ PRINCIPAL ═══════════ -->
                <li class="nav-header">Principal</li>

                <li class="nav-item">
                    <a href="inicio" class="nav-link<?php echo sidebarActivo('inicio', $rutaActual); ?>">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Inicio</p>
                    </a>
                </li>

                <?php if ($sbVerTickets): ?>
                <li class="nav-item">
                    <a href="tickets" class="nav-link<?php echo sidebarActivo(['tickets','ticket'], $rutaActual); ?>">
                        <i class="nav-icon fas fa-ticket-alt"></i>
                        <p>Tickets</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($sbVerActividad): ?>
                <li class="nav-item">
                    <a href="actividad" class="nav-link<?php echo sidebarActivo('actividad', $rutaActual); ?>">
                        <i class="nav-icon fas fa-clipboard-list"></i>
                        <p>Actividad</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- ═══════════ ORGANIZACIÓN ═══════════ -->
                <?php if ($sbShowOrganizacion): ?>
                <li class="nav-header">Organización</li>

                <?php if ($sbVerUsers): ?>
                <li class="nav-item">
                    <a href="users" class="nav-link<?php echo sidebarActivo(['users','user'], $rutaActual); ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Usuarios</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($sbEsAdmin): ?>
                <li class="nav-item">
                    <a href="coordinacion" class="nav-link<?php echo sidebarActivo('coordinacion', $rutaActual); ?>">
                        <i class="nav-icon fas fa-sitemap"></i>
                        <p>Coordinación</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="rol" class="nav-link<?php echo sidebarActivo('rol', $rutaActual); ?>">
                        <i class="nav-icon fas fa-user-tag"></i>
                        <p>Roles</p>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- ═══════════ INFRAESTRUCTURA ═══════════ -->
                <?php if ($sbShowInfraestructura): ?>
                <li class="nav-header">Infraestructura</li>

                <li class="nav-item">
                    <a href="hosts" class="nav-link<?php echo sidebarActivo(['hosts','host'], $rutaActual); ?>">
                        <i class="nav-icon fas fa-network-wired"></i>
                        <p>Hosts</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- ═══════════ DOCUMENTOS ═══════════ -->
                <?php if ($sbShowDocumentos): ?>
                <li class="nav-header">Documentos</li>

                <li class="nav-item">
                    <a href="doc" class="nav-link<?php echo sidebarActivo('doc', $rutaActual); ?>">
                        <i class="nav-icon fas fa-file-contract"></i>
                        <p>Documentos</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- ═══════════ CATÁLOGOS ═══════════ -->
                <?php if ($sbShowCatalogos): ?>
                <li class="nav-header">Catálogos</li>

                <?php if ($sbVerTag): ?>
                <li class="nav-item">
                    <a href="tag" class="nav-link<?php echo sidebarActivo('tag', $rutaActual); ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Tags</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($sbVerMedio): ?>
                <li class="nav-item">
                    <a href="medio" class="nav-link<?php echo sidebarActivo('medio', $rutaActual); ?>">
                        <i class="nav-icon fas fa-broadcast-tower"></i>
                        <p>Medio</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($sbVerPrioridad): ?>
                <li class="nav-item">
                    <a href="prioridad" class="nav-link<?php echo sidebarActivo('prioridad', $rutaActual); ?>">
                        <i class="nav-icon fas fa-flag"></i>
                        <p>Prioridad</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($sbVerStatus): ?>
                <li class="nav-item">
                    <a href="status" class="nav-link<?php echo sidebarActivo('status', $rutaActual); ?>">
                        <i class="nav-icon fas fa-tasks"></i>
                        <p>Status</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($sbVerEntidad): ?>
                <li class="nav-item">
                    <a href="entidad" class="nav-link<?php echo sidebarActivo('entidad', $rutaActual); ?>">
                        <i class="nav-icon fas fa-city"></i>
                        <p>Entidades</p>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <!-- ═══════════ AJUSTES ═══════════ -->
                <?php if ($sbShowAjustes): ?>
                <li class="nav-header">Ajustes</li>

                <?php if ($sbVerHostTipo): ?>
                <li class="nav-item">
                    <a href="host-tipo" class="nav-link<?php echo sidebarActivo('host-tipo', $rutaActual); ?>">
                        <i class="nav-icon fas fa-server"></i>
                        <p>Tipo de Host</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($sbVerDocTipo): ?>
                <li class="nav-item">
                    <a href="doc-tipo" class="nav-link<?php echo sidebarActivo('doc-tipo', $rutaActual); ?>">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <p>Tipo de Documento</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($sbVerTipoEntidad): ?>
                <li class="nav-item">
                    <a href="tipoEntidad" class="nav-link<?php echo sidebarActivo('tipoEntidad', $rutaActual); ?>">
                        <i class="nav-icon fas fa-building"></i>
                        <p>Tipo de Entidad</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($sbEsAdmin): ?>       
                <li class="nav-item">
                    <a href="tag-tipo" class="nav-link<?php echo sidebarActivo('tag-tipo', $rutaActual); ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Tipo de Tag</p>
                    </a>
                </li>
                <?php endif; ?>               
                <?php endif; ?>

            </ul>
        </nav>
    </div>
</aside>