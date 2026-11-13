DROP DATABASE IF EXISTS ferrorama_tr4;

CREATE DATABASE ferrorama_tr4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ferrorama_tr4;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    cargo ENUM('gestor', 'maquinista', 'cliente') NOT NULL DEFAULT 'cliente',
    nascimento DATE NULL,
    ativo_desde DATE NOT NULL,
    telefone VARCHAR(20) NULL
);

CREATE TABLE rotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(30) NOT NULL,
    origem VARCHAR(80) NOT NULL,
    destino VARCHAR(80) NOT NULL,
    passageiros INT NOT NULL DEFAULT 0,
    quilometragem INT NOT NULL DEFAULT 0,
    alerta VARCHAR(255) NULL
);

CREATE TABLE horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rota_id INT NOT NULL,
    horario CHAR(5) NOT NULL,
    FOREIGN KEY (rota_id) REFERENCES rotas (id) ON DELETE CASCADE
);

CREATE TABLE linhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rota_id INT NOT NULL,
    nome VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL,
    FOREIGN KEY (rota_id) REFERENCES rotas (id) ON DELETE CASCADE
);

CREATE TABLE trens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo CHAR(3) NOT NULL UNIQUE,
    nome VARCHAR(30) NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    ano_fabricacao YEAR NOT NULL,
    capacidade_toneladas DECIMAL(8,2) NOT NULL,
    status ENUM('em_rota', 'disponivel', 'manutencao') NOT NULL DEFAULT 'disponivel',
    rota_id INT NULL,
    consumo DECIMAL(5,2) NOT NULL,
    faturamento DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (rota_id) REFERENCES rotas (id) ON DELETE SET NULL
);

CREATE TABLE funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cargo VARCHAR(60) NOT NULL,
    ativo_desde DATE NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    telefone VARCHAR(20) NULL,
    cpf VARCHAR(14) NULL UNIQUE,
    nascimento DATE NULL,
    endereco VARCHAR(200) NULL
);

CREATE TABLE cargas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(60) NOT NULL,
    toneladas INT NOT NULL,
    destino VARCHAR(80) NOT NULL,
    partida DATE NOT NULL,
    status ENUM('aguardando', 'em_transito', 'entregue') NOT NULL DEFAULT 'aguardando',
    progresso INT NOT NULL DEFAULT 0
);

CREATE TABLE sensores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(40) NOT NULL,
    grandeza VARCHAR(60) NOT NULL,
    trem_id INT NOT NULL,
    status ENUM('ativo', 'instalacao', 'remocao') NOT NULL DEFAULT 'instalacao',
    prazo DATE NULL,
    FOREIGN KEY (trem_id) REFERENCES trens (id) ON DELETE CASCADE
);

CREATE TABLE leituras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trem_id INT NOT NULL,
    data_hora DATETIME NOT NULL,
    velocidade_kmh DECIMAL(6,2) NOT NULL,
    temperatura_motor_c DECIMAL(6,2) NOT NULL,
    consumo_litros_hora DECIMAL(6,2) NOT NULL,
    vibracao_mm_s DECIMAL(6,2) NOT NULL,
    FOREIGN KEY (trem_id) REFERENCES trens (id) ON DELETE CASCADE,
    INDEX (data_hora)
);

CREATE TABLE manutencoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trem_id INT NOT NULL,
    rota_id INT NOT NULL,
    carga VARCHAR(60) NOT NULL,
    causa VARCHAR(200) NOT NULL,
    data DATE NOT NULL,
    FOREIGN KEY (trem_id) REFERENCES trens (id),
    FOREIGN KEY (rota_id) REFERENCES rotas (id)
);

CREATE TABLE alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensagem VARCHAR(255) NOT NULL,
    tipo ENUM('info', 'aviso', 'atraso') NOT NULL DEFAULT 'info',
    data DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO usuarios (nome, email, senha, cargo, nascimento, ativo_desde, telefone) VALUES
    ('Gestor Demonstração', 'gestor@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'gestor', '1990-03-14', '2019-02-02', '(47) 99999-1010'),
    ('Maquinista Demonstração', 'maquinista@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'maquinista', '1994-07-22', '2021-08-10', '(47) 99999-2020'),
    ('Cliente Demonstração', 'cliente@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'cliente', '1998-11-05', '2024-01-18', '(47) 99999-3030');

INSERT INTO rotas (id, nome, origem, destino, passageiros, quilometragem, alerta) VALUES
    (1, 'Rota 2', 'Joinville', 'São Paulo', 860, 3120, NULL),
    (2, 'Rota 14', 'Curitiba', 'Santos', 1040, 4210, 'O trem da linha 306 está em manutenção devido a alguns problemas técnicos.'),
    (3, 'Rota 31', 'Joinville', 'Curitiba', 540, 1980, 'Chuva forte na serra: velocidade reduzida entre os km 40 e 62.'),
    (4, 'Rota 36', 'São Paulo', 'Belo Horizonte', 1200, 5069, NULL),
    (5, 'Rota 37', 'Santos', 'São Paulo', 930, 2875, NULL),
    (6, 'Rota 38', 'Rio de Janeiro', 'São Paulo', 1530, 6340, 'A Linha 2 está com atraso médio de 20 minutos.');

INSERT INTO horarios (rota_id, horario) VALUES
    (1, '05:00'), (1, '08:30'), (1, '12:00'), (1, '15:30'), (1, '19:00'), (1, '22:30'),
    (2, '00:00'), (2, '04:30'), (2, '06:00'), (2, '07:30'), (2, '13:30'), (2, '15:00'), (2, '17:00'), (2, '20:30'),
    (3, '06:15'), (3, '09:45'), (3, '13:15'), (3, '18:45'),
    (4, '04:00'), (4, '10:00'), (4, '16:00'), (4, '22:00'),
    (5, '05:30'), (5, '07:00'), (5, '11:30'), (5, '17:30'), (5, '21:00'),
    (6, '06:00'), (6, '12:00'), (6, '18:00'), (6, '23:45');

INSERT INTO linhas (rota_id, nome, status) VALUES
    (1, 'Linha 1', 'Em espera'), (1, 'Linha 2', 'Já partiu'),
    (2, 'Linha 1', 'Já partiu'), (2, 'Linha 2', 'Em espera'),
    (3, 'Linha 1', 'Atrasado'), (3, 'Linha 2', 'Em espera'),
    (4, 'Linha 1', 'Já partiu'), (4, 'Linha 2', 'Já partiu'),
    (5, 'Linha 1', 'Em espera'), (5, 'Linha 2', 'Em espera'),
    (6, 'Linha 1', 'Já partiu'), (6, 'Linha 2', 'Atrasado');

INSERT INTO trens (codigo, nome, modelo, ano_fabricacao, capacidade_toneladas, status, rota_id, consumo, faturamento) VALUES
    ('008', 'Trem 08', 'GE AC44i', 2014, 6200.00, 'em_rota', 4, 14, 1300000),
    ('009', 'Trem 09', 'EMD SD70ACe', 2011, 5800.50, 'em_rota', 2, 12, 980000),
    ('010', 'Trem 10', 'GE Dash 9-40BBW', 2006, 5400.00, 'disponivel', NULL, 15, 1120000),
    ('011', 'Trem 11', 'GE ES44AC', 2016, 6000.00, 'em_rota', 1, 13, 870000),
    ('012', 'Trem 12', 'EMD SD40-2', 1999, 5100.75, 'disponivel', NULL, 11, 640000),
    ('013', 'Trem 13', 'GE AC44i', 2018, 6200.00, 'em_rota', 6, 14, 1450000),
    ('014', 'Trem 14', 'EMD GT46AC', 2009, 5600.00, 'em_rota', 5, 13, 910000);

INSERT INTO funcionarios (nome, cargo, ativo_desde, email, telefone, cpf, nascimento, endereco) VALUES
    ('Ana Oliveira', 'Gestora de tráfego', '2018-03-12', 'ana.oliveira@tr4.com', '(47) 98811-2201', '529.982.247-25', '1987-04-08', 'Rua XV de Novembro, 120 - Joinville/SC'),
    ('Antonio Pereira', 'Maquinista', '2015-07-01', 'antonio.pereira@tr4.com', '(47) 98811-2202', NULL, '1979-09-19', NULL),
    ('Carlos Silva', 'Maquinista', '2020-01-15', 'carlos.silva@tr4.com', '(41) 98811-2203', NULL, '1991-12-02', NULL),
    ('Francisco Ferreira', 'Técnico de manutenção', '2019-05-20', 'francisco.ferreira@tr4.com', '(41) 98811-2204', NULL, '1985-01-30', NULL),
    ('Luiz Carvalho', 'Operador de sensores', '2022-02-03', 'luiz.carvalho@tr4.com', '(47) 98811-2205', NULL, '1996-06-11', NULL),
    ('Maria Silva', 'Analista de cargas', '2017-10-09', 'maria.silva@tr4.com', '(11) 98811-2206', NULL, '1989-08-25', NULL),
    ('Paulo Alves', 'Maquinista', '2016-04-14', 'paulo.alves@tr4.com', '(13) 98811-2207', NULL, '1982-03-17', NULL);

INSERT INTO cargas (tipo, toneladas, destino, partida, status, progresso) VALUES
    ('Carvão', 2, 'São Paulo', CURDATE() - INTERVAL 2 DAY, 'em_transito', 62),
    ('Soja', 50, 'Santos', CURDATE() - INTERVAL 1 DAY, 'em_transito', 28),
    ('Sacos de milho', 10, 'Curitiba', CURDATE() + INTERVAL 3 DAY, 'aguardando', 0),
    ('Minério de ferro', 100, 'Rio de Janeiro', CURDATE() - INTERVAL 6 DAY, 'entregue', 100);

INSERT INTO sensores (tipo, grandeza, trem_id, status, prazo) VALUES
    ('DS18B20', 'Temperatura do motor', 1, 'ativo', NULL),
    ('ADXL345', 'Vibração', 1, 'ativo', NULL),
    ('A3144', 'Velocidade (sensor Hall na roda)', 1, 'ativo', NULL),
    ('Omnicomm LLS 5', 'Nível e consumo de combustível', 1, 'ativo', NULL),
    ('DS18B20', 'Temperatura do motor', 2, 'ativo', NULL),
    ('MPU-6050', 'Vibração e inclinação', 2, 'ativo', NULL),
    ('NEO-6M', 'Localização e velocidade (GPS)', 2, 'ativo', NULL),
    ('MPX5700AP', 'Pressão do freio', 4, 'ativo', NULL);

INSERT INTO leituras (trem_id, data_hora, velocidade_kmh, temperatura_motor_c, consumo_litros_hora, vibracao_mm_s) VALUES
    (1, NOW() - INTERVAL 25 MINUTE, 62.40, 88.10, 48.30, 3.20),
    (1, NOW() - INTERVAL 20 MINUTE, 71.80, 92.50, 55.10, 4.10),
    (1, NOW() - INTERVAL 15 MINUTE, 84.30, 101.20, 61.70, 5.60),
    (1, NOW() - INTERVAL 10 MINUTE, 76.90, 108.40, 66.20, 7.90),
    (2, NOW() - INTERVAL 20 MINUTE, 55.20, 85.60, 42.80, 2.70),
    (2, NOW() - INTERVAL 10 MINUTE, 68.70, 90.30, 51.40, 3.80);

INSERT INTO alertas (mensagem, tipo, data) VALUES
    ('Seu trem está a caminho!', 'info', NOW()),
    ('Alerta de chuva na Rota 31!', 'aviso', NOW() - INTERVAL 1 HOUR),
    ('A Linha 02 está em atraso.', 'atraso', NOW() - INTERVAL 1 DAY);
