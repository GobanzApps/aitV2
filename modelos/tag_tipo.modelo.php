<?php

require_once "conexion.php";

class ModeloTagTipo{

    /*=============================================
    MOSTRAR TIPOS DE TAG
    =============================================*/
    static public function mdlMostrarTagTipo($tabla, $item, $valor){

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
    REGISTRO DE TIPO DE TAG
    =============================================*/
    static public function mdlIngresarTagTipo($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(tag_tipo, descripcion, color, id_user, estado, modificacion)
             VALUES (:tag_tipo, :descripcion, :color, :id_user, :estado, :modificacion)"
        );

        $stmt->bindParam(":tag_tipo",     $datos["tag_tipo"],     PDO::PARAM_STR);
        $stmt->bindParam(":descripcion",  $datos["descripcion"],  PDO::PARAM_STR);
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
    EDITAR TIPO DE TAG
    =============================================*/
    static public function mdlEditarTagTipo($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET tag_tipo     = :tag_tipo,
                 descripcion  = :descripcion,
                 color        = :color,
                 id_user      = :id_user,
                 modificacion = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":tag_tipo",     $datos["tag_tipo"],     PDO::PARAM_STR);
        $stmt -> bindParam(":descripcion",  $datos["descripcion"],  PDO::PARAM_STR);
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
    ACTUALIZAR TIPO DE TAG (estado, etc.)
    =============================================*/
    static public function mdlActualizarTagTipo($tabla, $item1, $valor1, $item2, $valor2){

        // Lista blanca para prevenir inyección SQL
        $columnasPermitidas = ['id', 'estado', 'tag_tipo', 'descripcion', 'color', 'id_user', 'modificacion'];
        if (!in_array($item1, $columnasPermitidas) || !in_array($item2, $columnasPermitidas)) {
            error_log("Intento de inyección SQL detectado en mdlActualizarTagTipo: " . $item1 . ", " . $item2);
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
    BORRAR TIPO DE TAG
    =============================================*/
    static public function mdlBorrarTagTipo($tabla, $datos){

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