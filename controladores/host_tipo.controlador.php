<?php

class ControladorHostTipo{

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
    ✅ AUTORIZACIÓN
    =============================================*/

    /**
     * ¿El usuario actual puede ver/operar el módulo de tipos de host?
     *  → Administrador activo  Ó
     *  → Coordinación activa "Soporte" o "Centro de atenci..."
     */
    static public function puedeVerModuloHostTipo(){
        if (session_status() == PHP_SESSION_NONE) { session_start(); }

        $idUser = (int)($_SESSION["id"] ?? 0);
        if ($idUser <= 0) return false;

        $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if (!$user) return false;

        $rolesIds = json_decode($user["id_rol"] ?? '[]', true) ?: [];
        foreach ($rolesIds as $idRol) {
            $rol = ModeloRol::mdlMostrarRol("rol", "id", (int)$idRol);
            if ($rol
                && (int)($rol["estado"] ?? 0) === 1
                && strcasecmp(trim($rol["rol"]), "Administrador") === 0) {
                return true;
            }
        }

        $coordsIds = json_decode($user["id_coordinacion"] ?? '[]', true) ?: [];
        foreach ($coordsIds as $idC) {
            $coord = ModeloCoordinacion::mdlMostrarCoordinacion("coordinacion", "id", (int)$idC);
            if ($coord && (int)($coord["estado"] ?? 0) === 1) {
                $nombre = mb_strtolower(trim($coord["coordinacion"]));
                if ($nombre === 'soporte' || strpos($nombre, 'centro de atenci') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ¿El usuario actual es Administrador activo?
     */
    static public function esAdminHostTipo(){
        if (session_status() == PHP_SESSION_NONE) { session_start(); }

        $idUser = (int)($_SESSION["id"] ?? 0);
        if ($idUser <= 0) return false;

        $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if (!$user) return false;

        $rolesIds = json_decode($user["id_rol"] ?? '[]', true) ?: [];
        foreach ($rolesIds as $idRol) {
            $rol = ModeloRol::mdlMostrarRol("rol", "id", (int)$idRol);
            if ($rol
                && (int)($rol["estado"] ?? 0) === 1
                && strcasecmp(trim($rol["rol"]), "Administrador") === 0) {
                return true;
            }
        }
        return false;
    }

    /*=============================================
    CREAR TIPO DE HOST (devuelve array JSON)
    =============================================*/
    static public function ctrCrearHostTipo(){

        if(!isset($_POST["nuevoHostTipo"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["nuevoHostTipo"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tipo de host contiene caracteres no permitidos"];
        }

        $existe = ModeloHostTipo::mdlMostrarHostTipo("host_tipo", "host_tipo", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "El tipo de host ya existe, intente con otro nombre"];
        }

        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "host_tipo"    => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloHostTipo::mdlIngresarHostTipo("host_tipo", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tipo de host ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el tipo de host en la base de datos"];
        }
    }

    /*=============================================
    EDITAR TIPO DE HOST (devuelve array JSON)
    =============================================*/
    static public function ctrEditarHostTipo(){

        if(!isset($_POST["editarHostTipo"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarHostTipo"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tipo de host contiene caracteres no permitidos"];
        }

        $existe = ModeloHostTipo::mdlMostrarHostTipo("host_tipo", "host_tipo", $nombre);
        if($existe && $existe["id"] != $_POST["idHostTipo"]){
            return ["status" => "error", "mensaje" => "Ya existe otro tipo de host con ese nombre"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idHostTipo"],
            "host_tipo"    => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloHostTipo::mdlEditarHostTipo("host_tipo", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tipo de host ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el tipo de host"];
        }
    }

    /*=============================================
    MOSTRAR TIPOS DE HOST
    =============================================*/
    static public function ctrMostrarHostTipo($item, $valor){
        return ModeloHostTipo::mdlMostrarHostTipo("host_tipo", $item, $valor);
    }

    /*=============================================
    BORRAR TIPO DE HOST — solo Administrador
    =============================================*/
    static public function ctrBorrarHostTipo(){
        if(isset($_GET["idHostTipo"])){

            // ✅ Guard: solo Administrador activo
            if (!self::esAdminHostTipo()) {
                echo '<script>
                    window.location.replace("index.php?ruta=host-tipo");
                </script>';
                exit();
            }

            $respuesta = ModeloHostTipo::mdlBorrarHostTipo("host_tipo", $_GET["idHostTipo"]);

            echo '<script>
                window.location.replace("index.php?ruta=host-tipo");
            </script>';
            exit();
        }
    }
}
?>