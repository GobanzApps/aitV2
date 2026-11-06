<?php

require_once "conexion.php";

class ModeloTservicio{

    /*=============================================
    MOSTRAR (todos o filtrados)
    =============================================*/
    static public function mdlMostrarTservicio($tabla, $item, $valor){
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
    static public function mdlMostrarTservicioUno($tabla, $item, $valor){
        $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item LIMIT 1");
        $stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);
        $stmt -> execute();
        return $stmt -> fetch();
    }

    /*=============================================
    INGRESAR
    =============================================*/
    static public function mdlIngresarTservicio($tabla, $datos){
        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(tservicio, id_ticket, id_tag, cantidad, id_host, descripcion, modificacion)
             VALUES (:tservicio, :id_ticket, :id_tag, :cantidad, :id_host, :descripcion, :modificacion)"
        );

        $stmt->bindParam(":tservicio",    $datos["tservicio"],    PDO::PARAM_STR);
        $stmt->bindParam(":id_ticket",    $datos["id_ticket"],    PDO::PARAM_STR);
        $stmt->bindParam(":id_tag",       $datos["id_tag"],       PDO::PARAM_STR);
        $stmt->bindParam(":cantidad",     $datos["cantidad"],     PDO::PARAM_STR);
        $stmt->bindParam(":id_host",      $datos["id_host"],      PDO::PARAM_STR);
        $stmt->bindParam(":descripcion",  $datos["descripcion"],  PDO::PARAM_STR);
        $stmt->bindParam(":modificacion", $datos["modificacion"], PDO::PARAM_STR);

        if($stmt->execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    EDITAR
    =============================================*/
    static public function mdlEditarTservicio($tabla, $datos){
        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET id_tag       = :id_tag,
                 cantidad     = :cantidad,
                 id_host      = :id_host,
                 descripcion  = :descripcion,
                 modificacion = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":id_tag",       $datos["id_tag"],       PDO::PARAM_STR);
        $stmt -> bindParam(":cantidad",     $datos["cantidad"],     PDO::PARAM_STR);
        $stmt -> bindParam(":id_host",      $datos["id_host"],      PDO::PARAM_STR);
        $stmt -> bindParam(":descripcion",  $datos["descripcion"],  PDO::PARAM_STR);
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
    static public function mdlBorrarTservicio($tabla, $datos){
        $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE id = :id");
        $stmt -> bindParam(":id", $datos, PDO::PARAM_INT);
        if($stmt -> execute()){
            return "ok";
        }
        return "error";
    }
}