// jobin.js — Sincronização via API Serverless (/api/dados)
// Os dados ficam no GitHub (db.json) e são compartilhados entre TODOS os navegadores/dispositivos.

(function () {
    var API_URL = '/api/dados';

    window.SYNC_CONFIG = {
        useJsonBin  : false,
        useFirebase : false,
        useApi      : true,
        binId       : '',
        jsonBinKey  : ''
    };

    window.SYNC_INIT = async function () { return true; };

    window.SYNC_LOAD = async function () {
        try {
            var resp = await fetch(API_URL, { cache: 'no-store' });
            if (resp.ok) return await resp.json();
        } catch (e) {
            console.warn('⚠️ Erro ao carregar dados:', e);
        }
        return null;
    };

    window.SYNC_SAVE = async function (dados) {
        try {
            var resp = await fetch(API_URL, {
                method  : 'POST',
                headers : { 'Content-Type': 'application/json' },
                body    : JSON.stringify(dados)
            });
            return resp.ok;
        } catch (e) {
            console.warn('⚠️ Erro ao salvar dados:', e);
            return false;
        }
    };
})();
