<?php
require_once 'includes/db.php';
$pdo = db_connect();
echo "Total materias: " . $pdo->query("SELECT COUNT(*) FROM materias")->fetchColumn();
