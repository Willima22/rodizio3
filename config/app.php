<?php
/**
 * Configurações Gerais da Aplicação
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

// Configurações da aplicação
define('APP_NAME', 'Fast Escova');
define('APP_VERSION', '1.0.0');
define('APP_URL', $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']);

// Configurações de ambiente
define('APP_ENV', 'production'); // development, testing, production
define('APP_DEBUG', APP_ENV === 'development');

// Configurações de segurança
define('SESSION_NAME', 'FAST_ESCOVA_SESSION');
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hora

// Configurações de sessão
define('SESSION_LIFETIME', 3600 * 8); // 8 horas
define('SESSION_COOKIE_SECURE', false); // true para HTTPS
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Lax');

// Rate limiting
define('LOGIN_ATTEMPTS_MAX', 5);
define('LOGIN_BLOCK_TIME', 600); // 10 minutos

// Configurações de upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['csv', 'pdf', 'jpg', 'png']);

// Configurações de relatórios
define('REPORTS_MAX_ROWS', 10000);
define('CSV_DELIMITER', ',');
define('CSV_ENCLOSURE', '"');

// Configurações de polling
define('POLLING_INTERVAL', 12000); // 12 segundos em milliseconds

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
}

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Configurações de paths
define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('VIEWS_PATH', SRC_PATH . '/views');
define('UPLOADS_PATH', ROOT_PATH . '/public/uploads');