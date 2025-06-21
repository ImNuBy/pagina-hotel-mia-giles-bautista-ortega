<?php
/**
 * AJAX Endpoint para calcular precios dinámicos
 * Archivo: ajax_calcular_precio.php
 */

header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once 'pricing_system.php';

try {
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

    if ($conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos");
    }

    $pricing = new HotelPricingSystem($conn);

    // Obtener y validar parámetros
    $tipo_id = intval($_POST['tipo_id'] ?? 0);
    $fecha_entrada = $_POST['fecha_entrada'] ?? '';
    $fecha_salida = $_POST['fecha_salida'] ?? '';

    if (!$tipo_id || !$fecha_entrada || !$fecha_salida) {
        throw new Exception("Parámetros incompletos");
    }

    // Validar fechas
    if (!strtotime($fecha_entrada) || !strtotime($fecha_salida)) {
        throw new Exception("Fechas inválidas");
    }

    if (strtotime($fecha_entrada) < strtotime(date('Y-m-d'))) {
        throw new Exception("La fecha de entrada no puede ser anterior a hoy");
    }

    if (strtotime($fecha_salida) <= strtotime($fecha_entrada)) {
        throw new Exception("La fecha de salida debe ser posterior a la entrada");
    }

    // Verificar que el tipo de habitación existe
    $stmt = $conn->prepare("SELECT * FROM tipos_habitacion WHERE id_tipo = ? AND activo = 1");
    $stmt->bind_param("i", $tipo_id);
    $stmt->execute();
    $tipo_habitacion = $stmt->get_result()->fetch_assoc();

    if (!$tipo_habitacion) {
        throw new Exception("Tipo de habitación no encontrado");
    }

    // Verificar disponibilidad básica
    $stmt = $conn->prepare("
        SELECT COUNT(*) as disponibles
        FROM habitaciones h
        WHERE h.id_tipo = ? 
        AND h.estado = 'disponible'
        AND h.id_habitacion NOT IN (
            SELECT r.id_habitacion 
            FROM reservas r 
            WHERE r.estado IN ('confirmada', 'pendiente')
            AND NOT (r.fecha_salida <= ? OR r.fecha_entrada >= ?)
        )
    ");
    
    $stmt->bind_param("iss", $tipo_id, $fecha_entrada, $fecha_salida);
    $stmt->execute();
    $disponibilidad = $stmt->get_result()->fetch_assoc();

    if ($disponibilidad['disponibles'] == 0) {
        throw new Exception("No hay habitaciones disponibles para las fechas seleccionadas");
    }

    // Calcular precios usando el sistema de precios dinámicos
    $precios_estadia = $pricing->getPreciosRango($tipo_id, $fecha_entrada, $fecha_salida);
    
    if (empty($precios_estadia)) {
        throw new Exception("Error al calcular precios para el período seleccionado");
    }

    $precio_total = 0;
    $desglose_precios = [];
    $noches = 0;

    foreach ($precios_estadia as $fecha => $precio_info) {
        $precio_total += $precio_info['precio_efectivo'];
        $noches++;
        
        $desglose_precios[] = [
            'fecha' => $fecha,
            'precio' => $precio_info['precio_efectivo'],
            'precio_original' => $precio_info['precio_original'],
            'fuente' => $precio_info['fuente'],
            'tarifa_nombre' => $precio_info['tarifa_nombre'],
            'descuento' => $precio_info['descuento'],
            'temporada' => $precio_info['temporada']
        ];
    }

    // Calcular estadísticas adicionales
    $precio_promedio = $precio_total / $noches;
    $hay_descuentos = array_sum(array_column($desglose_precios, 'descuento')) > 0;
    $tarifas_especiales = count(array_filter($desglose_precios, function($item) {
        return $item['fuente'] === 'tarifa';
    }));

    // Respuesta exitosa
    $response = [
        'success' => true,
        'precio_total' => $precio_total,
        'precio_promedio' => $precio_promedio,
        'noches' => $noches,
        'desglose' => $desglose_precios,
        'habitaciones_disponibles' => $disponibilidad['disponibles'],
        'tipo_habitacion' => [
            'id' => $tipo_habitacion['id_tipo'],
            'nombre' => $tipo_habitacion['nombre'],
            'capacidad' => $tipo_habitacion['capacidad'],
            'precio_base' => $tipo_habitacion['precio_noche']
        ],
        'estadisticas' => [
            'hay_descuentos' => $hay_descuentos,
            'tarifas_especiales' => $tarifas_especiales,
            'ahorro_total' => array_sum(array_map(function($item) {
                return $item['precio_original'] - $item['precio'];
            }, $desglose_precios))
        ],
        'fechas' => [
            'entrada' => $fecha_entrada,
            'salida' => $fecha_salida,
            'entrada_formatted' => date('d/m/Y', strtotime($fecha_entrada)),
            'salida_formatted' => date('d/m/Y', strtotime($fecha_salida))
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Log del error (opcional)
    error_log("Error en ajax_calcular_precio.php: " . $e->getMessage());
    
    // Respuesta de error
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    echo json_encode($response);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>