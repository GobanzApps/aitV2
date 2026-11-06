<?php

class ControladorTag{

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
    NORMALIZAR IDS (array o json) → JSON string
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
    ✅ AUTORIZACIÓN
    =============================================*/

    /**
     * ¿El usuario actual puede ver/operar el módulo de tags?
     *  → Administrador activo  Ó
     *  → Coordinación activa "Centro de Atención" o "Despacho"
     */
    static public function puedeVerModuloTag(){
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
     */
    static public function esAdminTag(){
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
    CREAR TAG (devuelve array JSON)
    =============================================*/
    static public function ctrCrearTag(){

        if(!isset($_POST["nuevoTag"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["nuevoTag"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tag contiene caracteres no permitidos"];
        }

        $existe = ModeloTag::mdlMostrarTag("tag", "tag", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "El tag ya existe, intente con otro nombre"];
        }

        // ✅ Tipos de tag (multi-select → JSON)
        $idTagTipo = self::normalizarIdsJson($_POST["nuevoIdTagTipo"] ?? []);

        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "tag"          => $nombre,
            "id_tag_tipo"  => $idTagTipo,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTag::mdlIngresarTag("tag", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tag ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el tag en la base de datos"];
        }
    }

    /*=============================================
    EDITAR TAG (devuelve array JSON)
    =============================================*/
    static public function ctrEditarTag(){

        if(!isset($_POST["editarTag"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarTag"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tag contiene caracteres no permitidos"];
        }

        $existe = ModeloTag::mdlMostrarTag("tag", "tag", $nombre);
        if($existe && $existe["id"] != $_POST["idTag"]){
            return ["status" => "error", "mensaje" => "Ya existe otro tag con ese nombre"];
        }

        // ✅ Tipos de tag (multi-select → JSON)
        $idTagTipo = self::normalizarIdsJson($_POST["editarIdTagTipo"] ?? []);

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idTag"],
            "tag"          => $nombre,
            "id_tag_tipo"  => $idTagTipo,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTag::mdlEditarTag("tag", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tag ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el tag"];
        }
    }

    /*=============================================
    MOSTRAR TAGS
    =============================================*/
    static public function ctrMostrarTag($item, $valor){
        return ModeloTag::mdlMostrarTag("tag", $item, $valor);
    }

    /*=============================================
    BORRAR TAG — ✅ SOLO ADMINISTRADOR
    =============================================*/
    static public function ctrBorrarTag(){
        if(isset($_GET["idTag"])){

            // ✅ Guard: solo Administrador activo
            if (!self::esAdminTag()) {
                echo '<script>
                    window.location.replace("index.php?ruta=tag");
                </script>';
                exit();
            }

            $respuesta = ModeloTag::mdlBorrarTag("tag", $_GET["idTag"]);

            echo '<script>
                window.location.replace("index.php?ruta=tag");
            </script>';
            exit();
        }
    }
}
?>