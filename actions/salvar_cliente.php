<?php
/** FIM Digital - Action: Salvar Dados do Cliente */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
verificarLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['sucesso'=>false,'mensagem'=>'Método inválido.']); exit; }

$pdo = getConnection();
$usuario = usuarioLogado();
$registro_id = (int)($_POST['registro_id'] ?? 0);

$stmt = $pdo->prepare("SELECT status FROM registros_fim WHERE id = ?");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();

if (!$reg) { echo json_encode(['sucesso'=>false,'mensagem'=>'Registro não encontrado.']); exit; }

// Só preencher dados do cliente a partir de AGUARDANDO_CLIENTE
if (!in_array($reg['status'], ['AGUARDANDO_CLIENTE','FINALIZADO'])) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Dados do cliente só podem ser preenchidos na etapa AGUARDANDO_CLIENTE.']);
    exit;
}

if ($reg['status'] === 'FINALIZADO' && !isGestor()) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'FIM finalizada. Apenas Gestores podem editar.']); exit;
}

$campos = ['nome_cliente','numero_pedido','numero_nota_fiscal','aplicacao','localizacao','responsavel_tecnico','observacoes_finais'];
$dados = [];
foreach ($campos as $c) { $dados[$c] = !empty($_POST[$c]) ? trim($_POST[$c]) : null; }
$dados['data_entrega'] = !empty($_POST['data_entrega']) ? $_POST['data_entrega'] : null;
$dados['data_preenchimento'] = date('Y-m-d H:i:s');
$dados['operador_id'] = $usuario['id'];

if ($reg['status'] === 'FINALIZADO') {
    $stmtOld = $pdo->prepare("SELECT * FROM dados_cliente WHERE registro_id = ?");
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

$sets = []; $params = [];
foreach ($dados as $c => $v) { $sets[] = "$c = ?"; $params[] = $v; }
$params[] = $registro_id;

try {
    $pdo->prepare("UPDATE dados_cliente SET " . implode(', ', $sets) . " WHERE registro_id = ?")->execute($params);
    echo json_encode(['sucesso'=>true,'mensagem'=>'✅ DADOS DO CLIENTE SALVOS!']);
} catch (Exception $e) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Erro: '.$e->getMessage()]);
}
