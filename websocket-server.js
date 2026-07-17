// websocket-server.js - Servidor WebSocket para sincronização em tempo real

const WebSocket = require('ws');
const http = require('http');
const mysql = require('mysql2');

// Configuração do banco de dados
const db = mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'agenda_sistema'
});

// Criar servidor HTTP
const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('WebSocket Server Running');
});

// Criar servidor WebSocket
const wss = new WebSocket.Server({ server });

// Clientes conectados
const clients = new Map();

wss.on('connection', (ws, req) => {
    console.log('🔌 Novo cliente conectado');
    let userId = null;
    
    ws.on('message', async (message) => {
        try {
            const data = JSON.parse(message);
            
            if (data.type === 'auth') {
                // Autenticar cliente
                userId = data.userId;
                clients.set(ws, userId);
                console.log(`✅ Cliente ${userId} autenticado`);
                
                // Enviar dados iniciais
                ws.send(JSON.stringify({
                    type: 'init',
                    data: await getInitialData(userId)
                }));
            }
            
            if (data.type === 'update') {
                // Cliente atualizou dados - notificar todos
                console.log(`📤 Atualização recebida de ${userId}`);
                
                // Salvar no banco de dados
                await saveData(userId, data.data);
                
                // Notificar todos os clientes conectados
                broadcast({
                    type: 'sync',
                    data: data.data,
                    from: userId,
                    timestamp: new Date().toISOString()
                });
            }
            
        } catch (error) {
            console.error('Erro no WebSocket:', error);
        }
    });
    
    ws.on('close', () => {
        console.log(`🔌 Cliente ${userId || 'desconhecido'} desconectado`);
        clients.delete(ws);
    });
});

// Broadcast para todos os clientes
function broadcast(data) {
    clients.forEach((clientUserId, ws) => {
        if (ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify(data));
        }
    });
}

// Funções do banco de dados
async function getInitialData(userId) {
    return new Promise((resolve, reject) => {
        db.query('SELECT * FROM eventos WHERE user_id = ?', [userId], (err, events) => {
            if (err) reject(err);
            resolve({ events });
        });
    });
}

async function saveData(userId, data) {
    return new Promise((resolve, reject) => {
        // Implementar lógica de salvamento
        resolve();
    });
}

// Iniciar servidor
const PORT = 8080;
server.listen(PORT, () => {
    console.log(`🚀 WebSocket Server rodando na porta ${PORT}`);
});