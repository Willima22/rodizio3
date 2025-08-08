<?php
/**
 * Classe para Padronização de Respostas
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Utils;

class Response
{
    /**
     * Enviar resposta JSON de sucesso
     */
    public static function success($data = null, string $message = 'Operação realizada com sucesso', int $code = 200): void
    {
        self::sendJson(true, $data, null, $message, $code);
    }

    /**
     * Enviar resposta JSON de erro
     */
    public static function error(string $error, $data = null, int $code = 400): void
    {
        self::sendJson(false, $data, $error, null, $code);
    }

    /**
     * Enviar resposta JSON de não autorizado
     */
    public static function unauthorized(string $error = 'Acesso não autorizado'): void
    {
        self::sendJson(false, null, $error, null, 401);
    }

    /**
     * Enviar resposta JSON de não encontrado
     */
    public static function notFound(string $error = 'Recurso não encontrado'): void
    {
        self::sendJson(false, null, $error, null, 404);
    }

    /**
     * Enviar resposta JSON de erro interno
     */
    public static function serverError(string $error = 'Erro interno do servidor'): void
    {
        self::sendJson(false, null, $error, null, 500);
    }

    /**
     * Enviar resposta JSON de validação
     */
    public static function validationError(array $errors): void
    {
        self::sendJson(false, null, 'Dados inválidos', null, 422, $errors);
    }

    /**
     * Enviar resposta JSON personalizada
     */
    public static function custom(bool $ok, $data = null, string $error = null, string $message = null, int $code = 200): void
    {
        self::sendJson($ok, $data, $error, $message, $code);
    }

    /**
     * Método privado para enviar JSON
     */
    private static function sendJson(bool $ok, $data = null, string $error = null, string $message = null, int $code = 200, array $validation = null): void
    {
        // Definir headers
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        
        // Montar resposta
        $response = [
            'ok' => $ok,
            'timestamp' => date('Y-m-d H:i:s'),
            'code' => $code
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($error !== null) {
            $response['error'] = $error;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($validation !== null) {
            $response['validation'] = $validation;
        }

        // Adicionar debug info em desenvolvimento
        if (APP_DEBUG) {
            $response['debug'] = [
                'memory_usage' => memory_get_usage(true),
                'execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms'
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Redirecionar usuário
     */
    public static function redirect(string $url, int $code = 302): void
    {
        header("Location: $url", true, $code);
        exit;
    }

    /**
     * Enviar header e continuar execução
     */
    public static function header(string $header): void
    {
        header($header);
    }

    /**
     * Definir código de status HTTP
     */
    public static function status(int $code): void
    {
        http_response_code($code);
    }

    /**
     * Verificar se é requisição AJAX
     */
    public static function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Verificar se é requisição POST
     */
    public static function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Verificar se é requisição GET
     */
    public static function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * Obter método da requisição
     */
    public static function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Enviar arquivo para download
     */
    public static function download(string $filepath, string $filename = null): void
    {
        if (!file_exists($filepath)) {
            self::notFound('Arquivo não encontrado');
        }

        $filename = $filename ?: basename($filepath);
        $mimeType = mime_content_type($filepath);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: must-revalidate');
        
        readfile($filepath);
        exit;
    }

    /**
     * Limpar output buffer
     */
    public static function clean(): void
    {
        if (ob_get_level()) {
            ob_clean();
        }
    }
}