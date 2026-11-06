<?php

class ControladorTstatus{

    /*=============================================
    CREAR
    =============================================*/
    static public function ctrCrearTstatus(){

        if(!isset($_POST["nuevoTstatus"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $idTicket = intval($_POST["idTicket"] ?? 0);
        if($idTicket <= 0){
            return ["status" => "error", "mensaje" => "Ticket no válido"];
        }

        $idStatus = intval($_POST["nuevoIdStatus"] ?? 0);
        if($idStatus <= 0){
            return ["status" => "error", "mensaje" => "Debe seleccionar un status"];
        }

        /* ── Datos del status seleccionado ── */
        $statusData   = ModeloStatus::mdlMostrarStatus("status", "id", $idStatus);
        $statusNombre = $statusData ? trim($statusData["status"]) : '';

        /* ── ¿Es un status notificable? (Nuevo / Reemplazo) ── */
        $esNotificable = $statusData && in_array($statusNombre, ["Nuevo", "Reemplazo"]);

        /* ── El comentario es OBLIGATORIO excepto para:
              "Suspendido", "En Proceso" y "Nuevo" ── */
        $statusesSinComentario = ["suspendido", "en proceso", "nuevo"];
        $comentarioRequerido   = !in_array(mb_strtolower($statusNombre), $statusesSinComentario, true);

        $idUserNotif = null;
        $check       = 0;
        $estado      = 1; // por defecto activo (no aplica a no-notificables)

        if($esNotificable){
            $idUserNotif = intval($_POST["nuevoIdUserNotif"] ?? 0);
            if($idUserNotif <= 0){
                return ["status" => "error", "mensaje" => "Debe seleccionar el usuario a notificar"];
            }
            $estado = 0; // notificación pendiente → aparece en la bandeja
        }

        // ⬇️ Comentario
        $comentario = trim($_POST["nuevoComentario"] ?? '');

        if($comentarioRequerido && $comentario === ''){
            return [
                "status"  => "error",
                "mensaje" => "El comentario es obligatorio para el status \"" . $statusNombre . "\". " .
                             "Solo se omite en \"Suspendido\", \"En Proceso\" y \"Nuevo\"."
            ];
        }
        if($comentario !== '' && mb_strlen($comentario) > 2000){
            return ["status" => "error", "mensaje" => "El comentario es demasiado largo (máx. 2000 caracteres)"];
        }

        $creador = $_SESSION["id"] ?? "";
        if($creador === ""){
            return ["status" => "error", "mensaje" => "Sesión no válida"];
        }

        date_default_timezone_set('America/Caracas');
        $fechaActual = date('Y-m-d H:i:s');

        /* fecha        → creación (inmutable)
           modificacion → fecha del comentario (al crear coincide con fecha, al editar cambia) */
        $datos = array(
            "tstatus"      => (string)$creador,
            "id_ticket"    => (string)$idTicket,
            "id_status"    => $idStatus,
            "comentario"   => $comentario,
            "id_user"      => $idUserNotif,
            "check"        => $check,
            "estado"       => $estado,
            "fecha"        => $fechaActual,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTstatus::mdlIngresarTstatus("tstatus", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡Status registrado correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo guardar el registro"];
    }

    /*=============================================
    EDITAR
    =============================================*/
    static public function ctrEditarTstatus(){

        if(!isset($_POST["editarTstatus"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $id = intval($_POST["idTstatus"] ?? 0);
        if($id <= 0){
            return ["status" => "error", "mensaje" => "ID inválido"];
        }

        $idStatus = intval($_POST["editarIdStatus"] ?? 0);
        if($idStatus <= 0){
            return ["status" => "error", "mensaje" => "Debe seleccionar un status"];
        }

        /* ── Datos del status seleccionado ── */
        $statusData   = ModeloStatus::mdlMostrarStatus("status", "id", $idStatus);
        $statusNombre = $statusData ? trim($statusData["status"]) : '';

        /* ── El comentario es OBLIGATORIO excepto para:
              "Suspendido", "En Proceso" y "Nuevo" ── */
        $statusesSinComentario = ["suspendido", "en proceso", "nuevo"];
        $comentarioRequerido   = !in_array(mb_strtolower($statusNombre), $statusesSinComentario, true);

        $comentario = trim($_POST["editarComentario"] ?? '');

        if($comentarioRequerido && $comentario === ''){
            return [
                "status"  => "error",
                "mensaje" => "El comentario es obligatorio para el status \"" . $statusNombre . "\". " .
                             "Solo se omite en \"Suspendido\", \"En Proceso\" y \"Nuevo\"."
            ];
        }
        if($comentario !== '' && mb_strlen($comentario) > 2000){
            return ["status" => "error", "mensaje" => "El comentario es demasiado largo (máx. 2000 caracteres)"];
        }

        date_default_timezone_set('America/Caracas');
        $fechaActual = date('Y-m-d H:i:s');

        // ⚠️ "fecha" NO se envía → se conserva la original de creación
        $datos = array(
            "id"           => $id,
            "id_status"    => $idStatus,
            "comentario"   => $comentario,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTstatus::mdlEditarTstatus("tstatus", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡Registro actualizado correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo actualizar el registro"];
    }

    /*=============================================
    MOSTRAR (filtrado)
    =============================================*/
    static public function ctrMostrarTstatus($item, $valor){
        return ModeloTstatus::mdlMostrarTstatus("tstatus", $item, $valor);
    }

    /*=============================================
    MOSTRAR UNO
    =============================================*/
    static public function ctrMostrarTstatusUno($id){
        return ModeloTstatus::mdlMostrarTstatusUno("tstatus", "id", $id);
    }

    /*=============================================
    MOSTRAR POR TICKET
    =============================================*/
    static public function ctrMostrarTstatusPorTicket($idTicket){
        return ModeloTstatus::mdlMostrarTstatus("tstatus", "id_ticket", $idTicket);
    }

    /*=============================================
    BORRAR
    =============================================*/
    static public function ctrBorrarTstatus($id){
        return ModeloTstatus::mdlBorrarTstatus("tstatus", $id);
    }

    /*=============================================
    ✅ LISTAR NOTIFICACIONES PENDIENTES
    =============================================*/
    static public function ctrListarNotificacionesPendientes($idUser){
        if($idUser <= 0) return [];
        return ModeloTstatus::mdlListarNotificacionesPendientes("tstatus", $idUser);
    }

    /*=============================================
    ✅ ACEPTAR NOTIFICACIÓN
    =============================================*/
    static public function ctrAceptarNotificacion($idTstatus){

        $idUser = $_SESSION["id"] ?? 0;
        if($idUser <= 0){
            return ["status" => "error", "mensaje" => "Sesión no válida"];
        }

        $registro = ModeloTstatus::mdlMostrarTstatusUno("tstatus", "id", $idTstatus);
        if(!$registro){
            return ["status" => "error", "mensaje" => "Notificación no encontrada"];
        }

        if((int)$registro["id_user"] !== (int)$idUser){
            return ["status" => "error", "mensaje" => "No tiene permiso para aceptar esta notificación"];
        }

        $idTicket = intval($registro["id_ticket"]);
        if($idTicket <= 0){
            return ["status" => "error", "mensaje" => "Ticket no válido"];
        }

        /* ── Verificar si ya está asignado en tuser ── */
        $tusers     = ModeloTuser::mdlMostrarTuser("tuser", "id_ticket", $idTicket);
        $yaAsignado = false;

        if($tusers && is_array($tusers)){
            foreach($tusers as $tu){
                $ids = json_decode($tu["id_user"], true);
                if(is_array($ids) && in_array((int)$idUser, array_map('intval', $ids), true)){
                    $yaAsignado = true;
                    break;
                }
            }
        }

        /* ── Si no está asignado, crear registro en tuser ── */
        if(!$yaAsignado){
            date_default_timezone_set('America/Caracas');
            $fechaActual = date('Y-m-d H:i:s');

            $datosTuser = array(
                "tuser"        => (string)$idUser,
                "id_ticket"    => (string)$idTicket,
                "id_user"      => json_encode([(int)$idUser]),
                "modificacion" => $fechaActual
            );
            ModeloTuser::mdlIngresarTuser("tuser", $datosTuser);
        }

        /* ── Marcar la notificación como leída ── */
        ModeloTstatus::mdlMarcarNotificacionLeida("tstatus", $idTstatus);

        return [
            "status"   => "success",
            "mensaje"  => "Ticket #".$idTicket." aceptado y asignado correctamente",
            "idTicket" => $idTicket
        ];
    }

    /*=============================================
    ✅ CAMBIAR ESTADO (activar/desactivar)
    =============================================*/
    static public function ctrCambiarEstadoTstatus($id, $estado){
        $id     = intval($id);
        $estado = intval($estado);

        if($id <= 0 || !in_array($estado, [0,1], true)){
            return ["status" => "error", "mensaje" => "Datos inválidos"];
        }

        $resp = ModeloTstatus::mdlActualizarEstadoTstatus("tstatus", $id, $estado);

        if($resp == "ok"){
            return ["status" => "success", "mensaje" => "Estado actualizado", "estado" => $estado];
        }
        return ["status" => "error", "mensaje" => "No se pudo actualizar el estado"];
    }
}
?>