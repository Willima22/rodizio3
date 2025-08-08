// Sistema de Gestão de Atendimentos - JavaScript Principal
class App {
    constructor() {
        this.apiUrl = '/api';
        this.currentUser = null;
        this.init();
    }

    init() {
        this.checkAuth();
        this.setupEventListeners();
        this.loadUserInterface();
    }

    // Verificação de autenticação
    async checkAuth() {
        try {
            const response = await this.makeRequest('/auth/check', 'GET');
            if (response.success) {
                this.currentUser = response.user;
                this.redirectToUserDashboard();
            }
        } catch (error) {
            console.log('Usuário não autenticado');
        }
    }

    // Setup de event listeners globais
    setupEventListeners() {
        // Logout
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action="logout"]')) {
                this.logout();
            }
        });

        // Modal handlers
        this.setupModalHandlers();
        
        // Form handlers
        this.setupFormHandlers();
    }

    // Setup de modais
    setupModalHandlers() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-modal-trigger]')) {
                const modalId = e.target.getAttribute('data-modal-trigger');
                this.openModal(modalId);
            }
            
            if (e.target.matches('[data-modal-close]')) {
                this.closeModal(e.target.closest('.modal'));
            }
        });

        // Fechar modal clicando fora
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal')) {
                this.closeModal(e.target);
            }
        });
    }

    // Setup de formulários
    setupFormHandlers() {
        document.addEventListener('submit', (e) => {
            if (e.target.matches('form[data-ajax]')) {
                e.preventDefault();
                this.handleAjaxForm(e.target);
            }
        });
    }

    // Carregar interface do usuário
    loadUserInterface() {
        if (this.currentUser) {
            this.loadNotifications();
            this.setupRealTimeUpdates();
        }
    }

    // Fazer requisição HTTP
    async makeRequest(endpoint, method = 'GET', data = null) {
        const config = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            config.body = JSON.stringify(data);
        }

        const response = await fetch(this.apiUrl + endpoint, config);
        return await response.json();
    }

    // Login
    async login(credentials) {
        try {
            const response = await this.makeRequest('/auth/login', 'POST', credentials);
            if (response.success) {
                this.currentUser = response.user;
                this.showNotification('Login realizado com sucesso!', 'success');
                this.redirectToUserDashboard();
            } else {
                this.showNotification(response.message, 'error');
            }
        } catch (error) {
            this.showNotification('Erro ao fazer login', 'error');
        }
    }

    // Logout
    async logout() {
        try {
            await this.makeRequest('/auth/logout', 'POST');
            this.currentUser = null;
            window.location.href = '/login';
        } catch (error) {
            this.showNotification('Erro ao fazer logout', 'error');
        }
    }

    // Redirecionar para dashboard do usuário
    redirectToUserDashboard() {
        if (!this.currentUser) return;

        const dashboards = {
            'gestor': '/gestor/dashboard',
            'profissional': '/profissional/painel',
            'recepcao': '/recepcao/fila'
        };

        const redirectUrl = dashboards[this.currentUser.tipo] || '/';
        window.location.href = redirectUrl;
    }

    // Manipular formulários AJAX
    async handleAjaxForm(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const endpoint = form.getAttribute('action') || '';
        const method = form.getAttribute('method') || 'POST';

        try {
            this.showLoading(true);
            const response = await this.makeRequest(endpoint, method, data);
            
            if (response.success) {
                this.showNotification(response.message || 'Operação realizada com sucesso!', 'success');
                
                // Recarregar tabela se existir
                const tableId = form.getAttribute('data-reload-table');
                if (tableId) {
                    this.reloadTable(tableId);
                }
                
                // Fechar modal se estiver em um
                const modal = form.closest('.modal');
                if (modal) {
                    this.closeModal(modal);
                }
                
                // Reset form
                form.reset();
            } else {
                this.showNotification(response.message, 'error');
            }
        } catch (error) {
            this.showNotification('Erro ao processar solicitação', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    // Abrir modal
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    // Fechar modal
    closeModal(modal) {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // Mostrar notificação
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">&times;</button>
        `;

        const container = document.getElementById('notifications') || document.body;
        container.appendChild(notification);

        // Auto remove após 5 segundos
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    // Mostrar/esconder loading
    showLoading(show) {
        let loader = document.getElementById('global-loader');
        
        if (show) {
            if (!loader) {
                loader = document.createElement('div');
                loader.id = 'global-loader';
                loader.className = 'loader-overlay';
                loader.innerHTML = '<div class="loader"></div>';
                document.body.appendChild(loader);
            }
            loader.style.display = 'flex';
        } else {
            if (loader) {
                loader.style.display = 'none';
            }
        }
    }

    // Recarregar tabela
    async reloadTable(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const endpoint = table.getAttribute('data-endpoint');
        if (!endpoint) return;

        try {
            const response = await this.makeRequest(endpoint, 'GET');
            if (response.success) {
                this.updateTableContent(table, response.data);
            }
        } catch (error) {
            console.error('Erro ao recarregar tabela:', error);
        }
    }

    // Atualizar conteúdo da tabela
    updateTableContent(table, data) {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = '';
        
        data.forEach(item => {
            const row = this.createTableRow(item, table.getAttribute('data-row-template'));
            tbody.appendChild(row);
        });
    }

    // Criar linha da tabela
    createTableRow(data, template) {
        const row = document.createElement('tr');
        row.innerHTML = template.replace(/\{\{(\w+)\}\}/g, (match, key) => {
            return data[key] || '';
        });
        return row;
    }

    // Carregar notificações
    async loadNotifications() {
        try {
            const response = await this.makeRequest('/notifications', 'GET');
            if (response.success) {
                this.updateNotificationCount(response.count);
            }
        } catch (error) {
            console.error('Erro ao carregar notificações:', error);
        }
    }

    // Atualizar contador de notificações
    updateNotificationCount(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'block' : 'none';
        }
    }

    // Setup de atualizações em tempo real
    setupRealTimeUpdates() {
        // Verificar novas notificações a cada 30 segundos
        setInterval(() => {
            this.loadNotifications();
        }, 30000);
    }

    // Confirmar ação
    confirm(message, callback) {
        if (window.confirm(message)) {
            callback();
        }
    }

    // Deletar item
    async deleteItem(endpoint, id, confirmMessage = 'Tem certeza que deseja excluir?') {
        this.confirm(confirmMessage, async () => {
            try {
                const response = await this.makeRequest(`${endpoint}/${id}`, 'DELETE');
                if (response.success) {
                    this.showNotification('Item excluído com sucesso!', 'success');
                    // Recarregar página ou tabela
                    window.location.reload();
                } else {
                    this.showNotification(response.message, 'error');
                }
            } catch (error) {
                this.showNotification('Erro ao excluir item', 'error');
            }
        });
    }
}

// Utilitários
class Utils {
    static formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR');
    }

    static formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('pt-BR');
    }

    static formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }

    static formatPhone(phone) {
        const cleaned = phone.replace(/\D/g, '');
        const match = cleaned.match(/^(\d{2})(\d{4,5})(\d{4})$/);
        if (match) {
            return `(${match[1]}) ${match[2]}-${match[3]}`;
        }
        return phone;
    }

    static validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    static validateCPF(cpf) {
        cpf = cpf.replace(/\D/g, '');
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
        
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            sum += parseInt(cpf.charAt(i)) * (10 - i);
        }
        let checkDigit = 11 - (sum % 11);
        if (checkDigit === 10 || checkDigit === 11) checkDigit = 0;
        if (checkDigit !== parseInt(cpf.charAt(9))) return false;
        
        sum = 0;
        for (let i = 0; i < 10; i++) {
            sum += parseInt(cpf.charAt(i)) * (11 - i);
        }
        checkDigit = 11 - (sum % 11);
        if (checkDigit === 10 || checkDigit === 11) checkDigit = 0;
        return checkDigit === parseInt(cpf.charAt(10));
    }
}

// Inicializar aplicação quando DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    window.app = new App();
});

// Exportar para uso global
window.App = App;
window.Utils = Utils;