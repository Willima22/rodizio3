<?php

/**
 * API de Profissionais - Fast Escova
 * 
 * Gerencia operações CRUD de profissionais via API RESTful
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
require_once __DIR__ . '/../src/Controllers/ProfissionalController.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Response.php';
require_once __DIR__ . '/../src/Utils/Validator.php';
require_once __DIR__ . '/../src/Utils/Sanitizer.php';

use FastEscova\Controllers\ProfissionalController;
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
$controller = new ProfissionalController();

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
            // Listar profissionais com filtros
            $filtros = [
                'nome' => $_GET['nome'] ?? null,
                'especialidade' => $_GET['especialidade'] ?? null,
                'status' => $_GET['status'] ?? null,
                'disponivel' => $_GET['disponivel'] ?? null,
                'page' => (int)($_GET['page'] ?? 1),
                'limit' => (int)($_GET['limit'] ?? 20),
                'orderBy' => $_GET['orderBy'] ?? 'nome',
                'orderDir' => $_GET['orderDir'] ?? 'ASC'
            ];
            
            $controller->apiIndex($filtros);
            break;
            
        case 'get':
            // Buscar profissional específico
            $id = (int)($_GET['id'] ?? 0);
            if ($id > 0) {
                $controller->apiGet($id);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'disponiveis':
            // Listar apenas profissionais disponíveis
            $data = $_GET['data'] ?? date('Y-m-d');
            $servicoId = (int)($_GET['servico_id'] ?? 0);
            
            $controller->apiProfissionaisDisponiveis($data, $servicoId);
            break;
            
        case 'agenda':
            // Agenda do profissional
            $id = (int)($_GET['id'] ?? 0);
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
            $dataFim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+7 days'));
            
            if ($id > 0) {
                $controller->apiAgenda($id, $dataInicio, $dataFim);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'horarios':
            // Horários de trabalho do profissional
            $id = (int)($_GET['id'] ?? 0);
            $diaSemana = $_GET['dia_semana'] ?? null;
            
            if ($id > 0) {
                $controller->apiHorarios($id, $diaSemana);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'performance':
            // Performance do profissional
            $id = (int)($_GET['id'] ?? 0);
            $periodo = $_GET['periodo'] ?? '30'; // dias
            
            if ($id > 0) {
                $controller->apiPerformance($id, $periodo);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'ranking':
            // Ranking de profissionais
            $periodo = $_GET['periodo'] ?? '30';
            $metrica = $_GET['metrica'] ?? 'faturamento'; // faturamento, atendimentos, avaliacao
            
            $controller->apiRanking($periodo, $metrica);
            break;
            
        case 'especialidades':
            // Lista de especialidades únicas
            $controller->apiEspecialidades();
            break;
            
        case 'servicos':
            // Serviços que o profissional pode realizar
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id > 0) {
                $controller->apiServicos($id);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'estatisticas':
            // Estatísticas gerais dos profissionais
            $periodo = $_GET['periodo'] ?? '30';
            $controller->apiEstatisticas($periodo);
            break;
            
        case 'validar_email':
            // Validar se email já existe
            $email = $_GET['email'] ?? '';
            $excludeId = (int)($_GET['exclude_id'] ?? 0);
            
            if ($email) {
                $controller->apiValidarEmail($email, $excludeId);
            } else {
                Response::json(['erro' => 'Email não informado'], 400);
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
    // Apenas gestores podem criar profissionais
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
            // Criar novo profissional
            $validator = new Validator();
            $sanitizer = new Sanitizer();
            
            // Sanitizar dados
            $data = $sanitizer->sanitizeArray($data, [
                'nome' => 'string',
                'email' => 'email',
                'telefone' => 'phone',
                'especialidade' => 'string',
                'registro_profissional' => 'string',
                'comissao_percentual' => 'float',
                'observacoes' => 'string'
            ]);
            
            // Validar dados
            $rules = [
                'nome' => 'required|string|min:2|max:100',
                'email' => 'required|email|max:150|unique:usuarios,email',
                'telefone' => 'required|string|min:10|max:15',
                'especialidade' => 'required|string|max:100',
                'registro_profissional' => 'string|max:50',
                'comissao_percentual' => 'numeric|min:0|max:100',
                'senha' => 'required|string|min:6',
                'observacoes' => 'string|max:500'
            ];
            
            $validation = $validator->validate($data, $rules);
            
            if (!$validation['valid']) {
                Response::json(['erro' => 'Dados inválidos', 'detalhes' => $validation['errors']], 400);
                return;
            }
            
            $controller->apiStore($data);
            break;
            
        case 'definir_horarios':
            // Definir horários de trabalho
            $profissionalId = (int)($data['profissional_id'] ?? 0);
            $horarios = $data['horarios'] ?? [];
            
            if ($profissionalId > 0 && !empty($horarios)) {
                $controller->apiDefinirHorarios($profissionalId, $horarios);
            } else {
                Response::json(['erro' => 'Dados inválidos'], 400);
            }
            break;
            
        case 'vincular_servicos':
            // Vincular serviços ao profissional
            $profissionalId = (int)($data['profissional_id'] ?? 0);
            $servicosIds = $data['servicos_ids'] ?? [];
            
            if ($profissionalId > 0 && !empty($servicosIds)) {
                $controller->apiVincularServicos($profissionalId, $servicosIds);
            } else {
                Response::json(['erro' => 'Dados inválidos'], 400);
            }
            break;
            
        case 'definir_ausencia':
            // Definir período de ausência
            $profissionalId = (int)($data['profissional_id'] ?? 0);
            $dataInicio = $data['data_inicio'] ?? '';
            $dataFim = $data['data_fim'] ?? '';
            $motivo = $data['motivo'] ?? '';
            
            if ($profissionalId > 0 && $dataInicio && $dataFim) {
                $controller->apiDefinirAusencia($profissionalId, $dataInicio, $dataFim, $motivo);
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
    
    // Verificar permissões
    $user = Auth::user();
    if (!Auth::isGestor() && $user['id'] != $id) {
        Response::json(['erro' => 'Permissão insuficiente'], 403);
        return;
    }
    
    $action = $data['action'] ?? 'update';
    
    switch ($action) {
        case 'update':
            // Atualizar dados do profissional
            $validator = new Validator();
            $sanitizer = new Sanitizer();
            
            // Sanitizar dados
            $data = $sanitizer->sanitizeArray($data, [
                'nome' => 'string',
                'email' => 'email',
                'telefone' => 'phone',
                'especialidade' => 'string',
                'registro_profissional' => 'string',
                'comissao_percentual' => 'float',
                'observacoes' => 'string',
                'status' => 'string'
            ]);
            
            // Validar dados
            $rules = [
                'nome' => 'string|min:2|max:100',
                'email' => 'email|max:150',
                'telefone' => 'string|min:10|max:15',
                'especialidade' => 'string|max:100',
                'registro_profissional' => 'string|max:50',
                'comissao_percentual' => 'numeric|min:0|max:100',
                'observacoes' => 'string|max:500',
                'status' => 'in:ativo,inativo,licenca,ferias'
            ];
            
            $validation = $validator->validate($data, $rules);
            
            if (!$validation['valid']) {
                Response::json(['erro' => 'Dados inválidos', 'detalhes' => $validation['errors']], 400);
                return;
            }
            
            $controller->apiUpdate($id, $data);
            break;
            
        case 'ativar':
            // Ativar profissional
            if (!Auth::isGestor()) {
                Response::json(['erro' => 'Permissão insuficiente'], 403);
                return;
            }
            
            $controller->apiAtivar($id);
            break;
            
        case 'inativar':
            // Inativar profissional
            if (!Auth::isGestor()) {
                Response::json(['erro' => 'Permissão insuficiente'], 403);
                return;
            }
            
            $motivo = $data['motivo'] ?? '';
            $controller->apiInativar($id, $motivo);
            break;
            
        case 'alterar_senha':
            // Alterar senha
            $senhaAtual = $data['senha_atual'] ?? '';
            $novaSenha = $data['nova_senha'] ?? '';
            
            if (!$senhaAtual || !$novaSenha) {
                Response::json(['erro' => 'Senhas não informadas'], 400);
                return;
            }
            
            if (strlen($novaSenha) < 6) {
                Response::json(['erro' => 'Nova senha deve ter pelo menos 6 caracteres'], 400);
                return;
            }
            
            $controller->apiAlterarSenha($id, $senhaAtual, $novaSenha);
            break;
            
        case 'marcar_disponivel':
            // Marcar como disponível
            $controller->apiMarcarDisponivel($id);
            break;
            
        case 'marcar_ocupado':
            // Marcar como ocupado
            $motivo = $data['motivo'] ?? '';
            $controller->apiMarcarOcupado($id, $motivo);
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
 * Validar horários de trabalho
 */
function validarHorarios($horarios) {
    $diasSemana = ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'];
    
    foreach ($horarios as $horario) {
        if (!isset($horario['dia_semana']) || !in_array($horario['dia_semana'], $diasSemana)) {
            return false;
        }
        
        if (!isset($horario['hora_inicio']) || !isset($horario['hora_fim'])) {
            return false;
        }
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $horario['hora_inicio']) ||
            !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $horario['hora_fim'])) {
            return false;
        }
        
        if ($horario['hora_inicio'] >= $horario['hora_fim']) {
            return false;
        }
    }
    
    return true;
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
    
    error_log('API_PROFISSIONAIS: ' . json_encode($logData));
}

?>