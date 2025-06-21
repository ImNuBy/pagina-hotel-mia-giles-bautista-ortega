<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    // Obtener datos del formulario
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Preparar la consulta para evitar inyecciones SQL
    $query = $conn->prepare("SELECT id_usuario, contraseña, rol FROM usuarios WHERE email = ?");
    $query->bind_param("s", $email);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        // Verificar la contraseña con password_verify
        if (password_verify($password, $user['contraseña'])) {
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_role'] = $user['rol'];

            // Redirigir al panel de administración
            if ($user['rol'] === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: index.php'); // Redirigir a la página principal para clientes
            }
            exit;
        } else {
            $error = "Credenciales incorrectas.";
        }
    } else {
        $error = "Credenciales incorrectas.";
    }

    // Cerrar la conexión
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Hotel Rivo</title>
    <link rel="stylesheet" href="css/login_estilos.css">
</head>
<body>
    <div class="login-container">
        <h2>Bienvenido a Hotel Rivo</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="email" name="email" placeholder="Correo electrónico" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Ingresar</button>
        </form>
        <a href="index.php" class="volver-btn">← Volver al sitio</a>
    </div>

    <div class="background"></div>
</body>
</html>
