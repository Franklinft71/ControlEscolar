USE controlescolar_db;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Agregar nuevas columnas a estudiantes si no existen
ALTER TABLE estudiantes 
ADD COLUMN IF NOT EXISTS nivel_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS seccion_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS horario_id INT DEFAULT NULL;

-- 2. Migrar los datos desde grados_secciones
-- Importante: e.grado_id actualmente apunta a gs.id
UPDATE estudiantes e 
JOIN grados_secciones gs ON e.grado_id = gs.id 
SET e.nivel_id = gs.nivel_id, 
    e.seccion_id = gs.seccion_id, 
    e.horario_id = gs.horario_id,
    e.grado_id = gs.grado_id; -- Aquí cambiamos el ID del combo por el ID del Grado real

-- 3. Borrar la tabla grados_secciones
DROP TABLE IF EXISTS grados_secciones;

-- 4. Re-establecer las llaves foráneas en la tabla estudiantes directamente
-- Intentar borrar cualquier FK antigua en grado_id
ALTER TABLE estudiantes DROP FOREIGN KEY IF EXISTS estudiantes_ibfk_1;

ALTER TABLE estudiantes
MODIFY grado_id INT DEFAULT NULL,
ADD CONSTRAINT fk_est_nivel FOREIGN KEY (nivel_id) REFERENCES niveles(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_est_grado FOREIGN KEY (grado_id) REFERENCES grados(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_est_seccion FOREIGN KEY (seccion_id) REFERENCES secciones(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_est_horario FOREIGN KEY (horario_id) REFERENCES horarios(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
