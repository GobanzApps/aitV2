<?php
/*=============================================
ajax/allticket.ajax.php
Devuelve SOLO el HTML de las filas <tr> del tbody
de la tabla "allticket" (sin <table>, sin <thead>).
=============================================*/

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING & ~E_STRICT);
ini_set('display_errors', 0);
ob_start();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok") {
    if (ob_get_length()) ob_clean();
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

/* ── Bootstrap de controladores / modelos ── */
require_once __DIR__ . "/../controladores/user.controlador.php";
require_once __DIR__ . "/../modelos/user.modelo.php";

require_once __DIR__ . "/../controladores/coordinacion.controlador.php";
require_once __DIR__ . "/../modelos/coordinacion.modelo.php";

require_once __DIR__ . "/../controladores/rol.controlador.php";
require_once __DIR__ . "/../modelos/rol.modelo.php";

require_once __DIR__ . "/../controladores/entidad.controlador.php";
require_once __DIR__ . "/../modelos/entidad.modelo.php";

require_once __DIR__ . "/../controladores/prioridad.controlador.php";
require_once __DIR__ . "/../modelos/prioridad.modelo.php";

require_once __DIR__ . "/../controladores/status.controlador.php";
require_once __DIR__ . "/../modelos/status.modelo.php";

require_once __DIR__ . "/../controladores/ticket.controlador.php";
require_once __DIR__ . "/../modelos/ticket.modelo.php";

require_once __DIR__ . "/../controladores/tcoordinacion.controlador.php";
require_once __DIR__ . "/../modelos/tcoordinacion.modelo.php";

require_once __DIR__ . "/../controladores/tuser.controlador.php";
require_once __DIR__ . "/../modelos/tuser.modelo.php";

require_once __DIR__ . "/../controladores/tstatus.controlador.php";
require_once __DIR__ . "/../modelos/tstatus.modelo.php";

/* ── Lógica compartida ── */
require_once __DIR__ . "/../vistas/modulos/inicio/allticket_core.php";

/* ── Calcular + renderizar ── */
$resultado = allticketCompute();
$html      = allticketRenderTbody($resultado);

if (ob_get_length()) ob_clean();
header('Content-Type: text/html; charset=utf-8');
echo $html;