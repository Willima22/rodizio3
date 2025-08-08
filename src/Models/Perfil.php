<?php
/**
 * Model Perfil
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Models;

class Perfil extends BaseModel
{
    protected string $table = 'perfis';
    
    protected array $fillable = [
        'nome',
        'descricao'
    ];
    
    /**
     * Buscar perfil por nome
     */
    public function findByName(string $nome): ?array
    {
        return $this->findBy(['nome' => $nome]);
    }
    
    /**
     * Listar todos os perfis ordenados
     */
    public function listarTodos(): array
    {
        return $this->findAll([], ['order_by' => 'nome']);
    }
    
    /**
     * Obter contagem de usuários por perfil
     */
    public function getContagemUsuarios(): array
    {
        $sql = "SELECT p.nome, p.descricao, COUNT(u.id) as total_usuarios
                FROM perfis p
                LEFT JOIN usuarios u ON p.id = u.id_perfil AND u.ativo = 1
                GROUP BY p.id, p.nome, p.descricao
                ORDER BY p.nome";
        
        return $this->db->fetchAll($sql);
    }
}