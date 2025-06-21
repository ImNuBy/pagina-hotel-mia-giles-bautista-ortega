<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$id_reserva = isset($_GET['reserva']) ? (int)$_GET['reserva'] : 0;

if ($id_reserva <= 0) {
    header('Location: admin_reservas.php');
    exit;
}

// Obtener información completa para la factura
$factura_query = "SELECT 
    r.*,
    u.nombre as cliente_nombre,
    u.email as cliente_email,
    h.numero as numero_habitacion,
    th.nombre as tipo_habitacion,
    th.precio_noche,
    th.capacidad,
    p.id_pago,
    p.monto as monto_pagado,
    p.estado as estado_pago,
    p.fecha_pago
    FROM reservas r
    LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
    LEFT JOIN habitaciones h ON r.id_habitacion = h.id_habitacion
    LEFT JOIN tipos_habitacion th ON h.id_tipo = th.id_tipo
    LEFT JOIN pagos p ON r.id_reserva = p.id_reserva
    WHERE r.id_reserva = $id_reserva AND p.estado = 'confirmado'";

$result = $conn->query($factura_query);

if ($result->num_rows == 0) {
    die("No se puede generar factura: Reserva no encontrada o pago no confirmado.");
}

$factura = $result->fetch_assoc();

// Calcular datos de la factura
$fecha_entrada = new DateTime($factura['fecha_entrada']);
$fecha_salida = new DateTime($factura['fecha_salida']);
$noches = $fecha_entrada->diff($fecha_salida)->days;
$subtotal = $factura['precio_noche'] * $noches;
$iva = $subtotal * 0.21; // 21% IVA
$total = $subtotal + $iva;

// Número de factura único
$numero_factura = 'FAC-' . date('Y') . '-' . str_pad($factura['id_reserva'], 6, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?= $numero_factura ?> - Hotel Rivo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .factura-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header-factura {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #007bff;
        }
        .logo-hotel {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        .info-factura {
            text-align: right;
        }
        .numero-factura {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .fecha-factura {
            color: #666;
            font-size: 1.1em;
        }
        .datos-hotel {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .datos-cliente {
            background: #e9ecef;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .section-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2em;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .detalle-reserva {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .tabla-servicios {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .tabla-servicios th,
        .tabla-servicios td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .tabla-servicios th {
            background: #007bff;
            color: white;
            font-weight: bold;
        }
        .tabla-servicios tr:hover {
            background: #f8f9fa;
        }
        .totales {
            background: #e9ecef;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        .total-final {
            font-size: 1.3em;
            font-weight: bold;
            color: #007bff;
            border-top: 2px solid #007bff;
            padding-top: 10px;
            margin-top: 15px;
        }
        .footer-factura {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 0.9em;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: all 0.3s;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .acciones {
            text-align: center;
            margin: 30px 0;
            page-break-inside: avoid;
        }
        .sello-pagado {
            position: absolute;
            top: 150px;
            right: 50px;
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.2em;
            transform: rotate(15deg);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .factura-container {
                box-shadow: none;
                margin: 0;
                padding: 20px;
            }
            .acciones {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="factura-container">
        <!-- Sello de Pagado -->
        <div class="sello-pagado">✓ PAGADO</div>
        
        <!-- Acciones (solo en pantalla) -->
        <div class="acciones">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir Factura</button>
            <a href="admin_reserva_detalle.php?id=<?= $factura['id_reserva'] ?>" class="btn btn-secondary">← Volver a Reserva</a>
            <a href="admin_reservas.php" class="btn btn-secondary">📅 Ver Reservas</a>
        </div>
        
        <!-- Header de la Factura -->
        <div class="header-factura">
            <div>
                <div class="logo-hotel">🏨 HOTEL RIVO</div>
                <div style="color: #666; font-size: 1.1em;">Punta Mogotes - Mar del Plata</div>
            </div>
            <div class="info-factura">
                <div class="numero-factura"><?= $numero_factura ?></div>
                <div class="fecha-factura">Fecha: <?= date('d/m/Y') ?></div>
            </div>
        </div>
        
        <!-- Datos del Hotel -->
        <div class="datos-hotel">
            <div class="section-title">📍 Datos del Establecimiento</div>
            <div class="info-row">
                <span><strong>Razón Social:</strong></span>
                <span>Hotel Rivo S.A.</span>
            </div>
            <div class="info-row">
                <span><strong>CUIT:</strong></span>
                <span>30-12345678-9</span>
            </div>
            <div class="info-row">
                <span><strong>Dirección:</strong></span>
                <span>Av. Costanera 1234, Punta Mogotes, Mar del Plata</span>
            </div>
            <div class="info-row">
                <span><strong>Teléfono:</strong></span>
                <span>+54 223 123-4567</span>
            </div>
            <div class="info-row">
                <span><strong>Email:</strong></span>
                <span>administracion@hotelrivo.com</span>
            </div>
        </div>
        
        <!-- Datos del Cliente -->
        <div class="datos-cliente">
            <div class="section-title">👤 Datos del Cliente</div>
            <div class="info-row">
                <span><strong>Nombre:</strong></span>
                <span><?= htmlspecialchars($factura['cliente_nombre']) ?></span>
            </div>
            <div class="info-row">
                <span><strong>Email:</strong></span>
                <span><?= htmlspecialchars($factura['cliente_email']) ?></span>
            </div>
            <div class="info-row">
                <span><strong>ID Cliente:</strong></span>
                <span><?= $factura['id_usuario'] ?></span>
            </div>
        </div>
        
        <!-- Detalle de la Reserva -->
        <div class="detalle-reserva">
            <div class="section-title">📅 Detalle de la Reserva</div>
            <div class="info-row">
                <span><strong>Reserva N°:</strong></span>
                <span><?= $factura['id_reserva'] ?></span>
            </div>
            <div class="info-row">
                <span><strong>Fecha de Reserva:</strong></span>
                <span><?= date('d/m/Y', strtotime($factura['fecha_reserva'])) ?></span>
            </div>
            <div class="info-row">
                <span><strong>Check-in:</strong></span>
                <span><?= date('d/m/Y', strtotime($factura['fecha_entrada'])) ?></span>
            </div>
            <div class="info-row">
                <span><strong>Check-out:</strong></span>
                <span><?= date('d/m/Y', strtotime($factura['fecha_salida'])) ?></span>
            </div>
            <div class="info-row">
                <span><strong>Habitación:</strong></span>
                <span>#<?= $factura['numero_habitacion'] ?> - <?= htmlspecialchars($factura['tipo_habitacion']) ?></span>
            </div>
            <div class="info-row">
                <span><strong>Capacidad:</strong></span>
                <span><?= $factura['capacidad'] ?> personas</span>
            </div>
        </div>
        
        <!-- Tabla de Servicios -->
        <table class="tabla-servicios">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Alojamiento - <?= htmlspecialchars($factura['tipo_habitacion']) ?></strong><br>
                        <small>Habitación #<?= $factura['numero_habitacion'] ?></small><br>
                        <small>Del <?= date('d/m/Y', strtotime($factura['fecha_entrada'])) ?> al <?= date('d/m/Y', strtotime($factura['fecha_salida'])) ?></small>
                    </td>
                    <td><?= $noches ?> noche<?= $noches != 1 ? 's' : '' ?></td>
                    <td>$<?= number_format($factura['precio_noche'], 2, ',', '.') ?></td>
                    <td>$<?= number_format($subtotal, 2, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Totales -->
        <div class="totales">
            <div class="total-row">
                <span><strong>Subtotal:</strong></span>
                <span>$<?= number_format($subtotal, 2, ',', '.') ?></span>
            </div>
            <div class="total-row">
                <span><strong>IVA (21%):</strong></span>
                <span>$<?= number_format($iva, 2, ',', '.') ?></span>
            </div>
            <div class="total-row total-final">
                <span><strong>TOTAL:</strong></span>
                <span><strong>$<?= number_format($total, 2, ',', '.') ?></strong></span>
            </div>
        </div>
        
        <!-- Información de Pago -->
        <div class="detalle-reserva">
            <div class="section-title">💳 Información de Pago</div>
            <div class="info-row">
                <span><strong>ID de Pago:</strong></span>
                <span>#<?= $factura['id_pago'] ?></span>
            </div>
            <div class="info-row">
                <span><strong>Fecha de Pago:</strong></span>
                <span><?= date('d/m/Y H:i', strtotime($factura['fecha_pago'])) ?></span>
            </div>
            <div class="info-row">
                <span><strong>Monto Pagado:</strong></span>
                <span>$<?= number_format($factura['monto_pagado'], 2, ',', '.') ?></span>
            </div>
            <div class="info-row">
                <span><strong>Estado:</strong></span>
                <span style="color: #28a745; font-weight: bold;">✅ <?= ucfirst($factura['estado_pago']) ?></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer-factura">
            <p><strong>¡Gracias por elegirnos!</strong></p>
            <p>Esta factura es válida como comprobante de pago y estadía.</p>
            <p>Para consultas: administracion@hotelrivo.com | Tel: +54 223 123-4567</p>
            <hr style="margin: 20px 0;">
            <p style="font-size: 0.8em;">
                Hotel Rivo - Punta Mogotes, Mar del Plata<br>
                Documento generado automáticamente el <?= date('d/m/Y H:i') ?>
            </p>
        </div>
    </div>

    <script>
        // Auto-print si se especifica en la URL
        if (window.location.search.includes('print=true')) {
            window.onload = function() {
                window.print();
            }
        }
        
        // Mensaje de confirmación al imprimir
        function confirmarImpresion() {
            if (confirm('¿Desea imprimir la factura?')) {
                window.print();
            }
        }
    </script>
</body>
</html>