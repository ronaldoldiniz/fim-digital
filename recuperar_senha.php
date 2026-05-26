<?php
/**
 * FIM Digital - Recuperação de Senha (Passo 1)
 * Formulário para solicitar redefinição de senha via email
 */

session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
$pdo = getConnection();

// Auto-migration: criar coluna email e tabela password_resets se não existirem
try {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN email VARCHAR(255) DEFAULT NULL UNIQUE AFTER login");
} catch (PDOException $e) {
    // Coluna já existe - ignorar
}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // Tabela já existe - ignorar
}

require_once __DIR__ . '/config/auth.php';

$mensagem = '';
$tipoMsg = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $tipoMsg = 'danger';
        $mensagem = 'Informe um endereço de email válido.';
    } else {
        $usuario = buscarUsuarioPorEmail($email);
        
        // Sempre mostra a mesma mensagem por segurança (não revelar se email existe)
        $mensagem = 'Se este email estiver cadastrado, você receberá instruções para redefinir sua senha em alguns minutos.';
        $tipoMsg = 'success';
        
        if ($usuario) {
            $token = gerarTokenRedefinicao($usuario['id']);
            if ($token) {
                $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                      . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')
                      . '/redefinir_senha.php?token=' . urlencode($token);
                
                $corpoHtml = '
                <!DOCTYPE html>
                <html>
                <head><meta charset="utf-8"><style>
                    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #1a2332, #2c3e50); padding: 30px; text-align: center; }
                    .header h1 { color: #e67e22; margin: 0; font-size: 24px; }
                    .header p { color: #ccc; margin: 5px 0 0; font-size: 14px; }
                    .body { padding: 30px; }
                    .body h2 { color: #333; font-size: 20px; margin: 0 0 15px; }
                    .body p { color: #666; line-height: 1.6; margin: 0 0 20px; }
                    .btn { display: inline-block; background: #e67e22; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; font-size: 16px; }
                    .btn:hover { background: #d35400; }
                    .footer { padding: 20px 30px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; }
                </style></head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>FIM Digital</h1>
                            <p>Redefinição de Senha</p>
                        </div>
                        <div class="body">
                            <h2>Olá, ' . htmlspecialchars($usuario['nome']) . '!</h2>
                            <p>Recebemos uma solicitação de redefinição de senha para sua conta no <strong>FIM Digital</strong>.</p>
                            <p>Clique no botão abaixo para criar uma nova senha. Este link expira em <strong>1 hora</strong>.</p>
                            <p style="text-align:center"><a href="' . $link . '" class="btn">Redefinir Senha</a></p>
                            <p>Se você não solicitou esta redefinição, ignore este email. Sua senha permanecerá a mesma.</p>
                            <p style="font-size:13px;color:#999">Ou copie o link:<br>' . $link . '</p>
                        </div>
                        <div class="footer">
                            FIM Digital &copy; ' . date('Y') . ' Morot&oacute; Ind&uacute;stria e Com&eacute;rcio de Aspiradores Industriais
                        </div>
                    </div>
                </body>
                </html>';
                
                enviarEmail($usuario['email'], 'Redefinição de Senha - FIM Digital', $corpoHtml);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha | FIM Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card fade-in" style="max-width:460px">
            <div class="logo-area">
                <div class="mb-3">
                    <i class="bi bi-key" style="font-size: 3rem; color: #e67e22;"></i>
                </div>
                <h1>Recuperar Senha</h1>
                <p>Insira seu email cadastrado para receber o link de redefinição</p>
            </div>

            <?php if ($mensagem): ?>
                <?php if ($tipoMsg === 'success'): ?>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($mensagem) ?>
                </div>
                <?php else: ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($mensagem) ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" action="" id="formRecuperar">
                <div class="mb-4">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope"></i> Email
                    </label>
                    <input type="email"
                           class="form-control form-control-lg"
                           id="email"
                           name="email"
                           placeholder="seu@email.com"
                           value="<?= htmlspecialchars($email) ?>"
                           autocomplete="email"
                           required
                           autofocus>
                </div>
                <button type="submit" class="btn btn-nova-fim btn-industrial w-100" id="btnEnviar">
                    <i class="bi bi-send me-2"></i> Enviar Link de Recuperação
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i> Voltar ao Login
                </a>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('formRecuperar').addEventListener('submit', function() {
        var btn = document.getElementById('btnEnviar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando...';
    });
    </script>
</body>
</html>
