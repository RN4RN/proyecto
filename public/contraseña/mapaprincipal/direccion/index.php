<?php
require_once '../config/conexion.php';
session_start();
define('UPLOADS_PERFILES_PATH', '../uploads/perfiles/');
define('DEFAULT_AVATAR', 'assets/img/avatar-default.png');

if (!isset($_SESSION['nombre']) || $_SESSION['rol'] != 'Director') {
    header("Location: http://localhost/nuevo/contrase%C3%B1a/indexlogin.php");
    exit();
}

function redirect(string $url): void {
    header("Location: {$url}");
    exit();
}

function get_user_by_session_name(mysqli $db_conn, string $session_name): ?array {
    $stmt = $db_conn->prepare("SELECT id_usuario, nombre, foto_rostro FROM usuarios WHERE nombre = ? LIMIT 1");
    if (!$stmt) {
        error_log("Error preparando consulta get_user_by_session_name: " . $db_conexion->error);
        return null;
    }
    $stmt->bind_param("s", $session_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function get_user_by_id(mysqli $db_conexion, int $user_id): ?array {
    $stmt = $db_conn->prepare("SELECT id_usuario, nombre FROM usuarios WHERE id_usuario = ? LIMIT 1");
    if (!$stmt) {
        error_log("Error preparando consulta get_user_by_id: " . $db_conexion->error);
        return null;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
    return $userData ?: null;
}

function get_equipment_for_update(mysqli $db_conexion, int $equipment_id): ?array {
    $stmt = $db_conexion->prepare("SELECT id_equipo, nombre_equipo, cantidad_disponible FROM equipos WHERE id_equipo = ? FOR UPDATE");
    if (!$stmt) {
        error_log("Error preparando consulta get_equipment_for_update: " . $db_conexion->error);
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
$currentUser = get_user_by_session_name($conexion, $nombre_sesion); // Usar $conn

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




// --- INICIALIZACIÓN DE VARIABLES ---
$feedback_message = '';
$feedback_type = '';
$current_section = isset($_GET['section']) ? $_GET['section'] : 'dashboard'; // Sección por defecto

// --- FUNCIONES HELPER ---
function set_flash_message($message, $type) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        echo '<div class="alert alert-' . htmlspecialchars($_SESSION['flash_type']) . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($_SESSION['flash_message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

// --- PROCESAMIENTO DE FORMULARIOS (POST REQUESTS) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['asignar_rol_estado'])) {
        $id_usuario = filter_input(INPUT_POST, 'id_usuario_modal', FILTER_VALIDATE_INT);
        $id_rol = filter_input(INPUT_POST, 'id_rol_modal', FILTER_VALIDATE_INT); // Puede ser 0 o nulo
        $nuevo_estado = filter_input(INPUT_POST, 'estado_modal', FILTER_SANITIZE_STRING);

        $errores = [];
        if ($id_usuario === false || $id_usuario === null) $errores[] = "ID de usuario inválido.";
        if (empty($nuevo_estado) || !in_array($nuevo_estado, ['SI', 'NO'])) $errores[] = "Debe seleccionar un estado válido (Activo/Inactivo).";

        if (empty($errores)) {
            $sql = "UPDATE usuarios SET id_rol = ?, activo = ? WHERE id_usuario = ?";
            $stmt = $conexion->prepare($sql);
            $rol_param = ($id_rol == 0 || empty($id_rol)) ? null : $id_rol;
            $stmt->bind_param("isi", $rol_param, $nuevo_estado, $id_usuario);
            if ($stmt->execute()) {
                set_flash_message("Rol y estado actualizados correctamente.", "success");
            } else {
                set_flash_message("Error al actualizar: " . $stmt->error, "danger");
            }
            $stmt->close();
        } else {
            set_flash_message(implode("<br>", $errores), "danger");
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?section=user_management");
        exit();
    }

    if (isset($_POST['desactivar_usuario'])) {
        $id_usuario = filter_input(INPUT_POST, 'id_usuario_desactivar', FILTER_VALIDATE_INT);
        if ($id_usuario) {
            $sql = "UPDATE usuarios SET activo = 'NO' WHERE id_usuario = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $id_usuario);
            if ($stmt->execute()) {
                set_flash_message("Usuario desactivado correctamente.", "success");
            } else {
                set_flash_message("Error al desactivar el usuario: " . $stmt->error, "danger");
            }
            $stmt->close();
        } else {
            set_flash_message("ID de usuario inválido para desactivar.", "danger");
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?section=user_management");
        exit();
    }

    if (isset($_POST['marcar_entregado'])) {
        $id_movimiento = filter_input(INPUT_POST, 'id_movimiento_entregar', FILTER_VALIDATE_INT);
        $observacion = filter_input(INPUT_POST, 'observacion_entrega', FILTER_SANITIZE_STRING);

        if ($id_movimiento) {
            $conexion->begin_transaction();
            try {
                $sql_estado = "UPDATE estado_entregas SET entregado='Sí', observacion=?, fecha_actualizacion=NOW() WHERE id_movimiento=?";
                $stmt_estado = $conexion->prepare($sql_estado);
                $stmt_estado->bind_param("si", $observacion, $id_movimiento);
                $stmt_estado->execute();
                $stmt_estado->close();

                $sql_mov = "UPDATE movimientos SET fecha_entrega=NOW() WHERE id_movimiento=?";
                $stmt_mov = $conexion->prepare($sql_mov);
                $stmt_mov->bind_param("i", $id_movimiento);
                $stmt_mov->execute();
                $stmt_mov->close();

                $conexion->commit();
                set_flash_message("Equipo ID Mov: $id_movimiento marcado como entregado.", "success");
            } catch (mysqli_sql_exception $exception) {
                $conexion->rollback();
                set_flash_message("Error al marcar como entregado: " . $exception->getMessage(), "danger");
            }
        } else {
             set_flash_message("ID de movimiento inválido.", "danger");
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?section=equipment_status");
        exit();
    }
}

// --- OBTENCIÓN DE DATOS PARA LA VISTA (GET REQUESTS Y DATOS GENERALES) ---

// Datos para Dashboard KPIs
$total_usuarios_res = $conexion->query("SELECT COUNT(*) as total FROM usuarios");
$total_usuarios = $total_usuarios_res ? $total_usuarios_res->fetch_assoc()['total'] : 0;

$usuarios_activos_res = $conexion->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 'SI'");
$usuarios_activos = $usuarios_activos_res ? $usuarios_activos_res->fetch_assoc()['total'] : 0;

$equipos_pendientes_res = $conexion->query("SELECT COUNT(*) as total FROM estado_entregas WHERE entregado = 'No'");
$equipos_pendientes_count = $equipos_pendientes_res ? $equipos_pendientes_res->fetch_assoc()['total'] : 0;

$equipos_entregados_res = $conexion->query("SELECT COUNT(*) as total FROM estado_entregas WHERE entregado = 'Sí'");
$equipos_entregados_count = $equipos_entregados_res ? $equipos_entregados_res->fetch_assoc()['total'] : 0;

$data_equipos_estado = [
    'labels' => ['Pendientes', 'Entregados'],
    'data' => [$equipos_pendientes_count, $equipos_entregados_count]
];

$sql_usuarios_rol = "SELECT IFNULL(r.nombre_rol, 'Sin Rol Asignado') as rol, COUNT(u.id_usuario) as cantidad 
                     FROM usuarios u 
                     LEFT JOIN roles r ON u.id_rol = r.id_rol 
                     GROUP BY rol ORDER BY rol";
$res_usuarios_rol = $conexion->query($sql_usuarios_rol);
$labels_usuarios_rol = [];
$data_usuarios_rol = [];
if ($res_usuarios_rol) {
    while ($row = $res_usuarios_rol->fetch_assoc()) {
        $labels_usuarios_rol[] = $row['rol'];
        $data_usuarios_rol[] = $row['cantidad'];
    }
}
$chart_data_usuarios_rol = [
    'labels' => $labels_usuarios_rol,
    'data' => $data_usuarios_rol
];

// Lista de usuarios con roles y estado
$sql_usuarios = "SELECT u.id_usuario, u.nombre, u.email, r.id_rol, r.nombre_rol, u.activo 
                 FROM usuarios u 
                 LEFT JOIN roles r ON u.id_rol = r.id_rol ORDER BY u.nombre";
$resultado_usuarios = $conexion->query($sql_usuarios);
$usuarios_list = [];
if ($resultado_usuarios) {
    while($row = $resultado_usuarios->fetch_assoc()){
        $usuarios_list[] = $row;
    }
}

// Lista de roles disponibles
$sql_roles = "SELECT * FROM roles ORDER BY nombre_rol";
$roles_result = $conexion->query($sql_roles);

// Equipos Entregados y Pendientes
$busqueda_equipos = isset($_GET['buscar_equipos']) ? $conexion->real_escape_string($_GET['buscar_equipos']) : '';

$sqlEntregados = "SELECT ee.*, m.nombre_usuario as usuario_movimiento
                  FROM estado_entregas ee 
                  LEFT JOIN movimientos m ON ee.id_movimiento = m.id_movimiento
                  WHERE ee.entregado = 'Sí'";
if ($busqueda_equipos !== '') {
    $sqlEntregados .= " AND (ee.nombre_equipo LIKE '%$busqueda_equipos%' OR m.nombre_usuario LIKE '%$busqueda_equipos%')";
}
$sqlEntregados .= " ORDER BY ee.fecha_actualizacion DESC LIMIT 20";
$resultadoEntregados = $conexion->query($sqlEntregados);

$sqlPendientes = "SELECT ee.*, m.nombre_usuario as usuario_movimiento, m.fecha_salida
                  FROM estado_entregas ee
                  LEFT JOIN movimientos m ON ee.id_movimiento = m.id_movimiento
                  WHERE ee.entregado = 'No'";
if ($busqueda_equipos !== '') {
    $sqlPendientes .= " AND (ee.nombre_equipo LIKE '%$busqueda_equipos%' OR m.nombre_usuario LIKE '%$busqueda_equipos%')";
}
$sqlPendientes .= " ORDER BY m.fecha_salida ASC LIMIT 20"; 
$resultadoPendientes = $conexion->query($sqlPendientes);

// --- VARIABLES Y LÓGICA PARA FILTROS DEL HISTORIAL DE MOVIMIENTOS ---
$filtro_fecha_inicio = isset($_GET['filtro_fecha_inicio']) ? $_GET['filtro_fecha_inicio'] : '';
$filtro_fecha_fin = isset($_GET['filtro_fecha_fin']) ? $_GET['filtro_fecha_fin'] : '';
$filtro_usuario = isset($_GET['filtro_usuario']) ? $conexion->real_escape_string(trim($_GET['filtro_usuario'])) : '';
$filtro_equipo = isset($_GET['filtro_equipo']) ? $conexion->real_escape_string(trim($_GET['filtro_equipo'])) : '';

$where_clauses_historial = [];
$bind_params_historial = [];
$bind_types_historial = '';

if (!empty($filtro_fecha_inicio)) {
    $where_clauses_historial[] = "m.fecha_salida >= ?";
    $bind_params_historial[] = $filtro_fecha_inicio . " 00:00:00";
    $bind_types_historial .= 's';
}
if (!empty($filtro_fecha_fin)) {
    $where_clauses_historial[] = "m.fecha_salida <= ?";
    $bind_params_historial[] = $filtro_fecha_fin . " 23:59:59";
    $bind_types_historial .= 's';
}
if (!empty($filtro_usuario)) {
    $where_clauses_historial[] = "m.nombre_usuario LIKE ?";
    $bind_params_historial[] = "%" . $filtro_usuario . "%";
    $bind_types_historial .= 's';
}
if (!empty($filtro_equipo)) {
    $where_clauses_historial[] = "m.nombre_equipo LIKE ?";
    $bind_params_historial[] = "%" . $filtro_equipo . "%";
    $bind_types_historial .= 's';
}

$where_sql_historial = "";
if (!empty($where_clauses_historial)) {
    $where_sql_historial = " WHERE " . implode(" AND ", $where_clauses_historial);
}

$items_per_page_historial = 10;
$page_historial = isset($_GET['page_historial']) ? (int)$_GET['page_historial'] : 1;
$offset_historial = ($page_historial - 1) * $items_per_page_historial;

$query_string_filtros = "&filtro_fecha_inicio=" . urlencode($filtro_fecha_inicio) .
                        "&filtro_fecha_fin=" . urlencode($filtro_fecha_fin) .
                        "&filtro_usuario=" . urlencode($filtro_usuario) .
                        "&filtro_equipo=" . urlencode($filtro_equipo);

$sql_total_historial = "SELECT COUNT(*) as total FROM movimientos m" . $where_sql_historial;
$stmt_total_historial = $conexion->prepare($sql_total_historial);
if ($stmt_total_historial) {
    if (!empty($bind_params_historial)) {
        $stmt_total_historial->bind_param($bind_types_historial, ...$bind_params_historial);
    }
    $stmt_total_historial->execute();
    $total_historial_res = $stmt_total_historial->get_result();
    $total_historial_items = $total_historial_res ? $total_historial_res->fetch_assoc()['total'] : 0;
    $stmt_total_historial->close();
} else {
    $total_historial_items = 0; // Fallback
}
$total_historial_pages = $items_per_page_historial > 0 ? ceil($total_historial_items / $items_per_page_historial) : 0;

$sql_historial = "SELECT m.id_movimiento, m.nombre_equipo, m.nombre_usuario, m.cantidad, m.fecha_salida, m.fecha_entrega, m.estado_entrega, m.observacion 
                  FROM movimientos m" . $where_sql_historial .
                 " ORDER BY m.id_movimiento DESC
                  LIMIT ? OFFSET ?";
$stmt_historial = $conexion->prepare($sql_historial);
$result_historial = null;
if ($stmt_historial) {
    $current_bind_types = $bind_types_historial . 'ii';
    $current_bind_params = array_merge($bind_params_historial, [$items_per_page_historial, $offset_historial]);
    $ref_params = [];
    foreach($current_bind_params as $key => $value) {
        $ref_params[$key] = &$current_bind_params[$key];
    }
    if (!empty($current_bind_types)) { // Evitar bind_param con cadena de tipos vacía
      $stmt_historial->bind_param($current_bind_types, ...$ref_params);
    }
    $stmt_historial->execute();
    $result_historial = $stmt_historial->get_result();
}


// Datos del Director
$director_foto = $_SESSION['foto_perfil'] ?? 'https://i.postimg.cc/T3ZGBW81/ingenieria-electronica-uch-universidad-560x416.png';
$director_nombre = $_SESSION['nombre'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Director</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../volver.css"> 
    <style>
        body {
            background-color: #212529; 
            color: #adb5bd; 
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .navbar-custom {
            background-color: #343a40; 
            padding: 0.8rem 1rem;
        }
        .navbar-brand, .nav-link, .welcome-text {
            color: #f8f9fa !important; 
        }
        .nav-link:hover {
            color: #00bcd4 !important; 
        }
        .nav-link.active {
            color: #00bcd4 !important;
            border-bottom: 2px solid #00bcd4;
        }
        .profile-img-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #00bcd4;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            margin-top: 70px; 
        }
        .card-custom {
            background-color: #2c3034; 
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            color: #e9ecef;
        }
        .card-custom .card-header {
            background-color: #343a40;
            color: #00bcd4;
            font-weight: bold;
            border-bottom: 1px solid #495057;
        }
        .kpi-card {
            background-color: #343a40;
            border-left: 5px solid #00bcd4;
            padding: 1.5rem;
            border-radius: 0.3rem;
            margin-bottom: 1rem;
        }
        .kpi-card h3 {
            color: #f8f9fa;
            font-size: 2.5rem;
            margin-bottom: 0.2rem;
        }
        .kpi-card p {
            color: #adb5bd;
            font-size: 0.9rem;
        }
        .table-dark-custom {
            background-color: #2c3034;
            color: #e9ecef;
        }
        
        .table-dark-custom th {
            background-color: #343a40;
            color: #00bcd4;
        }
        .table-dark-custom td, .table-dark-custom th {
            border-color: #495057;
        }
        .btn-cyan {
            background-color: #00bcd4;
            border-color: #00bcd4;
            color: #212529;
            font-weight: bold;
        }
        .btn-cyan:hover {
            background-color: #0097a7;
            border-color: #0097a7;
        }
        .btn-outline-cyan {
            color: #00bcd4;
            border-color: #00bcd4;
        }
        .btn-outline-cyan:hover {
            color: #212529;
            background-color: #00bcd4;
            border-color: #00bcd4;
        }
        .form-control-dark, .form-select-dark {
            background-color:rgb(255, 255, 255);
            color:rgb(0, 123, 247);
            border: 1px rgb(0, 110, 219);
        }
        .form-control-dark:focus, .form-select-dark:focus {
            background-color:rgb(247, 247, 247);
            color:rgb(0, 0, 0);
            border-color: #00bcd4;
            box-shadow: 0 0 0 0.25rem rgba(0, 188, 212, 0.25);
        }
        .modal-content-dark {
            background-color: #2c3034;
            color: #e9ecef;
        }
        .modal-header-dark {
            border-bottom: 1px solid #495057;
        }
        .modal-header-dark .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .equipment-panel {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            background-color:rgb(255, 255, 255);
            border-radius: 0.3rem;
        }
        .equipment-item {
            background-color: #2c3034;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 0.25rem;
            border-left: 3px solid #00bcd4; 
        }
        .equipment-item.border-success { 
             border-left: 3px solid #198754; 
        }
        .equipment-item.border-success strong { color: #198754; }
        .equipment-item strong { color: #00bcd4; }
        .search-form input { margin-bottom: 10px;}
        .nav-pills .nav-link { color: #adb5bd; }
        .nav-pills .nav-link.active { background-color: #00bcd4; color: #212529; }
        .footer-custom {
            background-color: #343a40;
            color: #adb5bd;
            padding: 1rem 0;
            text-align: center;
            font-size: 0.9em;
            margin-top: auto; 
        }
        .pagination .page-item.active .page-link {
            background-color: #00bcd4;
            border-color: #00bcd4;
            color: #212529;
        }
        .pagination .page-link {
            color: #00bcd4;
            background-color: transparent;
            border: 1px solid #495057;
        }
        .pagination .page-link:hover {
            color: #007bff;
            background-color: #3e444a;
            border-color: #495057;
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            background-color: transparent;
            border-color: #495057;
        }
        /*FOOTER*/
.footer {
    
    width: 100%;
    position: fixed;
    bottom: 0;
    left: 0;
    background: rgba(0, 0, 0, 0.158); /* Oscuro con transparencia */
    backdrop-filter: blur(5px); /* Efecto de desenfoque */
    color: rgb(255, 255, 255);
    text-align: center;
    padding: 15px 10px;
    font-size: 14px;
  }
  
  /* Estilos para los enlaces en el footer */
  .footer a {
    color: #01b6ed;
    text-decoration: none;
    transition: color 0.3s ease;
  }
  
  .footer a:hover {
    color: #f2453f;
  }
.perfil {
            position: absolute; /* Posición absoluta respecto a .barra */
            right: 20px; top: 50%; transform: translateY(-50%); /* Centrar verticalmente */
            width: 45px; height: 45px; border-radius: 50%;
            overflow: hidden; border: 2px solid white; box-shadow: 0px 2px 5px rgba(0,0,0,0.3);
            margin: 0px 0px;
        }
        .perfil img { width: 100%; height: 100%; object-fit: cover; }

    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
    <div class="container-fluid">
          <a href="http://localhost/nuevo/contraseña/mapaprincipal/index.php"><button class="button"> 
                <div class="button-box">
                   <span class="button-elem">
                    <svg viewBox="0 0 46 40" xmlns="http://www.w3.org/2000/svg">
                         <path
                           d="M46 20.038c0-.7-.3-1.5-.8-2.1l-16-17c-1.1-1-3.2-1.4-4.4-.3-1.2 1.1-1.2 3.3 0 4.4l11.3 11.9H3c-1.7 0-3 1.3-3 3s1.3 3 3 3h33.1l-11.3 11.9c-1 1-1.2 3.3 0 4.4 1.2 1.1 3.3.8 4.4-.3l16-17c.5-.5.8-1.1.8-1.9z"  ></path>
                     </svg>
                   </span>
        </button></a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDirector" aria-controls="navbarNavDirector" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDirector">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_section == 'dashboard') ? 'active' : ''; ?>" href="?section=dashboard"><i class="fas fa-chart-line me-1"></i>Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_section == 'user_management') ? 'active' : ''; ?>" href="?section=user_management"><i class="fas fa-users-cog me-1"></i>Gestión Usuarios</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_section == 'equipment_status') ? 'active' : ''; ?>" href="?section=equipment_status"><i class="fas fa-tasks me-1"></i>Estado Equipos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_section == 'movement_history') ? 'active' : ''; ?>" href="?section=movement_history"><i class="fas fa-history me-1"></i>Historial Mov.</a>
                </li>
            </ul>
            <span class="navbar-text welcome-text me-3" style="margin: 0px 50px !important;">
                Bienvenido, <?php echo htmlspecialchars($director_nombre); ?>
            </span>
             <div class="perfil">
            <img src="<?= htmlspecialchars($currentUserProfilePic, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de perfil de <?= htmlspecialchars($currentUser['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        </div>
    </div>
</nav>

<div class="main-content">
    <div class="container-fluid">
        <?php display_flash_message(); ?>

        <?php if ($current_section == 'dashboard'): ?>
            <h2 class="mb-4 text-light"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="kpi-card text-center">
                        <h3><?php echo $total_usuarios; ?></h3>
                        <p>Total Usuarios</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="kpi-card text-center">
                        <h3><?php echo $usuarios_activos; ?></h3>
                        <p>Usuarios Activos</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="kpi-card text-center">
                        <h3><?php echo $equipos_pendientes_count; ?></h3>
                        <p>Equipos Pendientes</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="kpi-card text-center">
                        <h3><?php echo $equipos_entregados_count; ?></h3>
                        <p>Equipos Entregados</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card card-custom">
                        <div class="card-header"><i class="fas fa-box-open me-2"></i>Equipos por Estado</div>
                        <div class="card-body" style="min-height: 300px;">
                            <canvas id="chartEquiposEstado"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="card card-custom">
                        <div class="card-header"><i class="fas fa-user-tag me-2"></i>Usuarios por Rol</div>
                        <div class="card-body" style="min-height: 300px;">
                            <canvas id="chartUsuariosRol"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($current_section == 'user_management'): ?>
            <h2 class="mb-4 text-light"><i class="fas fa-users-cog me-2"></i>Gestión de Usuarios</h2>
            <div class="card card-custom">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-dark-custom table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios_list as $usuario): ?>
                                <tr>
                                    <td><?php echo $usuario['id_usuario']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nombre_rol'] ?? 'Sin rol'); ?></td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?php echo ($usuario['activo'] == 'SI') ? 'success' : 'danger'; ?>">
                                            <?php echo ($usuario['activo'] == 'SI') ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-cyan me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal"
                                                data-userid="<?php echo $usuario['id_usuario']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                                data-rolid="<?php echo $usuario['id_rol'] ?? ''; ?>"
                                                data-estado="<?php echo $usuario['activo']; ?>">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <?php if ($usuario['activo'] == 'SI'): ?>
                                        <form method="POST" action="?section=user_management" style="display:inline;">
                                            <input type="hidden" name="id_usuario_desactivar" value="<?php echo $usuario['id_usuario']; ?>">
                                            <button type="submit" name="desactivar_usuario" class="btn btn-sm btn-outline-warning" onclick="return confirm('¿Está seguro de que desea desactivar a este usuario?');">
                                                <i class="fas fa-user-slash"></i> Desactivar
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($usuarios_list)): ?>
                                    <tr><td colspan="6" class="text-center">No se encontraron usuarios.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($current_section == 'equipment_status'): ?>
            <h2 class="mb-4 text-light"><i class="fas fa-tasks me-2"></i>Estado de Equipos</h2>
            <form method="GET" class="mb-3 search-form">
                <input type="hidden" name="section" value="equipment_status">
                <div class="input-group">
                    <input type="text" name="buscar_equipos" class="form-control form-control-dark" placeholder="Buscar equipo o usuario..." value="<?php echo htmlspecialchars($busqueda_equipos); ?>">
                    <button class="btn btn-cyan" type="submit"><i class="fas fa-search"></i> Buscar</button>
                </div>
            </form>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <h4 class="text-warning"><i class="fas fa-hourglass-half me-2"></i>Equipos Pendientes (<?php echo $resultadoPendientes ? $resultadoPendientes->num_rows : 0; ?>)</h4>
                    <div class="equipment-panel">
                        <?php if ($resultadoPendientes && $resultadoPendientes->num_rows > 0): ?>
                            <?php while($fila = $resultadoPendientes->fetch_assoc()): ?>
                                <div class="equipment-item">
                                    <strong>Equipo:</strong> <?php echo htmlspecialchars($fila['nombre_equipo']); ?><br>
                                    <strong>Usuario Asignado:</strong> <?php echo htmlspecialchars($fila['usuario_movimiento'] ?? 'N/A'); ?><br>
                                    <strong>ID Mov:</strong> <?php echo $fila['id_movimiento']; ?><br>
                                    <strong>Fecha Salida:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($fila['fecha_salida']))); ?><br>
                                    <strong>Obs. Inicial:</strong> <?php echo htmlspecialchars($fila['observacion'] ?: 'N/A'); ?><br>
                                    <form method="POST" action="?section=equipment_status" class="mt-2">
                                        <input type="hidden" name="id_movimiento_entregar" value="<?php echo $fila['id_movimiento']; ?>">
                                        <div class="mb-2">
                                            <label for="obs_entrega_<?php echo $fila['id_movimiento']; ?>" class="form-label form-label-sm visually-hidden">Observación de Entrega:</label>
                                            <textarea name="observacion_entrega" id="obs_entrega_<?php echo $fila['id_movimiento']; ?>" class="form-control form-control-sm form-control-dark" rows="2" placeholder="Observación de entrega..." required></textarea>
                                        </div>
                                        <button type="submit" name="marcar_entregado" class="btn btn-sm btn-success w-100"><i class="fas fa-check-circle me-1"></i>Marcar Entregado</button>
                                    </form>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center p-3 text-muted">No hay equipos pendientes.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6 mb-3">
                    <h4 class="text-success"><i class="fas fa-check-double me-2"></i>Equipos Entregados (<?php echo $resultadoEntregados ? $resultadoEntregados->num_rows : 0; ?>)</h4>
                    <div class="equipment-panel">
                        <?php if ($resultadoEntregados && $resultadoEntregados->num_rows > 0): ?>
                            <?php while($fila = $resultadoEntregados->fetch_assoc()): ?>
                                <div class="equipment-item border-success">
                                    <strong>Equipo:</strong> <?php echo htmlspecialchars($fila['nombre_equipo']); ?><br>
                                    <strong>Entregado a:</strong> <?php echo htmlspecialchars($fila['usuario_movimiento'] ?? 'N/A'); ?><br>
                                    <strong>ID Mov:</strong> <?php echo $fila['id_movimiento']; ?><br>
                                    <strong>Fecha Entrega:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($fila['fecha_actualizacion']))); ?><br>
                                    <strong>Obs. Entrega:</strong> <?php echo htmlspecialchars($fila['observacion'] ?: 'N/A'); ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center p-3 text-muted" style="color:white; !important">No hay equipos entregados.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        
        <?php elseif ($current_section == 'movement_history'): ?>
            <h2 class="mb-4 text-light"><i class="fas fa-history me-2"></i>Historial de Movimientos</h2>
            
            <div class="card card-custom mb-4">
                <div class="card-header">
                    <i class="fas fa-filter me-2"></i>Filtros de Búsqueda
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <input type="hidden" name="section" value="movement_history">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="filtro_fecha_inicio" class="form-label form-label-sm">Fecha Desde:</label>
                                <input type="date" class="form-control form-control-sm form-control-dark" id="filtro_fecha_inicio" name="filtro_fecha_inicio" value="<?php echo htmlspecialchars($filtro_fecha_inicio); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="filtro_fecha_fin" class="form-label form-label-sm">Fecha Hasta:</label>
                                <input type="date" class="form-control form-control-sm form-control-dark" id="filtro_fecha_fin" name="filtro_fecha_fin" value="<?php echo htmlspecialchars($filtro_fecha_fin); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="filtro_usuario" class="form-label form-label-sm">Usuario:</label>
                                <input type="text" class="form-control form-control-sm form-control-dark" id="filtro_usuario" name="filtro_usuario" style="color:white;" placeholder="Nombre de usuario" value="<?php echo htmlspecialchars($filtro_usuario); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="filtro_equipo" class="form-label form-label-sm">Equipo:</label>
                                <input type="text" class="form-control form-control-sm form-control-dark" id="filtro_equipo" name="filtro_equipo" placeholder="Nombre de equipo" value="<?php echo htmlspecialchars($filtro_equipo); ?>">
                            </div>
                            <div class="col-md-2 d-flex flex-column">
                                <button type="submit" class="btn btn-sm btn-cyan w-100 mb-1"><i class="fas fa-search me-1"></i>Filtrar</button>
                                <a href="?section=movement_history" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-times me-1"></i>Limpiar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-dark-custom table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID Mov.</th>
                                    <th>Equipo</th>
                                    <th>Usuario</th>
                                    <th class="text-center">Cant.</th>
                                    <th>Fecha Salida</th>
                                    <th>Fecha Entrega</th>
                                    <th>Estado Físico</th>
                                    <th style="min-width: 200px;">Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_historial && $result_historial->num_rows > 0): ?>
                                    <?php while ($row = $result_historial->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id_movimiento']; ?></td>
                                        <td class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($row['nombre_equipo']); ?>"><?php echo htmlspecialchars($row['nombre_equipo']); ?></td>
                                        <td class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($row['nombre_usuario']); ?>"><?php echo htmlspecialchars($row['nombre_usuario']); ?></td>
                                        <td class="text-center"><?php echo $row['cantidad']; ?></td>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['fecha_salida']))); ?></td>
                                        <td><?php echo $row['fecha_entrega'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($row['fecha_entrega']))) : '<span class="text-muted fst-italic">---</span>'; ?></td>
                                        <td>
                                            <?php 
                                            $estado_fisico = htmlspecialchars($row['estado_entrega'] ?? 'N/A');
                                            $badge_class = 'bg-secondary';
                                            if (strtolower($estado_fisico) == 'bueno') $badge_class = 'bg-success';
                                            if (strtolower($estado_fisico) == 'malo') $badge_class = 'bg-danger';
                                            if (strtolower($estado_fisico) == 'incompleto') $badge_class = 'bg-warning text-dark';
                                            ?>
                                            <span class="badge rounded-pill <?php echo $badge_class; ?>"><?php echo $estado_fisico; ?></span>
                                        </td>
                                        <td class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($row['observacion'] ?? '---'); ?>">
                                            <?php echo htmlspecialchars($row['observacion'] ?? '---'); ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center py-4">
                                        <i class="fas fa-info-circle fa-2x text-muted mb-2"></i><br>
                                        No se encontraron movimientos con los filtros aplicados.
                                    </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_historial_pages > 1): ?>
                    <nav aria-label="Paginación historial" class="mt-4">
                        <ul class="pagination justify-content-center flex-wrap">
                            <?php if ($page_historial > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?section=movement_history&page_historial=<?php echo $page_historial - 1; ?><?php echo $query_string_filtros; ?>" aria-label="Previous">
                                        <span aria-hidden="true">«</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php 
                            $rango_paginas = 2; 
                            $inicio_rango = max(1, $page_historial - $rango_paginas);
                            $fin_rango = min($total_historial_pages, $page_historial + $rango_paginas);

                            if ($inicio_rango > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?section=movement_history&page_historial=1' . $query_string_filtros . '">1</a></li>';
                                if ($inicio_rango > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $inicio_rango; $i <= $fin_rango; $i++): ?>
                            <li class="page-item <?php echo ($page_historial == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?section=movement_history&page_historial=<?php echo $i; ?><?php echo $query_string_filtros; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>

                            <?php
                            if ($fin_rango < $total_historial_pages) {
                                if ($fin_rango < $total_historial_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?section=movement_history&page_historial=' . $total_historial_pages . $query_string_filtros . '">' . $total_historial_pages . '</a></li>';
                            }
                            ?>

                            <?php if ($page_historial < $total_historial_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?section=movement_history&page_historial=<?php echo $page_historial + 1; ?><?php echo $query_string_filtros; ?>" aria-label="Next">
                                        <span aria-hidden="true">»</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                     <p class="text-center text-muted mt-3" style="color:white !important;">Mostrando <?php echo $result_historial ? $result_historial->num_rows : 0; ?> de <?php echo $total_historial_items; ?> registros.</p>
                </div>
            </div>
        <?php endif; // Cierre del if ($current_section == 'movement_history') ?>
        
    </div> <!-- Cierre de container-fluid -->
</div> <!-- Cierre de main-content -->

<!-- Modal Editar Usuario -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-dark">
      <form method="POST" action="?section=user_management"> <!-- La acción ya se maneja arriba con el isset -->
        <div class="modal-header modal-header-dark" style="background-color:red;">
          <h5 class="modal-title" id="editUserModalLabel"><i class="fas fa-user-edit me-2"></i>Editar Usuario: <span id="modalUserName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id_usuario_modal" id="id_usuario_modal">
            <div class="mb-3" >
                <label for="id_rol_modal" class="form-label">Rol:</label>
                <select name="id_rol_modal" id="id_rol_modal" class="form-select form-select-dark">
                    <option value="">-- Sin Rol --</option>
                    <?php 
                    if ($roles_result) {
                        $roles_result->data_seek(0); 
                        while ($rol = $roles_result->fetch_assoc()): ?>
                            <option value="<?php echo $rol['id_rol']; ?>"><?php echo htmlspecialchars($rol['nombre_rol']); ?></option>
                    <?php endwhile; 
                    }?>
                </select>
            </div>
            <div class="mb-3">
                <label for="estado_modal" class="form-label">Estado:</label>
                <select name="estado_modal" id="estado_modal" class="form-select form-select-dark" required>
                    <option value="SI">Activo</option>
                    <option value="NO">Inactivo</option>
                </select>
            </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" name="asignar_rol_estado" class="btn btn-cyan"><i class="fas fa-save me-1"></i>Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<footer class="footer">
        © 2025 Todos los derechos reservados | <a href="#">RNcorp</a> | <a href="#">Términos de uso</a>
    </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var userId = button.getAttribute('data-userid');
            var userName = button.getAttribute('data-nombre');
            var userRolId = button.getAttribute('data-rolid');
            var userEstado = button.getAttribute('data-estado');

            var modalTitle = editUserModal.querySelector('.modal-title #modalUserName');
            var inputUserId = editUserModal.querySelector('#id_usuario_modal');
            var selectRol = editUserModal.querySelector('#id_rol_modal');
            var selectEstado = editUserModal.querySelector('#estado_modal');

            modalTitle.textContent = userName;
            inputUserId.value = userId;
            selectRol.value = userRolId; 
            selectEstado.value = userEstado;
        });
    }

    <?php if ($current_section == 'dashboard'): ?>
    const ctxEquipos = document.getElementById('chartEquiposEstado');
    if (ctxEquipos) {
        new Chart(ctxEquipos, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($data_equipos_estado['labels']); ?>,
                datasets: [{
                    label: 'Cantidad',
                    data: <?php echo json_encode($data_equipos_estado['data']); ?>,
                    backgroundColor: [
                        'rgba(255, 159, 64, 0.7)', 
                        'rgba(75, 192, 192, 0.7)'  
                    ],
                    borderColor: [
                        'rgba(255, 159, 64, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#adb5bd', stepSize: 1 }, 
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    },
                    x: {
                        ticks: { color: '#adb5bd' },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    const ctxUsuarios = document.getElementById('chartUsuariosRol');
    if (ctxUsuarios) {
        new Chart(ctxUsuarios, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($chart_data_usuarios_rol['labels']); ?>,
                datasets: [{
                    label: 'Usuarios',
                    data: <?php echo json_encode($chart_data_usuarios_rol['data']); ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)', 
                        'rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 99, 132, 0.7)',  'rgba(255, 159, 64, 0.7)',
                        'rgba(100, 100, 100, 0.7)' 
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom', 
                        labels: { color: '#adb5bd' }
                    }
                }
            }
        });
    }
    <?php endif; ?>
});
</script>
</body>
</html>
<?php
if (isset($conexion) && $conexion instanceof mysqli) {
    $conexion->close();
}
?>