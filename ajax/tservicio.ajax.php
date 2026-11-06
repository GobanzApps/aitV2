<?php
// ** INICIO: Buffer + errores silenciados **
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING & ~E_STRICT);
ini_set('display_errors', 0);
ob_start();

// ** VERIFICACIÓN DE SESIÓN **
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok") {
    if (ob_get_length()) ob_clean();
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(["error" => "Acceso no autorizado"]);
    exit();
}

// Handler de errores fatales → devuelve JSON
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

// Helper para responder siempre JSON limpio
function ajaxResponder($data){
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/*=============================================
Dependencias del módulo
=============================================*/
require_once __DIR__ . "/../controladores/tservicio.controlador.php";
require_once __DIR__ . "/../modelos/tservicio.modelo.php";
require_once __DIR__ . "/../controladores/tag.controlador.php";
require_once __DIR__ . "/../modelos/tag.modelo.php";
require_once __DIR__ . "/../controladores/host.controlador.php";
require_once __DIR__ . "/../modelos/host.modelo.php";

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

/* ✅ NUEVO: necesarios para validar el último status del ticket */
require_once __DIR__ . "/../modelos/tstatus.modelo.php";
require_once __DIR__ . "/../modelos/status.modelo.php";

/*=============================================
GUARDS DE AUTORIZACIÓN (ticket finalizado + permisos)
=============================================*/

/**
 * Devuelve true si el ticket tiene check = 1 (finalizado).
 */
function tsvTicketFinalizado($idTicket){
    $idTicket = intval($idTicket);
    if ($idTicket <= 0) return false;

    $ticket = ModeloTicket::mdlMostrarTicket("ticket", "id", $idTicket);
    return $ticket && (int)($ticket["check"] ?? 0) === 1;
}

/**
 * Regla 3:
 *  - Administrador                     → SIEMPRE puede
 *  - Coordinador / Especialista/Técnico ASIGNADO al ticket → puede
 *  - Cualquier otro caso               → NO puede
 */
function tsvPuedeModificarServicios($idTicket){
    $idTicket = intval($idTicket);
    if ($idTicket <= 0) return false;

    $auth = ControladorTicket::authTicket();
    if (!$auth) return false;

    // 1) Administrador → bypass
    if (ControladorTicket::esAdminTicket($auth)) return true;

    // 2) Debe tener rol Coordinador o Especialista/Técnico
    $rolPermitido = ControladorTicket::esCoordinadorTicket($auth)
                 || ControladorTicket::esEspecialistaTicket($auth);
    if (!$rolPermitido) return false;

    // 3) Y debe estar asignado al ticket vía tuser
    return ControladorTicket::estaAsignadoAlTicket($idTicket, $auth);
}

/**
 * Corta la ejecución si:
 *  - el ticket está finalizado (regla 1)   → mensaje "finalizado"
 *  - el usuario no tiene permiso (regla 3) → mensaje "sin permisos"
 */
function tsvGuardModificar($idTicket, $accion = 'modificar'){
    if (tsvTicketFinalizado($idTicket)) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "El ticket está finalizado. No se puede " . $accion . " servicios."
        ]);
    }
    if (!tsvPuedeModificarServicios($idTicket)) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No tienes permisos para " . $accion . " servicios. Solo Administrador o usuarios asignados con rol Coordinador/Especialista pueden hacerlo."
        ]);
    }
}

/*=============================================
✅ NUEVO: Guard — el último status del ticket debe ser "En Proceso"
   (solo se aplica al CREAR servicios, no al editar/eliminar)
=============================================*/

/**
 * Devuelve el nombre del último status del ticket, o null si no hay ninguno.
 * (ModeloTstatus::mdlMostrarTstatus filtra por id_ticket con ORDER BY id DESC,
 *  por lo que [0] es siempre el más reciente.)
 */
function tsvUltimoStatusNombre($idTicket){
    $idTicket = intval($idTicket);
    if ($idTicket <= 0) return null;

    $hist = ModeloTstatus::mdlMostrarTstatus("tstatus", "id_ticket", $idTicket);
    if (!$hist || !is_array($hist) || count($hist) === 0) return null;

    $ultimo = $hist[0];
    $idSt   = (int)($ultimo["id_status"] ?? 0);
    if ($idSt <= 0) return null;

    $st = ModeloStatus::mdlMostrarStatus("status", "id", $idSt);
    return $st ? trim($st["status"]) : null;
}

/**
 * Corta la ejecución si el último status del ticket NO es "En Proceso".
 * Se invoca SOLO en el handler nuevoTservicio.
 */
function tsvGuardUltimoStatusEnProceso($idTicket){
    $ultimoNombre = tsvUltimoStatusNombre($idTicket);

    if ($ultimoNombre === null) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No se puede agregar servicios: el ticket aún no tiene ningún status registrado. " .
                         "Registra primero un status \"En Proceso\" en la pestaña Status."
        ]);
    }

    if (mb_strtolower($ultimoNombre) !== "en proceso") {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No se puede agregar servicios: el último status del ticket es \"" .
                         $ultimoNombre . "\". Debe estar en \"En Proceso\"."
        ]);
    }
}

/*=============================================
⚠️ CREAR
=============================================*/
if(isset($_POST["nuevoTservicio"])){

    $idTicket = intval($_POST["idTicket"] ?? 0);
    tsvGuardModificar($idTicket, 'agregar');

    /* ✅ NUEVO: exigir último status = "En Proceso" */
    tsvGuardUltimoStatusEnProceso($idTicket);

    ajaxResponder(ControladorTservicio::ctrCrearTservicio());
}

/*=============================================
⚠️ EDITAR
=============================================*/
if(isset($_POST["editarTservicio"])){

    $id = intval($_POST["idTservicio"] ?? 0);
    if($id <= 0){
        ajaxResponder(["status" => "error", "mensaje" => "ID inválido"]);
    }

    $registro = ModeloTservicio::mdlMostrarTservicioUno("tservicio", "id", $id);
    if(!$registro){
        ajaxResponder(["status" => "error", "mensaje" => "Registro no encontrado"]);
    }

    tsvGuardModificar(intval($registro["id_ticket"] ?? 0), 'editar');

    ajaxResponder(ControladorTservicio::ctrEditarTservicio());
}

/*=============================================
⚠️ BORRAR
=============================================*/
if(isset($_POST["borrarTservicio"])){

    $id = intval($_POST["idTservicio"] ?? 0);
    if($id <= 0){
        ajaxResponder(["status" => "error", "mensaje" => "ID inválido"]);
    }

    $registro = ModeloTservicio::mdlMostrarTservicioUno("tservicio", "id", $id);
    if($registro){
        tsvGuardModificar(intval($registro["id_ticket"] ?? 0), 'eliminar');
    }

    $r = ControladorTservicio::ctrBorrarTservicio($id);
    if($r === "ok"){
        ajaxResponder(["status" => "success", "mensaje" => "¡Registro eliminado correctamente!"]);
    }
    ajaxResponder(["status" => "error", "mensaje" => "No se pudo eliminar"]);
}

/*=============================================
NUEVO: Listar tags activos (multi-select)
=============================================*/
if(isset($_POST["listarTagsActivos"])){
    $tags = ControladorTag::ctrMostrarTag(null, null);
    $resultado = [];
    if($tags && is_array($tags)){
        foreach($tags as $t){
            if((int)$t["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$t["id"],
                    "texto" => $t["tag"],
                    "color" => $t["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
NUEVO: Listar hosts activos (multi-select)
=============================================*/
if(isset($_POST["listarHostsActivos"])){
    $hosts = ControladorHost::ctrMostrarHost(null, null);
    $resultado = [];
    if($hosts && is_array($hosts)){
        foreach($hosts as $h){
            if((int)$h["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$h["id"],
                    "texto" => $h["host"],
                    "color" => $h["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
TRAER UNO (para el modal de edición)
=============================================*/
if(isset($_POST["idTservicio"])){
    $id = intval($_POST["idTservicio"]);
    $fila = ControladorTservicio::ctrMostrarTservicioUno($id);
    if($fila){
        ajaxResponder($fila);
    }
    ajaxResponder(null);
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);