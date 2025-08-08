<?php

/**
 * API de Serviços - Fast Escova
 * 
 * Gerencia operações CRUD de serviços via API RESTful
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Lidar com preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/Controllers/ServicoController.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Response.php';
require_once __DIR__ . '/../src/Utils/Validator.php';
require_once __DIR__ . '/../src/Utils/Sanitizer.php';

use FastEscova\Controllers\ServicoController;
use FastEscova\Utils\Auth;
use FastEscova\Utils\Response;
use FastEscova\Utils\Validator;
use FastEscova\Utils\Sanitizer;

// Verificar autenticação
if (!Auth::check()) {
    Response::json(['erro' => 'Acesso não autorizado'], 401);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$controller = new ServicoController();

try {
    switch ($method) {
        case 'GET':
            handleGet($controller);
            break;
            
        case 'POST':
            handlePost($controller);
            break;
            
        case 'PUT':
            handlePut($controller);
            break;
            
        case 'DELETE':
            handleDelete($controller);
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
function handleGet($controller) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // Listar serviços com filtros
            $filtros = [
                'nome' => $_GET['nome'] ?? null,
                'categoria' => $_GET['categoria'] ?? null,
                'status' => $_GET['status'] ?? null,
                'preco_min' => $_GET['preco_min'] ?? null,
                'preco_max' => $_GET['preco_max'] ?? null,
                'duracao_min' => $_GET['duracao_min'] ?? null,
                'duracao_max' => $_GET['duracao_max'] ?? null,
                'page' => (int)($_GET['page'] ?? 1),
                'limit' => (int)($_GET['limit'] ?? 20),
                'orderBy' => $_GET['orderBy'] ?? 'nome',
                'orderDir' => $_GET['orderDir'] ?? 'ASC'
            ];
            
            $controller->apiIndex($filtros);
            break;
            
        case 'get':
            // Buscar serviço específico
            $id = (int)($_GET['id'] ?? 0);
            if ($id > 0) {
                $controller->apiGet($id);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'ativos':
            // Listar apenas serviços ativos
            $categoria = $_GET['categoria'] ?? null;
            $controller->apiServicosAtivos($categoria);
            break;
            
        case 'categorias':
            // Lista de categorias únicas
            $controller->apiCategorias();
            break;
            
        case 'mais_realizados':
            // Serviços mais realizados
            $periodo = $_GET['periodo'] ?? '30'; // dias
            $limite = (int)($_GET['limite'] ?? 10);
            
            $controller->apiMaisRealizados($periodo, $limite);
            break;
            
        case 'estatisticas':
            // Estatísticas de serviços
            $periodo = $_GET['periodo'] ?? '30';
            $controller->apiEstatisticas($periodo);
            break;
            
        case 'preco_medio':
            // Preço médio por categoria
            $controller->apiPrecoMedio();
            break;
            
        case 'duracao_media':
            // Duração média por categoria
            $controller->apiDuracaoMedia();
            break;
            
        case 'profissionais':
            // Profissionais que realizam o serviço
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id > 0) {
                $controller->apiProfissionaisServico($id);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'combos':
            // Serviços que são frequentemente realizados juntos
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id > 0) {
                $controller->apiServicosCombinados($id);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'agenda_disponivel':
            // Verificar disponibilidade de agenda para o serviço
            $id = (int)($_GET['id'] ?? 0);
            $data = $_GET['data'] ?? date('Y-m-d');
            $profissionalId = (int)($_GET['profissional_id'] ?? 0);
            
            if ($id > 0) {
                $controller->apiAgendaDisponivel($id, $data, $profissionalId);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'search':
            // Busca rápida por nome ou descrição
            $termo = $_GET['termo'] ?? '';
            if (strlen($termo) >= 2) {
                $controller->apiBuscaRapida($termo);
            } else {
                Response::json(['erro' => 'Termo de busca deve ter pelo menos 2 caracteres'], 400);
            }
            break;
            
        default:
            Response::json(['erro' => 'Ação não encontrada'], 404);
    }
}

/**
 * Lidar com requisições POST
 */
function handlePost($controller) {
    // Apenas gestores podem criar serviços
    if (!Auth::isGestor()) {
        Response::json(['erro' => 'Permissão insuficiente'], 403);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        Response::json(['erro' => 'Dados inválidos'], 400);
        return;
    }
    
    $action = $data['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            // Criar novo serviço
            $validator = new Validator();
            $sanitizer = new Sanitizer();
            
            // Sanitizar dados
            $data = $sanitizer->sanitizeArray($data, [
                'nome' => 'string',
                'descricao' => 'string',
                'categoria' => 'string',
                'preco' => 'decimal',
                'duracao_minutos' => 'integer',
                'comissao_percentual' => 'decimal',
                'observacoes' => 'string'
            ]);
            
            // Validar dados
            $rules = [
                'nome' => 'required|string|min:2|max:100',
                'descricao' => 'string|max:500',
                'categoria' => 'required|string|max:50',
                'preco' => 'required|numeric|min:0.01',
                'duracao_minutos' => 'required|integer|min:5|max:480',
                'comissao_percentual' => 'numeric|min:0|max:100',
                'observacoes' => 'string|max:500'
            ];
            
            $validation = $validator->validate($data, $rules);
            
            if (!$validation['valid']) {
                Response::json(['erro' => 'Dados inválidos', 'detalhes' => $validation['errors']], 400);
                return;
            }
            
            $controller->apiStore($data);
            break;
            
        case 'duplicar':
            // Duplicar serviço existente
            $servicoId = (int)($data['servico_id'] ?? 0);
            $novoNome = $data['novo_nome'] ?? '';
            
            if ($servicoId > 0 && $novoNome) {
                $controller->apiDuplicar($servicoId, $novoNome);
            } else {
                Response::json(['erro' => 'Dados inválidos'], 400);
            }
            break;
            
        case 'importar':
            // Importar serviços em lote
            $servicos = $data['servicos'] ?? [];
            
            if (empty($servicos)) {
                Response::json(['erro' => 'Lista de serviços vazia'], 400);
                return;
            }
            
            $controller->apiImportarServicos($servicos);
            break;
            
        case 'combo':
            // Criar combo de serviços
            $nome = $data['nome'] ?? '';
            $servicosIds = $data['servicos_ids'] ?? [];
            $desconto = $data['desconto'] ?? 0;
            
            if ($nome && !empty($servicosIds)) {
                $controller->apiCriarCombo($nome, $servicosIds, $desconto);
            } else {
                Response::json(['erro' => 'Dados inválidos'], 400);
            }
            break;
            
        default:
            Response::json(['erro' => 'Ação não encontrada'], 404);
    }
}

/**
 * Lidar com requisições PUT
 */
function handlePut($controller) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['id'])) {
        Response::json(['erro' => 'Dados inválidos'], 400);
        return;
    }
    
    $id = (int)$data['id'];
    
    if ($id <= 0) {
        Response::json(['erro' => 'ID inválido'], 400);
        return;
    }
    
    $action = $data['action'] ?? 'update';
    
    switch ($action) {
        case 'update':
            // Atualizar serviço
            if (!Auth::isGestor()) {
                Response::json(['erro' => 'Permissão insuficiente'], 403);
                return;
            }
            
            $validator = new Validator();
            $sanitizer = new Sanitizer();
            
            // Sanitizar dados
            $data = $sanitizer->sanitizeArray($data, [
                'nome' => 'string',
                'descricao' => 'string',
                'categoria' => 'string',
                'preco' => 'decimal',
                'duracao_minutos' => 'integer',
                'comissao_percentual' => 'decimal',
                'observacoes' => 'string',
                'status' => 'string'
            ]);
            
            // Validar dados
            $rules = [
                'nome' => 'string|min:2|max:100',
                'descricao' => 'string|max:500',
                'categoria' => 'string|max:50',
                'preco' => 'numeric|min:0.01',
                'duracao_minutos' => 'integer|min:5|max:480',
                'comissao_percentual' => 'numeric|min:0|max:100',
                'observacoes' => 'string|max:500',
                'status' => 'in:ativo,inativo,descontinuado'
            ];
            
            $validation = $validator->validate($data, $rules);
            
            if (!$validation['valid']) {
                Response::json(['erro' => 'Dados inválidos', 'detalhes' => $validation['errors']], 400);
                return;
            }
            
            $controller->apiUpdate($id, $data);
            break;
            
        case 'ativar':
            // Ativar serviço
            if (!Auth::isGestor()) {
                Response::json(['erro' => 'Permissão insuficiente'], 403);
                return;
            }
            
            $controller->apiAtivar($id);
            break;
            
        case 'inativar':
            // Inativar serviço
            if (!Auth::isGestor()) {
                Response::json(['erro' => 'Permissão insuficiente'], 403);
                return;
            }
            
            $motivo = $data['motivo'] ?? '';
            $controller->apiInativar($id, $motivo);
            break;
            
        case 'ajustar_preco':
            // Ajustar preço
            if (!Auth::isGestor()) {
                Response::json(['erro' => 'Permissão insuficiente'], 403);
                return;
            }
            
            $novoPreco = $data['novo_preco'] ?? 0;
            $motivo = $data['motivo'] ?? '';
            
            if ($novoPreco > 0) {
                $controller->apiAjustarPreco($id, $novoPreco, $motivo);
            } else {
                Response::json(['erro' => 'Preço inválido'], 400);
            }
            break;
            
        case 'vincular_profissionais':
            // Vincular profissionais ao serviço
            if (!Auth::isGestor()) {
                Response::json(['erro' => 'Permissão insuficiente'], 403);
                return;
            }
            
            $profissionaisIds = $data['profissionais_ids'] ?? [];
            
            if (!empty($profissionaisIds)) {
                $controller->apiVincularProfissionais($id, $profissionaisIds);
            } else {
                Response::json(['erro' => 'Lista de profissionais vazia'], 400);
            }
            break;
            
        default:
            Response::json(['erro' => 'Ação não encontrada'], 404);
    }
}

/**
 * Lidar com requisições DELETE
 */
function handleDelete($controller) {
    // Apenas gestores podem deletar
    if (!Auth::isGestor()) {
        Response::json(['erro' => 'Permissão insuficiente'], 403);
        return;
    }
    
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        Response::json(['erro' => 'ID inválido'], 400);
        return;
    }
    
    $controller->apiDelete($id);
}

/**
 * Validar duração em minutos
 */
function validarDuracao($duracao) {
    return is_numeric($duracao) && $duracao >= 5 && $duracao <= 480 && $duracao % 5 === 0;
}

/**
 * Validar preço
 */
function validarPreco($preco) {
    return is_numeric($preco) && $preco > 0 && $preco <= 9999.99;
}

/**
 * Formatar preço
 */
function formatarPreco($preco) {
    return 'R$ ' . number_format($preco, 2, ',', '.');
}

/**
 * Formatar duração
 */
function formatarDuracao($minutos) {
    $horas = floor($minutos / 60);
    $mins = $minutos % 60;
    
    if ($horas > 0) {
        return $horas . 'h' . ($mins > 0 ? ' ' . $mins . 'min' : '');
    }
    
    return $mins . 'min';
}

/**
 * Calcular desconto de combo
 */
function calcularDescontoCombo($servicosIds, $percentualDesconto) {
    // Implementar lógica de cálculo de desconto
    // Por enquanto, retorna valores fictícios
    return [
        'valor_original' => 100.00,
        'valor_desconto' => 15.00,
        'valor_final' => 85.00,
        'economia' => 15.00
    ];
}

/**
 * Logs de API
 */
function logApiCall($method, $action, $data = null) {
    $user = Auth::user();
    $logData = [
        'usuario_id' => $user['id'],
        'method' => $method,
        'action' => $action,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log('API_SERVICOS: ' . json_encode($logData));
}

?>