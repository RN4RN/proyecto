<?php
require_once './config/conexion.php';

session_start();

if (!isset($_SESSION['nombre'])) {
    header('Location: /contrase%C3%B1a/index.php');
    exit();
}

$nombre_sesion = $_SESSION['nombre'];

// Prepara y ejecuta
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
    ? 'uploads/perfiles/' . $user['foto_rostro']
    : 'assets/img/avatar-default.png';
    
// Obtener datos del usuario logueado

$sql_usuario = "SELECT * FROM usuarios WHERE nombre = ?";
$stmt_usuario = $conexion->prepare($sql_usuario);
$stmt_usuario->bind_param("s", $nombre_sesion);
$stmt_usuario->execute();
$resultado_usuario = $stmt_usuario->get_result();
$usuario = $resultado_usuario->fetch_assoc();
$stmt_usuario->close();
?>

<?php
$sql_tabla = "SELECT u.nombre, u.dni, u.email, u.telefono, u.perfil, u.fecha_registro, r.nombre_rol
            FROM usuarios u
            INNER JOIN roles r ON u.id_rol = r.id_rol";

$result_tabla = $conexion->query($sql_tabla);

$seccion = 'nombre_card'; // cambia esto dinámicamente según la sección/card
$nombreUsuario = $_SESSION['nombre'];
$fechaActual = date('Y-m-d H:i:s');

// Insertar o actualizar el acceso
$sql_registro = "INSERT INTO registro_acceso (seccion, nombre_usuario, fecha_ultimo_acceso)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                nombre_usuario = VALUES(nombre_usuario),
                fecha_ultimo_acceso = VALUES(fecha_ultimo_acceso)";

$stmt_registro = $conexion->prepare($sql_registro);
$stmt_registro->bind_param("sss", $seccion, $nombreUsuario, $fechaActual);
$stmt_registro->execute();
$stmt_registro->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telecomunicaciones</title>
    <link rel="shortcut icon" href="./ico.ico" />
    <link rel="stylesheet" href="paginapri.css">
    <link rel="stylesheet" href="boton.css">
    <link rel="stylesheet" href="styles3.css">
    <link rel="stylesheet" href="chat.css">
    <style>
     
    .perfile {
      position: fixed;
      margin-top: 5px;
      margin-left: 85%;
      width: 45px;  /* Tamaño del círculo */
      height: 45px; 
      border-radius: 50%;  /* Hace que sea un círculo */
      overflow: hidden;  /* Evita que la imagen se salga */
      border: 3px solid white;  /* Borde elegante */
      box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.5); /* Sombra para resaltar */
  }
  
  .perfile img {
      width:100%;
      height: 100%;
      object-fit: cover; /* Ajusta la imagen sin deformarla */
    
  }
  
  
  
    </style>
</head>
<!-- From Uiverse.io by kandalgaonkarshubham --> 
<div class="container"></div>

<body>
<div class="todo_contenido">
    
<div class="barra">
    <!-- From Uiverse.io by Li-Deheng --> 
<div class="btn-conteiner">
  <a class="btn-content" href="logout.php">
    <span class="btn-title">Cerrar sesión</span>
    <span class="icon-arrow">
      <svg width="66px" height="43px" viewBox="0 0 66 43" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
        <g id="arrow" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
          <path id="arrow-icon-one" d="M40.1543933,3.89485454 L43.9763149,0.139296592 C44.1708311,-0.0518420739 44.4826329,-0.0518571125 44.6771675,0.139262789 L65.6916134,20.7848311 C66.0855801,21.1718824 66.0911863,21.8050225 65.704135,22.1989893 C65.7000188,22.2031791 65.6958657,22.2073326 65.6916762,22.2114492 L44.677098,42.8607841 C44.4825957,43.0519059 44.1708242,43.0519358 43.9762853,42.8608513 L40.1545186,39.1069479 C39.9575152,38.9134427 39.9546793,38.5968729 40.1481845,38.3998695 C40.1502893,38.3977268 40.1524132,38.395603 40.1545562,38.3934985 L56.9937789,21.8567812 C57.1908028,21.6632968 57.193672,21.3467273 57.0001876,21.1497035 C56.9980647,21.1475418 56.9959223,21.1453995 56.9937605,21.1432767 L40.1545208,4.60825197 C39.9574869,4.41477773 39.9546013,4.09820839 40.1480756,3.90117456 C40.1501626,3.89904911 40.1522686,3.89694235 40.1543933,3.89485454 Z" fill="#FFFFFF"></path>
          <path id="arrow-icon-two" d="M20.1543933,3.89485454 L23.9763149,0.139296592 C24.1708311,-0.0518420739 24.4826329,-0.0518571125 24.6771675,0.139262789 L45.6916134,20.7848311 C46.0855801,21.1718824 46.0911863,21.8050225 45.704135,22.1989893 C45.7000188,22.2031791 45.6958657,22.2073326 45.6916762,22.2114492 L24.677098,42.8607841 C24.4825957,43.0519059 24.1708242,43.0519358 23.9762853,42.8608513 L20.1545186,39.1069479 C19.9575152,38.9134427 19.9546793,38.5968729 20.1481845,38.3998695 C20.1502893,38.3977268 20.1524132,38.395603 20.1545562,38.3934985 L36.9937789,21.8567812 C37.1908028,21.6632968 37.193672,21.3467273 37.0001876,21.1497035 C36.9980647,21.1475418 36.9959223,21.1453995 36.9937605,21.1432767 L20.1545208,4.60825197 C19.9574869,4.41477773 19.9546013,4.09820839 20.1480756,3.90117456 C20.1501626,3.89904911 20.1522686,3.89694235 20.1543933,3.89485454 Z" fill="#FFFFFF"></path>
          <path id="arrow-icon-three" d="M0.154393339,3.89485454 L3.97631488,0.139296592 C4.17083111,-0.0518420739 4.48263286,-0.0518571125 4.67716753,0.139262789 L25.6916134,20.7848311 C26.0855801,21.1718824 26.0911863,21.8050225 25.704135,22.1989893 C25.7000188,22.2031791 25.6958657,22.2073326 25.6916762,22.2114492 L4.67709797,42.8607841 C4.48259567,43.0519059 4.17082418,43.0519358 3.97628526,42.8608513 L0.154518591,39.1069479 C-0.0424848215,38.9134427 -0.0453206733,38.5968729 0.148184538,38.3998695 C0.150289256,38.3977268 0.152413239,38.395603 0.154556228,38.3934985 L16.9937789,21.8567812 C17.1908028,21.6632968 17.193672,21.3467273 17.0001876,21.1497035 C16.9980647,21.1475418 16.9959223,21.1453995 16.9937605,21.1432767 L0.15452076,4.60825197 C-0.0425130651,4.41477773 -0.0453986756,4.09820839 0.148075568,3.90117456 C0.150162624,3.89904911 0.152268631,3.89694235 0.154393339,3.89485454 Z" fill="#FFFFFF"></path>
        </g>
      </svg>
    </span> 
  </a>
</div>
 
    <button class="hamburger-button" id="hamburger-button" aria-label="Abrir menú lateral" aria-expanded="false" for="check" style="background-color: rgba(255, 255, 255, 0); border:none;">
     <input type="checkbox" id="check">
     <span class="top"></span>
     <span class="middle"></span>
     <span class="bottom"></span>
  </button>



<div class="perfile">
 <img src="<?= htmlspecialchars($imagen) ?>" alt="Foto de perfil">
</div>
    
<button class="calendar" style="background-color:rgba(248, 215, 218, 0);">
 <a href="calendar.php" style="text-decoration:none;"> <img src="https://i.postimg.cc/kgzHzm5c/calendario-rojo-gradiente-78370-38392.png" alt=""></a>
</button>
   
</div>

    <!-- Overlay para oscurecer el fondo cuando la barra está abierta -->
    <div class="overlay" id="overlay"></div>

    <!-- Barra Lateral Deslizante -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>Menú</h2>
            <!-- Puedes añadir un botón de cierre explícito si quieres, aunque el hamburguesa ya lo hace -->
            <!-- <button class="close-button" id="close-button">×</button> -->
        </div>
        <div class="sidebar-content">

            <!-- Botón de Perfil -->
            <div class="profile-section">
                <button class="profile-button" id="profile-button"
                        data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                        data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                        data-estado="<?php echo isset($usuario['estado']) ? htmlspecialchars($usuario['estado']) : 'Activo'; ?>"
                        data-rol="<?php echo isset($usuario['nombre_rol']) ? htmlspecialchars($usuario['nombre_rol']) : 'Usuario'; /* Asumiendo que 'nombre_rol' viene de la consulta de usuario si se une con roles */ ?>"
                        data-descripcion="<?php echo isset($usuario['descripcion']) ? htmlspecialchars($usuario['descripcion']) : 'Usuario del sistema.'; ?>">
                    <img src="<?= htmlspecialchars($imagen) ?>"
       alt="Foto de perfil">
                    <span>Mi Perfil</span>
                </button>
                <div class="profile-description" id="profile-description">
                    <!-- La descripción se insertará aquí por JS -->
                </div>
            </div>
<style>
       .containerdeperfil{
            width: 80%;
            margin: auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
       }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
        }

        input[type="text"],
        input[type="email"],
        textarea {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
        }

        textarea {
            resize: vertical;
            height: 80px;
        }

        .profile-pic {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .profile-pic img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ddd;
        }

        .custom-file {
            position: relative;
            margin-bottom: 20px;
        }

        .custom-file input[type="file"] {
            display: none;
        }

        .custom-file-label {
            display: block;
            background-color: #e9ecef;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.3s ease;
            border: 1px solid #ccc;
            font-weight: 500;
            color: #333;
        }

        .custom-file-label:hover {
            background-color: #d6d8db;
        }

        .file-name {
            margin-top: 8px;
            font-size: 13px;
            color: #555;
            text-align: center;
        }

       
    </style>

 
<div class="containerdeperfil">
    <h2>Configuración de Perfil</h2>
    <form action="subir_perfil.php" method="POST" enctype="multipart/form-data">
        <div class="profile-pic">
            <img src="<?= htmlspecialchars($imagen) ?>"
       alt="Foto de perfil">
        </div>
        <label>Foto de perfil:</label>
        <div class="custom-file">
            <label class="custom-file-label" for="foto">Seleccionar imagen</label>
            <input type="file" name="foto" id="foto" accept="image/jpeg,image/png" >
            <div class="file-name" id="nombreArchivo">Suba una imagen presionando este boton</div>
        </div>

        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?php echo $usuario['nombre']; ?>">

        <label>Email:</label>
        <input type="email" name="email" value="<?php echo $usuario['email']; ?>">

        <label>DNI:</label>
        <input type="text" name="dni" value="<?php echo $usuario['dni']; ?>">

        <label>Teléfono:</label>
        <input type="text" name="telefono" value="<?php echo $usuario['telefono']; ?>">

        <label>Descripción:</label>
        <textarea name="descripcion"><?php echo $usuario['descripcion']; ?></textarea>

        <button type="submit" style="background-color: #2ecc71;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;">Guardar Cambios</button>
    </form>
</div>

<script>
    function mostrarNombreArchivo(input) {
        const nombreArchivo = input.files.length > 0 
            ? input.files[0].name 
            : "Ningún archivo seleccionado (opcional)";
        document.getElementById('nombreArchivo').textContent = nombreArchivo;
    }
    
    // Asigna el evento al input file
    document.getElementById('foto').addEventListener('change', function() {
        mostrarNombreArchivo(this);
    });
</script>
            <!-- Botón de Calendario -->
            <a  class="sidebar-button calendar-button">
                <!-- Icono SVG de Calendario (Ejemplo) -->
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-calendar">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Calendario</span>
            </a>
 
    <div class="container10">
      <style>

.container10 {
    background-color: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    width: 90%;
    max-width: 900px;
}

header {
    text-align: center;
    margin-bottom: 20px;
}

header h1 {
    color: #2c3e50;
    margin-bottom: 10px;
}

.month-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.month-navigation button {
    background-color: #3498db;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.month-navigation button:hover {
    background-color: #2980b9;
}

.month-navigation h2 {
    color: #2980b9;
    margin: 0;
    font-size: 1.5em;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-bottom: 20px;
}

.weekday, .day-cell {
    background-color: #ecf0f1;
    padding: 10px;
    text-align: center;
    font-weight: bold;
    border-radius: 3px;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    grid-column: 1 / -1; /* Span all 7 columns */
}

.day-cell {
    background-color: #fdfdfe;
    border: 1px solid #ddd;
    min-height: 80px; /* Para que los días tengan algo de altura */
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding-top: 5px;
    cursor: pointer;
    transition: background-color 0.2s;
}
.day-cell:hover {
    background-color: #e9ecef;
}

.day-cell.empty {
    background-color: #f9f9f9;
    cursor: default;
    border: 1px solid #eee;
}

.day-cell .day-number {
    font-size: 0.9em;
    font-weight: bold;
}

.day-cell.current-day .day-number {
    background-color: #3498db;
    color: white;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    line-height: 25px;
    display: inline-block;
    text-align: center;
}

.event-marker {
    font-size: 0.7em;
    padding: 2px 4px;
    border-radius: 3px;
    margin-top: 5px;
    max-width: 90%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.event-holiday {
    background-color: #e74c3c; /* Rojo para feriados */
    color: white;
}

.event-custom {
    background-color: #2ecc71; /* Verde para personalizados */
    color: white;
}

.info-panel, .event-form-container {
    background-color: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border: 1px solid #e0e0e0;
}

.info-panel h3, .event-form-container h3 {
    margin-top: 0;
    color: #34495e;
}

#upcoming-events-list {
    list-style: none;
    padding: 0;
}

#upcoming-events-list li {
    padding: 5px 0;
    border-bottom: 1px dashed #ccc;
}
#upcoming-events-list li:last-child {
    border-bottom: none;
}

#add-event-form label {
    display: block;
    margin-top: 10px;
    margin-bottom: 5px;
    font-weight: bold;
}

#add-event-form input[type="date"],
#add-event-form input[type="text"] {
    width: calc(100% - 22px); /* Considera padding y borde */
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    margin-bottom: 10px;
}

#add-event-form button {
    background-color: #2ecc71;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

#add-event-form button:hover {
    background-color: #27ae60;
}

#notifications-area {
    margin-top: 20px;
}

.notification {
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 5px;
    color: white;
    font-weight: bold;
}

.notification.info {
    background-color: #3498db; /* Azul para info */
}
.notification.warning {
    background-color: #f39c12; /* Naranja para advertencia */
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .month-navigation {
        flex-direction: column;
    }
    .month-navigation h2 {
        margin: 10px 0;
    }
    .day-cell {
        min-height: 60px;
        font-size: 0.9em;
    }
    .event-marker {
        font-size: 0.6em;
    }
}

@media (max-width: 480px) {
    body {
        padding: 10px;
    }
    .container {
        padding: 15px;
    }
    .day-cell {
        min-height: 50px;
        font-size: 0.8em;
        padding: 5px;
    }
    .day-cell .day-number {
        width: 20px;
        height: 20px;
        line-height: 20px;
        font-size: 0.8em;
    }
    .event-marker {
        display: none; /* Ocultar texto del evento, solo color de fondo */
    }
    .day-cell.event-holiday, .day-cell.event-custom {
        /* Solo color de fondo como indicador en móviles pequeños */
    }
    .weekday {
        padding: 8px;
        font-size: 0.8em;
    }
    .month-navigation button {
        padding: 8px 10px;
        font-size: 0.9em;
    }
    .month-navigation h2 {
        font-size: 1.2em;
    }
}

.card {

    position:relative;
    
    width: 200px;
    height: 400px;
    min-height: 230px;
    border-radius: 20px;
    background: #212121;
    box-shadow: 5px 5px 8px #1b1b1b,
               -5px -5px 8px #272727;
    transition: 0.4s;
  }
.card{
        margin: 0px auto;
        position:relative;
      }
 .card:hover {
    translate: 0 -10px;
  }
  
  .card-title {
    font-size: 18px;
    font-weight: 600;
    color: #b2eccf;
    margin: 15px 0 0 10px;
  }
.card-image{
    margin-top:5px;
    width: 90%;
    height: auto;
}
.card-image img {
    width: 100%;
    border-radius: 10px;
    min-height: 170px;
    background-color: #313131;
    border-radius: 15px;
    background: #313131;
    box-shadow: inset 5px 5px 3px #2f2f2f,
              inset -5px -5px 3px #333333;
  }
  
  .card-body {
    margin: 13px 0 0 10px;
    color: rgb(184, 184, 184);
    font-size: 15px;
  }
   .footer {
    float: right;
    margin: 5px 0 0 18px;
    font-size: 13px;
    color: #b3b3b3;
  }
  
  
  
      
      </style>
        <header>
            <h1>Calendario Interactivo</h1>
            <div class="month-navigation">
                <button id="prev-month">< Anterior</button>
                <h2 id="current-month-year"></h2>
                <button id="next-month">Siguiente ></button>
            </div>
        </header>

        <div class="calendar-grid">
            <div class="weekday">Dom</div>
            <div class="weekday">Lun</div>
            <div class="weekday">Mar</div>
            <div class="weekday">Mié</div>
            <div class="weekday">Jue</div>
            <div class="weekday">Vie</div>
            <div class="weekday">Sáb</div>
            <div id="calendar-days" class="days-grid">
                <!-- Los días se generarán aquí -->
            </div>
        </div>

        <div class="info-panel">
            <h3>Información Anual</h3>
            <p id="days-passed-year"></p>
            
            <h3>Próximos Eventos Importantes</h3>
            <ul id="upcoming-events-list">
                <!-- Lista de eventos -->
            </ul>
        </div>

        <div class="event-form-container">
            <h3>Agregar Evento Personalizado</h3>
            <form id="add-event-form">
                <label for="event-date">Fecha:</label>
                <input type="date" id="event-date" required>
                
                <label for="event-name">Nombre del Evento:</label>
                <input type="text" id="event-name" placeholder="Ej: Cumpleaños de Ana, salida de algun operador, etc." required>
                
                <button type="submit">Agregar Evento</button>
            </form>
        </div>

        <div id="notifications-area">
            <!-- Las notificaciones aparecerán aquí -->
        </div>
         
    </div>

    <script src="calendar.js"></script>

            <!-- Botón Cerrar Sesión (Estilo Uiverse) -->

        </div>
    </aside> 
<!--Banner-->
<div class="banner" >
  <img src="https://i.postimg.cc/44xfXBPW/telecomunicaciones.jpg" class="banner-img active" alt="Banner 1">
  <img src="https://i.postimg.cc/Hm271Ks4/GESTION-DE-EQUIPOS.jpg" class="banner-img" alt="Banner 2">
</div>



<div class="content" style="position:relative; margin-top:20px;">
<!-- From Uiverse.io by Sashank02 --> 
<div class="card">
    <center>
  <div class="card-image">
    <a href="/consumibles/index.php" style="text-decoration:none;"><img src="https://i.postimg.cc/XvsNjrqh/Gemini-Generated-Image-x0w899x0w899x0w8.jpg" alt="Equipos consumibles"></a>
  </div>
  </center>
  <p class="card-title"><Em>Equipos Consumibles</Em></p>
  <p class="card-body">
   Gestionar equipos consumibles como cintas, lubricantes multiuso, etc.
  </p>
  <center>
    <br>
  <a href="/consumibles/index.php"><button class="botton">VER</button>
  </center>
 </a>
  <?php
$seccion = 'nombre_card'; // debe coincidir con el mismo nombre usado en el insert
$query = "SELECT nombre_usuario, fecha_ultimo_acceso FROM registro_acceso WHERE seccion = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $seccion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($fila = $resultado->fetch_assoc()) {
    echo "<p class='footer'>Última visita por: {$fila['nombre_usuario']} el  {$fila['fecha_ultimo_acceso']}</p>";
} else {
    echo "<p>Aún sin ingreso.</p>";
}
?> 
</div>
<div class="card">
    <center>
  <div class="card-image">
   <a href="/equipos/index.php" style="text-decoration:none;"> <img src="https://i.postimg.cc/767B1Qdy/Gemini-Generated-Image-6uffe66uffe66uff.png" alt="equipos en general"></a>
  </div>
  </center>
  <p class="card-title"><Em>Equipos</Em></p>
  <p class="card-body">
  Añadir-Modificar equipos por estacion, serie, estado, descripcion, etc.
  </p>
  <center>
    <br>
  <a href="/equipos/index.php"><button class="botton">VER</button> </a>
  </center>

 <?php
$seccion = 'nombre_card'; // debe coincidir con el mismo nombre usado en el insert
$query = "SELECT nombre_usuario, fecha_ultimo_acceso FROM registro_acceso WHERE seccion = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $seccion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($fila = $resultado->fetch_assoc()) {
    echo "<p class='footer' >Última visita por: {$fila['nombre_usuario']} el  {$fila['fecha_ultimo_acceso']}</p>";
} else {
    echo "<p>Aún sin ingreso.</p>";
}
?> 

</div>
<div class="card">
    <center>
  <div class="card-image">
    <a href="/Noconsumibles/index.php" style="text-decoration:none;"><img src="https://i.postimg.cc/nzBN0Lgr/Gemini-Generated-Image-gqosr0gqosr0gqos.png" alt="equipos permanentes">
  </div>
  </center>
  <p class="card-title"><Em>Equipos Permanentes</Em></p>
  <p class="card-body">
   Añadir-Modificar componentes de equipos por estacion, serie, estado, etc.
  </p>
  <center>
    <br>
  <button class="botton">VER</button>  </a>
  </center>

  <?php
$seccion = 'nombre_card'; 
$query = "SELECT nombre_usuario, fecha_ultimo_acceso FROM registro_acceso WHERE seccion = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $seccion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($fila = $resultado->fetch_assoc()) {
    echo "<p class='footer'>Última visita por: {$fila['nombre_usuario']} el  {$fila['fecha_ultimo_acceso']}</p>";
} else {
    echo "<p>Aún sin ingreso.</p>";
}
?> 
</div>
<div class="card">
    <center>
  <div class="card-image">
    <a href="./mantenimiento/index.html" style="text-decoration:none;"><img src="https://i.postimg.cc/8cK8r0xm/Gemini-Generated-Image-3ycxmx3ycxmx3ycx.png" alt="equipos en mantenimiento"></a>
  </div>
  </center>
  <p class="card-title"><Em>Equipos en Mantenimientos</Em></p>
  <p class="card-body">
   Visualizar el record historico de mantenimiento que se lleva acabo en cada estacion .
  </p>
  <center>
    <br>
  <a href="./mantenimiento/index.html"><button class="botton">IR</button>
  </center>
  </a>
  <?php
$seccion = 'nombre_card'; // debe coincidir con el mismo nombre usado en el insert
$query = "SELECT nombre_usuario, fecha_ultimo_acceso FROM registro_acceso WHERE seccion = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $seccion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($fila = $resultado->fetch_assoc()) {
    echo "<p class='footer'>Última visita por: {$fila['nombre_usuario']} el  {$fila['fecha_ultimo_acceso']}</p>";
} else {
    echo "<p>Aún sin ingreso.</p>";
}
?> 
</div>
<div class="card">
    <center>
  <div class="card-image">
    <a href="/operadores/index.php" style="text-decoration:none;"><img src="https://i.postimg.cc/VLdpV432/Gemini-Generated-Image-5knpes5knpes5knp.png" alt="Operadores"></a>
  </div>
  </center>
  <p class="card-title"><Em>Operadores</Em></p>
  <p class="card-body">
   Aqui se subira las ordenes de trabajo que se llevara acabo en cada estacion
  </p>
  <center>
    <br>
  <a href="/operadores/index.php"><button class="botton">IR</button>
  </center>
  </a>
  <?php
$seccion = 'nombre_card'; // debe coincidir con el mismo nombre usado en el insert
$query = "SELECT nombre_usuario, fecha_ultimo_acceso FROM registro_acceso WHERE seccion = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $seccion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($fila = $resultado->fetch_assoc()) {
    echo "<p class='footer'>Última visita por: {$fila['nombre_usuario']} el  {$fila['fecha_ultimo_acceso']}</p>";
} else {
    echo "<p>Aún sin ingreso.</p>";
}
?> 
</div>
<div class="card">
    <center>
  <div class="card-image">
    <a href="/movimientos/index.php" style="text-decoration:none;"><img src="https://i.postimg.cc/rpjLksgH/Gemini-Generated-Image-1gkwx41gkwx41gkw.png" alt="movimientos"></a>
  </div>
  </center>
  <p class="card-title"><Em>Movimientos de Equipo</Em></p>
  <p class="card-body">
   Ver ubicacion exacta de la estacion y de los equipos (PROVINCIA-DISTRITO-CENTREO POBLADO).
  </p>
  <center>
    <br>
  <a href="/movimientos/index.php"><button class="botton">IR</button>
  </center>
  </a>
  <?php
$seccion = 'nombre_card'; // debe coincidir con el mismo nombre usado en el insert
$query = "SELECT nombre_usuario, fecha_ultimo_acceso FROM registro_acceso WHERE seccion = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $seccion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($fila = $resultado->fetch_assoc()) {
    echo "<p class='footer'>Última visita por: {$fila['nombre_usuario']} el  {$fila['fecha_ultimo_acceso']}</p>";
} else {
    echo "<p>Aún sin ingreso.</p>";
}
?> 
</div>
<div class="card">
    <center>
  <div class="card-image">
    <a href="/direccion/verificacion.php" style="text-decoration:none;"><img src="https://i.postimg.cc/KzVG6482/Gemini-Generated-Image-pb60y8pb60y8pb60.png" alt="Director"></a>
  </div>
  </center>
  <p class="card-title">GERENCIA</p>
  <p class="card-body">
   Este contenido es solo accesible por el director del area de telecomunicaciones
  </p>
  <center>
    <br>
  <a href="/direccion/verificacion.php"><button class="botton">IR</button>
  </center>
  </a>
  <?php
$seccion = 'nombre_card'; // debe coincidir con el mismo nombre usado en el insert
$query = "SELECT nombre_usuario, fecha_ultimo_acceso FROM registro_acceso WHERE seccion = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $seccion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($fila = $resultado->fetch_assoc()) {
    echo "<p class='footer'>Última visita por: {$fila['nombre_usuario']} el  {$fila['fecha_ultimo_acceso']}</p>";
} else {
    echo "<p>Aún sin ingreso.</p>";
}
?> 
</div>

<!-- Elementos del DOM -->
<div id="chat-bubble" title="Abrir chat"></div>
<div id="chat-overlay"></div>
<div id="chat-widget">
  <div id="chat-header">
    TeleBot
    <span id="close-btn" title="Cerrar chat">×</span>
  </div>
  <div class="container-chat-options">
    <div id="messages"></div>
    <textarea id="chat_bot" placeholder="Pregunta lo que quieras...✦˚" rows="3"></textarea>
    <div class="options">
      <div class="btns-add">
        <button title="Opción 1">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8v8a5 5 0 1 0 10 0V6.5a3.5 3.5 0 1 0-7 0V15a2 2 0 0 0 4 0V8"></path>
          </svg>
        </button>
        <button title="Opción 2">
          <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M3 7h5l2 3h11v10H3z"></path>
              </svg>
        </button>
        <button title="Opción 3">
           <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                viewBox="0 0 24 24"
              >
                <rect width="18" height="14" x="3" y="5" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <path d="M21 15l-5-5L5 21"></path>
              </svg>
        </button>
      </div>
      <button class="btn-submit" title="Enviar mensaje">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
      </button>
    </div>
  </div>
</div>
</div>

</body>
<script>
    // Referencias a elementos
const chatBubble = document.getElementById('chat-bubble');
const chatOverlay = document.getElementById('chat-overlay');
const chatWidget = document.getElementById('chat-widget');
const closeBtn = document.getElementById('close-btn');
const messagesContainer = document.getElementById('messages');
const textarea = document.getElementById('chat_bot');
const sendBtn = document.querySelector('.btn-submit');

// Mostrar chat
chatBubble.addEventListener('click', () => {
  chatWidget.style.display = 'flex';
  chatOverlay.style.display = 'block';
  textarea.focus();
});

// Cerrar chat
closeBtn.addEventListener('click', () => {
  chatWidget.style.display = 'none';
  chatOverlay.style.display = 'none';
});

// Cerrar chat al hacer clic fuera del widget
chatOverlay.addEventListener('click', () => {
  chatWidget.style.display = 'none';
  chatOverlay.style.display = 'none';
});

// Función para agregar mensajes al chat
function addMessage(text, sender) {
  const msg = document.createElement('div');
  msg.classList.add(sender); // 'user' o 'bot'
  msg.textContent = text;
  messagesContainer.appendChild(msg);
  messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Enviar mensaje al backend y mostrar respuesta
function sendMessage() {
  const message = textarea.value.trim();
  if (!message) return;

  // Mostrar mensaje del usuario
  addMessage(message, 'user');
  textarea.value = '';
  textarea.focus();

  // Mostrar mensaje de espera
  const loadingMsg = document.createElement('div');
  loadingMsg.classList.add('bot');
  loadingMsg.textContent = 'Escribiendo...';
  messagesContainer.appendChild(loadingMsg);
  messagesContainer.scrollTop = messagesContainer.scrollHeight;

  // Enviar petición POST a chatbot.php
  fetch('chatbot.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'message=' + encodeURIComponent(message)
  })
  .then(response => response.text())
  .then(data => {
    // Quitar mensaje de espera
    messagesContainer.removeChild(loadingMsg);
    // Mostrar respuesta de la IA
    addMessage(data, 'bot');
  })
  .catch(() => {
    messagesContainer.removeChild(loadingMsg);
    addMessage('Error: no se pudo conectar con el servidor.', 'bot');
  });
}

// Enviar con botón
sendBtn.addEventListener('click', sendMessage);

// Enviar con tecla Enter (Shift + Enter para salto de línea)
textarea.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
});

    document.addEventListener('DOMContentLoaded', () => {
    const hamburgerButton = document.getElementById('hamburger-button');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const profileButton = document.getElementById('profile-button');
    const profileDescription = document.getElementById('profile-description');

    function toggleSidebar() {
        const isActive = sidebar.classList.contains('active');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        hamburgerButton.classList.toggle('active'); // Para la animación del botón hamburguesa si la tienes
        hamburgerButton.setAttribute('aria-expanded', String(!isActive));

        if (!sidebar.classList.contains('active')) {
            profileDescription.style.display = 'none';
        }
    }

    hamburgerButton.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);

    profileButton.addEventListener('click', (event) => {
        event.stopPropagation(); 

        if (profileDescription.style.display === 'block') {
            profileDescription.style.display = 'none';
        } else {
            const nombre = profileButton.dataset.nombre || 'N/A';
            const email = profileButton.dataset.email || 'N/A';
            const estado = profileButton.dataset.estado || 'N/A';
            const rol = profileButton.dataset.rol || 'N/A';
            const descripcion = profileButton.dataset.descripcion || 'Sin descripción.';

            profileDescription.innerHTML = `
                <p><strong>Nombre:</strong> ${nombre}</p>
                <p><strong>Email:</strong> ${email}</p>
                <p><strong>Estado:</strong> ${estado}</p>
                <p><strong>Rol:</strong> ${rol}</p>
                <p><strong>Descripción:</strong> ${descripcion}</p>
            `;
            profileDescription.style.display = 'block';
        }
    });

    sidebar.addEventListener('click', (event) => {
        if (!profileButton.contains(event.target) && !profileDescription.contains(event.target)) {
            profileDescription.style.display = 'none';
        }
    });

     // Cierra la descripción si haces clic en cualquier otro lugar del sidebar
    sidebar.addEventListener('click', (event) => {
        // Si el clic NO fue en el botón de perfil ni dentro de la descripción
        if (!profileButton.contains(event.target) && !profileDescription.contains(event.target)) {
            profileDescription.style.display = 'none';
        }
    });

});

 
  const banners = document.querySelectorAll('.banner-img');
  let current = 0;

  setInterval(() => {
    banners[current].classList.remove('active');
    current = (current + 1) % banners.length;
    banners[current].classList.add('active');
  }, 3000); // Cambia cada 5 segundos
</script>
</html>
