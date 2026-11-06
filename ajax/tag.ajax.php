<?php
// ** INICIO: Buffer + errores silenciados **
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING & ~E_STRICT);
ini_set('display_errors', 0);
ob_start();

// ** VERIFICACIÓN DE SESIÓN **
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok") {
    if (ob_get_length()) ob_clean();
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(["error" => "Acceso no autorizado"]);
    exit();
}
// ** FIN **

// Handler de errores fatales → devuelve JSON
register_shutdown_function(function() {
    $err = error_get_last();
    if($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            "status"  => "error",
            "mensaje" => "Error interno del servidor: " . $err['message']
        ]);
    }
});

// Helper para responder siempre JSON limpio
function ajaxResponder($data){
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

require_once __DIR__ . "/../controladores/tag.controlador.php";
require_once __DIR__ . "/../modelos/tag.modelo.php";

// ✅ NUEVO: dependencias para listar los tipos de tag activos
require_once __DIR__ . "/../controladores/tag_tipo.controlador.php";
require_once __DIR__ . "/../modelos/tag_tipo.modelo.php";

// ✅ Dependencias para ControladorTag::puedeVerModuloTag() / esAdminTag()
require_once __DIR__ . "/../modelos/user.modelo.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

/*=============================================
✅ GUARD: solo Administrador o (Centro de Atención / Despacho)
Se invoca ANTES de cada handler
=============================================*/
function ajaxGuardTagModulo(){
    if (!ControladorTag::puedeVerModuloTag()) {
        ajaxResponder([
            "status"  => "error",
            "mensaje" => "No autorizado: solo Administrador o usuarios con coordinación Centro de Atención / Despacho."
        ]);
    }
}

class AjaxTag{

    /* Editar (traer datos para el modal) */
    public $idTag;
    public function ajaxEditarTag(){
        $item = "id";
        $valor = $this->idTag;
        $respuesta = ControladorTag::ctrMostrarTag($item, $valor);
        ajaxResponder($respuesta);
    }

    /* Activar */
    public $activarTag;
    public $activarId;
    public function ajaxActivarTag(){
        $tabla = "tag";
        $item1 = "estado";
        $valor1 = $this->activarTag;
        $item2 = "id";
        $valor2 = $this->activarId;
        $respuesta = ModeloTag::mdlActualizarTag($tabla, $item1, $valor1, $item2, $valor2);
        ajaxResponder(["status" => $respuesta]);
    }
}

/*=============================================
⚠️ CREAR y EDITAR — GUARD ANTES
=============================================*/

/* CREAR TAG */
if(isset($_POST["nuevoTag"])){
    ajaxGuardTagModulo();
    $respuesta = ControladorTag::ctrCrearTag();
    ajaxResponder($respuesta);
}

/* EDITAR TAG (guardar cambios) */
if(isset($_POST["editarTag"])){
    ajaxGuardTagModulo();
    $respuesta = ControladorTag::ctrEditarTag();
    ajaxResponder($respuesta);
}

/*=============================================
✅ Listar tipos de tag activos (multi-select)
Se usa para alimentar el Tom Select en los modales
(compatibilidad con versiones previas)
=============================================*/
if(isset($_POST["listarTagTiposActivos"])){
    ajaxGuardTagModulo();
    $tipos = ControladorTagTipo::ctrMostrarTagTipo(null, null);
    $resultado = [];
    if($tipos && is_array($tipos)){
        foreach($tipos as $t){
            if((int)$t["estado"] === 1){
                $resultado[] = [
                    "id"          => (int)$t["id"],
                    "texto"       => $t["tag_tipo"],
                    "color"       => $t["color"] ?: "#0d6efd",
                    "descripcion" => $t["descripcion"] ?? ''
                ];
            }
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
✅ NUEVO: Paginación remota de tipos de tag (TomSelect)
Recibe: page (int), q (string)
Devuelve: { status, data:[{id,texto,color,descripcion}], total, page, has_more }
- Filtra por nombre O descripción (case-insensitive)
- Solo devuelve activos (estado = 1)
=============================================*/
if(isset($_POST["listarTagTiposPaginado"])){
    ajaxGuardTagModulo();

    $page   = max(1, intval($_POST["page"] ?? 1));
    $limit  = 10;
    $search = trim($_POST["q"] ?? '');
    $offset = ($page - 1) * $limit;

    $tipos = ControladorTagTipo::ctrMostrarTagTipo(null, null);
    $tipos = $tipos ?: [];

    /* ── Filtrar: activos + búsqueda por nombre o descripción ── */
    $filtrados = [];
    foreach($tipos as $t){
        if((int)($t["estado"] ?? 0) !== 1) continue;

        if($search !== ''){
            $enNombre = stripos($t["tag_tipo"], $search) !== false;
            $enDesc   = stripos($t["descripcion"] ?? '', $search) !== false;
            if(!$enNombre && !$enDesc) continue;
        }
        $filtrados[] = $t;
    }

    $total  = count($filtrados);
    $slice  = array_slice($filtrados, $offset, $limit);

    $data = [];
    foreach($slice as $t){
        $data[] = [
            "id"          => (int)$t["id"],
            "texto"       => $t["tag_tipo"],
            "color"       => $t["color"] ?: "#0d6efd",
            "descripcion" => $t["descripcion"] ?? ''
        ];
    }

    ajaxResponder([
        "status"   => "ok",
        "data"     => $data,
        "total"    => $total,
        "page"     => $page,
        "has_more" => ($offset + count($slice)) < $total
    ]);
}

/*=============================================
✅ NUEVO: Obtener tipos por IDs (para rellenar el modal Editar)
Recibe: ids (JSON array o array POST)
Devuelve: { status, data:[{id,texto,color,descripcion,estado}] }
→ A diferencia del listado paginado, aquí SÍ devolvemos inactivos,
  para que el modal editar no pierda tipos ya asignados.
=============================================*/
if(isset($_POST["obtenerTagTiposPorIds"])){
    ajaxGuardTagModulo();

    $ids = $_POST["ids"] ?? [];
    if(is_string($ids)){
        $ids = json_decode($ids, true) ?: [];
    }
    if(!is_array($ids)) $ids = [];
    $ids = array_map('intval', $ids);

    if(empty($ids)){
        ajaxResponder(["status" => "ok", "data" => []]);
    }

    $todosTipos = ControladorTagTipo::ctrMostrarTagTipo(null, null) ?: [];
    $mapa = [];
    foreach($todosTipos as $t){
        $mapa[(int)$t["id"]] = $t;
    }

    $resultado = [];
    foreach($ids as $id){
        if(isset($mapa[$id])){
            $t = $mapa[$id];
            $resultado[] = [
                "id"          => (int)$t["id"],
                "texto"       => $t["tag_tipo"],
                "color"       => $t["color"] ?: "#0d6efd",
                "descripcion" => $t["descripcion"] ?? '',
                "estado"      => (int)($t["estado"] ?? 0)
            ];
        }
    }
    ajaxResponder(["status" => "ok", "data" => $resultado]);
}

/*=============================================
Demás handlers — GUARD ANTES
=============================================*/

/* Editar (traer datos) */
if(isset($_POST["idTag"])){
    ajaxGuardTagModulo();
    $editar = new AjaxTag();
    $editar->idTag = $_POST["idTag"];
    $editar->ajaxEditarTag();
}

/* Activar */
if(isset($_POST["activarTag"])){
    ajaxGuardTagModulo();
    $activar = new AjaxTag();
    $activar->activarTag = $_POST["activarTag"];
    $activar->activarId  = $_POST["activarId"];
    $activar->ajaxActivarTag();
}

/* Fallback */
ajaxResponder(["status" => "error", "mensaje" => "Acción no reconocida"]);
?>