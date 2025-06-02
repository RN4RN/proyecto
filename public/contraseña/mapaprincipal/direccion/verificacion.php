<?php
session_start();
include("../config/conexion.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $clave = mysqli_real_escape_string($conexion, $_POST['clave']);

    $sql = "SELECT u.id_usuario, u.nombre, u.clave, r.nombre_rol 
            FROM usuarios u 
            INNER JOIN roles r ON u.id_rol = r.id_rol 
            WHERE u.nombre = '$nombre' LIMIT 1";

    $resultado = $conexion->query($sql);

    if ($resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();

        if (password_verify($clave, $row['clave'])) {

            if ($row['nombre_rol'] == 'Director') {
                // Guardar sesión solo si es Director
                $_SESSION['id_usuario'] = $row['id_usuario'];
                $_SESSION['nombre'] = $row['nombre'];
                $_SESSION['rol'] = $row['nombre_rol'];

                header("Location: index.php"); // Redirigir al panel del director
                exit;
            } else {
                $error = "⚠️ Acceso denegado: Solo el Director puede ingresar.";
            }

        } else {
            $error = "⚠️ Contraseña incorrecta.";
        }
    } else {
        $error = "⚠️ Usuario no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="styles1.css">
</head>
<body>
    <!-- Fondo -->
    <div class="fondo"></div>
    <div class="cabeza">
        <header class="header">
            <img src="../frontend/tele.png" alt="Logo" class="logo">
            DIRECCION DE TELECOMUNICACIONES 
            <img src="../frontend/mtc.png" alt="Logo" class="logo">
        </header>
    </div>

    <!-- Contenedor del login centrado -->
    <div class="login-container">
        <form class="login-form" method="POST">
            <p class="heading">Iniciar sesión</p>
            <p class="paragraph">Acceso solo para el director</p>
            <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
            <div class="input-group">
                <input required placeholder="Username" name="nombre" id="nombre" type="text"/>
            </div>
            <div class="input-group">
                <input required placeholder="Password" name="clave" id="password" type="password"/>
            </div>
            <button type="submit">Acceder</button>
            <div class="bottom-text">
                <p><a href="http://localhost/nuevo/contraseña/mapaprincipal/index.php" style="color:white;">Volver</a></p>
            </div>
        </form>
    </div>
    <div class="contenido_usuarios"></div>
    <footer class="footer">
        © 2025 Todos los derechos reservados | <a href="#">RNcorp</a> | <a href="#">Términos de uso</a>
    </footer>
</body>
</html>