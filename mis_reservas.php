<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'cliente') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');
$id_usuario = $_SESSION['user_id'];

$query = "
    SELECT r.fecha_entrada, r.fecha_salida, r.estado, h.numero, t.nombre AS tipo
    FROM reservas r
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    INNER JOIN tipos_habitacion t ON h.id_tipo = t.id_tipo
    WHERE r.id_usuario = $id_usuario
    ORDER BY r.fecha_entrada DESC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Reservas - Hotel Rivo</title>
</head>
<body>
    <h2>Mis Reservas</h2>
    <a href="index.php">Volver al inicio</a>
    <ul>
        <?php while ($reserva = $result->fetch_assoc()): ?>
            <li>
                Habitación Nº <?= $reserva['numero'] ?> (<?= $reserva['tipo'] ?>)<br>
                Entrada: <?= $reserva['fecha_entrada'] ?> - Salida: <?= $reserva['fecha_salida'] ?><br>
                Estado: <?= ucfirst($reserva['estado']) ?>
            </li><br>
        <?php endwhile; ?>
    </ul>
    <?php if ($reserva['estado'] != 'cancelada' && strtotime($reserva['fecha_entrada']) > time()): ?>
    <form action="cancelar_reserva.php" method="POST" style="display:inline;">
        <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
        <button type="submit">Cancelar</button>
    </form>
<?php endif; ?>

</body>
</html>
