<?php
/**
 * Ponto de Entrada Principal
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

// Iniciar sessão
session_start();

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Carregar configurações
require_once '../config/app.php';
require_once '../config/database.php';

// Autoloader simples
spl_autoload_register(function ($class) {
    $file = '../src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Importar classes essenciais
use Utils\Response;
use Utils\Auth;

try {
    // Obter URI e método
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Remover trailing slash
    $uri = rtrim($uri, '/');
    if (empty($uri)) $uri = '/';
    
    // Definir rotas
    $routes = [
        // Páginas principais
        '/' => 'home',
        '/login' => 'auth/login',
        '/logout' => 'auth/logout',
        '/nfc' => 'auth/nfc',
        
        // Recepção
        '/recepcao' => 'recepcao/fila',
        '/recepcao/cadastro' => 'recepcao/cadastro',
        '/recepcao/fila' => 'recepcao/fila',
        
        // Profissional
        '/profissional' => 'profissional/painel',
        '/profissional/painel' => 'profissional/painel',
        
        // Gestor
        '/gestor' => 'gestor/dashboard',
        '/gestor/dashboard' => 'gestor/dashboard',
        '/gestor/profissionais' => 'gestor/profissionais',
        '/gestor/servicos' => 'gestor/servicos',
        '/gestor/relatorios' => 'gestor/relatorios',
        
        // Admin
        '/admin' => 'gestor/dashboard',
        '/admin/dashboard' => 'gestor/dashboard',
        '/admin/profissionais' => 'gestor/profissionais',
        '/admin/servicos' => 'gestor/servicos',
        '/admin/relatorios' => 'gestor/relatorios'
    ];
    
    // Verificar se rota existe
    if (!isset($routes[$uri])) {
        throw new Exception('Página não encontrada', 404);
    }
    
    $route = $routes[$uri];
    
    // Verificar autenticação para rotas protegidas
    if ($uri !== '/login' && $uri !== '/nfc' && $uri !== '/') {
        $auth = Auth::check();
        if (!$auth) {
            Response::redirect('/login');
        }
        
        // Verificar permissões por rota
        $requiredPermissions = getRequiredPermissions($uri);
        if ($requiredPermissions && !Auth::hasPermission($requiredPermissions)) {
            http_response_code(403);
            include VIEWS_PATH . '/errors/403.php';
            exit;
        }
    }
    
    // Incluir view correspondente
    $viewFile = VIEWS_PATH . '/' . $route . '.php';
    
    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        throw new Exception('View não encontrada: ' . $route, 404);
    }
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro na aplicação: " . $e->getMessage());
    
    // Exibir página de erro
    $errorCode = $e->getCode() ?: 500;
    http_response_code($errorCode);
    
    if (file_exists(VIEWS_PATH . '/errors/' . $errorCode . '.php')) {
        include VIEWS_PATH . '/errors/' . $errorCode . '.php';
    } else {
        echo "<h1>Erro $errorCode</h1>";
        echo "<p>" . ($errorCode === 404 ? 'Página não encontrada' : 'Erro interno do servidor') . "</p>";
        if (APP_DEBUG) {
            echo "<pre>" . $e->getMessage() . "</pre>";
        }
    }
}

/**
 * Definir permissões necessárias por rota
 */
function getRequiredPermissions(string $uri): ?string
{
    $permissions = [
        '/recepcao' => 'recepcao',
        '/recepcao/cadastro' => 'recepcao',
        '/recepcao/fila' => 'recepcao',
        
        '/profissional' => 'profissional',
        '/profissional/painel' => 'profissional',
        
        '/gestor' => 'gestor',
        '/gestor/dashboard' => 'gestor',
        '/gestor/profissionais' => 'gestor',
        '/gestor/servicos' => 'gestor',
        '/gestor/relatorios' => 'gestor',
        
        '/admin' => 'administrador',
        '/admin/dashboard' => 'administrador',
        '/admin/profissionais' => 'administrador',
        '/admin/servicos' => 'administrador',
        '/admin/relatorios' => 'administrador'
    ];
    
    return $permissions[$uri] ?? null;
}

/**
 * Redirecionar para home baseado no perfil
 */
function getHomeRoute(): string
{
    if (!Auth::check()) {
        return '/login';
    }
    
    $perfil = Auth::getUser()['perfil'] ?? '';
    
    switch ($perfil) {
        case 'administrador':
            return '/admin/dashboard';
        case 'gestor':
            return '/gestor/dashboard';
        case 'recepcao':
            return '/recepcao/fila';
        case 'profissional':
            return '/profissional/painel';
        default:
            return '/login';
    }
}

// Redirecionar home para painel apropriado
if ($uri === '/') {
    Response::redirect(getHomeRoute());
}