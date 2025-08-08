<?php
/**
 * Controller de Serviços
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Controllers;

use Utils\Response;
use Utils\Auth;
use Utils\Validator;
use Utils\Sanitizer;
use Utils\CSRF;
use Models\Servico;
use Controllers\LogController;

class ServicoController
{
    /**
     * Listar todos os serviços
     */
    public static function listar(): void
    {
        try {
            $apenasAtivos = isset($_GET['ativos']) && $_GET['ativos'] === '1';
            
            $servicoModel = new Servico();
            
            if ($apenasAtivos) {
                $servicos = $servicoModel->listarAtivos();
            } else {
                $servicos = $servicoModel->findAll([], ['order_by' => 'nome']);
            }
            
            // Adicionar estatísticas se solicitado
            $incluirEstatisticas = isset($_GET['estatisticas']) && $_GET['estatisticas'] === '1';
            
            if ($incluirEstatisticas) {
                $servicos = array_map(function($servico) use ($servicoModel) {
                    $servico['estatisticas'] = $servicoModel->getEstatisticas($servico['id']);
                    return $servico;
                }, $servicos);
            }
            
            Response::success($servicos);
            
        } catch (\Exception $e) {
            error_log("Erro ao listar serviços: " . $e->getMessage());
            Response::serverError('Erro ao carregar serviços');
        }
    }
    
    /**
     * Obter detalhes de um serviço
     */
    public static function detalhes(): void
    {
        try {
            $servicoId = Sanitizer::uuid($_GET['id'] ?? '');
            
            if (empty($servicoId)) {
                Response::error('ID do serviço é obrigatório');
            }
            
            $servicoModel = new Servico();
            $servico = $servicoModel->find($servicoId);
            
            if (!$servico) {
                Response::notFound('Serviço não encontrado');
            }
            
            // Obter estatísticas do serviço
            $estatisticas = $servicoModel->getEstatisticas($servicoId);
            
            Response::success([
                'servico' => $servico,
                'estatisticas' => $estatisticas
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter detalhes do serviço: " . $e->getMessage());
            Response::serverError('Erro ao carregar dados do serviço');
        }
    }
    
    /**
     * Criar novo serviço
     */
    public static function criar(): void
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
                'nome' => 'name',
                'preco' => 'money',
                'tempo_estimado' => 'time'
            ]);
            
            // Validar dados
            $validator = new Validator($data);
            $validator
                ->required('nome', 'Nome é obrigatório')
                ->min('nome', 2, 'Nome deve ter pelo menos 2 caracteres')
                ->max('nome', 255, 'Nome deve ter no máximo 255 caracteres')
                ->required('preco', 'Preço é obrigatório')
                ->numeric('preco', 'Preço deve ser um número')
                ->minValue('preco', 0.01, 'Preço deve ser maior que zero')
                ->required('tempo_estimado', 'Tempo estimado é obrigatório');
            
            // Validar formato do tempo
            if (!empty($data['tempo_estimado'])) {
                if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $data['tempo_estimado'])) {
                    $validator->addError('tempo_estimado', 'Formato inválido. Use HH:MM ou HH:MM:SS');
                } else {
                    // Garantir formato HH:MM:SS
                    if (substr_count($data['tempo_estimado'], ':') === 1) {
                        $data['tempo_estimado'] .= ':00';
                    }
                }
            }
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Definir como ativo por padrão
            $data['ativo'] = true;
            
            $servicoModel = new Servico();
            $servicoId = $servicoModel->create($data);
            
            // Log da ação
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Serviço criado - Nome: {$data['nome']} | Preço: R$ {$data['preco']}"
            );
            
            $servicoCriado = $servicoModel->find($servicoId);
            
            Response::success($servicoCriado, 'Serviço criado com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao criar serviço: " . $e->getMessage());
            Response::serverError('Erro ao criar serviço');
        }
    }
    
    /**
     * Atualizar serviço
     */
    public static function atualizar(): void
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
            $servicoId = Sanitizer::uuid($_POST['id'] ?? '');
            
            if (empty($servicoId)) {
                Response::error('ID do serviço é obrigatório');
            }
            
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'nome' => 'name',
                'preco' => 'money',
                'tempo_estimado' => 'time',
                'ativo' => 'int'
            ]);
            
            // Remover campos vazios
            $data = array_filter($data, function($value) {
                return $value !== '' && $value !== null;
            });
            
            if (empty($data)) {
                Response::error('Nenhum dado para atualizar');
            }
            
            // Validar formato do tempo se fornecido
            if (isset($data['tempo_estimado'])) {
                if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $data['tempo_estimado'])) {
                    Response::error('Formato de tempo inválido. Use HH:MM ou HH:MM:SS');
                } else {
                    // Garantir formato HH:MM:SS
                    if (substr_count($data['tempo_estimado'], ':') === 1) {
                        $data['tempo_estimado'] .= ':00';
                    }
                }
            }
            
            // Converter ativo para boolean se fornecido
            if (isset($data['ativo'])) {
                $data['ativo'] = intval($data['ativo']) === 1;
            }
            
            $servicoModel = new Servico();
            $sucesso = $servicoModel->update($servicoId, $data);
            
            if (!$sucesso) {
                Response::error('Serviço não encontrado');
            }
            
            // Log da ação
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Serviço atualizado - ID: $servicoId"
            );
            
            $servicoAtualizado = $servicoModel->find($servicoId);
            
            Response::success($servicoAtualizado, 'Serviço atualizado com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao atualizar serviço: " . $e->getMessage());
            Response::serverError('Erro ao atualizar serviço');
        }
    }
    
    /**
     * Alternar status do serviço (ativo/inativo)
     */
    public static function toggleStatus(): void
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
            $servicoId = Sanitizer::uuid($_POST['id'] ?? '');
            
            if (empty($servicoId)) {
                Response::error('ID do serviço é obrigatório');
            }
            
            $servicoModel = new Servico();
            $servico = $servicoModel->find($servicoId);
            
            if (!$servico) {
                Response::error('Serviço não encontrado');
            }
            
            $novoStatus = !$servico['ativo'];
            $sucesso = $servicoModel->update($servicoId, ['ativo' => $novoStatus]);
            
            if (!$sucesso) {
                Response::error('Erro ao alterar status');
            }
            
            // Log da ação
            $statusTexto = $novoStatus ? 'ativado' : 'desativado';
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Serviço $statusTexto - {$servico['nome']}"
            );
            
            $servicoAtualizado = $servicoModel->find($servicoId);
            
            Response::success($servicoAtualizado, "Serviço $statusTexto com sucesso");
            
        } catch (\Exception $e) {
            error_log("Erro ao alterar status do serviço: " . $e->getMessage());
            Response::serverError('Erro ao alterar status');
        }
    }
    
    /**
     * Excluir serviço
     */
    public static function excluir(): void
    {
        // Verificar permissão (apenas administrador)
        if (!Auth::isAdmin()) {
            Response::unauthorized('Apenas administradores podem excluir serviços');
        }
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        // Validar CSRF
        if (!CSRF::validatePost()) {
            Response::error('Token CSRF inválido', null, 403);
        }
        
        try {
            $servicoId = Sanitizer::uuid($_POST['id'] ?? '');
            
            if (empty($servicoId)) {
                Response::error('ID do serviço é obrigatório');
            }
            
            $servicoModel = new Servico();
            $servico = $servicoModel->find($servicoId);
            
            if (!$servico) {
                Response::error('Serviço não encontrado');
            }
            
            // Verificar se há atendimentos vinculados
            $sql = "SELECT COUNT(*) as total FROM atendimentos WHERE id_servico = :id";
            $result = $servicoModel->queryOne($sql, ['id' => $servicoId]);
            
            if ($result && intval($result['total']) > 0) {
                Response::error('Não é possível excluir serviço com atendimentos vinculados. Desative o serviço em vez de excluir.');
            }
            
            $sucesso = $servicoModel->delete($servicoId);
            
            if (!$sucesso) {
                Response::error('Erro ao excluir serviço');
            }
            
            // Log da ação
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Serviço excluído - {$servico['nome']}"
            );
            
            Response::success(null, 'Serviço excluído com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao excluir serviço: " . $e->getMessage());
            Response::serverError('Erro ao excluir serviço');
        }
    }
    
    /**
     * Obter serviços mais utilizados
     */
    public static function maisUtilizados(): void
    {
        try {
            $limite = max(1, min(Sanitizer::int($_GET['limite'] ?? 5), 20));
            
            $servicoModel = new Servico();
            $servicos = $servicoModel->getMaisUtilizados($limite);
            
            Response::success($servicos);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter serviços mais utilizados: " . $e->getMessage());
            Response::serverError('Erro ao carregar serviços mais utilizados');
        }
    }
    
    /**
     * Obter estatísticas gerais dos serviços
     */
    public static function estatisticas(): void
    {
        // Verificar permissão (gestor ou superior)
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            $servicoModel = new Servico();
            
            // Total de serviços
            $totalServicos = $servicoModel->count();
            $servicosAtivos = $servicoModel->count(['ativo' => 1]);
            
            // Serviços mais utilizados
            $maisUtilizados = $servicoModel->getMaisUtilizados(5);
            
            // Faturamento por serviço no mês
            $inicioMes = date('Y-m-01');
            $fimMes = date('Y-m-t');
            
            $sql = "SELECT s.nome, s.preco, COUNT(a.id) as total_atendimentos,
                           SUM(COALESCE(a.valor_cobrado, s.preco)) as faturamento_total
                    FROM servicos s
                    LEFT JOIN atendimentos a ON s.id = a.id_servico 
                        AND a.status = 'finalizado'
                        AND DATE(a.criado_em) BETWEEN :inicio AND :fim
                    WHERE s.ativo = 1
                    GROUP BY s.id, s.nome, s.preco
                    ORDER BY faturamento_total DESC
                    LIMIT 10";
            
            $faturamentoPorServico = $servicoModel->query($sql, [
                'inicio' => $inicioMes,
                'fim' => $fimMes
            ]);
            
            Response::success([
                'total_servicos' => $totalServicos,
                'servicos_ativos' => $servicosAtivos,
                'servicos_inativos' => $totalServicos - $servicosAtivos,
                'mais_utilizados' => $maisUtilizados,
                'faturamento_mensal' => array_map(function($item) {
                    return [
                        'nome' => $item['nome'],
                        'preco_base' => floatval($item['preco']),
                        'total_atendimentos' => intval($item['total_atendimentos']),
                        'faturamento_total' => floatval($item['faturamento_total'])
                    ];
                }, $faturamentoPorServico)
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter estatísticas dos serviços: " . $e->getMessage());
            Response::serverError('Erro ao carregar estatísticas');
        }
    }
}