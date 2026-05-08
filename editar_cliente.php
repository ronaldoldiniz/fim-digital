<?php
/** FIM Digital - Tela para Alteração de Cliente (Histórico) */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
verificarLogin();
verificarPerfil(['ADMINISTRATIVO','GESTOR']);

$pdo = getConnection();
$registro_id = (int)($_GET['id'] ?? 0);

if (!$registro_id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("
    SELECT r.id, e.id_interno, dc.nome_cliente 
    FROM registros_fim r 
    JOIN equipamentos e ON r.equipamento_id = e.id 
    JOIN dados_cliente dc ON dc.registro_id = r.id 
    WHERE r.id = ?
");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();

if (!$reg) { header('Location: index.php'); exit; }

headerHTML('Alterar Cliente', 'consulta');
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card card-industrial fade-in">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-person-gear me-2"></i> Alterar Cliente - FIM: <?= sanitizar($reg['id_interno']) ?></h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i> 
                    Esta operação irá registrar um histórico permanente na linha do tempo do equipamento.
                </div>

                <form id="formAlterarCliente" onsubmit="event.preventDefault(); alterar();">
                    <input type="hidden" name="registro_id" value="<?= $reg['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted">Cliente Atual</label>
                        <input type="text" class="form-control bg-light" value="<?= sanitizar($reg['nome_cliente']) ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label obrigatorio">Novo Cliente</label>
                        <input type="text" class="form-control form-control-lg" name="cliente_novo" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label obrigatorio">Motivo Principal</label>
                        <select class="form-select" name="motivo" required>
                            <option value="">Selecione...</option>
                            <option value="Alteração de Pedido">Alteração de Pedido</option>
                            <option value="Otimização de Performance">Otimização de Performance</option>
                            <option value="Realocação de Estoque">Realocação de Estoque</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Detalhes Adicionais (Opcional)</label>
                        <textarea class="form-control" name="motivo_detalhe" rows="2" placeholder="Justificativa mais detalhada..."></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="consulta.php?q=<?= $reg['id_interno'] ?>" class="btn btn-outline-secondary btn-industrial">Cancelar</a>
                        <button type="submit" class="btn btn-warning btn-industrial fw-bold" id="btnSalvar">
                            <i class="bi bi-check-circle"></i> Confirmar Alteração
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function alterar() {
    const form = document.getElementById('formAlterarCliente');
    const btn = document.getElementById('btnSalvar');
    btn.disabled = true;
    
    fetch('actions/alterar_cliente.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(r => r.json())
    .then(data => {
        if(data.sucesso) {
            alert('Cliente alterado com sucesso!');
            window.location.href = 'consulta.php?q=<?= $reg['id_interno'] ?>';
        } else {
            alert(data.mensagem);
            btn.disabled = false;
        }
    });
}
</script>

<?php footerHTML(); ?>
