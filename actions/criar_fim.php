<?php
/**
 * FIM Digital - Action: Criar Nova FIM
 * Cria equipamento + registro FIM + dados iniciais
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
verificarLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getConnection();
$usuario = usuarioLogado();

// ============================
// GET: Verificar série existente
// ============================
if (isset($_GET['verificar_serie'])) {
    $serie = trim($_GET['verificar_serie']);
    
    $equip = buscarEquipamentoPorSerie($serie);
    
    if ($equip) {
        $fimAberta = fimAbertaParaEquipamento($equip['id']);
        
        if ($fimAberta) {
            // Buscar dados da FIM aberta
            $stmt = $pdo->prepare("SELECT id, status FROM registros_fim WHERE id = ?");
            $stmt->execute([$fimAberta]);
            $fim = $stmt->fetch();
            
            echo json_encode([
                'equipamento_existe' => true,
                'fim_aberta' => true,
                'registro_id' => $fimAberta,
                'id_interno' => $equip['id_interno'],
                'status' => $fim['status']
            ]);
        } else {
            echo json_encode([
                'equipamento_existe' => true,
                'fim_aberta' => false,
                'id_interno' => $equip['id_interno']
            ]);
        }
    } else {
        echo json_encode(['equipamento_existe' => false, 'fim_aberta' => false]);
    }
    exit;
}

// ============================
// POST: Criar nova FIM
// ============================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

$tipo_identificacao = $_POST['tipo_identificacao'] ?? 'INTERNO';
$numero_serie_motor = trim($_POST['numero_serie_motor'] ?? '');
$modelo_equipamento = trim($_POST['modelo_equipamento'] ?? '');
$natureza = $_POST['natureza'] ?? 'NOVO';
$numero_conserto = trim($_POST['numero_conserto'] ?? '');

// Validações
if ($tipo_identificacao === 'SERIE' && empty($numero_serie_motor)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Informe o número de série do motor.']);
    exit;
}

if ($natureza === 'CONSERTO' && empty($numero_conserto)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Informe o número do conserto para equipamentos em manutenção.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $equipamento_id = null;

    // Verificar se equipamento já existe (por nº série)
    if (!empty($numero_serie_motor)) {
        $equip = buscarEquipamentoPorSerie($numero_serie_motor);
        
        if ($equip) {
            $equipamento_id = $equip['id'];
            
            // Verificar FIM aberta
            $fimAberta = fimAbertaParaEquipamento($equipamento_id);
            if ($fimAberta) {
                $pdo->rollBack();
                echo json_encode([
                    'sucesso' => false,
                    'mensagem' => 'Já existe uma FIM em aberto para este equipamento. Finalize a FIM atual antes de criar uma nova.',
                    'registro_id' => $fimAberta
                ]);
                exit;
            }

            // Atualizar modelo se informado
            if (!empty($modelo_equipamento)) {
                $stmt = $pdo->prepare("UPDATE equipamentos SET modelo_equipamento = ? WHERE id = ?");
                $stmt->execute([$modelo_equipamento, $equipamento_id]);
            }
        }
    }

    // Criar novo equipamento se não existe
    if (!$equipamento_id) {
        $id_interno = gerarIdInterno();
        
        $stmt = $pdo->prepare("
            INSERT INTO equipamentos (numero_serie_motor, id_interno, tipo_identificacao, modelo_equipamento)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $numero_serie_motor ?: null,
            $id_interno,
            $tipo_identificacao,
            $modelo_equipamento ?: null
        ]);
        $equipamento_id = $pdo->lastInsertId();
    }

    // Impedir duplicidade de NATUREZA: NOVO para o mesmo equipamento
    if ($natureza === 'NOVO') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registros_fim WHERE equipamento_id = ? AND natureza = 'NOVO'");
        $stmt->execute([$equipamento_id]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            echo json_encode(['sucesso' => false, 'mensagem' => 'Já existe uma ficha NATUREZA: NOVO para este equipamento.']);
            exit;
        }
    }

    // Criar registro FIM
    $stmt = $pdo->prepare("
        INSERT INTO registros_fim (equipamento_id, natureza, numero_conserto, status, operador_inicio_id)
        VALUES (?, ?, ?, 'EM_BANCADA', ?)
    ");
    $stmt->execute([
        $equipamento_id,
        $natureza,
        $numero_conserto ?: null,
        $usuario['id']
    ]);
    $registro_id = $pdo->lastInsertId();

    // Criar registros vazios de dados_bancada e dados_cliente
    $stmt = $pdo->prepare("INSERT INTO dados_bancada (registro_id) VALUES (?)");
    $stmt->execute([$registro_id]);

    $stmt = $pdo->prepare("INSERT INTO dados_cliente (registro_id) VALUES (?)");
    $stmt->execute([$registro_id]);

    $pdo->commit();

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'FIM criada com sucesso!',
        'registro_id' => $registro_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao criar FIM: ' . $e->getMessage()
    ]);
}
