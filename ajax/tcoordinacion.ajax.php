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

require_once __DIR__ . "/../controladores/tcoordinacion.controlador.php";
require_once __DIR__ . "/../modelos/tcoordinacion.modelo.php";
require_once __DIR__ . "/../controladores/coordinacion.controlador.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
⚠️ CREAR y EDITAR van PRIMERO
=============================================*/

/* CREAR ASIGNACIÓN */
if(isset($_POST["nuevoTcoordinacion"])){
    $respuesta = ControladorTcoordinacion::ctrCrearTcoordinacion();
    ajaxResponder($respuesta);
}

/* EDITAR ASIGNACIÓN */
if(isset($_POST["editarTcoordinacion"])){
    $respuesta = ControladorTcoordinacion::ctrEditarTcoordinacion();
    ajaxResponder($respuesta);
}

/* BORRAR ASIGNACIÓN */
if(isset($_POST["borrarTcoordinacion"])){
    $respuesta = ControladorTcoordinacion::ctrBorrarTcoordinacion();
    ajaxResponder($respuesta);
}

/*=============================================
NUEVO: Listar coordinaciones activas (multi-select)
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

/*=============================================
Demás handlers
=============================================*/

/* Obtener UN registro por id (para el modal Editar) */
if(isset($_POST["idTcoordinacion"]) && !isset($_POST["borrarTcoordinacion"])){
    $registro = ControladorTcoordinacion::ctrMostrarTcoordinacionUno("id", intval($_POST["idTcoordinacion"]));
    if($registro){
        ajaxResponder($registro);
    } else {
        ajaxResponder(["status" => "error", "mensaje" => "Registro no encontrado"]);
    }
}