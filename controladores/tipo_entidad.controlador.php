<?php

class ControladorTipoEntidad{

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
     * ¿El usuario actual puede ver/operar el módulo de tipos de entidad?
     *  → Administrador activo  Ó
     *  → Coordinación activa "Centro de Atención" o "Despacho"
     */
    static public function puedeVerModuloTipoEntidad(){
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
     * (para eliminar y para el guard de ctrBorrarTipoEntidad)
     */
    static public function esAdminTipoEntidad(){
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
    CREAR TIPO DE ENTIDAD (devuelve array JSON)
    =============================================*/
    static public function ctrCrearTipoEntidad(){

        if(!isset($_POST["nuevoTipoEntidad"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        // Validar nombre (letras, números, espacios y tildes)
        $nombre = trim($_POST["nuevoTipoEntidad"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tipo de entidad contiene caracteres no permitidos"];
        }

        // Verificar duplicado
        $existe = ModeloTipoEntidad::mdlMostrarTipoEntidad("tipo_entidad", "tipo_entidad", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "El tipo de entidad ya existe, intente con otro nombre"];
        }

        // Color
        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        // Fecha actual
        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Usuario que crea (desde la sesión)
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "tipo_entidad" => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTipoEntidad::mdlIngresarTipoEntidad("tipo_entidad", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tipo de entidad ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el tipo de entidad en la base de datos"];
        }
    }

    /*=============================================
    EDITAR TIPO DE ENTIDAD (devuelve array JSON)
    =============================================*/
    static public function ctrEditarTipoEntidad(){

        if(!isset($_POST["editarTipoEntidad"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarTipoEntidad"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tipo de entidad contiene caracteres no permitidos"];
        }

        // Verificar duplicado (excluyendo el mismo id)
        $existe = ModeloTipoEntidad::mdlMostrarTipoEntidad("tipo_entidad", "tipo_entidad", $nombre);
        if($existe && $existe["id"] != $_POST["idTipoEntidad"]){
            return ["status" => "error", "mensaje" => "Ya existe otro tipo de entidad con ese nombre"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Al editar, se guarda el id del usuario que edita
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idTipoEntidad"],
            "tipo_entidad" => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloTipoEntidad::mdlEditarTipoEntidad("tipo_entidad", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tipo de entidad ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el tipo de entidad"];
        }
    }

    /*=============================================
    MOSTRAR TIPOS DE ENTIDAD
    =============================================*/
    static public function ctrMostrarTipoEntidad($item, $valor){
        return ModeloTipoEntidad::mdlMostrarTipoEntidad("tipo_entidad", $item, $valor);
    }

    /*=============================================
    BORRAR TIPO DE ENTIDAD — ✅ SOLO ADMINISTRADOR
    =============================================*/
    static public function ctrBorrarTipoEntidad(){
        if(isset($_GET["idTipoEntidad"])){

            // ✅ Guard: solo Administrador activo
            if (!self::esAdminTipoEntidad()) {
                echo '<script>
                    window.location.replace("index.php?ruta=tipoEntidad");
                </script>';
                exit();
            }

            $respuesta = ModeloTipoEntidad::mdlBorrarTipoEntidad("tipo_entidad", $_GET["idTipoEntidad"]);

            // Redirigir siempre a tipoEntidad limpio
            echo '<script>
                window.location.replace("index.php?ruta=tipoEntidad");
            </script>';
            exit();
        }
    }
}
?>