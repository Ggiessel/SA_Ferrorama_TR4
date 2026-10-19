<?php
/*
  /manutencoes — registro de trens em manutenção
  Usado por: manutencao.html e relatorios.html
*/

declare(strict_types=1);

// GET /manutencoes
rota('GET', '/manutencoes', function () {
    exigirCargo('gestor', 'maquinista');
    responder(consultar('
        SELECT id, trem_codigo AS codigo, trem, rota, carga, causa, data
          FROM vw_manutencoes
         ORDER BY data DESC, id DESC'));
});

// POST /manutencoes   { codigo: "008", rotaId, tipoCargaId (ou null = sem carga), causa }
rota('POST', '/manutencoes', function () {
    $usuario = exigirCargo('gestor', 'maquinista');
    $dados = corpo();

    $numeros = substr(soNumeros($dados['codigo'] ?? ''), 0, 3);
    $rotaId = (int) ($dados['rotaId'] ?? 0);
    $tipoCargaId = !empty($dados['tipoCargaId']) ? (int) $dados['tipoCargaId'] : null;
    $causa = texto($dados, 'causa');

    if ($numeros === '') {
        throw new ErroApi(400, 'Informe o código do trem (3 números).', 'codigoTrem');
    }

    $trem = consultarUm('SELECT id, nome FROM trens WHERE codigo = ?', [str_pad($numeros, 3, '0', STR_PAD_LEFT)]);
    if (!$trem) {
        $codigos = array_column(consultar('SELECT codigo FROM trens ORDER BY codigo'), 'codigo');
        throw new ErroApi(404, 'Nenhum trem com esse código. Códigos cadastrados: ' . implode(', ', $codigos) . '.', 'codigoTrem');
    }
    if (!consultarUm('SELECT id FROM rotas WHERE id = ?', [$rotaId])) {
        throw new ErroApi(400, 'Escolha a rota.', 'rotaManutencao');
    }
    if (mb_strlen($causa) < 3) {
        throw new ErroApi(400, 'Descreva a causa (ex.: Batida, Freio, Motor).', 'causaManutencao');
    }

    // O gatilho trg_manutencoes_apos_inserir muda o trem para "manutenção" e cria o alerta
    executar(
        'INSERT INTO manutencoes (trem_id, rota_id, tipo_carga_id, causa, data, registrado_por)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$trem['id'], $rotaId, $tipoCargaId, $causa, hojeISO(), $usuario['id']]
    );

    responder(['id' => ultimoId(), 'trem' => $trem['nome']], 201);
});
