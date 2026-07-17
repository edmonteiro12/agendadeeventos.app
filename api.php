<?php
// backend/api/sync.php - CORRIGIDO

// Configurar CORS e headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$authenticated = authenticate();

if (!$authenticated) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
    exit();
}

$userId = $authenticated['id'] ?? null;
$username = $authenticated['username'] ?? null;
$isAdmin = ($authenticated['role'] ?? '') === 'admin';
$db = Database::getInstance();

// Se não tiver userId, tentar buscar pelo username
if (!$userId && $username) {
    $conn = $db->getConnection();
    if ($conn) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user) {
            $userId = $user['id'];
        }
    }
}

if (!$userId) {
    jsonResponse(['success' => false, 'message' => 'Usuário não identificado'], 401);
    exit();
}

switch ($method) {
    case 'GET':
        try {
            $conn = $db->getConnection();
            
            if (!$conn) {
                // Fallback para JSONBin
                $data = $db->getFallbackData();
                $userEvents = [];
                foreach ($data['events'] ?? [] as $event) {
                    if (isset($event['user_id']) && ($event['user_id'] == $userId || $isAdmin)) {
                        $userEvents[] = $event;
                    }
                }
                jsonResponse([
                    'success' => true,
                    'events' => $userEvents,
                    'users' => $isAdmin ? ($data['users'] ?? []) : [],
                    'hasChanges' => true,
                    'lastSync' => date('Y-m-d H:i:s'),
                    'message' => 'Dados carregados (fallback)'
                ]);
                exit();
            }
            
            // Buscar eventos do usuário
            $stmt = $conn->prepare("SELECT * FROM eventos WHERE user_id = ? ORDER BY data DESC");
            $stmt->execute([$userId]);
            $events = $stmt->fetchAll();
            
            // Formatar eventos para compatibilidade
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
                    'user_id' => $event['user_id'],
                    'updated_at' => $event['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            }, $events);
            
            $users = [];
            if ($isAdmin) {
                $stmt = $conn->prepare("SELECT id, username, nome, email, active, role FROM usuarios");
                $stmt->execute();
                $users = $stmt->fetchAll();
            }
            
            jsonResponse([
                'success' => true,
                'events' => $formattedEvents,
                'users' => $users,
                'hasChanges' => true,
                'lastSync' => date('Y-m-d H:i:s'),
                'message' => 'Dados carregados com sucesso'
            ]);
            
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Erro ao carregar: ' . $e->getMessage()], 500);
        }
        break;
        
    case 'POST':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
                exit();
            }
            
            $conn = $db->getConnection();
            
            if (!$conn) {
                // Fallback para JSONBin
                $fallbackData = $db->getFallbackData();
                
                // Atualizar eventos do usuário
                if (isset($data['events'])) {
                    $fallbackData['events'] = $data['events'];
                }
                if ($isAdmin && isset($data['users'])) {
                    $fallbackData['users'] = $data['users'];
                }
                $fallbackData['version'] = date('Y-m-d H:i:s');
                $fallbackData['lastSync'] = date('Y-m-d H:i:s');
                
                $db->setFallbackData($fallbackData);
                
                jsonResponse([
                    'success' => true,
                    'message' => '✅ Sincronização efetuada (fallback)',
                    'lastSync' => date('Y-m-d H:i:s')
                ]);
                exit();
            }
            
            $conn->beginTransaction();
            
            // Processar eventos
            if (isset($data['events'])) {
                foreach ($data['events'] as $event) {
                    // Remover eventos do usuário atual para evitar duplicação
                    if (!isset($event['id']) || $event['id'] <= 0) {
                        // Inserir novo evento
                        $stmt = $conn->prepare("
                            INSERT INTO eventos (user_id, data, hora_inicio, hora_fim, hora_sede, local_evento, empresa, valor, status_pagamento)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $userId,
                            $event['data'],
                            $event['horaInicio'] ?? $event['hora_inicio'] ?? '00:00:00',
                            $event['horaFim'] ?? $event['hora_fim'] ?? '00:00:00',
                            $event['horaSede'] ?? $event['hora_sede'] ?? '00:00:00',
                            $event['local'] ?? $event['local_evento'] ?? '',
                            $event['empresa'] ?? '',
                            $event['valor'] ?? 0,
                            $event['statusPagamento'] ?? 'Pendente'
                        ]);
                    } else {
                        // Verificar se o evento existe
                        $check = $conn->prepare("SELECT id FROM eventos WHERE id = ? AND user_id = ?");
                        $check->execute([$event['id'], $userId]);
                        
                        if ($check->fetch()) {
                            // Atualizar evento existente
                            $stmt = $conn->prepare("
                                UPDATE eventos 
                                SET data = ?, hora_inicio = ?, hora_fim = ?, hora_sede = ?,
                                    local_evento = ?, empresa = ?, valor = ?, status_pagamento = ?,
                                    updated_at = NOW()
                                WHERE id = ? AND user_id = ?
                            ");
                            $stmt->execute([
                                $event['data'],
                                $event['horaInicio'] ?? $event['hora_inicio'] ?? '00:00:00',
                                $event['horaFim'] ?? $event['hora_fim'] ?? '00:00:00',
                                $event['horaSede'] ?? $event['hora_sede'] ?? '00:00:00',
                                $event['local'] ?? $event['local_evento'] ?? '',
                                $event['empresa'] ?? '',
                                $event['valor'] ?? 0,
                                $event['statusPagamento'] ?? 'Pendente',
                                $event['id'],
                                $userId
                            ]);
                        }
                    }
                }
            }
            
            // Se admin, processar usuários
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
                        }
                    }
                }
            }
            
            $conn->commit();
            
            jsonResponse([
                'success' => true,
                'message' => '✅ Sincronização efetuada',
                'lastSync' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            if (isset($conn)) $conn->rollBack();
            jsonResponse(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Método não suportado'], 405);
}
?>