<?php
session_start();

// --- START: Corrected code for redirection if already logged in ---
if (isset($_SESSION['user_id'])) {
    // Redirect based on user_type if already logged in
    if ($_SESSION['user_type'] === 'cliente') {
        header("Location: dashboardcliente.php");
    } elseif ($_SESSION['user_type'] === 'arrendador') {
        header("Location: dashboardarrendador.php");
    } else {
        // Fallback for unknown user types or if user_type isn't set (shouldn't happen if login sets it)
        header("Location: index.php"); // Or a generic error/info page
    }
    exit;
}
// --- END: Corrected code for redirection ---

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

// Store email if there was an error to repopulate, but never the password
$email_value = ""; 

// Login
if (isset($_POST['login'])) {
    $email = $conexion->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // Keep the email value to pre-fill the form if login fails
    $email_value = htmlspecialchars($email); 

    // Using prepared statement for security
    $stmt = $conexion->prepare("SELECT id, nombre, password, tipo_usuario, activo FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (!$user['activo']) {
            $mensaje = "Cuenta inactiva. Contacta con el administrador.";
        } elseif (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_type'] = $user['tipo_usuario']; // This is correctly set

            // --- START: Redirection after successful login ---
            if ($_SESSION['user_type'] === 'cliente') {
                header("Location: dashboardcliente.php");
            } elseif ($_SESSION['user_type'] === 'arrendador') {
                header("Location: dashboardarrendador.php");
            } else {
                // Default or fallback redirection if user type is unknown
                header("Location: index.php?error=unknown_user_type");
            }
            // --- END: Redirection after successful login ---
            exit;
        } else {
            $mensaje = "Contraseña incorrecta.";
        }
    } else {
        $mensaje = "Usuario no encontrado.";
    }
    $stmt->close(); // Close the statement
}
$conexion->close(); // Close the connection
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Arriendo de Herramientas - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        /* Copiar el mismo estilo CSS del ejemplo que proporcionaste */
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
        .register-text {
            max-width: 480px;
            margin: 10px auto 40px auto;
            text-align: center;
            font-weight: 500;
            color: #4e54c8;
        }
        .register-text a {
            color: #3b3f99;
            font-weight: 700;
            text-decoration: none;
        }
        .register-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="hero">
        <div class="container">
            <h1>Bienvenido al Arriendo de Herramientas</h1>
            <p>Inicia sesión para comenzar a usar la plataforma.</p>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <h2 class="form-title">Iniciar sesión</h2>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="email_login" class="form-label">Correo electrónico:</label>
                <input type="email" class="form-control" name="email" id="email_login" value="<?= $email_value ?>" required autocomplete="email">
            </div>

            <div class="mb-3">
                <label for="password_login" class="form-label">Contraseña:</label>
                <input type="password" class="form-control" name="password" id="password_login" required autocomplete="new-password"> 
            </div>

            <input type="submit" name="login" value="Ingresar">
        </form>
    </div>

    <div class="register-text">
        ¿No tienes cuenta? <a href="register.php">Haz clic aquí para registrarte</a>
    </div>

    <footer class="text-center mt-5 mb-3 text-muted">
        <p>&copy; <?= date("Y") ?> Arriendo de Herramientas. Todos los derechos reservados.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>