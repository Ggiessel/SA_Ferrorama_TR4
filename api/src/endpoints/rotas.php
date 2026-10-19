<?php
/*
  /rotas — rotas ferroviárias
  Usado por: gestao-rotas.html e gestao-geral.html
*/

declare(strict_types=1);

const SQL_ROTA = "
    SELECT r.id, r.numero, CONCAT('Rota ', r.numero) AS nome,
           o.nome AS origem, d.nome AS destino,
           r.passageiros_transportados AS passageiros, r.quilometragem, r.alerta
      FROM rotas r
      JOIN cidades o ON o.id = r.origem_id
      JOIN cidades d ON d.id = r.destino_id";

// GET /rotas
rota('GET', '/rotas', function () {
    exigirCargo();
    responder(consultar(SQL_ROTA . ' ORDER BY r.numero'));
});

// GET /rotas/2   -> rota + horários + linhas
rota('GET', '/rotas/(\d+)', function (int $id) {
    exigirCargo();

    $rota = consultarUm(SQL_ROTA . ' WHERE r.id = ?', [$id]);
    if (!$rota) {
        throw new ErroApi(404, 'Rota não encontrada.');
    }

    $horarios = consultar(
        "SELECT TIME_FORMAT(horario, '%H:%i') AS horario FROM rota_horarios WHERE rota_id = ? ORDER BY horario",
        [$id]
    );
    $linhas = consultar('SELECT nome, status FROM linhas WHERE rota_id = ? ORDER BY nome', [$id]);

    $rota['horarios'] = array_column($horarios, 'horario');
    $rota['linhas'] = array_map(
        fn ($l) => $l + ['statusTexto' => STATUS_TEXTO[$l['status']] ?? $l['status']],
        $linhas
    );
    responder($rota);
});
