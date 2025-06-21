<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reserva'])) {
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

    $id_reserva = intval($_POST['id_reserva']);
    $conn->query("UPDATE reservas SET estado = 'confirmada' WHERE id_reserva = $id_reserva");

    // También podrías cambiar el estado de la habitación si es necesario
    // Ej: $conn->query("UPDATE habitaciones SET estado = 'ocupada' WHERE id_habitacion = ...");

    header('Location: admin_dashboard.php?msg=confirmada');
    exit;
} else {
    header('Location: admin_dashboard.php');
    exit;
}
