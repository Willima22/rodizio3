<?php
/**
 * Página de Cadastro de Clientes
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

use Utils\Auth;
use Utils\Response;
use Utils\CSRF;

// Verificar autenticação e permissão
if (!Auth::check() || !Auth::isRecepcao()) {
    Response::redirect('/login');
}

$user = Auth::getUser();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Cliente - Fast Escova</title>
    <link rel="stylesheet" href="/assets/style.css">
    <?php echo CSRF::metaTag(); ?>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="d-flex align-items-center">
                <button id="sidebarToggle" class="btn btn-outline" style="margin-right: 1rem;">
                    ☰
                </button>
                <a href="/" class="logo">Fast Escova</a>
            </div>
            
            <div class="header-user">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['nome']); ?></div>
                    <div class="user-role"><?php echo ucfirst($user['perfil']); ?></div>
                </div>
                <a href="/api/auth.php?action=logout" class="btn btn-outline btn-sm">Sair</a>
            </div>
        </header>

        <!-- Sidebar -->
        <nav class="app-sidebar" id="sidebar">
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="/recepcao/fila" class="nav-link">
                        <span class="nav-icon">👥</span>
                        Fila de Espera
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/recepcao/cadastro" class="nav-link active">
                        <span class="nav-icon">➕</span>
                        Cadastrar Cliente
                    </a>
                </li>
                <?php if (Auth::isGestor()): ?>
                <li class="nav-item">
                    <a href="/gestor/dashboard" class="nav-link">
                        <span class="nav-icon">📊</span>
                        Dashboard
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="app-main">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Cadastrar Cliente na Fila</h2>
                </div>
                <div class="card-body">
                    <div id="alert-container"></div>

                    <form id="cadastroForm">
                        <?php echo CSRF::inputField(); ?>
                        
                        <div class="form-group">
                            <label for="nome" class="form-label">Nome do Cliente *</label>
                            <input 
                                type="text" 
                                id="nome" 
                                name="nome" 
                                class="form-control" 
                                required 
                                placeholder="Digite o nome completo do cliente"
                                autofocus
                            >
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input 
                                type="tel" 
                                id="telefone" 
                                name="telefone" 
                                class="form-control" 
                                placeholder="(11) 99999-9999"
                            >
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">E-mail</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control" 
                                placeholder="cliente@email.com"
                            >
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="id_servico" class="form-label">Serviço Desejado *</label>
                            <select id="id_servico" name="id_servico" class="form-control form-select" required>
                                <option value="">Selecione o serviço...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="form-group">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea 
                                id="observacoes" 
                                name="observacoes" 
                                class="form-control" 
                                rows="3"
                                placeholder="Observações adicionais sobre o cliente ou atendimento..."
                            ></textarea>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" id="limparBtn" class="btn btn-secondary">
                                Limpar Formulário
                            </button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <span id="btnText">Cadastrar na Fila</span>
                                <span id="btnLoading" class="loading d-none"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Card de Últimos Cadastros -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Últimos Cadastros</h3>
                    <button id="refreshBtn" class="btn btn-outline btn-sm">
                        🔄 Atualizar
                    </button>
                </div>
                <div class="card-body">
                    <div id="ultimos-cadastros">
                        <div class="text-center">
                            <div class="loading"></div>
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Configurações globais
        window.APP = {
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            baseUrl: ''
        };

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('cadastroForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');
            const limparBtn = document.getElementById('limparBtn');
            const refreshBtn = document.getElementById('refreshBtn');
            const alertContainer = document.getElementById('alert-container');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');

            // Toggle sidebar
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('sidebar-hidden');
            });

            // Função para mostrar alerta
            function showAlert(message, type = 'danger') {
                alertContainer.innerHTML = `
                    <div class="alert alert-${type}" role="alert">
                        ${message}
                    </div>
                `;
                
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 5000);
            }

            // Função para limpar erros de validação
            function clearValidationErrors() {
                document.querySelectorAll('.form-control').forEach(input => {
                    input.classList.remove('is-invalid');
                    const feedback = input.parentNode.querySelector('.invalid-feedback');
                    if (feedback) feedback.textContent = '';
                });
            }

            // Função para mostrar erros de validação
            function showValidationErrors(errors) {
                Object.keys(errors).forEach(field => {
                    const input = document.getElementById(field);
                    if (input) {
                        input.classList.add('is-invalid');
                        const feedback = input.parentNode.querySelector('.invalid-feedback');
                        if (feedback) {
                            feedback.textContent = errors[field][0] || errors[field];
                        }
                    }
                });
            }

            // Função para definir estado de loading
            function setLoading(loading) {
                submitBtn.disabled = loading;
                if (loading) {
                    btnText.classList.add('d-none');
                    btnLoading.classList.remove('d-none');
                } else {
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                }
            }

            // Carregar serviços
            async function carregarServicos() {
                try {
                    const response = await fetch('/api/servicos.php?action=listar&ativos=1');
                    const data = await response.json();
                    
                    if (data.ok) {
                        const select = document.getElementById('id_servico');
                        select.innerHTML = '<option value="">Selecione o serviço...</option>';
                        
                        data.data.forEach(servico => {
                            const option = document.createElement('option');
                            option.value = servico.id;
                            option.textContent = `${servico.nome} - R$ ${parseFloat(servico.preco).toFixed(2)}`;
                            select.appendChild(option);
                        });
                    }
                } catch (error) {
                    console.error('Erro ao carregar serviços:', error);
                }
            }

            // Carregar últimos cadastros
            async function carregarUltimosCadastros() {
                try {
                    const response = await fetch('/api/clientes.php?action=fila');
                    const data = await response.json();
                    
                    if (data.ok) {
                        const container = document.getElementById('ultimos-cadastros');
                        
                        if (data.data.fila.length === 0) {
                            container.innerHTML = '<p class="text-muted text-center">Nenhum cliente na fila</p>';
                            return;
                        }
                        
                        const lista = data.data.fila.slice(0, 5).map(item => `
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2" style="background: #f8f9fa; border-radius: 4px;">
                                <div>
                                    <strong>${item.cliente_nome}</strong><br>
                                    <small class="text-muted">${item.servico_nome}</small>
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-warning">${item.tempo_espera_formatado}</span>
                                </div>
                            </div>
                        `).join('');
                        
                        container.innerHTML = lista;
                    }
                } catch (error) {
                    console.error('Erro ao carregar fila:', error);
                    document.getElementById('ultimos-cadastros').innerHTML = 
                        '<p class="text-muted text-center">Erro ao carregar dados</p>';
                }
            }

            // Submit do formulário
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                clearValidationErrors();
                setLoading(true);
                
                try {
                    const formData = new FormData(form);
                    formData.append('action', 'cadastrar');
                    
                    const response = await fetch('/api/clientes.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.ok) {
                        showAlert(`Cliente ${data.data.cliente.nome} cadastrado na fila com sucesso!`, 'success');
                        form.reset();
                        carregarUltimosCadastros();
                        
                        // Foco no nome para próximo cadastro
                        document.getElementById('nome').focus();
                    } else {
                        if (data.validation) {
                            showValidationErrors(data.validation);
                        } else {
                            showAlert(data.error || 'Erro ao cadastrar cliente');
                        }
                    }
                } catch (error) {
                    console.error('Erro na requisição:', error);
                    showAlert('Erro de comunicação com o servidor');
                } finally {
                    setLoading(false);
                }
            });

            // Limpar formulário
            limparBtn.addEventListener('click', function() {
                form.reset();
                clearValidationErrors();
                document.getElementById('nome').focus();
            });

            // Atualizar últimos cadastros
            refreshBtn.addEventListener('click', function() {
                carregarUltimosCadastros();
            });

            // Limpar erros ao digitar
            form.querySelectorAll('input, select, textarea').forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                    const feedback = this.parentNode.querySelector('.invalid-feedback');
                    if (feedback) feedback.textContent = '';
                });
            });

            // Formatação do telefone
            document.getElementById('telefone').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length <= 11) {
                    if (value.length <= 2) {
                        value = value.replace(/(\d{0,2})/, '($1');
                    } else if (value.length <= 6) {
                        value = value.replace(/(\d{0,2})(\d{0,4})/, '($1) $2');
                    } else if (value.length <= 10) {
                        value = value.replace(/(\d{0,2})(\d{0,4})(\d{0,4})/, '($1) $2-$3');
                    } else {
                        value = value.replace(/(\d{0,2})(\d{0,5})(\d{0,4})/, '($1) $2-$3');
                    }
                }
                
                e.target.value = value;
            });

            // Inicializar
            carregarServicos();
            carregarUltimosCadastros();
            
            // Atualizar a cada 30 segundos
            setInterval(carregarUltimosCadastros, 30000);
        });
    </script>
</body>
</html>