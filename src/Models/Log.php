<?php
/**
 * Model Log
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Models;

class Log extends BaseModel
{
    protected string $table = 'logs_sistema';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'tipo',
        'usuario_id',
        'descricao',
        'ip_address',
        'user_agent'
    ];
    
    protected array $casts = [
        'id' => 'integer'
    ];
    
    // Tipos de log
    const TIPO_LOGIN = 'login';
    const TIPO_LOGOUT = 'logout';
    const TIPO_ATENDIMENTO = 'atendimento';
    const TIPO_ERRO = 'erro';
    const TIPO_SISTEMA = 'sistema';
    
    /**
     * Registrar log
     */
    public function registrar(string $tipo, ?string $usuarioId, string $descricao): bool
    {
        try {
            $this->create([
                'tipo' => $tipo,
                'usuario_id' => $usuarioId,
                'descricao' => $descricao,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao registrar log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter logs por tipo
     */
    public function getPorTipo(string $tipo, int $limite = 100): array
    {
        return $this->findAll(['tipo' => $tipo], [
            'order_by' => 'criado_em DESC',
            'limit' => $limite
        ]);
    }
    
    /**
     * Obter logs de um usuário
     */
    public function getPorUsuario(string $usuarioId, int $limite = 100): array
    {
        return $this->findAll(['usuario_id' => $usuarioId], [
            'order_by' => 'criado_em DESC',
            'limit' => $limite
        ]);
    }
    
    /**
     * Limpar logs antigos
     */
    public function limparAntigos(int $dias = 90): int
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE criado_em < DATE_SUB(NOW(), INTERVAL :dias DAY)";
        
        return $this->db->execute($sql, ['dias' => $dias]);
    }
}