<?php

/**
 * API de Relatórios - Fast Escova
 * 
 * Fornece dados para relatórios e dashboards em tempo real
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Lidar com preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/Controllers/RelatorioController.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Response.php';
require_once __DIR__ . '/../src/Models/Atendimento.php';
require_once __DIR__ . '/../src/Models/Cliente.php';
require_once __DIR__ . '/../src/Models/Profissional.php';
require_once __DIR__ . '/../src/Models/Servico.php';

use FastEscova\Controllers\RelatorioController;
use FastEscova\Utils\Auth;
use FastEscova\Utils\Response;
use FastEscova\Models\Atendimento;
use FastEscova\Models\Cliente;
use FastEscova\Models\Profissional;
use FastEscova\Models\Servico;

// Verificar autenticação
if (!Auth::check()) {
    Response::json(['erro' => 'Acesso não autorizado'], 401);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
            
        case 'POST':
            handlePost();
            break;
            
        default:
            Response::json(['erro' => 'Método não permitido'], 405);
    }
    
} catch (Exception $e) {
    Response::json(['erro' => 'Erro interno: ' . $e->getMessage()], 500);
}

/**
 * Lidar com requisições GET
 */
function handleGet($action) {
    switch ($action) {
        case 'dashboard':
            // Dados para dashboard principal
            getDashboardData();
            break;
            
        case 'kpis':
            // KPIs principais
            getKPIs();
            break;
            
        case 'atendimentos_hoje':
            // Atendimentos de hoje
            getAtendimentosHoje();
            break;
            
        case 'faturamento_periodo':
            // Faturamento por período
            getFaturamentoPeriodo();
            break;
            
        case 'ranking_profissionais':
            // Ranking de profissionais
            getRankingProfissionais();
            break;
            
        case 'servicos_populares':
            // Serviços mais populares
            getServicosPopulares();
            break;
            
        case 'clientes_novos':
            // Novos clientes
            getClientesNovos();
            break;
            
        case 'ocupacao_profissionais':
            // Taxa de ocupação dos profissionais
            getOcupacaoProfissionais();
            break;
            
        case 'agenda_semana':
            // Agenda da semana
            getAgendaSemana();
            break;
            
        case 'alertas':
            // Alertas e notificações
            getAlertas();
            break;
            
        case 'comparativo_mensal':
            // Comparativo mensal
            getComparativoMensal();
            break;
            
        case 'satisfacao_clientes':
            // Satisfação dos clientes
            getSatisfacaoClientes();
            break;
            
        case 'tempo_real':
            // Dados em tempo real
            getDadosTempoReal();
            break;
            
        default:
            Response::json(['erro' => 'Ação não encontrada'], 404);
    }
}

/**
 * Lidar com requisições POST
 */
function handlePost() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        Response::json(['erro' => 'Dados inválidos'], 400);
        return;
    }
    
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'personalizar_dashboard':
            // Personalizar layout do dashboard
            personalizarDashboard($data);
            break;
            
        case 'salvar_filtros':
            // Salvar filtros personalizados
            salvarFiltros($data);
            break;
            
        case 'exportar_dados':
            // Exportar dados customizados
            exportarDados($data);
            break;
            
        default:
            Response::json(['erro' => 'Ação não encontrada'], 404);
    }
}

/**
 * Dados para dashboard principal
 */
function getDashboardData() {
    $periodo = $_GET['periodo'] ?? '7'; // dias
    $dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));
    $dataFim = date('Y-m-d');
    
    $atendimentoModel = new Atendimento();
    $clienteModel = new Cliente();
    $profissionalModel = new Profissional();
    $servicoModel = new Servico();
    
    $dados = [
        'resumo' => [
            'atendimentos_periodo' => $atendimentoModel->contarPorPeriodo($dataInicio, $dataFim),
            'faturamento_periodo' => $atendimentoModel->obterReceitaPorPeriodo($dataInicio, $dataFim),
            'novos_clientes' => $clienteModel->contarNovosPorPeriodo($dataInicio, $dataFim),
            'ticket_medio' => $atendimentoModel->obterTicketMedio($dataInicio, $dataFim),
            'satisfacao_media' => $atendimentoModel->obterSatisfacaoMedia($dataInicio, $dataFim)
        ],
        
        'graficos' => [
            'atendimentos_por_dia' => $atendimentoModel->obterPorDia($dataInicio, $dataFim),
            'faturamento_por_dia' => $atendimentoModel->obterFaturamentoPorDia($dataInicio, $dataFim),
            'servicos_mais_realizados' => $atendimentoModel->obterTopServicos($dataInicio, $dataFim, 5),
            'status_atendimentos' => $atendimentoModel->obterPorStatus($dataInicio, $dataFim)
        ],
        
        'listas' => [
            'proximos_atendimentos' => $atendimentoModel->obterProximos(5),
            'em_andamento' => $atendimentoModel->obterEmAndamento(),
            'profissionais_ocupados' => $profissionalModel->obterOcupados()
        ],
        
        'performance' => [
            'taxa_ocupacao' => calcularTaxaOcupacao($dataInicio, $dataFim),
            'tempo_medio_atendimento' => $atendimentoModel->obterTempoMedioAtendimento($dataInicio, $dataFim),
            'conversao_agendamento' => calcularTaxaConversao($dataInicio, $dataFim)
        ]
    ];
    
    Response::json($dados);
}

/**
 * KPIs principais
 */
function getKPIs() {
    $periodo = $_GET['periodo'] ?? '30';
    $dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));
    $dataFim = date('Y-m-d');
    
    $atendimentoModel = new Atendimento();
    $clienteModel = new Cliente();
    
    // Período anterior para comparação
    $dataInicioAnterior = date('Y-m-d', strtotime($dataInicio . " -{$periodo} days"));
    $dataFimAnterior = date('Y-m-d', strtotime($dataInicio . ' -1 day'));
    
    $kpis = [
        'faturamento' => [
            'atual' => $atendimentoModel->obterReceitaPorPeriodo($dataInicio, $dataFim),
            'anterior' => $atendimentoModel->obterReceitaPorPeriodo($dataInicioAnterior, $dataFimAnterior),
            'meta' => 50000.00 // Meta configurável
        ],
        'atendimentos' => [
            'atual' => $atendimentoModel->contarPorPeriodo($dataInicio, $dataFim),
            'anterior' => $atendimentoModel->contarPorPeriodo($dataInicioAnterior, $dataFimAnterior),
            'meta' => 500 // Meta configurável
        ],
        'novos_clientes' => [
            'atual' => $clienteModel->contarNovosPorPeriodo($dataInicio, $dataFim),
            'anterior' => $clienteModel->contarNovosPorPeriodo($dataInicioAnterior, $dataFimAnterior),
            'meta' => 50 // Meta configurável
        ],
        'ticket_medio' => [
            'atual' => $atendimentoModel->obterTicketMedio($dataInicio, $dataFim),
            'anterior' => $atendimentoModel->obterTicketMedio($dataInicioAnterior, $dataFimAnterior),
            'meta' => 80.00 // Meta configurável
        ]
    ];
    
    // Calcular percentuais de crescimento
    foreach ($kpis as $kpi => &$dados) {
        if ($dados['anterior'] > 0) {
            $dados['crescimento'] = (($dados['atual'] - $dados['anterior']) / $dados['anterior']) * 100;
        } else {
            $dados['crescimento'] = $dados['atual'] > 0 ? 100 : 0;
        }
        
        $dados['progresso_meta'] = ($dados['atual'] / $dados['meta']) * 100;
    }
    
    Response::json($kpis);
}

/**
 * Atendimentos de hoje
 */
function getAtendimentosHoje() {
    $atendimentoModel = new Atendimento();
    $hoje = date('Y-m-d');
    
    $dados = [
        'total' => $atendimentoModel->contarPorPeriodo($hoje, $hoje),
        'concluidos' => $atendimentoModel->contarPorStatus('finalizado', $hoje, $hoje),
        'em_andamento' => $atendimentoModel->contarPorStatus('em_andamento', $hoje, $hoje),
        'agendados' => $atendimentoModel->contarPorStatus('agendado', $hoje, $hoje),
        'cancelados' => $atendimentoModel->contarPorStatus('cancelado', $hoje, $hoje),
        'lista' => $atendimentoModel->obterPorDia($hoje, $hoje, true)
    ];
    
    Response::json($dados);
}

/**
 * Faturamento por período
 */
function getFaturamentoPeriodo() {
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
    $agrupamento = $_GET['agrupamento'] ?? 'dia'; // dia, semana, mes
    
    $atendimentoModel = new Atendimento();
    
    switch ($agrupamento) {
        case 'semana':
            $dados = $atendimentoModel->obterFaturamentoPorSemana($dataInicio, $dataFim);
            break;
        case 'mes':
            $dados = $atendimentoModel->obterFaturamentoPorMes($dataInicio, $dataFim);
            break;
        default:
            $dados = $atendimentoModel->obterFaturamentoPorDia($dataInicio, $dataFim);
    }
    
    Response::json($dados);
}

/**
 * Ranking de profissionais
 */
function getRankingProfissionais() {
    $periodo = $_GET['periodo'] ?? '30';
    $metrica = $_GET['metrica'] ?? 'faturamento'; // faturamento, atendimentos, avaliacao
    $limite = (int)($_GET['limite'] ?? 10);
    
    $dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));
    $dataFim = date('Y-m-d');
    
    $atendimentoModel = new Atendimento();
    
    switch ($metrica) {
        case 'atendimentos':
            $ranking = $atendimentoModel->obterRankingPorAtendimentos($dataInicio, $dataFim, $limite);
            break;
        case 'avaliacao':
            $ranking = $atendimentoModel->obterRankingPorAvaliacao($dataInicio, $dataFim, $limite);
            break;
        default:
            $ranking = $atendimentoModel->obterRankingPorFaturamento($dataInicio, $dataFim, $limite);
    }
    
    Response::json($ranking);
}

/**
 * Serviços mais populares
 */
function getServicosPopulares() {
    $periodo = $_GET['periodo'] ?? '30';
    $limite = (int)($_GET['limite'] ?? 10);
    
    $dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));
    $dataFim = date('Y-m-d');
    
    $atendimentoModel = new Atendimento();
    $servicos = $atendimentoModel->obterTopServicos($dataInicio, $dataFim, $limite);
    
    Response::json($servicos);
}

/**
 * Novos clientes
 */
function getClientesNovos() {
    $periodo = $_GET['periodo'] ?? '30';
    $limite = (int)($_GET['limite'] ?? 10);
    
    $dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));
    $dataFim = date('Y-m-d');
    
    $clienteModel = new Cliente();
    $clientes = $clienteModel->obterNovosClientes($dataInicio, $dataFim, $limite);
    
    Response::json($clientes);
}

/**
 * Taxa de ocupação dos profissionais
 */
function getOcupacaoProfissionais() {
    $data = $_GET['data'] ?? date('Y-m-d');
    
    $profissionalModel = new Profissional();
    $ocupacao = $profissionalModel->obterTaxaOcupacao($data);
    
    Response::json($ocupacao);
}

/**
 * Agenda da semana
 */
function getAgendaSemana() {
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('monday this week'));
    $dataFim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('sunday this week'));
    $profissionalId = (int)($_GET['profissional_id'] ?? 0);
    
    $atendimentoModel = new Atendimento();
    
    if ($profissionalId > 0) {
        $agenda = $atendimentoModel->obterAgendaProfissional($profissionalId, $dataInicio, $dataFim);
    } else {
        $agenda = $atendimentoModel->obterAgendaGeral($dataInicio, $dataFim);
    }
    
    Response::json($agenda);
}

/**
 * Alertas e notificações
 */
function getAlertas() {
    $atendimentoModel = new Atendimento();
    $clienteModel = new Cliente();
    $profissionalModel = new Profissional();
    
    $alertas = [
        'atendimentos_atrasados' => $atendimentoModel->contarAtrasados(),
        'atendimentos_cancelados_hoje' => $atendimentoModel->contarCanceladosHoje(),
        'clientes_aniversariantes' => $clienteModel->contarAniversariantesHoje(),
        'profissionais_inativos' => $profissionalModel->contarInativos(),
        'agenda_lotada' => $atendimentoModel->verificarAgendaLotada(),
        'baixa_ocupacao' => $profissionalModel->verificarBaixaOcupacao()
    ];
    
    Response::json($alertas);
}

/**
 * Comparativo mensal
 */
function getComparativoMensal() {
    $meses = (int)($_GET['meses'] ?? 12);
    $atendimentoModel = new Atendimento();
    
    $comparativo = [];
    
    for ($i = $meses - 1; $i >= 0; $i--) {
        $dataInicio = date('Y-m-01', strtotime("-{$i} months"));
        $dataFim = date('Y-m-t', strtotime("-{$i} months"));
        
        $comparativo[] = [
            'mes' => date('Y-m', strtotime("-{$i} months")),
            'mes_nome' => date('M/Y', strtotime("-{$i} months")),
            'faturamento' => $atendimentoModel->obterReceitaPorPeriodo($dataInicio, $dataFim),
            'atendimentos' => $atendimentoModel->contarPorPeriodo($dataInicio, $dataFim),
            'novos_clientes' => (new Cliente())->contarNovosPorPeriodo($dataInicio, $dataFim)
        ];
    }
    
    Response::json($comparativo);
}

/**
 * Satisfação dos clientes
 */
function getSatisfacaoClientes() {
    $periodo = $_GET['periodo'] ?? '30';
    $dataInicio = date('Y-m-d', strtotime("-{$periodo} days"));
    $dataFim = date('Y-m-d');
    
    $atendimentoModel = new Atendimento();
    
    $dados = [
        'media_geral' => $atendimentoModel->obterSatisfacaoMedia($dataInicio, $dataFim),
        'distribuicao' => $atendimentoModel->obterDistribuicaoAvaliacoes($dataInicio, $dataFim),
        'por_profissional' => $atendimentoModel->obterSatisfacaoPorProfissional($dataInicio, $dataFim),
        'por_servico' => $atendimentoModel->obterSatisfacaoPorServico($dataInicio, $dataFim),
        'evolucao' => $atendimentoModel->obterEvolucaoSatisfacao($dataInicio, $dataFim)
    ];
    
    Response::json($dados);
}

/**
 * Dados em tempo real
 */
function getDadosTempoReal() {
    $atendimentoModel = new Atendimento();
    $hoje = date('Y-m-d');
    
    $dados = [
        'atendimentos_hoje' => $atendimentoModel->contarPorPeriodo($hoje, $hoje),
        'em_andamento' => $atendimentoModel->contarPorStatus('em_andamento', $hoje, $hoje),
        'fila_espera' => $atendimentoModel->contarFilaEspera(),
        'faturamento_hoje' => $atendimentoModel->obterReceitaPorPeriodo($hoje, $hoje),
        'proximos_30min' => $atendimentoModel->contarProximos30Minutos(),
        'ultima_atualizacao' => date('Y-m-d H:i:s')
    ];
    
    Response::json($dados);
}

/**
 * Personalizar dashboard
 */
function personalizarDashboard($data) {
    if (!Auth::isGestor()) {
        Response::json(['erro' => 'Permissão insuficiente'], 403);
        return;
    }
    
    $user = Auth::user();
    $layout = $data['layout'] ?? [];
    
    // Salvar configuração no banco/arquivo
    // Por enquanto, apenas retorna sucesso
    Response::json(['sucesso' => true, 'mensagem' => 'Layout salvo com sucesso']);
}

/**
 * Salvar filtros personalizados
 */
function salvarFiltros($data) {
    $user = Auth::user();
    $filtros = $data['filtros'] ?? [];
    $nome = $data['nome'] ?? 'Filtro Personalizado';
    
    // Salvar filtros no banco/arquivo
    // Por enquanto, apenas retorna sucesso
    Response::json(['sucesso' => true, 'mensagem' => 'Filtros salvos com sucesso']);
}

/**
 * Exportar dados customizados
 */
function exportarDados($data) {
    if (!Auth::isGestor()) {
        Response::json(['erro' => 'Permissão insuficiente'], 403);
        return;
    }
    
    $tipo = $data['tipo'] ?? 'excel';
    $dados = $data['dados'] ?? [];
    $nome = $data['nome'] ?? 'relatorio_customizado';
    
    // Gerar link de download
    $linkDownload = '/downloads/' . $nome . '_' . date('Y-m-d_H-i-s') . '.' . $tipo;
    
    Response::json([
        'sucesso' => true,
        'link_download' => $linkDownload,
        'mensagem' => 'Relatório gerado com sucesso'
    ]);
}

/**
 * Calcular taxa de ocupação
 */
function calcularTaxaOcupacao($dataInicio, $dataFim) {
    $atendimentoModel = new Atendimento();
    $profissionalModel = new Profissional();
    
    $horasAtendimento = $atendimentoModel->obterHorasTotaisAtendimento($dataInicio, $dataFim);
    $profissionaisAtivos = $profissionalModel->contarAtivos();
    $diasUteis = contarDiasUteis($dataInicio, $dataFim);
    $horasDisponiveis = $profissionaisAtivos * $diasUteis * 8; // 8h por dia
    
    return $horasDisponiveis > 0 ? ($horasAtendimento / $horasDisponiveis) * 100 : 0;
}

/**
 * Calcular taxa de conversão
 */
function calcularTaxaConversao($dataInicio, $dataFim) {
    $atendimentoModel = new Atendimento();
    
    $agendados = $atendimentoModel->contarPorStatus('agendado', $dataInicio, $dataFim);
    $realizados = $atendimentoModel->contarPorStatus('finalizado', $dataInicio, $dataFim);
    
    return $agendados > 0 ? ($realizados / $agendados) * 100 : 0;
}

/**
 * Contar dias úteis
 */
function contarDiasUteis($dataInicio, $dataFim) {
    $inicio = new DateTime($dataInicio);
    $fim = new DateTime($dataFim);
    $diasUteis = 0;
    
    while ($inicio <= $fim) {
        $diaSemana = $inicio->format('N');
        if ($diaSemana < 6) { // Segunda a sexta
            $diasUteis++;
        }
        $inicio->add(new DateInterval('P1D'));
    }
    
    return $diasUteis;
}

?>