<?php
$host = "localhost";
$user = "clindata";
$pass = "123456";
$db   = "clindata_db";

$conn = mysqli_connect($host, $user, $pass, $db);

// Verifica conexión
if (!$conn) {
    die("Conexión fallida: " . mysqli_connect_error());
}
?>
