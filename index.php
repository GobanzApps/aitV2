<?php

date_default_timezone_set('America/Caracas');

session_start();

require_once "controladores/plantilla.controlador.php";
require_once "controladores/user.controlador.php";
require_once "controladores/coordinacion.controlador.php";
require_once "controladores/rol.controlador.php";
require_once "controladores/tag.controlador.php";
require_once "controladores/tipo_entidad.controlador.php";
require_once "controladores/host_tipo.controlador.php";
require_once "controladores/doc_tipo.controlador.php";        // ← NUEVO
require_once "controladores/entidad.controlador.php";
require_once "controladores/medio.controlador.php";
require_once "controladores/prioridad.controlador.php";
require_once "modelos/user.modelo.php";
require_once "modelos/coordinacion.modelo.php";
require_once "modelos/rol.modelo.php";
require_once "modelos/tag.modelo.php";
require_once "modelos/tipo_entidad.modelo.php";
require_once "modelos/host_tipo.modelo.php";
require_once "modelos/doc_tipo.modelo.php";                  
require_once "modelos/entidad.modelo.php";
require_once "modelos/medio.modelo.php";
require_once "modelos/prioridad.modelo.php";

require_once "controladores/ticket.controlador.php";
require_once "modelos/ticket.modelo.php";

require_once "controladores/tuser.controlador.php";
require_once "modelos/tuser.modelo.php";

require_once "controladores/tcoordinacion.controlador.php";
require_once "modelos/tcoordinacion.modelo.php";

require_once "controladores/status.controlador.php";
require_once "modelos/status.modelo.php";

require_once "controladores/tstatus.controlador.php";
require_once "modelos/tstatus.modelo.php";

require_once "controladores/host.controlador.php";    
require_once "modelos/host.modelo.php";    

require_once "controladores/tservicio.controlador.php";
require_once "modelos/tservicio.modelo.php";

require_once "controladores/tevidencia.controlador.php";
require_once "modelos/tevidencia.modelo.php";

require_once "controladores/doc.controlador.php";  
require_once "modelos/doc.modelo.php";              

require_once "controladores/actividad.controlador.php";
require_once "modelos/actividad.modelo.php";

require_once "controladores/tag_tipo.controlador.php";   
require_once "modelos/tag_tipo.modelo.php";              


$plantilla = new ControladorPlantilla();
$plantilla->ctrPlantilla();
?>