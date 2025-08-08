<?php
/**
 * Classe de Proteção CSRF
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Utils;

class CSRF
{
    /**
     * Gerar token CSRF
     */
    public static function generate(): string
    {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
        }
        
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Validar token CSRF
     */
    public static function validate(string $token): bool
    {
        // Verificar se token existe na sessão
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        
        $sessionToken = $_SESSION[CSRF_TOKEN_NAME];
        $tokenTime = $_SESSION[CSRF_TOKEN_NAME . '_time'] ?? 0;
        
        // Verificar se token não expirou
        if (time() - $tokenTime > CSRF_TOKEN_EXPIRE) {
            self::destroy();
            return false;
        }
        
        // Comparação segura
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Validar token do POST
     */
    public static function validatePost(): bool
    {
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        return self::validate($token);
    }
    
    /**
     * Validar token do header
     */
    public static function validateHeader(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return self::validate($token);
    }
    
    /**
     * Validar token de qualquer fonte
     */
    public static function validateAny(): bool
    {
        // Tentar validar do POST primeiro
        if (self::validatePost()) {
            return true;
        }
        
        // Tentar validar do header
        if (self::validateHeader()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Obter token atual
     */
    public static function getToken(): string
    {
        return $_SESSION[CSRF_TOKEN_NAME] ?? '';
    }
    
    /**
     * Destruir token atual
     */
    public static function destroy(): void
    {
        unset($_SESSION[CSRF_TOKEN_NAME]);
        unset($_SESSION[CSRF_TOKEN_NAME . '_time']);
    }
    
    /**
     * Renovar token
     */
    public static function regenerate(): string
    {
        self::destroy();
        return self::generate();
    }
    
    /**
     * Verificar se precisa renovar
     */
    public static function needsRegeneration(): bool
    {
        $tokenTime = $_SESSION[CSRF_TOKEN_NAME . '_time'] ?? 0;
        
        // Renovar se passou da metade do tempo de expiração
        return (time() - $tokenTime) > (CSRF_TOKEN_EXPIRE / 2);
    }
    
    /**
     * Gerar campo input hidden para formulários
     */
    public static function inputField(): string
    {
        $token = self::generate();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Gerar meta tag para AJAX
     */
    public static function metaTag(): string
    {
        $token = self::generate();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Middleware para validação automática
     */
    public static function middleware(): void
    {
        // Apenas validar em requisições POST, PUT, PATCH, DELETE
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return;
        }
        
        // Pular validação para login (evitar loop)
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (in_array($uri, ['/login', '/api/auth.php'])) {
            return;
        }
        
        // Validar token
        if (!self::validateAny()) {
            Response::error('Token CSRF inválido', null, 403);
        }
    }
    
    /**
     * Verificar se é requisição que precisa de CSRF
     */
    public static function requiresValidation(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Métodos que requerem validação
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return false;
        }
        
        // URIs que não requerem validação
        $skipUris = ['/login', '/api/auth.php', '/api/nfc.php'];
        
        return !in_array($uri, $skipUris);
    }
    
    /**
     * JavaScript para incluir token em requests AJAX
     */
    public static function ajaxScript(): string
    {
        $token = self::generate();
        
        return "
        <script>
        // Configurar token CSRF para AJAX
        window.csrfToken = '" . addslashes($token) . "';
        
        // Adicionar token em todos os requests fetch
        const originalFetch = window.fetch;
        window.fetch = function(url, options = {}) {
            if (!options.headers) {
                options.headers = {};
            }
            
            // Adicionar token se for POST/PUT/PATCH/DELETE
            const method = options.method || 'GET';
            if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                options.headers['X-CSRF-Token'] = window.csrfToken;
            }
            
            return originalFetch(url, options);
        };
        
        // Adicionar token em formulários
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form[method=\"post\"], form[method=\"POST\"]');
            forms.forEach(function(form) {
                if (!form.querySelector('input[name=\"" . CSRF_TOKEN_NAME . "\"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = '" . CSRF_TOKEN_NAME . "';
                    input.value = window.csrfToken;
                    form.appendChild(input);
                }
            });
        });
        </script>";
    }
}