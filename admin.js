// frontend/js/admin.js

class AdminApp {
    constructor() {
        this.apiBase = '/backend/api/';
        this.token = localStorage.getItem('agenda_token');
        this.users = [];
        this.currentUser = null;
        this.init();
    }
    
    async init() {
        // Verificar se está logado como admin
        const userData = localStorage.getItem('agenda_user');
        if (userData) {
            this.currentUser = JSON.parse(userData);
            if (this.currentUser?.role === 'admin') {
                this.showAdminArea();
                await this.loadUsers();
                return;
            }
        }
        // Se não for admin, redirecionar
        window.location.href = 'index.html';
    }
    
    async apiRequest(endpoint, method = 'GET', data = null) {
        const url = this.apiBase + endpoint;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token || ''}`
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
            return { success: false, message: 'Erro de conexão' };
        }
    }
    
    async loadUsers() {
        const result = await this.apiRequest('users.php', 'GET');
        if (result.success) {
            this.users = result.users || [];
            this.renderUsers();
        }
    }
    
    renderUsers() {
        const container = document.getElementById('userListContainer');
        let html = '';
        
        this.users.forEach(user => {
            const isMaster = user.username === 'edmonteiro';
            const status = user.active ? 'Ativo' : 'Inativo';
            const statusClass = user.active ? 'active' : 'inactive';
            
            html += `
                <div class="user-item" data-id="${user.id}">
                    <div class="user-info">
                        <span class="user-name"><i class="fas fa-user"></i> ${user.username}</span>
                        <span class="user-badge ${statusClass}">${status}</span>
                        <span class="user-badge admin">${user.role}</span>
                        ${isMaster ? '<span style="font-size:0.6rem;color:#2a7faa;font-weight:600;">🔒 Master</span>' : ''}
                    </div>
                    <div class="user-actions">
                        ${!isMaster ? `
                            <button class="btn-warning btn-sm btn-toggle-active" data-id="${user.id}">
                                <i class="fas ${user.active ? 'fa-pause' : 'fa-play'}"></i>
                                ${user.active ? 'Desativar' : 'Ativar'}
                            </button>
                            <button class="btn-warning btn-sm btn-edit-pass" data-id="${user.id}" style="background:#8b5e3b;">
                                <i class="fas fa-key"></i> Senha
                            </button>
                            <button class="btn-danger btn-sm btn-delete-user" data-id="${user.id}">
                                <i class="fas fa-trash-alt"></i> Excluir
                            </button>
                        ` : `
                            <button class="btn-warning btn-sm btn-edit-pass" data-id="${user.id}" style="background:#8b5e3b;">
                                <i class="fas fa-key"></i> Senha
                            </button>
                        `}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html || '<p style="color:#7f9aaa; text-align:center; padding:20px;">Nenhum usuário cadastrado.</p>';
        
        // Atualizar estatísticas
        document.getElementById('totalUsers').textContent = this.users.length;
        document.getElementById('activeUsers').textContent = this.users.filter(u => u.active).length;
        document.getElementById('inactiveUsers').textContent = this.users.filter(u => !u.active).length;
        
        // Adicionar event listeners
        container.querySelectorAll('.btn-toggle-active').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id);
                await this.toggleUser(id);
            });
        });
        
        container.querySelectorAll('.btn-delete-user').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id);
                if (confirm('Tem certeza que deseja excluir este usuário?')) {
                    await this.deleteUser(id);
                }
            });
        });
        
        container.querySelectorAll('.btn-edit-pass').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id);
                const user = this.users.find(u => u.id === id);
                if (user) this.openPasswordModal(user);
            });
        });
    }
    
    async toggleUser(id) {
        const user = this.users.find(u => u.id === id);
        if (!user) return;
        
        const result = await this.apiRequest('users.php', 'PUT', {
            id: id,
            active: user.active ? 0 : 1
        });
        
        if (result.success) {
            this.showMessage(`Usuário ${user.active ? 'desativado' : 'ativado'} com sucesso!`, 'success');
            await this.loadUsers();
        } else {
            this.showMessage(result.message || 'Erro ao alterar status', 'error');
        }
    }
    
    async deleteUser(id) {
        const result = await this.apiRequest('users.php', 'DELETE', { id: id });
        if (result.success) {
            this.showMessage('Usuário excluído com sucesso!', 'success');
            await this.loadUsers();
        } else {
            this.showMessage(result.message || 'Erro ao excluir usuário', 'error');
        }
    }
    
    async createUser(username, password) {
        const result = await this.apiRequest('users.php', 'POST', { username, password });
        if (result.success) {
            this.showMessage(`Usuário "${username}" criado com sucesso!`, 'success');
            await this.loadUsers();
            return true;
        } else {
            this.showMessage(result.message || 'Erro ao criar usuário', 'error');
            return false;
        }
    }
    
    showAdminArea() {
        document.getElementById('loginAdminSection').style.display = 'none';
        document.getElementById('adminArea').style.display = 'block';
        document.getElementById('displayAdminUser').textContent = this.currentUser?.username || 'Admin';
    }
    
    showMessage(text, type) {
        const msg = document.getElementById('adminMessage');
        msg.textContent = text;
        msg.className = 'message ' + type;
        msg.style.display = 'block';
        setTimeout(() => { msg.style.display = 'none'; }, 4000);
    }
    
    openPasswordModal(user) {
        document.getElementById('editUsernameDisplay').textContent = user.username;
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editPassword').value = '';
        document.getElementById('editPasswordConfirm').value = '';
        document.getElementById('editModal').style.display = 'flex';
    }
    
    closePasswordModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    async changePassword(id, newPassword) {
        const result = await this.apiRequest('users.php', 'PUT', {
            id: id,
            password: newPassword
        });
        
        if (result.success) {
            this.showMessage('Senha alterada com sucesso!', 'success');
            this.closePasswordModal();
        } else {
            this.showMessage(result.message || 'Erro ao alterar senha', 'error');
        }
    }
    
    logout() {
        localStorage.removeItem('agenda_token');
        localStorage.removeItem('agenda_user');
        window.location.href = 'index.html';
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    const app = new AdminApp();
    
    // Event listeners
    document.getElementById('btnAddUser').addEventListener('click', async () => {
        const username = document.getElementById('newUsername').value.trim();
        const password = document.getElementById('newPassword').value.trim();
        
        if (!username || username.length < 3) {
            app.showMessage('Usuário deve ter pelo menos 3 caracteres', 'error');
            return;
        }
        if (!password || password.length < 3) {
            app.showMessage('Senha deve ter pelo menos 3 caracteres', 'error');
            return;
        }
        
        await app.createUser(username, password);
        document.getElementById('newUsername').value = '';
        document.getElementById('newPassword').value = '';
    });
    
    document.getElementById('adminLogoutBtn').addEventListener('click', () => app.logout());
    
    document.getElementById('editCancel').addEventListener('click', () => app.closePasswordModal());
    document.getElementById('editModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('editModal')) app.closePasswordModal();
    });
    
    document.getElementById('editSave').addEventListener('click', () => {
        const userId = parseInt(document.getElementById('editUserId').value);
        const password = document.getElementById('editPassword').value.trim();
        const confirm = document.getElementById('editPasswordConfirm').value.trim();
        
        if (!password || password.length < 3) {
            app.showMessage('Senha deve ter pelo menos 3 caracteres', 'error');
            return;
        }
        if (password !== confirm) {
            app.showMessage('As senhas não coincidem', 'error');
            return;
        }
        
        app.changePassword(userId, password);
    });
});