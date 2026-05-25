<?php
/**
 * FIM Digital - Visualização para Impressão / PDF
 * Layout otimizado para papel A4
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
verificarLogin();

$registro_id = (int)($_GET['id'] ?? 0);
if (!$registro_id) die('ID não informado.');

$pdo = getConnection();
$modoCliente = isset($_GET['cliente']);

// Buscar Registro e Equipamento
$stmt = $pdo->prepare("
    SELECT r.*, e.numero_serie_motor as e_serie, e.id_interno, e.modelo_equipamento, 
           u.nome as responsavel 
    FROM registros_fim r 
    JOIN equipamentos e ON r.equipamento_id = e.id 
    LEFT JOIN usuarios u ON r.operador_final_id = u.id 
    WHERE r.id = ?
");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();
if (!$reg) die('Registro não encontrado.');

// Buscar Dados de Bancada
$stmtB = $pdo->prepare("SELECT b.*, u.nome as operador_medicao FROM dados_bancada b LEFT JOIN usuarios u ON b.operador_id = u.id WHERE b.registro_id = ?");
$stmtB->execute([$registro_id]);
$b = $stmtB->fetch() ?: [];

// Buscar Dados de Cliente
$stmtC = $pdo->prepare("SELECT * FROM dados_cliente WHERE registro_id = ?");
$stmtC->execute([$registro_id]);
$c = $stmtC->fetch() ?: [];

// Formatar valores numéricos
function f1($val) {
    return (is_numeric($val) && $val !== null) ? number_format((float)$val, 1, ',', '.') : '---';
}
function f2($val) {
    return (is_numeric($val) && $val !== null) ? number_format((float)$val, 2, ',', '.') : '---';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FIM - <?= $reg['id_interno'] ?></title>
    <style>
        @page { size: A4 portrait; margin: 0.7cm; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 9pt; line-height: 1.2; color: #000; margin: 0; padding: 0; }
        
        .no-print { background: #f8f9fa; padding: 8px; text-align: center; border-bottom: 1px solid #ddd; margin-bottom: 8px; }
        .btn-print { padding: 6px 14px; background: #27ae60; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 9pt; }

        .container { width: 100%; max-width: 21cm; margin: 0 auto; background: #fff; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { border: 1px solid #333; padding: 3px 5px; vertical-align: middle; }
        
        .header-logo { text-align: center; font-weight: 800; font-size: 12pt; width: 25%; }
        .header-title { text-align: center; font-weight: 800; font-size: 11pt; background: #f2f2f2; }
        .header-info { text-align: center; font-size: 8pt; width: 20%; }

        .section-title { background: #eee; font-weight: bold; text-transform: uppercase; font-size: 8pt; }
        .bg-green { background-color: #e8f5e9; }
        .bg-yellow { background-color: #fffde7; }
        
        .label { font-size: 7pt; color: #555; display: block; margin-bottom: 1px; text-transform: uppercase; font-weight: bold; }
        .value { font-size: 9pt; font-weight: bold; }
        
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        
        @media print {
            .no-print { display: none; }
            body { background: #fff; }
            .container { width: 100%; max-width: none; margin: 0; }
            th, td { border: 1px solid #000; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()"><i class="bi bi-printer"></i> CLIQUE AQUI PARA IMPRIMIR OU SALVAR EM PDF</button>
    <p style="font-size: 0.9rem; margin-top: 5px; color: #666;">Dica: Na janela de impressão, selecione "Salvar como PDF".</p>
</div>

<div class="container">
    <!-- CABEÇALHO -->
    <table>
        <tr>
            <td class="header-logo">MOROTÓ</td>
            <td class="header-title">FOLHA DE INSPEÇÃO E MEDIÇÃO - F.I.M.</td>
            <td class="header-info">VER.: 09<br>DATA: <?= date('d/m/Y') ?></td>
        </tr>
    </table>

    <!-- IDENTIFICAÇÃO -->
    <table>
        <tr><td colspan="4" class="section-title bg-green">1. IDENTIFICAÇÃO DO EQUIPAMENTO / CLIENTE</td></tr>
        <tr>
            <td colspan="2" width="60%">
                <span class="label">Cliente</span>
                <div class="value"><?= sanitizar($c['nome_cliente'] ?? 'NÃO INFORMADO') ?></div>
            </td>
            <td width="20%">
                <span class="label">Nº Série</span>
                <div class="value"><?= sanitizar($reg['e_serie'] ?: '---') ?></div>
            </td>
            <td width="20%">
                <span class="label">Nº NF</span>
                <div class="value"><?= sanitizar($c['numero_nota_fiscal'] ?? '---') ?></div>
            </td>
        </tr>
        <tr>
            <td width="40%">
                <span class="label">Modelo do Equipamento</span>
                <div class="value"><?= sanitizar($reg['modelo_equipamento'] ?: '---') ?></div>
            </td>
            <td width="20%">
                <span class="label">Natureza</span>
                <div class="value"><?= $reg['natureza'] ?> <?= $reg['numero_conserto'] ? '('.$reg['numero_conserto'].')' : '' ?></div>
            </td>
            <td colspan="2">
                <span class="label">Data de Início da Inspeção</span>
                <div class="value"><?= date('d/m/Y', strtotime($reg['data_inicio'])) ?></div>
            </td>
        </tr>
    </table>

    <!-- INSPEÇÃO NA GERAÇÃO -->
    <table>
        <tr><td colspan="4" class="section-title bg-yellow">2. INSPEÇÃO E MEDIÇÃO: NA GERAÇÃO</td></tr>
        <tr>
            <td width="30%">
                <span class="label">Tensão de Teste</span>
                <div class="value">
                    [<?= $b['tensao_teste_220'] ? ' X ' : '&nbsp;&nbsp;&nbsp;' ?>] 220V &nbsp;
                    [<?= $b['tensao_teste_380'] ? ' X ' : '&nbsp;&nbsp;&nbsp;' ?>] 380V &nbsp;
                    [<?= $b['tensao_teste_440'] ? ' X ' : '&nbsp;&nbsp;&nbsp;' ?>] 440V
                </div>
            </td>
            <td width="25%">
                <span class="label">Pressão Negativa</span>
                <div class="value"><?= f1($b['pressao_negativa_mmhg']) ?> mmHg</div>
            </td>
            <td width="20%">
                <span class="label">Pressão Positiva</span>
                <div class="value"><?= f1($b['pressao_positiva_mmh2o']) ?> mmH2O</div>
            </td>
            <td width="25%">
                <span class="label">Temperatura</span>
                <div class="value"><?= f1($b['temperatura_c']) ?> °C</div>
            </td>
        </tr>
        <tr>
            <?php if (!$modoCliente): ?>
            <td>
                <span class="label">Vazão</span>
                <div class="value"><?= f1($b['vazao_m3min']) ?> m³/min</div>
            </td>
            <td colspan="<?= $modoCliente ? 4 : 3 ?>">
            <?php else: ?>
            <td colspan="4">
            <?php endif; ?>
                <span class="label">Folgas do Rotor</span>
                <div class="value">Radial: <?= f1($b['folga_radial_mm']) ?> mm | Axial: <?= f1($b['folga_axial_mm']) ?> mm</div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="text-center bg-green bold">CORRENTE OPERAÇÃO NORMAL (A)</td>
            <td colspan="2" class="text-center bg-green bold">CORRENTE CARGA MÁXIMA (A)</td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="text-center value">
                    R: <?= f1($b['corrente_normal_fase_r']) ?> | 
                    S: <?= f1($b['corrente_normal_fase_s']) ?> | 
                    T: <?= f1($b['corrente_normal_fase_t']) ?>
                </div>
                <?php if (!$modoCliente): ?><div class="text-center label">Desvio: <?= f2($b['desvio_normal']) ?>%</div><?php endif; ?>
            </td>
            <td colspan="2">
                <div class="text-center value">
                    R: <?= f1($b['corrente_carga_fase_r']) ?> | 
                    S: <?= f1($b['corrente_carga_fase_s']) ?> | 
                    T: <?= f1($b['corrente_carga_fase_t']) ?>
                </div>
                <?php if (!$modoCliente): ?><div class="text-center label">Desvio: <?= f2($b['desvio_carga']) ?>%</div><?php endif; ?>
            </td>
        </tr>
        <tr>
            <td colspan="4" class="text-center">
                <span class="label">Isolação (Massa)</span>
                <div class="value">
                    R: <?= f1($b['isolacao_fase_r']) ?> MΩ | 
                    S: <?= f1($b['isolacao_fase_s']) ?> MΩ | 
                    T: <?= f1($b['isolacao_fase_t']) ?> MΩ
                </div>
            </td>
        </tr>
    </table>

    <!-- DADOS DO MOTOR -->
    <table>
        <tr><td colspan="4" class="section-title bg-yellow">3. DADOS DO MOTOR</td></tr>
        <tr>
            <td colspan="2">
                <span class="label">Descrição / Fabricante</span>
                <div class="value"><?= sanitizar($b['descricao_motor'] ?? '---') ?> / <?= sanitizar($b['fabricante_motor'] ?? '---') ?></div>
            </td>
            <td>
                <span class="label">Nº Série Motor</span>
                <div class="value"><?= sanitizar($b['numero_serie_motor'] ?? '---') ?></div>
            </td>
            <td>
                <span class="label">Data Fab.</span>
                <div class="value"><?= $b['data_fabricacao_motor'] ? date('d/m/Y', strtotime($b['data_fabricacao_motor'])) : '---' ?></div>
            </td>
        </tr>
        <tr>
            <td width="30%">
                <span class="label">Tensão Aplicação</span>
                <div class="value">
                    [<?= $b['tensao_motor_220'] ? ' X ' : '&nbsp;&nbsp;&nbsp;' ?>] 220V &nbsp;
                    [<?= $b['tensao_motor_380'] ? ' X ' : '&nbsp;&nbsp;&nbsp;' ?>] 380V &nbsp;
                    [<?= $b['tensao_motor_440'] ? ' X ' : '&nbsp;&nbsp;&nbsp;' ?>] 440V
                </div>
            </td>
            <td width="20%">
                <span class="label">Frequência</span>
                <div class="value">
                    [<?= $b['frequencia_50hz'] ? ' X ' : '&nbsp;&nbsp;&nbsp;' ?>] 50Hz &nbsp;
                    [<?= $b['frequencia_60hz'] ? ' X ' : '&nbsp;&nbsp;&nbsp;' ?>] 60Hz
                </div>
            </td>
            <td width="20%">
                <span class="label">Potência</span>
                <div class="value"><?= f1($b['potencia_cv']) ?> CV / <?= f1($b['potencia_kw']) ?> kW</div>
            </td>
            <td width="30%">
                <span class="label">Fator de Serviço</span>
                <div class="value"><?= $b['fator_servico'] ?: '---' ?></div>
            </td>
        </tr>
        <tr>
            <td width="30%">
                <span class="label">Rol. Frontal</span>
                <div class="value"><?= sanitizar($b['rolamento_frontal'] ?? '---') ?></div>
            </td>
            <td width="20%">
                <span class="label">Rol. Traseiro</span>
                <div class="value"><?= sanitizar($b['rolamento_traseiro'] ?? '---') ?></div>
            </td>
            <td width="20%">
                <span class="label">Lubrificação</span>
                <div class="value"><?= sanitizar($b['lubrificacao_rolamento'] ?? '---') ?></div>
            </td>
            <td width="30%">
                <span class="label">Corrente Nominal</span>
                <div class="value">220V: <?= f1($b['corrente_nominal_220']) ?>A | 380V: <?= f1($b['corrente_nominal_380']) ?>A | 440V: <?= f1($b['corrente_nominal_440']) ?>A</div>
            </td>
        </tr>
    </table>

    <!-- VIBRAÇÃO -->
    <table>
        <tr><td colspan="4" class="section-title bg-yellow">4. VIBRAÇÃO (mm/s) - ISO 10816-7</td></tr>
        <tr class="text-center">
            <td width="25%"><span class="label">Ponto 1 (X | Y | Z)</span><div class="value"><?= f1($b['vibracao_x1']) ?> | <?= f1($b['vibracao_y1']) ?> | <?= f1($b['vibracao_z1']) ?></div></td>
            <td width="25%"><span class="label">Ponto 2 (X | Y | Z)</span><div class="value"><?= f1($b['vibracao_x2']) ?> | <?= f1($b['vibracao_y2']) ?> | <?= f1($b['vibracao_z2']) ?></div></td>
            <td width="25%"><span class="label">Ponto 3 (X | Y | Z)</span><div class="value"><?= f1($b['vibracao_x3']) ?> | <?= f1($b['vibracao_y3']) ?> | <?= f1($b['vibracao_z3']) ?></div></td>
            <td width="25%"><span class="label">Classificação Final</span><div class="value" style="font-size: 11pt;"><?= $b['classificacao_vibracao'] ?: '---' ?></div></td>
        </tr>
        <tr>
            <td colspan="4" style="text-align: center; padding: 4px;">
                <img src="../assets/image/pontosMedicao.PNG" style="max-height: 80px; width: auto;">
            </td>
        </tr>
    </table>

    <?php if ($reg['status'] !== 'EM_BANCADA' && !$modoCliente): ?>
    <!-- UTILIZAÇÃO -->
    <table>
        <tr><td colspan="4" class="section-title bg-yellow">5. NA UTILIZAÇÃO</td></tr>
        <tr>
            <td width="25%">
                <span class="label">Pressão de Utilização</span>
                <div class="value"><?= f1($b['pressao_utilizacao_mmhg']) ?> mmHg</div>
            </td>
            <td width="25%">
                <span class="label">Rotor</span>
                <div class="value">Ø <?= f1($b['rotor_diametro']) ?> mm | Esp: <?= f1($b['rotor_espessura']) ?> mm</div>
            </td>
            <td width="25%">
                <span class="label">Ruído</span>
                <div class="value"><?= f1($b['ruido_db']) ?> dB</div>
            </td>
            <td width="25%">
                <span class="label">Tipo de Fabricação</span>
                <div class="value"><?= $b['tipo_fabricacao'] ?: '---' ?></div>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <!-- OBSERVAÇÕES E ASSINATURA -->
    <table>
        <tr><td class="section-title">6. OBSERVAÇÕES GERAIS</td></tr>
        <tr>
            <td style="min-height: 30px; vertical-align: top;">
                <?= nl2br(sanitizar($b['observacoes'] ?? 'SEM OBSERVAÇÕES ADICIONAIS.')) ?>
                <br><br>
                <strong>Medições realizadas por:</strong> <?= sanitizar($b['operador_medicao'] ?? '---') ?>
            </td>
        </tr>
    </table>

</div>

<script>
    // Abre a janela de impressão automaticamente ao carregar
    // window.onload = function() { window.print(); }
</script>

</body>
</html>
