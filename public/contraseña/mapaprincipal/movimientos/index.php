<?php
declare(strict_types=1); 

error_reporting(E_ALL);
ini_set('display_errors', '1'); 
ini_set('log_errors', '1');     

require_once('../config/conexion1.php'); 


session_start();


define('UPLOADS_PERFILES_PATH', '../uploads/perfiles/');
define('UPLOADS_EVIDENCIAS_PATH', '../uploads/evidencias_movimientos/');
define('DEFAULT_AVATAR', 'assets/img/avatar-default.png');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); 
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);



function sanitize_input(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: {$url}");
    exit();
}

function get_user_by_session_name(mysqli $db_conn, string $session_name): ?array {
    $stmt = $db_conn->prepare("SELECT id_usuario, nombre, foto_rostro FROM usuarios WHERE nombre = ? LIMIT 1");
    if (!$stmt) {
        error_log("Error preparando consulta get_user_by_session_name: " . $db_conn->error);
        return null;
    }
    $stmt->bind_param("s", $session_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function get_user_by_id(mysqli $db_conn, int $user_id): ?array {
    $stmt = $db_conn->prepare("SELECT id_usuario, nombre FROM usuarios WHERE id_usuario = ? LIMIT 1");
    if (!$stmt) {
        error_log("Error preparando consulta get_user_by_id: " . $db_conn->error);
        return null;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
    return $userData ?: null;
}

function get_equipment_for_update(mysqli $db_conn, int $equipment_id): ?array {
    $stmt = $db_conn->prepare("SELECT id_equipo, nombre_equipo, cantidad_disponible FROM equipos WHERE id_equipo = ? FOR UPDATE");
    if (!$stmt) {
        error_log("Error preparando consulta get_equipment_for_update: " . $db_conn->error);
        return null;
    }
    $stmt->bind_param('i', $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $equipment = $result->fetch_assoc();
    $stmt->close();
    return $equipment ?: null;
}

// --- Autenticación y Obtención del Usuario de Sesión ---
if (!isset($_SESSION['nombre'])) {
    redirect('http://localhost/nuevo/contraseña/indexlogin.php');
}
$nombre_sesion = $_SESSION['nombre'];
$currentUser = get_user_by_session_name($conn, $nombre_sesion); // Usar $conn

if (!$currentUser) {
    // Considerar destruir la sesión y redirigir a login con un mensaje de error.
    error_log("Usuario de sesión '{$nombre_sesion}' no encontrado en la base de datos.");
    session_destroy();
    redirect('http://localhost/nuevo/contraseña/indexlogin.php?error=user_not_found');
    // O si prefieres un die: die("Error crítico: Usuario de sesión no encontrado. Contacte al administrador.");
}
$currentUserId = (int)$currentUser['id_usuario']; // ID del usuario que realiza la acción
$currentUserProfilePic = ($currentUser['foto_rostro'])
    ? UPLOADS_PERFILES_PATH . htmlspecialchars($currentUser['foto_rostro'], ENT_QUOTES, 'UTF-8')
    : DEFAULT_AVATAR;


// --- Variables para Alertas y Estado ---
$alertMessage = "";
$alertClass = "";
$currentTimestamp = date('Y-m-d H:i:s');

// --- Procesamiento del Formulario de Registro de Movimientos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_movimiento'])) {
    $id_equipo_form = isset($_POST['id_equipo']) ? (int)sanitize_input($_POST['id_equipo']) : 0;
    $id_usuario_seleccionado_form = isset($_POST['id_usuario_form']) ? (int)sanitize_input($_POST['id_usuario_form']) : 0;
    $cantidad_form = isset($_POST['cantidad']) ? (int)sanitize_input($_POST['cantidad']) : 0;
    $tipo_movimiento_form = isset($_POST['tipo']) ? sanitize_input($_POST['tipo']) : '';
    $estado_entrega_form = ($tipo_movimiento_form === 'Entrega' && isset($_POST['estado_entrega'])) ? sanitize_input($_POST['estado_entrega']) : null;
    $observacion_form = isset($_POST['observacion']) ? sanitize_input($_POST['observacion']) : '';

    $fecha_salida = $currentTimestamp;
    $fecha_devolucion = ($tipo_movimiento_form === 'Devolución') ? $currentTimestamp : null;

    // Validaciones
    if (empty($id_equipo_form)) {
        $alertMessage = "Debe seleccionar un equipo válido.";
        $alertClass = "alert-danger";
    } elseif (empty($id_usuario_seleccionado_form)) {
        $alertMessage = "Debe seleccionar un usuario/operador válido.";
        $alertClass = "alert-danger";
    } elseif ($cantidad_form <= 0) {
        $alertMessage = "La cantidad debe ser un número positivo.";
        $alertClass = "alert-danger";
    } elseif (empty($tipo_movimiento_form) || !in_array($tipo_movimiento_form, ['Entrega', 'Devolución'])) {
        $alertMessage = "Debe seleccionar un tipo de movimiento válido.";
        $alertClass = "alert-danger";
    } elseif ($tipo_movimiento_form === 'Entrega' && empty($estado_entrega_form)) {
        $alertMessage = "Debe especificar el estado de entrega para el tipo 'Entrega'.";
        $alertClass = "alert-danger";
    } else {
        $conn->begin_transaction();
        try {
            // Obtener datos del usuario seleccionado para el movimiento
            $usuarioMovimiento = get_user_by_id($conn, $id_usuario_seleccionado_form);
            if (!$usuarioMovimiento) {
                throw new Exception("El usuario seleccionado para el movimiento no fue encontrado.");
            }
            $nombre_usuario_movimiento = $usuarioMovimiento['nombre'];

            // Obtener datos del equipo y bloquear la fila para la actualización
            $equipo = get_equipment_for_update($conn, $id_equipo_form);
            if (!$equipo) {
                throw new Exception("El equipo seleccionado no existe o no pudo ser bloqueado.");
            }
            $nombre_equipo = $equipo['nombre_equipo'];
            $cantidad_disponible = (int)$equipo['cantidad_disponible'];

            if ($tipo_movimiento_form === 'Entrega') {
                if ($cantidad_disponible < $cantidad_form) {
                    throw new Exception("No hay suficiente stock de {$nombre_equipo}. Disponibles: {$cantidad_disponible}, Solicitados: {$cantidad_form}.");
                }
                $nueva_cantidad_disponible = $cantidad_disponible - $cantidad_form;
            } else { // Devolución
                $nueva_cantidad_disponible = $cantidad_disponible + $cantidad_form;
            }

            // Insertar en movimientos
            $sql_movimiento = "INSERT INTO movimientos (id_equipo, id_usuario, nombre_usuario, cantidad, fecha_salida, fecha_entrega, tipo_movimiento, estado_entrega_inicial, observacion, nombre_equipo, registrado_por_id_usuario)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_movimiento = $conn->prepare($sql_movimiento);
            if (!$stmt_movimiento) throw new mysqli_sql_exception("Error preparando inserción de movimiento: " . $conn->error);
            
            // El 'estado_entrega_inicial' es el que se registra al momento de la entrega.
            // 'nombre_usuario' es el del operador que recibe/devuelve el equipo.
            // 'registrado_por_id_usuario' es el ID del usuario logueado que está registrando la operación.
            $stmt_movimiento->bind_param(
                'iisississsi',
                $id_equipo_form,
                $id_usuario_seleccionado_form, // ID del operador
                $nombre_usuario_movimiento,    // Nombre del operador
                $cantidad_form,
                $fecha_salida,
                $fecha_devolucion,
                $tipo_movimiento_form,
                $estado_entrega_form, // Puede ser NULL si es Devolución
                $observacion_form,
                $nombre_equipo,
                $currentUserId // Usuario logueado que registra
            );
            if (!$stmt_movimiento->execute()) throw new mysqli_sql_exception("Error al registrar movimiento: " . $stmt_movimiento->error);
            $id_movimiento_insertado = $conn->insert_id;
            $stmt_movimiento->close();

            // Actualizar stock del equipo
            $sql_update_equipo = "UPDATE equipos SET cantidad_disponible = ? WHERE id_equipo = ?";
            $stmt_update_equipo = $conn->prepare($sql_update_equipo);
            if (!$stmt_update_equipo) throw new mysqli_sql_exception("Error preparando actualización de stock: " . $conn->error);
            $stmt_update_equipo->bind_param('ii', $nueva_cantidad_disponible, $id_equipo_form);
            if (!$stmt_update_equipo->execute()) throw new mysqli_sql_exception("Error al actualizar stock: " . $stmt_update_equipo->error);
            $stmt_update_equipo->close();

            // Registrar en estado_entregas (si es una Entrega y debe quedar Pendiente)
            // Esta tabla parece ser para el seguimiento del estado "Entregado Sí/No".
            // Si el movimiento es una 'Entrega', se crea un registro 'No' entregado aquí,
            // que luego se actualiza a 'Sí' mediante la otra parte del UI.
            if ($tipo_movimiento_form === 'Entrega') {
                 // Siempre se crea como 'No' (pendiente de confirmación de entrega física)
                $estado_inicial_tabla_estado_entrega = 'No';
                $sql_estado_entrega = "INSERT INTO estado_entregas (id_movimiento, entregado, observacion, nombre_equipo, nombre_usuario, fecha_creacion)
                                       VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_estado_entrega = $conn->prepare($sql_estado_entrega);
                if (!$stmt_estado_entrega) throw new mysqli_sql_exception("Error preparando inserción de estado entrega: " . $conn->error);
                $stmt_estado_entrega->bind_param(
                    'isssss',
                    $id_movimiento_insertado,
                    $estado_inicial_tabla_estado_entrega,
                    $observacion_form,
                    $nombre_equipo,
                    $nombre_usuario_movimiento, // Usuario que recibe el equipo
                    $currentTimestamp
                );
                if (!$stmt_estado_entrega->execute()) throw new mysqli_sql_exception("Error al registrar estado de entrega: " . $stmt_estado_entrega->error);
                $stmt_estado_entrega->close();
            }

            // Registrar en historial_cambios
            $detalle_historial = "Movimiento '{$tipo_movimiento_form}' registrado para equipo: {$nombre_equipo} (ID: {$id_equipo_form}), " .
                                 "Cantidad: {$cantidad_form}, Operador: {$nombre_usuario_movimiento} (ID: {$id_usuario_seleccionado_form}).";
            
            $sql_historial = "INSERT INTO historial_cambios (tipo_cambio, detalle, realizado_por_id_usuario, realizado_por_nombre, fecha_cambio)
                              VALUES ('Movimiento', ?, ?, ?, ?)";
            $stmt_historial = $conn->prepare($sql_historial);
            if (!$stmt_historial) throw new mysqli_sql_exception("Error preparando inserción de historial: " . $conn->error);
            
            $stmt_historial->bind_param(
                'siss',
                $detalle_historial,
                $currentUserId,                 // Usuario logueado que realiza la acción
                $currentUser['nombre'],         // Nombre del usuario logueado
                $currentTimestamp
            );
            if (!$stmt_historial->execute()) throw new mysqli_sql_exception("Error al registrar historial: " . $stmt_historial->error);
            $stmt_historial->close();
            
            // Manejo de Subida de Evidencias (Opcional)
            if (isset($_FILES['evidencias']) && count($_FILES['evidencias']['name']) > 0) {
                // Asegúrate que la carpeta de subida exista y tenga permisos de escritura.
                if (!is_dir(UPLOADS_EVIDENCIAS_PATH)) {
                    mkdir(UPLOADS_EVIDENCIAS_PATH, 0775, true);
                }

                foreach ($_FILES['evidencias']['name'] as $key => $name) {
                    if ($_FILES['evidencias']['error'][$key] == UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['evidencias']['tmp_name'][$key];
                        $file_name = basename($name);
                        $file_size = $_FILES['evidencias']['size'][$key];
                        $file_type = mime_content_type($tmp_name); // Más fiable que $_FILES['type']

                        if ($file_size > MAX_FILE_SIZE) {
                            throw new Exception("El archivo '{$file_name}' es demasiado grande (Máx: " . (MAX_FILE_SIZE / 1024 / 1024) . " MB).");
                        }
                        if (!in_array($file_type, ALLOWED_MIME_TYPES)) {
                            throw new Exception("El tipo de archivo '{$file_name}' ({$file_type}) no está permitido.");
                        }

                        // Generar un nombre único para el archivo
                        $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $safe_file_name = uniqid('evidencia_' . $id_movimiento_insertado . '_', true) . '.' . $extension;
                        $destination = UPLOADS_EVIDENCIAS_PATH . $safe_file_name;

                        if (move_uploaded_file($tmp_name, $destination)) {
                            // Guardar referencia a la evidencia en la BD (e.g., en una tabla 'movimiento_evidencias' o 'movimientos')
                            // Ejemplo: $sql_evidencia = "INSERT INTO movimiento_evidencias (id_movimiento, nombre_archivo, ruta_archivo) VALUES (?, ?, ?)";
                            // ... preparar y ejecutar statement ...
                            error_log("Evidencia '{$safe_file_name}' subida para movimiento ID {$id_movimiento_insertado}.");
                        } else {
                            throw new Exception("Error al mover el archivo de evidencia '{$file_name}'.");
                        }
                    } elseif ($_FILES['evidencias']['error'][$key] != UPLOAD_ERR_NO_FILE) {
                        throw new Exception("Error al subir el archivo '{$name}': Código " . $_FILES['evidencias']['error'][$key]);
                    }
                }
            }


            $conn->commit();
            $alertMessage = "Movimiento de {$nombre_equipo} ('{$tipo_movimiento_form}') registrado correctamente para el operador: {$nombre_usuario_movimiento}.";
            if ($tipo_movimiento_form === 'Entrega') {
                $alertMessage .= " Estado inicial marcado como 'Pendiente de Entrega'.";
            }
            $alertClass = "alert-success";

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $alertMessage = "Error de base de datos: " . $e->getMessage();
            $alertClass = "alert-danger";
            error_log("Error SQL en transacción de movimiento: " . $e->getMessage() . " - Query: " . ($e->getTrace()[0]['args'][0] ?? 'N/A'));
        } catch (Exception $e) {
            $conn->rollback();
            $alertMessage = "Error: " . $e->getMessage();
            $alertClass = "alert-danger";
            error_log("Error en transacción de movimiento: " . $e->getMessage());
        }
    }
}

// --- Procesamiento de Actualización de Estado (Desde Lista de Pendientes) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_movimiento_actualizar_estado'])) {
    $id_mov_actualizar = (int)$_POST['id_movimiento_actualizar_estado'];
    $nuevo_estado_entrega = sanitize_input($_POST['nuevo_estado_entrega']); // 'Sí' o 'No'
    $observacion_actualizar = sanitize_input($_POST['observacion_actualizar_estado']);

    if (empty($id_mov_actualizar) || !in_array($nuevo_estado_entrega, ['Sí', 'No'])) {
        $alertMessage = "Datos inválidos para actualizar estado de entrega.";
        $alertClass = "alert-danger";
    } else {
        try {
            $conn->begin_transaction();

            $stmt_update_estado = $conn->prepare("UPDATE estado_entregas SET entregado=?, observacion=?, fecha_actualizacion=? WHERE id_movimiento=?");
            if (!$stmt_update_estado) throw new mysqli_sql_exception("Error preparando update estado_entregas: " . $conn->error);
            $stmt_update_estado->bind_param('sssi', $nuevo_estado_entrega, $observacion_actualizar, $currentTimestamp, $id_mov_actualizar);
            if (!$stmt_update_estado->execute()) throw new mysqli_sql_exception("Error actualizando estado_entregas: " . $stmt_update_estado->error);
            $stmt_update_estado->close();

            if ($nuevo_estado_entrega === 'Sí') {
                // Si se marca como 'Sí' entregado, actualizamos la fecha_entrega en la tabla principal 'movimientos'
                $stmt_update_mov_fecha = $conn->prepare("UPDATE movimientos SET fecha_entrega=? WHERE id_movimiento=?");
                if (!$stmt_update_mov_fecha) throw new mysqli_sql_exception("Error preparando update fecha_entrega en movimientos: " . $conn->error);
                $stmt_update_mov_fecha->bind_param('si', $currentTimestamp, $id_mov_actualizar);
                if (!$stmt_update_mov_fecha->execute()) throw new mysqli_sql_exception("Error actualizando fecha_entrega en movimientos: " . $stmt_update_mov_fecha->error);
                $stmt_update_mov_fecha->close();
            }
            // Podríamos añadir una entrada al historial_cambios aquí también
            // ...

            $conn->commit();
            $alertMessage = "Estado del movimiento ID {$id_mov_actualizar} actualizado a '{$nuevo_estado_entrega}'.";
            $alertClass = "alert-success";
            // Para evitar reenvío de formulario con F5, es mejor redirigir,
            // opcionalmente con parámetros para mostrar el mensaje.
            redirect($_SERVER['PHP_SELF'] . '?update_status=success');


        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $alertMessage = "Error de BD al actualizar estado: " . $e->getMessage();
            $alertClass = "alert-danger";
            error_log("Error SQL al actualizar estado: " . $e->getMessage());
        } catch (Exception $e) {
            $conn->rollback();
            $alertMessage = "Error al actualizar estado: " . $e->getMessage();
            $alertClass = "alert-danger";
            error_log("Error general al actualizar estado: " . $e->getMessage());
        }
    }
}

if(isset($_GET['update_status']) && $_GET['update_status'] === 'success' && empty($alertMessage)){
    $alertMessage = "Estado actualizado correctamente.";
    $alertClass = "alert-success";
}


// --- Búsqueda y Listados ---
$busqueda = isset($_GET['buscar']) ? sanitize_input($_GET['buscar']) : '';
$param_busqueda = "%" . $busqueda . "%";

// Consultar Equipos Entregados
$equiposEntregados = [];
$sqlEntregados = "SELECT ee.*, u.nombre as nombre_usuario_display 
                  FROM estado_entregas ee 
                  LEFT JOIN movimientos m ON ee.id_movimiento = m.id_movimiento 
                  LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario 
                  WHERE ee.entregado = 'Sí'";
if (!empty($busqueda)) {
    $sqlEntregados .= " AND (ee.nombre_equipo LIKE ? OR u.nombre LIKE ? OR ee.nombre_usuario LIKE ?)"; // Incluir búsqueda por nombre de usuario en estado_entregas también
}
$sqlEntregados .= " ORDER BY ee.fecha_actualizacion DESC";
$stmtEntregados = $conn->prepare($sqlEntregados);

if (!$stmtEntregados) {
    error_log("Error preparando consulta equipos entregados: " . $conn->error);
    $alertMessage = "Error al preparar listado de equipos entregados."; $alertClass = "alert-danger";
} else {
    if (!empty($busqueda)) {
        $stmtEntregados->bind_param('sss', $param_busqueda, $param_busqueda, $param_busqueda);
    }
    if ($stmtEntregados->execute()) {
        $resultadoEntregados = $stmtEntregados->get_result();
        while ($fila = $resultadoEntregados->fetch_assoc()) {
            $equiposEntregados[] = $fila;
        }
    } else {
        error_log("Error ejecutando consulta equipos entregados: " . $stmtEntregados->error);
        $alertMessage = "Error al obtener listado de equipos entregados."; $alertClass = "alert-danger";
    }
    $stmtEntregados->close();
}


// Consultar Equipos Pendientes
$equiposPendientes = [];
$sqlPendientes = "SELECT ee.*, u.nombre as nombre_usuario_display
                  FROM estado_entregas ee 
                  LEFT JOIN movimientos m ON ee.id_movimiento = m.id_movimiento 
                  LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario 
                  WHERE ee.entregado = 'No'";
if (!empty($busqueda)) {
    $sqlPendientes .= " AND (ee.nombre_equipo LIKE ? OR u.nombre LIKE ? OR ee.nombre_usuario LIKE ?)";
}
$sqlPendientes .= " ORDER BY ee.fecha_creacion DESC"; // O fecha_actualizacion si se prefiere
$stmtPendientes = $conn->prepare($sqlPendientes);

if (!$stmtPendientes) {
    error_log("Error preparando consulta equipos pendientes: " . $conn->error);
    $alertMessage = "Error al preparar listado de equipos pendientes."; $alertClass = "alert-danger";
} else {
    if (!empty($busqueda)) {
        $stmtPendientes->bind_param('sss', $param_busqueda, $param_busqueda, $param_busqueda);
    }
    if ($stmtPendientes->execute()) {
        $resultadoPendientes = $stmtPendientes->get_result();
        while ($fila = $resultadoPendientes->fetch_assoc()) {
            $equiposPendientes[] = $fila;
        }
    } else {
        error_log("Error ejecutando consulta equipos pendientes: " . $stmtPendientes->error);
        $alertMessage = "Error al obtener listado de equipos pendientes."; $alertClass = "alert-danger";
    }
    $stmtPendientes->close();
}

// Consultar Historial de Movimientos para la tabla inferior
$historialMovimientos = [];
$sql_historial_display = "SELECT id_movimiento, id_equipo, nombre_equipo, id_usuario, nombre_usuario, cantidad, 
                                 fecha_salida, fecha_entrega, tipo_movimiento, estado_entrega_inicial, observacion 
                          FROM movimientos 
                          ORDER BY id_movimiento DESC LIMIT 20";
$result_historial = $conn->query($sql_historial_display); // Query simple, no requiere params
if ($result_historial) {
    while ($row = $result_historial->fetch_assoc()) {
        $historialMovimientos[] = $row;
    }
} else {
    error_log("Error al cargar historial de movimientos: " . $conn->error);
    $alertMessage = $alertMessage . (empty($alertMessage) ? '' : '<br>') . "Error al cargar el historial de movimientos.";
    $alertClass = empty($alertClass) ? "alert-warning" : $alertClass;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Movimientos de Equipos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../volver.css"> <!-- Asegúrate que esta ruta sea correcta -->
    <link rel="stylesheet" href="styles1.css"> <!-- Asegúrate que esta ruta sea correcta -->

    <style>
        body { font-family: Arial, sans-serif; background-color:rgb(77, 77, 77); } /* Un fondo más neutro */
        .movi {
            flex: 1; display: flex; justify-content: center; align-items: flex-start; /* flex-start en lugar de start */
            padding: 20px 15px; box-sizing: border-box;  /* Ajustado para que no se solape con la barra fija */
        }
        .movi .card {
            background-color: rgba(55, 55, 55, 0.85); /* Ligeramente más claro */
            width: 100%; max-width: 800px; box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15); /* Sombra más suave */
            color: white; border-radius: 12px; /* Bordes más redondeados */
            border: none; /* Quitar borde por defecto */
        }
        .card-header h2, .card-body h2 { margin-bottom: 1.5rem; } /* Espaciado consistente */
        .select2-container .select2-selection--single { height: calc(2.25rem + 2px); padding: 0.375rem 0.75rem; border-radius: .25rem; border: 1px solid #ced4da; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5; padding-left: 0;}
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(2.25rem + 2px); }
        /* Estilos para Select2 en tema oscuro */
        .select2-container--default .select2-selection--single { background-color: #495057; color: #fff; border: 1px solid #6c757d; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: #fff; }
        .select2-dropdown { background-color: #343a40; border: 1px solid #6c757d; }
        .select2-results__option { color: #fff; }
        .select2-results__option--highlighted { background-color: #007bff; color: white; }
        .select2-search__field { background-color: #495057; color: #fff; border: 1px solid #6c757d; }

        @media (max-width: 768px) { /* Ajuste para tabletas y móviles */
            .movi { margin-top: 60px; padding: 15px 10px; }
            .barra h2 { margin-left: 10px; font-size: 1.2rem; } /* Ajustar para móviles */
        }
        .barra {
            background-color: #343a40; /* Color estándar de barra de navegación */
            padding: 10px 20px; position: fixed; top: 0; left: 0; width: 100%;
            z-index: 1030; box-shadow: 0 2px 4px rgba(0,0,0,.1);
            display: flex; align-items: center;
        }
        .barra h2 { color: white; margin-left: auto; margin-right: auto; /* Centrar título */ font-size: 1.5rem; }
        .totalcont { display: flex; flex-direction: column; min-height: 100vh; } /* Asegurar altura mínima */
        .contentfor {
            width: 100%; /* Ajustado para ocupar todo el ancho disponible al lado del otro */
            display: flex;
            align-items: flex-start; /* Alineación al inicio */
            justify-content: center;
            padding-top: 20px; /* Espacio para no solapar el contenido de movi */
        }
        .superior {
            display: flex; flex-wrap: wrap; /* Permitir que se envuelvan en pantallas pequeñas */
            width: 100%;
            margin-top: 70px; /* Espacio para la barra fija */
            padding: 10px;
            box-sizing: border-box;
        }
        .superior > div { /* Aplicar a hijos directos: .contentfor y .contenedor_de_panels */
            flex: 1 1 550px; /* Permitir que crezcan y se encojan, base de 550px */
            min-width: 300px; /* Ancho mínimo para cada sección */
            margin: 10px;
        }
        .perfil {
            position: absolute; /* Posición absoluta respecto a .barra */
            right: 20px; top: 50%; transform: translateY(-50%); /* Centrar verticalmente */
            width: 45px; height: 45px; border-radius: 50%;
            overflow: hidden; border: 2px solid white; box-shadow: 0px 2px 5px rgba(0,0,0,0.3);
        }
        .perfil img { width: 100%; height: 100%; object-fit: cover; }
        
        .contenedor_de_panels {
            display: flex; flex-direction: column; /* Paneles uno encima del otro */
            gap: 20px; /* Espacio entre paneles */
            overflow-y: auto; max-height: calc(100vh - 160px); /* Altura máxima y scroll si es necesario */
            padding: 10px;
        }
         .panel {
            background-color: rgba(55, 55, 55, 0.85); color:white;
            padding: 15px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .panel h2 { border-bottom: 1px solid #6c757d; padding-bottom: 10px; margin-bottom: 15px; font-size: 1.2rem;}
        .equipo { 
            background-color: rgb(60, 60, 60); 
            border-left: 5px solid rgb(68, 243, 33); 
            padding: 12px; border-radius: 6px; margin-bottom: 12px; font-size: 0.9em; color:white; 
            transition: background-color 0.2s ease;
        }
        .panel:nth-child(2) .equipo { /* Para el panel de pendientes, si es el segundo */
            border-left-color: rgb(243, 180, 33);
        }
        .equipo:hover { background-color: rgb(80, 80, 80); }
        .equipo strong { color: #e9ecef; }
        .equipo form { margin-top: 8px; }
        .equipo select, .equipo input[type="text"], .equipo textarea { 
            width: calc(100% - 10px); margin: 5px 0; padding: 8px; border: 1px solid #555; 
            border-radius: 4px; background-color: #444; color: white; font-size: 0.9rem;
        }
        .equipo button[type="submit"] { 
            background-color: rgb(0, 143, 5); color: white; padding: 8px 12px; 
            border: none; border-radius: 4px; cursor: pointer; font-weight: bold; 
            margin-top: 5px; transition: background-color 0.2s;
        }
        .equipo button[type="submit"]:hover { background-color: rgb(0, 170, 10); }

        .contenidoInferior {
            width: 100%;
            padding: 20px; box-sizing: border-box;
            background-color: rgba(40, 40, 40, 0.8); /* Fondo oscuro para la tabla */
            margin-top: 20px;
        }
        .search-field input.input { background: transparent; color: white; border: 1px solid #6c757d; }
        .btn-icon-content { background: transparent; border: none; color: white; }

        .table-responsive { max-height: 400px; } /* Scroll para la tabla si es muy larga */
        .table th, .table td { vertical-align: middle; }
        
        .boton-elegante {
            padding: 12px 25px; border: 2px solid #4CAF50; background-color: #383838; color: #ffffff;
            font-size: 1rem; cursor: pointer; border-radius: 25px; transition: all 0.3s ease;
            outline: none; position: relative; overflow: hidden; font-weight: bold;
            text-decoration: none; display: inline-block;
        }
        .boton-elegante:hover { background-color: #4CAF50; color: #fff; border-color: #3e8e41; }
        .boton-elegante h3 { margin:0; font-size: 1em; line-height: 1.2; }
    </style>
</head>
<body>

<div class="totalcont">

    <div class="barra">
        <a href="http://localhost/nuevo/contraseña/mapaprincipal/index.php">
            <button class="button" aria-label="Volver al mapa principal">
                <div class="button-box">
                    <span class="button-elem">
                        <svg viewBox="0 0 46 40" xmlns="http://www.w3.org/2000/svg"><path d="M46 20.038c0-.7-.3-1.5-.8-2.1l-16-17c-1.1-1-3.2-1.4-4.4-.3-1.2 1.1-1.2 3.3 0 4.4l11.3 11.9H3c-1.7 0-3 1.3-3 3s1.3 3 3 3h33.1l-11.3 11.9c-1 1-1.2 3.3 0 4.4 1.2 1.1 3.3.8 4.4-.3l16-17c.5-.5.8-1.1.8-1.9z"></path></svg>
                    </span>
                    <span class="button-elem">
                        <svg viewBox="0 0 46 40"><path d="M46 20.038c0-.7-.3-1.5-.8-2.1l-16-17c-1.1-1-3.2-1.4-4.4-.3-1.2 1.1-1.2 3.3 0 4.4l11.3 11.9H3c-1.7 0-3 1.3-3 3s1.3 3 3 3h33.1l-11.3 11.9c-1 1-1.2 3.3 0 4.4 1.2 1.1 3.3.8 4.4-.3l16-17c.5-.5.8-1.1.8-1.9z"></path></svg>
                    </span>
                </div>
            </button>
        </a>
        <h2>Gestión de Movimiento de Equipos</h2>
        <div class="perfil">
            <img src="<?= htmlspecialchars($currentUserProfilePic, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de perfil de <?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?>">
        </div>
    </div>

    <div class="superior">
        <div class="contentfor">
            <div class="movi">
                <div class="card">
                    <div class="card-body">
                        <h2 class="mb-4 text-center">Registro de Movimientos</h2>

                        <?php if(!empty($alertMessage)): ?>
                        <div class="alert <?php echo htmlspecialchars($alertClass, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                            <?php echo nl2br(htmlspecialchars($alertMessage, ENT_QUOTES, 'UTF-8')); // nl2br para saltos de línea si los hay ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data">
                            <input type="hidden" name="submit_movimiento" value="1">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="id_equipo" class="form-label">Equipo</label>
                                    <select name="id_equipo" id="id_equipo" class="form-select" required>
                                        <option value="">Seleccione un equipo</option>
                                        <?php
                                        $sql_equipos_form = "SELECT id_equipo, IFNULL(nombre_equipo, 'Sin nombre') AS nombre_equipo, IFNULL(estacion, 'Sin estación') AS estacion, IFNULL(serie, 'Sin serie') AS serie, IFNULL(cantidad_disponible, 0) AS cantidad_disponible FROM equipos ORDER BY nombre_equipo";
                                        $result_equipos_form = $conn->query($sql_equipos_form);
                                        if ($result_equipos_form) {
                                            while ($equipo_form = $result_equipos_form->fetch_assoc()) {
                                                $texto_equipo = htmlspecialchars("{$equipo_form['nombre_equipo']} - Est: {$equipo_form['estacion']} - Serie: {$equipo_form['serie']}", ENT_QUOTES, 'UTF-8');
                                                $texto_equipo .= ((int)$equipo_form['cantidad_disponible'] <= 0) ? " (Sin stock)" : " (Disp: {$equipo_form['cantidad_disponible']})";
                                                echo "<option value='" . htmlspecialchars((string)$equipo_form['id_equipo'], ENT_QUOTES, 'UTF-8') . "'>" . $texto_equipo . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="id_usuario_form" class="form-label">Operador/Usuario</label>
                                    <select name="id_usuario_form" id="id_usuario_form" class="form-select" required>
                                        <option value="">Seleccione un usuario</option>
                                        <?php
                                        $sql_usuarios_form = "SELECT id_usuario, nombre FROM usuarios ORDER BY nombre";
                                        $result_usuarios_form = $conn->query($sql_usuarios_form);
                                        if ($result_usuarios_form) {
                                            while ($usuario_form = $result_usuarios_form->fetch_assoc()) {
                                                echo "<option value='" . htmlspecialchars((string)$usuario_form['id_usuario'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($usuario_form['nombre'], ENT_QUOTES, 'UTF-8') . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="cantidad" class="form-label">Cantidad</label>
                                    <input type="number" name="cantidad" id="cantidad" class="form-control" value="1" min="1" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="tipo_movimiento" class="form-label">Tipo de Movimiento</label>
                                    <select name="tipo" id="tipo_movimiento" class="form-select" required>
                                        <option value="">Seleccione</option>
                                        <option value="Entrega">Entrega</option>
                                        <option value="Devolución">Devolución</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="estado-entrega-container" style="display: none;">
                                    <label for="estado_entrega" class="form-label">Estado Físico (al entregar)</label>
                                    <select name="estado_entrega" id="estado_entrega" class="form-select">
                                        <option value="">Seleccione</option>
                                        <option value="Bueno">Bueno</option>
                                        <option value="Malo">Malo</option>
                                        <option value="Incompleto">Incompleto</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="observacion" class="form-label">Observaciones</label>
                                <textarea name="observacion" id="observacion" class="form-control" rows="3" placeholder="Ingrese detalles adicionales..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="evidencias" class="form-label">Subir Evidencias (Opcional)</label>
                                <input type="file" name="evidencias[]" id="evidencias" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf">
                                <div class="form-text" style="color: #ccc;">Formatos: JPG, PNG, PDF. Max 5MB/archivo.</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Registrar Movimiento</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="contenedor_de_panels">
            <div class="panel">
                <h2>Equipos Entregados ✅</h2>
                <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar equipo entregado..." value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>" style="background-color: #495057; color:white; border-color: #6c757d;">
                        <button class="btn btn-outline-secondary" type="submit" style="border-color: #6c757d; color:white;">Buscar</button>
                    </div>
                </form>
                <?php if (!empty($equiposEntregados)): ?>
                    <?php foreach ($equiposEntregados as $fila): ?>
                        <div class="equipo">
                            <strong>Equipo:</strong> <?= htmlspecialchars($fila['nombre_equipo'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>Usuario:</strong> <?= htmlspecialchars($fila['nombre_usuario_display'] ?? $fila['nombre_usuario'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> <br>
                            <strong>ID Mov:</strong> <?= htmlspecialchars((string)$fila['id_movimiento'], ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>Fecha Entrega:</strong> <?= htmlspecialchars($fila['fecha_actualizacion'] ? date('d/m/Y H:i', strtotime($fila['fecha_actualizacion'])) : 'N/A', ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>Obs:</strong> <?= htmlspecialchars($fila['observacion'] ?: 'Sin observaciones', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No hay equipos entregados<?php if($busqueda) echo " que coincidan con '".htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8')."'"; ?>.</p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Equipos Pendientes de Entrega ⏳</h2>
                 <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar equipo pendiente..." value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>" style="background-color: #495057; color:white; border-color: #6c757d;">
                        <button class="btn btn-outline-secondary" type="submit" style="border-color: #6c757d; color:white;">Buscar</button>
                    </div>
                </form>
                <?php if (!empty($equiposPendientes)): ?>
                    <?php foreach ($equiposPendientes as $fila): ?>
                        <div class="equipo">
                            <strong>Equipo:</strong> <?= htmlspecialchars($fila['nombre_equipo'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>Usuario:</strong> <?= htmlspecialchars($fila['nombre_usuario_display'] ?? $fila['nombre_usuario'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> <br>
                            <strong>ID Mov:</strong> <?= htmlspecialchars((string)$fila['id_movimiento'], ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>Fecha Registro:</strong> <?= htmlspecialchars($fila['fecha_creacion'] ? date('d/m/Y H:i', strtotime($fila['fecha_creacion'])) : 'N/A', ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>Obs. Inicial:</strong> <?= htmlspecialchars($fila['observacion'] ?: 'Sin observaciones', ENT_QUOTES, 'UTF-8') ?><br>

                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id_movimiento_actualizar_estado" value="<?= htmlspecialchars((string)$fila['id_movimiento'], ENT_QUOTES, 'UTF-8') ?>">
                                <label for="nuevo_estado_entrega_<?= htmlspecialchars((string)$fila['id_movimiento'], ENT_QUOTES, 'UTF-8') ?>">Marcar como:</label>
                                <select name="nuevo_estado_entrega" id="nuevo_estado_entrega_<?= htmlspecialchars((string)$fila['id_movimiento'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    <option value="Sí">Entregado</option>
                                    <!-- Opción para revertir a No podría ser útil en algunos casos -->
                                    <!-- <option value="No">Marcar como NO Entregado (Corregir)</option> -->
                                </select><br>
                                <label for="observacion_actualizar_estado_<?= htmlspecialchars((string)$fila['id_movimiento'], ENT_QUOTES, 'UTF-8') ?>">Obs. Entrega:</label>
                                <input type="text" name="observacion_actualizar_estado" id="observacion_actualizar_estado_<?= htmlspecialchars((string)$fila['id_movimiento'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Descripción de la entrega" required><br>
                                <button type="submit">Confirmar Entrega</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Todos los equipos han sido procesados o no hay pendientes<?php if($busqueda) echo " que coincidan con '".htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8')."'"; ?> 🎉</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="contenidoInferior">
        <div class="text-center mb-3">
            <a href="historial.php" class="boton-elegante">
                <h3>Historial Completo <br>de Movimientos</h3>
            </a>
        </div>
        <h4 class="text-white text-center my-3">Últimos 20 Movimientos Registrados</h4>
        <div class="table-responsive">
            <table class="table table-dark table-striped table-hover text-center align-middle">
                <thead>
                    <tr>
                        <th>ID Mov.</th>
                        <th>Equipo (ID)</th>
                        <th>Operador (ID)</th>
                        <th>Tipo Mov.</th>
                        <th>Cant.</th>
                        <th>Fecha Salida</th>
                        <th>Fecha Entrega Confirmada</th>
                        <th>Estado Físico Inicial</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($historialMovimientos)): ?>
                        <?php foreach ($historialMovimientos as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$row['id_movimiento'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['nombre_equipo'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$row['id_equipo'], ENT_QUOTES, 'UTF-8') ?>)</td>
                                <td><?= htmlspecialchars($row['nombre_usuario'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$row['id_usuario'], ENT_QUOTES, 'UTF-8') ?>)</td>
                                <td><?= htmlspecialchars($row['tipo_movimiento'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)$row['cantidad'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['fecha_salida'] ? date('d/m/Y H:i', strtotime($row['fecha_salida'])) : '---', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['fecha_entrega'] ? date('d/m/Y H:i', strtotime($row['fecha_entrega'])) : '---', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['estado_entrega_inicial'] ?: 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['observacion'] ?: '---', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9">No se encontraron movimientos recientes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Inicializar Select2
        $('#id_equipo, #id_usuario_form').select2({
            width: '100%',
            theme: "default" // o 'bootstrap5' si tienes el tema para BS5
        });

        // Lógica para mostrar/ocultar campo 'estado_entrega'
        $('#tipo_movimiento').on('change', function() {
            if ($(this).val() === 'Entrega') {
                $('#estado-entrega-container').slideDown();
                $('#estado_entrega').prop('required', true);
            } else {
                $('#estado-entrega-container').slideUp();
                $('#estado_entrega').prop('required', false);
                $('#estado_entrega').val(''); // Limpiar valor si se oculta
            }
        }).trigger('change'); // Ejecutar al cargar por si hay valor preseleccionado

        // Auto-cerrar alertas después de un tiempo
        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 7000); // 7 segundos
    });
</script>
</body>
</html>