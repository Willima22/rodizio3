<?php

namespace FastEscova\Controllers;

require_once __DIR__ . '/../Models/Atendimento.php';
require_once __DIR__ . '/../Models/Cliente.php';
require_once __DIR__ . '/../Models/Profissional.php';
require_once __DIR__ . '/../Models/Servico.php';
require_once __DIR__ . '/../Utils/Auth.php';
require_once __DIR__ . '/../Utils/Response.php';

use FastEscova\Models\Atendimento;
use FastEscova\Models\Cliente;
use FastEscova\Models\Profissional;
use FastEscova\Models\Servico;
use FastEscova\Utils\Auth;
use FastEscova\Utils\Response;

/**
 * Controller RelatorioController - Geração de Relatórios
 * 
 * Responsável por gerar relatórios detalhados do sistema
 */
class RelatorioController {
    
    /**
     * Relatório de atendimentos
     */
    public function atendimentos() {
        if (!Auth::isGestor()) {
            Response::json(['erro' => 'Acesso negado'], 403);
            return;
        }
        
        try {
            // Parâmetros do relatório
            $filtros = [
                'data_inicio' => $_GET['data_inicio'] ?? date('Y-m-01'),
                'data_fim' => $_GET['data_fim'] ?? date('Y-m-d'),
                'profissional_id' => $_GET['profissional_id'] ?? null,
                'servico_id' => $_GET['servico_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'formato' => $_GET['formato'] ?? 'json'
            ];
            
            $atendimentoModel = new Atendimento();
            
            // Dados principais
            $atendimentos = $atendimentoModel->relatorioDetalhado($filtros);
            
            // Estatísticas
            $estatisticas = $this->calcularEstatisticasAtendimentos($atendimentos);
            
            // Gráficos
            $graficos = [
                'por_dia' => $atendimentoModel->obterPorDia($filtros['data_inicio'], $filtros['data_fim']),
                'por_profissional' => $atendimentoModel->obterPorProfissional($filtros['data_inicio'], $filtros['data_fim']),
                'por_servico' => $atendimentoModel->obterPorServico($filtros['data_inicio'], $filtros['data_fim']),
                'por_status' => $atendimentoModel->obterPorStatus($filtros['data_inicio'], $filtros['data_fim'])
            ];
            
            $dados = [
                'filtros' => $filtros,
                'atendimentos' => $atendimentos,
                'estatisticas' => $estatisticas,
                'graficos' => $graficos,
                'total_registros' => count($atendimentos)
            ];
            
            // Retornar conforme formato solicitado
            if ($filtros['formato'] === 'excel') {
                $this->exportarAtendimentosExcel($dados);
            } elseif ($filtros['formato'] === 'pdf') {
                $this->exportarAtendimentosPdf($dados);
            } else {
                Response::json($dados);
            }
            
        } catch (Exception $e) {
            Response::json(['erro' => 'Erro ao gerar relatório: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Relatório financeiro
     */
    public function financeiro() {
        if (!Auth::isGestor()) {
            Response::json(['erro' => 'Acesso negado'], 403);
            return;
        }
        
        try {
            $filtros = [
                'data_inicio' => $_GET['data_inicio'] ?? date('Y-m-01'),
                'data_fim' => $_GET['data_fim'] ?? date('Y-m-d'),
                'profissional_id' => $_GET['profissional_id'] ?? null,
                'servico_id' => $_GET['servico_id'] ?? null,
                'formato' => $_GET['formato'] ?? 'json'
            ];
            
            $atendimentoModel = new Atendimento();
            
            // Dados financeiros
            $dados = [
                'resumo' => $this->obterResumoFinanceiro($filtros),
                'faturamento_diario' => $atendimentoModel->obterFaturamentoPorDia($filtros['data_inicio'], $filtros['data_fim']),
                'faturamento_por_profissional' => $atendimentoModel->obterFaturamentoPorProfissional($filtros['data_inicio'], $filtros['data_fim']),
                'faturamento_por_servico' => $atendimentoModel->obterFaturamentoPorServico($filtros['data_inicio'], $filtros['data_fim']),
                'top_servicos' => $atendimentoModel->obterTopServicos($filtros['data_inicio'], $filtros['data_fim'], 10),
                'comparativo_mensal' => $this->obterComparativoMensal($filtros)
            ];
            
            if ($filtros['formato'] === 'excel') {
                $this->exportarFinanceiroExcel($dados);
            } elseif ($filtros['formato'] === 'pdf') {
                $this->exportarFinanceiroPdf($dados);
            } else {
                Response::json($dados);
            }
            
        } catch (Exception $e) {
            Response::json(['erro' => 'Erro ao gerar relatório financeiro: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Relatório de profissionais
     */
    public function profissionais() {
        if (!Auth::isGestor()) {
            Response::json(['erro' => 'Acesso negado'], 403);
            return;
        }
        
        try {
            $filtros = [
                'data_inicio' => $_GET['data_inicio'] ?? date('Y-m-01'),
                'data_fim' => $_GET['data_fim'] ?? date('Y-m-d'),
                'profissional_id' => $_GET['profissional_id'] ?? null,
                'formato' => $_GET['formato'] ?? 'json'
            ];
            
            $profissionalModel = new Profissional();
            $atendimentoModel = new Atendimento();
            
            // Dados dos profissionais
            $profissionais = $profissionalModel->relatorioPerformance($filtros);
            
            // Performance por profissional
            foreach ($profissionais as &$profissional) {
                $profissional['atendimentos'] = $atendimentoModel->contarPorProfissional(
                    $profissional['id'], 
                    $filtros['data_inicio'], 
                    $filtros['data_fim']
                );
                $profissional['faturamento'] = $atendimentoModel->obterFaturamentoProfissional(
                    $profissional['id'], 
                    $filtros['data_inicio'], 
                    $filtros['data_fim']
                );
                $profissional['avaliacao_media'] = $atendimentoModel->obterAvaliacaoMedia(
                    $profissional['id'], 
                    $filtros['data_inicio'], 
                    $filtros['data_fim']
                );
            }
            
            $dados = [
                'filtros' => $filtros,
                'profissionais' => $profissionais,
                'ranking' => $this->calcularRankingProfissionais($profissionais),
                'estatisticas_gerais' => $this->calcularEstatisticasProfissionais($profissionais)
            ];
            
            if ($filtros['formato'] === 'excel') {
                $this->exportarProfissionaisExcel($dados);
            } elseif ($filtros['formato'] === 'pdf') {
                $this->exportarProfissionaisPdf($dados);
            } else {
                Response::json($dados);
            }
            
        } catch (Exception $e) {
            Response::json(['erro' => 'Erro ao gerar relatório de profissionais: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * API para dados do dashboard (usado pelo JavaScript)
     */
    public function apiDashboard() {
        if (!Auth::check()) {
            Response::json(['erro' => 'Não autenticado'], 401);
            return;
        }
        
        try {
            $periodo = $_GET['periodo'] ?? '7'; // dias
            $dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));
            $dataFim = date('Y-m-d');
            
            $atendimentoModel = new Atendimento();
            
            $dados = [
                'atendimentos_periodo' => $atendimentoModel->contarPorPeriodo($dataInicio, $dataFim),
                'faturamento_periodo' => $atendimentoModel->obterReceitaPorPeriodo($dataInicio, $dataFim),
                'atendimentos_por_dia' => $atendimentoModel->obterPorDia($dataInicio, $dataFim),
                'status_atendimentos' => $atendimentoModel->obterPorStatus($dataInicio, $dataFim),
                'top_servicos' => $atendimentoModel->obterTopServicos($dataInicio, $dataFim, 5)
            ];
            
            Response::json($dados);
            
        } catch (Exception $e) {
            Response::json(['erro' => 'Erro ao obter dados do dashboard: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Calcular estatísticas dos atendimentos
     */
    private function calcularEstatisticasAtendimentos($atendimentos) {
        $total = count($atendimentos);
        $faturamentoTotal = 0;
        $statusCounts = [];
        $duracaoTotal = 0;
        $avaliacaoTotal = 0;
        $avaliacaoCount = 0;
        
        foreach ($atendimentos as $atendimento) {
            $faturamentoTotal += floatval($atendimento['valor'] ?? 0);
            
            $status = $atendimento['status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            
            if ($atendimento['duracao']) {
                $duracaoTotal += intval($atendimento['duracao']);
            }
            
            if ($atendimento['avaliacao']) {
                $avaliacaoTotal += floatval($atendimento['avaliacao']);
                $avaliacaoCount++;
            }
        }
        
        return [
            'total_atendimentos' => $total,
            'faturamento_total' => $faturamentoTotal,
            'ticket_medio' => $total > 0 ? $faturamentoTotal / $total : 0,
            'duracao_media' => $total > 0 ? $duracaoTotal / $total : 0,
            'avaliacao_media' => $avaliacaoCount > 0 ? $avaliacaoTotal / $avaliacaoCount : 0,
            'distribuicao_status' => $statusCounts
        ];
    }
    
    /**
     * Obter resumo financeiro
     */
    private function obterResumoFinanceiro($filtros) {
        $atendimentoModel = new Atendimento();
        
        $receita = $atendimentoModel->obterReceitaPorPeriodo($filtros['data_inicio'], $filtros['data_fim']);
        $atendimentos = $atendimentoModel->contarPorPeriodo($filtros['data_inicio'], $filtros['data_fim']);
        
        // Comparar com período anterior
        $diasPeriodo = (strtotime($filtros['data_fim']) - strtotime($filtros['data_inicio'])) / (60 * 60 * 24);
        $dataInicioAnterior = date('Y-m-d', strtotime($filtros['data_inicio'] . " -{$diasPeriodo} days"));
        $dataFimAnterior = date('Y-m-d', strtotime($filtros['data_inicio'] . ' -1 day'));
        
        $receitaAnterior = $atendimentoModel->obterReceitaPorPeriodo($dataInicioAnterior, $dataFimAnterior);
        $atendimentosAnterior = $atendimentoModel->contarPorPeriodo($dataInicioAnterior, $dataFimAnterior);
        
        return [
            'receita_atual' => $receita,
            'receita_anterior' => $receitaAnterior,
            'crescimento_receita' => $receitaAnterior > 0 ? (($receita - $receitaAnterior) / $receitaAnterior) * 100 : 0,
            'atendimentos_atual' => $atendimentos,
            'atendimentos_anterior' => $atendimentosAnterior,
            'crescimento_atendimentos' => $atendimentosAnterior > 0 ? (($atendimentos - $atendimentosAnterior) / $atendimentosAnterior) * 100 : 0,
            'ticket_medio' => $atendimentos > 0 ? $receita / $atendimentos : 0
        ];
    }
    
    /**
     * Obter comparativo mensal
     */
    private function obterComparativoMensal($filtros) {
        $atendimentoModel = new Atendimento();
        $comparativo = [];
        
        // Últimos 12 meses
        for ($i = 11; $i >= 0; $i--) {
            $dataInicio = date('Y-m-01', strtotime("-{$i} months"));
            $dataFim = date('Y-m-t', strtotime("-{$i} months"));
            
            $comparativo[] = [
                'mes' => date('Y-m', strtotime("-{$i} months")),
                'mes_nome' => date('M/Y', strtotime("-{$i} months")),
                'receita' => $atendimentoModel->obterReceitaPorPeriodo($dataInicio, $dataFim),
                'atendimentos' => $atendimentoModel->contarPorPeriodo($dataInicio, $dataFim)
            ];
        }
        
        return $comparativo;
    }
    
    /**
     * Calcular ranking de profissionais
     */
    private function calcularRankingProfissionais($profissionais) {
        // Ordenar por faturamento
        usort($profissionais, function($a, $b) {
            return $b['faturamento'] <=> $a['faturamento'];
        });
        
        return array_slice($profissionais, 0, 10); // Top 10
    }
    
    /**
     * Calcular estatísticas gerais dos profissionais
     */
    private function calcularEstatisticasProfissionais($profissionais) {
        $totalAtendimentos = array_sum(array_column($profissionais, 'atendimentos'));
        $totalFaturamento = array_sum(array_column($profissionais, 'faturamento'));
        $avaliacoes = array_filter(array_column($profissionais, 'avaliacao_media'));
        
        return [
            'total_profissionais' => count($profissionais),
            'total_atendimentos' => $totalAtendimentos,
            'total_faturamento' => $totalFaturamento,
            'avaliacao_media_geral' => count($avaliacoes) > 0 ? array_sum($avaliacoes) / count($avaliacoes) : 0,
            'atendimentos_por_profissional' => count($profissionais) > 0 ? $totalAtendimentos / count($profissionais) : 0,
            'faturamento_por_profissional' => count($profissionais) > 0 ? $totalFaturamento / count($profissionais) : 0
        ];
    }
    
    /**
     * Exportar atendimentos para Excel
     */
    private function exportarAtendimentosExcel($dados) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="relatorio_atendimentos_' . date('Y-m-d') . '.xls"');
        
        echo "<html><body>";
        echo "<h1>Relatório de Atendimentos</h1>";
        echo "<p>Período: " . $dados['filtros']['data_inicio'] . " a " . $dados['filtros']['data_fim'] . "</p>";
        
        echo "<h2>Resumo</h2>";
        echo "<table border='1'>";
        echo "<tr><th>Total de Atendimentos</th><td>" . $dados['estatisticas']['total_atendimentos'] . "</td></tr>";
        echo "<tr><th>Faturamento Total</th><td>R$ " . number_format($dados['estatisticas']['faturamento_total'], 2, ',', '.') . "</td></tr>";
        echo "<tr><th>Ticket Médio</th><td>R$ " . number_format($dados['estatisticas']['ticket_medio'], 2, ',', '.') . "</td></tr>";
        echo "<tr><th>Duração Média</th><td>" . round($dados['estatisticas']['duracao_media']) . " minutos</td></tr>";
        echo "</table>";
        
        echo "<h2>Detalhamento</h2>";
        echo "<table border='1'>";
        echo "<tr><th>Data</th><th>Cliente</th><th>Profissional</th><th>Serviço</th><th>Status</th><th>Valor</th></tr>";
        
        foreach ($dados['atendimentos'] as $atendimento) {
            echo "<tr>";
            echo "<td>" . date('d/m/Y H:i', strtotime($atendimento['data_hora'])) . "</td>";
            echo "<td>" . htmlspecialchars($atendimento['cliente_nome']) . "</td>";
            echo "<td>" . htmlspecialchars($atendimento['profissional_nome']) . "</td>";
            echo "<td>" . htmlspecialchars($atendimento['servico_nome']) . "</td>";
            echo "<td>" . htmlspecialchars($atendimento['status']) . "</td>";
            echo "<td>R$ " . number_format($atendimento['valor'], 2, ',', '.') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</body></html>";
    }
    
    /**
     * Exportar financeiro para Excel
     */
    private function exportarFinanceiroExcel($dados) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="relatorio_financeiro_' . date('Y-m-d') . '.xls"');
        
        echo "<html><body>";
        echo "<h1>Relatório Financeiro</h1>";
        
        echo "<h2>Resumo</h2>";
        echo "<table border='1'>";
        echo "<tr><th>Receita Atual</th><td>R$ " . number_format($dados['resumo']['receita_atual'], 2, ',', '.') . "</td></tr>";
        echo "<tr><th>Receita Anterior</th><td>R$ " . number_format($dados['resumo']['receita_anterior'], 2, ',', '.') . "</td></tr>";
        echo "<tr><th>Crescimento</th><td>" . number_format($dados['resumo']['crescimento_receita'], 2, ',', '.') . "%</td></tr>";
        echo "<tr><th>Ticket Médio</th><td>R$ " . number_format($dados['resumo']['ticket_medio'], 2, ',', '.') . "</td></tr>";
        echo "</table>";
        
        echo "</body></html>";
    }
    
    /**
     * Métodos de exportação PDF (implementação futura)
     */
    private function exportarAtendimentosPdf($dados) {
        Response::json(['erro' => 'Exportação PDF em desenvolvimento'], 501);
    }
    
    private function exportarFinanceiroPdf($dados) {
        Response::json(['erro' => 'Exportação PDF em desenvolvimento'], 501);
    }
    
    private function exportarProfissionaisExcel($dados) {
        Response::json(['erro' => 'Exportação Excel para profissionais em desenvolvimento'], 501);
    }
    
    private function exportarProfissionaisPdf($dados) {
        Response::json(['erro' => 'Exportação PDF em desenvolvimento'], 501);
    }
}

?>