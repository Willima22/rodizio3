<?php
/**
 * Controller de Logs do Sistema
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Controllers;

use Utils\Response;
use Utils\Auth;
use Utils\Validator;
use Utils\Sanitizer;
use Utils\DB;
use Utils\Date;

class LogController
{
    /**
     * Registrar log no sistema
     */
    public static function registrar(string $tipo, ?string $usuarioId, string $descricao): bool
    {
        try {
            $db = DB::getInstance();
            
            $sql = "INSERT INTO logs_sistema (tipo, usuario_id, descricao, ip_address, user_agent) 
                    VALUES (:tipo, :usuario_id, :descricao, :ip, :user_agent)";
            
            $db->execute($sql, [
                'tipo' => $tipo,
                'usuario_id' => $usuarioId,
                'descricao' => $descricao,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Erro ao registrar log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Listar logs com filtros
     */
    public static function listar(): void
    {
        // Apenas admin pode ver logs
        if (!Auth::isAdmin()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            // Sanitizar parâmetros
            $data = Sanitizer::array($_GET, [
                'tipo' => 'input',
                'usuario_id' => 'uuid',
                'data_inicio' => 'date',
                'data_fim' => 'date',
                'limite' => 'int',
                'pagina' => 'int'
            ]);
            
            $limite = max(1, min($data['limite'] ?: 50, 200)); // Entre 1 e 200
            $pagina = max(1, $data['pagina'] ?: 1);
            $offset = ($pagina - 1) * $limite;
            
            $db = DB::getInstance();
            
            // Construir WHERE
            $where = [];
            $params = [];
            
            if (!empty($data['tipo'])) {
                $where[] = "l.tipo = :tipo";
                $params['tipo'] = $data['tipo'];
            }
            
            if (!empty($data['usuario_id'])) {
                $where[] = "l.usuario_id = :usuario_id";
                $params['usuario_id'] = $data['usuario_id'];
            }
            
            if (!empty($data['data_inicio'])) {
                $where[] = "DATE(l.criado_em) >= :data_inicio";
                $params['data_inicio'] = $data['data_inicio'];
            }
            
            if (!empty($data['data_fim'])) {
                $where[] = "DATE(l.criado_em) <= :data_fim";
                $params['data_fim'] = $data['data_fim'];
            }
            
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Query principal
            $sql = "SELECT l.*, 
                           COALESCE(u.nome, p.nome, 'Sistema') as usuario_nome,
                           COALESCE(pf.nome, 'N/A') as perfil_nome
                    FROM logs_sistema l
                    LEFT JOIN usuarios u ON l.usuario_id = u.id
                    LEFT JOIN profissionais p ON l.usuario_id = p.id
                    LEFT JOIN perfis pf ON u.id_perfil = pf.id
                    $whereClause
                    ORDER BY l.criado_em DESC
                    LIMIT :limite OFFSET :offset";
            
            $params['limite'] = $limite;
            $params['offset'] = $offset;
            
            $logs = $db->fetchAll($sql, $params);
            
            // Contar total
            $sqlCount = "SELECT COUNT(*) as total FROM logs_sistema l $whereClause";
            $countParams = array_filter($params, function($key) {
                return !in_array($key, ['limite', 'offset']);
            }, ARRAY_FILTER_USE_KEY);
            
            $total = $db->fetchOne($sqlCount, $countParams)['total'];
            
            // Formatar logs
            $logsFormatados = array_map(function($log) {
                return [
                    'id' => $log['id'],
                    'tipo' => $log['tipo'],
                    'usuario_nome' => $log['usuario_nome'],
                    'perfil_nome' => $log['perfil_nome'],
                    'descricao' => $log['descricao'],
                    'ip_address' => $log['ip_address'],
                    'criado_em' => $log['criado_em'],
                    'criado_em_formatado' => Date::toBrazilianDateTime($log['criado_em']),
                    'tempo_relativo' => Date::relative($log['criado_em'])
                ];
            }, $logs);
            
            Response::success([
                'logs' => $logsFormatados,
                'paginacao' => [
                    'total' => intval($total),
                    'pagina_atual' => $pagina,
                    'limite' => $limite,
                    'total_paginas' => ceil($total / $limite)
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao listar logs: " . $e->getMessage());
            Response::serverError('Erro ao carregar logs');
        }
    }
    
    /**
     * Obter estatísticas dos logs
     */
    public static function estatisticas(): void
    {
        // Apenas admin pode ver estatísticas
        if (!Auth::isAdmin()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            $db = DB::getInstance();
            
            // Logs por tipo
            $sql = "SELECT tipo, COUNT(*) as total 
                    FROM logs_sistema 
                    WHERE DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY tipo 
                    ORDER BY total DESC";
            $logsPorTipo = $db->fetchAll($sql);
            
            // Logs por dia (últimos 7 dias)
            $sql = "SELECT DATE(criado_em) as data, COUNT(*) as total 
                    FROM logs_sistema 
                    WHERE DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY DATE(criado_em) 
                    ORDER BY data DESC";
            $logsPorDia = $db->fetchAll($sql);
            
            // Usuários mais ativos
            $sql = "SELECT l.usuario_id, 
                           COALESCE(u.nome, p.nome, 'Sistema') as usuario_nome,
                           COUNT(*) as total_logs
                    FROM logs_sistema l
                    LEFT JOIN usuarios u ON l.usuario_id = u.id
                    LEFT JOIN profissionais p ON l.usuario_id = p.id
                    WHERE DATE(l.criado_em) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    AND l.usuario_id IS NOT NULL
                    GROUP BY l.usuario_id, usuario_nome
                    ORDER BY total_logs DESC
                    LIMIT 5";
            $usuariosAtivos = $db->fetchAll($sql);
            
            // Total de logs hoje
            $sql = "SELECT COUNT(*) as total FROM logs_sistema WHERE DATE(criado_em) = CURDATE()";
            $logsHoje = $db->fetchOne($sql)['total'];
            
            // Horários de pico
            $sql = "SELECT HOUR(criado_em) as hora, COUNT(*) as total 
                    FROM logs_sistema 
                    WHERE DATE(criado_em) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY HOUR(criado_em) 
                    ORDER BY total DESC 
                    LIMIT 5";
            $horariosPico = $db->fetchAll($sql);
            
            Response::success([
                'logs_por_tipo' => $logsPorTipo,
                'logs_por_dia' => $logsPorDia,
                'usuarios_ativos' => $usuariosAtivos,
                'logs_hoje' => intval($logsHoje),
                'horarios_pico' => $horariosPico
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            Response::serverError('Erro ao carregar estatísticas');
        }
    }
    
    /**
     * Limpar logs antigos
     */
    public static function limpar(): void
    {
        // Apenas admin pode limpar logs
        if (!Auth::isAdmin()) {
            Response::unauthorized('Acesso negado');
        }
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'dias' => 'int'
            ]);
            
            $dias = max(7, min($data['dias'] ?: 90, 365)); // Entre 7 e 365 dias
            
            $db = DB::getInstance();
            
            $sql = "DELETE FROM logs_sistema 
                    WHERE criado_em < DATE_SUB(NOW(), INTERVAL :dias DAY)";
            
            $removidos = $db->execute($sql, ['dias' => $dias]);
            
            // Log da limpeza
            self::registrar('sistema', Auth::getUserId(), "Limpeza de logs: $removidos registros removidos (mais de $dias dias)");
            
            Response::success([
                'registros_removidos' => $removidos,
                'dias_mantidos' => $dias
            ], 'Limpeza realizada com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao limpar logs: " . $e->getMessage());
            Response::serverError('Erro ao limpar logs');
        }
    }
    
    /**
     * Logs de login específicos
     */
    public static function logLogin(string $usuarioId, string $tipoLogin): void
    {
        $descricao = "Login realizado via $tipoLogin";
        self::registrar('login', $usuarioId, $descricao);
    }
    
    /**
     * Logs de logout específicos
     */
    public static function logLogout(string $usuarioId, string $tipoLogin): void
    {
        $descricao = "Logout realizado via $tipoLogin";
        self::registrar('logout', $usuarioId, $descricao);
    }
    
    /**
     * Logs de atendimento específicos
     */
    public static function logAtendimento(string $acao, string $atendimentoId, ?string $clienteId = null, ?string $profissionalId = null): void
    {
        $descricao = "Atendimento $acao - ID: $atendimentoId";
        
        if ($clienteId) {
            $descricao .= " | Cliente: $clienteId";
        }
        
        if ($profissionalId) {
            $descricao .= " | Profissional: $profissionalId";
        }
        
        self::registrar('atendimento', Auth::getUserId(), $descricao);
    }
    
    /**
     * Logs de sistema específicos
     */
    public static function logSistema(string $descricao): void
    {
        self::registrar('sistema', Auth::getUserId(), $descricao);
    }
    
    /**
     * Logs de erro específicos
     */
    public static function logErro(string $descricao, string $contexto = ''): void
    {
        $descricaoCompleta = $descricao;
        
        if ($contexto) {
            $descricaoCompleta .= " | Contexto: $contexto";
        }
        
        self::registrar('erro', Auth::getUserId(), $descricaoCompleta);
    }
    
    /**
     * Exportar logs para CSV
     */
    public static function exportar(): void
    {
        // Apenas admin pode exportar
        if (!Auth::isAdmin()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            // Sanitizar parâmetros
            $data = Sanitizer::array($_GET, [
                'data_inicio' => 'date',
                'data_fim' => 'date'
            ]);
            
            $dataInicio = $data['data_inicio'] ?: date('Y-m-d', strtotime('-30 days'));
            $dataFim = $data['data_fim'] ?: date('Y-m-d');
            
            $db = DB::getInstance();
            
            $sql = "SELECT l.tipo, l.descricao, l.ip_address, l.criado_em,
                           COALESCE(u.nome, p.nome, 'Sistema') as usuario_nome
                    FROM logs_sistema l
                    LEFT JOIN usuarios u ON l.usuario_id = u.id
                    LEFT JOIN profissionais p ON l.usuario_id = p.id
                    WHERE DATE(l.criado_em) BETWEEN :data_inicio AND :data_fim
                    ORDER BY l.criado_em DESC";
            
            $logs = $db->fetchAll($sql, [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);
            
            // Gerar CSV
            $filename = 'logs_' . $dataInicio . '_' . $dataFim . '_' . date('His') . '.csv';
            $filepath = UPLOADS_PATH . '/' . $filename;
            
            $file = fopen($filepath, 'w');
            
            // Header do CSV
            fputcsv($file, [
                'Data/Hora',
                'Tipo',
                'Usuário',
                'Descrição',
                'IP'
            ], CSV_DELIMITER);
            
            // Dados
            foreach ($logs as $log) {
                fputcsv($file, [
                    Date::toBrazilianDateTime($log['criado_em']),
                    $log['tipo'],
                    $log['usuario_nome'],
                    $log['descricao'],
                    $log['ip_address']
                ], CSV_DELIMITER);
            }
            
            fclose($file);
            
            // Log da exportação
            self::registrar('sistema', Auth::getUserId(), "Exportação de logs: $filename");
            
            Response::success([
                'filename' => $filename,
                'total_logs' => count($logs),
                'download_url' => '/uploads/' . $filename
            ], 'Logs exportados com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao exportar logs: " . $e->getMessage());
            Response::serverError('Erro ao exportar logs');
        }
    }
}