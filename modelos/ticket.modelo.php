<?php

require_once "conexion.php";

class ModeloTicket{

    /*=============================================
    MOSTRAR TICKETS
    =============================================*/
    static public function mdlMostrarTicket($tabla, $item, $valor){

        if($item != null){
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");
            $stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);
            $stmt -> execute();
            return $stmt -> fetch();
        }else{
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY id DESC");
            $stmt -> execute();
            return $stmt -> fetchAll();
        }

        $stmt -> close();
        $stmt = null;
    }

    /*=============================================
    REGISTRO DE TICKET
    → Devuelve el ID insertado en éxito, false si falla.
    (Esto permite al controlador responder con idTicket y
     redirigir al detalle justo después de crear.)
    =============================================*/
    static public function mdlIngresarTicket($tabla, $datos){

        $conexion = Conexion::conectar();
        $stmt = $conexion->prepare(
            "INSERT INTO $tabla(id_entidad, solicitante, id_medio, id_prioridad, descripcion, id_user, estado, modificacion)
             VALUES (:id_entidad, :solicitante, :id_medio, :id_prioridad, :descripcion, :id_user, :estado, :modificacion)"
        );

        $stmt->bindParam(":id_entidad",   $datos["id_entidad"],   PDO::PARAM_STR);
        $stmt->bindParam(":solicitante",  $datos["solicitante"],  PDO::PARAM_STR);
        $stmt->bindParam(":id_medio",     $datos["id_medio"],     PDO::PARAM_STR);
        $stmt->bindParam(":id_prioridad", $datos["id_prioridad"], PDO::PARAM_STR);
        $stmt->bindParam(":descripcion",  $datos["descripcion"],  PDO::PARAM_STR);
        $stmt->bindParam(":id_user",      $datos["id_user"],      PDO::PARAM_STR);
        $stmt->bindParam(":estado",       $datos["estado"],       PDO::PARAM_INT);
        $stmt->bindParam(":modificacion", $datos["modificacion"], PDO::PARAM_STR);

        if($stmt->execute()){
            return $conexion->lastInsertId();   // string con el ID
        }else{
            return false;
        }

        $stmt->close();
        $stmt = null;
    }

    /*=============================================
    EDITAR TICKET (listado tickets.php)
    =============================================*/
    static public function mdlEditarTicket($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET id_entidad   = :id_entidad,
                 solicitante  = :solicitante,
                 id_medio     = :id_medio,
                 id_prioridad = :id_prioridad,
                 descripcion  = :descripcion,
                 id_user      = :id_user,
                 modificacion = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":id_entidad",   $datos["id_entidad"],   PDO::PARAM_STR);
        $stmt -> bindParam(":solicitante",  $datos["solicitante"],  PDO::PARAM_STR);
        $stmt -> bindParam(":id_medio",     $datos["id_medio"],     PDO::PARAM_STR);
        $stmt -> bindParam(":id_prioridad", $datos["id_prioridad"], PDO::PARAM_STR);
        $stmt -> bindParam(":descripcion",  $datos["descripcion"],  PDO::PARAM_STR);
        $stmt -> bindParam(":id_user",      $datos["id_user"],      PDO::PARAM_STR);
        $stmt -> bindParam(":modificacion", $datos["modificacion"], PDO::PARAM_STR);
        $stmt -> bindParam(":id",           $datos["id"],           PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt -> close();
        $stmt = null;
    }

    /*=============================================
    EDITAR TICKET desde el DETALLE
    =============================================*/
    static public function mdlEditarTicketDetalle($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET id_entidad   = :id_entidad,
                 solicitante  = :solicitante,
                 id_medio     = :id_medio,
                 id_prioridad = :id_prioridad,
                 descripcion  = :descripcion,
                 estado       = :estado,
                 id_user      = :id_user,
                 modificacion = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":id_entidad",   $datos["id_entidad"],   PDO::PARAM_STR);
        $stmt -> bindParam(":solicitante",  $datos["solicitante"],  PDO::PARAM_STR);
        $stmt -> bindParam(":id_medio",     $datos["id_medio"],     PDO::PARAM_STR);
        $stmt -> bindParam(":id_prioridad", $datos["id_prioridad"], PDO::PARAM_STR);
        $stmt -> bindParam(":descripcion",  $datos["descripcion"],  PDO::PARAM_STR);
        $stmt -> bindParam(":estado",       $datos["estado"],       PDO::PARAM_INT);
        $stmt -> bindParam(":id_user",      $datos["id_user"],      PDO::PARAM_STR);
        $stmt -> bindParam(":modificacion", $datos["modificacion"], PDO::PARAM_STR);
        $stmt -> bindParam(":id",           $datos["id"],           PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    ACTUALIZAR TICKET (estado, check, etc.)
    =============================================*/
    static public function mdlActualizarTicket($tabla, $item1, $valor1, $item2, $valor2){

        $columnasPermitidas = ['id', 'estado', 'check', 'id_entidad', 'solicitante',
                               'id_medio', 'id_prioridad', 'descripcion', 'id_user', 'modificacion'];
        if (!in_array($item1, $columnasPermitidas) || !in_array($item2, $columnasPermitidas)) {
            error_log("Intento de inyección SQL detectado en mdlActualizarTicket: " . $item1 . ", " . $item2);
            return "error";
        }

        $stmt = Conexion::conectar()->prepare("UPDATE $tabla SET `$item1` = :$item1 WHERE `$item2` = :$item2");

        $stmt -> bindParam(":".$item1, $valor1, PDO::PARAM_STR);
        $stmt -> bindParam(":".$item2, $valor2, PDO::PARAM_STR);

        if($stmt -> execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt -> close();
        $stmt = null;
    }

    /*=============================================
    BORRAR TICKET
    =============================================*/
    static public function mdlBorrarTicket($tabla, $datos){

        $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE id = :id");
        $stmt -> bindParam(":id", $datos, PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt -> close();
        $stmt = null;
    }
}
?>