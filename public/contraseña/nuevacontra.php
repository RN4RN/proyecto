<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $clave = $_POST['clave'];
    $clave_repeat = $_POST['clave_repeat'];

    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";

    if ($clave !== $clave_repeat) {
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Las contraseñas no coinciden',
                text: 'Verifica e intenta nuevamente.',
                showConfirmButton: false,
                timer: 3000
            });
        });
        </script>";
    } else {
        $hashed_clave = password_hash($clave, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET clave = ? WHERE email = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $hashed_clave, $email);

        if ($stmt->execute()) {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Contraseña actualizada',
                    text: 'Ya puedes iniciar sesión.',
                    timer: 3000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '/contrase%C3%B1a/index.php';
                });
            });
            </script>";
        } else {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al actualizar',
                    text: 'Inténtalo de nuevo más tarde.',
                    showConfirmButton: false,
                    timer: 3000
                });
            });
            </script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña</title>
    <link rel="shortcut icon" href="./mapaprincipal/ico.ico" />
    <link rel="stylesheet" href="styles1.css">
  
</head>
<body>
    <div class="fondo"></div>
    <div class="login-container1">
        <p class="heading">Cambiar Contraseña</p>

        <form method="POST">
        <p class="pe" id="fortaleza" >Ingrese una contraseña</p>
        
            <input type="hidden" name="email" value="<?php echo $_GET['email']; ?>">
            <div class="input-group">
            <input type="password" id="password" name="clave" placeholder="Nueva Contraseña" required onkeyup="verificarFortaleza()">
            </div>
            <div class="input-group">
            <input type="password" name="clave_repeat" placeholder="Repite la Contraseña" required>
            </div>
            <button type="submit">Cambiar Contraseña</button> <br>
            <br>
            <a href="index.php" class="link">Iniciar sesion</a>
            
        </form>
    </div>
    <footer class="footer">
        © 2025 Todos los derechos reservados | <a href="#">RNcorp</a> | <a href="#">Términos de uso</a>
    </footer>
</body>
<script>
        function verificarFortaleza() {
            let clave = document.getElementById("clave").value;
            let mensaje = document.getElementById("fortaleza");
            if (clave.length < 6) {
                mensaje.innerHTML = "Débil (Mínimo 6 caracteres)";
                mensaje.style.color = "red";
            } else if (clave.match(/[A-Z]/) && clave.match(/[0-9]/) && clave.match(/[@$!%*?&]/)) {
                mensaje.innerHTML = "Fuerte";
                mensaje.style.color = "green";
            } else {
                mensaje.innerHTML = "Media (Añade números y símbolos)";
                mensaje.style.color = "orange";
            }
        }
    </script>
</html>

