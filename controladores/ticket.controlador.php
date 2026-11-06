<?php

class ControladorTicket{

    /*=============================================
    AUTORIZACIÓN / PERMISOS
    =============================================*/
    static private $authCacheTicket = null;

    static public function authTicket(){
        if (self::$authCacheTicket !== null) return self::$authCacheTicket;

        $idUser = (int)($_SESSION["id"] ?? 0);
        if ($idUser <= 0) { return self::$authCacheTicket = false; }

        $userData = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if (!$userData) { return self::$authCacheTicket = false; }

        /* Roles */
        $rolesIds = json_decode($userData["id_rol"] ?? '[]', true);
        if (!is_array($rolesIds)) $rolesIds = [];
        $rolesIds = array_map('intval', $rolesIds);

        $todosRoles = ModeloRol::mdlMostrarRol("rol", null, null) ?: [];
        $mapaRoles  = [];
        foreach ($todosRoles as $r) { $mapaRoles[(int)$r["id"]] = $r; }

        $rolesNombres = [];
        foreach ($rolesIds as $idR) {
            if (isset($mapaRoles[$idR]) && (int)$mapaRoles[$idR]["estado"] === 1) {
                $rolesNombres[] = mb_strtolower(trim($mapaRoles[$idR]["rol"]));
            }
        }

        /* Coordinaciones */
        $coordsIds = json_decode($userData["id_coordinacion"] ?? '[]', true);
        if (!is_array($coordsIds)) $coordsIds = [];
        $coordsIds = array_map('intval', $coordsIds);

        $todasCoords = ModeloCoordinacion::mdlMostrarCoordinacion("coordinacion", null, null) ?: [];
        $mapaCoords  = [];
        foreach ($todasCoords as $c) { $mapaCoords[(int)$c["id"]] = $c; }

        $coordsNombres = [];
        foreach ($coordsIds as $idC) {
            if (isset($mapaCoords[$idC]) && (int)$mapaCoords[$idC]["estado"] === 1) {
                $coordsNombres[] = mb_strtolower(trim($mapaCoords[$idC]["coordinacion"]));
            }
        }

        return self::$authCacheTicket = [
            "id"            => $idUser,
            "userData"      => $userData,
            "rolesIds"      => $rolesIds,
            "rolesNombres"  => $rolesNombres,
            "coordsIds"     => $coordsIds,
            "coordsNombres" => $coordsNombres,
        ];
    }

    static public function esAdminTicket($auth = null){
        $auth = $auth ?: self::authTicket();
        if (!$auth) return false;
        return in_array('administrador', $auth['rolesNombres'], true);
    }

    static public function esCoordinadorTicket($auth = null){
        $auth = $auth ?: self::authTicket();
        if (!$auth) return false;
        return in_array('coordinador', $auth['rolesNombres'], true);
    }

    static public function esEspecialistaTicket($auth = null){
        $auth = $auth ?: self::authTicket();
        if (!$auth) return false;
        foreach (['especialista', 'tecnico', 'técnico'] as $r) {
            if (in_array($r, $auth['rolesNombres'], true)) return true;
        }
        return false;
    }

    static public function esUsuarioTicket($auth = null){
        $auth = $auth ?: self::authTicket();
        if (!$auth) return false;
        return in_array('usuario', $auth['rolesNombres'], true);
    }

    static public function esCentroAtencionTicket($auth = null){
        $auth = $auth ?: self::authTicket();
        if (!$auth) return false;
        foreach ($auth['coordsNombres'] as $c) {
            if (strpos($c, 'centro de atenci') !== false) return true;
        }
        return false;
    }

    static public function esDespachoTicket($auth = null){
        $auth = $auth ?: self::authTicket();
        if (!$auth) return false;
        foreach ($auth['coordsNombres'] as $c) {
            if ($c === 'despacho') return true;
        }
        return false;
    }

    /*=============================================
    ¿El usuario está asignado vía tuser?
    =============================================*/
    static public function estaAsignadoAlTicket($idTicket, $auth = null){
        $auth = $auth ?: self::authTicket();
        if (!$auth) return false;

        $idTicket = (int)$idTicket;
        if ($idTicket <= 0) return false;

        $tusers = ModeloTuser::mdlMostrarTuser("tuser", "id_ticket", $idTicket);
        if (!$tusers || !is_array($tusers)) return false;

        foreach ($tusers as $tu) {
            $ids = json_decode($tu["id_user"] ?? '[]', true);
            if (!is_array($ids)) $ids = [];
            foreach ($ids as $idU) {
                if ((int)$idU === (int)$auth['id']) return true;
            }
        }
        return false;
    }

    /*=============================================
    ¿Alguna coordinación del usuario está asignada al ticket?
    =============================================*/
    static public function coordinacionAsignadaAlTicket($idTicket, $auth = null){
        $auth = $auth ?: self::authTicket();
        if (!$auth) return false;

        $idTicket = (int)$idTicket;
        if ($idTicket <= 0) return false;

        $tcoords = ModeloTcoordinacion::mdlMostrarTcoordinacion("tcoordinacion", "id_ticket", $idTicket);
        if (!$tcoords || !is_array($tcoords)) return false;

        foreach ($tcoords as $tc) {
            $ids = json_decode($tc["id_coordinacion"] ?? '[]', true);
            if (!is_array($ids)) $ids = [];
            foreach ($ids as $idC) {
                if (in_array((int)$idC, $auth['coordsIds'], true)) return true;
            }
        }
        return false;
    }

    /*=============================================
    AUTORIZACIÓN PARA VER UN TICKET ESPECÍFICO
    =============================================*/
    static public function puedeVerTicketEspecifico($idTicket, $auth = null){
        $auth = $auth ?: self::authTicket();
        if (!$auth) return ["ok" => false, "msg" => "Sesión no válida"];

        $idTicket = (int)$idTicket;
        if ($idTicket <= 0) return ["ok" => false, "msg" => "Ticket no válido"];

        $ticket = ModeloTicket::mdlMostrarTicket("ticket", "id", $idTicket);
        if (!$ticket) return ["ok" => false, "msg" => "Ticket no encontrado"];

        $tieneRolBase = self::esAdminTicket($auth)
                     || self::esCoordinadorTicket($auth)
                     || self::esUsuarioTicket($auth)
                     || self::esEspecialistaTicket($auth);
        if (!$tieneRolBase) {
            return ["ok" => false, "msg" => "No tienes permisos para ver tickets"];
        }

        $esSoporte = self::esAdminTicket($auth) || self::esCentroAtencionTicket($auth);

        if ((int)($ticket["check"] ?? 0) === 1) {
            return ["ok" => true];
        }

        if ((int)($ticket["estado"] ?? 0) !== 1) {
            if ($esSoporte) return ["ok" => true];
            return ["ok" => false, "msg" => "El ticket está inactivo y no se puede abrir."];
        }

        if ($esSoporte) return ["ok" => true];

        if (self::estaAsignadoAlTicket($idTicket, $auth)) {
            return ["ok" => true];
        }

        if (self::esCoordinadorTicket($auth) && self::coordinacionAsignadaAlTicket($idTicket, $auth)) {
            return ["ok" => true];
        }

        return [
            "ok"  => false,
            "msg" => "No tienes permiso para abrir este ticket. Debes estar asignado al ticket o (si eres Coordinador) tener tu coordinación asignada."
        ];
    }

    static public function puedeVerSeccionTickets($auth = null){
        $auth = $auth ?: self::authTicket();
        return self::esAdminTicket($auth)
            || self::esCoordinadorTicket($auth)
            || self::esUsuarioTicket($auth)
            || self::esEspecialistaTicket($auth);
    }

    static public function puedeModificarTickets($auth = null){
        $auth = $auth ?: self::authTicket();
        return self::esAdminTicket($auth) || self::esCentroAtencionTicket($auth);
    }

    static public function puedeEliminarTickets($auth = null){
        $auth = $auth ?: self::authTicket();
        return self::esAdminTicket($auth);
    }

    static public function puedeVerTodosLosTickets($auth = null){
        $auth = $auth ?: self::authTicket();
        return self::esAdminTicket($auth)
            || self::esCentroAtencionTicket($auth)
            || self::esDespachoTicket($auth);
    }

    /*=============================================
    HELPERS INTERNOS
    =============================================*/
    static private function normalizarIdsJson($valor){
        if(is_array($valor)){
            $ids = array_values(array_filter(array_map('intval', $valor), function($v){ return $v > 0; }));
            return json_encode($ids);
        }
        if(is_string($valor) && $valor !== ''){
            $decoded = json_decode($valor, true);
            if(is_array($decoded)){
                $ids = array_values(array_filter(array_map('intval', $decoded), function($v){ return $v > 0; }));
                return json_encode($ids);
            }
        }
        return json_encode([]);
    }

    static private function validarSolicitante($texto){
        $texto = trim($texto ?? '');
        if($texto === ''){ return false; }
        if(!preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_\'\/&]+$/u', $texto)){ return false; }
        return $texto;
    }

    static private function obtenerUltimoStatusNombre($idTicket){
        $idTicket = intval($idTicket);
        if($idTicket <= 0) return null;

        $historial = ControladorTstatus::ctrMostrarTstatusPorTicket($idTicket);
        if(!$historial || !is_array($historial) || count($historial) === 0){ return null; }

        $idStatus = (int)($historial[0]["id_status"] ?? 0);
        if($idStatus <= 0) return null;

        $statusData = ControladorStatus::ctrMostrarStatus("id", $idStatus);
        if(!$statusData) return null;

        return trim($statusData["status"]);
    }

    static private function contarServiciosTicket($idTicket){
        $idTicket = intval($idTicket);
        if($idTicket <= 0) return 0;

        $servicios = ControladorTservicio::ctrMostrarTservicioPorTicket($idTicket);
        return ($servicios && is_array($servicios)) ? count($servicios) : 0;
    }

    /*=============================================
    CREAR TICKET
    ✅ Devuelve idTicket para que el front redirija
    =============================================*/
    static public function ctrCrearTicket(){
        if(!isset($_POST["nuevoTicket"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $solicitante = self::validarSolicitante($_POST["nuevoSolicitante"] ?? '');
        if($solicitante === false){
            return ["status" => "error", "mensaje" => "El solicitante está vacío o contiene caracteres no permitidos"];
        }

        $descripcion = trim($_POST["nuevoDescripcion"] ?? '');
        if($descripcion === ''){
            return ["status" => "error", "mensaje" => "La descripción es obligatoria"];
        }
        if(mb_strlen($descripcion) > 5000){
            return ["status" => "error", "mensaje" => "La descripción es demasiado larga (máx. 5000 caracteres)"];
        }

        $idEntidad   = self::normalizarIdsJson($_POST["nuevoIdEntidad"]   ?? []);
        $idMedio     = self::normalizarIdsJson($_POST["nuevoIdMedio"]     ?? []);
        $idPrioridad = self::normalizarIdsJson($_POST["nuevoIdPrioridad"] ?? []);

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? "";

        $datos = array(
            "id_entidad"   => $idEntidad,
            "solicitante"  => $solicitante,
            "id_medio"     => $idMedio,
            "id_prioridad" => $idPrioridad,
            "descripcion"  => $descripcion,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTicket::mdlIngresarTicket("ticket", $datos);

        // ✅ mdlIngresarTicket ahora devuelve el ID (string) o false
        if($respuesta !== false && $respuesta !== null){
            return [
                "status"   => "success",
                "mensaje"  => "¡El ticket ha sido guardado correctamente!",
                "idTicket" => (int)$respuesta
            ];
        }
        return ["status" => "error", "mensaje" => "No se pudo guardar el ticket en la base de datos"];
    }

    /*=============================================
    EDITAR TICKET (listado tickets.php)
    =============================================*/
    static public function ctrEditarTicket(){
        if(!isset($_POST["editarTicket"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $solicitante = self::validarSolicitante($_POST["editarSolicitante"] ?? '');
        if($solicitante === false){
            return ["status" => "error", "mensaje" => "El solicitante está vacío o contiene caracteres no permitidos"];
        }

        $descripcion = trim($_POST["editarDescripcion"] ?? '');
        if($descripcion === ''){
            return ["status" => "error", "mensaje" => "La descripción es obligatoria"];
        }
        if(mb_strlen($descripcion) > 5000){
            return ["status" => "error", "mensaje" => "La descripción es demasiado larga (máx. 5000 caracteres)"];
        }

        $idEntidad   = self::normalizarIdsJson($_POST["editarIdEntidad"]   ?? []);
        $idMedio     = self::normalizarIdsJson($_POST["editarIdMedio"]     ?? []);
        $idPrioridad = self::normalizarIdsJson($_POST["editarIdPrioridad"] ?? []);

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? "";

        $datos = array(
            "id"           => $_POST["idTicket"],
            "id_entidad"   => $idEntidad,
            "solicitante"  => $solicitante,
            "id_medio"     => $idMedio,
            "id_prioridad" => $idPrioridad,
            "descripcion"  => $descripcion,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTicket::mdlEditarTicket("ticket", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El ticket ha sido editado correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo editar el ticket"];
    }

    /*=============================================
    EDITAR TICKET desde el DETALLE
    =============================================*/
    static public function ctrEditarTicketDetalle(){
        if(!isset($_POST["editarTicketDetalle"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $id = intval($_POST["idTicket"] ?? 0);
        if($id <= 0){
            return ["status" => "error", "mensaje" => "ID de ticket inválido"];
        }

        $solicitante = self::validarSolicitante($_POST["detalleSolicitante"] ?? '');
        if($solicitante === false){
            return ["status" => "error", "mensaje" => "El solicitante está vacío o contiene caracteres no permitidos"];
        }

        $descripcion = trim($_POST["detalleDescripcion"] ?? '');
        if($descripcion === ''){
            return ["status" => "error", "mensaje" => "La descripción es obligatoria"];
        }
        if(mb_strlen($descripcion) > 5000){
            return ["status" => "error", "mensaje" => "La descripción es demasiado larga (máx. 5000 caracteres)"];
        }

        $estado = intval($_POST["detalleEstado"] ?? 1);
        if(!in_array($estado, [0, 1], true)){
            return ["status" => "error", "mensaje" => "Estado inválido"];
        }

        $idEntidad   = self::normalizarIdsJson($_POST["detalleIdEntidad"]   ?? []);
        $idMedio     = self::normalizarIdsJson($_POST["detalleIdMedio"]     ?? []);
        $idPrioridad = self::normalizarIdsJson($_POST["detalleIdPrioridad"] ?? []);

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? "";

        $datos = array(
            "id"           => $id,
            "id_entidad"   => $idEntidad,
            "solicitante"  => $solicitante,
            "id_medio"     => $idMedio,
            "id_prioridad" => $idPrioridad,
            "descripcion"  => $descripcion,
            "estado"       => $estado,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTicket::mdlEditarTicketDetalle("ticket", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El ticket ha sido actualizado correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo actualizar el ticket"];
    }

    /*=============================================
    CAMBIAR CHECK (Abierto / Finalizado)
    =============================================*/
    static public function ctrCambiarCheckTicket($id, $check){
        $id    = intval($id);
        $check = intval($check);

        if($id <= 0 || !in_array($check, [0, 1], true)){
            return ["status" => "error", "mensaje" => "Datos inválidos"];
        }

        if($check === 1){
            $ultimoStatusNombre = self::obtenerUltimoStatusNombre($id);
            $statusesPermitidos = ["resuelto", "suspendido"];
            $ultimoNormalizado  = mb_strtolower(trim($ultimoStatusNombre ?? ''));

            if(!in_array($ultimoNormalizado, $statusesPermitidos, true)){
                return [
                    "status"  => "error",
                    "mensaje" => "No se puede finalizar el ticket. El último status debe ser 'Resuelto' o 'Suspendido'. Actualmente es: "
                               . ($ultimoStatusNombre ?: "sin status registrado")
                ];
            }

            if($ultimoNormalizado === "resuelto"){
                $totalServicios = self::contarServiciosTicket($id);
                if($totalServicios <= 0){
                    return [
                        "status"  => "error",
                        "mensaje" => "No se puede finalizar el ticket. El último status es 'Resuelto' y debe existir al menos 1 servicio registrado antes de finalizar."
                    ];
                }
            }
        }

        $resp = ModeloTicket::mdlActualizarTicket("ticket", "check", $check, "id", $id);

        if($resp == "ok"){
            return [
                "status"  => "success",
                "mensaje" => $check === 1 ? "Ticket finalizado correctamente" : "Ticket reabierto correctamente",
                "check"   => $check
            ];
        }
        return ["status" => "error", "mensaje" => "No se pudo actualizar el check"];
    }

    /*=============================================
    MOSTRAR TICKETS
    =============================================*/
    static public function ctrMostrarTicket($item, $valor){
        return ModeloTicket::mdlMostrarTicket("ticket", $item, $valor);
    }

    /*=============================================
    BORRAR TICKET (Solo Administrador)
    =============================================*/
    static public function ctrBorrarTicket(){
        if(isset($_GET["idTicket"])){

            if (!self::puedeEliminarTickets()) {
                echo '<script>window.location.replace("index.php?ruta=tickets");</script>';
                exit();
            }

            ModeloTicket::mdlBorrarTicket("ticket", $_GET["idTicket"]);

            echo '<script>window.location.replace("index.php?ruta=tickets");</script>';
            exit();
        }
    }
}
?>