<?php

class ControladorTservicio{

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
    CREAR
    =============================================*/
    static public function ctrCrearTservicio(){

        if(!isset($_POST["nuevoTservicio"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $idTicket = intval($_POST["idTicket"] ?? 0);
        if($idTicket <= 0){
            return ["status" => "error", "mensaje" => "Ticket no válido"];
        }

        /* ── Tags (obligatorio, multi-select) ── */
        $idTags  = self::normalizarIdsJson($_POST["nuevoIdTag"] ?? []);
        $arrTags = json_decode($idTags, true);
        if(empty($arrTags)){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos un tag"];
        }

        /* ── Hosts (OPCIONAL, multi-select) ── */
        $idHosts = self::normalizarIdsJson($_POST["nuevoIdHost"] ?? []);

        /* ── Cantidad (number, obligatorio) ── */
        $cantidad = intval($_POST["nuevoCantidad"] ?? 0);
        if($cantidad <= 0){
            return ["status" => "error", "mensaje" => "La cantidad debe ser mayor a 0"];
        }

        /* ── Descripción (Quill HTML, obligatorio) ── */
        $descripcion = trim($_POST["nuevoDescripcion"] ?? '');
        if($descripcion === '' || $descripcion === '<p><br></p>'){
            return ["status" => "error", "mensaje" => "La descripción es obligatoria"];
        }
        if(mb_strlen(strip_tags($descripcion)) > 5000){
            return ["status" => "error", "mensaje" => "La descripción es demasiado larga (máx. 5000 caracteres)"];
        }

        /* ── Usuario creador ── */
        $creador = $_SESSION["id"] ?? "";
        if($creador === ""){
            return ["status" => "error", "mensaje" => "Sesión no válida"];
        }

        date_default_timezone_set('America/Caracas');
        $fechaActual = date('Y-m-d H:i:s');

        $datos = array(
            "tservicio"    => (string)$creador,
            "id_ticket"    => (string)$idTicket,
            "id_tag"       => $idTags,
            "cantidad"     => (string)$cantidad,
            "id_host"      => $idHosts,
            "descripcion"  => $descripcion,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTservicio::mdlIngresarTservicio("tservicio", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡Servicio registrado correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo guardar el registro"];
    }

    /*=============================================
    EDITAR
    =============================================*/
    static public function ctrEditarTservicio(){

        if(!isset($_POST["editarTservicio"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $id = intval($_POST["idTservicio"] ?? 0);
        if($id <= 0){
            return ["status" => "error", "mensaje" => "ID inválido"];
        }

        $idTags  = self::normalizarIdsJson($_POST["editarIdTag"] ?? []);
        $arrTags = json_decode($idTags, true);
        if(empty($arrTags)){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos un tag"];
        }

        $idHosts = self::normalizarIdsJson($_POST["editarIdHost"] ?? []);

        $cantidad = intval($_POST["editarCantidad"] ?? 0);
        if($cantidad <= 0){
            return ["status" => "error", "mensaje" => "La cantidad debe ser mayor a 0"];
        }

        $descripcion = trim($_POST["editarDescripcion"] ?? '');
        if($descripcion === '' || $descripcion === '<p><br></p>'){
            return ["status" => "error", "mensaje" => "La descripción es obligatoria"];
        }

        date_default_timezone_set('America/Caracas');
        $fechaActual = date('Y-m-d H:i:s');

        $datos = array(
            "id"           => $id,
            "id_tag"       => $idTags,
            "cantidad"     => (string)$cantidad,
            "id_host"      => $idHosts,
            "descripcion"  => $descripcion,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTservicio::mdlEditarTservicio("tservicio", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡Servicio actualizado correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo actualizar el registro"];
    }

    /*=============================================
    MOSTRAR (filtrado)
    =============================================*/
    static public function ctrMostrarTservicio($item, $valor){
        return ModeloTservicio::mdlMostrarTservicio("tservicio", $item, $valor);
    }

    /*=============================================
    MOSTRAR UNO
    =============================================*/
    static public function ctrMostrarTservicioUno($id){
        return ModeloTservicio::mdlMostrarTservicioUno("tservicio", "id", $id);
    }

    /*=============================================
    MOSTRAR POR TICKET
    =============================================*/
    static public function ctrMostrarTservicioPorTicket($idTicket){
        return ModeloTservicio::mdlMostrarTservicio("tservicio", "id_ticket", $idTicket);
    }

    /*=============================================
    BORRAR
    =============================================*/
    static public function ctrBorrarTservicio($id){
        return ModeloTservicio::mdlBorrarTservicio("tservicio", $id);
    }
}