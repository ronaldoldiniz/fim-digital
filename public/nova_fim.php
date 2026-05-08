<?php
/**
 * FIM Digital - Nova FIM
 * Criar nova Ficha de Inspeção e Medição
 * Etapa 1: Identificação do Equipamento
 * Etapa 2: Natureza (Novo / Conserto)
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
verificarLogin();

if (isset($_GET['logout'])) { fazerLogout(); }

headerHTML('Nova FIM', 'nova');
?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-6">
        <div class="card fade-in">
            <div class="card-header titulo-bancada">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i> Criar Nova FIM</h5>
            </div>
            <div class="card-body p-4">

                <!-- Alertas dinâmicos -->
                <div id="alertaFim" class="d-none"></div>

                <form id="formNovaFim" method="POST" action="../actions/criar_fim.php">
                    
                    <!-- ETAPA 1: Identificação -->
                    <h6 class="text-uppercase text-muted mb-3">
                        <i class="bi bi-1-circle me-1"></i> Identificação do Equipamento
                    </h6>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Método de Identificação:</label>
                        <div class="d-flex gap-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipo_identificacao" 
                                       id="tipoSerie" value="SERIE" checked 
                                       onchange="toggleIdentificacao()">
                                <label class="form-check-label fw-bold" for="tipoSerie">
                                    <i class="bi bi-upc-scan me-1"></i> Nº Série do Motor
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tipo_identificacao" 
                                       id="tipoInterno" value="INTERNO" 
                                       onchange="toggleIdentificacao()">
                                <label class="form-check-label fw-bold" for="tipoInterno">
                                    <i class="bi bi-hash me-1"></i> ID Automático (sem nº série)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4" id="campoSerie">
                        <label for="numero_serie_motor" class="form-label obrigatorio">
                            Número de Série do Motor
                        </label>
                        <input type="text" class="form-control form-control-lg" 
                               id="numero_serie_motor" name="numero_serie_motor" 
                               placeholder="Ex: WEG-123456789"
                               onblur="verificarSerieExistente()">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            Número da placa do motor. Se o motor não possui placa, selecione "ID Automático".
                        </small>
                    </div>

                    <div class="mb-4" id="campoInterno" style="display:none;">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            O sistema irá gerar um ID automático no formato <strong>FIM-<?= date('Y') ?>-XXXXXX</strong>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="modelo_equipamento" class="form-label">
                            Modelo do Equipamento
                        </label>
                        <input type="text" class="form-control" 
                               id="modelo_equipamento" name="modelo_equipamento" 
                               placeholder="Ex: ND 325VS x 2200">
                    </div>

                    <hr class="my-4">

                    <!-- ETAPA 2: Natureza -->
                    <h6 class="text-uppercase text-muted mb-3">
                        <i class="bi bi-2-circle me-1"></i> Natureza do Equipamento
                    </h6>

                    <div class="mb-3">
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="natureza" 
                                       id="naturezaNovo" value="NOVO" checked 
                                       onchange="toggleNatureza()">
                                <label class="form-check-label fw-bold" for="naturezaNovo">
                                    <i class="bi bi-star-fill text-primary me-1"></i> NOVO
                                    <small class="text-muted d-block">Equipamento novo fabricado</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="natureza" 
                                       id="naturezaConserto" value="CONSERTO" 
                                       onchange="toggleNatureza()">
                                <label class="form-check-label fw-bold" for="naturezaConserto">
                                    <i class="bi bi-tools text-warning me-1"></i> CONSERTO
                                    <small class="text-muted d-block">Equipamento em manutenção</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 d-none" id="campoConserto">
                        <label for="numero_conserto" class="form-label obrigatorio">
                            Número do Conserto
                        </label>
                        <input type="text" class="form-control" 
                               id="numero_conserto" name="numero_conserto" 
                               placeholder="Nº fornecido pelo setor de Compras">
                        <small class="text-muted">Obrigatório para consertos</small>
                    </div>

                    <hr class="my-4">

                    <button type="submit" class="btn btn-nova-fim btn-industrial btn-industrial-lg w-100"
                            id="btnCriar">
                        <i class="bi bi-plus-circle-fill me-2"></i> CRIAR NOVA FIM
                    </button>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleIdentificacao() {
    const serie = document.getElementById('tipoSerie').checked;
    document.getElementById('campoSerie').style.display = serie ? 'block' : 'none';
    document.getElementById('campoInterno').style.display = serie ? 'none' : 'block';
    
    const inputSerie = document.getElementById('numero_serie_motor');
    if (!serie) {
        inputSerie.value = '';
        inputSerie.removeAttribute('required');
    } else {
        inputSerie.setAttribute('required', 'required');
    }
}

function toggleNatureza() {
    const conserto = document.getElementById('naturezaConserto').checked;
    document.getElementById('campoConserto').classList.toggle('d-none', !conserto);
    
    const inputConserto = document.getElementById('numero_conserto');
    if (conserto) {
        inputConserto.setAttribute('required', 'required');
    } else {
        inputConserto.value = '';
        inputConserto.removeAttribute('required');
    }
}

function verificarSerieExistente() {
    const serie = document.getElementById('numero_serie_motor').value.trim();
    if (!serie) return;

    const alerta = document.getElementById('alertaFim');

    fetch('../actions/criar_fim.php?verificar_serie=' + encodeURIComponent(serie))
        .then(r => r.json())
        .then(data => {
            if (data.fim_aberta) {
                alerta.className = 'alert alert-warning';
                alerta.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Atenção!</strong> Já existe uma FIM em aberto para este equipamento (${data.id_interno}).
                    <br>Status atual: <strong>${data.status}</strong>
                    <br><br>
                    <a href="formulario.php?id=${data.registro_id}" class="btn btn-warning btn-industrial">
                        <i class="bi bi-arrow-right-circle"></i> Abrir FIM Existente
                    </a>
                `;
            } else if (data.equipamento_existe) {
                alerta.className = 'alert alert-info';
                alerta.innerHTML = `
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Equipamento encontrado: <strong>${data.id_interno}</strong>. Uma nova FIM será criada para este equipamento.
                `;
            } else {
                alerta.className = 'd-none';
                alerta.innerHTML = '';
            }
        })
        .catch(() => {});
}

// Envio do formulário via AJAX
document.getElementById('formNovaFim').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btnCriar');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Criando...';

    const formData = new FormData(this);

    fetch('../actions/criar_fim.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.sucesso) {
            // Redirecionar para o formulário da FIM criada
            window.location.href = 'formulario.php?id=' + data.registro_id + '&novo=1';
        } else {
            const alerta = document.getElementById('alertaFim');
            alerta.className = 'alert alert-danger';
            alerta.innerHTML = '<i class="bi bi-x-circle-fill me-2"></i>' + data.mensagem;
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plus-circle-fill me-2"></i> CRIAR NOVA FIM';
        }
    })
    .catch(() => {
        mostrarToast('Erro de comunicação com o servidor.', 'erro');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-circle-fill me-2"></i> CRIAR NOVA FIM';
    });
});
</script>

<?php footerHTML(); ?>
