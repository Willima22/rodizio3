<?php
/**
 * API de Autenticação
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

// Iniciar sessão
session_start();

// Carregar configurações
require_once '../config/app.php';
require_once '../config/database.php';

// Autoloader
spl_autoload_register(function ($class) {
    $file = '../src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use Utils\Response;
use Controllers\AuthController;

// Headers CORS para desenvolvimento (remover em produção se necessário)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');

// Tratar OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Obter ação
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if (empty($action)) {
        Response::error('Ação não especificada');
    }
    
    // Limpar output anterior
    Response::clean();
    
    // Roteamento das ações
    switch ($action) {
        case 'login':
            AuthController::login();
            break;
            
        case 'logout':
            AuthController::logout();
            break;
            
        case 'check_session':
            AuthController::checkSession();
            break;
            
        case 'me':
            AuthController::me();
            break;
            
        case 'refresh_csrf':
            AuthController::refreshCsrf();
            break;
            
        case 'change_password':
            AuthController::changePassword();
            break;
            
        default:
            Response::error('Ação inválida', null, 400);
    }
    
} catch (\Throwable $e) {
    // Log do erro
    error_log("Erro na API de autenticação: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Resposta de erro
    if (APP_DEBUG) {
        Response::error('Erro interno: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], 500);
    } else {
        Response::serverError('Erro interno do servidor');
    }
}