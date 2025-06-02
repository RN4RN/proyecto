<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';
include 'conexion.php'; 

echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $nombre= trim($_POST['nombre']);

    if (empty($email) || empty($nombre)) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Campos vacíos',
                    text: 'Debes ingresar tu correo y nombre de usuario.',
                    timer: 3000,
                    showConfirmButton: false
                });
            });
        </script>";
        exit;
    }

    $sql = "SELECT id_usuario FROM usuarios WHERE email = ? AND nombre = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ss", $email, $nombre);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Datos incorrectos',
                    text: 'Los datos ingresados no son correctos.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'http://localhost/nuevo/contrase%C3%B1a/olvidocontra.php';
                });
            });
        </script>";
        exit;
    }

    $codigo = rand(100000, 999999);

    $sql = "INSERT INTO tokens_recuperacion (email, codigo, expiracion) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE)) 
            ON DUPLICATE KEY UPDATE codigo = VALUES(codigo), expiracion = VALUES(expiracion)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $email, $codigo);
    $stmt->execute();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'grijalvauve@gmail.com';
        $mail->Password = 'ndrg qqgw qwwv tfnb'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('MtcTelecomunicaciones@gmail.com', 'Telecomunicaciones');
        $mail->addAddress($email);
        $mail->Subject = 'Codigo de Recuperacion - Telecomunicaciones';

        $logo_url = 'https://i.postimg.cc/QhMmKcpF/telecomunicaciqwqones.png';

        $mail->isHTML(true);
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; color: #333; padding: 40px; text-align: center; border-radius: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 600px; margin: auto;'>
    <img src='$logo_url' width='180' alt='Logo de Telecomunicaciones' style='margin-bottom: 20px;'>
    <h1 style='color: #0056b3; font-size: 28px; margin-bottom: 10px;'>Restablecimiento de Acceso</h1>
    <p style='font-size: 18px; margin-bottom: 30px;'>
        Hemos recibido una solicitud para recuperar el acceso a tu cuenta. Para completar el proceso, por favor utiliza el siguiente código de verificación:
    </p>
    <div style='background-color: #fff3f3; padding: 20px; border-radius: 8px; display: inline-block; margin-bottom: 30px;'>
        <h2 style='color: #d9534f; font-size: 36px; margin: 0;'>$codigo</h2>
    </div>
    <p style='font-size: 16px; margin-bottom: 10px;'>Este código es válido por <strong>5 minutos</strong>.</p>
    <p style='font-size: 16px; margin-bottom: 30px;'>Ingresa este código en el formulario correspondiente para continuar con el proceso.</p>
    <hr style='margin: 40px 0; border: none; border-top: 1px solid #ddd;'>
    <p style='font-size: 16px;'>Si tú no solicitaste este código, puedes ignorar este mensaje de forma segura.</p>
    <br>
    <p style='font-size: 16px;'>Atentamente,</p>
    <p style='font-weight: bold; font-size: 16px;'>Equipo de Soporte de Telecomunicaciones</p>
    <em style='font-size:10px; color:red;'>PowerBy <a>Rncorp</a></em>
</div>
";

        $mail->send();

        header("Location: verificaciondecontra.php?email=" . urlencode($email));
        exit();
    } catch (Exception $e) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al enviar',
                    text: 'No se pudo enviar el correo. {$mail->ErrorInfo}',
                    timer: 3000,
                    showConfirmButton: false
                });
            });
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificacion de usuario</title>
    <link rel="shortcut icon" href="./mapaprincipal/ico.ico" />
    <link rel="stylesheet" href="styles1.css">
</head>
<body>
    <div class="fondo"></div>
    <div class="form">
        <center><h2>Verificar Usuario</h2>

        <form method="POST">
        <input class="usuario" type="text" name="nombre" placeholder="Nombre de Usuario" required> <br>
            <input class="contra" type="email" name="email" placeholder="Correo Electrónico" required> <br>
            <button type="submit">Enviar Código</button>
        </form>
    </div>
    </center>
    <footer class="footer">
        © 2025 Todos los derechos reservados | <a href="#">RNcorp</a> | <a href="#">Términos de uso</a>
    </footer>
</body>
</html>
