<?php

class ControladorStatus{

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
     * ¿El usuario actual puede ver/operar el módulo de status?
     *  → Administrador activo  Ó
     *  → Coordinación activa "Centro de Atención" o "Despacho"
     */
    static public function puedeVerModuloStatus(){
        if (session_status() == PHP_SESSION_NONE) { session_start(); }

        $idUser = (int)($_SESSION["id"] ?? 0);
        if ($idUser <= 0) return false;

        $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if (!$user) return false;

        /* ¿Administrador? */
        $rolesIds = json_decode($user["id_rol"] ?? '[]', true) ?: [];
        foreach ($rolesIds as $idRol) {
            $rol = ModeloRol::mdlMostrarRol("rol", "id", (int)$idRol);
            if ($rol
                && (int)($rol["estado"] ?? 0) === 1
                && strcasecmp(trim($rol["rol"]), "Administrador") === 0) {
                return true;
            }
        }

        /* ¿Coordinación Centro de Atención o Despacho? */
        $coordsIds = json_decode($user["id_coordinacion"] ?? '[]', true) ?: [];
        foreach ($coordsIds as $idC) {
            $coord = ModeloCoordinacion::mdlMostrarCoordinacion("coordinacion", "id", (int)$idC);
            if ($coord && (int)($coord["estado"] ?? 0) === 1) {
                $nombre = mb_strtolower(trim($coord["coordinacion"]));
                if (strpos($nombre, 'centro de atenci') !== false || $nombre === 'despacho') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ¿El usuario actual es Administrador activo?
     * (para eliminar y para el guard de ctrBorrarStatus)
     */
    static public function esAdminStatus(){
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
    CREAR STATUS (devuelve array JSON)
    =============================================*/
    static public function ctrCrearStatus(){

        if(!isset($_POST["nuevoStatus"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["nuevoStatus"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del status contiene caracteres no permitidos"];
        }

        $existe = ModeloStatus::mdlMostrarStatus("status", "status", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "El status ya existe, intente con otro nombre"];
        }

        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "status"       => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloStatus::mdlIngresarStatus("status", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El status ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el status en la base de datos"];
        }
    }

    /*=============================================
    EDITAR STATUS (devuelve array JSON)
    =============================================*/
    static public function ctrEditarStatus(){

        if(!isset($_POST["editarStatus"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarStatus"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del status contiene caracteres no permitidos"];
        }

        $existe = ModeloStatus::mdlMostrarStatus("status", "status", $nombre);
        if($existe && $existe["id"] != $_POST["idStatus"]){
            return ["status" => "error", "mensaje" => "Ya existe otro status con ese nombre"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idStatus"],
            "status"       => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloStatus::mdlEditarStatus("status", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El status ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el status"];
        }
    }

    /*=============================================
    MOSTRAR STATUS
    =============================================*/
    static public function ctrMostrarStatus($item, $valor){
        return ModeloStatus::mdlMostrarStatus("status", $item, $valor);
    }

    /*=============================================
    BORRAR STATUS — ✅ SOLO ADMINISTRADOR
    =============================================*/
    static public function ctrBorrarStatus(){
        if(isset($_GET["idStatus"])){

            // ✅ Guard: solo Administrador activo
            if (!self::esAdminStatus()) {
                echo '<script>
                    window.location.replace("index.php?ruta=status");
                </script>';
                exit();
            }

            $respuesta = ModeloStatus::mdlBorrarStatus("status", $_GET["idStatus"]);

            echo '<script>
                window.location.replace("index.php?ruta=status");
            </script>';
            exit();
        }
    }
}
?>