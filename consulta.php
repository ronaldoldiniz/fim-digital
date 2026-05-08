<?php
/** FIM Digital - Tela de Consulta e Timeline */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
verificarLogin();

$pdo = getConnection();
$busca = trim($_GET['q'] ?? '');
$registro = null;
$historico = [];
$logs = [];

if ($busca) {
    // Buscar FIM
    $stmt = $pdo->prepare("
        SELECT r.*, e.numero_serie_motor, e.id_interno, e.modelo_equipamento,
               dc.nome_cliente, u1.nome as op_inicio, u2.nome as op_final
        FROM registros_fim r
        JOIN equipamentos e ON r.equipamento_id = e.id
        LEFT JOIN dados_cliente dc ON dc.registro_id = r.id
        LEFT JOIN usuarios u1 ON r.operador_inicio_id = u1.id
        LEFT JOIN usuarios u2 ON r.operador_final_id = u2.id
        WHERE e.id_interno = ? OR e.numero_serie_motor = ? OR r.numero_conserto = ?
        ORDER BY r.data_inicio DESC LIMIT 1
    ");
    $stmt->execute([$busca, $busca, $busca]);
    $registro = $stmt->fetch();

    if ($registro) {
        // Histórico de Cliente
        $stmtH = $pdo->prepare("
            SELECT h.*, u.nome as usuario_nome 
            FROM historico_cliente h JOIN usuarios u ON h.usuario_id = u.id 
            WHERE h.registro_id = ? ORDER BY h.data_alteracao DESC
        ");
        $stmtH->execute([$registro['id']]);
        $historico = $stmtH->fetchAll();

        // Logs de Edição (se finalizado)
        $stmtL = $pdo->prepare("
            SELECT l.*, u.nome as usuario_nome 
            FROM log_edicoes l JOIN usuarios u ON l.usuario_id = u.id 
            WHERE l.registro_id = ? ORDER BY l.data DESC
        ");
        $stmtL->execute([$registro['id']]);
        $logs = $stmtL->fetchAll();
    }
}

headerHTML('Consulta', 'consulta');
?>

<div class="row justify-content-center mb-4 fade-in">
    <div class="col-md-8">
        <div class="card card-industrial">
            <div class="card-body">
                <h5 class="card-title text-center mb-4"><i class="bi bi-search me-2"></i> Rastreabilidade de FIM</h5>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control form-control-lg" 
                           placeholder="Digite o ID Interno, Nº de Série ou Nº Conserto..." 
                           value="<?= sanitizar($busca) ?>" required autofocus>
                    <button type="submit" class="btn btn-primary btn-industrial px-4">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($busca && !$registro): ?>
    <div class="alert alert-warning text-center fade-in">
        <i class="bi bi-emoji-frown fs-4 d-block mb-2"></i>
        Nenhuma FIM encontrada para "<strong><?= sanitizar($busca) ?></strong>".
    </div>
<?php elseif ($registro): ?>
    
    <div class="row fade-in">
        <div class="col-lg-8">
            <!-- Card Principal -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-file-text"></i> <?= sanitizar($registro['id_interno']) ?>
                    </h5>
                    <?= badgeStatus($registro['status']) ?>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <p class="mb-1 text-muted">Nº Série Motor</p>
                            <p class="fw-bold"><?= sanitizar($registro['numero_serie_motor'] ?: '-') ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-1 text-muted">Natureza</p>
                            <p class="fw-bold"><?= badgeNatureza($registro['natureza']) ?> <?= $registro['natureza']==='CONSERTO' ? '(Nº '.sanitizar($registro['numero_conserto']).')' : '' ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <p class="mb-1 text-muted">Cliente Atual</p>
                            <p class="fw-bold"><?= sanitizar($registro['nome_cliente'] ?: 'Não definido') ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-1 text-muted">Modelo Equipamento</p>
                            <p class="fw-bold"><?= sanitizar($registro['modelo_equipamento'] ?: '-') ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="formulario.php?id=<?= $registro['id'] ?>" class="btn btn-primary btn-industrial">
                            <i class="bi bi-eye"></i> Visualizar FIM Completa
                        </a>
                        <a href="reports/gerar_pdf.php?id=<?= $registro['id'] ?>" target="_blank" class="btn btn-info btn-industrial">
                            <i class="bi bi-file-pdf"></i> Gerar PDF
                        </a>
                        <?php if(isGestor()): ?>
                            <a href="editar_cliente.php?id=<?= $registro['id'] ?>" class="btn btn-warning btn-industrial">
                                <i class="bi bi-person-gear"></i> Alterar Cliente
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Logs de Edição (Auditoria) -->
            <?php if (!empty($logs)): ?>
                <div class="card mb-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i> Auditoria de Edições (Pós-Finalização)</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach($logs as $log): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Campo alterado: <strong><?= sanitizar($log['campo']) ?></strong></h6>
                                    <small class="text-muted"><?= formatarData($log['data']) ?></small>
                                </div>
                                <p class="mb-1">
                                    De: <span class="text-danger text-decoration-line-through"><?= sanitizar($log['valor_antigo']) ?></span> 
                                    <i class="bi bi-arrow-right mx-1"></i> 
                                    Para: <span class="text-success fw-bold"><?= sanitizar($log['valor_novo']) ?></span>
                                </p>
                                <small class="text-muted">
                                    <i class="bi bi-person-badge"></i> <?= sanitizar($log['usuario_nome']) ?> | 
                                    Motivo: <?= sanitizar($log['motivo']) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Timeline de Vida do Equipamento -->
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i> Linha do Tempo</h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <!-- Criação -->
                        <div class="timeline-item pb-3 border-bottom mb-3">
                            <div class="fw-bold text-primary"><i class="bi bi-plus-circle-fill"></i> Criação / Em Bancada</div>
                            <div class="text-muted small"><?= formatarData($registro['data_inicio']) ?></div>
                            <div class="small mt-1">Por: <?= sanitizar($registro['op_inicio']) ?></div>
                        </div>
                        
                        <!-- Histórico de Clientes -->
                        <?php foreach (array_reverse($historico) as $h): ?>
                            <div class="timeline-item pb-3 border-bottom mb-3">
                                <div class="fw-bold text-warning"><i class="bi bi-person-fill-exclamation"></i> Alteração de Cliente</div>
                                <div class="text-muted small"><?= formatarData($h['data_alteracao']) ?></div>
                                <div class="small mt-1">
                                    De: <?= sanitizar($h['cliente_anterior']) ?><br>
                                    Para: <strong><?= sanitizar($h['cliente_novo']) ?></strong>
                                </div>
                                <div class="small mt-1 fst-italic">"<?= sanitizar($h['motivo']) ?>"</div>
                                <div class="small text-muted">Por: <?= sanitizar($h['usuario_nome']) ?></div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Finalização -->
                        <?php if ($registro['status'] === 'FINALIZADO'): ?>
                            <div class="timeline-item">
                                <div class="fw-bold text-success"><i class="bi bi-check-circle-fill"></i> FIM Finalizada</div>
                                <div class="text-muted small"><?= formatarData($registro['data_finalizacao']) ?></div>
                                <div class="small mt-1">Por: <?= sanitizar($registro['op_final']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php footerHTML(); ?>
