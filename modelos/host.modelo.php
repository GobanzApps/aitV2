<?php

require_once "conexion.php";

class ModeloHost{

    /*=============================================
    MOSTRAR HOSTS
    =============================================*/
    static public function mdlMostrarHost($tabla, $item, $valor){

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
    REGISTRO DE HOST
    =============================================*/
    static public function mdlIngresarHost($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(host, id_host_tipo, id_entidad, color, id_user, estado, modificacion)
             VALUES (:host, :id_host_tipo, :id_entidad, :color, :id_user, :estado, :modificacion)"
        );

        $stmt->bindParam(":host",         $datos["host"],         PDO::PARAM_STR);
        $stmt->bindParam(":id_host_tipo", $datos["id_host_tipo"], PDO::PARAM_STR);
        $stmt->bindParam(":id_entidad",   $datos["id_entidad"],   PDO::PARAM_STR);
        $stmt->bindParam(":color",        $datos["color"],        PDO::PARAM_STR);
        $stmt->bindParam(":id_user",      $datos["id_user"],      PDO::PARAM_INT);
        $stmt->bindParam(":estado",       $datos["estado"],       PDO::PARAM_INT);
        $stmt->bindParam(":modificacion", $datos["modificacion"], PDO::PARAM_STR);

        if($stmt->execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt->close();
        $stmt = null;
    }

    /*=============================================
    EDITAR HOST
    =============================================*/
    static public function mdlEditarHost($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET host         = :host,
                 id_host_tipo = :id_host_tipo,
                 id_entidad   = :id_entidad,
                 color        = :color,
                 id_user      = :id_user,
                 modificacion = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":host",         $datos["host"],         PDO::PARAM_STR);
        $stmt -> bindParam(":id_host_tipo", $datos["id_host_tipo"], PDO::PARAM_STR);
        $stmt -> bindParam(":id_entidad",   $datos["id_entidad"],   PDO::PARAM_STR);
        $stmt -> bindParam(":color",        $datos["color"],        PDO::PARAM_STR);
        $stmt -> bindParam(":id_user",      $datos["id_user"],      PDO::PARAM_INT);
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
    ACTUALIZAR HOST (estado, etc.)
    =============================================*/
    static public function mdlActualizarHost($tabla, $item1, $valor1, $item2, $valor2){

        $columnasPermitidas = ['id', 'estado', 'host', 'id_host_tipo', 'id_entidad', 'color', 'id_user', 'modificacion'];
        if (!in_array($item1, $columnasPermitidas) || !in_array($item2, $columnasPermitidas)) {
            error_log("Intento de inyección SQL detectado en mdlActualizarHost: " . $item1 . ", " . $item2);
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
    BORRAR HOST
    =============================================*/
    static public function mdlBorrarHost($tabla, $datos){

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