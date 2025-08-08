<?php
/**
 * Model Atendimento
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Models;

use Utils\Sanitizer;
use Utils\Validator;
use Utils\Date;

class Atendimento extends BaseModel
{
    protected string $table = 'atendimentos';
    
    protected array $fillable = [
        'id_cliente',
        'id_profissional',
        'id_servico',
        'hora_inicio',
        'hora_fim',
        'status',
        'valor_cobrado',
        'observacoes'
    ];
    
    protected array $casts = [
        'valor_cobrado' => 'decimal'
    ];
    
    // Status válidos
    const STATUS_AGUARDANDO = 'aguardando';
    const STATUS_EM_ANDAMENTO = 'em_andamento';
    const STATUS_FINALIZADO = 'finalizado';
    const STATUS_CANCELADO = 'cancelado';
    
    /**
     * Obter próximo da fila (FIFO)
     */
    public function getProximoDaFila(): ?array
    {
        $sql = "SELECT * FROM atendimentos 
                WHERE status = :status 
                ORDER BY criado_em ASC 
                LIMIT 1";
        
        $result = $this->db->fetchOne($sql, ['status' => self::STATUS_AGUARDANDO]);
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Obter fila de espera com detalhes
     */
    public function getFilaEspera(): array
    {
        $sql = "SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone,
                       s.nome as servico_nome, s.preco as servico_preco, s.tempo_estimado,
                       TIMESTAMPDIFF(MINUTE, a.criado_em, NOW()) as tempo_espera
                FROM atendimentos a
                JOIN clientes c ON a.id_cliente = c.id
                JOIN servicos s ON a.id_servico = s.id
                WHERE a.status = :status
                ORDER BY a.criado_em ASC";
        
        return $this->db->fetchAll($sql, ['status' => self::STATUS_AGUARDANDO]);
    }
    
    /**
     * Distribuir cliente automaticamente
     */
    public function distribuirClienteAutomatico(): ?array
    {
        $this->beginTransaction();
        
        try {
            // Buscar próximo cliente da fila
            $proximoCliente = $this->getProximoDaFila();
            
            if (!$proximoCliente) {
                $this->rollback();
                return null;
            }
            
            // Buscar próxima profissional disponível
            $profissionalModel = new Profissional();
            $proximaProfissional = $profissionalModel->getProximaDisponivel();
            
            if (!$proximaProfissional) {
                $this->rollback();
                throw new \Exception('Nenhuma profissional disponível no momento');
            }
            
            // Iniciar atendimento
            $sucesso = $this->iniciarAtendimento(
                $proximoCliente['id'], 
                $proximaProfissional['id']
            );
            
            if (!$sucesso) {
                $this->rollback();
                throw new \Exception('Erro ao iniciar atendimento');
            }
            
            $this->commit();
            
            // Retornar dados da distribuição
            return [
                'atendimento_id' => $proximoCliente['id'],
                'cliente_id' => $proximoCliente['id_cliente'],
                'profissional_id' => $proximaProfissional['id'],
                'cliente_nome' => $this->getClienteNome($proximoCliente['id_cliente']),
                'profissional_nome' => $proximaProfissional['nome'],
                'servico_nome' => $this->getServicoNome($proximoCliente['id_servico'])
            ];
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Forçar distribuição manual
     */
    public function forcarDistribuicao(string $atendimentoId, string $profissionalId): bool
    {
        $this->beginTransaction();
        
        try {
            // Verificar se atendimento está aguardando
            $atendimento = $this->find($atendimentoId);
            
            if (!$atendimento || $atendimento['status'] !== self::STATUS_AGUARDANDO) {
                throw new \Exception('Atendimento não está aguardando');
            }
            
            // Verificar se profissional está livre
            $profissionalModel = new Profissional();
            $profissional = $profissionalModel->find($profissionalId);
            
            if (!$profissional || $profissional['status'] !== Profissional::STATUS_LIVRE) {
                throw new \Exception('Profissional não está disponível');
            }
            
            // Iniciar atendimento
            $sucesso = $this->iniciarAtendimento($atendimentoId, $profissionalId);
            
            if (!$sucesso) {
                $this->rollback();
                return false;
            }
            
            $this->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Iniciar atendimento
     */
    public function iniciarAtendimento(string $atendimentoId, string $profissionalId): bool
    {
        // Atualizar atendimento
        $atendimentoAtualizado = $this->update($atendimentoId, [
            'id_profissional' => $profissionalId,
            'status' => self::STATUS_EM_ANDAMENTO,
            'hora_inicio' => date('Y-m-d H:i:s')
        ]);
        
        if (!$atendimentoAtualizado) {
            return false;
        }
        
        // Atualizar status da profissional
        $profissionalModel = new Profissional();
        return $profissionalModel->iniciarAtendimento($profissionalId);
    }
    
    /**
     * Finalizar atendimento
     */
    public function finalizarAtendimento(string $atendimentoId, array $dados = []): bool
    {
        $this->beginTransaction();
        
        try {
            // Buscar atendimento
            $atendimento = $this->find($atendimentoId);
            
            if (!$atendimento || $atendimento['status'] !== self::STATUS_EM_ANDAMENTO) {
                throw new \Exception('Atendimento não está em andamento');
            }
            
            // Dados para finalização
            $dadosFinalizacao = [
                'status' => self::STATUS_FINALIZADO,
                'hora_fim' => date('Y-m-d H:i:s')
            ];
            
            if (isset($dados['valor_cobrado'])) {
                $dadosFinalizacao['valor_cobrado'] = $dados['valor_cobrado'];
            }
            
            if (isset($dados['observacoes'])) {
                $dadosFinalizacao['observacoes'] = $dados['observacoes'];
            }
            
            // Atualizar atendimento
            $this->update($atendimentoId, $dadosFinalizacao);
            
            // Atualizar profissional (voltar para livre e incrementar contador)
            $profissionalModel = new Profissional();
            $profissionalModel->finalizarAtendimento($atendimento['id_profissional']);
            
            $this->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Cancelar atendimento
     */
    public function cancelarAtendimento(string $atendimentoId, string $motivo = null): bool
    {
        $this->beginTransaction();
        
        try {
            // Buscar atendimento
            $atendimento = $this->find($atendimentoId);
            
            if (!$atendimento) {
                throw new \Exception('Atendimento não encontrado');
            }
            
            if ($atendimento['status'] === self::STATUS_FINALIZADO) {
                throw new \Exception('Não é possível cancelar atendimento finalizado');
            }
            
            // Dados para cancelamento
            $dadosCancelamento = [
                'status' => self::STATUS_CANCELADO,
                'observacoes' => $motivo
            ];
            
            // Se estava em andamento, definir hora fim
            if ($atendimento['status'] === self::STATUS_EM_ANDAMENTO) {
                $dadosCancelamento['hora_fim'] = date('Y-m-d H:i:s');
                
                // Liberar profissional
                if ($atendimento['id_profissional']) {
                    $profissionalModel = new Profissional();
                    $profissionalModel->update($atendimento['id_profissional'], [
                        'status' => Profissional::STATUS_LIVRE
                    ]);
                }
            }
            
            $this->update($atendimentoId, $dadosCancelamento);
            
            $this->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Obter atendimentos em andamento
     */
    public function getAtendimentosEmAndamento(): array
    {
        $sql = "SELECT a.*, c.nome as cliente_nome, c.telefone as cliente_telefone,
                       p.nome as profissional_nome, s.nome as servico_nome,
                       TIMESTAMPDIFF(MINUTE, a.hora_inicio, NOW()) as duracao_minutos
                FROM atendimentos a
                JOIN clientes c ON a.id_cliente = c.id
                JOIN profissionais p ON a.id_profissional = p.id
                JOIN servicos s ON a.id_servico = s.id
                WHERE a.status = :status
                ORDER BY a.hora_inicio";
        
        return $this->db->fetchAll($sql, ['status' => self::STATUS_EM_ANDAMENTO]);
    }
    
    /**
     * Obter relatório de atendimentos por período
     */
    public function getRelatorioAtendimentos(string $dataInicio, string $dataFim): array
    {
        $sql = "SELECT a.*, c.nome as cliente_nome, p.nome as profissional_nome,
                       s.nome as servico_nome, s.preco as servico_preco,
                       TIMESTAMPDIFF(MINUTE, a.criado_em, a.hora_inicio) as tempo_espera,
                       TIMESTAMPDIFF(MINUTE, a.hora_inicio, a.hora_fim) as duracao_atendimento
                FROM atendimentos a
                JOIN clientes c ON a.id_cliente = c.id
                LEFT JOIN profissionais p ON a.id_profissional = p.id
                JOIN servicos s ON a.id_servico = s.id
                WHERE DATE(a.criado_em) BETWEEN :data_inicio AND :data_fim
                ORDER BY a.criado_em DESC";
        
        return $this->db->fetchAll($sql, [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ]);
    }
    
    /**
     * Obter métricas do dia
     */
    public function getMetricasDoDia(string $data = null): array
    {
        $data = $data ?: date('Y-m-d');
        
        // Contadores por status
        $sql = "SELECT status, COUNT(*) as total 
                FROM atendimentos 
                WHERE DATE(criado_em) = :data 
                GROUP BY status";
        
        $statusCounts = $this->db->fetchAll($sql, ['data' => $data]);
        
        // Tempo médio de espera
        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, criado_em, hora_inicio)) as tempo_medio_espera
                FROM atendimentos 
                WHERE DATE(criado_em) = :data 
                AND status IN ('em_andamento', 'finalizado')
                AND hora_inicio IS NOT NULL";
        
        $tempoEspera = $this->db->fetchOne($sql, ['data' => $data]);
        
        // Tempo médio de atendimento
        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fim)) as tempo_medio_atendimento
                FROM atendimentos 
                WHERE DATE(criado_em) = :data 
                AND status = 'finalizado'
                AND hora_inicio IS NOT NULL AND hora_fim IS NOT NULL";
        
        $tempoAtendimento = $this->db->fetchOne($sql, ['data' => $data]);
        
        // Faturamento do dia
        $sql = "SELECT SUM(valor_cobrado) as faturamento_total
                FROM atendimentos 
                WHERE DATE(criado_em) = :data 
                AND status = 'finalizado'
                AND valor_cobrado IS NOT NULL";
        
        $faturamento = $this->db->fetchOne($sql, ['data' => $data]);
        
        return [
            'contadores' => $statusCounts,
            'tempo_medio_espera' => round($tempoEspera['tempo_medio_espera'] ?? 0, 1),
            'tempo_medio_atendimento' => round($tempoAtendimento['tempo_medio_atendimento'] ?? 0, 1),
            'faturamento_total' => floatval($faturamento['faturamento_total'] ?? 0)
        ];
    }
    
    /**
     * Obter atendimentos por hora do dia
     */
    public function getAtendimentosPorHora(string $data = null): array
    {
        $data = $data ?: date('Y-m-d');
        
        $sql = "SELECT HOUR(criado_em) as hora, COUNT(*) as total
                FROM atendimentos 
                WHERE DATE(criado_em) = :data
                GROUP BY HOUR(criado_em)
                ORDER BY hora";
        
        return $this->db->fetchAll($sql, ['data' => $data]);
    }
    
    /**
     * Verificar duplicidade diária do cliente
     */
    public function clienteTemAtendimentoHoje(string $clienteId): bool
    {
        $sql = "SELECT COUNT(*) as total 
                FROM atendimentos 
                WHERE id_cliente = :cliente_id 
                AND DATE(criado_em) = CURDATE() 
                AND status IN ('aguardando', 'em_andamento')";
        
        $result = $this->db->fetchOne($sql, ['cliente_id' => $clienteId]);
        return intval($result['total']) > 0;
    }
    
    /**
     * Obter nome do cliente
     */
    private function getClienteNome(string $clienteId): string
    {
        $sql = "SELECT nome FROM clientes WHERE id = :id";
        $result = $this->db->fetchOne($sql, ['id' => $clienteId]);
        return $result['nome'] ?? 'Cliente não encontrado';
    }
    
    /**
     * Obter nome do serviço
     */
    private function getServicoNome(string $servicoId): string
    {
        $sql = "SELECT nome FROM servicos WHERE id = :id";
        $result = $this->db->fetchOne($sql, ['id' => $servicoId]);
        return $result['nome'] ?? 'Serviço não encontrado';
    }
    
    /**
     * Sanitizar dados específicos do atendimento
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        if (isset($data['id_cliente'])) {
            $sanitized['id_cliente'] = Sanitizer::uuid($data['id_cliente']);
        }
        
        if (isset($data['id_profissional'])) {
            $sanitized['id_profissional'] = Sanitizer::uuid($data['id_profissional']);
        }
        
        if (isset($data['id_servico'])) {
            $sanitized['id_servico'] = Sanitizer::uuid($data['id_servico']);
        }
        
        if (isset($data['valor_cobrado'])) {
            $sanitized['valor_cobrado'] = Sanitizer::money($data['valor_cobrado']);
        }
        
        if (isset($data['observacoes'])) {
            $sanitized['observacoes'] = Sanitizer::text($data['observacoes'], 500);
        }
        
        if (isset($data['status'])) {
            $sanitized['status'] = Sanitizer::input($data['status']);
        }
        
        return array_merge($data, $sanitized);
    }
    
    /**
     * Validar dados específicos do atendimento
     */
    protected function validateData(array $data, string $id = null): void
    {
        $validator = new Validator($data);
        
        // Cliente obrigatório
        if (isset($data['id_cliente'])) {
            $validator
                ->required('id_cliente', 'Cliente é obrigatório')
                ->uuid('id_cliente', 'ID do cliente inválido')
                ->exists('id_cliente', 'clientes', 'id', 'Cliente não existe');
        }
        
        // Serviço obrigatório
        if (isset($data['id_servico'])) {
            $validator
                ->required('id_servico', 'Serviço é obrigatório')
                ->uuid('id_servico', 'ID do serviço inválido')
                ->exists('id_servico', 'servicos', 'id', 'Serviço não existe');
        }
        
        // Profissional opcional mas válido
        if (isset($data['id_profissional']) && !empty($data['id_profissional'])) {
            $validator
                ->uuid('id_profissional', 'ID do profissional inválido')
                ->exists('id_profissional', 'profissionais', 'id', 'Profissional não existe');
        }
        
        // Status válido
        if (isset($data['status'])) {
            $statusValidos = [
                self::STATUS_AGUARDANDO,
                self::STATUS_EM_ANDAMENTO,
                self::STATUS_FINALIZADO,
                self::STATUS_CANCELADO
            ];
            $validator->in('status', $statusValidos, 'Status inválido');
        }
        
        // Valor cobrado
        if (isset($data['valor_cobrado']) && !empty($data['valor_cobrado'])) {
            $validator
                ->numeric('valor_cobrado', 'Valor cobrado deve ser um número')
                ->minValue('valor_cobrado', 0, 'Valor cobrado deve ser maior que zero');
        }
        
        if (!$validator->isValid()) {
            throw new \InvalidArgumentException($validator->getErrorsAsString());
        }
    }
}