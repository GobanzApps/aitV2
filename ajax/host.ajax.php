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

require_once __DIR__ . "/../controladores/host.controlador.php";
require_once __DIR__ . "/../modelos/host.modelo.php";
require_once __DIR__ . "/../controladores/host_tipo.controlador.php";
require_once __DIR__ . "/../modelos/host_tipo.modelo.php";
require_once __DIR__ . "/../controladores/entidad.controlador.php";
require_once __DIR__ . "/../modelos/entidad.modelo.php";

// ✅ NUEVOS requires: necesarios para ControladorHost::puedeVerModuloHost()
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
✅ GUARD: solo Administrador o (Soporte/Centro de Atención)
   Se invoca ANTES de cada handler
=============================================*/
function ajaxGuardHostModulo(){
    if (!ControladorHost::puedeVerModuloHost()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No autorizado: solo Administrador o usuarios con coordinación Soporte / Centro de Atención."
        ]);
    }
}

class AjaxHost{

    /* Editar (traer datos para el modal) */
    public $idHost;
    public function ajaxEditarHost(){
        $item = "id";
        $valor = $this->idHost;
        $respuesta = ControladorHost::ctrMostrarHost($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarHost;
    public $activarId;
    public function ajaxActivarHost(){
        $tabla = "host";
        $item1 = "estado";
        $valor1 = $this->activarHost;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloHost::mdlActualizarHost($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — GUARD ANTES
=============================================*/

/* CREAR HOST */
if(isset($_POST["nuevoHost"])){
    ajaxGuardHostModulo();
    $respuesta = ControladorHost::ctrCrearHost();
    ajaxResponder($respuesta);
}

/* EDITAR HOST (guardar cambios) */
if(isset($_POST["editarHost"])){
    ajaxGuardHostModulo();
    $respuesta = ControladorHost::ctrEditarHost();
    ajaxResponder($respuesta);
}

/*=============================================
Listar tipos de host activos (single-select)
=============================================*/
if(isset($_POST["listarHostTiposActivos"])){
    ajaxGuardHostModulo();
    $tipos = ControladorHostTipo::ctrMostrarHostTipo(null, null);
    $resultado = [];
    if($tipos && is_array($tipos)){
        foreach($tipos as $t){
            if((int)$t["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$t["id"],
                    "texto" => $t["host_tipo"],
                    "color" => $t["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
Listar entidades activas (single-select)
=============================================*/
if(isset($_POST["listarEntidadesActivas"])){
    ajaxGuardHostModulo();
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
Demás handlers — guardados también
=============================================*/

/* Editar (traer datos) */
if(isset($_POST["idHost"])){
    ajaxGuardHostModulo();
    $editar = new AjaxHost();
    $editar->idHost = $_POST["idHost"];
    $editar->ajaxEditarHost();
}

/* Activar */
if(isset($_POST["activarHost"])){
    ajaxGuardHostModulo();
    $activar = new AjaxHost();
    $activar->activarHost = $_POST["activarHost"];
    $activar->activarId   = $_POST["activarId"];
    $activar->ajaxActivarHost();
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);