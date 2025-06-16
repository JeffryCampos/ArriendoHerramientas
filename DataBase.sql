CREATE DATABASE IF NOT EXISTS arriendo_herramientas;
USE arriendo_herramientas;

-- Tabla usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- para hash de contraseña
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_usuario ENUM('cliente', 'arrendador') DEFAULT 'cliente',
    activo BOOLEAN DEFAULT TRUE
);

-- Tabla herramientas
CREATE TABLE herramientas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL, -- propietario o quien la pone en arriendo
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio_dia DECIMAL(10,2) NOT NULL,
    imagen VARCHAR(255),
    disponible BOOLEAN DEFAULT TRUE,
    fecha_publicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla solicitudes (arriendos)
CREATE TABLE solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL, -- quien arrienda la herramienta
    id_herramienta INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('pendiente', 'aprobado', 'rechazado', 'finalizado') DEFAULT 'pendiente',
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_herramienta) REFERENCES herramientas(id) ON DELETE CASCADE
);
