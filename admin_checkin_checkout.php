<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

// Procesar acciones de check-in y check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = intval($_POST['id_reserva']);
    $accion = $_POST['accion'];

    // Obtener la reserva
    $reserva = $conn->query("SELECT * FROM reservas WHERE id_reserva = $id_reserva")->fetch_assoc();
    if ($reserva) {
        $id_habitacion = $reserva['id_habitacion'];
        $fecha_actual = date('Y-m-d H:i:s');

        if ($accion === 'checkin' && $reserva['estado'] === 'pendiente') {
            // Validar que la fecha actual sea igual o posterior a la fecha de entrada
            if (date('Y-m-d') >= $reserva['fecha_entrada']) {
                $conn->query("UPDATE reservas SET estado = 'checkin', fecha_checkin = '$fecha_actual' WHERE id_reserva = $id_reserva");
                $conn->query("UPDATE habitaciones SET estado = 'ocupada' WHERE id_habitacion = $id_habitacion");
            }
        } elseif ($accion === 'checkout' && $reserva['estado'] === 'checkin') {
            if (date('Y-m-d') >= $reserva['fecha_salida']) {
                $conn->query("UPDATE reservas SET estado = 'checkout', fecha_checkout = '$fecha_actual' WHERE id_reserva = $id_reserva");
                $conn->query("UPDATE habitaciones SET estado = 'disponible' WHERE id_habitacion = $id_habitacion");
            }
        }
    }
    header("Location: admin_checkin_checkout.php");
    exit;
}

// Obtener reservas con estado 'pendiente' o 'checkin'
$reservas = $conn->query("
    SELECT r.id_reserva, u.nombre AS cliente, h.numero AS habitacion, r.fecha_entrada, r.fecha_salida, r.estado
    FROM reservas r
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    WHERE r.estado IN ('pendiente', 'checkin')
    ORDER BY r.fecha_entrada ASC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <title>Gestión de Check-In y Check-Out</title>
</head>
<body>
    <h1>Gestión de Check-In y Check-Out</h1>
    <table border="1" cellpadding="6">
        <tr>
            <th>Cliente</th>
            <th>Habitación</th>
            <th>Fecha Entrada</th>
            <th>Fecha Salida</th>
            <th>Estado</th>
            <th>Acción</th>
        </tr>
        <?php while ($reserva = $reservas->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($reserva['cliente']) ?></td>
                <td><?= htmlspecialchars($reserva['habitacion']) ?></td>
                <td><?= $reserva['fecha_entrada'] ?></td>
                <td><?= $reserva['fecha_salida'] ?></td>
                <td><?= $reserva['estado'] ?></td>
                <td>
                    <?php if ($reserva['estado'] === 'pendiente'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                            <input type="hidden" name="accion" value="checkin">
                            <button type="submit">Check-In</button>
                        </form>
                    <?php elseif ($reserva['estado'] === 'checkin'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                            <input type="hidden" name="accion" value="checkout">
                            <button type="submit">Check-Out</button>
                        </form>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    <p><a href="admin_dashboard.php">Volver al Panel</a></p>
</body>
</html>
