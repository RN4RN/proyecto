<?php
header('Content-Type: application/json');
include '../conexion.php'; // Ajusta la ruta si db_connect.php está en otro lugar

$events = [];
$sql = "SELECT id, event_date, event_name, event_details, event_type FROM eventos ORDER BY event_date ASC";
$result = $conexion->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

echo json_encode($events);
$conexion->close();
?>