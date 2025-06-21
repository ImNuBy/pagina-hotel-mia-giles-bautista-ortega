<?php
$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

$nombre = 'Administrador';
$email = 'admin@rivo.com';
$password = password_hash('admin123', PASSWORD_DEFAULT); // Contraseña segura
$rol = 'admin';

// Verificar si ya existe
$check = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "El usuario administrador ya existe.";
} else {
    // Cambiado 'password' por 'contraseña'
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, contraseña, rol) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nombre, $email, $password, $rol);
    
    if ($stmt->execute()) {
        echo "✅ Usuario administrador creado correctamente.<br>";
        echo "📧 Email: admin@rivo.com<br>";
        echo "🔑 Contraseña: admin123";
    } else {
        echo "❌ Error al crear el administrador.";
    }
}
?>
