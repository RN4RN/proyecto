<?php
declare(strict_types=1);

// --- Autoloader (si usas namespaces y estructura de directorios) o requires ---
// spl_autoload_register(function ($class_name) {
//    include 'src/' . str_replace('\\', '/', $class_name) . '.php';
// });
require_once('../config/conexion1.php'); 
require_once('funciones_comunes.php');
require_once('MovimientoRepository.php'); // Asegúrate que la ruta sea correcta
// require_once('UserRepository.php'); // Eliminado según solicitud previa

// --- Configuración de Errores (Idealmente en un archivo de bootstrap global) ---
error_reporting(E_ALL);
ini_set('display_errors', '0'); // En producción SIEMPRE '0'
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-errors.log');

session_start();

// --- Constantes de Aplicación (Idealmente en un config.php) ---
define('UPLOADS_EVIDENCIAS_PATH_RELATIVE', '../uploads/evidencias_movimientos/');
define('UPLOADS_EVIDENCIAS_URL_BASE', '../uploads/evidencias_movimientos/');
define('ITEMS_PER_PAGE', 20);
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/nuevo/contraseña/mapaprincipal/movimientos');
define('LOGIN_URL', APP_URL . '/../../indexlogin.php');


// --- Autenticación ---
if (!isset($_SESSION['nombre'])) {
    redirect_to(LOGIN_URL);
}

// --- Inicializar Repositorios ---
$movimientoRepository = new MovimientoRepository($conn);

// --- Recoger y Validar Parámetros de Filtro y Paginación ---
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * ITEMS_PER_PAGE;

$filters = [
    'fecha_desde' => isset($_GET['fecha_desde']) ? sanitize_input($_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? sanitize_input($_GET['fecha_hasta']) : '',
    'id_usuario' => isset($_GET['id_usuario']) ? (int)sanitize_input($_GET['id_usuario']) : 0,
    'tipo_movimiento' => isset($_GET['tipo_movimiento']) ? sanitize_input($_GET['tipo_movimiento']) : '',
    'tipo_equipo' => isset($_GET['tipo_equipo']) ? sanitize_input($_GET['tipo_equipo']) : '',
];

// --- Obtener Datos ---
$movimientos = $movimientoRepository->getFilteredMovimientos($filters, ITEMS_PER_PAGE, $offset);
$total_items = $movimientoRepository->countFilteredMovimientos($filters);
$total_pages = ($total_items > 0) ? (int)ceil($total_items / ITEMS_PER_PAGE) : 1;

// --- Obtener usuarios para el filtro (directamente) ---
$usuarios_para_filtro = [];
$sql_usuarios = "SELECT id_usuario, nombre FROM usuarios ORDER BY nombre ASC";
$result_usuarios = $conn->query($sql_usuarios);
if ($result_usuarios && $result_usuarios->num_rows > 0) {
    $usuarios_para_filtro = $result_usuarios->fetch_all(MYSQLI_ASSOC);
} elseif ($result_usuarios === false) {
    error_log("Error en " . basename(__FILE__) . ": No se pudo obtener la lista de usuarios para el filtro. Error DB: " . $conn->error);
}

// ---- Manejo de Solicitud AJAX para Detalles del Movimiento ----
if (isset($_GET['action']) && $_GET['action'] === 'get_movimiento_details' && isset($_GET['id_movimiento'])) {
    header('Content-Type: application/json');
    $id_movimiento_detalle = (int)$_GET['id_movimiento'];
    $response = ['success' => false, 'data' => null, 'error' => ''];

    $detalle_data = $movimientoRepository->getMovimientoDetailsById($id_movimiento_detalle);

    if ($detalle_data) {
        $response['success'] = true;
        $detalle_data['evidencias_urls'] = [];
        if (!empty($detalle_data['archivos_evidencia'])) {
            $archivos_json = json_decode($detalle_data['archivos_evidencia'], true); // Renombrar para evitar confusión con $archivos variable loop
            if (is_array($archivos_json)) {
                foreach ($archivos_json as $archivo_guardado) { // Usar diferente nombre de variable en loop
                    // Asume que $archivo_guardado es el nombre del archivo o una ruta relativa de la cual basename es seguro.
                    // UPLOADS_EVIDENCIAS_URL_BASE debe ser la ruta web completa HASTA la carpeta de evidencias.
                    $detalle_data['evidencias_urls'][] = rtrim(UPLOADS_EVIDENCIAS_URL_BASE, '/') . '/' . rawurlencode(basename($archivo_guardado));
                }
            }
        }
        $response['data'] = $detalle_data;
    } else {
        $response['error'] = 'Movimiento no encontrado.';
    }
    echo json_encode($response);
    exit;
}

// ---- Construcción de la URL base para la paginación (manteniendo filtros) ----
$base_pagination_url_params = array_merge($_GET);
unset($base_pagination_url_params['page']);
$base_pagination_url_query = http_build_query($base_pagination_url_params);
$base_pagination_url = basename(__FILE__) . '?' . $base_pagination_url_query;
$base_pagination_url .= (!empty($base_pagination_url_query) ? '&' : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Completo de Movimientos - Gestión Avanzada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../volver.css">
    <style>
        body { background-color: #4d4d4d; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container-fluid { padding-top: 1.5rem; padding-bottom: 1.5rem;}
        .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1); }
        .card-header { background-color: #212529; color: white; font-weight: 500; }
        .table th { background-color: #f8f9fa; }
        .table td { vertical-align: middle; }
        .actions-col { width: 80px; text-align: center; }
        .filter-form .form-label { font-weight: 500; }
        .pagination .page-item.active .page-link { background-color: #007bff; border-color: #007bff; }
        .pagination .page-link { color: #007bff; }
        .pagination .page-link:hover { color: #0056b3; }
        
        .barra {
            background-color: #343a40;
            padding: 0.75rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            display: flex; align-items: center; justify-content: space-between;
            color:white;
        }
        .barra .app-title { font-size: 1.5rem; font-weight: bold; margin: 0; }
        .barra .button-volver { margin-right: 1rem; }

        #modalDetalles .modal-body .badge { font-size: 0.9em; }
        #modalDetalles .evidencia-thumbnail {
            width: 100px; height: 100px; object-fit: cover; margin: 0.25rem;
            border: 2px solid #dee2e6; border-radius: 0.25rem; cursor: pointer;
            transition: transform 0.2s;
        }
        #modalDetalles .evidencia-thumbnail:hover { transform: scale(1.1); }
        #modalImagenEvidencia .modal-body img,
        #modalImagenEvidencia .modal-body iframe { max-width: 100%; max-height: 80vh; display: block; margin: 0 auto; }

        @media print {
            body {
                font-size: 10pt !important; /* Reducir para que quepa más */
                background-color: white !important;
                margin: 0 !important; /* Reset body margins */
                padding: 0 !important; /* Reset body paddings */
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }
            
            /* Ocultar todo por defecto */
            body * {
                visibility: hidden !important;
            }

            /* Mostrar solo la sección de impresión y sus hijos */
            #printSection,
            #printSection * {
                visibility: visible !important;
            }

            #printSection {
                position: static !important; /* Anular cualquier posicionamiento absoluto previo */
                display: block !important;
                width: 100% !important; /* Ocupar el ancho completo del área de impresión */
                margin: 0 auto !important; 
                padding: 10mm !important; /* Simula márgenes de página */
                box-sizing: border-box !important; 
                background-color: white !important;
            }
            
            #printSection .print-header {
                text-align: center !important;
                margin-bottom: 8mm !important;
            }
            #printSection .print-header h4 {
                font-size: 14pt !important;
                margin-bottom: 0.5rem !important;
            }
            #printSection .print-header p {
                font-size: 9pt !important;
                margin-bottom: 1rem !important;
            }
             #printSection .print-header strong {
                font-weight: bold !important;
            }

            #printSection #printTable {
                width: 100% !important;
                border-collapse: collapse !important;
                table-layout: auto !important; /* auto para ajustar, fixed si necesitas control preciso */
                font-size: 8pt !important; /* Muy importante para que quepa la tabla */
                margin-top: 0 !important;
                margin-bottom: 5mm !important;
            }

            #printSection #printTable th,
            #printSection #printTable td {
                border: 1px solid #6c757d !important;
                padding: 2px 4px !important; /* Reducir padding considerablemente */
                text-align: left !important;
                color: black !important;
                word-wrap: break-word !important; /* Forzar salto de palabra */
                overflow-wrap: break-word !important;
            }
            #printSection #printTable th {
                background-color: #f0f0f0 !important; /* Un gris muy claro */
                font-weight: bold !important;
            }

            /* Stripe para tabla, pero muy suave */
            #printSection #printTable tbody tr:nth-of-type(odd) td {
                 background-color: #f9f9f9 !important; 
            }
            
            /* Repetir encabezados de tabla en cada página */
            #printSection #printTable thead { display: table-header-group !important; }
            #printSection #printTable tbody { display: table-row-group !important; }
            /* Intentar evitar que las filas se partan entre páginas */
            #printSection #printTable tr { page-break-inside: avoid !important; }

            #printSection .print-footer {
                font-size: 8pt !important;
                text-align: center !important;
                margin-top: 8mm !important;
                border-top: 1px solid #ccc !important;
                padding-top: 4mm !important;
            }

            /* Para Badges, si se quieren con algo de estilo */
            #printSection .badge {
                display: inline-block !important;
                padding: .2em .4em !important;
                font-size: 85% !important; /* Un poco más grande que la tabla para legibilidad */
                font-weight: normal !important; /* No tan grueso */
                line-height: 1 !important;
                color: #000 !important; /* Texto negro */
                text-align: center !important;
                white-space: nowrap !important;
                vertical-align: baseline !important;
                border-radius: .25rem !important;
                border: 1px solid #ddd !important; /* Un borde ligero */
                background-color: transparent !important; /* Quitar color de fondo */
            }
        }
    </style>
</head>
<body>

<div class="barra no-print">
    <a href="index.php" class="button-volver">
      <button class="button" aria-label="Volver a gestión de movimientos">
          <div class="button-box">
            <span class="button-elem"><svg viewBox="0 0 46 40"><path d="M46 20.038c0-.7-.3-1.5-.8-2.1l-16-17c-1.1-1-3.2-1.4-4.4-.3-1.2 1.1-1.2 3.3 0 4.4l11.3 11.9H3c-1.7 0-3 1.3-3 3s1.3 3 3 3h33.1l-11.3 11.9c-1 1-1.2 3.3 0 4.4 1.2 1.1 3.3.8 4.4-.3l16-17c.5-.5.8-1.1.8-1.9z"></path></svg></span>
            <span class="button-elem"><svg viewBox="0 0 46 40"><path d="M46 20.038c0-.7-.3-1.5-.8-2.1l-16-17c-1.1-1-3.2-1.4-4.4-.3-1.2 1.1-1.2 3.3 0 4.4l11.3 11.9H3c-1.7 0-3 1.3-3 3s1.3 3 3 3h33.1l-11.3 11.9c-1 1-1.2 3.3 0 4.4 1.2 1.1 3.3.8 4.4-.3l16-17c.5-.5.8-1.1.8-1.9z"></path></svg></span>
          </div>
      </button>
    </a>
    <h1 class="app-title">Historial Completo de Movimientos</h1>
    <div style="width: 60px;"></div>
</div>


<div class="container-fluid no-print">
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-filter-circle-fill"></i> Filtros de Búsqueda</span>
                <button class="btn btn-light btn-sm" id="btnPrint" title="Imprimir vista actual de la tabla"><i class="bi bi-printer-fill"></i> Imprimir</button>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="<?= basename(__FILE__) ?>" class="filter-form">
                <div class="row g-3">
                    <div class="col-md-6 col-lg-3">
                        <label for="fecha_desde" class="form-label">Fecha Desde:</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_desde" name="fecha_desde" value="<?= htmlspecialchars($filters['fecha_desde']) ?>">
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="fecha_hasta" class="form-label">Fecha Hasta:</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_hasta" name="fecha_hasta" value="<?= htmlspecialchars($filters['fecha_hasta']) ?>">
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="id_usuario" class="form-label">Operador:</label>
                        <select class="form-select form-select-sm" id="id_usuario" name="id_usuario">
                            <option value="0">Todos</option>
                            <?php if (!empty($usuarios_para_filtro)): ?>
                                <?php foreach ($usuarios_para_filtro as $usr): ?>
                                    <option value="<?= (int)$usr['id_usuario'] ?>" <?= ($filters['id_usuario'] == $usr['id_usuario']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($usr['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="0" disabled>No hay operadores</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="tipo_movimiento" class="form-label">Tipo Mov.:</label>
                        <select class="form-select form-select-sm" id="tipo_movimiento" name="tipo_movimiento">
                            <option value="">Todos</option>
                            <option value="Entrega" <?= ($filters['tipo_movimiento'] == 'Entrega') ? 'selected' : '' ?>>Entrega</option>
                            <option value="Devolución" <?= ($filters['tipo_movimiento'] == 'Devolución') ? 'selected' : '' ?>>Devolución</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <label for="tipo_equipo" class="form-label">Tipo Equipo:</label>
                        <select class="form-select form-select-sm" id="tipo_equipo" name="tipo_equipo">
                            <option value="">Todos</option>
                            <option value="Permanente" <?= ($filters['tipo_equipo'] == 'Permanente') ? 'selected' : '' ?>>Permanente</option>
                            <option value="Consumible" <?= ($filters['tipo_equipo'] == 'Consumible') ? 'selected' : '' ?>>Consumible</option>
                        </select>
                    </div>
                     <div class="col-md-12 col-lg-auto align-self-end text-lg-end mt-3 mt-lg-0">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Filtrar</button>
                        <a href="<?= basename(__FILE__) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eraser"></i> Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card" id="resultsCard">
        <div class="card-header">
           <i class="bi bi-list-ul"></i> Resultados de Movimientos <span class="badge bg-secondary ms-2">Total: <?= $total_items ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Equipo</th>
                            <th>Tipo Eq.</th>
                            <th>Operador</th>
                            <th>Tipo Mov.</th>
                            <th class="text-center">Cant.</th>
                            <th>F. Salida</th>
                            <th>F. Ent/Dev.</th>
                            <th>Est. Inicial</th>
                            <th>Observación</th>
                            <th class="actions-col">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($movimientos)): ?>
                            <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$mov['id_movimiento']) ?></td>
                                    <td><?= htmlspecialchars($mov['nombre_equipo_tabla'] ?? 'N/A') ?></td>
                                    <td><span class="badge <?= $mov['tipo_equipo'] === 'Permanente' ? 'bg-primary' : ($mov['tipo_equipo'] === 'Consumible' ? 'bg-success' : 'bg-secondary') ?>"><?= htmlspecialchars($mov['tipo_equipo'] ?? 'N/A') ?></span></td>
                                    <td><?= htmlspecialchars($mov['nombre_usuario_tabla'] ?? 'N/A') ?></td>
                                    <td><span class="badge <?= $mov['tipo_movimiento'] === 'Entrega' ? 'bg-warning text-dark' : 'bg-info text-dark' ?>"><?= htmlspecialchars($mov['tipo_movimiento'] ?? 'N/A') ?></span></td>
                                    <td class="text-center"><?= htmlspecialchars((string)$mov['cantidad']) ?></td>
                                    <td><?= htmlspecialchars(format_datetime_user($mov['fecha_salida'], 'd/m/y H:i')) ?></td>
                                    <td><?= htmlspecialchars(format_datetime_user($mov['fecha_entrega'], 'd/m/y H:i')) ?></td>
                                    <td><?= htmlspecialchars($mov['estado_entrega_inicial'] ?: 'N/A') ?></td>
                                    <td title="<?= htmlspecialchars($mov['observacion'] ?? '') ?>"><?= htmlspecialchars(mb_substr($mov['observacion'] ?? '', 0, 25) . (mb_strlen($mov['observacion'] ?? '') > 25 ? '...' : '')) ?></td>
                                    <td class="actions-col">
                                        <button class="btn btn-outline-primary btn-sm btn-ver-detalles" title="Ver Detalles del Movimiento"
                                                data-id="<?= $mov['id_movimiento'] ?>">
                                            <i class="bi bi-eye-fill"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center fst-italic py-4">No se encontraron movimientos con los filtros aplicados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-light">
            <nav aria-label="Paginación de movimientos">
                <ul class="pagination justify-content-center pagination-sm mb-0">
                    <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base_pagination_url ?>page=<?= $current_page - 1 ?>" aria-label="Anterior">
                            <span aria-hidden="true">«</span>
                        </a>
                    </li>
                    <?php
                    $num_links = 2; 
                    $start = max(1, $current_page - $num_links);
                    $end = min($total_pages, $current_page + $num_links);

                    if ($start > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= $base_pagination_url ?>page=1">1</a></li>
                        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
                    endif;

                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $base_pagination_url ?>page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor;

                    if ($end < $total_pages):
                        if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="<?= $base_pagination_url ?>page=<?= $total_pages ?>"><?= $total_pages ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $base_pagination_url ?>page=<?= $current_page + 1 ?>" aria-label="Siguiente">
                            <span aria-hidden="true">»</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div> 
</div>


<!-- Modal para Detalles del Movimiento -->
<div class="modal fade no-print" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDetallesLabel">Detalles del Movimiento ID: <span id="detalleMovId"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="loadingDetalles" class="text-center p-5"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"><span class="visually-hidden">Cargando...</span></div></div>
                <div id="contenidoDetalles" style="display:none;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6><i class="bi bi-box-seam"></i> Información del Equipo</h6>
                            <p class="mb-1"><strong>Equipo:</strong> <span id="detalleNombreEquipo"></span></p>
                            <p class="mb-1"><strong>Serie:</strong> <span class="badge bg-light text-dark" id="detalleSerieEquipo"></span></p>
                            <p class="mb-1"><strong>Estación:</strong> <span id="detalleEstacionEquipo"></span></p>
                            <p class="mb-1"><strong>Tipo Equipo:</strong> <span class="badge" id="detalleTipoEquipoBadge"></span> <span id="detalleTipoEquipo"></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-person-badge"></i> Operador Involucrado</h6>
                            <p class="mb-1"><strong>Nombre:</strong> <span id="detalleNombreUsuario"></span></p>
                        </div>
                    </div>
                     <hr class="my-2">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6><i class="bi bi-arrows-move"></i> Detalles del Movimiento</h6>
                            <p class="mb-1"><strong>Tipo:</strong> <span class="badge" id="detalleTipoMovimientoBadge"></span> <span id="detalleTipoMovimiento"></span></p>
                            <p class="mb-1"><strong>Cantidad:</strong> <span id="detalleCantidad"></span></p>
                            <p class="mb-1"><strong>Fecha Salida (del inventario):</strong> <span id="detalleFechaSalida"></span></p>
                            <p class="mb-1"><strong>Fecha Entrega/Devolución (al/del operador):</strong> <span id="detalleFechaEntrega"></span></p>
                            <p class="mb-1"><strong>Estado Físico Inicial (al entregar):</strong> <span id="detalleEstadoEntregaInicial"></span></p>
                        </div>
                        <div class="col-md-6">
                             <h6><i class="bi bi-person-fill-gear"></i> Registro del Sistema</h6>
                             <p class="mb-1"><strong>Registrado por:</strong> <span id="detalleRegistradoPor"></span> (ID Usuario: <span id="detalleRegistradoPorId"></span>)</p>
                             <p class="mb-1"><strong>Fecha de registro:</strong> <span id="detalleFechaCreacionMovimiento"></span></p>
                        </div>
                    </div>
                    <hr class="my-2">
                    <h6><i class="bi bi-card-text"></i> Observaciones:</h6>
                    <p class="text-muted" style="white-space: pre-wrap;"><span id="detalleObservacion"></span></p>
                    
                    <hr class="my-2">
                    <h6><i class="bi bi-paperclip"></i> Evidencias Adjuntas:</h6>
                    <div id="galeriaEvidencias" class="d-flex flex-wrap align-items-start">
                    </div>
                    <p id="noEvidencias" class="fst-italic text-muted" style="display:none;">No hay evidencias adjuntas para este movimiento.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver imagen/PDF de evidencia ampliada -->
<div class="modal fade no-print" id="modalImagenEvidencia" tabindex="-1" aria-labelledby="modalImagenEvidenciaLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalImagenEvidenciaLabel">Vista Previa de Evidencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-0">
        <img src="" id="imagenAmpliadaEvidencia" alt="Evidencia Ampliada" class="img-fluid" style="display:none; max-height: 80vh;">
        <iframe src="" id="pdfEvidencia" style="width:100%; height:80vh; border:none; display:none;"></iframe>
      </div>
    </div>
  </div>
</div>

<!-- Contenedor para la Impresión -->
<div id="printSection" class="d-none"> <!-- d-none será anulado por @media print -->
    <div class="print-header"> <!-- Quitado text-center, @media print lo gestiona -->
        <h4>Reporte de Historial de Movimientos</h4>
        <p>
            Filtros Aplicados: 
            <span class="print-only-inline">Fecha Desde: <strong id="printFilterFechaDesde">N/A</strong>, </span>
            <span class="print-only-inline">Fecha Hasta: <strong id="printFilterFechaHasta">N/A</strong>, </span>
            <span class="print-only-inline">Operador: <strong id="printFilterUsuario">Todos</strong>, </span>
            <span class="print-only-inline">Tipo Mov.: <strong id="printFilterTipoMov">Todos</strong>, </span>
            <span class="print-only-inline">Tipo Eq.: <strong id="printFilterTipoEq">Todos</strong></span>
        </p>
    </div>
    <!-- El div table-responsive no es necesario aquí para impresión -->
    <table class="table table-bordered table-striped table-sm" id="printTable">
        <thead> <!-- El encabezado es estático y ya omite "Acciones" -->
             <tr>
                <th>ID</th><th>Equipo</th><th>T.Eq.</th><th>Operador</th>
                <th>T.Mov.</th><th>Cant.</th><th>F.Salida</th><th>F.Ent/Dev.</th>
                <th>Est.Ini.</th><th>Obs.</th>
            </tr>
        </thead>
        <tbody>
            <!-- El contenido de la tabla visible se clonará aquí por JS -->
        </tbody>
    </table>
    <div class="print-footer">
        Reporte generado el: <span id="printDate"></span> por <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario Desconocido') ?>.
    </div>
</div>


<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalDetallesEl = document.getElementById('modalDetalles');
    const modalImagenEvidenciaEl = document.getElementById('modalImagenEvidencia');
    const modalDetalles = modalDetallesEl ? new bootstrap.Modal(modalDetallesEl) : null;
    const modalImagenEvidencia = modalImagenEvidenciaEl ? new bootstrap.Modal(modalImagenEvidenciaEl) : null;

    const detallesFields = {
        MovId: document.getElementById('detalleMovId'),
        NombreEquipo: document.getElementById('detalleNombreEquipo'),
        SerieEquipo: document.getElementById('detalleSerieEquipo'),
        EstacionEquipo: document.getElementById('detalleEstacionEquipo'),
        TipoEquipo: document.getElementById('detalleTipoEquipo'),
        TipoEquipoBadge: document.getElementById('detalleTipoEquipoBadge'),
        NombreUsuario: document.getElementById('detalleNombreUsuario'),
        TipoMovimiento: document.getElementById('detalleTipoMovimiento'),
        TipoMovimientoBadge: document.getElementById('detalleTipoMovimientoBadge'),
        Cantidad: document.getElementById('detalleCantidad'),
        FechaSalida: document.getElementById('detalleFechaSalida'),
        FechaEntrega: document.getElementById('detalleFechaEntrega'),
        EstadoEntregaInicial: document.getElementById('detalleEstadoEntregaInicial'),
        Observacion: document.getElementById('detalleObservacion'),
        RegistradoPor: document.getElementById('detalleRegistradoPor'),
        RegistradoPorId: document.getElementById('detalleRegistradoPorId'),
        FechaCreacionMovimiento: document.getElementById('detalleFechaCreacionMovimiento'),
        GaleriaEvidencias: document.getElementById('galeriaEvidencias'),
        NoEvidencias: document.getElementById('noEvidencias'),
        ContenidoDetalles: document.getElementById('contenidoDetalles'),
        LoadingDetalles: document.getElementById('loadingDetalles')
    };

    document.querySelectorAll('.btn-ver-detalles').forEach(button => {
        button.addEventListener('click', function () {
            if (!modalDetalles) return;
            const movimientoId = this.dataset.id;
            
            detallesFields.MovId.textContent = movimientoId;
            detallesFields.ContenidoDetalles.style.display = 'none';
            detallesFields.LoadingDetalles.style.display = 'block';
            detallesFields.GaleriaEvidencias.innerHTML = '';
            detallesFields.NoEvidencias.style.display = 'none';
            modalDetalles.show();

            fetch(`<?= basename(__FILE__) ?>?action=get_movimiento_details&id_movimiento=${movimientoId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }
                    return response.json();
                })
                .then(result => {
                    detallesFields.LoadingDetalles.style.display = 'none';
                    if (result.success && result.data) {
                        const data = result.data;
                        detallesFields.NombreEquipo.textContent = data.nombre_equipo || 'N/A';
                        detallesFields.SerieEquipo.textContent = data.serie || 'N/A';
                        detallesFields.EstacionEquipo.textContent = data.estacion || 'N/A';
                        detallesFields.TipoEquipo.textContent = data.tipo_equipo || 'N/A';
                        detallesFields.TipoEquipoBadge.className = `badge ${data.tipo_equipo === 'Permanente' ? 'bg-primary' : (data.tipo_equipo === 'Consumible' ? 'bg-success' : 'bg-secondary')}`;

                        detallesFields.NombreUsuario.textContent = data.nombre_usuario || 'N/A';
                        
                        detallesFields.TipoMovimiento.textContent = data.tipo_movimiento || 'N/A';
                        detallesFields.TipoMovimientoBadge.className = `badge ${data.tipo_movimiento === 'Entrega' ? 'bg-warning text-dark' : 'bg-info text-dark'}`;

                        detallesFields.Cantidad.textContent = data.cantidad || 'N/A';
                        detallesFields.FechaSalida.textContent = formatJSDateTime(data.fecha_salida);
                        detallesFields.FechaEntrega.textContent = formatJSDateTime(data.fecha_entrega);
                        detallesFields.EstadoEntregaInicial.textContent = data.estado_entrega_inicial || 'N/A';
                        detallesFields.Observacion.textContent = data.observacion || 'Sin observaciones.';

                        detallesFields.RegistradoPor.textContent = data.nombre_registrado_por || 'N/A';
                        detallesFields.RegistradoPorId.textContent = data.registrado_por_id_usuario || 'N/A';
                        detallesFields.FechaCreacionMovimiento.textContent = formatJSDateTime(data.fecha_creacion_movimiento || data.fecha_salida);

                        if (data.evidencias_urls && data.evidencias_urls.length > 0) {
                            data.evidencias_urls.forEach(function(url) {
                                const decodedUrl = decodeURIComponent(url);
                                const filename = decodedUrl.substring(decodedUrl.lastIndexOf('/') + 1);
                                const evidenciaEl = document.createElement('div');
                                evidenciaEl.className = 'text-center m-1';
                                evidenciaEl.style.cursor = 'pointer';
                                evidenciaEl.dataset.url = url;
                                
                                if (filename.toLowerCase().endsWith('.pdf')) {
                                    evidenciaEl.dataset.type = 'pdf';
                                    evidenciaEl.innerHTML = `<i class="bi bi-file-earmark-pdf-fill text-danger" style="font-size: 3rem;" title="${filename}"></i><br><small class="text-muted">${filename.substring(0,15)}...</small>`;
                                } else {
                                    evidenciaEl.dataset.type = 'image';
                                    evidenciaEl.innerHTML = `<img src="${url}" alt="Evidencia: ${filename}" class="evidencia-thumbnail" title="${filename}">`;
                                }
                                detallesFields.GaleriaEvidencias.appendChild(evidenciaEl);
                            });
                        } else {
                            detallesFields.NoEvidencias.style.display = 'block';
                        }
                        detallesFields.ContenidoDetalles.style.display = 'block';
                    } else {
                        showErrorInModal(`Error: ${result.error || 'No se pudieron cargar los detalles.'}`);
                    }
                })
                .catch(error => {
                    console.error("Error fetching details:", error);
                    showErrorInModal(`Error de conexión o procesamiento: ${error.message}`);
                });
        });
    });

    function showErrorInModal(message) {
        detallesFields.LoadingDetalles.style.display = 'none';
        detallesFields.ContenidoDetalles.innerHTML = `<p class="text-danger p-3">${message}</p>`;
        detallesFields.ContenidoDetalles.style.display = 'block';
    }

    detallesFields.GaleriaEvidencias.addEventListener('click', function (e) {
        const target = e.target.closest('[data-url]');
        if (!target || !modalImagenEvidencia) return;
        
        e.preventDefault();
        const url = target.dataset.url;
        const type = target.dataset.type;
        const decodedUrl = decodeURIComponent(url);
        const filename = decodedUrl.substring(decodedUrl.lastIndexOf('/') + 1);
        
        document.getElementById('modalImagenEvidenciaLabel').textContent = 'Vista Previa: ' + filename;
        const imgEl = document.getElementById('imagenAmpliadaEvidencia');
        const pdfEl = document.getElementById('pdfEvidencia');

        imgEl.style.display = 'none'; imgEl.src = '';
        pdfEl.style.display = 'none'; pdfEl.src = '';

        if (type === 'pdf') {
            pdfEl.style.display = 'block'; 
            pdfEl.src = url; 
        } else {
            imgEl.style.display = 'block'; 
            imgEl.src = url;
        }
        modalImagenEvidencia.show();
    });

    const btnPrint = document.getElementById('btnPrint');
    if (btnPrint) {
        btnPrint.addEventListener('click', function () {
            document.getElementById('printFilterFechaDesde').textContent = document.getElementById('fecha_desde').value || 'N/A';
            document.getElementById('printFilterFechaHasta').textContent = document.getElementById('fecha_hasta').value || 'N/A';
            const userSelect = document.getElementById('id_usuario');
            document.getElementById('printFilterUsuario').textContent = userSelect.value !== "0" ? userSelect.options[userSelect.selectedIndex].text : 'Todos';
            const tipoMovSelect = document.getElementById('tipo_movimiento');
            document.getElementById('printFilterTipoMov').textContent = tipoMovSelect.value ? tipoMovSelect.options[tipoMovSelect.selectedIndex].text : 'Todos';
            const tipoEqSelect = document.getElementById('tipo_equipo');
            document.getElementById('printFilterTipoEq').textContent = tipoEqSelect.value ? tipoEqSelect.options[tipoEqSelect.selectedIndex].text : 'Todos';

            const sourceTable = document.querySelector('#resultsCard .table-responsive table');
            const printTableBody = document.querySelector('#printTable tbody'); 

            if (sourceTable && sourceTable.tBodies[0] && printTableBody) {
                const sourceTableBody = sourceTable.tBodies[0];
                printTableBody.innerHTML = ''; // Limpiar contenido anterior

                const sourceRows = sourceTableBody.rows; // HTMLCollection de TRs
                for (let i = 0; i < sourceRows.length; i++) {
                    const originalRow = sourceRows[i];
                    const newRow = printTableBody.insertRow();
                    
                    // Copiar N-1 celdas (todas excepto la última, que se asume es "Acciones")
                    const cellsToCopyCount = originalRow.cells.length > 0 ? originalRow.cells.length - 1 : 0;
                    
                    for (let j = 0; j < cellsToCopyCount; j++) {
                        const originalCell = originalRow.cells[j];
                        if(originalCell) { // Comprobar que la celda original existe
                            const newCell = newRow.insertCell();
                            newCell.innerHTML = originalCell.innerHTML; // Copia el contenido
                            // newCell.className = originalCell.className; // Opcional: copiar clases si es relevante para estilos de impresión
                        }
                    }
                }
            } else {
                console.error('Error: Tabla fuente (#resultsCard table) o tabla de destino (#printTable tbody) no encontrada.');
                alert("Error al preparar la tabla para impresión. Intente de nuevo.");
                return;
            }

            document.getElementById('printDate').textContent = new Date().toLocaleString('es-ES', { dateStyle: 'long', timeStyle: 'short' });
            
            setTimeout(function() { // Pequeña demora para asegurar renderizado
                window.print();
            }, 150);
        });
    }

    function formatJSDateTime(dateTimeString) {
        if (!dateTimeString || dateTimeString === '0000-00-00 00:00:00') return '---';
        try {
            const date = new Date(dateTimeString);
            if (isNaN(date.getTime())) return dateTimeString;
            return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false });
        } catch (e) {
            console.warn("Error formatting date:", dateTimeString, e);
            return dateTimeString; 
        }
    }
});
</script>

</body>
</html>