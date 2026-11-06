<?php
// Verificar sesión
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}

/*=============================================
✅ GUARD: solo Admin, Coordinador o Especialista/Técnico
=============================================*/
if (!ControladorActividad::puedeVerModuloActividad()) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-shield-alt me-2"></i>
            <strong>Acceso denegado.</strong>
            Esta sección es solo para usuarios con rol
            <strong>Administrador</strong>, <strong>Coordinador</strong>
            o <strong>Especialista/Técnico</strong>.
            <br><br>
            <a href="index.php?ruta=inicio" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
          </div>';
    return;
}

$esAdminActividad       = ControladorActividad::esAdminActividad();
$puedeEliminarActividad = ControladorActividad::puedeEliminarActividad();

// Procesar borrado ANTES de renderizar la tabla (controlador revalida)
ControladorActividad::ctrBorrarActividad();

/*=============================================
✅ Base URL absoluta (soporta subcarpetas)
   Se usa en el enlace del mensaje de WhatsApp
=============================================*/
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
    $scriptDir = '';
}
$baseUrlAct    = $protocol . '://' . $host . rtrim($scriptDir, '/\\') . '/';
$linkActividad = $baseUrlAct . 'index.php?ruta=actividad';

/*=============================================
✅ Precarga de catálogos (para WhatsApp)
   Se carga UNA sola vez por catálogo
=============================================*/
$mapaEntidadesWa = [];
foreach ((ControladorEntidad::ctrMostrarEntidad(null, null) ?: []) as $e) {
    $mapaEntidadesWa[(int)$e['id']] = $e;
}
$mapaTagsWa = [];
foreach ((ControladorTag::ctrMostrarTag(null, null) ?: []) as $t) {
    $mapaTagsWa[(int)$t['id']] = $t;
}
$mapaCoordsWa = [];
foreach ((ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: []) as $c) {
    $mapaCoordsWa[(int)$c['id']] = $c;
}
$mapaHostsWa = [];
foreach ((ControladorHost::ctrMostrarHost(null, null) ?: []) as $h) {
    $mapaHostsWa[(int)$h['id']] = $h;
}
$mapaUsersWa = [];
foreach ((ControladorUser::ctrMostrarUser(null, null) ?: []) as $u) {
    $mapaUsersWa[(int)$u['id']] = $u;
}

/*=============================================
Helpers de render
=============================================*/
function renderActividadTags($jsonIds, $tipo){
    $ids = json_decode($jsonIds ?? '[]', true);
    if(empty($ids) || !is_array($ids)) return '<span class="text-muted">—</span>';

    $html = '';
    if($tipo === 'entidad'){
        $lista = ControladorEntidad::ctrMostrarEntidad(null, null);
        $campo = 'entidad'; $icono = 'fa-city';
    } elseif($tipo === 'tag'){
        $lista = ControladorTag::ctrMostrarTag(null, null);
        $campo = 'tag'; $icono = 'fa-tag';
    } elseif($tipo === 'coordinacion'){
        $lista = ControladorCoordinacion::ctrMostrarCoordinacion(null, null);
        $campo = 'coordinacion'; $icono = 'fa-sitemap';
    } elseif($tipo === 'host'){
        $lista = ControladorHost::ctrMostrarHost(null, null);
        $campo = 'host'; $icono = 'fa-network-wired';
    } else { // user
        $lista = ControladorUser::ctrMostrarUser(null, null);
        $campo = 'user'; $icono = 'fa-user';
    }

    $mapa = [];
    if($lista && is_array($lista)){
        foreach($lista as $item){ $mapa[(int)$item["id"]] = $item; }
    }

    foreach($ids as $id){
        $id = (int)$id;
        if(isset($mapa[$id])){
            $item  = $mapa[$id];
            $color = $item["color"] ?? "#0d6efd";

            if($tipo === 'user'){
                $texto = !empty($item["name"]) ? $item["name"] : $item["user"];
            } else {
                $texto = $item[$campo];
            }

            $html .= '<span class="badge" style="background-color:'.htmlspecialchars($color).'; color:#fff; margin:2px; font-size:0.72rem; padding:5px 9px;">'
                  .  '<i class="fas '.$icono.' me-1"></i>'.htmlspecialchars($texto)
                  .  '</span>';
        } else {
            $html .= '<span class="badge bg-secondary me-1 mb-1">ID '.$id.'</span>';
        }
    }
    return $html ?: '<span class="text-muted">—</span>';
}

function decodificarFotosActividad($valor){
    if(empty($valor)) return [];
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

function renderActividadFotos($fotoValue, $altText){
    $fotos = decodificarFotosActividad($fotoValue);

    if(empty($fotos)){
        $fallback = "https://ui-avatars.com/api/?name=".urlencode(substr($altText, 0, 2))."&background=0d6efd&color=fff&size=64";
        return '<img src="'.htmlspecialchars($fallback).'"
                     class="rounded"
                     style="width:48px; height:48px; object-fit:cover; border:1px solid #ddd;">';
    }

    $fotosJson = htmlspecialchars(json_encode($fotos), ENT_QUOTES);
    $maxMostrar = 3;
    $total = count($fotos);

    $html = '<div class="foto-gallery d-flex flex-wrap gap-1 justify-content-center"
                  data-fotos=\''.$fotosJson.'\'>';

    foreach(array_slice($fotos, 0, $maxMostrar) as $i => $ruta){
        $html .= '<img src="'.htmlspecialchars($ruta).'"
                       class="rounded foto-thumb"
                       data-index="'.$i.'"
                       style="width:36px; height:36px; object-fit:cover; border:1px solid #ddd; cursor:zoom-in;"
                       onerror="this.src=\'https://ui-avatars.com/api/?name=A&background=0d6efd&color=fff&size=64\'">';
    }

    if($total > $maxMostrar){
        $extra = $total - $maxMostrar;
        $html .= '<span class="badge text-bg-secondary d-flex align-items-center justify-content-center"
                        style="width:36px; height:36px; font-size:0.75rem;"
                        title="'.$extra.' foto(s) más">+'.$extra.'</span>';
    }

    $html .= '</div>';
    return $html;
}
?>

<style>
    .foto-thumb{ transition: transform .15s ease, box-shadow .15s ease; }
    .foto-thumb:hover{ transform: scale(1.12); box-shadow: 0 4px 12px rgba(13,110,253,.3); }

    .foto-edit-item{ position: relative; display: inline-block; transition: transform .15s ease; }
    .foto-edit-item img{
        width: 80px; height: 80px; object-fit: cover;
        border: 2px solid #ddd; border-radius: 8px; cursor: zoom-in;
        transition: opacity .2s ease, filter .2s ease, border-color .2s ease;
    }
    .foto-edit-item.marcada img{ opacity: 0.35; filter: grayscale(100%); border-color: #dc3545; }
    .foto-edit-item .btn-eliminar-foto{
        position: absolute; top: -8px; right: -8px;
        width: 26px; height: 26px; padding: 0; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.75rem; border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,.25); z-index: 2;
    }
    .foto-edit-item .badge-eliminar{
        position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
        font-size: 0.55rem; white-space: nowrap; pointer-events: none;
    }
    .foto-preview-item{ position: relative; display: inline-block; }
    .foto-preview-item img{
        width: 80px; height: 80px; object-fit: cover;
        border-radius: 8px; border: 1px solid #ddd;
    }
    .foto-preview-item .badge{
        position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
        font-size: 0.55rem; max-width: 90px;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    /* Botón WhatsApp en la columna de acciones */
    .btnWhatsappActividad {
        background-color: #25D366 !important;
        border-color:     #25D366 !important;
        color: #fff !important;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .btnWhatsappActividad:hover {
        background-color: #1ebe5d !important;
        border-color:     #1ebe5d !important;
        color: #fff !important;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(37,211,102,0.4);
    }
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="fas fa-clipboard-list me-2"></i> Gestión de Actividades
            <?php if ($esAdminActividad): ?>
                <span class="badge text-bg-danger ms-2" title="Vista Administrador">
                    <i class="fas fa-user-shield me-1"></i> Admin
                </span>
            <?php endif; ?>
        </h3>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearActividad">
            <i class="fas fa-plus me-1"></i> Nueva Actividad
        </button>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover table-striped align-middle tablas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th style="width: 70px;">#</th>
                    <th>Actividad</th>
                    <th>Entidades</th>
                    <th>Tags</th>
                    <th>Coordinación</th>
                    <th>Usuarios</th>
                    <th>Descripción</th>
                    <th style="width: 150px;">Última modificación</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 200px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                /* ✅ Solo actividades visibles para el usuario (admin ve todas) */
                $actividades = ControladorActividad::actividadesVisibles();

                if(!empty($actividades) && is_array($actividades)){
                    foreach($actividades as $value){

                        $modificacion = !empty($value["modificacion"])
                            ? date("d/m/Y H:i", strtotime($value["modificacion"]))
                            : "—";

                        $descPlano = strip_tags($value["descripcion"] ?? '');
                        $descCorta = mb_strlen($descPlano) > 60
                            ? mb_substr($descPlano, 0, 60).'…'
                            : $descPlano;

                        /*=============================================
                        ✅ Preparar mensaje de WhatsApp para ESTA fila
                        =============================================*/
                        $waEntidades = [];
                        foreach ((json_decode($value["id_entidad"] ?? '[]', true) ?: []) as $idE) {
                            $idE = (int)$idE;
                            if (isset($mapaEntidadesWa[$idE])) {
                                $waEntidades[] = $mapaEntidadesWa[$idE]['entidad'];
                            }
                        }
                        $waTags = [];
                        foreach ((json_decode($value["id_tag"] ?? '[]', true) ?: []) as $idT) {
                            $idT = (int)$idT;
                            if (isset($mapaTagsWa[$idT])) {
                                $waTags[] = $mapaTagsWa[$idT]['tag'];
                            }
                        }
                        $waCoords = [];
                        foreach ((json_decode($value["id_coordinacion"] ?? '[]', true) ?: []) as $idC) {
                            $idC = (int)$idC;
                            if (isset($mapaCoordsWa[$idC])) {
                                $waCoords[] = $mapaCoordsWa[$idC]['coordinacion'];
                            }
                        }
                        $waHosts = [];
                        foreach ((json_decode($value["id_host"] ?? '[]', true) ?: []) as $idH) {
                            $idH = (int)$idH;
                            if (isset($mapaHostsWa[$idH])) {
                                $waHosts[] = $mapaHostsWa[$idH]['host'];
                            }
                        }
                        $waUsers = [];
                        foreach ((json_decode($value["id_user"] ?? '[]', true) ?: []) as $idU) {
                            $idU = (int)$idU;
                            if (isset($mapaUsersWa[$idU])) {
                                $u = $mapaUsersWa[$idU];
                                $waUsers[] = !empty($u['name']) ? $u['name'] : $u['user'];
                            }
                        }

                        $waFotosCount = count(decodificarFotosActividad($value["foto"] ?? ''));

                        $waDesc = strip_tags($value["descripcion"] ?? '');
                        $waDesc = html_entity_decode($waDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $waDesc = preg_replace('/\s+/', ' ', $waDesc);
                        $waDesc = trim($waDesc);
                        if (mb_strlen($waDesc) > 250) {
                            $waDesc = mb_substr($waDesc, 0, 250) . '…';
                        }

                        $msgWhatsapp  = "📋 *ACTIVIDAD - AIT*\n";
                        $msgWhatsapp .= "━━━━━━━━━━━━━\n\n";
                        $msgWhatsapp .= "🆔 *ID:* #" . $value["id"] . "\n";
                        $msgWhatsapp .= "📝 *Actividad:* " . $value["actividad"] . "\n";
                        $msgWhatsapp .= "📦 *Cantidad:* " . $value["cantidad"] . "\n\n";
                        $msgWhatsapp .= "🏢 *Entidad(es):* " . (empty($waEntidades) ? '—' : implode(', ', $waEntidades)) . "\n";
                        $msgWhatsapp .= "🏷️ *Tag(s):* " . (empty($waTags) ? '—' : implode(', ', $waTags)) . "\n";
                        $msgWhatsapp .= "🏗️ *Coordinación(es):* " . (empty($waCoords) ? '—' : implode(', ', $waCoords)) . "\n";
                        $msgWhatsapp .= "🖥️ *Host(s):* " . (empty($waHosts) ? '—' : implode(', ', $waHosts)) . "\n";
                        $msgWhatsapp .= "👥 *Usuario(s):* " . (empty($waUsers) ? '—' : implode(', ', $waUsers)) . "\n\n";
                        if ($waDesc !== '') {
                            $msgWhatsapp .= "📄 *Descripción:*\n" . $waDesc . "\n\n";
                        }
                        $msgWhatsapp .= "📅 *Última modificación:* " . $modificacion . "\n";
                        $msgWhatsapp .= "📸 *Fotos:* " . $waFotosCount . "\n\n";
                        $msgWhatsapp .= "━━━━━━━━━━━━━\n";
                        $msgWhatsapp .= "🔗 *Ver en el sistema:* " . $linkActividad;

                        /*=============================================
                        Render de la fila
                        =============================================*/
                        echo '<tr>
                            <td><span class="badge text-bg-dark">#'.(int)$value["id"].'</span></td>
                            <td>
                                <span class="badge" style="background-color:#0d6efd; color:#fff; font-size:0.85rem; padding:7px 12px;">
                                    <i class="fas fa-clipboard-list me-1"></i> '.htmlspecialchars($value["actividad"]).'
                                </span>
                            </td>
                            <td>'.renderActividadTags($value["id_entidad"]      ?? '', 'entidad').'</td>
                            <td>'.renderActividadTags($value["id_tag"]          ?? '', 'tag').'</td>
                            <td>'.renderActividadTags($value["id_coordinacion"] ?? '', 'coordinacion').'</td>
                            <td>'.renderActividadTags($value["id_user"]         ?? '', 'user').'</td>
                            <td><span title="'.htmlspecialchars($descPlano).'">'.htmlspecialchars($descCorta).'</span></td>
                            <td><small class="text-muted">'.$modificacion.'</small></td>
                            <td>';

                        if($value["estado"] == 1){
                            echo '<button class="btn btn-success btn-sm btnActivarActividad" idActividad="'.$value["id"].'" estadoActividad="0">
                                    <i class="fas fa-check-circle"></i> Activo
                                  </button>';
                        }else{
                            echo '<button class="btn btn-danger btn-sm btnActivarActividad" idActividad="'.$value["id"].'" estadoActividad="1">
                                    <i class="fas fa-times-circle"></i> Inactivo
                                  </button>';
                        }

                        echo '</td>
                            <td>
                                <div class="btn-group" role="group">

                                    <!-- Ver -->
                                    <button class="btn btn-info btn-sm btnVerActividad"
                                            idActividad="'.$value["id"].'"
                                            title="Ver información completa">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <!-- ✅ Compartir por WhatsApp -->
                                    <button type="button"
                                            class="btn btn-sm btnWhatsappActividad"
                                            data-msg="'.htmlspecialchars($msgWhatsapp, ENT_QUOTES).'"
                                            title="Compartir por WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </button>

                                    <!-- Editar -->
                                    <button class="btn btn-warning btn-sm btnEditarActividad"
                                            idActividad="'.$value["id"].'"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarActividad"
                                            title="Editar">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>';

                                    /* ✅ Eliminar SOLO si es admin */
                                    if ($puedeEliminarActividad) {
                                        echo '<button class="btn btn-danger btn-sm btnEliminarActividad"
                                                idActividad="'.$value["id"].'"
                                                nombre="'.htmlspecialchars($value["actividad"]).'"
                                                title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                              </button>';
                                    }

                        echo '</div>
                            </td>
                        </tr>';
                    }
                } else {
                    echo '<tr><td colspan="10" class="text-center text-muted">No hay actividades registradas visibles para ti</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ MODAL CREAR ═══════════════ -->
<div class="modal fade" id="modalCrearActividad" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="formCrearActividad" method="post" enctype="multipart/form-data">
            <input type="hidden" name="nuevaActividad" value="1">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nueva Actividad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nombre de la Actividad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nuevaActividadNombre" id="nuevaActividadNombre"
                               placeholder="Ej: Instalación de software" maxlength="255" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="nuevaCantidad" id="nuevaCantidad"
                               min="1" step="1" value="1" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-city me-1 text-muted"></i> Entidad(es) <span class="text-danger">*</span></label>
                        <select id="nuevaIdEntidad" name="nuevaIdEntidad[]" multiple placeholder="Seleccione entidades..."></select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-tags me-1 text-muted"></i> Tag(s) <span class="text-danger">*</span></label>
                        <select id="nuevaIdTag" name="nuevaIdTag[]" multiple placeholder="Seleccione tags..."></select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-sitemap me-1 text-muted"></i> Coordinación(es) <span class="text-muted small">(opcional)</span></label>
                        <select id="nuevaIdCoordinacion" name="nuevaIdCoordinacion[]" multiple placeholder="Seleccione coordinaciones..."></select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-network-wired me-1 text-muted"></i> Host(s) <span class="text-muted small">(opcional)</span></label>
                        <select id="nuevaIdHost" name="nuevaIdHost[]" multiple placeholder="Seleccione hosts..."></select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-users me-1 text-muted"></i> Usuario(s) <span class="text-danger">*</span> <small class="text-muted">(Admin / Coord / Especialista)</small></label>
                        <select id="nuevaIdUser" name="nuevaIdUser[]" multiple placeholder="Seleccione usuarios..."></select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">
                            Fotos
                            <span class="text-muted small">(opcional — varias)</span>
                        </label>
                        <input type="file" class="form-control" name="nuevaFoto[]" id="nuevaFoto"
                               accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                        <small class="text-muted d-block mt-1">
                            JPG, PNG, GIF o WEBP · Máx. 5 MB por imagen · <strong>Máximo 4 fotos</strong>
                            <span id="contadorFotosNuevo" class="badge text-bg-secondary ms-1">0 / 4</span>
                        </small>
                        <div id="nuevaFotoPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Descripción <span class="text-danger">*</span></label>
                        <div id="editorNuevoActividad" style="min-height:180px; background:#fff;"></div>
                        <input type="hidden" name="nuevaDescripcion" id="nuevaDescripcion">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Guardar Actividad
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════ MODAL EDITAR ═══════════════ -->
<div class="modal fade" id="modalEditarActividad" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="formEditarActividad" method="post" enctype="multipart/form-data">
            <input type="hidden" name="editarActividad" value="1">
            <input type="hidden" name="idActividad" id="idActividad">
            <input type="hidden" name="fotosAEliminar" id="fotosAEliminar" value="[]">

            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Editar Actividad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nombre de la Actividad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="editarActividadNombre" id="editarActividadNombre" maxlength="255" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="editarCantidad" id="editarCantidad" min="1" step="1" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-city me-1 text-muted"></i> Entidad(es) <span class="text-danger">*</span></label>
                        <select id="editarIdEntidad" name="editarIdEntidad[]" multiple placeholder="Seleccione entidades..."></select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-tags me-1 text-muted"></i> Tag(s) <span class="text-danger">*</span></label>
                        <select id="editarIdTag" name="editarIdTag[]" multiple placeholder="Seleccione tags..."></select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-sitemap me-1 text-muted"></i> Coordinación(es) <span class="text-muted small">(opcional)</span></label>
                        <select id="editarIdCoordinacion" name="editarIdCoordinacion[]" multiple placeholder="Seleccione coordinaciones..."></select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-network-wired me-1 text-muted"></i> Host(s) <span class="text-muted small">(opcional)</span></label>
                        <select id="editarIdHost" name="editarIdHost[]" multiple placeholder="Seleccione hosts..."></select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label"><i class="fas fa-users me-1 text-muted"></i> Usuario(s) <span class="text-danger">*</span> <small class="text-muted">(Admin / Coord / Especialista)</small></label>
                        <select id="editarIdUser" name="editarIdUser[]" multiple placeholder="Seleccione usuarios..."></select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label d-flex align-items-center gap-2 flex-wrap">
                            Fotos actuales
                            <span id="contadorFotosEdit" class="badge text-bg-secondary">0 / 4</span>
                            <small class="text-muted ms-2">— click en <i class="fas fa-times text-danger"></i> para eliminar</small>
                        </label>
                        <div id="fotoActualPreview" class="d-flex flex-wrap gap-2 p-2 border rounded bg-body-tertiary" style="min-height: 60px;">
                            <small class="text-muted align-self-center">Sin fotos actuales</small>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Agregar fotos nuevas <span class="text-muted small">(opcional)</span></label>
                        <input type="file" class="form-control" name="editarFoto[]" id="editarFoto"
                               accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                        <small class="text-muted d-block mt-1">Se <strong>agregan</strong> a las que sobrevivan. Máximo 4 fotos por actividad en total.</small>
                        <div id="editarFotoPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Descripción <span class="text-danger">*</span></label>
                        <div id="editorEditarActividad" style="min-height:180px; background:#fff;"></div>
                        <input type="hidden" name="editarDescripcion" id="editarDescripcion">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-save me-1"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════ MODAL VER (VISTA COMPLETA) ═══════════════ -->
<div class="modal fade" id="modalVerActividad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i> Ver Actividad</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="verActividadBody">
                <div class="text-center text-muted py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2 mb-0">Cargando información...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ LIGHTBOX ═══════════════ -->
<div class="modal fade" id="modalLightboxFoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fas fa-image me-2"></i>
                    <span id="lightboxTitle">Foto</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0"
                 style="background:#0c0f16; min-height:60vh; display:flex; align-items:center; justify-content:center;">
                <img id="lightboxImg" src="" alt="Foto" style="max-width:100%; max-height:80vh; object-fit:contain;">
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <button type="button" class="btn btn-outline-light btn-sm" id="lightboxPrev">
                    <i class="fas fa-chevron-left"></i> Anterior
                </button>
                <span id="lightboxCounter" class="text-white-50 small">1 / 1</span>
                <button type="button" class="btn btn-outline-light btn-sm" id="lightboxNext">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ SCRIPTS ═══════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    /* Toast */
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    const MAX_FOTOS        = 4;
    const MAX_MB           = 5;
    const MAX_BYTES        = MAX_MB * 1024 * 1024;
    const EXT_PERMITIDAS   = ['jpg','jpeg','png','gif','webp'];
    const MIMES_PERMITIDOS = ['image/jpeg','image/jpg','image/pjpeg','image/png','image/gif','image/webp'];

    function validarArchivos(files){
        if(!files || files.length === 0) return { ok: true };
        if(files.length > MAX_FOTOS) return { ok: false, msg: 'Máximo ' + MAX_FOTOS + ' fotos por actividad' };

        for(let i = 0; i < files.length; i++){
            const f = files[i];
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            if(!EXT_PERMITIDAS.includes(ext)){
                return { ok: false, msg: 'El archivo #' + (i+1) + ' no es una imagen permitida' };
            }
            if(!MIMES_PERMITIDOS.includes(f.type)){
                return { ok: false, msg: 'El archivo #' + (i+1) + ' no es una imagen válida' };
            }
            if(f.size > MAX_BYTES){
                return { ok: false, msg: 'La imagen #' + (i+1) + ' supera los ' + MAX_MB + ' MB' };
            }
        }
        return { ok: true };
    }

    /* Preview de archivos nuevos */
    function renderPreviewFiles(files, containerId){
        const container = document.getElementById(containerId);
        if(!container) return;

        container.innerHTML = '';
        if(!files || files.length === 0) return;

        Array.from(files).forEach((f) => {
            const reader = new FileReader();
            reader.onload = function(e){
                const wrap = document.createElement('div');
                wrap.className = 'foto-preview-item';

                const img = document.createElement('img');
                img.src = e.target.result;

                const lbl = document.createElement('span');
                lbl.className = 'badge text-bg-primary';
                lbl.textContent = f.name.length > 12 ? f.name.substr(0, 12) + '…' : f.name;

                wrap.appendChild(img);
                wrap.appendChild(lbl);
                container.appendChild(wrap);
            };
            reader.readAsDataURL(f);
        });
    }

    function actualizarContadorNuevo(){
        const input = document.getElementById('nuevaFoto');
        const badge = document.getElementById('contadorFotosNuevo');
        if(!input || !badge) return;
        const cant = input.files ? input.files.length : 0;
        badge.textContent = cant + ' / ' + MAX_FOTOS;
        badge.className = 'badge ms-1 ' + (cant > MAX_FOTOS ? 'text-bg-danger' : 'text-bg-secondary');
    }

    /* Estado EDIT */
    let fotosActualesEdit = [];
    let fotosEliminarSet  = new Set();

    function actualizarContadorEdit(){
        const badge = document.getElementById('contadorFotosEdit');
        const input = document.getElementById('editarFoto');
        if(!badge) return;

        const restantes = fotosActualesEdit.length - fotosEliminarSet.size;
        const nuevas    = input && input.files ? input.files.length : 0;
        const total     = restantes + nuevas;

        badge.textContent = total + ' / ' + MAX_FOTOS;
        badge.className = 'badge ' + (total > MAX_FOTOS ? 'text-bg-danger' : 'text-bg-secondary');
    }

    function renderFotosActualesEdit(){
        const preview = document.getElementById('fotoActualPreview');
        if(!preview) return;

        preview.innerHTML = '';
        if(fotosActualesEdit.length === 0){
            preview.innerHTML = '<small class="text-muted align-self-center">Sin fotos actuales</small>';
            actualizarContadorEdit();
            return;
        }

        const fotosJson = JSON.stringify(fotosActualesEdit);

        fotosActualesEdit.forEach((ruta, idx) => {
            const wrap = document.createElement('div');
            wrap.className = 'foto-edit-item';
            wrap.dataset.ruta = ruta;

            const img = document.createElement('img');
            img.src = ruta;
            img.dataset.fotos = fotosJson;
            img.dataset.index = idx;
            img.title = 'Click para ampliar';
            img.onerror = function(){
                this.src = 'https://ui-avatars.com/api/?name=A&background=0d6efd&color=fff&size=64';
            };

            const btnDel = document.createElement('button');
            btnDel.type = 'button';
            btnDel.className = 'btn btn-danger btn-eliminar-foto';
            btnDel.innerHTML = '<i class="fas fa-times"></i>';
            btnDel.title = 'Eliminar esta foto';
            btnDel.dataset.ruta = ruta;

            btnDel.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();

                const r = this.dataset.ruta;
                const parent = this.closest('.foto-edit-item');

                if(fotosEliminarSet.has(r)){
                    fotosEliminarSet.delete(r);
                    parent.classList.remove('marcada');
                    this.className = 'btn btn-danger btn-eliminar-foto';
                    this.innerHTML = '<i class="fas fa-times"></i>';
                    this.title = 'Eliminar esta foto';

                    const badge = parent.querySelector('.badge-eliminar');
                    if(badge) badge.remove();
                } else {
                    fotosEliminarSet.add(r);
                    parent.classList.add('marcada');
                    this.className = 'btn btn-warning btn-eliminar-foto';
                    this.innerHTML = '<i class="fas fa-undo"></i>';
                    this.title = 'Restaurar foto';

                    if(!parent.querySelector('.badge-eliminar')){
                        const badge = document.createElement('span');
                        badge.className = 'badge text-bg-danger badge-eliminar';
                        badge.textContent = 'Se eliminará';
                        parent.appendChild(badge);
                    }
                }

                actualizarContadorEdit();
            });

            wrap.appendChild(img);
            wrap.appendChild(btnDel);
            preview.appendChild(wrap);
        });

        actualizarContadorEdit();
    }

    /* Inputs de archivos */
    const inputNuevaFoto = document.getElementById('nuevaFoto');
    if(inputNuevaFoto){
        inputNuevaFoto.addEventListener('change', function(){
            const check = validarArchivos(this.files);
            if(!check.ok){
                Toast.fire({ icon: 'error', title: check.msg });
                this.value = '';
                renderPreviewFiles([], 'nuevaFotoPreview');
                actualizarContadorNuevo();
                return;
            }
            renderPreviewFiles(this.files, 'nuevaFotoPreview');
            actualizarContadorNuevo();
        });
    }

    const inputEditarFoto = document.getElementById('editarFoto');
    if(inputEditarFoto){
        inputEditarFoto.addEventListener('change', function(){
            const check = validarArchivos(this.files);
            if(!check.ok){
                Toast.fire({ icon: 'error', title: check.msg });
                this.value = '';
                renderPreviewFiles([], 'editarFotoPreview');
                actualizarContadorEdit();
                return;
            }

            const restantes = fotosActualesEdit.length - fotosEliminarSet.size;
            const total = restantes + this.files.length;
            if(total > MAX_FOTOS){
                Toast.fire({
                    icon: 'error',
                    title: 'Límite superado: ' + restantes + ' actuales + ' + this.files.length + ' nuevas = ' + total + ' (máx. ' + MAX_FOTOS + ')'
                });
                this.value = '';
                renderPreviewFiles([], 'editarFotoPreview');
                actualizarContadorEdit();
                return;
            }

            renderPreviewFiles(this.files, 'editarFotoPreview');
            actualizarContadorEdit();
        });
    }

    /* Parsear fotos */
    function parseFotos(fotoValue){
        if(!fotoValue) return [];
        const v = String(fotoValue).trim();
        if(v === '') return [];

        if(v.startsWith('[')){
            try {
                const arr = JSON.parse(v);
                return Array.isArray(arr) ? arr.filter(x => typeof x === 'string' && x) : [];
            } catch(e) { return []; }
        }
        return [v];
    }

    /* Tom Select */
    function renderOpcion(data, escape) {
        const color = data.color || '#0d6efd';
        return `<div><span class="tag-dot" style="background-color:${color};"></span><span class="option-label">${escape(data.text)}</span></div>`;
    }
    function renderItem(data, escape) {
        const color = data.color || '#0d6efd';
        return `<div style="--tag-color:${color};" class="item-tag">${escape(data.text)}</div>`;
    }
    const tomSelectConfig = {
        plugins: ['remove_button'],
        persist: false,
        create: false,
        maxItems: null,
        valueField: 'id',
        labelField: 'text',
        searchField: 'text',
        render: { option: renderOpcion, item: renderItem }
    };

    /* Quill */
    const quillToolbar = [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        [{ 'align': [] }],
        ['blockquote', 'code-block'],
        ['link'], ['clean']
    ];
    let quillNuevo  = null;
    let quillEditar = null;

    let tsNuevaEnt, tsNuevaTag, tsNuevaCoord, tsNuevaHost, tsNuevaUser;
    let tsEditEnt,  tsEditTag,  tsEditCoord,  tsEditHost,  tsEditUser;
    let tsNuevoInitialized  = false;
    let tsEditarInitialized = false;

    let cacheEnt = null, cacheTag = null, cacheCoord = null, cacheHost = null, cacheUser = null;
    let pendingActividadData = null;

    function fetchEntidades(){
        if(cacheEnt) return Promise.resolve(cacheEnt);
        return fetch('ajax/user.ajax.php', { method:'POST', body:(()=>{const fd=new FormData();fd.append('listarEntidadesActivas','1');return fd;})() })
            .then(r => r.json())
            .then(r => { cacheEnt = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheEnt; })
            .catch(() => []);
    }
    function fetchTags(){
        if(cacheTag) return Promise.resolve(cacheTag);
        return fetch('ajax/tservicio.ajax.php', { method:'POST', body:(()=>{const fd=new FormData();fd.append('listarTagsActivos','1');return fd;})() })
            .then(r => r.json())
            .then(r => { cacheTag = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheTag; })
            .catch(() => []);
    }
    function fetchCoordinaciones(){
        if(cacheCoord) return Promise.resolve(cacheCoord);
        return fetch('ajax/actividad.ajax.php', { method:'POST', body:(()=>{const fd=new FormData();fd.append('listarCoordinacionesActivas','1');return fd;})() })
            .then(r => r.json())
            .then(r => { cacheCoord = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheCoord; })
            .catch(() => []);
    }
    function fetchHosts(){
        if(cacheHost) return Promise.resolve(cacheHost);
        return fetch('ajax/tservicio.ajax.php', { method:'POST', body:(()=>{const fd=new FormData();fd.append('listarHostsActivos','1');return fd;})() })
            .then(r => r.json())
            .then(r => { cacheHost = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheHost; })
            .catch(() => []);
    }
    function fetchUsuarios(){
        if(cacheUser) return Promise.resolve(cacheUser);
        return fetch('ajax/actividad.ajax.php', { method:'POST', body:(()=>{const fd=new FormData();fd.append('listarUsuariosRolesActividad','1');return fd;})() })
            .then(r => r.json())
            .then(r => { cacheUser = (r.status === 'ok' && Array.isArray(r.data)) ? r.data : []; return cacheUser; })
            .catch(() => []);
    }

    function addOptionsToTS(ts, data){
        data.forEach(item => {
            if(!ts.options[String(item.id)]){
                ts.addOption({ id: String(item.id), text: item.texto, color: item.color });
            }
        });
        ts.refreshOptions(false);
    }
    function setTSValue(ts, jsonIds){
        let ids = [];
        try { ids = JSON.parse(jsonIds || '[]'); } catch(e){ ids = []; }
        if(!Array.isArray(ids)) ids = [];
        ids = ids.map(String);
        ids.forEach(id => {
            if(!ts.options[id]) ts.addOption({ id: id, text: 'ID '+id, color: '#6c757d' });
        });
        ts.setValue(ids, true);
        ts.refreshOptions(false);
    }

    async function initCrearTS(){
        if(tsNuevoInitialized) return;
        const [ents, tags, coords, hosts, users] = await Promise.all([
            fetchEntidades(), fetchTags(), fetchCoordinaciones(), fetchHosts(), fetchUsuarios()
        ]);

        tsNuevaEnt   = new TomSelect('#nuevaIdEntidad',      Object.assign({}, tomSelectConfig));
        tsNuevaTag   = new TomSelect('#nuevaIdTag',          Object.assign({}, tomSelectConfig));
        tsNuevaCoord = new TomSelect('#nuevaIdCoordinacion', Object.assign({}, tomSelectConfig));
        tsNuevaHost  = new TomSelect('#nuevaIdHost',         Object.assign({}, tomSelectConfig));
        tsNuevaUser  = new TomSelect('#nuevaIdUser',         Object.assign({}, tomSelectConfig));

        addOptionsToTS(tsNuevaEnt,   ents);
        addOptionsToTS(tsNuevaTag,   tags);
        addOptionsToTS(tsNuevaCoord, coords);
        addOptionsToTS(tsNuevaHost,  hosts);
        addOptionsToTS(tsNuevaUser,  users);

        tsNuevoInitialized = true;
    }

    async function initEditarTS(){
        if(tsEditarInitialized) return;
        const [ents, tags, coords, hosts, users] = await Promise.all([
            fetchEntidades(), fetchTags(), fetchCoordinaciones(), fetchHosts(), fetchUsuarios()
        ]);

        tsEditEnt   = new TomSelect('#editarIdEntidad',      Object.assign({}, tomSelectConfig));
        tsEditTag   = new TomSelect('#editarIdTag',          Object.assign({}, tomSelectConfig));
        tsEditCoord = new TomSelect('#editarIdCoordinacion', Object.assign({}, tomSelectConfig));
        tsEditHost  = new TomSelect('#editarIdHost',         Object.assign({}, tomSelectConfig));
        tsEditUser  = new TomSelect('#editarIdUser',         Object.assign({}, tomSelectConfig));

        addOptionsToTS(tsEditEnt,   ents);
        addOptionsToTS(tsEditTag,   tags);
        addOptionsToTS(tsEditCoord, coords);
        addOptionsToTS(tsEditHost,  hosts);
        addOptionsToTS(tsEditUser,  users);

        tsEditarInitialized = true;
    }

    /* MODAL CREAR */
    const modalCrearEl = document.getElementById('modalCrearActividad');
    if(modalCrearEl){
        modalCrearEl.addEventListener('shown.bs.modal', async function(){
            await initCrearTS();
            if(!quillNuevo){
                quillNuevo = new Quill('#editorNuevoActividad', {
                    theme: 'snow',
                    placeholder: 'Escriba una descripción de la actividad...',
                    modules: { toolbar: quillToolbar }
                });
                quillNuevo.on('text-change', function(){
                    document.getElementById('nuevaDescripcion').value = quillNuevo.root.innerHTML;
                });
            }
            tsNuevaEnt.refreshOptions(false);
            tsNuevaTag.refreshOptions(false);
            tsNuevaCoord.refreshOptions(false);
            tsNuevaHost.refreshOptions(false);
            tsNuevaUser.refreshOptions(false);
            actualizarContadorNuevo();
        });

        modalCrearEl.addEventListener('hidden.bs.modal', function(){
            if(tsNuevaEnt)   tsNuevaEnt.clear(true);
            if(tsNuevaTag)   tsNuevaTag.clear(true);
            if(tsNuevaCoord) tsNuevaCoord.clear(true);
            if(tsNuevaHost)  tsNuevaHost.clear(true);
            if(tsNuevaUser)  tsNuevaUser.clear(true);
            if(quillNuevo)   quillNuevo.setContents([]);
            const f = document.getElementById('formCrearActividad');
            if(f) f.reset();
            const hiddenDesc = document.getElementById('nuevaDescripcion');
            if(hiddenDesc) hiddenDesc.value = '';
            renderPreviewFiles([], 'nuevaFotoPreview');
            actualizarContadorNuevo();
        });
    }

    /* MODAL EDITAR */
    const modalEditarEl = document.getElementById('modalEditarActividad');
    if(modalEditarEl){
        modalEditarEl.addEventListener('shown.bs.modal', async function(){
            await initEditarTS();

            if(!quillEditar){
                quillEditar = new Quill('#editorEditarActividad', {
                    theme: 'snow',
                    placeholder: 'Escriba una descripción de la actividad...',
                    modules: { toolbar: quillToolbar }
                });
                quillEditar.on('text-change', function(){
                    document.getElementById('editarDescripcion').value = quillEditar.root.innerHTML;
                });
            }

            if(pendingActividadData){
                const d = pendingActividadData;

                document.getElementById('idActividad').value           = d.id;
                document.getElementById('editarActividadNombre').value = d.actividad || '';
                document.getElementById('editarCantidad').value        = d.cantidad || 1;

                fotosActualesEdit = parseFotos(d.foto);
                fotosEliminarSet.clear();
                document.getElementById('fotosAEliminar').value = '[]';

                renderFotosActualesEdit();

                setTSValue(tsEditEnt,   d.id_entidad);
                setTSValue(tsEditTag,   d.id_tag);
                setTSValue(tsEditCoord, d.id_coordinacion);
                setTSValue(tsEditHost,  d.id_host);
                setTSValue(tsEditUser,  d.id_user);

                const html = d.descripcion || '';
                quillEditar.clipboard.dangerouslyPasteHTML(html);
                document.getElementById('editarDescripcion').value = quillEditar.root.innerHTML;

                renderPreviewFiles([], 'editarFotoPreview');
                const editarInput = document.getElementById('editarFoto');
                if(editarInput) editarInput.value = '';

                pendingActividadData = null;
            }

            tsEditEnt.refreshOptions(false);
            tsEditTag.refreshOptions(false);
            tsEditCoord.refreshOptions(false);
            tsEditHost.refreshOptions(false);
            tsEditUser.refreshOptions(false);
            actualizarContadorEdit();
        });

        modalEditarEl.addEventListener('hidden.bs.modal', function(){
            fotosActualesEdit = [];
            fotosEliminarSet.clear();
            const hf = document.getElementById('fotosAEliminar');
            if(hf) hf.value = '[]';
            renderPreviewFiles([], 'editarFotoPreview');
            const fi = document.getElementById('editarFoto');
            if(fi) fi.value = '';
        });
    }

    /* SUBMIT CREAR */
    const formCrear = document.getElementById('formCrearActividad');
    if(formCrear){
        formCrear.addEventListener('submit', function(e){
            e.preventDefault();

            const check = validarArchivos(inputNuevaFoto.files);
            if(!check.ok){
                Toast.fire({ icon: 'error', title: check.msg });
                return;
            }

            document.getElementById('nuevaDescripcion').value = quillNuevo ? quillNuevo.root.innerHTML : '';

            const formData = new FormData(this);
            const nombre = document.getElementById('nuevaActividadNombre').value.trim();
            formData.delete('nuevaActividadNombre');
            formData.set('nuevaActividad', nombre);

            Swal.fire({
                title: 'Guardando actividad...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/actividad.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Actividad guardada' });
                        setTimeout(() => location.reload(), 1800);
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error al guardar' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.close();
                    Toast.fire({ icon: 'error', title: 'Error de conexión con el servidor' });
                });
        });
    }

    /* SUBMIT EDITAR */
    const formEditar = document.getElementById('formEditarActividad');
    if(formEditar){
        formEditar.addEventListener('submit', function(e){
            e.preventDefault();

            if(inputEditarFoto && inputEditarFoto.files.length > 0){
                const check = validarArchivos(inputEditarFoto.files);
                if(!check.ok){
                    Toast.fire({ icon: 'error', title: check.msg });
                    return;
                }
            }

            const restantes = fotosActualesEdit.length - fotosEliminarSet.size;
            const nuevas    = inputEditarFoto && inputEditarFoto.files ? inputEditarFoto.files.length : 0;
            if(restantes + nuevas > MAX_FOTOS){
                Toast.fire({
                    icon: 'error',
                    title: 'El límite es ' + MAX_FOTOS + ' fotos. Quedarían ' + (restantes + nuevas) + '.'
                });
                return;
            }

            document.getElementById('fotosAEliminar').value = JSON.stringify(Array.from(fotosEliminarSet));
            document.getElementById('editarDescripcion').value = quillEditar ? quillEditar.root.innerHTML : '';

            const formData = new FormData(this);
            const nombre = document.getElementById('editarActividadNombre').value.trim();
            formData.delete('editarActividadNombre');
            formData.set('editarActividad', nombre);

            Swal.fire({
                title: 'Actualizando actividad...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('ajax/actividad.ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(respuesta => {
                    Swal.close();
                    if(respuesta.status === 'success'){
                        Toast.fire({ icon: 'success', title: respuesta.mensaje || 'Actividad actualizada' });
                        setTimeout(() => location.reload(), 1800);
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error al actualizar' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.close();
                    Toast.fire({ icon: 'error', title: 'Error de conexión con el servidor' });
                });
        });
    }

    /* ACTIVAR / DESACTIVAR */
    document.querySelectorAll('.btnActivarActividad').forEach(btn => {
        btn.addEventListener('click', function() {
            const idActividad = this.getAttribute('idActividad');
            const estadoActividad = this.getAttribute('estadoActividad');
            const btnElement = this;

            const datos = new FormData();
            datos.append('activarActividad', estadoActividad);
            datos.append('activarId', idActividad);

            fetch('ajax/actividad.ajax.php', { method: 'POST', body: datos })
                .then(res => res.json())
                .then(respuesta => {
                    if(respuesta.status === 'ok'){
                        if(estadoActividad === '1'){
                            btnElement.classList.remove('btn-danger');
                            btnElement.classList.add('btn-success');
                            btnElement.innerHTML = '<i class="fas fa-check-circle"></i> Activo';
                            btnElement.setAttribute('estadoActividad', '0');
                        }else{
                            btnElement.classList.remove('btn-success');
                            btnElement.classList.add('btn-danger');
                            btnElement.innerHTML = '<i class="fas fa-times-circle"></i> Inactivo';
                            btnElement.setAttribute('estadoActividad', '1');
                        }
                        Toast.fire({ icon: 'success', title: 'Estado actualizado' });
                    }else{
                        Toast.fire({ icon: 'error', title: respuesta.mensaje || 'Error al actualizar el estado' });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Toast.fire({ icon: 'error', title: 'Error de conexión' });
                });
        });
    });

    /* ✅ COMPARTIR POR WHATSAPP (delegado) */
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.btnWhatsappActividad');
        if(!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const msg = btn.getAttribute('data-msg');
        if(!msg) return;

        try {
            const encoded = encodeURIComponent(msg);
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const url = isMobile
                ? "https://api.whatsapp.com/send?text=" + encoded
                : "https://web.whatsapp.com/send?text=" + encoded;

            window.open(url, "_blank");
        } catch(err) {
            console.error('[WhatsApp Actividad]', err);
            if(typeof Toast !== 'undefined'){
                Toast.fire({ icon: 'error', title: 'No se pudo abrir WhatsApp' });
            }
        }
    });

    /* CARGAR DATOS PARA EDITAR */
    document.addEventListener('click', async function(e){
        const btn = e.target.closest('.btnEditarActividad');
        if(!btn) return;

        e.preventDefault();
        const id = btn.getAttribute('idActividad');
        if(!id) return;

        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const fd = new FormData();
            fd.append('idActividad', id);
            const res  = await fetch('ajax/actividad.ajax.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data && data.id){
                pendingActividadData = data;
            } else {
                Toast.fire({ icon: 'error', title: data.mensaje || 'No se pudo cargar la actividad' });
            }
        } catch(err) {
            console.error(err);
            Toast.fire({ icon: 'error', title: 'Error de conexión' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    });

    /* ELIMINAR */
    document.querySelectorAll('.btnEliminarActividad').forEach(btn => {
        btn.addEventListener('click', function() {
            const idActividad = this.getAttribute('idActividad');
            const nombre = this.getAttribute('nombre');

            Swal.fire({
                title: '¿Eliminar actividad?',
                html: `Estás a punto de eliminar <strong>${nombre}</strong>.<br>
                       Se borrarán también <strong>todas sus fotos</strong>.<br>
                       Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if(result.isConfirmed){
                    window.location = 'index.php?ruta=actividad&idActividad=' + idActividad;
                }
            });
        });
    });

    /* ────── MODAL VER ────── */
    function buildCatalogoMap(lista){
        const map = {};
        if(Array.isArray(lista)){
            lista.forEach(item => { map[String(item.id)] = item; });
        }
        return map;
    }

    function esc(s){
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderVerTags(jsonIds, catalogo, field, icono){
        let ids = [];
        try { ids = JSON.parse(jsonIds || '[]'); } catch(e){ ids = []; }
        if(!Array.isArray(ids) || ids.length === 0) return '<span class="text-muted">—</span>';

        let html = '';
        ids.forEach(id => {
            const it = catalogo[String(id)];
            if(it){
                const color = it.color || '#0d6efd';
                const text  = it.name || it.entidad || it.tag || it.coordinacion || it.host || it.medio || it.prioridad || it.status || 'ID ' + id;
                html += `<span class="badge me-1 mb-1" style="background-color:${esc(color)}; color:#fff; padding:6px 10px;">
                            <i class="fas ${icono} me-1"></i>${esc(text)}
                         </span>`;
            } else {
                html += `<span class="badge bg-secondary me-1 mb-1">ID ${esc(id)}</span>`;
            }
        });
        return html || '<span class="text-muted">—</span>';
    }

    async function abrirModalVer(idActividad){
    const body = document.getElementById('verActividadBody');
    body.innerHTML = `<div class="text-center text-muted py-4">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2 mb-0">Cargando...</p>
                      </div>`;

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVerActividad'));
    modal.show();

    try {
        const fd = new FormData();
        fd.append('idActividad', idActividad);
        const res  = await fetch('ajax/actividad.ajax.php', { method: 'POST', body: fd });
        const a    = await res.json();
        if(!a || !a.id){
            body.innerHTML = '<div class="alert alert-danger mb-0">No se pudo cargar la actividad.</div>';
            return;
        }

        // Catálogos
        const [ents, tags, coords, hosts, users] = await Promise.all([
            fetchEntidades(), fetchTags(), fetchCoordinaciones(), fetchHosts(), fetchUsuarios()
        ]);

        const mapEnts   = buildCatalogoMap(ents);
        const mapTags   = buildCatalogoMap(tags);
        const mapCoords = buildCatalogoMap(coords);
        const mapHosts  = buildCatalogoMap(hosts);
        const mapUsers  = buildCatalogoMap(users);
        const mapUsersAsignados = mapUsers;

        // Fotos
        const fotos = parseFotos(a.foto);
        let galeriaHtml = '';
        if(fotos.length > 0){
            fotos.forEach((r, i) => {
                galeriaHtml += `
                    <div class="foto-edit-item">
                        <img src="${esc(r)}" class="rounded" 
                             style="width: 60px; height: 60px; object-fit: cover; border: 2px solid #ddd; border-radius: 8px; cursor: zoom-in;"
                             data-fotos='${esc(JSON.stringify(fotos))}' data-index="${i}">
                    </div>
                `;
            });
        } else {
            galeriaHtml = '<small class="text-muted align-self-center">Sin fotos</small>';
        }

        const modificacion = a.modificacion
            ? new Date(a.modificacion.replace(' ','T')).toLocaleString('es-VE')
            : '—';

        // Función auxiliar corregida
        function renderReadonlyTags(jsonIds, catalogo, field, icono){
            let ids = [];
            try { ids = JSON.parse(jsonIds || '[]'); } catch(e){ ids = []; }
            if(!Array.isArray(ids) || ids.length === 0) return '<span class="text-muted ms-1 fst-italic" style="font-size:0.85rem;">Ninguno</span>';

            let html = '';
            ids.forEach(id => {
                const it = catalogo[String(id)];
                if(it){
                    const color = it.color || '#0d6efd';
                    // ✅ CORRECCIÓN: Agregado "it.texto" para que muestre el nombre y no el ID
                    const text  = it.texto || it.name || it.entidad || it.tag || it.coordinacion || it.host || it.medio || it.prioridad || it.status || ('ID ' + id);
                    
                    html += `
                        <div class="item" style="background-color:${esc(color)}; color:#fff; padding: 3px 8px; border-radius: 4px; font-size: 0.78rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; margin: 2px;">
                            <i class="fas ${icono}" style="font-size:0.75em;"></i> ${esc(text)}
                        </div>
                    `;
                } else {
                    html += `<div class="item bg-secondary" style="color:#fff; padding: 3px 8px; border-radius: 4px; font-size: 0.78rem; margin: 2px;">ID ${esc(id)}</div>`;
                }
            });
            return html;
        }

        // Estilo para simular select
        const readOnlySelectStyle = "min-height: 32px; display: flex; flex-wrap: wrap; gap: 2px; align-items: center; background-color: var(--bs-secondary-bg); padding: 2px 6px; border-radius: 6px; border: 1px solid var(--bs-border-color);";

        // HTML Compacto a 2 columnas
        body.innerHTML = `
            <div class="row g-2">
                <div class="col-md-8">
                    <label class="form-label mb-1" style="font-size:0.85rem;">Nombre de la Actividad</label>
                    <input type="text" class="form-control form-control-sm" value="${esc(a.actividad)}" readonly style="background-color: var(--bs-secondary-bg);">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1" style="font-size:0.85rem;">Cantidad</label>
                    <input type="number" class="form-control form-control-sm" value="${esc(a.cantidad)}" readonly style="background-color: var(--bs-secondary-bg);">
                </div>

                <div class="col-md-6">
                    <label class="form-label mb-1" style="font-size:0.85rem;"><i class="fas fa-city me-1 text-muted"></i> Entidad(es)</label>
                    <div style="${readOnlySelectStyle}">
                        ${renderReadonlyTags(a.id_entidad, mapEnts, 'entidad', 'fa-city')}
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label mb-1" style="font-size:0.85rem;"><i class="fas fa-tags me-1 text-muted"></i> Tag(s)</label>
                    <div style="${readOnlySelectStyle}">
                        ${renderReadonlyTags(a.id_tag, mapTags, 'tag', 'fa-tag')}
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label mb-1" style="font-size:0.85rem;"><i class="fas fa-sitemap me-1 text-muted"></i> Coordinación(es)</label>
                    <div style="${readOnlySelectStyle}">
                        ${renderReadonlyTags(a.id_coordinacion, mapCoords, 'coordinacion', 'fa-sitemap')}
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label mb-1" style="font-size:0.85rem;"><i class="fas fa-network-wired me-1 text-muted"></i> Host(s)</label>
                    <div style="${readOnlySelectStyle}">
                        ${renderReadonlyTags(a.id_host, mapHosts, 'host', 'fa-network-wired')}
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label mb-1" style="font-size:0.85rem;"><i class="fas fa-users me-1 text-muted"></i> Usuario(s)</label>
                    <div style="${readOnlySelectStyle}">
                        ${renderReadonlyTags(a.id_user, mapUsersAsignados, 'user', 'fa-user')}
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label mb-1 d-flex align-items-center gap-2" style="font-size:0.85rem;">
                        <i class="fas fa-image me-1 text-muted"></i> Fotos
                        <span class="badge text-bg-secondary" style="font-size:0.6rem;">${fotos.length} / 4</span>
                    </label>
                    <div class="d-flex flex-wrap gap-2 p-1 border rounded bg-body-tertiary" style="min-height: 42px; align-items: center;">
                        ${galeriaHtml}
                    </div>
                </div>

                <div class="col-md-12 mt-2">
                    <label class="form-label mb-1" style="font-size:0.85rem;">Descripción</label>
                    <div class="border rounded p-2 bg-body-tertiary" style="min-height: 100px; max-height: 200px; overflow-y: auto; font-size: 0.88rem;">
                        ${a.descripcion || '<span class="text-muted fst-italic">Sin descripción</span>'}
                    </div>
                </div>
                
                <div class="col-md-12 mt-1">
                    <div class="small text-muted text-end" style="font-size:0.75rem;">
                        <i class="fas fa-clock me-1"></i> Última modificación: <strong>${esc(modificacion)}</strong>
                    </div>
                </div>
            </div>
        `;
    } catch(err) {
        console.error(err);
        body.innerHTML = '<div class="alert alert-danger mb-0">Error de conexión con el servidor.</div>';
    }
}

    document.addEventListener('click', function(e){
        const btn = e.target.closest('.btnVerActividad');
        if(!btn) return;
        e.preventDefault();
        const id = btn.getAttribute('idActividad');
        if(!id) return;
        abrirModalVer(id);
    });

    /* ────── LIGHTBOX ────── */
    let lightboxFotos = [];
    let lightboxIndex = 0;

    const lightboxEl    = document.getElementById('modalLightboxFoto');
    const lightboxImgEl = document.getElementById('lightboxImg');
    const lightboxCount = document.getElementById('lightboxCounter');

    function lightboxUpdate(){
        if(lightboxFotos.length === 0){
            lightboxImgEl.src = '';
            lightboxCount.textContent = '0 / 0';
            return;
        }
        if(lightboxIndex < 0) lightboxIndex = lightboxFotos.length - 1;
        if(lightboxIndex >= lightboxFotos.length) lightboxIndex = 0;

        lightboxImgEl.src = lightboxFotos[lightboxIndex];
        lightboxCount.textContent = (lightboxIndex + 1) + ' / ' + lightboxFotos.length;
    }

    function lightboxOpen(fotos, index){
        lightboxFotos = fotos;
        lightboxIndex = index || 0;
        lightboxUpdate();
        bootstrap.Modal.getOrCreateInstance(lightboxEl).show();
    }

    const lbPrev = document.getElementById('lightboxPrev');
    const lbNext = document.getElementById('lightboxNext');
    if(lbPrev) lbPrev.addEventListener('click', () => { lightboxIndex--; lightboxUpdate(); });
    if(lbNext) lbNext.addEventListener('click', () => { lightboxIndex++; lightboxUpdate(); });

    document.addEventListener('click', function(e){
        const img = e.target.closest('.foto-thumb, #fotoActualPreview img, #verActividadBody img[data-fotos]');
        if(!img) return;

        let fotos = null;
        let idx   = 0;

        if(img.dataset && img.dataset.fotos){
            try { fotos = JSON.parse(img.dataset.fotos); } catch(e) { fotos = null; }
            idx = parseInt(img.dataset.index || 0) || 0;
        }

        if(!fotos){
            const parent = img.closest('.foto-gallery');
            if(parent && parent.dataset.fotos){
                try { fotos = JSON.parse(parent.dataset.fotos); } catch(e) { fotos = null; }
            }
        }

        if(!fotos || fotos.length === 0) return;

        e.preventDefault();
        lightboxOpen(fotos, idx);
    });

    document.addEventListener('keydown', function(e){
        if(!lightboxEl || !lightboxEl.classList.contains('show')) return;
        if(e.key === 'ArrowLeft'){ lightboxIndex--; lightboxUpdate(); }
        if(e.key === 'ArrowRight'){ lightboxIndex++; lightboxUpdate(); }
    });

});
</script>