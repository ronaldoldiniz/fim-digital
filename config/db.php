<?php
/**
 * FIM Digital - Configuração do Banco de Dados
 * Conexão PDO com MySQL
 */

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'fim_digital');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna a conexão PDO com o banco de dados
 * Singleton: reutiliza a mesma conexão durante a requisição
 */
function getConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Em produção, logar o erro e mostrar mensagem genérica
            die("Erro de conexão com o banco de dados. Contate o administrador.");
        }
    }
    
    return $pdo;
}
