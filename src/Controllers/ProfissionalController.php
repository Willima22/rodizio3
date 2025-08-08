<?php
/**
 * Controller de Profissionais
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Controllers;

use Utils\Response;
use Utils\Auth;
use Utils\Validator;
use Utils\Sanitizer;
use Utils\CSRF;
use Models\Profissional;
use Models\Atendimento;
use Controllers\LogController;

class ProfissionalController
{
    /**
     * Obter status de todas as profissionais
     */
    public static function status(): void
    {
        try {
            $profissionalModel = new Profissional();
            $profissionais = $profissionalModel->listWithStatus();
            
            // Formatar dados
            $profissionaisFormatados = array_map(function($prof) use ($profissionalModel) {
                $formatado = $profissionalModel->formatarParaExibicao($prof);
                
                // Adicionar informações do atendimento atual se estiver atendendo
                if (isset($prof['atendimento_atual'])) {
                    $formatado['atendimento_atual'] = [
                        'id' => $prof['atendimento_atual']['id'],
                        'cliente_nome' => $prof['atendimento_atual']['cliente_nome'],
                        'servico_nome' => $prof['atendimento_atual']['servico_nome'],
                        'duracao_minutos' => intval($prof['atendimento_atual']['duracao_minutos']),
                        'hora_inicio' => $prof['atendimento_atual']['hora_inicio']
                    ];
                }
                
                return $formatado;
            }, $profissionais);
            
            // Separar por status
            $livres = array_filter($profissionaisFormatados, fn($p) => $p['status'] === 'livre');
            $atendendo = array_filter($profissionaisFormatados, fn($p) => $p['status'] === 'atendendo');
            $ausentes = array_filter($profissionaisFormatados, fn($p) => $p['status'] === 'ausente');
            
            Response::success([
                'profissionais' => $profissionaisFormatados,
                'resumo' => [
                    'total' => count($profissionaisFormatados),
                    'livres' => count($livres),
                    'atendendo' => count($atendendo),
                    'ausentes' => count($ausentes)
                ],
                'por_status' => [
                    'livres' => array_values($livres),
                    'atendendo' => array_values($atendendo),
                    'ausentes' => array_values($ausentes)
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter status das profissionais: " . $e->getMessage());
            Response::serverError('Erro ao carregar status das profissionais');
        }
    }
    
    /**
     * Registrar chegada da profissional
     */
    public static function chegada(): void
    {
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            $profissionalId = null;
            
            // Se é login NFC, usar ID da sessão
            if (Auth::getLoginType() === 'nfc' && Auth::isProfissional()) {
                $profissionalId = Auth::getUserId();
            } 
            // Se é gestor/admin, pode especificar ID
            elseif (Auth::isGestor()) {
                $profissionalId = Sanitizer::uuid($_POST['profissional_id'] ?? '');
                
                if (empty($profissionalId)) {
                    Response::error('ID do profissional é obrigatório');
                }
            } else {
                Response::unauthorized('Acesso negado');
            }
            
            $profissionalModel = new Profissional();
            $profissional = $profissionalModel->find($profissionalId);
            
            if (!$profissional) {
                Response::error('Profissional não encontrado');
            }
            
            if (!$profissional['ativo']) {
                Response::error('Profissional não está ativo');
            }
            
            // Registrar chegada
            $sucesso = $profissionalModel->definirChegada($profissionalId);
            
            if (!$sucesso) {
                Response::error('Erro ao registrar chegada');
            }
            
            // Log da ação
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Chegada registrada - Profissional: {$profissional['nome']}"
            );
            
            // Buscar dados atualizados
            $profissionalAtualizado = $profissionalModel->find($profissionalId);
            
            Response::success(
                $profissionalModel->formatarParaExibicao($profissionalAtualizado),
                'Chegada registrada com sucesso'
            );
            
        } catch (\Exception $e) {
            error_log("Erro ao registrar chegada: " . $e->getMessage());
            Response::serverError('Erro ao registrar chegada');
        }
    }
    
    /**
     * Registrar saída da profissional
     */
    public static function saida(): void
    {
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            $profissionalId = null;
            
            // Se é login NFC, usar ID da sessão
            if (Auth::getLoginType() === 'nfc' && Auth::isProfissional()) {
                $profissionalId = Auth::getUserId();
            } 
            // Se é gestor/admin, pode especificar ID
            elseif (Auth::isGestor()) {
                $profissionalId = Sanitizer::uuid($_POST['profissional_id'] ?? '');
                
                if (empty($profissionalId)) {
                    Response::error('ID do profissional é obrigatório');
                }
            } else {
                Response::unauthorized('Acesso negado');
            }
            
            $profissionalModel = new Profissional();
            $profissional = $profissionalModel->find($profissionalId);
            
            if (!$profissional) {
                Response::error('Profissional não encontrado');
            }
            
            // Verificar se não está atendendo
            if ($profissional['status'] === Profissional::STATUS_ATENDENDO) {
                Response::error('Não é possível registrar saída durante atendimento');
            }
            
            // Registrar saída
            $sucesso = $profissionalModel->definirSaida($profissionalId);
            
            if (!$sucesso) {
                Response::error('Erro ao registrar saída');
            }
            
            // Log da ação
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Saída registrada - Profissional: {$profissional['nome']}"
            );
            
            // Buscar dados atualizados
            $profissionalAtualizado = $profissionalModel->find($profissionalId);
            
            Response::success(
                $profissionalModel->formatarParaExibicao($profissionalAtualizado),
                'Saída registrada com sucesso'
            );
            
        } catch (\Exception $e) {
            error_log("Erro ao registrar saída: " . $e->getMessage());
            Response::serverError('Erro ao registrar saída');
        }
    }
    
    /**
     * Obter dados do profissional logado (para painel)
     */
    public static function meusDados(): void
    {
        if (!Auth::isProfissional()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            $profissionalId = Auth::getUserId();
            
            $profissionalModel = new Profissional();
            $profissional = $profissionalModel->find($profissionalId);
            
            if (!$profissional) {
                Response::error('Profissional não encontrado');
            }
            
            // Obter atendimento atual se estiver atendendo
            $atendimentoAtual = null;
            if ($profissional['status'] === Profissional::STATUS_ATENDENDO) {
                $atendimentoAtual = $profissionalModel->getAtendimentoAtual($profissionalId);
            }
            
            // Obter histórico do dia
            $historicoDia = $profissionalModel->getHistoricoAtendimentos($profissionalId);
            
            // Obter estatísticas do mês
            $inicioMes = date('Y-m-01');
            $fimMes = date('Y-m-t');
            $estatisticas = $profissionalModel->getEstatisticas($profissionalId, $inicioMes, $fimMes);
            
            $dadosFormatados = $profissionalModel->formatarParaExibicao($profissional);
            $dadosFormatados['atendimento_atual'] = $atendimentoAtual;
            $dadosFormatados['historico_dia'] = $historicoDia;
            $dadosFormatados['estatisticas_mes'] = $estatisticas;
            
            Response::success($dadosFormatados);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter dados do profissional: " . $e->getMessage());
            Response::serverError('Erro ao carregar dados');
        }
    }
    
    /**
     * Listar profissionais (CRUD - apenas gestor/admin)
     */
    public static function listar(): void
    {
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            $profissionalModel = new Profissional();
            $profissionais = $profissionalModel->findAll([], ['order_by' => 'nome']);
            
            $profissionaisFormatados = array_map(function($prof) use ($profissionalModel) {
                $formatado = $profissionalModel->formatarParaExibicao($prof);
                
                // Adicionar estatísticas básicas
                $estatisticas = $profissionalModel->getEstatisticas($prof['id'], date('Y-m-01'), date('Y-m-t'));
                $formatado['estatisticas_mes'] = $estatisticas;
                
                return $formatado;
            }, $profissionais);
            
            Response::success($profissionaisFormatados);
            
        } catch (\Exception $e) {
            error_log("Erro ao listar profissionais: " . $e->getMessage());
            Response::serverError('Erro ao carregar profissionais');
        }
    }
    
    /**
     * Criar profissional (apenas gestor/admin)
     */
    public static function criar(): void
    {
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        // Validar CSRF
        if (!CSRF::validatePost()) {
            Response::error('Token CSRF inválido', null, 403);
        }
        
        try {
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'nome' => 'name',
                'senha' => 'input',
                'nfc_uid' => 'nfcUid'
            ]);
            
            // Validar dados
            $validator = new Validator($data);
            $validator
                ->required('nome', 'Nome é obrigatório')
                ->min('nome', 2, 'Nome deve ter pelo menos 2 caracteres')
                ->required('senha', 'Senha é obrigatória')
                ->min('senha', 6, 'Senha deve ter pelo menos 6 caracteres');
            
            if (!empty($data['nfc_uid'])) {
                $validator->min('nfc_uid', 4, 'UID NFC deve ter pelo menos 4 caracteres');
            }
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Hash da senha
            $data['senha'] = password_hash($data['senha'], PASSWORD_BCRYPT);
            $data['status'] = Profissional::STATUS_AUSENTE;
            $data['ativo'] = true;
            
            $profissionalModel = new Profissional();
            $profissionalId = $profissionalModel->create($data);
            
            // Log da ação
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Profissional criado - Nome: {$data['nome']}"
            );
            
            $profissionalCriado = $profissionalModel->find($profissionalId);
            
            Response::success(
                $profissionalModel->formatarParaExibicao($profissionalCriado),
                'Profissional criado com sucesso'
            );
            
        } catch (\Exception $e) {
            error_log("Erro ao criar profissional: " . $e->getMessage());
            Response::serverError('Erro ao criar profissional');
        }
    }
    
    /**
     * Atualizar profissional (apenas gestor/admin)
     */
    public static function atualizar(): void
    {
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        // Validar CSRF
        if (!CSRF::validatePost()) {
            Response::error('Token CSRF inválido', null, 403);
        }
        
        try {
            $profissionalId = Sanitizer::uuid($_POST['id'] ?? '');
            
            if (empty($profissionalId)) {
                Response::error('ID do profissional é obrigatório');
            }
            
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'nome' => 'name',
                'nfc_uid' => 'nfcUid',
                'ativo' => 'int'
            ]);
            
            // Se senha foi fornecida, hash
            if (!empty($_POST['senha'])) {
                $data['senha'] = password_hash($_POST['senha'], PASSWORD_BCRYPT);
            }
            
            // Remover campos vazios
            $data = array_filter($data, function($value) {
                return $value !== '' && $value !== null;
            });
            
            if (empty($data)) {
                Response::error('Nenhum dado para atualizar');
            }
            
            $profissionalModel = new Profissional();
            $sucesso = $profissionalModel->update($profissionalId, $data);
            
            if (!$sucesso) {
                Response::error('Profissional não encontrado');
            }
            
            // Log da ação
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Profissional atualizado - ID: $profissionalId"
            );
            
            $profissionalAtualizado = $profissionalModel->find($profissionalId);
            
            Response::success(
                $profissionalModel->formatarParaExibicao($profissionalAtualizado),
                'Profissional atualizado com sucesso'
            );
            
        } catch (\Exception $e) {
            error_log("Erro ao atualizar profissional: " . $e->getMessage());
            Response::serverError('Erro ao atualizar profissional');
        }
    }
    
    /**
     * Obter ranking de profissionais
     */
    public static function ranking(): void
    {
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            $dataInicio = Sanitizer::date($_GET['data_inicio'] ?? date('Y-m-01'));
            $dataFim = Sanitizer::date($_GET['data_fim'] ?? date('Y-m-t'));
            
            $profissionalModel = new Profissional();
            $ranking = $profissionalModel->getRanking($dataInicio, $dataFim);
            
            // Formatar ranking
            $rankingFormatado = array_map(function($item, $posicao) {
                return [
                    'posicao' => $posicao + 1,
                    'id' => $item['id'],
                    'nome' => $item['nome'],
                    'total_atendimentos' => intval($item['total_atendimentos']),
                    'finalizados' => intval($item['finalizados']),
                    'tempo_medio' => round($item['tempo_medio'] ?? 0, 1),
                    'total_faturado' => floatval($item['total_faturado'] ?? 0),
                    'taxa_finalizacao' => $item['total_atendimentos'] > 0 ? 
                        round(($item['finalizados'] / $item['total_atendimentos']) * 100, 1) : 0
                ];
            }, $ranking, array_keys($ranking));
            
            Response::success([
                'ranking' => $rankingFormatado,
                'periodo' => [
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim
                ]
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter ranking: " . $e->getMessage());
            Response::serverError('Erro ao carregar ranking');
        }
    }
    
    /**
     * Reset diário (zerar contadores)
     */
    public static function resetDiario(): void
    {
        if (!Auth::isAdmin()) {
            Response::unauthorized('Apenas administradores podem fazer reset diário');
        }
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            $profissionalModel = new Profissional();
            $afetados = $profissionalModel->resetDiario();
            
            // Log da ação
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Reset diário executado - $afetados profissionais afetados"
            );
            
            Response::success([
                'profissionais_afetados' => $afetados
            ], 'Reset diário executado com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro no reset diário: " . $e->getMessage());
            Response::serverError('Erro ao executar reset diário');
        }
    }
}