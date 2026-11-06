<?php

require_once "conexion.php";

class ModeloTuser{

    /*=============================================
    MOSTRAR (todos o filtrados)
    =============================================*/
    static public function mdlMostrarTuser($tabla, $item, $valor){
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
    static public function mdlMostrarTuserUno($tabla, $item, $valor){
        $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item LIMIT 1");
        $stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);
        $stmt -> execute();
        return $stmt -> fetch();
    }

    /*=============================================
    INGRESAR
    =============================================*/
    static public function mdlIngresarTuser($tabla, $datos){
        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(tuser, id_ticket, id_user, modificacion)
             VALUES (:tuser, :id_ticket, :id_user, :modificacion)"
        );

        $stmt->bindParam(":tuser",        $datos["tuser"],        PDO::PARAM_STR);
        $stmt->bindParam(":id_ticket",    $datos["id_ticket"],    PDO::PARAM_STR);
        $stmt->bindParam(":id_user",      $datos["id_user"],      PDO::PARAM_STR);
        $stmt->bindParam(":modificacion", $datos["modificacion"], PDO::PARAM_STR);

        if($stmt->execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    EDITAR (solo id_user y modificacion)
    =============================================*/
    static public function mdlEditarTuser($tabla, $datos){
        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET id_user      = :id_user,
                 modificacion = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":id_user",      $datos["id_user"],      PDO::PARAM_STR);
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
    static public function mdlBorrarTuser($tabla, $datos){
        $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE id = :id");
        $stmt -> bindParam(":id", $datos, PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }
        return "error";
    }
}
?>