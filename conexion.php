<?php
session_start();
 // Conexión a la base de datos
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

?>