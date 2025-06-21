<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');
$usuarios = $conn->query("SELECT id_usuario, nombre, email FROM usuarios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="css/admin_estilos.css">
    <meta charset="UTF-8">
    <title>Usuarios - Historial</title>
</head>
<body>
    <h1>Historial de Reservas</h1>
    <a href="admin_dashboard.php">← Volver al Panel</a><br><br>

    <h2>Selecciona un usuario:</h2>
    <ul>
        <?php while ($usuario = $usuarios->fetch_assoc()): ?>
            <li>
                <a href="admin_historial_usuario.php?id=<?= $usuario['id_usuario'] ?>">
                    <?= htmlspecialchars($usuario['nombre']) ?> (<?= $usuario['email'] ?>)
                </a>
            </li>
        <?php endwhile; ?>
    </ul>
</body>
</html>
