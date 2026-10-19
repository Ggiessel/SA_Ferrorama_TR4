<?php
/*
  /alertas — alertas e notificações
  Usado por: alertas.html
  Os alertas são criados pelos gatilhos do banco (cargas, manutenções, sensores).
*/

declare(strict_types=1);

// GET /alertas?busca=chuva
rota('GET', '/alertas', function () {
    exigirCargo();
    $busca = trim((string) ($_GET['busca'] ?? ''));

    $sql = 'SELECT id, mensagem, tipo, criado_em AS data FROM alertas '
        . ($busca !== '' ? 'WHERE mensagem LIKE ? ' : '')
        . 'ORDER BY criado_em DESC, id DESC LIMIT 200';

    responder(consultar($sql, $busca !== '' ? ["%$busca%"] : []));
});

// DELETE /alertas/7   (só gestor)
rota('DELETE', '/alertas/(\d+)', function (int $id) {
    exigirCargo('gestor');
    if (executar('DELETE FROM alertas WHERE id = ?', [$id]) === 0) {
        throw new ErroApi(404, 'Alerta não encontrado.');
    }
    responder(null, 204);
});
