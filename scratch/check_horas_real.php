<?php
require_once 'includes/db.php';
$pdo = db_connect();
$stmt = $pdo->query("SELECT nombre, horas_semanales FROM materias");
$res = $stmt->fetchAll();
print_r($res);
