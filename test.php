<?php
require_once __DIR__ . '/config/functions.php';

$pdo = getConnection();
$registro_id = 1;
$_POST = ['registro_id' => 1, 'acao' => 'avancar'];

$stmtB = $pdo->prepare("SELECT * FROM dados_bancada WHERE registro_id=?");
$stmtB->execute([$registro_id]);
$bancada = $stmtB->fetch();

$faltantes = [];
$vazio = function($campo) use ($bancada) {
    return (!isset($bancada[$campo]) || $bancada[$campo] === null || $bancada[$campo] === '');
};

if ($vazio('pressao_negativa_mmhg')) $faltantes[] = 'Pressão Negativa';
if ($vazio('corrente_normal_fase_r')) $faltantes[] = 'Corrente Normal Fase R';
if ($vazio('potencia_cv')) $faltantes[] = 'Potência (CV)';

$temTensaoTeste = !empty($bancada['tensao_teste_220']) || !empty($bancada['tensao_teste_380']) || !empty($bancada['tensao_teste_440']);
if (!$temTensaoTeste) $faltantes[] = 'Tensão Aplicada no Teste';

var_dump($faltantes);
