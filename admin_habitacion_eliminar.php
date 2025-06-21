<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

$id = intval($_GET['id']);
$conn->query("DELETE FROM habitaciones WHERE id_habitacion = $id");

header('Location: admin_habitaciones.php');
exit;
?>
