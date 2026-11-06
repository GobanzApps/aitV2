<?php

class ControladorUser{

    /*=============================================
    Helper: ruta absoluta a la carpeta de fotos
    =============================================*/
    static private function rutaUsuarios(){
        return realpath(__DIR__ . "/../") . DIRECTORY_SEPARATOR . "vistas" . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "usuarios";
    }

    /*=============================================
    Helper: normaliza un array de IDs a JSON string
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
    Helper: valida el tema permitido
    =============================================*/
    static private function validarTema($tema){
        $permitidos = ['light', 'dark', 'auto'];
        return in_array($tema, $permitidos, true) ? $tema : 'light';
    }

    /*=============================================
    Helper: valida y limpia el nombre completo
    =============================================*/
    static private function validarName($name){
        $name = trim($name ?? '');
        if($name === ''){
            return '';
        }
        if(!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-\']+$/u', $name)){
            return false;
        }
        return $name;
    }

    /*=============================================
    Helper: valida email
    =============================================*/
    static private function validarEmail($email){
        $email = trim($email ?? '');
        if($email === ''){
            return false;
        }
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return false;
        }
        return $email;
    }

    /*=============================================
    Helper: valida teléfono (permite +, espacios, guiones, paréntesis)
    =============================================*/
    static private function validarPhone($phone){
        $phone = trim($phone ?? '');
        if($phone === ''){
            return '';
        }
        if(!preg_match('/^[0-9\+\s\-\(\)]{6,25}$/', $phone)){
            return false;
        }
        return $phone;
    }

    /*=============================================
    ✅ HELPER: ¿el usuario tiene el rol X activo?
    =============================================*/
    static private function tieneRolActivo($user, $nombreRol){
        $rolesIds = json_decode($user["id_rol"] ?? '[]', true) ?: [];
        $nombreRol = mb_strtolower(trim($nombreRol));
        foreach ($rolesIds as $idRol) {
            $rol = ModeloRol::mdlMostrarRol("rol", "id", (int)$idRol);
            if ($rol
                && (int)($rol["estado"] ?? 0) === 1
                && mb_strtolower(trim($rol["rol"])) === $nombreRol) {
                return true;
            }
        }
        return false;
    }

    /*=============================================
    ✅ HELPER: ¿el usuario tiene rol Especialista/Técnico activo?
    Acepta los sinónimos con y sin tilde.
    =============================================*/
    static private function tieneRolEspecialista($user){
        $rolesIds = json_decode($user["id_rol"] ?? '[]', true) ?: [];
        $sinonimos = ['especialista', 'tecnico', 'técnico'];
        foreach ($rolesIds as $idRol) {
            $rol = ModeloRol::mdlMostrarRol("rol", "id", (int)$idRol);
            if ($rol
                && (int)($rol["estado"] ?? 0) === 1
                && in_array(mb_strtolower(trim($rol["rol"])), $sinonimos, true)) {
                return true;
            }
        }
        return false;
    }

    /*=============================================
    ✅ HELPER: ¿alguna coordinación activa es "Centro de Atención"?
    =============================================*/
    static private function tieneCoordinacionCentroAtencion($coordsIds){
        if (empty($coordsIds)) return false;
        foreach ($coordsIds as $idC) {
            $coord = ModeloCoordinacion::mdlMostrarCoordinacion("coordinacion", "id", (int)$idC);
            if ($coord && (int)($coord["estado"] ?? 0) === 1) {
                $nombre = mb_strtolower(trim($coord["coordinacion"]));
                if (strpos($nombre, 'centro de atenci') !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /*=============================================
    ✅ AUTORIZACIÓN DE MÓDULO
    =============================================*/

    /**
     * ¿El usuario actual es Administrador activo?
     * Se usa para permitir CRUD y para el guard de ctrBorrarUser().
     */
    static public function esAdminUserModulo(){
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        $idUser = (int)($_SESSION["id"] ?? 0);
        if ($idUser <= 0) return false;

        $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if (!$user) return false;

        return self::tieneRolActivo($user, 'Administrador');
    }

    /**
     * ¿El usuario actual puede acceder al listado users.php?
     *  → Administrador
     *  → Coordinación Centro de Atención
     *  → Rol Coordinador
     *  → Rol Especialista/Técnico    ← NUEVO
     */
    static public function puedeVerListadoUsers(){
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        $idUser = (int)($_SESSION["id"] ?? 0);
        if ($idUser <= 0) return false;

        $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if (!$user) return false;

        if (self::tieneRolActivo($user, 'Administrador')) return true;

        $coordsIds = json_decode($user["id_coordinacion"] ?? '[]', true) ?: [];
        if (self::tieneCoordinacionCentroAtencion($coordsIds)) return true;

        if (self::tieneRolActivo($user, 'Coordinador'))    return true;
        if (self::tieneRolEspecialista($user))             return true;   // ← NUEVO

        return false;
    }

    /**
     * ¿El usuario actual puede ver el detalle de $idObjetivo?
     *  → Administrador                       : cualquiera
     *  → Coordinación Centro de Atención     : cualquiera EXCEPTO Administradores
     *  → Rol Coordinador   ó Especialista    : solo usuarios con misma coordinación
     */
    static public function puedeVerUsuario($idObjetivo){
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        $idUserActual = (int)($_SESSION["id"] ?? 0);
        $idObjetivo   = (int)$idObjetivo;

        if ($idUserActual <= 0 || $idObjetivo <= 0) return false;

        $userActual = ModeloUser::mdlMostrarUser("user", "id", $idUserActual);
        if (!$userActual) return false;

        /* 1.1 Administrador → ve TODO */
        if (self::tieneRolActivo($userActual, 'Administrador')) return true;

        $userObjetivo = ModeloUser::mdlMostrarUser("user", "id", $idObjetivo);
        if (!$userObjetivo) return false;

        $objetivoEsAdmin = self::tieneRolActivo($userObjetivo, 'Administrador');

        $coordsActual = array_map('intval', json_decode($userActual["id_coordinacion"] ?? '[]', true) ?: []);

        /* 1.2 Centro de Atención → todo menos Administradores */
        if (self::tieneCoordinacionCentroAtencion($coordsActual)) {
            return !$objetivoEsAdmin;
        }

        /* 1.3 Coordinador  ó  1.4 Especialista/Técnico → solo misma coordinación */
        if (self::tieneRolActivo($userActual, 'Coordinador') || self::tieneRolEspecialista($userActual)) {
            $coordsObjetivo = array_map('intval', json_decode($userObjetivo["id_coordinacion"] ?? '[]', true) ?: []);
            return !empty(array_intersect($coordsActual, $coordsObjetivo));
        }

        return false;
    }

    /**
     * Devuelve SOLO los usuarios visibles para el listado users.php
     * aplicando las mismas reglas que puedeVerUsuario().
     */
    static public function usuariosVisiblesParaListado(){
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        $idUserActual = (int)($_SESSION["id"] ?? 0);
        if ($idUserActual <= 0) return [];

        $userActual = ModeloUser::mdlMostrarUser("user", "id", $idUserActual);
        if (!$userActual) return [];

        $todosUsuarios = ModeloUser::mdlMostrarUser("user", null, null) ?: [];

        /* Precarga de roles activos (id => nombre_normalizado) */
        $todosRoles = ModeloRol::mdlMostrarRol("rol", null, null) ?: [];
        $mapaRolesActivos = [];
        foreach ($todosRoles as $r) {
            if ((int)($r["estado"] ?? 0) === 1) {
                $mapaRolesActivos[(int)$r["id"]] = mb_strtolower(trim($r["rol"]));
            }
        }

        /* Precarga de coordinaciones activas (id => nombre_normalizado) */
        $todasCoords = ModeloCoordinacion::mdlMostrarCoordinacion("coordinacion", null, null) ?: [];
        $mapaCoordsActivas = [];
        foreach ($todasCoords as $c) {
            if ((int)($c["estado"] ?? 0) === 1) {
                $mapaCoordsActivas[(int)$c["id"]] = mb_strtolower(trim($c["coordinacion"]));
            }
        }

        $tieneRolMapa = function($user, $nombreRol) use ($mapaRolesActivos) {
            $ids = json_decode($user["id_rol"] ?? '[]', true) ?: [];
            $nombreRol = mb_strtolower($nombreRol);
            foreach ($ids as $idR) {
                if (isset($mapaRolesActivos[(int)$idR]) && $mapaRolesActivos[(int)$idR] === $nombreRol) {
                    return true;
                }
            }
            return false;
        };

        /* Cierre local: cualquier sinónimo de Especialista/Técnico */
        $sinonimosEspecialista = ['especialista', 'tecnico', 'técnico'];
        $tieneEspecialistaMapa = function($user) use ($mapaRolesActivos, $sinonimosEspecialista) {
            $ids = json_decode($user["id_rol"] ?? '[]', true) ?: [];
            foreach ($ids as $idR) {
                if (isset($mapaRolesActivos[(int)$idR])
                    && in_array($mapaRolesActivos[(int)$idR], $sinonimosEspecialista, true)) {
                    return true;
                }
            }
            return false;
        };

        $tieneCentroAtencion = function($coordsIds) use ($mapaCoordsActivas) {
            foreach ($coordsIds as $idC) {
                if (isset($mapaCoordsActivas[(int)$idC])
                    && strpos($mapaCoordsActivas[(int)$idC], 'centro de atenci') !== false) {
                    return true;
                }
            }
            return false;
        };

        $esAdminActual    = $tieneRolMapa($userActual, 'administrador');
        $coordsActual     = array_map('intval', json_decode($userActual["id_coordinacion"] ?? '[]', true) ?: []);
        $esCentroAtencion = $tieneCentroAtencion($coordsActual);
        $esCoordinador    = $tieneRolMapa($userActual, 'coordinador');
        $esEspecialista   = $tieneEspecialistaMapa($userActual);          // ← NUEVO

        $resultado = [];
        foreach ($todosUsuarios as $u) {
            if ($esAdminActual) { $resultado[] = $u; continue; }

            $uEsAdmin = $tieneRolMapa($u, 'administrador');

            /* Centro de Atención → todos menos admin */
            if ($esCentroAtencion) {
                if (!$uEsAdmin) $resultado[] = $u;
                continue;
            }

            /* Coordinador ó Especialista → intersección de coordinaciones */
            if ($esCoordinador || $esEspecialista) {
                $coordsU = array_map('intval', json_decode($u["id_coordinacion"] ?? '[]', true) ?: []);
                if (!empty(array_intersect($coordsActual, $coordsU))) {
                    $resultado[] = $u;
                }
            }
        }

        return $resultado;
    }

    /*=============================================
    INGRESO DE USUARIO
    =============================================*/
    static public function ctrIngresoUser(){

        if(isset($_POST["ingUser"])){

            if(preg_match('/^[a-zA-Z0-9]+$/', $_POST["ingUser"])){

                $tabla = "user";
                $item = "user";
                $valor = $_POST["ingUser"];

                $respuesta = ModeloUser::mdlMostrarUser($tabla, $item, $valor);

                if($respuesta && $respuesta["user"] == $_POST["ingUser"] && password_verify($_POST["ingPassword"], $respuesta["password"])){

                    if($respuesta["estado"] == 1){

                        $_SESSION["iniciarSesion"] = "ok";
                        $_SESSION["id"]    = $respuesta["id"];
                        $_SESSION["user"]  = $respuesta["user"];
                        $_SESSION["name"]  = $respuesta["name"] ?? "";
                        $_SESSION["email"] = $respuesta["email"];
                        $_SESSION["foto"]  = $respuesta["foto"];
                        $_SESSION["tema"]  = $respuesta["tema"] ?? "light";

                        date_default_timezone_set('America/Bogota');
                        $fechaActual = date('Y-m-d H:i:s');

                        $item1 = "ultimo_login";
                        $valor1 = $fechaActual;
                        $item2 = "id";
                        $valor2 = $respuesta["id"];

                        $ultimoLogin = ModeloUser::mdlActualizarUser($tabla, $item1, $valor1, $item2, $valor2);

                        if($ultimoLogin == "ok"){
                            echo '<script>window.location = "inicio";</script>';
                        }

                    }else{
                        echo '<br><div class="alert alert-danger">El usuario aún no está activado</div>';
                    }

                }else{
                    echo '<br><div class="alert alert-danger">Error al ingresar, vuelve a intentarlo</div>';
                }
            }
        }
    }

    /*=============================================
    REGISTRO DE USUARIO
    =============================================*/
    static public function ctrCrearUser(){

        if(!isset($_POST["nuevoUser"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        if(!preg_match('/^[a-zA-Z0-9]+$/', $_POST["nuevoUser"]) ||
           !preg_match('/^[a-zA-Z0-9]+$/', $_POST["nuevoPassword"])){
            return ["status" => "error", "mensaje" => "Usuario y contraseña solo pueden tener letras y números"];
        }

        $name = self::validarName($_POST["nuevoName"] ?? '');
        if($name === false){
            return ["status" => "error", "mensaje" => "El nombre completo contiene caracteres no permitidos"];
        }

        $existe = ModeloUser::mdlMostrarUser("user", "user", $_POST["nuevoUser"]);
        if($existe){
            return ["status" => "error", "mensaje" => "El usuario ya existe, elija otro"];
        }

        // ====== Manejo de imagen ======
        $ruta = "";
        if(isset($_FILES["nuevaFoto"]["tmp_name"]) && !empty($_FILES["nuevaFoto"]["tmp_name"]) && $_FILES["nuevaFoto"]["error"] === UPLOAD_ERR_OK){

            if(!extension_loaded('gd')){
                return ["status" => "error", "mensaje" => "La extensión GD de PHP no está instalada en el servidor"];
            }
            if($_FILES["nuevaFoto"]["size"] > 2 * 1024 * 1024){
                return ["status" => "error", "mensaje" => "La imagen supera los 2MB permitidos"];
            }

            $info = @getimagesize($_FILES["nuevaFoto"]["tmp_name"]);
            if($info === false){
                return ["status" => "error", "mensaje" => "El archivo no es una imagen válida"];
            }

            list($ancho, $alto) = $info;
            $nuevoAncho = 500;
            $nuevoAlto  = 500;

            $directorio = self::rutaUsuarios() . DIRECTORY_SEPARATOR . $_POST["nuevoUser"];
            if(!file_exists($directorio)){
                if(!@mkdir($directorio, 0755, true) && !is_dir($directorio)){
                    return ["status" => "error", "mensaje" => "No se pudo crear la carpeta de imágenes"];
                }
            }

            $mime = $_FILES["nuevaFoto"]["type"];
            if($mime == "image/jpeg" || $mime == "image/jpg" || $mime == "image/pjpeg"){
                $aleatorio = mt_rand(100, 999);
                $ruta = $directorio . DIRECTORY_SEPARATOR . $aleatorio . ".jpg";
                $origen = @imagecreatefromjpeg($_FILES["nuevaFoto"]["tmp_name"]);
                if(!$origen) return ["status" => "error", "mensaje" => "No se pudo procesar la imagen JPG"];
                $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
                imagecopyresized($destino, $origen, 0,0,0,0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
                imagejpeg($destino, $ruta, 90);
                imagedestroy($origen);
                imagedestroy($destino);
            } elseif($mime == "image/png"){
                $aleatorio = mt_rand(100, 999);
                $ruta = $directorio . DIRECTORY_SEPARATOR . $aleatorio . ".png";
                $origen = @imagecreatefrompng($_FILES["nuevaFoto"]["tmp_name"]);
                if(!$origen) return ["status" => "error", "mensaje" => "No se pudo procesar la imagen PNG"];
                $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
                imagealphablending($destino, false);
                imagesavealpha($destino, true);
                imagecopyresized($destino, $origen, 0,0,0,0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
                imagepng($destino, $ruta);
                imagedestroy($origen);
                imagedestroy($destino);
            } else {
                return ["status" => "error", "mensaje" => "La imagen debe ser JPG o PNG"];
            }

            $ruta = "vistas/img/usuarios/" . $_POST["nuevoUser"] . "/" . basename($ruta);
        }

        $idCoordinacion = self::normalizarIdsJson($_POST["nuevoIdCoordinacion"] ?? []);
        $idRol          = self::normalizarIdsJson($_POST["nuevoIdRol"] ?? []);
        $idEntidad      = self::normalizarIdsJson($_POST["nuevoIdEntidad"] ?? []);

        $tabla = "user";
        $encriptar = password_hash($_POST["nuevoPassword"], PASSWORD_DEFAULT);

        $datos = array(
            "user"            => $_POST["nuevoUser"],
            "name"            => $name,
            "password"        => $encriptar,
            "email"           => $_POST["nuevoEmail"],
            "phone"           => $_POST["nuevoPhone"],
            "foto"            => $ruta,
            "id_coordinacion" => $idCoordinacion,
            "id_rol"          => $idRol,
            "id_entidad"      => $idEntidad,
            "estado"          => "1",
            "tema"            => "light"
        );

        $respuesta = ModeloUser::mdlIngresarUser($tabla, $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El usuario ha sido guardado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo guardar el usuario en la base de datos"];
        }
    }


    /*=============================================
    EDITAR USUARIO
    =============================================*/
    static public function ctrEditarUser(){

        if(!isset($_POST["editarUser"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $ruta = isset($_POST["fotoActual"]) ? $_POST["fotoActual"] : "";

        $name = self::validarName($_POST["editarName"] ?? '');
        if($name === false){
            return ["status" => "error", "mensaje" => "El nombre completo contiene caracteres no permitidos"];
        }

        // ====== Manejo de imagen ======
        if(isset($_FILES["editarFoto"]["tmp_name"]) && !empty($_FILES["editarFoto"]["tmp_name"]) && $_FILES["editarFoto"]["error"] === UPLOAD_ERR_OK){

            if(!extension_loaded('gd')){
                return ["status" => "error", "mensaje" => "La extensión GD de PHP no está instalada en el servidor"];
            }
            if($_FILES["editarFoto"]["size"] > 2 * 1024 * 1024){
                return ["status" => "error", "mensaje" => "La imagen supera los 2MB permitidos"];
            }

            $info = @getimagesize($_FILES["editarFoto"]["tmp_name"]);
            if($info === false){
                return ["status" => "error", "mensaje" => "El archivo no es una imagen válida"];
            }

            list($ancho, $alto) = $info;
            $nuevoAncho = 500;
            $nuevoAlto  = 500;

            $directorio = self::rutaUsuarios() . DIRECTORY_SEPARATOR . $_POST["editarUser"];
            if(!file_exists($directorio)){
                if(!@mkdir($directorio, 0755, true) && !is_dir($directorio)){
                    return ["status" => "error", "mensaje" => "No se pudo crear la carpeta de imágenes"];
                }
            }

            if(!empty($_POST["fotoActual"])){
                $fotoViejaAbs = realpath(__DIR__ . "/../") . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $_POST["fotoActual"]);
                if(file_exists($fotoViejaAbs) && is_file($fotoViejaAbs)){
                    @unlink($fotoViejaAbs);
                }
            }

            $mime = $_FILES["editarFoto"]["type"];
            $nombreFinal = "";

            if($mime == "image/jpeg" || $mime == "image/jpg" || $mime == "image/pjpeg"){
                $aleatorio = mt_rand(100, 999);
                $nombreFinal = $aleatorio . ".jpg";
                $rutaAbs = $directorio . DIRECTORY_SEPARATOR . $nombreFinal;
                $origen = @imagecreatefromjpeg($_FILES["editarFoto"]["tmp_name"]);
                if(!$origen) return ["status" => "error", "mensaje" => "No se pudo procesar la imagen JPG"];
                $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
                imagecopyresized($destino, $origen, 0,0,0,0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
                imagejpeg($destino, $rutaAbs, 90);
                imagedestroy($origen);
                imagedestroy($destino);
            } elseif($mime == "image/png"){
                $aleatorio = mt_rand(100, 999);
                $nombreFinal = $aleatorio . ".png";
                $rutaAbs = $directorio . DIRECTORY_SEPARATOR . $nombreFinal;
                $origen = @imagecreatefrompng($_FILES["editarFoto"]["tmp_name"]);
                if(!$origen) return ["status" => "error", "mensaje" => "No se pudo procesar la imagen PNG"];
                $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
                imagealphablending($destino, false);
                imagesavealpha($destino, true);
                imagecopyresized($destino, $origen, 0,0,0,0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
                imagepng($destino, $rutaAbs);
                imagedestroy($origen);
                imagedestroy($destino);
            } else {
                return ["status" => "error", "mensaje" => "La imagen debe ser JPG o PNG"];
            }

            $ruta = "vistas/img/usuarios/" . $_POST["editarUser"] . "/" . $nombreFinal;
        }

        $idCoordinacion = self::normalizarIdsJson($_POST["editarIdCoordinacion"] ?? []);
        $idRol          = self::normalizarIdsJson($_POST["editarIdRol"] ?? []);
        $idEntidad      = self::normalizarIdsJson($_POST["editarIdEntidad"] ?? []);

        $tabla = "user";

        if($_POST["editarPassword"] != ""){
            if(preg_match('/^[a-zA-Z0-9]+$/', $_POST["editarPassword"])){
                $encriptar = password_hash($_POST["editarPassword"], PASSWORD_DEFAULT);
            } else {
                return ["status" => "error", "mensaje" => "La contraseña solo puede contener letras y números"];
            }
        } else {
            $encriptar = $_POST["passwordActual"];
        }

        $tema = $_POST["editarTema"] ?? null;
        if($tema === null || $tema === ''){
            $usuarioActual = ModeloUser::mdlMostrarUser($tabla, "id", $_POST["idUser"]);
            $tema = $usuarioActual["tema"] ?? "light";
        }
        $tema = self::validarTema($tema);

        $datos = array(
            "id"              => $_POST["idUser"],
            "user"            => $_POST["editarUser"],
            "name"            => $name,
            "password"        => $encriptar,
            "email"           => $_POST["editarEmail"],
            "phone"           => $_POST["editarPhone"],
            "foto"            => $ruta,
            "id_coordinacion" => $idCoordinacion,
            "id_rol"          => $idRol,
            "id_entidad"      => $idEntidad,
            "tema"            => $tema
        );

        $respuesta = ModeloUser::mdlEditarUser($tabla, $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El usuario ha sido editado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo editar el usuario"];
        }
    }

    /*=============================================
    EDITAR PERFIL (solo name, email y phone del propio usuario)
    =============================================*/
    static public function ctrEditarPerfil($idUser, $name, $email, $phone){

        $usuario = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if(!$usuario){
            return ["status" => "error", "mensaje" => "Usuario no encontrado"];
        }

        $nameVal = self::validarName($name);
        if($nameVal === false){
            return ["status" => "error", "mensaje" => "El nombre completo contiene caracteres no permitidos"];
        }

        $emailVal = self::validarEmail($email);
        if($emailVal === false){
            return ["status" => "error", "mensaje" => "El correo electrónico no es válido"];
        }

        $phoneVal = self::validarPhone($phone);
        if($phoneVal === false){
            return ["status" => "error", "mensaje" => "El teléfono contiene caracteres no permitidos"];
        }

        $ok1 = ModeloUser::mdlActualizarUser("user", "name",  $nameVal,  "id", $idUser);
        $ok2 = ModeloUser::mdlActualizarUser("user", "email", $emailVal, "id", $idUser);
        $ok3 = ModeloUser::mdlActualizarUser("user", "phone", $phoneVal, "id", $idUser);

        if($ok1 === "ok" && $ok2 === "ok" && $ok3 === "ok"){
            $_SESSION["name"]  = $nameVal;
            $_SESSION["email"] = $emailVal;
            return ["status" => "success", "mensaje" => "¡Perfil actualizado correctamente!"];
        } else {
            return ["status" => "error", "mensaje" => "No se pudo actualizar el perfil"];
        }
    }

    /*=============================================
    MOSTRAR USUARIOS
    =============================================*/
    static public function ctrMostrarUser($item, $valor){
        return ModeloUser::mdlMostrarUser("user", $item, $valor);
    }

    /*=============================================
    BORRAR USUARIO — ✅ solo Administrador
    =============================================*/
    static public function ctrBorrarUser(){
        if(isset($_GET["idUser"])){

            /* ✅ Guard: solo Administrador activo */
            if (!self::esAdminUserModulo()) {
                echo '<script>
                    window.location.replace("index.php?ruta=inicio");
                </script>';
                exit();
            }

            $tabla = "user";
            $datos = $_GET["idUser"];

            if(isset($_GET["fotoUsuario"]) && !empty($_GET["fotoUsuario"])){
                $fotoAbs = realpath(__DIR__ . "/../") . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $_GET["fotoUsuario"]);
                if(file_exists($fotoAbs) && is_file($fotoAbs)){
                    @unlink($fotoAbs);
                }
            }

            $respuesta = ModeloUser::mdlBorrarUser($tabla, $datos);

            echo '<script>
                window.location.replace("index.php?ruta=users");
            </script>';
            exit();
        }
    }

    /*=============================================
    CAMBIAR CONTRASEÑA
    =============================================*/
    static public function ctrCambiarPassword($idUser, $passwordActual, $nuevaPassword, $confirmarPassword){

        if($nuevaPassword !== $confirmarPassword){
            return array("status" => "error", "mensaje" => "Las contraseñas no coinciden");
        }
        if(strlen($nuevaPassword) < 4){
            return array("status" => "error", "mensaje" => "La contraseña debe tener al menos 4 caracteres");
        }
        if(!preg_match('/^[a-zA-Z0-9]+$/', $nuevaPassword)){
            return array("status" => "error", "mensaje" => "Solo letras y números");
        }

        $usuario = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if(!$usuario){
            return array("status" => "error", "mensaje" => "Usuario no encontrado");
        }
        if(!password_verify($passwordActual, $usuario["password"])){
            return array("status" => "error", "mensaje" => "La contraseña actual es incorrecta");
        }

        $nuevaEncriptada = password_hash($nuevaPassword, PASSWORD_DEFAULT);
        $respuesta = ModeloUser::mdlActualizarUser("user", "password", $nuevaEncriptada, "id", $idUser);

        if($respuesta == "ok"){
            return array("status" => "success", "mensaje" => "Contraseña actualizada");
        }else{
            return array("status" => "error", "mensaje" => "Error al actualizar");
        }
    }

    /*=============================================
    CAMBIAR TEMA (light / dark / auto)
    =============================================*/
    static public function ctrCambiarTema($idUser, $tema){

        $tema = self::validarTema($tema);

        $usuario = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if(!$usuario){
            return ["status" => "error", "mensaje" => "Usuario no encontrado"];
        }

        $respuesta = ModeloUser::mdlActualizarUser("user", "tema", $tema, "id", $idUser);

        if($respuesta == "ok"){
            $_SESSION["tema"] = $tema;
            return ["status" => "success", "mensaje" => "Tema actualizado", "tema" => $tema];
        }else{
            return ["status" => "error", "mensaje" => "No se pudo actualizar el tema"];
        }
    }
}
?>