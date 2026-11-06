<?php

require_once "conexion.php";

class ModeloUser{

    /*=============================================
    MOSTRAR USUARIOS
    =============================================*/
    static public function mdlMostrarUser($tabla, $item, $valor){

        if($item != null){
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");
            $stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);
            $stmt -> execute();
            return $stmt -> fetch();
        }else{
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");
            $stmt -> execute();
            return $stmt -> fetchAll();
        }

        $stmt -> close();
        $stmt = null;
    }

    /*=============================================
    REGISTRO DE USUARIO
    =============================================*/
    static public function mdlIngresarUser($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(user, name, password, email, phone, foto, id_coordinacion, id_rol, id_entidad, estado, tema)
             VALUES (:user, :name, :password, :email, :phone, :foto, :id_coordinacion, :id_rol, :id_entidad, :estado, :tema)"
        );

        $stmt->bindParam(":user",             $datos["user"],             PDO::PARAM_STR);
        $stmt->bindParam(":name",             $datos["name"],             PDO::PARAM_STR);
        $stmt->bindParam(":password",         $datos["password"],         PDO::PARAM_STR);
        $stmt->bindParam(":email",            $datos["email"],            PDO::PARAM_STR);
        $stmt->bindParam(":phone",            $datos["phone"],            PDO::PARAM_STR);
        $stmt->bindParam(":foto",             $datos["foto"],             PDO::PARAM_STR);
        $stmt->bindParam(":id_coordinacion",  $datos["id_coordinacion"],  PDO::PARAM_STR);
        $stmt->bindParam(":id_rol",           $datos["id_rol"],           PDO::PARAM_STR);
        $stmt->bindParam(":id_entidad",       $datos["id_entidad"],       PDO::PARAM_STR);
        $stmt->bindParam(":estado",           $datos["estado"],           PDO::PARAM_STR);
        $stmt->bindParam(":tema",             $datos["tema"],             PDO::PARAM_STR);

        if($stmt->execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt->close();
        $stmt = null;
    }

    /*=============================================
    EDITAR USUARIO
    =============================================*/
    static public function mdlEditarUser($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET user            = :user,
                 name            = :name,
                 password        = :password,
                 email           = :email,
                 phone           = :phone,
                 foto            = :foto,
                 id_coordinacion = :id_coordinacion,
                 id_rol          = :id_rol,
                 id_entidad      = :id_entidad,
                 tema            = :tema
             WHERE id = :id"
        );

        $stmt -> bindParam(":user",             $datos["user"],             PDO::PARAM_STR);
        $stmt -> bindParam(":name",             $datos["name"],             PDO::PARAM_STR);
        $stmt -> bindParam(":password",         $datos["password"],         PDO::PARAM_STR);
        $stmt -> bindParam(":email",            $datos["email"],            PDO::PARAM_STR);
        $stmt -> bindParam(":phone",            $datos["phone"],            PDO::PARAM_STR);
        $stmt -> bindParam(":foto",             $datos["foto"],             PDO::PARAM_STR);
        $stmt -> bindParam(":id_coordinacion",  $datos["id_coordinacion"],  PDO::PARAM_STR);
        $stmt -> bindParam(":id_rol",           $datos["id_rol"],           PDO::PARAM_STR);
        $stmt -> bindParam(":id_entidad",       $datos["id_entidad"],       PDO::PARAM_STR);
        $stmt -> bindParam(":tema",             $datos["tema"],             PDO::PARAM_STR);
        $stmt -> bindParam(":id",               $datos["id"],               PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt -> close();
        $stmt = null;
    }

    /*=============================================
    ACTUALIZAR USUARIO (login, estado, tema, etc.)
    =============================================*/
    static public function mdlActualizarUser($tabla, $item1, $valor1, $item2, $valor2){

        $columnasPermitidas = ['id', 'estado', 'user', 'name', 'password', 'email', 'phone', 'foto', 'ultimo_login', 'id_coordinacion', 'id_rol', 'id_entidad', 'tema'];
        if (!in_array($item1, $columnasPermitidas) || !in_array($item2, $columnasPermitidas)) {
            error_log("Intento de inyección SQL detectado en mdlActualizarUser: " . $item1 . ", " . $item2);
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
    BORRAR USUARIO
    =============================================*/
    static public function mdlBorrarUser($tabla, $datos){

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