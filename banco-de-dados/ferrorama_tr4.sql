/*
============================================================
  FERRORAMA TR4 — BANCO DE DADOS
  Arquivo: banco-de-dados/ferrorama_tr4.sql
============================================================

  Compatível com MySQL 8 e MariaDB 10.4+ (XAMPP).

  Como importar:
    - MySQL Workbench: File > Open SQL Script > executar tudo (raio ⚡).
    - phpMyAdmin (XAMPP): aba "Importar" > escolher este arquivo > Executar.
    - Terminal: mysql -u root -p < ferrorama_tr4.sql

  ATENÇÃO: o script APAGA e recria o banco ferrorama_tr4.

  Ordem do arquivo:
    1. Banco
    2. Tabelas
    3. Dados iniciais (os mesmos de js/dados/dados-iniciais.js)
    4. Gatilhos (triggers) — regras automáticas
    5. Views — consultas prontas para as telas
    6. Procedimentos (procedures)

  Equivalência com o JavaScript (TR4.db no localStorage):
    usuarios      -> usuarios
    rotas         -> rotas + rota_horarios + linhas
    trens         -> trens
    funcionarios  -> funcionarios
    alertas       -> alertas
    tiposCarga    -> tipos_carga
    destinos      -> cidades
    cargas        -> cargas
    tiposSensor   -> tipos_sensor
    sensores      -> sensores
    manutencoes   -> manutencoes
*/

/* ============================================================
   1. BANCO
   ============================================================ */

DROP DATABASE IF EXISTS ferrorama_tr4;

-- utf8mb4_unicode_ci: aceita acentos e emojis e faz buscas sem
-- diferenciar maiúsculas/acentos ("gestao" encontra "Gestão")
CREATE DATABASE ferrorama_tr4
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ferrorama_tr4;


/* ============================================================
   2. TABELAS
   ============================================================ */

-- ------------------------------------------------------------
-- Cidades: origem/destino das rotas e destino das cargas
-- ------------------------------------------------------------
CREATE TABLE cidades (
    id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome  VARCHAR(80)  NOT NULL,
    uf    CHAR(2)      NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_cidades_nome_uf (nome, uf)
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Usuários que fazem login no sistema (login.html / signin.html)
--
-- A senha NUNCA é guardada como texto. Guardamos:
--   senha_salt = texto aleatório próprio de cada usuário
--   senha_hash = SHA2(senha_salt + senha, 256)
-- Para conferir o login:
--   WHERE email = ? AND senha_hash = SHA2(CONCAT(senha_salt, ?), 256)
-- Num back-end de produção, prefira bcrypt ou argon2.
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome             VARCHAR(100) NOT NULL,
    email            VARCHAR(120) NOT NULL,
    senha_salt       CHAR(32)     NOT NULL,
    senha_hash       CHAR(64)     NOT NULL,
    cargo            ENUM('gestor', 'maquinista', 'cliente') NULL, -- NULL até escolher em selecao-usuario.html
    data_nascimento  DATE         NULL,
    ativo_desde      DATE         NOT NULL,
    telefone         VARCHAR(15)  NULL,                            -- (47) 99999-9999
    criado_em        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email),
    CONSTRAINT ck_usuarios_nome CHECK (CHAR_LENGTH(nome) >= 3)
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Funcionários da empresa (funcionarios.html / perfil-funcionario.html)
-- Um funcionário pode (ou não) ter um usuário para entrar no sistema.
-- ------------------------------------------------------------
CREATE TABLE funcionarios (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id       INT UNSIGNED NULL,
    nome             VARCHAR(100) NOT NULL,
    cargo            VARCHAR(60)  NOT NULL,           -- ex.: Maquinista, Técnico de manutenção
    ativo_desde      DATE         NOT NULL,
    email            VARCHAR(120) NOT NULL,
    telefone         VARCHAR(15)  NULL,
    cpf              CHAR(14)     NULL,               -- 000.000.000-00
    data_nascimento  DATE         NULL,
    endereco         VARCHAR(200) NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_funcionarios_email (email),
    UNIQUE KEY uk_funcionarios_cpf (cpf),
    UNIQUE KEY uk_funcionarios_usuario (usuario_id),
    CONSTRAINT fk_funcionarios_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_funcionarios_cpf
        CHECK (cpf IS NULL OR cpf REGEXP '^[0-9]{3}\\.[0-9]{3}\\.[0-9]{3}-[0-9]{2}$')
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Rotas (gestao-rotas.html / gestao-geral.html)
-- ------------------------------------------------------------
CREATE TABLE rotas (
    id                         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    numero                     SMALLINT UNSIGNED NOT NULL,              -- 14 -> "Rota 14"
    origem_id                  INT UNSIGNED      NOT NULL,
    destino_id                 INT UNSIGNED      NOT NULL,
    passageiros_transportados  INT UNSIGNED      NOT NULL DEFAULT 0,
    quilometragem              INT UNSIGNED      NOT NULL DEFAULT 0,    -- km
    alerta                     VARCHAR(255)      NULL,                  -- aviso exibido no painel "Alertas"

    PRIMARY KEY (id),
    UNIQUE KEY uk_rotas_numero (numero),
    CONSTRAINT fk_rotas_origem
        FOREIGN KEY (origem_id) REFERENCES cidades (id),
    CONSTRAINT fk_rotas_destino
        FOREIGN KEY (destino_id) REFERENCES cidades (id),
    CONSTRAINT ck_rotas_cidades CHECK (origem_id <> destino_id)
) ENGINE = InnoDB;

-- Horários de partida de cada rota (os cartões 07:30, 15:00...)
CREATE TABLE rota_horarios (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rota_id  INT UNSIGNED NOT NULL,
    horario  TIME         NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_rota_horario (rota_id, horario),
    CONSTRAINT fk_horarios_rota
        FOREIGN KEY (rota_id) REFERENCES rotas (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- Linhas de cada rota e o status delas (painel "Status")
CREATE TABLE linhas (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rota_id  INT UNSIGNED NOT NULL,
    nome     VARCHAR(30)  NOT NULL,                                       -- "Linha 1"
    status   ENUM('em_espera', 'ja_partiu', 'atrasado') NOT NULL DEFAULT 'em_espera',

    PRIMARY KEY (id),
    UNIQUE KEY uk_linhas_rota_nome (rota_id, nome),
    CONSTRAINT fk_linhas_rota
        FOREIGN KEY (rota_id) REFERENCES rotas (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Trens (gestao-geral.html / manutencao.html)
-- ------------------------------------------------------------
CREATE TABLE trens (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    codigo        CHAR(3)       NOT NULL,                  -- "008"
    nome          VARCHAR(30)   NOT NULL,                  -- "Trem 08"
    status        ENUM('em_rota', 'disponivel', 'manutencao') NOT NULL DEFAULT 'disponivel',
    rota_id       INT UNSIGNED  NULL,                      -- rota atual (só quando em rota)
    consumo_km_l  DECIMAL(5,2)  NOT NULL,                  -- km por litro
    faturamento   DECIMAL(14,2) NOT NULL DEFAULT 0,        -- R$

    PRIMARY KEY (id),
    UNIQUE KEY uk_trens_codigo (codigo),
    CONSTRAINT fk_trens_rota
        FOREIGN KEY (rota_id) REFERENCES rotas (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_trens_codigo  CHECK (codigo REGEXP '^[0-9]{3}$'),
    CONSTRAINT ck_trens_consumo CHECK (consumo_km_l > 0)
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Cargas (cadastro-carga.html / monitoramento.html)
-- ------------------------------------------------------------
CREATE TABLE tipos_carga (
    id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome  VARCHAR(60)  NOT NULL,                          -- Carvão, Soja...

    PRIMARY KEY (id),
    UNIQUE KEY uk_tipos_carga_nome (nome)
) ENGINE = InnoDB;

CREATE TABLE cargas (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    tipo_carga_id   INT UNSIGNED     NOT NULL,
    toneladas       DECIMAL(8,2)     NOT NULL,
    destino_id      INT UNSIGNED     NOT NULL,
    data_partida    DATE             NOT NULL,
    status          ENUM('aguardando', 'em_transito', 'entregue') NOT NULL DEFAULT 'aguardando',
    progresso       TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- % do trajeto (0 a 100)
    trem_id         INT UNSIGNED     NULL,
    cadastrado_por  INT UNSIGNED     NULL,                -- usuário que cadastrou
    criado_em       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY ix_cargas_status (status),
    KEY ix_cargas_partida (data_partida),
    CONSTRAINT fk_cargas_tipo
        FOREIGN KEY (tipo_carga_id) REFERENCES tipos_carga (id),
    CONSTRAINT fk_cargas_destino
        FOREIGN KEY (destino_id) REFERENCES cidades (id),
    CONSTRAINT fk_cargas_trem
        FOREIGN KEY (trem_id) REFERENCES trens (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_cargas_usuario
        FOREIGN KEY (cadastrado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_cargas_toneladas CHECK (toneladas > 0),
    CONSTRAINT ck_cargas_progresso CHECK (progresso <= 100),
    CONSTRAINT ck_cargas_entregue  CHECK (status <> 'entregue' OR progresso = 100)
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Sensores (gestao-sensores.html / adicionar-sensor.html / remover-sensor.html)
-- ------------------------------------------------------------
CREATE TABLE tipos_sensor (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    modelo     VARCHAR(40)  NOT NULL,                     -- "GEL 2747"
    descricao  VARCHAR(100) NULL,                         -- "temperatura", "vibração"...

    PRIMARY KEY (id),
    UNIQUE KEY uk_tipos_sensor_modelo (modelo)
) ENGINE = InnoDB;

CREATE TABLE sensores (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo_sensor_id  INT UNSIGNED NOT NULL,
    rota_id         INT UNSIGNED NOT NULL,
    status          ENUM('ativo', 'instalacao', 'remocao') NOT NULL DEFAULT 'instalacao',
    prazo           DATE         NULL,                    -- fim da instalação/remoção (4 semanas)
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Coluna calculada: 1 enquanto o sensor ocupa a "vaga" na rota,
    -- NULL quando está sendo removido. Junto com o UNIQUE abaixo,
    -- impede dois sensores do mesmo modelo na mesma rota
    -- (a mesma regra do sensores.js), mas libera a vaga durante a remoção.
    vaga_ocupada    TINYINT AS (IF(status = 'remocao', NULL, 1)) STORED,

    PRIMARY KEY (id),
    UNIQUE KEY uk_sensor_por_rota (tipo_sensor_id, rota_id, vaga_ocupada),
    CONSTRAINT fk_sensores_tipo
        FOREIGN KEY (tipo_sensor_id) REFERENCES tipos_sensor (id),
    CONSTRAINT fk_sensores_rota
        FOREIGN KEY (rota_id) REFERENCES rotas (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_sensores_prazo CHECK (status = 'ativo' OR prazo IS NOT NULL)
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Manutenções (manutencao.html / relatorios.html)
-- ------------------------------------------------------------
CREATE TABLE manutencoes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    trem_id         INT UNSIGNED NOT NULL,
    rota_id         INT UNSIGNED NOT NULL,                -- rota em que o trem estava
    tipo_carga_id   INT UNSIGNED NULL,                    -- NULL = "Sem carga"
    causa           VARCHAR(200) NOT NULL,
    data            DATE         NOT NULL,
    registrado_por  INT UNSIGNED NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY ix_manutencoes_data (data),
    CONSTRAINT fk_manutencoes_trem
        FOREIGN KEY (trem_id) REFERENCES trens (id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_manutencoes_rota
        FOREIGN KEY (rota_id) REFERENCES rotas (id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_manutencoes_carga
        FOREIGN KEY (tipo_carga_id) REFERENCES tipos_carga (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_manutencoes_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT ck_manutencoes_causa CHECK (CHAR_LENGTH(causa) >= 3)
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Alertas e notificações (alertas.html)
-- ------------------------------------------------------------
CREATE TABLE alertas (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mensagem   VARCHAR(255) NOT NULL,
    tipo       ENUM('info', 'aviso', 'atraso') NOT NULL DEFAULT 'info',
    rota_id    INT UNSIGNED NULL,                         -- opcional: rota relacionada
    trem_id    INT UNSIGNED NULL,                         -- opcional: trem relacionado
    criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY ix_alertas_criado_em (criado_em),
    CONSTRAINT fk_alertas_rota
        FOREIGN KEY (rota_id) REFERENCES rotas (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_alertas_trem
        FOREIGN KEY (trem_id) REFERENCES trens (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB;


/* ============================================================
   3. DADOS INICIAIS
   (os mesmos de js/dados/dados-iniciais.js)
   Inseridos ANTES dos gatilhos para não gerar alertas repetidos.
   ============================================================ */

INSERT INTO cidades (id, nome, uf) VALUES
    (1, 'Joinville',      'SC'),
    (2, 'São Paulo',      'SP'),
    (3, 'Curitiba',       'PR'),
    (4, 'Santos',         'SP'),
    (5, 'Belo Horizonte', 'MG'),
    (6, 'Rio de Janeiro', 'RJ');

-- Contas de demonstração (senha de todas: 123456)
INSERT INTO usuarios (id, nome, email, senha_salt, senha_hash, cargo, data_nascimento, ativo_desde, telefone) VALUES
    (1, 'Gestor Demonstração', 'gestor@tr4.com',
        '9f2c1a7e4b8d3f6a0c5e2b9d7a1f4c8e', SHA2(CONCAT('9f2c1a7e4b8d3f6a0c5e2b9d7a1f4c8e', '123456'), 256),
        'gestor', '1990-03-14', '2019-02-02', '(47) 99999-1010'),
    (2, 'Maquinista Demonstração', 'maquinista@tr4.com',
        '3b7e9a1c5d2f8e4a6c0b9d3e7f1a5c2d', SHA2(CONCAT('3b7e9a1c5d2f8e4a6c0b9d3e7f1a5c2d', '123456'), 256),
        'maquinista', '1994-07-22', '2021-08-10', '(47) 99999-2020'),
    (3, 'Cliente Demonstração', 'cliente@tr4.com',
        'c4a8e2f6b0d9c3a7e1f5b8d2a6c0e4f9', SHA2(CONCAT('c4a8e2f6b0d9c3a7e1f5b8d2a6c0e4f9', '123456'), 256),
        'cliente', '1998-11-05', '2024-01-18', '(47) 99999-3030');

INSERT INTO rotas (id, numero, origem_id, destino_id, passageiros_transportados, quilometragem, alerta) VALUES
    (1,  2, 1, 2,  860, 3120, NULL),
    (2, 14, 3, 4, 1040, 4210, 'O trem da linha 306 está em manutenção devido a alguns problemas técnicos.'),
    (3, 31, 1, 3,  540, 1980, 'Chuva forte na serra: velocidade reduzida entre os km 40 e 62.'),
    (4, 36, 2, 5, 1200, 5069, NULL),
    (5, 37, 4, 2,  930, 2875, NULL),
    (6, 38, 6, 2, 1530, 6340, 'A Linha 2 está com atraso médio de 20 minutos.');

INSERT INTO rota_horarios (rota_id, horario) VALUES
    (1, '05:00'), (1, '08:30'), (1, '12:00'), (1, '15:30'), (1, '19:00'), (1, '22:30'),
    (2, '00:00'), (2, '04:30'), (2, '06:00'), (2, '07:30'), (2, '13:30'), (2, '15:00'), (2, '17:00'), (2, '20:30'),
    (3, '06:15'), (3, '09:45'), (3, '13:15'), (3, '18:45'),
    (4, '04:00'), (4, '10:00'), (4, '16:00'), (4, '22:00'),
    (5, '05:30'), (5, '07:00'), (5, '11:30'), (5, '17:30'), (5, '21:00'),
    (6, '06:00'), (6, '12:00'), (6, '18:00'), (6, '23:45');

INSERT INTO linhas (rota_id, nome, status) VALUES
    (1, 'Linha 1', 'em_espera'), (1, 'Linha 2', 'ja_partiu'),
    (2, 'Linha 1', 'ja_partiu'), (2, 'Linha 2', 'em_espera'),
    (3, 'Linha 1', 'atrasado'),  (3, 'Linha 2', 'em_espera'),
    (4, 'Linha 1', 'ja_partiu'), (4, 'Linha 2', 'ja_partiu'),
    (5, 'Linha 1', 'em_espera'), (5, 'Linha 2', 'em_espera'),
    (6, 'Linha 1', 'ja_partiu'), (6, 'Linha 2', 'atrasado');

INSERT INTO trens (id, codigo, nome, status, rota_id, consumo_km_l, faturamento) VALUES
    (1, '008', 'Trem 08', 'em_rota',    4,    14, 1300000),
    (2, '009', 'Trem 09', 'em_rota',    2,    12,  980000),
    (3, '010', 'Trem 10', 'disponivel', NULL, 15, 1120000),
    (4, '011', 'Trem 11', 'em_rota',    1,    13,  870000),
    (5, '012', 'Trem 12', 'disponivel', NULL, 11,  640000),
    (6, '013', 'Trem 13', 'em_rota',    6,    14, 1450000),
    (7, '014', 'Trem 14', 'em_rota',    5,    13,  910000);

INSERT INTO funcionarios (nome, cargo, ativo_desde, email, telefone, cpf, data_nascimento, endereco) VALUES
    ('Ana Oliveira',       'Gestora de tráfego',    '2018-03-12', 'ana.oliveira@tr4.com',       '(47) 98811-2201', '529.982.247-25', '1987-04-08', 'Rua XV de Novembro, 120 - Joinville/SC'),
    ('Antonio Pereira',    'Maquinista',            '2015-07-01', 'antonio.pereira@tr4.com',    '(47) 98811-2202', NULL, '1979-09-19', NULL),
    ('Carlos Silva',       'Maquinista',            '2020-01-15', 'carlos.silva@tr4.com',       '(41) 98811-2203', NULL, '1991-12-02', NULL),
    ('Francisco Ferreira', 'Técnico de manutenção', '2019-05-20', 'francisco.ferreira@tr4.com', '(41) 98811-2204', NULL, '1985-01-30', NULL),
    ('Luiz Carvalho',      'Operador de sensores',  '2022-02-03', 'luiz.carvalho@tr4.com',      '(47) 98811-2205', NULL, '1996-06-11', NULL),
    ('Maria Silva',        'Analista de cargas',    '2017-10-09', 'maria.silva@tr4.com',        '(11) 98811-2206', NULL, '1989-08-25', NULL),
    ('Paulo Alves',        'Maquinista',            '2016-04-14', 'paulo.alves@tr4.com',        '(13) 98811-2207', NULL, '1982-03-17', NULL);

INSERT INTO tipos_carga (id, nome) VALUES
    (1, 'Carvão'), (2, 'Minério de ferro'), (3, 'Sacos de milho'), (4, 'Soja'),
    (5, 'Contêineres'), (6, 'Combustível'), (7, 'Madeira');

-- Datas relativas a hoje, para os exemplos nunca ficarem "velhos"
INSERT INTO cargas (tipo_carga_id, toneladas, destino_id, data_partida, status, progresso, cadastrado_por) VALUES
    (1,   2, 2, CURDATE() - INTERVAL 2 DAY, 'em_transito', 62,  1),
    (4,  50, 4, CURDATE() - INTERVAL 1 DAY, 'em_transito', 28,  1),
    (3,  10, 3, CURDATE() + INTERVAL 3 DAY, 'aguardando',   0,  1),
    (2, 100, 6, CURDATE() - INTERVAL 6 DAY, 'entregue',   100,  1);

INSERT INTO tipos_sensor (id, modelo, descricao) VALUES
    (1, 'GEL 2747', 'Geolocalização'),
    (2, 'GEL 3100', 'Geolocalização'),
    (3, 'TMP 220',  'Temperatura'),
    (4, 'VBR 15',   'Vibração'),
    (5, 'PSO 80',   'Peso por eixo');

INSERT INTO sensores (tipo_sensor_id, rota_id, status, prazo) VALUES
    (1, 1, 'ativo', NULL),
    (3, 2, 'ativo', NULL),
    (4, 3, 'ativo', NULL);

INSERT INTO alertas (mensagem, tipo, rota_id, criado_em) VALUES
    ('Seu trem está a caminho!',          'info',   NULL, CONCAT(CURDATE(), ' 08:10:00')),
    ('Alerta de chuva na Rota 31!',       'aviso',  3,    CONCAT(CURDATE(), ' 07:45:00')),
    ('A Linha 02 está em atraso.',        'atraso', 6,    CONCAT(CURDATE() - INTERVAL 1 DAY, ' 18:20:00'));


/* ============================================================
   4. GATILHOS (TRIGGERS)
   As mesmas regras automáticas que o JavaScript já faz.
   ============================================================ */

DELIMITER $$

-- Nova carga -> alerta "Nova carga cadastrada..."  (cargas-rotas.js)
CREATE TRIGGER trg_cargas_apos_inserir
AFTER INSERT ON cargas
FOR EACH ROW
BEGIN
    INSERT INTO alertas (mensagem, tipo)
    SELECT CONCAT('Nova carga cadastrada: ', tc.nome, ' (', IF(NEW.toneladas = FLOOR(NEW.toneladas), FLOOR(NEW.toneladas), NEW.toneladas),
                  ' t) para ', c.nome, ', partida em ', DATE_FORMAT(NEW.data_partida, '%d/%m/%Y'), '.'),
           'info'
      FROM tipos_carga tc
      JOIN cidades c ON c.id = NEW.destino_id
     WHERE tc.id = NEW.tipo_carga_id;
END$$

-- Carga chegou a 100% -> vira "entregue"  (monitoramento.js)
CREATE TRIGGER trg_cargas_antes_atualizar
BEFORE UPDATE ON cargas
FOR EACH ROW
BEGIN
    IF NEW.progresso >= 100 THEN
        SET NEW.progresso = 100;
        SET NEW.status = 'entregue';
    END IF;
END$$

-- Carga entregue -> alerta "Carga entregue..."  (monitoramento.js)
CREATE TRIGGER trg_cargas_apos_atualizar
AFTER UPDATE ON cargas
FOR EACH ROW
BEGIN
    IF NEW.status = 'entregue' AND OLD.status <> 'entregue' THEN
        INSERT INTO alertas (mensagem, tipo, trem_id)
        SELECT CONCAT('Carga entregue: ', tc.nome, ' (', IF(NEW.toneladas = FLOOR(NEW.toneladas), FLOOR(NEW.toneladas), NEW.toneladas),
                      ' t) chegou em ', c.nome, '.'),
               'info', NEW.trem_id
          FROM tipos_carga tc
          JOIN cidades c ON c.id = NEW.destino_id
         WHERE tc.id = NEW.tipo_carga_id;
    END IF;
END$$

-- Manutenção registrada -> trem fica "em manutenção" + alerta  (manutencao.js)
CREATE TRIGGER trg_manutencoes_apos_inserir
AFTER INSERT ON manutencoes
FOR EACH ROW
BEGIN
    UPDATE trens
       SET status = 'manutencao', rota_id = NULL
     WHERE id = NEW.trem_id;

    INSERT INTO alertas (mensagem, tipo, rota_id, trem_id)
    SELECT CONCAT('O ', t.nome, ' (Rota ', r.numero, ') entrou em manutenção. Causa: ', NEW.causa, '.'),
           'aviso', NEW.rota_id, NEW.trem_id
      FROM trens t
      JOIN rotas r ON r.id = NEW.rota_id
     WHERE t.id = NEW.trem_id;
END$$

-- Pedido de instalação -> alerta  (sensores.js)
CREATE TRIGGER trg_sensores_apos_inserir
AFTER INSERT ON sensores
FOR EACH ROW
BEGIN
    IF NEW.status = 'instalacao' THEN
        INSERT INTO alertas (mensagem, tipo, rota_id)
        SELECT CONCAT('Instalação do sensor ', ts.modelo, ' na Rota ', r.numero,
                      ' prevista até ', DATE_FORMAT(NEW.prazo, '%d/%m/%Y'), '.'),
               'info', NEW.rota_id
          FROM tipos_sensor ts
          JOIN rotas r ON r.id = NEW.rota_id
         WHERE ts.id = NEW.tipo_sensor_id;
    END IF;
END$$

-- Pedido de remoção -> alerta  (sensores.js)
CREATE TRIGGER trg_sensores_apos_atualizar
AFTER UPDATE ON sensores
FOR EACH ROW
BEGIN
    IF NEW.status = 'remocao' AND OLD.status <> 'remocao' THEN
        INSERT INTO alertas (mensagem, tipo, rota_id)
        SELECT CONCAT('Remoção do sensor ', ts.modelo, ' da Rota ', r.numero,
                      ' prevista até ', DATE_FORMAT(NEW.prazo, '%d/%m/%Y'), '.'),
               'aviso', NEW.rota_id
          FROM tipos_sensor ts
          JOIN rotas r ON r.id = NEW.rota_id
         WHERE ts.id = NEW.tipo_sensor_id;
    END IF;
END$$

DELIMITER ;


/* ============================================================
   5. VIEWS (consultas prontas para as telas)
   Use como uma tabela: SELECT * FROM vw_rotas;
   ============================================================ */

-- gestao-rotas.html / gestao-geral.html
CREATE VIEW vw_rotas AS
SELECT r.id,
       CONCAT('Rota ', r.numero)  AS nome,
       o.nome                     AS origem,
       d.nome                     AS destino,
       r.passageiros_transportados,
       r.quilometragem,
       r.alerta
  FROM rotas r
  JOIN cidades o ON o.id = r.origem_id
  JOIN cidades d ON d.id = r.destino_id;

-- Gráfico "Em rota x Disponíveis" de gestao-geral.html
CREATE VIEW vw_dashboard_trens AS
SELECT COUNT(*)                                          AS total,
       COALESCE(SUM(status = 'em_rota'), 0)              AS em_rota,
       COALESCE(SUM(status = 'disponivel'), 0)           AS disponiveis,
       COALESCE(SUM(status = 'manutencao'), 0)           AS em_manutencao
  FROM trens;

-- monitoramento.html / relatorios.html
CREATE VIEW vw_cargas AS
SELECT c.id,
       tc.nome          AS carga,
       c.toneladas,
       d.nome           AS destino,
       c.data_partida,
       c.status,
       CASE c.status
            WHEN 'aguardando'  THEN 'Aguardando'
            WHEN 'em_transito' THEN 'Em trânsito'
            WHEN 'entregue'    THEN 'Entregue'
       END              AS status_texto,
       c.progresso,
       t.nome           AS trem,
       u.nome           AS cadastrado_por
  FROM cargas c
  JOIN tipos_carga tc   ON tc.id = c.tipo_carga_id
  JOIN cidades d        ON d.id  = c.destino_id
  LEFT JOIN trens t     ON t.id  = c.trem_id
  LEFT JOIN usuarios u  ON u.id  = c.cadastrado_por;

-- relatorios.html (tabela "Manutenções registradas")
CREATE VIEW vw_manutencoes AS
SELECT m.id,
       t.codigo                         AS trem_codigo,
       t.nome                           AS trem,
       CONCAT('Rota ', r.numero)        AS rota,
       COALESCE(tc.nome, 'Sem carga')   AS carga,
       m.causa,
       m.data,
       u.nome                           AS registrado_por
  FROM manutencoes m
  JOIN trens t              ON t.id  = m.trem_id
  JOIN rotas r              ON r.id  = m.rota_id
  LEFT JOIN tipos_carga tc  ON tc.id = m.tipo_carga_id
  LEFT JOIN usuarios u      ON u.id  = m.registrado_por;

-- gestao-sensores.html
CREATE VIEW vw_sensores AS
SELECT s.id,
       ts.modelo                    AS tipo,
       CONCAT('Rota ', r.numero)    AS rota,
       s.status,
       CASE s.status
            WHEN 'ativo'      THEN 'Ativo'
            WHEN 'instalacao' THEN CONCAT('Instalação até ', DATE_FORMAT(s.prazo, '%d/%m/%Y'))
            WHEN 'remocao'    THEN CONCAT('Remoção até ',    DATE_FORMAT(s.prazo, '%d/%m/%Y'))
       END                          AS status_texto,
       s.prazo
  FROM sensores s
  JOIN tipos_sensor ts ON ts.id = s.tipo_sensor_id
  JOIN rotas r         ON r.id  = s.rota_id;

-- Cartões de resumo de relatorios.html
CREATE VIEW vw_relatorio_resumo AS
SELECT (SELECT COUNT(*) FROM cargas)                              AS cargas_cadastradas,
       (SELECT COUNT(*) FROM cargas  WHERE status = 'em_transito')  AS cargas_em_transito,
       (SELECT COUNT(*) FROM cargas  WHERE status = 'entregue')     AS cargas_entregues,
       (SELECT COUNT(*) FROM trens   WHERE status = 'manutencao')   AS trens_em_manutencao,
       (SELECT COUNT(*) FROM sensores WHERE status = 'ativo')       AS sensores_ativos;


/* ============================================================
   6. PROCEDIMENTOS (PROCEDURES)
   Chame com: CALL nome_do_procedimento();
   ============================================================ */

DELIMITER $$

-- Cargas "aguardando" cuja data de partida chegou começam a viagem
-- (o mesmo que iniciarPartidas() do monitoramento.js)
CREATE PROCEDURE sp_iniciar_partidas()
BEGIN
    UPDATE cargas
       SET status = 'em_transito'
     WHERE status = 'aguardando'
       AND data_partida <= CURDATE();
END$$

-- Instalações vencidas viram "ativo"; remoções vencidas são apagadas
-- (o mesmo que concluirPrazosVencidos() do sensores.js)
CREATE PROCEDURE sp_concluir_prazos_sensores()
BEGIN
    UPDATE sensores
       SET status = 'ativo', prazo = NULL
     WHERE status = 'instalacao'
       AND prazo <= CURDATE();

    DELETE FROM sensores
     WHERE status = 'remocao'
       AND prazo <= CURDATE();
END$$

-- Confere e-mail e senha. Devolve o usuário (sem a senha) ou nenhuma linha.
-- Exemplo: CALL sp_login('gestor@tr4.com', '123456');
CREATE PROCEDURE sp_login(IN p_email VARCHAR(120), IN p_senha VARCHAR(255))
BEGIN
    SELECT id, nome, email, cargo
      FROM usuarios
     WHERE email = p_email
       AND senha_hash = SHA2(CONCAT(senha_salt, p_senha), 256);
END$$

-- Cria uma conta nova com salt aleatório (signin.html)
-- Exemplo: CALL sp_criar_usuario('Novo Usuário', 'novo@tr4.com', 'abcdef');
CREATE PROCEDURE sp_criar_usuario(IN p_nome VARCHAR(100), IN p_email VARCHAR(120), IN p_senha VARCHAR(255))
BEGIN
    DECLARE v_salt CHAR(32);

    IF CHAR_LENGTH(p_senha) < 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A senha precisa ter pelo menos 6 caracteres.';
    END IF;

    SET v_salt = MD5(CONCAT(RAND(), NOW(6), p_email));

    INSERT INTO usuarios (nome, email, senha_salt, senha_hash, cargo, ativo_desde)
    VALUES (p_nome, LOWER(p_email), v_salt, SHA2(CONCAT(v_salt, p_senha), 256), NULL, CURDATE());

    SELECT LAST_INSERT_ID() AS id;
END$$

DELIMITER ;

/*
  OPCIONAL: rodar as duas rotinas de prazo automaticamente todo dia.
  Precisa do agendador ligado: SET GLOBAL event_scheduler = ON;

  CREATE EVENT ev_rotinas_diarias
      ON SCHEDULE EVERY 1 DAY STARTS (CURDATE() + INTERVAL 1 DAY)
      DO BEGIN
          CALL sp_iniciar_partidas();
          CALL sp_concluir_prazos_sensores();
      END;
*/


/* ============================================================
   EXEMPLOS DE CONSULTA (um por tela)
   ============================================================

   -- login.html
   CALL sp_login('gestor@tr4.com', '123456');

   -- selecao-usuario.html
   UPDATE usuarios SET cargo = 'maquinista' WHERE id = 1;

   -- gestao-rotas.html (rota escolhida no select)
   SELECT TIME_FORMAT(horario, '%H:%i') AS horario FROM rota_horarios WHERE rota_id = 2 ORDER BY horario;
   SELECT nome, status FROM linhas WHERE rota_id = 2;
   SELECT * FROM vw_rotas WHERE id = 2;

   -- gestao-geral.html
   SELECT * FROM vw_dashboard_trens;
   SELECT passageiros_transportados, quilometragem FROM rotas WHERE numero = 36;
   SELECT consumo_km_l, faturamento FROM trens WHERE codigo = '008';

   -- cadastro-carga.html
   INSERT INTO cargas (tipo_carga_id, toneladas, destino_id, data_partida, cadastrado_por)
   VALUES (4, 20, 4, '2026-11-19', 1);

   -- monitoramento.html
   SELECT * FROM vw_cargas ORDER BY data_partida;
   SELECT * FROM vw_cargas WHERE status = 'em_transito';
   UPDATE cargas SET progresso = progresso + 5 WHERE id = 1;

   -- manutencao.html
   INSERT INTO manutencoes (trem_id, rota_id, tipo_carga_id, causa, data, registrado_por)
   VALUES ((SELECT id FROM trens WHERE codigo = '009'), 2, 3, 'Freio', CURDATE(), 1);

   -- gestao-sensores.html / adicionar-sensor.html / remover-sensor.html
   SELECT * FROM vw_sensores;
   INSERT INTO sensores (tipo_sensor_id, rota_id, status, prazo) VALUES (1, 6, 'instalacao', CURDATE() + INTERVAL 28 DAY);
   UPDATE sensores SET status = 'remocao', prazo = CURDATE() + INTERVAL 28 DAY WHERE id = 1;

   -- funcionarios.html (pesquisa) / perfil-funcionario.html
   SELECT id, nome, cargo FROM funcionarios WHERE nome LIKE '%silva%' OR cargo LIKE '%silva%' ORDER BY nome;
   UPDATE funcionarios SET telefone = '(47) 98888-7777' WHERE id = 6;

   -- alertas.html
   SELECT * FROM alertas WHERE mensagem LIKE '%manutencao%' ORDER BY criado_em DESC;
   DELETE FROM alertas WHERE id = 1;

   -- relatorios.html
   SELECT * FROM vw_relatorio_resumo;
   SELECT * FROM vw_cargas ORDER BY data_partida DESC;
   SELECT * FROM vw_manutencoes ORDER BY data DESC;
*/
