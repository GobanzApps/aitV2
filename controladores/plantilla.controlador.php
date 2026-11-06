<?php

class ControladorPlantilla{

    static public function ctrPlantilla(){

        // ✅ NUEVO: RUTA ESPECIAL para "salir" (bypass del layout AdminLTE)
        // Esto se ejecuta ANTES de cargar vistas/plantilla.php y evita el error de cabeceras
        if(isset($_GET["ruta"]) && $_GET["ruta"] === "salir"){
            include "vistas/modulos/salir.php";
            return;
        }

        // Si NO hay sesión iniciada, mostrar el login
        if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
            include "vistas/modulos/login.php";
            return;
        }

        // ✅ RUTA ESPECIAL: PDF (bypass del layout AdminLTE)
        if(isset($_GET["ruta"]) && $_GET["ruta"] === "ticket-pdf"){
            include "vistas/modulos/ticket-pdf.php";
            return;
        }

        // Si hay sesión, mostrar la plantilla completa de AdminLTE
        include "vistas/plantilla.php";
    }
}
?>