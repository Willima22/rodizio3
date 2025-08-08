<?php
/**
 * API de NFC
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
use Controllers\NfcController;

// Headers CORS para desenvolvimento
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
            // Login via NFC - não requer CSRF (é um login)
            NfcController::login();
            break;
            
        case 'status':
            // Verificar status do NFC
            NfcController::status();
            break;
            
        case 'associate':
            // Associar UID a profissional (requer autenticação)
            NfcController::associate();
            break;
            
        case 'disassociate':
            // Remover associação (requer autenticação)
            NfcController::disassociate();
            break;
            
        case 'list_profissionais':
            // Listar profissionais (requer autenticação)
            NfcController::listProfissionais();
            break;
            
        default:
            Response::error('Ação inválida', null, 400);
    }
    
} catch (\Throwable $e) {
    // Log do erro
    error_log("Erro na API de NFC: " . $e->getMessage());
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