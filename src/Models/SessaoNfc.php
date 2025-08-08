<?php

namespace FastEscova\Models;

require_once __DIR__ . '/BaseModel.php';

/**
 * Modelo SessaoNfc - Gerencia sessões NFC
 * 
 * Controla dispositivos NFC registrados e suas sessões ativas
 */
class SessaoNfc extends BaseModel {
    
    protected $table = 'sessoes_nfc';
    
    /**
     * Colunas permitidas para inserção/atualização
     */
    protected $fillable = [
        'usuario_id',
        'nfc_id',
        'device_info',
        'ip_address',
        'user_agent',
        'status',
        'token_sessao',
        'data_inicio',
        'data_fim',
        'ultima_atividade'
    ];
    
    /**
     * Iniciar nova sessão NFC
     * 
     * @param array $dados Dados da sessão
     * @return array Resultado da operação
     */
    public function iniciarSessao($dados) {
        try {
            // Finalizar sessões ativas do mesmo dispositivo
            $this->finalizarSessoesDispositivo($dados['nfc_id']);
            
            // Gerar token único para a sessão
            $tokenSessao = bin2hex(random_bytes(32));
            
            // Preparar dados da sessão
            $dadosSessao = [
                'usuario_id' => $dados['usuario_id'],
                'nfc_id' => $dados['nfc_id'],
                'device_info' => json_encode([
                    'tipo' => $dados['device_info']['tipo'] ?? 'desconhecido',
                    'modelo' => $dados['device_info']['modelo'] ?? 'N/A',
                    'sistema' => $dados['device_info']['sistema'] ?? 'N/A',
                    'navegador' => $dados['device_info']['navegador'] ?? 'N/A'
                ]),
                'ip_address' => $dados['ip_address'] ?? $_SERVER['REMOTE_ADDR'],
                'user_agent' => $dados['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'],
                'status' => 'ativa',
                'token_sessao' => $tokenSessao,
                'data_inicio' => date('Y-m-d H:i:s'),
                'ultima_atividade' => date('Y-m-d H:i:s')
            ];
            
            $id = $this->insert($dadosSessao);
            
            if ($id) {
                return [
                    'sucesso' => true,
                    'id' => $id,
                    'token' => $tokenSessao,
                    'mensagem' => 'Sessão NFC iniciada com sucesso'
                ];
            }
            
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao iniciar sessão NFC'
            ];
            
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar sessão por token
     * 
     * @param string $token Token da sessão
     * @return array|null Dados da sessão
     */
    public function buscarPorToken($token) {
        $sql = "
            SELECT s.*, u.nome, u.email, u.perfil 
            FROM {$this->table} s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE s.token_sessao = ? 
            AND s.status = 'ativa'
            AND s.ultima_atividade > DATE_SUB(NOW(), INTERVAL 4 HOUR)
        ";
        
        $result = $this->query($sql, [$token]);
        return $result[0] ?? null;
    }
    
    /**
     * Buscar sessão por NFC ID
     * 
     * @param string $nfcId ID do dispositivo NFC
     * @return array|null Dados da sessão
     */
    public function buscarPorNfcId($nfcId) {
        $sql = "
            SELECT s.*, u.nome, u.email, u.perfil 
            FROM {$this->table} s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE s.nfc_id = ? 
            AND s.status = 'ativa'
            ORDER BY s.data_inicio DESC
            LIMIT 1
        ";
        
        $result = $this->query($sql, [$nfcId]);
        return $result[0] ?? null;
    }
    
    /**
     * Atualizar última atividade da sessão
     * 
     * @param string $token Token da sessão
     * @return bool Sucesso da operação
     */
    public function atualizarAtividade($token) {
        return $this->update(
            ['ultima_atividade' => date('Y-m-d H:i:s')],
            ['token_sessao' => $token]
        );
    }
    
    /**
     * Finalizar sessão específica
     * 
     * @param string $token Token da sessão
     * @return bool Sucesso da operação
     */
    public function finalizarSessao($token) {
        return $this->update(
            [
                'status' => 'finalizada',
                'data_fim' => date('Y-m-d H:i:s')
            ],
            ['token_sessao' => $token]
        );
    }
    
    /**
     * Finalizar todas as sessões de um dispositivo
     * 
     * @param string $nfcId ID do dispositivo NFC
     * @return bool Sucesso da operação
     */
    public function finalizarSessoesDispositivo($nfcId) {
        return $this->update(
            [
                'status' => 'finalizada',
                'data_fim' => date('Y-m-d H:i:s')
            ],
            [
                'nfc_id' => $nfcId,
                'status' => 'ativa'
            ]
        );
    }
    
    /**
     * Finalizar todas as sessões de um usuário
     * 
     * @param int $usuarioId ID do usuário
     * @return bool Sucesso da operação
     */
    public function finalizarSessoesUsuario($usuarioId) {
        return $this->update(
            [
                'status' => 'finalizada',
                'data_fim' => date('Y-m-d H:i:s')
            ],
            [
                'usuario_id' => $usuarioId,
                'status' => 'ativa'
            ]
        );
    }
    
    /**
     * Listar sessões ativas
     * 
     * @param array $filtros Filtros opcionais
     * @return array Lista de sessões
     */
    public function listarSessoesAtivas($filtros = []) {
        $where = ["s.status = 'ativa'"];
        $params = [];
        
        if (!empty($filtros['usuario_id'])) {
            $where[] = "s.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        
        if (!empty($filtros['nfc_id'])) {
            $where[] = "s.nfc_id = ?";
            $params[] = $filtros['nfc_id'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT 
                s.*,
                u.nome,
                u.email,
                u.perfil,
                TIMESTAMPDIFF(MINUTE, s.ultima_atividade, NOW()) as minutos_inativa
            FROM {$this->table} s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE {$whereClause}
            ORDER BY s.ultima_atividade DESC
        ";
        
        return $this->query($sql, $params);
    }
    
    /**
     * Limpar sessões expiradas
     * 
     * @param int $horasExpiracao Horas para considerar sessão expirada (padrão: 4)
     * @return int Número de sessões limpas
     */
    public function limparSessoesExpiradas($horasExpiracao = 4) {
        $sql = "
            UPDATE {$this->table} 
            SET status = 'expirada', data_fim = NOW()
            WHERE status = 'ativa' 
            AND ultima_atividade < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$horasExpiracao]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Obter estatísticas de sessões NFC
     * 
     * @param array $filtros Filtros para o período
     * @return array Estatísticas
     */
    public function obterEstatisticas($filtros = []) {
        $dataInicio = $filtros['data_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
        $dataFim = $filtros['data_fim'] ?? date('Y-m-d');
        
        // Sessões por status
        $sql1 = "
            SELECT 
                status,
                COUNT(*) as total
            FROM {$this->table}
            WHERE DATE(data_inicio) BETWEEN ? AND ?
            GROUP BY status
        ";
        
        // Sessões por usuário
        $sql2 = "
            SELECT 
                u.nome,
                u.perfil,
                COUNT(s.id) as total_sessoes,
                AVG(TIMESTAMPDIFF(MINUTE, s.data_inicio, 
                    COALESCE(s.data_fim, s.ultima_atividade))) as duracao_media
            FROM {$this->table} s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE DATE(s.data_inicio) BETWEEN ? AND ?
            GROUP BY s.usuario_id, u.nome, u.perfil
            ORDER BY total_sessoes DESC
        ";
        
        // Sessões por dia
        $sql3 = "
            SELECT 
                DATE(data_inicio) as data,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'ativa' THEN 1 END) as ativas,
                COUNT(CASE WHEN status = 'finalizada' THEN 1 END) as finalizadas,
                COUNT(CASE WHEN status = 'expirada' THEN 1 END) as expiradas
            FROM {$this->table}
            WHERE DATE(data_inicio) BETWEEN ? AND ?
            GROUP BY DATE(data_inicio)
            ORDER BY data DESC
        ";
        
        return [
            'por_status' => $this->query($sql1, [$dataInicio, $dataFim]),
            'por_usuario' => $this->query($sql2, [$dataInicio, $dataFim]),
            'por_dia' => $this->query($sql3, [$dataInicio, $dataFim])
        ];
    }
    
    /**
     * Verificar se dispositivo NFC está registrado
     * 
     * @param string $nfcId ID do dispositivo NFC
     * @return bool Se está registrado
     */
    public function dispositivoRegistrado($nfcId) {
        $sql = "
            SELECT COUNT(*) as total
            FROM {$this->table}
            WHERE nfc_id = ?
        ";
        
        $result = $this->query($sql, [$nfcId]);
        return ($result[0]['total'] ?? 0) > 0;
    }
    
    /**
     * Obter histórico de um dispositivo NFC
     * 
     * @param string $nfcId ID do dispositivo
     * @param int $limite Limite de registros
     * @return array Histórico
     */
    public function historicoDispositivo($nfcId, $limite = 20) {
        $sql = "
            SELECT 
                s.*,
                u.nome,
                u.email,
                u.perfil
            FROM {$this->table} s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE s.nfc_id = ?
            ORDER BY s.data_inicio DESC
            LIMIT ?
        ";
        
        return $this->query($sql, [$nfcId, $limite]);
    }
}

?>