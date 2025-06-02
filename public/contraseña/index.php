<?php
session_start();
require_once './mapaprincipal/config/conexion.php';

// Verificar si ya se mostraron los términos (solo la primera vez)
if (!isset($_SESSION['terminos_mostrados'])) {
    $_SESSION['terminos_mostrados'] = true;
    $mostrar_modal = true;
} else {
    $mostrar_modal = false;
}

// ...existing code...
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $clave = $_POST['clave'];

    $sql = "SELECT * FROM usuarios WHERE nombre='$nombre'";
    $resultado = $conexion->query($sql);

    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();

        if ($fila['activo'] !== 'SI') {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Usuario inactivo',
                    text: 'Tu cuenta está desactivada. Contacta al administrador.',
                    showConfirmButton: false,
                    timer: 4000
                });
            });
            </script>";
        } elseif (empty($fila['id_rol'])) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin rol asignado',
                    text: 'Contacta con el director para poder acceder a la plataforma.',
                    showConfirmButton: false,
                    timer: 4000
                });
            });
            </script>";
        } elseif (password_verify($clave, $fila['clave'])) {
            $_SESSION['nombre'] = $nombre;
            header("Location: http://localhost/nuevo/contraseña/mapaprincipal/index.php");
            exit();
        } else {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Contraseña incorrecta',
                    text: 'Intenta nuevamente.',
                    showConfirmButton: false,
                    timer: 3000
                });
            });
            </script>";
        }
    } else {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'warning',
                title: 'Usuario no encontrado',
                text: 'Verifica tu nombre de usuario.',
                showConfirmButton: false,
                timer: 3000
            });
        });
        </script>";
    }
}
// ...existing code...
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="shortcut icon" href="./mapaprincipal/ico.ico" />
    <link rel="stylesheet" href="styles1.css">
    <style>
         #modal {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }

    #modal-content {
      background: white;
      padding: 20px;
      border-radius: 10px;
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
      text-align: left;
      box-shadow: 0 0 10px rgba(0,0,0,0.3);
    }

    button {
      margin-top: 20px;
      padding: 10px 20px; 
      background-color:  rgba(46, 129, 255, 0.7);
    }
    </style>
</head>
<?php if ($mostrar_modal): ?>
<div id="modal" style="background-color:rgba(134, 134, 134, 0.07);">
  <div id="modal-content">
      <h1>Términos y Condiciones de Uso del Software</h1>
    <div id="terminos-texto">Cargando...</div>
    <button onclick="cerrarModal()" style="color:white; background:rgb(255, 153, 36);">Aceptar</button>
    <a href="https://www.google.com.pe/" style=" text-decoration: none;"><button class="button is-ghost" style="background-color: rgba(192, 0, 0, 0.7);">Decline</button></a>
  </div>
</div>
<?php endif; ?>
<body>
    <div class="fondo"></div>
    <div class="cabeza">
        <header class="header">
            <img src="./mapaprincipal/frontend/tele.png" alt="Logo" class="logo">
            Ministerio de Transportes y Telecomunicaciones
            <img src="./mapaprincipal/frontend/mtc.png" alt="Logo" class="logo">
        </header>
    </div>

    <div class="login-container">
        <form class="login-form" method="POST">
            <p class="heading">Iniciar sesión</p>
            <p class="paragraph">Ingresa con tu cuenta</p>
            <div class="input-group">
                <input required placeholder="Username" name="nombre" id="nombre" type="text"/>
            </div>
            <div class="input-group">
                <input required placeholder="Password" name="clave" id="password" type="password"/>
            </div>
            <button type="submit">Acceder</button>
            <div class="bottom-text">
                <p>¿No tienes una cuenta? <a href="crearcuenta2.php">clic aquí</a></p>
                <p><a href="olvidocontra.php">¿Olvidaste tu contraseña?</a></p>
            </div>
        </form>
    </div>

    <footer class="footer">
        © 2025 Todos los derechos reservados | <a href="https://www.facebook.com/share/15yZx44QFM/">RNcorp</a> | <a href="terminosdeuso.php">Términos de uso</a>
    </footer>
</body>
<script>
  function cerrarModal() {
    document.getElementById('modal').style.display = 'none';
  }

  // Cargar los términos desde el archivo PHP solo si el modal está visible
  <?php if ($mostrar_modal): ?>
  window.onload = function() {
    fetch('terminos.php')
      .then(response => response.text())
      .then(data => {
        document.getElementById('terminos-texto').innerHTML = data;
      })
      .catch(error => {
        document.getElementById('terminos-texto').innerText = 'Error pagz.';
      });
  };
  <?php endif; ?>
</script>
</html>