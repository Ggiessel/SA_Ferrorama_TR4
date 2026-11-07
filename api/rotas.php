<?php

require "conexao.php";

exigirCargo();

if (isset($_GET["id"])) {
    $resultado = consultar("SELECT * FROM rotas WHERE id = ?", [$_GET["id"]]);

    if (!$resultado) {
        erro("Rota não encontrada.", null, 404);
    }

    $rota = $resultado[0];
    $rota["horarios"] = array_column(consultar("SELECT horario FROM horarios WHERE rota_id = ? ORDER BY horario", [$rota["id"]]), "horario");
    $rota["linhas"] = consultar("SELECT nome, status FROM linhas WHERE rota_id = ? ORDER BY nome", [$rota["id"]]);

    responder($rota);
}

responder(consultar("SELECT * FROM rotas ORDER BY id"));
