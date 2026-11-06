<?php

require_once "conexion.php";

class ModeloDoc{

    /*=============================================
    MOSTRAR DOCUMENTOS
    =============================================*/
    static public function mdlMostrarDoc($tabla, $item, $valor){

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
    REGISTRO DE DOCUMENTO
    =============================================*/
    static public function mdlIngresarDoc($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "INSERT INTO $tabla(doc, id_doc_tipo, id_ticket, remitente, destinatario, asunto, adjunto, emision, fecha_registro, id_user, estado, modificacion)
             VALUES (:doc, :id_doc_tipo, :id_ticket, :remitente, :destinatario, :asunto, :adjunto, :emision, :fecha_registro, :id_user, :estado, :modificacion)"
        );

        $stmt->bindParam(":doc",            $datos["doc"],            PDO::PARAM_STR);
        $stmt->bindParam(":id_doc_tipo",    $datos["id_doc_tipo"],    PDO::PARAM_STR);
        $stmt->bindParam(":id_ticket",      $datos["id_ticket"],      PDO::PARAM_STR);
        $stmt->bindParam(":remitente",      $datos["remitente"],      PDO::PARAM_STR);
        $stmt->bindParam(":destinatario",   $datos["destinatario"],   PDO::PARAM_STR);
        $stmt->bindParam(":asunto",         $datos["asunto"],         PDO::PARAM_STR);
        $stmt->bindParam(":adjunto",        $datos["adjunto"],        PDO::PARAM_STR);
        $stmt->bindParam(":emision",        $datos["emision"],        PDO::PARAM_STR);
        $stmt->bindParam(":fecha_registro", $datos["fecha_registro"], PDO::PARAM_STR);
        $stmt->bindParam(":id_user",        $datos["id_user"],        PDO::PARAM_INT);
        $stmt->bindParam(":estado",         $datos["estado"],         PDO::PARAM_INT);
        $stmt->bindParam(":modificacion",   $datos["modificacion"],   PDO::PARAM_STR);

        if($stmt->execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt->close();
        $stmt = null;
    }

    /*=============================================
    EDITAR DOCUMENTO
    =============================================*/
    static public function mdlEditarDoc($tabla, $datos){

        $stmt = Conexion::conectar()->prepare(
            "UPDATE $tabla
             SET doc            = :doc,
                 id_doc_tipo    = :id_doc_tipo,
                 id_ticket      = :id_ticket,
                 remitente      = :remitente,
                 destinatario   = :destinatario,
                 asunto         = :asunto,
                 adjunto        = :adjunto,
                 emision        = :emision,
                 fecha_registro = :fecha_registro,
                 id_user        = :id_user,
                 modificacion   = :modificacion
             WHERE id = :id"
        );

        $stmt -> bindParam(":doc",            $datos["doc"],            PDO::PARAM_STR);
        $stmt -> bindParam(":id_doc_tipo",    $datos["id_doc_tipo"],    PDO::PARAM_STR);
        $stmt -> bindParam(":id_ticket",      $datos["id_ticket"],      PDO::PARAM_STR);
        $stmt -> bindParam(":remitente",      $datos["remitente"],      PDO::PARAM_STR);
        $stmt -> bindParam(":destinatario",   $datos["destinatario"],   PDO::PARAM_STR);
        $stmt -> bindParam(":asunto",         $datos["asunto"],         PDO::PARAM_STR);
        $stmt -> bindParam(":adjunto",        $datos["adjunto"],        PDO::PARAM_STR);
        $stmt -> bindParam(":emision",        $datos["emision"],        PDO::PARAM_STR);
        $stmt -> bindParam(":fecha_registro", $datos["fecha_registro"], PDO::PARAM_STR);
        $stmt -> bindParam(":id_user",        $datos["id_user"],        PDO::PARAM_INT);
        $stmt -> bindParam(":modificacion",   $datos["modificacion"],   PDO::PARAM_STR);
        $stmt -> bindParam(":id",             $datos["id"],             PDO::PARAM_INT);

        if($stmt -> execute()){
            return "ok";
        }else{
            return "error";
        }

        $stmt -> close();
        $stmt = null;
    }

    /*=============================================
    ACTUALIZAR DOCUMENTO (estado, etc.)
    =============================================*/
    static public function mdlActualizarDoc($tabla, $item1, $valor1, $item2, $valor2){

        // ✅ Se añade "fecha_registro" a la lista blanca
        $columnasPermitidas = ['id', 'estado', 'doc', 'id_doc_tipo', 'id_ticket', 'remitente',
                               'destinatario', 'asunto', 'adjunto', 'emision', 'fecha_registro',
                               'id_user', 'modificacion'];
        if (!in_array($item1, $columnasPermitidas) || !in_array($item2, $columnasPermitidas)) {
            error_log("Intento de inyección SQL detectado en mdlActualizarDoc: " . $item1 . ", " . $item2);
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
    BORRAR DOCUMENTO
    =============================================*/
    static public function mdlBorrarDoc($tabla, $datos){

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