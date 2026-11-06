<?php

require_once "conexion.php";

class ModeloMedio{

    /*=============================================
    MOSTRAR MEDIOS
    =============================================*/
    static public function mdlMostrarMedio($tabla, $item, $valor){

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
    REGISTRO DE MEDIO
    =============================================*/
    static public function mdlIngresarMedio($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(medio, color, id_user, estado, modificacion)
             VALUES (:medio, :color, :id_user, :estado, :modificacion)"
        );

        $stmt->bindParam(":medio",        $datos["medio"],        PDO::PARAM_STR);
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
    EDITAR MEDIO
    =============================================*/
    static public function mdlEditarMedio($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET medio        = :medio,
                 color        = :color,
                 id_user      = :id_user,
                 modificacion = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":medio",        $datos["medio"],        PDO::PARAM_STR);
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
    ACTUALIZAR MEDIO (estado, etc.)
    =============================================*/
    static public function mdlActualizarMedio($tabla, $item1, $valor1, $item2, $valor2){

        // Lista blanca para prevenir inyección SQL
        $columnasPermitidas = ['id', 'estado', 'medio', 'color', 'id_user', 'modificacion'];
        if (!in_array($item1, $columnasPermitidas) || !in_array($item2, $columnasPermitidas)) {
            error_log("Intento de inyección SQL detectado en mdlActualizarMedio: " . $item1 . ", " . $item2);
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
    BORRAR MEDIO
    =============================================*/
    static public function mdlBorrarMedio($tabla, $datos){

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