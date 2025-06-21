<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

// Estadísticas
$reservas = $conn->query("SELECT COUNT(*) AS total FROM reservas")->fetch_assoc();
$habitaciones_disponibles = $conn->query("SELECT COUNT(*) AS disponibles FROM habitaciones WHERE estado = 'disponible'")->fetch_assoc();
$clientes = $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'cliente'")->fetch_assoc();

// Últimas reservas
$ultimas_reservas = $conn->query("
    SELECT r.id_reserva, u.nombre, h.numero, r.fecha_entrada, r.fecha_salida, r.estado 
    FROM reservas r
    INNER JOIN usuarios u ON r.id_usuario = u.id_usuario
    INNER JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    ORDER BY r.fecha_entrada DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <title>Panel de Administración - Hotel Rivo</title>
</head>
<body>
    <h1>Panel de Administración - Hotel Rivo</h1>

    <?php if (isset($_GET['msg'])): ?>
        <p>
            <?php
                if ($_GET['msg'] == 'confirmada') echo "✅ Reserva confirmada correctamente.";
                if ($_GET['msg'] == 'cancelada') echo "❌ Reserva cancelada correctamente.";
            ?>
        </p>
    <?php endif; ?>

    <h2>Resumen general</h2>
    <ul>
        <li>Total de reservas: <?= $reservas['total'] ?></li>
        <li>Habitaciones disponibles: <?= $habitaciones_disponibles['disponibles'] ?></li>
        <li>Clientes registrados: <?= $clientes['total'] ?></li>
    </ul>

    <h2>Últimas reservas</h2>
    <table border="1" cellpadding="6">
        <tr>
            <th>Cliente</th>
            <th>Habitación</th>
            <th>Entrada</th>
            <th>Salida</th>
            <th>Estado</th>
            <th>Acción</th>
        </tr>
        <?php while ($reserva = $ultimas_reservas->fetch_assoc()): ?>
            <tr>
                <td><?= $reserva['nombre'] ?></td>
                <td><?= $reserva['numero'] ?></td>
                <td><?= $reserva['fecha_entrada'] ?></td>
                <td><?= $reserva['fecha_salida'] ?></td>
                <td><?= ucfirst($reserva['estado']) ?></td>
                <td>
                    <?php if ($reserva['estado'] === 'pendiente'): ?>
                        <form action="admin_confirmar_reserva.php" method="POST" style="display:inline;">
                            <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                            <button type="submit">Confirmar</button>
                        </form>
                        <form action="admin_cancelar_reserva.php" method="POST" style="display:inline;">
                            <input type="hidden" name="id_reserva" value="<?= $reserva['id_reserva'] ?>">
                            <button type="submit">Cancelar</button>
                        </form>
                    <?php else: ?>
                        No disponible
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Gestiones</h2>
    <ul>
        <li><a href="admin_habitaciones.php" class="boton">Gestión de habitaciones</a></li>
        <li><a href="admin_usuarios.php" class="boton">Gestión de usuarios</a></li>
        <li><a href="admin_tipos_habitacion.php" class="boton">Gestión de tipos de habitación</a></li>
        <li><a href="admin_pagos.php" class="boton">Gestión de pagos</a></li> <!-- Nueva opción añadida -->
        <li><a href="admin_comentarios.php" class="boton">Comentarios</a></li> <!-- Nueva opción añadida -->
        <li><a href="admin_tarifas.php" class="boton">💰 Gestión de Tarifas y Precios</a></li>
        <li><a href="admin_reportes.php" class="boton" >Estadísticas</a></li> <!-- Nueva opción añadida -->
        <li><a href="admin_ofertas.php" class="boton">Gestión de ofertas especiales</a></li> <!-- Nueva opción añadida -->
        <li><a href="admin_historial_usuarios.php" class="boton">historial de reservas por usuario</a></li> <!-- Nueva opción añadida -->
        <li><a href="admin_checkin_checkout.php" class="boton">checkins y checkouts</a></li> <!-- Nueva opción añadida -->
    </ul>

    <p><a href="logout.php" class="boton">Cerrar sesión</a></p>
</body>
</html>
