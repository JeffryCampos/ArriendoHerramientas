<?php
$host = "127.0.0.1";$usuario = "root";$clave = "1234";$bd = "arriendo_herramientas";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {$conexion = new mysqli($host, $usuario, $clave, $bd);$conexion->set_charset("utf8mb4");}
catch (mysqli_sql_exception $e) {die("Error de conexión a la base de datos.");}
$mensaje = "";$mensaje_tipo = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];$email = $_POST['email'];$password = $_POST['password'];$tipo_usuario = $_POST['tipo_usuario'];
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt_check_email = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt_check_email->bind_param("s", $email);$stmt_check_email->execute();$stmt_check_email->store_result();
    if ($stmt_check_email->num_rows > 0) {$mensaje = "El correo electrónico ya está registrado.";$mensaje_tipo = "danger";}
    else {
        $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, email, password_hash, tipo_usuario) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("ssss", $nombre, $email, $password_hash, $tipo_usuario);
        if ($stmt_insert->execute()) {
            if ($tipo_usuario === 'cliente') {header("Location: index.php?registro=exito");}
            else {header("Location: index.php");}
            exit;
        } else {$mensaje = "Error al registrar el usuario: " . $stmt_insert->error;$mensaje_tipo = "danger";}
        $stmt_insert->close();
    }
    $stmt_check_email->close();
}
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Registrarse - Arriendo de Herramientas</title><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {background: linear-gradient(to right, #6a11cb, #2575fc);display: flex;justify-content: center;align-items: center;min-height: 100vh;margin: 0;font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;}
        .register-container {background-color: #fff;padding: 40px;border-radius: 10px;box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);width: 100%;max-width: 450px;text-align: center;}
        .register-container h2 {margin-bottom: 30px;color: #333;font-weight: 700;}
        .form-floating label {color: #6c757d;}
        .form-control:focus, .form-select:focus {box-shadow: 0 0 0 0.25rem rgba(45, 122, 237, 0.25);border-color: #2575fc;}
        .btn-primary {background-color: #2575fc;border-color: #2575fc;padding: 12px 0;font-size: 1.1rem;font-weight: 600;margin-top: 20px;}
        .btn-primary:hover {background-color: #1a5bbd;border-color: #1a5bbd;}
        .mt-3 a {color: #2575fc;text-decoration: none;}
        .mt-3 a:hover {text-decoration: underline;}
        .alert {margin-top: 20px;}
    </style>
</head>
<body>
    <div class="register-container">
    
        <?php if ($mensaje): ?><div class="alert alert-<?= $mensaje_tipo ?>"><?= $mensaje ?></div><?php endif; ?>
        <form action="register.php" method="POST">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Tu Nombre Completo" required>
                <label for="nombre">Nombre Completo</label>
            </div>
            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                <label for="email">Correo Electrónico</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                <label for="password">Contraseña</label>
            </div>
            <div class="form-floating mb-3">
                <select class="form-select" id="tipo_usuario" name="tipo_usuario" required>
                    <option value="" disabled selected>Selecciona tu tipo de usuario</option>
                    <option value="cliente">Cliente</option>
                    <option value="arrendador">Arrendador</option>
                </select>
                <label for="tipo_usuario">Tipo de Usuario</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Registrarse</button>
        </form>
        <p class="mt-3">¿Ya tienes una cuenta? <a href="index.php">Inicia Sesión</a></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>