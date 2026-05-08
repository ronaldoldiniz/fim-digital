<?php
/** FIM Digital - Action: Upload de Foto (Pontos Medidos) */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
verificarLogin();
header('Content-Type: application/json; charset=utf-8');

$registro_id = (int)($_POST['registro_id'] ?? 0);
if (!$registro_id) { echo json_encode(['sucesso'=>false,'mensagem'=>'ID não informado.']); exit; }

$uploadDir = __DIR__ . '/../uploads/pontos_medidos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Erro no upload do arquivo.']); exit;
}

$ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Formato inválido. Use JPG, PNG ou GIF.']); exit;
}

$nomeArquivo = 'pontos_' . $registro_id . '_' . time() . '.' . $ext;
$destino = $uploadDir . $nomeArquivo;

if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
    $pdo = getConnection();
    $pdo->prepare("UPDATE dados_bancada SET pontos_medidos_foto=? WHERE registro_id=?")
        ->execute(['uploads/pontos_medidos/' . $nomeArquivo, $registro_id]);
    echo json_encode(['sucesso'=>true,'mensagem'=>'✅ Foto salva!','caminho'=>'uploads/pontos_medidos/'.$nomeArquivo]);
} else {
    echo json_encode(['sucesso'=>false,'mensagem'=>'Erro ao mover arquivo.']);
}
