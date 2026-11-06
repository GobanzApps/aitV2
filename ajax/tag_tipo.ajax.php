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

require_once __DIR__ . "/../controladores/tag_tipo.controlador.php";
require_once __DIR__ . "/../modelos/tag_tipo.modelo.php";
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";

/*=============================================
GUARD: solo rol "Administrador"
(Módulo 100 % admin-only, igual que rol)
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

class AjaxTagTipo{

    /* Editar (traer datos para el modal) */
    public $idTagTipo;
    public function ajaxEditarTagTipo(){
        $item = "id";
        $valor = $this->idTagTipo;
        $respuesta = ControladorTagTipo::ctrMostrarTagTipo($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarTagTipo;
    public $activarId;
    public function ajaxActivarTagTipo(){
        $tabla = "tag_tipo";
        $item1 = "estado";
        $valor1 = $this->activarTagTipo;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloTagTipo::mdlActualizarTagTipo($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — SOLO ADMINISTRADOR
=============================================*/

if(isset($_POST["nuevoTagTipo"])){
    ajaxAdminGuard();
    $respuesta = ControladorTagTipo::ctrCrearTagTipo();
    ajaxResponder($respuesta);
}

if(isset($_POST["editarTagTipo"])){
    ajaxAdminGuard();
    $respuesta = ControladorTagTipo::ctrEditarTagTipo();
    ajaxResponder($respuesta);
}

/*=============================================
Demás handlers — SOLO ADMINISTRADOR
=============================================*/

if(isset($_POST["idTagTipo"])){
    ajaxAdminGuard();
    $editar = new AjaxTagTipo();
    $editar->idTagTipo = $_POST["idTagTipo"];
    $editar->ajaxEditarTagTipo();
}

if(isset($_POST["activarTagTipo"])){
    ajaxAdminGuard();
    $activar = new AjaxTagTipo();
    $activar->activarTagTipo = $_POST["activarTagTipo"];
    $activar->activarId      = $_POST["activarId"];
    $activar->ajaxActivarTagTipo();
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);
?>