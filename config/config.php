<?php
define('APP_NAME', 'ControlEscolar - Control de Acceso');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/controlescolar');
define('DB_HOST', 'localhost');
define('DB_NAME', 'controlescolar_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('UPLOAD_PATH_ESTUDIANTES', __DIR__ . '/../assets/uploads/estudiantes/');
define('UPLOAD_PATH_REPRESENTANTES', __DIR__ . '/../assets/uploads/representantes/');
define('WHATSAPP_API_URL', 'https://graph.facebook.com/v18.0/');
define('WHATSAPP_TOKEN', '');
define('WHATSAPP_PHONE_NUMBER_ID', '');
ini_set('display_errors', 1);

error_reporting(E_ALL);
date_default_timezone_set('America/Caracas');
session_start();