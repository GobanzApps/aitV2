<?php
require_once "conexion.php";

class ModeloTevidencia{

    /*=============================================
    MOSTRAR (todos o filtrados)
    =============================================*/
    static public function mdlMostrarTevidencia($tabla, $item, $valor){
        if($item != null){
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item ORDER BY id DESC");
            $stmt->bindParam(":".$item, $valor, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll();
        }else{
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY id DESC");
            $stmt->execute();
            return $stmt->fetchAll();
        }
    }

    /*=============================================
    MOSTRAR UNO
    =============================================*/
    static public function mdlMostrarTevidenciaUno($tabla, $item, $valor){
        $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item LIMIT 1");
        $stmt->bindParam(":".$item, $valor, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch();
    }

    /*=============================================
    CONTAR EVIDENCIAS POR TICKET
    =============================================*/
    static public function mdlContarTevidenciaPorTicket($tabla, $id_ticket){
        $stmt = Conexion::conectar()->prepare("SELECT COUNT(*) AS total FROM $tabla WHERE id_ticket = :id_ticket");
        $stmt->bindParam(":id_ticket", $id_ticket, PDO::PARAM_STR);
        $stmt->execute();
        $r = $stmt->fetch();
        return (int)($r["total"] ?? 0);
    }

    /*=============================================
    INGRESAR
    =============================================*/
    static public function mdlIngresarTevidencia($tabla, $datos){
        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(tevidencia, id_ticket, foto)
             VALUES (:tevidencia, :id_ticket, :foto)"
        );
        $stmt->bindParam(":tevidencia", $datos["tevidencia"], PDO::PARAM_STR);
        $stmt->bindParam(":id_ticket",  $datos["id_ticket"],  PDO::PARAM_STR);
        $stmt->bindParam(":foto",       $datos["foto"],       PDO::PARAM_STR);

        if($stmt->execute()){
            return "ok";
        }
        return "error";
    }

    /*=============================================
    BORRAR
    =============================================*/
    static public function mdlBorrarTevidencia($tabla, $datos){
        $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE id = :id");
        $stmt->bindParam(":id", $datos, PDO::PARAM_INT);

        if($stmt->execute()){
            return "ok";
        }
        return "error";
    }
}