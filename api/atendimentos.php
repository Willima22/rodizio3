<?php

/**
 * API de Atendimentos - Fast Escova
 * 
 * Gerencia operações CRUD de atendimentos via API RESTful
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
require_once __DIR__ . '/../src/Controllers/AtendimentoController.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Response.php';
require_once __DIR__ . '/../src/Utils/Validator.php';

use FastEscova\Controllers\AtendimentoController;
use FastEscova\Utils\Auth;
use FastEscova\Utils\Response;
use FastEscova\Utils\Validator;

// Verificar autenticação
if (!Auth::check()) {
    Response::json(['erro' => 'Acesso não autorizado'], 401);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$controller = new AtendimentoController();

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
            // Listar atendimentos com filtros
            $filtros = [
                'data_inicio' => $_GET['data_inicio'] ?? null,
                'data_fim' => $_GET['data_fim'] ?? null,
                'profissional_id' => $_GET['profissional_id'] ?? null,
                'cliente_id' => $_GET['cliente_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'servico_id' => $_GET['servico_id'] ?? null,
                'page' => (int)($_GET['page'] ?? 1),
                'limit' => (int)($_GET['limit'] ?? 20)
            ];
            
            $controller->apiIndex($filtros);
            break;
            
        case 'get':
            // Buscar atendimento específico
            $id = (int)($_GET['id'] ?? 0);
            if ($id > 0) {
                $controller->apiGet($id);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'fila':
            // Obter fila de atendimento
            $controller->apiFilaAtendimento();
            break;
            
        case 'agenda':
            // Obter agenda do profissional
            $profissionalId = (int)($_GET['profissional_id'] ?? 0);
            $data = $_GET['data'] ?? date('Y-m-d');
            
            if ($profissionalId > 0) {
                $controller->apiAgenda($profissionalId, $data);
            } else {
                Response::json(['erro' => 'ID do profissional inválido'], 400);
            }
            break;
            
        case 'horarios':
            // Obter horários disponíveis
            $profissionalId = (int)($_GET['profissional_id'] ?? 0);
            $servicoId = (int)($_GET['servico_id'] ?? 0);
            $data = $_GET['data'] ?? date('Y-m-d');
            
            if ($profissionalId > 0 && $servicoId > 0) {
                $controller->apiHorariosDisponiveis($profissionalId, $servicoId, $data);
            } else {
                Response::json(['erro' => 'Parâmetros inválidos'], 400);
            }
            break;
            
        case 'estatisticas':
            // Obter estatísticas
            $periodo = $_GET['periodo'] ?? '30'; // dias
            $controller->apiEstatisticas($periodo);
            break;
            
        default:
            Response::json(['erro' => 'Ação não encontrada'], 404);
    }
}

/**
 * Lidar com requisições POST
 */
function handlePost($controller) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        Response::json(['erro' => 'Dados inválidos'], 400);
        return;
    }
    
    $action = $data['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            // Criar novo atendimento
            $validator = new Validator();
            
            $rules = [
                'cliente_id' => 'required|integer|min:1',
                'profissional_id' => 'required|integer|min:1',
                'servico_id' => 'required|integer|min:1',
                'data_hora' => 'required|datetime',
                'observacoes' => 'string|max:500'
            ];
            
            $validation = $validator->validate($data, $rules);
            
            if (!$validation['valid']) {
                Response::json(['erro' => 'Dados inválidos', 'detalhes' => $validation['errors']], 400);
                return;
            }
            
            $controller->apiStore($data);
            break;
            
        case 'chamar_proximo':
            // Chamar próximo da fila
            if (!Auth::isRecepcao() && !Auth::isGestor()) {
                Response::json(['erro' => 'Permissão insuficiente'], 403);
                return;
            }
            
            $controller->apiChamarProximo();
            break;
            
        case 'iniciar_atendimento':
            // Iniciar atendimento
            $id = (int)($data['id'] ?? 0);
            if ($id > 0) {
                $controller->apiIniciarAtendimento($id);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'finalizar_atendimento':
            // Finalizar atendimento
            $id = (int)($data['id'] ?? 0);
            $observacoes = $data['observacoes'] ?? '';
            $avaliacao = $data['avaliacao'] ?? null;
            
            if ($id > 0) {
                $controller->apiFinalizarAtendimento($id, $observacoes, $avaliacao);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'remarcar':
            // Remarcar atendimento
            $id = (int)($data['id'] ?? 0);
            $novaDataHora = $data['nova_data_hora'] ?? '';
            $motivo = $data['motivo'] ?? '';
            
            if ($id > 0 && $novaDataHora) {
                $controller->apiRemarcar($id, $novaDataHora, $motivo);
            } else {
                Response::json(['erro' => 'Parâmetros inválidos'], 400);
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
    
    // Validar dados
    $validator = new Validator();
    
    $rules = [
        'cliente_id' => 'integer|min:1',
        'profissional_id' => 'integer|min:1',
        'servico_id' => 'integer|min:1',
        'data_hora' => 'datetime',
        'status' => 'in:agendado,confirmado,em_andamento,finalizado,cancelado',
        'observacoes' => 'string|max:500'
    ];
    
    $validation = $validator->validate($data, $rules);
    
    if (!$validation['valid']) {
        Response::json(['erro' => 'Dados inválidos', 'detalhes' => $validation['errors']], 400);
        return;
    }
    
    $controller->apiUpdate($id, $data);
}

/**
 * Lidar com requisições DELETE
 */
function handleDelete($controller) {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        Response::json(['erro' => 'ID inválido'], 400);
        return;
    }
    
    // Apenas gestores podem deletar
    if (!Auth::isGestor()) {
        Response::json(['erro' => 'Permissão insuficiente'], 403);
        return;
    }
    
    $controller->apiDelete($id);
}

/**
 * Validar data/hora
 */
function isValidDateTime($datetime) {
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    return $d && $d->format('Y-m-d H:i:s') === $datetime;
}

/**
 * Logs de API para auditoria
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
    
    // Log para arquivo ou banco de dados
    error_log('API_ATENDIMENTOS: ' . json_encode($logData));
}

?>