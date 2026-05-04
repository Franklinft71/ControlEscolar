<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Establece y retorna la conexión a la base de datos usando PDO.
 * @return PDO
 */
function db_connect() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}

/**
 * Limpia una cadena de texto de posibles scripts o etiquetas HTML.
 * @param string $data
 * @return string
 */
function sanitize($data) {
    if (is_null($data)) return '';
    return htmlspecialchars(strip_tags(trim($data)));
}
