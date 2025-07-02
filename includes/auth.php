<?php
session_start();

// A07: Authentication Failure - No expiración de sesión, sin token CSRF, sin regenerar ID de sesión
if (!isset($_SESSION['user'])) {
    echo "Acceso denegado. Debes iniciar sesión.";
    exit;
}

// A01: Broken Access Control - No se valida el rol del usuario
// Para forzar la vulnerabilidad, simplemente permite el acceso sin validación
// Para simular protección fallida, puedes descomentar esto y modificarlo:

/*
if ($_SESSION['user'] !== 'admin') {
    echo "Acceso restringido solo a administradores.";
    exit;
}
*/

?>
