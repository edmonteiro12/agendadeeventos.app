<?php
// backend/config/database.php

// Detectar ambiente automaticamente
$isLocal = $_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1';
$isHostinger = strpos($_SERVER['SERVER_NAME'], 'hostinger') !== false || strpos($_SERVER['DOCUMENT_ROOT'], 'hostinger') !== false;

// Configurações por ambiente
if ($isLocal) {
    // Ambiente Local
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'agenda_sistema');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
} elseif ($isHostinger) {
    // Ambiente Hostinger
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u123456789_agenda'); // Substitua pelo nome do banco
    define('DB_USER', 'u123456789_admin'); // Substitua pelo usuário
    define('DB_PASS', 'sua_senha_aqui'); // Substitua pela senha
    define('DB_CHARSET', 'utf8mb4');
} else {
    // Ambiente Genérico - usar variáveis de ambiente
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'agenda_sistema');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_CHARSET', 'utf8mb4');
}

// Configuração JSONBin (fallback se MySQL não funcionar)
define('USE_JSONBIN_FALLBACK', true);
define('JSONBIN_KEY', '$2a$10$qV3YqXZkQh9CmKhCpt7V9O7V9O7V9O7V9O7V9O'); // Sua chave
define('JSONBIN_BIN_ID', 'seu_bin_id_aqui');

// Configuração CORS para multi-domínios
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:5500',
    'http://127.0.0.1:5500',
    'https://seu-dominio.com',
    'https://www.seu-dominio.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins) || strpos($origin, 'localhost') !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

class Database {
    private static $instance = null;
    private $conn;
    private $useFallback = false;
    private $fallbackData = [];
    
    private function __construct() {
        $this->useFallback = !$this->initMySQL();
        
        if ($this->useFallback && USE_JSONBIN_FALLBACK) {
            $this->initJSONBinFallback();
        }
    }
    
    private function initMySQL() {
        try {
            // Tentar conectar com diferentes configurações
            $hosts = [DB_HOST, '127.0.0.1', 'localhost'];
            $connected = false;
            
            foreach ($hosts as $host) {
                try {
                    $this->conn = new PDO(
                        "mysql:host={$host};dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                        DB_USER,
                        DB_PASS,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false,
                            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
                        ]
                    );
                    $connected = true;
                    break;
                } catch (PDOException $e) {
                    continue;
                }
            }
            
            if ($connected) {
                $this->createTablesIfNeeded();
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log('MySQL Connection Error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function createTablesIfNeeded() {
        try {
            // Criar tabela de usuários
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS usuarios (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    cpf VARCHAR(14) NULL,
                    nome VARCHAR(100),
                    email VARCHAR(100),
                    token VARCHAR(255),
                    active TINYINT(1) DEFAULT 1,
                    role ENUM('admin', 'user') DEFAULT 'user',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_username (username),
                    INDEX idx_cpf (cpf),
                    INDEX idx_token (token)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            try {
                $this->conn->exec("ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(14) NULL");
                $this->conn->exec("CREATE INDEX IF NOT EXISTS idx_cpf ON usuarios (cpf)");
            } catch (Exception $e) {
            }
            
            // Criar tabela de eventos
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS eventos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    data DATE NOT NULL,
                    hora_inicio TIME NOT NULL,
                    hora_fim TIME NOT NULL,
                    hora_sede TIME NOT NULL,
                    local_evento VARCHAR(255) NOT NULL,
                    empresa VARCHAR(255) NOT NULL,
                    valor DECIMAL(10,2) DEFAULT 0.00,
                    status_pagamento ENUM('Pago', 'Pendente') DEFAULT 'Pendente',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                    INDEX idx_data (data),
                    INDEX idx_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Criar tabela de sync log
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS sync_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    table_name VARCHAR(50),
                    operation VARCHAR(20),
                    record_id INT,
                    user_id INT,
                    sync_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Verificar se admin existe
            $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE username = 'admin'");
            $stmt->execute();
            if (!$stmt->fetch()) {
                $this->conn->prepare("
                    INSERT INTO usuarios (username, password, nome, role, active)
                    VALUES ('admin', ?, 'Administrador', 'admin', 1)
                ")->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
            }
            
            // Verificar se edmonteiro existe
            $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE username = 'edmonteiro'");
            $stmt->execute();
            if (!$stmt->fetch()) {
                $this->conn->prepare("
                    INSERT INTO usuarios (username, password, nome, role, active)
                    VALUES ('edmonteiro', ?, 'Edmonteiro', 'admin', 1)
                ")->execute([password_hash('155145', PASSWORD_DEFAULT)]);
            }
            
        } catch (Exception $e) {
            error_log('Table creation error: ' . $e->getMessage());
        }
    }
    
    private function initJSONBinFallback() {
        $this->fallbackData = $this->loadFromJSONBin();
    }
    
    private function loadFromJSONBin() {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.jsonbin.io/v3/b/" . JSONBIN_BIN_ID . "/latest");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Master-Key: ' . JSONBIN_KEY,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if ($data && isset($data['record'])) {
                    return $data['record'];
                }
            }
        } catch (Exception $e) {
            error_log('JSONBin load error: ' . $e->getMessage());
        }
        return ['events' => [], 'users' => [], 'nextId' => 1];
    }
    
    public function saveToJSONBin($data) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.jsonbin.io/v3/b/" . JSONBIN_BIN_ID);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Master-Key: ' . JSONBIN_KEY,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200 || $httpCode === 201;
        } catch (Exception $e) {
            error_log('JSONBin save error: ' . $e->getMessage());
            return false;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function isUsingFallback() {
        return $this->useFallback;
    }
    
    public function getFallbackData() {
        return $this->fallbackData;
    }
    
    public function setFallbackData($data) {
        $this->fallbackData = $data;
        $this->saveToJSONBin($data);
    }
}

// Funções auxiliares
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)/', $authHeader, $matches)) {
        return false;
    }
    
    $token = $matches[1];
    $db = Database::getInstance();
    
    // Se estiver usando fallback, verificar no JSON
    if ($db->isUsingFallback()) {
        $users = $db->getFallbackData()['users'] ?? [];
        foreach ($users as $user) {
            if (isset($user['token']) && $user['token'] === $token && $user['active'] !== false) {
                return $user;
            }
        }
        return false;
    }
    
    try {
        $stmt = $db->getConnection()->prepare("SELECT * FROM usuarios WHERE token = ? AND active = 1");
        $stmt->execute([$token]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function generateToken($userId, $username) {
    return base64_encode($userId . '|' . $username . '|' . time() . '|' . bin2hex(random_bytes(8)));
}

// Verificar se o arquivo é chamado diretamente
if (basename($_SERVER['PHP_SELF']) == 'database.php') {
    echo json_encode(['status' => 'OK', 'message' => 'Database config loaded']);
    exit;
}
?>