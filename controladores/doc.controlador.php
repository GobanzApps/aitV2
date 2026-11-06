<?php

class ControladorDoc{

    /*=============================================
    HELPER: carpeta física de documentos
    =============================================*/
    static private function rutaDocAbs(){
        return realpath(__DIR__ . "/../") . DIRECTORY_SEPARATOR . "vistas" . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "doc";
    }

    /*=============================================
    HELPER: extensiones permitidas (solo documentos)
    =============================================*/
    static private function extensionesPermitidas(){
        return ['doc', 'docx', 'pdf', 'xls', 'xlsx', 'ppt', 'pptx',
                'odt', 'ods', 'odp', 'txt', 'csv', 'rtf'];
    }

    /*=============================================
    HELPER: normaliza array/JSON de IDs → JSON string
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
    HELPER: subir archivo adjunto (15 MB máx, renombrado)
    =============================================*/
    static private function subirAdjunto($archivo, &$errorMsg){

        if(!isset($archivo["tmp_name"]) || empty($archivo["tmp_name"]) || $archivo["error"] !== UPLOAD_ERR_OK){
            $errorMsg = "Debe adjuntar un archivo válido";
            return false;
        }

        if($archivo["size"] > 15 * 1024 * 1024){
            $errorMsg = "El archivo supera los 15 MB permitidos";
            return false;
        }

        $nombreOriginal = $archivo["name"];
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $permitidas = self::extensionesPermitidas();

        if(!in_array($ext, $permitidas)){
            $errorMsg = "Solo se permiten documentos: " . implode(", ", $permitidas);
            return false;
        }

        $directorio = self::rutaDocAbs();
        if(!file_exists($directorio)){
            if(!@mkdir($directorio, 0755, true) && !is_dir($directorio)){
                $errorMsg = "No se pudo crear la carpeta de documentos";
                return false;
            }
        }

        $aleatorio = bin2hex(random_bytes(5));
        $fechaHora = date('Ymd_His');
        $nombreFinal = $aleatorio . '_' . $fechaHora . '.' . $ext;
        $rutaAbs  = $directorio . DIRECTORY_SEPARATOR . $nombreFinal;

        if(!move_uploaded_file($archivo["tmp_name"], $rutaAbs)){
            $errorMsg = "No se pudo mover el archivo al destino";
            return false;
        }

        return "vistas/img/doc/" . $nombreFinal;
    }

    /*=============================================
    HELPER: eliminar archivo físico
    =============================================*/
    static private function eliminarAdjunto($rutaRelativa){
        if(empty($rutaRelativa)) return;
        $abs = realpath(__DIR__ . "/../") . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaRelativa);
        if(file_exists($abs) && is_file($abs)){
            @unlink($abs);
        }
    }

    /*=============================================
    ✅ HELPERS DE AUTORIZACIÓN — MÓDULO DOC
    =============================================*/

    /**
     * ¿El usuario actual puede ver el módulo de documentos?
     *  → Administrador
     *  → Coordinación activa: Archivo, Planificación, Administración,
     *    Bienes, Centro de Atención o Despacho
     */
    static public function puedeVerModuloDoc($auth = null){
        if ($auth === null) {
            if (!class_exists('ControladorTicket')) return false;
            $auth = ControladorTicket::authTicket();
        }
        if (!$auth) return false;

        if (ControladorTicket::esAdminTicket($auth)) return true;

        foreach ($auth['coordsNombres'] as $coord) {
            $c = mb_strtolower(trim($coord));
            if (strpos($c, 'archivo')           !== false) return true;
            if (strpos($c, 'planificaci')       !== false) return true;
            if (strpos($c, 'administraci')      !== false) return true;
            if (strpos($c, 'bienes')            !== false) return true;
            if (strpos($c, 'centro de atenci')  !== false) return true;
            if (strpos($c, 'despacho')          !== false) return true;
        }
        return false;
    }

    /**
     * ¿El usuario puede crear, editar o activar/desactivar documentos?
     *  → Administrador
     *  → coordinación Archivo
     *  → coordinación Planificación
     */
    static public function puedeModificarDocs($auth = null){
        if ($auth === null) {
            if (!class_exists('ControladorTicket')) return false;
            $auth = ControladorTicket::authTicket();
        }
        if (!$auth) return false;

        if (ControladorTicket::esAdminTicket($auth)) return true;

        foreach ($auth['coordsNombres'] as $coord) {
            $c = mb_strtolower(trim($coord));
            if (strpos($c, 'archivo')     !== false) return true;
            if (strpos($c, 'planificaci') !== false) return true;
        }
        return false;
    }

    /**
     * ¿El usuario puede eliminar documentos? → Solo Administrador
     */
    static public function puedeEliminarDocs($auth = null){
        if ($auth === null) {
            if (!class_exists('ControladorTicket')) return false;
            $auth = ControladorTicket::authTicket();
        }
        if (!$auth) return false;
        return ControladorTicket::esAdminTicket($auth);
    }

    /**
     * ¿El usuario puede ver documentos con tipo "Privado"?
     */
    static public function puedeVerDocsPrivados($auth = null){
        if ($auth === null) {
            if (!class_exists('ControladorTicket')) return false;
            $auth = ControladorTicket::authTicket();
        }
        if (!$auth) return false;

        if (ControladorTicket::esAdminTicket($auth)) return true;

        foreach ($auth['coordsNombres'] as $coord) {
            $c = mb_strtolower(trim($coord));
            if (strpos($c, 'archivo')  !== false) return true;
            if (strpos($c, 'despacho') !== false) return true;
        }
        return false;
    }

    /**
     * ¿El documento tiene el tipo "Privado"?
     */
    static public function esDocumentoPrivado($idDocTipoJson, $mapaTipos){
        $ids = json_decode($idDocTipoJson ?? '[]', true);
        if (!is_array($ids) || empty($ids)) return false;

        foreach ($ids as $idT) {
            $idT = (int)$idT;
            if (isset($mapaTipos[$idT])) {
                if (strcasecmp(trim($mapaTipos[$idT]["doc_tipo"]), "Privado") === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /*=============================================
    CREAR DOCUMENTO
    =============================================*/
    static public function ctrCrearDoc(){

        if(!isset($_POST["nuevoDoc"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $doc = trim($_POST["nuevoDoc"]);
        if($doc === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_\/]+$/u', $doc)){
            return ["status" => "error", "mensaje" => "El nombre del documento contiene caracteres no permitidos"];
        }

        $existe = ModeloDoc::mdlMostrarDoc("doc", "doc", $doc);
        if($existe){
            return ["status" => "error", "mensaje" => "El documento ya existe, intente con otro nombre"];
        }

        $idDocTipo     = self::normalizarIdsJson($_POST["nuevoIdDocTipo"]     ?? []);
        $idTicket      = self::normalizarIdsJson($_POST["nuevoIdTicket"]      ?? []);
        $remitente     = self::normalizarIdsJson($_POST["nuevoRemitente"]     ?? []);
        $destinatario  = self::normalizarIdsJson($_POST["nuevoDestinatario"]  ?? []);

        $asunto  = trim($_POST["nuevoAsunto"]  ?? '');
        $emision = trim($_POST["nuevoEmision"] ?? '');

        /* ✅ NUEVO: validar fecha_registro (recibido/entregado) */
        $fechaRegistro = trim($_POST["nuevoFechaRegistro"] ?? '');

        if($asunto === ''){
            return ["status" => "error", "mensaje" => "El asunto es obligatorio"];
        }
        if(mb_strlen($asunto) > 255){
            return ["status" => "error", "mensaje" => "El asunto es demasiado largo (máx. 255 caracteres)"];
        }
        if($emision === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $emision)){
            return ["status" => "error", "mensaje" => "La fecha de emisión no es válida"];
        }
        if($fechaRegistro === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRegistro)){
            return ["status" => "error", "mensaje" => "La fecha Recibido/Entregado no es válida"];
        }

        $errorMsg = "";
        $ruta = self::subirAdjunto($_FILES["nuevoAdjunto"] ?? [], $errorMsg);
        if($ruta === false){
            return ["status" => "error", "mensaje" => $errorMsg];
        }

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');
        $idUser      = $_SESSION["id"] ?? 0;

        $datos = array(
            "doc"            => $doc,
            "id_doc_tipo"    => $idDocTipo,
            "id_ticket"      => $idTicket,
            "remitente"      => $remitente,
            "destinatario"   => $destinatario,
            "asunto"         => $asunto,
            "adjunto"        => $ruta,
            "emision"        => $emision,
            "fecha_registro" => $fechaRegistro,
            "id_user"        => $idUser,
            "estado"         => 1,
            "modificacion"   => $fechaActual
        );

        $respuesta = ModeloDoc::mdlIngresarDoc("doc", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El documento ha sido guardado correctamente!"];
        }
        self::eliminarAdjunto($ruta);
        return ["status" => "error", "mensaje" => "No se pudo guardar el documento en la base de datos"];
    }

    /*=============================================
    EDITAR DOCUMENTO
    =============================================*/
    static public function ctrEditarDoc(){

        if(!isset($_POST["editarDoc"])){
            return ["status" => "error", "mensaje" => "No se recibieron los datos del formulario"];
        }

        $id = intval($_POST["idDoc"] ?? 0);
        if($id <= 0){
            return ["status" => "error", "mensaje" => "ID inválido"];
        }

        $actual = ModeloDoc::mdlMostrarDoc("doc", "id", $id);
        if(!$actual){
            return ["status" => "error", "mensaje" => "Documento no encontrado"];
        }

        $doc = trim($_POST["editarDoc"]);
        if($doc === "" || !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.\,\-\_\/]+$/u', $doc)){
            return ["status" => "error", "mensaje" => "El nombre del documento contiene caracteres no permitidos"];
        }

        $existe = ModeloDoc::mdlMostrarDoc("doc", "doc", $doc);
        if($existe && $existe["id"] != $id){
            return ["status" => "error", "mensaje" => "Ya existe otro documento con ese nombre"];
        }

        $idDocTipo     = self::normalizarIdsJson($_POST["editarIdDocTipo"]    ?? []);
        $idTicket      = self::normalizarIdsJson($_POST["editarIdTicket"]     ?? []);
        $remitente     = self::normalizarIdsJson($_POST["editarRemitente"]    ?? []);
        $destinatario  = self::normalizarIdsJson($_POST["editarDestinatario"] ?? []);

        $asunto  = trim($_POST["editarAsunto"]  ?? '');
        $emision = trim($_POST["editarEmision"] ?? '');

        /* ✅ NUEVO: validar fecha_registro */
        $fechaRegistro = trim($_POST["editarFechaRegistro"] ?? '');

        if($asunto === ''){
            return ["status" => "error", "mensaje" => "El asunto es obligatorio"];
        }
        if(mb_strlen($asunto) > 255){
            return ["status" => "error", "mensaje" => "El asunto es demasiado largo (máx. 255 caracteres)"];
        }
        if($emision === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $emision)){
            return ["status" => "error", "mensaje" => "La fecha de emisión no es válida"];
        }
        if($fechaRegistro === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRegistro)){
            return ["status" => "error", "mensaje" => "La fecha Recibido/Entregado no es válida"];
        }

        $ruta = $actual["adjunto"];
        if(isset($_FILES["editarAdjunto"]["tmp_name"]) && !empty($_FILES["editarAdjunto"]["tmp_name"]) && $_FILES["editarAdjunto"]["error"] === UPLOAD_ERR_OK){
            $errorMsg = "";
            $nuevaRuta = self::subirAdjunto($_FILES["editarAdjunto"], $errorMsg);
            if($nuevaRuta === false){
                return ["status" => "error", "mensaje" => $errorMsg];
            }
            self::eliminarAdjunto($actual["adjunto"]);
            $ruta = $nuevaRuta;
        }

        date_default_timezone_set('America/Bogota');
        $fechaActual = date('Y-m-d H:i:s');
        $idUser      = $_SESSION["id"] ?? 0;

        $datos = array(
            "id"             => $id,
            "doc"            => $doc,
            "id_doc_tipo"    => $idDocTipo,
            "id_ticket"      => $idTicket,
            "remitente"      => $remitente,
            "destinatario"   => $destinatario,
            "asunto"         => $asunto,
            "adjunto"        => $ruta,
            "emision"        => $emision,
            "fecha_registro" => $fechaRegistro,
            "id_user"        => $idUser,
            "modificacion"   => $fechaActual
        );

        $respuesta = ModeloDoc::mdlEditarDoc("doc", $datos);

        if($respuesta == "ok"){
            return ["status" => "success", "mensaje" => "¡El documento ha sido editado correctamente!"];
        }
        return ["status" => "error", "mensaje" => "No se pudo editar el documento"];
    }

    /*=============================================
    MOSTRAR DOCUMENTOS
    =============================================*/
    static public function ctrMostrarDoc($item, $valor){
        return ModeloDoc::mdlMostrarDoc("doc", $item, $valor);
    }

    /*=============================================
    ✅ BORRAR DOCUMENTO — solo Administrador
    =============================================*/
    static public function ctrBorrarDoc(){
        if(isset($_GET["idDoc"])){

            if (!self::puedeEliminarDocs()) {
                echo '<script>window.location.replace("index.php?ruta=doc");</script>';
                exit();
            }

            $id = intval($_GET["idDoc"]);
            $fila = ModeloDoc::mdlMostrarDoc("doc", "id", $id);

            if($fila){
                self::eliminarAdjunto($fila["adjunto"]);
            }

            ModeloDoc::mdlBorrarDoc("doc", $id);

            echo '<script>
                window.location.replace("index.php?ruta=doc");
            </script>';
            exit();
        }
    }
}
?>