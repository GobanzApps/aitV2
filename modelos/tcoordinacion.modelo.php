<?php

require_once "conexion.php";

class ModeloTcoordinacion{

    /*=============================================
    MOSTRAR (todos o filtrados)
    =============================================*/
    static public function mdlMostrarTcoordinacion($tabla, $item, $valor){
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
    static public function mdlMostrarTcoordinacionUno($tabla, $item, $valor){
        $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item LIMIT 1");
        $stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);
        $stmt -> execute();
        return $stmt -> fetch();
    }

    /*=============================================
    INGRESAR
    =============================================*/
    static public function mdlIngresarTcoordinacion($tabla, $datos){
        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(tcoordinacion, id_ticket, id_coordinacion, modificacion)
             VALUES (:tcoordinacion, :id_ticket, :id_coordinacion, :modificacion)"
        );

        $stmt->bindParam(":tcoordinacion",   $datos["tcoordinacion"],   PDO::PARAM_STR);
        $stmt->bindParam(":id_ticket",       $datos["id_ticket"],       PDO::PARAM_STR);
        $stmt->bindParam(":id_coordinacion", $datos["id_coordinacion"], PDO::PARAM_STR);
        $stmt->bindParam(":modificacion",    $datos["modificacion"],    PDO::PARAM_STR);

        if($stmt->execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    EDITAR (solo id_coordinacion y modificacion)
    =============================================*/
    static public function mdlEditarTcoordinacion($tabla, $datos){
        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET id_coordinacion = :id_coordinacion,
                 modificacion    = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":id_coordinacion", $datos["id_coordinacion"], PDO::PARAM_STR);
        $stmt -> bindParam(":modificacion",    $datos["modificacion"],    PDO::PARAM_STR);
        $stmt -> bindParam(":id",              $datos["id"],              PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    BORRAR
    =============================================*/
    static public function mdlBorrarTcoordinacion($tabla, $datos){
        $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE id = :id");
        $stmt -> bindParam(":id", $datos, PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }
        return "error";
    }
}
?>