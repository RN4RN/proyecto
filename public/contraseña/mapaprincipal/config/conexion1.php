<?php
$host = "mysql-rncorp.alwaysdata.net";
$user = "rncorp";
$pass = "9vDNVL#DLm45wan";
$db = "rncorp_gestion_equipos_telecom";

// Crear conexión
$conn = new mysqli($host, $user, $pass, $db);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
