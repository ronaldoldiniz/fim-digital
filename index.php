<?php
/**
 * FIM Digital - Dashboard
 * Página inicial com visão geral das FIMs em aberto
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
verificarLogin();

// Logout
if (isset($_GET['logout'])) {
    fazerLogout();
}

$pdo = getConnection();
$usuario = usuarioLogado();

// Contadores por status
$stmtContadores = $pdo->query("
    SELECT status, COUNT(*) as total 
    FROM registros_fim 
    GROUP BY status
");
$contadores = ['EM_BANCADA' => 0, 'EM_MONTAGEM' => 0, 'AGUARDANDO_CLIENTE' => 0, 'FINALIZADO' => 0];
while ($row = $stmtContadores->fetch()) {
    $contadores[$row['status']] = $row['total'];
}

// Filtros
$filtroStatus = $_GET['status'] ?? '';
$filtroData = $_GET['data'] ?? '';
$filtroBusca = $_GET['busca'] ?? '';

// Montar query com filtros
$where = ["1=1"];
$params = [];

if ($filtroStatus && $filtroStatus !== 'TODOS') {
    $where[] = "r.status = ?";
    $params[] = $filtroStatus;
} else {
    // Por padrão, mostrar apenas FIMs em aberto
    $where[] = "r.status != 'FINALIZADO'";
}

if ($filtroData) {
    $where[] = "DATE(r.data_inicio) = ?";
    $params[] = $filtroData;
}

if ($filtroBusca) {
    $where[] = "(e.numero_serie_motor LIKE ? OR e.id_interno LIKE ? OR r.numero_conserto LIKE ? OR dc.nome_cliente LIKE ?)";
    $busca = '%' . $filtroBusca . '%';
    $params = array_merge($params, [$busca, $busca, $busca, $busca]);
}

$whereSQL = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT r.*, e.numero_serie_motor, e.id_interno, e.modelo_equipamento,
           u.nome as operador_nome, dc.nome_cliente
    FROM registros_fim r
    JOIN equipamentos e ON r.equipamento_id = e.id
    JOIN usuarios u ON r.operador_inicio_id = u.id
    LEFT JOIN dados_cliente dc ON dc.registro_id = r.id
    WHERE {$whereSQL}
    ORDER BY 
        CASE r.status 
            WHEN 'EM_BANCADA' THEN 1 
            WHEN 'EM_MONTAGEM' THEN 2 
            WHEN 'AGUARDANDO_CLIENTE' THEN 3 
            WHEN 'FINALIZADO' THEN 4 
        END,
        r.data_inicio DESC
    LIMIT 100
");
$stmt->execute($params);
$registros = $stmt->fetchAll();

headerHTML('Dashboard', 'dashboard');
?>

<!-- Cards de Resumo -->
<div class="row g-3 mb-4 fade-in">
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-bancada">
            <div class="stat-numero"><?= $contadores['EM_BANCADA'] ?></div>
            <div class="stat-label"><i class="bi bi-wrench me-1"></i> Em Bancada</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-montagem">
            <div class="stat-numero"><?= $contadores['EM_MONTAGEM'] ?></div>
            <div class="stat-label"><i class="bi bi-gear me-1"></i> Em Montagem</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-cliente">
            <div class="stat-numero"><?= $contadores['AGUARDANDO_CLIENTE'] ?></div>
            <div class="stat-label"><i class="bi bi-person me-1"></i> Aguard. Cliente</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card stat-finalizado">
            <div class="stat-numero"><?= $contadores['FINALIZADO'] ?></div>
            <div class="stat-label"><i class="bi bi-check-circle me-1"></i> Finalizados</div>
        </div>
    </div>
</div>

<!-- Barra de Ações -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="nova_fim.php" class="btn btn-nova-fim btn-industrial w-100">
            <i class="bi bi-plus-circle-fill me-2"></i> NOVA FIM
        </a>
    </div>
    <div class="col-md-8">
        <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-select" style="max-width: 200px;">
                <option value="">Em Aberto</option>
                <option value="TODOS" <?= $filtroStatus === 'TODOS' ? 'selected' : '' ?>>Todos</option>
                <option value="EM_BANCADA" <?= $filtroStatus === 'EM_BANCADA' ? 'selected' : '' ?>>Em Bancada</option>
                <option value="EM_MONTAGEM" <?= $filtroStatus === 'EM_MONTAGEM' ? 'selected' : '' ?>>Em Montagem</option>
                <option value="AGUARDANDO_CLIENTE" <?= $filtroStatus === 'AGUARDANDO_CLIENTE' ? 'selected' : '' ?>>Aguard. Cliente</option>
                <option value="FINALIZADO" <?= $filtroStatus === 'FINALIZADO' ? 'selected' : '' ?>>Finalizado</option>
            </select>
            <input type="date" name="data" class="form-control" style="max-width: 180px;" 
                   value="<?= sanitizar($filtroData) ?>" placeholder="Data">
            <input type="text" name="busca" class="form-control" 
                   value="<?= sanitizar($filtroBusca) ?>" placeholder="Buscar nº série, ID, conserto, cliente...">
            <button type="submit" class="btn btn-primary btn-industrial">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($filtroStatus || $filtroData || $filtroBusca): ?>
                <a href="index.php" class="btn btn-outline-secondary btn-industrial">
                    <i class="bi bi-x-circle"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tabela de FIMs -->
<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-fim table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID Interno</th>
                        <th>Nº Série Motor</th>
                        <th>Modelo</th>
                        <th>Natureza</th>
                        <th>Nº Conserto</th>
                        <th>Status</th>
                        <th>Operador</th>
                        <th>Cliente</th>
                        <th>Data Início</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Nenhuma FIM encontrada com os filtros atuais.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registros as $r): ?>
                            <tr onclick="window.location='formulario.php?id=<?= $r['id'] ?>'" style="cursor:pointer;">
                                <td><strong><?= sanitizar($r['id_interno']) ?></strong></td>
                                <td><?= sanitizar($r['numero_serie_motor']) ?: '<span class="text-muted">-</span>' ?></td>
                                <td><?= sanitizar($r['modelo_equipamento']) ?: '-' ?></td>
                                <td><?= badgeNatureza($r['natureza']) ?></td>
                                <td><?= sanitizar($r['numero_conserto']) ?: '-' ?></td>
                                <td><?= badgeStatus($r['status']) ?></td>
                                <td><?= sanitizar($r['operador_nome']) ?></td>
                                <td><?= sanitizar($r['nome_cliente']) ?: '<span class="text-muted">Não informado</span>' ?></td>
                                <td><?= formatarData($r['data_inicio']) ?></td>
                                <td>
                                    <a href="formulario.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary" 
                                       onclick="event.stopPropagation();">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php footerHTML(); ?>
