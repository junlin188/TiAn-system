<?php
declare(strict_types=1);

chdir(dirname(__DIR__));
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
ob_start();
require __DIR__ . '/../public/index.php';
ob_end_clean();

db();

$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/app.sqlite');
$users = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

echo "db-ok\n";
echo $users . " users\n";
