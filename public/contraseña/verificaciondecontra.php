<?php
include 'conexion.php';

echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['codigo'])) {
    $email = $_POST['email'];
    $codigo = implode("", $_POST['codigo']);

    if (!preg_match('/^\d{6}$/', $codigo)) {
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Código inválido',
                text: 'El código debe tener exactamente 6 dígitos numéricos.',
                timer: 3000,
                showConfirmButton: false
            });
        });
        </script>";
    } else {
        $sql = "SELECT cod_tokens FROM tokens_recuperacion WHERE email = ? AND codigo = ? AND expiracion > NOW()";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ss", $email, $codigo);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Código correcto',
                    text: 'Redirigiendo para cambiar la contraseña...',
                    timer: 3000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'nuevacontra.php?email=" . urlencode($email) . "';
                });
            });
            </script>";
        } else {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Código incorrecto o expirado',
                    text: 'Verifica e intenta nuevamente.',
                    timer: 3000,
                    showConfirmButton: false
                });
            });
            </script>";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Código</title>
    <link rel="stylesheet" href="styles1.css">
    <link rel="shortcut icon" href="./mapaprincipal/ico.ico" />
</head>
<body>
    <div class="fondo"></div>
    <div class="cabeza"></div>
    <form class="form" method="POST">
        <div class="content">
            <h2 align="center">Ingrese el código de verificación</h2>
            <?php if (!empty($mensaje)) echo $mensaje; ?>
            <input type="hidden" name="email" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
            <div class="inp">
                <input type="text" class="input" name="codigo[]" maxlength="1" oninput="autoMove(this)">
                <input type="text" class="input" name="codigo[]" maxlength="1" oninput="autoMove(this)">
                <input type="text" class="input" name="codigo[]" maxlength="1" oninput="autoMove(this)">
                <input type="text" class="input" name="codigo[]" maxlength="1" oninput="autoMove(this)">
                <input type="text" class="input" name="codigo[]" maxlength="1" oninput="autoMove(this)">
                <input type="text" class="input" name="codigo[]" maxlength="1" oninput="autoMove(this)">
            </div>
            <button type="submit">Verificar</button>
            <center>
            <a href="" class="link">volver a enviar el codigo</a>
            </center>
        </div>
    </form>
    <footer class="footer">
        © 2025 Todos los derechos reservados | <a href="#">RNcorp</a> | <a href="#">Términos de uso</a>
    </footer>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const inputs = document.querySelectorAll(".input");
            const resendBtn = document.getElementById("resendBtn");

            inputs.forEach((input, index) => {
                input.addEventListener("input", (e) => {
                    if (e.target.value.length === 1 && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                });
                input.addEventListener("keydown", (e) => {
                    if (e.key === "Backspace" && index > 0 && !input.value) {
                        inputs[index - 1].focus();
                    }
                });
            });

            setTimeout(() => {
                resendBtn.style.display = "block";
                resendBtn.classList.add("fade-in");
            }, 3000);
        });

        function autoMove(input) {
            let inputs = document.querySelectorAll(".input");
            if (input.value.length === 1) {
                let nextInput = input.nextElementSibling;
                if (nextInput) nextInput.focus();
            }
        }
    </script>
</body>
</html>
