<?php
/**
 * FIM Digital - Recuperação de Senha
 * Redefinição de senha via login do usuário
 */

session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/auth.php';
$pdo = getConnection();

$mensagem = '';
$tipoMsg = '';
$login = '';
$linkExibido = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    
    if (empty($login)) {
        $tipoMsg = 'danger';
        $mensagem = 'Informe seu nome de usuário.';
    } else {
        $stmt = $pdo->prepare("SELECT id, nome, login, email FROM usuarios WHERE login = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$login]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            // Invalidar tokens anteriores do usuário
            $pdo->prepare("UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE usuario_id = ? AND used_at IS NULL")->execute([$usuario['id']]);
            
            $token = gerarTokenRedefinicao($usuario['id']);
            if ($token) {
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                         . '://' . $_SERVER['HTTP_HOST']
                         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $linkExibido = $baseUrl . '/redefinir_senha.php?token=' . urlencode($token);

                // Tentar enviar email se o usuário tiver email cadastrado
                if (!empty($usuario['email'])) {
                    $corpoHtml = '
                    <!DOCTYPE html><html><head><meta charset="utf-8"><style>
                        body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
                        .container{max-width:600px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1)}
                        .header{background:linear-gradient(135deg,#1a2332,#2c3e50);padding:30px;text-align:center}
                        .header h1{color:#e67e22;margin:0;font-size:24px}
                        .header p{color:#ccc;margin:5px 0 0;font-size:14px}
                        .body{padding:30px}
                        .body h2{color:#333;font-size:20px;margin:0 0 15px}
                        .body p{color:#666;line-height:1.6;margin:0 0 20px}
                        .btn{display:inline-block;background:#e67e22;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:bold;font-size:16px}
                        .btn:hover{background:#d35400}
                        .footer{padding:20px 30px;text-align:center;color:#999;font-size:12px;border-top:1px solid #eee}
                    </style></head><body>
                    <div class="container">
                        <div class="header"><h1>FIM Digital</h1><p>Redefinição de Senha</p></div>
                        <div class="body">
                            <h2>Olá, ' . htmlspecialchars($usuario['nome']) . '!</h2>
                            <p>Recebemos uma solicitação de redefinição de senha para sua conta no <strong>FIM Digital</strong>.</p>
                            <p>Clique no botão abaixo para criar uma nova senha. Este link expira em <strong>1 hora</strong>.</p>
                            <p style="text-align:center"><a href="' . $linkExibido . '" class="btn">Redefinir Senha</a></p>
                            <p>Se você não solicitou esta redefinição, ignore este email.</p>
                        </div>
                        <div class="footer">FIM Digital &copy; ' . date('Y') . ' Morot&oacute; Ind&uacute;stria</div>
                    </div></body></html>';
                    enviarEmail($usuario['email'], 'Redefinição de Senha - FIM Digital', $corpoHtml);
                }

                $mensagem = 'Clique no link abaixo para redefinir sua senha. O link expira em <strong>1 hora</strong>.';
                $tipoMsg = 'success';
            } else {
                $mensagem = 'Erro ao gerar token. Tente novamente.';
                $tipoMsg = 'danger';
            }
        } else {
            $mensagem = 'Usuário não encontrado ou inativo.';
            $tipoMsg = 'danger';
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
                <p>Digite seu nome de usuário para receber o link de redefinição</p>
            </div>

            <?php if ($mensagem): ?>
                <?php if ($tipoMsg === 'success'): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= $mensagem ?>
                    <?php if ($linkExibido): ?>
                    <div class="mt-2 p-2 bg-white rounded border" style="word-break:break-all;font-size:0.85rem">
                        <a href="<?= htmlspecialchars($linkExibido) ?>"><?= htmlspecialchars($linkExibido) ?></a>
                    </div>
                    <div class="mt-2 small">
                        <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($linkExibido) ?>');this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar link',2000)">
                            <i class="bi bi-clipboard"></i> Copiar link
                        </button>
                    </div>
                    <?php endif; ?>
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
                    <label for="login" class="form-label">
                        <i class="bi bi-person"></i> Usuário
                    </label>
                    <input type="text"
                           class="form-control form-control-lg"
                           id="login"
                           name="login"
                           placeholder="Digite seu login"
                           value="<?= htmlspecialchars($login) ?>"
                           autocomplete="username"
                           required
                           autofocus>
                </div>
                <button type="submit" class="btn btn-nova-fim btn-industrial w-100" id="btnEnviar">
                    <i class="bi bi-send me-2"></i> Gerar Link de Redefinição
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
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Gerando...';
    });
    </script>
</body>
</html>
