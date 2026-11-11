<?php
// Archivo: modelos/conexion.php (tu archivo original, solo agrega la línea de charset)
class Conexion{
    static public function conectar(){
        $link = new PDO("mysql:host=localhost:3306;dbname=sait",
                        "root",
                        "");
        $link->exec("set names utf8");
        $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $link;
    }
}
?>