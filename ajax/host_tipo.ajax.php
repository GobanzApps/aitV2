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

require_once __DIR__ . "/../controladores/host_tipo.controlador.php";
require_once __DIR__ . "/../modelos/host_tipo.modelo.php";

// ✅ NUEVOS requires: necesarios para ControladorHostTipo::puedeVerModuloHostTipo()
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
✅ GUARD: solo Administrador o (Soporte/Centro de Atención)
=============================================*/
function ajaxGuardHostTipoModulo(){
    if (!ControladorHostTipo::puedeVerModuloHostTipo()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No autorizado: solo Administrador o usuarios con coordinación Soporte / Centro de Atención."
        ]);
    }
}

class AjaxHostTipo{

    /* Editar (traer datos para el modal) */
    public $idHostTipo;
    public function ajaxEditarHostTipo(){
        $item = "id";
        $valor = $this->idHostTipo;
        $respuesta = ControladorHostTipo::ctrMostrarHostTipo($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarHostTipo;
    public $activarId;
    public function ajaxActivarHostTipo(){
        $tabla = "host_tipo";
        $item1 = "estado";
        $valor1 = $this->activarHostTipo;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloHostTipo::mdlActualizarHostTipo($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — GUARD ANTES
=============================================*/

/* CREAR TIPO DE HOST */
if(isset($_POST["nuevoHostTipo"])){
    ajaxGuardHostTipoModulo();
    $respuesta = ControladorHostTipo::ctrCrearHostTipo();
    ajaxResponder($respuesta);
}

/* EDITAR TIPO DE HOST (guardar cambios) */
if(isset($_POST["editarHostTipo"])){
    ajaxGuardHostTipoModulo();
    $respuesta = ControladorHostTipo::ctrEditarHostTipo();
    ajaxResponder($respuesta);
}

/*=============================================
Demás handlers — guardados también
=============================================*/

/* Editar (traer datos) */
if(isset($_POST["idHostTipo"])){
    ajaxGuardHostTipoModulo();
    $editar = new AjaxHostTipo();
    $editar->idHostTipo = $_POST["idHostTipo"];
    $editar->ajaxEditarHostTipo();
}

/* Activar */
if(isset($_POST["activarHostTipo"])){
    ajaxGuardHostTipoModulo();
    $activar = new AjaxHostTipo();
    $activar->activarHostTipo = $_POST["activarHostTipo"];
    $activar->activarId       = $_POST["activarId"];
    $activar->ajaxActivarHostTipo();
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);