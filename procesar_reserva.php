<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'cliente') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

$id_usuario = $_SESSION['user_id'];
$id_habitacion = $_POST['id_habitacion'];
$entrada = $_SESSION['fecha_entrada'];
$salida = $_SESSION['fecha_salida'];

// Insertar la reserva
$query = "INSERT INTO reservas (id_usuario, id_habitacion, fecha_entrada, fecha_salida, estado) 
          VALUES (?, ?, ?, ?, 'pendiente')";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiss", $id_usuario, $id_habitacion, $entrada, $salida);

if ($stmt->execute()) {
    echo "<h2>Reserva confirmada</h2>";
    echo "<p>Tu reserva ha sido registrada. Estado: pendiente.</p>";
} else {
    echo "<p>Error al guardar la reserva: " . $conn->error . "</p>";
}
?>
<a href="index.php">Volver al inicio</a>
