<?php

require "conexao.php";

exigirCargo("gestor", "maquinista");

function contar($sql)
{
    return (int) consultar($sql)[0]["total"];
}

responder([
    "cargas" => contar("SELECT COUNT(*) AS total FROM cargas"),
    "em_transito" => contar("SELECT COUNT(*) AS total FROM cargas WHERE status = 'em_transito'"),
    "entregues" => contar("SELECT COUNT(*) AS total FROM cargas WHERE status = 'entregue'"),
    "trens_manutencao" => contar("SELECT COUNT(*) AS total FROM trens WHERE status = 'manutencao'"),
    "sensores_ativos" => contar("SELECT COUNT(*) AS total FROM sensores WHERE status = 'ativo'")
]);
