<?php
/**
 * Página de Login
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

use Utils\CSRF;

// Se já estiver logado, redirecionar
if (Auth::check()) {
    $perfil = Auth::getUser()['perfil'] ?? '';
    switch ($perfil) {
        case 'administrador':
            Response::redirect('/admin/dashboard');
            break;
        case 'gestor':
            Response::redirect('/gestor/dashboard');
            break;
        case 'recepcao':
            Response::redirect('/recepcao/fila');
            break;
        case 'profissional':
            Response::redirect('/profissional/painel');
            break;
        default:
            Auth::logout();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fast Escova</title>
    <link rel="stylesheet" href="/assets/style.css">
    <?php echo CSRF::metaTag(); ?>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Fast Escova</h1>
                <p class="login-subtitle">Sistema de Gerenciamento</p>
            </div>

            <div id="alert-container"></div>

            <form id="loginForm" method="POST">
                <?php echo CSRF::inputField(); ?>
                
                <div class="form-group">
                    <label for="usuario" class="form-label">Usuário</label>
                    <input 
                        type="text" 
                        id="usuario" 
                        name="usuario" 
                        class="form-control" 
                        required 
                        autocomplete="username"
                        placeholder="Digite seu usuário"
                        autofocus
                    >
                    <div class="invalid-feedback"></div>
                </div>

                <div class="form-group">
                    <label for="senha" class="form-label">Senha</label>
                    <input 
                        type="password" 
                        id="senha" 
                        name="senha" 
                        class="form-control" 
                        required 
                        autocomplete="current-password"
                        placeholder="Digite sua senha"
                    >
                    <div class="invalid-feedback"></div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;" id="submitBtn">
                        <span id="btnText">Entrar</span>
                        <span id="btnLoading" class="loading d-none"></span>
                    </button>
                </div>
            </form>

            <div class="text-center">
                <p class="text-muted">ou</p>
                <a href="/nfc" class="btn btn-outline" style="width: 100%;">
                    <span style="margin-right: 0.5rem;">📱</span>
                    Login com NFC
                </a>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">
                    Fast Escova v1.0 &copy; <?php echo date('Y'); ?>
                </small>
            </div>
        </div>
    </div>

    <script>
        // Configurações globais
        window.APP = {
            csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            baseUrl: ''
        };

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');
            const alertContainer = document.getElementById('alert-container');

            // Função para mostrar alerta
            function showAlert(message, type = 'danger') {
                alertContainer.innerHTML = `
                    <div class="alert alert-${type}" role="alert">
                        ${message}
                    </div>
                `;
                
                // Auto-remover após 5 segundos
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

            // Submit do formulário
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                clearValidationErrors();
                setLoading(true);
                
                try {
                    const formData = new FormData(form);
                    formData.append('action', 'login');
                    
                    const response = await fetch('/api/auth.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.ok) {
                        // Login bem-sucedido
                        showAlert('Login realizado com sucesso! Redirecionando...', 'success');
                        
                        // Redirecionar após 1 segundo
                        setTimeout(() => {
                            window.location.href = data.data.redirect_url || '/';
                        }, 1000);
                    } else {
                        // Erro no login
                        if (data.validation) {
                            showValidationErrors(data.validation);
                        } else {
                            showAlert(data.error || 'Erro no login');
                        }
                    }
                } catch (error) {
                    console.error('Erro na requisição:', error);
                    showAlert('Erro de comunicação com o servidor');
                } finally {
                    setLoading(false);
                }
            });

            // Limpar erros ao digitar
            form.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                    const feedback = this.parentNode.querySelector('.invalid-feedback');
                    if (feedback) feedback.textContent = '';
                });
            });

            // Verificar se há mensagem de erro na URL (logout forçado, etc)
            const urlParams = new URLSearchParams(window.location.search);
            const errorMsg = urlParams.get('error');
            if (errorMsg) {
                showAlert(decodeURIComponent(errorMsg));
                
                // Limpar URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Foco no primeiro campo
            document.getElementById('usuario').focus();
        });
    </script>
</body>
</html>