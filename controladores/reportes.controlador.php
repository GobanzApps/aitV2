<?php

class ControladorReportes
{
    /*=============================================
    PERMISOS PARA REPORTES
    Administrador | Coordinador | Archivo |
    Centro de Atención | Usuario especial
    =============================================*/
    static public function puedeVerReportes($auth = null)
    {
        $auth = $auth ?: ControladorTicket::authTicket();

        if (!$auth) {
            return false;
        }

        $usuario = strtolower(trim(
            $auth["userData"]["user"] ?? ""
        ));

        $coordinaciones = array_map(
            "intval",
            $auth["coordsIds"] ?? []
        );

        return
            ControladorTicket::esAdminTicket($auth) ||
            ControladorTicket::esCoordinadorTicket($auth) ||
            in_array(11, $coordinaciones, true) ||
            in_array(9, $coordinaciones, true) ||
            $usuario === "agamardo";
    }
}
