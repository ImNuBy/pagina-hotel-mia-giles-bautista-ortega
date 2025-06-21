<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reserva'])) {
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

    $id_reserva = intval($_POST['id_reserva']);
    $conn->query("UPDATE reservas SET estado = 'cancelada' WHERE id_reserva = $id_reserva");

    header('Location: admin_dashboard.php?msg=cancelada');
    exit;
} else {
    header('Location: admin_dashboard.php');
    exit;
}
