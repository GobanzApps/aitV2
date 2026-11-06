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
Dependencias del módulo
=============================================*/
require_once __DIR__ . "/../controladores/tstatus.controlador.php";
require_once __DIR__ . "/../modelos/tstatus.modelo.php";

// Necesarios para listarStatusActivos (combo de los modales)
require_once __DIR__ . "/../controladores/status.controlador.php";
require_once __DIR__ . "/../modelos/status.modelo.php";

// ═══ Requerido por ctrAceptarNotificacion()
// (usa ModeloTuser::mdlMostrarTuser y mdlIngresarTuser)
require_once __DIR__ . "/../modelos/tuser.modelo.php";

// ═══ NUEVO ═══  Requeridos para validar rol en listarStatusActivos
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
⚠️ CREAR STATUS
=============================================*/
if(isset($_POST["nuevoTstatus"])){
    $respuesta = ControladorTstatus::ctrCrearTstatus();
    ajaxResponder($respuesta);
}

/*=============================================
⚠️ EDITAR STATUS
=============================================*/
if(isset($_POST["editarTstatus"])){
    $respuesta = ControladorTstatus::ctrEditarTstatus();
    ajaxResponder($respuesta);
}

/*=============================================
⚠️ BORRAR STATUS  (antes que idTstatus)
   El JS envía "borrarTstatus" + "idTstatus".
   ✅ Se pasa el $id al controlador.
=============================================*/
if(isset($_POST["borrarTstatus"])){

    $id = intval($_POST["idTstatus"] ?? 0);
    if($id <= 0){
        ajaxResponder(["status" => "error", "mensaje" => "ID inválido"]);
    }

    $respuesta = ControladorTstatus::ctrBorrarTstatus($id);

    if($respuesta == "ok"){
        ajaxResponder([
            "status"  => "success",
            "mensaje" => "¡Registro eliminado correctamente!"
        ]);
    }
    ajaxResponder([
        "status"  => "error",
        "mensaje" => "No se pudo eliminar el registro"
    ]);
}

/*=============================================
⚠️ CAMBIAR ESTADO (activar / desactivar)
   El JS envía "cambiarEstadoTstatus" + "idTstatus"
   + "nuevoEstado". Debe ir antes que idTstatus.
=============================================*/
if(isset($_POST["cambiarEstadoTstatus"])){

    $id     = intval($_POST["idTstatus"]   ?? 0);
    $estado = intval($_POST["nuevoEstado"] ?? 0);

    if($id <= 0){
        ajaxResponder(["status" => "error", "mensaje" => "ID inválido"]);
    }
    if(!in_array($estado, [0, 1], true)){
        ajaxResponder(["status" => "error", "mensaje" => "Estado inválido"]);
    }

    $respuesta = ControladorTstatus::ctrCambiarEstadoTstatus($id, $estado);
    ajaxResponder($respuesta);
}

/*=============================================
✅ ACEPTAR NOTIFICACIÓN (campanita 🛎️)
   El JS de header.php envía:
       aceptarNotificacion = 1
       idTstatus           = <id del registro>
   Debe ir ANTES del handler idTstatus.
=============================================*/
if(isset($_POST["aceptarNotificacion"])){

    $idTstatus = intval($_POST["idTstatus"] ?? 0);
    if($idTstatus <= 0){
        ajaxResponder(["status" => "error", "mensaje" => "ID inválido"]);
    }

    $respuesta = ControladorTstatus::ctrAceptarNotificacion($idTstatus);
    ajaxResponder($respuesta);
}

/*=============================================
✅ LISTAR STATUS ACTIVOS (combos Tom Select)
   Alimenta los <select> de los modales Crear y Editar.

   ✅ NUEVO: Nuevo / Reemplazo solo se devuelven si el
   usuario es Administrador o tiene coordinación
   "Centro de Atención". Para el resto de roles esos
   dos status se excluyen de la respuesta.
=============================================*/
if(isset($_POST["listarStatusActivos"])){

    /* ── ¿El usuario actual es Admin o Centro de Atención? ── */
    $idUserAct      = (int)($_SESSION["id"] ?? 0);
    $esAdminOCentro = false;

    if ($idUserAct > 0) {
        $userAct = ModeloUser::mdlMostrarUser("user", "id", $idUserAct);
        if ($userAct) {

            // ¿Administrador activo?
            $rolesIdsAct = json_decode($userAct["id_rol"] ?? '[]', true) ?: [];
            foreach ($rolesIdsAct as $idR) {
                $rolAct = ModeloRol::mdlMostrarRol("rol", "id", (int)$idR);
                if ($rolAct
                    && (int)($rolAct["estado"] ?? 0) === 1
                    && strcasecmp(trim($rolAct["rol"]), "Administrador") === 0) {
                    $esAdminOCentro = true;
                    break;
                }
            }

            // ¿Coordinación Centro de Atención?
            if (!$esAdminOCentro) {
                $coordsIdsAct = json_decode($userAct["id_coordinacion"] ?? '[]', true) ?: [];
                foreach ($coordsIdsAct as $idC) {
                    $coordAct = ModeloCoordinacion::mdlMostrarCoordinacion("coordinacion", "id", (int)$idC);
                    if ($coordAct
                        && (int)($coordAct["estado"] ?? 0) === 1
                        && strpos(mb_strtolower(trim($coordAct["coordinacion"])), 'centro de atenci') !== false) {
                        $esAdminOCentro = true;
                        break;
                    }
                }
            }
        }
    }

    $statuses  = ControladorStatus::ctrMostrarStatus(null, null);
    $resultado = [];

    if($statuses && is_array($statuses)){
        foreach($statuses as $s){
            if((int)($s["estado"] ?? 0) !== 1) continue;

            /* ✅ Ocultar Nuevo / Reemplazo a quien no sea Admin ni Centro */
            if (!$esAdminOCentro
                && in_array(trim($s["status"]), ["Nuevo", "Reemplazo"], true)) {
                continue;
            }

            $resultado[] = [
                "id"    => (int)$s["id"],
                "texto" => $s["status"],
                "color" => $s["color"] ?: "#0d6efd"
            ];
        }
    }

    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
✅ LISTAR NOTIFICACIONES PENDIENTES
   El JS de header.php (campanita) hace polling
   cada 30 s con el flag "listarNotificaciones".
   Se filtran por el usuario de la sesión.
=============================================*/
if(isset($_POST["listarNotificaciones"])){

    $idUser = (int)($_SESSION["id"] ?? 0);
    if($idUser <= 0){
        ajaxResponder(["status" => "ok", "data" => []]);
    }

    $data = ControladorTstatus::ctrListarNotificacionesPendientes($idUser);
    if(!is_array($data)) $data = [];

    ajaxResponder(["status" => "ok", "data" => $data]);
}

/*=============================================
⚠️ TRAER UNO (para el modal Editar) — AL FINAL
   Este handler debe quedar al final porque
   cualquier petición con "idTstatus" caería aquí;
   los handlers de arriba ya se habrán consumido
   si envían sus flags específicos.
=============================================*/
if(isset($_POST["idTstatus"])){
    $id   = intval($_POST["idTstatus"]);
    $fila = ControladorTstatus::ctrMostrarTstatusUno($id);
    ajaxResponder($fila ?: null);
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);