<?php
/*
  /trens — trens e o resumo do gráfico
  Usado por: gestao-geral.html e manutencao.html
*/

declare(strict_types=1);

// GET /trens
rota('GET', '/trens', function () {
    exigirCargo();

    $trens = consultar("
        SELECT t.id, t.codigo, t.nome, t.status, t.rota_id AS rotaId,
               CONCAT('Rota ', r.numero) AS rota,
               t.consumo_km_l AS consumo, t.faturamento
          FROM trens t
          LEFT JOIN rotas r ON r.id = t.rota_id
         ORDER BY t.codigo");

    responder(array_map(fn ($t) => array_merge($t, [
        'consumo' => (float) $t['consumo'],         // DECIMAL chega como texto no PDO
        'faturamento' => (float) $t['faturamento'],
        'statusTexto' => STATUS_TEXTO[$t['status']] ?? $t['status'],
    ]), $trens));
});

// GET /trens/resumo   -> números do gráfico "Em rota x Disponíveis"
rota('GET', '/trens/resumo', function () {
    exigirCargo();
    $r = consultarUm('SELECT * FROM vw_dashboard_trens');
    responder([
        'total' => (int) $r['total'],
        'emRota' => (int) $r['em_rota'],
        'disponiveis' => (int) $r['disponiveis'],
        'emManutencao' => (int) $r['em_manutencao'],
    ]);
});
