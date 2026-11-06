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

require_once __DIR__ . "/../controladores/tuser.controlador.php";
require_once __DIR__ . "/../modelos/tuser.modelo.php";
require_once __DIR__ . "/../controladores/user.controlador.php";
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../controladores/rol.controlador.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";

// Dependencias necesarias para el conteo de tickets + guards
require_once __DIR__ . "/../controladores/ticket.controlador.php";
require_once __DIR__ . "/../modelos/ticket.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
GUARDS DE AUTORIZACIÓN (backend)
=============================================*/

/**
 * Verifica que el usuario tenga acceso a la sección de tickets.
 * Corta la ejecución si no cumple.
 */
function tuserGuardVerSeccionTickets(){
    $auth = ControladorTicket::authTicket();
    if (!$auth || !ControladorTicket::puedeVerSeccionTickets($auth)) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No autorizado para acceder a esta sección"
        ]);
    }
    return $auth;
}

/**
 * Devuelve true si el ticket existe.
 */
function tuserTicketExiste($idTicket){
    $idTicket = intval($idTicket);
    if ($idTicket <= 0) return false;
    return ModeloTicket::mdlMostrarTicket("ticket", "id", $idTicket) !== false;
}

/**
 * Devuelve true si el ticket está finalizado (check = 1).
 */
function tuserTicketFinalizado($idTicket){
    $idTicket = intval($idTicket);
    if ($idTicket <= 0) return false;
    $ticket = ModeloTicket::mdlMostrarTicket("ticket", "id", $idTicket);
    return $ticket && (int)($ticket["check"] ?? 0) === 1;
}

/**
 * Guard para AGREGAR usuarios:
 *  - Ticket debe existir
 *  - Ticket NO debe estar finalizado
 *  - Usuario debe ser Administrador, Coordinador o Especialista/Técnico
 */
function tuserGuardAgregar($idTicket){
    $idTicket = intval($idTicket);
    if ($idTicket <= 0 || !tuserTicketExiste($idTicket)) {
        ajaxResponder(["status" => "error", "mensaje" => "Ticket no válido"]);
    }

    if (tuserTicketFinalizado($idTicket)) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "El ticket está finalizado. No se pueden agregar usuarios."
        ]);
    }

    $auth = ControladorTicket::authTicket();
    if (!$auth) {
        ajaxResponder(["status" => "error", "mensaje" => "Sesión no válida"]);
    }

    $rolPermitido = ControladorTicket::esAdminTicket($auth)
                 || ControladorTicket::esCoordinadorTicket($auth)
                 || ControladorTicket::esEspecialistaTicket($auth);

    if (!$rolPermitido) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "Solo Administrador, Coordinador o Especialista/Técnico pueden agregar usuarios."
        ]);
    }
}

/**
 * Guard para EDITAR / ELIMINAR usuarios:
 *  - Ticket debe existir
 *  - Ticket NO debe estar finalizado
 *  - Usuario debe ser Administrador o pertenecer a Centro de Atención
 */
function tuserGuardEditarEliminar($idTicket){
    $idTicket = intval($idTicket);
    if ($idTicket <= 0 || !tuserTicketExiste($idTicket)) {
        ajaxResponder(["status" => "error", "mensaje" => "Ticket no válido"]);
    }

    if (tuserTicketFinalizado($idTicket)) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "El ticket está finalizado. No se pueden editar ni eliminar usuarios."
        ]);
    }

    $auth = ControladorTicket::authTicket();
    if (!$auth) {
        ajaxResponder(["status" => "error", "mensaje" => "Sesión no válida"]);
    }

    $rolPermitido = ControladorTicket::esAdminTicket($auth)
                 || ControladorTicket::esCentroAtencionTicket($auth);

    if (!$rolPermitido) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "Solo Administrador o Coordinador de Centro de Atención pueden editar/eliminar usuarios."
        ]);
    }
}

/*=============================================
⚠️ CREAR ASIGNACIÓN
=============================================*/
if(isset($_POST["nuevoTuser"])){
    $idTicket = intval($_POST["idTicket"] ?? 0);
    tuserGuardAgregar($idTicket);

    $respuesta = ControladorTuser::ctrCrearTuser();
    ajaxResponder($respuesta);
}

/*=============================================
⚠️ EDITAR ASIGNACIÓN
=============================================*/
if(isset($_POST["editarTuser"])){
    $id = intval($_POST["idTuser"] ?? 0);
    if($id <= 0){
        ajaxResponder(["status" => "error", "mensaje" => "ID inválido"]);
    }

    $registro = ModeloTuser::mdlMostrarTuserUno("tuser", "id", $id);
    if(!$registro){
        ajaxResponder(["status" => "error", "mensaje" => "Registro no encontrado"]);
    }

    tuserGuardEditarEliminar(intval($registro["id_ticket"] ?? 0));

    $respuesta = ControladorTuser::ctrEditarTuser();
    ajaxResponder($respuesta);
}

/*=============================================
⚠️ BORRAR ASIGNACIÓN
=============================================*/
if(isset($_POST["borrarTuser"])){
    $id = intval($_POST["idTuser"] ?? 0);
    if($id <= 0){
        ajaxResponder(["status" => "error", "mensaje" => "ID inválido"]);
    }

    $registro = ModeloTuser::mdlMostrarTuserUno("tuser", "id", $id);
    if($registro){
        tuserGuardEditarEliminar(intval($registro["id_ticket"] ?? 0));
    }

    $r = ControladorTuser::ctrBorrarTuser($id);
    if($r === "ok"){
        ajaxResponder(["status" => "success", "mensaje" => "¡Asignación eliminada correctamente!"]);
    }
    ajaxResponder(["status" => "error", "mensaje" => "No se pudo eliminar"]);
}

/*=============================================
LISTAR USUARIOS ACTIVOS (multi-select)
=============================================*/
if(isset($_POST["listarUsuariosActivos"])){
    tuserGuardVerSeccionTickets();

    $usuarios = ControladorUser::ctrMostrarUser(null, null);
    $roles    = ControladorRol::ctrMostrarRol(null, null);
    if(!$roles || !is_array($roles)) $roles = [];

    $mapaRoles = [];
    foreach($roles as $r){ $mapaRoles[(int)$r["id"]] = $r; }

    $resultado = [];
    if($usuarios && is_array($usuarios)){
        foreach($usuarios as $u){
            if((int)$u["estado"] !== 1) continue;

            $color  = "#6c757d";
            $idsRol = json_decode($u["id_rol"] ?? '[]', true);
            if(is_array($idsRol)){
                foreach($idsRol as $idR){
                    $idR = (int)$idR;
                    if(isset($mapaRoles[$idR]) && (int)$mapaRoles[$idR]["estado"] === 1){
                        $color = $mapaRoles[$idR]["color"] ?: "#0d6efd";
                        break;
                    }
                }
            }

            $nombreMostrar = !empty($u["name"]) ? $u["name"] : $u["user"];

            $resultado[] = [
                "id"    => (int)$u["id"],
                "texto" => $nombreMostrar,
                "color" => $color
            ];
        }
    }

    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
LISTAR USUARIOS PAGINADOS CON CONTEO DE TICKETS
(solo usuarios con coordinación asignada)
=============================================*/
if(isset($_POST["listarUsuariosConTicketsPaginado"])){
    tuserGuardVerSeccionTickets();

    $page   = max(1, intval($_POST["page"] ?? 1));
    $limit  = 10;
    $search = trim($_POST["q"] ?? '');
    $offset = ($page - 1) * $limit;

    $usuarios = ControladorUser::ctrMostrarUser(null, null);
    if(!$usuarios || !is_array($usuarios)) $usuarios = [];

    $roles = ControladorRol::ctrMostrarRol(null, null);
    if(!$roles || !is_array($roles)) $roles = [];
    $mapaRoles = [];
    foreach($roles as $r){ $mapaRoles[(int)$r["id"]] = $r; }

    $filtrados = [];
    foreach($usuarios as $u){
        if((int)($u["estado"] ?? 0) !== 1) continue;

        $idsCoord = json_decode($u["id_coordinacion"] ?? '[]', true);
        if(empty($idsCoord) || !is_array($idsCoord)){
            continue;
        }

        $nombreMostrar = !empty($u["name"]) ? $u["name"] : $u["user"];

        if($search !== ''){
            $enNombre = stripos($nombreMostrar, $search) !== false;
            $enUser   = stripos((string)$u["user"], $search) !== false;
            if(!$enNombre && !$enUser) continue;
        }

        $filtrados[] = $u;
    }

    $total     = count($filtrados);
    $paginados = array_slice($filtrados, $offset, $limit);

    $tickets = ControladorTicket::ctrMostrarTicket(null, null);
    if(!$tickets || !is_array($tickets)) $tickets = [];

    $idsTicketsActivos = [];
    foreach($tickets as $t){
        if((int)($t["estado"] ?? 0) === 1 && (int)($t["check"] ?? 0) === 0){
            $idsTicketsActivos[(int)$t["id"]] = true;
        }
    }

    $tusers = ControladorTuser::ctrMostrarTuser(null, null);
    if(!$tusers || !is_array($tusers)) $tusers = [];

    $conteoPorUsuario = [];
    $seenPairs        = [];

    foreach($tusers as $tu){
        $tid = (int)$tu["id_ticket"];
        if(!isset($idsTicketsActivos[$tid])) continue;

        $ids = json_decode($tu["id_user"] ?? '[]', true);
        if(!is_array($ids)) $ids = [];

        foreach($ids as $idU){
            $idU = (int)$idU;
            if($idU <= 0) continue;

            $pairKey = $idU . '_' . $tid;
            if(isset($seenPairs[$pairKey])) continue;
            $seenPairs[$pairKey] = true;

            $conteoPorUsuario[$idU] = ($conteoPorUsuario[$idU] ?? 0) + 1;
        }
    }

    $data = [];
    foreach($paginados as $u){
        $idU    = (int)$u["id"];
        $nombre = !empty($u["name"]) ? $u["name"] : $u["user"];

        $color = "#6c757d";
        foreach((json_decode($u["id_rol"] ?? '[]', true) ?: []) as $idR){
            $idR = (int)$idR;
            if(isset($mapaRoles[$idR]) && (int)$mapaRoles[$idR]["estado"] === 1){
                $color = $mapaRoles[$idR]["color"] ?: "#0d6efd";
                break;
            }
        }

        $data[] = [
            "id"           => $idU,
            "texto"        => $nombre,
            "user"         => $u["user"],
            "color"        => $color,
            "ticket_count" => $conteoPorUsuario[$idU] ?? 0
        ];
    }

    ajaxResponder([
        "status"   => "ok",
        "data"     => $data,
        "total"    => $total,
        "page"     => $page,
        "has_more" => ($offset + count($paginados)) < $total
    ]);
}

/*=============================================
TRAER UNO (para el modal de edición)
=============================================*/
if(isset($_POST["idTuser"])){
    $id = intval($_POST["idTuser"]);
    if($id <= 0){
        ajaxResponder(null);
    }

    $registro = ModeloTuser::mdlMostrarTuserUno("tuser", "id", $id);
    if(!$registro){
        ajaxResponder(null);
    }

    // Solo quien puede editar/eliminar puede ver el registro para editarlo
    tuserGuardEditarEliminar(intval($registro["id_ticket"] ?? 0));

    ajaxResponder($registro);
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);