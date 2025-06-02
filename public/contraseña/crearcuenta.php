<?php
session_start();
require_once './mapaprincipal/config/conexion.php';
echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $nombre = $_POST['nombre'];
    $clave = $_POST['clave'];
    $dni = $_POST['dni'];
    $perfil = "usuario"; // Puedes asignar un valor por defecto si no hay input
    $telefono = $_POST['telefono'];
    $confirm_clave = $_POST['confirm_password'];

    // Verificar si las contraseñas coinciden
    if ($clave !== $confirm_clave) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Las contraseñas no coinciden.',
                    timer: 3000,
                    showConfirmButton: false
                });
            });
        </script>";
    } else {
        // Verificar si el usuario ya existe
        $check_sql = "SELECT * FROM usuarios WHERE nombre = '$nombre' OR email = '$email'";
        $check_result = $conexion->query($check_sql);
        
        if ($check_result->num_rows > 0) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Usuario existente',
                        text: 'El nombre de usuario o correo ya está en uso.',
                        timer: 3000,
                        showConfirmButton: false
                    });
                });
            </script>";
        } else {
            $hashed_clave = password_hash($clave, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (email, nombre, clave, dni, perfil, telefono) VALUES ('$email', '$nombre', '$hashed_clave','$dni','$perfil','$telefono')";
            
            if ($conexion->query($sql) === TRUE) {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Registro exitoso',
                            text: 'Redirigiendo al inicio de sesión...',
                            timer: 3000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = '/index.php';
                        });
                    });
                </script>";
            } else {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de registro',
                            text: 'No se pudo completar el registro.',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    });
                </script>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta</title>
    <link rel="shortcut icon" href="./mapaprincipal/ico.ico" />
    <link rel="stylesheet" href="styles1.css">
</head>
<body>
    <div class="fondo"></div>
    <div class="login-container1">
        <p class="heading">Registrarse</p>
        <form method="POST">
            <div class="input-group">
            <input type="email" name="email" placeholder="Correo Electrónico" required><br>
            </div>
            <div class="input-group">
            <input type="text" name="nombre" placeholder="nombre" required><br>
            </div>
            <div class="input-group">
            <input type="text" name="dni" placeholder="dni" required><br>
            </div>
            <div class="input-group">
            <input type="text" name="telefono" placeholder="telefono" required><br>
            </div>
            <div class="input-group">
            <input type="password" name="clave" placeholder="Contraseña" required><br>
            </div>
            <div class="input-group">
            <input type="password" name="confirm_password" placeholder="Repetir Contraseña" required><br>
            </div>
            <button type="submit">Registrarse</button>
        </form>
        <div class="bottom-text">
        <p>¿Ya tienes cuenta? <a class="link" href="index.php" >Inicia sesión aquí</a></p>
        </div>
    </div>
    <footer class="footer">
        © 2025 Todos los derechos reservados | <a href="#">RNcorp</a> | <a href="#">Términos de uso</a>
    </footer>
</body>
</html>
