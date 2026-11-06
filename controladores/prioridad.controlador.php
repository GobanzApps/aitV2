<?php

class ControladorPrioridad{

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
     * ¿El usuario actual puede ver/operar el módulo de prioridades?
     *  → Administrador activo  Ó
     *  → Coordinación activa "Centro de Atención" o "Despacho"
     */
    static public function puedeVerModuloPrioridad(){
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
     * (para eliminar y para el guard de ctrBorrarPrioridad)
     */
    static public function esAdminPrioridad(){
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
    CREAR PRIORIDAD (devuelve array JSON)
    =============================================*/
    static public function ctrCrearPrioridad(){

        if(!isset($_POST["nuevaPrioridad"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["nuevaPrioridad"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre de la prioridad contiene caracteres no permitidos"];
        }

        $existe = ModeloPrioridad::mdlMostrarPrioridad("prioridad", "prioridad", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "La prioridad ya existe, intente con otro nombre"];
        }

        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "prioridad"    => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloPrioridad::mdlIngresarPrioridad("prioridad", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡La prioridad ha sido guardada correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar la prioridad en la base de datos"];
        }
    }

    /*=============================================
    EDITAR PRIORIDAD (devuelve array JSON)
    =============================================*/
    static public function ctrEditarPrioridad(){

        if(!isset($_POST["editarPrioridad"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarPrioridad"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre de la prioridad contiene caracteres no permitidos"];
        }

        $existe = ModeloPrioridad::mdlMostrarPrioridad("prioridad", "prioridad", $nombre);
        if($existe && $existe["id"] != $_POST["idPrioridad"]){
            return ["status" => "error", "mensaje" => "Ya existe otra prioridad con ese nombre"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idPrioridad"],
            "prioridad"    => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloPrioridad::mdlEditarPrioridad("prioridad", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡La prioridad ha sido editada correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar la prioridad"];
        }
    }

    /*=============================================
    MOSTRAR PRIORIDADES
    =============================================*/
    static public function ctrMostrarPrioridad($item, $valor){
        return ModeloPrioridad::mdlMostrarPrioridad("prioridad", $item, $valor);
    }

    /*=============================================
    BORRAR PRIORIDAD — ✅ SOLO ADMINISTRADOR
    =============================================*/
    static public function ctrBorrarPrioridad(){
        if(isset($_GET["idPrioridad"])){

            // ✅ Guard: solo Administrador activo
            if (!self::esAdminPrioridad()) {
                echo '<script>
                    window.location.replace("index.php?ruta=prioridad");
                </script>';
                exit();
            }

            $respuesta = ModeloPrioridad::mdlBorrarPrioridad("prioridad", $_GET["idPrioridad"]);

            echo '<script>
                window.location.replace("index.php?ruta=prioridad");
            </script>';
            exit();
        }
    }
}
?>