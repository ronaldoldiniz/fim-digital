<?php
/** FIM Digital - Action: Alterar Status */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
verificarLogin();
header('Content-Type: application/json; charset=utf-8');

$pdo = getConnection();
$usuario = usuarioLogado();
$registro_id = (int)($_POST['registro_id'] ?? 0);

$stmt = $pdo->prepare("SELECT r.*, e.id_interno FROM registros_fim r JOIN equipamentos e ON r.equipamento_id=e.id WHERE r.id=?");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();

if (!$reg) { echo json_encode(['sucesso'=>false,'mensagem'=>'Registro não encontrado.']); exit; }

$statusAtual = $reg['status'];
$acao = $_POST['acao'] ?? 'avancar';
$proximo = ['EM_BANCADA'=>'EM_MONTAGEM','EM_MONTAGEM'=>'AGUARDANDO_CLIENTE','AGUARDANDO_CLIENTE'=>'FINALIZADO'];
$anterior = ['EM_MONTAGEM'=>'EM_BANCADA','AGUARDANDO_CLIENTE'=>'EM_MONTAGEM','FINALIZADO'=>'AGUARDANDO_CLIENTE'];

if ($acao === 'voltar') {
    if (!isset($anterior[$statusAtual])) {
        echo json_encode(['sucesso'=>false,'mensagem'=>'Não é possível voltar deste status.']); exit;
    }
    $novoStatus = $anterior[$statusAtual];
    $pdo->prepare("UPDATE registros_fim SET status=?, data_finalizacao=NULL, operador_final_id=NULL WHERE id=?")
        ->execute([$novoStatus, $registro_id]);
    echo json_encode(['sucesso'=>true,'mensagem'=>'Status retornado com sucesso!','novo_status'=>$novoStatus]);
    exit;
}

if (!isset($proximo[$statusAtual])) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Esta FIM já está finalizada.']); exit;
}

// Validar campos obrigatórios da etapa atual
$stmtB = $pdo->prepare("SELECT * FROM dados_bancada WHERE registro_id=?");
$stmtB->execute([$registro_id]);
$bancada = $stmtB->fetch();

if ($statusAtual === 'EM_BANCADA') {
    $faltantes = [];
    $vazio = function($campo) use ($bancada) {
        return (!isset($bancada[$campo]) || $bancada[$campo] === null || $bancada[$campo] === '');
    };

    if ($vazio('pressao_negativa_mmhg')) $faltantes[] = 'Pressão Negativa';
    if ($vazio('corrente_normal_fase_r')) $faltantes[] = 'Corrente Normal Fase R';
    if ($vazio('corrente_normal_fase_s')) $faltantes[] = 'Corrente Normal Fase S';
    if ($vazio('corrente_normal_fase_t')) $faltantes[] = 'Corrente Normal Fase T';
    if ($vazio('isolacao_fase_r')) $faltantes[] = 'Isolação Fase R';
    if ($vazio('isolacao_fase_s')) $faltantes[] = 'Isolação Fase S';
    if ($vazio('isolacao_fase_t')) $faltantes[] = 'Isolação Fase T';
    if ($vazio('descricao_motor')) $faltantes[] = 'Descrição do Motor';
    if ($vazio('numero_serie_motor')) $faltantes[] = 'Nº Série (Motor)';
    if ($vazio('potencia_cv')) $faltantes[] = 'Potência (CV)';
    
    if ($vazio('corrente_carga_fase_r')) $faltantes[] = 'Corrente Carga Fase R';
    if ($vazio('corrente_carga_fase_s')) $faltantes[] = 'Corrente Carga Fase S';
    if ($vazio('corrente_carga_fase_t')) $faltantes[] = 'Corrente Carga Fase T';

    // Tensões são obrigatórias (apenas 1 selecionada via rádio)
    $temTensaoTeste = !empty($bancada['tensao_teste_220']) || !empty($bancada['tensao_teste_380']) || !empty($bancada['tensao_teste_440']);
    if (!$temTensaoTeste) $faltantes[] = 'Tensão Aplicada no Teste';

    if (!empty($faltantes)) {
        echo json_encode(['sucesso'=>false,'mensagem'=>'Campos obrigatórios faltantes: '.implode(', ',$faltantes)]);
        exit;
    }
}

if ($statusAtual === 'AGUARDANDO_CLIENTE') {
    $stmtC = $pdo->prepare("SELECT * FROM dados_cliente WHERE registro_id=?");
    $stmtC->execute([$registro_id]);
    $cliente = $stmtC->fetch();
    if (empty($cliente['nome_cliente'])) {
        echo json_encode(['sucesso'=>false,'mensagem'=>'Preencha o Nome do Cliente antes de finalizar.']);
        exit;
    }
}

$novoStatus = $proximo[$statusAtual];

try {
    if ($novoStatus === 'FINALIZADO') {
        $pdo->prepare("UPDATE registros_fim SET status=?, data_finalizacao=NOW(), operador_final_id=? WHERE id=?")
            ->execute([$novoStatus, $usuario['id'], $registro_id]);
    } else {
        $pdo->prepare("UPDATE registros_fim SET status=? WHERE id=?")->execute([$novoStatus, $registro_id]);
    }
    echo json_encode(['sucesso'=>true,'mensagem'=>'Status atualizado!','novo_status'=>$novoStatus]);
} catch (Exception $e) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Erro: '.$e->getMessage()]);
}
