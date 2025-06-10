<?php
session_start();

$host = "127.0.0.1";
$usuario = "root";
$clave = "1234";
$bd = "arriendo_herramientas";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion = new mysqli($host, $usuario, $clave, $bd);
    $conexion->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Error de conexión a la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

$mensaje = "";
$mensaje_tipo = "";

$nombre_value = "";
$email_value = "";
$selected_tipo_usuario = "cliente";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $tipo_usuario = in_array($_POST['tipo_usuario'], ['cliente', 'arrendador']) ? $_POST['tipo_usuario'] : 'cliente';

    $nombre_value = htmlspecialchars($nombre);
    $email_value = htmlspecialchars($email);
    $selected_tipo_usuario = htmlspecialchars($tipo_usuario);

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "El formato del correo electrónico no es válido.";
        $mensaje_tipo = "danger";
    } else {
        $stmt_check_email = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();

        if ($stmt_check_email->num_rows > 0) {
            $mensaje = "El correo electrónico ya está registrado.";
            $mensaje_tipo = "danger";
        } else {
            $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, email, password, tipo_usuario) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $nombre, $email, $password_hash, $tipo_usuario);

            if ($stmt_insert->execute()) {
                $_SESSION['registration_success'] = "¡Registro exitoso! Por favor, inicia sesión.";
                header("Location: index.php");
                exit;
            } else {
                $mensaje = "Error al registrar el usuario: " . $stmt_insert->error;
                $mensaje_tipo = "danger";
            }

            $stmt_insert->close();
        }

        $stmt_check_email->close();
    }
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - Arriendo de Herramientas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }
        /* Figuras decorativas */
        .shape {
            position: fixed;
            border-radius: 50%;
            background: rgba(255,255,255,0.12);
            filter: blur(60px);
            animation: float 12s ease-in-out infinite;
            z-index: 0;
        }
        .shape.shape1 {
            width: 350px;
            height: 350px;
            top: -100px;
            left: -120px;
            animation-delay: 0s;
        }
        .shape.shape2 {
            width: 300px;
            height: 300px;
            bottom: -90px;
            right: -100px;
            background: rgba(255, 255, 255, 0.08);
            animation-delay: 6s;
        }
        .shape.shape3 {
            width: 250px;
            height: 250px;
            top: 40%;
            left: -80px;
            background: rgba(255, 255, 255, 0.07);
            animation-delay: 3s;
        }
        .shape.shape4 {
            width: 150px;
            height: 150px;
            bottom: 10%;
            left: 50%;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20% 80% 50% 50% / 50% 20% 80% 50%;
            animation-delay: 8s;
            filter: blur(30px);
            transform: translateX(-50%);
        }
        .shape-polygon {
            position: fixed;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            clip-path: polygon(50% 0%, 90% 25%, 90% 75%, 50% 100%, 10% 75%, 10% 25%);
            filter: blur(40px);
            animation: floatRotate 14s linear infinite;
            z-index: 0;
        }
        .shape-polygon.shape5 {
            top: 15%;
            right: 20%;
            animation-delay: 0s;
        }
        .shape-polygon.shape6 {
            bottom: 20%;
            left: 25%;
            animation-delay: 7s;
            width: 180px;
            height: 180px;
            background: rgba(255, 255, 255, 0.08);
            clip-path: polygon(50% 0%, 85% 15%, 100% 50%, 85% 85%, 50% 100%, 15% 85%, 0% 50%, 15% 15%);
            border-radius: 15px;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0);}
            50% { transform: translateY(25px) translateX(15px);}
        }
        @keyframes floatRotate {
            0% { transform: translateY(0) rotate(0deg);}
            50% { transform: translateY(20px) rotate(180deg);}
            100% { transform: translateY(0) rotate(360deg);}
        }

        .register-container {
            position: relative;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            padding: 40px 35px 35px 35px;
            max-width: 420px;
            width: 100%;
            z-index: 1;
            text-align: center;
        }
        .register-container h2 {
            color: #4e54c8;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 30px;
            margin-top: 20px;
        }
        .register-logo {
            max-width: 150px;
            margin: 0 auto 10px auto;
            display: block;
        }
        .form-floating label {
            color: #666;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1.5px solid #ccc;
            padding: 1rem 1rem 1rem 1rem;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4e54c8;
            box-shadow: 0 0 8px rgba(78, 84, 200, 0.6);
            outline: none;
        }
        .btn-primary {
            background: linear-gradient(90deg, #4e54c8, #8f94fb);
            border: none;
            padding: 14px;
            font-size: 1.15rem;
            font-weight: 700;
            border-radius: 12px;
            width: 100%;
            box-shadow: 0 8px 15px rgba(78, 84, 200, 0.3);
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #3a3f9e, #6f73d9);
            box-shadow: 0 10px 20px rgba(58, 63, 158, 0.6);
        }
        .alert {
            position: fixed;
            top: 25px;
            left: 50%;
            transform: translateX(-50%);
            max-width: 420px;
            width: 90%;
            z-index: 9999;
            border-radius: 12px;
            font-weight: 600;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .login-text {
            margin-top: 20px;
            font-size: 1rem;
            color: #555;
            text-align: center;
        }
        .login-text a {
            color: #4e54c8;
            font-weight: 600;
            text-decoration: none;
            transition: text-decoration 0.3s ease;
        }
        .login-text a:hover {
            text-decoration: underline;
        }
        @media (max-width: 576px) {
            .register-container {
                padding: 30px 25px;
            }
            .alert {
                max-width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="shape shape1"></div>
    <div class="shape shape2"></div>
    <div class="shape shape3"></div>
    <div class="shape shape4"></div>
    <div class="shape-polygon shape5"></div>
    <div class="shape-polygon shape6"></div>

    <main class="register-container" role="main" aria-label="Formulario de registro">
        <img src="logo.png" alt="Logo de la empresa" class="register-logo" />
        <h2>Crear cuenta</h2>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $mensaje_tipo; ?>" role="alert" aria-live="polite">
                <?= htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
            <div class="form-floating mb-3">
                <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Nombre completo" required value="<?= $nombre_value; ?>" aria-describedby="nombreHelp" />
                <label for="nombre">Nombre completo</label>
            </div>

            <div class="form-floating mb-3">
                <input type="email" name="email" id="email" class="form-control" placeholder="Correo electrónico" required value="<?= $email_value; ?>" aria-describedby="emailHelp" />
                <label for="email">Correo electrónico</label>
            </div>

            <div class="form-floating mb-3">
                <input type="password" name="password" id="password" class="form-control" placeholder="Contraseña" required aria-describedby="passwordHelp" />
                <label for="password">Contraseña</label>
            </div>

            <div class="form-floating mb-4">
                <select name="tipo_usuario" id="tipo_usuario" class="form-select" aria-label="Tipo de usuario">
                    <option value="cliente" <?= $selected_tipo_usuario === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                    <option value="arrendador" <?= $selected_tipo_usuario === 'arrendador' ? 'selected' : ''; ?>>Arrendador</option>
                </select>
                <label for="tipo_usuario">Tipo de usuario</label>
            </div>

            <button type="submit" class="btn btn-primary" aria-label="Registrar nueva cuenta">Registrarse</button>
        </form>

        <p class="login-text">¿Ya tienes una cuenta? <a href="index.php">Inicia sesión</a></p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
