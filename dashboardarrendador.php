<?php
session_start();

$user_timezone = 'America/Santiago';
if (isset($_COOKIE['user_timezone']) && !empty($_COOKIE['user_timezone'])) {
    $user_timezone = $_COOKIE['user_timezone'];
}
date_default_timezone_set($user_timezone);

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SESSION['user_type'] !== 'arrendador') {
    if ($_SESSION['user_type'] === 'cliente') {
        header("Location: dashboardcliente.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

$host = "127.0.0.1";
$usuario = "root";
$clave = "";
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
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $mensaje_tipo = $_SESSION['mensaje_tipo'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['mensaje_tipo']);
}

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'approve_request' && isset($_POST['solicitud_id'])) {
        $solicitud_id = $_POST['solicitud_id'];

        $conexion->begin_transaction();
        try {
            $stmt_approve = $conexion->prepare("UPDATE solicitudes SET estado = 'aprobado' WHERE id = ?");
            $stmt_approve->bind_param("i", $solicitud_id);
            $stmt_approve->execute();
            $stmt_approve->close();

            $stmt_get_req = $conexion->prepare("SELECT id_herramienta, fecha_inicio, fecha_fin FROM solicitudes WHERE id = ?");
            $stmt_get_req->bind_param("i", $solicitud_id);
            $stmt_get_req->execute();
            $req_result = $stmt_get_req->get_result()->fetch_assoc();
            $tool_id = $req_result['id_herramienta'];
            $fecha_inicio = $req_result['fecha_inicio'];
            $fecha_fin = $req_result['fecha_fin'];
            $stmt_get_req->close();

            $stmt_reject_conflicts = $conexion->prepare(
                "UPDATE solicitudes SET estado = 'rechazado' 
                 WHERE id_herramienta = ? AND estado = 'pendiente' AND id != ?
                 AND (
                    (fecha_inicio <= ? AND fecha_fin >= ?) OR
                    (fecha_inicio <= ? AND fecha_fin >= ?) OR
                    (fecha_inicio >= ? AND fecha_fin <= ?)
                 )"
            );
            $stmt_reject_conflicts->bind_param("iissssss", $tool_id, $solicitud_id, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_inicio, $fecha_fin);
            $stmt_reject_conflicts->execute();
            $stmt_reject_conflicts->close();

            $conexion->commit();
            $_SESSION['mensaje'] = "Solicitud aprobada y conflictos resueltos.";
            $_SESSION['mensaje_tipo'] = "success";

        } catch (mysqli_sql_exception $exception) {
            $conexion->rollback();
            $_SESSION['mensaje'] = "Error al aprobar la solicitud: " . $exception->getMessage();
            $_SESSION['mensaje_tipo'] = "danger";
        }

        header("Location: dashboardarrendador.php");
        exit;

    } elseif ($action == 'reject_request' && isset($_POST['solicitud_id'])) {
        $solicitud_id = $_POST['solicitud_id'];
        $stmt = $conexion->prepare("UPDATE solicitudes SET estado = 'rechazado' WHERE id = ?");
        $stmt->bind_param("i", $solicitud_id);
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Solicitud rechazada correctamente.";
            $_SESSION['mensaje_tipo'] = "warning";
        } else {
            $_SESSION['mensaje'] = "Error al rechazar la solicitud.";
            $_SESSION['mensaje_tipo'] = "danger";
        }
        $stmt->close();
        header("Location: dashboardarrendador.php");
        exit;
    }

    if ($_POST['action'] == 'add_tool') {
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio_dia = $_POST['precio_dia'];
        $imagen = $_FILES['imagen'];
        $uploadDir = 'uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imagePath = '';
        if ($imagen['error'] == UPLOAD_ERR_OK) {
            $imageFileType = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));
            $allowedTypes = array('jpg', 'jpeg', 'png', 'gif');
            if (in_array($imageFileType, $allowedTypes)) {
                $newFileName = uniqid('tool_') . '.' . $imageFileType;
                $targetFilePath = $uploadDir . $newFileName;
                if (move_uploaded_file($imagen['tmp_name'], $targetFilePath)) {
                    $imagePath = $targetFilePath;
                } else {
                    $_SESSION['mensaje'] = "Error al subir la imagen.";
                    $_SESSION['mensaje_tipo'] = "danger";
                }
            } else {
                $_SESSION['mensaje'] = "Tipo de archivo no permitido. Solo se permiten JPG, JPEG, PNG y GIF.";
                $_SESSION['mensaje_tipo'] = "danger";
            }
        }
        
        if (empty($_SESSION['mensaje'])) {
            $stmt = $conexion->prepare("INSERT INTO herramientas (id_usuario, nombre, descripcion, precio_dia, imagen) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issds", $user_id, $nombre, $descripcion, $precio_dia, $imagePath);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Herramienta agregada exitosamente.";
                $_SESSION['mensaje_tipo'] = "success";
            } else {
                $_SESSION['mensaje'] = "Error al agregar la herramienta: " . $stmt->error;
                $_SESSION['mensaje_tipo'] = "danger";
            }
            $stmt->close();
        }
        header("Location: dashboardarrendador.php");
        exit;
        
    } elseif ($_POST['action'] == 'edit_tool' && isset($_POST['tool_id'])) {
        $tool_id = $_POST['tool_id'];
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio_dia = $_POST['precio_dia'];
        $disponible = isset($_POST['disponible']) ? 1 : 0;
        $current_image_path = $_POST['current_image_path'] ?? '';
        $new_image = $_FILES['imagen'];
        $imagePath = $current_image_path;

        if ($new_image['error'] == UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {mkdir($uploadDir, 0777, true);}
            
            $imageFileType = strtolower(pathinfo($new_image['name'], PATHINFO_EXTENSION));
            $allowedTypes = array('jpg', 'jpeg', 'png', 'gif');
            if (in_array($imageFileType, $allowedTypes)) {
                if (!empty($current_image_path) && file_exists($current_image_path)) {
                    unlink($current_image_path);
                }
                $newFileName = uniqid('tool_') . '.' . $imageFileType;
                $targetFilePath = $uploadDir . $newFileName;
                if (move_uploaded_file($new_image['tmp_name'], $targetFilePath)) {
                    $imagePath = $targetFilePath;
                } else {
                    $_SESSION['mensaje'] = "Error al subir la nueva imagen.";
                    $_SESSION['mensaje_tipo'] = "danger";
                }
            } else {
                $_SESSION['mensaje'] = "Tipo de archivo no permitido para la nueva imagen.";
                $_SESSION['mensaje_tipo'] = "danger";
            }
        }

        if (empty($_SESSION['mensaje'])) {
            $stmt = $conexion->prepare("UPDATE herramientas SET nombre = ?, descripcion = ?, precio_dia = ?, imagen = ?, disponible = ? WHERE id = ? AND id_usuario = ?");
            $stmt->bind_param("ssdsiii", $nombre, $descripcion, $precio_dia, $imagePath, $disponible, $tool_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Herramienta actualizada exitosamente.";
                $_SESSION['mensaje_tipo'] = "success";
            } else {
                $_SESSION['mensaje'] = "Error al actualizar la herramienta: " . $stmt->error;
                $_SESSION['mensaje_tipo'] = "danger";
            }
            $stmt->close();
        }
        header("Location: dashboardarrendador.php");
        exit;

    } elseif ($_POST['action'] == 'delete_tool' && isset($_POST['tool_id'])) {
        $tool_id = $_POST['tool_id'];
        
        $stmt_select_img = $conexion->prepare("SELECT imagen FROM herramientas WHERE id = ? AND id_usuario = ?");
        $stmt_select_img->bind_param("ii", $tool_id, $user_id);
        $stmt_select_img->execute();
        $result_img = $stmt_select_img->get_result();
        if ($result_img->num_rows > 0) {
            $row_img = $result_img->fetch_assoc();
            if (!empty($row_img['imagen']) && file_exists($row_img['imagen'])) {
                unlink($row_img['imagen']);
            }
        }
        $stmt_select_img->close();

        $stmt_delete = $conexion->prepare("DELETE FROM herramientas WHERE id = ? AND id_usuario = ?");
        $stmt_delete->bind_param("ii", $tool_id, $user_id);
        if ($stmt_delete->execute()) {
            $_SESSION['mensaje'] = "Herramienta eliminada exitosamente.";
            $_SESSION['mensaje_tipo'] = "success";
        } else {
            $_SESSION['mensaje'] = "Error al eliminar la herramienta: " . $stmt_delete->error;
            $_SESSION['mensaje_tipo'] = "danger";
        }
        $stmt_delete->close();
        header("Location: dashboardarrendador.php");
        exit;

    } elseif ($_POST['action'] == 'toggle_availability' && isset($_POST['tool_id'])) {
        $tool_id = $_POST['tool_id'];
        
        $stmt_select_dispo = $conexion->prepare("SELECT disponible FROM herramientas WHERE id = ? AND id_usuario = ?");
        $stmt_select_dispo->bind_param("ii", $tool_id, $user_id);
        $stmt_select_dispo->execute();
        $result_dispo = $stmt_select_dispo->get_result();
        if ($result_dispo->num_rows > 0) {
            $row_dispo = $result_dispo->fetch_assoc();
            $new_disponibilidad = !$row_dispo['disponible'];

            $stmt_update_dispo = $conexion->prepare("UPDATE herramientas SET disponible = ? WHERE id = ? AND id_usuario = ?");
            $stmt_update_dispo->bind_param("iii", $new_disponibilidad, $tool_id, $user_id);
            if ($stmt_update_dispo->execute()) {
                $_SESSION['mensaje'] = "Disponibilidad de la herramienta actualizada.";
                $_SESSION['mensaje_tipo'] = "success";
            } else {
                $_SESSION['mensaje'] = "Error al actualizar la disponibilidad: " . $stmt_update_dispo->error;
                $_SESSION['mensaje_tipo'] = "danger";
            }
            $stmt_update_dispo->close();
        }
        $stmt_select_dispo->close();
        header("Location: dashboardarrendador.php");
        exit;
    }
}

$herramientas_propias = [];
$sql_herramientas_propias = "SELECT id, nombre, descripcion, precio_dia, imagen, disponible, fecha_publicacion FROM herramientas WHERE id_usuario = ? ORDER BY fecha_publicacion DESC";
$stmt_herramientas_propias = $conexion->prepare($sql_herramientas_propias);
$stmt_herramientas_propias->bind_param("i", $user_id);
$stmt_herramientas_propias->execute();
$result_herramientas_propias = $stmt_herramientas_propias->get_result();
if ($result_herramientas_propias->num_rows > 0) {
    while ($row = $result_herramientas_propias->fetch_assoc()) {
        $herramientas_propias[] = $row;
    }
}
$stmt_herramientas_propias->close();

$solicitudes = [];
$sql_solicitudes = "SELECT s.id, h.nombre as herramienta_nombre, u.nombre as cliente_nombre, s.fecha_inicio, s.fecha_fin, s.estado, s.fecha_solicitud 
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
    while($row = $result_solicitudes->fetch_assoc()) {
        $solicitudes[] = $row;
    }
}
$stmt_solicitudes->close();

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Arriendo de Herramientas - Dashboard Arrendador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; min-height: 100vh; }
        .hero { background: linear-gradient(135deg, #4e54c8, #8f94fb); color: white; padding: 40px 20px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); margin-bottom: 30px; text-align: center; position: relative; }
        .hero h1 { font-weight: 700; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); font-size: 2.5rem; }
        .hero p { font-size: 1.2rem; opacity: 0.85; margin-top: 10px; }
        .alert { max-width: 90%; margin: 20px auto; text-align: center; font-weight: 600; }
        .sidebar { width: 250px; background-color: #343a40; color: white; padding-top: 20px; flex-shrink: 0; transition: all 0.3s ease; position: sticky; top: 0; height: 100vh; overflow-y: auto; box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); }
        .sidebar.collapsed { width: 80px; }
        .sidebar-header { padding: 10px 20px; margin-bottom: 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 15px; }
        .sidebar-header h3 { font-size: 1.5rem; margin-bottom: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-menu a { padding: 12px 20px; color: #adb5bd; text-decoration: none; display: flex; align-items: center; transition: background-color 0.2s, color 0.2s; border-left: 3px solid transparent; cursor: pointer; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: #495057; color: white; border-left: 3px solid #007bff; }
        .sidebar-menu a i { margin-right: 10px; font-size: 1.2rem; }
        .sidebar.collapsed .sidebar-menu span { display: none; }
        .sidebar.collapsed .sidebar-header h3 { display: none; }
        .sidebar.collapsed .sidebar-menu a { justify-content: center; padding: 12px 0; }
        .sidebar.collapsed .sidebar-menu a i { margin-right: 0; }
        .toggle-btn { background-color: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; margin: 15px auto; display: block; width: fit-content; }
        .content { flex-grow: 1; padding: 20px; }
        .tool-card { border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px; background-color: white; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); transition: transform 0.2s ease-in-out; }
        .tool-card:hover { transform: translateY(-5px); }
        .tool-card img { max-height: 200px; width: 100%; object-fit: cover; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .tool-card .card-body { padding: 15px; }
        .tool-card .card-title { font-size: 1.4rem; font-weight: 600; color: #4e54c8; }
        .tool-card .card-text { font-size: 0.95rem; color: #6c757d; }
        .tool-card .card-price { font-size: 1.2rem; font-weight: 700; color: #28a745; }
        .tool-card .card-publication-date, .tool-card .card-disponibilidad { font-size: 0.85rem; color: #4e54c8; margin-top: 10px; font-weight: 500; }
        .tool-card .actions { margin-top: 15px; display: flex; justify-content: space-around; }
        .tool-card .btn { font-size: 0.9rem; padding: 8px 12px; }
        .disponible-checkbox-group { display: flex; align-items: center; justify-content: flex-end; }
        .disponible-checkbox-group .form-check-label { margin-left: 5px; margin-bottom: 0; }
        .hero .logo-container { position: absolute; top: 20px; right: 20px; z-index: 10; }
        .hero .logo-container img { max-width: 80px; height: auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        .hidden-section { display: none; }
    </style>
</head>
<body>
    <div class="sidebar collapsed" id="sidebar">
        <div class="sidebar-header">
            <h3>Área Arrendador</h3>
        </div>
        <ul class="nav flex-column sidebar-menu">
            <li class="nav-item"><a class="nav-link active" id="link-herramientas"><i class="bi bi-tools"></i> <span>Mis Herramientas</span></a></li>
            <li class="nav-item"><a class="nav-link" id="link-solicitudes"><i class="bi bi-box-seam"></i> <span>Solicitudes de Arriendo</span></a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> <span>Cerrar Sesión</span></a></li>
        </ul>
        <button class="btn toggle-btn" id="sidebarToggle"><i class="bi bi-chevron-right"></i></button>
    </div>

    <div class="content">
        <div class="hero">
            <div class="container">
                <div class="logo-container">
                    <img src="logo.png" alt="Logo">
                </div>
                <h1>¡Hola, <?= htmlspecialchars($user_name) ?>!</h1>
                <p>Gestiona tus herramientas y arriendos aquí.</p>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        
        <div id="herramientas-section">
            <div class="container mt-4">
                <h2 class="mb-4 text-center" style="color: #4e54c8;" id="form-title">Añadir Nueva Herramienta</h2>
                <div class="card p-4 mb-5 shadow-sm">
                    <form action="dashboardarrendador.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" id="form-action" value="add_tool">
                        <input type="hidden" name="tool_id" id="tool_id">
                        <input type="hidden" name="current_image_path" id="current_image_path">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre de la Herramienta</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="precio_dia" class="form-label">Precio por Día ($)</label>
                            <input type="number" class="form-control" id="precio_dia" name="precio_dia" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="imagen" class="form-label">Imagen de la Herramienta</label>
                            <input class="form-control" type="file" id="imagen" name="imagen" accept="image/*" capture="environment">
                            <small class="form-text text-muted" id="current-image-preview" style="display:none; margin-top: 5px;">
                                Imagen actual: <img src="" alt="Imagen Actual" style="max-height: 50px; vertical-align: middle; margin-left: 10px;">
                            </small>
                        </div>
                        <div class="mb-3 form-check disponible-checkbox-group">
                            <input type="checkbox" class="form-check-input" id="disponible" name="disponible" value="1" checked>
                            <label class="form-check-label" for="disponible">Disponible para arriendo</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="background-color: #4e54c8; border-color: #4e54c8;" id="submit-button">Agregar Herramienta</button>
                        <button type="button" class="btn btn-secondary w-100 mt-2" id="cancel-edit-button" style="display:none;">Cancelar Edición</button>
                    </form>
                </div>

                <h2 class="mb-4 text-center" style="color: #4e54c8;">Mis Herramientas Publicadas</h2>
                <?php if (empty($herramientas_propias)): ?>
                    <div class="alert alert-info text-center" role="alert">No tienes herramientas publicadas. ¡Publica la primera!</div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php foreach ($herramientas_propias as $herramienta): ?>
                            <div class="col">
                                <div class="card h-100 tool-card">
                                    <?php $imagePath = !empty($herramienta['imagen']) ? htmlspecialchars($herramienta['imagen']) : 'https://via.placeholder.com/400x200?text=Sin+Imagen'; ?>
                                    <img src="<?= $imagePath ?>" class="card-img-top" alt="<?= htmlspecialchars($herramienta['nombre']) ?>">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($herramienta['nombre']) ?></h5>
                                        <p class="card-text"><?= htmlspecialchars($herramienta['descripcion']) ?></p>
                                        <p class="card-price">Precio por día: $<?= number_format($herramienta['precio_dia'], 0, ',', '.') ?></p>
                                        <p class="card-disponibilidad">Estado: <span class="badge bg-<?= $herramienta['disponible'] ? 'success' : 'danger' ?>"><?= $herramienta['disponible'] ? 'Disponible' : 'No Disponible' ?></span></p>
                                        <div class="actions">
                                            <button type="button" class="btn btn-sm btn-info edit-tool-button"
                                                    data-id="<?= $herramienta['id'] ?>"
                                                    data-nombre="<?= htmlspecialchars($herramienta['nombre']) ?>"
                                                    data-descripcion="<?= htmlspecialchars($herramienta['descripcion']) ?>"
                                                    data-precio="<?= htmlspecialchars($herramienta['precio_dia']) ?>"
                                                    data-imagen="<?= htmlspecialchars($herramienta['imagen']) ?>"
                                                    data-disponible="<?= $herramienta['disponible'] ? 'true' : 'false' ?>">
                                                Editar
                                            </button>
                                            <form action="dashboardarrendador.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="action" value="toggle_availability">
                                                <input type="hidden" name="tool_id" value="<?= $herramienta['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-<?= $herramienta['disponible'] ? 'warning' : 'success' ?>">
                                                    <?= $herramienta['disponible'] ? 'Marcar No Disponible' : 'Marcar Disponible' ?>
                                                </button>
                                            </form>
                                            <form action="dashboardarrendador.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="action" value="delete_tool">
                                                <input type="hidden" name="tool_id" value="<?= $herramienta['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar esta herramienta?');">Eliminar</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="solicitudes-section" class="hidden-section">
            <div class="container mt-4">
                <h2 class="mb-4 text-center" style="color: #4e54c8;">Solicitudes de Arriendo</h2>
                <?php if (empty($solicitudes)): ?>
                    <div class="alert alert-info text-center" role="alert">No has recibido ninguna solicitud de arriendo todavía.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Herramienta</th>
                                    <th>Cliente</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solicitudes as $solicitud): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($solicitud['herramienta_nombre']) ?></td>
                                        <td><?= htmlspecialchars($solicitud['cliente_nombre']) ?></td>
                                        <td><?= htmlspecialchars(date("d/m/Y", strtotime($solicitud['fecha_inicio']))) ?></td>
                                        <td><?= htmlspecialchars(date("d/m/Y", strtotime($solicitud['fecha_fin']))) ?></td>
                                        <td>
                                            <?php
                                            $estado = htmlspecialchars($solicitud['estado']);
                                            $badge_class = 'bg-secondary';
                                            if ($estado == 'aprobado') $badge_class = 'bg-success';
                                            elseif ($estado == 'pendiente') $badge_class = 'bg-warning text-dark';
                                            elseif ($estado == 'rechazado') $badge_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?= ucfirst($estado) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($solicitud['estado'] == 'pendiente'): ?>
                                                <form action="dashboardarrendador.php" method="POST" class="d-inline-block me-1">
                                                    <input type="hidden" name="action" value="approve_request">
                                                    <input type="hidden" name="solicitud_id" value="<?= $solicitud['id'] ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">Aprobar</button>
                                                </form>
                                                <form action="dashboardarrendador.php" method="POST" class="d-inline-block">
                                                    <input type="hidden" name="action" value="reject_request">
                                                    <input type="hidden" name="solicitud_id" value="<?= $solicitud['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Rechazar</button>
                                                </form>
                                            <?php else: ?>
                                                <span>-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="text-center mt-5 mb-3 text-muted">
            <p>&copy; <?= date("Y") ?> TuTallerOnline. Todos los derechos reservados.</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (!document.cookie.includes('user_timezone') || getCookie('user_timezone') === '') {
            try {
                const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (userTimeZone) {
                    document.cookie = `user_timezone=${encodeURIComponent(userTimeZone)}; path=/; max-age=31536000`;
                }
            } catch (e) {
                console.error("Error al obtener la zona horaria del usuario:", e);
            }
        }
        function getCookie(name) {const value = `; ${document.cookie}`;const parts = value.split(`; ${name}=`);if (parts.length === 2) return parts.pop().split(';').shift();}
        
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

        const linkHerramientas = document.getElementById('link-herramientas');
        const linkSolicitudes = document.getElementById('link-solicitudes');
        const sectionHerramientas = document.getElementById('herramientas-section');
        const sectionSolicitudes = document.getElementById('solicitudes-section');

        linkHerramientas.addEventListener('click', () => {
            sectionHerramientas.style.display = 'block';
            sectionSolicitudes.style.display = 'none';
            linkHerramientas.classList.add('active');
            linkSolicitudes.classList.remove('active');
        });

        linkSolicitudes.addEventListener('click', () => {
            sectionHerramientas.style.display = 'none';
            sectionSolicitudes.style.display = 'block';
            linkHerramientas.classList.remove('active');
            linkSolicitudes.classList.add('active');
        });

        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('.edit-tool-button');
            const formTitle = document.getElementById('form-title');
            const formAction = document.getElementById('form-action');
            const toolIdInput = document.getElementById('tool_id');
            const nombreInput = document.getElementById('nombre');
            const descripcionInput = document.getElementById('descripcion');
            const precioDiaInput = document.getElementById('precio_dia');
            const imagenInput = document.getElementById('imagen');
            const disponibleCheckbox = document.getElementById('disponible');
            const currentImagePathInput = document.getElementById('current_image_path');
            const currentImagePreview = document.querySelector('#current-image-preview img');
            const currentImagePreviewContainer = document.getElementById('current-image-preview');
            const submitButton = document.getElementById('submit-button');
            const cancelEditButton = document.getElementById('cancel-edit-button');

            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const toolId = this.dataset.id;
                    const nombre = this.dataset.nombre;
                    const descripcion = this.dataset.descripcion;
                    const precio = this.dataset.precio;
                    const imagen = this.dataset.imagen;
                    const disponible = this.dataset.disponible === 'true';

                    formTitle.textContent = 'Editar Herramienta';
                    formAction.value = 'edit_tool';
                    toolIdInput.value = toolId;
                    nombreInput.value = nombre;
                    descripcionInput.value = descripcion;
                    precioDiaInput.value = precio;
                    disponibleCheckbox.checked = disponible;
                    if (imagen) {
                        currentImagePreview.src = imagen;
                        currentImagePathInput.value = imagen;
                        currentImagePreviewContainer.style.display = 'block';
                    } else {
                        currentImagePreviewContainer.style.display = 'none';
                    }

                    submitButton.textContent = 'Actualizar Herramienta';
                    cancelEditButton.style.display = 'block';

                    document.getElementById('form-title').scrollIntoView({ behavior: 'smooth' });
                });
            });

            cancelEditButton.addEventListener('click', function() {
                formTitle.textContent = 'Añadir Nueva Herramienta';
                formAction.value = 'add_tool';
                toolIdInput.value = '';
                nombreInput.value = '';
                descripcionInput.value = '';
                precioDiaInput.value = '';
                imagenInput.value = '';
                disponibleCheckbox.checked = true;
                currentImagePathInput.value = '';
                currentImagePreview.src = '';
                currentImagePreviewContainer.style.display = 'none';
                submitButton.textContent = 'Agregar Herramienta';
                cancelEditButton.style.display = 'none';
            });
        });
    </script>
</body>
</html>