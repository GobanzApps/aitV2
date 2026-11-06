<?php

class ControladorTagTipo{

    /*=============================================
    VALIDAR COLOR HEX
    =============================================*/
    static private function validarColor($color){
        if(!empty($color) && preg_match('/^#[a-fA-F0-9]{6}$/', $color)){
            return $color;
        }
        return "#0d6efd";
    }

    /*=============================================
    VALIDAR DESCRIPCIÓN (opcional, máx. 500)
    =============================================*/
    static private function validarDescripcion($descripcion){
        $descripcion = trim($descripcion ?? '');
        if($descripcion === '') return '';
        if(mb_strlen($descripcion) > 500){
            return false;
        }
        return $descripcion;
    }

    /*=============================================
    CREAR TIPO DE TAG (devuelve array JSON)
    =============================================*/
    static public function ctrCrearTagTipo(){

        if(!isset($_POST["nuevoTagTipo"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        // Validar nombre (letras, números, espacios y tildes)
        $nombre = trim($_POST["nuevoTagTipo"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tipo de tag contiene caracteres no permitidos"];
        }

        // Verificar duplicado
        $existe = ModeloTagTipo::mdlMostrarTagTipo("tag_tipo", "tag_tipo", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "El tipo de tag ya existe, intente con otro nombre"];
        }

        // ✅ Descripción (opcional)
        $descripcion = self::validarDescripcion($_POST["nuevoDescripcion"] ?? '');
        if($descripcion === false){
            return ["status" => "error", "mensaje" => "La descripción no puede superar los 500 caracteres"];
        }

        // Color
        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        // Fecha actual
        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Usuario que crea (desde la sesión)
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "tag_tipo"     => $nombre,
            "descripcion"  => $descripcion,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTagTipo::mdlIngresarTagTipo("tag_tipo", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tipo de tag ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el tipo de tag en la base de datos"];
        }
    }

    /*=============================================
    EDITAR TIPO DE TAG (devuelve array JSON)
    =============================================*/
    static public function ctrEditarTagTipo(){

        if(!isset($_POST["editarTagTipo"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarTagTipo"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tipo de tag contiene caracteres no permitidos"];
        }

        // Verificar duplicado (excluyendo el mismo id)
        $existe = ModeloTagTipo::mdlMostrarTagTipo("tag_tipo", "tag_tipo", $nombre);
        if($existe && $existe["id"] != $_POST["idTagTipo"]){
            return ["status" => "error", "mensaje" => "Ya existe otro tipo de tag con ese nombre"];
        }

        // ✅ Descripción (opcional)
        $descripcion = self::validarDescripcion($_POST["editarDescripcion"] ?? '');
        if($descripcion === false){
            return ["status" => "error", "mensaje" => "La descripción no puede superar los 500 caracteres"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Al editar, se guarda el id del usuario que edita
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idTagTipo"],
            "tag_tipo"     => $nombre,
            "descripcion"  => $descripcion,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTagTipo::mdlEditarTagTipo("tag_tipo", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tipo de tag ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el tipo de tag"];
        }
    }

    /*=============================================
    MOSTRAR TIPOS DE TAG
    =============================================*/
    static public function ctrMostrarTagTipo($item, $valor){
        return ModeloTagTipo::mdlMostrarTagTipo("tag_tipo", $item, $valor);
    }

    /*=============================================
    BORRAR TIPO DE TAG — solo Administrador
    =============================================*/
    static public function ctrBorrarTagTipo(){
        if(isset($_GET["idTagTipo"])){

            $respuesta = ModeloTagTipo::mdlBorrarTagTipo("tag_tipo", $_GET["idTagTipo"]);

            // Redirigir siempre a tag-tipo limpio
            echo '<script>
                window.location.replace("index.php?ruta=tag-tipo");
            </script>';
            exit();
        }
    }
}
?>