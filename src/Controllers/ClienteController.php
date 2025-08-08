<?php
/**
 * Controller de Clientes
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Controllers;

use Utils\Response;
use Utils\Auth;
use Utils\Validator;
use Utils\Sanitizer;
use Utils\CSRF;
use Models\Cliente;
use Models\Servico;
use Models\Atendimento;
use Controllers\LogController;

class ClienteController
{
    /**
     * Cadastrar cliente na fila
     */
    public static function cadastrar(): void
    {
        // Verificar permissão (recepção ou superior)
        if (!Auth::isRecepcao()) {
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
                'telefone' => 'phone',
                'email' => 'email',
                'id_servico' => 'uuid',
                'observacoes' => 'text'
            ]);
            
            // Validar dados
            $validator = new Validator($data);
            $validator
                ->required('nome', 'Nome é obrigatório')
                ->min('nome', 2, 'Nome deve ter pelo menos 2 caracteres')
                ->required('id_servico', 'Serviço é obrigatório')
                ->uuid('id_servico', 'Serviço inválido')
                ->exists('id_servico', 'servicos', 'id', 'Serviço não encontrado');
            
            if (!empty($data['telefone'])) {
                $validator->phone('telefone', 'Telefone deve ser válido');
            }
            
            if (!empty($data['email'])) {
                $validator->email('email', 'Email deve ser válido');
            }
            
            if (!$validator->isValid()) {
                Response::validationError($validator->getErrors());
            }
            
            // Verificar se serviço está ativo
            $servicoModel = new Servico();
            $servico = $servicoModel->find($data['id_servico']);
            
            if (!$servico || !$servico['ativo']) {
                Response::error('Serviço não disponível');
            }
            
            // Cadastrar cliente e criar atendimento
            $clienteModel = new Cliente();
            $resultado = $clienteModel->cadastrarComAtendimento([
                'nome' => $data['nome'],
                'telefone' => $data['telefone'],
                'email' => $data['email'],
                'observacoes' => $data['observacoes']
            ], $data['id_servico']);
            
            // Log da ação
            LogController::registrar(
                'atendimento',
                Auth::getUserId(),
                "Cliente cadastrado na fila - Cliente: {$resultado['cliente']['nome']} | Serviço: {$servico['nome']}"
            );
            
            Response::success([
                'cliente' => $resultado['cliente'],
                'atendimento_id' => $resultado['atendimento_id'],
                'servico' => $servico
            ], 'Cliente cadastrado na fila com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao cadastrar cliente: " . $e->getMessage());
            
            if ($e->getMessage() === 'Cliente já possui atendimento agendado para hoje') {
                Response::error('Este cliente já possui atendimento agendado para hoje');
            }
            
            Response::serverError('Erro ao cadastrar cliente');
        }
    }
    
    /**
     * Obter fila de espera
     */
    public static function fila(): void
    {
        try {
            $atendimentoModel = new Atendimento();
            $fila = $atendimentoModel->getFilaEspera();
            
            // Formatar dados da fila
            $filaFormatada = array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'cliente_nome' => $item['cliente_nome'],
                    'cliente_telefone' => $item['cliente_telefone'],
                    'servico_nome' => $item['servico_nome'],
                    'servico_preco' => floatval($item['servico_preco']),
                    'tempo_estimado' => $item['tempo_estimado'],
                    'tempo_espera' => intval($item['tempo_espera']),
                    'tempo_espera_formatado' => self::formatarTempo($item['tempo_espera']),
                    'criado_em' => $item['criado_em']
                ];
            }, $fila);
            
            // Obter próximo da fila
            $proximo = !empty($filaFormatada) ? $filaFormatada[0] : null;
            
            Response::success([
                'fila' => $filaFormatada,
                'total_fila' => count($filaFormatada),
                'proximo' => $proximo
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter fila: " . $e->getMessage());
            Response::serverError('Erro ao carregar fila');
        }
    }
    
    /**
     * Buscar clientes
     */
    public static function buscar(): void
    {
        try {
            $termo = Sanitizer::input($_GET['termo'] ?? '', 50);
            
            if (strlen($termo) < 2) {
                Response::error('Termo de busca deve ter pelo menos 2 caracteres');
            }
            
            $clienteModel = new Cliente();
            $clientes = $clienteModel->search($termo, 20);
            
            // Formatar clientes
            $clientesFormatados = array_map(function($cliente) use ($clienteModel) {
                return $clienteModel->formatarParaExibicao($cliente);
            }, $clientes);
            
            Response::success($clientesFormatados);
            
        } catch (\Exception $e) {
            error_log("Erro ao buscar clientes: " . $e->getMessage());
            Response::serverError('Erro na busca');
        }
    }
    
    /**
     * Obter detalhes do cliente
     */
    public static function detalhes(): void
    {
        try {
            $clienteId = Sanitizer::uuid($_GET['id'] ?? '');
            
            if (empty($clienteId)) {
                Response::error('ID do cliente é obrigatório');
            }
            
            $clienteModel = new Cliente();
            $cliente = $clienteModel->find($clienteId);
            
            if (!$cliente) {
                Response::notFound('Cliente não encontrado');
            }
            
            // Obter histórico e estatísticas
            $historico = $clienteModel->getHistoricoAtendimentos($clienteId, 10);
            $estatisticas = $clienteModel->getEstatisticas($clienteId);
            
            Response::success([
                'cliente' => $clienteModel->formatarParaExibicao($cliente),
                'historico' => $historico,
                'estatisticas' => $estatisticas
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter detalhes do cliente: " . $e->getMessage());
            Response::serverError('Erro ao carregar dados do cliente');
        }
    }
    
    /**
     * Atualizar cliente
     */
    public static function atualizar(): void
    {
        // Verificar permissão (recepção ou superior)
        if (!Auth::isRecepcao()) {
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
            $clienteId = Sanitizer::uuid($_POST['id'] ?? '');
            
            if (empty($clienteId)) {
                Response::error('ID do cliente é obrigatório');
            }
            
            // Sanitizar dados
            $data = Sanitizer::array($_POST, [
                'nome' => 'name',
                'telefone' => 'phone',
                'email' => 'email',
                'data_nascimento' => 'date',
                'observacoes' => 'text'
            ]);
            
            // Remover campos vazios
            $data = array_filter($data, function($value) {
                return $value !== '' && $value !== null;
            });
            
            if (empty($data)) {
                Response::error('Nenhum dado para atualizar');
            }
            
            $clienteModel = new Cliente();
            $sucesso = $clienteModel->update($clienteId, $data);
            
            if (!$sucesso) {
                Response::error('Cliente não encontrado');
            }
            
            // Log da ação
            LogController::registrar(
                'sistema',
                Auth::getUserId(),
                "Cliente atualizado - ID: $clienteId"
            );
            
            // Buscar dados atualizados
            $clienteAtualizado = $clienteModel->find($clienteId);
            
            Response::success(
                $clienteModel->formatarParaExibicao($clienteAtualizado),
                'Cliente atualizado com sucesso'
            );
            
        } catch (\Exception $e) {
            error_log("Erro ao atualizar cliente: " . $e->getMessage());
            Response::serverError('Erro ao atualizar cliente');
        }
    }
    
    /**
     * Listar clientes com paginação
     */
    public static function listar(): void
    {
        try {
            $pagina = max(1, Sanitizer::int($_GET['pagina'] ?? 1));
            $limite = max(1, min(Sanitizer::int($_GET['limite'] ?? 20), 100));
            $busca = Sanitizer::input($_GET['busca'] ?? '', 50);
            
            $clienteModel = new Cliente();
            $conditions = [];
            
            if (!empty($busca)) {
                // Busca será implementada com query customizada
                $sql = "SELECT * FROM clientes 
                        WHERE nome LIKE :busca 
                        OR telefone LIKE :busca 
                        OR email LIKE :busca
                        ORDER BY nome";
                
                $params = ['busca' => "%$busca%"];
                $clientes = $clienteModel->query($sql, $params);
                
                $resultado = [
                    'data' => array_map([$clienteModel, 'formatarParaExibicao'], $clientes),
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => count($clientes),
                        'total' => count($clientes),
                        'total_pages' => 1,
                        'has_next' => false,
                        'has_prev' => false
                    ]
                ];
            } else {
                $resultado = $clienteModel->paginate($conditions, $pagina, $limite);
                $resultado['data'] = array_map([$clienteModel, 'formatarParaExibicao'], $resultado['data']);
            }
            
            Response::success($resultado);
            
        } catch (\Exception $e) {
            error_log("Erro ao listar clientes: " . $e->getMessage());
            Response::serverError('Erro ao carregar clientes');
        }
    }
    
    /**
     * Obter clientes frequentes
     */
    public static function frequentes(): void
    {
        try {
            $limite = max(1, min(Sanitizer::int($_GET['limite'] ?? 10), 50));
            
            $clienteModel = new Cliente();
            $frequentes = $clienteModel->getClientesFrequentes($limite);
            
            Response::success($frequentes);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter clientes frequentes: " . $e->getMessage());
            Response::serverError('Erro ao carregar clientes frequentes');
        }
    }
    
    /**
     * Obter aniversariantes do mês
     */
    public static function aniversariantes(): void
    {
        try {
            $mes = Sanitizer::int($_GET['mes'] ?? date('m'));
            $mes = max(1, min($mes, 12));
            
            $clienteModel = new Cliente();
            $aniversariantes = $clienteModel->getAniversariantesDoMes($mes);
            
            // Formatar dados
            $aniversariantesFormatados = array_map(function($cliente) use ($clienteModel) {
                $formatado = $clienteModel->formatarParaExibicao($cliente);
                $formatado['dia_aniversario'] = intval($cliente['dia_aniversario']);
                return $formatado;
            }, $aniversariantes);
            
            Response::success($aniversariantesFormatados);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter aniversariantes: " . $e->getMessage());
            Response::serverError('Erro ao carregar aniversariantes');
        }
    }
    
    /**
     * Remover cliente da fila
     */
    public static function removerDaFila(): void
    {
        // Verificar permissão (gestor ou superior)
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        if (!Response::isPost()) {
            Response::error('Método não permitido', null, 405);
        }
        
        try {
            $atendimentoId = Sanitizer::uuid($_POST['atendimento_id'] ?? '');
            $motivo = Sanitizer::text($_POST['motivo'] ?? '', 200);
            
            if (empty($atendimentoId)) {
                Response::error('ID do atendimento é obrigatório');
            }
            
            $atendimentoModel = new Atendimento();
            $sucesso = $atendimentoModel->cancelarAtendimento($atendimentoId, $motivo);
            
            if (!$sucesso) {
                Response::error('Atendimento não encontrado ou não pode ser cancelado');
            }
            
            // Log da ação
            LogController::registrar(
                'atendimento',
                Auth::getUserId(),
                "Cliente removido da fila - Atendimento: $atendimentoId | Motivo: $motivo"
            );
            
            Response::success(null, 'Cliente removido da fila com sucesso');
            
        } catch (\Exception $e) {
            error_log("Erro ao remover da fila: " . $e->getMessage());
            Response::serverError('Erro ao remover cliente da fila');
        }
    }
    
    /**
     * Obter estatísticas gerais de clientes
     */
    public static function estatisticas(): void
    {
        // Verificar permissão (gestor ou superior)
        if (!Auth::isGestor()) {
            Response::unauthorized('Acesso negado');
        }
        
        try {
            $clienteModel = new Cliente();
            
            // Total de clientes
            $totalClientes = $clienteModel->count();
            
            // Novos clientes no mês
            $inicioMes = date('Y-m-01');
            $fimMes = date('Y-m-t');
            $relatorioNovoClientes = $clienteModel->getRelatorioNovoClientes($inicioMes, $fimMes);
            $novosNoMes = array_sum(array_column($relatorioNovoClientes, 'novos_clientes'));
            
            // Clientes frequentes
            $frequentes = $clienteModel->getClientesFrequentes(5);
            
            // Clientes inativos (sem atendimento há 90 dias)
            $inativos = $clienteModel->getClientesInativos(90);
            
            Response::success([
                'total_clientes' => $totalClientes,
                'novos_no_mes' => $novosNoMes,
                'clientes_frequentes' => $frequentes,
                'clientes_inativos' => count($inativos),
                'crescimento_mensal' => $relatorioNovoClientes
            ]);
            
        } catch (\Exception $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            Response::serverError('Erro ao carregar estatísticas');
        }
    }
    
    /**
     * Formatar tempo em minutos para exibição
     */
    private static function formatarTempo(int $minutos): string
    {
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