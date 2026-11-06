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

require_once __DIR__ . "/../controladores/tipo_entidad.controlador.php";
require_once __DIR__ . "/../modelos/tipo_entidad.modelo.php";

// ✅ Dependencias para ControladorTipoEntidad::puedeVerModuloTipoEntidad() / esAdminTipoEntidad()
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
✅ GUARD: solo Administrador o (Centro de Atención / Despacho)
Se invoca ANTES de cada handler
=============================================*/
function ajaxGuardTipoEntidadModulo(){
    if (!ControladorTipoEntidad::puedeVerModuloTipoEntidad()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No autorizado: solo Administrador o usuarios con coordinación Centro de Atención / Despacho."
        ]);
    }
}

class AjaxTipoEntidad{

    /* Editar (traer datos para el modal) */
    public $idTipoEntidad;
    public function ajaxEditarTipoEntidad(){
        $item = "id";
        $valor = $this->idTipoEntidad;
        $respuesta = ControladorTipoEntidad::ctrMostrarTipoEntidad($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarTipoEntidad;
    public $activarId;
    public function ajaxActivarTipoEntidad(){
        $tabla = "tipo_entidad";
        $item1 = "estado";
        $valor1 = $this->activarTipoEntidad;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloTipoEntidad::mdlActualizarTipoEntidad($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — GUARD ANTES
=============================================*/

/* CREAR TIPO DE ENTIDAD */
if(isset($_POST["nuevoTipoEntidad"])){
    ajaxGuardTipoEntidadModulo();
    $respuesta = ControladorTipoEntidad::ctrCrearTipoEntidad();
    ajaxResponder($respuesta);
}

/* EDITAR TIPO DE ENTIDAD (guardar cambios) */
if(isset($_POST["editarTipoEntidad"])){
    ajaxGuardTipoEntidadModulo();
    $respuesta = ControladorTipoEntidad::ctrEditarTipoEntidad();
    ajaxResponder($respuesta);
}

/*=============================================
Demás handlers — GUARD ANTES
=============================================*/

/* Editar (traer datos) */
if(isset($_POST["idTipoEntidad"])){
    ajaxGuardTipoEntidadModulo();
    $editar = new AjaxTipoEntidad();
    $editar->idTipoEntidad = $_POST["idTipoEntidad"];
    $editar->ajaxEditarTipoEntidad();
}

/* Activar */
if(isset($_POST["activarTipoEntidad"])){
    ajaxGuardTipoEntidadModulo();
    $activar = new AjaxTipoEntidad();
    $activar->activarTipoEntidad = $_POST["activarTipoEntidad"];
    $activar->activarId = $_POST["activarId"];
    $activar->ajaxActivarTipoEntidad();
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);
?>