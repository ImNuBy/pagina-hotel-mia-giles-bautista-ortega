<?php
include "conexion.php";

$sql = "SELECT id, nombre, precio FROM habitaciones";
$result = $conn->query($sql);

$habitaciones = [];

while ($row = $result->fetch_assoc()) {
    $habitaciones[] = $row;
}

header('Content-Type: application/json');
echo json_encode($habitaciones);
?>
