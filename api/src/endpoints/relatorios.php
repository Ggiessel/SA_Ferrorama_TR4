<?php
/*
  /relatorios — números de resumo
  Usado por: relatorios.html
  (As tabelas usam GET /cargas e GET /manutencoes.)
*/

declare(strict_types=1);

// GET /relatorios/resumo
rota('GET', '/relatorios/resumo', function () {
    exigirCargo('gestor', 'maquinista');
    $r = consultarUm('SELECT * FROM vw_relatorio_resumo');
    responder([
        'cargasCadastradas' => (int) $r['cargas_cadastradas'],
        'cargasEmTransito' => (int) $r['cargas_em_transito'],
        'cargasEntregues' => (int) $r['cargas_entregues'],
        'trensEmManutencao' => (int) $r['trens_em_manutencao'],
        'sensoresAtivos' => (int) $r['sensores_ativos'],
    ]);
});
