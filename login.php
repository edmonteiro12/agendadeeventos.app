<?php
// backend/api/login.php - CORRIGIDO E FUNCIONAL

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username']) || !isset($data['password'])) {
    jsonResponse(['success' => false, 'message' => 'Usuário e senha são obrigatórios'], 400);
    exit();
}

$username = trim($data['username']);
$password = trim($data['password']);

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // ==========================================
    // FALLBACK: Se MySQL não estiver disponível
    // ==========================================
    if (!$conn) {
        $fallbackData = $db->getFallbackData();
        $users = $fallbackData['users'] ?? [];
        $foundUser = null;
        
        foreach ($users as $user) {
            if ($user['username'] === $username && $user['password'] === $password && $user['active'] !== false) {
                $foundUser = $user;
                break;
            }
        }
        
        if ($foundUser) {
            $token = generateToken($foundUser['id'] ?? 1, $username);
            
            jsonResponse([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $foundUser['id'] ?? 1,
                    'username' => $foundUser['username'],
                    'nome' => $foundUser['nome'] ?? $username,
                    'role' => $foundUser['role'] ?? 'user'
                ],
                'events' => $fallbackData['events'] ?? [],
                'users' => $users,
                'nextId' => count($fallbackData['events'] ?? []) + 1
            ]);
            exit();
        }
        
        jsonResponse(['success' => false, 'message' => 'Usuário ou senha inválidos'], 401);
        exit();
    }
    
    // ==========================================
    // MYSQL: Buscar usuário
    // ==========================================
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE username = ? AND active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Usuário não encontrado ou inativo'], 401);
        exit();
    }
    
    // ==========================================
    // VERIFICAR SENHA
    // ==========================================
    $passwordValid = false;
    
    // 1. Verificar com password_hash (recomendado)
    if (password_verify($password, $user['password'])) {
        $passwordValid = true;
    }
    // 2. Verificar senha em texto plano (compatibilidade)
    elseif ($password === $user['password']) {
        $passwordValid = true;
        // Atualizar para hash (melhorar segurança)
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $user['id']]);
    }
    
    if (!$passwordValid) {
        jsonResponse(['success' => false, 'message' => 'Senha incorreta'], 401);
        exit();
    }
    
    // ==========================================
    // GERAR TOKEN
    // ==========================================
    $token = generateToken($user['id'], $user['username']);
    
    $stmt = $conn->prepare("UPDATE usuarios SET token = ? WHERE id = ?");
    $stmt->execute([$token, $user['id']]);
    
    // ==========================================
    // BUSCAR EVENTOS DO USUÁRIO
    // ==========================================
    $stmt = $conn->prepare("SELECT * FROM eventos WHERE user_id = ? ORDER BY data DESC");
    $stmt->execute([$user['id']]);
    $events = $stmt->fetchAll();
    
    $formattedEvents = array_map(function($event) {
        return [
            'id' => $event['id'],
            'data' => $event['data'],
            'horaInicio' => $event['hora_inicio'],
            'horaFim' => $event['hora_fim'],
            'horaSede' => $event['hora_sede'],
            'local' => $event['local_evento'],
            'empresa' => $event['empresa'],
            'valor' => floatval($event['valor']),
            'statusPagamento' => $event['status_pagamento'],
            'user_id' => $event['user_id']
        ];
    }, $events);
    
    // ==========================================
    // BUSCAR USUÁRIOS (SE ADMIN)
    // ==========================================
    $users = [];
    if ($user['role'] === 'admin') {
        $stmt = $conn->prepare("SELECT id, username, nome, email, active, role FROM usuarios");
        $stmt->execute();
        $users = $stmt->fetchAll();
    }
    
    // ==========================================
    // RESPOSTA
    // ==========================================
    jsonResponse([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'nome' => $user['nome'],
            'role' => $user['role']
        ],
        'events' => $formattedEvents,
        'users' => $users,
        'nextId' => count($formattedEvents) + 1,
        'message' => 'Login realizado com sucesso'
    ]);
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()], 500);
}
?>