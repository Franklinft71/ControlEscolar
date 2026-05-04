<?php
require_once 'includes/db.php';
$pdo = db_connect();
$res = $pdo->query('SHOW INDEX FROM horarios_clases WHERE Non_unique = 0')->fetchAll();
foreach ($res as $row) {
    echo "Index: " . $row['Key_name'] . " | Column: " . $row['Column_name'] . "\n";
}
