<?php
/**
 * Controller de Atendimentos
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Controllers;

use Utils\Response;
use Utils\Auth;
use Utils\Validator;
use Utils\Sanitizer;
use Utils\CSRF;
use Utils\Date;
use Models\Atendimento;
use Models\Profissional;
use Models\Cliente;
use Models\Servico;
use Controllers\LogController;

class AtendimentoController
{
    /**
     * Distribuir cliente automaticamente (algoritmo FIFO + rodízio)
     */
    public static function distribuirAuto(): void
    {
        // Verificar permissão (recepção ou superior)
        if (!Auth::isRecepcao()) {
            Response::unauthorized('Acesso negado');
        }
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            $atendimentoModel = new Atendimento();
            $resultado = $atendimentoModel->distribuirClienteAutomatico();
            
            if (!$resultado) {
                Response::error('Nenhum cliente na fila ou profissional disponível');
            }
            
            // Log da distribuição automática
            LogController::logAtendimento(
                'distribuição automática',
                $resultado['atendimento_id'],
                $resultado['cliente_id'],
                $resultado['profissional_id']
            );
            
            Response::success($resultado, 'Cliente distribuído automaticamente');
            
        } catch (\Exception $e) {
            error_log("Erro na distribuição automática: " . $e->getMessage());
            
            if (str_contains($e->getMessage(), 'Nenhuma profissional disponível')) {
                Response::error('Nenhuma profissional disponível no momento');
            }
            
            Response::serverError('Erro na distribuição automática');
        }
    }
    
    /**
     * Forçar distribuição manual
     */
    public static function forcar(): void
    {
        // Verificar permissão (gestor ou superior)
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
                'atendimento_id' => 'uuid',
                'profissional_id' => 'uuid'
            ]);
            
            // Validar dados
            $validator = new Validator($data);
            $validator
                ->required('atendimento_id', 'ID do atendimento é obrigatório')
                ->uuid('atendimento_id', 'ID do atendimento inválido')
                ->required('profissional_id', 'ID do profissional é obrigatório')
                ->uuid('profissional_id', 'ID do profissional inválido');
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            $atendimentoModel = new Atendimento();
            $sucesso = $atendimentoModel->forcarDistribuicao(
                $data['atendimento_id'],
                $data['profissional_id']
            );
            
            if (!$sucesso) {
                Response::error('Não foi possível forçar a distribuição');
            }
            
            // Log da distribuição manual
            LogController::logAtendimento(
                'distribuição manual',
                $data['atendimento_id'],
                null,
                $data['profissional_id']
            );
            
            Response::success(null, 'Distribuição manual realizada com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro na distribuição manual: " . $e->getMessage());
            
            if (str_contains($e->getMessage(), 'não está aguardando')) {
                Response::error('Atendimento não está aguardando');
            } elseif (str_contains($e->getMessage(), 'não está disponível')) {
                Response::error('Profissional não está disponível');
            }
            
            Response::serverError('Erro na distribuição manual');
        }
    }
    
    /**
     * Finalizar atendimento
     */
    public static function finalizar(): void
    {
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            $atendimentoId = Sanitizer::uuid($_POST['atendimento_id'] ?? '');
            
            if (empty($atendimentoId)) {
                Response::error('ID do atendimento é obrigatório');
            }
            
            // Verificar permissões
            $atendimentoModel = new Atendimento();
            $atendimento = $atendimentoModel->find($atendimentoId);
            
            if (!$atendimento) {
                Response::error('Atendimento não encontrado');
            }
            
            // Profissional só pode finalizar seu próprio atendimento
            if (Auth::isProfissional() && !Auth::isGestor()) {
                $profissionalId = Auth::getUserId();
                
                if ($atendimento['id_profissional'] !== $profissionalId) {
                    Response::unauthorized('Você só pode finalizar seus próprios atendimentos');
                }
            } elseif (!Auth::isRecepcao()) {
                Response::unauthorized('Acesso negado');
            }
            
            // Sanitizar dados opcionais
            $dados = [];
            
            if (isset($_POST['valor_cobrado']) && !empty($_POST['valor_cobrado'])) {
                $dados['valor_cobrado'] = Sanitizer::money($_POST['valor_cobrado']);
            }
            
            if (isset($_POST['observacoes']) && !empty($_POST['observacoes'])) {
                $dados['observacoes'] = Sanitizer::text($_POST['observacoes'], 500);
            }
            
            // Finalizar atendimento
            $sucesso = $atendimentoModel->finalizarAtendimento($atendimentoId, $dados);
            
            if (!$sucesso) {
                Response::error('Não foi possível finalizar o atendimento');
            }
            
            // Log da finalização
            LogController::logAtendimento(
                'finalizado',
                $atendimentoId,
                $atendimento['id_cliente'],
                $atendimento['id_profissional']
            );
            
            Response::success(null, 'Atendimento finalizado com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao finalizar atendimento: " . $e->getMessage());
            
            if (str_contains($e->getMessage(), 'não está em andamento')) {
                Response::error('Atendimento não está em andamento');
            }
            
            Response::serverError('Erro ao finalizar atendimento');
        }
    }
    
    /**
     * Cancelar atendimento
     */
    public static function cancelar(): void
    {
        // Verificar permissão (gestor ou superior)
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
            $atendimentoId = Sanitizer::uuid($_POST['atendimento_id'] ?? '');
            $motivo = Sanitizer::text($_POST['motivo'] ?? '', 200);
            
            if (empty($atendimentoId)) {
                Response::error('ID do atendimento é obrigatório');
            }
            
            $atendimentoModel = new Atendimento();
            $atendimento = $atendimentoModel->find($atendimentoId);
            
            if (!$atendimento) {
                Response::error('Atendimento não encontrado');
            }
            
            $sucesso = $atendimentoModel->cancelarAtendimento($atendimentoId, $motivo);
            
            if (!$sucesso) {
                Response::error('Não foi possível cancelar o atendimento');
            }
            
            // Log do cancelamento
            LogController::logAtendimento(
                'cancelado',
                $atendimentoId,
                $atendimento['id_cliente'],
                $atendimento['id_profissional']
            );
            
            Response::success(null, 'Atendimento cancelado com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao cancelar atendimento: " . $e->getMessage());
            
            if (str_contains($e->getMessage(), 'atendimento finalizado')) {
                Response::error('Não é possível cancelar atendimento finalizado');
            }
            
            Response::serverError('Erro ao cancelar atendimento');
        }
    }
    
    /**
     * Obter atendimentos em andamento
     */
    public static function emAndamento(): void
    {
        try {
            $atendimentoModel = new Atendimento();
            $atendimentos = $atendimentoModel->getAtendimentosEmAndamento();
            
            // Formatar dados
            $atendimentosFormatados = array_map(function($atendimento) {
                return [
                    'id' => $atendimento['id'],
                    'cliente_nome' => $atendimento['cliente_nome'],
                    'cliente_telefone' => $atendimento['cliente_telefone'],
                    'profissional_nome' => $atendimento['profissional_nome'],
                    'servico_nome' => $atendimento['servico_nome'],
                    'hora_inicio' => $atendimento['hora_inicio'],
                    'duracao_minutos' => intval($atendimento['duracao_minutos']),
                    'duracao_formatada' => self::formatarTempo($atendimento['duracao_minutos'])
                ];
            }, $atendimentos);
            
            Response::success($atendimentosFormatados);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter atendimentos em andamento: " . $e->getMessage());
            Response::serverError('Erro ao carregar atendimentos em andamento');
        }
    }
    
    /**
     * Obter relatório de atendimentos
     */
    public static function relatorio(): void
    {
        // Verificar permissão (gestor ou superior)
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            // Sanitizar parâmetros
            $dataInicio = Sanitizer::date($_GET['data_inicio'] ?? date('Y-m-d'));
            $dataFim = Sanitizer::date($_GET['data_fim'] ?? date('Y-m-d'));
            
            $atendimentoModel = new Atendimento();
            $atendimentos = $atendimentoModel->getRelatorioAtendimentos($dataInicio, $dataFim);
            
            // Formatar dados
            $atendimentosFormatados = array_map(function($atendimento) {
                return [
                    'id' => $atendimento['id'],
                    'cliente_nome' => $atendimento['cliente_nome'],
                    'profissional_nome' => $atendimento['profissional_nome'],
                    'servico_nome' => $atendimento['servico_nome'],
                    'servico_preco' => floatval($atendimento['servico_preco']),
                    'status' => $atendimento['status'],
                    'criado_em' => Date::toBrazilianDateTime($atendimento['criado_em']),
                    'hora_inicio' => $atendimento['hora_inicio'] ? 
                        Date::toBrazilianDateTime($atendimento['hora_inicio']) : null,
                    'hora_fim' => $atendimento['hora_fim'] ? 
                        Date::toBrazilianDateTime($atendimento['hora_fim']) : null,
                    'tempo_espera' => $atendimento['tempo_espera'] ? 
                        intval($atendimento['tempo_espera']) : null,
                    'tempo_espera_formatado' => $atendimento['tempo_espera'] ? 
                        self::formatarTempo($atendimento['tempo_espera']) : null,
                    'duracao_atendimento' => $atendimento['duracao_atendimento'] ? 
                        intval($atendimento['duracao_atendimento']) : null,
                    'duracao_atendimento_formatada' => $atendimento['duracao_atendimento'] ? 
                        self::formatarTempo($atendimento['duracao_atendimento']) : null,
                    'valor_cobrado' => $atendimento['valor_cobrado'] ? 
                        floatval($atendimento['valor_cobrado']) : null
                ];
            }, $atendimentos);
            
            Response::success([
                'atendimentos' => $atendimentosFormatados,
                'periodo' => [
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim
                ],
                'total' => count($atendimentosFormatados)
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao gerar relatório: " . $e->getMessage());
            Response::serverError('Erro ao gerar relatório');
        }
    }
    
    /**
     * Obter métricas do dia
     */
    public static function metricasDia(): void
    {
        try {
            $data = Sanitizer::date($_GET['data'] ?? date('Y-m-d'));
            
            $atendimentoModel = new Atendimento();
            $metricas = $atendimentoModel->getMetricasDoDia($data);
            
            // Formatar contadores por status
            $contadores = [
                'aguardando' => 0,
                'em_andamento' => 0,
                'finalizado' => 0,
                'cancelado' => 0
            ];
            
            foreach ($metricas['contadores'] as $contador) {
                $contadores[$contador['status']] = intval($contador['total']);
            }
            
            $total = array_sum($contadores);
            
            Response::success([
                'data' => $data,
                'data_formatada' => Date::toBrazilian($data),
                'contadores' => $contadores,
                'total_atendimentos' => $total,
                'tempo_medio_espera' => $metricas['tempo_medio_espera'],
                'tempo_medio_espera_formatado' => self::formatarTempo($metricas['tempo_medio_espera']),
                'tempo_medio_atendimento' => $metricas['tempo_medio_atendimento'],
                'tempo_medio_atendimento_formatado' => self::formatarTempo($metricas['tempo_medio_atendimento']),
                'faturamento_total' => $metricas['faturamento_total']
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter métricas do dia: " . $e->getMessage());
            Response::serverError('Erro ao carregar métricas');
        }
    }
    
    /**
     * Obter dados para gráfico de atendimentos por hora
     */
    public static function atendimentosPorHora(): void
    {
        try {
            $data = Sanitizer::date($_GET['data'] ?? date('Y-m-d'));
            
            $atendimentoModel = new Atendimento();
            $dadosHora = $atendimentoModel->getAtendimentosPorHora($data);
            
            // Preencher todas as horas (0-23) com zero se não houver dados
            $horasCompletas = [];
            for ($hora = 0; $hora < 24; $hora++) {
                $horasCompletas[$hora] = 0;
            }
            
            foreach ($dadosHora as $item) {
                $horasCompletas[intval($item['hora'])] = intval($item['total']);
            }
            
            // Converter para formato de gráfico
            $dadosGrafico = [];
            foreach ($horasCompletas as $hora => $total) {
                $dadosGrafico[] = [
                    'hora' => sprintf('%02d:00', $hora),
                    'total' => $total
                ];
            }
            
            Response::success([
                'data' => $data,
                'dados' => $dadosGrafico
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter atendimentos por hora: " . $e->getMessage());
            Response::serverError('Erro ao carregar dados do gráfico');
        }
    }
    
    /**
     * Pausar atendimento (se implementado futuramente)
     */
    public static function pausar(): void
    {
        // Verificar permissão (gestor ou superior)
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        Response::error('Funcionalidade de pausa não implementada. Use cancelar e reagendar.');
    }
    
    /**
     * Retomar atendimento (se implementado futuramente)
     */
    public static function retomar(): void
    {
        // Verificar permissão (gestor ou superior)
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        Response::error('Funcionalidade de retomada não implementada. Use reagendar.');
    }
    
    /**
     * Formatar tempo em minutos para exibição
     */
    private static function formatarTempo($minutos): string
    {
        if (!$minutos || $minutos <= 0) {
            return '0 min';
        }
        
        $minutos = intval($minutos);
        
        if ($minutos < 60) {
            return $minutos . ' min';
        }
        
        $horas = intval($minutos / 60);
        $minutosRestantes = $minutos % 60;
        
        if ($minutosRestantes === 0) {
            return $horas . 'h';
        }
        
        return $horas . 'h ' . $minutosRestantes . 'min';
    }
}