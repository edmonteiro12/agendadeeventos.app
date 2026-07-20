const fs = require('fs');

function patchFile(filename) {
    let txt = fs.readFileSync(filename, 'utf8');

    // ---- PATCH 1: carregarDadosCompletosNuvem ----
    // Encontra o bloco inteiro da função
    const startCarregar = txt.indexOf('    async function carregarDadosCompletosNuvem()');
    const startSalvar   = txt.indexOf('    async function salvarDadosCompletosNuvem()');
    
    if (startCarregar === -1 || startSalvar === -1) {
        console.log(filename + ': funções não encontradas');
        return;
    }

    // Encontra o fim de criarBinNuvem (próxima função após salvar)
    const startCriar = txt.indexOf('    async function criarBinNuvem(');
    let endCriar = -1;
    if (startCriar !== -1) {
        // Encontra o fechamento da função criarBinNuvem
        let depth = 0, i = startCriar;
        let inFunc = false;
        while (i < txt.length) {
            if (txt[i] === '{') { depth++; inFunc = true; }
            if (txt[i] === '}') { depth--; if (inFunc && depth === 0) { endCriar = i + 1; break; } }
            i++;
        }
    }

    const novaCarregar = `    async function carregarDadosCompletosNuvem() {
        try {
            if (!window.SYNC_LOAD) return false;
            const record = await window.SYNC_LOAD();
            if (!record) return false;
            if (record.events !== undefined) {
                events = record.events;
                nextId = record.nextId || events.length + 1;
                salvarEventosLocal();
            }
            if (record.users && record.users.length > 0) {
                users = record.users;
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
            salvarEventosLocal();
            salvarUsuariosLocal(users);
            if (!window.SYNC_SAVE) return true;
            const dados = {
                events   : events,
                users    : users,
                nextId   : nextId,
                version  : Date.now().toString(),
                updatedAt: new Date().toISOString()
            };
            return await window.SYNC_SAVE(dados);
        } catch (error) {
            console.warn('Erro ao salvar dados na nuvem:', error);
            return false;
        }
    }`;

    // Substitui do início de carregarDados até o fim de criarBinNuvem (ou fim de salvarDados)
    let endBlock;
    if (endCriar !== -1) {
        endBlock = endCriar;
    } else {
        // Encontra fim de salvarDadosCompletosNuvem
        let depth = 0, i = startSalvar, inFunc = false;
        while (i < txt.length) {
            if (txt[i] === '{') { depth++; inFunc = true; }
            if (txt[i] === '}') { depth--; if (inFunc && depth === 0) { endBlock = i + 1; break; } }
            i++;
        }
    }

    txt = txt.substring(0, startCarregar) + novaCarregar + '\n\n' + novaSalvar + '\n' + txt.substring(endBlock);

    fs.writeFileSync(filename, txt, 'utf8');
    console.log(filename + ': OK');
}

patchFile('index.html');
patchFile('cadastrousuario.html');
