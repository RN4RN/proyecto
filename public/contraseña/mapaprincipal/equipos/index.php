<?php 
require_once '../config/conexion.php';
session_start();

if (!isset($_SESSION['nombre'])) {
    header('Location: http://localhost/nuevo/contrase%C3%B1a/indexlogin.php');
    exit();
}

$nombre_sesion = $_SESSION['nombre'];

// Obtener usuario actual
if ($stmt = $conexion->prepare("SELECT nombre, foto_rostro FROM usuarios WHERE nombre = ?")) {
    $stmt->bind_param("s", $nombre_sesion);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} else {
    die("Error en la consulta: " . $conexion->error);
}

if (!$user) {
    die("Usuario no encontrado.");
}

$imagen = $user['foto_rostro']
    ? '../uploads/perfiles/' . $user['foto_rostro']
    : 'assets/img/avatar-default.png';

// Obtener datos del usuario logueado
$sql_usuario = "SELECT * FROM usuarios WHERE nombre = ?";
$stmt_usuario = $conexion->prepare($sql_usuario);
$stmt_usuario->bind_param("s", $nombre_sesion);
$stmt_usuario->execute();
$resultado_usuario = $stmt_usuario->get_result();
$usuario = $resultado_usuario->fetch_assoc();
$stmt_usuario->close();

// FUNCIONES AUXILIARES
function obtenerNombreUsuario($conexion, $id_usuario) {
    if (!$id_usuario) return 'Desconocido';
    
    $sql = "SELECT nombre FROM usuarios WHERE id_usuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc()['nombre'] : 'Desconocido';
}

function registrarCambio($conexion, $tipo, $detalle, $id_equipo = null, $id_usuario = null) {
    if ($tipo == 'edicion' && $id_equipo) {
        $sql = "UPDATE equipos SET 
                editado_por = ?,
                fecha_edicion = CURRENT_TIMESTAMP
                WHERE id_equipo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $id_usuario, $id_equipo);
        return $stmt->execute();
    }
    return true;
}

// PROCESAR FORMULARIO POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_equipo = $_POST['id_equipo'] ?? null;
    $nombre_equipo = $_POST['nombre_equipo'];
    $descripcion = $_POST['descripcion'];
    $tipo_equipo = $_POST['tipo_equipo'];
    $cantidad_total = $_POST['cantidad_total'];
    $cantidad_disponible = $_POST['cantidad_disponible'];
    $serie = $_POST['serie'];
    $estado = $_POST['estado'];
    $estacion = $_POST['estacion'];
    $marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $tip_equip = $_POST['tip_equip'];

    if ($id_equipo) {
        // Actualizar equipo
        $sql = "UPDATE equipos SET 
                nombre_equipo = ?, 
                descripcion = ?, 
                tipo_equipo = ?, 
                cantidad_total = ?, 
                cantidad_disponible = ?, 
                serie = ?, 
                estado = ?, 
                estacion = ?, 
                marca = ?, 
                modelo = ?, 
                tip_equip = ?,
                editado_por = ?
                WHERE id_equipo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssiissssssii", $nombre_equipo, $descripcion, $tipo_equipo, $cantidad_total, 
                          $cantidad_disponible, $serie, $estado, $estacion, $marca, $modelo, $tip_equip, 
                          $usuario['id_usuario'], $id_equipo);
    } else {
        // Insertar nuevo equipo
        $sql = "INSERT INTO equipos 
                (nombre_equipo, descripcion, tipo_equipo, cantidad_total, cantidad_disponible, serie, estado, estacion, marca, modelo, tip_equip, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssiissssssi", $nombre_equipo, $descripcion, $tipo_equipo, $cantidad_total, 
                          $cantidad_disponible, $serie, $estado, $estacion, $marca, $modelo, $tip_equip, 
                          $usuario['id_usuario']);
    }

    if ($stmt->execute()) {
        $id_nuevo = $id_equipo ?: $conexion->insert_id;

        // Registrar historial
        $detalle = $id_equipo
            ? "Se editó el equipo: $nombre_equipo (ID: $id_equipo)"
            : "Se añadió el equipo: $nombre_equipo (ID: $id_nuevo)";
        registrarCambio($conexion, $id_equipo ? 'edicion' : 'creacion', $detalle, $id_equipo, $usuario['id_usuario']);
        
        exit();
    } else {
        die("Error al guardar: " . $stmt->error);
    }
}

// PROCESAR DUPLICACIÓN
if (isset($_GET['duplicar'])) {
    $id_original = $_GET['duplicar'];

    $sql = "SELECT * FROM equipos WHERE id_equipo = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_original);
    $stmt->execute();
    $equipo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($equipo) {
        echo json_encode([
            'success' => true,
            'data' => [
                'nombre_equipo' => $equipo['nombre_equipo'] . ' (Copia)',
                'descripcion' => $equipo['descripcion'],
                'tipo_equipo' => $equipo['tipo_equipo'],
                'cantidad_total' => $equipo['cantidad_total'],
                'cantidad_disponible' => $equipo['cantidad_disponible'],
                'serie' => '',
                'estado' => $equipo['estado'],
                'estacion' => $equipo['estacion'],
                'marca' => $equipo['marca'],
                'modelo' => $equipo['modelo'],
                'tip_equip' => $equipo['tip_equip']
            ]
        ]);
        exit();
    }
}

// PROCESAR ELIMINACIÓN
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];

    $sql = "SELECT nombre_equipo FROM equipos WHERE id_equipo = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_eliminar);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $equipo = $result->fetch_assoc();
        $nombre_equipo = $equipo['nombre_equipo'];

        $sql = "DELETE FROM equipos WHERE id_equipo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_eliminar);

        if ($stmt->execute()) {
            $detalle = "Se eliminó el equipo: $nombre_equipo (ID: $id_eliminar)";
            registrarCambio($conexion, 'eliminacion', $detalle);
            echo json_encode(['success' => true, 'message' => 'Equipo eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el equipo']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Equipo no encontrado']);
    }
    exit();
}

// OBTENER LISTA DE EQUIPOS
$sql = "SELECT id_equipo, nombre_equipo FROM equipos ORDER BY nombre_equipo";
$result = $conexion->query($sql);
$equipos = $result->fetch_all(MYSQLI_ASSOC);

// CONSULTA COMPLETA CON NOMBRES
$sql = "SELECT e.*, 
        uc.nombre AS creador_nombre,
        ue.nombre AS editor_nombre,
        DATE_FORMAT(e.fecha_creacion, '%d/%m/%Y %H:%i') AS fecha_creacion_formatted,
        DATE_FORMAT(e.fecha_edicion, '%d/%m/%Y %H:%i') AS fecha_edicion_formatted
        FROM equipos e
        LEFT JOIN usuarios uc ON e.creado_por = uc.id_usuario
        LEFT JOIN usuarios ue ON e.editado_por = ue.id_usuario";
$result = $conexion->query($sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="../telecomunicaciones0.ico" />
  <title>Document</title>
  <link rel="stylesheet" href="../frontend/stylesEquip.css">
    <link rel="stylesheet" href="../frontend/stylesEquip2.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../volver.css">
  <style>
        #barraEstado {
  transition: width 0.3s ease-in-out, background-color 0.3s ease-in-out; /* Animación suave */
}
  .perfile {
      position: fixed;
      margin-top: 5px;
      margin-left: 96%;
      width: 45px;  /* Tamaño del círculo */
      height: 45px; 
      border-radius: 50%;  /* Hace que sea un círculo */
      overflow: hidden;  /* Evita que la imagen se salga */
      border: 3px solid white;  /* Borde elegante */
      box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.5); /* Sombra para resaltar */
      z-index: 3;
  }
  
  .perfile img {
      width:100%;
      height: 100%;
      object-fit: cover; /* Ajusta la imagen sin deformarla */
      z-index: 2;
    
  }
  
   
  
    </style> 
   
</head>
<body>
<div class="barra">
      <!-- From Uiverse.io by xopc333 --> 
  <a href="http://localhost/nuevo/contraseña/mapaprincipal/index.php"><button class="button"> 
  <div class="button-box">
    <span class="button-elem">
      <svg viewBox="0 0 46 40" xmlns="http://www.w3.org/2000/svg">
        <path
          d="M46 20.038c0-.7-.3-1.5-.8-2.1l-16-17c-1.1-1-3.2-1.4-4.4-.3-1.2 1.1-1.2 3.3 0 4.4l11.3 11.9H3c-1.7 0-3 1.3-3 3s1.3 3 3 3h33.1l-11.3 11.9c-1 1-1.2 3.3 0 4.4 1.2 1.1 3.3.8 4.4-.3l16-17c.5-.5.8-1.1.8-1.9z"
        ></path>
      </svg>
    </span>
    
    <span class="button-elem">
      <svg viewBox="0 0 46 40">
        <path
          d="M46 20.038c0-.7-.3-1.5-.8-2.1l-16-17c-1.1-1-3.2-1.4-4.4-.3-1.2 1.1-1.2 3.3 0 4.4l11.3 11.9H3c-1.7 0-3 1.3-3 3s1.3 3 3 3h33.1l-11.3 11.9c-1 1-1.2 3.3 0 4.4 1.2 1.1 3.3.8 4.4-.3l16-17c.5-.5.8-1.1.8-1.9z"
        ></path>
      </svg>
    </span>
  </div>
</button></a> 
<input type="text" id="buscarEquipo" placeholder="Buscar por el nombre del equipo..."> 
    
<div class="perfile">
 <img src="<?= htmlspecialchars($imagen) ?>" alt="Foto de perfil">
</div>    
</div>

<div class="totalmente">

    <div class="contenedor" style="margin-top:10px;">
        <h2>Añadir-Duplicar-Editar-Equipos</h2>

    <form id="formEquipo">
        <input type="hidden" name="id_equipo" id="id_equipo">
        <input type="text" name="nombre_equipo" id="nombre_equipo" placeholder="Nombre del equipo" required>
        <textarea name="descripcion" id="descripcion" placeholder="Descripción" rows="3" required></textarea>
        <input type="text" name="tipo_equipo" id="tipo_equipo" placeholder="Tipo de equipo" required>
        <input type="number" name="cantidad_total" id="cantidad_total" placeholder="Cantidad total" required min="0">
        <input type="number" name="cantidad_disponible" id="cantidad_disponible" placeholder="Cantidad disponible" required min="0">
        <input type="text" name="serie" id="serie" placeholder="Serie" required>
        <select name="estado" id="estado" required>
        <option value="">Estado</option>
        <option value="Bueno">Bueno</option>
        <option value="Aceptable">Aceptable</option>
        <option value="Malo">Malo</option>
        </select>
        <input type="text" name="estacion" id="estacion" placeholder="Estación" required>
        <input type="text" name="marca" id="marca" placeholder="Marca" required>
        <input type="text" name="modelo" id="modelo" placeholder="Modelo" required>
        <input type="text" name="tip_equip" id="tip_equip" placeholder="Tipo equipo (abreviado)" required>

          <div class="botones">
            <button type="submit" class="btn-guardar" id="btnGuardar">Guardar</button>
            <button type="button" class="btn-duplicar" onclick="duplicarEquipo()">Duplicar</button>
            <button type="button" class="btn-nuevo" onclick="limpiarFormulario()">Limpiar</button>
          </div>
    </form>
    </div>

    <div class="contenedor_equipos">
    <div class="antenas">
    
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card">
                <div class="card-image">
                    <img src="https://i.postimg.cc/j5gCb8QK/depositphotos-18849767-stock-photo-radio-telescope.png" alt="Equipo">
                </div>
                <div class="card-title"><?= htmlspecialchars($row['nombre_equipo']) ?></div>
                <div class="card-body">
                    <p><strong>Estación:</strong> <?= $row['estacion'] ?><br>
                    <strong>Serie:</strong> <?= $row['serie'] ?><br>
                    <strong>Marca:</strong> <?= $row['marca'] ?><br>
                    <strong>Disponible:</strong> <?= $row['cantidad_disponible'] ?><br>
                    <strong>Total:</strong> <?= $row['cantidad_total'] ?><br>
                 
                </div>
                <center>
        <button class="boton ver-btn" data-nombre="<?= htmlspecialchars($row['nombre_equipo']) ?>" 
        data-nombre="<?= htmlspecialchars($row['nombre_equipo']) ?>" 
        data-estado="<?= htmlspecialchars($row['estado']) ?>"
        data-serie="<?= htmlspecialchars($row['serie']) ?>"
        data-estacion="<?= htmlspecialchars($row['estacion']) ?>"
        data-marca="<?= htmlspecialchars($row['marca']) ?>"
        data-modelo="<?= htmlspecialchars($row['modelo']) ?>"
        data-tipo_equipo="<?= htmlspecialchars($row['tipo_equipo']) ?>" 
        data-cantidad_disponible="<?= htmlspecialchars($row['cantidad_disponible']) ?>" 
        data-cantidad_total="<?= htmlspecialchars($row['cantidad_total']) ?>"
        data-tip_equip="<?= htmlspecialchars($row['tip_equip']) ?>"
        data-descripcion="<?= htmlspecialchars($row['descripcion']) ?>"
        data-creador="<?= htmlspecialchars($row['creador_nombre'] ?: 'Admin') ?>"
        data-editor="<?= htmlspecialchars($row['editor_nombre'] ?: 'No editado') ?>"
          data-fecha_edicion="<?= htmlspecialchars($row['fecha_edicion_formatted']) ?>"
          data-fecha_creacion="<?= htmlspecialchars($row['fecha_creacion_formatted']) ?>"
       
        
          onclick="mostrarDescripcion(this)">Ver</button>
      
          <button class="boton1" onclick="editarEquipo(<?= htmlspecialchars(json_encode($row)) ?>)">Editar</button>
          <button class="boton2" onclick="eliminarEquipo(<?= $row['id_equipo'] ?>, '<?= htmlspecialchars(addslashes($row['nombre_equipo'])) ?>')">Eliminar</button>
      </center>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No hay equipos registrados.</p>
    <?php endif; ?>
</div>
    </div>

</div>

<!-- Ventana lateral -->
<div id="ventanaDerecha" style="background-color:rgba(39, 39, 39, 0.99); filter: blur(4);">
  <h2 id="tituloEquipo" style="color:#b2eccf;">Nombre del equipo</h2> <br>
  <center>
  <img style="width: 95%; height: 30%; border-radius: 20px;"src="https://i.postimg.cc/j5gCb8QK/depositphotos-18849767-stock-photo-radio-telescope.png" alt="imagen_Equipo">
 
  </center>
 
  <h3 style="color:#17a2b8; display: inline;">Estado: </h3>
  <p id="EstadoEquip" style="color: white; display: inline;">Estado del equipo</p><br>
  <div class="barra-estado-contenedor" style="background-color: #ccc; width: 450px; height: 10px; display: inline-block; border-radius: 10px; overflow: hidden; margin-top: 5px;">
    <div id="barraEstadoVentana" class="barra-estado" style="background-color: gray; height: 100%; width: 100%;">
  </div>
</div><br><br>
<div style="background-color:#343a40; color:white; padding:15px; border-radius: 5px;">
  <h2 style="color:#b2eccf; margin-top: 0;">Información del Equipo</h2>
  <hr style="border-top: 1px solid #6c757d;">
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Estación:</h3>
    <p id="EstacionEquip" style="color: white; display: inline;">Estación del equipo</p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Serie:</h3>
    <p id="SerieEquip" style="color: white; display: inline;">Serie del equipo</p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Marca:</h3>
    <p id="MarcaEquip" style="color: white; display: inline;">Marca del equipo</p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Modelo:</h3>
    <p id="modelos" style="color: white; display: inline;">Modelo del equipo</p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Tipo de equipo:</h3>
    <p id="TipoEquip" style="color: white; display: inline;">Tipo del equipo</p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Tipo:</h3>
    <p id="tipo" style="color: white; display: inline;">Tipo de equipo</p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Descripción:</h3>
    <p id="descripciones" style="color: white; display: inline;">Descripción del equipo</p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Cantidad disponible:</h3>
    <p id="disponible" style="color: white; display: inline;">15</p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Cantidad total:</h3>
    <p id="total" style="color: white; display: inline;">20</p>
  </div>
</div>

<div style="background-color:#343a40; color:white; padding:15px; border-radius: 5px; margin-top: 15px;">
  <h2 style="color:#b2eccf; margin-top: 0;">Modificaciones de este equipo</h2>
  <hr style="border-top: 1px solid #6c757d;">
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Creado por:</h3>
    <p id="creadorNombre" style="color: white; display: inline;"></p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Fecha de creación:</h3>
    <p id="fecha_creacion" style="color: white; display: inline;"></p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Última edición por:</h3>
    <p id="editorNombre" style="color: white; display: inline;"></p>
  </div>
  <div>
    <h3 style="color:#17a2b8; display: inline; margin-right: 10px;">Fecha de edición:</h3>
    <p id="fecha_edicion" style="color: white; display: inline;"></p>
  </div>
</div>


            <button onclick="cerrarVentana()" style="float:right;   border-radius: 20px;" id="btnCerrar">Cerrar</button> 
</div>
<footer class="footer">
        © 2025 Todos los derechos reservados | <a href="#">RNcorp</a> | <a href="#">Términos de uso</a>
    </footer>
</body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../scripts/script.js"></script>
<script>
document.getElementById('formEquipo').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        Swal.fire({
            title: '¡Éxito!',
            text: 'Equipo guardado correctamente.',
            icon: 'success'
        }).then(() => {
            location.reload(); // Recarga la página para mostrar los datos actualizados
        });
    })
    .catch(error => {
        Swal.fire({
            title: 'Error',
            text: 'Hubo un problema al guardar: ' + error,
            icon: 'error'
        });
    });
});

   
  function mostrarDescripcion(boton) {
    const nombre = boton.getAttribute('data-nombre');
    const estado = boton.getAttribute('data-estado');
    const serie = boton.getAttribute('data-serie');
    const estacion = boton.getAttribute('data-estacion');
    const marca = boton.getAttribute('data-marca');
    const tipo_equipo = boton.getAttribute('data-tipo_equipo');
    const cantidad_disponible = boton.getAttribute('data-cantidad_disponible');
    const cantidad_total= boton.getAttribute('data-cantidad_total');
    const modelo = boton.getAttribute('data-modelo');
    const tip_equip = boton.getAttribute('data-tip_equip');
    const descripcion = boton.getAttribute('data-descripcion');
    const creador = boton.getAttribute('data-creador');
    const editor = boton.getAttribute('data-editor');
    const fecha = boton.getAttribute('data-fecha_edicion');
      const fechaC = boton.getAttribute('data-fecha_creacion');
   
     document.getElementById('fecha_creacion').textContent = fechaC || 'Admin';
     document.getElementById('fecha_edicion').textContent = fecha || 'No editado';
    document.getElementById('creadorNombre').textContent = creador;
    document.getElementById('editorNombre').textContent = editor;
    document.getElementById('tituloEquipo').textContent = nombre;
    document.getElementById('EstadoEquip').textContent = estado;
    document.getElementById('SerieEquip').textContent = serie;
    document.getElementById('EstacionEquip').textContent = estacion;
    document.getElementById('MarcaEquip').textContent = marca;
    document.getElementById('TipoEquip').textContent = tipo_equipo;
    document.getElementById('disponible').textContent = cantidad_disponible;
    document.getElementById('total').textContent = cantidad_total;
    document.getElementById('modelos').textContent = modelo;
    document.getElementById('tipo').textContent = tip_equip;
    document.getElementById('descripciones').textContent = descripcion;
    

   
    

    const barraEstado = document.getElementById('barraEstadoVentana');
    switch(estado) {
        case 'Bueno':
            barraEstado.style.backgroundColor = '#28a745';
            barraEstado.style.width = '100%';
            break;
        case 'Aceptable':
            barraEstado.style.backgroundColor = '#ffc107';
            barraEstado.style.width = '50%';
            break;
        case 'Malo':
            barraEstado.style.backgroundColor = '#dc3545';
            barraEstado.style.width = '10%';
            break;
        default:
            barraEstado.style.backgroundColor = 'gray';
            barraEstado.style.width = '100%';
    }
    
    // Mostrar ventana
    document.getElementById('ventanaDerecha').classList.add('activa');
}

// Función para cerrar la ventana
function cerrarVentana() {
    document.getElementById('ventanaDerecha').classList.remove('activa');
}

// Asignar evento al botón cerrar (solo hay uno)
document.getElementById('btnCerrar').addEventListener('click', cerrarVentana);


</script>
</html>