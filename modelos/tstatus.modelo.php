<?php

require_once "conexion.php";

class ModeloTstatus{

    /*=============================================
    MOSTRAR (todos o filtrados)
    =============================================*/
    static public function mdlMostrarTstatus($tabla, $item, $valor){
        if($item != null){
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item ORDER BY id DESC");
            $stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);
            $stmt -> execute();
            return $stmt -> fetchAll();
        }else{
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY id DESC");
            $stmt -> execute();
            return $stmt -> fetchAll();
        }
    }

    /*=============================================
    MOSTRAR UNO (por id)
    =============================================*/
    static public function mdlMostrarTstatusUno($tabla, $item, $valor){
        $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item LIMIT 1");
        $stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);
        $stmt -> execute();
        return $stmt -> fetch();
    }

    /*=============================================
    INGRESAR (con id_user, check, estado, fecha, modificacion)
    → fecha = creación (queda fija para siempre)
    → modificacion = misma fecha al crear (luego puede cambiar al editar el comentario)
    =============================================*/
    static public function mdlIngresarTstatus($tabla, $datos){
        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(tstatus, id_ticket, id_status, comentario, id_user, `check`, estado, fecha, modificacion)
             VALUES (:tstatus, :id_ticket, :id_status, :comentario, :id_user, :check, :estado, :fecha, :modificacion)"
        );

        $stmt->bindParam(":tstatus",      $datos["tstatus"],      PDO::PARAM_STR);
        $stmt->bindParam(":id_ticket",    $datos["id_ticket"],    PDO::PARAM_STR);
        $stmt->bindParam(":id_status",    $datos["id_status"],    PDO::PARAM_INT);
        $stmt->bindParam(":comentario",   $datos["comentario"],   PDO::PARAM_STR);
        $stmt->bindParam(":id_user",      $datos["id_user"],      PDO::PARAM_INT);
        $stmt->bindParam(":check",        $datos["check"],        PDO::PARAM_INT);
        $stmt->bindParam(":estado",       $datos["estado"],       PDO::PARAM_INT);
        $stmt->bindParam(":fecha",        $datos["fecha"],        PDO::PARAM_STR);
        $stmt->bindParam(":modificacion", $datos["modificacion"], PDO::PARAM_STR);

        if($stmt->execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    EDITAR (solo id_status, comentario y modificacion)
    → fecha NO se toca (queda fija con el valor de creación)
    =============================================*/
    static public function mdlEditarTstatus($tabla, $datos){
        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET id_status    = :id_status,
                 comentario   = :comentario,
                 modificacion = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":id_status",    $datos["id_status"],    PDO::PARAM_INT);
        $stmt -> bindParam(":comentario",   $datos["comentario"],   PDO::PARAM_STR);
        $stmt -> bindParam(":modificacion", $datos["modificacion"], PDO::PARAM_STR);
        $stmt -> bindParam(":id",           $datos["id"],           PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    BORRAR
    =============================================*/
    static public function mdlBorrarTstatus($tabla, $datos){
        $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE id = :id");
        $stmt -> bindParam(":id", $datos, PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    LISTAR NOTIFICACIONES PENDIENTES
    → Se ordena por fecha (creación), no por modificación
    =============================================*/
    static public function mdlListarNotificacionesPendientes($tabla, $idUser){
        $stmt = Conexion::conectar()->prepare(
            "SELECT t.*, s.status AS status_nombre, s.color AS status_color
             FROM $tabla t
             INNER JOIN status s ON s.id = t.id_status
             WHERE t.id_user = :id_user
               AND t.`check` = 0
               AND t.estado = 0
               AND s.status IN ('Nuevo', 'Reemplazo')
             ORDER BY t.fecha DESC"
        );
        $stmt->bindParam(":id_user", $idUser, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /*=============================================
    MARCAR NOTIFICACIÓN COMO LEÍDA
    → además guarda la fecha exacta de lectura
    =============================================*/
    static public function mdlMarcarNotificacionLeida($tabla, $id){

        date_default_timezone_set('America/Caracas');
        $fechaActual = date('Y-m-d H:i:s');

        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET `check` = 1,
                 `fecha_check` = :fecha_check
             WHERE id = :id"
        );
        $stmt->bindParam(":fecha_check", $fechaActual, PDO::PARAM_STR);
        $stmt->bindParam(":id",          $id,          PDO::PARAM_INT);

        if($stmt->execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    ACTUALIZAR ESTADO (activar / desactivar)
    =============================================*/
    static public function mdlActualizarEstadoTstatus($tabla, $id, $estado){
        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla SET estado = :estado WHERE id = :id"
        );
        $stmt->bindParam(":estado", $estado, PDO::PARAM_INT);
        $stmt->bindParam(":id",     $id,     PDO::PARAM_INT);

        if($stmt->execute()){
            return "ok";
        }
        return "error";
    }
}
?>