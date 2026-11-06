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

require_once __DIR__ . "/../controladores/tipo_entidad.controlador.php";
require_once __DIR__ . "/../modelos/tipo_entidad.modelo.php";
require_once __DIR__ . "/../controladores/user.controlador.php";
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../controladores/coordinacion.controlador.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";
require_once __DIR__ . "/../controladores/rol.controlador.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../controladores/entidad.controlador.php";
require_once __DIR__ . "/../modelos/entidad.modelo.php";

/*=============================================
GUARD: solo rol "Administrador"
(para los CRUD de usuario; los handlers de
auto-servicio — editarPerfil, cambiarPassword,
cambiarTema — NO pasan por esta guarda)
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

class AjaxUser{

    /* Editar (traer datos para el modal) */
    public $idUser;
    public function ajaxEditarUser(){
        $item = "id";
        $valor = $this->idUser;
        $respuesta = ControladorUser::ctrMostrarUser($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarUser;
    public $activarId;
    public function ajaxActivarUser(){
        $tabla = "user";
        $item1 = "estado";
        $valor1 = $this->activarUser;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloUser::mdlActualizarUser($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }

    /* Validar no repetir usuario */
    public $validarUser;
    public function ajaxValidarUser(){
        $item = "user";
        $valor = $this->validarUser;
        $respuesta = ControladorUser::ctrMostrarUser($item, $valor);
        ajaxResponder($respuesta);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — SOLO ADMINISTRADOR
=============================================*/

/* CREAR USUARIO */
if(isset($_POST["nuevoUser"])){
    ajaxAdminGuard();
    $respuesta = ControladorUser::ctrCrearUser();
    ajaxResponder($respuesta);
}

/* EDITAR USUARIO */
if(isset($_POST["editarUser"])){
    ajaxAdminGuard();
    $respuesta = ControladorUser::ctrEditarUser();
    ajaxResponder($respuesta);
}

/*=============================================
AUTO-SERVICIO: editar perfil propio (cualquier
usuario logueado, NO requiere admin)
=============================================*/
if(isset($_POST["editarPerfil"])){
    if(!isset($_SESSION["id"])){
        ajaxResponder(["status" => "error", "mensaje" => "Sesión no válida"]);
    }
    $respuesta = ControladorUser::ctrEditarPerfil(
        $_SESSION["id"],
        $_POST["perfilName"]  ?? '',
        $_POST["perfilEmail"] ?? '',
        $_POST["perfilPhone"] ?? ''
    );
    ajaxResponder($respuesta);
}

/*=============================================
Listers compartidos (usados por otras vistas
como entidad.php y actividad.php) — SIN GUARD
=============================================*/
if(isset($_POST["listarCoordinacionesActivas"])){
    $coordinaciones = ControladorCoordinacion::ctrMostrarCoordinacion(null, null);
    $resultado = [];
    if($coordinaciones && is_array($coordinaciones)){
        foreach($coordinaciones as $c){
            if((int)$c["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$c["id"],
                    "texto" => $c["coordinacion"],
                    "color" => $c["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

if(isset($_POST["listarRolesActivos"])){
    $roles = ControladorRol::ctrMostrarRol(null, null);
    $resultado = [];
    if($roles && is_array($roles)){
        foreach($roles as $r){
            if((int)$r["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$r["id"],
                    "texto" => $r["rol"],
                    "color" => $r["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

if(isset($_POST["listarTiposEntidadActivos"])){
    $tipos = ControladorTipoEntidad::ctrMostrarTipoEntidad(null, null);
    $resultado = [];
    if($tipos && is_array($tipos)){
        foreach($tipos as $t){
            if((int)$t["estado"] === 1){
                $resultado[] = [
                    "id"    => (int)$t["id"],
                    "texto" => $t["tipo_entidad"],
                    "color" => $t["color"] ?: "#0d6efd"
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

if(isset($_POST["listarEntidadesActivas"])){
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
Demás handlers — SOLO ADMINISTRADOR
=============================================*/

/* Editar (traer datos) */
if(isset($_POST["idUser"])){
    ajaxAdminGuard();
    $editar = new AjaxUser();
    $editar->idUser = $_POST["idUser"];
    $editar->ajaxEditarUser();
}

/* Activar */
if(isset($_POST["activarUser"])){
    ajaxAdminGuard();
    $activarUser = new AjaxUser();
    $activarUser->activarUser = $_POST["activarUser"];
    $activarUser->activarId = $_POST["activarId"];
    $activarUser->ajaxActivarUser();
}

/* Validar (usado al crear usuario) */
if(isset($_POST["validarUser"])){
    ajaxAdminGuard();
    $valUser = new AjaxUser();
    $valUser->validarUser = $_POST["validarUser"];
    $valUser->ajaxValidarUser();
}

/*=============================================
AUTO-SERVICIO: cambiar contraseña propia
=============================================*/
if(isset($_POST["cambiarPassword"])){
    if(isset($_POST["passwordActual"]) && isset($_POST["nuevaPassword"]) && isset($_POST["confirmarPassword"])){
        $respuesta = ControladorUser::ctrCambiarPassword(
            $_SESSION["id"],
            $_POST["passwordActual"],
            $_POST["nuevaPassword"],
            $_POST["confirmarPassword"]
        );
        ajaxResponder($respuesta);
    }
}

/*=============================================
AUTO-SERVICIO: cambiar tema
=============================================*/
if(isset($_POST["cambiarTema"])){
    if(isset($_POST["tema"]) && isset($_SESSION["id"])){
        $respuesta = ControladorUser::ctrCambiarTema(
            $_SESSION["id"],
            $_POST["tema"]
        );
        ajaxResponder($respuesta);
    } else {
        ajaxResponder(["status" => "error", "mensaje" => "Datos incompletos"]);
    }
}
?>