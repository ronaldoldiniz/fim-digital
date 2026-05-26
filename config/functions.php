<?php
/**
 * FIM Digital - Funções Utilitárias
 * Funções auxiliares reutilizáveis em todo o sistema
 */

require_once __DIR__ . '/db.php';

/**
 * Gera o ID interno sequencial no formato FIM-ANO-SEQUENCIAL
 * Ex: FIM-2026-000123
 */
function gerarIdInterno(): string {
    $pdo = getConnection();
    $ano = (int)date('Y');
    
    // Usar transação apenas se não houver uma ativa
    $transactionStarted = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $transactionStarted = true;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT ultimo_numero FROM sequencial_fim WHERE ano = ? FOR UPDATE");
        $stmt->execute([$ano]);
        $row = $stmt->fetch();
        
        if ($row) {
            $numero = $row['ultimo_numero'] + 1;
            $stmt2 = $pdo->prepare("UPDATE sequencial_fim SET ultimo_numero = ? WHERE ano = ?");
            $stmt2->execute([$numero, $ano]);
        } else {
            $numero = 1;
            $stmt2 = $pdo->prepare("INSERT INTO sequencial_fim (ano, ultimo_numero) VALUES (?, ?)");
            $stmt2->execute([$ano, $numero]);
        }
        
        if ($transactionStarted) {
            $pdo->commit();
        }
        return sprintf("FIM-%d-%06d", $ano, $numero);
    } catch (Exception $e) {
        if ($transactionStarted) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Sanitiza string para exibição segura
 */
function sanitizar(?string $valor): string {
    if ($valor === null) return '';
    return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
}

/**
 * Formata data para exibição (dd/mm/yyyy HH:mm)
 */
function formatarData(?string $data, bool $comHora = true): string {
    if (empty($data)) return '-';
    $formato = $comHora ? 'd/m/Y H:i' : 'd/m/Y';
    return date($formato, strtotime($data));
}

/**
 * Formata número decimal para exibição com vírgula
 */
function formatarNumero($valor, int $casas = 2): string {
    if ($valor === null || $valor === '') return '';
    return number_format((float)$valor, $casas, ',', '.');
}

/**
 * Retorna o label de status formatado com badge Bootstrap
 */
function badgeStatus(string $status): string {
    $badges = [
        'EM_BANCADA'         => '<span class="badge bg-warning text-dark fs-7"><i class="bi bi-wrench"></i> Em Bancada</span>',
        'EM_MONTAGEM'        => '<span class="badge bg-info text-dark fs-7"><i class="bi bi-gear"></i> Em Montagem</span>',
        'AGUARDANDO_CLIENTE' => '<span class="badge bg-success fs-7"><i class="bi bi-person"></i> Aguardando Cliente</span>',
        'FINALIZADO'         => '<span class="badge bg-secondary fs-7"><i class="bi bi-check-circle"></i> Finalizado</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-dark">' . sanitizar($status) . '</span>';
}

/**
 * Retorna o label da natureza formatado
 */
function badgeNatureza(string $natureza): string {
    if ($natureza === 'NOVO') {
        return '<span class="badge bg-primary fs-7"><i class="bi bi-star"></i> Novo</span>';
    }
    return '<span class="badge bg-orange fs-7"><i class="bi bi-tools"></i> Conserto</span>';
}

/**
 * Retorna o label do tipo de fabricação
 */
function labelFabricacao(?string $tipo): string {
    $labels = [
        'AS' => 'ASPÓ',
        'MO' => 'MOROVÁCUO',
        'MA' => 'MACKVEN',
        'MI' => 'IBRAM'
    ];
    return $labels[$tipo] ?? '-';
}

/**
 * Verifica se existe FIM aberta para um equipamento
 * Retorna o registro_id se existir, ou false
 */
function fimAbertaParaEquipamento(int $equipamento_id): int|false {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT id FROM registros_fim 
        WHERE equipamento_id = ? AND status != 'FINALIZADO' 
        LIMIT 1
    ");
    $stmt->execute([$equipamento_id]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : false;
}

/**
 * Busca equipamento pelo número de série do motor
 */
function buscarEquipamentoPorSerie(string $serie): ?array {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM equipamentos WHERE numero_serie_motor = ? LIMIT 1");
    $stmt->execute([$serie]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Registra log de edição pós-finalização
 */
function registrarLogEdicao(int $registro_id, string $campo, ?string $valor_antigo, ?string $valor_novo, int $usuario_id, ?string $motivo = null): void {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        INSERT INTO log_edicoes (registro_id, campo, valor_antigo, valor_novo, usuario_id, data, motivo)
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$registro_id, $campo, $valor_antigo, $valor_novo, $usuario_id, $motivo]);
}

/**
 * Calcula o desvio percentual entre 3 fases
 * Fórmula: ((MAX - MIN) / MEDIA) * 100
 * Normal até 5%
 */
function calcularDesvio(?float $r, ?float $s, ?float $t): ?float {
    if ($r === null || $s === null || $t === null) return null;
    if ($r == 0 && $s == 0 && $t == 0) return 0;
    
    $media = ($r + $s + $t) / 3;
    if ($media == 0) return null;
    
    $max = max($r, $s, $t);
    $min = min($r, $s, $t);
    
    return round((($max - $min) / $media) * 100, 2);
}

/**
 * Classifica vibração conforme ISO 10816-7
 * A: 0,1 - 3,2 | B: 3,2 - 4,2 | C: 4,2 - 6,1 | D: > 6,1
 */
function classificarVibracao(array $valores): string {
    $max = 0;
    foreach ($valores as $v) {
        if ($v !== null && $v > $max) {
            $max = (float)$v;
        }
    }
    
    if ($max <= 3.2) return 'A';
    if ($max <= 4.2) return 'B';
    if ($max <= 6.1) return 'C';
    return 'D';
}

/**
 * Valida campos obrigatórios da etapa de bancada
 * Retorna array com campos faltantes
 */
function validarCamposBancada(array $dados): array {
    $faltantes = [];
    $obrigatorios = [
        'corrente_normal_fase_t' => 'Corrente Normal - Fase T',
        'corrente_carga_fase_r' => 'Corrente Carga - Fase R',
        'corrente_carga_fase_s' => 'Corrente Carga - Fase S',
        'corrente_carga_fase_t' => 'Corrente Carga - Fase T',
        'isolacao_fase_r' => 'Isolação - Fase R',
        'isolacao_fase_s' => 'Isolação - Fase S',
        'isolacao_fase_t' => 'Isolação - Fase T',
        'descricao_motor' => 'Descrição do Motor',
        'numero_serie_motor' => 'Nº Série (Motor)',
        'potencia_cv' => 'Potência (CV/kW)',
    ];
    
    foreach ($obrigatorios as $campo => $label) {
        if (empty($dados[$campo]) && $dados[$campo] !== '0') {
            $faltantes[] = $label;
        }
    }
    
    return $faltantes;
}

/**
 * Retorna o header HTML padrão do sistema
 */
function headerHTML(string $titulo = 'FIM Digital', string $paginaAtiva = ''): void {
    $usuario = usuarioLogado();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="FIM Digital - Sistema de Ficha de Inspeção e Medição - Morotó Indústria">
    <title><?= sanitizar($titulo) ?> | FIM Digital</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- CSS customizado -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-navbar sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <i class="bi bi-clipboard2-check me-2 fs-4"></i>
                <div>
                    <span class="fw-bold">FIM Digital</span>
                    <small class="d-block text-light opacity-75" style="font-size: 0.65rem; line-height: 1;">Morotó Indústria</small>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-btn <?= $paginaAtiva === 'dashboard' ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-btn <?= $paginaAtiva === 'nova' ? 'active' : '' ?>" href="nova_fim.php">
                            <i class="bi bi-plus-circle"></i> Nova FIM
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-btn <?= $paginaAtiva === 'consulta' ? 'active' : '' ?>" href="consulta.php">
                            <i class="bi bi-search"></i> Consulta
                        </a>
                    </li>
                    <?php if (isGestor()): ?>
                    <li class="nav-item">
                        <a class="nav-link nav-btn <?= $paginaAtiva === 'usuarios' ? 'active' : '' ?>" href="usuarios.php">
                            <i class="bi bi-people"></i> Usuários
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-light me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= sanitizar($usuario['nome']) ?>
                        <small class="badge bg-light text-dark ms-1"><?= sanitizar($usuario['perfil']) ?></small>
                    </span>
                    <a href="?logout=1" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <main class="container-fluid py-3">
    <?php
}

/**
 * Envia email com template HTML
 */
function enviarEmail(string $para, string $assunto, string $corpoHtml): bool {
    $dominio = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $fromAddr = 'noreply@' . $dominio;
    $replyTo = 'noreply@' . $dominio;
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    $headers .= "From: FIM Digital <$fromAddr>\r\n";
    $headers .= "Reply-To: $replyTo\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";
    $params = "-f $fromAddr";
    return mail($para, '=?UTF-8?B?'.base64_encode($assunto).'?=', $corpoHtml, $headers, $params);
}

/**
 * Retorna o footer HTML padrão do sistema
 */
function footerHTML(): void {
    ?>
    </main>
    <!-- Toast container para notificações -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JS customizado -->
    <script src="assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
    <?php
}
