-- Crear tabla de pagos
CREATE TABLE IF NOT EXISTS pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    moneda VARCHAR(10) DEFAULT 'USD',
    metodo_pago VARCHAR(50) NOT NULL,
    referencia VARCHAR(100),
    concepto VARCHAR(100) NOT NULL,
    mes_pagado INT,
    anio_pagado INT,
    fecha_pago DATE NOT NULL,
    usuario_id INT,
    comprobante VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
