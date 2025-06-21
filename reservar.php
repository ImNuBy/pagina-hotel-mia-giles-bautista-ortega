<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'cliente') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

$habitaciones_disponibles = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entrada = $_POST['entrada'];
    $salida = $_POST['salida'];

    $_SESSION['fecha_entrada'] = $entrada;
    $_SESSION['fecha_salida'] = $salida;

    // Consultar habitaciones que NO están reservadas en ese rango
    $query = "
        SELECT h.id_habitacion, h.numero, t.nombre AS tipo, h.precio_noche 
        FROM habitaciones h
        INNER JOIN tipos_habitacion t ON h.id_tipo = t.id_tipo
        WHERE h.estado = 'disponible'
        AND h.id_habitacion NOT IN (
            SELECT r.id_habitacion FROM reservas r 
            WHERE ('$entrada' < r.fecha_salida AND '$salida' > r.fecha_entrada)
        )
    ";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $habitaciones_disponibles[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reservar - Hotel Rivo</title>
</head>
<body>
    <h2>Reservar habitación</h2>

    <form method="POST">
        <label>Fecha de entrada:</label>
        <input type="date" name="entrada" required>
        <label>Fecha de salida:</label>
        <input type="date" name="salida" required>
        <button type="submit">Buscar habitaciones</button>
    </form>

    <?php if (!empty($habitaciones_disponibles)): ?>
        <h3>Habitaciones disponibles:</h3>
        <form action="procesar_reserva.php" method="POST">
            <ul>
                <?php foreach ($habitaciones_disponibles as $habitacion): ?>
                    <li>
                        Habitación Nº <?= $habitacion['numero'] ?> - Tipo: <?= $habitacion['tipo'] ?> - $<?= $habitacion['precio_noche'] ?> /noche
                        <input type="radio" name="id_habitacion" value="<?= $habitacion['id_habitacion'] ?>" required>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="submit">Confirmar reserva</button>
        </form>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <p>No hay habitaciones disponibles en ese rango de fechas.</p>
    <?php endif; ?>
</body>
</html>
