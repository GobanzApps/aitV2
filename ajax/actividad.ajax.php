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

/*=============================================
✅ Bootstrap de controladores / modelos
   (IMPORTANTE: los archivos AJAX NO pasan por index.php,
   por lo que cada controlador/modelo debe cargarse aquí)
=============================================*/
require_once __DIR__ . "/../controladores/actividad.controlador.php";
require_once __DIR__ . "/../controladores/user.controlador.php";           // ← NUEVO
require_once __DIR__ . "/../controladores/rol.controlador.php";            // ← NUEVO
require_once __DIR__ . "/../controladores/coordinacion.controlador.php";   // ← NUEVO

require_once __DIR__ . "/../modelos/actividad.modelo.php";
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
✅ GUARDS DE AUTORIZACIÓN
=============================================*/

/**
 * Requiere que el usuario sea Administrador, Coordinador o Especialista/Técnico.
 */
function ajaxGuardModuloActividad(){
    if (!ControladorActividad::puedeVerModuloActividad()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "Solo Administrador, Coordinador o Especialista/Técnico pueden realizar esta acción"
        ]);
    }
}

/**
 * Requiere Administrador (para activar/desactivar).
 */
function ajaxGuardAdminActividad(){
    if (!ControladorActividad::esAdminActividad()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "Solo el Administrador puede realizar esta acción"
        ]);
    }
}

/**
 * Requiere poder ver la actividad indicada.
 */
function ajaxGuardVerActividad($idActividad){
    $idActividad = intval($idActividad);
    if ($idActividad <= 0) {
        ajaxResponder(["status" => "error", "mensaje" => "ID de actividad inválido"]);
    }

    $actividad = ControladorActividad::ctrMostrarActividad("id", $idActividad);
    if (!$actividad) {
        ajaxResponder(["status" => "error", "mensaje" => "Actividad no encontrada"]);
    }

    if (!ControladorActividad::puedeVerActividad($actividad)) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No tienes permisos para ver esta actividad"
        ]);
    }
    return $actividad;
}

class AjaxActividad{

    /* Editar (traer datos para el modal Ver/Editar) */
    public $idActividad;
    public function ajaxEditarActividad(){
        $item = "id";
        $valor = $this->idActividad;
        $respuesta = ControladorActividad::ctrMostrarActividad($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarActividad;
    public $activarId;
    public function ajaxActivarActividad(){
        $tabla = "actividad";
        $item1 = "estado";
        $valor1 = $this->activarActividad;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloActividad::mdlActualizarActividad($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ LISTAR USUARIOS (solo Admin / Coord / Especialista)
=============================================*/
if(isset($_POST["listarUsuariosRolesActividad"])){
    ajaxGuardModuloActividad();

    $usuarios = ControladorUser::ctrMostrarUser(null, null);
    $roles    = ControladorRol::ctrMostrarRol(null, null);
    if(!$roles || !is_array($roles)) $roles = [];

    $mapaRoles = [];
    foreach($roles as $r){ $mapaRoles[(int)$r["id"]] = $r; }

    $rolesPermitidos = ['administrador', 'coordinador', 'especialista', 'tecnico', 'técnico'];

    $resultado = [];
    if($usuarios && is_array($usuarios)){
        foreach($usuarios as $u){
            if((int)$u["estado"] !== 1) continue;

            $idsRol = json_decode($u["id_rol"] ?? '[]', true) ?: [];
            $nombreRolElegido = null;
            $colorElegido     = null;

            foreach($idsRol as $idR){
                $idR = (int)$idR;
                if(isset($mapaRoles[$idR]) && (int)$mapaRoles[$idR]["estado"] === 1){
                    $nombreRol = mb_strtolower(trim($mapaRoles[$idR]["rol"]));
                    if(in_array($nombreRol, $rolesPermitidos, true)){
                        $nombreRolElegido = $mapaRoles[$idR]["rol"];
                        $colorElegido     = $mapaRoles[$idR]["color"] ?: "#0d6efd";
                        break;
                    }
                }
            }

            if($nombreRolElegido !== null){
                $nombreMostrar = !empty($u["name"]) ? $u["name"] : $u["user"];
                $resultado[] = [
                    "id"    => (int)$u["id"],
                    "texto" => $nombreMostrar,
                    "color" => $colorElegido
                ];
            }
        }
    }

    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
⚠️ LISTAR COORDINACIONES ACTIVAS
=============================================*/
if(isset($_POST["listarCoordinacionesActivas"])){
    ajaxGuardModuloActividad();

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

/*=============================================
⚠️ CREAR y EDITAR van PRIMERO
=============================================*/

/* CREAR ACTIVIDAD */
if(isset($_POST["nuevaActividad"])){
    ajaxGuardModuloActividad();
    $respuesta = ControladorActividad::ctrCrearActividad();
    ajaxResponder($respuesta);
}

/* EDITAR ACTIVIDAD (guardar cambios) */
if(isset($_POST["editarActividad"])){
    ajaxGuardModuloActividad();
    ajaxGuardVerActividad(intval($_POST["idActividad"] ?? 0));
    $respuesta = ControladorActividad::ctrEditarActividad();
    ajaxResponder($respuesta);
}

/*=============================================
Demás handlers
=============================================*/

/* Editar/Ver (traer datos) */
if(isset($_POST["idActividad"])){
    ajaxGuardModuloActividad();
    ajaxGuardVerActividad(intval($_POST["idActividad"] ?? 0));

    $editar = new AjaxActividad();
    $editar->idActividad = intval($_POST["idActividad"]);
    $editar->ajaxEditarActividad();
}

/* Activar — SOLO ADMIN */
if(isset($_POST["activarActividad"])){
    ajaxGuardModuloActividad();
    ajaxGuardAdminActividad();
    ajaxGuardVerActividad(intval($_POST["activarId"] ?? 0));

    $activar = new AjaxActividad();
    $activar->activarActividad = $_POST["activarActividad"];
    $activar->activarId        = $_POST["activarId"];
    $activar->ajaxActivarActividad();
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);
?>