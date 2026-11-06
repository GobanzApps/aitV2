<?php
/*=============================================
Archivo: vistas/modulos/ticket/tdoc.php
Muestra los documentos adjuntos vinculados al ticket actual.

Reglas de acción:
- PDF   → se abre en nueva pestaña (target="_blank")
- Word  → se descarga (download="Nombre.docx")
- Otros → se descargan con su nombre original
=============================================*/

if(!isset($idTicket) || $idTicket <= 0){
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No se pudo identificar el ticket.</div>';
    return;
}

/*=============================================
1) Obtener todos los documentos y filtrar
   solo los que incluyan el ticket actual
   (id_ticket guarda un JSON: "[1,2]")
=============================================*/
$todosDocs = ControladorDoc::ctrMostrarDoc(null, null);
$docsDelTicket = [];

if($todosDocs && is_array($todosDocs)){
    foreach($todosDocs as $doc){

        // Solo documentos activos
        if((int)($doc["estado"] ?? 0) !== 1) continue;

        $idsTickets = json_decode($doc["id_ticket"] ?? '[]', true);
        if(!is_array($idsTickets)) $idsTickets = [];
        $idsTickets = array_map('intval', $idsTickets);

        if(in_array((int)$idTicket, $idsTickets, true)){
            $docsDelTicket[] = $doc;
        }
    }
}

/*=============================================
2) Mapa de tipos de documento (para badges)
=============================================*/
$tiposDoc = ControladorDocTipo::ctrMostrarDocTipo(null, null);
$mapaTipos = [];
if($tiposDoc && is_array($tiposDoc)){
    foreach($tiposDoc as $t){ $mapaTipos[(int)$t["id"]] = $t; }
}

/*=============================================
3) Helpers: ícono / color según extensión
=============================================*/
if(!function_exists('tdocIconoPorExt')){
    function tdocIconoPorExt($ext){
        switch(strtolower($ext)){
            case 'pdf':              return 'fa-file-pdf';
            case 'doc':
            case 'docx':             return 'fa-file-word';
            case 'xls':
            case 'xlsx':             return 'fa-file-excel';
            case 'ppt':
            case 'pptx':             return 'fa-file-powerpoint';
            case 'txt':              return 'fa-file-lines';
            case 'csv':              return 'fa-file-csv';
            case 'rtf':              return 'fa-file-text';
            default:                 return 'fa-file';
        }
    }
}

if(!function_exists('tdocColorPorExt')){
    function tdocColorPorExt($ext){
        switch(strtolower($ext)){
            case 'pdf':              return 'text-danger';
            case 'doc':
            case 'docx':             return 'text-primary';
            case 'xls':
            case 'xlsx':             return 'text-success';
            case 'ppt':
            case 'pptx':             return 'text-warning';
            case 'txt':
            case 'csv':
            case 'rtf':              return 'text-info';
            default:                 return 'text-secondary';
        }
    }
}

if(!function_exists('tdocDecodificarIds')){
    function tdocDecodificarIds($json){
        $ids = json_decode($json ?? '[]', true);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }
}
?>

<style>
    /* Hover sutil sobre cada documento */
    .tdoc-item {
        transition: box-shadow .2s ease, transform .15s ease, border-color .15s ease;
    }
    .tdoc-item:hover {
        box-shadow: 0 4px 14px rgba(13,110,253,0.12);
        border-color: #0d6efd !important;
        transform: translateY(-1px);
    }
    /* Truncado del nombre largo */
    .tdoc-item__body {
        min-width: 0; /* imprescindible para elipsis en flex */
    }
    .tdoc-item__title {
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
    }
</style>

<!-- ═══════════════ CABECERA DE LA SECCIÓN ═══════════════ -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0 text-muted" style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px;">
        <i class="fas fa-paperclip me-1"></i>
        Documentos adjuntos
        <span class="badge text-bg-primary ms-1"><?php echo count($docsDelTicket); ?></span>
    </h6>
</div>

<?php if(empty($docsDelTicket)): ?>

    <div class="alert alert-info d-flex align-items-center mb-0">
        <i class="fas fa-info-circle me-2"></i>
        <div>Este ticket no tiene documentos adjuntos vinculados.</div>
    </div>

<?php else: ?>

    <div class="d-flex flex-column gap-2">
        <?php foreach($docsDelTicket as $d):

            $adjunto        = $d["adjunto"] ?? '';
            $ext            = $adjunto ? strtolower(pathinfo($adjunto, PATHINFO_EXTENSION)) : '';
            $nombreDescarga = $d["doc"] . ($ext ? '.' . $ext : '');
            $icono          = tdocIconoPorExt($ext);
            $colorIcono     = tdocColorPorExt($ext);

            $emisionFmt = !empty($d["emision"])
                ? date("d/m/Y", strtotime($d["emision"]))
                : "—";

            $idsTipos = tdocDecodificarIds($d["id_doc_tipo"] ?? '');
        ?>
        <div class="tdoc-item d-flex gap-3 p-3 border rounded bg-body-tertiary">

            <!-- Icono según extensión -->
            <div class="flex-shrink-0 d-flex align-items-center justify-content-center"
                 style="width:48px; height:48px;">
                <i class="fas <?php echo $icono; ?> fa-2x <?php echo $colorIcono; ?>"
                   title="<?php echo strtoupper($ext ?: 'DOC'); ?>"></i>
            </div>

            <!-- Cuerpo -->
            <div class="tdoc-item__body flex-grow-1">

                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">

                    <div class="tdoc-item__body">
                        <div class="tdoc-item__title" title="<?php echo htmlspecialchars($d["doc"]); ?>">
                            <i class="fas fa-file-contract me-1 text-muted"></i>
                            <?php echo htmlspecialchars($d["doc"]); ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <i class="far fa-calendar me-1"></i><?php echo $emisionFmt; ?>
                            <?php if(!empty($d["asunto"])): ?>
                                <span class="mx-1">·</span>
                                <i class="fas fa-align-left me-1"></i>
                                <?php echo htmlspecialchars(mb_strimwidth($d["asunto"], 0, 60, '…')); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Acción según extensión -->
                    <div class="flex-shrink-0">
                        <?php if(!empty($adjunto)):

                            if($ext === 'pdf'): ?>
                                <!-- PDF → abrir en nueva pestaña -->
                                <a href="<?php echo htmlspecialchars($adjunto); ?>"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="btn btn-sm btn-danger"
                                   title="Abrir PDF en nueva pestaña">
                                    <i class="fas fa-external-link-alt me-1"></i> Abrir PDF
                                </a>
                            <?php else: ?>
                                <!-- Word, Excel, PPT, etc. → descargar -->
                                <a href="<?php echo htmlspecialchars($adjunto); ?>"
                                   download="<?php echo htmlspecialchars($nombreDescarga); ?>"
                                   class="btn btn-sm btn-primary"
                                   title="Descargar <?php echo htmlspecialchars($nombreDescarga); ?>">
                                    <i class="fas fa-download me-1"></i> Descargar
                                </a>
                            <?php endif;

                        else: ?>
                            <span class="badge bg-secondary">Sin adjunto</span>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Badges de tipos de documento -->
                <?php if(!empty($idsTipos)): ?>
                <div class="mt-2">
                    <?php foreach($idsTipos as $idT):
                        if(isset($mapaTipos[$idT])):
                            $tp        = $mapaTipos[$idT];
                            $colorTipo = $tp["color"] ?: "#0d6efd";
                    ?>
                        <span class="badge me-1 mb-1"
                              style="background-color:<?php echo htmlspecialchars($colorTipo); ?>; color:#fff; padding:4px 9px; font-size:0.72rem;">
                            <i class="fas fa-file-alt me-1"></i><?php echo htmlspecialchars($tp["doc_tipo"]); ?>
                        </span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>