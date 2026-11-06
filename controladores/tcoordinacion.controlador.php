<?php

class ControladorTcoordinacion{

    /*=============================================
    Helper: normaliza array/JSON de IDs → JSON string
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
    CREAR (asignar coordinaciones a un ticket)
    =============================================*/
    static public function ctrCrearTcoordinacion(){

        if(!isset($_POST["nuevoTcoordinacion"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }
        if(!isset($_POST["idTicket"]) || intval($_POST["idTicket"]) <= 0){
            return ["status" => "error", "mensaje" => "Ticket no válido"];
        }

        $idCoordinacion = self::normalizarIdsJson($_POST["nuevoIdCoordinacion"] ?? []);
        $decoded = json_decode($idCoordinacion, true);
        if(empty($decoded)){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos una coordinación"];
        }

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Usuario que crea (desde la sesión)
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "tcoordinacion"   => $idUser,
            "id_ticket"       => intval($_POST["idTicket"]),
            "id_coordinacion" => $idCoordinacion,
            "modificacion"    => $fechaActual
        );

        $respuesta = ModeloTcoordinacion::mdlIngresarTcoordinacion("tcoordinacion", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡Coordinaciones asignadas correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo guardar en la base de datos"];
    }

    /*=============================================
    EDITAR (actualizar coordinaciones asignadas)
    =============================================*/
    static public function ctrEditarTcoordinacion(){

        if(!isset($_POST["editarTcoordinacion"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }
        if(!isset($_POST["idTcoordinacion"]) || intval($_POST["idTcoordinacion"]) <= 0){
            return ["status" => "error", "mensaje" => "Registro no válido"];
        }

        $idCoordinacion = self::normalizarIdsJson($_POST["editarIdCoordinacion"] ?? []);
        $decoded = json_decode($idCoordinacion, true);
        if(empty($decoded)){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos una coordinación"];
        }

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $datos = array(
            "id"              => intval($_POST["idTcoordinacion"]),
            "id_coordinacion" => $idCoordinacion,
            "modificacion"    => $fechaActual
        );

        $respuesta = ModeloTcoordinacion::mdlEditarTcoordinacion("tcoordinacion", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡Asignación actualizada correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo actualizar"];
    }

    /*=============================================
    MOSTRAR (todos o filtrados)
    =============================================*/
    static public function ctrMostrarTcoordinacion($item, $valor){
        return ModeloTcoordinacion::mdlMostrarTcoordinacion("tcoordinacion", $item, $valor);
    }

    /*=============================================
    MOSTRAR POR TICKET (helper usado en la vista)
    =============================================*/
    static public function ctrMostrarTcoordinacionPorTicket($idTicket){
        return ModeloTcoordinacion::mdlMostrarTcoordinacion("tcoordinacion", "id_ticket", $idTicket);
    }

    /*=============================================
    MOSTRAR UNO (por id)
    =============================================*/
    static public function ctrMostrarTcoordinacionUno($item, $valor){
        return ModeloTcoordinacion::mdlMostrarTcoordinacionUno("tcoordinacion", $item, $valor);
    }

    /*=============================================
    BORRAR
    =============================================*/
    static public function ctrBorrarTcoordinacion(){
        if(isset($_POST["borrarTcoordinacion"]) && isset($_POST["idTcoordinacion"])){
            $respuesta = ModeloTcoordinacion::mdlBorrarTcoordinacion("tcoordinacion", intval($_POST["idTcoordinacion"]));
            if($respuesta == "ok"){
                return ["status" => "success", "mensaje" => "Eliminado correctamente"];
            }
            return ["status" => "error", "mensaje" => "No se pudo eliminar"];
        }
        return ["status" => "error", "mensaje" => "Datos incompletos"];
    }
}
?>