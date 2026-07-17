<?php
// backend/api/sync.php - Versão com Sincronização em Tempo Real

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$authenticated = authenticate();

if (!$authenticated) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
}

$userId = $authenticated['id'] ?? $authenticated['username'] ?? null;
$isAdmin = ($authenticated['role'] ?? '') === 'admin';
$db = Database::getInstance();

// Obter timestamp da última sincronização
$lastSync = isset($_GET['lastSync']) ? $_GET['lastSync'] : null;
$lastSync = $lastSync ? date('Y-m-d H:i:s', strtotime($lastSync)) : date('Y-m-d H:i:s', strtotime('-1 hour'));

switch ($method) {
    case 'GET':
        // Carregar dados com verificação de mudanças
        if ($db->isUsingFallback()) {
            $data = $db->getFallbackData();
            $userEvents = [];
            $hasChanges = false;
            
            foreach ($data['events'] ?? [] as $event) {
                if ($event['user_id'] == $userId || $isAdmin) {
                    // Verificar se o evento foi alterado após a última sincronização
                    if (isset($event['updated_at']) && strtotime($event['updated_at']) > strtotime($lastSync)) {
                        $hasChanges = true;
                    }
                    $userEvents[] = $event;
                }
            }
            
            jsonResponse([
                'success' => true,
                'events' => $userEvents,
                'users' => $isAdmin ? ($data['users'] ?? []) : [],
                'hasChanges' => $hasChanges,
                'lastSync' => date('Y-m-d H:i:s'),
                'version' => $data['version'] ?? date('Y-m-d H:i:s')
            ]);
        }
        
        try {
            $conn = $db->getConnection();
            
            // Buscar eventos com timestamp
            $stmt = $conn->prepare("
                SELECT *, 
                    UNIX_TIMESTAMP(updated_at) as updated_timestamp,
                    DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') as updated_at_formatted
                FROM eventos 
                WHERE user_id = ? 
                AND updated_at > ?
            ");
            $stmt->execute([$userId, $lastSync]);
            $changedEvents = $stmt->fetchAll();
            
            // Buscar todos os eventos do usuário
            $stmt = $conn->prepare("SELECT * FROM eventos WHERE user_id = ?");
            $stmt->execute([$userId]);
            $allEvents = $stmt->fetchAll();
            
            $users = [];
            if ($isAdmin) {
                $stmt = $conn->prepare("
                    SELECT id, username, nome, email, active, role, 
                           DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') as updated_at
                    FROM usuarios
                    WHERE updated_at > ?
                ");
                $stmt->execute([$lastSync]);
                $changedUsers = $stmt->fetchAll();
                
                $stmt = $conn->prepare("SELECT id, username, nome, email, active, role FROM usuarios");
                $stmt->execute();
                $allUsers = $stmt->fetchAll();
                
                $users = [
                    'all' => $allUsers,
                    'changed' => $changedUsers
                ];
            }
            
            jsonResponse([
                'success' => true,
                'events' => [
                    'all' => $allEvents,
                    'changed' => $changedEvents
                ],
                'users' => $users,
                'hasChanges' => count($changedEvents) > 0 || (isset($changedUsers) && count($changedUsers) > 0),
                'lastSync' => date('Y-m-d H:i:s'),
                'serverTime' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'POST':
        // Salvar dados e notificar mudanças
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($db->isUsingFallback()) {
            $fallbackData = $db->getFallbackData();
            $changes = false;
            
            if (isset($data['events'])) {
                $fallbackData['events'] = $data['events'];
                $changes = true;
            }
            if (isset($data['users']) && $isAdmin) {
                $fallbackData['users'] = $data['users'];
                $changes = true;
            }
            
            if ($changes) {
                $fallbackData['version'] = date('Y-m-d H:i:s');
                $fallbackData['lastSync'] = date('Y-m-d H:i:s');
                $db->setFallbackData($fallbackData);
            }
            
            jsonResponse([
                'success' => true, 
                'message' => 'Dados salvos',
                'lastSync' => date('Y-m-d H:i:s')
            ]);
        }
        
        try {
            $conn = $db->getConnection();
            $conn->beginTransaction();
            
            $hasChanges = false;
            
            if (isset($data['events'])) {
                foreach ($data['events'] as $event) {
                    if (isset($event['id']) && $event['id'] > 0) {
                        // Verificar se o evento pertence ao usuário
                        $check = $conn->prepare("SELECT id FROM eventos WHERE id = ? AND user_id = ?");
                        $check->execute([$event['id'], $userId]);
                        if ($check->fetch()) {
                            $stmt = $conn->prepare("
                                UPDATE eventos 
                                SET data = ?, hora_inicio = ?, hora_fim = ?, hora_sede = ?,
                                    local_evento = ?, empresa = ?, valor = ?, status_pagamento = ?,
                                    updated_at = NOW()
                                WHERE id = ? AND user_id = ?
                            ");
                            $stmt->execute([
                                $event['data'],
                                $event['horaInicio'] ?? $event['hora_inicio'],
                                $event['horaFim'] ?? $event['hora_fim'],
                                $event['horaSede'] ?? $event['hora_sede'],
                                $event['local'] ?? $event['local_evento'],
                                $event['empresa'],
                                $event['valor'] ?? 0,
                                $event['statusPagamento'] ?? 'Pendente',
                                $event['id'],
                                $userId
                            ]);
                            $hasChanges = true;
                        }
                    } else {
                        $stmt = $conn->prepare("
                            INSERT INTO eventos (user_id, data, hora_inicio, hora_fim, hora_sede, local_evento, empresa, valor, status_pagamento)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $userId,
                            $event['data'],
                            $event['horaInicio'] ?? $event['hora_inicio'],
                            $event['horaFim'] ?? $event['hora_fim'],
                            $event['horaSede'] ?? $event['hora_sede'],
                            $event['local'] ?? $event['local_evento'],
                            $event['empresa'],
                            $event['valor'] ?? 0,
                            $event['statusPagamento'] ?? 'Pendente'
                        ]);
                        $hasChanges = true;
                    }
                }
            }
            
            if ($isAdmin && isset($data['users'])) {
                foreach ($data['users'] as $user) {
                    if (isset($user['id']) && $user['id'] > 0) {
                        $updates = [];
                        $params = [];
                        foreach (['username', 'nome', 'email', 'active', 'role'] as $field) {
                            if (isset($user[$field])) {
                                $updates[] = "$field = ?";
                                $params[] = $user[$field];
                            }
                        }
                        if (isset($user['password']) && !empty($user['password'])) {
                            $updates[] = "password = ?";
                            $params[] = password_hash($user['password'], PASSWORD_DEFAULT);
                        }
                        if (!empty($updates)) {
                            $updates[] = "updated_at = NOW()";
                            $params[] = $user['id'];
                            $stmt = $conn->prepare("UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?");
                            $stmt->execute($params);
                            $hasChanges = true;
                        }
                    }
                }
            }
            
            // Registrar no log de sincronização
            if ($hasChanges) {
                $stmt = $conn->prepare("
                    INSERT INTO sync_log (table_name, operation, record_id, user_id)
                    VALUES ('eventos', 'sync', ?, ?)
                ");
                $stmt->execute([$userId, $userId]);
            }
            
            $conn->commit();
            
            jsonResponse([
                'success' => true, 
                'message' => 'Dados sincronizados',
                'hasChanges' => $hasChanges,
                'lastSync' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            if (isset($conn)) $conn->rollBack();
            jsonResponse(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'DELETE':
        // Deletar evento
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $eventId = $data['id'] ?? 0;
            
            if (!$eventId) {
                jsonResponse(['success' => false, 'message' => 'ID do evento é obrigatório'], 400);
            }
            
            $conn = $db->getConnection();
            $stmt = $conn->prepare("DELETE FROM eventos WHERE id = ? AND user_id = ?");
            $stmt->execute([$eventId, $userId]);
            
            jsonResponse([
                'success' => true, 
                'message' => 'Evento removido',
                'lastSync' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado'], 405);
}
?>