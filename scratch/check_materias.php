<?php
require_once 'includes/db.php';
$pdo = db_connect();
$res = $pdo->query('DESCRIBE materias')->fetchAll();
print_r($res);
