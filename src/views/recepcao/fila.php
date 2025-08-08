<?php
/**
 * Página da Fila de Espera
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

use Utils\Auth;
use Utils\Response;
use Utils\CSRF;

// Verificar autenticação e permissão
if (!Auth::check() || !Auth::isRecepcao()) {
    Response::redirect('/login');
}

$user = Auth::getUser();
$isGestor = Auth::isGestor();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fila de Espera - Fast Escova</title>
    <link rel="stylesheet" href="/assets/style.css">
    <?php echo CSRF::metaTag(); ?>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="d-flex align-items-center">
                <button id="sidebarToggle" class="btn btn-outline" style="margin-right: 1rem;">
                    ☰
                </button>
                <a href="/" class="logo">Fast Escova</a>
            </div>
            
            <div class="header-user">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['nome']); ?></div>
                    <div class="user-role"><?php echo ucfirst($user['perfil']); ?></div>
                </div>
                <a href="/api/auth.php?action=logout" class="btn btn-outline btn-sm">Sair</a>
            </div>
        </header>

        <!-- Sidebar -->
        <nav class="app-sidebar" id="sidebar">
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="/recepcao/fila" class="nav-link active">
                        <span class="nav-icon">👥</span>
                        Fila de Espera
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/recepcao/cadastro" class="nav-link">
                        <span class="nav-icon">➕</span>
                        Cadastrar Cliente
                    </a>
                </li>
                <?php if ($isGestor): ?>
                <li class="nav-item">
                    <a href="/gestor/dashboard" class="nav-link">
                        <span class="nav-icon">📊</span>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/gestor/profissionais" class="nav-link">
                        <span class="nav-icon">👩</span>
                        Profissionais
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="app-main">
            <!-- Cards de Resumo -->
            <div class="stats-grid">
                <div class="stat-card stat-warning">
                    <div class="stat-value" id="totalFila">-</div>
                    <div class="stat-label">Na Fila</div>
                </div>
                <div class="stat-card stat-info">
                    <div class="stat-value" id="totalAtendendo">-</div>
                    <div class="stat-label">Atendendo</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-value" id="profissionaisLivres">-</div>
                    <div class="stat-label">Profissionais Livres</div>
                </div>
                <div class="stat-card stat-primary">
                    <div class="stat-value" id="atendimentosHoje">-</div>
                    <div class="stat-label">Atendimentos Hoje</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Fila de Espera -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Fila de Espera</h2>
                        <div class="d-flex align-items-center gap-1rem">
                            <?php if ($isGestor): ?>
                            <button id="distribuirAutoBtn" class="btn btn-success btn-sm">
                                ⚡ Distribuir Automático
                            </button>
                            <?php endif; ?>
                            <button id="refreshFilaBtn" class="btn btn-outline btn-sm">
                                🔄
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="fila-container">
                            <div class="text-center">
                                <div class="loading"></div>
                                <p>Carregando fila...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profissionais -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Status das Profissionais</h2>
                        <button id="refreshProfBtn" class="btn btn-outline btn-sm">
                            🔄
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="profissionais-container">
                            <div class="text-center">
                                <div class="loading"></div>
                                <p>Carregando profissionais...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Atendimentos em Andamento -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Atendimentos em Andamento</h2>
                    <button id="refreshAndamentoBtn" class="btn btn-outline btn-sm">
                        🔄
                    </button>
                </div>
                <div class="card-body">
                    <div id="andamento-container">
                        <div class="text-center">
                            <div class="loading"></div>
                            <p>Carregando atendimentos...</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de Distribuição Manual -->
    <?php if ($isGestor): ?>
    <div id="distribuicaoModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Distribuição Manual</h3>
                <button id="closeModal" class="btn btn-sm">✕</button>
            </div>
            <div class="modal-body">
                <form id="distribuicaoForm">
                    <?php echo CSRF::inputField(); ?>
                    
                    <div class="form-group">
                        <label>Cliente:</label>
                        <div id="clienteInfo" class="p-2" style="background: #f8f9fa; border-radius: 4px;">
                            <strong id="clienteNome">-</strong><br>
                            <small id="clienteServico" class="text-muted">-</small>
                        </div>
                        <input type="hidden" id="atendimentoId" name="atendimento_id">
                    </div>
                    
                    <div class="form-group">
                        <label for="profissionalSelect">Profissional:</label>
                        <select id="profissionalSelect" name="profissional_id" class="form-control" required>
                            <option value="">Selecione a profissional...</option>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" id="cancelarModal" class="btn btn-secondary">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Distribuir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Configurações globais
        window.APP = {
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            baseUrl: '',
            isGestor: <?php echo $isGestor ? 'true' : 'false'; ?>,
            pollingInterval: null
        };

        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');

            // Toggle sidebar
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('sidebar-hidden');
            });

            // Inicializar sistema de fila
            initFilaSystem();
        });

        function initFilaSystem() {
            // Carregar dados iniciais
            carregarTodosDados();
            
            // Configurar polling a cada 12 segundos
            window.APP.pollingInterval = setInterval(carregarTodosDados, 12000);
            
            // Event listeners
            document.getElementById('refreshFilaBtn').addEventListener('click', carregarFila);
            document.getElementById('refreshProfBtn').addEventListener('click', carregarProfissionais);
            document.getElementById('refreshAndamentoBtn').addEventListener('click', carregarAtendimentosAndamento);
            
            if (window.APP.isGestor) {
                document.getElementById('distribuirAutoBtn').addEventListener('click', distribuirAutomatico);
                initDistribuicaoModal();
            }
        }

        async function carregarTodosDados() {
            await Promise.all([
                carregarFila(),
                carregarProfissionais(),
                carregarAtendimentosAndamento(),
                carregarMetricasHoje()
            ]);
        }

        async function carregarFila() {
            try {
                const response = await fetch('/api/clientes.php?action=fila');
                const data = await response.json();
                
                if (data.ok) {
                    renderFila(data.data.fila);
                    document.getElementById('totalFila').textContent = data.data.total_fila;
                }
            } catch (error) {
                console.error('Erro ao carregar fila:', error);
            }
        }

        function renderFila(fila) {
            const container = document.getElementById('fila-container');
            
            if (fila.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Nenhum cliente na fila</p>';
                return;
            }
            
            const filaHtml = fila.map((item, index) => `
                <div class="fila-item ${index === 0 ? 'fila-proximo' : ''}">
                    <div class="fila-info">
                        <div class="cliente-nome">${item.cliente_nome}</div>
                        <div class="servico-info">
                            ${item.servico_nome} - R$ ${parseFloat(item.servico_preco).toFixed(2)}
                            ${item.cliente_telefone ? ` • ${item.cliente_telefone}` : ''}
                        </div>
                    </div>
                    <div>
                        <div class="tempo-espera">${item.tempo_espera_formatado}</div>
                        ${window.APP.isGestor ? `
                            <button onclick="abrirDistribuicaoModal('${item.id}', '${item.cliente_nome}', '${item.servico_nome}')" 
                                    class="btn btn-sm btn-primary mt-1">
                                Distribuir
                            </button>
                        ` : ''}
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = filaHtml;
        }

        async function carregarProfissionais() {
            try {
                const response = await fetch('/api/profissionais.php?action=status');
                const data = await response.json();
                
                if (data.ok) {
                    renderProfissionais(data.data.profissionais);
                    document.getElementById('profissionaisLivres').textContent = data.data.resumo.livres;
                    document.getElementById('totalAtendendo').textContent = data.data.resumo.atendendo;
                }
            } catch (error) {
                console.error('Erro ao carregar profissionais:', error);
            }
        }

        function renderProfissionais(profissionais) {
            const container = document.getElementById('profissionais-container');
            
            if (profissionais.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Nenhuma profissional cadastrada</p>';
                return;
            }
            
            const profHtml = profissionais.map(prof => {
                let statusInfo = '';
                if (prof.atendimento_atual) {
                    statusInfo = `
                        <small class="text-muted">
                            ${prof.atendimento_atual.cliente_nome} • ${prof.atendimento_atual.duracao_minutos} min
                        </small>
                    `;
                }
                
                return `
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2" 
                         style="background: #f8f9fa; border-radius: 4px;">
                        <div>
                            <strong>${prof.nome}</strong>
                            ${statusInfo}
                        </div>
                        <div class="text-right">
                            <span class="badge status-${prof.status}">${prof.status_label}</span>
                            ${prof.status === 'livre' ? `<br><small>Atend. hoje: ${prof.total_atendimentos_dia}</small>` : ''}
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = profHtml;
        }

        async function carregarAtendimentosAndamento() {
            try {
                const response = await fetch('/api/atendimentos.php?action=em_andamento');
                const data = await response.json();
                
                if (data.ok) {
                    renderAtendimentosAndamento(data.data);
                }
            } catch (error) {
                console.error('Erro ao carregar atendimentos:', error);
            }
        }

        function renderAtendimentosAndamento(atendimentos) {
            const container = document.getElementById('andamento-container');
            
            if (atendimentos.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Nenhum atendimento em andamento</p>';
                return;
            }
            
            const atendHtml = atendimentos.map(item => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2" 
                     style="background: #f8f9fa; border-radius: 4px;">
                    <div>
                        <strong>${item.cliente_nome}</strong> • ${item.profissional_nome}<br>
                        <small class="text-muted">${item.servico_nome}</small>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-info">${item.duracao_formatada}</span>
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = atendHtml;
        }

        async function carregarMetricasHoje() {
            try {
                const response = await fetch('/api/atendimentos.php?action=metricas_dia');
                const data = await response.json();
                
                if (data.ok) {
                    const metricas = data.data;
                    document.getElementById('atendimentosHoje').textContent = metricas.total_atendimentos;
                }
            } catch (error) {
                console.error('Erro ao carregar métricas:', error);
            }
        }

        async function distribuirAutomatico() {
            const btn = document.getElementById('distribuirAutoBtn');
            const originalText = btn.innerHTML;
            
            try {
                btn.disabled = true;
                btn.innerHTML = '<span class="loading"></span> Distribuindo...';
                
                const response = await fetch('/api/atendimentos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=distribuir_auto&${window.APP.csrfToken}=${encodeURIComponent(window.APP.csrfToken)}`
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    showNotification('Cliente distribuído automaticamente!', 'success');
                    carregarTodosDados();
                } else {
                    showNotification(data.error || 'Erro na distribuição automática', 'error');
                }
            } catch (error) {
                console.error('Erro na distribuição:', error);
                showNotification('Erro de comunicação', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Modal de distribuição manual (apenas para gestores)
        function initDistribuicaoModal() {
            const modal = document.getElementById('distribuicaoModal');
            const form = document.getElementById('distribuicaoForm');
            
            document.getElementById('closeModal').addEventListener('click', fecharModal);
            document.getElementById('cancelarModal').addEventListener('click', fecharModal);
            
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                await distribuirManual();
            });
        }

        function abrirDistribuicaoModal(atendimentoId, clienteNome, servicoNome) {
            document.getElementById('atendimentoId').value = atendimentoId;
            document.getElementById('clienteNome').textContent = clienteNome;
            document.getElementById('clienteServico').textContent = servicoNome;
            
            // Carregar profissionais livres
            carregarProfissionaisLivres();
            
            document.getElementById('distribuicaoModal').style.display = 'flex';
        }

        function fecharModal() {
            document.getElementById('distribuicaoModal').style.display = 'none';
            document.getElementById('distribuicaoForm').reset();
        }

        async function carregarProfissionaisLivres() {
            try {
                const response = await fetch('/api/profissionais.php?action=status');
                const data = await response.json();
                
                if (data.ok) {
                    const select = document.getElementById('profissionalSelect');
                    select.innerHTML = '<option value="">Selecione a profissional...</option>';
                    
                    data.data.por_status.livres.forEach(prof => {
                        const option = document.createElement('option');
                        option.value = prof.id;
                        option.textContent = `${prof.nome} (${prof.total_atendimentos_dia} atendimentos hoje)`;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar profissionais:', error);
            }
        }

        async function distribuirManual() {
            const form = document.getElementById('distribuicaoForm');
            const formData = new FormData(form);
            formData.append('action', 'forcar');
            
            try {
                const response = await fetch('/api/atendimentos.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    showNotification('Distribuição manual realizada com sucesso!', 'success');
                    fecharModal();
                    carregarTodosDados();
                } else {
                    showNotification(data.error || 'Erro na distribuição manual', 'error');
                }
            } catch (error) {
                console.error('Erro na distribuição:', error);
                showNotification('Erro de comunicação', 'error');
            }
        }

        function showNotification(message, type = 'info') {
            // Criar notificação simples
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
        });
    </script>
</body>
</html>