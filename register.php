<?php
session_start();

$host = "127.0.0.1";
$usuario = "root";
$clave = "1234";
$bd = "arriendo_herramientas";
$conexion = new mysqli($host, $usuario, $clave, $bd);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$mensaje = "";
$mensaje_tipo = "danger";

// Initialize variables to hold form values. They will be empty on first load.
// If there's an error, these will be populated with the submitted values.
$nombre_value = "";
$email_value = "";
$selected_tipo_usuario = "cliente"; // Default selected value for the dropdown

// Handle Registration Form Submission
if (isset($_POST['register'])) {
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $email = $conexion->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    // Ensure tipo_usuario is one of the allowed values, default to 'cliente'
    $tipo_usuario = in_array($_POST['tipo_usuario'], ['cliente', 'arrendador']) ? $_POST['tipo_usuario'] : 'cliente';

    // Populate values back to form for user convenience in case of error
    $nombre_value = htmlspecialchars($nombre);
    $email_value = htmlspecialchars($email);
    $selected_tipo_usuario = htmlspecialchars($tipo_usuario);

    if (empty($nombre) || empty($email) || empty($password)) {
        $mensaje = "Por favor, completa todos los campos.";
    } else {
        // Check if email already exists
        $stmt_check_email = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $result_check_email = $stmt_check_email->get_result();

        if ($result_check_email->num_rows > 0) {
            $mensaje = "Este correo electrónico ya está registrado. Intenta con otro.";
        } else {
            // Hash the password before storing
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, email, password, tipo_usuario) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $nombre, $email, $hash, $tipo_usuario);

            if ($stmt_insert->execute()) {
                // Registration successful, redirect to login page with success message
                header("Location: index.php?registro=exito");
                exit;
            } else {
                $mensaje = "Error al registrar: " . $stmt_insert->error;
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
    <meta charset="UTF-8" />
    <title>Arriendo de Herramientas - Registro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        /* Copiar el mismo estilo CSS que en index.php */
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .hero {
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: white;
            padding: 60px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 50px;
            text-align: center;
        }
        .hero h1 {
            font-weight: 700;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
            font-size: 2.8rem;
        }
        .hero p {
            font-size: 1.3rem;
            opacity: 0.85;
            margin-top: 12px;
        }
        .form-container {
            max-width: 480px;
            margin: auto;
            background: white;
            padding: 30px 35px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        .form-title {
            font-weight: 600;
            color: #4e54c8;
            margin-bottom: 25px;
            text-align: center;
        }
        label {
            font-weight: 500;
        }
        input[type="submit"] {
            background: #4e54c8;
            border: none;
            padding: 10px 0;
            font-weight: 600;
            border-radius: 50px;
            width: 100%;
            box-shadow: 0 4px 10px rgba(78, 84, 200, 0.4);
            transition: background 0.3s ease;
            color: white;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background: #3b3f99;
            box-shadow: 0 6px 15px rgba(59, 63, 153, 0.6);
        }
        .alert {
            max-width: 480px;
            margin: 20px auto;
            text-align: center;
            font-weight: 600;
        }
        .login-text {
            max-width: 480px;
            margin: 10px auto 40px auto;
            text-align: center;
            font-weight: 500;
            color: #4e54c8;
        }
        .login-text a {
            color: #3b3f99;
            font-weight: 700;
            text-decoration: none;
        }
        .login-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="hero">
        <div class="container">
            <h1>Regístrate en Arriendo de Herramientas</h1>
            <p>Completa el formulario para crear tu cuenta.</p>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <h2 class="form-title">Registrarse</h2>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre completo:</label>
                <input type="text" class="form-control" name="nombre" id="nombre" value="<?= $nombre_value ?>" required autocomplete="name">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico:</label>
                <input type="email" class="form-control" name="email" id="email" value="<?= $email_value ?>" required autocomplete="email">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Contraseña:</label>
                <input type="password" class="form-control" name="password" id="password" required autocomplete="new-password">
            </div>

            <div class="mb-3">
                <label for="tipo_usuario" class="form-label">Tipo de usuario:</label>
                <select class="form-select" name="tipo_usuario" id="tipo_usuario">
                    <option value="cliente" <?= ($selected_tipo_usuario === 'cliente') ? 'selected' : '' ?>>Cliente</option>
                    <option value="arrendador" <?= ($selected_tipo_usuario === 'arrendador') ? 'selected' : '' ?>>Arrendador</option>
                </select>
            </div>

            <input type="submit" name="register" value="Registrar">
        </form>
    </div>

    <div class="login-text">
        ¿Ya tienes cuenta? <a href="index.php">Inicia sesión aquí</a>
    </div>

    <footer class="text-center mt-5 mb-3 text-muted">
        <p>&copy; <?= date("Y") ?> Arriendo de Herramientas. Todos los derechos reservados.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>