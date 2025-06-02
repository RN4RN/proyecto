<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['nombre'])) {
    die("No has iniciado sesión.");
}

$nombre_actual = $_SESSION['nombre'];
$nuevo_nombre = $_POST['nombre'] ?? $nombre_actual;
$email = $_POST['email'] ?? '';
$dni = $_POST['dni'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';

// Manejo de la imagen (solo si se sube una nueva)
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    // Validación de imagen
    $info = getimagesize($_FILES['foto']['tmp_name']);
    if ($info === false) {
        header("Location: index.php?error=" . urlencode("El archivo no es una imagen válida."));
        exit;
    }

    $ext = image_type_to_extension($info[2], false);
    $ext = strtolower($ext);
    $permitidas = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $permitidas)) {
        header("Location: index.php?error=" . urlencode("Solo se permiten archivos JPG, PNG, GIF o WEBP."));
        exit;
    }

    // Obtener y borrar foto antigua
    $fotoAntigua = null;
    if ($stmt = $conexion->prepare("SELECT foto_rostro FROM usuarios WHERE nombre = ?")) {
        $stmt->bind_param("s", $nombre_actual);
        $stmt->execute();
        $stmt->bind_result($fotoAntigua);
        $stmt->fetch();
        $stmt->close();
    }

    if ($fotoAntigua) {
        $rutaAntigua = __DIR__ . '/uploads/perfiles/' . $fotoAntigua;
        if (is_file($rutaAntigua)) {
            unlink($rutaAntigua);
        }
    }

    // Guardar nueva imagen
    $base = preg_replace('/[^a-zA-Z0-9]/', '_', $nombre_actual);
    $nuevoNombre = $base . '_' . time() . '.' . $ext;
    $rutaFinal = __DIR__ . '/uploads/perfiles/' . $nuevoNombre;

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $rutaFinal)) {
        header("Location: index.php?error=" . urlencode("No se pudo guardar la imagen."));
        exit;
    }

    // Actualizar con imagen
    $sql = "UPDATE usuarios SET nombre = ?, email = ?, dni = ?, telefono = ?, descripcion = ?, foto_rostro = ? WHERE nombre = ?";
    if ($stmt = $conexion->prepare($sql)) {
        $stmt->bind_param("sssssss", $nuevo_nombre, $email, $dni, $telefono, $descripcion, $nuevoNombre, $nombre_actual);
        $stmt->execute();
        $stmt->close();
    } else {
        header("Location: index.php?error=" . urlencode("Error al preparar la consulta con imagen."));
        exit;
    }
} else {
    // Actualizar sin cambiar la imagen
    $sql = "UPDATE usuarios SET nombre = ?, email = ?, dni = ?, telefono = ?, descripcion = ? WHERE nombre = ?";
    if ($stmt = $conexion->prepare($sql)) {
        $stmt->bind_param("ssssss", $nuevo_nombre, $email, $dni, $telefono, $descripcion, $nombre_actual);
        $stmt->execute();
        $stmt->close();
    } else {
        header("Location: index.php?error=" . urlencode("Error al preparar la consulta sin imagen."));
        exit;
    }
}

// Actualizar sesión si cambió el nombre
if ($nombre_actual !== $nuevo_nombre) {
    $_SESSION['nombre'] = $nuevo_nombre;
}

header("Location: index.php?success=1");
exit;