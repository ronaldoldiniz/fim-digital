<?php
/** FIM Digital - Action: Alterar Cliente (com histórico) */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
verificarLogin();
verificarPerfil(['ADMINISTRATIVO','GESTOR']);
header('Content-Type: application/json; charset=utf-8');

$pdo = getConnection();
$usuario = usuarioLogado();
$registro_id = (int)($_POST['registro_id'] ?? 0);
$cliente_novo = trim($_POST['cliente_novo'] ?? '');
$motivo = $_POST['motivo'] ?? '';
$motivo_detalhe = trim($_POST['motivo_detalhe'] ?? '');

if (!$registro_id || !$cliente_novo || !$motivo) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Preencha todos os campos obrigatórios.']); exit;
}

$stmt = $pdo->prepare("SELECT dc.nome_cliente FROM dados_cliente dc WHERE dc.registro_id=?");
$stmt->execute([$registro_id]);
$row = $stmt->fetch();
$cliente_anterior = $row['nome_cliente'] ?? '';

if ($cliente_anterior === $cliente_novo) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'O nome do cliente não foi alterado.']); exit;
}

try {
    $pdo->beginTransaction();
    // Registrar histórico
    $pdo->prepare("INSERT INTO historico_cliente (registro_id,cliente_anterior,cliente_novo,motivo,motivo_detalhe,usuario_id) VALUES (?,?,?,?,?,?)")
        ->execute([$registro_id, $cliente_anterior, $cliente_novo, $motivo, $motivo_detalhe ?: null, $usuario['id']]);
    // Atualizar cliente
    $pdo->prepare("UPDATE dados_cliente SET nome_cliente=?, data_preenchimento=NOW(), operador_id=? WHERE registro_id=?")
        ->execute([$cliente_novo, $usuario['id'], $registro_id]);
    $pdo->commit();
    echo json_encode(['sucesso'=>true,'mensagem'=>'✅ Cliente alterado com sucesso! Histórico registrado.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['sucesso'=>false,'mensagem'=>'Erro: '.$e->getMessage()]);
}
