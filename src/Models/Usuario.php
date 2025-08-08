<?php
/**
 * Model Usuario
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Models;

use Utils\Sanitizer;
use Utils\Validator;

class Usuario extends BaseModel
{
    protected string $table = 'usuarios';
    
    protected array $fillable = [
        'nome',
        'usuario',
        'senha',
        'id_perfil',
        'ativo'
    ];
    
    protected array $hidden = [
        'senha'
    ];
    
    protected array $casts = [
        'ativo' => 'boolean'
    ];
    
    /**
     * Buscar usuário para login
     */
    public function findForLogin(string $usuario): ?array
    {
        $sql = "SELECT u.*, p.nome as perfil_nome 
                FROM usuarios u 
                JOIN perfis p ON u.id_perfil = p.id 
                WHERE u.usuario = :usuario AND u.ativo = 1 
                LIMIT 1";
        
        $result = $this->db->fetchOne($sql, ['usuario' => $usuario]);
        
        if (!$result) {
            return null;
        }
        
        // Não esconder senha aqui pois precisamos para verificação
        return $result;
    }
    
    /**
     * Buscar usuário com perfil
     */
    public function findWithPerfil(string $id): ?array
    {
        $sql = "SELECT u.*, p.nome as perfil_nome, p.descricao as perfil_descricao
                FROM usuarios u 
                JOIN perfis p ON u.id_perfil = p.id 
                WHERE u.id = :id 
                LIMIT 1";
        
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Listar usuários com perfis
     */
    public function listWithPerfis(array $filters = []): array
    {
        $sql = "SELECT u.id, u.nome, u.usuario, u.ativo, u.criado_em,
                       p.nome as perfil_nome
                FROM usuarios u 
                JOIN perfis p ON u.id_perfil = p.id";
        
        $where = [];
        $params = [];
        
        if (isset($filters['ativo'])) {
            $where[] = "u.ativo = :ativo";
            $params['ativo'] = $filters['ativo'];
        }
        
        if (isset($filters['perfil'])) {
            $where[] = "p.nome = :perfil";
            $params['perfil'] = $filters['perfil'];
        }
        
        if (isset($filters['busca'])) {
            $where[] = "(u.nome LIKE :busca OR u.usuario LIKE :busca)";
            $params['busca'] = '%' . $filters['busca'] . '%';
        }
        
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY u.nome";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Verificar se usuário existe
     */
    public function usuarioExists(string $usuario, string $exceptId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM usuarios WHERE usuario = :usuario";
        $params = ['usuario' => $usuario];
        
        if ($exceptId) {
            $sql .= " AND id != :except_id";
            $params['except_id'] = $exceptId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return intval($result['total']) > 0;
    }
    
    /**
     * Ativar/Desativar usuário
     */
    public function toggleStatus(string $id): bool
    {
        $user = $this->find($id);
        
        if (!$user) {
            return false;
        }
        
        $novoStatus = !$user['ativo'];
        return $this->update($id, ['ativo' => $novoStatus]);
    }
    
    /**
     * Alterar senha
     */
    public function changePassword(string $id, string $novaSenha): bool
    {
        $senhaHash = password_hash($novaSenha, PASSWORD_BCRYPT);
        return $this->update($id, ['senha' => $senhaHash]);
    }
    
    /**
     * Buscar usuários por perfil
     */
    public function findByPerfil(string $perfilNome): array
    {
        $sql = "SELECT u.* 
                FROM usuarios u 
                JOIN perfis p ON u.id_perfil = p.id 
                WHERE p.nome = :perfil AND u.ativo = 1 
                ORDER BY u.nome";
        
        return $this->db->fetchAll($sql, ['perfil' => $perfilNome]);
    }
    
    /**
     * Obter estatísticas de usuários
     */
    public function getEstatisticas(): array
    {
        // Total por perfil
        $sql = "SELECT p.nome as perfil, COUNT(u.id) as total 
                FROM perfis p 
                LEFT JOIN usuarios u ON p.id = u.id_perfil AND u.ativo = 1 
                GROUP BY p.id, p.nome 
                ORDER BY p.nome";
        
        $porPerfil = $this->db->fetchAll($sql);
        
        // Usuários ativos vs inativos
        $sql = "SELECT 
                    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos,
                    SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as inativos,
                    COUNT(*) as total
                FROM usuarios";
        
        $status = $this->db->fetchOne($sql);
        
        // Usuários criados nos últimos 30 dias
        $sql = "SELECT COUNT(*) as total 
                FROM usuarios 
                WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $novos = $this->db->fetchOne($sql);
        
        return [
            'por_perfil' => $porPerfil,
            'status' => $status,
            'novos_ultimo_mes' => intval($novos['total'])
        ];
    }
    
    /**
     * Sanitizar dados específicos do usuário
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        if (isset($data['nome'])) {
            $sanitized['nome'] = Sanitizer::name($data['nome']);
        }
        
        if (isset($data['usuario'])) {
            $sanitized['usuario'] = Sanitizer::input($data['usuario'], 50);
            $sanitized['usuario'] = strtolower($sanitized['usuario']);
            $sanitized['usuario'] = preg_replace('/[^a-z0-9._-]/', '', $sanitized['usuario']);
        }
        
        if (isset($data['senha'])) {
            // Não sanitizar senha, apenas validar
            $sanitized['senha'] = $data['senha'];
        }
        
        if (isset($data['id_perfil'])) {
            $sanitized['id_perfil'] = Sanitizer::uuid($data['id_perfil']);
        }
        
        if (isset($data['ativo'])) {
            $sanitized['ativo'] = intval($data['ativo']) === 1;
        }
        
        return array_merge($data, $sanitized);
    }
    
    /**
     * Validar dados específicos do usuário
     */
    protected function validateData(array $data, string $id = null): void
    {
        $validator = new Validator($data);
        
        // Nome obrigatório
        if (isset($data['nome'])) {
            $validator
                ->required('nome', 'Nome é obrigatório')
                ->min('nome', 3, 'Nome deve ter pelo menos 3 caracteres')
                ->max('nome', 255, 'Nome deve ter no máximo 255 caracteres');
        }
        
        // Usuário obrigatório e único
        if (isset($data['usuario'])) {
            $validator
                ->required('usuario', 'Usuário é obrigatório')
                ->min('usuario', 3, 'Usuário deve ter pelo menos 3 caracteres')
                ->max('usuario', 50, 'Usuário deve ter no máximo 50 caracteres')
                ->regex('usuario', '/^[a-z0-9._-]+$/', 'Usuário deve conter apenas letras minúsculas, números, pontos, hífens e sublinhados');
            
            // Verificar unicidade
            if ($this->usuarioExists($data['usuario'], $id)) {
                $validator->addError('usuario', 'Este usuário já está em uso');
            }
        }
        
        // Senha (apenas para criação ou alteração)
        if (isset($data['senha']) && !empty($data['senha'])) {
            $validator
                ->min('senha', 6, 'Senha deve ter pelo menos 6 caracteres')
                ->max('senha', 255, 'Senha deve ter no máximo 255 caracteres');
            
            // Hash da senha
            $data['senha'] = password_hash($data['senha'], PASSWORD_BCRYPT);
        }
        
        // Perfil obrigatório
        if (isset($data['id_perfil'])) {
            $validator
                ->required('id_perfil', 'Perfil é obrigatório')
                ->uuid('id_perfil', 'ID do perfil inválido')
                ->exists('id_perfil', 'perfis', 'id', 'Perfil não existe');
        }
        
        if (!$validator->isValid()) {
            throw new \InvalidArgumentException($validator->getErrorsAsString());
        }
    }
    
    /**
     * Criar usuário com validação completa
     */
    public function createUser(array $data): string
    {
        // Validação extra para criação
        if (empty($data['senha'])) {
            throw new \InvalidArgumentException('Senha é obrigatória para novos usuários');
        }
        
        return $this->create($data);
    }
    
    /**
     * Buscar último login (se implementado futuramente)
     */
    public function getLastLogin(string $id): ?array
    {
        $sql = "SELECT criado_em 
                FROM logs_sistema 
                WHERE tipo = 'login' AND usuario_id = :id 
                ORDER BY criado_em DESC 
                LIMIT 1";
        
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
    
    /**
     * Obter atividade do usuário
     */
    public function getAtividade(string $id, int $dias = 30): array
    {
        $sql = "SELECT DATE(criado_em) as data, COUNT(*) as total 
                FROM logs_sistema 
                WHERE usuario_id = :id 
                AND criado_em >= DATE_SUB(NOW(), INTERVAL :dias DAY)
                GROUP BY DATE(criado_em) 
                ORDER BY data DESC";
        
        return $this->db->fetchAll($sql, ['id' => $id, 'dias' => $dias]);
    }
}