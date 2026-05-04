<?php
require_once 'includes/db.php';
$pdo = db_connect();
$res = $pdo->query('SHOW COLUMNS FROM materias')->fetchAll();
print_r($res);
