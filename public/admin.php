<?php
require_once('../includes/auth.php');
require_once('../includes/db.php');

// Procesar búsqueda si se envió
$busqueda = '';
$resultados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $busqueda = $_POST['busqueda'];

    // Vulnerabilidad A03: SQL Injection
    $query = "SELECT * FROM usuarios WHERE username LIKE '%$busqueda%'";
    $resultado = mysqli_query($conn, $query);

    if ($resultado) {
        while ($fila = mysqli_fetch_assoc($resultado)) {
            $resultados[] = $fila;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Administrativo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="#">Clindata Panel</a>
    <div class="d-flex">
      <span class="navbar-text text-white me-3">Sesión: <?= $_SESSION['user'] ?></span>
      <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar sesión</a>
    </div>
  </div>
</nav>

<div class="container mt-4">
  <h2>Bienvenido <?= $_SESSION['user'] ?></h2>
  <p class="text-muted">Este panel simula funciones administrativas.</p>

  <hr>

  <h5>Búsqueda de usuarios (vulnerable a SQL Injection)</h5>
  <form method="POST" class="row g-2 mb-4">
    <div class="col-md-8">
      <input type="text" name="busqueda" class="form-control" placeholder="Buscar por nombre de usuario" value="<?= htmlspecialchars($busqueda) ?>">
    </div>
    <div class="col-md-4">
      <button type="submit" class="btn btn-primary">Buscar</button>
    </div>
  </form>

  <?php if (!empty($resultados)): ?>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>ID</th>
          <th>Usuario</th>
          <th>Rol</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resultados as $fila): ?>
          <tr>
            <td><?= $fila['id'] ?></td>
            <td><?= $fila['username'] ?></td>
            <td><?= $fila['role'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <p class="text-danger">No se encontraron resultados.</p>
  <?php endif; ?>
</div>

</body>
</html>
