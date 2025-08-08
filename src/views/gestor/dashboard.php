<?php
/**
 * Dashboard do Gestor
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

use Utils\Auth;
use Utils\Response;
use Utils\CSRF;

// Verificar autenticação e permissão
if (!Auth::check() || !Auth::isGestor()) {
    Response::redirect('/login');
}

$user = Auth::getUser();
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fast Escova</title>
    <link rel="stylesheet" href="/assets/style.css">
    <?php echo CSRF::metaTag(); ?>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        .metric-trend {
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        .trend-up { color: var(--success-color); }
        .trend-down { color: var(--danger-color); }
        .trend-neutral { color: var(--secondary-color); }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .action-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }
        .action-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary-color);
        }
        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
    </style>
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
                    <a href="/gestor/dashboard" class="nav-link active">
                        <span class="nav-icon">📊</span>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/recepcao/fila" class="nav-link">
                        <span class="nav-icon">👥</span>
                        Fila de Espera
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/gestor/profissionais" class="nav-link">
                        <span class="nav-icon">👩</span>
                        Profissionais
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/gestor/servicos" class="nav-link">
                        <span class="nav-icon">✂️</span>
                        Serviços
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/gestor/relatorios" class="nav-link">
                        <span class="nav-icon">📈</span>
                        Relatórios
                    </a>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-item">
                    <a href="#" onclick="resetDiario()" class="nav-link">
                        <span class="nav-icon">🔄</span>
                        Reset Diário
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="app-main">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Dashboard</h1>
                <div class="d-flex gap-1rem">
                    <select id="periodoSelect" class="form-control" style="width: auto;">
                        <option value="hoje">Hoje</option>
                        <option value="semana">Esta Semana</option>
                        <option value="mes" selected>Este Mês</option>
                    </select>
                    <button id="refreshDashboard" class="btn btn-outline">🔄 Atualizar</button>
                </div>
            </div>

            <!-- Ações Rápidas -->
            <div class="quick-actions">
                <div class="action-card" onclick="window.location.href='/recepcao/cadastro'">
                    <div class="action-icon">➕</div>
                    <div><strong>Cadastrar Cliente</strong></div>
                    <small class="text-muted">Adicionar na fila</small>
                </div>
                <div class="action-card" onclick="distribuirAutomatico()">
                    <div class="action-icon">⚡</div>
                    <div><strong>Distribuir Automático</strong></div>
                    <small class="text-muted">Próximo da fila</small>
                </div>
                <div class="action-card" onclick="window.location.href='/gestor/profissionais'">
                    <div class="action-icon">👩</div>
                    <div><strong>Gerenciar Profissionais</strong></div>
                    <small class="text-muted">Status e controle</small>
                </div>
                <div class="action-card" onclick="exportarRelatorio()">
                    <div class="action-icon">📊</div>
                    <div><strong>Exportar Relatório</strong></div>
                    <small class="text-muted">Dados em CSV</small>
                </div>
            </div>

            <!-- Indicadores Principais -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-value" id="tempoMedioEspera">-</div>
                    <div class="stat-label">Tempo Médio de Espera</div>
                    <div class="metric-trend" id="trendEspera">-</div>
                </div>
                <div class="stat-card stat-info">
                    <div class="stat-value" id="tempoMedioAtendimento">-</div>
                    <div class="stat-label">Tempo Médio de Atendimento</div>
                    <div class="metric-trend" id="trendAtendimento">-</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-value" id="atendimentosFinalizados">-</div>
                    <div class="stat-label">Atendimentos Finalizados</div>
                    <div class="metric-trend" id="trendFinalizados">-</div>
                </div>
                <div class="stat-card stat-warning">
                    <div class="stat-value" id="faturamentoTotal">-</div>
                    <div class="stat-label">Faturamento Total</div>
                    <div class="metric-trend" id="trendFaturamento">-</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <!-- Gráfico de Atendimentos por Hora -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Atendimentos por Hora (Hoje)</h3>
                        <button id="refreshGrafico" class="btn btn-outline btn-sm">🔄</button>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="atendimentosChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Status Atual -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Status Atual</h3>
                    </div>
                    <div class="card-body">
                        <div id="status-atual">
                            <div class="mb-3">
                                <strong>Na Fila:</strong>
                                <span class="badge badge-warning" id="totalFila">0</span>
                            </div>
                            <div class="mb-3">
                                <strong>Atendendo:</strong>
                                <span class="badge badge-info" id="totalAtendendo">0</span>
                            </div>
                            <div class="mb-3">
                                <strong>Profissionais Livres:</strong>
                                <span class="badge badge-success" id="profissionaisLivres">0</span>
                            </div>
                            <div class="mb-3">
                                <strong>Próximo Cliente:</strong>
                                <div id="proximoCliente" class="text-muted">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profissional com Maior Carga e Métricas Detalhadas -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top Profissionais (Período)</h3>
                    </div>
                    <div class="card-body">
                        <div id="ranking-profissionais">
                            <div class="text-center">
                                <div class="loading"></div>
                                <p>Carregando ranking...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Serviços Mais Procurados</h3>
                    </div>
                    <div class="card-body">
                        <div id="servicos-populares">
                            <div class="text-center">
                                <div class="loading"></div>
                                <p>Carregando serviços...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Configurações globais
        window.APP = {
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            baseUrl: '',
            chart: null,
            pollingInterval: null
        };

        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
        });

        function initDashboard() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');

            // Toggle sidebar
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('sidebar-hidden');
            });

            // Event listeners
            document.getElementById('refreshDashboard').addEventListener('click', carregarTodosDados);
            document.getElementById('refreshGrafico').addEventListener('click', carregarGraficoAtendimentos);
            document.getElementById('periodoSelect').addEventListener('change', carregarIndicadores);

            // Carregar dados iniciais
            carregarTodosDados();

            // Polling a cada 30 segundos
            window.APP.pollingInterval = setInterval(() => {
                carregarStatusAtual();
                carregarGraficoAtendimentos();
            }, 30000);
        }

        async function carregarTodosDados() {
            await Promise.all([
                carregarIndicadores(),
                carregarGraficoAtendimentos(),
                carregarStatusAtual(),
                carregarRankingProfissionais(),
                carregarServicosPopulares()
            ]);
        }

        async function carregarIndicadores() {
            try {
                const periodo = document.getElementById('periodoSelect').value;
                let dataInicio, dataFim;

                const hoje = new Date();
                switch (periodo) {
                    case 'hoje':
                        dataInicio = dataFim = hoje.toISOString().split('T')[0];
                        break;
                    case 'semana':
                        const inicioSemana = new Date(hoje);
                        inicioSemana.setDate(hoje.getDate() - hoje.getDay());
                        dataInicio = inicioSemana.toISOString().split('T')[0];
                        dataFim = hoje.toISOString().split('T')[0];
                        break;
                    case 'mes':
                    default:
                        dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().split('T')[0];
                        dataFim = hoje.toISOString().split('T')[0];
                        break;
                }

                const response = await fetch(`/api/relatorios.php?action=indicadores&data_inicio=${dataInicio}&data_fim=${dataFim}`);
                const data = await response.json();

                if (data.ok) {
                    renderizarIndicadores(data.data);
                }
            } catch (error) {
                console.error('Erro ao carregar indicadores:', error);
            }
        }

        function renderizarIndicadores(dados) {
            document.getElementById('tempoMedioEspera').textContent = dados.tempo_medio_espera_formatado || '0 min';
            document.getElementById('tempoMedioAtendimento').textContent = dados.tempo_medio_atendimento_formatado || '0 min';
            document.getElementById('atendimentosFinalizados').textContent = dados.total_atendimentos_finalizados || 0;
            document.getElementById('faturamentoTotal').textContent = `R$ ${dados.faturamento_total.toFixed(2).replace('.', ',')}`;

            // Trends (simulado - em produção viria da API)
            document.getElementById('trendEspera').innerHTML = '<span class="trend-down">↓ 12% vs período anterior</span>';
            document.getElementById('trendAtendimento').innerHTML = '<span class="trend-up">↑ 5% vs período anterior</span>';
            document.getElementById('trendFinalizados').innerHTML = '<span class="trend-up">↑ 18% vs período anterior</span>';
            document.getElementById('trendFaturamento').innerHTML = '<span class="trend-up">↑ 22% vs período anterior</span>';
        }

        async function carregarGraficoAtendimentos() {
            try {
                const hoje = new Date().toISOString().split('T')[0];
                const response = await fetch(`/api/relatorios.php?action=atendimentos_por_hora&data=${hoje}`);
                const data = await response.json();

                if (data.ok) {
                    renderizarGrafico(data.data.serie);
                }
            } catch (error) {
                console.error('Erro ao carregar gráfico:', error);
            }
        }

        function renderizarGrafico(dados) {
            const ctx = document.getElementById('atendimentosChart').getContext('2d');

            if (window.APP.chart) {
                window.APP.chart.destroy();
            }

            window.APP.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dados.map(item => item.hora),
                    datasets: [{
                        label: 'Atendimentos',
                        data: dados.map(item => item.total),
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        async function carregarStatusAtual() {
            try {
                const [filaResponse, profResponse] = await Promise.all([
                    fetch('/api/clientes.php?action=fila'),
                    fetch('/api/profissionais.php?action=status')
                ]);

                const filaData = await filaResponse.json();
                const profData = await profResponse.json();

                if (filaData.ok) {
                    document.getElementById('totalFila').textContent = filaData.data.total_fila;
                    
                    const proximoCliente = filaData.data.proximo;
                    document.getElementById('proximoCliente').textContent = proximoCliente 
                        ? `${proximoCliente.cliente_nome} (${proximoCliente.servico_nome})`
                        : 'Nenhum cliente na fila';
                }

                if (profData.ok) {
                    document.getElementById('totalAtendendo').textContent = profData.data.resumo.atendendo;
                    document.getElementById('profissionaisLivres').textContent = profData.data.resumo.livres;
                }
            } catch (error) {
                console.error('Erro ao carregar status atual:', error);
            }
        }

        async function carregarRankingProfissionais() {
            try {
                const periodo = document.getElementById('periodoSelect').value;
                let dataInicio, dataFim;

                const hoje = new Date();
                switch (periodo) {
                    case 'hoje':
                        dataInicio = dataFim = hoje.toISOString().split('T')[0];
                        break;
                    case 'semana':
                        const inicioSemana = new Date(hoje);
                        inicioSemana.setDate(hoje.getDate() - hoje.getDay());
                        dataInicio = inicioSemana.toISOString().split('T')[0];
                        dataFim = hoje.toISOString().split('T')[0];
                        break;
                    case 'mes':
                    default:
                        dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().split('T')[0];
                        dataFim = hoje.toISOString().split('T')[0];
                        break;
                }

                const response = await fetch(`/api/relatorios.php?action=performance_profissionais&data_inicio=${dataInicio}&data_fim=${dataFim}`);
                const data = await response.json();

                if (data.ok) {
                    renderizarRankingProfissionais(data.data.profissionais.slice(0, 5));
                }
            } catch (error) {
                console.error('Erro ao carregar ranking:', error);
            }
        }

        function renderizarRankingProfissionais(profissionais) {
            const container = document.getElementById('ranking-profissionais');

            if (profissionais.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Nenhum dado disponível</p>';
                return;
            }

            const rankingHtml = profissionais.map((prof, index) => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2" 
                     style="background: #f8f9fa; border-radius: 4px;">
                    <div>
                        <span class="badge badge-primary">${index + 1}º</span>
                        <strong class="ml-2">${prof.nome}</strong><br>
                        <small class="text-muted">${prof.finalizados} atendimentos • ${prof.tempo_medio_formatado}</small>
                    </div>
                    <div class="text-right">
                        <div style="font-weight: bold;">R$ ${prof.total_faturado.toFixed(2)}</div>
                        <small>${prof.taxa_finalizacao}% conclusão</small>
                    </div>
                </div>
            `).join('');

            container.innerHTML = rankingHtml;
        }

        async function carregarServicosPopulares() {
            try {
                const response = await fetch('/api/servicos.php?action=mais_utilizados&limite=5');
                const data = await response.json();

                if (data.ok) {
                    renderizarServicosPopulares(data.data);
                }
            } catch (error) {
                console.error('Erro ao carregar serviços populares:', error);
            }
        }

        function renderizarServicosPopulares(servicos) {
            const container = document.getElementById('servicos-populares');

            if (servicos.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Nenhum dado disponível</p>';
                return;
            }

            const servicosHtml = servicos.map((servico, index) => `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2" 
                     style="background: #f8f9fa; border-radius: 4px;">
                    <div>
                        <strong>${servico.nome}</strong><br>
                        <small class="text-muted">R$ ${parseFloat(servico.preco).toFixed(2)}</small>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-info">${servico.total_atendimentos}</span><br>
                        <small>atendimentos</small>
                    </div>
                </div>
            `).join('');

            container.innerHTML = servicosHtml;
        }

        async function distribuirAutomatico() {
            try {
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
                    carregarStatusAtual();
                } else {
                    showNotification(data.error || 'Erro na distribuição automática', 'error');
                }
            } catch (error) {
                console.error('Erro na distribuição:', error);
                showNotification('Erro de comunicação', 'error');
            }
        }

        async function exportarRelatorio() {
            try {
                const hoje = new Date().toISOString().split('T')[0];
                const response = await fetch(`/api/relatorios.php?action=exportar_csv&tipo=atendimentos&data_inicio=${hoje}&data_fim=${hoje}`);
                const data = await response.json();

                if (data.ok) {
                    // Criar link de download
                    const link = document.createElement('a');
                    link.href = data.data.download_url;
                    link.download = data.data.filename;
                    link.click();
                    
                    showNotification('Relatório exportado com sucesso!', 'success');
                } else {
                    showNotification(data.error || 'Erro ao exportar relatório', 'error');
                }
            } catch (error) {
                console.error('Erro na exportação:', error);
                showNotification('Erro de comunicação', 'error');
            }
        }

        async function resetDiario() {
            if (!confirm('Tem certeza que deseja fazer o reset diário? Esta ação irá zerar os contadores de todas as profissionais.')) {
                return;
            }

            try {
                const response = await fetch('/api/profissionais.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=reset_diario&${window.APP.csrfToken}=${encodeURIComponent(window.APP.csrfToken)}`
                });

                const data = await response.json();

                if (data.ok) {
                    showNotification(`Reset diário executado! ${data.data.profissionais_afetados} profissionais afetados.`, 'success');
                    carregarTodosDados();
                } else {
                    showNotification(data.error || 'Erro no reset diário', 'error');
                }
            } catch (error) {
                console.error('Erro no reset:', error);
                showNotification('Erro de comunicação', 'error');
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
            }, 5000);
        }

        // Cleanup ao sair da página
        window.addEventListener('beforeunload', function() {
            if (window.APP.pollingInterval) {
                clearInterval(window.APP.pollingInterval);
            }
            if (window.APP.chart) {
                window.APP.chart.destroy();
            }
        });
    </script>
</body>
</html>