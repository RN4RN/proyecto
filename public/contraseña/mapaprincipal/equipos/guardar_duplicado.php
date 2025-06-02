<?php
require_once '../config/conexion.php';
session_start();

function registrarCambio($conexion, $tipo, $detalle) {
    $nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : "Desconocido";
    $sql = "INSERT INTO historial_cambios (tipo_cambio, detalle, realizado_por) VALUES (?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sss", $tipo, $detalle, $nombre);
    return $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_equipo = $_POST['nombre_equipo'];
    $id_original = $_POST['id_equipo']; // Este será el ID del equipo original
    
    // Insertar nuevo equipo (la copia)
    $sql = "INSERT INTO equipos (nombre_equipo, descripcion, tipo_equipo, cantidad_total, cantidad_disponible, serie, estado, estacion, marca, modelo, tip_equip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sssiissssss", 
        $_POST['nombre_equipo'],
        $_POST['descripcion'],
        $_POST['tipo_equipo'],
        $_POST['cantidad_total'],
        $_POST['cantidad_disponible'],
        $_POST['serie'],
        $_POST['estado'],
        $_POST['estacion'],
        $_POST['marca'],
        $_POST['modelo'],
        $_POST['tip_equip']
    );

    if ($stmt->execute()) {
        $id_nuevo = $conexion->insert_id;
        
        // Registrar en historial como DUPLICACIÓN en lugar de CREACIÓN
        $detalle = "Se duplicó el equipo: {$nombre_equipo} (ID Original: {$id_original}, ID Nuevo: {$id_nuevo})";
        registrarCambio($conexion, 'duplicacion', $detalle);
        
        echo json_encode(['success' => true, 'message' => 'Equipo duplicado correctamente!', 'id_nuevo' => $id_nuevo]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al duplicar el equipo']);
    }
}
?>