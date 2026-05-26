<?php
/** FIM Digital - Gerador de PDF */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
verificarLogin();

$registro_id = (int)($_GET['id'] ?? 0);
if (!$registro_id) die('ID não informado.');

$pdo = getConnection();
$modoCliente = isset($_GET['cliente']);

// Buscar FIM
$stmt = $pdo->prepare("SELECT r.*, e.numero_serie_motor, e.id_interno, e.modelo_equipamento, u.nome as responsavel FROM registros_fim r JOIN equipamentos e ON r.equipamento_id = e.id LEFT JOIN usuarios u ON r.operador_final_id = u.id WHERE r.id = ?");
$stmt->execute([$registro_id]);
$reg = $stmt->fetch();
if (!$reg) die('Registro não encontrado.');

$stmtB = $pdo->prepare("SELECT b.*, u.nome as operador_medicao FROM dados_bancada b LEFT JOIN usuarios u ON b.operador_id = u.id WHERE b.registro_id = ?");
$stmtB->execute([$registro_id]);
$b = $stmtB->fetch() ?: [];

$stmtC = $pdo->prepare("SELECT * FROM dados_cliente WHERE registro_id = ?");
$stmtC->execute([$registro_id]);
$c = $stmtC->fetch() ?: [];

// Dependência mPDF
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    die("A biblioteca mPDF não está instalada. Execute 'composer install' na raiz do projeto.");
}
require_once __DIR__ . '/../vendor/autoload.php';

// Montar HTML das Dimensões do Rotor (MA - MI)
$rotorDimsHtml = '';
if (!$modoCliente && in_array($b['tipo_fabricacao'] ?? '', ['MA', 'MI'])) {
    $rotorDimsHtml = '
    <tr><td colspan="4" class="title bg-yellow">DIMENSÕES DO ROTOR (MA - MI)</td></tr>
    <tr>';
    for($i=1; $i<=4; $i++) {
        $v = $b["medida_rotor_".$i] ?? null;
        $rotorDimsHtml .= '<td width="25%" class="text-center">N° '.$i.' (Ø): '.(is_numeric($v)?number_format((float)$v,2,',','.'):'___').' mm</td>';
    }
    $rotorDimsHtml .= '
    </tr>
    <tr>';
    for($i=5; $i<=8; $i++) {
        $v = $b["medida_rotor_".$i] ?? null;
        $rotorDimsHtml .= '<td width="25%" class="text-center">N° '.$i.' (Ø): '.(is_numeric($v)?number_format((float)$v,2,',','.'):'___').' mm</td>';
    }
    $rotorDimsHtml .= '
    </tr>
    <tr>';
    for($i=9; $i<=11; $i++) {
        $v = $b["medida_rotor_".$i] ?? null;
        $rotorDimsHtml .= '<td width="25%" class="text-center">N° '.$i.': '.(is_numeric($v)?number_format((float)$v,2,',','.'):'___').' mm</td>';
    }
    $rotorDimsHtml .= '<td width="25%"></td>
    </tr>';
}

$html = '
<style>
    body { font-family: sans-serif; font-size: 8px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
    th, td { border: 1px solid #000; padding: 3px; }
    .header { text-align: center; font-weight: bold; font-size: 12px; background-color: #f0f0f0; }
    .title { background-color: #d9edf7; font-weight: bold; text-align: left; font-size: 9px; }
    .bg-green { background-color: #dff0d8; }
    .bg-yellow { background-color: #fcf8e3; }
    .text-center { text-align: center; }
    .bold { font-weight: bold; }
</style>
<table>
    <tr>
        <td width="20%" class="text-center">MOROTÓ</td>
        <td width="60%" class="header">FOLHA DE INSPEÇÃO E MEDIÇÃO - F.I.M.</td>
        <td width="20%" class="text-center">VER.: 09<br>DATA: '.date('d/m/Y').'</td>
    </tr>
</table>

<table>
    <tr><td colspan="4" class="title bg-green">IDENTIFICAÇÃO DO EQUIPAMENTO / CLIENTE</td></tr>
    <tr>
        <td width="60%" colspan="2">Cliente: '.sanitizar($c['nome_cliente'] ?? '').'</td>
        <td width="20%">Nº Série: '.sanitizar($reg['numero_serie_motor']).'</td>
        <td width="20%">Nº NF: '.sanitizar($c['numero_nota_fiscal'] ?? '').'</td>
    </tr>
    <tr>
        <td width="40%">Modelo do Equip.: '.sanitizar($reg['modelo_equipamento']).'</td>
        <td width="20%">Natureza: '.$reg['natureza'].' '.($reg['natureza']==='CONSERTO'?'('.sanitizar($reg['numero_conserto']).')':'').'</td>
        <td width="20%" colspan="2">Data Inspeção: '.formatarData($reg['data_inicio']).'</td>
    </tr>
</table>

<table>
    <tr><td colspan="4" class="title bg-yellow">INSPEÇÃO E MEDIÇÃO: NA GERAÇÃO</td></tr>
    <tr>
        <td width="30%">Tensão de Teste:<br>[ '.($b['tensao_teste_220']?$b['tensao_teste_220']:'___').' ] 220V &nbsp; [ '.($b['tensao_teste_380']?$b['tensao_teste_380']:'___').' ] 380V &nbsp; [ '.($b['tensao_teste_440']?$b['tensao_teste_440']:'___').' ] 440V</td>
        <td width="25%">Pressão Negat.: '.($b['pressao_negativa_mmhg']??'___').' mmHg</td>
        <td width="20%">Press. Posit.: '.($b['pressao_positiva_mmh2o']??'___').' mmH2O</td>
        <td width="25%">Temperatura: '.($b['temperatura_c']??'___').' °C</td>
    </tr>
    <tr>
        '.($modoCliente ? '' : '<td>Vazão: '.($b['vazao_m3min']??'___').' m³/min</td>').'
        <td colspan="'.($modoCliente ? 4 : 3).'">Folga Rotor: Rad '.($b['folga_radial_mm']??'___').'mm / Ax '.($b['folga_axial_mm']??'___').'mm</td>
    </tr>'.$rotorDimsHtml.'
    <tr>
        <td colspan="2" class="text-center bold">Corrente Operação Normal</td>
        <td colspan="2" class="text-center bold">Corrente Carga Máxima</td>
    </tr>
    <tr>
        <td colspan="2">Fase R: '.($b['corrente_normal_fase_r']??'___').'A &nbsp;&nbsp; Fase S: '.($b['corrente_normal_fase_s']??'___').'A &nbsp;&nbsp; Fase T: '.($b['corrente_normal_fase_t']??'___').'A</td>
        <td colspan="2">Fase R: '.($b['corrente_carga_fase_r']??'___').'A &nbsp;&nbsp; Fase S: '.($b['corrente_carga_fase_s']??'___').'A &nbsp;&nbsp; Fase T: '.($b['corrente_carga_fase_t']??'___').'A</td>
    </tr>
    <tr>
        <td colspan="4" class="text-center">Isolação (Massa) - Fase R: '.($b['isolacao_fase_r']??'___').'MΩ &nbsp;&nbsp; Fase S: '.($b['isolacao_fase_s']??'___').'MΩ &nbsp;&nbsp; Fase T: '.($b['isolacao_fase_t']??'___').'MΩ</td>
    </tr>
</table>

<table>
    <tr><td colspan="4" class="title bg-yellow">DADOS DO MOTOR</td></tr>
    <tr>
        <td colspan="2">Descrição: '.sanitizar($b['descricao_motor']??'').'</td>
        <td>Nº Série: '.sanitizar($b['numero_serie_motor']??'').'</td>
        <td>Data Fab.: '.($b['data_fabricacao_motor'] ? date('d/m/Y', strtotime($b['data_fabricacao_motor'])) : '').'</td>
    </tr>
    <tr>
        <td width="30%">Tensão Aplicação:<br>[ '.($b['tensao_motor_220']?$b['tensao_motor_220']:'___').' ] 220V &nbsp; [ '.($b['tensao_motor_380']?$b['tensao_motor_380']:'___').' ] 380V &nbsp; [ '.($b['tensao_motor_440']?$b['tensao_motor_440']:'___').' ] 440V</td>
        <td width="20%">Freq:<br>[ '.($b['frequencia_50hz']?'X':' ').' ] 50Hz &nbsp; [ '.($b['frequencia_60hz']?'X':' ').' ] 60Hz</td>
        <td width="20%">Potência: '.($b['potencia_cv']??'___').' CV / '.($b['potencia_kw']??'___').' kW</td>
        <td width="30%">Fator Serv.: '.sanitizar($b['fator_servico']??'').'</td>
    </tr>
    <tr>
        <td width="30%">Rol. Frontal: '.sanitizar($b['rolamento_frontal']??'___').'</td>
        <td width="20%">Rol. Traseiro: '.sanitizar($b['rolamento_traseiro']??'___').'</td>
        <td width="20%">Lubrificação: '.sanitizar($b['lubrificacao_rolamento']??'___').'</td>
        <td width="30%">Corrente Nom.: p/220V: '.($b['corrente_nominal_220']??'___').'A &nbsp; p/380V: '.($b['corrente_nominal_380']??'___').'A &nbsp; p/440V: '.($b['corrente_nominal_440']??'___').'A</td>
    </tr>
</table>

<table>
    <tr><td class="title bg-yellow">OBSERVAÇÕES</td></tr>
    <tr><td height="40">'.nl2br(sanitizar($b['observacoes']??'')).'<br><br><strong>Medições realizadas por:</strong> '.sanitizar($b['operador_medicao'] ?? '---').'</td></tr>
</table>

<table>
    <tr><td colspan="4" class="title bg-yellow">VIBRAÇÃO - VELOCIDADE (mm/s) - ISO 10816-7</td></tr>
    <tr>
        <td width="25%" class="text-center">X: '.($b['vibracao_x1']??'_').' | '.($b['vibracao_x2']??'_').' | '.($b['vibracao_x3']??'_').'</td>
        <td width="25%" class="text-center">Y: '.($b['vibracao_y1']??'_').' | '.($b['vibracao_y2']??'_').' | '.($b['vibracao_y3']??'_').'</td>
        <td width="25%" class="text-center">Z: '.($b['vibracao_z1']??'_').' | '.($b['vibracao_z2']??'_').' | '.($b['vibracao_z3']??'_').'</td>
        <td width="25%" class="text-center bold">Classe: '.($b['classificacao_vibracao']??'_').'</td>
    </tr>
    <tr>
        <td colspan="4" style="text-align: center; padding: 2px;">
            <img src="../assets/image/pontosMedicao.PNG" style="max-height: 60px; width: auto;">
        </td>
    </tr>
</table>

'.($reg['status'] !== 'EM_BANCADA' && !$modoCliente ? '
<table>
    <tr><td colspan="4" class="title bg-yellow">NA UTILIZAÇÃO</td></tr>
    <tr>
        <td width="25%">Pressão Negat.: '.($b['pressao_utilizacao_mmhg']??'___').' mmHg</td>
        <td width="25%">Rotor: Ø '.($b['rotor_diametro']??'___').'mm / Esp. '.($b['rotor_espessura']??'___').'mm</td>
        <td width="25%">Ruído: '.($b['ruido_db']??'___').' dB</td>
        <td width="25%">Fabricação: '.($b['tipo_fabricacao']??'___').'</td>
    </tr>
</table>
' : '').'
';

$mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin_top' => 5, 'margin_bottom' => 5, 'margin_left' => 8, 'margin_right' => 8]);
$mpdf->WriteHTML($html);
$mpdf->Output('FIM_'.$reg['id_interno'].'.pdf', 'I');
