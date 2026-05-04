<?php
require_once 'includes/db.php';
$pdo = db_connect();
$bloques = $pdo->query("SELECT * FROM bloques_horarios ORDER BY orden")->fetchAll();
echo json_encode($bloques, JSON_PRETTY_PRINT);
