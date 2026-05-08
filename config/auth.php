<?php
/**
 * FIM Digital - Sistema de Autenticação
 * Controle de sessão, login e permissões
 */

session_start();

/**
 * Verifica se o usuário está logado. Redireciona para login se não estiver.
 */
function verificarLogin(): void {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Verifica se o usuário tem o perfil necessário.
 * @param array $perfis_permitidos Lista de perfis que podem acessar
 */
function verificarPerfil(array $perfis_permitidos): void {
    verificarLogin();
    if (!in_array($_SESSION['usuario_perfil'], $perfis_permitidos)) {
        http_response_code(403);
        echo '<div class="alert alert-danger m-5">Acesso negado. Você não tem permissão para acessar esta página.</div>';
        exit;
    }
}

/**
 * Retorna os dados do usuário logado
 */
function usuarioLogado(): array {
    return [
        'id'    => $_SESSION['usuario_id'] ?? 0,
        'nome'  => $_SESSION['usuario_nome'] ?? '',
        'login' => $_SESSION['usuario_login'] ?? '',
        'perfil'=> $_SESSION['usuario_perfil'] ?? ''
    ];
}

/**
 * Verifica se o usuário logado é Gestor
 */
function isGestor(): bool {
    return ($_SESSION['usuario_perfil'] ?? '') === 'GESTOR';
}

/**
 * Verifica se o usuário logado é Administrativo ou Gestor
 */
function isAdmin(): bool {
    $perfil = $_SESSION['usuario_perfil'] ?? '';
    return in_array($perfil, ['ADMINISTRATIVO', 'GESTOR']);
}

/**
 * Realiza o login do usuário
 */
function fazerLogin(string $login, string $senha): bool|string {
    require_once __DIR__ . '/db.php';
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("SELECT id, nome, login, senha_hash, perfil, ativo FROM usuarios WHERE login = ? LIMIT 1");
    $stmt->execute([$login]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        return 'Usuário não encontrado.';
    }
    
    if (!$usuario['ativo']) {
        return 'Usuário desativado. Contate o administrador.';
    }
    
    if (!password_verify($senha, $usuario['senha_hash'])) {
        return 'Senha incorreta.';
    }
    
    // Criar sessão
    $_SESSION['usuario_id']    = $usuario['id'];
    $_SESSION['usuario_nome']  = $usuario['nome'];
    $_SESSION['usuario_login'] = $usuario['login'];
    $_SESSION['usuario_perfil']= $usuario['perfil'];
    
    return true;
}

/**
 * Realiza o logout
 */
function fazerLogout(): void {
    session_destroy();
    header('Location: login.php');
    exit;
}
