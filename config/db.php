<?php
/**
 * Configuração de Conexão com o Banco de Dados
 * Identifica automaticamente se o ambiente é local (XAMPP) ou produção (InfinityFree)
 */

function getConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        // Detecta se estamos rodando localmente
        $is_local = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || $_SERVER['SERVER_NAME'] === 'localhost';

        if ($is_local) {
            // CONFIGURAÇÃO LOCAL (XAMPP)
            $host = 'localhost';
            $db   = 'fim_digital';
            $user = 'root';
            $pass = '';
        } else {
            // CONFIGURAÇÃO PRODUÇÃO (INFINITYFREE)
            $host = 'sql104.infinityfree.com';
            $db   = 'if0_41866708_controledeinspecoes';
            $user = 'if0_41866708';
            $pass = 'ZYzM1s88Or3sRu6';
        }

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            if ($is_local) {
                die("Erro na conexão local: " . $e->getMessage());
            } else {
                die("Erro de conexão com o banco de dados. Por favor, verifique se o banco foi importado corretamente no InfinityFree.");
            }
        }
    }
    
    return $pdo;
}
