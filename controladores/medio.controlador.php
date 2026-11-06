<?php

class ControladorMedio{

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
     * ¿El usuario actual puede ver/operar el módulo de medios?
     *  → Administrador activo  Ó
     *  → Coordinación activa "Centro de Atención" o "Despacho"
     */
    static public function puedeVerModuloMedio(){
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
     * (para eliminar y para el guard de ctrBorrarMedio)
     */
    static public function esAdminMedio(){
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
    CREAR MEDIO (devuelve array JSON)
    =============================================*/
    static public function ctrCrearMedio(){

        if(!isset($_POST["nuevoMedio"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["nuevoMedio"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del medio contiene caracteres no permitidos"];
        }

        $existe = ModeloMedio::mdlMostrarMedio("medio", "medio", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "El medio ya existe, intente con otro nombre"];
        }

        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "medio"        => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloMedio::mdlIngresarMedio("medio", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El medio ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el medio en la base de datos"];
        }
    }

    /*=============================================
    EDITAR MEDIO (devuelve array JSON)
    =============================================*/
    static public function ctrEditarMedio(){

        if(!isset($_POST["editarMedio"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarMedio"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del medio contiene caracteres no permitidos"];
        }

        $existe = ModeloMedio::mdlMostrarMedio("medio", "medio", $nombre);
        if($existe && $existe["id"] != $_POST["idMedio"]){
            return ["status" => "error", "mensaje" => "Ya existe otro medio con ese nombre"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idMedio"],
            "medio"        => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloMedio::mdlEditarMedio("medio", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El medio ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el medio"];
        }
    }

    /*=============================================
    MOSTRAR MEDIOS
    =============================================*/
    static public function ctrMostrarMedio($item, $valor){
        return ModeloMedio::mdlMostrarMedio("medio", $item, $valor);
    }

    /*=============================================
    BORRAR MEDIO — ✅ SOLO ADMINISTRADOR
    =============================================*/
    static public function ctrBorrarMedio(){
        if(isset($_GET["idMedio"])){

            // ✅ Guard: solo Administrador activo
            if (!self::esAdminMedio()) {
                echo '<script>
                    window.location.replace("index.php?ruta=medio");
                </script>';
                exit();
            }

            $respuesta = ModeloMedio::mdlBorrarMedio("medio", $_GET["idMedio"]);

            echo '<script>
                window.location.replace("index.php?ruta=medio");
            </script>';
            exit();
        }
    }
}
?>