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

require_once __DIR__ . "/../controladores/rol.controlador.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/user.modelo.php";

/*=============================================
GUARD: solo rol "Administrador"
(Módulo 100 % admin-only)
=============================================*/
function ajaxAdminGuard(){
    $idUser = (int)($_SESSION["id"] ?? 0);
    if ($idUser <= 0) {
        ajaxResponder(["status" => "error", "mensaje" => "Sesión no válida"]);
    }

    $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
    if (!$user) {
        ajaxResponder(["status" => "error", "mensaje" => "Usuario no encontrado"]);
    }

    $rolesIds = json_decode($user["id_rol"] ?? '[]', true);
    if (!is_array($rolesIds)) $rolesIds = [];

    foreach ($rolesIds as $idR) {
        $rol = ModeloRol::mdlMostrarRol("rol", "id", (int)$idR);
        if ($rol
            && (int)($rol["estado"] ?? 0) === 1
            && strcasecmp(trim($rol["rol"]), "Administrador") === 0) {
            return;
        }
    }

    ajaxResponder([
        "status"  => "error",
        "mensaje" => "Solo el Administrador puede realizar esta acción"
    ]);
}

class AjaxRol{

    /* Editar (traer datos para el modal) */
    public $idRol;
    public function ajaxEditarRol(){
        $item = "id";
        $valor = $this->idRol;
        $respuesta = ControladorRol::ctrMostrarRol($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarRol;
    public $activarId;
    public function ajaxActivarRol(){
        $tabla = "rol";
        $item1 = "estado";
        $valor1 = $this->activarRol;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloRol::mdlActualizarRol($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — SOLO ADMINISTRADOR
=============================================*/

if(isset($_POST["nuevoRol"])){
    ajaxAdminGuard();
    $respuesta = ControladorRol::ctrCrearRol();
    ajaxResponder($respuesta);
}

if(isset($_POST["editarRol"])){
    ajaxAdminGuard();
    $respuesta = ControladorRol::ctrEditarRol();
    ajaxResponder($respuesta);
}

/*=============================================
Demás handlers — SOLO ADMINISTRADOR
=============================================*/

if(isset($_POST["idRol"])){
    ajaxAdminGuard();
    $editar = new AjaxRol();
    $editar->idRol = $_POST["idRol"];
    $editar->ajaxEditarRol();
}

if(isset($_POST["activarRol"])){
    ajaxAdminGuard();
    $activar = new AjaxRol();
    $activar->activarRol = $_POST["activarRol"];
    $activar->activarId  = $_POST["activarId"];
    $activar->ajaxActivarRol();
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);