<?php
/**
 * Model Cliente
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Models;

use Utils\Sanitizer;
use Utils\Validator;
use Utils\Date;

class Cliente extends BaseModel
{
    protected string $table = 'clientes';
    
    protected array $fillable = [
        'nome',
        'telefone',
        'email',
        'data_nascimento',
        'observacoes'
    ];
    
    protected array $casts = [
        'data_nascimento' => 'date'
    ];
    
    /**
     * Buscar cliente por nome e telefone
     */
    public function findByNameAndPhone(string $nome, string $telefone = null): ?array
    {
        $sql = "SELECT * FROM clientes WHERE nome = :nome";
        $params = ['nome' => $nome];
        
        if (!empty($telefone)) {
            $sql .= " AND telefone = :telefone";
            $params['telefone'] = $telefone;
        }
        
        $sql .= " ORDER BY criado_em DESC LIMIT 1";
        
        $result = $this->db->fetchOne($sql, $params);
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Buscar clientes com busca
     */
    public function search(string $termo, int $limite = 20): array
    {
        $sql = "SELECT * FROM clientes 
                WHERE nome LIKE :termo 
                OR telefone LIKE :termo 
                OR email LIKE :termo 
                ORDER BY nome 
                LIMIT :limite";
        
        return $this->db->fetchAll($sql, [
            'termo' => "%$termo%",
            'limite' => $limite
        ]);
    }
    
    /**
     * Verificar duplicidade no dia
     */
    public function temAtendimentoHoje(string $clienteId): bool
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
     * Obter histórico de atendimentos
     */
    public function getHistoricoAtendimentos(string $clienteId, int $limite = 10): array
    {
        $sql = "SELECT a.*, s.nome as servico_nome, s.preco as servico_preco,
                       p.nome as profissional_nome
                FROM atendimentos a
                JOIN servicos s ON a.id_servico = s.id
                LEFT JOIN profissionais p ON a.id_profissional = p.id
                WHERE a.id_cliente = :cliente_id
                ORDER BY a.criado_em DESC
                LIMIT :limite";
        
        return $this->db->fetchAll($sql, [
            'cliente_id' => $clienteId,
            'limite' => $limite
        ]);
    }
    
    /**
     * Obter estatísticas do cliente
     */
    public function getEstatisticas(string $clienteId): array
    {
        // Total de atendimentos
        $sql = "SELECT COUNT(*) as total_atendimentos,
                       COUNT(CASE WHEN status = 'finalizado' THEN 1 END) as finalizados,
                       COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as cancelados,
                       MIN(criado_em) as primeiro_atendimento,
                       MAX(criado_em) as ultimo_atendimento
                FROM atendimentos 
                WHERE id_cliente = :cliente_id";
        
        $stats = $this->db->fetchOne($sql, ['cliente_id' => $clienteId]);
        
        // Valor total gasto
        $sql = "SELECT SUM(valor_cobrado) as total_gasto
                FROM atendimentos 
                WHERE id_cliente = :cliente_id 
                AND status = 'finalizado' 
                AND valor_cobrado IS NOT NULL";
        
        $gasto = $this->db->fetchOne($sql, ['cliente_id' => $clienteId]);
        
        // Serviços mais utilizados
        $sql = "SELECT s.nome, COUNT(*) as total
                FROM atendimentos a
                JOIN servicos s ON a.id_servico = s.id
                WHERE a.id_cliente = :cliente_id
                AND a.status = 'finalizado'
                GROUP BY s.id, s.nome
                ORDER BY total DESC
                LIMIT 3";
        
        $servicosFavoritos = $this->db->fetchAll($sql, ['cliente_id' => $clienteId]);
        
        return [
            'total_atendimentos' => intval($stats['total_atendimentos']),
            'finalizados' => intval($stats['finalizados']),
            'cancelados' => intval($stats['cancelados']),
            'primeiro_atendimento' => $stats['primeiro_atendimento'],
            'ultimo_atendimento' => $stats['ultimo_atendimento'],
            'total_gasto' => floatval($gasto['total_gasto'] ?? 0),
            'servicos_favoritos' => $servicosFavoritos
        ];
    }
    
    /**
     * Listar clientes frequentes
     */
    public function getClientesFrequentes(int $limite = 10): array
    {
        $sql = "SELECT c.*, COUNT(a.id) as total_atendimentos,
                       MAX(a.criado_em) as ultimo_atendimento
                FROM clientes c
                JOIN atendimentos a ON c.id = a.id_cliente
                WHERE a.status = 'finalizado'
                GROUP BY c.id
                ORDER BY total_atendimentos DESC, ultimo_atendimento DESC
                LIMIT :limite";
        
        return $this->db->fetchAll($sql, ['limite' => $limite]);
    }
    
    /**
     * Listar aniversariantes do mês
     */
    public function getAniversariantesDoMes(int $mes = null): array
    {
        $mes = $mes ?: intval(date('m'));
        
        $sql = "SELECT *, DAY(data_nascimento) as dia_aniversario
                FROM clientes 
                WHERE MONTH(data_nascimento) = :mes
                AND data_nascimento IS NOT NULL
                ORDER BY DAY(data_nascimento)";
        
        return $this->db->fetchAll($sql, ['mes' => $mes]);
    }
    
    /**
     * Obter relatório de novos clientes
     */
    public function getRelatorioNovoClientes(string $dataInicio, string $dataFim): array
    {
        $sql = "SELECT DATE(criado_em) as data, COUNT(*) as novos_clientes
                FROM clientes 
                WHERE DATE(criado_em) BETWEEN :data_inicio AND :data_fim
                GROUP BY DATE(criado_em)
                ORDER BY data";
        
        return $this->db->fetchAll($sql, [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ]);
    }
    
    /**
     * Sanitizar dados específicos do cliente
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        if (isset($data['nome'])) {
            $sanitized['nome'] = Sanitizer::name($data['nome']);
        }
        
        if (isset($data['telefone'])) {
            $sanitized['telefone'] = Sanitizer::phone($data['telefone']);
        }
        
        if (isset($data['email'])) {
            $sanitized['email'] = Sanitizer::email($data['email']);
        }
        
        if (isset($data['data_nascimento'])) {
            $sanitized['data_nascimento'] = Sanitizer::date($data['data_nascimento']);
        }
        
        if (isset($data['observacoes'])) {
            $sanitized['observacoes'] = Sanitizer::text($data['observacoes'], 1000);
        }
        
        return array_merge($data, $sanitized);
    }
    
    /**
     * Validar dados específicos do cliente
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
        
        // Telefone opcional mas válido
        if (isset($data['telefone']) && !empty($data['telefone'])) {
            $validator->phone('telefone', 'Telefone deve ser válido');
        }
        
        // Email opcional mas válido
        if (isset($data['email']) && !empty($data['email'])) {
            $validator->email('email', 'Email deve ser válido');
        }
        
        // Data de nascimento opcional mas válida
        if (isset($data['data_nascimento']) && !empty($data['data_nascimento'])) {
            $validator->date('data_nascimento', 'Y-m-d', 'Data de nascimento deve ser válida');
            
            // Não pode ser futuro
            if (strtotime($data['data_nascimento']) > time()) {
                $validator->addError('data_nascimento', 'Data de nascimento não pode ser no futuro');
            }
            
            // Não pode ser muito antiga (mais de 120 anos)
            if (strtotime($data['data_nascimento']) < strtotime('-120 years')) {
                $validator->addError('data_nascimento', 'Data de nascimento muito antiga');
            }
        }
        
        // Observações opcional
        if (isset($data['observacoes'])) {
            $validator->max('observacoes', 1000, 'Observações devem ter no máximo 1000 caracteres');
        }
        
        if (!$validator->isValid()) {
            throw new \InvalidArgumentException($validator->getErrorsAsString());
        }
    }
    
    /**
     * Cadastrar cliente e criar atendimento
     */
    public function cadastrarComAtendimento(array $dadosCliente, string $servicoId): array
    {
        $this->beginTransaction();
        
        try {
            // Buscar cliente existente
            $cliente = $this->findByNameAndPhone(
                $dadosCliente['nome'], 
                $dadosCliente['telefone'] ?? null
            );
            
            if (!$cliente) {
                // Criar novo cliente
                $clienteId = $this->create($dadosCliente);
                $cliente = $this->find($clienteId);
            } else {
                $clienteId = $cliente['id'];
                
                // Verificar duplicidade no dia
                if ($this->temAtendimentoHoje($clienteId)) {
                    throw new \Exception('Cliente já possui atendimento agendado para hoje');
                }
            }
            
            // Criar atendimento
            $atendimentoModel = new Atendimento();
            $atendimentoId = $atendimentoModel->create([
                'id_cliente' => $clienteId,
                'id_servico' => $servicoId,
                'status' => 'aguardando'
            ]);
            
            $this->commit();
            
            return [
                'cliente' => $cliente,
                'atendimento_id' => $atendimentoId
            ];
            
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Buscar clientes inativos (sem atendimento há X dias)
     */
    public function getClientesInativos(int $dias = 90): array
    {
        $sql = "SELECT c.*, MAX(a.criado_em) as ultimo_atendimento
                FROM clientes c
                LEFT JOIN atendimentos a ON c.id = a.id_cliente
                GROUP BY c.id
                HAVING ultimo_atendimento IS NULL 
                OR ultimo_atendimento < DATE_SUB(NOW(), INTERVAL :dias DAY)
                ORDER BY ultimo_atendimento DESC";
        
        return $this->db->fetchAll($sql, ['dias' => $dias]);
    }
    
    /**
     * Calcular idade do cliente
     */
    public function calcularIdade(string $dataNascimento): ?int
    {
        if (empty($dataNascimento)) {
            return null;
        }
        
        $nascimento = new \DateTime($dataNascimento);
        $hoje = new \DateTime();
        
        return $hoje->diff($nascimento)->y;
    }
    
    /**
     * Formatar dados do cliente para exibição
     */
    public function formatarParaExibicao(array $cliente): array
    {
        return [
            'id' => $cliente['id'],
            'nome' => $cliente['nome'],
            'telefone' => $cliente['telefone'],
            'email' => $cliente['email'],
            'data_nascimento' => $cliente['data_nascimento'] ? 
                Date::toBrazilian($cliente['data_nascimento']) : null,
            'idade' => $cliente['data_nascimento'] ? 
                $this->calcularIdade($cliente['data_nascimento']) : null,
            'observacoes' => $cliente['observacoes'],
            'criado_em' => Date::toBrazilianDateTime($cliente['criado_em']),
            'criado_em_relativo' => Date::relative($cliente['criado_em'])
        ];
    }
}