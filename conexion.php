<?php
$host = "127.0.0.1";
$usuario = "root";
$clave = "";
$bd = "arriendo_herramientas";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {$conexion = new mysqli($host, $usuario, $clave, $bd);$conexion->set_charset("utf8mb4");}
catch (mysqli_sql_exception $e) {die("Error de conexión a la base de datos. Por favor, inténtalo de nuevo más tarde.");}
?>