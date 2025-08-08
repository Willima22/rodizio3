<?php
/**
 * Model Servico
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Models;

use Utils\Sanitizer;
use Utils\Validator;

class Servico extends BaseModel
{
    protected string $table = 'servicos';
    
    protected array $fillable = [
        'nome',
        'preco',
        'tempo_estimado',
        'ativo'
    ];
    
    protected array $casts = [
        'preco' => 'decimal',
        'ativo' => 'boolean'
    ];
    
    /**
     * Listar serviços ativos
     */
    public function listarAtivos(): array
    {
        return $this->findAll(['ativo' => 1], ['order_by' => 'nome']);
    }
    
    /**
     * Obter serviços mais utilizados
     */
    public function getMaisUtilizados(int $limite = 5): array
    {
        $sql = "SELECT s.*, COUNT(a.id) as total_atendimentos
                FROM servicos s
                LEFT JOIN atendimentos a ON s.id = a.id_servico 
                    AND a.status = 'finalizado'
                WHERE s.ativo = 1
                GROUP BY s.id
                ORDER BY total_atendimentos DESC, s.nome
                LIMIT :limite";
        
        return $this->db->fetchAll($sql, ['limite' => $limite]);
    }
    
    /**
     * Obter estatísticas do serviço
     */
    public function getEstatisticas(string $servicoId): array
    {
        // Total de atendimentos
        $sql = "SELECT COUNT(*) as total_atendimentos,
                       COUNT(CASE WHEN status = 'finalizado' THEN 1 END) as finalizados,
                       AVG(CASE WHEN hora_fim IS NOT NULL THEN 
                           TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fim) END) as tempo_medio_real,
                       SUM(valor_cobrado) as faturamento_total
                FROM atendimentos 
                WHERE id_servico = :servico_id";
        
        $stats = $this->db->fetchOne($sql, ['servico_id' => $servicoId]);
        
        // Distribuição por profissional
        $sql = "SELECT p.nome as profissional_nome, COUNT(a.id) as total
                FROM atendimentos a
                JOIN profissionais p ON a.id_profissional = p.id
                WHERE a.id_servico = :servico_id
                AND a.status = 'finalizado'
                GROUP BY p.id, p.nome
                ORDER BY total DESC";
        
        $porProfissional = $this->db->fetchAll($sql, ['servico_id' => $servicoId]);
        
        return [
            'total_atendimentos' => intval($stats['total_atendimentos']),
            'finalizados' => intval($stats['finalizados']),
            'tempo_medio_real' => round($stats['tempo_medio_real'] ?? 0, 1),
            'faturamento_total' => floatval($stats['faturamento_total'] ?? 0),
            'distribuicao_profissional' => $porProfissional
        ];
    }
    
    /**
     * Ativar/Desativar serviço
     */
    public function toggleStatus(string $id): bool
    {
        $servico = $this->find($id);
        
        if (!$servico) {
            return false;
        }
        
        $novoStatus = !$servico['ativo'];
        return $this->update($id, ['ativo' => $novoStatus]);
    }
    
    /**
     * Sanitizar dados específicos do serviço
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        if (isset($data['nome'])) {
            $sanitized['nome'] = Sanitizer::name($data['nome']);
        }
        
        if (isset($data['preco'])) {
            $sanitized['preco'] = Sanitizer::money($data['preco']);
        }
        
        if (isset($data['tempo_estimado'])) {
            $sanitized['tempo_estimado'] = Sanitizer::time($data['tempo_estimado']);
        }
        
        if (isset($data['ativo'])) {
            $sanitized['ativo'] = intval($data['ativo']) === 1;
        }
        
        return array_merge($data, $sanitized);
    }
    
    /**
     * Validar dados específicos do serviço
     */
    protected function validateData(array $data, string $id = null): void
    {
        $validator = new Validator($data);
        
        // Nome obrigatório
        if (isset($data['nome'])) {
            $validator
                ->required('nome', 'Nome é obrigatório')
                ->min('nome', 2, 'Nome deve ter pelo menos 2 caracteres')
                ->max('nome', 255, 'Nome deve ter no máximo 255 caracteres');
        }
        
        // Preço obrigatório
        if (isset($data['preco'])) {
            $validator
                ->required('preco', 'Preço é obrigatório')
                ->numeric('preco', 'Preço deve ser um número')
                ->minValue('preco', 0.01, 'Preço deve ser maior que zero');
        }
        
        // Tempo estimado obrigatório
        if (isset($data['tempo_estimado'])) {
            $validator->required('tempo_estimado', 'Tempo estimado é obrigatório');
            
            // Validar formato HH:MM
            if (!preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $data['tempo_estimado'])) {
                $validator->addError('tempo_estimado', 'Tempo estimado deve estar no formato HH:MM:SS');
            }
        }
        
        if (!$validator->isValid()) {
            throw new \InvalidArgumentException($validator->getErrorsAsString());
        }
    }
}