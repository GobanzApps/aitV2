<?php
/* ACTIVIDAD DETALLE — OPCIÓN B: SOCIAL FEED */
/* [BLOQUE PHP COMÚN — igual que opción A, hasta $estado] */
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){ echo '<script>window.location = "/";</script>'; exit(); }
$idActividad = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
if($idActividad <= 0){ echo '<div class="alert alert-danger">ID no válido.</div>'; return; }
$actividad = ControladorActividad::ctrMostrarActividad("id", $idActividad);
if(!$actividad){ echo '<div class="alert alert-danger">Actividad no encontrada.</div>'; return; }
$fotos = ControladorActividad::decodeFotos($actividad["foto"] ?? '');
$ents  = ControladorEntidad::ctrMostrarEntidad(null, null) ?: [];
$tags  = ControladorTag::ctrMostrarTag(null, null) ?: [];
$hosts = ControladorHost::ctrMostrarHost(null, null) ?: [];
$mapaEnts=[]; foreach($ents as $e) $mapaEnts[(int)$e["id"]]=$e;
$mapaTags=[]; foreach($tags as $t) $mapaTags[(int)$t["id"]]=$t;
$mapaHosts=[]; foreach($hosts as $h) $mapaHosts[(int)$h["id"]]=$h;
$todosRoles=ControladorRol::ctrMostrarRol(null,null)?:[]; $mapaRoles=[]; foreach($todosRoles as $r) $mapaRoles[(int)$r["id"]]=$r;
if(!function_exists('actColorUsuario')){ function actColorUsuario($u,$m){ if(!$u) return "#6c757d"; foreach((json_decode($u["id_rol"]??'[]',true)?:[]) as $i){ if(isset($m[(int)$i]) && (int)$m[(int)$i]["estado"]===1) return $m[(int)$i]["color"]?:"#0d6efd"; } return "#6c757d"; } }
if(!function_exists('actIniciales')){ function actIniciales($n){ $n=trim((string)$n); if($n==='') return '?'; $i=''; foreach(array_slice(preg_split('/\s+/',$n),0,2) as $p) $i.=mb_strtoupper(mb_substr($p,0,1)); return $i?:'?'; } }
$idCreador=(int)($actividad["id_useradd"]??0); $creador=ControladorUser::ctrMostrarUser("id",$idCreador);
$nombreCreador=$creador?($creador["name"]?:$creador["user"]):"Desconocido";
$colorCreador=actColorUsuario($creador,$mapaRoles); $iniCreador=actIniciales($nombreCreador);
$fechaCreacion=date("d/m/Y H:i",strtotime($actividad["fecha"])); $estado=(int)$actividad["estado"];
?>

<style>
    .feed-wrap { max-width: 820px; margin: 0 auto; }
    .feed-post { background:#fff; border-radius:14px; padding:20px 22px; border:1px solid #e9ecef; }
    .feed-post__avatar {
        width: 52px; height: 52px; border-radius: 50%;
        display:flex; align-items:center; justify-content:center;
        font-weight:700; color:#fff; flex-shrink:0;
    }
    .feed-card {
        background:#fff; border-radius:14px; border:1px solid #e9ecef;
        margin-top:16px; overflow:hidden;
    }
    .feed-card__header {
        padding: 12px 18px; border-bottom: 1px solid #eef1f4;
        font-weight:600; font-size:0.9rem; color:#495057;
        display:flex; align-items:center; gap:8px;
    }
    .feed-card__body { padding: 16px 18px; }
    .feed-desc {
        background:#f8f9fa; border-radius:10px; padding:14px 16px;
        font-size:0.95rem; line-height:1.6;
    }
    .feed-gallery { display:grid; grid-template-columns: repeat(auto-fill, minmax(140px,1fr)); gap:8px; }
    .feed-gallery a { display:block; border-radius:8px; overflow:hidden; }
    .feed-gallery img { width:100%; aspect-ratio:1/1; object-fit:cover; transition: transform .2s; }
    .feed-gallery a:hover img { transform: scale(1.05); }
</style>

<div class="feed-wrap">

    <!-- POST HEADER -->
    <div class="feed-post">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <a href="index.php?ruta=actividad" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i>
            </a>
            <span class="badge bg-<?php echo $estado===1?'success':'danger'; ?>">
                <i class="fas <?php echo $estado===1?'fa-check-circle':'fa-times-circle'; ?> me-1"></i>
                <?php echo $estado===1?'Activo':'Inactivo'; ?>
            </span>
        </div>

        <div class="d-flex gap-3 align-items-center">
            <div class="feed-post__avatar" style="background-color: <?php echo htmlspecialchars($colorCreador); ?>;">
                <?php echo htmlspecialchars($iniCreador); ?>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold"><?php echo htmlspecialchars($nombreCreador); ?></div>
                <small class="text-muted">
                    <i class="far fa-clock me-1"></i><?php echo $fechaCreacion; ?>
                    <span class="mx-2">·</span>
                    <i class="fas fa-clipboard-list me-1"></i>Actividad #<?php echo (int)$actividad["id"]; ?>
                </small>
            </div>
        </div>

        <h2 class="h4 mt-3 mb-2 fw-bold"><?php echo htmlspecialchars($actividad["actividad"]); ?></h2>
        <div class="d-flex gap-3 small text-muted">
            <span><i class="fas fa-sort-numeric-up me-1"></i>Cant: <strong><?php echo (int)$actividad["cantidad"]; ?></strong></span>
            <span><i class="fas fa-images me-1"></i>Fotos: <strong><?php echo count($fotos); ?></strong></span>
        </div>
    </div>

    <!-- DESCRIPCIÓN -->
    <div class="feed-card">
        <div class="feed-card__header"><i class="fas fa-align-left text-primary"></i> Descripción</div>
        <div class="feed-card__body">
            <?php if(trim(strip_tags($actividad["descripcion"] ?? ''))===''): ?>
                <p class="text-muted mb-0 small">Sin descripción.</p>
            <?php else: ?>
                <div class="feed-desc"><?php echo $actividad["descripcion"]; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- GALERÍA -->
    <div class="feed-card">
        <div class="feed-card__header"><i class="fas fa-images text-info"></i> Galería (<?php echo count($fotos); ?>)</div>
        <div class="feed-card__body">
            <?php if(empty($fotos)): ?>
                <p class="text-muted mb-0 small">Sin fotos.</p>
            <?php else: ?>
                <div class="feed-gallery">
                    <?php foreach($fotos as $f): ?>
                        <a href="<?php echo htmlspecialchars($f); ?>" target="_blank" rel="noopener">
                            <img src="<?php echo htmlspecialchars($f); ?>"
                                 onerror="this.src='https://ui-avatars.com/api/?name=IMG&background=6c757d&color=fff&size=200'">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RELACIONES (misma estructura que opción A) -->
    <?php
    $secciones = [
        ['t'=>'Usuarios Relacionados','i'=>'fa-users','c'=>'primary','ids'=>json_decode($actividad["id_user"]??'[]',true)?:[],'mapa'=>$mapaEnts,'campo'=>null],
        ['t'=>'Entidad(es)','i'=>'fa-city','c'=>'success','ids'=>json_decode($actividad["id_entidad"]??'[]',true)?:[],'mapa'=>$mapaEnts,'campo'=>'entidad'],
        ['t'=>'Tag(s)','i'=>'fa-tags','c'=>'warning','ids'=>json_decode($actividad["id_tag"]??'[]',true)?:[],'mapa'=>$mapaTags,'campo'=>'tag'],
        ['t'=>'Host(s)','i'=>'fa-network-wired','c'=>'danger','ids'=>json_decode($actividad["id_host"]??'[]',true)?:[],'mapa'=>$mapaHosts,'campo'=>'host'],
    ];
    foreach($secciones as $sec):
    ?>
    <div class="feed-card">
        <div class="feed-card__header"><i class="fas <?php echo $sec['i']; ?> text-<?php echo $sec['c']; ?>"></i> <?php echo $sec['t']; ?></div>
        <div class="feed-card__body">
            <?php if(empty($sec['ids'])): ?>
                <small class="text-muted">— Sin registros —</small>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-1">
                <?php foreach($sec['ids'] as $idI):
                    $idI=(int)$idI;
                    if($sec['campo']===null){
                        $u=ControladorUser::ctrMostrarUser("id",$idI);
                        if($u){ $n=$u["name"]?:$u["user"]; $c=actColorUsuario($u,$mapaRoles);
                            echo '<span class="badge" style="background-color:'.htmlspecialchars($c).'; color:#fff; padding:5px 10px;">'.htmlspecialchars($n).'</span>'; }
                    } elseif(isset($sec['mapa'][$idI])){
                        $it=$sec['mapa'][$idI]; $c=$it["color"]?:"#0d6efd";
                        echo '<span class="badge" style="background-color:'.htmlspecialchars($c).'; color:#fff; padding:5px 10px;">'.htmlspecialchars($it[$sec['campo']]).'</span>';
                    }
                endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>