<?php
/*=============================================
vistas/modulos/button.php

Botones de acceso rápido con control de permisos:
  • Nuevo Ticket     → Administrador  ó  coordinación "Centro de Atención"
  • Nueva Actividad  → Administrador, Coordinador o Especialista/Técnico
  • Nuevo Documento  → Administrador  ó  coordinaciones
                       Archivo / Administración / Planificación / Despacho
                       (NO incluye "Centro de Atención" ni "Bienes")
=============================================*/

// ── Dependencias (idempotentes) ──
if (!class_exists('ControladorTicket')) {
    require_once __DIR__ . "/../../controladores/ticket.controlador.php";
}
if (!class_exists('ControladorActividad')) {
    require_once __DIR__ . "/../../controladores/actividad.controlador.php";
}
if (!class_exists('ModeloUser')) {
    require_once __DIR__ . "/../../modelos/user.modelo.php";
}
if (!class_exists('ModeloRol')) {
    require_once __DIR__ . "/../../modelos/rol.modelo.php";
}
if (!class_exists('ModeloCoordinacion')) {
    require_once __DIR__ . "/../../modelos/coordinacion.modelo.php";
}

// ── Contexto de sesión / roles / coordinaciones ──
$authBtn = ControladorTicket::authTicket();

/*-------------------------------------------------
  1) NUEVO TICKET
     → Administrador  ó  coordinación "Centro de Atención"
-------------------------------------------------*/
$verNuevoTicket = $authBtn
    && ControladorTicket::puedeModificarTickets($authBtn);

/*-------------------------------------------------
  2) NUEVA ACTIVIDAD
     → Administrador, Coordinador
       o Especialista / Técnico
-------------------------------------------------*/
$verNuevaActividad = ControladorActividad::puedeVerModuloActividad();

/*-------------------------------------------------
  3) NUEVO DOCUMENTO
     → Administrador  ó  coordinaciones:
       Archivo, Administración, Planificación, Despacho
     ⚠️ No aplica para "Centro de Atención" ni "Bienes".
-------------------------------------------------*/
$verNuevoDocumento = false;
if ($authBtn) {
    if (ControladorTicket::esAdminTicket($authBtn)) {
        $verNuevoDocumento = true;
    } else {
        foreach ($authBtn['coordsNombres'] as $coordNombre) {
            $nombre = mb_strtolower(trim($coordNombre));
            if (strpos($nombre, 'archivo')      !== false
             || strpos($nombre, 'administraci') !== false
             || strpos($nombre, 'planificaci')  !== false
             || $nombre === 'despacho') {
                $verNuevoDocumento = true;
                break;
            }
        }
    }
}
?>
<br>

<div class="card">
    <div class="card-header">

        <?php if ($verNuevoTicket || $verNuevaActividad || $verNuevoDocumento): ?>
        <div class="d-flex flex-wrap gap-2 mb-3">

            <?php if ($verNuevoTicket): ?>
                <a href="index.php?ruta=tickets"
                class="btn btn-primary"
                title="Crear un nuevo ticket">
                    <i class="fas fa-plus-circle me-1"></i> Nuevo Ticket
                </a>
            <?php endif; ?>

            <?php if ($verNuevaActividad): ?>
                <a href="index.php?ruta=actividad"
                class="btn btn-success"
                title="Registrar una nueva actividad">
                    <i class="fas fa-plus-circle me-1"></i> Nueva Actividad
                </a>
            <?php endif; ?>

            <?php if ($verNuevoDocumento): ?>
                <a href="index.php?ruta=doc"
                class="btn btn-info"
                title="Subir un nuevo documento">
                    <i class="fas fa-plus-circle me-1"></i> Nuevo Documento
                </a>
            <?php endif; ?>

        </div>

    </div>
</div>

<br>

<?php endif; ?>