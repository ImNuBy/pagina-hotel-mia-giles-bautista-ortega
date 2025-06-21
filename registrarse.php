<?php
// Lógica para registrar a un nuevo usuario (esto es solo un ejemplo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hasheo de la contraseña

    $query = "INSERT INTO usuarios (email, contraseña, rol) VALUES ('$email', '$password', 'cliente')";
    if ($conn->query($query) === TRUE) {
        header('Location: login.php'); // Redirige al login después del registro
        exit;
    } else {
        $error = "Error al registrar el usuario.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Hotel Rivo</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="registration-container">
        <h2>Registrarse en Hotel Rivo</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="email" name="email" placeholder="Correo electrónico" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Registrar</button>
        </form>
        <a href="index.php" class="volver-btn">← Volver al sitio</a>
    </div>
</body>
</html>
