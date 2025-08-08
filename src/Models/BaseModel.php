<?php
/**
 * Model Base
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Models;

use Utils\DB;
use Utils\Sanitizer;
use Utils\Validator;

abstract class BaseModel
{
    protected DB $db;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $casts = [];
    protected array $dates = ['criado_em', 'atualizado_em'];
    
    public function __construct()
    {
        $this->db = DB::getInstance();
    }
    
    /**
     * Buscar por ID
     */
    public function find(string $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Buscar primeiro registro com condições
     */
    public function findBy(array $conditions): ?array
    {
        $where = $this->buildWhere($conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$where['clause']} LIMIT 1";
        $result = $this->db->fetchOne($sql, $where['params']);
        
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Buscar todos os registros
     */
    public function findAll(array $conditions = [], array $options = []): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = $this->buildWhere($conditions);
            $sql .= " WHERE {$where['clause']}";
            $params = $where['params'];
        }
        
        // Order by
        if (isset($options['order_by'])) {
            $sql .= " ORDER BY {$options['order_by']}";
        }
        
        // Limit
        if (isset($options['limit'])) {
            $sql .= " LIMIT {$options['limit']}";
            
            if (isset($options['offset'])) {
                $sql .= " OFFSET {$options['offset']}";
            }
        }
        
        $results = $this->db->fetchAll($sql, $params);
        
        return array_map([$this, 'castAttributes'], $results);
    }
    
    /**
     * Contar registros
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where = $this->buildWhere($conditions);
            $sql .= " WHERE {$where['clause']}";
            $params = $where['params'];
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return intval($result['total'] ?? 0);
    }
    
    /**
     * Criar novo registro
     */
    public function create(array $data): string
    {
        // Filtrar campos permitidos
        $data = $this->filterFillable($data);
        
        // Sanitizar dados
        $data = $this->sanitizeData($data);
        
        // Validar dados
        $this->validateData($data);
        
        // Gerar UUID se necessário
        if (!isset($data[$this->primaryKey]) && $this->primaryKey === 'id') {
            $data[$this->primaryKey] = $this->db->generateUuid();
        }
        
        // Adicionar timestamps
        if (in_array('criado_em', $this->dates)) {
            $data['criado_em'] = date('Y-m-d H:i:s');
        }
        
        if (in_array('atualizado_em', $this->dates)) {
            $data['atualizado_em'] = date('Y-m-d H:i:s');
        }
        
        // Construir query
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { return ":$field"; }, $fields);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->db->execute($sql, $data);
        
        return $data[$this->primaryKey];
    }
    
    /**
     * Atualizar registro
     */
    public function update(string $id, array $data): bool
    {
        // Filtrar campos permitidos
        $data = $this->filterFillable($data);
        
        // Sanitizar dados
        $data = $this->sanitizeData($data);
        
        // Validar dados
        $this->validateData($data, $id);
        
        // Adicionar timestamp de atualização
        if (in_array('atualizado_em', $this->dates)) {
            $data['atualizado_em'] = date('Y-m-d H:i:s');
        }
        
        // Construir query
        $sets = array_map(function($field) { return "$field = :$field"; }, array_keys($data));
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " 
                WHERE {$this->primaryKey} = :id";
        
        $data['id'] = $id;
        
        $affected = $this->db->execute($sql, $data);
        
        return $affected > 0;
    }
    
    /**
     * Deletar registro
     */
    public function delete(string $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $affected = $this->db->execute($sql, ['id' => $id]);
        
        return $affected > 0;
    }
    
    /**
     * Soft delete (se aplicável)
     */
    public function softDelete(string $id): bool
    {
        if (in_array('deletado_em', $this->dates)) {
            return $this->update($id, ['deletado_em' => date('Y-m-d H:i:s')]);
        }
        
        return $this->delete($id);
    }
    
    /**
     * Verificar se registro existe
     */
    public function exists(string $id): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        
        return intval($result['total']) > 0;
    }
    
    /**
     * Buscar com paginação
     */
    public function paginate(array $conditions = [], int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;
        
        $options = [
            'limit' => $perPage,
            'offset' => $offset
        ];
        
        $items = $this->findAll($conditions, $options);
        $total = $this->count($conditions);
        
        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_next' => ($page * $perPage) < $total,
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Executar query personalizada
     */
    public function query(string $sql, array $params = []): array
    {
        $results = $this->db->fetchAll($sql, $params);
        return array_map([$this, 'castAttributes'], $results);
    }
    
    /**
     * Executar query que retorna um único resultado
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        $result = $this->db->fetchOne($sql, $params);
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Iniciar transação
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }
    
    /**
     * Confirmar transação
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }
    
    /**
     * Desfazer transação
     */
    public function rollback(): bool
    {
        return $this->db->rollback();
    }
    
    /**
     * Construir cláusula WHERE
     */
    protected function buildWhere(array $conditions): array
    {
        $clauses = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                // Operador IN
                $placeholders = array_map(function($i) use ($field) { 
                    return ":{$field}_{$i}"; 
                }, array_keys($value));
                
                $clauses[] = "$field IN (" . implode(', ', $placeholders) . ")";
                
                foreach ($value as $i => $val) {
                    $params["{$field}_{$i}"] = $val;
                }
            } else {
                $clauses[] = "$field = :$field";
                $params[$field] = $value;
            }
        }
        
        return [
            'clause' => implode(' AND ', $clauses),
            'params' => $params
        ];
    }
    
    /**
     * Filtrar campos permitidos
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Remover campos ocultos
     */
    protected function hideAttributes(array $data): array
    {
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        
        return $data;
    }
    
    /**
     * Aplicar casting aos atributos
     */
    protected function castAttributes(array $data): array
    {
        foreach ($this->casts as $field => $type) {
            if (!isset($data[$field])) {
                continue;
            }
            
            switch ($type) {
                case 'int':
                case 'integer':
                    $data[$field] = intval($data[$field]);
                    break;
                case 'float':
                case 'decimal':
                    $data[$field] = floatval($data[$field]);
                    break;
                case 'bool':
                case 'boolean':
                    $data[$field] = boolval($data[$field]);
                    break;
                case 'json':
                    $data[$field] = json_decode($data[$field], true);
                    break;
                case 'date':
                    $data[$field] = date('Y-m-d', strtotime($data[$field]));
                    break;
                case 'datetime':
                    $data[$field] = date('Y-m-d H:i:s', strtotime($data[$field]));
                    break;
            }
        }
        
        return $this->hideAttributes($data);
    }
    
    /**
     * Sanitizar dados (override em classes filhas se necessário)
     */
    protected function sanitizeData(array $data): array
    {
        // Sanitização básica por padrão
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = Sanitizer::input($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Validar dados (override em classes filhas)
     */
    protected function validateData(array $data, string $id = null): void
    {
        // Validação básica - pode ser sobrescrita
    }
    
    /**
     * Obter esquema da tabela
     */
    public function getSchema(): array
    {
        return $this->db->getTableInfo($this->table);
    }
    
    /**
     * Truncar tabela (cuidado!)
     */
    public function truncate(): bool
    {
        $sql = "TRUNCATE TABLE {$this->table}";
        $this->db->execute($sql);
        return true;
    }
    
    /**
     * Obter estatísticas básicas da tabela
     */
    public function getStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    MIN(criado_em) as oldest_record,
                    MAX(criado_em) as newest_record
                FROM {$this->table}";
        
        return $this->db->fetchOne($sql) ?: [];
    }
}