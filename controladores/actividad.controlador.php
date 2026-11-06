<?php

class ControladorActividad{

    /*=============================================
    LÍMITE DE FOTOS POR ACTIVIDAD
    =============================================*/
    const LIMITE_FOTOS = 4;

    /*=============================================
    HELPER: normaliza un array/JSON de IDs → JSON string
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
    HELPER: ruta física de /vistas/img/actividad
    =============================================*/
    static private function rutaActividad(){
        $dir = realpath(__DIR__ . "/../") . DIRECTORY_SEPARATOR . "vistas" . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "actividad";
        if(!file_exists($dir)){
            if(!@mkdir($dir, 0755, true) && !is_dir($dir)){
                return false;
            }
        }
        return $dir;
    }

    /*=============================================
    HELPER: decodifica el campo `foto`
    =============================================*/
    static private function decodificarFotos($valor){
        if(empty($valor)) return [];
        if(is_array($valor)) return array_values(array_filter($valor, 'is_string'));

        $valor = trim((string)$valor);
        if($valor === '') return [];

        if($valor[0] === '['){
            $decoded = json_decode($valor, true);
            if(is_array($decoded)){
                return array_values(array_filter($decoded, function($f){ return is_string($f) && $f !== ''; }));
            }
            return [];
        }
        return [$valor];
    }

    /*=============================================
    HELPER: subir MÚLTIPLES fotos
    =============================================*/
    static private function subirFotos($archivos, &$errorMsg, $maxFotos = null){

        if($maxFotos === null) $maxFotos = self::LIMITE_FOTOS;

        $rutas = [];

        if(!isset($archivos["name"]) || empty($archivos["name"])){
            return $rutas;
        }

        if(!is_array($archivos["name"])){
            $archivos = [
                "name"     => [$archivos["name"]],
                "type"     => [$archivos["type"]],
                "tmp_name" => [$archivos["tmp_name"]],
                "error"    => [$archivos["error"]],
                "size"     => [$archivos["size"]],
            ];
        }

        $indicesValidos = [];
        foreach($archivos["name"] as $i => $nom){
            if(!empty($nom) && $archivos["error"][$i] !== UPLOAD_ERR_NO_FILE){
                $indicesValidos[] = $i;
            }
        }

        if(empty($indicesValidos)){
            return $rutas;
        }

        if(count($indicesValidos) > $maxFotos){
            $errorMsg = "Máximo " . $maxFotos . " fotos por actividad";
            return false;
        }

        $permitidas = [
            "image/jpeg"  => "jpg",
            "image/jpg"   => "jpg",
            "image/pjpeg" => "jpg",
            "image/png"   => "png",
            "image/gif"   => "gif",
            "image/webp"  => "webp"
        ];

        $directorio = self::rutaActividad();
        if($directorio === false){
            $errorMsg = "No se pudo crear la carpeta de imágenes";
            return false;
        }

        foreach($indicesValidos as $orden => $i){

            if($archivos["error"][$i] !== UPLOAD_ERR_OK){
                $errorMsg = "Error al subir la imagen #".($orden + 1);
                self::eliminarFotos($rutas);
                return false;
            }
            if($archivos["size"][$i] > 5 * 1024 * 1024){
                $errorMsg = "La imagen #".($orden + 1)." supera los 5 MB permitidos";
                self::eliminarFotos($rutas);
                return false;
            }

            $info = @getimagesize($archivos["tmp_name"][$i]);
            if($info === false){
                $errorMsg = "El archivo #".($orden + 1)." no es una imagen válida";
                self::eliminarFotos($rutas);
                return false;
            }

            $mime = $archivos["type"][$i];
            if(!isset($permitidas[$mime])){
                $errorMsg = "La imagen #".($orden + 1)." debe ser JPG, PNG, GIF o WEBP";
                self::eliminarFotos($rutas);
                return false;
            }

            $extension     = $permitidas[$mime];
            $random        = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 10);
            $nombreArchivo = $random . "_" . date("Ymd_His") . "_" . ($orden + 1) . "." . $extension;
            $rutaAbs       = $directorio . DIRECTORY_SEPARATOR . $nombreArchivo;

            if(!move_uploaded_file($archivos["tmp_name"][$i], $rutaAbs)){
                $errorMsg = "No se pudo guardar la imagen #".($orden + 1);
                self::eliminarFotos($rutas);
                return false;
            }

            $rutas[] = "vistas/img/actividad/" . $nombreArchivo;
        }

        return $rutas;
    }

    /*=============================================
    HELPER: elimina UNA o VARIAS fotos físicas
    =============================================*/
    static private function eliminarFotos($fotoValue){

        if(empty($fotoValue)) return;

        $fotos = self::decodificarFotos($fotoValue);
        if(empty($fotos)) return;

        $base = realpath(__DIR__ . "/../");

        foreach($fotos as $ruta){
            if(empty($ruta) || !is_string($ruta)) continue;
            $abs = $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta);
            if(file_exists($abs) && is_file($abs)){
                @unlink($abs);
            }
        }
    }

    /*=============================================
    ✅ HELPERS DE AUTORIZACIÓN
    =============================================*/

    /**
     * ¿El usuario actual tiene el rol X activo? (búsqueda por nombre, case-insensitive)
     */
    static private function userTieneRol($user, array $rolesNombres){
        if(!$user) return false;
        $rolesNombres = array_map(function($r){ return mb_strtolower(trim($r)); }, $rolesNombres);
        $ids = json_decode($user["id_rol"] ?? '[]', true) ?: [];
        foreach($ids as $idRol){
            $rol = ModeloRol::mdlMostrarRol("rol", "id", (int)$idRol);
            if($rol
                && (int)($rol["estado"] ?? 0) === 1
                && in_array(mb_strtolower(trim($rol["rol"])), $rolesNombres, true)){
                return true;
            }
        }
        return false;
    }

    /**
     * ¿El usuario actual es Administrador activo?
     */
    static public function esAdminActividad(){
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        $idUser = (int)($_SESSION["id"] ?? 0);
        if ($idUser <= 0) return false;

        $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if (!$user) return false;

        return self::userTieneRol($user, ['Administrador']);
    }

    /**
     * ¿El usuario puede acceder al módulo de actividades?
     *  → Administrador, Coordinador o Especialista/Técnico activo
     */
    static public function puedeVerModuloActividad(){
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        $idUser = (int)($_SESSION["id"] ?? 0);
        if ($idUser <= 0) return false;

        $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if (!$user) return false;

        return self::userTieneRol($user, [
            'Administrador', 'Coordinador', 'Especialista', 'Tecnico', 'Técnico'
        ]);
    }

    /**
     * ¿Puede eliminar actividades? Solo Administrador.
     */
    static public function puedeEliminarActividad(){
        return self::esAdminActividad();
    }

    /**
     * ¿El usuario actual puede VER una actividad concreta?
     * Reglas:
     *   - Admin → siempre
     *   - user.id ∈ id_user → sí
     *   - user.id_coordinacion ∩ actividad.id_coordinacion ≠ ∅ → sí
     *   - en cualquier otro caso → no
     */
    static public function puedeVerActividad($actividad){
        if(!$actividad) return false;

        if(self::esAdminActividad()) return true;

        $idUser = (int)($_SESSION["id"] ?? 0);
        if($idUser <= 0) return false;

        // 1) match por usuario asignado
        $idsUser = json_decode($actividad["id_user"] ?? '[]', true) ?: [];
        $idsUser = array_map('intval', $idsUser);
        if(in_array($idUser, $idsUser, true)) return true;

        // 2) match por coordinación
        $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        if(!$user) return false;

        $coordsUser = array_map('intval', json_decode($user["id_coordinacion"] ?? '[]', true) ?: []);
        if(empty($coordsUser)) return false;

        $idsCoordAct = array_map('intval', json_decode($actividad["id_coordinacion"] ?? '[]', true) ?: []);
        if(empty($idsCoordAct)) return false;

        return !empty(array_intersect($coordsUser, $idsCoordAct));
    }

    /**
     * Devuelve SOLO las actividades visibles para el usuario actual.
     * (Admin ve todas; el resto filtra por id_user / id_coordinacion)
     */
    static public function actividadesVisibles(){
        $todas = ModeloActividad::mdlMostrarActividad("actividad", null, null);
        if(!$todas || !is_array($todas)) return [];

        if(self::esAdminActividad()) return $todas;

        $idUser = (int)($_SESSION["id"] ?? 0);
        if($idUser <= 0) return [];

        $user = ModeloUser::mdlMostrarUser("user", "id", $idUser);
        $coordsUser = $user
            ? array_map('intval', json_decode($user["id_coordinacion"] ?? '[]', true) ?: [])
            : [];

        $resultado = [];
        foreach($todas as $a){
            $idsUser = array_map('intval', json_decode($a["id_user"] ?? '[]', true) ?: []);
            if(in_array($idUser, $idsUser, true)){
                $resultado[] = $a;
                continue;
            }

            if(!empty($coordsUser)){
                $idsCoordAct = array_map('intval', json_decode($a["id_coordinacion"] ?? '[]', true) ?: []);
                if(!empty($idsCoordAct) && !empty(array_intersect($coordsUser, $idsCoordAct))){
                    $resultado[] = $a;
                }
            }
        }
        return $resultado;
    }

    /*=============================================
    CREAR ACTIVIDAD
    =============================================*/
    static public function ctrCrearActividad(){

        if(!isset($_POST["nuevaActividad"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $nombre = trim($_POST["nuevaActividad"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_\:\/]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre de la actividad contiene caracteres no permitidos"];
        }
        if(mb_strlen($nombre) > 255){
            return ["status" => "error", "mensaje" => "El nombre es demasiado largo (máx. 255)"];
        }

        $cantidad = intval($_POST["nuevaCantidad"] ?? 0);
        if($cantidad <= 0){
            return ["status" => "error", "mensaje" => "La cantidad debe ser mayor a 0"];
        }

        $descripcion = trim($_POST["nuevaDescripcion"] ?? '');
        if($descripcion === '' || $descripcion === '<p><br></p>'){
            return ["status" => "error", "mensaje" => "La descripción es obligatoria"];
        }
        if(mb_strlen(strip_tags($descripcion)) > 5000){
            return ["status" => "error", "mensaje" => "La descripción es demasiado larga (máx. 5000 caracteres)"];
        }

        $idEntidad      = self::normalizarIdsJson($_POST["nuevaIdEntidad"]      ?? []);
        $idTag          = self::normalizarIdsJson($_POST["nuevaIdTag"]          ?? []);
        $idCoordinacion = self::normalizarIdsJson($_POST["nuevaIdCoordinacion"] ?? []);
        $idHost         = self::normalizarIdsJson($_POST["nuevaIdHost"]         ?? []);
        $idUser         = self::normalizarIdsJson($_POST["nuevaIdUser"]         ?? []);

        if(empty(json_decode($idEntidad, true))){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos una entidad"];
        }
        if(empty(json_decode($idTag, true))){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos un tag"];
        }
        if(empty(json_decode($idUser, true))){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos un usuario"];
        }

        // ── FOTOS
        $errorMsg = "";
        $rutas = self::subirFotos($_FILES["nuevaFoto"] ?? [], $errorMsg, self::LIMITE_FOTOS);
        if($rutas === false){
            return ["status" => "error", "mensaje" => $errorMsg];
        }

        $fotoJson = empty($rutas) ? "" : json_encode($rutas);

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $datos = array(
            "actividad"       => $nombre,
            "id_entidad"      => $idEntidad,
            "id_tag"          => $idTag,
            "id_coordinacion" => $idCoordinacion,
            "cantidad"        => (string)$cantidad,
            "id_host"         => $idHost,
            "id_user"         => $idUser,
            "foto"            => $fotoJson,
            "descripcion"     => $descripcion,
            "estado"          => 1,
            "modificacion"    => $fechaActual
        );

        $respuesta = ModeloActividad::mdlIngresarActividad("actividad", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡La actividad ha sido guardada correctamente!"];
        }
        self::eliminarFotos($rutas);
        return ["status" => "error", "mensaje" => "No se pudo guardar la actividad"];
    }

    /*=============================================
    EDITAR ACTIVIDAD
    =============================================*/
    static public function ctrEditarActividad(){

        if(!isset($_POST["editarActividad"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $id = intval($_POST["idActividad"] ?? 0);
        if($id <= 0){
            return ["status" => "error", "mensaje" => "ID inválido"];
        }

        $actual = ModeloActividad::mdlMostrarActividad("actividad", "id", $id);
        if(!$actual){
            return ["status" => "error", "mensaje" => "Actividad no encontrada"];
        }

        $nombre = trim($_POST["editarActividad"]);
        if($nombre === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_\:\/]+$/u', $nombre)){
            return ["status" => "error", "mensaje" => "El nombre de la actividad contiene caracteres no permitidos"];
        }
        if(mb_strlen($nombre) > 255){
            return ["status" => "error", "mensaje" => "El nombre es demasiado largo (máx. 255)"];
        }

        $cantidad = intval($_POST["editarCantidad"] ?? 0);
        if($cantidad <= 0){
            return ["status" => "error", "mensaje" => "La cantidad debe ser mayor a 0"];
        }

        $descripcion = trim($_POST["editarDescripcion"] ?? '');
        if($descripcion === '' || $descripcion === '<p><br></p>'){
            return ["status" => "error", "mensaje" => "La descripción es obligatoria"];
        }
        if(mb_strlen(strip_tags($descripcion)) > 5000){
            return ["status" => "error", "mensaje" => "La descripción es demasiado larga (máx. 5000 caracteres)"];
        }

        $idEntidad      = self::normalizarIdsJson($_POST["editarIdEntidad"]      ?? []);
        $idTag          = self::normalizarIdsJson($_POST["editarIdTag"]          ?? []);
        $idCoordinacion = self::normalizarIdsJson($_POST["editarIdCoordinacion"] ?? []);
        $idHost         = self::normalizarIdsJson($_POST["editarIdHost"]         ?? []);
        $idUser         = self::normalizarIdsJson($_POST["editarIdUser"]         ?? []);

        if(empty(json_decode($idEntidad, true))){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos una entidad"];
        }
        if(empty(json_decode($idTag, true))){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos un tag"];
        }
        if(empty(json_decode($idUser, true))){
            return ["status" => "error", "mensaje" => "Debe seleccionar al menos un usuario"];
        }

        $fotosActuales = self::decodificarFotos($actual["foto"]);

        $fotosAEliminarInput = $_POST["fotosAEliminar"] ?? '[]';
        if(is_string($fotosAEliminarInput)){
            $decoded = json_decode($fotosAEliminarInput, true);
            $fotosAEliminar = is_array($decoded) ? $decoded : [];
        } elseif(is_array($fotosAEliminarInput)){
            $fotosAEliminar = $fotosAEliminarInput;
        } else {
            $fotosAEliminar = [];
        }

        $fotosAEliminar = array_values(array_intersect($fotosActuales, $fotosAEliminar));
        $fotosRestantes = array_values(array_diff($fotosActuales, $fotosAEliminar));

        $nuevasRutas = [];
        $hayNuevas   = false;
        if(isset($_FILES["editarFoto"]["name"])){
            $nombres = $_FILES["editarFoto"]["name"];
            if(is_array($nombres)){
                foreach($nombres as $n){ if(!empty($n)) { $hayNuevas = true; break; } }
            } elseif(!empty($nombres)){
                $hayNuevas = true;
            }
        }

        if($hayNuevas){
            $errorMsg = "";
            $nuevasRutas = self::subirFotos($_FILES["editarFoto"], $errorMsg, self::LIMITE_FOTOS);
            if($nuevasRutas === false){
                return ["status" => "error", "mensaje" => $errorMsg];
            }
        }

        $totalFinal = count($fotosRestantes) + count($nuevasRutas);
        if($totalFinal > self::LIMITE_FOTOS){
            self::eliminarFotos($nuevasRutas);
            return [
                "status"  => "error",
                "mensaje" => "El límite es " . self::LIMITE_FOTOS . " fotos por actividad. "
                           . "Con los cambios quedarían " . $totalFinal . "."
            ];
        }

        self::eliminarFotos($fotosAEliminar);

        $fotosFinales = array_merge($fotosRestantes, $nuevasRutas);
        $fotoJson = empty($fotosFinales) ? "" : json_encode($fotosFinales);

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');

        $datos = array(
            "id"              => $id,
            "actividad"       => $nombre,
            "id_entidad"      => $idEntidad,
            "id_tag"          => $idTag,
            "id_coordinacion" => $idCoordinacion,
            "cantidad"        => (string)$cantidad,
            "id_host"         => $idHost,
            "id_user"         => $idUser,
            "foto"            => $fotoJson,
            "descripcion"     => $descripcion,
            "modificacion"    => $fechaActual
        );

        $respuesta = ModeloActividad::mdlEditarActividad("actividad", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡La actividad ha sido editada correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo editar la actividad"];
    }

    /*=============================================
    MOSTRAR ACTIVIDADES
    =============================================*/
    static public function ctrMostrarActividad($item, $valor){
        return ModeloActividad::mdlMostrarActividad("actividad", $item, $valor);
    }

    /*=============================================
    BORRAR ACTIVIDAD — SOLO ADMIN
    =============================================*/
    static public function ctrBorrarActividad(){
        if(isset($_GET["idActividad"])){

            // ✅ GUARD: solo Administrador
            if(!self::puedeEliminarActividad()){
                echo '<script>
                    window.location.replace("index.php?ruta=actividad");
                </script>';
                exit();
            }

            $id = intval($_GET["idActividad"]);
            $fila = ModeloActividad::mdlMostrarActividad("actividad", "id", $id);

            if($fila){
                self::eliminarFotos($fila["foto"]);
            }

            ModeloActividad::mdlBorrarActividad("actividad", $id);

            echo '<script>
                window.location.replace("index.php?ruta=actividad");
            </script>';
            exit();
        }
    }
}
?>