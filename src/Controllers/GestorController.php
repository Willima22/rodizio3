<?php

namespace FastEscova\Controllers;

require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Atendimento.php';
require_once __DIR__ . '/../Models/Cliente.php';
require_once __DIR__ . '/../Models/Profissional.php';
require_once __DIR__ . '/../Models/Servico.php';
require_once __DIR__ . '/../Utils/Auth.php';

use FastEscova\Models\Usuario;
use FastEscova\Models\Atendimento;
use FastEscova\Models\Cliente;
use FastEscova\Models\Profissional;
use FastEscova\Models\Servico;
use FastEscova\Utils\Auth;

/**
 * Controller GestorController - Painel do Gestor
 * 
 * Gerencia todas as funcionalidades administrativas do sistema
 */
class GestorController {
    
    /**
     * Dashboard principal do gestor
     */
    public function dashboard() {
        // Verificar permissão
        if (!Auth::isGestor()) {
            header('Location: /login');
            exit;
        }
        
        try {
            // Buscar dados para o dashboard
            $dadosDashboard = $this->obterDadosDashboard();
            
            // Incluir view
            $title = 'Dashboard - Gestor';
            $user = Auth::user();
            
            include __DIR__ . '/../views/gestor/dashboard.php';
            
        } catch (Exception $e) {
            $_SESSION['erro'] = 'Erro ao carregar dashboard: ' . $e->getMessage();
            header('Location: /login');
            exit;
        }
    }
    
    /**
     * Página de gerenciamento de profissionais
     */
    public function profissionais() {
        if (!Auth::isGestor()) {
            header('Location: /login');
            exit;
        }
        
        try {
            $profissionalModel = new Profissional();
            
            // Filtros
            $filtros = [
                'nome' => $_GET['nome'] ?? '',
                'especialidade' => $_GET['especialidade'] ?? '',
                'status' => $_GET['status'] ?? ''
            ];
            
            // Buscar profissionais
            $profissionais = $profissionalModel->listar($filtros);
            
            // Estatísticas
            $estatisticas = $profissionalModel->obterEstatisticas();
            
            $title = 'Profissionais - Gestor';
            $user = Auth::user();
            
            include __DIR__ . '/../views/gestor/profissionais.php';
            
        } catch (Exception $e) {
            $_SESSION['erro'] = 'Erro ao carregar profissionais: ' . $e->getMessage();
            header('Location: /gestor/dashboard');
            exit;
        }
    }
    
    /**
     * Página de gerenciamento de serviços
     */
    public function servicos() {
        if (!Auth::isGestor()) {
            header('Location: /login');
            exit;
        }
        
        try {
            $servicoModel = new Servico();
            
            // Filtros
            $filtros = [
                'nome' => $_GET['nome'] ?? '',
                'categoria' => $_GET['categoria'] ?? '',
                'status' => $_GET['status'] ?? ''
            ];
            
            // Buscar serviços
            $servicos = $servicoModel->listar($filtros);
            
            // Estatísticas
            $estatisticas = $servicoModel->obterEstatisticas();
            
            $title = 'Serviços - Gestor';
            $user = Auth::user();
            
            include __DIR__ . '/../views/gestor/servicos.php';
            
        } catch (Exception $e) {
            $_SESSION['erro'] = 'Erro ao carregar serviços: ' . $e->getMessage();
            header('Location: /gestor/dashboard');
            exit;
        }
    }
    
    /**
     * Página de relatórios
     */
    public function relatorios() {
        if (!Auth::isGestor()) {
            header('Location: /login');
            exit;
        }
        
        try {
            // Buscar dados básicos para seleção de relatórios
            $periodoDefault = [
                'inicio' => date('Y-m-01'), // Primeiro dia do mês
                'fim' => date('Y-m-d')      // Hoje
            ];
            
            // Listas para filtros
            $profissionalModel = new Profissional();
            $servicoModel = new Servico();
            
            $profissionais = $profissionalModel->listarAtivos();
            $servicos = $servicoModel->listarAtivos();
            
            $title = 'Relatórios - Gestor';
            $user = Auth::user();
            
            include __DIR__ . '/../views/gestor/relatorios.php';
            
        } catch (Exception $e) {
            $_SESSION['erro'] = 'Erro ao carregar relatórios: ' . $e->getMessage();
            header('Location: /gestor/dashboard');
            exit;
        }
    }
    
    /**
     * Obter dados consolidados para o dashboard
     * 
     * @return array Dados do dashboard
     */
    private function obterDadosDashboard() {
        $atendimentoModel = new Atendimento();
        $clienteModel = new Cliente();
        $profissionalModel = new Profissional();
        $servicoModel = new Servico();
        
        // Período: últimos 30 dias
        $dataInicio = date('Y-m-d', strtotime('-30 days'));
        $dataFim = date('Y-m-d');
        
        return [
            // Cards principais
            'cards' => [
                'atendimentos_hoje' => $atendimentoModel->contarPorPeriodo(date('Y-m-d'), date('Y-m-d')),
                'atendimentos_mes' => $atendimentoModel->contarPorPeriodo(date('Y-m-01'), date('Y-m-d')),
                'clientes_total' => $clienteModel->contarTotal(),
                'clientes_novos_mes' => $clienteModel->contarNovosPorPeriodo(date('Y-m-01'), date('Y-m-d')),
                'profissionais_ativos' => $profissionalModel->contarAtivos(),
                'servicos_ativos' => $servicoModel->contarAtivos()
            ],
            
            // Gráficos
            'graficos' => [
                'atendimentos_por_dia' => $atendimentoModel->obterPorDia($dataInicio, $dataFim),
                'atendimentos_por_profissional' => $atendimentoModel->obterPorProfissional($dataInicio, $dataFim),
                'servicos_mais_realizados' => $atendimentoModel->obterServicosMaisRealizados($dataInicio, $dataFim),
                'faturamento_por_dia' => $atendimentoModel->obterFaturamentoPorDia($dataInicio, $dataFim)
            ],
            
            // Listas
            'listas' => [
                'proximos_atendimentos' => $atendimentoModel->obterProximos(10),
                'atendimentos_em_andamento' => $atendimentoModel->obterEmAndamento(),
                'clientes_recentes' => $clienteModel->obterRecentes(5),
                'profissionais_ocupados' => $profissionalModel->obterOcupados()
            ],
            
            // Alertas e notificações
            'alertas' => [
                'atendimentos_atrasados' => $atendimentoModel->contarAtrasados(),
                'profissionais_inativos' => $profissionalModel->contarInativos(),
                'servicos_sem_agendamento' => $servicoModel->contarSemAgendamento(),
                'clientes_sem_retorno' => $clienteModel->contarSemRetorno(90) // 90 dias
            ],
            
            // Performance
            'performance' => [
                'taxa_ocupacao' => $this->calcularTaxaOcupacao($dataInicio, $dataFim),
                'tempo_medio_atendimento' => $atendimentoModel->obterTempoMedioAtendimento($dataInicio, $dataFim),
                'satisfacao_media' => $atendimentoModel->obterSatisfacaoMedia($dataInicio, $dataFim),
                'receita_total_mes' => $atendimentoModel->obterReceitaPorPeriodo(date('Y-m-01'), date('Y-m-d'))
            ]
        ];
    }
    
    /**
     * Calcular taxa de ocupação dos profissionais
     * 
     * @param string $dataInicio Data de início
     * @param string $dataFim Data de fim
     * @return float Taxa de ocupação em percentual
     */
    private function calcularTaxaOcupacao($dataInicio, $dataFim) {
        $atendimentoModel = new Atendimento();
        $profissionalModel = new Profissional();
        
        // Horas totais de atendimento realizadas
        $horasAtendimento = $atendimentoModel->obterHorasTotaisAtendimento($dataInicio, $dataFim);
        
        // Horas totais disponíveis (considerando 8h/dia útil por profissional ativo)
        $profissionaisAtivos = $profissionalModel->contarAtivos();
        $diasUteis = $this->contarDiasUteis($dataInicio, $dataFim);
        $horasDisponiveis = $profissionaisAtivos * $diasUteis * 8;
        
        if ($horasDisponiveis > 0) {
            return round(($horasAtendimento / $horasDisponiveis) * 100, 2);
        }
        
        return 0;
    }
    
    /**
     * Contar dias úteis entre duas datas
     * 
     * @param string $dataInicio Data de início
     * @param string $dataFim Data de fim
     * @return int Número de dias úteis
     */
    private function contarDiasUteis($dataInicio, $dataFim) {
        $inicio = new DateTime($dataInicio);
        $fim = new DateTime($dataFim);
        $diasUteis = 0;
        
        while ($inicio <= $fim) {
            $diaSemana = $inicio->format('N'); // 1 = segunda, 7 = domingo
            if ($diaSemana < 6) { // segunda a sexta
                $diasUteis++;
            }
            $inicio->add(new DateInterval('P1D'));
        }
        
        return $diasUteis;
    }
    
    /**
     * Exportar dados do dashboard para Excel/PDF
     */
    public function exportarDashboard() {
        if (!Auth::isGestor()) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Acesso negado']);
            exit;
        }
        
        try {
            $formato = $_GET['formato'] ?? 'excel';
            $dados = $this->obterDadosDashboard();
            
            if ($formato === 'pdf') {
                $this->exportarDashboardPdf($dados);
            } else {
                $this->exportarDashboardExcel($dados);
            }
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Erro ao exportar: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Exportar dashboard para Excel
     */
    private function exportarDashboardExcel($dados) {
        // Configurar headers para download
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="dashboard_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: max-age=0');
        
        // Gerar conteúdo Excel simples
        echo "<html><body>";
        echo "<h1>Dashboard - Fast Escova</h1>";
        echo "<p>Gerado em: " . date('d/m/Y H:i:s') . "</p>";
        
        echo "<h2>Resumo Geral</h2>";
        echo "<table border='1'>";
        echo "<tr><th>Métrica</th><th>Valor</th></tr>";
        echo "<tr><td>Atendimentos Hoje</td><td>" . $dados['cards']['atendimentos_hoje'] . "</td></tr>";
        echo "<tr><td>Atendimentos do Mês</td><td>" . $dados['cards']['atendimentos_mes'] . "</td></tr>";
        echo "<tr><td>Total de Clientes</td><td>" . $dados['cards']['clientes_total'] . "</td></tr>";
        echo "<tr><td>Novos Clientes do Mês</td><td>" . $dados['cards']['clientes_novos_mes'] . "</td></tr>";
        echo "<tr><td>Profissionais Ativos</td><td>" . $dados['cards']['profissionais_ativos'] . "</td></tr>";
        echo "<tr><td>Serviços Ativos</td><td>" . $dados['cards']['servicos_ativos'] . "</td></tr>";
        echo "</table>";
        
        echo "</body></html>";
    }
    
    /**
     * Exportar dashboard para PDF
     */
    private function exportarDashboardPdf($dados) {
        // Para implementação futura com biblioteca de PDF
        // Por enquanto, retorna erro
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'Exportação PDF em desenvolvimento']);
    }
}

?>