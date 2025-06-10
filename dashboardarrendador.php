<?php
session_start();

// Redirigir a la página de inicio de sesión si el usuario no ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// --- Restringir el acceso solo al tipo de usuario 'arrendador' ---
if ($_SESSION['user_type'] !== 'arrendador') {
    // Si no es un arrendador, redirigirlos.
    if ($_SESSION['user_type'] === 'cliente') {
        header("Location: dashboardcliente.php"); // Redirigir al dashboard del cliente
    } else {
        header("Location: index.php"); // Redirección por defecto para tipos de usuario desconocidos/inválidos
    }
    exit;
}
// --- FIN de la Restricción ---

// Encabezados para prevenir el almacenamiento en caché y "bloquear" el botón de retroceso a páginas seguras anteriores
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Una fecha en el pasado

// --- Configuración de la Conexión a la Base de Datos ---
$host = "127.0.0.1";
$usuario = "root";
$clave = "1234";
$bd = "arriendo_herramientas";

// Habilitar el reporte de errores de MySQLi como excepciones para un mejor manejo
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion = new mysqli($host, $usuario, $clave, $bd);
    $conexion->set_charset("utf8mb4"); // Establecer el juego de caracteres para un manejo adecuado de caracteres especiales
} catch (mysqli_sql_exception $e) {
    // En un entorno de producción, podrías loggear el error en lugar de mostrarlo
    // error_log("Error de conexión a la base de datos: " . $e->getMessage());
    die("Error de conexión a la base de datos. Por favor, inténtalo de nuevo más tarde.");
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$mensaje = "";
$mensaje_tipo = "";

// --- Manejo de mensajes temporales después de una redirección (Patrón PRG) ---
if (isset($_SESSION['temp_message'])) {
    $mensaje = $_SESSION['temp_message'];
    $mensaje_tipo = $_SESSION['temp_message_type'];
    unset($_SESSION['temp_message']); // Eliminar el mensaje de la sesión después de mostrarlo
    unset($_SESSION['temp_message_type']); // Eliminar el tipo de mensaje de la sesión
}

// --- Lógica para agregar nueva herramienta ---
if (isset($_POST['add_tool'])) {
    // Validación básica de campos obligatorios
    if (empty($_POST['nombre']) || empty($_POST['descripcion']) || empty($_POST['precio_dia'])) {
        $_SESSION['temp_message'] = "Todos los campos obligatorios deben ser completados.";
        $_SESSION['temp_message_type'] = "danger";
    } else {
        $nombre = trim($_POST['nombre']); // Limpiar espacios en blanco
        $descripcion = trim($_POST['descripcion']); // Limpiar espacios en blanco
        $precio_dia = filter_var($_POST['precio_dia'], FILTER_VALIDATE_FLOAT); // Sanear y validar como flotante

        // Validar que el precio sea un número válido y mayor que cero
        if ($precio_dia === false || $precio_dia <= 0) {
            $_SESSION['temp_message'] = "El precio por día debe ser un número válido mayor que cero.";
            $_SESSION['temp_message_type'] = "danger";
        } else {
            $disponible = isset($_POST['disponible']) ? 1 : 0; // Valor del checkbox

            $upload_dir = 'uploads/'; // Directorio donde se almacenarán las imágenes
            $image_path = ''; // Ruta de la imagen por defecto (vacía)

            // Manejo de la subida de imagen
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['imagen']['tmp_name'];
                $file_name = basename($_FILES['imagen']['name']);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                $max_file_size = 5 * 1024 * 1024; // Límite de tamaño: 5MB

                if (!in_array($file_ext, $allowed_ext)) {
                    $_SESSION['temp_message'] = "Tipo de archivo no permitido. Solo se aceptan JPG, JPEG, PNG, GIF.";
                    $_SESSION['temp_message_type'] = "danger";
                } elseif ($_FILES['imagen']['size'] > $max_file_size) {
                    $_SESSION['temp_message'] = "El tamaño del archivo excede el límite permitido (5MB).";
                    $_SESSION['temp_message_type'] = "danger";
                } else {
                    // Generar un nombre de archivo único para evitar conflictos
                    $new_file_name = uniqid('tool_', true) . '.' . $file_ext;
                    $destination_path = $upload_dir . $new_file_name;

                    // Asegurarse de que el directorio de subida exista
                    if (!is_dir($upload_dir)) {
                        // Crear el directorio con permisos 0755 (dueño rwx, grupo rx, otros rx)
                        mkdir($upload_dir, 0755, true);
                    }

                    if (move_uploaded_file($file_tmp_name, $destination_path)) {
                        $image_path = $destination_path; // Almacenar la ruta relativa en la DB
                    } else {
                        $_SESSION['temp_message'] = "Error al subir la imagen. Código: " . $_FILES['imagen']['error'];
                        $_SESSION['temp_message_type'] = "danger";
                    }
                }
            } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] != UPLOAD_ERR_NO_FILE) {
                // Manejar otros errores de subida (ej. archivo muy grande por php.ini)
                $msg_error_upload = "Error al subir la imagen: ";
                switch ($_FILES['imagen']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $msg_error_upload .= "El archivo excede el tamaño máximo permitido.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $msg_error_upload .= "La subida fue parcial.";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $msg_error_upload .= "Falta una carpeta temporal.";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $msg_error_upload .= "No se pudo escribir el archivo en el disco.";
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $msg_error_upload .= "Una extensión de PHP detuvo la subida del archivo.";
                        break;
                    default:
                        $msg_error_upload .= "Error desconocido.";
                        break;
                }
                $_SESSION['temp_message'] = $msg_error_upload;
                $_SESSION['temp_message_type'] = "danger";
            }

            // Solo proceder con la inserción si no hubo errores de validación o subida de imagen
            if (empty($_SESSION['temp_message'])) { // Verificar si no hay un mensaje de error previo
                // Usar sentencias preparadas para mayor seguridad contra inyecciones SQL
                $stmt = $conexion->prepare("INSERT INTO herramientas (id_usuario, nombre, descripcion, precio_dia, imagen, disponible) VALUES (?, ?, ?, ?, ?, ?)");
                // "issdsi" -> i:integer, s:string, s:string, d:double/float, s:string, i:integer
                $stmt->bind_param("issdsi", $user_id, $nombre, $descripcion, $precio_dia, $image_path, $disponible);

                if ($stmt->execute()) {
                    // Éxito: Guardar mensaje en sesión y redirigir
                    $_SESSION['temp_message'] = "Herramienta agregada exitosamente.";
                    $_SESSION['temp_message_type'] = "success";
                    header("Location: dashboardarrendador.php"); // Redirección limpia (PRG)
                    exit; // Detener la ejecución para asegurar la redirección
                } else {
                    // Error en la inserción: Asignar mensaje de error
                    $_SESSION['temp_message'] = "Error al agregar la herramienta: " . $stmt->error;
                    $_SESSION['temp_message_type'] = "danger";
                }
                $stmt->close();
            }
        }
    }
    header("Location: dashboardarrendador.php"); // Redirigir para mostrar mensajes
    exit;
}

// --- Lógica para actualizar herramienta ---
if (isset($_POST['update_tool'])) {
    $tool_id_to_update = filter_var($_POST['tool_id'], FILTER_VALIDATE_INT);

    if ($tool_id_to_update === false) {
        $_SESSION['temp_message'] = "ID de herramienta inválido para actualizar.";
        $_SESSION['temp_message_type'] = "danger";
    } else {
        if (empty($_POST['nombre']) || empty($_POST['descripcion']) || empty($_POST['precio_dia'])) {
            $_SESSION['temp_message'] = "Todos los campos obligatorios deben ser completados para la actualización.";
            $_SESSION['temp_message_type'] = "danger";
        } else {
            $nombre = trim($_POST['nombre']);
            $descripcion = trim($_POST['descripcion']);
            $precio_dia = filter_var($_POST['precio_dia'], FILTER_VALIDATE_FLOAT);

            if ($precio_dia === false || $precio_dia <= 0) {
                $_SESSION['temp_message'] = "El precio por día debe ser un número válido mayor que cero para la actualización.";
                $_SESSION['temp_message_type'] = "danger";
            } else {
                $disponible = isset($_POST['disponible']) ? 1 : 0;
                $current_image_path = $_POST['current_image_path'] ?? ''; // Obtener la ruta de la imagen actual si no se sube una nueva

                $image_path = $current_image_path; // Por defecto, mantener la imagen actual

                // Manejo de la subida de nueva imagen si se proporciona una
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
                    $file_tmp_name = $_FILES['imagen']['tmp_name'];
                    $file_name = basename($_FILES['imagen']['name']);
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                    $max_file_size = 5 * 1024 * 1024;

                    if (!in_array($file_ext, $allowed_ext)) {
                        $_SESSION['temp_message'] = "Tipo de archivo no permitido. Solo se aceptan JPG, JPEG, PNG, GIF.";
                        $_SESSION['temp_message_type'] = "danger";
                    } elseif ($_FILES['imagen']['size'] > $max_file_size) {
                        $_SESSION['temp_message'] = "El tamaño del archivo excede el límite permitido (5MB).";
                        $_SESSION['temp_message_type'] = "danger";
                    } else {
                        // Generar un nombre de archivo único
                        $new_file_name = uniqid('tool_', true) . '.' . $file_ext;
                        $destination_path = $upload_dir . $new_file_name;

                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        if (move_uploaded_file($file_tmp_name, $destination_path)) {
                            $image_path = $destination_path;
                            // Eliminar la imagen antigua si existe y es diferente a la nueva
                            if (!empty($current_image_path) && file_exists($current_image_path) && is_file($current_image_path)) {
                                unlink($current_image_path);
                            }
                        } else {
                            $_SESSION['temp_message'] = "Error al subir la nueva imagen. Código: " . $_FILES['imagen']['error'];
                            $_SESSION['temp_message_type'] = "danger";
                        }
                    }
                } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] != UPLOAD_ERR_NO_FILE) {
                     // Manejar otros errores de subida
                    $msg_error_upload = "Error al subir la imagen: ";
                    switch ($_FILES['imagen']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $msg_error_upload .= "El archivo excede el tamaño máximo permitido.";
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $msg_error_upload .= "La subida fue parcial.";
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $msg_error_upload .= "Falta una carpeta temporal.";
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $msg_error_upload .= "No se pudo escribir el archivo en el disco.";
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $msg_error_upload .= "Una extensión de PHP detuvo la subida del archivo.";
                            break;
                        default:
                            $msg_error_upload .= "Error desconocido.";
                            break;
                    }
                    $_SESSION['temp_message'] = $msg_error_upload;
                    $_SESSION['temp_message_type'] = "danger";
                }

                if (empty($_SESSION['temp_message'])) {
                    $stmt = $conexion->prepare("UPDATE herramientas SET nombre = ?, descripcion = ?, precio_dia = ?, imagen = ?, disponible = ? WHERE id = ? AND id_usuario = ?");
                    $stmt->bind_param("ssdsiii", $nombre, $descripcion, $precio_dia, $image_path, $disponible, $tool_id_to_update, $user_id);

                    if ($stmt->execute()) {
                        $_SESSION['temp_message'] = "Herramienta actualizada exitosamente.";
                        $_SESSION['temp_message_type'] = "success";
                    } else {
                        $_SESSION['temp_message'] = "Error al actualizar la herramienta: " . $stmt->error;
                        $_SESSION['temp_message_type'] = "danger";
                    }
                    $stmt->close();
                }
            }
        }
    }
    header("Location: dashboardarrendador.php#my-tools"); // Redirigir y anclar a mis herramientas
    exit;
}


// --- Lógica para eliminar herramienta ---
if (isset($_GET['delete_tool_id'])) {
    $tool_id_to_delete = filter_var($_GET['delete_tool_id'], FILTER_VALIDATE_INT); // Sanear y validar el ID

    if ($tool_id_to_delete === false) {
        // ID inválido
        $_SESSION['temp_message'] = "ID de herramienta inválido.";
        $_SESSION['temp_message_type'] = "danger";
    } else {
        // Primero, obtener la ruta de la imagen para eliminar el archivo físico
        $stmt_get_image = $conexion->prepare("SELECT imagen FROM herramientas WHERE id = ? AND id_usuario = ?");
        $stmt_get_image->bind_param("ii", $tool_id_to_delete, $user_id);
        $stmt_get_image->execute();
        $result_image = $stmt_get_image->get_result();
        $image_row = $result_image->fetch_assoc();
        $stmt_get_image->close();

        if ($image_row && !empty($image_row['imagen'])) {
            $file_to_delete = $image_row['imagen'];
            // Verificar que el archivo existe y es un archivo (no un directorio) antes de intentar eliminar
            if (file_exists($file_to_delete) && is_file($file_to_delete)) {
                if (!unlink($file_to_delete)) {
                    // Loggear error si falla la eliminación del archivo, pero no detener la eliminación de la DB
                    error_log("Error al eliminar el archivo de imagen: " . $file_to_delete);
                }
            }
        }

        // Luego, eliminar el registro de la base de datos
        $stmt_delete = $conexion->prepare("DELETE FROM herramientas WHERE id = ? AND id_usuario = ?");
        $stmt_delete->bind_param("ii", $tool_id_to_delete, $user_id);
        if ($stmt_delete->execute()) {
            // Éxito: Guardar mensaje en sesión y redirigir
            $_SESSION['temp_message'] = "Herramienta eliminada exitosamente.";
            $_SESSION['temp_message_type'] = "success";
        } else {
            // Error en la eliminación: Guardar mensaje de error en sesión
            $_SESSION['temp_message'] = "Error al eliminar la herramienta: " . $stmt_delete->error;
            $_SESSION['temp_message_type'] = "danger";
        }
        $stmt_delete->close();
    }
    header("Location: dashboardarrendador.php#my-tools"); // Redirigir para aplicar PRG
    exit;
}

// --- Lógica para obtener las herramientas del arrendador actual ---
$mis_herramientas = [];
$sql_mis_herramientas = "SELECT id, nombre, descripcion, precio_dia, imagen, disponible
                            FROM herramientas
                            WHERE id_usuario = ?
                            ORDER BY fecha_publicacion DESC"; // Asumiendo que 'fecha_publicacion' existe en tu tabla
$stmt_mis_herramientas = $conexion->prepare($sql_mis_herramientas);
$stmt_mis_herramientas->bind_param("i", $user_id);
$stmt_mis_herramientas->execute();
$result_mis_herramientas = $stmt_mis_herramientas->get_result();

if ($result_mis_herramientas->num_rows > 0) {
    while ($row = $result_mis_herramientas->fetch_assoc()) {
        $mis_herramientas[] = $row;
    }
}
$stmt_mis_herramientas->close();

// --- Lógica para obtener las solicitudes de arriendo para las herramientas de este arrendador ---
$solicitudes_recibidas = [];
$sql_solicitudes = "SELECT s.id, h.nombre AS herramienta_nombre, u.nombre AS cliente_nombre,
                            s.fecha_inicio, s.fecha_fin, s.estado, s.fecha_solicitud, s.id_herramienta
                     FROM solicitudes s
                     JOIN herramientas h ON s.id_herramienta = h.id
                     JOIN usuarios u ON s.id_usuario = u.id
                     WHERE h.id_usuario = ?
                     ORDER BY s.fecha_solicitud DESC";
$stmt_solicitudes = $conexion->prepare($sql_solicitudes);
$stmt_solicitudes->bind_param("i", $user_id);
$stmt_solicitudes->execute();
$result_solicitudes = $stmt_solicitudes->get_result();

if ($result_solicitudes->num_rows > 0) {
    while ($row = $result_solicitudes->fetch_assoc()) {
        $solicitudes_recibidas[] = $row;
    }
}
$stmt_solicitudes->close();

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Arriendo de Herramientas - Dashboard Arrendador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            min-height: 100vh;
        }
        .hero {
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: white;
            padding: 40px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }
        .hero h1 {
            font-weight: 700;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
            font-size: 2.5rem;
        }
        .hero p {
            font-size: 1.2rem;
            opacity: 0.85;
            margin-top: 10px;
        }
        .alert {
            max-width: 90%;
            margin: 20px auto;
            text-align: center;
            font-weight: 600;
        }
        /* Sidebar styles */
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            padding-top: 20px;
            flex-shrink: 0;
            transition: all 0.3s ease;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar.collapsed {
            width: 80px;
        }
        .sidebar-header {
            padding: 10px 20px;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
        }
        .sidebar-header h3 {
            font-size: 1.5rem;
            margin-bottom: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-menu a {
            padding: 12px 20px;
            color: #adb5bd;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: background-color 0.2s, color 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #495057;
            color: white;
            border-left: 3px solid #007bff;
        }
        .sidebar-menu a i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        .sidebar.collapsed .sidebar-menu span {
            display: none;
        }
        .sidebar.collapsed .sidebar-header h3 {
            display: none;
        }
        .sidebar.collapsed .sidebar-menu a {
            justify-content: center;
            padding: 12px 0;
        }
        .sidebar.collapsed .sidebar-menu a i {
            margin-right: 0;
        }
        .toggle-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin: 15px auto;
            display: block;
            width: fit-content;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        /* Card styles for tools */
        .tool-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s ease-in-out;
        }
        .tool-card:hover {
            transform: translateY(-5px);
        }
        .tool-card img {
            max-height: 200px;
            width: 100%;
            object-fit: cover;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .tool-card .card-body {
            padding: 15px;
        }
        .tool-card .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #4e54c8;
        }
        .tool-card .card-text {
            font-size: 0.95rem;
            color: #6c757d;
        }
        .tool-card .card-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #28a745;
        }
        .tool-card .card-status {
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 5px;
        }
        .tool-card .card-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        /* Form styling */
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        .form-section h2 {
            color: #4e54c8;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Área Arrendador</h3>
        </div>
        <ul class="nav flex-column sidebar-menu">
            <li class="nav-item">
                <a class="nav-link active" href="#inicio"><i class="bi bi-house-door-fill"></i> <span>Inicio</span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#add-tool"><i class="bi bi-plus-circle"></i> <span>Publicar Herramienta</span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#my-tools"><i class="bi bi-tools"></i> <span>Mis Herramientas</span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#requests"><i class="bi bi-bell"></i> <span>Solicitudes Recibidas</span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#"><i class="bi bi-person-circle"></i> <span>Mi Perfil</span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> <span>Cerrar Sesión</span></a>
            </li>
        </ul>
        <button class="btn toggle-btn" id="sidebarToggle">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <div class="content">
        <div class="hero" id="inicio">
            <div class="container">
                <h1>¡Hola, <?= htmlspecialchars($user_name) ?>!</h1>
                <p>Gestiona tus herramientas y solicitudes de arriendo.</p>
            </div>
        </div>

        <?php if ($mensaje): // Mostrar mensaje de éxito o error ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <div class="container mt-4">
            <div class="form-section" id="tool-form-section">
                <h2 class="mb-4" id="form-title">Publicar Nueva Herramienta</h2>
                <form action="" method="POST" enctype="multipart/form-data" id="tool-form">
                    <input type="hidden" id="tool_id" name="tool_id" value="">
                    <input type="hidden" id="current_image_path" name="current_image_path" value="">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre de la Herramienta:</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="" required>
                    </div>
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción:</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="precio_dia" class="form-label">Precio por Día ($):</label>
                        <input type="number" step="0.01" class="form-control" id="precio_dia" name="precio_dia" value="" required min="0.01">
                    </div>
                    <div class="mb-3">
                        <label for="imagen" class="form-label">Imagen de la Herramienta:</label>
                        <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                        <div class="form-text">Solo se aceptan archivos JPG, JPEG, PNG, GIF (máx. 5MB).</div>
                        <div id="current-image-preview" class="mt-2" style="display: none;">
                            <p>Imagen actual:</p>
                            <img src="" alt="Imagen Actual" style="max-width: 150px; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="disponible" name="disponible">
                        <label class="form-check-label" for="disponible">Disponible para Arriendo</label>
                    </div>
                    <button type="submit" name="add_tool" id="submit-button" class="btn btn-primary" style="background-color: #4e54c8; border-color: #4e54c8;">Publicar Herramienta</button>
                    <button type="button" id="cancel-edit-button" class="btn btn-secondary ms-2" style="display:none;">Cancelar Edición</button>
                </form>
            </div>

            ---

            <h2 class="mb-4 text-center" style="color: #4e54c8;" id="my-tools">Mis Herramientas Publicadas</h2>
            <?php if (empty($mis_herramientas)): ?>
                <div class="alert alert-info text-center" role="alert">
                    Aún no has publicado ninguna herramienta.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($mis_herramientas as $herramienta): ?>
                        <div class="col">
                            <div class="card h-100 tool-card">
                                <?php
                                // Verificar si la imagen existe en la ruta especificada, si no, usar un placeholder
                                $imagePath = !empty($herramienta['imagen']) && file_exists($herramienta['imagen'])
                                    ? htmlspecialchars($herramienta['imagen'])
                                    : 'https://via.placeholder.com/400x200?text=Sin+Imagen';
                                ?>
                                <img src="<?= $imagePath ?>" class="card-img-top" alt="<?= htmlspecialchars($herramienta['nombre']) ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($herramienta['nombre']) ?></h5>
                                    <p class="card-text"><?= htmlspecialchars($herramienta['descripcion']) ?></p>
                                    <p class="card-price">Precio por día: $<?= number_format($herramienta['precio_dia'], 2, ',', '.') ?></p>
                                    <p class="card-status">Estado:
                                        <?php if ($herramienta['disponible']): ?>
                                            <span class="badge bg-success">Disponible</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">No Disponible</span>
                                        <?php endif; ?>
                                    </p>
                                    <div class="card-actions">
                                        <button type="button"
                                                class="btn btn-sm btn-info text-white edit-tool-btn"
                                                data-id="<?= htmlspecialchars($herramienta['id']) ?>"
                                                data-nombre="<?= htmlspecialchars($herramienta['nombre']) ?>"
                                                data-descripcion="<?= htmlspecialchars($herramienta['descripcion']) ?>"
                                                data-precio="<?= htmlspecialchars($herramienta['precio_dia']) ?>"
                                                data-imagen="<?= $imagePath ?>"
                                                data-disponible="<?= htmlspecialchars($herramienta['disponible']) ?>">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </button>
                                        <a href="dashboardarrendador.php?delete_tool_id=<?= $herramienta['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar esta herramienta? Esta acción no se puede deshacer.');"><i class="bi bi-trash"></i> Eliminar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            ---

            <h2 class="mb-4 text-center mt-5" style="color: #4e54c8;" id="requests">Solicitudes de Arriendo Recibidas</h2>
            <?php if (empty($solicitudes_recibidas)): ?>
                <div class="alert alert-info text-center" role="alert">
                    No tienes solicitudes de arriendo en este momento.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped bg-white rounded shadow-sm">
                        <thead>
                            <tr>
                                <th scope="col">Herramienta</th>
                                <th scope="col">Cliente</th>
                                <th scope="col">Fecha Inicio</th>
                                <th scope="col">Fecha Fin</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Fecha Solicitud</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes_recibidas as $solicitud): ?>
                                <tr>
                                    <td><?= htmlspecialchars($solicitud['herramienta_nombre']) ?></td>
                                    <td><?= htmlspecialchars($solicitud['cliente_nombre']) ?></td>
                                    <td><?= htmlspecialchars($solicitud['fecha_inicio']) ?></td>
                                    <td><?= htmlspecialchars($solicitud['fecha_fin']) ?></td>
                                    <td>
                                        <?php
                                        // Definir la clase de la insignia según el estado de la solicitud
                                        $badge_class = '';
                                        switch ($solicitud['estado']) {
                                            case 'pendiente': $badge_class = 'bg-warning text-dark'; break;
                                            case 'aprobado': $badge_class = 'bg-success'; break;
                                            case 'rechazado': $badge_class = 'bg-danger'; break;
                                            case 'finalizado': $badge_class = 'bg-secondary'; break;
                                            default: $badge_class = 'bg-info'; break;
                                        }
                                        ?>
                                        <span class="badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($solicitud['estado'])) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($solicitud['fecha_solicitud']) ?></td>
                                    <td>
                                        <?php if ($solicitud['estado'] == 'pendiente'): ?>
                                            <a href="handle_request.php?action=approve&id=<?= $solicitud['id'] ?>&tool_id=<?= $solicitud['id_herramienta'] ?>" class="btn btn-sm btn-success mb-1" onclick="return confirm('¿Aprobar esta solicitud? Esto marcará la herramienta como no disponible durante el periodo de arriendo.');"><i class="bi bi-check-circle"></i> Aprobar</a>
                                            <a href="handle_request.php?action=reject&id=<?= $solicitud['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Rechazar esta solicitud?');"><i class="bi bi-x-circle"></i> Rechazar</a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <footer class="text-center mt-5 mb-3 text-muted">
            <p>&copy; <?= date("Y") ?> Arriendo de Herramientas. Todos los derechos reservados.</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para colapsar/expandir la barra lateral
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            const icon = this.querySelector('i');
            if (document.getElementById('sidebar').classList.contains('collapsed')) {
                icon.classList.remove('bi-chevron-left');
                icon.classList.add('bi-chevron-right');
            } else {
                icon.classList.remove('bi-chevron-right');
                icon.classList.add('bi-chevron-left');
            }
        });

        // Desplazamiento suave a la sección de la página si hay un hash en la URL
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.hash) {
                const targetElement = document.querySelector(window.location.hash);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }
            }

            // Manejar los botones de edición
            document.querySelectorAll('.edit-tool-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const toolId = this.dataset.id;
                    const nombre = this.dataset.nombre;
                    const descripcion = this.dataset.descripcion;
                    const precio = this.dataset.precio;
                    const imagen = this.dataset.imagen;
                    const disponible = parseInt(this.dataset.disponible);

                    // Rellenar el formulario
                    document.getElementById('tool_id').value = toolId;
                    document.getElementById('nombre').value = nombre;
                    document.getElementById('descripcion').value = descripcion;
                    document.getElementById('precio_dia').value = precio;
                    document.getElementById('disponible').checked = (disponible === 1);

                    // Si hay una imagen actual, mostrarla
                    const currentImagePreview = document.getElementById('current-image-preview');
                    const currentImageTag = currentImagePreview.querySelector('img');
                    const currentImagePathInput = document.getElementById('current_image_path');

                    if (imagen && imagen !== 'https://via.placeholder.com/400x200?text=Sin+Imagen') {
                        currentImageTag.src = imagen;
                        currentImagePathInput.value = imagen; // Guardar la ruta actual para la actualización
                        currentImagePreview.style.display = 'block';
                    } else {
                        currentImagePreview.style.display = 'none';
                        currentImagePathInput.value = '';
                    }

                    // Cambiar el título del formulario y el texto del botón
                    document.getElementById('form-title').textContent = 'Editar Herramienta';
                    document.getElementById('submit-button').name = 'update_tool';
                    document.getElementById('submit-button').textContent = 'Guardar Cambios';
                    document.getElementById('cancel-edit-button').style.display = 'inline-block'; // Mostrar botón de cancelar

                    // Desplazar la vista al formulario de edición
                    document.getElementById('tool-form-section').scrollIntoView({ behavior: 'smooth' });
                });
            });

            // Manejar el botón de cancelar edición
            document.getElementById('cancel-edit-button').addEventListener('click', function() {
                // Limpiar el formulario
                document.getElementById('tool_id').value = '';
                document.getElementById('nombre').value = '';
                document.getElementById('descripcion').value = '';
                document.getElementById('precio_dia').value = '';
                document.getElementById('disponible').checked = true; // Por defecto, disponible para nuevas publicaciones
                document.getElementById('imagen').value = ''; // Limpiar el input de archivo
                document.getElementById('current-image-preview').style.display = 'none';
                document.getElementById('current_image_path').value = '';

                // Restaurar el título del formulario y el texto del botón
                document.getElementById('form-title').textContent = 'Publicar Nueva Herramienta';
                document.getElementById('submit-button').name = 'add_tool';
                document.getElementById('submit-button').textContent = 'Publicar Herramienta';
                this.style.display = 'none'; // Ocultar botón de cancelar
            });
        });
    </script>
</body>
</html>