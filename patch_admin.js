const fs = require('fs');

let txt = fs.readFileSync('cadastrousuario.html', 'utf8');

const startCarregar = txt.indexOf('    async function carregarDadosCompletosNuvem()');
const startSalvar   = txt.indexOf('    async function salvarDadosCompletosNuvem()');

// Encontra fim de salvarDadosCompletosNuvem
let depth = 0, i = startSalvar, inFunc = false, endSalvar = -1;
while (i < txt.length) {
    if (txt[i] === '{') { depth++; inFunc = true; }
    if (txt[i] === '}') { depth--; if (inFunc && depth === 0) { endSalvar = i + 1; break; } }
    i++;
}

const novaCarregar = `    async function carregarDadosCompletosNuvem() {
        try {
            if (!window.SYNC_LOAD) return false;
            const record = await window.SYNC_LOAD();
            if (!record) return false;
            if (record.users && record.users.length > 0) {
                users = record.users;
                const adminExists = users.find(u => u.username === MASTER_ADMIN);
                if (!adminExists) users.push({ username: MASTER_ADMIN, password: ADMIN_PASS, active: true });
                salvarUsuariosLocal(users);
            }
            return true;
        } catch (error) {
            console.warn('Erro ao carregar dados da nuvem:', error);
            return false;
        }
    }`;

const novaSalvar = `    async function salvarDadosCompletosNuvem() {
        try {
            salvarUsuariosLocal(users);
            if (!window.SYNC_SAVE) return true;
            // Carrega dados atuais para não perder eventos
            let dadosAtuais = { events: [], nextId: 1 };
            if (window.SYNC_LOAD) {
                const atual = await window.SYNC_LOAD();
                if (atual) dadosAtuais = atual;
            }
            dadosAtuais.users    = users;
            dadosAtuais.version  = Date.now().toString();
            dadosAtuais.updatedAt = new Date().toISOString();
            return await window.SYNC_SAVE(dadosAtuais);
        } catch (error) {
            console.warn('Erro ao salvar dados na nuvem:', error);
            salvarUsuariosLocal(users);
            return false;
        }
    }`;

txt = txt.substring(0, startCarregar) + novaCarregar + '\n\n' + novaSalvar + '\n' + txt.substring(endSalvar);
fs.writeFileSync('cadastrousuario.html', txt, 'utf8');
console.log('cadastrousuario.html: OK');
