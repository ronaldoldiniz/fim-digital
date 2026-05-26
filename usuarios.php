<?php
/** FIM Digital - Gestão de Usuários */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
verificarLogin();
verificarPerfil(['GESTOR']); // Apenas GESTOR

$pdo = getConnection();
$erro = '';
$sucesso = '';
$linkGerado = '';

// GET: Gerar link de redefinição para um usuário
if (isset($_GET['gerar_link'])) {
    $uid = (int)$_GET['gerar_link'];
    $stmt = $pdo->prepare("SELECT id, nome, login FROM usuarios WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if ($user) {
        $pdo->prepare("UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE usuario_id = ? AND used_at IS NULL")->execute([$uid]);
        $token = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', strtotime('+1 hour'));
        $pdo->prepare("INSERT INTO password_resets (usuario_id, token, expires_at) VALUES (?, ?, ?)")->execute([$uid, $token, $expires]);
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                 . '://' . $_SERVER['HTTP_HOST']
                 . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $linkGerado = $baseUrl . '/redefinir_senha.php?token=' . urlencode($token);
        $sucesso = 'Link gerado para <strong>' . htmlspecialchars($user['nome']) . '</strong> (' . htmlspecialchars($user['login']) . '):';
    } else {
        $erro = 'Usuário não encontrado.';
    }
}

// POST: Criar/Editar usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['gerar_link'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $perfil = $_POST['perfil'] ?? 'OPERADOR';
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    if ($email === '') $email = null;

    if (!$nome || !$login || (!$id && !$senha)) {
        $erro = 'Preencha todos os campos obrigatórios.';
    } else {
        try {
            if ($id) {
                // Update
                if ($senha) {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, login=?, email=?, senha_hash=?, perfil=?, ativo=? WHERE id=?");
                    $stmt->execute([$nome, $login, $email, $hash, $perfil, $ativo, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, login=?, email=?, perfil=?, ativo=? WHERE id=?");
                    $stmt->execute([$nome, $login, $email, $perfil, $ativo, $id]);
                }
                $sucesso = 'Usuário atualizado com sucesso!';
            } else {
                // Insert
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, login, email, senha_hash, perfil, ativo) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$nome, $login, $email, $hash, $perfil, $ativo]);
                $sucesso = 'Usuário criado com sucesso!';
            }
        } catch (Exception $e) {
            $erro = 'Erro: o login ou email já existe ou ocorreu falha no banco de dados.';
        }
    }
}

$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY nome")->fetchAll();

headerHTML('Usuários', 'usuarios');
?>

<div class="row fade-in">
    <div class="col-md-4 mb-4">
        <div class="card card-industrial">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i> Novo/Editar Usuário</h5>
            </div>
            <div class="card-body">
                <?php if($erro): ?><div class="alert alert-danger"><?= $erro ?></div><?php endif; ?>
                <?php if($sucesso): ?>
                <div class="alert alert-success">
                    <?= $sucesso ?>
                    <?php if ($linkGerado): ?>
                    <div class="mt-2 p-2 bg-white rounded border" style="word-break:break-all;font-size:0.85rem">
                        <a href="<?= htmlspecialchars($linkGerado) ?>"><?= htmlspecialchars($linkGerado) ?></a>
                    </div>
                    <button class="btn btn-sm btn-outline-dark mt-1" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($linkGerado) ?>');this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar link',2000)">
                        <i class="bi bi-clipboard"></i> Copiar link
                    </button>
                    <div class="small text-muted mt-1">⏳ Expira em 1 hora</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="formUser">
                    <input type="hidden" name="id" id="userId">
                    <div class="mb-3">
                        <label class="form-label obrigatorio">Nome Completo</label>
                        <input type="text" class="form-control" name="nome" id="userNome" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label obrigatorio">Login</label>
                        <input type="text" class="form-control" name="login" id="userLogin" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <small class="text-muted">(para recuperação de senha)</small></label>
                        <input type="email" class="form-control" name="email" id="userEmail" placeholder="usuario@exemplo.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha <small class="text-muted">(preencha para alterar)</small></label>
                        <input type="password" class="form-control" name="senha" id="userSenha">
                    </div>
                    <div class="mb-3">
                        <label class="form-label obrigatorio">Perfil</label>
                        <select class="form-select" name="perfil" id="userPerfil" required>
                            <option value="OPERADOR">Operador (Bancada/Montagem)</option>
                            <option value="ADMINISTRATIVO">Administrativo</option>
                            <option value="GESTOR">Gestor</option>
                        </select>
                    </div>
                    <div class="mb-4 form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ativo" id="userAtivo" checked>
                        <label class="form-check-label">Usuário Ativo</label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-industrial">Salvar Usuário</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpar()">Novo Cadastro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card card-industrial">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i> Usuários do Sistema</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Nome</th>
                            <th>Login</th>
                            <th>Email</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usuarios as $u): ?>
                            <tr>
                                <td><?= sanitizar($u['nome']) ?></td>
                                <td><?= sanitizar($u['login']) ?></td>
                                <td><small><?= sanitizar($u['email'] ?? '') ?: '<span class="text-muted">—</span>' ?></small></td>
                                <td>
                                    <?php 
                                        $cores = ['OPERADOR'=>'secondary','ADMINISTRATIVO'=>'info','GESTOR'=>'danger'];
                                        $cor = $cores[$u['perfil']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $cor ?>"><?= $u['perfil'] ?></span>
                                </td>
                                <td>
                                    <?php if($u['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap">
                                    <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editar(<?= $u['id'] ?>, '<?= sanitizar($u['nome']) ?>', '<?= sanitizar($u['login']) ?>', '<?= sanitizar($u['email'] ?? '') ?>', '<?= $u['perfil'] ?>', <?= $u['ativo'] ?>)"
                                        title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?gerar_link=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning" title="Gerar link de redefinição de senha">
                                        <i class="bi bi-key"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editar(id, nome, login, email, perfil, ativo) {
    document.getElementById('userId').value = id;
    document.getElementById('userNome').value = nome;
    document.getElementById('userLogin').value = login;
    document.getElementById('userEmail').value = email;
    document.getElementById('userPerfil').value = perfil;
    document.getElementById('userAtivo').checked = (ativo == 1);
    document.getElementById('userSenha').required = false;
    document.getElementById('userNome').focus();
}
function limpar() {
    document.getElementById('userId').value = '';
    document.getElementById('formUser').reset();
    document.getElementById('userSenha').required = true;
}
</script>

<?php footerHTML(); ?>
