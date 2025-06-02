<?php
header('Content-Type: application/json');
include '../conexion.php'; // Ajusta la ruta

$response = ['success' => false, 'message' => 'Datos no recibidos.'];

// Leer el cuerpo de la solicitud JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($data && isset($data['date']) && isset($data['name'])) {
    $event_date = $conexion->real_escape_string($data['date']);
    $event_name = $conexion->real_escape_string($data['name']);
    $event_details = isset($data['details']) ? $conn->real_escape_string($data['details']) : '';
    $event_type = isset($data['type']) ? $conn->real_escape_string($data['type']) : 'General';

    if (empty($event_date) || empty($event_name)) {
        $response['message'] = 'La fecha y el nombre del evento son obligatorios.';
    } else {
        $sql = "INSERT INTO eventos (event_date, event_name, event_details, event_type) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssss", $event_date, $event_name, $event_details, $event_type);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Evento agregado correctamente.', 'id' => $conexion->insert_id];
            } else {
                $response['message'] = 'Error al agregar evento: ' . $stmt->error;
            }
            $stmt->close();
        } else {
             $response['message'] = 'Error al preparar la consulta: ' . $conexion->error;
        }
    }
} else {
    $response['message'] = 'Faltan datos necesarios (fecha, nombre).';
}

$conexion->close();
echo json_encode($response);
?>