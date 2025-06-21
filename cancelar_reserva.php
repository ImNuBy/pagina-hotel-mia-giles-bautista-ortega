<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'cliente') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');
$id_reserva = $_POST['id_reserva'];
$id_usuario = $_SESSION['user_id'];

// Solo puede cancelar su propia reserva
$query = "UPDATE reservas SET estado = 'cancelada' WHERE id_reserva = ? AND id_usuario = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $id_reserva, $id_usuario);

if ($stmt->execute()) {
    echo "<p>Reserva cancelada correctamente.</p>";
} else {
    echo "<p>Error al cancelar la reserva.</p>";
}
?>
<a href="mis_reservas.php">Volver</a>
