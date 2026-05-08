/**
 * FIM Digital - JavaScript Principal
 * Morotó Indústria e Comércio de Aspiradores Industriais
 * 
 * Funções: AJAX, cálculos automáticos, validações, feedback visual
 */

// ================================================================
// TOAST / NOTIFICAÇÕES
// ================================================================

/**
 * Exibe um toast de notificação
 * @param {string} mensagem - Texto da notificação
 * @param {string} tipo - 'sucesso', 'erro' ou 'aviso'
 * @param {number} duracao - Duração em ms (padrão 4000)
 */
function mostrarToast(mensagem, tipo = 'sucesso', duracao = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toastId = 'toast-' + Date.now();
    const classeCSS = tipo === 'sucesso' ? 'toast-sucesso' : (tipo === 'erro' ? 'toast-erro' : 'bg-warning text-dark');
    const icone = tipo === 'sucesso' ? 'bi-check-circle-fill' : (tipo === 'erro' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');

    const toastHTML = `
        <div id="${toastId}" class="toast ${classeCSS}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="${duracao}">
            <div class="toast-body d-flex align-items-center gap-2 fs-6">
                <i class="bi ${icone} fs-4"></i>
                <span>${mensagem}</span>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHTML);
    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl);
    toast.show();

    // Remover o elemento após fechar
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

// ================================================================
// AJAX - SALVAR DADOS
// ================================================================

/**
 * Envia dados via AJAX (POST)
 * @param {string} url - URL da action
 * @param {FormData|Object} dados - Dados para enviar
 * @param {Function} callback - Função de callback (sucesso)
 */
function salvarAjax(url, dados, callback) {
    const formData = dados instanceof FormData ? dados : criarFormData(dados);

    // Adicionar indicador de loading
    const btnSalvar = document.querySelector('[data-salvando]');
    if (btnSalvar) {
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<i class="bi bi-hourglass-split"></i> Salvando...';
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            mostrarToast(data.mensagem || '✅ DADOS SALVOS COM SUCESSO!', 'sucesso');
            if (callback) callback(data);
        } else {
            mostrarToast(data.mensagem || '❌ Erro ao salvar dados.', 'erro');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mostrarToast('❌ Erro de comunicação com o servidor.', 'erro');
    })
    .finally(() => {
        if (btnSalvar) {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="bi bi-floppy"></i> Salvar';
        }
    });
}

/**
 * Converte objeto para FormData
 */
function criarFormData(obj) {
    const fd = new FormData();
    for (const key in obj) {
        if (obj.hasOwnProperty(key)) {
            fd.append(key, obj[key]);
        }
    }
    return fd;
}

// ================================================================
// SALVAR SEÇÕES DO FORMULÁRIO
// ================================================================

/**
 * Salva dados de bancada via AJAX
 */
function salvarBancada() {
    const form = document.getElementById('formBancada');
    if (!form) return;

    const formData = new FormData(form);
    salvarAjax('actions/salvar_bancada.php', formData, function(data) {
        // Atualizar campos calculados se retornados
        if (data.desvio_normal !== undefined) {
            atualizarDesvio('desvioNormal', data.desvio_normal);
        }
        if (data.desvio_carga !== undefined) {
            atualizarDesvio('desvioCarga', data.desvio_carga);
        }
        if (data.classificacao_vibracao) {
            atualizarClassificacaoVibracao(data.classificacao_vibracao);
        }
    });
}

/**
 * Salva dados do cliente via AJAX
 */
function salvarCliente() {
    const form = document.getElementById('formCliente');
    if (!form) return;

    const formData = new FormData(form);
    salvarAjax('actions/salvar_cliente.php', formData);
}

/**
 * Limpa todos os campos editáveis do formulário
 */
window.limparFormulario = function() {
    console.log('Executando: window.limparFormulario()');
    if (!confirm('Deseja realmente LIMPAR todos os campos desta tela? Esta ação não pode ser desfeita após salvar.')) {
        return;
    }

    // Selecionar todos os inputs e selects das seções editáveis
    const campos = document.querySelectorAll('input:not([type="hidden"]), select, textarea');
    
    campos.forEach(campo => {
        if (campo.type === 'radio' || campo.type === 'checkbox') {
            campo.checked = false;
        } else {
            campo.value = '';
        }
        
        // Disparar evento de mudança para atualizar cálculos (desvios, etc)
        campo.dispatchEvent(new Event('change', { bubbles: true }));
        campo.dispatchEvent(new Event('input', { bubbles: true }));
    });

    mostrarToast('Formulário limpo com sucesso!', 'aviso');
}

/**
 * Limpa apenas os campos de uma seção específica
 * @param {string} containerId - ID do elemento pai (ex: collapseMedicao)
 */
window.limparSecao = function(containerId) {
    console.log('--- INICIANDO LIMPEZA DE SEÇÃO ---');
    console.log('Container ID:', containerId);
    
    const container = document.getElementById(containerId);
    
    if (!container) {
        console.error('ERRO: Container não encontrado:', containerId);
        return;
    }

    // Removendo confirm para evitar bloqueio do navegador
    const campos = container.querySelectorAll('input, select, textarea');
    console.log('Campos encontrados para limpeza:', campos.length);

    campos.forEach(campo => {
        // Ignorar campos hidden de sistema
        if (campo.type === 'hidden' && (campo.name === 'registro_id' || campo.id === 'registro_id')) {
            return;
        }

        if (campo.type === 'radio' || campo.type === 'checkbox') {
            campo.checked = false;
        } else {
            campo.value = '';
        }
        
        // Disparar eventos para atualizar cálculos e visual
        campo.dispatchEvent(new Event('change', { bubbles: true }));
        campo.dispatchEvent(new Event('input', { bubbles: true }));
    });

    // Casos especiais (Vibração e Desvios)
    if (containerId === 'collapseVibracao') {
        recalcularClassificacaoVibracao();
    }
    
    // Forçar atualização de desvios se os campos forem da seção de medição
    if (containerId === 'collapseMedicao') {
        calcularDesvioNormal();
        calcularDesvioCarga();
    }

    mostrarToast('Seção limpa com sucesso!', 'aviso');
}

// ================================================================
// CÁLCULOS AUTOMÁTICOS
// ================================================================

/**
 * Calcula desvio entre 3 fases
 * Fórmula: ((MAX - MIN) / MEDIA) * 100
 * Normal até 5%
 */
function calcularDesvio(faseR, faseS, faseT) {
    const r = parseFloat(faseR) || 0;
    const s = parseFloat(faseS) || 0;
    const t = parseFloat(faseT) || 0;

    if (r === 0 && s === 0 && t === 0) return null;

    const media = (r + s + t) / 3;
    if (media === 0) return null;

    const max = Math.max(r, s, t);
    const min = Math.min(r, s, t);

    return ((max - min) / media * 100).toFixed(2);
}

/**
 * Calcula e exibe o desvio de corrente normal
 */
function calcularDesvioNormal() {
    const r = document.getElementById('corrente_normal_fase_r')?.value;
    const s = document.getElementById('corrente_normal_fase_s')?.value;
    const t = document.getElementById('corrente_normal_fase_t')?.value;

    const desvio = calcularDesvio(r, s, t);
    atualizarDesvio('desvioNormal', desvio);

    // Atualizar campo hidden
    const hidden = document.getElementById('desvio_normal');
    if (hidden) hidden.value = desvio || '';
}

/**
 * Calcula e exibe o desvio de corrente em carga máxima
 */
function calcularDesvioCarga() {
    const r = document.getElementById('corrente_carga_fase_r')?.value;
    const s = document.getElementById('corrente_carga_fase_s')?.value;
    const t = document.getElementById('corrente_carga_fase_t')?.value;

    const desvio = calcularDesvio(r, s, t);
    atualizarDesvio('desvioCarga', desvio);

    const hidden = document.getElementById('desvio_carga');
    if (hidden) hidden.value = desvio || '';
}

/**
 * Atualiza a exibição visual do desvio
 */
function atualizarDesvio(elementId, desvio) {
    const el = document.getElementById(elementId);
    if (!el) return;

    if (desvio === null || desvio === undefined) {
        el.innerHTML = '<span class="text-muted">--</span>';
        return;
    }

    const valor = parseFloat(desvio);
    const formatado = valor.toFixed(2).replace('.', ',') + '%';

    if (valor <= 5) {
        el.innerHTML = `<span class="badge badge-desvio bg-success">${formatado} <i class="bi bi-check-circle"></i> OK</span>`;
    } else {
        el.innerHTML = `<span class="badge badge-desvio bg-danger desvio-alerta">${formatado} <i class="bi bi-exclamation-triangle"></i> ACIMA DO LIMITE (5%)</span>`;
    }
}

/**
 * Calcula Im (corrente média), DM (desvio máximo), DI (desvio percentual)
 * Para a seção de cálculos auxiliares
 */
function calcularImDmDi(prefixo) {
    const ia = parseFloat(document.getElementById(prefixo + '_ia')?.value) || 0;
    const ib = parseFloat(document.getElementById(prefixo + '_ib')?.value) || 0;
    const ic = parseFloat(document.getElementById(prefixo + '_ic')?.value) || 0;

    if (ia === 0 && ib === 0 && ic === 0) return;

    const im = (ia + ib + ic) / 3;
    const dm = Math.max(ia, ib, ic) - Math.min(ia, ib, ic);
    const di = im !== 0 ? (dm / im * 100) : 0;

    const elIm = document.getElementById(prefixo + '_im');
    const elDm = document.getElementById(prefixo + '_dm');
    const elDi = document.getElementById(prefixo + '_di');

    if (elIm) elIm.value = im.toFixed(1);
    if (elDm) elDm.value = dm.toFixed(1);
    if (elDi) {
        elDi.value = di.toFixed(1);
        // Colorir com base no limite de 5%
        if (di <= 5) {
            elDi.classList.remove('text-danger');
            elDi.classList.add('text-success');
        } else {
            elDi.classList.remove('text-success');
            elDi.classList.add('text-danger');
        }
    }
}

// ================================================================
// VIBRAÇÃO - GESTÃO VISUAL
// ================================================================

/**
 * Verifica o valor de vibração e aplica gestão visual
 * Valores acima de 4,2 mm/s: background vermelho + confirmação
 * 
 * Classificação ISO 10816-7:
 * A: 0,1 - 3,2 (OK - Novo)
 * B: 3,2 - 4,2 (OK - Normal)
 * C: 4,2 - 6,1 (Alerta - Restrito)
 * D: > 6,1 (Perigo - Desligar)
 */
function verificarVibracao(input) {
    const valor = parseFloat(input.value) || 0;

    // Remover todas as classes de vibracao
    input.classList.remove('vibracao-ok', 'vibracao-atencao', 'vibracao-alerta', 'vibracao-perigo');

    if (valor === 0 || input.value === '') return;

    if (valor <= 3.2) {
        input.classList.add('vibracao-ok');
    } else if (valor <= 4.2) {
        input.classList.add('vibracao-atencao');
    } else if (valor <= 6.1) {
        input.classList.add('vibracao-alerta');
        confirmarVibracao(input, valor);
    } else {
        input.classList.add('vibracao-perigo');
        confirmarVibracao(input, valor);
    }

    // Recalcular classificação geral
    recalcularClassificacaoVibracao();
}

/**
 * Mostra confirmação quando vibração está acima de 4,2 mm/s
 */
function confirmarVibracao(input, valor) {
    const classificacao = valor > 6.1 ? 'D - RISCO DE DANOS' : 'C - TRABALHO RESTRITO';
    const corFundo = valor > 6.1 ? '#ffcdd2' : '#ffe0b2';
    
    // Usar modal Bootstrap em vez de confirm() para melhor UX
    const modalId = 'modalVibracao';
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        const modalHTML = `
        <div class="modal fade modal-vibracao" id="${modalId}" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>⚠️ VIBRAÇÃO FORA DO LIMITE!</h5>
                    </div>
                    <div class="modal-body" id="modalVibracaoBody">
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary btn-industrial" id="btnVibracaoCancelar">
                            <i class="bi bi-x-circle"></i> Cancelar e Corrigir
                        </button>
                        <button type="button" class="btn btn-danger btn-industrial" id="btnVibracaoConfirmar">
                            <i class="bi bi-check-circle"></i> Registrar Mesmo Assim
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        modal = document.getElementById(modalId);
    }

    document.getElementById('modalVibracaoBody').innerHTML = `
        <p style="font-size: 1.2rem;">O valor <strong>${valor.toFixed(2).replace('.', ',')} mm/s</strong> está <strong>ACIMA do limite de 4,2 mm/s</strong>.</p>
        <p>Classificação: <strong class="text-danger">${classificacao}</strong></p>
        <p class="mt-3">Deseja realmente registrar este valor?</p>
    `;

    const bsModal = new bootstrap.Modal(modal);
    
    document.getElementById('btnVibracaoCancelar').onclick = () => {
        input.value = '';
        input.classList.remove('vibracao-alerta', 'vibracao-perigo');
        input.focus();
        bsModal.hide();
        recalcularClassificacaoVibracao();
    };

    document.getElementById('btnVibracaoConfirmar').onclick = () => {
        bsModal.hide();
        // Valor mantido, classe visual permanece
    };

    bsModal.show();
}

/**
 * Recalcula a classificação geral de vibração com base em todos os valores
 */
function recalcularClassificacaoVibracao() {
    const campos = ['vibracao_x1', 'vibracao_y1', 'vibracao_z1',
                     'vibracao_x2', 'vibracao_y2', 'vibracao_z2',
                     'vibracao_x3', 'vibracao_y3', 'vibracao_z3'];

    let maxValor = 0;
    campos.forEach(campo => {
        const el = document.getElementById(campo);
        if (el) {
            const val = parseFloat(el.value) || 0;
            if (val > maxValor) maxValor = val;
        }
    });

    let classificacao = 'A';
    if (maxValor > 6.1) classificacao = 'D';
    else if (maxValor > 4.2) classificacao = 'C';
    else if (maxValor > 3.2) classificacao = 'B';

    atualizarClassificacaoVibracao(classificacao);

    // Atualizar campo hidden
    const hidden = document.getElementById('classificacao_vibracao');
    if (hidden) hidden.value = classificacao;
}

/**
 * Atualiza a exibição visual da classificação
 */
function atualizarClassificacaoVibracao(classificacao) {
    const el = document.getElementById('classificacaoBox');
    if (!el) return;

    el.className = 'classificacao-box classificacao-' + classificacao;

    const descricoes = {
        'A': 'Equipamento Novo / Recém-comissionado',
        'B': 'Trabalho Normal e Seguro',
        'C': 'Trabalho Restrito - Agendar Manutenção',
        'D': 'RISCO DE DANOS - DESLIGAR EQUIPAMENTO'
    };

    el.innerHTML = `
        <div class="fs-1 fw-bold">${classificacao}</div>
        <div class="fs-7 mt-1">${descricoes[classificacao] || ''}</div>
    `;
}

// ================================================================
// AVANÇAR STATUS
// ================================================================

/**
 * Avança o status da FIM
 */
window.avancarStatus = function(registroId) {
    console.log('--- INICIANDO AVANÇAR STATUS ---');
    console.log('ID do Registro:', registroId);

    // Removendo confirm para evitar bloqueio do navegador
    salvarAjax('actions/alterar_status.php', { registro_id: registroId }, function(data) {
        console.log('RESPOSTA DO SERVIDOR (Avançar):', data);
        if (data.sucesso) {
            mostrarToast('Status atualizado com sucesso!', 'sucesso');
            setTimeout(() => {
                window.location.href = 'formulario.php?id=' + registroId;
            }, 1000);
        } else {
            console.error('ERRO NO SERVIDOR:', data.mensagem);
            alert('Atenção: ' + data.mensagem);
        }
    });
}

/**
 * Volta o status da FIM para a etapa anterior
 */
window.voltarStatus = function(registroId) {
    console.log('--- INICIANDO VOLTAR STATUS ---');
    console.log('ID do Registro:', registroId);
    
    // Removendo o confirm nativo temporariamente para teste de fluxo direto
    const dados = { 
        registro_id: registroId, 
        acao: 'voltar' 
    };
    
    console.log('Dados enviados via AJAX:', dados);

    salvarAjax('actions/alterar_status.php', dados, function(data) {
        console.log('RESPOSTA DO SERVIDOR:', data);
        if (data.sucesso) {
            mostrarToast('Status retornado com sucesso!', 'sucesso');
            setTimeout(() => {
                window.location.href = 'formulario.php?id=' + registroId;
            }, 1000);
        } else {
            console.error('ERRO NO SERVIDOR:', data.mensagem);
            alert('Erro ao voltar status: ' + data.mensagem);
        }
    });
}

/**
 * Formata o nome do status para exibição
 */
function formatarStatus(status) {
    const nomes = {
        'EM_BANCADA': 'Em Bancada',
        'EM_MONTAGEM': 'Em Montagem',
        'AGUARDANDO_CLIENTE': 'Aguardando Cliente',
        'FINALIZADO': 'Finalizado'
    };
    return nomes[status] || status;
}

// ================================================================
// VALIDAÇÃO CLIENT-SIDE
// ================================================================

/**
 * Valida campos obrigatórios antes de avançar status
 */
function validarCamposObrigatorios(secao) {
    const campos = document.querySelectorAll(`#${secao} [required]`);
    let validos = true;
    let primeiroInvalido = null;

    campos.forEach(campo => {
        if (!campo.value || campo.value.trim() === '') {
            campo.classList.add('is-invalid');
            validos = false;
            if (!primeiroInvalido) primeiroInvalido = campo;
        } else {
            campo.classList.remove('is-invalid');
        }
    });

    if (!validos && primeiroInvalido) {
        primeiroInvalido.scrollIntoView({ behavior: 'smooth', block: 'center' });
        mostrarToast('⚠️ Preencha todos os campos obrigatórios destacados em vermelho.', 'erro');
    }

    return validos;
}

// ================================================================
// N/A (NÃO APLICÁVEL) - Toggle para campos opcionais
// ================================================================

/**
 * Toggle N/A para um campo
 */
function toggleNA(inputId) {
    const input = document.getElementById(inputId);
    const btn = document.getElementById('na_' + inputId);

    if (!input || !btn) return;

    if (btn.classList.contains('btn-secondary')) {
        // Ativar N/A
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-info');
        btn.innerHTML = 'N/A ✓';
        input.value = 'N/A';
        input.disabled = true;
        input.classList.add('bg-light');
    } else {
        // Desativar N/A
        btn.classList.remove('btn-info');
        btn.classList.add('btn-secondary');
        btn.innerHTML = 'N/A';
        input.value = '';
        input.disabled = false;
        input.classList.remove('bg-light');
        input.focus();
    }
}

// ================================================================
// INICIALIZAÇÃO
// ================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Configurar inputmode numérico para campos de medição
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.setAttribute('inputmode', 'decimal');
        input.setAttribute('step', 'any');
    });

    // Configurar listeners de vibração
    document.querySelectorAll('.input-vibracao').forEach(input => {
        input.addEventListener('change', function() {
            verificarVibracao(this);
        });
        input.addEventListener('blur', function() {
            verificarVibracao(this);
        });
    });

    // Configurar listeners de corrente (para cálculo de desvio)
    ['corrente_normal_fase_r', 'corrente_normal_fase_s', 'corrente_normal_fase_t'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', calcularDesvioNormal);
            el.addEventListener('change', calcularDesvioNormal);
        }
    });

    ['corrente_carga_fase_r', 'corrente_carga_fase_s', 'corrente_carga_fase_t'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', calcularDesvioCarga);
            el.addEventListener('change', calcularDesvioCarga);
        }
    });

    // Logout handler
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('logout') === '1') {
        window.location.href = 'actions/processar_login.php?logout=1';
    }

    // Calcular desvios iniciais (se campos já preenchidos)
    calcularDesvioNormal();
    calcularDesvioCarga();
    recalcularClassificacaoVibracao();

    // Inicializar inputs de vibração que já têm valor
    document.querySelectorAll('.input-vibracao').forEach(input => {
        if (input.value && parseFloat(input.value) > 0) {
            verificarVibracao(input);
        }
    });

    console.log('FIM Digital v1.0 - Sistema inicializado.');
});
