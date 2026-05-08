<?php
/**
 * FIM Digital - Formulário Principal (Ficha de Inspeção e Medição)
 * Permite preenchimento de todas as seções (Amarelo, Azul, Verde)
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';
verificarLogin();

$pdo = getConnection();
$usuario = usuarioLogado();
$registro_id = (int)($_GET['id'] ?? 0);

if (!$registro_id) { header('Location: index.php'); exit; }

// Buscar Registro FIM e Equipamento
$stmt = $pdo->prepare("
    SELECT r.*, e.numero_serie_motor, e.id_interno, e.tipo_identificacao, e.modelo_equipamento,
           u1.nome as operador_inicio_nome, u2.nome as operador_final_nome
    FROM registros_fim r
    JOIN equipamentos e ON r.equipamento_id = e.id
    LEFT JOIN usuarios u1 ON r.operador_inicio_id = u1.id
    LEFT JOIN usuarios u2 ON r.operador_final_id = u2.id
    WHERE r.id = ?
");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();

if (!$reg) { header('Location: index.php'); exit; }

// Buscar Dados de Bancada
$stmtB = $pdo->prepare("SELECT * FROM dados_bancada WHERE registro_id = ?");
$stmtB->execute([$registro_id]);
$bancada = $stmtB->fetch() ?: [];

// Formatação forçada de casas decimais conforme solicitado
$campos1Casa = ['pressao_negativa_mmhg','pressao_positiva_mmh2o','temperatura_c','vazao_m3min','ruido_db',
    'folga_radial_mm','folga_axial_mm','corrente_normal_fase_r','corrente_normal_fase_s','corrente_normal_fase_t',
    'corrente_carga_fase_r','corrente_carga_fase_s','corrente_carga_fase_t','isolacao_fase_r','isolacao_fase_s','isolacao_fase_t',
    'potencia_cv','corrente_nominal_220','corrente_nominal_380','corrente_nominal_440',
    'vibracao_x1','vibracao_y1','vibracao_z1','vibracao_x2','vibracao_y2','vibracao_z2',
    'vibracao_x3','vibracao_y3','vibracao_z3','pressao_utilizacao_mmhg','rotor_diametro','rotor_espessura'];

foreach ($campos1Casa as $c) {
    if (isset($bancada[$c]) && is_numeric($bancada[$c])) {
        // Usa '.' para inputs type="number"
        $bancada[$c] = number_format((float)$bancada[$c], 1, '.', '');
    }
}

if (isset($bancada['fator_servico']) && is_numeric($bancada['fator_servico'])) {
    $bancada['fator_servico'] = number_format((float)$bancada['fator_servico'], 2, '.', '');
}

// Buscar Dados de Cliente
$stmtC = $pdo->prepare("SELECT * FROM dados_cliente WHERE registro_id = ?");
$stmtC->execute([$registro_id]);
$cliente = $stmtC->fetch() ?: [];

$finalizado = ($reg['status'] === 'FINALIZADO');
$somosLeitura = $finalizado && !isGestor();

headerHTML('FIM: ' . $reg['id_interno'], 'formulario');
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="mb-0">
            <i class="bi bi-file-earmark-text"></i> FIM: <?= sanitizar($reg['id_interno']) ?>
        </h3>
        <div class="text-muted mt-1">
            <?= badgeNatureza($reg['natureza']) ?> | Status: <?= badgeStatus($reg['status']) ?>
        </div>
    </div>
    <div class="col-md-6 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2">
        <button type="button" class="btn btn-outline-danger btn-industrial" onclick="console.log('Clique: Limpar Tudo'); limparFormulario()">
            <i class="bi bi-trash"></i> Limpar Tudo
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-industrial">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <?php if ($finalizado): ?>
            <a href="reports/visualizar_fim.php?id=<?= $reg['id'] ?>" target="_blank" class="btn btn-info btn-industrial">
                <i class="bi bi-printer"></i> Imprimir / PDF
            </a>
        <?php endif; ?>
        <?php if ($reg['status'] !== 'EM_BANCADA'): ?>
            <button type="button" class="btn btn-warning btn-industrial text-dark" onclick="console.log('Clique: Voltar Status'); voltarStatus(<?= (int)$reg['id'] ?>)">
                <i class="bi bi-arrow-left-circle"></i> Voltar Status
            </button>
        <?php endif; ?>

        <?php if (!$finalizado): ?>
            <button type="button" class="btn btn-success btn-industrial" onclick="console.log('Clique: Avançar Status'); avancarStatus(<?= (int)$reg['id'] ?>)">
                <i class="bi bi-arrow-right-circle"></i> Avançar Status
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Barra de Progresso -->
<div class="card mb-4">
    <div class="card-body">
        <div class="position-relative m-4">
            <div class="progress" style="height: 4px;">
                <?php 
                    $pct = ['EM_BANCADA'=>25, 'EM_MONTAGEM'=>50, 'AGUARDANDO_CLIENTE'=>75, 'FINALIZADO'=>100];
                    $progresso = $pct[$reg['status']] ?? 0;
                ?>
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progresso ?>%"></div>
            </div>
            <div class="d-flex justify-content-between position-absolute w-100" style="top: -10px;">
                <span class="badge rounded-pill bg-<?= $progresso >= 25 ? 'success' : 'secondary' ?>">Bancada</span>
                <span class="badge rounded-pill bg-<?= $progresso >= 50 ? 'success' : 'secondary' ?>">Montagem</span>
                <span class="badge rounded-pill bg-<?= $progresso >= 75 ? 'success' : 'secondary' ?>">Cliente</span>
                <span class="badge rounded-pill bg-<?= $progresso >= 100 ? 'success' : 'secondary' ?>">Finalizado</span>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     DADOS DO CLIENTE (SEÇÃO VERDE)
     ========================================== -->
<div class="card card-industrial mb-4 border-0 shadow-sm">
    <div class="card-header bg-success text-white py-2">
        <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i> IDENTIFICAÇÃO DO EQUIPAMENTO / CLIENTE</h5>
    </div>
    <div class="card-body">
        <?php if (in_array($reg['status'], ['AGUARDANDO_CLIENTE', 'FINALIZADO'])): ?>
            <form id="formCliente" onsubmit="event.preventDefault(); salvarCliente();">
                <input type="hidden" name="registro_id" value="<?= $reg['id'] ?>">
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label obrigatorio fs-7">Cliente</label>
                        <input type="text" class="form-control form-control-sm" name="nome_cliente" value="<?= sanitizar($cliente['nome_cliente'] ?? '') ?>" required <?= $somosLeitura ? 'readonly' : '' ?>>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-7">Natureza</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= $reg['natureza'] ?>" readonly>
                    </div>
                    <?php if($reg['natureza'] === 'CONSERTO'): ?>
                        <div class="col-md-2">
                            <label class="form-label fs-7">Nº Conserto</label>
                            <input type="text" class="form-control form-control-sm bg-light" value="<?= sanitizar($reg['numero_conserto']) ?>" readonly>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label fs-7">Nº Série</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= sanitizar($reg['numero_serie_motor'] ?: 'Sem Número') ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-7">Nº Nota Fiscal</label>
                        <input type="text" class="form-control form-control-sm" name="numero_nota_fiscal" value="<?= sanitizar($cliente['numero_nota_fiscal'] ?? '') ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-7">Modelo</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= sanitizar($reg['modelo_equipamento']) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-7">Data Registro</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= formatarData($reg['data_inicio']) ?>" readonly>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                        <?php if (!$somosLeitura): ?>
                            <button type="submit" class="btn btn-success btn-sm px-4" data-salvando>
                                <i class="bi bi-save"></i> Salvar Identificação
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-lock-fill me-2"></i> Os dados do cliente só poderão ser preenchidos quando a FIM avançar para o status <strong>AGUARDANDO CLIENTE</strong>.
                <br><br>
                <strong>Dados Fixos:</strong>
                <div class="row mt-2 g-2">
                    <div class="col-md-3"><strong>Natureza:</strong> <?= $reg['natureza'] ?> <?= $reg['natureza'] === 'CONSERTO' ? '(Nº '.$reg['numero_conserto'].')' : '' ?></div>
                    <div class="col-md-3"><strong>Nº Série:</strong> <?= sanitizar($reg['numero_serie_motor'] ?: 'Sem Número') ?></div>
                    <div class="col-md-3"><strong>Modelo:</strong> <?= sanitizar($reg['modelo_equipamento']) ?></div>
                    <div class="col-md-3"><strong>Data Abertura:</strong> <?= formatarData($reg['data_inicio']) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==========================================
     DADOS DA BANCADA (SEÇÃO AMARELA/AZUL)
     ========================================== -->
<form id="formBancada" onsubmit="event.preventDefault(); salvarBancada();">
    <input type="hidden" name="registro_id" value="<?= $reg['id'] ?>">

    <!-- INSPEÇÃO E MEDIÇÃO -->
    <div class="card card-industrial mb-4">
        <div class="card-header titulo-bancada d-flex justify-content-between align-items-center py-2">
            <div class="flex-grow-1" data-bs-toggle="collapse" data-bs-target="#collapseMedicao" style="cursor:pointer">
                <h5 class="mb-0"><i class="bi bi-tools me-2"></i> INSPEÇÃO E MEDIÇÃO: NA GERAÇÃO</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if (!$somosLeitura): ?>
                    <button type="button" class="btn btn-sm btn-outline-light px-3 py-0 fs-8" onclick="limparSecao('collapseMedicao')">
                        <i class="bi bi-trash"></i> Limpar Seção
                    </button>
                <?php endif; ?>
                <i class="bi bi-chevron-down" data-bs-toggle="collapse" data-bs-target="#collapseMedicao" style="cursor:pointer"></i>
            </div>
        </div>
        <div id="collapseMedicao" class="collapse show">
            <div class="card-body">
                
                <div class="row g-3 mb-4">
                    <!-- Coluna: Fabricação (Movida para o início) -->
                    <div class="col-md-6 col-xl-4 col-xxl-3">
                        <div class="p-2 border rounded h-100 bg-light shadow-sm">
                            <label class="form-label text-primary fs-7 mb-1">Fabricação</label>
                            <select class="form-select form-select-sm" name="tipo_fabricacao" id="tipo_fabricacao" <?= $somosLeitura ? 'disabled' : '' ?>>
                                <option value="">Selecione...</option>
                                <option value="AS" <?= ($bancada['tipo_fabricacao']??'') === 'AS' ? 'selected' : '' ?>>ASPÓ (AS)</option>
                                <option value="MO" <?= ($bancada['tipo_fabricacao']??'') === 'MO' ? 'selected' : '' ?>>MOROVÁCUO (MO)</option>
                                <option value="MA" <?= ($bancada['tipo_fabricacao']??'') === 'MA' ? 'selected' : '' ?>>MACKVEN (MA)</option>
                                <option value="MI" <?= ($bancada['tipo_fabricacao']??'') === 'MI' ? 'selected' : '' ?>>IBRAM (MI)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Coluna: Tensão de Teste (Novo Visual) -->
                    <div class="col-md-6 col-xl-4 col-xxl-3">
                        <div class="p-2 border rounded h-100 bg-white shadow-sm">
                            <label class="form-label text-primary fs-7 mb-1">Tensão de Teste</label>
                            <div class="d-flex gap-3 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tensao_teste_unica" id="tt220" value="220" <?= !empty($bancada['tensao_teste_220']) ? 'checked' : '' ?> <?= $somosLeitura ? 'disabled' : '' ?>>
                                    <label class="form-check-label fs-7" for="tt220">220V</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tensao_teste_unica" id="tt380" value="380" <?= !empty($bancada['tensao_teste_380']) ? 'checked' : '' ?> <?= $somosLeitura ? 'disabled' : '' ?>>
                                    <label class="form-check-label fs-7" for="tt380">380V</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tensao_teste_unica" id="tt440" value="440" <?= !empty($bancada['tensao_teste_440']) ? 'checked' : '' ?> <?= $somosLeitura ? 'disabled' : '' ?>>
                                    <label class="form-check-label fs-7" for="tt440">440V</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3 mb-4">
                    <!-- Coluna 1: Pressão -->
                    <div class="col-auto">
                        <div class="p-2 border rounded h-100 bg-light shadow-sm" style="width: 520px;">
                            <h6 class="text-primary mb-2 fs-7"><span class="obrigatorio"></span> Pressões</h6>
                            <div class="mb-2">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 140px;">Neg.</span>
                                    <input type="number" step="0.1" class="form-control" name="pressao_negativa_mmhg" value="<?= $bancada['pressao_negativa_mmhg'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">mmHg</span>
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 140px;">Pos.</span>
                                    <input type="number" step="0.1" class="form-control" name="pressao_positiva_mmh2o" value="<?= $bancada['pressao_positiva_mmh2o'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">mmH2O</span>
                                </div>
                            </div>
                            <div class="mt-2 pt-1 border-top fs-8 text-muted">
                                <strong>Instrução:</strong> Utilizar Manômetro.
                            </div>
                        </div>
                    </div>

                    <!-- Coluna 2: Folga do Rotor -->
                    <div class="col-auto">
                        <div class="p-2 border rounded h-100 bg-light shadow-sm" style="width: 520px;">
                            <h6 class="text-primary mb-2 fs-7">Folga Rotor</h6>
                            <div class="mb-2">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 120px;">Rad.</span>
                                    <input type="number" step="0.1" class="form-control" name="folga_radial_mm" value="<?= $bancada['folga_radial_mm'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 120px;">Axi.</span>
                                    <input type="number" step="0.1" class="form-control" name="folga_axial_mm" value="<?= $bancada['folga_axial_mm'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                            <div class="mt-2 pt-1 border-top fs-8 text-muted">
                                <strong>Instrução:</strong> Utilizar Calibre de Folga.
                            </div>
                        </div>
                    </div>

                    <!-- Coluna 3: Dimensões Rotor (Ativa apenas para AS ou MO) -->
                    <div class="col-auto" id="container_dimensoes" style="transition: opacity 0.3s ease;">
                        <div class="p-2 border rounded h-100 bg-light shadow-sm" style="width: 520px;">
                            <h6 class="text-primary mb-2 fs-7">Dimensões Rotor (AS - MO)</h6>
                            <div class="mb-2">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 100px;">Ø</span>
                                    <input type="number" step="0.1" class="form-control" name="rotor_diametro" value="<?= $bancada['rotor_diametro'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 100px;">Esp.</span>
                                    <input type="number" step="0.1" class="form-control" name="rotor_espessura" value="<?= $bancada['rotor_espessura'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                            <div class="mt-2 pt-1 border-top fs-8 text-muted">
                                <strong>Instrução:</strong> Utilizar Paquímetro.
                            </div>
                        </div>
                    </div>

                    <!-- Coluna 4: Outras Medidas -->
                    <div class="col-auto">
                        <div class="p-2 border rounded h-100 bg-light shadow-sm" style="width: 520px;">
                            <h6 class="text-primary mb-2 fs-7">Outras Medidas</h6>
                            <div class="mb-2">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 150px;">Temp.</span>
                                    <input type="number" step="0.1" class="form-control" name="temperatura_c" value="<?= $bancada['temperatura_c'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">°C</span>
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 150px;">Vazão</span>
                                    <input type="number" step="0.1" class="form-control" name="vazao_m3min" value="<?= $bancada['vazao_m3min'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">m³/min</span>
                                </div>
                            </div>
                            <div class="mt-2 pt-1 border-top fs-8 text-muted">
                                <strong>Instrução:</strong> Utilizar Termometro e Rotâmetro.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NOVA SEÇÃO: Dimensões Detalhadas (MA - MI) -->
                <div id="container_dimensoes_ma_mi" class="d-none mb-4 p-4 border rounded bg-white shadow-sm">
                    <h6 class="text-primary mb-4 fw-bold border-bottom pb-2"><i class="bi bi-rulers me-2"></i>DIMENSÕES DO ROTOR (MA - MI)</h6>
                    
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
                        <?php for($i=1; $i<=11; $i++): ?>
                            <div class="col">
                                <div class="input-group">
                                    <span class="input-group-text fw-bold bg-light" style="width: 110px;">N° <?= $i ?></span>
                                    <?php if($i <= 6): ?>
                                        <span class="input-group-text bg-white" title="Diâmetro" style="width: 60px;">Ø</span>
                                    <?php endif; ?>
                                    <input type="number" step="0.001" class="form-control" name="medida_rotor_<?= $i ?>" value="<?= $bancada['medida_rotor_'.$i] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <div class="row align-items-center">
                            <div class="col-lg-6 mb-3 mb-lg-0">
                                <div class="p-3 bg-light rounded fs-7 text-muted border-start border-primary border-4">
                                    <i class="bi bi-info-circle-fill me-1 text-primary"></i> 
                                    <strong>Instrução:</strong> Utilize o paquímetro e medidor de profundidade para medir os pontos indicados na imagem ao lado.
                                </div>
                            </div>
                            <div class="col-lg-6 text-center">
                                <h6 class="text-primary mb-2 fw-bold small"><i class="bi bi-image me-1"></i>GUIA DE MEDIÇÃO (Clique para ampliar)</h6>
                                <img src="assets/image/medidasRotor.PNG" 
                                     class="img-fluid rounded border shadow-sm img-zoomable" 
                                     alt="Medidas Rotor" 
                                     style="max-height: 350px; cursor: zoom-in;"
                                     onclick="ampliarImagem(this.src)">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal para Ampliar Imagem -->
                <div class="modal fade" id="modalZoom" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content bg-transparent border-0">
                            <div class="modal-body p-0 text-center">
                                <img src="" id="imgZoom" class="img-fluid rounded shadow-lg border border-white border-4" alt="Zoom">
                                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- CORRENTES E ISOLAÇÃO -->
                <div class="row g-3">
                    <!-- Corrente Normal -->
                    <div class="col-md-auto">
                        <div class="p-3 border rounded h-100 bg-light shadow-sm" style="width: 340px;">
                            <h6 class="obrigatorio mb-3 fs-7 text-center">Corrente Normal (A)</h6>
                            <div class="grid-vertical">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 80px;">R</span>
                                    <input type="number" step="0.1" class="form-control" id="corrente_normal_fase_r" name="corrente_normal_fase_r" value="<?= $bancada['corrente_normal_fase_r'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                </div>
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 80px;">S</span>
                                    <input type="number" step="0.1" class="form-control" id="corrente_normal_fase_s" name="corrente_normal_fase_s" value="<?= $bancada['corrente_normal_fase_s'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                </div>
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 80px;">T</span>
                                    <input type="number" step="0.1" class="form-control" id="corrente_normal_fase_t" name="corrente_normal_fase_t" value="<?= $bancada['corrente_normal_fase_t'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="mt-2 text-center">
                                <small class="text-muted d-block mb-1">Desvio:</small>
                                <div id="desvioNormal" class="fw-bold">--</div>
                                <input type="hidden" name="desvio_normal" id="desvio_normal" value="<?= $bancada['desvio_normal'] ?? '' ?>">
                            </div>
                            <div class="mt-2 pt-1 border-top fs-8 text-muted">
                                <strong>Instrução:</strong> Utilizar Alicate Amperímetro.
                            </div>
                        </div>
                    </div>

                    <!-- Corrente Carga Máxima -->
                    <div class="col-md-auto">
                        <div class="p-3 border rounded h-100 bg-light shadow-sm" style="width: 340px;">
                            <h6 class="obrigatorio mb-3 fs-7 text-center">Carga Máxima (A)</h6>
                            <div class="grid-vertical">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 80px;">R</span>
                                    <input type="number" step="0.1" class="form-control" id="corrente_carga_fase_r" name="corrente_carga_fase_r" value="<?= $bancada['corrente_carga_fase_r'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                </div>
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 80px;">S</span>
                                    <input type="number" step="0.1" class="form-control" id="corrente_carga_fase_s" name="corrente_carga_fase_s" value="<?= $bancada['corrente_carga_fase_s'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                </div>
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 80px;">T</span>
                                    <input type="number" step="0.1" class="form-control" id="corrente_carga_fase_t" name="corrente_carga_fase_t" value="<?= $bancada['corrente_carga_fase_t'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                </div>
                            </div>
                            <div class="mt-2 text-center">
                                <small class="text-muted d-block mb-1">Desvio:</small>
                                <div id="desvioCarga" class="fw-bold">--</div>
                                <input type="hidden" name="desvio_carga" id="desvio_carga" value="<?= $bancada['desvio_carga'] ?? '' ?>">
                            </div>
                            <div class="mt-2 pt-1 border-top fs-8 text-muted">
                                <strong>Instrução:</strong> Utilizar Alicate Amperímetro.
                            </div>
                        </div>
                    </div>

                    <!-- Isolação -->
                    <div class="col-md-auto">
                        <div class="p-3 border rounded h-100 bg-light shadow-sm" style="width: 340px;">
                            <h6 class="obrigatorio mb-3 fs-7 text-center">Isolação (Massa)</h6>
                            <div class="grid-vertical">
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 80px;">R</span>
                                    <input type="number" step="0.1" class="form-control input-isolacao" name="isolacao_fase_r" value="<?= $bancada['isolacao_fase_r'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">MΩ</span>
                                </div>
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 80px;">S</span>
                                    <input type="number" step="0.1" class="form-control input-isolacao" name="isolacao_fase_s" value="<?= $bancada['isolacao_fase_s'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">MΩ</span>
                                </div>
                                <div class="input-group input-group-compact">
                                    <span class="input-group-text fw-bold" style="width: 80px;">T</span>
                                    <input type="number" step="0.1" class="form-control input-isolacao" name="isolacao_fase_t" value="<?= $bancada['isolacao_fase_t'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                    <span class="input-group-text">MΩ</span>
                                </div>
                            </div>
                            <div id="alertaIsolacao" class="d-none alert alert-danger p-2 mt-2 mb-0 fs-7">
                                <i class="bi bi-exclamation-triangle-fill"></i> Isolação < 100MΩ!
                            </div>
                            <div class="mt-2 pt-1 border-top fs-8 text-muted">
                                <strong>Instrução:</strong> Utilizar Megômetro.
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$somosLeitura): ?>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary btn-industrial" data-salvando>
                            <i class="bi bi-floppy"></i> Salvar Bancada
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- DADOS DO MOTOR -->
        <div class="card-header titulo-bancada d-flex justify-content-between align-items-center py-2">
            <div class="flex-grow-1" data-bs-toggle="collapse" data-bs-target="#collapseMotor" style="cursor:pointer">
                <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i> DADOS DO MOTOR</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if (!$somosLeitura): ?>
                    <button type="button" class="btn btn-sm btn-outline-light px-3 py-0 fs-8" onclick="limparSecao('collapseMotor')">
                        <i class="bi bi-trash"></i> Limpar Seção
                    </button>
                <?php endif; ?>
                <i class="bi bi-chevron-down" data-bs-toggle="collapse" data-bs-target="#collapseMotor" style="cursor:pointer"></i>
            </div>
        </div>
        <div id="collapseMotor" class="collapse show">
            <div class="card-body">
                
                <!-- Linha 1: DESCRIÇÃO DO MOTOR - Nº SÉRIE - FABRICAÇÃO -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <label class="form-label obrigatorio fs-7">Descrição do Motor</label>
                        <input type="text" class="form-control" name="descricao_motor" value="<?= sanitizar($bancada['descricao_motor'] ?? '') ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label obrigatorio fs-7">Nº Série</label>
                        <input type="text" class="form-control" name="numero_serie_motor" value="<?= sanitizar($bancada['numero_serie_motor'] ?? $reg['numero_serie_motor'] ?? '') ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label fs-7">Fabricação</label>
                        <input type="date" class="form-control" name="data_fabricacao_motor" value="<?= $bancada['data_fabricacao_motor'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                    </div>
                </div>

                <!-- Linha 2: Fabricante - Potência - Fator de Serviço -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-4">
                        <label class="form-label fs-7">Fabricante</label>
                        <select class="form-select" name="fabricante_motor" <?= $somosLeitura ? 'disabled' : '' ?>>
                            <option value="">Selecione...</option>
                            <option value="WEG" <?= ($bancada['fabricante_motor'] ?? '') == 'WEG' ? 'selected' : '' ?>>WEG</option>
                            <option value="EBERLE" <?= ($bancada['fabricante_motor'] ?? '') == 'EBERLE' ? 'selected' : '' ?>>EBERLE</option>
                            <option value="MERCOSUL" <?= ($bancada['fabricante_motor'] ?? '') == 'MERCOSUL' ? 'selected' : '' ?>>MERCOSUL</option>
                            <option value="SIEMENS" <?= ($bancada['fabricante_motor'] ?? '') == 'SIEMENS' ? 'selected' : '' ?>>SIEMENS</option>
                            <option value="ABB" <?= ($bancada['fabricante_motor'] ?? '') == 'ABB' ? 'selected' : '' ?>>ABB</option>
                        </select>
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label obrigatorio fs-7">Potência</label>
                        <div class="input-group">
                            <input type="number" step="0.1" class="form-control" name="potencia_cv" id="potencia_cv" value="<?= $bancada['potencia_cv'] ?? '' ?>" placeholder="CV" <?= $somosLeitura ? 'readonly' : '' ?>>
                            <span class="input-group-text">CV</span>
                            <span class="input-group-text bg-light text-muted" id="potencia_kw_show" style="min-width: 150px;"><?= ($bancada['potencia_kw']??'') ? number_format((float)$bancada['potencia_kw'], 1, ',', '.') . ' kW' : '...' ?></span>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label fs-7">F.S. (Fator de Serviço)</label>
                        <input type="number" step="0.01" class="form-control" name="fator_servico" value="<?= sanitizar($bancada['fator_servico'] ?? '') ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                    </div>
                </div>

                <!-- Linha 3: Tensão (Opcional) - Frequência -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="p-3 border rounded bg-white h-100">
                            <label class="form-label text-muted mb-2 fs-7">Tensão do Motor (opcional)</label>
                            <div class="d-flex gap-4 flex-wrap">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="tensao_motor_unica" id="tm220" value="220" <?= !empty($bancada['tensao_motor_220']) ? 'checked' : '' ?> <?= $somosLeitura ? 'disabled' : '' ?>>
                                    <label class="form-check-label fs-7" for="tm220">220V</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="tensao_motor_unica" id="tm380" value="380" <?= !empty($bancada['tensao_motor_380']) ? 'checked' : '' ?> <?= $somosLeitura ? 'disabled' : '' ?>>
                                    <label class="form-check-label fs-7" for="tm380">380V</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="tensao_motor_unica" id="tm440" value="440" <?= !empty($bancada['tensao_motor_440']) ? 'checked' : '' ?> <?= $somosLeitura ? 'disabled' : '' ?>>
                                    <label class="form-check-label fs-7" for="tm440">440V</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="p-3 border rounded bg-white h-100">
                            <label class="form-label d-block mb-2 fs-7">Frequência</label>
                            <div class="d-flex gap-4">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="frequencia_unica" id="f50" value="50" <?= !empty($bancada['frequencia_50hz']) ? 'checked' : '' ?> <?= $somosLeitura ? 'disabled' : '' ?>>
                                    <label class="form-check-label fs-7" for="f50">50Hz</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="frequencia_unica" id="f60" value="60" <?= !empty($bancada['frequencia_60hz']) ? 'checked' : '' ?> <?= $somosLeitura ? 'disabled' : '' ?>>
                                    <label class="form-check-label fs-7" for="f60">60Hz</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Linha 4: Corrente Nominal (A) -->
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="p-3 border rounded bg-white shadow-sm">
                            <h6 class="obrigatorio fs-7 mb-3">CORRENTE NOMINAL (A)</h6>
                            <div class="d-flex gap-4 flex-wrap">
                                <div class="input-group" style="max-width: 450px;">
                                    <span class="input-group-text fw-bold" style="width: 120px;">220V</span>
                                    <input type="number" step="0.1" class="form-control" name="corrente_nominal_220" value="<?= $bancada['corrente_nominal_220'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                </div>
                                <div class="input-group" style="max-width: 450px;">
                                    <span class="input-group-text fw-bold" style="width: 120px;">380V</span>
                                    <input type="number" step="0.1" class="form-control" name="corrente_nominal_380" value="<?= $bancada['corrente_nominal_380'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                </div>
                                <div class="input-group" style="max-width: 450px;">
                                    <span class="input-group-text fw-bold" style="width: 120px;">440V</span>
                                    <input type="number" step="0.1" class="form-control" name="corrente_nominal_440" value="<?= $bancada['corrente_nominal_440'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Linha 5: Rolamento Frontal - Rolamento Traseiro - Lubrificação -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-4">
                        <label class="form-label fs-7">Rolamento Frontal</label>
                        <input type="text" class="form-control" name="rolamento_frontal" value="<?= sanitizar($bancada['rolamento_frontal'] ?? '') ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fs-7">Rolamento Traseiro</label>
                        <input type="text" class="form-control" name="rolamento_traseiro" value="<?= sanitizar($bancada['rolamento_traseiro'] ?? '') ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fs-7">Lubrificação</label>
                        <input type="text" class="form-control" name="lubrificacao_rolamento" value="<?= sanitizar($bancada['lubrificacao_rolamento'] ?? '') ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                    </div>
                </div>

                <!-- OBSERVAÇÕES DO MOTOR (Movido para cá) -->
                <div class="mb-3">
                    <label class="form-label fs-7 fw-bold text-primary"><i class="bi bi-chat-text me-1"></i> OBSERVAÇÕES GERAIS / MOTOR</label>
                    <textarea class="form-control" name="observacoes" rows="2" placeholder="Observações sobre o motor ou gerais..." <?= $somosLeitura ? 'readonly' : '' ?>><?= sanitizar($bancada['observacoes'] ?? '') ?></textarea>
                </div>

                <?php if (!$somosLeitura): ?>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-industrial px-4" data-salvando>
                            <i class="bi bi-floppy"></i> Salvar Motor
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- VIBRAÇÃO ISO 10816-7 -->
        <div class="card-header titulo-bancada d-flex justify-content-between align-items-center py-2">
            <div class="flex-grow-1" data-bs-toggle="collapse" data-bs-target="#collapseVibracao" style="cursor:pointer">
                <h5 class="mb-0"><i class="bi bi-activity me-2"></i> VIBRAÇÃO - VELOCIDADE (mm/s) - ISO 10816-7</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if (!$somosLeitura): ?>
                    <button type="button" class="btn btn-sm btn-outline-light px-3 py-0 fs-8" onclick="limparSecao('collapseVibracao')">
                        <i class="bi bi-trash"></i> Limpar Seção
                    </button>
                <?php endif; ?>
                <i class="bi bi-chevron-down" data-bs-toggle="collapse" data-bs-target="#collapseVibracao" style="cursor:pointer"></i>
            </div>
        </div>
        <div id="collapseVibracao" class="collapse show">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-xl-6">
                    <!-- Tabela de Entrada -->
                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 120px;">Eixo</th>
                                    <th style="width: 160px;">Ponto 1</th>
                                    <th style="width: 160px;">Ponto 2</th>
                                    <th style="width: 160px;">Ponto 3</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold bg-light">X</td>
                                    <td><input type="number" step="0.1" class="form-control input-vibracao" name="vibracao_x1" id="vibracao_x1" value="<?= $bancada['vibracao_x1'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>></td>
                                    <td><input type="number" step="0.1" class="form-control input-vibracao" name="vibracao_x2" id="vibracao_x2" value="<?= $bancada['vibracao_x2'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>></td>
                                    <td><input type="number" step="0.1" class="form-control input-vibracao" name="vibracao_x3" id="vibracao_x3" value="<?= $bancada['vibracao_x3'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold bg-light">Y</td>
                                    <td><input type="number" step="0.1" class="form-control input-vibracao" name="vibracao_y1" id="vibracao_y1" value="<?= $bancada['vibracao_y1'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>></td>
                                    <td><input type="number" step="0.1" class="form-control input-vibracao" name="vibracao_y2" id="vibracao_y2" value="<?= $bancada['vibracao_y2'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>></td>
                                    <td><input type="number" step="0.1" class="form-control input-vibracao" name="vibracao_y3" id="vibracao_y3" value="<?= $bancada['vibracao_y3'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold bg-light">Z</td>
                                    <td><input type="number" step="0.1" class="form-control input-vibracao" name="vibracao_z1" id="vibracao_z1" value="<?= $bancada['vibracao_z1'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>></td>
                                    <td><input type="number" step="0.1" class="form-control input-vibracao" name="vibracao_z2" id="vibracao_z2" value="<?= $bancada['vibracao_z2'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>></td>
                                    <td><input type="number" step="0.1" class="form-control input-vibracao" name="vibracao_z3" id="vibracao_z3" value="<?= $bancada['vibracao_z3'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="h-100 d-flex flex-column gap-3">
                        <!-- Classificação Resultante -->
                        <div class="p-3 border rounded bg-white shadow-sm flex-grow-1 d-flex flex-column justify-content-center align-items-center">
                            <div id="classificacaoBox" class="classificacao-box py-3 w-100" style="font-size: 1.5rem; min-height: 80px;">
                                <span class="text-muted">Classificação Final</span>
                            </div>
                            <input type="hidden" name="classificacao_vibracao" id="classificacao_vibracao" value="<?= $bancada['classificacao_vibracao'] ?? '' ?>">
                        </div>

                        <!-- Referência visual compacta -->
                        <div class="p-3 border rounded bg-light d-flex align-items-center justify-content-around flex-wrap gap-2">
                            <div class="small fw-bold text-muted w-100 text-center mb-1">REFERÊNCIA ISO 10816-7 (mm/s):</div>
                            <div class="badge bg-success opacity-75 p-2">A (0,1-3,2)</div>
                            <div class="badge bg-primary opacity-75 p-2">B (3,2-4,2)</div>
                            <div class="badge bg-warning text-dark opacity-75 p-2">C (4,2-6,1)</div>
                            <div class="badge bg-danger opacity-75 p-2">D (>6,1)</div>
                        </div>

                    </div>
                </div>

                <div class="col-12 mt-3">
                    <!-- Guia Visual -->
                    <div class="text-start p-2 border rounded bg-white shadow-sm ms-0 mb-3" style="max-width: 600px;">
                        <h6 class="text-muted mb-2 small"><i class="bi bi-geo-alt-fill me-1"></i> Localização dos Pontos de Medição (Clique para ampliar)</h6>
                        <img src="assets/image/pontosMedicao.PNG" alt="Pontos de Medição" class="img-fluid rounded border shadow-sm" style="max-height: 300px; object-fit: contain; cursor: zoom-in;" onclick="ampliarImagem(this.src)">
                        <div class="mt-3 p-2 bg-light rounded fs-7 text-muted border-start border-primary border-4 text-start">
                            <i class="bi bi-info-circle-fill me-1 text-primary"></i> 
                            <strong>Instrução:</strong> Utilizar medidor de Vibração.
                        </div>
                    </div>

                    <!-- Observações -->
                    <div class="p-3 border rounded bg-white shadow-sm">
                        <label class="form-label fs-7 fw-bold text-primary mb-2"><i class="bi bi-chat-text me-1"></i> Observações da Vibração</label>
                        <textarea class="form-control" name="observacoes_vibracao" rows="3" placeholder="Observações específicas sobre a vibração..." <?= $somosLeitura ? 'readonly' : '' ?>><?= sanitizar($bancada['observacoes_vibracao'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            
            <?php if (!$somosLeitura): ?>
                <div class="text-end mt-2">
                    <button type="submit" class="btn btn-primary btn-industrial" data-salvando>
                        <i class="bi bi-floppy"></i> Salvar Vibração
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- NA UTILIZAÇÃO -->
        <div class="card-header titulo-bancada d-flex justify-content-between align-items-center py-2">
            <div class="flex-grow-1" data-bs-toggle="collapse" data-bs-target="#collapseUtilizacao" style="cursor:pointer">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i> NA UTILIZAÇÃO</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if (!$somosLeitura): ?>
                    <button type="button" class="btn btn-sm btn-outline-light px-3 py-0 fs-8" onclick="limparSecao('collapseUtilizacao')">
                        <i class="bi bi-trash"></i> Limpar Seção
                    </button>
                <?php endif; ?>
                <i class="bi bi-chevron-down" data-bs-toggle="collapse" data-bs-target="#collapseUtilizacao" style="cursor:pointer"></i>
            </div>
        </div>
        <div id="collapseUtilizacao" class="collapse show">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-auto">
                    <div class="input-group input-group-compact" style="width: 650px;">
                        <span class="input-group-text fw-bold" style="width: 300px;">Pressão Utilização</span>
                        <input type="number" step="0.1" class="form-control" name="pressao_utilizacao_mmhg" value="<?= $bancada['pressao_utilizacao_mmhg'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                        <span class="input-group-text">mmHg</span>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="input-group input-group-compact" style="width: 520px;">
                        <span class="input-group-text fw-bold" style="width: 260px;">Ruído (Opcional)</span>
                        <input type="number" step="0.1" class="form-control" name="ruido_db" value="<?= $bancada['ruido_db'] ?? '' ?>" <?= $somosLeitura ? 'readonly' : '' ?>>
                        <span class="input-group-text">dB</span>
                    </div>
                </div>
            </div>

            <?php if (!$somosLeitura): ?>
                <div class="d-flex justify-content-center mt-4">
                    <button type="submit" class="btn btn-primary btn-industrial btn-lg px-5" style="min-width: 400px;" data-salvando>
                        <i class="bi bi-floppy-fill me-2"></i> SALVAR TODOS OS DADOS DA BANCADA
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
// JS Específico do formulário
document.addEventListener('DOMContentLoaded', function() {
    // Conversão CV para kW visual
    const potCV = document.getElementById('potencia_cv');
    const potKW = document.getElementById('potencia_kw_show');
    if(potCV) {
        potCV.addEventListener('input', function() {
            const val = parseFloat(this.value);
            if(!isNaN(val)) {
                potKW.textContent = (val * 0.7355).toFixed(1).replace('.', ',') + ' kW';
            } else {
                potKW.textContent = '...';
            }
        });
    }

    // Controle de Dimensões
    const selectFab = document.getElementById('tipo_fabricacao');
    const containerDimASMO = document.getElementById('container_dimensoes');
    const containerDimMAMI = document.getElementById('container_dimensoes_ma_mi');

    function atualizarDimensoes() {
        if(!selectFab) return;
        const val = selectFab.value;
        
        // Lógica AS - MO
        const isASMO = (val === 'AS' || val === 'MO');
        if(containerDimASMO) {
            const inputsASMO = containerDimASMO.querySelectorAll('input');
            inputsASMO.forEach(i => {
                i.disabled = !isASMO;
                i.style.backgroundColor = !isASMO ? '#e9ecef' : '';
            });
            containerDimASMO.style.opacity = isASMO ? '1' : '0.5';
        }

        // Lógica MA - MI (Toggle Visibility)
        const isMAMI = (val === 'MA' || val === 'MI');
        if(containerDimMAMI) {
            if(isMAMI) {
                containerDimMAMI.classList.remove('d-none');
            } else {
                containerDimMAMI.classList.add('d-none');
                // Opcional: não limpar aqui para evitar perda de dados se o usuário mudar sem querer
            }
        }
    }
    if(selectFab) {
        selectFab.addEventListener('change', atualizarDimensoes);
        atualizarDimensoes(); // Inicial
    }

    // Alerta de isolação < 100MΩ
    const inputsIsolacao = document.querySelectorAll('.input-isolacao');
    const alertaIsol = document.getElementById('alertaIsolacao');
    function checarIsolacao() {
        let menor100 = false;
        inputsIsolacao.forEach(i => {
            const val = parseFloat(i.value);
            if(!isNaN(val) && val < 100) menor100 = true;
        });
        if(alertaIsol) {
            if(menor100) alertaIsol.classList.remove('d-none');
            else alertaIsol.classList.add('d-none');
        }
    }
    inputsIsolacao.forEach(i => i.addEventListener('input', checarIsolacao));
    checarIsolacao(); // Inicial
});

// Função global para ampliar imagem
function ampliarImagem(src) {
    const modal = new bootstrap.Modal(document.getElementById('modalZoom'));
    document.getElementById('imgZoom').src = src;
    modal.show();
}
</script>

<?php footerHTML(); ?>
