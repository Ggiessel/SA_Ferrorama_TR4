-- =====================================================================
-- Ferrorama TR4 - migração do banco antigo (v1) para a versão 2
-- Use SOMENTE se você já tem o banco ferrorama_tr4 da versão anterior e quer
-- manter os dados. Para uma instalação nova, importe ferrorama_tr4.sql.
-- Faça um backup antes (phpMyAdmin > Exportar).
-- =====================================================================

USE ferrorama_tr4;
SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ---------------------------------------------------------------- rotas
ALTER TABLE rotas
    ADD COLUMN distancia_km INT NOT NULL DEFAULT 0 AFTER destino,
    ADD COLUMN ativa TINYINT(1) NOT NULL DEFAULT 1;
-- Distância estimada a partir da quilometragem acumulada (ajuste depois na tela de Rotas)
UPDATE rotas SET distancia_km = GREATEST(50, ROUND(quilometragem / 8)) WHERE distancia_km = 0;

-- ---------------------------------------------------------------- trens
ALTER TABLE trens MODIFY status ENUM('em_rota', 'disponivel', 'manutencao', 'inativo') NOT NULL DEFAULT 'disponivel';
ALTER TABLE trens ADD INDEX idx_trens_status (status);

-- ---------------------------------------------------------------- usuários
ALTER TABLE usuarios
    ADD COLUMN funcao VARCHAR(60) NULL AFTER cargo,
    ADD COLUMN trem_id INT NULL AFTER funcao,
    ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER trem_id,
    ADD COLUMN trocar_senha TINYINT(1) NOT NULL DEFAULT 0 AFTER ativo,
    ADD COLUMN pedido_senha_em DATETIME NULL AFTER trocar_senha,
    ADD COLUMN cpf VARCHAR(14) NULL UNIQUE,
    ADD COLUMN endereco VARCHAR(200) NULL,
    ADD CONSTRAINT fk_usuarios_trem FOREIGN KEY (trem_id) REFERENCES trens (id) ON DELETE SET NULL,
    ADD INDEX idx_usuarios_cargo (cargo, ativo);

-- Os funcionários (tabela separada, sem login) passam a ser usuários.
-- Senha inicial 123456, com troca obrigatória no primeiro acesso.
INSERT INTO usuarios (nome, email, senha, cargo, funcao, ativo, trocar_senha, nascimento, ativo_desde, telefone, cpf, endereco)
SELECT f.nome, f.email, '$2y$10$biAI5q2rpTVAVOahaNMlQepEjg2WIOGa24RPoT084xS5GSBeGy6QG',
       CASE WHEN f.cargo LIKE 'Maquinista%' THEN 'maquinista' WHEN f.cargo LIKE 'Gestor%' THEN 'gestor' ELSE 'cliente' END,
       f.cargo, 1, 1, f.nascimento, f.ativo_desde, f.telefone, f.cpf, f.endereco
FROM funcionarios f
WHERE NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.email = f.email);

DROP TABLE funcionarios;

-- ---------------------------------------------------------------- viagens (substitui "linhas")
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

-- Cada trem que já estava "em rota" ganha uma viagem em andamento
INSERT INTO viagens (rota_id, trem_id, partida_prevista, chegada_prevista, partida_real, status)
SELECT t.rota_id, t.trem_id, NOW(), NOW() + INTERVAL 6 HOUR, NOW(), 'em_andamento'
FROM (SELECT id AS trem_id, rota_id FROM trens WHERE status = 'em_rota' AND rota_id IS NOT NULL) t;

DROP TABLE linhas;

-- ---------------------------------------------------------------- sensores
ALTER TABLE sensores
    ADD COLUMN chave VARCHAR(30) NOT NULL DEFAULT '' AFTER grandeza,
    ADD COLUMN unidade VARCHAR(10) NOT NULL DEFAULT '' AFTER chave,
    ADD COLUMN instalado_em DATE NULL,
    ADD COLUMN observacao VARCHAR(200) NULL,
    MODIFY status ENUM('ativo', 'instalacao', 'remocao', 'inativo') NOT NULL DEFAULT 'instalacao',
    ADD INDEX idx_sensores_trem (trem_id, status);

UPDATE sensores SET chave = 'temperatura_motor', unidade = '°C' WHERE tipo = 'DS18B20';
UPDATE sensores SET chave = 'temperatura_escape', unidade = '°C' WHERE tipo = 'MAX6675';
UPDATE sensores SET chave = 'temperatura_rolamento', unidade = '°C' WHERE tipo = 'MLX90614';
UPDATE sensores SET chave = 'vibracao', unidade = 'mm/s' WHERE tipo IN ('ADXL345', 'MPU-6050');
UPDATE sensores SET chave = 'velocidade', unidade = 'km/h' WHERE tipo IN ('A3144', 'NEO-6M');
UPDATE sensores SET chave = 'consumo', unidade = 'L/h' WHERE tipo = 'Omnicomm LLS 5';
UPDATE sensores SET chave = 'pressao_freio', unidade = 'kPa' WHERE tipo = 'MPX5700AP';
UPDATE sensores SET chave = 'temperatura_carga', unidade = '°C' WHERE tipo = 'DHT22';
UPDATE sensores SET instalado_em = CURDATE() WHERE status = 'ativo';

-- ---------------------------------------------------------------- leituras (por trem -> por sensor)
RENAME TABLE leituras TO leituras_v1;

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

-- Garante um sensor por grandeza nos trens que já tinham leituras, para não perder dados
INSERT INTO sensores (tipo, grandeza, chave, unidade, trem_id, status, instalado_em, observacao)
SELECT g.tipo, g.grandeza, g.chave, g.unidade, t.trem_id, 'ativo', CURDATE(), 'Criado na migração para guardar as leituras antigas'
FROM (SELECT DISTINCT trem_id FROM leituras_v1) t
CROSS JOIN (
    SELECT 'A3144' AS tipo, 'Velocidade (sensor Hall na roda)' AS grandeza, 'velocidade' AS chave, 'km/h' AS unidade
    UNION ALL SELECT 'DS18B20', 'Temperatura do motor', 'temperatura_motor', '°C'
    UNION ALL SELECT 'Omnicomm LLS 5', 'Nível e consumo de combustível', 'consumo', 'L/h'
    UNION ALL SELECT 'ADXL345', 'Vibração', 'vibracao', 'mm/s'
) g
WHERE NOT EXISTS (SELECT 1 FROM sensores s WHERE s.trem_id = t.trem_id AND s.chave = g.chave);

INSERT INTO leituras (sensor_id, trem_id, data_hora, valor, nivel)
SELECT s.id, l.trem_id, l.data_hora,
       CASE g.chave WHEN 'velocidade' THEN l.velocidade_kmh WHEN 'temperatura_motor' THEN l.temperatura_motor_c
                    WHEN 'consumo' THEN l.consumo_litros_hora ELSE l.vibracao_mm_s END AS valor,
       'normal'
FROM leituras_v1 l
CROSS JOIN (SELECT 'velocidade' AS chave UNION ALL SELECT 'temperatura_motor' UNION ALL SELECT 'consumo' UNION ALL SELECT 'vibracao') g
JOIN sensores s ON s.id = (
    SELECT s2.id FROM sensores s2 WHERE s2.trem_id = l.trem_id AND s2.chave = g.chave ORDER BY s2.status = 'ativo' DESC, s2.id LIMIT 1
)
ORDER BY l.data_hora, l.id;

-- Classificação com os limites de includes/limites.php
UPDATE leituras l JOIN sensores s ON s.id = l.sensor_id SET l.nivel = CASE
    WHEN s.chave = 'velocidade' AND l.valor > 80 THEN 'critico'
    WHEN s.chave = 'velocidade' AND l.valor > 70 THEN 'atencao'
    WHEN s.chave = 'temperatura_motor' AND l.valor > 105 THEN 'critico'
    WHEN s.chave = 'temperatura_motor' AND l.valor > 95 THEN 'atencao'
    WHEN s.chave = 'consumo' AND l.valor > 75 THEN 'critico'
    WHEN s.chave = 'consumo' AND l.valor > 65 THEN 'atencao'
    WHEN s.chave = 'vibracao' AND l.valor > 7 THEN 'critico'
    WHEN s.chave = 'vibracao' AND l.valor > 5.5 THEN 'atencao'
    ELSE 'normal' END;

DROP TABLE leituras_v1;

-- ---------------------------------------------------------------- manutenções
ALTER TABLE manutencoes
    MODIFY rota_id INT NULL,
    MODIFY carga VARCHAR(60) NULL,
    ADD COLUMN tipo ENUM('preventiva', 'corretiva') NOT NULL DEFAULT 'corretiva' AFTER rota_id,
    ADD COLUMN status ENUM('aberta', 'em_andamento', 'concluida') NOT NULL DEFAULT 'aberta' AFTER causa,
    ADD COLUMN custo DECIMAL(12,2) NULL AFTER status,
    ADD COLUMN data_conclusao DATE NULL AFTER data,
    ADD COLUMN responsavel_id INT NULL AFTER data_conclusao,
    ADD CONSTRAINT fk_manutencoes_responsavel FOREIGN KEY (responsavel_id) REFERENCES usuarios (id) ON DELETE SET NULL,
    ADD INDEX idx_manutencoes_data (data);

-- ---------------------------------------------------------------- alertas
ALTER TABLE alertas
    ADD COLUMN nivel ENUM('normal', 'atencao', 'critico') NOT NULL DEFAULT 'normal' AFTER mensagem,
    ADD COLUMN origem ENUM('sensor', 'manual', 'sistema') NOT NULL DEFAULT 'sistema' AFTER nivel,
    ADD COLUMN situacao ENUM('ativo', 'resolvido') NOT NULL DEFAULT 'ativo' AFTER origem,
    ADD COLUMN publico TINYINT(1) NOT NULL DEFAULT 0 AFTER situacao,
    ADD COLUMN trem_id INT NULL AFTER publico,
    ADD COLUMN sensor_id INT NULL AFTER trem_id,
    ADD COLUMN leitura_id INT NULL AFTER sensor_id,
    ADD COLUMN criado_por INT NULL AFTER leitura_id,
    ADD COLUMN resolvido_em DATETIME NULL,
    ADD COLUMN resolvido_por INT NULL,
    ADD CONSTRAINT fk_alertas_trem FOREIGN KEY (trem_id) REFERENCES trens (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_alertas_sensor FOREIGN KEY (sensor_id) REFERENCES sensores (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_alertas_leitura FOREIGN KEY (leitura_id) REFERENCES leituras (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_alertas_criador FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_alertas_resolvido FOREIGN KEY (resolvido_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    ADD INDEX idx_alertas_situacao (situacao, nivel),
    ADD INDEX idx_alertas_data (data);

-- Alertas antigos: "info" vira Normal; "aviso" e "atraso" viram Atenção. Antes eram visíveis a todos.
UPDATE alertas SET nivel = IF(tipo = 'info', 'normal', 'atencao'), publico = 1;
ALTER TABLE alertas DROP COLUMN tipo;

-- Alerta ativo para cada sensor cuja leitura mais recente não está normal
INSERT INTO alertas (mensagem, nivel, origem, situacao, trem_id, sensor_id, leitura_id, data)
SELECT CONCAT(t.nome, ' - ', s.grandeza, ' (', s.tipo, '): ', REPLACE(FORMAT(l.valor, 2), '.', ','), ' ', s.unidade,
              IF(l.nivel = 'critico', ' - nível crítico.', ' - nível de atenção.')),
       l.nivel, 'sensor', 'ativo', s.trem_id, s.id, l.id, l.data_hora
FROM sensores s
JOIN trens t ON t.id = s.trem_id
JOIN leituras l ON l.id = (SELECT l2.id FROM leituras l2 WHERE l2.sensor_id = s.id ORDER BY l2.data_hora DESC, l2.id DESC LIMIT 1)
WHERE l.nivel <> 'normal';
