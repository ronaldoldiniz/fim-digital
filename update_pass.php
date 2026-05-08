<?php
require 'config/db.php';
$pdo = getConnection();
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$pdo->exec("UPDATE usuarios SET senha_hash='$hash' WHERE login='admin'");
echo 'Updated';
