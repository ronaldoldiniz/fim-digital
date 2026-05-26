<?php
/**
 * FIM Digital - Redefinição de Senha (Passo 2)
 * Formulário para criar nova senha com token válido
 */

session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/auth.php';

$token = $_GET['token'] ?? '';
$tokenValido = false;
$nomeUsuario = '';
$erro = '';
$sucesso = '';

if (empty($token)) {
    $erro = 'Token não informado. Solicite uma nova redefinição de senha.';
} else {
    $dados = validarTokenRedefinicao($token);
    if ($dados) {
        $tokenValido = true;
        $nomeUsuario = $dados['nome'];
    } else {
        $erro = 'Token inválido ou expirado. Solicite uma nova redefinição de senha.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';
    
    if (strlen($senha) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não conferem.';
    } else {
        if (redefinirSenhaComToken($token, $senha)) {
            $sucesso = 'Senha redefinida com sucesso! Você será redirecionado para o login.';
            $tokenValido = false;
        } else {
            $erro = 'Erro ao redefinir senha. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha | FIM Digital</title>
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
                    <i class="bi bi-shield-lock" style="font-size: 3rem; color: #e67e22;"></i>
                </div>
                <h1>Redefinir Senha</h1>
                <?php if ($tokenValido): ?>
                    <p>Olá, <strong><?= htmlspecialchars($nomeUsuario) ?></strong>. Crie sua nova senha.</p>
                <?php else: ?>
                    <p>Crie uma nova senha para sua conta.</p>
                <?php endif; ?>
            </div>

            <?php if ($sucesso): ?>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($sucesso) ?>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-nova-fim btn-industrial">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Ir para o Login
                    </a>
                </div>
            <?php elseif ($erro): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($erro) ?>
                </div>
                <div class="text-center mt-3">
                    <a href="recuperar_senha.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Solicitar nova redefinição
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($tokenValido): ?>
            <form method="POST" action="" id="formRedefinir">
                <div class="mb-3">
                    <label for="senha" class="form-label">
                        <i class="bi bi-lock"></i> Nova Senha
                    </label>
                    <div class="input-group">
                        <input type="password"
                               class="form-control form-control-lg"
                               id="senha"
                               name="senha"
                               placeholder="Mínimo 6 caracteres"
                               autocomplete="new-password"
                               minlength="6"
                               required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleSenha" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-text mt-1">
                        <span id="forcaSenha" class="badge bg-secondary">Digite uma senha</span>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="confirmar_senha" class="form-label">
                        <i class="bi bi-lock-fill"></i> Confirmar Senha
                    </label>
                    <input type="password"
                           class="form-control form-control-lg"
                           id="confirmar_senha"
                           name="confirmar_senha"
                           placeholder="Repita a senha"
                           autocomplete="new-password"
                           minlength="6"
                           required>
                    <div class="form-text mt-1">
                        <span id="confirmacaoSenha" class="badge bg-secondary">Confirme a senha</span>
                    </div>
                </div>
                <button type="submit" class="btn btn-nova-fim btn-industrial w-100" id="btnRedefinir">
                    <i class="bi bi-check-lg me-2"></i> Redefinir Senha
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i> Voltar ao Login
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($tokenValido): ?>
    <script>
    (function() {
        var senha = document.getElementById('senha');
        var confirmar = document.getElementById('confirmar_senha');
        var forcaSpan = document.getElementById('forcaSenha');
        var confirmaSpan = document.getElementById('confirmacaoSenha');
        var btn = document.getElementById('btnRedefinir');
        var toggleBtn = document.getElementById('toggleSenha');

        toggleBtn.addEventListener('click', function() {
            var icon = this.querySelector('i');
            if (senha.type === 'password') {
                senha.type = 'text';
                confirmar.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                senha.type = 'password';
                confirmar.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });

        function avaliarForca(s) {
            if (s.length === 0) return { text: 'Digite uma senha', cls: 'bg-secondary' };
            if (s.length < 6) return { text: 'Muito fraca', cls: 'bg-danger' };
            var forca = 0;
            if (s.length >= 8) forca++;
            if (/[a-z]/.test(s) && /[A-Z]/.test(s)) forca++;
            if (/\d/.test(s)) forca++;
            if (/[^a-zA-Z0-9]/.test(s)) forca++;
            if (forca <= 1) return { text: 'Fraca', cls: 'bg-warning text-dark' };
            if (forca === 2) return { text: 'Boa', cls: 'bg-info' };
            return { text: 'Forte', cls: 'bg-success' };
        }

        function atualizar() {
            var f = avaliarForca(senha.value);
            forcaSpan.className = 'badge ' + f.cls;
            forcaSpan.textContent = f.text;

            if (confirmar.value.length === 0) {
                confirmaSpan.className = 'badge bg-secondary';
                confirmaSpan.textContent = 'Confirme a senha';
            } else if (senha.value === confirmar.value) {
                confirmaSpan.className = 'badge bg-success';
                confirmaSpan.textContent = 'Senhas conferem';
            } else {
                confirmaSpan.className = 'badge bg-danger';
                confirmaSpan.textContent = 'Senhas não conferem';
            }
        }

        senha.addEventListener('input', atualizar);
        confirmar.addEventListener('input', atualizar);

        document.getElementById('formRedefinir').addEventListener('submit', function() {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Salvando...';
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
