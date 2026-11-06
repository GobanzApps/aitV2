<?php

class ControladorTevidencia{

    /*=============================================
    RUTA ABSOLUTA A /vistas/img/evidencias
    =============================================*/
    static private function rutaEvidencias(){
        $dir = realpath(__DIR__ . "/../") . DIRECTORY_SEPARATOR . "vistas" . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "evidencias";
        if(!file_exists($dir)){
            if(!@mkdir($dir, 0755, true) && !is_dir($dir)){
                return false;
            }
        }
        return $dir;
    }

    /*=============================================
    MOSTRAR POR TICKET
    =============================================*/
    static public function ctrMostrarTevidenciaPorTicket($idTicket){
        return ModeloTevidencia::mdlMostrarTevidencia("tevidencia", "id_ticket", $idTicket);
    }

    /*=============================================
    MOSTRAR UNO
    =============================================*/
    static public function ctrMostrarTevidenciaUno($id){
        return ModeloTevidencia::mdlMostrarTevidenciaUno("tevidencia", "id", $id);
    }

    /*=============================================
    CONTAR POR TICKET
    =============================================*/
    static public function ctrContarTevidenciaPorTicket($idTicket){
        return ModeloTevidencia::mdlContarTevidenciaPorTicket("tevidencia", $idTicket);
    }

    /*=============================================
    CREAR EVIDENCIA (sube imagen)
    =============================================*/
    static public function ctrCrearTevidencia(){

        if(!isset($_POST["nuevoTevidencia"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $idTicket = intval($_POST["idTicket"] ?? 0);
        if($idTicket <= 0){
            return ["status" => "error", "mensaje" => "Ticket no válido"];
        }

        // ── Límite: máximo 2 imágenes por ticket
        $total = self::ctrContarTevidenciaPorTicket($idTicket);
        if($total >= 2){
            return [
                "status"  => "error",
                "mensaje" => "Límite alcanzado: máximo 2 evidencias por ticket. Elimine una para subir otra."
            ];
        }

        // ── Debe venir un archivo
        if(!isset($_FILES["nuevaFoto"]) || $_FILES["nuevaFoto"]["error"] !== UPLOAD_ERR_OK){
            return ["status" => "error", "mensaje" => "Debe seleccionar una imagen"];
        }

        $archivo = $_FILES["nuevaFoto"];

        // ── Tamaño máximo 5 MB
        if($archivo["size"] > 5 * 1024 * 1024){
            return ["status" => "error", "mensaje" => "La imagen supera los 5 MB permitidos"];
        }

        // ── Validar que sea imagen real
        $info = @getimagesize($archivo["tmp_name"]);
        if($info === false){
            return ["status" => "error", "mensaje" => "El archivo no es una imagen válida"];
        }

        // ── Solo extensiones de imagen permitidas
        $permitidas = [
            "image/jpeg" => "jpg",
            "image/jpg"  => "jpg",
            "image/pjpeg"=> "jpg",
            "image/png"  => "png",
            "image/gif"  => "gif",
            "image/webp" => "webp"
        ];
        $mime = $archivo["type"];
        if(!isset($permitidas[$mime])){
            return ["status" => "error", "mensaje" => "Solo se permiten imágenes JPG, PNG, GIF o WEBP"];
        }
        $extension = $permitidas[$mime];

        // ── Directorio destino
        $directorio = self::rutaEvidencias();
        if($directorio === false){
            return ["status" => "error", "mensaje" => "No se pudo crear la carpeta de evidencias"];
        }

        // ── Nombre: 10 chars random + _ + id_ticket + _ + Ymd + _ + His + .ext
        //    Ejemplo: fdebfdcdfb_11_20260508_095821.jpg
        $random   = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 10);
        $fechaYmd = date("Ymd");
        $horaHis  = date("His");
        $nombreArchivo = $random . "_" . $idTicket . "_" . $fechaYmd . "_" . $horaHis . "." . $extension;

        $rutaAbs = $directorio . DIRECTORY_SEPARATOR . $nombreArchivo;

        if(!move_uploaded_file($archivo["tmp_name"], $rutaAbs)){
            return ["status" => "error", "mensaje" => "No se pudo guardar la imagen en el servidor"];
        }

        $rutaBD = "vistas/img/evidencias/" . $nombreArchivo;

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "tevidencia" => (string)$idUser,
            "id_ticket"  => (string)$idTicket,
            "foto"       => $rutaBD
        );

        $respuesta = ModeloTevidencia::mdlIngresarTevidencia("tevidencia", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡Evidencia guardada correctamente!"];
        }

        // Si falla la BD, eliminar el archivo subido para no dejar basura
        if(file_exists($rutaAbs)) @unlink($rutaAbs);
        return ["status" => "error", "mensaje" => "No se pudo guardar el registro en la base de datos"];
    }

    /*=============================================
    BORRAR EVIDENCIA (archivo + registro)
    =============================================*/
    static public function ctrBorrarTevidencia($id){

        $registro = ModeloTevidencia::mdlMostrarTevidenciaUno("tevidencia", "id", $id);
        if(!$registro){
            return ["status" => "error", "mensaje" => "Registro no encontrado"];
        }

        // ── Eliminar archivo físico
        if(!empty($registro["foto"])){
            $rutaAbs = realpath(__DIR__ . "/../") . DIRECTORY_SEPARATOR
                     . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $registro["foto"]);
            if(file_exists($rutaAbs) && is_file($rutaAbs)){
                @unlink($rutaAbs);
            }
        }

        $respuesta = ModeloTevidencia::mdlBorrarTevidencia("tevidencia", $id);
        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "Evidencia eliminada correctamente"];
        }
        return ["status" => "error", "mensaje" => "No se pudo eliminar el registro"];
    }
}