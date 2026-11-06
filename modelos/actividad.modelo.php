<?php

require_once "conexion.php";

class ModeloActividad{

    /*=============================================
    MOSTRAR ACTIVIDADES
    =============================================*/
    static public function mdlMostrarActividad($tabla, $item, $valor){

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
    REGISTRO DE ACTIVIDAD
    =============================================*/
    static public function mdlIngresarActividad($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(actividad, id_entidad, id_tag, id_coordinacion, cantidad, id_host, id_user, foto, descripcion, estado, modificacion)
             VALUES (:actividad, :id_entidad, :id_tag, :id_coordinacion, :cantidad, :id_host, :id_user, :foto, :descripcion, :estado, :modificacion)"
        );

        $stmt->bindParam(":actividad",       $datos["actividad"],       PDO::PARAM_STR);
        $stmt->bindParam(":id_entidad",      $datos["id_entidad"],      PDO::PARAM_STR);
        $stmt->bindParam(":id_tag",          $datos["id_tag"],          PDO::PARAM_STR);
        $stmt->bindParam(":id_coordinacion", $datos["id_coordinacion"], PDO::PARAM_STR);
        $stmt->bindParam(":cantidad",        $datos["cantidad"],        PDO::PARAM_STR);
        $stmt->bindParam(":id_host",         $datos["id_host"],         PDO::PARAM_STR);
        $stmt->bindParam(":id_user",         $datos["id_user"],         PDO::PARAM_STR);
        $stmt->bindParam(":foto",            $datos["foto"],            PDO::PARAM_STR);
        $stmt->bindParam(":descripcion",     $datos["descripcion"],     PDO::PARAM_STR);
        $stmt->bindParam(":estado",          $datos["estado"],          PDO::PARAM_INT);
        $stmt->bindParam(":modificacion",    $datos["modificacion"],    PDO::PARAM_STR);

        if($stmt->execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt->close();
        $stmt = null;
    }

    /*=============================================
    EDITAR ACTIVIDAD
    =============================================*/
    static public function mdlEditarActividad($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET actividad       = :actividad,
                 id_entidad      = :id_entidad,
                 id_tag          = :id_tag,
                 id_coordinacion = :id_coordinacion,
                 cantidad        = :cantidad,
                 id_host         = :id_host,
                 id_user         = :id_user,
                 foto            = :foto,
                 descripcion     = :descripcion,
                 modificacion    = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":actividad",       $datos["actividad"],       PDO::PARAM_STR);
        $stmt -> bindParam(":id_entidad",      $datos["id_entidad"],      PDO::PARAM_STR);
        $stmt -> bindParam(":id_tag",          $datos["id_tag"],          PDO::PARAM_STR);
        $stmt -> bindParam(":id_coordinacion", $datos["id_coordinacion"], PDO::PARAM_STR);
        $stmt -> bindParam(":cantidad",        $datos["cantidad"],        PDO::PARAM_STR);
        $stmt -> bindParam(":id_host",         $datos["id_host"],         PDO::PARAM_STR);
        $stmt -> bindParam(":id_user",         $datos["id_user"],         PDO::PARAM_STR);
        $stmt -> bindParam(":foto",            $datos["foto"],            PDO::PARAM_STR);
        $stmt -> bindParam(":descripcion",     $datos["descripcion"],     PDO::PARAM_STR);
        $stmt -> bindParam(":modificacion",    $datos["modificacion"],    PDO::PARAM_STR);
        $stmt -> bindParam(":id",              $datos["id"],              PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt -> close();
        $stmt = null;
    }

    /*=============================================
    ACTUALIZAR ACTIVIDAD (estado, etc.)
    =============================================*/
    static public function mdlActualizarActividad($tabla, $item1, $valor1, $item2, $valor2){

        $columnasPermitidas = ['id', 'estado', 'actividad', 'id_entidad', 'id_tag', 'id_coordinacion',
                               'cantidad', 'id_host', 'id_user', 'foto', 'descripcion', 'modificacion'];
        if (!in_array($item1, $columnasPermitidas) || !in_array($item2, $columnasPermitidas)) {
            error_log("Intento de inyección SQL detectado en mdlActualizarActividad: " . $item1 . ", " . $item2);
            return "error";
        }

        $stmt = Conexion::conectar()->prepare("UPDATE $tabla SET $item1 = :$item1 WHERE $item2 = :$item2");

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
    BORRAR ACTIVIDAD
    =============================================*/
    static public function mdlBorrarActividad($tabla, $datos){

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