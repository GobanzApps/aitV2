<?php

class ControladorTuser{

    /*=============================================
    Normaliza un array/JSON de IDs → JSON string
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

    /*=============================================
    CREAR
    =============================================*/
    static public function ctrCrearTuser(){

        if(!isset($_POST["nuevoTuser"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $idTicket = intval($_POST["idTicket"] ?? 0);
        if($idTicket <= 0){
            return ["status" => "error", "mensaje" => "Ticket no válido"];
        }

        $idUsers  = self::normalizarIdsJson($_POST["nuevoIdUser"] ?? []);
        $arrUsers = json_decode($idUsers, true);
        if(empty($arrUsers)){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos un usuario"];
        }

        // Usuario que crea la asignación
        $creador = $_SESSION["id"] ?? "";
        if($creador === ""){
            return ["status" => "error", "mensaje" => "Sesión no válida"];
        }

        date_default_timezone_set('America/Caracas');
        $fechaActual = date('Y-m-d H:i:s');

        $datos = array(
            "tuser"        => (string)$creador,
            "id_ticket"    => (string)$idTicket,
            "id_user"      => $idUsers,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTuser::mdlIngresarTuser("tuser", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡Usuarios asignados correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo guardar la asignación"];
    }

    /*=============================================
    EDITAR
    =============================================*/
    static public function ctrEditarTuser(){

        if(!isset($_POST["editarTuser"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $id = intval($_POST["idTuser"] ?? 0);
        if($id <= 0){
            return ["status" => "error", "mensaje" => "ID inválido"];
        }

        $idUsers  = self::normalizarIdsJson($_POST["editarIdUser"] ?? []);
        $arrUsers = json_decode($idUsers, true);
        if(empty($arrUsers)){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos un usuario"];
        }

        date_default_timezone_set('America/Caracas');
        $fechaActual = date('Y-m-d H:i:s');

        $datos = array(
            "id"           => $id,
            "id_user"      => $idUsers,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTuser::mdlEditarTuser("tuser", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡Asignación actualizada correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo actualizar la asignación"];
    }

    /*=============================================
    MOSTRAR (filtrado)
    =============================================*/
    static public function ctrMostrarTuser($item, $valor){
        return ModeloTuser::mdlMostrarTuser("tuser", $item, $valor);
    }

    /*=============================================
    MOSTRAR UNO
    =============================================*/
    static public function ctrMostrarTuserUno($id){
        return ModeloTuser::mdlMostrarTuserUno("tuser", "id", $id);
    }

    /*=============================================
    MOSTRAR POR TICKET
    =============================================*/
    static public function ctrMostrarTuserPorTicket($idTicket){
        return ModeloTuser::mdlMostrarTuser("tuser", "id_ticket", $idTicket);
    }

    /*=============================================
    BORRAR
    =============================================*/
    static public function ctrBorrarTuser($id){
        return ModeloTuser::mdlBorrarTuser("tuser", $id);
    }
}
?>