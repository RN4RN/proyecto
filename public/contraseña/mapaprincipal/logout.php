<?php
session_start();
session_destroy();
header("Location: http://localhost/nuevo/contrase%C3%B1a/index.php");
exit();
?>