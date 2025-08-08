<?php
/**
 * Controller de Autenticação NFC
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Controllers;

use Utils\Response;
use Utils\Auth;
use Utils\Validator;
use Utils\Sanitizer;
use Utils\DB;

class NfcController
{
    /**
     * Processar login via NFC
     */
    public static function login(): void
    {
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            // Verificar se WebNFC é suportado (apenas informativo)
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (!self::isNfcSupported($userAgent)) {
                Response::error('NFC não é suportado neste dispositivo ou navegador');
            }
            
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'uid' => 'nfcUid'
            ]);
            
            // Validar dados
            $validator = new Validator($data);
            $validator
                ->required('uid', 'UID do NFC é obrigatório')
                ->min('uid', 4, 'UID deve ter pelo menos 4 caracteres')
                ->max('uid', 20, 'UID deve ter no máximo 20 caracteres');
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Tentar fazer login
            $success = Auth::loginProfissional($data['uid']);
            
            if (!$success) {
                self::logFailedNfcAttempt($data['uid']);
                Response::error('Cartão NFC não reconhecido ou profissional inativo');
            }
            
            // Login bem-sucedido
            $user = Auth::getUser();
            
            // Buscar dados adicionais do profissional
            $profissionalData = self::getProfissionalData($user['id']);
            
            Response::success([
                'user' => array_merge($user, $profissionalData),
                'redirect_url' => '/profissional/painel',
                'login_type' => 'nfc'
            ], 'Login NFC realizado com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro no login NFC: " . $e->getMessage());
            Response::serverError('Erro interno do servidor');
        }
    }
    
    /**
     * Associar UID NFC a um profissional
     */
    public static function associate(): void
    {
        // Verificar permissão (apenas gestor/admin)
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'profissional_id' => 'uuid',
                'uid' => 'nfcUid'
            ]);
            
            // Validar dados
            $validator = new Validator($data);
            $validator
                ->required('profissional_id', 'ID do profissional é obrigatório')
                ->uuid('profissional_id', 'ID do profissional inválido')
                ->required('uid', 'UID do NFC é obrigatório')
                ->exists('profissional_id', 'profissionais', 'id', 'Profissional não encontrado')
                ->unique('uid', 'profissionais', 'nfc_uid', null, 'Este cartão NFC já está associado a outro profissional');
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            $db = DB::getInstance();
            
            // Atualizar profissional
            $sql = "UPDATE profissionais 
                    SET nfc_uid = :uid, atualizado_em = NOW() 
                    WHERE id = :id AND ativo = 1";
            
            $affected = $db->execute($sql, [
                'uid' => $data['uid'],
                'id' => $data['profissional_id']
            ]);
            
            if ($affected === 0) {
                Response::error('Profissional não encontrado ou inativo');
            }
            
            // Log da associação
            self::logNfcAssociation($data['profissional_id'], $data['uid']);
            
            Response::success(null, 'Cartão NFC associado com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao associar NFC: " . $e->getMessage());
            Response::serverError('Erro ao associar cartão NFC');
        }
    }
    
    /**
     * Remover associação NFC de um profissional
     */
    public static function disassociate(): void
    {
        // Verificar permissão (apenas gestor/admin)
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'profissional_id' => 'uuid'
            ]);
            
            // Validar dados
            $validator = new Validator($data);
            $validator
                ->required('profissional_id', 'ID do profissional é obrigatório')
                ->uuid('profissional_id', 'ID do profissional inválido');
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            $db = DB::getInstance();
            
            // Remover associação
            $sql = "UPDATE profissionais 
                    SET nfc_uid = NULL, atualizado_em = NOW() 
                    WHERE id = :id";
            
            $affected = $db->execute($sql, ['id' => $data['profissional_id']]);
            
            if ($affected === 0) {
                Response::error('Profissional não encontrado');
            }
            
            // Remover sessões NFC ativas
            $sql = "DELETE FROM sessoes_nfc WHERE id_profissional = :id";
            $db->execute($sql, ['id' => $data['profissional_id']]);
            
            // Log da desassociação
            self::logNfcDisassociation($data['profissional_id']);
            
            Response::success(null, 'Associação NFC removida com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao remover associação NFC: " . $e->getMessage());
            Response::serverError('Erro ao remover associação NFC');
        }
    }
    
    /**
     * Verificar status do NFC
     */
    public static function status(): void
    {
        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $supported = self::isNfcSupported($userAgent);
            
            Response::success([
                'nfc_supported' => $supported,
                'browser_info' => [
                    'user_agent' => $userAgent,
                    'is_mobile' => self::isMobile($userAgent),
                    'is_android' => self::isAndroid($userAgent),
                    'is_chrome' => self::isChrome($userAgent)
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao verificar status NFC: " . $e->getMessage());
            Response::serverError('Erro ao verificar status NFC');
        }
    }
    
    /**
     * Listar profissionais e seus NIDs NFC
     */
    public static function listProfissionais(): void
    {
        // Verificar permissão (apenas gestor/admin)
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            $db = DB::getInstance();
            
            $sql = "SELECT id, nome, nfc_uid, status, ativo 
                    FROM profissionais 
                    ORDER BY nome";
            
            $profissionais = $db->fetchAll($sql);
            
            Response::success($profissionais);
            
        } catch (\Exception $e) {
            error_log("Erro ao listar profissionais NFC: " . $e->getMessage());
            Response::serverError('Erro ao listar profissionais');
        }
    }
    
    /**
     * Obter dados adicionais do profissional
     */
    private static function getProfissionalData(string $profissionalId): array
    {
        try {
            $db = DB::getInstance();
            
            $sql = "SELECT status, ordem_chegada, total_atendimentos_dia 
                    FROM profissionais 
                    WHERE id = :id";
            
            $data = $db->fetchOne($sql, ['id' => $profissionalId]);
            
            return $data ?: [];
            
        } catch (\Exception $e) {
            error_log("Erro ao buscar dados do profissional: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verificar se NFC é suportado
     */
    private static function isNfcSupported(string $userAgent): bool
    {
        // WebNFC só funciona em Chrome Android atualmente
        return self::isAndroid($userAgent) && self::isChrome($userAgent);
    }
    
    /**
     * Verificar se é dispositivo móvel
     */
    private static function isMobile(string $userAgent): bool
    {
        return preg_match('/Mobile|Android|iPhone|iPad/', $userAgent);
    }
    
    /**
     * Verificar se é Android
     */
    private static function isAndroid(string $userAgent): bool
    {
        return strpos($userAgent, 'Android') !== false;
    }
    
    /**
     * Verificar se é Chrome
     */
    private static function isChrome(string $userAgent): bool
    {
        return strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Edg') === false;
    }
    
    /**
     * Log de tentativa NFC falhada
     */
    private static function logFailedNfcAttempt(string $uid): void
    {
        try {
            $db = DB::getInstance();
            
            $sql = "INSERT INTO logs_sistema (tipo, descricao, ip_address, user_agent) 
                    VALUES ('login', :descricao, :ip, :user_agent)";
            
            $db->execute($sql, [
                'descricao' => "Tentativa de login NFC falhada - UID: $uid",
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao logar tentativa NFC falhada: " . $e->getMessage());
        }
    }
    
    /**
     * Log de associação NFC
     */
    private static function logNfcAssociation(string $profissionalId, string $uid): void
    {
        try {
            $db = DB::getInstance();
            
            $sql = "INSERT INTO logs_sistema (tipo, usuario_id, descricao, ip_address, user_agent) 
                    VALUES ('sistema', :usuario_id, :descricao, :ip, :user_agent)";
            
            $db->execute($sql, [
                'usuario_id' => Auth::getUserId(),
                'descricao' => "NFC UID $uid associado ao profissional $profissionalId",
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao logar associação NFC: " . $e->getMessage());
        }
    }
    
    /**
     * Log de desassociação NFC
     */
    private static function logNfcDisassociation(string $profissionalId): void
    {
        try {
            $db = DB::getInstance();
            
            $sql = "INSERT INTO logs_sistema (tipo, usuario_id, descricao, ip_address, user_agent) 
                    VALUES ('sistema', :usuario_id, :descricao, :ip, :user_agent)";
            
            $db->execute($sql, [
                'usuario_id' => Auth::getUserId(),
                'descricao' => "Associação NFC removida do profissional $profissionalId",
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao logar desassociação NFC: " . $e->getMessage());
        }
    }
}