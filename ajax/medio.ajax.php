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

require_once __DIR__ . "/../controladores/medio.controlador.php";
require_once __DIR__ . "/../modelos/medio.modelo.php";

// ✅ Dependencias para ControladorMedio::puedeVerModuloMedio() / esAdminMedio()
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
✅ GUARD: solo Administrador o (Centro de Atención / Despacho)
Se invoca ANTES de cada handler
=============================================*/
function ajaxGuardMedioModulo(){
    if (!ControladorMedio::puedeVerModuloMedio()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No autorizado: solo Administrador o usuarios con coordinación Centro de Atención / Despacho."
        ]);
    }
}

class AjaxMedio{

    /* Editar (traer datos para el modal) */
    public $idMedio;
    public function ajaxEditarMedio(){
        $item = "id";
        $valor = $this->idMedio;
        $respuesta = ControladorMedio::ctrMostrarMedio($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarMedio;
    public $activarId;
    public function ajaxActivarMedio(){
        $tabla = "medio";
        $item1 = "estado";
        $valor1 = $this->activarMedio;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloMedio::mdlActualizarMedio($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — GUARD ANTES
=============================================*/

/* CREAR MEDIO */
if(isset($_POST["nuevoMedio"])){
    ajaxGuardMedioModulo();
    $respuesta = ControladorMedio::ctrCrearMedio();
    ajaxResponder($respuesta);
}

/* EDITAR MEDIO (guardar cambios) */
if(isset($_POST["editarMedio"])){
    ajaxGuardMedioModulo();
    $respuesta = ControladorMedio::ctrEditarMedio();
    ajaxResponder($respuesta);
}

/*=============================================
Demás handlers — GUARD ANTES
=============================================*/

/* Editar (traer datos) */
if(isset($_POST["idMedio"])){
    ajaxGuardMedioModulo();
    $editar = new AjaxMedio();
    $editar->idMedio = $_POST["idMedio"];
    $editar->ajaxEditarMedio();
}

/* Activar */
if(isset($_POST["activarMedio"])){
    ajaxGuardMedioModulo();
    $activar = new AjaxMedio();
    $activar->activarMedio = $_POST["activarMedio"];
    $activar->activarId = $_POST["activarId"];
    $activar->ajaxActivarMedio();
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);
?>