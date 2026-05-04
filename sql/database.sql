-- =====================================================
-- ControlEscolar - Sistema de Control de Acceso Estudiantil
-- Base de Datos v1.1.0
-- =====================================================
CREATE DATABASE IF NOT EXISTS controlescolar_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE controlescolar_db;

-- Tabla de niveles (Primaria, Secundaria, etc.)
CREATE TABLE IF NOT EXISTS niveles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB;

-- Tabla de grados (1ero, 2do, etc.)
CREATE TABLE IF NOT EXISTS grados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB;

-- Tabla de secciones (A, B, C, etc.)
CREATE TABLE IF NOT EXISTS secciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(10) UNIQUE NOT NULL
) ENGINE=InnoDB;

-- Tabla de horarios
CREATE TABLE IF NOT EXISTS horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    turno ENUM('manana', 'tarde', 'noche') DEFAULT 'manana',
    hora_entrada TIME DEFAULT '07:00:00',
    hora_salida TIME DEFAULT '12:00:00'
) ENGINE=InnoDB;

-- Tabla relacional de grados y secciones

-- Tabla de docentes
CREATE TABLE IF NOT EXISTS docentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de estudiantes
CREATE TABLE IF NOT EXISTS estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula_escolar VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    genero ENUM('M', 'F') NOT NULL,
    direccion TEXT,
    foto VARCHAR(255) DEFAULT NULL,
    rfid_uid VARCHAR(100) UNIQUE DEFAULT NULL,
    nivel_id INT DEFAULT NULL,
    grado_id INT DEFAULT NULL,
    seccion_id INT DEFAULT NULL,
    horario_id INT DEFAULT NULL,
    anno_escolar VARCHAR(20) DEFAULT NULL,
    nombre_representante VARCHAR(200) DEFAULT NULL,
    telefono_representante VARCHAR(20) DEFAULT NULL,
    telefono_alternativo VARCHAR(20) DEFAULT NULL,
    foto_representante VARCHAR(255) DEFAULT NULL,
    parentesco VARCHAR(50) DEFAULT NULL,
    observaciones TEXT DEFAULT NULL,
    estatus ENUM('activo', 'inactivo', 'retirado') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (nivel_id) REFERENCES niveles(id) ON DELETE SET NULL,
    FOREIGN KEY (grado_id) REFERENCES grados(id) ON DELETE SET NULL,
    FOREIGN KEY (seccion_id) REFERENCES secciones(id) ON DELETE SET NULL,
    FOREIGN KEY (horario_id) REFERENCES horarios(id) ON DELETE SET NULL,
    INDEX idx_rfid_uid (rfid_uid),
    INDEX idx_cedula (cedula_escolar)
) ENGINE=InnoDB;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'receptor', 'docente', 'viewer') DEFAULT 'viewer',
    telefono VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    estatus ENUM('activo', 'inactivo') DEFAULT 'activo',
    ultimo_acceso DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de registro de asistencia
CREATE TABLE IF NOT EXISTS asistencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    tipo ENUM('entrada', 'salida') NOT NULL,
    retardo BOOLEAN DEFAULT FALSE,
    fecha_hora DATETIME NOT NULL,
    rfid_uid VARCHAR(100) NOT NULL,
    metodo ENUM('rfid', 'manual') DEFAULT 'rfid',
    confirmacion_whatsapp ENUM('enviado', 'fallido', 'pendiente', 'no_configurado') DEFAULT 'no_configurado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    INDEX idx_estudiante_fecha (estudiante_id, fecha_hora),
    INDEX idx_fecha_hora (fecha_hora)
) ENGINE=InnoDB;

-- Tabla de configuracion
CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descripcion TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de log de notificaciones
CREATE TABLE IF NOT EXISTS notificaciones_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT DEFAULT NULL,
    telefono VARCHAR(20) NOT NULL,
    tipo ENUM('whatsapp', 'sms') NOT NULL,
    mensaje TEXT NOT NULL,
    estado ENUM('enviado', 'fallido', 'pendiente') DEFAULT 'pendiente',
    respuesta_api TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- DATOS INICIALES
-- =====================================================

INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES 
('Administrador', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO configuracion (clave, valor, descripcion) VALUES 
('nombre_institucion', 'Instituto ControlEscolar', 'Nombre de la institucion'),
('horario_entrada_inicio', '06:30:00', 'Inicio del horario de entrada'),
('horario_entrada_fin', '08:00:00', 'Fin del horario de entrada'),
('horario_salida_inicio', '12:00:00', 'Inicio del horario de salida'),
('horario_salida_fin', '14:00:00', 'Fin del horario de salida'),
('whatsapp_token', '', 'Token de WhatsApp Business API'),
('whatsapp_phone_id', '', 'Phone Number ID de WhatsApp'),
('notificaciones_activas', '1', 'Activar envio de notificaciones (1=si, 0=no)'),
('sonido_entrada', '1', 'Reproducir sonido en entrada'),
('sonido_salida', '1', 'Reproducir sonido en salida');

-- Semillas para la nueva estructura
INSERT INTO niveles (nombre) VALUES ('Primaria'), ('Secundaria'), ('Diversificado');
INSERT INTO grados (nombre) VALUES 
('1er Grado'), ('2do Grado'), ('3er Grado'), ('4to Grado'), ('5to Grado'), ('6to Grado'),
('1er Año'), ('2do Año'), ('3er Año'), ('4to Año'), ('5to Año');
INSERT INTO secciones (nombre) VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F');

INSERT INTO horarios (nombre, turno, hora_entrada, hora_salida) VALUES 
('Mañana Primaria', 'manana', '07:00:00', '12:00:00'),
('Tarde Secundaria', 'tarde', '13:00:00', '17:30:00');


INSERT INTO docentes (cedula, nombre, apellido) VALUES 
('V-12345678', 'Maria', 'Gonzalez'),
('V-23456789', 'Carlos', 'Rodriguez'),
('V-34567890', 'Ana', 'Martinez');

-- Estudiantes de prueba
INSERT INTO estudiantes (cedula_escolar, nombre, apellido, fecha_nacimiento, genero, direccion, rfid_uid, grado_id, anno_escolar, nombre_representante, telefono_representante, parentesco, estatus) VALUES 
('V-11223344', 'Juan', 'Perez', '2010-05-15', 'M', 'Calle Principal 123', 'A1B2C3D4', 1, '2025-2026', 'Maria Perez', '584141234567', 'Madre', 'activo'),
('V-22334455', 'Ana', 'Gomez', '2011-08-20', 'F', 'Avenida Central 456', 'E5F6G7H8', 2, '2025-2026', 'Carlos Gomez', '584129876543', 'Padre', 'activo'),
('V-33445566', 'Luis', 'Rodriguez', '2009-02-10', 'M', 'Urb. Los Pinos', 'I9J0K1L2', 4, '2025-2026', 'Sofia Rodriguez', '584161112233', 'Madre', 'activo');

-- Asistencia de prueba
INSERT INTO asistencia (estudiante_id, tipo, fecha_hora, rfid_uid, metodo) VALUES 
(1, 'entrada', CONCAT(CURDATE(), ' 07:15:00'), 'A1B2C3D4', 'rfid'),
(2, 'entrada', CONCAT(CURDATE(), ' 07:20:00'), 'E5F6G7H8', 'rfid'),
(1, 'salida', CONCAT(CURDATE(), ' 12:05:00'), 'A1B2C3D4', 'rfid');