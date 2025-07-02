<?php
require_once('../includes/db.php');
session_start();

$message = '';

// Si ya está autenticado, redirige
if (isset($_SESSION['user'])) {
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // A03: SQL Injection
    $query = "SELECT * FROM usuarios WHERE username = '$user' AND password = '$pass'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 1) {
        $_SESSION['user'] = $user;
        header("Location: admin.php");
        exit;
    } else {
        $message = "<div class='alert alert-danger'>Credenciales inválidas</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login - Clindata</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="#">Clindata App</a>
  </div>
</nav>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="card-title text-center mb-4">Iniciar sesión</h4>

          <?= $message ?>

          <form method="POST">
            <div class="mb-3">
              <label for="username" class="form-label">Usuario</label>
              <input type="text" class="form-control" id="username" name="username" required>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Clave</label>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary">Acceder</button>
            </div>
          </form>
        </div>
      </div>
      <p class="text-muted mt-3 text-center">App vulnerable - solo para pruebas</p>
    </div>
  </div>
</div>

</body>
</html>
