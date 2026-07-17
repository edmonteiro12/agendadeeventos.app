-- database/schema.sql

CREATE DATABASE IF NOT EXISTS agenda_sistema;
USE agenda_sistema;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) NULL,
    nome VARCHAR(100),
    email VARCHAR(100),
    active TINYINT(1) DEFAULT 1,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de eventos
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
);

-- Tabela de sincronização (para controle de versão)
CREATE TABLE IF NOT EXISTS sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50),
    operation VARCHAR(20),
    record_id INT,
    user_id INT,
    sync_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserir usuário admin padrão
INSERT INTO usuarios (username, password, nome, role, active) 
VALUES ('edmonteiro', '$2y$10$YourHashedPasswordHere', 'Administrador', 'admin', 1);

-- Inserir usuário de exemplo
INSERT INTO usuarios (username, password, nome, role, active) 
VALUES ('demo', '$2y$10$YourHashedPasswordHere', 'Usuário Demo', 'user', 1);