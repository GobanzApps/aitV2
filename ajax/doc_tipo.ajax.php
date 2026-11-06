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

require_once __DIR__ . "/../controladores/doc_tipo.controlador.php";
require_once __DIR__ . "/../modelos/doc_tipo.modelo.php";

// ✅ Dependencias para ControladorDocTipo::puedeVerModuloDocTipo() / esAdminDocTipo()
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
✅ GUARD: solo Administrador o (Centro de Atención / Despacho)
Se invoca ANTES de cada handler
=============================================*/
function ajaxGuardDocTipoModulo(){
    if (!ControladorDocTipo::puedeVerModuloDocTipo()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No autorizado: solo Administrador o usuarios con coordinación Centro de Atención / Despacho."
        ]);
    }
}

class AjaxDocTipo{

    /* Editar (traer datos para el modal) */
    public $idDocTipo;
    public function ajaxEditarDocTipo(){
        $item = "id";
        $valor = $this->idDocTipo;
        $respuesta = ControladorDocTipo::ctrMostrarDocTipo($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarDocTipo;
    public $activarId;
    public function ajaxActivarDocTipo(){
        $tabla = "doc_tipo";
        $item1 = "estado";
        $valor1 = $this->activarDocTipo;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloDocTipo::mdlActualizarDocTipo($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — GUARD ANTES
=============================================*/

/* CREAR TIPO DE DOCUMENTO */
if(isset($_POST["nuevoDocTipo"])){
    ajaxGuardDocTipoModulo();
    $respuesta = ControladorDocTipo::ctrCrearDocTipo();
    ajaxResponder($respuesta);
}

/* EDITAR TIPO DE DOCUMENTO (guardar cambios) */
if(isset($_POST["editarDocTipo"])){
    ajaxGuardDocTipoModulo();
    $respuesta = ControladorDocTipo::ctrEditarDocTipo();
    ajaxResponder($respuesta);
}

/*=============================================
Demás handlers — GUARD ANTES
=============================================*/

/* Editar (traer datos) */
if(isset($_POST["idDocTipo"])){
    ajaxGuardDocTipoModulo();
    $editar = new AjaxDocTipo();
    $editar->idDocTipo = $_POST["idDocTipo"];
    $editar->ajaxEditarDocTipo();
}

/* Activar */
if(isset($_POST["activarDocTipo"])){
    ajaxGuardDocTipoModulo();
    $activar = new AjaxDocTipo();
    $activar->activarDocTipo = $_POST["activarDocTipo"];
    $activar->activarId = $_POST["activarId"];
    $activar->ajaxActivarDocTipo();
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);
?>