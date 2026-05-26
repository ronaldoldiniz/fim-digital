<?php
/**
 * FIM Digital - Página de Login
 * Autenticação de usuários do sistema
 */

session_start();

// Se já está logado, redirecionar para dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/auth.php';
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($login) || empty($senha)) {
        $erro = 'Preencha todos os campos.';
    } else {
        $resultado = fazerLogin($login, $senha);
        if ($resultado === true) {
            header('Location: index.php');
            exit;
        } else {
            $erro = $resultado;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | FIM Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card fade-in">
            <div class="logo-area">
                <div class="mb-3">
                    <i class="bi bi-clipboard2-check" style="font-size: 3rem; color: #e67e22;"></i>
                </div>
                <h1>FIM Digital</h1>
                <p>Ficha de Inspeção e Medição</p>
                <small class="text-muted">Morotó Indústria e Comércio de Aspiradores Industriais</small>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="login" class="form-label">
                        <i class="bi bi-person"></i> Usuário
                    </label>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="login" 
                           name="login" 
                           placeholder="Digite seu login"
                           value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                           autocomplete="username"
                           required 
                           autofocus>
                </div>
                <div class="mb-4">
                    <label for="senha" class="form-label">
                        <i class="bi bi-lock"></i> Senha
                    </label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control form-control-lg" 
                               id="senha" 
                               name="senha" 
                               placeholder="Digite sua senha"
                               autocomplete="current-password"
                               required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleSenha" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-nova-fim btn-industrial w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Entrar
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="recuperar_senha.php" class="text-decoration-none small">
                    <i class="bi bi-question-circle me-1"></i> Esqueceu sua senha?
                </a>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">
                    FIM Digital v1.0 &copy; <?= date('Y') ?> Morotó Indústria
                </small>
            </div>
        </div>
    </div>

    <script>
    // Toggle visibilidade da senha
    document.getElementById('toggleSenha').addEventListener('click', function() {
        const input = document.getElementById('senha');
        const icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
    </script>
</body>
</html>
