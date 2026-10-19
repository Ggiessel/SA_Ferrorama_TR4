<?php
/*
  /sensores — sensores das rotas (só para o gestor)
  Usado por: gestao-sensores.html, adicionar-sensor.html e remover-sensor.html

  Instalar ou remover leva até 4 semanas (prazo). Enquanto isso o
  sensor fica com status "instalacao" ou "remocao".
*/

declare(strict_types=1);

const PRAZO_SENSOR_DIAS = 28;

// GET /sensores
rota('GET', '/sensores', function () {
    exigirCargo('gestor');
    executar('CALL sp_concluir_prazos_sensores()'); // fecha os prazos que já venceram

    responder(consultar("
        SELECT s.id, ts.modelo AS tipo, s.tipo_sensor_id AS tipoId,
               s.rota_id AS rotaId, CONCAT('Rota ', r.numero) AS rota,
               s.status, v.status_texto AS statusTexto, s.prazo
          FROM sensores s
          JOIN tipos_sensor ts ON ts.id = s.tipo_sensor_id
          JOIN rotas r         ON r.id  = s.rota_id
          JOIN vw_sensores v   ON v.id  = s.id
         ORDER BY r.numero, ts.modelo"));
});

// POST /sensores   { tipoSensorId, rotaId }  -> pedido de instalação
rota('POST', '/sensores', function () {
    exigirCargo('gestor');
    $dados = corpo();
    $tipoSensorId = (int) ($dados['tipoSensorId'] ?? 0);
    $rotaId = (int) ($dados['rotaId'] ?? 0);

    $tipo = consultarUm('SELECT modelo FROM tipos_sensor WHERE id = ?', [$tipoSensorId]);
    if (!$tipo) {
        throw new ErroApi(400, 'Escolha o tipo de sensor.', 'tipoSensor');
    }
    if (!consultarUm('SELECT id FROM rotas WHERE id = ?', [$rotaId])) {
        throw new ErroApi(400, 'Escolha o local do sensor.', 'localSensor');
    }

    try {
        // O gatilho trg_sensores_apos_inserir cria o alerta de instalação
        executar(
            "INSERT INTO sensores (tipo_sensor_id, rota_id, status, prazo)
             VALUES (?, ?, 'instalacao', CURDATE() + INTERVAL ? DAY)",
            [$tipoSensorId, $rotaId, PRAZO_SENSOR_DIAS]
        );
    } catch (PDOException $erro) {
        // A chave única uk_sensor_por_rota impede o mesmo modelo duas vezes na rota
        if ((int) ($erro->errorInfo[1] ?? 0) === 1062) {
            throw new ErroApi(409, "Já existe um {$tipo['modelo']} nessa rota (instalado ou a caminho).", 'localSensor');
        }
        throw $erro;
    }

    responder(['id' => ultimoId()], 201);
});

// PUT /sensores/5/remover   -> pedido de remoção
rota('PUT', '/sensores/(\d+)/remover', function (int $id) {
    exigirCargo('gestor');

    $sensor = consultarUm('SELECT status FROM sensores WHERE id = ?', [$id]);
    if (!$sensor) {
        throw new ErroApi(404, 'Sensor não encontrado.');
    }
    if ($sensor['status'] !== 'ativo') {
        throw new ErroApi(409, 'Só dá para remover um sensor ativo.');
    }

    // O gatilho trg_sensores_apos_atualizar cria o alerta de remoção
    executar(
        "UPDATE sensores SET status = 'remocao', prazo = CURDATE() + INTERVAL ? DAY WHERE id = ?",
        [PRAZO_SENSOR_DIAS, $id]
    );
    responder(['id' => $id]);
});
