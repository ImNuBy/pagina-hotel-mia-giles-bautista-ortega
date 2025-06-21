<?php
// get_tipos_habitaciones.php
// Consulta actualizada para obtener tipos con precios

session_start();
require_once 'config/database.php';

try {
    // Consulta para obtener tipos de habitación con precios
    $query = "SELECT 
                t.id_tipo,
                t.nombre,
                t.descripcion,
                t.precio_noche,
                t.capacidad_maxima,
                t.metros_cuadrados,
                t.amenidades,
                t.imagen_principal,
                COUNT(h.id_habitacion) as total_habitaciones
              FROM tipos_habitaciones t
              LEFT JOIN habitaciones h ON t.id_tipo = h.id_tipo
              WHERE t.activo = 1
              GROUP BY t.id_tipo, t.nombre, t.descripcion, t.precio_noche, 
                       t.capacidad_maxima, t.metros_cuadrados, t.amenidades, t.imagen_principal
              ORDER BY t.precio_noche ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $tipos_habitaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $tipos_habitaciones = [];
    error_log("Error al obtener tipos de habitaciones: " . $e->getMessage());
}
?>

<!-- Formulario actualizado -->
<form id="form-tarifas" class="tarifas-form">
    <div class="form-group">
        <label for="tipo-habitacion">Tipo de habitación</label>
        <select id="tipo-habitacion" name="tipo_habitacion" required>
            <option value="">Seleccione un tipo de habitación</option>
            <?php if (!empty($tipos_habitaciones)): ?>
                <?php foreach ($tipos_habitaciones as $tipo): ?>
                    <option value="<?= htmlspecialchars($tipo['id_tipo']) ?>" 
                            data-precio="<?= $tipo['precio_noche'] ?>"
                            data-nombre="<?= htmlspecialchars($tipo['nombre']) ?>"
                            data-capacidad="<?= $tipo['capacidad_maxima'] ?>"
                            data-metros="<?= $tipo['metros_cuadrados'] ?? '' ?>"
                            data-descripcion="<?= htmlspecialchars($tipo['descripcion'] ?? '') ?>"
                            data-amenidades="<?= htmlspecialchars($tipo['amenidades'] ?? '') ?>"
                            data-total-habitaciones="<?= $tipo['total_habitaciones'] ?>">
                        <?= htmlspecialchars($tipo['nombre']) ?> - 
                        $<?= number_format($tipo['precio_noche'], 0, ',', '.') ?> por noche
                        <?php if ($tipo['capacidad_maxima']): ?>
                            (Hasta <?= $tipo['capacidad_maxima'] ?> personas)
                        <?php endif; ?>
                        <?php if ($tipo['total_habitaciones']): ?>
                            - <?= $tipo['total_habitaciones'] ?> habitaciones
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="" disabled>No hay tipos de habitación disponibles</option>
            <?php endif; ?>
        </select>
    </div>
    
    <!-- Resto del formulario igual que antes -->
    <!-- ... -->
</form>

<?php
// Función para verificar disponibilidad actualizada
function verificarDisponibilidadActualizada($pdo, $id_tipo, $fecha_entrada, $fecha_salida) {
    $query = "SELECT COUNT(DISTINCT h.id_habitacion) as habitaciones_disponibles
              FROM habitaciones h
              JOIN tipos_habitaciones t ON h.id_tipo = t.id_tipo
              WHERE t.id_tipo = ? 
              AND t.activo = 1
              AND h.id_habitacion NOT IN (
                  SELECT hd.id_habitacion 
                  FROM habitaciones_disponibilidad hd 
                  WHERE hd.fecha BETWEEN ? AND DATE_SUB(?, INTERVAL 1 DAY)
                  AND hd.estado = 'ocupada'
              )";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id_tipo, $fecha_entrada, $fecha_salida]);
    $result = $stmt->fetch();
    
    return [
        'disponible' => $result['habitaciones_disponibles'] > 0,
        'cantidad' => $result['habitaciones_disponibles']
    ];
}

// Función para obtener precio del tipo
function obtenerPrecioTipo($pdo, $id_tipo) {
    $query = "SELECT precio_noche, nombre FROM tipos_habitaciones WHERE id_tipo = ? AND activo = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id_tipo]);
    return $stmt->fetch();
}

// Ejemplo de uso en AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar_disponibilidad'])) {
    $id_tipo = $_POST['id_tipo'];
    $fecha_entrada = $_POST['fecha_entrada'];
    $fecha_salida = $_POST['fecha_salida'];
    
    $disponibilidad = verificarDisponibilidadActualizada($pdo, $id_tipo, $fecha_entrada, $fecha_salida);
    $precio_info = obtenerPrecioTipo($pdo, $id_tipo);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'disponible' => $disponibilidad['disponible'],
        'habitaciones_disponibles' => $disponibilidad['cantidad'],
        'precio_noche' => $precio_info['precio_noche'],
        'tipo_nombre' => $precio_info['nombre']
    ]);
    exit;
}
?>