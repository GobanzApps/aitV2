<?php

class ControladorEntidad{

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
     * ¿El usuario actual puede ver/operar el módulo de entidades?
     *  → Administrador activo  Ó
     *  → Coordinación activa "Centro de Atención" o "Despacho"
     */
    static public function puedeVerModuloEntidad(){
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
     * (para eliminar y para el guard de ctrBorrarEntidad)
     */
    static public function esAdminEntidad(){
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
    CREAR ENTIDAD (devuelve array JSON)
    =============================================*/
    static public function ctrCrearEntidad(){

        if(!isset($_POST["nuevaEntidad"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        // Validar nombre
        $nombre = trim($_POST["nuevaEntidad"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre de la entidad contiene caracteres no permitidos"];
        }

        // Verificar duplicado
        $existe = ModeloEntidad::mdlMostrarEntidad("entidad", "entidad", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "La entidad ya existe, intente con otro nombre"];
        }

        // Tipos de entidad (multi-select → JSON)
        $tipoEntidad = self::normalizarIdsJson($_POST["nuevoTipoEntidad"] ?? []);

        // Color
        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        // Fecha actual
        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Usuario que crea (desde la sesión)
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "entidad"      => $nombre,
            "tipo_entidad" => $tipoEntidad,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloEntidad::mdlIngresarEntidad("entidad", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡La entidad ha sido guardada correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar la entidad en la base de datos"];
        }
    }

    /*=============================================
    EDITAR ENTIDAD (devuelve array JSON)
    =============================================*/
    static public function ctrEditarEntidad(){

        if(!isset($_POST["editarEntidad"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarEntidad"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre de la entidad contiene caracteres no permitidos"];
        }

        // Verificar duplicado (excluyendo el mismo id)
        $existe = ModeloEntidad::mdlMostrarEntidad("entidad", "entidad", $nombre);
        if($existe && $existe["id"] != $_POST["idEntidad"]){
            return ["status" => "error", "mensaje" => "Ya existe otra entidad con ese nombre"];
        }

        // Tipos de entidad (multi-select → JSON)
        $tipoEntidad = self::normalizarIdsJson($_POST["editarTipoEntidad"] ?? []);

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idEntidad"],
            "entidad"      => $nombre,
            "tipo_entidad" => $tipoEntidad,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloEntidad::mdlEditarEntidad("entidad", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡La entidad ha sido editada correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar la entidad"];
        }
    }

    /*=============================================
    MOSTRAR ENTIDADES
    =============================================*/
    static public function ctrMostrarEntidad($item, $valor){
        return ModeloEntidad::mdlMostrarEntidad("entidad", $item, $valor);
    }

    /*=============================================
    BORRAR ENTIDAD — ✅ SOLO ADMINISTRADOR
    =============================================*/
    static public function ctrBorrarEntidad(){
        if(isset($_GET["idEntidad"])){

            // ✅ Guard: solo Administrador activo
            if (!self::esAdminEntidad()) {
                echo '<script>
                    window.location.replace("index.php?ruta=entidad");
                </script>';
                exit();
            }

            $respuesta = ModeloEntidad::mdlBorrarEntidad("entidad", $_GET["idEntidad"]);

            echo '<script>
                window.location.replace("index.php?ruta=entidad");
            </script>';
            exit();
        }
    }
}
?>