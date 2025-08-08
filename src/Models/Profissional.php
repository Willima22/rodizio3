<?php
/**
 * Model Profissional
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Models;

use Utils\Sanitizer;
use Utils\Validator;
use Utils\Date;

class Profissional extends BaseModel
{
    protected string $table = 'profissionais';
    
    protected array $fillable = [
        'nome',
        'nfc_uid',
        'senha',
        'status',
        'ordem_chegada',
        'total_atendimentos_dia',
        'ativo'
    ];
    
    protected array $hidden = [
        'senha'
    ];
    
    protected array $casts = [
        'ordem_chegada' => 'integer',
        'total_atendimentos_dia' => 'integer',
        'ativo' => 'boolean'
    ];
    
    // Status válidos
    const STATUS_LIVRE = 'livre';
    const STATUS_ATENDENDO = 'atendendo';
    const STATUS_AUSENTE = 'ausente';
    
    /**
     * Buscar profissional por NFC UID
     */
    public function findByNfcUid(string $nfcUid): ?array
    {
        $sql = "SELECT * FROM profissionais 
                WHERE nfc_uid = :nfc_uid AND ativo = 1 
                LIMIT 1";
        
        $result = $this->db->fetchOne($sql, ['nfc_uid' => $nfcUid]);
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Obter próxima profissional disponível (algoritmo de rodízio)
     */
    public function getProximaDisponivel(): ?array
    {
        $sql = "SELECT * FROM profissionais 
                WHERE status = :status 
                AND ativo = 1 
                AND ordem_chegada > 0
                ORDER BY total_atendimentos_dia ASC, ordem_chegada ASC
                LIMIT 1";
        
        $result = $this->db->fetchOne($sql, ['status' => self::STATUS_LIVRE]);
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Listar profissionais com status
     */
    public function listWithStatus(): array
    {
        $sql = "SELECT id, nome, status, ordem_chegada, total_atendimentos_dia, ativo
                FROM profissionais 
                ORDER BY 
                    CASE 
                        WHEN status = 'livre' THEN 1
                        WHEN status = 'atendendo' THEN 2
                        WHEN status = 'ausente' THEN 3
                    END,
                    ordem_chegada, nome";
        
        $profissionais = $this->db->fetchAll($sql);
        
        // Adicionar informações do atendimento atual se estiver atendendo
        foreach ($profissionais as &$prof) {
            if ($prof['status'] === self::STATUS_ATENDENDO) {
                $prof['atendimento_atual'] = $this->getAtendimentoAtual($prof['id']);
            }
        }
        
        return $profissionais;
    }
    
    /**
     * Definir chegada da profissional
     */
    public function definirChegada(string $id): bool
    {
        // Obter próxima ordem de chegada
        $sql = "SELECT COALESCE(MAX(ordem_chegada), 0) + 1 as proxima_ordem 
                FROM profissionais 
                WHERE ativo = 1";
        
        $result = $this->db->fetchOne($sql);
        $proximaOrdem = intval($result['proxima_ordem']);
        
        return $this->update($id, [
            'status' => self::STATUS_LIVRE,
            'ordem_chegada' => $proximaOrdem
        ]);
    }
    
    /**
     * Definir saída da profissional
     */
    public function definirSaida(string $id): bool
    {
        return $this->update($id, [
            'status' => self::STATUS_AUSENTE,
            'ordem_chegada' => 0
        ]);
    }
    
    /**
     * Iniciar atendimento
     */
    public function iniciarAtendimento(string $id): bool
    {
        return $this->update($id, [
            'status' => self::STATUS_ATENDENDO
        ]);
    }
    
    /**
     * Finalizar atendimento
     */
    public function finalizarAtendimento(string $id): bool
    {
        return $this->update($id, [
            'status' => self::STATUS_LIVRE,
            'total_atendimentos_dia' => $this->db->query(
                "SELECT total_atendimentos_dia + 1 FROM profissionais WHERE id = :id",
                ['id' => $id]
            )[0]['total_atendimentos_dia + 1']
        ]);
    }
    
    /**
     * Incrementar contador de atendimentos
     */
    public function incrementarAtendimentos(string $id): bool
    {
        $sql = "UPDATE profissionais 
                SET total_atendimentos_dia = total_atendimentos_dia + 1 
                WHERE id = :id";
        
        return $this->db->execute($sql, ['id' => $id]) > 0;
    }
    
    /**
     * Reset diário (zerar contadores)
     */
    public function resetDiario(): int
    {
        $sql = "UPDATE profissionais 
                SET ordem_chegada = 0, 
                    total_atendimentos_dia = 0,
                    status = :status";
        
        return $this->db->execute($sql, ['status' => self::STATUS_AUSENTE]);
    }
    
    /**
     * Obter atendimento atual da profissional
     */
    public function getAtendimentoAtual(string $profissionalId): ?array
    {
        $sql = "SELECT a.*, c.nome as cliente_nome, s.nome as servico_nome,
                       TIMESTAMPDIFF(MINUTE, a.hora_inicio, NOW()) as duracao_minutos
                FROM atendimentos a
                JOIN clientes c ON a.id_cliente = c.id
                JOIN servicos s ON a.id_servico = s.id
                WHERE a.id_profissional = :profissional_id 
                AND a.status = 'em_andamento'
                LIMIT 1";
        
        return $this->db->fetchOne($sql, ['profissional_id' => $profissionalId]);
    }
    
    /**
     * Obter histórico de atendimentos da profissional
     */
    public function getHistoricoAtendimentos(string $profissionalId, string $data = null): array
    {
        $data = $data ?: date('Y-m-d');
        
        $sql = "SELECT a.*, c.nome as cliente_nome, s.nome as servico_nome,
                       TIMESTAMPDIFF(MINUTE, a.hora_inicio, a.hora_fim) as duracao_minutos
                FROM atendimentos a
                JOIN clientes c ON a.id_cliente = c.id
                JOIN servicos s ON a.id_servico = s.id
                WHERE a.id_profissional = :profissional_id 
                AND DATE(a.criado_em) = :data
                ORDER BY a.criado_em DESC";
        
        return $this->db->fetchAll($sql, [
            'profissional_id' => $profissionalId,
            'data' => $data
        ]);
    }
    
    /**
     * Obter estatísticas da profissional
     */
    public function getEstatisticas(string $profissionalId, string $dataInicio = null, string $dataFim = null): array
    {
        $dataInicio = $dataInicio ?: date('Y-m-d', strtotime('-30 days'));
        $dataFim = $dataFim ?: date('Y-m-d');
        
        // Atendimentos no período
        $sql = "SELECT 
                    COUNT(*) as total_atendimentos,
                    COUNT(CASE WHEN status = 'finalizado' THEN 1 END) as finalizados,
                    COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as cancelados,
                    AVG(CASE WHEN hora_fim IS NOT NULL THEN 
                        TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fim) END) as tempo_medio_atendimento,
                    SUM(valor_cobrado) as total_faturado
                FROM atendimentos 
                WHERE id_profissional = :profissional_id
                AND DATE(criado_em) BETWEEN :data_inicio AND :data_fim";
        
        $stats = $this->db->fetchOne($sql, [
            'profissional_id' => $profissionalId,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ]);
        
        // Atendimentos por dia
        $sql = "SELECT DATE(criado_em) as data, COUNT(*) as total
                FROM atendimentos 
                WHERE id_profissional = :profissional_id
                AND DATE(criado_em) BETWEEN :data_inicio AND :data_fim
                AND status = 'finalizado'
                GROUP BY DATE(criado_em)
                ORDER BY data";
        
        $atendimentosPorDia = $this->db->fetchAll($sql, [
            'profissional_id' => $profissionalId,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ]);
        
        return [
            'total_atendimentos' => intval($stats['total_atendimentos']),
            'finalizados' => intval($stats['finalizados']),
            'cancelados' => intval($stats['cancelados']),
            'tempo_medio_atendimento' => round($stats['tempo_medio_atendimento'] ?? 0, 1),
            'total_faturado' => floatval($stats['total_faturado'] ?? 0),
            'atendimentos_por_dia' => $atendimentosPorDia
        ];
    }
    
    /**
     * Obter ranking de profissionais por performance
     */
    public function getRanking(string $dataInicio = null, string $dataFim = null): array
    {
        $dataInicio = $dataInicio ?: date('Y-m-01'); // Início do mês
        $dataFim = $dataFim ?: date('Y-m-d');
        
        $sql = "SELECT p.id, p.nome,
                       COUNT(a.id) as total_atendimentos,
                       COUNT(CASE WHEN a.status = 'finalizado' THEN 1 END) as finalizados,
                       AVG(CASE WHEN a.hora_fim IS NOT NULL THEN 
                           TIMESTAMPDIFF(MINUTE, a.hora_inicio, a.hora_fim) END) as tempo_medio,
                       SUM(a.valor_cobrado) as total_faturado
                FROM profissionais p
                LEFT JOIN atendimentos a ON p.id = a.id_profissional 
                    AND DATE(a.criado_em) BETWEEN :data_inicio AND :data_fim
                WHERE p.ativo = 1
                GROUP BY p.id, p.nome
                ORDER BY finalizados DESC, tempo_medio ASC";
        
        return $this->db->fetchAll($sql, [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ]);
    }
    
    /**
     * Verificar se NFC UID já existe
     */
    public function nfcUidExists(string $nfcUid, string $exceptId = null): bool
    {
        $sql = "SELECT COUNT(*) as total FROM profissionais WHERE nfc_uid = :nfc_uid";
        $params = ['nfc_uid' => $nfcUid];
        
        if ($exceptId) {
            $sql .= " AND id != :except_id";
            $params['except_id'] = $exceptId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return intval($result['total']) > 0;
    }
    
    /**
     * Sanitizar dados específicos do profissional
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        if (isset($data['nome'])) {
            $sanitized['nome'] = Sanitizer::name($data['nome']);
        }
        
        if (isset($data['nfc_uid'])) {
            $sanitized['nfc_uid'] = Sanitizer::nfcUid($data['nfc_uid']);
        }
        
        if (isset($data['status'])) {
            $sanitized['status'] = Sanitizer::input($data['status']);
        }
        
        if (isset($data['ordem_chegada'])) {
            $sanitized['ordem_chegada'] = Sanitizer::int($data['ordem_chegada']);
        }
        
        if (isset($data['total_atendimentos_dia'])) {
            $sanitized['total_atendimentos_dia'] = Sanitizer::int($data['total_atendimentos_dia']);
        }
        
        if (isset($data['ativo'])) {
            $sanitized['ativo'] = intval($data['ativo']) === 1;
        }
        
        return array_merge($data, $sanitized);
    }
    
    /**
     * Validar dados específicos do profissional
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
        
        // NFC UID único (se fornecido)
        if (isset($data['nfc_uid']) && !empty($data['nfc_uid'])) {
            $validator
                ->min('nfc_uid', 4, 'UID NFC deve ter pelo menos 4 caracteres')
                ->max('nfc_uid', 20, 'UID NFC deve ter no máximo 20 caracteres');
            
            if ($this->nfcUidExists($data['nfc_uid'], $id)) {
                $validator->addError('nfc_uid', 'Este UID NFC já está em uso');
            }
        }
        
        // Status válido
        if (isset($data['status'])) {
            $statusValidos = [self::STATUS_LIVRE, self::STATUS_ATENDENDO, self::STATUS_AUSENTE];
            $validator->in('status', $statusValidos, 'Status deve ser: ' . implode(', ', $statusValidos));
        }
        
        // Ordem de chegada
        if (isset($data['ordem_chegada'])) {
            $validator
                ->integer('ordem_chegada', 'Ordem de chegada deve ser um número inteiro')
                ->minValue('ordem_chegada', 0, 'Ordem de chegada deve ser maior ou igual a 0');
        }
        
        // Total de atendimentos do dia
        if (isset($data['total_atendimentos_dia'])) {
            $validator
                ->integer('total_atendimentos_dia', 'Total de atendimentos deve ser um número inteiro')
                ->minValue('total_atendimentos_dia', 0, 'Total de atendimentos deve ser maior ou igual a 0');
        }
        
        if (!$validator->isValid()) {
            throw new \InvalidArgumentException($validator->getErrorsAsString());
        }
    }
    
    /**
     * Formatar dados do profissional para exibição
     */
    public function formatarParaExibicao(array $profissional): array
    {
        $statusLabels = [
            self::STATUS_LIVRE => 'Livre',
            self::STATUS_ATENDENDO => 'Atendendo',
            self::STATUS_AUSENTE => 'Ausente'
        ];
        
        return [
            'id' => $profissional['id'],
            'nome' => $profissional['nome'],
            'status' => $profissional['status'],
            'status_label' => $statusLabels[$profissional['status']] ?? $profissional['status'],
            'ordem_chegada' => $profissional['ordem_chegada'],
            'total_atendimentos_dia' => $profissional['total_atendimentos_dia'],
            'tem_nfc' => !empty($profissional['nfc_uid']),
            'ativo' => $profissional['ativo'],
            'criado_em' => Date::toBrazilianDateTime($profissional['criado_em'])
        ];
    }
}