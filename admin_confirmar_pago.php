<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

// Confirmar pago si se envió por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pago'])) {
    $id_pago = intval($_POST['confirmar_pago']);
    $conn->query("UPDATE pagos SET estado = 'confirmado' WHERE id_pago = $id_pago");
    header("Location: admin_confirmar_pago.php");
    exit;
}

// Obtener pagos pendientes
$pagos = $conn->query("
    SELECT p.id_pago, p.monto, p.fecha_pago, r.id_reserva, u.nombre, u.apellido
    FROM pagos p
    JOIN reservas r ON p.id_reserva = r.id_reserva
    JOIN usuarios u ON r.id_usuario = u.id_usuario
    WHERE p.estado = 'pendiente'
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <title>Confirmar Pagos - Hotel Rivo</title>
</head>
<body>
    <h1>Confirmar Pagos</h1>
    <a href="admin_dashboard.php">← Volver al Panel</a><br><br>

    <?php if ($pagos->num_rows > 0): ?>
        <table border="1" cellpadding="5">
            <tr>
                <th>ID Pago</th>
                <th>Reserva</th>
                <th>Usuario</th>
                <th>Monto</th>
                <th>Fecha</th>
                <th>Acción</th>
            </tr>
            <?php while ($pago = $pagos->fetch_assoc()): ?>
                <tr>
                    <td><?= $pago['id_pago'] ?></td>
                    <td>#<?= $pago['id_reserva'] ?></td>
                    <td><?= $pago['nombre'] . ' ' . $pago['apellido'] ?></td>
                    <td>$<?= number_format($pago['monto'], 2) ?></td>
                    <td><?= $pago['fecha_pago'] ?></td>
                    <td>
                        <form method="POST">
                            <button type="submit" name="confirmar_pago" value="<?= $pago['id_pago'] ?>">Confirmar</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No hay pagos pendientes por confirmar.</p>
    <?php endif; ?>
</body>
</html>
