<?php

class ControladorRol{

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
    CREAR ROL (devuelve array JSON)
    =============================================*/
    static public function ctrCrearRol(){

        if(!isset($_POST["nuevoRol"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        // Validar nombre (letras, números, espacios y tildes)
        $nombre = trim($_POST["nuevoRol"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del rol contiene caracteres no permitidos"];
        }

        // Verificar duplicado
        $existe = ModeloRol::mdlMostrarRol("rol", "rol", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "El rol ya existe, intente con otro nombre"];
        }

        // Color
        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        // Fecha actual
        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Usuario que crea (desde la sesión)
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "rol"          => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloRol::mdlIngresarRol("rol", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El rol ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el rol en la base de datos"];
        }
    }

    /*=============================================
    EDITAR ROL (devuelve array JSON)
    =============================================*/
    static public function ctrEditarRol(){

        if(!isset($_POST["editarRol"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarRol"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del rol contiene caracteres no permitidos"];
        }

        // Verificar duplicado (excluyendo el mismo id)
        $existe = ModeloRol::mdlMostrarRol("rol", "rol", $nombre);
        if($existe && $existe["id"] != $_POST["idRol"]){
            return ["status" => "error", "mensaje" => "Ya existe otro rol con ese nombre"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Al editar, se guarda el id del usuario que edita
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idRol"],
            "rol"          => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloRol::mdlEditarRol("rol", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El rol ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el rol"];
        }
    }

    /*=============================================
    MOSTRAR ROLES
    =============================================*/
    static public function ctrMostrarRol($item, $valor){
        return ModeloRol::mdlMostrarRol("rol", $item, $valor);
    }

    /*=============================================
    BORRAR ROL
    =============================================*/
    static public function ctrBorrarRol(){
        if(isset($_GET["idRol"])){

            $respuesta = ModeloRol::mdlBorrarRol("rol", $_GET["idRol"]);

            // Redirigir siempre a rol limpio
            echo '<script>
                window.location.replace("index.php?ruta=rol");
            </script>';
            exit();
        }
    }
}
?>