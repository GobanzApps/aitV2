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
// ** FIN **

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

require_once __DIR__ . "/../controladores/ticket.controlador.php";
require_once __DIR__ . "/../modelos/ticket.modelo.php";
require_once __DIR__ . "/../controladores/entidad.controlador.php";
require_once __DIR__ . "/../modelos/entidad.modelo.php";
require_once __DIR__ . "/../controladores/medio.controlador.php";
require_once __DIR__ . "/../modelos/medio.modelo.php";
require_once __DIR__ . "/../controladores/prioridad.controlador.php";
require_once __DIR__ . "/../modelos/prioridad.modelo.php";

// ✅ Necesarios para validar el último status en ctrCambiarCheckTicket
require_once __DIR__ . "/../controladores/tstatus.controlador.php";
require_once __DIR__ . "/../modelos/tstatus.modelo.php";
require_once __DIR__ . "/../controladores/status.controlador.php";
require_once __DIR__ . "/../modelos/status.modelo.php";

// ✅ NUEVO: necesarios para validar que exista al menos 1 servicio al finalizar
require_once __DIR__ . "/../controladores/tservicio.controlador.php";
require_once __DIR__ . "/../modelos/tservicio.modelo.php";

// ✅ Dependencias para la autorización (authTicket)
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

// ✅ NUEVO: dependencias para validar asignación por tuser / tcoordinacion
require_once __DIR__ . "/../modelos/tuser.modelo.php";
require_once __DIR__ . "/../modelos/tcoordinacion.modelo.php";

class AjaxTicket{

    /* Editar (traer datos para el modal) */
    public $idTicket;
    public function ajaxEditarTicket(){
        $item = "id";
        $valor = $this->idTicket;
        $respuesta = ControladorTicket::ctrMostrarTicket($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarTicket;
    public $activarId;
    public function ajaxActivarTicket(){
        $tabla = "ticket";
        $item1 = "estado";
        $valor1 = $this->activarTicket;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloTicket::mdlActualizarTicket($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
GUARDS DE AUTORIZACIÓN (blindaje backend)
=============================================*/
function guardModificarTicket(){
    if (!ControladorTicket::puedeModificarTickets()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No autorizado para realizar esta acción"
        ]);
    }
}
function guardVerSeccionTicket(){
    if (!ControladorTicket::puedeVerSeccionTickets()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No autorizado para acceder a esta sección"
        ]);
    }
}
function guardVerTicketEspecifico($idTicket){
    $autorizacion = ControladorTicket::puedeVerTicketEspecifico($idTicket);
    if (empty($autorizacion["ok"])) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => $autorizacion["msg"] ?? "No autorizado para ver este ticket"
        ]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR van PRIMERO
=============================================*/

/* CREAR TICKET */
if(isset($_POST["nuevoTicket"])){
    guardModificarTicket();
    ajaxResponder(ControladorTicket::ctrCrearTicket());
}

/* EDITAR TICKET (guardar cambios) */
if(isset($_POST["editarTicket"])){
    guardModificarTicket();
    $idT = intval($_POST["idTicket"] ?? 0);
    guardVerTicketEspecifico($idT);
    ajaxResponder(ControladorTicket::ctrEditarTicket());
}

/*=============================================
✅ EDITAR TICKET desde el DETALLE (ticket.php)
=============================================*/
if(isset($_POST["editarTicketDetalle"])){
    guardModificarTicket();
    $idT = intval($_POST["idTicket"] ?? 0);
    guardVerTicketEspecifico($idT);
    ajaxResponder(ControladorTicket::ctrEditarTicketDetalle());
}

/*=============================================
✅ CAMBIAR CHECK (Abierto / Finalizado)
=============================================*/
if(isset($_POST["cambiarCheckTicket"])){
    guardModificarTicket();
    $id    = intval($_POST["idTicket"] ?? 0);
    guardVerTicketEspecifico($id);
    $check = intval($_POST["nuevoCheck"] ?? 0);
    ajaxResponder(ControladorTicket::ctrCambiarCheckTicket($id, $check));
}

/*=============================================
LISTAR ENTIDADES ACTIVAS
=============================================*/
if(isset($_POST["listarEntidadesActivas"])){
    guardVerSeccionTicket();
    $entidades = ControladorEntidad::ctrMostrarEntidad(null, null);
    $resultado = [];
    if($entidades && is_array($entidades)){
        foreach($entidades as $e){
            if((int)$e["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$e["id"],
                    "texto" => $e["entidad"],
                    "color" => $e["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
LISTAR MEDIOS ACTIVOS
=============================================*/
if(isset($_POST["listarMediosActivos"])){
    guardVerSeccionTicket();
    $medios = ControladorMedio::ctrMostrarMedio(null, null);
    $resultado = [];
    if($medios && is_array($medios)){
        foreach($medios as $m){
            if((int)$m["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$m["id"],
                    "texto" => $m["medio"],
                    "color" => $m["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
LISTAR PRIORIDADES ACTIVAS
=============================================*/
if(isset($_POST["listarPrioridadesActivas"])){
    guardVerSeccionTicket();
    $prioridades = ControladorPrioridad::ctrMostrarPrioridad(null, null);
    $resultado = [];
    if($prioridades && is_array($prioridades)){
        foreach($prioridades as $p){
            if((int)$p["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$p["id"],
                    "texto" => $p["prioridad"],
                    "color" => $p["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
Demás handlers
=============================================*/

/* Editar (traer datos) */
if(isset($_POST["idTicket"])){
    guardModificarTicket();
    $idT = intval($_POST["idTicket"] ?? 0);
    guardVerTicketEspecifico($idT);
    $editar = new AjaxTicket();
    $editar->idTicket = $idT;
    $editar->ajaxEditarTicket();
}

/* Activar (cambiar estado) */
if(isset($_POST["activarTicket"])){
    guardModificarTicket();
    $activar = new AjaxTicket();
    $activar->activarTicket = $_POST["activarTicket"];
    $activar->activarId = $_POST["activarId"];
    $activar->ajaxActivarTicket();
}
?>