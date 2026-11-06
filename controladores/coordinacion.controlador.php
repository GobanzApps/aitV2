<?php

class ControladorCoordinacion{

    /*=============================================
    VALIDAR COLOR HEX
    =============================================*/
    static private function validarColor($color){
        if(!empty($color) && preg_match('/^#[a-fA-F0-9]{6}$/', $color)){
            return $color;
        }
        return "#0d6efd"; // color por defecto (azul bootstrap)
    }

    /*=============================================
    CREAR COORDINACIÓN (devuelve array JSON)
    =============================================*/
    static public function ctrCrearCoordinacion(){

        if(!isset($_POST["nuevoCoordinacion"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        // Validar nombre (letras, números, espacios y tildes)
        $nombre = trim($_POST["nuevoCoordinacion"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre de la coordinación contiene caracteres no permitidos"];
        }

        // Verificar duplicado
        $existe = ModeloCoordinacion::mdlMostrarCoordinacion("coordinacion", "coordinacion", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "La coordinación ya existe, intente con otro nombre"];
        }

        // Color
        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        // Fecha actual
        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Usuario que crea (desde la sesión)
        $idUser = $_SESSION["id"] ?? "";

        $datos = array(
            "coordinacion" => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => "1",
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloCoordinacion::mdlIngresarCoordinacion("coordinacion", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡La coordinación ha sido guardada correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar la coordinación en la base de datos"];
        }
    }

    /*=============================================
    EDITAR COORDINACIÓN (devuelve array JSON)
    =============================================*/
    static public function ctrEditarCoordinacion(){

        if(!isset($_POST["editarCoordinacion"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarCoordinacion"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre de la coordinación contiene caracteres no permitidos"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Al editar, se guarda el id del usuario que edita
        $idUser = $_SESSION["id"] ?? "";

        $datos = array(
            "id"           => $_POST["idCoordinacion"],
            "coordinacion" => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloCoordinacion::mdlEditarCoordinacion("coordinacion", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡La coordinación ha sido editada correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar la coordinación"];
        }
    }

    /*=============================================
    MOSTRAR COORDINACIONES
    =============================================*/
    static public function ctrMostrarCoordinacion($item, $valor){
        return ModeloCoordinacion::mdlMostrarCoordinacion("coordinacion", $item, $valor);
    }

    /*=============================================
    BORRAR COORDINACIÓN
    =============================================*/
    static public function ctrBorrarCoordinacion(){
        if(isset($_GET["idCoordinacion"])){

            $respuesta = ModeloCoordinacion::mdlBorrarCoordinacion("coordinacion", $_GET["idCoordinacion"]);

            // Redirigir siempre a coordinacion limpio
            echo '<script>
                window.location.replace("index.php?ruta=coordinacion");
            </script>';
            exit();
        }
    }
}
?>