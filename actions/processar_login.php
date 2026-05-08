<?php
/**
 * FIM Digital - Processar Login / Logout
 */

require_once __DIR__ . '/../config/auth.php';

// Logout
if (isset($_GET['logout'])) {
    fazerLogout();
    exit;
}

// Login via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    $resultado = fazerLogin($login, $senha);
    
    if ($resultado === true) {
        header('Location: ../public/index.php');
    } else {
        header('Location: ../public/login.php?erro=' . urlencode($resultado));
    }
    exit;
}

header('Location: ../public/login.php');
exit;
