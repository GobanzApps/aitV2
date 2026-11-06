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

require_once __DIR__ . "/../controladores/doc.controlador.php";
require_once __DIR__ . "/../modelos/doc.modelo.php";
require_once __DIR__ . "/../controladores/doc_tipo.controlador.php";
require_once __DIR__ . "/../modelos/doc_tipo.modelo.php";
require_once __DIR__ . "/../controladores/ticket.controlador.php";
require_once __DIR__ . "/../modelos/ticket.modelo.php";
require_once __DIR__ . "/../controladores/entidad.controlador.php";
require_once __DIR__ . "/../modelos/entidad.modelo.php";

// ✅ Dependencias para ControladorTicket::authTicket()
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
✅ GUARDS DE AUTORIZACIÓN
=============================================*/

/**
 * Requiere que el usuario sea Administrador, coordinación Archivo
 * o coordinación Planificación.
 * Corta la ejecución con JSON de error si no cumple.
 */
function guardModificarDoc(){
    if (!ControladorDoc::puedeModificarDocs()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "Solo Administrador o usuarios con coordinación Archivo o Planificación pueden realizar esta acción"
        ]);
    }
}

class AjaxDoc{

    /* Editar (traer datos para el modal) */
    public $idDoc;
    public function ajaxEditarDoc(){
        $item = "id";
        $valor = $this->idDoc;
        $respuesta = ControladorDoc::ctrMostrarDoc($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarDoc;
    public $activarId;
    public function ajaxActivarDoc(){
        $tabla = "doc";
        $item1 = "estado";
        $valor1 = $this->activarDoc;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloDoc::mdlActualizarDoc($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — Admin / Archivo / Planificación
=============================================*/

if(isset($_POST["nuevoDoc"])){
    guardModificarDoc();
    $respuesta = ControladorDoc::ctrCrearDoc();
    ajaxResponder($respuesta);
}

if(isset($_POST["editarDoc"])){
    guardModificarDoc();
    $respuesta = ControladorDoc::ctrEditarDoc();
    ajaxResponder($respuesta);
}

/*=============================================
Listers de catálogos (usados por modales de crear/editar)
=============================================*/

if(isset($_POST["listarDocTiposActivos"])){
    guardModificarDoc();
    $tipos = ControladorDocTipo::ctrMostrarDocTipo(null, null);
    $resultado = [];
    if($tipos && is_array($tipos)){
        foreach($tipos as $t){
            if((int)$t["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$t["id"],
                    "texto" => $t["doc_tipo"],
                    "color" => $t["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

if(isset($_POST["listarTicketsActivos"])){
    guardModificarDoc();
    $tickets = ControladorTicket::ctrMostrarTicket(null, null);
    $resultado = [];
    if($tickets && is_array($tickets)){
        foreach($tickets as $t){
            if((int)$t["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$t["id"],
                    "texto" => "Ticket #" . $t["id"],
                    "color" => "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

if(isset($_POST["listarEntidadesActivas"])){
    guardModificarDoc();
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
Demás handlers
=============================================*/

/* Editar (traer datos) — solo Admin / Archivo / Planificación */
if(isset($_POST["idDoc"])){
    guardModificarDoc();
    $editar = new AjaxDoc();
    $editar->idDoc = $_POST["idDoc"];
    $editar->ajaxEditarDoc();
}

/* Activar — solo Admin / Archivo / Planificación */
if(isset($_POST["activarDoc"])){
    guardModificarDoc();
    $activar = new AjaxDoc();
    $activar->activarDoc = $_POST["activarDoc"];
    $activar->activarId  = $_POST["activarId"];
    $activar->ajaxActivarDoc();
}
?>