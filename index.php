<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'cliente') {header("Location: dashboardcliente.php");}
    elseif ($_SESSION['user_type'] === 'arrendador') {header("Location: dashboardarrendador.php");}
    else {header("Location: index.php");}
    exit;
}
$host = "127.0.0.1";$usuario = "root";$clave = "1234";$bd = "arriendo_herramientas";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {$conexion = new mysqli($host, $usuario, $clave, $bd);$conexion->set_charset("utf8mb4");}
catch (mysqli_sql_exception $e) {die("Error de conexión a la base de datos. Por favor, inténtalo de nuevo más tarde.");}
$mensaje = "";$mensaje_tipo = "danger";
$email_value = "";
if (isset($_SESSION['registration_success'])) {$mensaje = $_SESSION['registration_success'];$mensaje_tipo = "success";unset($_SESSION['registration_success']);}
if (isset($_POST['login'])) {
    $email = $_POST['email'];$password = $_POST['password'];
    $email_value = htmlspecialchars($email);
    $stmt = $conexion->prepare("SELECT id, nombre, password, tipo_usuario, activo FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);$stmt->execute();$stmt->store_result();
    $stmt->bind_result($user_id, $user_name, $hashed_password, $user_type, $activo);$stmt->fetch();
    if ($stmt->num_rows == 1) {
        if (password_verify($password, $hashed_password)) {
            if ($activo) {
                $_SESSION['user_id'] = $user_id;$_SESSION['user_name'] = $user_name;$_SESSION['user_type'] = $user_type;
                if ($user_type === 'cliente') {header("Location: dashboardcliente.php");}
                elseif ($user_type === 'arrendador') {header("Location: dashboardarrendador.php");}
                else {$mensaje = "Tipo de usuario desconocido.";session_unset();session_destroy();}
                exit;
            } else {$mensaje = "Tu cuenta está inactiva. Contacta al administrador.";}
        } else {$mensaje = "Credenciales incorrectas.";}
    } else {$mensaje = "Credenciales incorrectas.";}
    $stmt->close();
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Iniciar Sesión - Arriendo de Herramientas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body {
        background: linear-gradient(135deg, #4e54c8, #8f94fb);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
        padding: 20px;
        position: relative;
        overflow: hidden;
    }
    .shape {
        position: fixed;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.12);
        filter: blur(60px);
        animation: float 12s ease-in-out infinite;
        z-index: 0;
    }
    .shape.shape1 { width: 350px; height: 350px; top: -100px; left: -120px; animation-delay: 0s; }
    .shape.shape2 { width: 300px; height: 300px; bottom: -90px; right: -100px; background: rgba(255, 255, 255, 0.08); animation-delay: 6s; }
    .shape.shape3 { width: 250px; height: 250px; top: 40%; left: -80px; background: rgba(255, 255, 255, 0.07); animation-delay: 3s; }
    .shape.shape4 { width: 150px; height: 150px; bottom: 10%; left: 50%; background: rgba(255, 255, 255, 0.05); border-radius: 20% 80% 50% 50% / 50% 20% 80% 50%; animation-delay: 8s; filter: blur(30px); transform: translateX(-50%); }
    .shape-polygon { position: fixed; width: 200px; height: 200px; background: rgba(255, 255, 255, 0.1); clip-path: polygon(50% 0%, 90% 25%, 90% 75%, 50% 100%, 10% 75%, 10% 25%); filter: blur(40px); animation: floatRotate 14s linear infinite; z-index: 0; }
    .shape-polygon.shape5 { top: 15%; right: 20%; animation-delay: 0s; }
    .shape-polygon.shape6 { bottom: 20%; left: 25%; animation-delay: 7s; width: 180px; height: 180px; background: rgba(255, 255, 255, 0.08); clip-path: polygon(50% 0%, 85% 15%, 100% 50%, 85% 85%, 50% 100%, 15% 85%, 0% 50%, 15% 15%); border-radius: 15px; }
    @keyframes float { 0%, 100% { transform: translateY(0) translateX(0);} 50% { transform: translateY(25px) translateX(15px);} }
    @keyframes floatRotate { 0% { transform: translateY(0) rotate(0deg);} 50% { transform: translateY(20px) rotate(180deg);} 100% { transform: translateY(0) rotate(360deg);} }
    #page-wrapper.fade-in { animation: fadeIn 0.5s forwards; }
    #page-wrapper.fade-out { animation: fadeOut 0.5s forwards; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
    .alert { position: absolute; top: 20px; width: 90%; max-width: 400px; z-index: 1000; text-align: center; }
    .form-container { background-color: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 100%; max-width: 400px; text-align: center; margin-top: auto; margin-bottom: auto; position: relative; z-index: 1; }
    .form-title { margin-bottom: 30px; color: #4e54c8; font-weight: 700; }
    .form-label { text-align: left; display: block; margin-bottom: 8px; font-weight: 500; color: #495057; }
    .form-control { border-radius: 5px; padding: 10px 15px; }
    .form-control:focus { border-color: #8f94fb; box-shadow: 0 0 0 .25rem rgba(143,148,251,.25); }
    input[type="submit"] { background-color: #4e54c8; color: #fff; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 1.1rem; font-weight: 600; width: 100%; transition: background-color .3s ease; }
    input[type="submit"]:hover { background-color: #3a3f9e; }
    .register-text { margin-top: 20px; font-size: 1rem; color: #6c757d; }
    .register-text a { color: #4e54c8; text-decoration: none; font-weight: 600; }
    .register-text a:hover { text-decoration: underline; }
    footer { margin-top: 20px; padding: 20px 0; color: rgba(255,255,255,.7); font-size: .9rem; width: 100%; text-align: center; position: relative; z-index: 1; }
    .logo-img { width: 120px; height: auto; margin-bottom: 20px; }
    @media (max-width: 576px) {
        body { padding: 10px; }
        .form-container { padding: 25px; }
        .alert { top: 10px; width: 95%; }
        .logo-img { width: 100px; }
    }
</style>
</head>
<body>
    <div id="page-wrapper" class="fade-in">
        <div class="shape shape1"></div>
        <div class="shape shape2"></div>
        <div class="shape shape3"></div>
        <div class="shape shape4"></div>
        <div class="shape-polygon shape5"></div>
        <div class="shape-polygon shape6"></div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <img src="logo.png" alt="Logo" class="logo mb-3" style="max-width: 200px; height: auto;">
            <h2 class="form-title">Iniciar sesión</h2>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email_login" class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" id="email_login" name="email" required value="<?= $email_value ?>">
                </div>
                <div class="mb-3">
                    <label for="password_login" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password_login" name="password" required>
                </div>
                <input type="submit" name="login" value="Ingresar">
            </form>
            <div class="register-text">
                ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a>
            </div>
        </div>
        <footer>
            &copy; <?= date("Y") ?> Arriendo de Herramientas. Todos los derechos reservados.
        </footer>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
