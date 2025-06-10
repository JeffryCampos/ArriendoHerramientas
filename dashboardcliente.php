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
if ($_SESSION['user_type'] !== 'cliente') {
    if ($_SESSION['user_type'] === 'arrendador') {
        header("Location: dashboardarrendador.php");
    } else {
        header("Location: index.php");
    }
    exit;
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
$host = "127.0.0.1";$usuario = "root";$clave = "1234";$bd = "arriendo_herramientas";
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

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'año',
        'm' => 'mes',
        'w' => 'semana',
        'd' => 'día',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'hace ' . implode(', ', $string) : 'justo ahora';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'rent_tool') {
    $tool_id = $_POST['tool_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];

    $today = date('Y-m-d');
    if ($fecha_inicio < $today || $fecha_fin < $fecha_inicio) {
        $_SESSION['mensaje'] = "Fechas de arriendo inválidas. Asegúrate que la fecha de inicio no sea anterior a hoy y que la fecha fin sea posterior a la fecha de inicio.";
        $_SESSION['mensaje_tipo'] = "danger";
        header("Location: dashboardcliente.php");
        exit;
    }

    $stmt_check_availability = $conexion->prepare(
        "SELECT COUNT(*) FROM solicitudes 
         WHERE id_herramienta = ? 
         AND estado IN ('pendiente', 'aprobado') 
         AND (
             (fecha_inicio <= ? AND fecha_fin >= ?) OR
             (fecha_inicio <= ? AND fecha_fin >= ?) OR
             (fecha_inicio >= ? AND fecha_fin <= ?)
         )"
    );
    $stmt_check_availability->bind_param("issssss", $tool_id, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_inicio, $fecha_fin);
    $stmt_check_availability->execute();
    $result_availability = $stmt_check_availability->get_result();
    $row_availability = $result_availability->fetch_row();
    $conflicting_requests = $row_availability[0];
    $stmt_check_availability->close();

    if ($conflicting_requests > 0) {
        $_SESSION['mensaje'] = "La herramienta no está disponible para las fechas seleccionadas debido a un arriendo existente o pendiente.";
        $_SESSION['mensaje_tipo'] = "danger";
    } else {
        $stmt = $conexion->prepare("INSERT INTO solicitudes (id_usuario, id_herramienta, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $user_id, $tool_id, $fecha_inicio, $fecha_fin);
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Solicitud de arriendo enviada exitosamente. Esperando aprobación del arrendador.";
            $_SESSION['mensaje_tipo'] = "success";
        } else {
            $_SESSION['mensaje'] = "Error al enviar la solicitud de arriendo: " . $stmt->error;
            $_SESSION['mensaje_tipo'] = "danger";
        }
        $stmt->close();
    }
    header("Location: dashboardcliente.php");
    exit;
}

$herramientas = [];
$sql_herramientas = "SELECT h.id, h.nombre, h.descripcion, h.precio_dia, h.imagen, h.fecha_publicacion, u.nombre as arrendador_nombre
                     FROM herramientas h
                     JOIN usuarios u ON h.id_usuario = u.id
                     WHERE h.disponible = TRUE
                     ORDER BY h.fecha_publicacion DESC";
$stmt_herramientas = $conexion->prepare($sql_herramientas);
$stmt_herramientas->execute();
$result_herramientas = $stmt_herramientas->get_result();
if ($result_herramientas->num_rows > 0) {
    while ($row = $result_herramientas->fetch_assoc()) {
        $herramientas[] = $row;
    }
}
$stmt_herramientas->close();
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Arriendo de Herramientas - Dashboard Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;display:flex;min-height:100vh;}
        .hero{background:linear-gradient(135deg,#4e54c8,#8f94fb);color:white;padding:40px 20px;box-shadow:0 4px 15px rgba(0,0,0,0.2);margin-bottom:30px;text-align:center;position:relative;}
        .hero h1{font-weight:700;text-shadow:0 2px 8px rgba(0,0,0,0.3);font-size:2.5rem;}
        .hero p{font-size:1.2rem;opacity:0.85;margin-top:10px;}
        .alert{max-width:90%;margin:20px auto;text-align:center;font-weight:600;}
        .sidebar{width:250px;background-color:#343a40;color:white;padding-top:20px;flex-shrink:0;transition:all 0.3s ease;position:sticky;top:0;height:100vh;overflow-y:auto;box-shadow:2px 0 10px rgba(0,0,0,0.1);}
        .sidebar.collapsed{width:80px;}
        .sidebar-header{padding:10px 20px;margin-bottom:20px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:15px;}
        .sidebar-header h3{font-size:1.5rem;margin-bottom:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .sidebar-menu a{padding:12px 20px;color:#adb5bd;text-decoration:none;display:flex;align-items:center;transition:background-color 0.2s,color 0.2s;border-left:3px solid transparent;}
        .sidebar-menu a:hover,.sidebar-menu a.active{background-color:#495057;color:white;border-left:3px solid #007bff;}
        .sidebar-menu a i{margin-right:10px;font-size:1.2rem;}
        .sidebar.collapsed .sidebar-menu span{display:none;}
        .sidebar.collapsed .sidebar-header h3{display:none;}
        .sidebar.collapsed .sidebar-menu a{justify-content:center;padding:12px 0;}
        .sidebar.collapsed .sidebar-menu a i{margin-right:0;}
        .toggle-btn{background-color:#007bff;color:white;border:none;padding:8px 15px;border-radius:5px;cursor:pointer;margin:15px auto;display:block;width:fit-content;}
        .content{flex-grow:1;padding:20px;}
        .tool-card{border:1px solid #dee2e6;border-radius:8px;margin-bottom:20px;background-color:white;box-shadow:0 2px 10px rgba(0,0,0,0.05);transition:transform 0.2s ease-in-out;}
        .tool-card:hover{transform:translateY(-5px);}
        .tool-card img{max-height:200px;width:100%;object-fit:cover;border-top-left-radius:8px;border-top-right-radius:8px;}
        .tool-card .card-body{padding:15px;}
        .tool-card .card-title{font-size:1.4rem;font-weight:600;color:#4e54c8;}
        .tool-card .card-text{font-size:0.95rem;color:#6c757d;}
        .tool-card .card-price{font-size:1.2rem;font-weight:700;color:#28a745;}
        .tool-card .card-publication-date, .tool-card .card-arrendador{font-size:0.85rem;color:#4e54c8;margin-top:10px;font-weight:500;}
        .tool-card .actions{margin-top:15px;display:flex;justify-content:space-around;}
        .tool-card .btn{font-size:0.9rem;padding:8px 12px;}
        .hero .logo-container {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 10;
        }
        .hero .logo-container img {
            max-width: 80px;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Área Cliente</h3>
        </div>
        <ul class="nav flex-column sidebar-menu">
            <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-house-door-fill"></i> <span>Inicio</span></a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-basket"></i> <span>Mis Arriendos</span></a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-person-circle"></i> <span>Mi Perfil</span></a></li>
            <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> <span>Cerrar Sesión</span></a></li>
        </ul>
        <button class="btn toggle-btn" id="sidebarToggle"><i class="bi bi-chevron-left"></i></button>
    </div>
    <div class="content">
        <div class="hero">
            <div class="container">
                <div class="logo-container">
                    <img src="logo.png" alt="Logo">
                </div>
                <h1>¡Hola, <?= htmlspecialchars($user_name) ?>!</h1>
                <p>Encuentra las herramientas que necesitas para tus proyectos.</p>
            </div>
        </div>
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        <div class="container mt-4">
            <h2 class="mb-4 text-center" style="color: #4e54c8;">Herramientas Disponibles</h2>
            <?php if (empty($herramientas)): ?>
                <div class="alert alert-info text-center" role="alert">No hay herramientas disponibles en este momento. Vuelve pronto.</div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($herramientas as $herramienta): ?>
                        <div class="col">
                            <div class="card h-100 tool-card">
                                <?php $imagePath = !empty($herramienta['imagen']) ? htmlspecialchars($herramienta['imagen']) : 'https://via.placeholder.com/400x200?text=Sin+Imagen'; ?>
                                <img src="<?= $imagePath ?>" class="card-img-top" alt="<?= htmlspecialchars($herramienta['nombre']) ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($herramienta['nombre']) ?></h5>
                                    <p class="card-text"><?= htmlspecialchars($herramienta['descripcion']) ?></p>
                                    <p class="card-price">Precio por día: $<?= number_format($herramienta['precio_dia'], 2, ',', '.') ?></p>
                                    <p class="card-arrendador">Arrendador: <?= htmlspecialchars($herramienta['arrendador_nombre']) ?></p>
                                    <p class="card-publication-date">Publicado: <?= time_elapsed_string($herramienta['fecha_publicacion']) ?></p>
                                    <div class="actions">
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#rentModal"
                                                data-tool-id="<?= $herramienta['id'] ?>"
                                                data-tool-name="<?= htmlspecialchars($herramienta['nombre']) ?>">
                                            ¡Arrendar!
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <footer class="text-center mt-5 mb-3 text-muted"><p>&copy; <?= date("Y") ?> Arriendo de Herramientas. Todos los derechos reservados.</p></footer>
    </div>

    <div class="modal fade" id="rentModal" tabindex="-1" aria-labelledby="rentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rentModalLabel">Arrendar <span id="modalToolName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="dashboardcliente.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="rent_tool">
                        <input type="hidden" name="tool_id" id="modalToolId">
                        <div class="mb-3">
                            <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                        </div>
                        <div class="mb-3">
                            <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar Arriendo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (!document.cookie.includes('user_timezone') || getCookie('user_timezone') === '') {
            try {
                const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (userTimeZone) {
                    fetch(window.location.pathname, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'user_timezone=' + encodeURIComponent(userTimeZone)
                    }).then(response => {
                        if (response.ok) {
                        }
                    }).catch(error => console.error('Error al enviar zona horaria:', error));
                    document.cookie = `user_timezone=${encodeURIComponent(userTimeZone)}; path=/; max-age=31536000`;
                }
            } catch (e) {
                console.error("Error al obtener la zona horaria del usuario:", e);
            }
        }

        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
        }

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

        document.addEventListener('DOMContentLoaded', function() {
            const rentModal = document.getElementById('rentModal');
            rentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const toolId = button.dataset.toolId;
                const toolName = button.dataset.toolName;
                const modalToolName = rentModal.querySelector('#modalToolName');
                const modalToolId = rentModal.querySelector('#modalToolId');

                modalToolName.textContent = toolName;
                modalToolId.value = toolId;

                const today = new Date();
                const dd = String(today.getDate()).padStart(2, '0');
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const yyyy = today.getFullYear();
                const minDate = yyyy + '-' + mm + '-' + dd;

                rentModal.querySelector('#fecha_inicio').setAttribute('min', minDate);
                rentModal.querySelector('#fecha_fin').setAttribute('min', minDate);
            });
            
            const fechaInicioInput = document.getElementById('fecha_inicio');
            const fechaFinInput = document.getElementById('fecha_fin');

            fechaInicioInput.addEventListener('change', function() {
                fechaFinInput.setAttribute('min', this.value);
                if (fechaFinInput.value < this.value) {
                    fechaFinInput.value = this.value;
                }
            });
        });
    </script>
</body>
</html>
