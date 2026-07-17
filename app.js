// frontend/js/app.js - CORRIGIDO

class AgendaApp {
    constructor() {
        // Detectar caminho base automaticamente
        this.basePath = window.location.pathname.includes('/agenda/') ? '/agenda' : '';
        this.apiBase = this.basePath + '/backend/api/';
        this.token = localStorage.getItem('agenda_token');
        this.currentUser = null;
        this.events = [];
        this.users = [];
        this.selectedDate = null;
        this.currentMonth = new Date().getMonth();
        this.currentYear = new Date().getFullYear();
        this.nextId = 1;
        this.syncInterval = null;
        this.isSyncing = false;
        this.lastSync = localStorage.getItem('agenda_last_sync') || new Date().toISOString();
        this.isLoggedIn = false;
        
        // Iniciar
        this.init();
    }
    
    async init() {
        // Verificar se já está logado
        const savedUser = localStorage.getItem('agenda_user');
        if (savedUser) {
            try {
                this.currentUser = JSON.parse(savedUser);
                this.token = localStorage.getItem('agenda_token');
                
                // Tentar sincronizar dados
                const result = await this.syncData();
                if (result.success) {
                    this.isLoggedIn = true;
                    this.showAgenda();
                    this.startAutoSync();
                    return;
                }
            } catch (e) {
                console.warn('Erro ao restaurar sessão:', e);
            }
        }
        
        // Se não estiver logado, mostrar login
        this.showLogin();
    }
    
    async apiRequest(endpoint, method = 'GET', data = null) {
        const url = this.apiBase + endpoint;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token || ''}`,
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('API Error:', error);
            return { success: false, message: 'Erro de conexão com o servidor' };
        }
    }
    
    async login(username, password) {
        const result = await this.apiRequest('login.php', 'POST', { username, password });
        
        if (result.success) {
            this.token = result.token;
            this.currentUser = result.user;
            this.events = result.events || [];
            this.users = result.users || [];
            this.nextId = result.nextId || this.events.length + 1;
            this.lastSync = new Date().toISOString();
            this.isLoggedIn = true;
            
            localStorage.setItem('agenda_token', this.token);
            localStorage.setItem('agenda_user', JSON.stringify(this.currentUser));
            localStorage.setItem('agenda_last_sync', this.lastSync);
            
            return { success: true };
        }
        
        return { success: false, message: result.message || 'Login falhou' };
    }
    
    async syncData() {
        if (this.isSyncing) {
            return { success: false, message: 'Já sincronizando...' };
        }
        
        this.isSyncing = true;
        this.setSyncStatus('syncing', '<i class="fas fa-spinner fa-spin"></i> Sincronizando...');
        
        try {
            const result = await this.apiRequest('sync.php', 'GET');
            
            if (result.success) {
                // Atualizar dados
                if (result.events) {
                    this.events = result.events;
                    this.nextId = Math.max(...this.events.map(e => e.id || 0), 0) + 1;
                }
                if (result.users) {
                    this.users = result.users;
                }
                
                this.lastSync = result.lastSync || new Date().toISOString();
                localStorage.setItem('agenda_last_sync', this.lastSync);
                
                // Atualizar interface
                if (this.isLoggedIn) {
                    this.renderCalendar();
                    this.updateFinance();
                }
                
                this.setSyncStatus('synced', '<i class="fas fa-cloud"></i> Sincronizado');
                
                return { 
                    success: true, 
                    message: '✅ Sincronização efetuada',
                    hasChanges: result.hasChanges !== false
                };
            }
            
            this.setSyncStatus('error', '<i class="fas fa-exclamation-triangle"></i> Erro');
            return { success: false, message: result.message || 'Erro ao sincronizar' };
            
        } catch (error) {
            console.error('Sync error:', error);
            this.setSyncStatus('error', '<i class="fas fa-exclamation-triangle"></i> Erro');
            return { success: false, message: 'Erro de conexão' };
        } finally {
            this.isSyncing = false;
        }
    }
    
    async saveData() {
        const data = {
            events: this.events.map(e => ({
                id: e.id,
                data: e.data,
                horaInicio: e.horaInicio,
                horaFim: e.horaFim,
                horaSede: e.horaSede,
                local: e.local,
                empresa: e.empresa,
                valor: e.valor || 0,
                statusPagamento: e.statusPagamento || 'Pendente',
                user_id: e.user_id || this.currentUser?.id || 0
            })),
            users: this.users,
            lastSync: this.lastSync
        };
        
        const result = await this.apiRequest('sync.php', 'POST', data);
        
        if (result.success) {
            this.lastSync = result.lastSync || new Date().toISOString();
            localStorage.setItem('agenda_last_sync', this.lastSync);
            
            // Mostrar mensagem de sucesso
            this.showToast(result.message || '✅ Sincronização efetuada');
            
            return { success: true };
        }
        
        this.showToast('❌ ' + (result.message || 'Erro ao salvar'));
        return { success: false, message: result.message };
    }
    
    // Métodos para CRUD de eventos
    addEvent(eventData) {
        const newEvent = {
            id: this.nextId++,
            user_id: this.currentUser?.id || 0,
            ...eventData,
            updated_at: new Date().toISOString()
        };
        this.events.push(newEvent);
        this.saveData();
        return newEvent;
    }
    
    updateEvent(id, updates) {
        const index = this.events.findIndex(e => e.id === id);
        if (index !== -1) {
            this.events[index] = { 
                ...this.events[index], 
                ...updates,
                updated_at: new Date().toISOString()
            };
            this.saveData();
            return true;
        }
        return false;
    }
    
    deleteEvent(id) {
        this.events = this.events.filter(e => e.id !== id);
        this.saveData();
        return true;
    }
    
    getEventsForDate(dateKey) {
        return this.events
            .filter(e => e.data === dateKey)
            .sort((a, b) => (a.horaInicio || '').localeCompare(b.horaInicio || ''));
    }
    
    // Sincronização automática
    startAutoSync() {
        if (this.syncInterval) {
            clearInterval(this.syncInterval);
        }
        
        // Sincronizar a cada 5 segundos
        this.syncInterval = setInterval(async () => {
            if (this.isLoggedIn && !this.isSyncing) {
                const result = await this.syncData();
                if (result.success && result.hasChanges) {
                    console.log('🔄 Dados atualizados automaticamente');
                }
            }
        }, 5000);
        
        console.log('✅ Sincronização automática iniciada');
    }
    
    // Sincronização manual (botão)
    async syncManual() {
        const btn = document.getElementById('btnSync');
        if (!btn) return;
        
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sincronizando...';
        btn.disabled = true;
        
        const result = await this.syncData();
        
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (result.success) {
            this.showToast(result.message || '✅ Sincronização efetuada');
            this.renderCalendar();
            this.updateFinance();
        } else {
            this.showToast('❌ ' + (result.message || 'Erro ao sincronizar'));
        }
    }
    
    // UI Helpers
    showLogin() {
        const loginSection = document.getElementById('loginSection');
        const agendaSection = document.getElementById('agendaSection');
        if (loginSection) loginSection.style.display = 'flex';
        if (agendaSection) agendaSection.style.display = 'none';
        this.isLoggedIn = false;
    }
    
    showAgenda() {
        const loginSection = document.getElementById('loginSection');
        const agendaSection = document.getElementById('agendaSection');
        const displayUser = document.getElementById('displayUser');
        
        if (loginSection) loginSection.style.display = 'none';
        if (agendaSection) agendaSection.style.display = 'block';
        if (displayUser) displayUser.textContent = this.currentUser?.username || 'Usuário';
        
        this.isLoggedIn = true;
        this.renderCalendar();
        this.updateFinance();
        this.setSyncStatus('synced', '<i class="fas fa-cloud"></i> Sincronizado');
    }
    
    setSyncStatus(status, message) {
        const syncStatus = document.getElementById('syncStatus');
        if (syncStatus) {
            syncStatus.className = 'sync-status ' + status;
            syncStatus.innerHTML = message;
        }
    }
    
    showToast(message) {
        const toast = document.getElementById('toastMessage');
        if (toast) {
            toast.textContent = message || 'Operação realizada!';
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
    }
    
    // Logout - NÃO sai ao atualizar a página
    logout() {
        // Apenas limpa a sessão em memória, mantém no localStorage
        this.isLoggedIn = false;
        this.showLogin();
        
        // Mas não remove do localStorage para persistir
        // localStorage.removeItem('agenda_token');
        // localStorage.removeItem('agenda_user');
    }
    
    // ... (restante dos métodos: renderCalendar, renderDayEvents, updateFinance, etc)
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    window.app = new AgendaApp();
    
    // Event listeners
    const loginBtn = document.getElementById('loginBtn');
    if (loginBtn) {
        loginBtn.addEventListener('click', async function() {
            const username = document.getElementById('loginUser')?.value.trim() || '';
            const password = document.getElementById('loginPass')?.value.trim() || '';
            
            if (!username || !password) {
                const errorEl = document.getElementById('loginError');
                if (errorEl) {
                    errorEl.textContent = 'Preencha usuário e senha';
                    errorEl.style.display = 'block';
                }
                return;
            }
            
            const result = await window.app.login(username, password);
            const errorEl = document.getElementById('loginError');
            
            if (result.success) {
                if (errorEl) errorEl.style.display = 'none';
                window.app.showAgenda();
                window.app.startAutoSync();
                window.app.showToast('✅ Bem-vindo, ' + username + '!');
            } else {
                if (errorEl) {
                    errorEl.textContent = result.message || 'Usuário ou senha inválidos';
                    errorEl.style.display = 'block';
                }
            }
        });
    }
    
    // Botão de sincronização
    const syncBtn = document.getElementById('btnSync');
    if (syncBtn) {
        syncBtn.addEventListener('click', function() {
            if (window.app) window.app.syncManual();
        });
    }
    
    // Logout - NÃO sai ao atualizar
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            if (window.app) window.app.logout();
        });
    }
    
    // Salvar agenda
    const saveBtn = document.getElementById('btnSalvarAgenda');
    if (saveBtn) {
        saveBtn.addEventListener('click', async function() {
            if (window.app) {
                const result = await window.app.saveData();
                if (result.success) {
                    window.app.showToast('✅ Agenda salva com sucesso!');
                }
            }
        });
    }
    
    // Agendar evento
    const agendarBtn = document.getElementById('btnAgendar');
    if (agendarBtn) {
        agendarBtn.addEventListener('click', function() {
            if (window.app) window.app.openNewModal();
        });
    }
    
    // Enter no login
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && document.getElementById('loginSection')?.style.display !== 'none') {
            const loginBtn = document.getElementById('loginBtn');
            if (loginBtn) loginBtn.click();
        }
    });
});