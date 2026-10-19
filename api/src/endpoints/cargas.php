<?php
/*
  /cargas — cadastro e monitoramento de cargas
  Usado por: cadastro-carga.html, monitoramento.html e relatorios.html
*/

declare(strict_types=1);

const SQL_CARGAS = "
    SELECT id, carga AS tipo, toneladas, destino, data_partida AS partida,
           status, status_texto AS statusTexto, progresso
      FROM vw_cargas";

function listarCargas(?string $status): array
{
    if ($status && in_array($status, ['aguardando', 'em_transito', 'entregue'], true)) {
        $cargas = consultar(SQL_CARGAS . ' WHERE status = ? ORDER BY data_partida', [$status]);
    } else {
        $cargas = consultar(SQL_CARGAS . ' ORDER BY data_partida');
    }
    // DECIMAL chega como texto no PDO: converte para número
    return array_map(fn ($c) => array_merge($c, ['toneladas' => (float) $c['toneladas']]), $cargas);
}

// GET /cargas?status=em_transito
rota('GET', '/cargas', function () {
    exigirCargo();
    executar('CALL sp_iniciar_partidas()'); // cargas cuja partida chegou começam a viagem
    responder(listarCargas($_GET['status'] ?? null));
});

// POST /cargas   { tipoCargaId, toneladas, destinoId, partida: "AAAA-MM-DD" }
rota('POST', '/cargas', function () {
    $usuario = exigirCargo('gestor');
    $dados = corpo();

    $tipoCargaId = (int) ($dados['tipoCargaId'] ?? 0);
    $destinoId = (int) ($dados['destinoId'] ?? 0);
    $toneladas = (float) ($dados['toneladas'] ?? 0);
    $partida = texto($dados, 'partida');

    if (!consultarUm('SELECT id FROM tipos_carga WHERE id = ?', [$tipoCargaId])) {
        throw new ErroApi(400, 'Escolha qual carga o trem vai levar.', 'tipoCarga');
    }
    if ($toneladas <= 0) {
        throw new ErroApi(400, 'Escolha o tamanho da carga.', 'tamanhoCarga');
    }
    if (!consultarUm('SELECT id FROM cidades WHERE id = ?', [$destinoId])) {
        throw new ErroApi(400, 'Escolha o destino da carga.', 'destinoCarga');
    }
    if (!validarDataISO($partida)) {
        throw new ErroApi(400, 'Data de partida inválida.', 'diaPartida');
    }
    if ($partida < hojeISO()) {
        throw new ErroApi(400, 'A data de partida não pode estar no passado.', 'diaPartida');
    }

    // O gatilho trg_cargas_apos_inserir cria o alerta "Nova carga cadastrada"
    executar(
        'INSERT INTO cargas (tipo_carga_id, toneladas, destino_id, data_partida, status, cadastrado_por)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$tipoCargaId, $toneladas, $destinoId, $partida,
            $partida <= hojeISO() ? 'em_transito' : 'aguardando', $usuario['id']]
    );

    $carga = consultarUm(SQL_CARGAS . ' WHERE id = ?', [ultimoId()]);
    $carga['toneladas'] = (float) $carga['toneladas'];
    responder($carga, 201);
});

/*
  POST /cargas/simular
  SIMULAÇÃO do rastreamento: avança de 2% a 7% as cargas em trânsito.
  A tela de monitoramento chama a cada 3 segundos. Quando uma carga chega
  a 100%, o gatilho trg_cargas_antes_atualizar marca "entregue" e o
  trg_cargas_apos_atualizar cria o alerta.
  Com sensores de verdade, este endpoint seria trocado pelos dados reais.
*/
rota('POST', '/cargas/simular', function () {
    exigirCargo();

    $antes = array_column(consultar("SELECT id FROM cargas WHERE status = 'em_transito'"), 'id');

    executar("
        UPDATE cargas
           SET progresso = LEAST(100, progresso + 2 + FLOOR(RAND() * 6))
         WHERE status = 'em_transito'");

    $entregues = [];
    if ($antes) {
        $entregues = consultar(
            "SELECT carga AS tipo, destino FROM vw_cargas
              WHERE status = 'entregue' AND id IN (" . marcadores($antes) . ')',
            $antes
        );
    }

    responder(['cargas' => listarCargas($_GET['status'] ?? null), 'entregues' => $entregues]);
});
