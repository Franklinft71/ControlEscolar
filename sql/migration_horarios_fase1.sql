-- =====================================================
-- MIGRACIÓN FASE 1: GESTIÓN DE HORARIOS AVANZADA
-- =====================================================

-- 1. Renombrar tabla de horarios actual a turnos para evitar confusión
RENAME TABLE horarios TO turnos;

-- 2. Actualizar referencias en estudiantes (la FK se mantiene por el nombre del campo, pero ajustamos lógica)
ALTER TABLE estudiantes CHANGE COLUMN horario_id turno_id INT;

-- 3. Crear tabla de bloques horarios (Lógica de 70 min por bloque)
CREATE TABLE IF NOT EXISTS bloques_horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    turno ENUM('manana', 'tarde') NOT NULL,
    orden INT NOT NULL
) ENGINE=InnoDB;

-- Insertar bloques Mañana (7:00 AM - 12:50 PM)
INSERT INTO bloques_horarios (nombre, hora_inicio, hora_fin, turno, orden) VALUES
('1er Bloque', '07:00:00', '08:10:00', 'manana', 1),
('2do Bloque', '08:10:00', '09:20:00', 'manana', 2),
('3er Bloque', '09:20:00', '10:30:00', 'manana', 3),
('4to Bloque', '10:30:00', '11:40:00', 'manana', 4),
('5to Bloque', '11:40:00', '12:50:00', 'manana', 5);

-- Insertar bloques Tarde (1:00 PM - 5:40 PM)
INSERT INTO bloques_horarios (nombre, hora_inicio, hora_fin, turno, orden) VALUES
('6to Bloque', '13:00:00', '14:10:00', 'tarde', 1),
('7mo Bloque', '14:10:00', '15:20:00', 'tarde', 2),
('8vo Bloque', '15:20:00', '16:30:00', 'tarde', 3),
('9no Bloque', '16:30:00', '17:40:00', 'tarde', 4);

-- 4. Crear tabla de aulas y recursos
CREATE TABLE IF NOT EXISTS aulas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('regular', 'laboratorio', 'cancha', 'musica_arte') DEFAULT 'regular',
    capacidad INT DEFAULT 40,
    descripcion TEXT
) ENGINE=InnoDB;

-- Insertar algunas aulas de ejemplo
INSERT INTO aulas (nombre, tipo, capacidad) VALUES 
('Aula 1-A', 'regular', 40),
('Aula 1-B', 'regular', 40),
('Laboratorio de Ciencias', 'laboratorio', 30),
('Cancha Deportiva', 'cancha', 100),
('Sala de Arte', 'musica_arte', 35);

-- 5. Crear tabla de materias (Currículo Venezuela)
CREATE TABLE IF NOT EXISTS materias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    nivel_tipo EN_ENUM('secundaria', 'media') NOT NULL,
    grado_id INT,
    horas_semanales INT NOT NULL,
    requiere_laboratorio BOOLEAN DEFAULT FALSE,
    color VARCHAR(10) DEFAULT '#0d6efd',
    FOREIGN KEY (grado_id) REFERENCES grados(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Actualizar tabla de docentes para carga horaria
ALTER TABLE docentes ADD COLUMN horas_max_semanales INT DEFAULT 25;

-- 7. Tabla de Disponibilidad Docente
CREATE TABLE IF NOT EXISTS disponibilidad_docente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    dia_semana ENUM('Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes') NOT NULL,
    bloque_id INT NOT NULL,
    FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE CASCADE,
    FOREIGN KEY (bloque_id) REFERENCES bloques_horarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. Tabla Principal de Horarios (El Calendario)
CREATE TABLE IF NOT EXISTS horarios_clases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seccion_id INT NOT NULL,
    materia_id INT NOT NULL,
    docente_id INT NOT NULL,
    aula_id INT NOT NULL,
    dia_semana ENUM('Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes') NOT NULL,
    bloque_id INT NOT NULL,
    lapso INT DEFAULT 1, -- 1, 2 o 3 momento
    FOREIGN KEY (seccion_id) REFERENCES secciones(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE,
    FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE CASCADE,
    FOREIGN KEY (aula_id) REFERENCES aulas(id) ON DELETE CASCADE,
    FOREIGN KEY (bloque_id) REFERENCES bloques_horarios(id) ON DELETE CASCADE,
    UNIQUE KEY (docente_id, dia_semana, bloque_id), -- Un docente no puede estar en dos sitios
    UNIQUE KEY (aula_id, dia_semana, bloque_id),    -- Un aula no puede estar ocupada dos veces
    UNIQUE KEY (seccion_id, dia_semana, bloque_id) -- Una sección no puede tener dos clases a la vez
) ENGINE=InnoDB;

-- 9. Carga de Materias (Currículo Venezolano)
-- Ejemplo 1er Año Secundaria
INSERT INTO materias (nombre, nivel_tipo, grado_id, horas_semanales, color) VALUES 
('Castellano', 'secundaria', 7, 4, '#e74c3c'),
('Matemática', 'secundaria', 7, 4, '#3498db'),
('Inglés', 'secundaria', 7, 3, '#2ecc71'),
('Educación Física', 'secundaria', 7, 2, '#f1c40f'),
('Ciencias Naturales', 'secundaria', 7, 4, '#1abc9c'),
('Geografía, Historia y Ciudadanía', 'secundaria', 7, 4, '#e67e22'),
('Arte y Patrimonio', 'secundaria', 7, 2, '#9b59b6');

-- Ejemplo 4to Año Media General
INSERT INTO materias (nombre, nivel_tipo, grado_id, horas_semanales, requiere_laboratorio, color) VALUES 
('Matemática', 'media', 10, 4, 0, '#3498db'),
('Física', 'media', 10, 4, 1, '#16a085'),
('Química', 'media', 10, 4, 1, '#27ae60'),
('Biología', 'media', 10, 4, 1, '#2ecc71'),
('Castellano', 'media', 10, 4, 0, '#e74c3c'),
('Inglés', 'media', 10, 3, 0, '#2ecc71'),
('Educación Física', 'media', 10, 2, 0, '#f1c40f');
