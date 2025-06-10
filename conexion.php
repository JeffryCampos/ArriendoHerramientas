<?php
$host = "127.0.0.1";  // o "localhost"
$usuario = "root";    // usuario por defecto en XAMPP
$clave = "1234";          // usualmente vacío en XAMPP, si tienes contraseña ponla aquí
$bd = "arriendo_herramientas";

$conexion = new mysqli($host, $usuario, $clave, $bd);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
