<?php

/**
 * API de Clientes - Fast Escova
 * 
 * Gerencia operações CRUD de clientes via API RESTful
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
require_once __DIR__ . '/../src/Controllers/ClienteController.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Response.php';
require_once __DIR__ . '/../src/Utils/Validator.php';
require_once __DIR__ . '/../src/Utils/Sanitizer.php';

use FastEscova\Controllers\ClienteController;
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
$controller = new ClienteController();

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
            // Listar clientes com filtros e paginação
            $filtros = [
                'nome' => $_GET['nome'] ?? null,
                'email' => $_GET['email'] ?? null,
                'telefone' => $_GET['telefone'] ?? null,
                'cpf' => $_GET['cpf'] ?? null,
                'data_nascimento' => $_GET['data_nascimento'] ?? null,
                'status' => $_GET['status'] ?? null,
                'page' => (int)($_GET['page'] ?? 1),
                'limit' => (int)($_GET['limit'] ?? 20),
                'orderBy' => $_GET['orderBy'] ?? 'nome',
                'orderDir' => $_GET['orderDir'] ?? 'ASC'
            ];
            
            $controller->apiIndex($filtros);
            break;
            
        case 'get':
            // Buscar cliente específico
            $id = (int)($_GET['id'] ?? 0);
            if ($id > 0) {
                $controller->apiGet($id);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'search':
            // Busca rápida por nome, email ou telefone
            $termo = $_GET['termo'] ?? '';
            if (strlen($termo) >= 2) {
                $controller->apiBuscaRapida($termo);
            } else {
                Response::json(['erro' => 'Termo de busca deve ter pelo menos 2 caracteres'], 400);
            }
            break;
            
        case 'historico':
            // Histórico de atendimentos do cliente
            $id = (int)($_GET['id'] ?? 0);
            $limit = (int)($_GET['limit'] ?? 10);
            
            if ($id > 0) {
                $controller->apiHistoricoAtendimentos($id, $limit);
            } else {
                Response::json(['erro' => 'ID inválido'], 400);
            }
            break;
            
        case 'aniversariantes':
            // Clientes aniversariantes do mês
            $mes = $_GET['mes'] ?? date('m');
            $ano = $_GET['ano'] ?? date('Y');
            
            $controller->apiAniversariantes($mes, $ano);
            break;
            
        case 'inativos':
            // Clientes inativos (sem atendimento há X dias)
            $dias = (int)($_GET['dias'] ?? 90);
            $controller->apiClientesInativos($dias);
            break;
            
        case 'estatisticas':
            // Estatísticas de clientes
            $controller->apiEstatisticas();
            break;
            
        case 'validar_cpf':
            // Validar se CPF já existe
            $cpf = $_GET['cpf'] ?? '';
            $excludeId = (int)($_GET['exclude_id'] ?? 0);
            
            if ($cpf) {
                $controller->apiValidarCpf($cpf, $excludeId);
            } else {
                Response::json(['erro' => 'CPF não informado'], 400);
            }
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
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        Response::json(['erro' => 'Dados inválidos'], 400);
        return;
    }
    
    $action = $data['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            // Criar novo cliente
            $validator = new Validator();
            $sanitizer = new Sanitizer();
            
            // Sanitizar dados
            $data = $sanitizer->sanitizeArray($data, [
                'nome' => 'string',
                'email' => 'email',
                'telefone' => 'phone',
                'cpf' => 'cpf',
                'data_nascimento' => 'date',
                'endereco' => 'string',
                'cidade' => 'string',
                'estado' => 'string',
                'cep' => 'string',
                'observacoes' => 'string'
            ]);
            
            // Validar dados
            $rules = [
                'nome' => 'required|string|min:2|max:100',
                'email' => 'email|max:150',
                'telefone' => 'required|string|min:10|max:15',
                'cpf' => 'cpf|unique:clientes,cpf',
                'data_nascimento' => 'date|before:today',
                'endereco' => 'string|max:200',
                'cidade' => 'string|max:100',
                'estado' => 'string|size:2',
                'cep' => 'string|size:8',
                'observacoes' => 'string|max:500'
            ];
            
            $validation = $validator->validate($data, $rules);
            
            if (!$validation['valid']) {
                Response::json(['erro' => 'Dados inválidos', 'detalhes' => $validation['errors']], 400);
                return;
            }
            
            $controller->apiStore($data);
            break;
            
        case 'import':
            // Importar clientes em lote
            if (!Auth::isGestor()) {
                Response::json(['erro' => 'Permissão insuficiente'], 403);
                return;
            }
            
            $clientes = $data['clientes'] ?? [];
            if (empty($clientes)) {
                Response::json(['erro' => 'Lista de clientes vazia'], 400);
                return;
            }
            
            $controller->apiImportarClientes($clientes);
            break;
            
        case 'agendar':
            // Criar cliente e agendar atendimento
            $clienteData = $data['cliente'] ?? [];
            $atendimentoData = $data['atendimento'] ?? [];
            
            if (empty($clienteData) || empty($atendimentoData)) {
                Response::json(['erro' => 'Dados incompletos'], 400);
                return;
            }
            
            $controller->apiCriarEAgendar($clienteData, $atendimentoData);
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
            // Atualizar dados do cliente
            $validator = new Validator();
            $sanitizer = new Sanitizer();
            
            // Sanitizar dados
            $data = $sanitizer->sanitizeArray($data, [
                'nome' => 'string',
                'email' => 'email',
                'telefone' => 'phone',
                'cpf' => 'cpf',
                'data_nascimento' => 'date',
                'endereco' => 'string',
                'cidade' => 'string',
                'estado' => 'string',
                'cep' => 'string',
                'observacoes' => 'string',
                'status' => 'string'
            ]);
            
            // Validar dados
            $rules = [
                'nome' => 'string|min:2|max:100',
                'email' => 'email|max:150',
                'telefone' => 'string|min:10|max:15',
                'cpf' => 'cpf',
                'data_nascimento' => 'date|before:today',
                'endereco' => 'string|max:200',
                'cidade' => 'string|max:100',
                'estado' => 'string|size:2',
                'cep' => 'string|size:8',
                'observacoes' => 'string|max:500',
                'status' => 'in:ativo,inativo,bloqueado'
            ];
            
            $validation = $validator->validate($data, $rules);
            
            if (!$validation['valid']) {
                Response::json(['erro' => 'Dados inválidos', 'detalhes' => $validation['errors']], 400);
                return;
            }
            
            $controller->apiUpdate($id, $data);
            break;
            
        case 'ativar':
            // Ativar cliente
            $controller->apiAtivarCliente($id);
            break;
            
        case 'inativar':
            // Inativar cliente
            $motivo = $data['motivo'] ?? '';
            $controller->apiInativarCliente($id, $motivo);
            break;
            
        case 'bloquear':
            // Bloquear cliente
            $motivo = $data['motivo'] ?? '';
            $controller->apiBloquearCliente($id, $motivo);
            break;
            
        default:
            Response::json(['erro' => 'Ação não encontrada'], 404);
    }
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
    
    // Verificar se há atendimentos vinculados
    $controller->apiDelete($id);
}

/**
 * Validar CPF
 */
function validarCpf($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $cpf[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : (11 - $resto);
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $cpf[$i] * (11 - $i);
    }
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : (11 - $resto);
    
    // Verifica se os dígitos calculados são iguais aos informados
    return ($cpf[9] == $dv1 && $cpf[10] == $dv2);
}

/**
 * Formatar telefone
 */
function formatarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
    }
    
    return $telefone;
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
    
    error_log('API_CLIENTES: ' . json_encode($logData));
}

?>