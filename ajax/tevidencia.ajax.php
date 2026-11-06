<?php
// ** Buffer + errores silenciados **
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING & ~E_STRICT);
ini_set('display_errors', 0);
ob_start();

// ** SESIÓN **
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok") {
    if (ob_get_length()) ob_clean();
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(["error" => "Acceso no autorizado"]);
    exit();
}

register_shutdown_function(function() {
    $err = error_get_last();
    if($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            "status"  => "error",
            "mensaje" => "Error interno del servidor: " . $err['message']
        ]);
    }
});

function ajaxResponder($data){
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/*=============================================
Dependencias del módulo
=============================================*/
require_once __DIR__ . "/../controladores/tevidencia.controlador.php";
require_once __DIR__ . "/../modelos/tevidencia.modelo.php";

/*=============================================
✅ Dependencias para check + permisos del ticket
=============================================*/
require_once __DIR__ . "/../modelos/ticket.modelo.php";
require_once __DIR__ . "/../controladores/ticket.controlador.php";
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";
require_once __DIR__ . "/../modelos/tuser.modelo.php";
require_once __DIR__ . "/../modelos/tcoordinacion.modelo.php";

/*=============================================
GUARDS DE AUTORIZACIÓN (ticket finalizado + permisos)
=============================================*/

function tevTicketFinalizado($idTicket){
    $idTicket = intval($idTicket);
    if ($idTicket <= 0) return false;

    $ticket = ModeloTicket::mdlMostrarTicket("ticket", "id", $idTicket);
    return $ticket && (int)($ticket["check"] ?? 0) === 1;
}

/**
 * Regla 4:
 *  - Administrador                     → SIEMPRE puede
 *  - Coordinador / Especialista/Técnico ASIGNADO al ticket → puede
 *  - Cualquier otro caso               → NO puede
 */
function tevPuedeModificarEvidencias($idTicket){
    $idTicket = intval($idTicket);
    if ($idTicket <= 0) return false;

    $auth = ControladorTicket::authTicket();
    if (!$auth) return false;

    if (ControladorTicket::esAdminTicket($auth)) return true;

    $rolPermitido = ControladorTicket::esCoordinadorTicket($auth)
                 || ControladorTicket::esEspecialistaTicket($auth);
    if (!$rolPermitido) return false;

    return ControladorTicket::estaAsignadoAlTicket($idTicket, $auth);
}

function tevGuardModificar($idTicket, $accion = 'modificar'){
    if (tevTicketFinalizado($idTicket)) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "El ticket está finalizado. No se puede " . $accion . " evidencias."
        ]);
    }
    if (!tevPuedeModificarEvidencias($idTicket)) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No tienes permisos para " . $accion . " evidencias. Solo Administrador o usuarios asignados con rol Coordinador/Especialista pueden hacerlo."
        ]);
    }
}

/*=============================================
⚠️ CREAR
=============================================*/
if(isset($_POST["nuevoTevidencia"])){

    $idTicket = intval($_POST["idTicket"] ?? 0);
    tevGuardModificar($idTicket, 'agregar');

    ajaxResponder(ControladorTevidencia::ctrCrearTevidencia());
}

/*=============================================
⚠️ BORRAR
=============================================*/
if(isset($_POST["borrarTevidencia"])){

    $id = intval($_POST["idTevidencia"] ?? 0);
    if($id <= 0){
        ajaxResponder(["status" => "error", "mensaje" => "ID inválido"]);
    }

    $registro = ModeloTevidencia::mdlMostrarTevidenciaUno("tevidencia", "id", $id);
    if($registro){
        tevGuardModificar(intval($registro["id_ticket"] ?? 0), 'eliminar');
    }

    ajaxResponder(ControladorTevidencia::ctrBorrarTevidencia($id));
}

/*=============================================
MOSTRAR UNO (solo lectura → sin guard)
=============================================*/
if(isset($_POST["idTevidencia"])){
    $id = intval($_POST["idTevidencia"]);
    $fila = ControladorTevidencia::ctrMostrarTevidenciaUno($id);
    ajaxResponder($fila ?: null);
}

ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);