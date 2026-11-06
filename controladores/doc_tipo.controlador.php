<?php

class ControladorDocTipo{

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
     * ¿El usuario actual puede ver/operar el módulo de tipos de documento?
     *  → Administrador activo  Ó
     *  → Coordinación activa "Centro de Atención" o "Despacho"
     */
    static public function puedeVerModuloDocTipo(){
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
     * (para eliminar y para el guard de ctrBorrarDocTipo)
     */
    static public function esAdminDocTipo(){
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
    CREAR TIPO DE DOCUMENTO (devuelve array JSON)
    =============================================*/
    static public function ctrCrearDocTipo(){

        if(!isset($_POST["nuevoDocTipo"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        // Validar nombre (letras, números, espacios y tildes)
        $nombre = trim($_POST["nuevoDocTipo"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tipo de documento contiene caracteres no permitidos"];
        }

        // Verificar duplicado
        $existe = ModeloDocTipo::mdlMostrarDocTipo("doc_tipo", "doc_tipo", $nombre);
        if($existe){
            return ["status" => "error", "mensaje" => "El tipo de documento ya existe, intente con otro nombre"];
        }

        // Color
        $color = self::validarColor($_POST["nuevoColor"] ?? "");

        // Fecha actual
        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Usuario que crea (desde la sesión)
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "doc_tipo"     => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "estado"       => 1,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloDocTipo::mdlIngresarDocTipo("doc_tipo", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tipo de documento ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el tipo de documento en la base de datos"];
        }
    }

    /*=============================================
    EDITAR TIPO DE DOCUMENTO (devuelve array JSON)
    =============================================*/
    static public function ctrEditarDocTipo(){

        if(!isset($_POST["editarDocTipo"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["editarDocTipo"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre del tipo de documento contiene caracteres no permitidos"];
        }

        // Verificar duplicado (excluyendo el mismo id)
        $existe = ModeloDocTipo::mdlMostrarDocTipo("doc_tipo", "doc_tipo", $nombre);
        if($existe && $existe["id"] != $_POST["idDocTipo"]){
            return ["status" => "error", "mensaje" => "Ya existe otro tipo de documento con ese nombre"];
        }

        $color = self::validarColor($_POST["editarColor"] ?? "");

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        // Al editar, se guarda el id del usuario que edita
        $idUser = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"           => $_POST["idDocTipo"],
            "doc_tipo"     => $nombre,
            "color"        => $color,
            "id_user"      => $idUser,
            "modificacion" => $fechaActual
        );

        $respuesta = ModeloDocTipo::mdlEditarDocTipo("doc_tipo", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El tipo de documento ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el tipo de documento"];
        }
    }

    /*=============================================
    MOSTRAR TIPOS DE DOCUMENTO
    =============================================*/
    static public function ctrMostrarDocTipo($item, $valor){
        return ModeloDocTipo::mdlMostrarDocTipo("doc_tipo", $item, $valor);
    }

    /*=============================================
    BORRAR TIPO DE DOCUMENTO — ✅ SOLO ADMINISTRADOR
    =============================================*/
    static public function ctrBorrarDocTipo(){
        if(isset($_GET["idDocTipo"])){

            // ✅ Guard: solo Administrador activo
            if (!self::esAdminDocTipo()) {
                echo '<script>
                    window.location.replace("index.php?ruta=doc-tipo");
                </script>';
                exit();
            }

            $respuesta = ModeloDocTipo::mdlBorrarDocTipo("doc_tipo", $_GET["idDocTipo"]);

            // Redirigir siempre a doc-tipo limpio
            echo '<script>
                window.location.replace("index.php?ruta=doc-tipo");
            </script>';
            exit();
        }
    }
}
?>