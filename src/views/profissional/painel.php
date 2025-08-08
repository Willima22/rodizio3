<?php
/**
 * Painel da Profissional
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

use Utils\Auth;
use Utils\Response;
use Utils\CSRF;

// Verificar autenticação e permissão
if (!Auth::check() || !Auth::isProfissional()) {
    Response::redirect('/login');
}

$user = Auth::getUser();
$loginType = Auth::getLoginType();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Profissional - Fast Escova</title>
    <link rel="stylesheet" href="/assets/style.css">
    <?php echo CSRF::metaTag(); ?>
    <style>
        .cronometro {
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
            margin: 2rem 0;
            color: var(--primary-color);
        }
        .atendimento-info {
            background: linear-gradient(135deg, var(--primary-color), #6f42c1);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }
        .status-profissional {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }
        @media (max-width: 768px) {
            .action-buttons {
                grid-template-columns: 1fr;
            }
            .cronometro {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="d-flex align-items-center">
                <a href="/" class="logo">Fast Escova</a>
            </div>
            
            <div class="header-user">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['nome']); ?></div>
                    <div class="user-role">
                        Profissional 
                        <?php if ($loginType === 'nfc'): ?>
                            <span style="color: var(--success-color);">📱 NFC</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-1rem">
                    <button id="chegadaBtn" class="btn btn-success btn-sm d-none">Cheguei</button>
                    <button id="saidaBtn" class="btn btn-warning btn-sm d-none">Sair</button>
                    <a href="/api/auth.php?action=logout" class="btn btn-outline btn-sm">Sair</a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="app-main" style="margin-left: 0;">
            <div id="alert-container"></div>

            <!-- Status Atual -->
            <div class="card">
                <div class="card-body text-center">
                    <div id="statusProfissional" class="status-profissional">
                        Carregando...
                    </div>
                    <h2 id="mensagemStatus">Carregando dados...</h2>
                </div>
            </div>

            <!-- Atendimento Atual -->
            <div id="atendimentoAtual" class="d-none">
                <div class="atendimento-info">
                    <div class="text-center">
                        <h3>Atendimento em Andamento</h3>
                        <div class="cronometro" id="cronometro">00:00</div>
                        <div style="font-size: 1.2rem;">
                            <strong id="clienteNome">-</strong><br>
                            <span id="servicoNome">-</span>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <button id="finalizarBtn" class="btn btn-success btn-lg">
                        ✅ Finalizar Atendimento
                    </button>
                    <button id="pausarBtn" class="btn btn-warning btn-lg" disabled>
                        ⏸️ Pausar Atendimento
                    </button>
                </div>
            </div>

            <!-- Aguardando Atendimento -->
            <div id="aguardandoContainer" class="d-none">
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">😊</div>
                        <h3>Aguardando próximo atendimento</h3>
                        <p class="text-muted">Você será notificada quando um cliente for direcionado para você.</p>
                    </div>
                </div>
            </div>

            <!-- Estatísticas do Dia -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-value" id="atendimentosHoje">0</div>
                    <div class="stat-label">Atendimentos Hoje</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-value" id="finalizadosHoje">0</div>
                    <div class="stat-label">Finalizados Hoje</div>
                </div>
                <div class="stat-card stat-info">
                    <div class="stat-value" id="tempoMedio">0 min</div>
                    <div class="stat-label">Tempo Médio</div>
                </div>
                <div class="stat-card stat-warning">
                    <div class="stat-value" id="minhaFila">0</div>
                    <div class="stat-label">Posição na Fila</div>
                </div>
            </div>

            <!-- Histórico do Dia -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Histórico do Dia</h3>
                    <button id="refreshHistoricoBtn" class="btn btn-outline btn-sm">🔄</button>
                </div>
                <div class="card-body">
                    <div id="historico-container">
                        <div class="text-center">
                            <div class="loading"></div>
                            <p>Carregando histórico...</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de Finalização -->
    <div id="finalizacaoModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Finalizar Atendimento</h3>
                <button id="closeFinalizacaoModal" class="btn btn-sm">✕</button>
            </div>
            <div class="modal-body">
                <form id="finalizacaoForm">
                    <?php echo CSRF::inputField(); ?>
                    <input type="hidden" id="atendimentoIdFinalizar" name="atendimento_id">
                    
                    <div class="form-group">
                        <label for="valorCobrado">Valor Cobrado (opcional)</label>
                        <input 
                            type="text" 
                            id="valorCobrado" 
                            name="valor_cobrado" 
                            class="form-control" 
                            placeholder="R$ 0,00"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="observacoes">Observações (opcional)</label>
                        <textarea 
                            id="observacoes" 
                            name="observacoes" 
                            class="form-control" 
                            rows="3"
                            placeholder="Observações sobre o atendimento..."
                        ></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" id="cancelarFinalizacao" class="btn btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <span id="finalizarBtnText">Finalizar</span>
                            <span id="finalizarLoading" class="loading d-none"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Configurações globais
        window.APP = {
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            baseUrl: '',
            dados: {},
            cronometroInterval: null,
            pollingInterval: null
        };

        document.addEventListener('DOMContentLoaded', function() {
            initPainelProfissional();
        });

        function initPainelProfissional() {
            // Carregar dados iniciais
            carregarDadosProfissional();
            
            // Configurar polling a cada 15 segundos
            window.APP.pollingInterval = setInterval(carregarDadosProfissional, 15000);
            
            // Event listeners
            document.getElementById('finalizarBtn').addEventListener('click', abrirModalFinalizacao);
            document.getElementById('chegadaBtn').addEventListener('click', registrarChegada);
            document.getElementById('saidaBtn').addEventListener('click', registrarSaida);
            document.getElementById('refreshHistoricoBtn').addEventListener('click', carregarHistorico);
            
            initModalFinalizacao();
        }

        async function carregarDadosProfissional() {
            try {
                const response = await fetch('/api/profissionais.php?action=meus_dados');
                const data = await response.json();
                
                if (data.ok) {
                    window.APP.dados = data.data;
                    renderizarInterface();
                } else {
                    showNotification('Erro ao carregar dados', 'error');
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                showNotification('Erro de comunicação', 'error');
            }
        }

        function renderizarInterface() {
            const dados = window.APP.dados;
            
            // Status da profissional
            renderizarStatus(dados);
            
            // Atendimento atual ou aguardando
            if (dados.status === 'atendendo' && dados.atendimento_atual) {
                mostrarAtendimentoAtual(dados.atendimento_atual);
            } else {
                mostrarAguardando();
            }
            
            // Estatísticas
            renderizarEstatisticas(dados);
            
            // Histórico
            carregarHistorico();
        }

        function renderizarStatus(dados) {
            const statusElement = document.getElementById('statusProfissional');
            const mensagemElement = document.getElementById('mensagemStatus');
            const chegadaBtn = document.getElementById('chegadaBtn');
            const saidaBtn = document.getElementById('saidaBtn');
            
            // Limpar classes
            statusElement.className = 'status-profissional';
            
            switch (dados.status) {
                case 'livre':
                    statusElement.classList.add('status-livre');
                    statusElement.textContent = 'LIVRE';
                    mensagemElement.textContent = 'Você está disponível para atendimentos';
                    saidaBtn.classList.remove('d-none');
                    chegadaBtn.classList.add('d-none');
                    break;
                    
                case 'atendendo':
                    statusElement.classList.add('status-atendendo');
                    statusElement.textContent = 'ATENDENDO';
                    mensagemElement.textContent = 'Você está realizando um atendimento';
                    saidaBtn.classList.add('d-none');
                    chegadaBtn.classList.add('d-none');
                    break;
                    
                case 'ausente':
                    statusElement.classList.add('status-ausente');
                    statusElement.textContent = 'AUSENTE';
                    mensagemElement.textContent = 'Clique em "Cheguei" para ficar disponível';
                    chegadaBtn.classList.remove('d-none');
                    saidaBtn.classList.add('d-none');
                    break;
            }
        }

        function mostrarAtendimentoAtual(atendimento) {
            document.getElementById('atendimentoAtual').classList.remove('d-none');
            document.getElementById('aguardandoContainer').classList.add('d-none');
            
            // Dados do atendimento
            document.getElementById('clienteNome').textContent = atendimento.cliente_nome;
            document.getElementById('servicoNome').textContent = atendimento.servico_nome;
            document.getElementById('atendimentoIdFinalizar').value = atendimento.id;
            
            // Iniciar cronômetro
            iniciarCronometro(atendimento.hora_inicio);
        }

        function mostrarAguardando() {
            document.getElementById('atendimentoAtual').classList.add('d-none');
            document.getElementById('aguardandoContainer').classList.remove('d-none');
            
            // Parar cronômetro
            pararCronometro();
        }

        function iniciarCronometro(horaInicio) {
            if (window.APP.cronometroInterval) {
                clearInterval(window.APP.cronometroInterval);
            }
            
            const inicio = new Date(horaInicio).getTime();
            
            window.APP.cronometroInterval = setInterval(() => {
                const agora = new Date().getTime();
                const duracao = Math.floor((agora - inicio) / 1000);
                
                const horas = Math.floor(duracao / 3600);
                const minutos = Math.floor((duracao % 3600) / 60);
                const segundos = duracao % 60;
                
                const cronometroText = horas > 0 
                    ? `${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`
                    : `${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;
                
                document.getElementById('cronometro').textContent = cronometroText;
            }, 1000);
        }

        function pararCronometro() {
            if (window.APP.cronometroInterval) {
                clearInterval(window.APP.cronometroInterval);
                window.APP.cronometroInterval = null;
            }
            document.getElementById('cronometro').textContent = '00:00';
        }

        function renderizarEstatisticas(dados) {
            const estatisticas = dados.estatisticas_mes || {};
            
            document.getElementById('atendimentosHoje').textContent = dados.total_atendimentos_dia || 0;
            document.getElementById('finalizadosHoje').textContent = estatisticas.finalizados || 0;
            document.getElementById('tempoMedio').textContent = 
                estatisticas.tempo_medio_atendimento ? `${Math.round(estatisticas.tempo_medio_atendimento)} min` : '0 min';
            document.getElementById('minhaFila').textContent = dados.ordem_chegada || 0;
        }

        async function carregarHistorico() {
            try {
                const response = await fetch(`/api/profissionais.php?action=meus_dados`);
                const data = await response.json();
                
                if (data.ok && data.data.historico_dia) {
                    renderizarHistorico(data.data.historico_dia);
                }
            } catch (error) {
                console.error('Erro ao carregar histórico:', error);
            }
        }

        function renderizarHistorico(historico) {
            const container = document.getElementById('historico-container');
            
            if (historico.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Nenhum atendimento hoje</p>';
                return;
            }
            
            const historicoHtml = historico.map(item => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2" 
                     style="background: #f8f9fa; border-radius: 4px;">
                    <div>
                        <strong>${item.cliente_nome}</strong><br>
                        <small class="text-muted">${item.servico_nome}</small>
                    </div>
                    <div class="text-right">
                        <span class="badge status-${item.status}">${item.status}</span><br>
                        ${item.duracao_minutos ? `<small>${item.duracao_minutos} min</small>` : ''}
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = historicoHtml;
        }

        async function registrarChegada() {
            const btn = document.getElementById('chegadaBtn');
            const originalText = btn.innerHTML;
            
            try {
                btn.disabled = true;
                btn.innerHTML = '<span class="loading"></span>';
                
                const response = await fetch('/api/profissionais.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=chegada&${window.APP.csrfToken}=${encodeURIComponent(window.APP.csrfToken)}`
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    showNotification('Chegada registrada com sucesso!', 'success');
                    carregarDadosProfissional();
                } else {
                    showNotification(data.error || 'Erro ao registrar chegada', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification('Erro de comunicação', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        async function registrarSaida() {
            if (!confirm('Tem certeza que deseja registrar sua saída?')) {
                return;
            }
            
            const btn = document.getElementById('saidaBtn');
            const originalText = btn.innerHTML;
            
            try {
                btn.disabled = true;
                btn.innerHTML = '<span class="loading"></span>';
                
                const response = await fetch('/api/profissionais.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=saida&${window.APP.csrfToken}=${encodeURIComponent(window.APP.csrfToken)}`
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    showNotification('Saída registrada com sucesso!', 'success');
                    carregarDadosProfissional();
                } else {
                    showNotification(data.error || 'Erro ao registrar saída', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification('Erro de comunicação', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Modal de finalização
        function initModalFinalizacao() {
            const modal = document.getElementById('finalizacaoModal');
            const form = document.getElementById('finalizacaoForm');
            
            document.getElementById('closeFinalizacaoModal').addEventListener('click', fecharModalFinalizacao);
            document.getElementById('cancelarFinalizacao').addEventListener('click', fecharModalFinalizacao);
            
            form.addEventListener('submit', finalizarAtendimento);
            
            // Formatação do valor
            document.getElementById('valorCobrado').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = (value / 100).toFixed(2);
                value = value.replace('.', ',');
                value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                e.target.value = value ? 'R$ ' + value : '';
            });
        }

        function abrirModalFinalizacao() {
            document.getElementById('finalizacaoModal').style.display = 'flex';
        }

        function fecharModalFinalizacao() {
            document.getElementById('finalizacaoModal').style.display = 'none';
            document.getElementById('finalizacaoForm').reset();
        }

        async function finalizarAtendimento(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'finalizar');
            
            const btnText = document.getElementById('finalizarBtnText');
            const btnLoading = document.getElementById('finalizarLoading');
            
            try {
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
                
                const response = await fetch('/api/atendimentos.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    showNotification('Atendimento finalizado com sucesso!', 'success');
                    fecharModalFinalizacao();
                    carregarDadosProfissional();
                } else {
                    showNotification(data.error || 'Erro ao finalizar atendimento', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showNotification('Erro de comunicação', 'error');
            } finally {
                btnText.classList.remove('d-none');
                btnLoading.classList.add('d-none');
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 4000);
        }

        // CSS para modal
        const modalCSS = `
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            }
            .modal-content {
                background: white;
                border-radius: 8px;
                max-width: 500px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
            }
            .modal-header {
                padding: 1rem 1.5rem;
                border-bottom: 1px solid #dee2e6;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .modal-body {
                padding: 1.5rem;
            }
        `;
        
        const style = document.createElement('style');
        style.textContent = modalCSS;
        document.head.appendChild(style);

        // Cleanup ao sair da página
        window.addEventListener('beforeunload', function() {
            if (window.APP.pollingInterval) {
                clearInterval(window.APP.pollingInterval);
            }
            if (window.APP.cronometroInterval) {
                clearInterval(window.APP.cronometroInterval);
            }
        });
    </script>
</body>
</html>