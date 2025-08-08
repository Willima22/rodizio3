<?php
/**
 * Página de Login NFC
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

use Utils\Auth;
use Utils\Response;

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
    <title>Login NFC - Fast Escova</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Login NFC</h1>
                <p class="login-subtitle">Aproxime seu cartão do dispositivo</p>
            </div>

            <div id="nfc-login-container" class="nfc-container">
                <div class="nfc-icon">📱</div>
                
                <div id="nfc-status" class="nfc-status nfc-status-info">
                    Verificando suporte ao NFC...
                </div>

                <div id="nfc-controls" class="d-none">
                    <button id="startNfcBtn" class="btn btn-primary btn-lg">
                        Ativar NFC
                    </button>
                    
                    <button id="stopNfcBtn" class="btn btn-secondary btn-lg d-none">
                        Parar Escaneamento
                    </button>
                </div>

                <div id="device-info" class="mt-4" style="display: none;">
                    <small class="text-muted">
                        <strong>Requisitos:</strong><br>
                        • Chrome no Android<br>
                        • NFC habilitado no dispositivo<br>
                        • Conexão HTTPS
                    </small>
                </div>
            </div>

            <div class="text-center">
                <a href="/login" class="btn btn-outline">
                    ← Voltar ao Login Normal
                </a>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">
                    Fast Escova v1.0 &copy; <?php echo date('Y'); ?>
                </small>
            </div>
        </div>
    </div>

    <script src="/assets/webnfc.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async function() {
            const statusElement = document.getElementById('nfc-status');
            const controlsElement = document.getElementById('nfc-controls');
            const startBtn = document.getElementById('startNfcBtn');
            const stopBtn = document.getElementById('stopNfcBtn');
            const deviceInfo = document.getElementById('device-info');

            let nfcLogin = null;

            // Função para atualizar status
            function updateStatus(message, type = 'info') {
                statusElement.textContent = message;
                statusElement.className = `nfc-status nfc-status-${type}`;
            }

            // Função para mostrar controles
            function showControls(show = true) {
                if (show) {
                    controlsElement.classList.remove('d-none');
                } else {
                    controlsElement.classList.add('d-none');
                }
            }

            // Verificar suporte ao NFC
            try {
                // Verificar se WebNFC é suportado
                if (!('NDEFReader' in window)) {
                    throw new Error('WebNFC não é suportado neste navegador');
                }

                // Verificar requisitos
                const userAgent = navigator.userAgent.toLowerCase();
                const isAndroid = userAgent.includes('android');
                const isChrome = userAgent.includes('chrome') && !userAgent.includes('edg');
                const isHttps = location.protocol === 'https:' || location.hostname === 'localhost';

                if (!isAndroid) {
                    throw new Error('NFC funciona apenas em dispositivos Android');
                }

                if (!isChrome) {
                    throw new Error('NFC funciona apenas no Chrome');
                }

                if (!isHttps) {
                    throw new Error('NFC requer conexão HTTPS');
                }

                // Criar instância do NFCLogin
                nfcLogin = new NFCLogin();

                // Configurar eventos personalizados
                window.addEventListener('nfcStatus', function(event) {
                    const { message, type } = event.detail;
                    updateStatus(message, type);
                });

                window.addEventListener('nfcError', function(event) {
                    const { message } = event.detail;
                    updateStatus(message, 'error');
                    
                    // Resetar botões
                    startBtn.classList.remove('d-none');
                    stopBtn.classList.add('d-none');
                });

                // Configurar handler de sucesso personalizado
                nfcLogin.nfcHandler.onRead(async function(uid) {
                    updateStatus('Cartão detectado! Processando login...', 'info');
                    
                    try {
                        const response = await fetch('/api/nfc.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                action: 'login',
                                uid: uid
                            })
                        });

                        const data = await response.json();

                        if (data.ok) {
                            updateStatus('Login realizado com sucesso! Redirecionando...', 'success');
                            
                            // Parar escaneamento
                            await nfcLogin.stopLogin();
                            
                            // Redirecionar após 1 segundo
                            setTimeout(() => {
                                window.location.href = data.data.redirect_url || '/profissional/painel';
                            }, 1000);
                        } else {
                            updateStatus(data.error || 'Cartão NFC não reconhecido', 'error');
                            
                            // Tentar novamente após 3 segundos
                            setTimeout(() => {
                                updateStatus('Aproxime o cartão NFC novamente...', 'info');
                            }, 3000);
                        }
                    } catch (error) {
                        console.error('Erro no login NFC:', error);
                        updateStatus('Erro de comunicação com o servidor', 'error');
                    }
                });

                updateStatus('NFC suportado. Clique em "Ativar NFC" para começar.', 'success');
                showControls(true);

            } catch (error) {
                console.error('Erro de suporte NFC:', error);
                updateStatus(error.message, 'error');
                deviceInfo.style.display = 'block';
            }

            // Event listeners dos botões
            startBtn.addEventListener('click', async function() {
                try {
                    updateStatus('Solicitando permissões NFC...', 'info');
                    
                    const success = await nfcLogin.startLogin();
                    
                    if (success) {
                        startBtn.classList.add('d-none');
                        stopBtn.classList.remove('d-none');
                        updateStatus('Aproxime o cartão NFC do dispositivo...', 'info');
                    }
                } catch (error) {
                    console.error('Erro ao iniciar NFC:', error);
                    updateStatus('Erro ao ativar NFC: ' + error.message, 'error');
                }
            });

            stopBtn.addEventListener('click', async function() {
                try {
                    await nfcLogin.stopLogin();
                    
                    startBtn.classList.remove('d-none');
                    stopBtn.classList.add('d-none');
                    updateStatus('Escaneamento NFC parado', 'info');
                } catch (error) {
                    console.error('Erro ao parar NFC:', error);
                }
            });

            // Verificar status do NFC no servidor
            try {
                const response = await fetch('/api/nfc.php?action=status');
                const data = await response.json();
                
                if (data.ok) {
                    console.log('Status NFC do servidor:', data.data);
                }
            } catch (error) {
                console.error('Erro ao verificar status NFC:', error);
            }

            // Cleanup ao sair da página
            window.addEventListener('beforeunload', function() {
                if (nfcLogin) {
                    nfcLogin.stopLogin();
                }
            });
        });
    </script>
</body>
</html>