<?php
// backend/api/users.php

require_once '../config/database.php';

$authenticated = authenticate();

if (!$authenticated || $authenticated['role'] !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Acesso negado'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

switch ($method) {
    case 'GET':
        try {
            $stmt = $db->prepare("SELECT id, username, nome, email, active, role FROM usuarios");
            $stmt->execute();
            $users = $stmt->fetchAll();
            jsonResponse(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Erro ao buscar usuários: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'POST':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $username = trim($data['username'] ?? '');
            $cpf = preg_replace('/\D/', '', $data['cpf'] ?? '');
            $password = $data['password'] ?? '';

            if ($username === '' || strlen($username) < 3) {
                jsonResponse(['success' => false, 'message' => 'Usuário deve ter pelo menos 3 caracteres'], 400);
            }

            if ($password === '' || strlen($password) < 3) {
                jsonResponse(['success' => false, 'message' => 'Senha deve ter pelo menos 3 caracteres'], 400);
            }

            if ($cpf !== '' && strlen($cpf) !== 11) {
                jsonResponse(['success' => false, 'message' => 'CPF deve conter 11 dígitos'], 400);
            }

            $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ? OR (cpf <> '' AND cpf = ?)");
            $stmt->execute([$username, $cpf]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => $cpf !== '' ? 'CPF ou usuário já cadastrado' : 'Usuário já existe'], 409);
            }
            
            $stmt = $db->prepare("
                INSERT INTO usuarios (username, password, cpf, nome, email, role, active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $cpf,
                $data['nome'] ?? $username,
                $data['email'] ?? '',
                $data['role'] ?? 'user',
                $data['active'] ?? 1
            ]);
            
            jsonResponse(['success' => true, 'message' => 'Usuário criado com sucesso', 'id' => $db->lastInsertId()]);
            
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Erro ao criar usuário: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'PUT':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['id'] ?? 0;
            
            if (!$userId) {
                jsonResponse(['success' => false, 'message' => 'ID do usuário é obrigatório'], 400);
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['username'])) {
                $updates[] = "username = ?";
                $params[] = $data['username'];
            }
            if (isset($data['nome'])) {
                $updates[] = "nome = ?";
                $params[] = $data['nome'];
            }
            if (isset($data['email'])) {
                $updates[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['active'])) {
                $updates[] = "active = ?";
                $params[] = $data['active'];
            }
            if (isset($data['role'])) {
                $updates[] = "role = ?";
                $params[] = $data['role'];
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $updates[] = "password = ?";
                $params[] = $data['password'];
            }
            
            if (empty($updates)) {
                jsonResponse(['success' => false, 'message' => 'Nenhum campo para atualizar'], 400);
            }
            
            $params[] = $userId;
            $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            jsonResponse(['success' => true, 'message' => 'Usuário atualizado com sucesso']);
            
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Erro ao atualizar usuário: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'DELETE':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['id'] ?? 0;
            
            if (!$userId) {
                jsonResponse(['success' => false, 'message' => 'ID do usuário é obrigatório'], 400);
            }
            
            // Verificar se é o admin master
            $stmt = $db->prepare("SELECT role FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user && $user['role'] === 'admin') {
                jsonResponse(['success' => false, 'message' => 'Não é possível excluir o administrador master'], 403);
            }
            
            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            
            jsonResponse(['success' => true, 'message' => 'Usuário excluído com sucesso']);
            
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Erro ao excluir usuário: ' . $e->getMessage()], 500);
        }
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado'], 405);
}
?>