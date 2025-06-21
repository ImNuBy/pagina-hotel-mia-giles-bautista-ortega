<?php
/**
 * Sistema de Precios Dinámicos para Hotel Rivo
 * 
 * Esta clase maneja la lógica de precios donde las tarifas activas
 * sobrescriben los precios base de los tipos de habitación
 */

class HotelPricingSystem {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Obtiene el precio actual efectivo para un tipo de habitación en una fecha específica
     * 
     * @param int $id_tipo ID del tipo de habitación
     * @param string $fecha Fecha en formato Y-m-d (opcional, por defecto hoy)
     * @return array Información completa del precio
     */
    public function getPrecioEfectivo($id_tipo, $fecha = null) {
        if (!$fecha) {
            $fecha = date('Y-m-d');
        }
        
        // 1. Buscar tarifa activa para esta fecha y tipo de habitación
        $stmt = $this->conn->prepare("
            SELECT 
                t.*,
                th.nombre as tipo_habitacion,
                th.precio_noche as precio_base,
                (t.precio - (t.precio * t.descuento / 100)) as precio_final,
                'tarifa' as fuente_precio
            FROM tarifas t
            INNER JOIN tipos_habitacion th ON t.id_tipo = th.id_tipo
            WHERE t.id_tipo = ? 
            AND ? BETWEEN t.fecha_inicio AND t.fecha_fin
            AND t.activa = 1
            ORDER BY t.fecha_inicio DESC, t.id_tarifa DESC
            LIMIT 1
        ");
        
        $stmt->bind_param("is", $id_tipo, $fecha);
        $stmt->execute();
        $result = $stmt->get_result();
        $tarifa_activa = $result->fetch_assoc();
        
        if ($tarifa_activa) {
            // HAY TARIFA ACTIVA - usar precio de la tarifa
            return [
                'precio_efectivo' => $tarifa_activa['precio_final'],
                'precio_original' => $tarifa_activa['precio'],
                'descuento' => $tarifa_activa['descuento'],
                'precio_base_tipo' => $tarifa_activa['precio_base'],
                'temporada' => $tarifa_activa['temporada'],
                'fuente' => 'tarifa',
                'tarifa_id' => $tarifa_activa['id_tarifa'],
                'tarifa_nombre' => $tarifa_activa['temporada'],
                'vigente_desde' => $tarifa_activa['fecha_inicio'],
                'vigente_hasta' => $tarifa_activa['fecha_fin'],
                'tipo_habitacion' => $tarifa_activa['tipo_habitacion']
            ];
        } else {
            // NO HAY TARIFA ACTIVA - usar precio base del tipo de habitación
            $stmt = $this->conn->prepare("
                SELECT 
                    th.*,
                    th.precio_noche as precio_efectivo,
                    'tipo_habitacion' as fuente_precio
                FROM tipos_habitacion th
                WHERE th.id_tipo = ?
            ");
            
            $stmt->bind_param("i", $id_tipo);
            $stmt->execute();
            $result = $stmt->get_result();
            $tipo = $result->fetch_assoc();
            
            if ($tipo) {
                return [
                    'precio_efectivo' => $tipo['precio_noche'],
                    'precio_original' => $tipo['precio_noche'],
                    'descuento' => 0,
                    'precio_base_tipo' => $tipo['precio_noche'],
                    'temporada' => 'Precio Base',
                    'fuente' => 'tipo_habitacion',
                    'tarifa_id' => null,
                    'tarifa_nombre' => 'Precio Base',
                    'vigente_desde' => null,
                    'vigente_hasta' => null,
                    'tipo_habitacion' => $tipo['nombre']
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Obtiene precios para un rango de fechas
     */
    public function getPreciosRango($id_tipo, $fecha_inicio, $fecha_fin) {
        $precios = [];
        $current_date = new DateTime($fecha_inicio);
        $end_date = new DateTime($fecha_fin);
        
        while ($current_date <= $end_date) {
            $fecha_str = $current_date->format('Y-m-d');
            $precio_info = $this->getPrecioEfectivo($id_tipo, $fecha_str);
            
            if ($precio_info) {
                $precios[$fecha_str] = $precio_info;
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $precios;
    }
    
    /**
     * Actualiza el precio base de un tipo de habitación
     * IMPORTANTE: Solo se debe usar cuando NO hay tarifas activas
     */
    public function actualizarPrecioBase($id_tipo, $nuevo_precio) {
        // Verificar si hay tarifas activas
        $tarifas_activas = $this->getTarifasActivas($id_tipo);
        
        if (!empty($tarifas_activas)) {
            throw new Exception("No se puede cambiar el precio base mientras hay tarifas activas. Gestiona los precios a través de las tarifas.");
        }
        
        $stmt = $this->conn->prepare("UPDATE tipos_habitacion SET precio_noche = ? WHERE id_tipo = ?");
        $stmt->bind_param("di", $nuevo_precio, $id_tipo);
        
        return $stmt->execute();
    }
    
    /**
     * Obtiene todas las tarifas activas para un tipo de habitación
     */
    public function getTarifasActivas($id_tipo, $fecha = null) {
        if (!$fecha) {
            $fecha = date('Y-m-d');
        }
        
        $stmt = $this->conn->prepare("
            SELECT 
                t.*,
                (t.precio - (t.precio * t.descuento / 100)) as precio_final
            FROM tarifas t
            WHERE t.id_tipo = ? 
            AND ? BETWEEN t.fecha_inicio AND t.fecha_fin
            AND t.activa = 1
            ORDER BY t.fecha_inicio DESC
        ");
        
        $stmt->bind_param("is", $id_tipo, $fecha);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tarifas = [];
        while ($row = $result->fetch_assoc()) {
            $tarifas[] = $row;
        }
        
        return $tarifas;
    }
    
    /**
     * Verifica si un tipo de habitación tiene tarifas activas
     */
    public function tieneTarifasActivas($id_tipo, $fecha = null) {
        $tarifas = $this->getTarifasActivas($id_tipo, $fecha);
        return !empty($tarifas);
    }
    
    /**
     * Verifica si se puede editar el precio base de un tipo de habitación
     * Solo se puede editar si NO hay tarifas activas
     */
    public function puedeEditarPrecioBase($id_tipo, $fecha = null) {
        return !$this->tieneTarifasActivas($id_tipo, $fecha);
    }
    
    /**
     * Obtiene información completa de disponibilidad y precios
     */
    public function getDisponibilidadCompleta($fecha_entrada, $fecha_salida, $id_tipo = null) {
        $where_tipo = $id_tipo ? "AND th.id_tipo = ?" : "";
        
        $sql = "
            SELECT 
                th.*,
                COUNT(h.id_habitacion) as habitaciones_totales,
                COUNT(CASE WHEN h.estado = 'disponible' THEN 1 END) as habitaciones_disponibles
            FROM tipos_habitacion th
            LEFT JOIN habitaciones h ON th.id_tipo = h.id_tipo
            WHERE th.activo = 1 {$where_tipo}
            GROUP BY th.id_tipo
        ";
        
        $stmt = $this->conn->prepare($sql);
        if ($id_tipo) {
            $stmt->bind_param("i", $id_tipo);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $disponibilidad = [];
        while ($tipo = $result->fetch_assoc()) {
            // Obtener precio efectivo para el rango de fechas
            $precios_rango = $this->getPreciosRango($tipo['id_tipo'], $fecha_entrada, $fecha_salida);
            
            // Calcular precio promedio y total
            $precio_total = 0;
            $noches = count($precios_rango);
            
            foreach ($precios_rango as $precio_dia) {
                $precio_total += $precio_dia['precio_efectivo'];
            }
            
            $precio_promedio = $noches > 0 ? $precio_total / $noches : 0;
            
            $disponibilidad[] = [
                'tipo_habitacion' => $tipo,
                'precios_por_noche' => $precios_rango,
                'precio_promedio_noche' => $precio_promedio,
                'precio_total_estadia' => $precio_total,
                'noches' => $noches,
                'habitaciones_disponibles' => $tipo['habitaciones_disponibles']
            ];
        }
        
        return $disponibilidad;
    }
    
    /**
     * Método helper para mostrar el precio efectivo en interfaces
     */
    public function formatearPrecioParaMostrar($id_tipo, $fecha = null) {
        $precio_info = $this->getPrecioEfectivo($id_tipo, $fecha);
        
        if (!$precio_info) {
            return "Precio no disponible";
        }
        
        $formato = [];
        
        if ($precio_info['fuente'] == 'tarifa') {
            if ($precio_info['descuento'] > 0) {
                $formato['precio_tachado'] = '$' . number_format($precio_info['precio_original'], 0, ',', '.');
                $formato['precio_final'] = '$' . number_format($precio_info['precio_efectivo'], 0, ',', '.');
                $formato['descuento'] = $precio_info['descuento'] . '% OFF';
                $formato['temporada'] = $precio_info['temporada'];
            } else {
                $formato['precio_final'] = '$' . number_format($precio_info['precio_efectivo'], 0, ',', '.');
                $formato['temporada'] = $precio_info['temporada'];
            }
            $formato['fuente'] = 'Tarifa: ' . $precio_info['tarifa_nombre'];
        } else {
            $formato['precio_final'] = '$' . number_format($precio_info['precio_efectivo'], 0, ',', '.');
            $formato['fuente'] = 'Precio Base';
        }
        
        return $formato;
    }
}

// =========================================
// FUNCIONES HELPER PARA USAR EN LAS VISTAS
// =========================================

/**
 * Función global para obtener precio efectivo
 * Usar en lugar de acceder directamente a tipos_habitacion.precio_noche
 */
function getPrecioHabitacion($conn, $id_tipo, $fecha = null) {
    $pricing = new HotelPricingSystem($conn);
    return $pricing->getPrecioEfectivo($id_tipo, $fecha);
}

/**
 * Función para mostrar precio formateado en vistas
 */
function mostrarPrecioHabitacion($conn, $id_tipo, $fecha = null) {
    $pricing = new HotelPricingSystem($conn);
    return $pricing->formatearPrecioParaMostrar($id_tipo, $fecha);
}

/**
 * Verificar si se puede editar precio base
 */
function puedeEditarPrecioBase($conn, $id_tipo) {
    $pricing = new HotelPricingSystem($conn);
    return $pricing->puedeEditarPrecioBase($id_tipo);
}

// =========================================
// EJEMPLOS DE USO
// =========================================

/*
// En lugar de esto (INCORRECTO):
$precio = $conn->query("SELECT precio_noche FROM tipos_habitacion WHERE id_tipo = 1")->fetch_assoc()['precio_noche'];

// Usar esto (CORRECTO):
$precio_info = getPrecioHabitacion($conn, 1, '2024-12-25');
$precio_efectivo = $precio_info['precio_efectivo'];

// Para mostrar en interfaz:
$precio_formateado = mostrarPrecioHabitacion($conn, 1, '2024-12-25');
echo $precio_formateado['precio_final']; // $100.000
if (isset($precio_formateado['descuento'])) {
    echo $precio_formateado['descuento']; // 20% OFF
}

// Para verificar si se puede editar precio base:
if (puedeEditarPrecioBase($conn, 1)) {
    echo "Puedes editar el precio base";
} else {
    echo "Hay tarifas activas, edita a través de las tarifas";
}
*/

?>