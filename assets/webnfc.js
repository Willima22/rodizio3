// Sistema de Autenticação NFC
class NFCAuth {
    constructor() {
        this.ndef = null;
        this.isReading = false;
        this.isWriting = false;
        this.currentSession = null;
        this.init();
    }

    async init() {
        try {
            // Verificar se Web NFC está disponível
            if ('NDEFReader' in window) {
                this.ndef = new NDEFReader();
                this.setupEventListeners();
                console.log('Web NFC inicializado com sucesso');
            } else {
                console.warn('Web NFC não está disponível neste navegador');
                this.showUnsupportedMessage();
            }
        } catch (error) {
            console.error('Erro ao inicializar Web NFC:', error);
            this.showErrorMessage('Erro ao inicializar sistema NFC');
        }
    }

    // Configurar event listeners
    setupEventListeners() {
        // Botões de ação NFC
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-nfc-action="read"]')) {
                this.startReading();
            }
            
            if (e.target.matches('[data-nfc-action="write"]')) {
                const userId = e.target.getAttribute('data-user-id');
                this.startWriting(userId);
            }
            
            if (e.target.matches('[data-nfc-action="stop"]')) {
                this.stopNFC();
            }

            if (e.target.matches('[data-nfc-action="login"]')) {
                this.startNFCLogin();
            }
        });
    }

    // Iniciar leitura NFC
    async startReading() {
        if (!this.ndef) {
            this.showErrorMessage('NFC não está disponível');
            return;
        }

        try {
            this.isReading = true;
            this.updateUIState();
            
            await this.ndef.scan();
            console.log('Escaneamento NFC iniciado');
            
            this.ndef.addEventListener('reading', ({ message, serialNumber }) => {
                this.handleNFCRead(message, serialNumber);
            });

            this.ndef.addEventListener('readingerror', () => {
                this.showErrorMessage('Erro ao ler tag NFC');
                this.stopReading();
            });

        } catch (error) {
            console.error('Erro ao iniciar leitura NFC:', error);
            this.showErrorMessage('Erro ao iniciar leitura NFC');
            this.isReading = false;
            this.updateUIState();
        }
    }

    // Parar leitura NFC
    async stopReading() {
        if (this.ndef && this.isReading) {
            try {
                await this.ndef.stop();
                this.isReading = false;
                this.updateUIState();
                console.log('Leitura NFC parada');
            } catch (error) {
                console.error('Erro ao parar leitura NFC:', error);
            }
        }
    }

    // Iniciar escrita NFC
    async startWriting(userId) {
        if (!this.ndef) {
            this.showErrorMessage('NFC não está disponível');
            return;
        }

        if (!userId) {
            this.showErrorMessage('ID do usuário não fornecido');
            return;
        }

        try {
            this.isWriting = true;
            this.updateUIState();

            // Preparar dados para escrita
            const nfcData = await this.prepareNFCData(userId);
            
            await this.ndef.write({
                records: [{
                    recordType: "text",
                    data: JSON.stringify(nfcData)
                }]
            });

            this.showSuccessMessage('Tag NFC gravada com sucesso!');
            
            // Salvar sessão NFC no servidor
            await this.saveNFCSession(userId, nfcData.sessionId);

        } catch (error) {
            console.error('Erro ao escrever NFC:', error);
            this.showErrorMessage('Erro ao gravar tag NFC');
        } finally {
            this.isWriting = false;
            this.updateUIState();
        }
    }

    // Preparar dados para NFC
    async prepareNFCData(userId) {
        const sessionId = this.generateSessionId();
        const timestamp = Date.now();
        
        return {
            userId: userId,
            sessionId: sessionId,
            timestamp: timestamp,
            type: 'auth',
            version: '1.0'
        };
    }

    // Manipular leitura NFC
    async handleNFCRead(message, serialNumber) {
        try {
            console.log('Tag NFC detectada:', serialNumber);
            
            // Extrair dados da mensagem
            const record = message.records[0];
            const textDecoder = new TextDecoder(record.encoding || 'utf-8');
            const data = JSON.parse(textDecoder.decode(record.data));
            
            console.log('Dados NFC:', data);
            
            // Verificar se é uma tag de autenticação
            if (data.type === 'auth') {
                await this.processAuthTag(data, serialNumber);
            } else {
                this.showErrorMessage('Tag NFC não é válida para autenticação');
            }
            
        } catch (error) {
            console.error('Erro ao processar tag NFC:', error);
            this.showErrorMessage('Erro ao processar tag NFC');
        }
    }

    // Processar tag de autenticação
    async processAuthTag(data, serialNumber) {
        try {
            // Verificar validade da sessão
            const response = await fetch('/api/nfc/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    userId: data.userId,
                    sessionId: data.sessionId,
                    timestamp: data.timestamp,
                    serialNumber: serialNumber
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showSuccessMessage('Autenticação NFC realizada com sucesso!');
                
                // Realizar login automático
                await this.performNFCLogin(result.user);
            } else {
                this.showErrorMessage(result.message || 'Tag NFC inválida ou expirada');
            }
            
        } catch (error) {
            console.error('Erro ao validar tag NFC:', error);
            this.showErrorMessage('Erro ao validar autenticação NFC');
        }
    }

    // Realizar login via NFC
    async performNFCLogin(userData) {
        try {
            const response = await fetch('/api/auth/nfc-login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            });

            const result = await response.json();
            
            if (result.success) {
                this.showSuccessMessage('Login realizado com sucesso!');
                
                // Redirecionar para dashboard
                setTimeout(() => {
                    window.location.href = this.getDashboardUrl(userData.tipo);
                }, 1500);
            } else {
                this.showErrorMessage(result.message || 'Erro ao realizar login');
            }
            
        } catch (error) {
            console.error('Erro ao realizar login NFC:', error);
            this.showErrorMessage('Erro ao realizar login');
        }
    }

    // Iniciar login NFC (página de login)
    async startNFCLogin() {
        this.showInfoMessage('Aproxime sua tag NFC do dispositivo...');
        await this.startReading();
    }

    // Salvar sessão NFC no servidor
    async saveNFCSession(userId, sessionId) {
        try {
            await fetch('/api/nfc/session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    userId: userId,
                    sessionId: sessionId,
                    timestamp: Date.now()
                })
            });
        } catch (error) {
            console.error('Erro ao salvar sessão NFC:', error);
        }
    }

    // Parar todas as operações NFC
    async stopNFC() {
        await this.stopReading();
        this.isWriting = false;
        this.updateUIState();
    }

    // Gerar ID de sessão único
    generateSessionId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    // Obter URL do dashboard baseado no tipo de usuário
    getDashboardUrl(userType) {
        const dashboards = {
            'gestor': '/gestor/dashboard',
            'profissional': '/profissional/painel',
            'recepcao': '/recepcao/fila'
        };
        return dashboards[userType] || '/';
    }

    // Atualizar estado da UI
    updateUIState() {
        // Atualizar botões
        const readBtn = document.querySelector('[data-nfc-action="read"]');
        const writeBtn = document.querySelector('[data-nfc-action="write"]');
        const stopBtn = document.querySelector('[data-nfc-action="stop"]');
        const loginBtn = document.querySelector('[data-nfc-action="login"]');

        if (readBtn) {
            readBtn.disabled = this.isReading || this.isWriting;
            readBtn.textContent = this.isReading ? 'Lendo...' : 'Ler NFC';
        }

        if (writeBtn) {
            writeBtn.disabled = this.isReading || this.isWriting;
            writeBtn.textContent = this.isWriting ? 'Gravando...' : 'Gravar NFC';
        }

        if (stopBtn) {
            stopBtn.style.display = (this.isReading || this.isWriting) ? 'block' : 'none';
        }

        if (loginBtn) {
            loginBtn.disabled = this.isReading || this.isWriting;
            loginBtn.textContent = this.isReading ? 'Aguardando NFC...' : 'Login com NFC';
        }

        // Atualizar indicadores de status
        this.updateStatusIndicator();
    }

    // Atualizar indicador de status
    updateStatusIndicator() {
        const indicator = document.querySelector('.nfc-status-indicator');
        if (indicator) {
            if (this.isReading) {
                indicator.className = 'nfc-status-indicator reading';
                indicator.textContent = 'Lendo NFC...';
            } else if (this.isWriting) {
                indicator.className = 'nfc-status-indicator writing';
                indicator.textContent = 'Gravando NFC...';
            } else {
                indicator.className = 'nfc-status-indicator idle';
                indicator.textContent = 'NFC Pronto';
            }
        }
    }

    // Mostrar mensagem de erro
    showErrorMessage(message) {
        this.showMessage(message, 'error');
    }

    // Mostrar mensagem de sucesso
    showSuccessMessage(message) {
        this.showMessage(message, 'success');
    }

    // Mostrar mensagem de informação
    showInfoMessage(message) {
        this.showMessage(message, 'info');
    }

    // Mostrar mensagem genérica
    showMessage(message, type) {
        // Se existe uma instância global do app, usar seu sistema de notificações
        if (window.app && window.app.showNotification) {
            window.app.showNotification(message, type);
            return;
        }

        // Fallback para alert simples
        alert(message);
    }

    // Mostrar mensagem para navegadores não suportados
    showUnsupportedMessage() {
        const message = `
            <div class="nfc-unsupported">
                <h3>NFC não suportado</h3>
                <p>Seu navegador não suporta Web NFC. Por favor, use:</p>
                <ul>
                    <li>Chrome 89+ no Android</li>
                    <li>Edge 89+ no Android</li>
                </ul>
                <p>Você ainda pode fazer login usando email e senha.</p>
            </div>
        `;
        
        const container = document.querySelector('.nfc-container');
        if (container) {
            container.innerHTML = message;
        }
    }

    // Verificar se NFC está disponível
    static isNFCAvailable() {
        return 'NDEFReader' in window;
    }

    // Verificar permissões NFC
    async checkNFCPermissions() {
        try {
            const permissionStatus = await navigator.permissions.query({ name: 'nfc' });
            return permissionStatus.state === 'granted';
        } catch (error) {
            console.warn('Não foi possível verificar permissões NFC:', error);
            return false;
        }
    }
}

// Classe para gerenciar tags NFC de funcionários
class NFCEmployeeManager {
    constructor() {
        this.nfcAuth = new NFCAuth();
    }

    // Cadastrar nova tag para funcionário
    async registerEmployeeTag(employeeId, employeeName) {
        try {
            const confirmed = confirm(`Deseja cadastrar uma nova tag NFC para ${employeeName}?`);
            if (!confirmed) return;

            // Iniciar processo de gravação
            await this.nfcAuth.startWriting(employeeId);
            
        } catch (error) {
            console.error('Erro ao cadastrar tag do funcionário:', error);
        }
    }

    // Remover tag de funcionário
    async removeEmployeeTag(employeeId) {
        try {
            const response = await fetch('/api/nfc/remove-tag', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ userId: employeeId })
            });

            const result = await response.json();
            
            if (result.success) {
                this.nfcAuth.showSuccessMessage('Tag NFC removida com sucesso!');
            } else {
                this.nfcAuth.showErrorMessage(result.message || 'Erro ao remover tag NFC');
            }
            
        } catch (error) {
            console.error('Erro ao remover tag NFC:', error);
            this.nfcAuth.showErrorMessage('Erro ao remover tag NFC');
        }
    }

    // Listar tags ativas
    async listActiveTags() {
        try {
            const response = await fetch('/api/nfc/active-tags');
            const result = await response.json();
            
            if (result.success) {
                return result.tags;
            } else {
                throw new Error(result.message || 'Erro ao listar tags');
            }
            
        } catch (error) {
            console.error('Erro ao listar tags ativas:', error);
            return [];
        }
    }
}

// Inicializar quando DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    // Verificar se estamos em uma página que usa NFC
    if (document.querySelector('[data-nfc-action]') || document.querySelector('.nfc-container')) {
        window.nfcAuth = new NFCAuth();
        window.nfcEmployeeManager = new NFCEmployeeManager();
        
        // Verificar disponibilidade do NFC
        if (!NFCAuth.isNFCAvailable()) {
            console.warn('Web NFC não está disponível neste dispositivo/navegador');
        }
    }
});

// Exportar classes
window.NFCAuth = NFCAuth;
window.NFCEmployeeManager = NFCEmployeeManager;