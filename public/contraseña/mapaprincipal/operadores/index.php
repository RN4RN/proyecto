<?php
// 1. Iniciar sesión ANTES de cualquier output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Asumimos que este archivo define $conexion
require '../config/conexion.php';

if (!$conexion) {
    error_log("Error fatal: No se pudo establecer la conexión a la base de datos.");
    die("Error de conexión. Por favor, inténtelo más tarde.");
}

// 2. Verificar sesión
if (!isset($_SESSION['nombre'])) {
    header("Location: http://localhost/nuevo/contraseña/indexlogin.php"); // Asegúrate que esta URL es correcta
    exit();
}

// --- Obtener lista de TODOS los usuarios para las tarjetas ---
$allUsers = [];
$sqlAllUsers = "SELECT usuarios.id_usuario, usuarios.nombre, usuarios.dni, usuarios.email, usuarios.telefono,
                       usuarios.fecha_registro, usuarios.activo, usuarios.descripcion,
                       roles.nombre_rol
                FROM usuarios
                LEFT JOIN roles ON usuarios.id_rol = roles.id_rol
                ORDER BY usuarios.nombre ASC";

$resultAllUsers = $conexion->query($sqlAllUsers);

if (!$resultAllUsers) {
    error_log("Error en la consulta de usuarios: " . $conexion->error);
} else {
    while ($row = $resultAllUsers->fetch_assoc()) {
        $row['perfil'] = 'default_avatar.png'; // Forzar imagen por defecto
        $allUsers[] = $row;
    }
}

// --- OBTENER TODOS LOS MOVIMIENTOS PARA JS ---
$allMovements = [];
$sqlAllMovements = "SELECT id_movimiento, id_equipo, id_usuario, cantidad, fecha_salida, fecha_entrega, 
                           estado_entrega, observacion, nombre_equipo, nombre_usuario 
                    FROM movimientos ORDER BY fecha_salida DESC";
$resultAllMovementsQuery = $conexion->query($sqlAllMovements);
if ($resultAllMovementsQuery) {
    while ($mov_row = $resultAllMovementsQuery->fetch_assoc()) {
        $allMovements[] = $mov_row;
    }
} else {
    error_log("Error al cargar movimientos: " . $conexion->error);
}


// --- Registrar Acceso ---
if (isset($_SESSION['nombre'])) {
    $seccion = 'gestion_usuarios';
    $nombreUsuario = $_SESSION['nombre'];
    $fechaActual = date('Y-m-d H:i:s');
    $idUsuarioActual = $_SESSION['id_usuario'] ?? null;

    $sqlAccess = "INSERT INTO registro_acceso (seccion, nombre_usuario, id_usuario, fecha_ultimo_acceso)
                  VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE
                  fecha_ultimo_acceso = VALUES(fecha_ultimo_acceso),
                  id_usuario = VALUES(id_usuario)"; 

    $stmtAccess = $conexion->prepare($sqlAccess);
    if ($stmtAccess) {
        $stmtAccess->bind_param("ssis", $seccion, $nombreUsuario, $idUsuarioActual, $fechaActual);
        if (!$stmtAccess->execute()) {
            error_log("Error ejecutando registro de acceso: " . $stmtAccess->error);
        }
        $stmtAccess->close();
    } else {
        error_log("Error preparando registro de acceso: " . $conexion->error);
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESTACIONES - Gestión de Usuarios</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --text-color: #212529;
            --top-bar-height: 60px;
            --border-radius: 0.3rem;
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Roboto', sans-serif; background-color: var(--light-gray);
            color: var(--text-color); line-height: 1.6; display: flex;
            flex-direction: column; min-height: 100vh;
        }
        .page-wrapper { display: flex; flex-direction: column; min-height: 100vh; padding-top: var(--top-bar-height); }
        
        .top-bar {
            background-color: var(--dark-gray); color: white; padding: 0 1rem;
            height: var(--top-bar-height); display: flex; align-items: center;
            position: fixed; top: 0; left: 0; right: 0; z-index: 1030; box-shadow: var(--box-shadow);
        }

        .search {
            display: flex; align-items: center; background-color: #555;
            border-radius: var(--border-radius); padding: 0.3rem 0.5rem;
            max-width: 400px; 
            margin-left: 1rem; 
        }
        .search input { background: transparent; border: none; color: white; outline: none; flex-grow: 1; padding: 0.25rem; }
        .search input::placeholder { color: #ccc; }
        .search button { background: none; border: none; color: white; cursor: pointer; padding: 0.25rem; }
        
        .main-container {
            display: flex; flex-grow: 1; padding: 1.5rem;
            gap: 1.5rem;
        }
        @media (max-width: 991px) {
            .main-container {
                flex-direction: column;
            }
        }

        .left-panel { flex: 3; background-color: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); overflow-y: auto;}
        .right-panel { flex: 2; background-color: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); overflow-y: auto; position: sticky; top: calc(var(--top-bar-height) + 1.5rem); height: calc(100vh - var(--top-bar-height) - 3rem); }
         @media (max-width: 991px) {
            .right-panel { position: static; height: auto; margin-top: 1.5rem; }
        }

        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .card {
            background-color: #fff; border: 1px solid #e0e0e0; border-radius: var(--border-radius);
            overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; flex-direction: column;
        }
        .card.selected { border-left: 5px solid var(--primary-color); }
        .card-image { position: relative; text-align:center; padding-top:10px; background-color: #f5f5f5;}
        .card-image .user-image {
            width: 100px; height: 100px; border-radius: 50%; object-fit: cover;
            margin: 0 auto; display: block; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .user-status {
            position: absolute; bottom: 10px; right: 10px; padding: 0.2em 0.5em;
            border-radius: 10px; font-size: 0.8em; color: white;
        }
        .user-status.active { background-color: #28a745; }
        .user-status.inactive { background-color: #dc3545; }
        .card-content { padding: 1rem; flex-grow: 1; }
        .card-title { font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--primary-color); }
        .card-body p { font-size: 0.9rem; margin-bottom: 0.3rem; color: #555; }
        .card-body p i { margin-right: 8px; color: var(--secondary-color); width:16px; text-align:center; }
        .card-button {
            background-color: var(--primary-color); color: white; border: none;
            padding: 0.75rem; width: 100%; cursor: pointer; font-weight: 500;
            transition: background-color 0.2s;
        }
        .card-button:hover { background-color: #0056b3; }
        .placeholder-content {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; color: #aaa; height: 100%; font-size: 1.2rem;
        }
        .placeholder-content i { font-size: 3rem; margin-bottom: 1rem; }
        .user-details .user-header { display: flex; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
        .user-details .user-header img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-right: 1rem; border: 3px solid var(--light-gray); }
        .user-details .user-header h3 { font-size: 1.5rem; margin: 0 0 0.25rem 0; color: var(--dark-gray); }
        .user-details .user-header .user-role { font-size: 0.9rem; color: var(--secondary-color); display: block; margin-bottom: 0.25rem; }
        .user-details .user-header .user-status { padding: 0.2em 0.6em; font-size: 0.8em; border-radius: 10px; color: white; }
        .user-info .info-group { margin-bottom: 1.5rem; }
        .user-info .info-group h4 { font-size: 1.1rem; color: var(--primary-color); margin-bottom: 0.75rem; border-bottom: 2px solid var(--primary-color); padding-bottom: 0.25rem; display: inline-block; }
        .user-info .info-group h4 i { margin-right: 8px; }
        .user-info p { font-size: 0.95rem; margin-bottom: 0.5rem; line-height: 1.5; }
        .user-info p i { margin-right: 8px; color: var(--secondary-color); width:16px; text-align:center; }
        .user-info p strong { color: var(--dark-gray); }
        #detail-user-description { background-color: #f9f9f9; padding: 10px; border-radius: var(--border-radius); border: 1px solid #eee; min-height: 60px;}
        .user-actions { display: flex; gap: 0.5rem; margin-top: 1.5rem; margin-bottom: 1.5rem; padding-bottom:1.5rem; border-bottom:1px solid #eee;}
        .user-actions .button {
            padding: 0.6rem 1rem; border: none; border-radius: var(--border-radius); color: white;
            cursor: pointer; font-size: 0.9rem; transition: background-color 0.2s;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .user-actions .edit-button { background-color: var(--primary-color); }
        .user-actions .edit-button:hover { background-color: #0056b3; }
        .user-actions .delete-button { background-color: #dc3545; }
        .user-actions .delete-button:hover { background-color: #c82333; }
        .user-activity-section { margin-top: 1.5rem; }
        .user-activity-section h4 { margin-bottom: 1rem; color: var(--primary-color); font-size:1.1rem;}
        .user-activity-section h4 i { margin-right: 8px; }
        .activity-buttons button {
            margin-right: 10px; margin-bottom: 10px; background-color: #6c757d; color: white;
            border: none; padding: 0.6rem 1rem; border-radius: var(--border-radius); cursor: pointer; font-size: 0.9em;
        }
        .activity-buttons button:hover { background-color: #5a6268; }
        
        /* Contenedor para el Chart para controlar su tamaño */
        .chart-container {
            position: relative; /* Necesario para Chart.js responsive */
            margin-top: 20px;
            padding: 10px;
            background: #fff;
            border-radius: var(--border-radius);
            border: 1px solid #eee;
            height: 300px; /* Altura fija o max-height */
            width: 100%;   /* Ancho completo del contenedor padre */
        }
        #userActivityChart { /* El canvas mismo no necesita dimensiones aquí si maintainAspectRatio es false */
            display: block; /* Chart.js puede requerir esto */
        }

        #movements-container h5 { margin-bottom:0.5rem; font-size:1rem; color: var(--dark-gray); }
        .movements-list {
            list-style: none; padding: 0; max-height: 250px; overflow-y: auto; border: 1px solid #eee;
            padding:10px; border-radius: var(--border-radius); margin-top: 10px; background-color: #f9f9f9;
        }
        .movements-list li { padding: 10px; border-bottom: 1px dashed #e0e0e0; font-size: 0.9em; }
        .movements-list li:last-child { border-bottom: none; }
        .movements-list .mov-equipo { font-weight: bold; color: var(--primary-color); }
        .movements-list .mov-details { font-size: 0.85em; color: #555; display:block; margin-top: 4px;}
        .footer {
            background-color: var(--dark-gray); color: #ccc; padding: 1.5rem 1rem;
            text-align: center; font-size: 0.9em; margin-top: auto;
        }
        .footer-content { max-width: 960px; margin: 0 auto; }
        .footer-links a { color: #fff; text-decoration: none; margin: 0 0.5rem; }
        .footer-links a:hover { text-decoration: underline; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
        .no-results { text-align: center; padding: 2rem; color: #777; font-size: 1.1rem; }
        .no-results i { font-size: 2.5rem; margin-bottom: 0.5rem; display: block; }
    </style>
</head>
<body>

<div class="page-wrapper">
    <header class="top-bar">
        <div class="search">
            <label for="search-input" class="sr-only">Buscar usuarios</label>
            <input id="search-input" placeholder="Buscar usuarios por nombre o DNI..." type="search" aria-label="Campo de búsqueda">
            <button type="button" aria-label="Buscar"><i class="fas fa-search"></i></button>
        </div>
    </header>

    <main class="main-container" id="main-content">
        <section class="left-panel" aria-labelledby="users-heading">
            <h1 id="users-heading" class="sr-only">Lista de Usuarios</h1>
            <?php if (!empty($allUsers)): ?>
                <div class="cards-grid">
                    <?php foreach ($allUsers as $row): ?>
                        <?php
                            $userId = $row['id_usuario'] ?? 'unknown';
                            $userNombre = $row['nombre'] ?? 'Sin nombre';
                            $userDni = $row['dni'] ?? 'N/A';
                            $userEstado = $row['activo'] ?? 'Desconocido';
                            $userRol = $row['nombre_rol'] ?? 'N/A';
                            $userTelefono = $row['telefono'] ?? 'N/A';
                            $userFechaRegistro = $row['fecha_registro'] ? date("d/m/Y", strtotime($row['fecha_registro'])) : 'N/A';
                        ?>
                        <article class="card" id="user-<?= htmlspecialchars($userId) ?>" data-user-id="<?= htmlspecialchars($userId) ?>">
                            <div class="card-image">
                                <img src="../uploads/default_avatar.png"
                                     alt="Foto de <?= htmlspecialchars($userNombre) ?>"
                                     onerror="this.onerror=null; this.src='default_avatar.png';"
                                     class="user-image">
                                <span class="user-status <?= strtolower($userEstado) === 'si' ? 'active' : 'inactive' ?>">
                                    <?= htmlspecialchars(strtolower($userEstado) === 'si' ? 'Activo' : 'Inactivo') ?>
                                </span>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($userNombre) ?></h3>
                                <div class="card-body">
                                    <p><i class="fas fa-id-card"></i> <strong>DNI:</strong> <?= htmlspecialchars($userDni) ?></p>
                                    <p><i class="fas fa-user-tag"></i> <strong>Rol:</strong> <?= htmlspecialchars($userRol) ?></p>
                                    <p><i class="fas fa-phone"></i> <strong>Teléfono:</strong> <?= htmlspecialchars($userTelefono) ?></p>
                                    <p><i class="fas fa-calendar-day"></i> <strong>Registro:</strong> <?= htmlspecialchars($userFechaRegistro) ?></p>
                                </div>
                            </div>
                            <button class="card-button" 
                                    aria-label="Ver detalles de <?= htmlspecialchars($userNombre) ?>"
                                    data-userid="<?= htmlspecialchars($userId) ?>">
                                Ver Detalles
                            </button>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($resultAllUsers): ?>
                <div class="no-results placeholder-content">
                    <i class="fas fa-users-slash"></i><p>No hay usuarios registrados actualmente.</p>
                </div>
            <?php else: ?>
                 <div class="no-results placeholder-content">
                    <i class="fas fa-exclamation-triangle"></i><p>Error al cargar los usuarios. Intente de nuevo más tarde.</p>
                </div>
            <?php endif; ?>
            <div id="no-search-results" class="no-results placeholder-content" style="display:none;">
                 <i class="fas fa-search-minus"></i><p>No se encontraron usuarios que coincidan con la búsqueda.</p>
            </div>
        </section>

        <section class="right-panel" aria-labelledby="user-details-heading" role="region">
            <h2 id="user-details-heading" class="sr-only">Detalles del Usuario</h2>
            <div id="user-details-placeholder" class="placeholder-content">
                <i class="fas fa-user-circle"></i><p>Selecciona un usuario de la lista para ver sus detalles y actividad.</p>
            </div>
            <div id="user-details-content" class="user-details" style="display: none;" aria-live="polite">
                <div class="user-header">
                    <img id="detail-user-image" src="../uploads/default_avatar.png" alt="Foto del usuario"
                         onerror="this.onerror=null; this.src='../uploads/default_avatar.png';">
                    <div>
                        <h3 id="detail-user-name"></h3>
                        <span id="detail-user-role" class="user-role"></span>
                        <span id="detail-user-status" class="user-status"></span>
                    </div>
                </div>
                <div class="user-info">
                    <div class="info-group">
                        <h4><i class="fas fa-info-circle"></i> Información Básica</h4>
                        <p><i class="fas fa-id-card"></i> <strong>DNI:</strong> <span id="detail-user-dni"></span></p>
                        <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <span id="detail-user-email"></span></p>
                        <p><i class="fas fa-phone"></i> <strong>Teléfono:</strong> <span id="detail-user-phone"></span></p>
                        <p><i class="fas fa-calendar-plus"></i> <strong>Fecha Registro:</strong> <span id="detail-user-register"></span></p>
                    </div>
                    <div class="info-group">
                        <h4><i class="fas fa-file-alt"></i> Descripción</h4>
                        <p id="detail-user-description"></p>
                    </div>
                </div>
               
                <div class="user-activity-section">
                    <h4><i class="fas fa-chart-line"></i> Actividad del Usuario</h4>
                    <div class="activity-buttons">
                        <button id="btn-view-movements" class="button"><i class="fas fa-exchange-alt"></i> Ver Movimientos de Equipos</button>
                    </div>
                    <div id="movements-container" style="display:none;">
                        <h5>Movimientos Recientes de Equipos:</h5>
                        <ul id="movements-list-ul" class="movements-list"></ul>
                    </div>
                    <!-- Contenedor del gráfico con estilo -->
                    <div class="chart-container" id="activity-chart-wrapper" style="display:none;">
                        <canvas id="userActivityChart" aria-label="Gráfico de actividad del usuario"></canvas>
                    </div>
                    <p id="no-activity-message" style="display:none; text-align:center; margin-top:1rem; color:#777;">No hay actividad de movimientos para mostrar para este usuario.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <p>© <?= date('Y') ?> ESTACIONES - Todos los derechos reservados.</p>
            <div class="footer-links">
                <a href=".../terminosdeuso.php">Términos de Uso</a> | 
                <a href="https://www.facebook.com/share/15yZx44QFM/" target="_blank" rel="noopener noreferrer">Contacto</a>
            </div>
        </div>
    </footer>
</div>

<script>
const usersData = {
    <?php
    if (!empty($allUsers)) {
        $outputJs = [];
        foreach ($allUsers as $row) {
            $userData = [
                'id_usuario' => $row['id_usuario'] ?? null,
                'nombre' => $row['nombre'] ?? 'N/A',
                'dni' => $row['dni'] ?? 'N/A',
                'email' => $row['email'] ?? 'N/A',
                'telefono' => $row['telefono'] ?? 'N/A',
                'perfil' => 'default_avatar.png',
                'rol' => $row['nombre_rol'] ?? 'N/A',
                'estado' => $row['activo'] ?? 'Desconocido',
                'estado_display' => (strtolower($row['activo'] ?? '') === 'si') ? 'Activo' : 'Inactivo',
                'fecha_registro' => $row['fecha_registro'] ? date("d/m/Y", strtotime($row['fecha_registro'])) : 'N/A',
                'descripcion' => $row['descripcion'] ?? 'No hay descripción disponible.'
            ];
            $outputJs[] = json_encode((string)$userData['id_usuario']) . ': ' . json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        echo implode(',', $outputJs);
    }
    ?>
};

const allMovementsData = <?= json_encode($allMovements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || [];
let activityChartInstance = null;

function displayUserMovements(userId, userName) {
    const movementsListEl = document.getElementById('movements-list-ul');
    const movementsContainerEl = document.getElementById('movements-container');
    const chartWrapperEl = document.getElementById('activity-chart-wrapper'); // Contenedor del canvas
    const noActivityMessage = document.getElementById('no-activity-message');
    
    movementsListEl.innerHTML = ''; 
    noActivityMessage.style.display = 'none';
    chartWrapperEl.style.display = 'none'; // Ocultar el wrapper del chart al inicio

    const userMovements = allMovementsData.filter(mov => mov.id_usuario == userId);

    if (activityChartInstance) { // Destruir instancia previa SIEMPRE antes de decidir si mostrar una nueva
        activityChartInstance.destroy();
        activityChartInstance = null;
    }

    if (userMovements && userMovements.length > 0) {
        userMovements.forEach(mov => {
            const listItem = document.createElement('li');
            const fechaSalida = mov.fecha_salida ? new Date(mov.fecha_salida).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) : 'Fecha N/A';
            const estadoEntrega = mov.estado_entrega || 'Salida';
            const nombreEquipo = mov.nombre_equipo || 'Equipo Desconocido';
            const cantidad = mov.cantidad || 0;
            const observacion = mov.observacion || 'Ninguna';
            listItem.innerHTML = `
                <strong class="mov-equipo">${nombreEquipo} (Cantidad: ${cantidad})</strong>
                <span class="mov-details">Fecha: ${fechaSalida} - Tipo: ${estadoEntrega}</span>
                <span class="mov-details">Observación: ${observacion}</span>
            `;
            movementsListEl.appendChild(listItem);
        });
        movementsContainerEl.style.display = 'block';
        renderUserActivityChart(userMovements, userName); // Esto mostrará chartWrapperEl si hay datos
    } else {
        movementsListEl.innerHTML = '<li>No hay movimientos de equipos registrados para este usuario.</li>';
        movementsContainerEl.style.display = 'block';
        noActivityMessage.style.display = 'block';
        // chartWrapperEl ya está oculto
    }
}

function renderUserActivityChart(userMovements, userName) {
    const chartWrapperEl = document.getElementById('activity-chart-wrapper');
    const chartCanvas = document.getElementById('userActivityChart');
    const ctx = chartCanvas.getContext('2d');
    const noActivityMessage = document.getElementById('no-activity-message');

    // La destrucción ya se hace en displayUserMovements ANTES de llamar a esta función.
    // if (activityChartInstance) { activityChartInstance.destroy(); activityChartInstance = null; }

    if (!userMovements || userMovements.length === 0) {
        chartWrapperEl.style.display = 'none'; 
        noActivityMessage.style.display = 'block'; 
        return;
    }
    
    noActivityMessage.style.display = 'none'; 
    chartWrapperEl.style.display = 'block'; // Mostrar el contenedor del gráfico

    const equipmentCounts = userMovements.reduce((acc, mov) => {
        const equipmentName = mov.nombre_equipo || 'Desconocido';
        acc[equipmentName] = (acc[equipmentName] || 0) + (parseInt(mov.cantidad, 10) || 0);
        return acc;
    }, {});

    const labels = Object.keys(equipmentCounts);
    const dataValues = Object.values(equipmentCounts);

    if (labels.length === 0) { 
        chartWrapperEl.style.display = 'none'; 
        noActivityMessage.style.display = 'block'; 
        return; 
    }

    const chartData = {
        labels: labels,
        datasets: [{
            label: `Cantidad de Equipos Movidos`, data: dataValues,
            backgroundColor: 'rgba(0, 123, 255, 0.5)', borderColor: 'rgba(0, 123, 255, 1)', borderWidth: 1
        }]
    };

    activityChartInstance = new Chart(ctx, {
        type: 'bar', 
        data: chartData,
        options: {
            responsive: true, 
            maintainAspectRatio: false, // CLAVE: Permitir que el CSS del contenedor controle el tamaño
            indexAxis: 'y',
            scales: { 
                x: { beginAtZero: true, title: { display: true, text: 'Cantidad Total Movida' } }, 
                y: { title: { display: true, text: 'Equipo' } } 
            },
            plugins: { 
                legend: { display: false }, 
                title: { display: true, text: `Movimientos de Equipos por ${userName}` } 
            }
        }
    });
}

function mostrarDetallesUsuario(userId) {
    const user = usersData[String(userId)];
    const placeholderEl = document.getElementById('user-details-placeholder');
    const contentEl = document.getElementById('user-details-content');
    const movementsContainerEl = document.getElementById('movements-container');
    const chartWrapperEl = document.getElementById('activity-chart-wrapper'); // Contenedor
    const noActivityMessageEl = document.getElementById('no-activity-message');

    if (!user) {
        console.error("Usuario no encontrado para ID:", userId);
        contentEl.style.display = 'none'; placeholderEl.style.display = 'flex';
        placeholderEl.innerHTML = `<i class="fas fa-exclamation-triangle"></i><p>Error al cargar datos.</p>`;
        if (activityChartInstance) { activityChartInstance.destroy(); activityChartInstance = null; }
        movementsContainerEl.style.display = 'none'; 
        chartWrapperEl.style.display = 'none'; 
        noActivityMessageEl.style.display = 'none'; return;
    }

    placeholderEl.style.display = 'none'; contentEl.style.display = 'block';
    // Ocultar/Resetear actividad al cambiar de usuario
    movementsContainerEl.style.display = 'none'; 
    chartWrapperEl.style.display = 'none';
    noActivityMessageEl.style.display = 'none';
    if (activityChartInstance) { activityChartInstance.destroy(); activityChartInstance = null;}
    document.getElementById('movements-list-ul').innerHTML = '';


    document.getElementById('detail-user-name').textContent = user.nombre;
    document.getElementById('detail-user-role').textContent = user.rol;
    const statusEl = document.getElementById('detail-user-status');
    statusEl.textContent = user.estado_display;
    statusEl.className = `user-status ${user.estado && user.estado.toLowerCase() === 'si' ? 'active' : 'inactive'}`;
    document.getElementById('detail-user-dni').textContent = user.dni;
    document.getElementById('detail-user-email').textContent = user.email;
    document.getElementById('detail-user-phone').textContent = user.telefono;
    document.getElementById('detail-user-register').textContent = user.fecha_registro;
    document.getElementById('detail-user-description').textContent = user.descripcion;

    const userImgEl = document.getElementById('detail-user-image');
    userImgEl.src = 'default_avatar.png';
    userImgEl.alt = `Foto de ${user.nombre}`;

    document.getElementById('edit-user-btn').onclick = () => { window.location.href = `edit_user.php?id=${user.id_usuario}`; };
    document.getElementById('delete-user-btn').onclick = () => {
        if (confirm(`¿Eliminar a ${user.nombre}?`)) { console.log(`Eliminar usuario ID: ${user.id_usuario}`); /* Lógica de eliminación aquí */ }
    };
    document.getElementById('btn-view-movements').onclick = () => { displayUserMovements(user.id_usuario, user.nombre); };
    
    document.querySelectorAll('.card').forEach(card => card.classList.remove('selected'));
    const selectedCard = document.getElementById(`user-${user.id_usuario}`);
    if (selectedCard) selectedCard.classList.add('selected');
    if (window.innerWidth < 992) { contentEl.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
}

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search-input');
    const cardsGrid = document.querySelector('.cards-grid');
    const noSearchResultsMsg = document.getElementById('no-search-results');
    if (searchInput && cardsGrid) {
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase().trim();
            const cards = cardsGrid.querySelectorAll('.card');
            let MhayResultados = false;
            cards.forEach(card => {
                const userNameElement = card.querySelector('.card-title');
                const userDniElement = card.querySelector('.card-body p:nth-of-type(1)');
                const userName = userNameElement ? userNameElement.textContent.toLowerCase() : '';
                const userDniText = userDniElement ? userDniElement.textContent.toLowerCase() : '';
                const isMatch = userName.includes(searchTerm) || userDniText.includes(searchTerm);
                card.style.display = isMatch ? '' : 'none';
                if (isMatch) MhayResultados = true;
            });
            if (noSearchResultsMsg) {
                noSearchResultsMsg.style.display = (MhayResultados || searchTerm === '') ? 'none' : 'flex';
            }
        });
    }

    if (cardsGrid) {
        cardsGrid.addEventListener('click', function(event) {
            const targetButton = event.target.closest('.card-button');
            if (targetButton) {
                const userId = targetButton.dataset.userid;
                if (userId) { mostrarDetallesUsuario(userId); }
            }
        });
    }

    document.addEventListener('error', (e) => {
        if (e.target.tagName === 'IMG' && e.target.src.includes('default_avatar.png')) {
            console.error("Error cargando la imagen por defecto 'default_avatar.png'. Verifica la ruta y el archivo.");
            e.target.alt = "Error al cargar imagen por defecto";
        } else if (e.target.tagName === 'IMG') {
             if (e.target.src !== '../uploads/default_avatar.png') {
                 e.target.src = '../uploads/default_avatar.png';
            }
        }
        e.target.onerror = null;
    }, true);
});
</script>

<?php
if (isset($conexion) && $conexion instanceof mysqli) {
    $conexion->close();
}
?>
</body>
</html>