<?php
require_once 'includes/db.php';
$pdo = db_connect();
$affected = $pdo->exec("UPDATE materias SET horas_semanales = 2");
echo "Updated $affected rows.";
