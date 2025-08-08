<?php

namespace FastEscova\Utils;

require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Log.php';

use FastEscova\Models\Usuario;
use FastEscova\Models\Log;

/**
 * Classe Auth - Sistema de Autenticação
 * 
 * Gerencia autenticação, sessões e permissões do sistema
 */
class Auth {
    
    /**
     * Inicializar sessão se não estiver ativa
     */
    private static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Realizar login do usuário
     * 
     * @param string $email Email do usuário
     * @param string $senha Senha do usuário
     * @param bool $lembrar Se deve lembrar o login
     * @return array Resultado do login
     */
    public static function login($email, $senha, $lembrar = false) {
        self::initSession();
        
        try {
            $usuario = new Usuario();
            $dadosUsuario = $usuario->buscarPorEmail($email);
            
            if (!$dadosUsuario) {
                self::registrarTentativaLogin($email, false, 'Email não encontrado');
                return [
                    'sucesso' => false,
                    'mensagem' => 'Credenciais inválidas'
                ];
            }
            
            // Verificar se usuário está ativo
            if ($dadosUsuario['status'] !== 'ativo') {
                self::registrarTentativaLogin($email, false, 'Usuário inativo');
                return [
                    'sucesso' => false,
                    'mensagem' => 'Usuário inativo. Contate o administrador.'
                ];
            }
            
            // Verificar senha
            if (!password_verify($senha, $dadosUsuario['senha'])) {
                self::registrarTentativaLogin($email, false, 'Senha incorreta');
                return [
                    'sucesso' => false,
                    'mensagem' => 'Credenciais inválidas'
                ];
            }
            
            // Login bem-sucedido
            self::setUserSession($dadosUsuario);
            self::registrarTentativaLogin($email, true, 'Login realizado com sucesso');
            
            // Atualizar último login
            $usuario->atualizarUltimoLogin($dadosUsuario['id']);
            
            // Configurar cookie "lembrar-me" se solicitado
            if ($lembrar) {
                self::setRememberToken($dadosUsuario['id']);
            }
            
            return [
                'sucesso' => true,
                'mensagem' => 'Login realizado com sucesso',
                'usuario' => $dadosUsuario,
                'redirect' => self::getRedirectPath($dadosUsuario['perfil'])
            ];
            
        } catch (Exception $e) {
            self::registrarTentativaLogin($email, false, 'Erro interno: ' . $e->getMessage());
            return [
                'sucesso' => false,
                'mensagem' => 'Erro interno do sistema'
            ];
        }
    }
    
    /**
     * Login via NFC
     * 
     * @param string $nfcId ID do NFC
     * @return array Resultado do login
     */
    public static function loginNfc($nfcId) {
        self::initSession();
        
        try {
            $usuario = new Usuario();
            $dadosUsuario = $usuario->buscarPorNfc($nfcId);
            
            if (!$dadosUsuario) {
                self::registrarTentativaLogin($nfcId, false, 'NFC não encontrado', 'nfc');
                return [
                    'sucesso' => false,
                    'mensagem' => 'NFC não autorizado'
                ];
            }
            
            if ($dadosUsuario['status'] !== 'ativo') {
                self::registrarTentativaLogin($nfcId, false, 'Usuário inativo', 'nfc');
                return [
                    'sucesso' => false,
                    'mensagem' => 'Usuário inativo'
                ];
            }
            
            // Login NFC bem-sucedido
            self::setUserSession($dadosUsuario);
            self::registrarTentativaLogin($nfcId, true, 'Login NFC realizado', 'nfc');
            
            $usuario->atualizarUltimoLogin($dadosUsuario['id']);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Login NFC realizado com sucesso',
                'usuario' => $dadosUsuario,
                'redirect' => self::getRedirectPath($dadosUsuario['perfil'])
            ];
            
        } catch (Exception $e) {
            self::registrarTentativaLogin($nfcId, false, 'Erro NFC: ' . $e->getMessage(), 'nfc');
            return [
                'sucesso' => false,
                'mensagem' => 'Erro no sistema NFC'
            ];
        }
    }
    
    /**
     * Definir sessão do usuário
     */
    private static function setUserSession($dadosUsuario) {
        $_SESSION['user_id'] = $dadosUsuario['id'];
        $_SESSION['user_email'] = $dadosUsuario['email'];
        $_SESSION['user_nome'] = $dadosUsuario['nome'];
        $_SESSION['user_perfil'] = $dadosUsuario['perfil'];
        $_SESSION['user_foto'] = $dadosUsuario['foto'] ?? null;
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    /**
     * Logout do usuário
     */
    public static function logout() {
        self::initSession();
        
        if (self::check()) {
            $email = $_SESSION['user_email'] ?? 'Desconhecido';
            self::registrarTentativaLogin($email, true, 'Logout realizado');
        }
        
        // Limpar cookie "lembrar-me"
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Destruir sessão
        session_destroy();
        
        // Redirecionar para login
        header('Location: /login');
        exit;
    }
    
    /**
     * Verificar se usuário está autenticado
     */
    public static function check() {
        self::initSession();
        
        // Verificar sessão
        if (isset($_SESSION['user_id']) && isset($_SESSION['login_time'])) {
            // Verificar timeout (4 horas)
            if (time() - $_SESSION['login_time'] > 14400) {
                self::logout();
                return false;
            }
            return true;
        }
        
        // Verificar cookie "lembrar-me"
        if (isset($_COOKIE['remember_token'])) {
            return self::checkRememberToken($_COOKIE['remember_token']);
        }
        
        return false;
    }
    
    /**
     * Obter dados do usuário logado
     */
    public static function user() {
        self::initSession();
        
        if (!self::check()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'nome' => $_SESSION['user_nome'],
            'perfil' => $_SESSION['user_perfil'],
            'foto' => $_SESSION['user_foto']
        ];
    }
    
    /**
     * Verificar se usuário tem perfil específico
     */
    public static function hasRole($perfil) {
        $usuario = self::user();
        return $usuario && $usuario['perfil'] === $perfil;
    }
    
    /**
     * Verificar se usuário é gestor
     */
    public static function isGestor() {
        return self::hasRole('gestor');
    }
    
    /**
     * Verificar se usuário é profissional
     */
    public static function isProfissional() {
        return self::hasRole('profissional');
    }
    
    /**
     * Verificar se usuário é recepção
     */
    public static function isRecepcao() {
        return self::hasRole('recepcao');
    }
    
    /**
     * Obter token CSRF
     */
    public static function getCsrfToken() {
        self::initSession();
        return $_SESSION['csrf_token'] ?? '';
    }
    
    /**
     * Verificar token CSRF
     */
    public static function verifyCsrfToken($token) {
        return hash_equals(self::getCsrfToken(), $token);
    }
    
    /**
     * Obter caminho de redirecionamento baseado no perfil
     */
    private static function getRedirectPath($perfil) {
        switch ($perfil) {
            case 'gestor':
                return '/gestor/dashboard';
            case 'profissional':
                return '/profissional/painel';
            case 'recepcao':
                return '/recepcao/fila';
            default:
                return '/login';
        }
    }
    
    /**
     * Configurar token "lembrar-me"
     */
    private static function setRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (30 * 24 * 60 * 60); // 30 dias
        
        // Salvar token no banco
        $usuario = new Usuario();
        $usuario->salvarRememberToken($userId, $token, $expiry);
        
        // Configurar cookie
        setcookie('remember_token', $token, $expiry, '/', '', true, true);
    }
    
    /**
     * Verificar token "lembrar-me"
     */
    private static function checkRememberToken($token) {
        $usuario = new Usuario();
        $dadosUsuario = $usuario->buscarPorRememberToken($token);
        
        if ($dadosUsuario && $dadosUsuario['token_expiry'] > time()) {
            self::setUserSession($dadosUsuario);
            return true;
        }
        
        return false;
    }
    
    /**
     * Registrar tentativa de login nos logs
     */
    private static function registrarTentativaLogin($email, $sucesso, $detalhes = '', $tipo = 'email') {
        try {
            $log = new Log();
            $log->registrar([
                'usuario_id' => $sucesso ? ($_SESSION['user_id'] ?? null) : null,
                'acao' => $sucesso ? 'login_sucesso' : 'login_falha',
                'tabela' => 'usuarios',
                'registro_id' => null,
                'detalhes' => json_encode([
                    'email' => $email,
                    'tipo' => $tipo,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido',
                    'detalhes' => $detalhes
                ])
            ]);
        } catch (Exception $e) {
            // Log de erro silencioso - não queremos quebrar o login por problema de log
            error_log("Erro ao registrar log de login: " . $e->getMessage());
        }
    }
    
    /**
     * Middleware para verificar autenticação
     */
    public static function middleware() {
        if (!self::check()) {
            if (self::isAjaxRequest()) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['erro' => 'Não autenticado']);
                exit;
            } else {
                header('Location: /login');
                exit;
            }
        }
    }
    
    /**
     * Verificar se é requisição AJAX
     */
    private static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Gerar hash de senha
     */
    public static function hashPassword($senha) {
        return password_hash($senha, PASSWORD_DEFAULT);
    }
    
    /**
     * Verificar força da senha
     */
    public static function validarForcaSenha($senha) {
        $erros = [];
        
        if (strlen($senha) < 8) {
            $erros[] = 'Senha deve ter pelo menos 8 caracteres';
        }
        
        if (!preg_match('/[A-Z]/', $senha)) {
            $erros[] = 'Senha deve conter pelo menos uma letra maiúscula';
        }
        
        if (!preg_match('/[a-z]/', $senha)) {
            $erros[] = 'Senha deve conter pelo menos uma letra minúscula';
        }
        
        if (!preg_match('/[0-9]/', $senha)) {
            $erros[] = 'Senha deve conter pelo menos um número';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $senha)) {
            $erros[] = 'Senha deve conter pelo menos um caractere especial';
        }
        
        return [
            'valida' => empty($erros),
            'erros' => $erros
        ];
    }
}

?>