-- =====================================================================
-- Ferrorama TR4 - banco de dados completo (versão 2)
-- Compatível com MySQL 8 e MariaDB 10.4+ (XAMPP).
-- ATENÇÃO: este script apaga e recria o banco ferrorama_tr4.
-- Para atualizar um banco antigo sem perder dados, use migracao-v2.sql.
-- =====================================================================

DROP DATABASE IF EXISTS ferrorama_tr4;

CREATE DATABASE ferrorama_tr4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ferrorama_tr4;

SET NAMES utf8mb4;

-- Mesmo fuso horário usado pelo PHP (America/Sao_Paulo), para as datas relativas a NOW() ficarem corretas.
SET time_zone = '-03:00';

-- ---------------------------------------------------------------------
-- Rotas e horários
-- ---------------------------------------------------------------------
CREATE TABLE rotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(30) NOT NULL UNIQUE,
    origem VARCHAR(80) NOT NULL,
    destino VARCHAR(80) NOT NULL,
    distancia_km INT NOT NULL DEFAULT 0,
    passageiros INT NOT NULL DEFAULT 0,
    quilometragem INT NOT NULL DEFAULT 0,
    alerta VARCHAR(255) NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rota_id INT NOT NULL,
    horario CHAR(5) NOT NULL,
    FOREIGN KEY (rota_id) REFERENCES rotas (id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Trens
-- ---------------------------------------------------------------------
CREATE TABLE trens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo CHAR(3) NOT NULL UNIQUE,
    nome VARCHAR(30) NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    ano_fabricacao YEAR NOT NULL,
    capacidade_toneladas DECIMAL(8,2) NOT NULL,
    status ENUM('em_rota', 'disponivel', 'manutencao', 'inativo') NOT NULL DEFAULT 'disponivel',
    rota_id INT NULL,
    consumo DECIMAL(5,2) NOT NULL COMMENT 'Consumo nominal em km/L',
    faturamento DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (rota_id) REFERENCES rotas (id) ON DELETE SET NULL,
    INDEX idx_trens_status (status)
);

-- ---------------------------------------------------------------------
-- Usuários (login, perfil e dados do funcionário)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    cargo ENUM('gestor', 'maquinista', 'cliente') NOT NULL DEFAULT 'cliente',
    funcao VARCHAR(60) NULL,
    trem_id INT NULL COMMENT 'Trem conduzido pelo maquinista',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    trocar_senha TINYINT(1) NOT NULL DEFAULT 0,
    pedido_senha_em DATETIME NULL,
    nascimento DATE NULL,
    ativo_desde DATE NOT NULL,
    telefone VARCHAR(20) NULL,
    cpf VARCHAR(14) NULL UNIQUE,
    endereco VARCHAR(200) NULL,
    FOREIGN KEY (trem_id) REFERENCES trens (id) ON DELETE SET NULL,
    INDEX idx_usuarios_cargo (cargo, ativo)
);

-- ---------------------------------------------------------------------
-- Viagens (relaciona rota, trem e maquinista no tempo)
-- ---------------------------------------------------------------------
CREATE TABLE viagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rota_id INT NOT NULL,
    trem_id INT NOT NULL,
    maquinista_id INT NULL,
    partida_prevista DATETIME NOT NULL,
    chegada_prevista DATETIME NOT NULL,
    partida_real DATETIME NULL,
    chegada_real DATETIME NULL,
    status ENUM('agendada', 'em_andamento', 'concluida', 'atrasada', 'cancelada') NOT NULL DEFAULT 'agendada',
    km_percorridos INT NOT NULL DEFAULT 0,
    observacao VARCHAR(255) NULL,
    FOREIGN KEY (rota_id) REFERENCES rotas (id),
    FOREIGN KEY (trem_id) REFERENCES trens (id),
    FOREIGN KEY (maquinista_id) REFERENCES usuarios (id) ON DELETE SET NULL,
    INDEX idx_viagens_partida (partida_prevista),
    INDEX idx_viagens_status (status),
    INDEX idx_viagens_trem (trem_id, partida_prevista)
);

-- ---------------------------------------------------------------------
-- Cargas
-- ---------------------------------------------------------------------
CREATE TABLE cargas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(60) NOT NULL,
    toneladas INT NOT NULL,
    destino VARCHAR(80) NOT NULL,
    partida DATE NOT NULL,
    status ENUM('aguardando', 'em_transito', 'entregue') NOT NULL DEFAULT 'aguardando',
    progresso INT NOT NULL DEFAULT 0
);

-- ---------------------------------------------------------------------
-- Sensores e leituras (cada leitura pertence a um sensor e a um trem)
-- ---------------------------------------------------------------------
CREATE TABLE sensores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(40) NOT NULL,
    grandeza VARCHAR(60) NOT NULL,
    chave VARCHAR(30) NOT NULL COMMENT 'Grandeza usada nos limites (includes/limites.php)',
    unidade VARCHAR(10) NOT NULL,
    trem_id INT NOT NULL,
    status ENUM('ativo', 'instalacao', 'remocao', 'inativo') NOT NULL DEFAULT 'instalacao',
    prazo DATE NULL,
    instalado_em DATE NULL,
    observacao VARCHAR(200) NULL,
    FOREIGN KEY (trem_id) REFERENCES trens (id) ON DELETE CASCADE,
    INDEX idx_sensores_trem (trem_id, status)
);

CREATE TABLE leituras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sensor_id INT NOT NULL,
    trem_id INT NOT NULL,
    data_hora DATETIME NOT NULL,
    valor DECIMAL(8,2) NOT NULL,
    nivel ENUM('normal', 'atencao', 'critico') NOT NULL DEFAULT 'normal',
    FOREIGN KEY (sensor_id) REFERENCES sensores (id) ON DELETE CASCADE,
    FOREIGN KEY (trem_id) REFERENCES trens (id) ON DELETE CASCADE,
    INDEX idx_leituras_sensor (sensor_id, data_hora),
    INDEX idx_leituras_trem (trem_id, data_hora),
    INDEX idx_leituras_data (data_hora)
);

-- ---------------------------------------------------------------------
-- Manutenções
-- ---------------------------------------------------------------------
CREATE TABLE manutencoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trem_id INT NOT NULL,
    rota_id INT NULL,
    tipo ENUM('preventiva', 'corretiva') NOT NULL DEFAULT 'corretiva',
    carga VARCHAR(60) NULL,
    causa VARCHAR(200) NOT NULL,
    status ENUM('aberta', 'em_andamento', 'concluida') NOT NULL DEFAULT 'aberta',
    custo DECIMAL(12,2) NULL,
    data DATE NOT NULL,
    data_conclusao DATE NULL,
    responsavel_id INT NULL,
    FOREIGN KEY (trem_id) REFERENCES trens (id),
    FOREIGN KEY (rota_id) REFERENCES rotas (id) ON DELETE SET NULL,
    FOREIGN KEY (responsavel_id) REFERENCES usuarios (id) ON DELETE SET NULL,
    INDEX idx_manutencoes_data (data)
);

-- ---------------------------------------------------------------------
-- Alertas e notificações (gravidade: normal, atenção, crítico)
-- ---------------------------------------------------------------------
CREATE TABLE alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensagem VARCHAR(255) NOT NULL,
    nivel ENUM('normal', 'atencao', 'critico') NOT NULL DEFAULT 'normal',
    origem ENUM('sensor', 'manual', 'sistema') NOT NULL DEFAULT 'sistema',
    situacao ENUM('ativo', 'resolvido') NOT NULL DEFAULT 'ativo',
    publico TINYINT(1) NOT NULL DEFAULT 0,
    trem_id INT NULL,
    sensor_id INT NULL,
    leitura_id INT NULL,
    criado_por INT NULL,
    data DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolvido_em DATETIME NULL,
    resolvido_por INT NULL,
    FOREIGN KEY (trem_id) REFERENCES trens (id) ON DELETE SET NULL,
    FOREIGN KEY (sensor_id) REFERENCES sensores (id) ON DELETE SET NULL,
    FOREIGN KEY (leitura_id) REFERENCES leituras (id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    FOREIGN KEY (resolvido_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    INDEX idx_alertas_situacao (situacao, nivel),
    INDEX idx_alertas_data (data)
);

-- =====================================================================
-- DADOS DE EXEMPLO
-- =====================================================================

INSERT INTO rotas (id, nome, origem, destino, distancia_km, passageiros, quilometragem, alerta, ativa) VALUES
    (1, 'Rota 2', 'Joinville', 'São Paulo', 520, 860, 3120, NULL, 1),
    (2, 'Rota 14', 'Curitiba', 'Santos', 410, 1040, 4210, 'O trem da linha 306 está em manutenção devido a alguns problemas técnicos.', 1),
    (3, 'Rota 31', 'Joinville', 'Curitiba', 130, 540, 1980, 'Chuva forte na serra: velocidade reduzida entre os km 40 e 62.', 1),
    (4, 'Rota 36', 'São Paulo', 'Belo Horizonte', 590, 1200, 5069, NULL, 1),
    (5, 'Rota 37', 'Santos', 'São Paulo', 80, 930, 2875, NULL, 1),
    (6, 'Rota 38', 'Rio de Janeiro', 'São Paulo', 430, 1530, 6340, 'A Linha 2 está com atraso médio de 20 minutos.', 1),
    (7, 'Rota 40', 'Joinville', 'Itajaí', 90, 0, 0, NULL, 0);

INSERT INTO horarios (rota_id, horario) VALUES
    (1, '05:00'), (1, '08:30'), (1, '12:00'), (1, '15:30'), (1, '19:00'), (1, '22:30'),
    (2, '00:00'), (2, '04:30'), (2, '06:00'), (2, '07:30'), (2, '13:30'), (2, '15:00'), (2, '17:00'), (2, '20:30'),
    (3, '06:15'), (3, '09:45'), (3, '13:15'), (3, '18:45'),
    (4, '04:00'), (4, '10:00'), (4, '16:00'), (4, '22:00'),
    (5, '05:30'), (5, '07:00'), (5, '11:30'), (5, '17:30'), (5, '21:00'),
    (6, '06:00'), (6, '12:00'), (6, '18:00'), (6, '23:45'),
    (7, '08:00');

INSERT INTO trens (id, codigo, nome, modelo, ano_fabricacao, capacidade_toneladas, status, rota_id, consumo, faturamento) VALUES
    (1, '008', 'Trem 08', 'GE AC44i', 2014, 6200.00, 'em_rota', 4, 14, 1300000),
    (2, '009', 'Trem 09', 'EMD SD70ACe', 2011, 5800.50, 'manutencao', NULL, 12, 980000),
    (3, '010', 'Trem 10', 'GE Dash 9-40BBW', 2006, 5400.00, 'disponivel', NULL, 15, 1120000),
    (4, '011', 'Trem 11', 'GE ES44AC', 2016, 6000.00, 'em_rota', 1, 13, 870000),
    (5, '012', 'Trem 12', 'EMD SD40-2', 1999, 5100.75, 'disponivel', NULL, 11, 640000),
    (6, '013', 'Trem 13', 'GE AC44i', 2018, 6200.00, 'em_rota', 6, 14, 1450000),
    (7, '014', 'Trem 14', 'EMD GT46AC', 2009, 5600.00, 'em_rota', 5, 13, 910000),
    (8, '015', 'Trem 15', 'Wabtec AC44C6M', 2021, 6400.00, 'disponivel', NULL, 15, 0);

-- Senha de todos os usuários de exemplo: 123456 (hash bcrypt gerado com password_hash)
INSERT INTO usuarios (nome, email, senha, cargo, funcao, trem_id, ativo, nascimento, ativo_desde, telefone, cpf, endereco) VALUES
    ('Gestor Demonstração', 'gestor@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'gestor', 'Gerente de operações', NULL, 1, '1990-03-14', '2019-02-02', '(47) 99999-1010', NULL, NULL),
    ('Maquinista Demonstração', 'maquinista@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'maquinista', 'Maquinista', 1, 1, '1994-07-22', '2021-08-10', '(47) 99999-2020', NULL, NULL),
    ('Cliente Demonstração', 'cliente@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'cliente', NULL, NULL, 1, '1998-11-05', '2024-01-18', '(47) 99999-3030', NULL, NULL),
    ('Ana Oliveira', 'ana.oliveira@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'gestor', 'Gestora de tráfego', NULL, 1, '1987-04-08', '2018-03-12', '(47) 98811-2201', '529.982.247-25', 'Rua XV de Novembro, 120 - Joinville/SC'),
    ('Antonio Pereira', 'antonio.pereira@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'maquinista', 'Maquinista', 4, 1, '1979-09-19', '2015-07-01', '(47) 98811-2202', NULL, NULL),
    ('Carlos Silva', 'carlos.silva@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'maquinista', 'Maquinista', 6, 1, '1991-12-02', '2020-01-15', '(41) 98811-2203', NULL, NULL),
    ('Francisco Ferreira', 'francisco.ferreira@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'cliente', 'Técnico de manutenção', NULL, 1, '1985-01-30', '2019-05-20', '(41) 98811-2204', NULL, NULL),
    ('Luiz Carvalho', 'luiz.carvalho@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'cliente', 'Operador de sensores', NULL, 1, '1996-06-11', '2022-02-03', '(47) 98811-2205', NULL, NULL),
    ('Maria Silva', 'maria.silva@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'cliente', 'Analista de cargas', NULL, 1, '1989-08-25', '2017-10-09', '(11) 98811-2206', NULL, NULL),
    ('Paulo Alves', 'paulo.alves@tr4.com', '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG', 'maquinista', 'Maquinista', 7, 0, '1982-03-17', '2016-04-14', '(13) 98811-2207', NULL, NULL);

INSERT INTO cargas (tipo, toneladas, destino, partida, status, progresso) VALUES
    ('Carvão', 2, 'São Paulo', CURDATE() - INTERVAL 2 DAY, 'em_transito', 62),
    ('Soja', 50, 'Santos', CURDATE() - INTERVAL 1 DAY, 'em_transito', 28),
    ('Sacos de milho', 10, 'Curitiba', CURDATE() + INTERVAL 3 DAY, 'aguardando', 0),
    ('Minério de ferro', 100, 'Rio de Janeiro', CURDATE() - INTERVAL 6 DAY, 'entregue', 100);

-- Sensores: tipo, grandeza, chave e unidade seguem tiposSensor() em api/conexao.php
INSERT INTO sensores (id, tipo, grandeza, chave, unidade, trem_id, status, prazo, instalado_em) VALUES
    (1, 'DS18B20', 'Temperatura do motor', 'temperatura_motor', '°C', 1, 'ativo', NULL, CURDATE() - INTERVAL 200 DAY),
    (2, 'ADXL345', 'Vibração', 'vibracao', 'mm/s', 1, 'ativo', NULL, CURDATE() - INTERVAL 200 DAY),
    (3, 'A3144', 'Velocidade (sensor Hall na roda)', 'velocidade', 'km/h', 1, 'ativo', NULL, CURDATE() - INTERVAL 200 DAY),
    (4, 'Omnicomm LLS 5', 'Nível e consumo de combustível', 'consumo', 'L/h', 1, 'ativo', NULL, CURDATE() - INTERVAL 200 DAY),
    (5, 'DS18B20', 'Temperatura do motor', 'temperatura_motor', '°C', 2, 'ativo', NULL, CURDATE() - INTERVAL 150 DAY),
    (6, 'MPU-6050', 'Vibração e inclinação', 'vibracao', 'mm/s', 2, 'ativo', NULL, CURDATE() - INTERVAL 150 DAY),
    (7, 'NEO-6M', 'Localização e velocidade (GPS)', 'velocidade', 'km/h', 2, 'ativo', NULL, CURDATE() - INTERVAL 150 DAY),
    (8, 'MPX5700AP', 'Pressão do freio', 'pressao_freio', 'kPa', 4, 'ativo', NULL, CURDATE() - INTERVAL 120 DAY),
    (9, 'A3144', 'Velocidade (sensor Hall na roda)', 'velocidade', 'km/h', 4, 'ativo', NULL, CURDATE() - INTERVAL 120 DAY),
    (10, 'Omnicomm LLS 5', 'Nível e consumo de combustível', 'consumo', 'L/h', 4, 'ativo', NULL, CURDATE() - INTERVAL 120 DAY),
    (11, 'DS18B20', 'Temperatura do motor', 'temperatura_motor', '°C', 4, 'ativo', NULL, CURDATE() - INTERVAL 120 DAY),
    (12, 'A3144', 'Velocidade (sensor Hall na roda)', 'velocidade', 'km/h', 6, 'ativo', NULL, CURDATE() - INTERVAL 90 DAY),
    (13, 'MLX90614', 'Temperatura do rolamento', 'temperatura_rolamento', '°C', 6, 'ativo', NULL, CURDATE() - INTERVAL 90 DAY),
    (14, 'Omnicomm LLS 5', 'Nível e consumo de combustível', 'consumo', 'L/h', 6, 'ativo', NULL, CURDATE() - INTERVAL 90 DAY),
    (15, 'NEO-6M', 'Localização e velocidade (GPS)', 'velocidade', 'km/h', 7, 'ativo', NULL, CURDATE() - INTERVAL 60 DAY),
    (16, 'MAX6675', 'Temperatura dos gases de escape', 'temperatura_escape', '°C', 7, 'ativo', NULL, CURDATE() - INTERVAL 60 DAY),
    (17, 'DHT22', 'Temperatura e umidade da carga', 'temperatura_carga', '°C', 7, 'ativo', NULL, CURDATE() - INTERVAL 60 DAY),
    (18, 'Omnicomm LLS 5', 'Nível e consumo de combustível', 'consumo', 'L/h', 7, 'ativo', NULL, CURDATE() - INTERVAL 60 DAY),
    -- Sensor ativo ainda sem nenhuma leitura (deve aparecer como "Sem leituras")
    (19, 'ADXL345', 'Vibração', 'vibracao', 'mm/s', 3, 'ativo', NULL, CURDATE() - INTERVAL 2 DAY),
    -- Sensor em instalação
    (20, 'DS18B20', 'Temperatura do motor', 'temperatura_motor', '°C', 8, 'instalacao', CURDATE() + INTERVAL 10 DAY, NULL);

-- Leituras: 100 leituras por sensor ativo (uma a cada 7 horas, cerca de 29 dias),
-- com valores pseudoaleatórios determinísticos. O nível segue os mesmos limites de
-- includes/limites.php (atenção / crítico).
INSERT INTO leituras (sensor_id, trem_id, data_hora, valor, nivel)
SELECT s.id, s.trem_id, d.data_hora, d.valor,
       CASE
           WHEN s.chave = 'velocidade' AND d.valor > 80 THEN 'critico'
           WHEN s.chave = 'velocidade' AND d.valor > 70 THEN 'atencao'
           WHEN s.chave = 'temperatura_motor' AND d.valor > 105 THEN 'critico'
           WHEN s.chave = 'temperatura_motor' AND d.valor > 95 THEN 'atencao'
           WHEN s.chave = 'consumo' AND d.valor > 75 THEN 'critico'
           WHEN s.chave = 'consumo' AND d.valor > 65 THEN 'atencao'
           WHEN s.chave = 'vibracao' AND d.valor > 7 THEN 'critico'
           WHEN s.chave = 'vibracao' AND d.valor > 5.5 THEN 'atencao'
           WHEN s.chave = 'temperatura_escape' AND d.valor > 550 THEN 'critico'
           WHEN s.chave = 'temperatura_escape' AND d.valor > 480 THEN 'atencao'
           WHEN s.chave = 'temperatura_rolamento' AND d.valor > 85 THEN 'critico'
           WHEN s.chave = 'temperatura_rolamento' AND d.valor > 70 THEN 'atencao'
           WHEN s.chave = 'pressao_freio' AND d.valor < 350 THEN 'critico'
           WHEN s.chave = 'pressao_freio' AND d.valor < 420 THEN 'atencao'
           WHEN s.chave = 'temperatura_carga' AND d.valor > 35 THEN 'critico'
           WHEN s.chave = 'temperatura_carga' AND d.valor > 30 THEN 'atencao'
           ELSE 'normal'
       END
FROM sensores s
JOIN (
    SELECT s2.id AS sensor_id,
           NOW() - INTERVAL (n.n * 7) HOUR - INTERVAL MOD(s2.id * 13, 50) MINUTE AS data_hora,
           ROUND(CASE s2.chave
               WHEN 'velocidade' THEN 35 + MOD(n.n * 37 + s2.id * 17, 100) * 0.47
               WHEN 'temperatura_motor' THEN 70 + MOD(n.n * 37 + s2.id * 17, 100) * 0.37
               WHEN 'consumo' THEN 30 + MOD(n.n * 37 + s2.id * 17, 100) * 0.47
               WHEN 'vibracao' THEN 1.5 + MOD(n.n * 37 + s2.id * 17, 100) * 0.058
               WHEN 'temperatura_escape' THEN 330 + MOD(n.n * 37 + s2.id * 17, 100) * 2.25
               WHEN 'temperatura_rolamento' THEN 40 + MOD(n.n * 37 + s2.id * 17, 100) * 0.46
               WHEN 'pressao_freio' THEN 340 + MOD(n.n * 37 + s2.id * 17, 100) * 3.1
               WHEN 'temperatura_carga' THEN 18 + MOD(n.n * 37 + s2.id * 17, 100) * 0.18
               ELSE 0
           END, 2) AS valor
    FROM sensores s2
    CROSS JOIN (
        SELECT a.d + b.d * 10 AS n
        FROM (SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
              UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
        CROSS JOIN (SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
              UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
    ) n
    WHERE s2.status = 'ativo' AND s2.id <> 19
) d ON d.sensor_id = s.id
ORDER BY d.data_hora;

-- Leituras mais recentes com nível crítico, para testar os alertas e o painel
INSERT INTO leituras (sensor_id, trem_id, data_hora, valor, nivel) VALUES
    (4, 1, NOW() - INTERVAL 1 MINUTE, 78.40, 'critico'),
    (13, 6, NOW() - INTERVAL 1 MINUTE, 88.10, 'critico'),
    (9, 4, NOW() - INTERVAL 1 MINUTE, 72.30, 'atencao');

-- Viagens concluídas: 12 por trem nos últimos 30 dias (trens 1, 4, 6, 7 e 3)
INSERT INTO viagens (rota_id, trem_id, maquinista_id, partida_prevista, chegada_prevista, partida_real, chegada_real, status, km_percorridos)
SELECT r.id, t.trem_id, t.maquinista_id,
       p.partida,
       p.partida + INTERVAL ROUND(r.distancia_km / 50 * 60) MINUTE,
       p.partida + INTERVAL MOD(p.n * 7 + t.trem_id, 12) MINUTE,
       p.partida + INTERVAL (ROUND(r.distancia_km / 50 * 60) + MOD(p.n * 13 + t.trem_id * 5, 40) - 8) MINUTE,
       'concluida',
       r.distancia_km
FROM (
    SELECT 1 AS trem_id, 4 AS rota_id, 2 AS maquinista_id UNION ALL
    SELECT 4, 1, 5 UNION ALL
    SELECT 6, 6, 6 UNION ALL
    SELECT 7, 5, NULL UNION ALL
    SELECT 3, 3, NULL
) t
JOIN rotas r ON r.id = t.rota_id
JOIN (
    SELECT n.n, CURDATE() - INTERVAL (n.n * 2 + 1) DAY + INTERVAL 6 HOUR AS partida
    FROM (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
          UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12) n
) p
ORDER BY p.partida;

-- Viagens atuais e futuras
INSERT INTO viagens (rota_id, trem_id, maquinista_id, partida_prevista, chegada_prevista, partida_real, chegada_real, status, km_percorridos, observacao) VALUES
    (4, 1, 2, NOW() - INTERVAL 3 HOUR, NOW() + INTERVAL 9 HOUR, NOW() - INTERVAL 175 MINUTE, NULL, 'em_andamento', 0, NULL),
    (1, 4, 5, NOW() - INTERVAL 2 HOUR, NOW() + INTERVAL 8 HOUR, NOW() - INTERVAL 2 HOUR, NULL, 'em_andamento', 0, NULL),
    (6, 6, 6, NOW() - INTERVAL 5 HOUR, NOW() + INTERVAL 4 HOUR, NOW() - INTERVAL 4 HOUR, NULL, 'atrasada', 0, 'Atraso de cerca de 20 minutos na Linha 2.'),
    (5, 7, NULL, NOW() - INTERVAL 1 HOUR, NOW() + INTERVAL 1 HOUR, NOW() - INTERVAL 1 HOUR, NULL, 'em_andamento', 0, NULL),
    (3, 3, NULL, CURDATE() + INTERVAL 1 DAY + INTERVAL 6 HOUR, CURDATE() + INTERVAL 1 DAY + INTERVAL 9 HOUR, NULL, NULL, 'agendada', 0, NULL),
    (2, 5, NULL, CURDATE() + INTERVAL 2 DAY + INTERVAL 7 HOUR, CURDATE() + INTERVAL 2 DAY + INTERVAL 15 HOUR, NULL, NULL, 'agendada', 0, NULL),
    (2, 2, NULL, CURDATE() - INTERVAL 1 DAY + INTERVAL 7 HOUR, CURDATE() - INTERVAL 1 DAY + INTERVAL 15 HOUR, NULL, NULL, 'cancelada', 0, 'Cancelada: trem enviado para manutenção.');

-- Manutenções (preventivas e corretivas, abertas e concluídas)
INSERT INTO manutencoes (trem_id, rota_id, tipo, carga, causa, status, custo, data, data_conclusao, responsavel_id) VALUES
    (2, 2, 'corretiva', 'Soja', 'Freio: desgaste das sapatas', 'em_andamento', 18500.00, CURDATE() - INTERVAL 1 DAY, NULL, 1),
    (1, 4, 'preventiva', 'Sem carga', 'Revisão dos 50 mil km', 'concluida', 32000.00, CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 23 DAY, 1),
    (4, 1, 'corretiva', 'Carvão', 'Motor: superaquecimento', 'concluida', 47250.50, CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 15 DAY, 4),
    (6, 6, 'preventiva', 'Sem carga', 'Troca de óleo e filtros', 'concluida', 8900.00, CURDATE() - INTERVAL 10 DAY, CURDATE() - INTERVAL 10 DAY, 4),
    (7, 5, 'corretiva', 'Contêineres', 'Rolamento com vibração acima do normal', 'aberta', NULL, CURDATE(), NULL, 2),
    (5, NULL, 'preventiva', NULL, 'Inspeção anual de segurança', 'concluida', 12000.00, CURDATE() - INTERVAL 40 DAY, CURDATE() - INTERVAL 38 DAY, 1);

-- Alertas gerados a partir da última leitura de cada sensor fora do normal
INSERT INTO alertas (mensagem, nivel, origem, situacao, publico, trem_id, sensor_id, leitura_id, data)
SELECT CONCAT(t.nome, ' - ', s.grandeza, ' (', s.tipo, '): ', REPLACE(FORMAT(l.valor, 2), '.', ','), ' ', s.unidade,
              IF(l.nivel = 'critico', ' - nível crítico.', ' - nível de atenção.')),
       l.nivel, 'sensor', 'ativo', 0, s.trem_id, s.id, l.id, l.data_hora
FROM sensores s
JOIN trens t ON t.id = s.trem_id
JOIN leituras l ON l.id = (SELECT l2.id FROM leituras l2 WHERE l2.sensor_id = s.id ORDER BY l2.data_hora DESC, l2.id DESC LIMIT 1)
WHERE l.nivel <> 'normal';

-- Alertas históricos resolvidos (leituras críticas antigas)
INSERT INTO alertas (mensagem, nivel, origem, situacao, publico, trem_id, sensor_id, leitura_id, data, resolvido_em, resolvido_por)
SELECT CONCAT(t.nome, ' - ', s.grandeza, ' (', s.tipo, '): ', REPLACE(FORMAT(l.valor, 2), '.', ','), ' ', s.unidade, ' - nível crítico.'),
       'critico', 'sensor', 'resolvido', 0, s.trem_id, s.id, l.id, l.data_hora, l.data_hora + INTERVAL 2 HOUR, 1
FROM leituras l
JOIN sensores s ON s.id = l.sensor_id
JOIN trens t ON t.id = s.trem_id
WHERE l.nivel = 'critico' AND l.data_hora < NOW() - INTERVAL 10 DAY
ORDER BY l.data_hora DESC
LIMIT 6;

-- Avisos e notificações gerais
INSERT INTO alertas (mensagem, nivel, origem, situacao, publico, trem_id, criado_por, data) VALUES
    ('Seu trem está a caminho! Rota 36 em andamento.', 'normal', 'sistema', 'ativo', 1, 1, NULL, NOW() - INTERVAL 3 HOUR),
    ('Alerta de chuva na Rota 31: velocidade reduzida na serra.', 'atencao', 'manual', 'ativo', 1, NULL, 1, NOW() - INTERVAL 1 HOUR),
    ('A Linha 2 da Rota 38 está em atraso.', 'atencao', 'sistema', 'ativo', 1, 6, NULL, NOW() - INTERVAL 4 HOUR),
    ('Trem 09 parado para manutenção do freio.', 'critico', 'manual', 'ativo', 0, 2, 1, NOW() - INTERVAL 1 DAY),
    ('Carga entregue: Minério de ferro chegou em Rio de Janeiro.', 'normal', 'sistema', 'resolvido', 1, NULL, NULL, NOW() - INTERVAL 6 DAY);
