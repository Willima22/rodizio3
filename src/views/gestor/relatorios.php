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
    <title>Relatórios - Fast Escova</title>
    <link rel="stylesheet" href="/assets/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <h1>📊 Relatórios e Analytics</h1>
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
            <a href="/gestor/servicos" class="nav-link">Serviços</a>
            <a href="/gestor/relatorios" class="nav-link active">Relatórios</a>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Filtros -->
            <section class="filters-section">
                <div class="card">
                    <div class="card-header">
                        <h3>🔍 Filtros de Relatório</h3>
                    </div>
                    <div class="card-body">
                        <form id="filtrosForm" class="filters-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="form-group">
                                <label for="data_de">Data Inicial:</label>
                                <input type="date" id="data_de" name="data_de" class="form-control" 
                                       value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
                            </div>

                            <div class="form-group">
                                <label for="data_ate">Data Final:</label>
                                <input type="date" id="data_ate" name="data_ate" class="form-control" 
                                       value="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="form-group">
                                <label for="profissional_id">Profissional:</label>
                                <select id="profissional_id" name="profissional_id" class="form-control">
                                    <option value="">Todos os profissionais</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="servico_id">Serviço:</label>
                                <select id="servico_id" name="servico_id" class="form-control">
                                    <option value="">Todos os serviços</option>
                                </select>
                            </div>

                            <div class="form-actions">
                                <button type="button" id="btnAtualizar" class="btn btn-primary">
                                    🔄 Atualizar Relatório
                                </button>
                                <button type="button" id="btnExportar" class="btn btn-success">
                                    📥 Exportar CSV
                                </button>
                                <button type="button" id="btnRelatorioHoje" class="btn btn-info">
                                    📅 Relatório de Hoje
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Indicadores Principais -->
            <section class="metrics-section">
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">⏱️</div>
                        <div class="metric-content">
                            <div class="metric-label">Tempo Médio de Espera</div>
                            <div class="metric-value" id="tempoMedioEspera">-</div>
                            <div class="metric-unit">minutos</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">🕒</div>
                        <div class="metric-content">
                            <div class="metric-label">Tempo Médio de Atendimento</div>
                            <div class="metric-value" id="tempoMedioAtendimento">-</div>
                            <div class="metric-unit">minutos</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-content">
                            <div class="metric-label">Atendimentos Finalizados</div>
                            <div class="metric-value" id="totalFinalizados">-</div>
                            <div class="metric-unit">atendimentos</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">💰</div>
                        <div class="metric-content">
                            <div class="metric-label">Faturamento Total</div>
                            <div class="metric-value" id="faturamentoTotal">-</div>
                            <div class="metric-unit">reais</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Gráficos -->
            <section class="charts-section">
                <div class="charts-grid">
                    <!-- Gráfico de Atendimentos por Hora -->
                    <div class="card">
                        <div class="card-header">
                            <h3>📈 Atendimentos por Hora do Dia</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="chartAtendimentosPorHora" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Performance por Profissional -->
                    <div class="card">
                        <div class="card-header">
                            <h3>👥 Performance por Profissional</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="chartPerformanceProfissional" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Serviços Mais Procurados -->
                    <div class="card">
                        <div class="card-header">
                            <h3>🛀 Serviços Mais Procurados</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="chartServicosProcurados" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Faturamento Diário -->
                    <div class="card">
                        <div class="card-header">
                            <h3>💵 Evolução do Faturamento</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="chartFaturamentoDiario" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Ranking de Profissionais -->
            <section class="ranking-section">
                <div class="card">
                    <div class="card-header">
                        <h3>🏆 Ranking de Profissionais</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="tabelaRanking">
                                <thead>
                                    <tr>
                                        <th>Posição</th>
                                        <th>Profissional</th>
                                        <th>Atendimentos</th>
                                        <th>Tempo Médio</th>
                                        <th>Faturamento</th>
                                        <th>Eficiência</th>
                                    </tr>
                                </thead>
                                <tbody id="rankingBody">
                                    <tr>
                                        <td colspan="6" class="text-center">Carregando dados...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Detalhamento dos Atendimentos -->
            <section class="details-section">
                <div class="card">
                    <div class="card-header">
                        <h3>📋 Detalhamento dos Atendimentos</h3>
                        <div class="card-actions">
                            <button type="button" id="btnAtualizarDetalhes" class="btn btn-sm btn-secondary">
                                🔄 Atualizar
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="tabelaDetalhes">
                                <thead>
                                    <tr>
                                        <th>Data/Hora</th>
                                        <th>Cliente</th>
                                        <th>Profissional</th>
                                        <th>Serviço</th>
                                        <th>Tempo Espera</th>
                                        <th>Tempo Atendimento</th>
                                        <th>Valor</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="detalhesBody">
                                    <tr>
                                        <td colspan="8" class="text-center">Carregando dados...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-content">
            <div class="spinner"></div>
            <p>Gerando relatório...</p>
        </div>
    </div>

    <script>
        // Variáveis globais
        let chartAtendimentosPorHora = null;
        let chartPerformanceProfissional = null;
        let chartServicosProcurados = null;
        let chartFaturamentoDiario = null;

        // Inicialização da página
        document.addEventListener('DOMContentLoaded', function() {
            carregarFiltros();
            carregarRelatorio();
            configurarEventos();
        });

        // Configurar eventos
        function configurarEventos() {
            document.getElementById('btnAtualizar').addEventListener('click', carregarRelatorio);
            document.getElementById('btnExportar').addEventListener('click', exportarCSV);
            document.getElementById('btnRelatorioHoje').addEventListener('click', relatorioHoje);
            document.getElementById('btnAtualizarDetalhes').addEventListener('click', carregarDetalhamento);
        }

        // Carregar opções dos filtros
        async function carregarFiltros() {
            try {
                // Carregar profissionais
                const resProfissionais = await fetch('/api/profissionais.php?action=listar');
                const dataProfissionais = await resProfissionais.json();
                
                if (dataProfissionais.ok) {
                    const selectProf = document.getElementById('profissional_id');
                    dataProfissionais.data.forEach(prof => {
                        const option = document.createElement('option');
                        option.value = prof.id;
                        option.textContent = prof.nome;
                        selectProf.appendChild(option);
                    });
                }

                // Carregar serviços
                const resServicos = await fetch('/api/servicos.php?action=listar');
                const dataServicos = await resServicos.json();
                
                if (dataServicos.ok) {
                    const selectServ = document.getElementById('servico_id');
                    dataServicos.data.forEach(serv => {
                        const option = document.createElement('option');
                        option.value = serv.id;
                        option.textContent = serv.nome;
                        selectServ.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar filtros:', error);
            }
        }

        // Carregar relatório completo
        async function carregarRelatorio() {
            showLoading(true);
            
            try {
                const filtros = obterFiltros();
                
                // Carregar indicadores
                await carregarIndicadores(filtros);
                
                // Carregar gráficos
                await carregarGraficos(filtros);
                
                // Carregar ranking
                await carregarRanking(filtros);
                
                // Carregar detalhamento
                await carregarDetalhamento(filtros);
                
            } catch (error) {
                console.error('Erro ao carregar relatório:', error);
                alert('Erro ao carregar relatório. Tente novamente.');
            } finally {
                showLoading(false);
            }
        }

        // Obter filtros do formulário
        function obterFiltros() {
            const formData = new FormData(document.getElementById('filtrosForm'));
            const filtros = {};
            
            for (let [key, value] of formData.entries()) {
                if (value.trim() !== '') {
                    filtros[key] = value;
                }
            }
            
            return filtros;
        }

        // Carregar indicadores principais
        async function carregarIndicadores(filtros) {
            try {
                const params = new URLSearchParams(filtros);
                const response = await fetch(`/api/relatorios.php?action=indicadores&${params}`);
                const data = await response.json();
                
                if (data.ok) {
                    const indicadores = data.data;
                    
                    document.getElementById('tempoMedioEspera').textContent = 
                        indicadores.tempo_medio_espera ? Math.round(indicadores.tempo_medio_espera) : '0';
                    
                    document.getElementById('tempoMedioAtendimento').textContent = 
                        indicadores.tempo_medio_atendimento ? Math.round(indicadores.tempo_medio_atendimento) : '0';
                    
                    document.getElementById('totalFinalizados').textContent = 
                        indicadores.total_finalizados || '0';
                    
                    document.getElementById('faturamentoTotal').textContent = 
                        formatarMoeda(indicadores.faturamento_total || 0);
                }
            } catch (error) {
                console.error('Erro ao carregar indicadores:', error);
            }
        }

        // Carregar gráficos
        async function carregarGraficos(filtros) {
            try {
                const params = new URLSearchParams(filtros);
                
                // Gráfico de atendimentos por hora
                const resHoras = await fetch(`/api/relatorios.php?action=atendimentos_por_hora&${params}`);
                const dataHoras = await resHoras.json();
                
                if (dataHoras.ok) {
                    atualizarGraficoAtendimentosPorHora(dataHoras.data);
                }

                // Gráfico de performance por profissional
                const resPerformance = await fetch(`/api/relatorios.php?action=performance_profissional&${params}`);
                const dataPerformance = await resPerformance.json();
                
                if (dataPerformance.ok) {
                    atualizarGraficoPerformanceProfissional(dataPerformance.data);
                }

                // Gráfico de serviços mais procurados
                const resServicos = await fetch(`/api/relatorios.php?action=servicos_procurados&${params}`);
                const dataServicos = await resServicos.json();
                
                if (dataServicos.ok) {
                    atualizarGraficoServicosProcurados(dataServicos.data);
                }

                // Gráfico de faturamento diário
                const resFaturamento = await fetch(`/api/relatorios.php?action=faturamento_diario&${params}`);
                const dataFaturamento = await resFaturamento.json();
                
                if (dataFaturamento.ok) {
                    atualizarGraficoFaturamentoDiario(dataFaturamento.data);
                }
                
            } catch (error) {
                console.error('Erro ao carregar gráficos:', error);
            }
        }

        // Atualizar gráfico de atendimentos por hora
        function atualizarGraficoAtendimentosPorHora(dados) {
            const ctx = document.getElementById('chartAtendimentosPorHora').getContext('2d');
            
            if (chartAtendimentosPorHora) {
                chartAtendimentosPorHora.destroy();
            }
            
            chartAtendimentosPorHora = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dados.map(item => item.hora + 'h'),
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

        // Atualizar gráfico de performance por profissional
        function atualizarGraficoPerformanceProfissional(dados) {
            const ctx = document.getElementById('chartPerformanceProfissional').getContext('2d');
            
            if (chartPerformanceProfissional) {
                chartPerformanceProfissional.destroy();
            }
            
            chartPerformanceProfissional = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dados.map(item => item.nome),
                    datasets: [{
                        label: 'Atendimentos',
                        data: dados.map(item => item.total_atendimentos),
                        backgroundColor: '#28a745',
                        borderColor: '#1e7e34',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
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

        // Atualizar gráfico de serviços mais procurados
        function atualizarGraficoServicosProcurados(dados) {
            const ctx = document.getElementById('chartServicosProcurados').getContext('2d');
            
            if (chartServicosProcurados) {
                chartServicosProcurados.destroy();
            }
            
            const cores = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
            
            chartServicosProcurados = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: dados.map(item => item.nome),
                    datasets: [{
                        data: dados.map(item => item.total),
                        backgroundColor: cores.slice(0, dados.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Atualizar gráfico de faturamento diário
        function atualizarGraficoFaturamentoDiario(dados) {
            const ctx = document.getElementById('chartFaturamentoDiario').getContext('2d');
            
            if (chartFaturamentoDiario) {
                chartFaturamentoDiario.destroy();
            }
            
            chartFaturamentoDiario = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dados.map(item => formatarData(item.data)),
                    datasets: [{
                        label: 'Faturamento (R$)',
                        data: dados.map(item => parseFloat(item.faturamento)),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Carregar ranking de profissionais
        async function carregarRanking(filtros) {
            try {
                const params = new URLSearchParams(filtros);
                const response = await fetch(`/api/relatorios.php?action=ranking_profissionais&${params}`);
                const data = await response.json();
                
                if (data.ok) {
                    const tbody = document.getElementById('rankingBody');
                    tbody.innerHTML = '';
                    
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum dado encontrado</td></tr>';
                        return;
                    }
                    
                    data.data.forEach((prof, index) => {
                        const row = document.createElement('tr');
                        
                        const posicaoClass = index === 0 ? 'ranking-first' : 
                                           index === 1 ? 'ranking-second' : 
                                           index === 2 ? 'ranking-third' : '';
                        
                        row.className = posicaoClass;
                        
                        row.innerHTML = `
                            <td class="ranking-position">
                                ${index + 1}
                                ${index < 3 ? '<span class="medal">🏆</span>' : ''}
                            </td>
                            <td class="font-weight-bold">${prof.nome}</td>
                            <td>${prof.total_atendimentos}</td>
                            <td>${prof.tempo_medio ? Math.round(prof.tempo_medio) : '0'} min</td>
                            <td>${formatarMoeda(prof.faturamento || 0)}</td>
                            <td>
                                <div class="eficiencia-bar">
                                    <div class="eficiencia-fill" style="width: ${prof.eficiencia || 0}%"></div>
                                    <span class="eficiencia-text">${prof.eficiencia || 0}%</span>
                                </div>
                            </td>
                        `;
                        
                        tbody.appendChild(row);
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar ranking:', error);
            }
        }

        // Carregar detalhamento dos atendimentos
        async function carregarDetalhamento(filtros) {
            try {
                const params = new URLSearchParams(filtros);
                const response = await fetch(`/api/relatorios.php?action=detalhamento&${params}`);
                const data = await response.json();
                
                if (data.ok) {
                    const tbody = document.getElementById('detalhesBody');
                    tbody.innerHTML = '';
                    
                    if (data.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center">Nenhum atendimento encontrado</td></tr>';
                        return;
                    }
                    
                    data.data.forEach(atendimento => {
                        const row = document.createElement('tr');
                        
                        const statusClass = {
                            'finalizado': 'status-finalizado',
                            'em_andamento': 'status-em-andamento',
                            'aguardando': 'status-aguardando',
                            'cancelado': 'status-cancelado'
                        }[atendimento.status] || '';
                        
                        row.innerHTML = `
                            <td>${formatarDataHora(atendimento.criado_em)}</td>
                            <td>${atendimento.cliente_nome}</td>
                            <td>${atendimento.profissional_nome || '-'}</td>
                            <td>${atendimento.servico_nome}</td>
                            <td>${atendimento.tempo_espera ? Math.round(atendimento.tempo_espera) : '-'} min</td>
                            <td>${atendimento.tempo_atendimento ? Math.round(atendimento.tempo_atendimento) : '-'} min</td>
                            <td>${formatarMoeda(atendimento.valor_cobrado || 0)}</td>
                            <td><span class="status-badge ${statusClass}">${formatarStatus(atendimento.status)}</span></td>
                        `;
                        
                        tbody.appendChild(row);
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar detalhamento:', error);
            }
        }

        // Exportar relatório em CSV
        async function exportarCSV() {
            try {
                showLoading(true);
                
                const filtros = obterFiltros();
                const params = new URLSearchParams(filtros);
                
                const response = await fetch(`/api/relatorios.php?action=exportar_csv&${params}`);
                const data = await response.json();
                
                if (data.ok) {
                    // Criar link de download
                    const link = document.createElement('a');
                    link.href = data.data.url;
                    link.download = data.data.filename;
                    link.click();
                    
                    alert('Relatório exportado com sucesso!');
                } else {
                    alert('Erro ao exportar relatório: ' + data.error);
                }
            } catch (error) {
                console.error('Erro ao exportar CSV:', error);
                alert('Erro ao exportar relatório. Tente novamente.');
            } finally {
                showLoading(false);
            }
        }

        // Relatório de hoje
        function relatorioHoje() {
            const hoje = new Date().toISOString().split('T')[0];
            document.getElementById('data_de').value = hoje;
            document.getElementById('data_ate').value = hoje;
            document.getElementById('profissional_id').value = '';
            document.getElementById('servico_id').value = '';
            
            carregarRelatorio();
        }

        // Utility functions
        function showLoading(show) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }

        function formatarMoeda(valor) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(valor);
        }

        function formatarData(data) {
            return new Date(data).toLocaleDateString('pt-BR');
        }

        function formatarDataHora(dataHora) {
            return new Date(dataHora).toLocaleString('pt-BR');
        }

        function formatarStatus(status) {
            const statusMap = {
                'aguardando': 'Aguardando',
                'em_andamento': 'Em Andamento',
                'finalizado': 'Finalizado',
                'cancelado': 'Cancelado'
            };
            return statusMap[status] || status;
        }
    </script>
</body>
</html>