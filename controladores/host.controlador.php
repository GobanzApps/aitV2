<?php

class ControladorHost{

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
    VALIDAR ID (single select)
    =============================================*/
    static private function validarIdSingle($valor){
        if(is_array($valor)) $valor = reset($valor);
        $valor = trim((string)$valor);
        if($valor === '' || !preg_match('/^[0-9]+$/', $valor)){
            return false;
        }
        return (int)$valor;
    }

    /*=============================================
    ✅ AUTORIZACIÓN
    =============================================*/

    /**
     * ¿El usuario actual puede ver/operar el módulo de hosts?
     *  → Administrador activo  Ó
     *  → Coordinación activa "Soporte" o que contenga "Centro de atenci"
     */
    static public function puedeVerModuloHost(){
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

        /* ¿Coordinación Soporte / Centro de Atención? */
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
    static public function esAdminHost(){
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
    CREAR HOST
    =============================================*/
    static public function ctrCrearHost(){

        if(!isset($_POST["nuevoHost"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["nuevoHost"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_\:]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del host contiene caracteres no permitidos"];
        }

        $existe = ModeloHost::mdlMostrarHost("host", "host", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "El host ya existe, intente con otro nombre"];
        }

        $idHostTipo = self::validarIdSingle($_POST["nuevoIdHostTipo"] ?? '');
        if($idHostTipo === false){
            return ["status" => "error", "mensaje" => "Debe seleccionar un tipo de host válido"];
        }

        $idEntidad = self::validarIdSingle($_POST["nuevoIdEntidad"] ?? '');
        if($idEntidad === false){
            return ["status" => "error", "mensaje" => "Debe seleccionar una entidad válida"];
        }

        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "host"         => $nombre,
            "id_host_tipo" => (string)$idHostTipo,
            "id_entidad"   => (string)$idEntidad,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloHost::mdlIngresarHost("host", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El host ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el host en la base de datos"];
        }
    }

    /*=============================================
    EDITAR HOST
    =============================================*/
    static public function ctrEditarHost(){

        if(!isset($_POST["editarHost"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarHost"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_\:]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del host contiene caracteres no permitidos"];
        }

        $existe = ModeloHost::mdlMostrarHost("host", "host", $nombre);
        if($existe && $existe["id"] != $_POST["idHost"]){
            return ["status" => "error", "mensaje" => "Ya existe otro host con ese nombre"];
        }

        $idHostTipo = self::validarIdSingle($_POST["editarIdHostTipo"] ?? '');
        if($idHostTipo === false){
            return ["status" => "error", "mensaje" => "Debe seleccionar un tipo de host válido"];
        }

        $idEntidad = self::validarIdSingle($_POST["editarIdEntidad"] ?? '');
        if($idEntidad === false){
            return ["status" => "error", "mensaje" => "Debe seleccionar una entidad válida"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idHost"],
            "host"         => $nombre,
            "id_host_tipo" => (string)$idHostTipo,
            "id_entidad"   => (string)$idEntidad,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloHost::mdlEditarHost("host", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El host ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el host"];
        }
    }

    /*=============================================
    MOSTRAR HOSTS
    =============================================*/
    static public function ctrMostrarHost($item, $valor){
        return ModeloHost::mdlMostrarHost("host", $item, $valor);
    }

    /*=============================================
    BORRAR HOST — solo Administrador
    =============================================*/
    static public function ctrBorrarHost(){
        if(isset($_GET["idHost"])){

            // ✅ Guard: solo Administrador activo
            if (!self::esAdminHost()) {
                echo '<script>
                    window.location.replace("index.php?ruta=hosts");
                </script>';
                exit();
            }

            $respuesta = ModeloHost::mdlBorrarHost("host", $_GET["idHost"]);

            echo '<script>
                window.location.replace("index.php?ruta=hosts");
            </script>';
            exit();
        }
    }
}
?>