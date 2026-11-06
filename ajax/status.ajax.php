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

require_once __DIR__ . "/../controladores/status.controlador.php";
require_once __DIR__ . "/../modelos/status.modelo.php";

class AjaxStatus{

    /* Editar (traer datos para el modal) */
    public $idStatus;
    public function ajaxEditarStatus(){
        $item = "id";
        $valor = $this->idStatus;
        $respuesta = ControladorStatus::ctrMostrarStatus($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarStatus;
    public $activarId;
    public function ajaxActivarStatus(){
        $tabla = "status";
        $item1 = "estado";
        $valor1 = $this->activarStatus;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloStatus::mdlActualizarStatus($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR van PRIMERO
=============================================*/

/* CREAR STATUS */
if(isset($_POST["nuevoStatus"])){
    $respuesta = ControladorStatus::ctrCrearStatus();
    ajaxResponder($respuesta);
}

/* EDITAR STATUS (guardar cambios) */
if(isset($_POST["editarStatus"])){
    $respuesta = ControladorStatus::ctrEditarStatus();
    ajaxResponder($respuesta);
}

/*=============================================
Demás handlers
=============================================*/

/* Editar (traer datos) */
if(isset($_POST["idStatus"])){
    $editar = new AjaxStatus();
    $editar->idStatus = $_POST["idStatus"];
    $editar->ajaxEditarStatus();
}

/* Activar */
if(isset($_POST["activarStatus"])){
    $activar = new AjaxStatus();
    $activar->activarStatus = $_POST["activarStatus"];
    $activar->activarId = $_POST["activarId"];
    $activar->ajaxActivarStatus();
}