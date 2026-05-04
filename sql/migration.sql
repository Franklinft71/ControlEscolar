-- =====================================================
-- MIGRACIÓN PARA NORMALIZACIÓN DE GRADOS Y SECCIONES
-- =====================================================

USE controlescolar_db;

-- 1. Crear tabla de niveles
CREATE TABLE IF NOT EXISTS niveles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO niveles (nombre) VALUES ('Primaria'), ('Secundaria'), ('Diversificado');

-- 2. Crear tabla de grados
CREATE TABLE IF NOT EXISTS grados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO grados (nombre) VALUES 
('1er Grado'), ('2do Grado'), ('3er Grado'), ('4to Grado'), ('5to Grado'), ('6to Grado'),
('1er Año'), ('2do Año'), ('3er Año'), ('4to Año'), ('5to Año');

-- 3. Crear tabla de secciones
CREATE TABLE IF NOT EXISTS secciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(10) UNIQUE NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO secciones (nombre) VALUES ('A'), ('B'), ('C'), ('D'), ('E'), ('F');

-- 4. Crear tabla de horarios
CREATE TABLE IF NOT EXISTS horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    turno ENUM('manana', 'tarde', 'noche') DEFAULT 'manana',
    hora_entrada TIME DEFAULT '07:00:00',
    hora_salida TIME DEFAULT '12:00:00'
) ENGINE=InnoDB;

-- Insertar horarios base si no existen
INSERT INTO horarios (nombre, turno, hora_entrada, hora_salida) 
SELECT 'Horario Mañana Primaria', 'manana', '07:00:00', '12:00:00'
WHERE NOT EXISTS (SELECT 1 FROM horarios WHERE nombre = 'Horario Mañana Primaria');

INSERT INTO horarios (nombre, turno, hora_entrada, hora_salida) 
SELECT 'Horario Tarde Secundaria', 'tarde', '13:00:00', '17:30:00'
WHERE NOT EXISTS (SELECT 1 FROM horarios WHERE nombre = 'Horario Tarde Secundaria');

-- 5. Modificar tabla grados_secciones para usar llaves foráneas
-- Primero agregamos las columnas como nulables para la migración
ALTER TABLE grados_secciones 
ADD COLUMN nivel_id INT DEFAULT NULL,
ADD COLUMN grado_id INT DEFAULT NULL,
ADD COLUMN seccion_id INT DEFAULT NULL,
ADD COLUMN horario_id INT DEFAULT NULL;

-- 6. MIGRAR DATOS EXISTENTES
-- Intentar mapear los strings actuales a los IDs de las nuevas tablas
UPDATE grados_secciones gs
SET gs.nivel_id = (SELECT id FROM niveles WHERE LOWER(nombre) = LOWER(gs.nivel) LIMIT 1),
    gs.grado_id = (SELECT id FROM grados WHERE LOWER(nombre) = LOWER(gs.grado) OR LOWER(REPLACE(nombre, 'Grado', 'ano')) = LOWER(gs.grado) OR LOWER(REPLACE(nombre, 'Año', 'ano')) = LOWER(gs.grado) LIMIT 1),
    gs.seccion_id = (SELECT id FROM secciones WHERE LOWER(nombre) = LOWER(gs.seccion) LIMIT 1);

-- Para horarios, creamos registros únicos para los que ya existen en grados_secciones
INSERT INTO horarios (nombre, turno, hora_entrada, hora_salida)
SELECT DISTINCT 
    CONCAT('Horario ', UPPER(LEFT(turno, 1)), SUBSTRING(turno, 2), ' ', (SELECT nombre FROM niveles n WHERE LOWER(n.nombre) = LOWER(gs.nivel) LIMIT 1)),
    turno, 
    horario_entrada, 
    horario_salida
FROM grados_secciones gs
WHERE NOT EXISTS (
    SELECT 1 FROM horarios h 
    WHERE h.turno = gs.turno 
    AND h.hora_entrada = gs.horario_entrada 
    AND h.hora_salida = gs.horario_salida
);

UPDATE grados_secciones gs
SET gs.horario_id = (
    SELECT id FROM horarios h 
    WHERE h.turno = gs.turno 
    AND h.hora_entrada = gs.horario_entrada 
    AND h.hora_salida = gs.horario_salida
    LIMIT 1
);

-- 7. Limpiar y establecer restricciones
-- Nota: Si hay datos que no se mapearon, podrías querer revisarlos antes de borrar las columnas originales.
-- En una instalación limpia esto no es problema.

ALTER TABLE grados_secciones 
MODIFY nivel_id INT NOT NULL,
MODIFY grado_id INT NOT NULL,
MODIFY seccion_id INT NOT NULL,
MODIFY horario_id INT NOT NULL;

-- Eliminar columnas redundantes
ALTER TABLE grados_secciones 
DROP COLUMN nivel,
DROP COLUMN grado,
DROP COLUMN seccion,
DROP COLUMN turno,
DROP COLUMN horario_entrada,
DROP COLUMN horario_salida;

-- Agregar llaves foráneas
ALTER TABLE grados_secciones 
ADD CONSTRAINT fk_gs_nivel FOREIGN KEY (nivel_id) REFERENCES niveles(id),
ADD CONSTRAINT fk_gs_grado FOREIGN KEY (grado_id) REFERENCES grados(id),
ADD CONSTRAINT fk_gs_seccion FOREIGN KEY (seccion_id) REFERENCES secciones(id),
ADD CONSTRAINT fk_gs_horario FOREIGN KEY (horario_id) REFERENCES horarios(id);

-- Actualizar índice único
ALTER TABLE grados_secciones DROP INDEX unique_grado_seccion;
ALTER TABLE grados_secciones ADD UNIQUE KEY unique_gs_rel (nivel_id, grado_id, seccion_id);
