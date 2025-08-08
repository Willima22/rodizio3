<?php
/**
 * Controller de Autenticação
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Controllers;

use Utils\Response;
use Utils\Auth;
use Utils\Validator;
use Utils\Sanitizer;
use Utils\CSRF;
use Utils\DB;

class AuthController
{
    /**
     * Processar login
     */
    public static function login(): void
    {
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'usuario' => 'input',
                'senha' => 'input'
            ]);
            
            // Validar dados
            $validator = new Validator($data);
            $validator
                ->required('usuario', 'Usuário é obrigatório')
                ->min('usuario', 3, 'Usuário deve ter pelo menos 3 caracteres')
                ->required('senha', 'Senha é obrigatória')
                ->min('senha', 4, 'Senha deve ter pelo menos 4 caracteres');
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Verificar rate limiting
            if (!self::checkRateLimit()) {
                Response::error('Muitas tentativas de login. Tente novamente em 10 minutos.', null, 429);
            }
            
            // Tentar fazer login
            $success = Auth::loginUser($data['usuario'], $data['senha']);
            
            if (!$success) {
                self::registerFailedAttempt();
                Response::error('Usuário ou senha inválidos');
            }
            
            // Login bem-sucedido
            self::clearFailedAttempts();
            
            $user = Auth::getUser();
            $redirectUrl = self::getRedirectUrl($user['perfil']);
            
            Response::success([
                'user' => $user,
                'redirect_url' => $redirectUrl
            ], 'Login realizado com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            Response::serverError('Erro interno do servidor');
        }
    }
    
    /**
     * Processar logout
     */
    public static function logout(): void
    {
        try {
            if (Auth::check()) {
                Auth::logout();
            }
            
            // Se for AJAX, retornar JSON
            if (Response::isAjax()) {
                Response::success(null, 'Logout realizado com sucesso');
            }
            
            // Senão, redirecionar
            Response::redirect('/login');
            
        } catch (\Exception $e) {
            error_log("Erro no logout: " . $e->getMessage());
            
            if (Response::isAjax()) {
                Response::serverError('Erro no logout');
            }
            
            Response::redirect('/login');
        }
    }
    
    /**
     * Verificar status da sessão
     */
    public static function checkSession(): void
    {
        try {
            $authenticated = Auth::check();
            
            $response = [
                'authenticated' => $authenticated,
                'type' => Auth::getLoginType(),
                'user' => $authenticated ? Auth::getUser() : null
            ];
            
            Response::success($response);
            
        } catch (\Exception $e) {
            error_log("Erro ao verificar sessão: " . $e->getMessage());
            Response::serverError('Erro ao verificar sessão');
        }
    }
    
    /**
     * Gerar novo token CSRF
     */
    public static function refreshCsrf(): void
    {
        try {
            $token = CSRF::regenerate();
            
            Response::success([
                'csrf_token' => $token
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao gerar CSRF: " . $e->getMessage());
            Response::serverError('Erro ao gerar token');
        }
    }
    
    /**
     * Obter URL de redirecionamento baseada no perfil
     */
    private static function getRedirectUrl(string $perfil): string
    {
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
    
    /**
     * Verificar rate limiting por IP
     */
    private static function checkRateLimit(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login_attempts_' . $ip;
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'last_attempt' => 0,
                'blocked_until' => 0
            ];
        }
        
        $attempts = &$_SESSION[$key];
        $now = time();
        
        // Verificar se ainda está bloqueado
        if ($attempts['blocked_until'] > $now) {
            return false;
        }
        
        // Reset se passou muito tempo desde a última tentativa
        if ($now - $attempts['last_attempt'] > LOGIN_BLOCK_TIME) {
            $attempts['attempts'] = 0;
            $attempts['blocked_until'] = 0;
        }
        
        return $attempts['attempts'] < LOGIN_ATTEMPTS_MAX;
    }
    
    /**
     * Registrar tentativa de login falhada
     */
    private static function registerFailedAttempt(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login_attempts_' . $ip;
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'last_attempt' => 0,
                'blocked_until' => 0
            ];
        }
        
        $attempts = &$_SESSION[$key];
        $now = time();
        
        $attempts['attempts']++;
        $attempts['last_attempt'] = $now;
        
        // Bloquear se atingiu o limite
        if ($attempts['attempts'] >= LOGIN_ATTEMPTS_MAX) {
            $attempts['blocked_until'] = $now + LOGIN_BLOCK_TIME;
        }
        
        // Log da tentativa falhada
        error_log("Login falhado para IP: $ip | Tentativas: {$attempts['attempts']}");
    }
    
    /**
     * Limpar tentativas de login para o IP atual
     */
    private static function clearFailedAttempts(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login_attempts_' . $ip;
        
        unset($_SESSION[$key]);
    }
    
    /**
     * Verificar se usuário tem permissão para acessar rota
     */
    public static function authorize(string $permission): bool
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::hasPermission($permission);
    }
    
    /**
     * Middleware de autenticação
     */
    public static function requireAuth(): void
    {
        if (!Auth::check()) {
            if (Response::isAjax()) {
                Response::unauthorized('Sessão expirada');
            }
            
            Response::redirect('/login');
        }
    }
    
    /**
     * Middleware de autorização
     */
    public static function requirePermission(string $permission): void
    {
        self::requireAuth();
        
        if (!Auth::hasPermission($permission)) {
            if (Response::isAjax()) {
                Response::error('Acesso negado', null, 403);
            }
            
            Response::redirect('/login');
        }
    }
    
    /**
     * Obter informações do usuário logado
     */
    public static function me(): void
    {
        try {
            if (!Auth::check()) {
                Response::unauthorized();
            }
            
            $user = Auth::getUser();
            $loginType = Auth::getLoginType();
            
            // Buscar dados adicionais se necessário
            if ($loginType === 'nfc') {
                $db = DB::getInstance();
                $sql = "SELECT status, ordem_chegada, total_atendimentos_dia 
                        FROM profissionais WHERE id = :id";
                $profData = $db->fetchOne($sql, ['id' => $user['id']]);
                
                if ($profData) {
                    $user['status'] = $profData['status'];
                    $user['ordem_chegada'] = $profData['ordem_chegada'];
                    $user['total_atendimentos_dia'] = $profData['total_atendimentos_dia'];
                }
            }
            
            Response::success([
                'user' => $user,
                'login_type' => $loginType,
                'permissions' => self::getUserPermissions($user['perfil'] ?? '')
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter dados do usuário: " . $e->getMessage());
            Response::serverError('Erro ao obter dados do usuário');
        }
    }
    
    /**
     * Obter permissões do usuário
     */
    private static function getUserPermissions(string $perfil): array
    {
        $permissions = [
            'administrador' => ['all'],
            'gestor' => ['dashboard', 'profissionais', 'servicos', 'relatorios', 'distribuicao_manual'],
            'recepcao' => ['cadastro_cliente', 'visualizar_fila'],
            'profissional' => ['painel_profissional', 'finalizar_atendimento']
        ];
        
        return $permissions[$perfil] ?? [];
    }
    
    /**
     * Alterar senha (se implementado futuramente)
     */
    public static function changePassword(): void
    {
        self::requireAuth();
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'senha_atual' => 'input',
                'nova_senha' => 'input',
                'confirmar_senha' => 'input'
            ]);
            
            // Validar dados
            $validator = new Validator($data);
            $validator
                ->required('senha_atual', 'Senha atual é obrigatória')
                ->required('nova_senha', 'Nova senha é obrigatória')
                ->min('nova_senha', 6, 'Nova senha deve ter pelo menos 6 caracteres')
                ->required('confirmar_senha', 'Confirmação de senha é obrigatória');
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Verificar se confirmação confere
            if ($data['nova_senha'] !== $data['confirmar_senha']) {
                Response::error('Confirmação de senha não confere');
            }
            
            $userId = Auth::getUserId();
            
            // Verificar senha atual
            $db = DB::getInstance();
            $sql = "SELECT senha FROM usuarios WHERE id = :id";
            $user = $db->fetchOne($sql, ['id' => $userId]);
            
            if (!$user || !password_verify($data['senha_atual'], $user['senha'])) {
                Response::error('Senha atual incorreta');
            }
            
            // Atualizar senha
            $newHash = password_hash($data['nova_senha'], PASSWORD_BCRYPT);
            $sql = "UPDATE usuarios SET senha = :senha, atualizado_em = NOW() WHERE id = :id";
            $db->execute($sql, ['senha' => $newHash, 'id' => $userId]);
            
            Response::success(null, 'Senha alterada com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao alterar senha: " . $e->getMessage());
            Response::serverError('Erro ao alterar senha');
        }
    }
}