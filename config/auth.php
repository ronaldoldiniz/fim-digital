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
    
    $stmt = $pdo->prepare("SELECT id, nome, login, email, senha_hash, perfil, ativo FROM usuarios WHERE login = ? LIMIT 1");
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

/**
 * Busca usuário pelo email
 */
function buscarUsuarioPorEmail(string $email): array|false {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, nome, login, email FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

/**
 * Gera token de redefinição de senha
 */
function gerarTokenRedefinicao(int $usuario_id): string|false {
    $pdo = getConnection();
    $token = bin2hex(random_bytes(32));
    // Usar UTC para evitar diferença de timezone entre PHP e MySQL
    $expires = gmdate('Y-m-d H:i:s', strtotime('+1 hour'));
    $stmt = $pdo->prepare("INSERT INTO password_resets (usuario_id, token, expires_at) VALUES (?, ?, ?)");
    if ($stmt->execute([$usuario_id, $token, $expires])) {
        return $token;
    }
    return false;
}

/**
 * Valida token de redefinição e retorna dados do usuário
 */
function validarTokenRedefinicao(string $token): array|false {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT pr.*, u.id as user_id, u.nome, u.login, u.email
        FROM password_resets pr
        JOIN usuarios u ON pr.usuario_id = u.id
        WHERE pr.token = ? AND pr.used_at IS NULL AND pr.expires_at > UTC_TIMESTAMP()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

/**
 * Marca token como utilizado e atualiza a senha
 */
function redefinirSenhaComToken(string $token, string $novaSenha): bool {
    $pdo = getConnection();
    $dados = validarTokenRedefinicao($token);
    if (!$dados) return false;
    
    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $stmt1 = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
        $stmt1->execute([$hash, $dados['user_id']]);
        
        $stmt2 = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
        $stmt2->execute([$dados['id']]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
