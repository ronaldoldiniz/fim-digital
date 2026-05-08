<?php
/**
 * FIM Digital - Action: Salvar Dados de Bancada
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
verificarLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

$pdo = getConnection();
$usuario = usuarioLogado();
$registro_id = (int)($_POST['registro_id'] ?? 0);

if (!$registro_id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID do registro não informado.']);
    exit;
}

// Verificar se registro existe e status permite edição
$stmt = $pdo->prepare("SELECT status, equipamento_id FROM registros_fim WHERE id = ?");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();

if (!$reg) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Registro não encontrado.']);
    exit;
}

$finalizado = ($reg['status'] === 'FINALIZADO');

// Se finalizado, só GESTOR pode editar (com log)
if ($finalizado && !isGestor()) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'FIM finalizada. Apenas Gestores podem editar.']);
    exit;
}

// Campos numéricos para tratar
$decimais = ['pressao_negativa_mmhg','pressao_positiva_mmh2o','temperatura_c','diametro_mm','vazao_m3min','ruido_db',
    'folga_radial_mm','folga_axial_mm','corrente_normal_fase_r','corrente_normal_fase_s','corrente_normal_fase_t',
    'corrente_carga_fase_r','corrente_carga_fase_s','corrente_carga_fase_t','isolacao_fase_r','isolacao_fase_s','isolacao_fase_t',
    'potencia_cv','corrente_nominal_220','corrente_nominal_380','corrente_nominal_440',
    'vibracao_x1','vibracao_y1','vibracao_z1','vibracao_x2','vibracao_y2','vibracao_z2',
    'vibracao_x3','vibracao_y3','vibracao_z3','pressao_utilizacao_mmhg','rotor_diametro','rotor_espessura',
    'medida_rotor_1','medida_rotor_2','medida_rotor_3','medida_rotor_4','medida_rotor_5','medida_rotor_6',
    'medida_rotor_7','medida_rotor_8','medida_rotor_9','medida_rotor_10','medida_rotor_11'];

$textos = ['tensao_teste_220','tensao_teste_380','tensao_teste_440','descricao_motor','numero_serie_motor','fabricante_motor',
    'tensao_motor_220','tensao_motor_380','tensao_motor_440','fator_servico','rolamento_frontal','rolamento_traseiro',
    'lubrificacao_rolamento','observacoes','observacoes_vibracao','classificacao_vibracao','tipo_fabricacao'];

$dados = [];
foreach ($decimais as $campo) {
    $val = $_POST[$campo] ?? null;
    $val = str_replace(',', '.', $val ?? '');
    $dados[$campo] = ($val !== '' && $val !== null) ? (float)$val : null;
}
foreach ($textos as $campo) {
    $val = $_POST[$campo] ?? null;
    $dados[$campo] = ($val !== null && trim($val) !== '') ? trim($val) : null;
}

// Frequência Única (Rádio)
$freq = $_POST['frequencia_unica'] ?? null;
$dados['frequencia_50hz'] = ($freq === '50') ? 1 : 0;
$dados['frequencia_60hz'] = ($freq === '60') ? 1 : 0;

// Data fabricação motor
$dados['data_fabricacao_motor'] = !empty($_POST['data_fabricacao_motor']) ? $_POST['data_fabricacao_motor'] : null;

// Tensões únicas (Rádio) - Mapeamento para as colunas existentes
$tt = $_POST['tensao_teste_unica'] ?? null;
$dados['tensao_teste_220'] = ($tt === '220') ? '1' : null;
$dados['tensao_teste_380'] = ($tt === '380') ? '1' : null;
$dados['tensao_teste_440'] = ($tt === '440') ? '1' : null;

$tm = $_POST['tensao_motor_unica'] ?? null;
$dados['tensao_motor_220'] = ($tm === '220') ? '1' : null;
$dados['tensao_motor_380'] = ($tm === '380') ? '1' : null;
$dados['tensao_motor_440'] = ($tm === '440') ? '1' : null;

// Calcular desvios automaticamente
$dados['desvio_normal'] = calcularDesvio($dados['corrente_normal_fase_r'], $dados['corrente_normal_fase_s'], $dados['corrente_normal_fase_t']);
$dados['desvio_carga'] = calcularDesvio($dados['corrente_carga_fase_r'], $dados['corrente_carga_fase_s'], $dados['corrente_carga_fase_t']);

// Converter CV para kW (1 CV = 0.7355 kW)
$dados['potencia_kw'] = $dados['potencia_cv'] !== null ? round($dados['potencia_cv'] * 0.7355, 2) : null;

// Classificar vibração
$vibValues = [$dados['vibracao_x1'],$dados['vibracao_y1'],$dados['vibracao_z1'],
    $dados['vibracao_x2'],$dados['vibracao_y2'],$dados['vibracao_z2'],
    $dados['vibracao_x3'],$dados['vibracao_y3'],$dados['vibracao_z3']];
$dados['classificacao_vibracao'] = classificarVibracao($vibValues);

// Se finalizado, registrar log de cada campo alterado
if ($finalizado) {
    $stmtOld = $pdo->prepare("SELECT * FROM dados_bancada WHERE registro_id = ?");
    $stmtOld->execute([$registro_id]);
    $antigo = $stmtOld->fetch();
    $motivo = $_POST['motivo_edicao'] ?? 'Edição pós-finalização';
    
    if ($antigo) {
        foreach ($dados as $campo => $novoVal) {
            $antigoVal = $antigo[$campo] ?? null;
            if ((string)$antigoVal !== (string)$novoVal) {
                registrarLogEdicao($registro_id, $campo, $antigoVal, $novoVal, $usuario['id'], $motivo);
            }
        }
    }
}

// Metadados
$dados['data_preenchimento'] = date('Y-m-d H:i:s');
$dados['operador_id'] = $usuario['id'];

// Garantir que o registro exista em dados_bancada
$stmtCheck = $pdo->prepare("SELECT 1 FROM dados_bancada WHERE registro_id = ?");
$stmtCheck->execute([$registro_id]);
if (!$stmtCheck->fetch()) {
    $pdo->prepare("INSERT INTO dados_bancada (registro_id) VALUES (?)")->execute([$registro_id]);
}

// Montar UPDATE
$sets = [];
$params = [];
foreach ($dados as $campo => $valor) {
    $sets[] = "{$campo} = ?";
    $params[] = $valor;
}
$params[] = $registro_id;

try {
    $sql = "UPDATE dados_bancada SET " . implode(', ', $sets) . " WHERE registro_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Sincronizar o número de série do motor com a tabela equipamentos para exibição no Dashboard
    if (!empty($dados['numero_serie_motor']) && !empty($reg['equipamento_id'])) {
        $pdo->prepare("UPDATE equipamentos SET numero_serie_motor = ? WHERE id = ?")
            ->execute([$dados['numero_serie_motor'], $reg['equipamento_id']]);
    }

    echo json_encode([
        'sucesso' => true,
        'mensagem' => '✅ DADOS SALVOS COM SUCESSO!',
        'desvio_normal' => $dados['desvio_normal'],
        'desvio_carga' => $dados['desvio_carga'],
        'classificacao_vibracao' => $dados['classificacao_vibracao'],
        'potencia_kw' => $dados['potencia_kw']
    ]);
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar: ' . $e->getMessage()]);
}
