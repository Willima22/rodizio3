<?php
session_start();
require_once __DIR__ . '/../../Utils/Auth.php';
require_once __DIR__ . '/../../Utils/CSRF.php';

// Verificar autenticação e permissão de gestor/admin
if (!Auth::check() || !in_array(Auth::getPerfilNome(), ['administrador', 'gestor'])) {
    header('Location: /auth/login');
    exit;
}

$csrfToken = CSRF::generate();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Serviços - Fast Escova</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <h1>🛀 Gerenciar Serviços</h1>
                <div class="user-info">
                    <span><?= htmlspecialchars(Auth::getNome()) ?></span>
                    <span class="badge badge-gestor"><?= htmlspecialchars(Auth::getPerfilNome()) ?></span>
                    <a href="/auth/logout" class="btn-logout">Sair</a>
                </div>
            </div>
        </header>

        <!-- Navigation -->
        <nav class="nav-tabs">
            <a href="/gestor/dashboard" class="nav-link">Dashboard</a>
            <a href="/gestor/profissionais" class="nav-link">Profissionais</a>
            <a href="/gestor/servicos" class="nav-link active">Serviços</a>
            <a href="/gestor/relatorios" class="nav-link">Relatórios</a>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Actions Bar -->
            <section class="actions-bar">
                <div class="actions-left">
                    <button type="button" id="btnNovoServico" class="btn btn-primary">
                        ➕ Novo Serviço
                    </button>
                    <button type="button" id="btnAtualizar" class="btn btn-secondary">
                        🔄 Atualizar Lista
                    </button>
                </div>
                <div class="actions-right">
                    <div class="search-box">
                        <input type="text" id="filtroNome" placeholder="Buscar serviço..." class="form-control">
                        <button type="button" id="btnLimparFiltro" class="btn btn-sm btn-light">✖️</button>
                    </div>
                    <select id="filtroStatus" class="form-control">
                        <option value="">Todos os status</option>
                        <option value="1">Ativos</option>
                        <option value="0">Inativos</option>
                    </select>
                </div>
            </section>

            <!-- Estatísticas Rápidas -->
            <section class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-content">
                            <div class="stat-value" id="totalServicos">-</div>
                            <div class="stat-label">Total de Serviços</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-content">
                            <div class="stat-value" id="servicosAtivos">-</div>
                            <div class="stat-label">Serviços Ativos</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-content">
                            <div class="stat-value" id="precoMedio">-</div>
                            <div class="stat-label">Preço Médio</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-content">
                            <div class="stat-value" id="servicoMaisProcurado">-</div>
                            <div class="stat-label">Mais Procurado</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Lista de Serviços -->
            <section class="services-section">
                <div class="card">
                    <div class="card-header">
                        <h3>📋 Lista de Serviços</h3>
                        <div class="card-actions">
                            <span id="totalResultados" class="text-muted">0 serviços encontrados</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="tabelaServicos">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Preço</th>
                                        <th>Tempo Estimado</th>
                                        <th>Status</th>
                                        <th>Atendimentos (Mês)</th>
                                        <th>Faturamento (Mês)</th>
                                        <th>Criado em</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="servicosBody">
                                    <tr>
                                        <td colspan="8" class="text-center loading-row">
                                            <div class="loading-text">
                                                <div class="spinner-small"></div>
                                                Carregando serviços...
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal de Serviço -->
    <div id="modalServico" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">➕ Novo Serviço</h3>
                <button type="button" class="modal-close" id="btnFecharModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="formServico">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="id" id="servicoId">
                    
                    <div class="form-group">
                        <label for="nome" class="required">Nome do Serviço:</label>
                        <input type="text" id="nome" name="nome" class="form-control" 
                               placeholder="Ex: Escova Simples" required maxlength="255">
                        <div class="form-feedback" id="feedbackNome"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-6">
                            <label for="preco" class="required">Preço (R$):</label>
                            <input type="number" id="preco" name="preco" class="form-control" 
                                   step="0.01" min="0" max="9999.99" placeholder="0,00" required>
                            <div class="form-feedback" id="feedbackPreco"></div>
                        </div>
                        <div class="form-group col-6">
                            <label for="tempo_estimado" class="required">Tempo Estimado:</label>
                            <input type="time" id="tempo_estimado" name="tempo_estimado" class="form-control" required>
                            <div class="form-feedback" id="feedbackTempo"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ativo">Status:</label>
                        <div class="form-toggle">
                            <input type="checkbox" id="ativo" name="ativo" value="1" checked>
                            <label for="ativo" class="toggle-label">
                                <span class="toggle-switch"></span>
                                <span class="toggle-text">Serviço Ativo</span>
                            </label>
                        </div>
                        <small class="form-text">Serviços inativos não aparecem no cadastro de atendimentos</small>
                    </div>

                    <div class="form-group">
                        <label for="descricao">Descrição (opcional):</label>
                        <textarea id="descricao" name="descricao" class="form-control" 
                                  rows="3" maxlength="500" placeholder="Descrição detalhada do serviço..."></textarea>
                        <div class="char-counter">
                            <span id="descricaoCounter">0</span>/500 caracteres
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCancelar" class="btn btn-secondary">Cancelar</button>
                <button type="button" id="btnSalvar" class="btn btn-primary">
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner-small"></span>
                    </span>
                    <span class="btn-text">Salvar Serviço</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div id="modalConfirmacao" class="modal" style="display: none;">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3 id="confirmTitle">Confirmar Ação</h3>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">Tem certeza que deseja realizar esta ação?</p>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCancelarConfirm" class="btn btn-secondary">Cancelar</button>
                <button type="button" id="btnConfirmar" class="btn btn-danger">Confirmar</button>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toastContainer" class="toast-container"></div>

    <script>
        // Variáveis globais
        let servicosData = [];
        let servicoEditando = null;
        let confirmCallback = null;

        // Inicialização da página
        document.addEventListener('DOMContentLoaded', function() {
            configurarEventos();
            carregarServicos();
            carregarEstatisticas();
        });

        // Configurar todos os eventos
        function configurarEventos() {
            // Botões principais
            document.getElementById('btnNovoServico').addEventListener('click', novoServico);
            document.getElementById('btnAtualizar').addEventListener('click', carregarServicos);
            
            // Filtros
            document.getElementById('filtroNome').addEventListener('input', debounce(filtrarServicos, 300));
            document.getElementById('filtroStatus').addEventListener('change', filtrarServicos);
            document.getElementById('btnLimparFiltro').addEventListener('click', limparFiltros);
            
            // Modal de serviço
            document.getElementById('btnFecharModal').addEventListener('click', fecharModal);
            document.getElementById('btnCancelar').addEventListener('click', fecharModal);
            document.getElementById('btnSalvar').addEventListener('click', salvarServico);
            
            // Modal de confirmação
            document.getElementById('btnCancelarConfirm').addEventListener('click', fecharModalConfirmacao);
            document.getElementById('btnConfirmar').addEventListener('click', executarConfirmacao);
            
            // Formulário
            document.getElementById('formServico').addEventListener('submit', function(e) {
                e.preventDefault();
                salvarServico();
            });
            
            // Contador de caracteres
            document.getElementById('descricao').addEventListener('input', atualizarContador);
            
            // Fechar modais com ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    fecharModal();
                    fecharModalConfirmacao();
                }
            });
        }

        // Carregar lista de serviços
        async function carregarServicos() {
            try {
                showTableLoading(true);
                
                const response = await fetch('/api/servicos.php?action=listar');
                const data = await response.json();
                
                if (data.ok) {
                    servicosData = data.data;
                    renderizarServicos(servicosData);
                    atualizarContadorResultados();
                } else {
                    showToast('Erro ao carregar serviços: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Erro ao carregar serviços:', error);
                showToast('Erro de conexão ao carregar serviços', 'error');
            } finally {
                showTableLoading(false);
            }
        }

        // Renderizar lista de serviços na tabela
        function renderizarServicos(servicos) {
            const tbody = document.getElementById('servicosBody');
            tbody.innerHTML = '';
            
            if (servicos.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center no-data">
                            <div class="no-data-content">
                                <div class="no-data-icon">🔍</div>
                                <p>Nenhum serviço encontrado</p>
                                <button type="button" class="btn btn-primary btn-sm" onclick="novoServico()">
                                    ➕ Adicionar Primeiro Serviço
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            servicos.forEach(servico => {
                const row = document.createElement('tr');
                row.className = servico.ativo == 1 ? 'row-ativo' : 'row-inativo';
                
                row.innerHTML = `
                    <td>
                        <div class="servico-info">
                            <span class="servico-nome">${servico.nome}</span>
                            ${servico.descricao ? `<small class="servico-desc">${servico.descricao}</small>` : ''}
                        </div>
                    </td>
                    <td class="font-weight-bold text-success">
                        ${formatarMoeda(servico.preco)}
                    </td>
                    <td>
                        <span class="badge badge-info">${formatarTempo(servico.tempo_estimado)}</span>
                    </td>
                    <td>
                        <span class="status-badge ${servico.ativo == 1 ? 'status-ativo' : 'status-inativo'}">
                            ${servico.ativo == 1 ? '✅ Ativo' : '❌ Inativo'}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="metric-value">${servico.atendimentos_mes || 0}</span>
                    </td>
                    <td class="text-center text-success">
                        ${formatarMoeda(servico.faturamento_mes || 0)}
                    </td>
                    <td class="text-muted">
                        ${formatarDataHora(servico.criado_em)}
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-primary" 
                                    onclick="editarServico('${servico.id}')" title="Editar">
                                ✏️
                            </button>
                            <button type="button" class="btn btn-sm ${servico.ativo == 1 ? 'btn-warning' : 'btn-success'}" 
                                    onclick="toggleStatusServico('${servico.id}', ${servico.ativo})" 
                                    title="${servico.ativo == 1 ? 'Desativar' : 'Ativar'}">
                                ${servico.ativo == 1 ? '⏸️' : '▶️'}
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="excluirServico('${servico.id}', '${servico.nome}')" title="Excluir">
                                🗑️
                            </button>
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }

        // Carregar estatísticas
        async function carregarEstatisticas() {
            try {
                const response = await fetch('/api/servicos.php?action=estatisticas');
                const data = await response.json();
                
                if (data.ok) {
                    const stats = data.data;
                    
                    document.getElementById('totalServicos').textContent = stats.total || 0;
                    document.getElementById('servicosAtivos').textContent = stats.ativos || 0;
                    document.getElementById('precoMedio').textContent = formatarMoeda(stats.preco_medio || 0);
                    document.getElementById('servicoMaisProcurado').textContent = stats.mais_procurado || 'N/A';
                }
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
            }
        }

        // Filtrar serviços
        function filtrarServicos() {
            const filtroNome = document.getElementById('filtroNome').value.toLowerCase().trim();
            const filtroStatus = document.getElementById('filtroStatus').value;
            
            let servicosFiltrados = servicosData.filter(servico => {
                const matchNome = !filtroNome || 
                    servico.nome.toLowerCase().includes(filtroNome) ||
                    (servico.descricao && servico.descricao.toLowerCase().includes(filtroNome));
                
                const matchStatus = !filtroStatus || servico.ativo == filtroStatus;
                
                return matchNome && matchStatus;
            });
            
            renderizarServicos(servicosFiltrados);
            atualizarContadorResultados(servicosFiltrados.length);
        }

        // Limpar filtros
        function limparFiltros() {
            document.getElementById('filtroNome').value = '';
            document.getElementById('filtroStatus').value = '';
            renderizarServicos(servicosData);
            atualizarContadorResultados();
        }

        // Novo serviço
        function novoServico() {
            servicoEditando = null;
            document.getElementById('modalTitle').textContent = '➕ Novo Serviço';
            document.getElementById('servicoId').value = '';
            document.getElementById('formServico').reset();
            document.getElementById('ativo').checked = true;
            document.getElementById('btnSalvar').querySelector('.btn-text').textContent = 'Salvar Serviço';
            
            limparValidacoes();
            mostrarModal();
        }

        // Editar serviço
        function editarServico(servicoId) {
            const servico = servicosData.find(s => s.id === servicoId);
            if (!servico) {
                showToast('Serviço não encontrado', 'error');
                return;
            }
            
            servicoEditando = servico;
            document.getElementById('modalTitle').textContent = '✏️ Editar Serviço';
            document.getElementById('servicoId').value = servico.id;
            document.getElementById('nome').value = servico.nome;
            document.getElementById('preco').value = servico.preco;
            document.getElementById('tempo_estimado').value = servico.tempo_estimado;
            document.getElementById('ativo').checked = servico.ativo == 1;
            document.getElementById('descricao').value = servico.descricao || '';
            document.getElementById('btnSalvar').querySelector('.btn-text').textContent = 'Salvar Alterações';
            
            atualizarContador();
            limparValidacoes();
            mostrarModal();
        }

        // Salvar serviço
        async function salvarServico() {
            if (!validarFormulario()) {
                return;
            }
            
            try {
                setBtnLoading('btnSalvar', true);
                
                const formData = new FormData(document.getElementById('formServico'));
                const action = servicoEditando ? 'atualizar' : 'criar';
                formData.append('action', action);
                
                const response = await fetch('/api/servicos.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    showToast(
                        servicoEditando ? 'Serviço atualizado com sucesso!' : 'Serviço criado com sucesso!', 
                        'success'
                    );
                    fecharModal();
                    carregarServicos();
                    carregarEstatisticas();
                } else {
                    showToast('Erro ao salvar serviço: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Erro ao salvar serviço:', error);
                showToast('Erro de conexão ao salvar serviço', 'error');
            } finally {
                setBtnLoading('btnSalvar', false);
            }
        }

        // Toggle status do serviço
        function toggleStatusServico(servicoId, statusAtual) {
            const novoStatus = statusAtual == 1 ? 0 : 1;
            const acao = novoStatus ? 'ativar' : 'desativar';
            const servico = servicosData.find(s => s.id === servicoId);
            
            mostrarConfirmacao(
                `${novoStatus ? 'Ativar' : 'Desativar'} Serviço`,
                `Tem certeza que deseja ${acao} o serviço "${servico.nome}"?`,
                async () => {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'toggle_status');
                        formData.append('id', servicoId);
                        formData.append('ativo', novoStatus);
                        formData.append('csrf_token', '<?= $csrfToken ?>');
                        
                        const response = await fetch('/api/servicos.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.ok) {
                            showToast(`Serviço ${acao}do com sucesso!`, 'success');
                            carregarServicos();
                            carregarEstatisticas();
                        } else {
                            showToast('Erro ao alterar status: ' + data.error, 'error');
                        }
                    } catch (error) {
                        console.error('Erro ao alterar status:', error);
                        showToast('Erro de conexão', 'error');
                    }
                }
            );
        }

        // Excluir serviço
        function excluirServico(servicoId, nomeServico) {
            mostrarConfirmacao(
                'Excluir Serviço',
                `Tem certeza que deseja excluir o serviço "${nomeServico}"?\n\nEsta ação não pode ser desfeita e pode afetar atendimentos existentes.`,
                async () => {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'excluir');
                        formData.append('id', servicoId);
                        formData.append('csrf_token', '<?= $csrfToken ?>');
                        
                        const response = await fetch('/api/servicos.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.ok) {
                            showToast('Serviço excluído com sucesso!', 'success');
                            carregarServicos();
                            carregarEstatisticas();
                        } else {
                            showToast('Erro ao excluir serviço: ' + data.error, 'error');
                        }
                    } catch (error) {
                        console.error('Erro ao excluir serviço:', error);
                        showToast('Erro de conexão', 'error');
                    }
                }
            );
        }

        // Validar formulário
        function validarFormulario() {
            let valido = true;
            
            // Validar nome
            const nome = document.getElementById('nome').value.trim();
            if (!nome) {
                mostrarErroValidacao('feedbackNome', 'Nome é obrigatório');
                valido = false;
            } else if (nome.length < 3) {
                mostrarErroValidacao('feedbackNome', 'Nome deve ter pelo menos 3 caracteres');
                valido = false;
            } else {
                limparErroValidacao('feedbackNome');
            }
            
            // Validar preço
            const preco = parseFloat(document.getElementById('preco').value);
            if (!preco || preco <= 0) {
                mostrarErroValidacao('feedbackPreco', 'Preço deve ser maior que zero');
                valido = false;
            } else if (preco > 9999.99) {
                mostrarErroValidacao('feedbackPreco', 'Preço máximo é R$ 9.999,99');
                valido = false;
            } else {
                limparErroValidacao('feedbackPreco');
            }
            
            // Validar tempo
            const tempo = document.getElementById('tempo_estimado').value;
            if (!tempo) {
                mostrarErroValidacao('feedbackTempo', 'Tempo estimado é obrigatório');
                valido = false;
            } else {
                limparErroValidacao('feedbackTempo');
            }
            
            return valido;
        }

        // Utility Functions
        function mostrarModal() {
            document.getElementById('modalServico').style.display = 'flex';
            document.getElementById('nome').focus();
        }

        function fecharModal() {
            document.getElementById('modalServico').style.display = 'none';
            servicoEditando = null;
        }

        function mostrarConfirmacao(titulo, mensagem, callback) {
            document.getElementById('confirmTitle').textContent = titulo;
            document.getElementById('confirmMessage').textContent = mensagem;
            confirmCallback = callback;
            document.getElementById('modalConfirmacao').style.display = 'flex';
        }

        function fecharModalConfirmacao() {
            document.getElementById('modalConfirmacao').style.display = 'none';
            confirmCallback = null;
        }

        function executarConfirmacao() {
            if (confirmCallback) {
                confirmCallback();
            }
            fecharModalConfirmacao();
        }

        function showTableLoading(show) {
            const tbody = document.getElementById('servicosBody');
            if (show) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center loading-row">
                            <div class="loading-text">
                                <div class="spinner-small"></div>
                                Carregando serviços...
                            </div>
                        </td>
                    </tr>
                `;
            }
        }

        function setBtnLoading(btnId, loading) {
            const btn = document.getElementById(btnId);
            const spinner = btn.querySelector('.btn-loading');
            const text = btn.querySelector('.btn-text');
            
            if (loading) {
                btn.disabled = true;
                spinner.style.display = 'inline-block';
            } else {
                btn.disabled = false;
                spinner.style.display = 'none';
            }
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            const icon = {
                'success': '✅',
                'error': '❌',
                'warning': '⚠️',
                'info': 'ℹ️'
            }[type] || 'ℹ️';
            
            toast.innerHTML = `
                <div class="toast-content">
                    <span class="toast-icon">${icon}</span>
                    <span class="toast-message">${message}</span>
                </div>
            `;
            
            container.appendChild(toast);
            
            // Auto-remove após 5 segundos
            setTimeout(() => {
                toast.classList.add('toast-removing');
                setTimeout(() => container.removeChild(toast), 300);
            }, 5000);
        }

        function atualizarContadorResultados(total = null) {
            const contador = document.getElementById('totalResultados');
            const count = total !== null ? total : servicosData.length;
            contador.textContent = `${count} serviço${count !== 1 ? 's' : ''} encontrado${count !== 1 ? 's' : ''}`;
        }

        function atualizarContador() {
            const textarea = document.getElementById('descricao');
            const contador = document.getElementById('descricaoCounter');
            contador.textContent = textarea.value.length;
        }

        function mostrarErroValidacao(elementId, mensagem) {
            const element = document.getElementById(elementId);
            element.textContent = mensagem;
            element.className = 'form-feedback error';
            element.style.display = 'block';
        }

        function limparErroValidacao(elementId) {
            const element = document.getElementById(elementId);
            element.style.display = 'none';
        }

        function limparValidacoes() {
            const feedbacks = document.querySelectorAll('.form-feedback');
            feedbacks.forEach(fb => fb.style.display = 'none');
        }

        function formatarMoeda(valor) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(valor);
        }

        function formatarTempo(tempo) {
            const [horas, minutos] = tempo.split(':');
            const h = parseInt(horas);
            const m = parseInt(minutos);
            
            if (h > 0) {
                return `${h}h ${m > 0 ? m + 'min' : ''}`.trim();
            }
            return `${m}min`;
        }

        function formatarDataHora(dataHora) {
            return new Date(dataHora).toLocaleString('pt-BR');
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    </script>
</body>
</html>