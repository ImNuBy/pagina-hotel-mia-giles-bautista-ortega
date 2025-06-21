<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $id_tipo = (int)$_GET['id'];

    // Conexión a la base de datos
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    // Eliminar el tipo de habitación
    $sql = "DELETE FROM tipos_habitacion WHERE id_tipo = $id_tipo";
    
    if ($conn->query($sql) === TRUE) {
        header("Location: admin_tipos_habitacion.php?mensaje=Tipo de habitación eliminado exitosamente");
    } else {
        die("Error al eliminar el tipo de habitación: " . $conn->error);
    }

    $conn->close();
} else {
    die("ID de tipo de habitación no proporcionado.");
}
