<?php
require_once 'includes/db.php';
$pdo = db_connect();
$res = $pdo->query('SHOW INDEX FROM horarios_clases')->fetchAll();
print_r($res);
