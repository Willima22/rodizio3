<?php

/**
 * Sistema de Rotas - Fast Escova
 * 
 * Define todas as rotas da aplicação organizadas por módulos
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/Utils/Auth.php';

use FastEscova\Utils\Auth;

class Router {
    private $routes = [];
    private $currentRoute = '';
    
    public function __construct() {
        $this->currentRoute = $_GET['route'] ?? '/';
    }
    
    public function get($path, $controller, $method, $middleware = []) {
        $this->routes['GET'][$path] = [
            'controller' => $controller,
            'method' => $method,
            'middleware' => $middleware
        ];
    }
    
    public function post($path, $controller, $method, $middleware = []) {
        $this->routes['POST'][$path] = [
            'controller' => $controller,
            'method' => $method,
            'middleware' => $middleware
        ];
    }
    
    public function run() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->currentRoute;
        
        if (!isset($this->routes[$method][$path])) {
            $this->handleNotFound();
            return;
        }
        
        $route = $this->routes[$method][$path];
        
        // Verificar middleware
        foreach ($route['middleware'] as $middlewareName) {
            if (!$this->runMiddleware($middlewareName)) {
                return;
            }
        }
        
        // Executar controller
        $controllerClass = $route['controller'];
        $method = $route['method'];
        
        if (class_exists($controllerClass)) {
            $controller = new $controllerClass();
            if (method_exists($controller, $method)) {
                $controller->$method();
            } else {
                $this->handleNotFound();
            }
        } else {
            $this->handleNotFound();
        }
    }
    
    private function runMiddleware($middleware) {
        switch ($middleware) {
            case 'auth':
                return Auth::check();
            case 'guest':
                return !Auth::check();
            case 'gestor':
                return Auth::check() && Auth::user()['perfil'] === 'gestor';
            case 'profissional':
                return Auth::check() && Auth::user()['perfil'] === 'profissional';
            case 'recepcao':
                return Auth::check() && Auth::user()['perfil'] === 'recepcao';
            default:
                return true;
        }
    }
    
    private function handleNotFound() {
        http_response_code(404);
        include __DIR__ . '/../src/views/errors/404.php';
    }
}

// Instanciar router
$router = new Router();

// ================================
// ROTAS PÚBLICAS (SEM AUTENTICAÇÃO)
// ================================

// Autenticação
$router->get('/', 'FastEscova\Controllers\AuthController', 'showLogin', ['guest']);
$router->get('/login', 'FastEscova\Controllers\AuthController', 'showLogin', ['guest']);
$router->post('/login', 'FastEscova\Controllers\AuthController', 'login', ['guest']);
$router->get('/login/nfc', 'FastEscova\Controllers\AuthController', 'showNfcLogin', ['guest']);
$router->post('/login/nfc', 'FastEscova\Controllers\NfcController', 'authenticate', ['guest']);
$router->post('/logout', 'FastEscova\Controllers\AuthController', 'logout');

// ================================
// ROTAS DO GESTOR
// ================================

// Dashboard
$router->get('/gestor/dashboard', 'FastEscova\Controllers\GestorController', 'dashboard', ['auth', 'gestor']);

// Profissionais
$router->get('/gestor/profissionais', 'FastEscova\Controllers\GestorController', 'profissionais', ['auth', 'gestor']);
$router->get('/gestor/profissionais/novo', 'FastEscova\Controllers\ProfissionalController', 'create', ['auth', 'gestor']);
$router->post('/gestor/profissionais', 'FastEscova\Controllers\ProfissionalController', 'store', ['auth', 'gestor']);
$router->get('/gestor/profissionais/editar', 'FastEscova\Controllers\ProfissionalController', 'edit', ['auth', 'gestor']);
$router->post('/gestor/profissionais/atualizar', 'FastEscova\Controllers\ProfissionalController', 'update', ['auth', 'gestor']);

// Serviços
$router->get('/gestor/servicos', 'FastEscova\Controllers\GestorController', 'servicos', ['auth', 'gestor']);
$router->get('/gestor/servicos/novo', 'FastEscova\Controllers\ServicoController', 'create', ['auth', 'gestor']);
$router->post('/gestor/servicos', 'FastEscova\Controllers\ServicoController', 'store', ['auth', 'gestor']);
$router->get('/gestor/servicos/editar', 'FastEscova\Controllers\ServicoController', 'edit', ['auth', 'gestor']);
$router->post('/gestor/servicos/atualizar', 'FastEscova\Controllers\ServicoController', 'update', ['auth', 'gestor']);

// Relatórios
$router->get('/gestor/relatorios', 'FastEscova\Controllers\GestorController', 'relatorios', ['auth', 'gestor']);
$router->get('/gestor/relatorios/atendimentos', 'FastEscova\Controllers\RelatorioController', 'atendimentos', ['auth', 'gestor']);
$router->get('/gestor/relatorios/financeiro', 'FastEscova\Controllers\RelatorioController', 'financeiro', ['auth', 'gestor']);
$router->get('/gestor/relatorios/profissionais', 'FastEscova\Controllers\RelatorioController', 'profissionais', ['auth', 'gestor']);

// ================================
// ROTAS DO PROFISSIONAL
// ================================

// Painel
$router->get('/profissional/painel', 'FastEscova\Controllers\ProfissionalController', 'painel', ['auth', 'profissional']);
$router->get('/profissional/agenda', 'FastEscova\Controllers\ProfissionalController', 'agenda', ['auth', 'profissional']);
$router->get('/profissional/atendimentos', 'FastEscova\Controllers\AtendimentoController', 'profissionalAtendimentos', ['auth', 'profissional']);

// ================================
// ROTAS DA RECEPÇÃO
// ================================

// Fila de atendimento
$router->get('/recepcao/fila', 'FastEscova\Controllers\AtendimentoController', 'fila', ['auth', 'recepcao']);
$router->post('/recepcao/fila/chamar', 'FastEscova\Controllers\AtendimentoController', 'chamarProximo', ['auth', 'recepcao']);

// Cadastros
$router->get('/recepcao/cadastro', 'FastEscova\Controllers\ClienteController', 'create', ['auth', 'recepcao']);
$router->post('/recepcao/cadastro', 'FastEscova\Controllers\ClienteController', 'store', ['auth', 'recepcao']);
$router->get('/recepcao/clientes', 'FastEscova\Controllers\ClienteController', 'index', ['auth', 'recepcao']);

// Agendamentos
$router->get('/recepcao/agendamentos', 'FastEscova\Controllers\AtendimentoController', 'index', ['auth', 'recepcao']);
$router->get('/recepcao/agendamentos/novo', 'FastEscova\Controllers\AtendimentoController', 'create', ['auth', 'recepcao']);
$router->post('/recepcao/agendamentos', 'FastEscova\Controllers\AtendimentoController', 'store', ['auth', 'recepcao']);

// ================================
// ROTAS DE API
// ================================

// APIs de dados (usado pelo JavaScript)
$router->get('/api/atendimentos', 'FastEscova\Controllers\AtendimentoController', 'apiIndex', ['auth']);
$router->post('/api/atendimentos', 'FastEscova\Controllers\AtendimentoController', 'apiStore', ['auth']);
$router->get('/api/clientes', 'FastEscova\Controllers\ClienteController', 'apiIndex', ['auth']);
$router->get('/api/profissionais', 'FastEscova\Controllers\ProfissionalController', 'apiIndex', ['auth']);
$router->get('/api/servicos', 'FastEscova\Controllers\ServicoController', 'apiIndex', ['auth']);
$router->get('/api/relatorios/dashboard', 'FastEscova\Controllers\RelatorioController', 'apiDashboard', ['auth']);

// NFC
$router->post('/api/nfc/registrar', 'FastEscova\Controllers\NfcController', 'registrar', ['auth']);
$router->post('/api/nfc/ativar', 'FastEscova\Controllers\NfcController', 'ativar', ['auth']);

// ================================
// EXECUTAR ROUTER
// ================================

$router->run();

?>