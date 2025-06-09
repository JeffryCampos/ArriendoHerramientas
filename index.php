<?php
require_once 'conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Arriendo de Herramientas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .hero {
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: white;
            padding: 80px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 40px;
        }
        .hero h1 {
            font-weight: 700;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .hero p {
            font-size: 1.3rem;
            opacity: 0.85;
            margin-top: 12px;
        }
        #herramientas h2 {
            font-weight: 600;
            color: #333;
            margin-bottom: 40px;
        }
        .card.tool-card {
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            background: white;
        }
        .card.tool-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        .card-img-top {
    border-top-left-radius: 15px;
    border-top-right-radius: 15px;
    height: 150px;       /* Más pequeña que antes */
    object-fit: contain; /* Para que la imagen no se recorte */
    background-color: #f8f9fa; /* Fondo claro para que se vea bien si la imagen no ocupa todo */
    padding: 10px;       /* Pequeño espacio para que no toque bordes */
}
        .card-body {
            padding: 20px;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #4e54c8;
            margin-bottom: 12px;
        }
        .card-text {
            color: #555;
            font-size: 0.95rem;
            min-height: 70px;
        }
        .card p strong {
            font-size: 1.1rem;
            color: #222;
        }
        .btn-primary {
            background: #4e54c8;
            border: none;
            padding: 10px 18px;
            font-weight: 600;
            border-radius: 50px;
            box-shadow: 0 4px 10px rgba(78, 84, 200, 0.4);
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: #3b3f99;
            box-shadow: 0 6px 15px rgba(59, 63, 153, 0.6);
        }
        footer {
            background: #fff;
            border-top: 1px solid #ddd;
            padding: 20px 0;
            font-size: 0.9rem;
            color: #666;
            box-shadow: inset 0 1px 5px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <div class="hero text-center">
        <div class="container">
            <h1 class="display-4">Bienvenido al Arriendo de Herramientas</h1>
            <p class="lead">Encuentra la herramienta que necesitas, cuando la necesites.</p>
        </div>
    </div>

    <section id="herramientas" class="container py-5">
        <h2 class="text-center">Herramientas Disponibles</h2>
        <div class="row g-4">
            <?php
            $query = "SELECT * FROM herramientas WHERE disponible = 1";
            $resultado = $conexion->query($query);

            if ($resultado && $resultado->num_rows > 0) {
                while ($herramienta = $resultado->fetch_assoc()) {
                    $imagen = htmlspecialchars($herramienta["imagen"] ?? "https://via.placeholder.com/400x250");
                    $nombre = htmlspecialchars($herramienta["nombre"]);
                    $descripcion = htmlspecialchars($herramienta["descripcion"]);
                    $precio = number_format($herramienta["precio_dia"], 0, ',', '.');

                    echo <<<HTML
                    <div class="col-md-4">
                        <div class="card tool-card">
                            <img src="$imagen" class="card-img-top" alt="Imagen de herramienta" />
                            <div class="card-body">
                                <h5 class="card-title">$nombre</h5>
                                <p class="card-text">$descripcion</p>
                                <p><strong>\$$precio / día</strong></p>
                                <a href="#" class="btn btn-primary">Arriéndalo</a>
                            </div>
                        </div>
                    </div>
                    HTML;
                }
            } else {
                echo '<p class="text-center">No hay herramientas disponibles en este momento.</p>';
            }
            ?>
        </div>
    </section>

    <footer class="text-center">
        <p>&copy; <?php echo date("Y"); ?> Arriendo de Herramientas. Todos los derechos reservados.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
