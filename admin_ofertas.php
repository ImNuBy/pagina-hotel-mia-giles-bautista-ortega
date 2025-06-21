<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

// Obtener ofertas
$ofertas = $conn->query("SELECT * FROM ofertas_especiales");

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <title>Gestión de Ofertas - Hotel Rivo</title>
</head>
<body>
    <h1>Gestión de Ofertas Especiales</h1>

    <table border="1" cellpadding="6">
        <tr>
            <th>Nombre</th>
            <th>Descripción</th>
            <th>Descuento</th>
            <th>Fechas</th>
        </tr>
        <?php while ($oferta = $ofertas->fetch_assoc()): ?>
            <tr>
                <td><?= $oferta['nombre'] ?></td>
                <td><?= $oferta['descripcion'] ?></td>
                <td><?= $oferta['descuento'] ?>%</td>
                <td><?= $oferta['fecha_inicio'] ?> - <?= $oferta['fecha_fin'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <p><a href="admin_dashboard.php">Volver al Panel</a></p>
</body>
</html>
