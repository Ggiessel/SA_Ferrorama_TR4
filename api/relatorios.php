<?php

/*
 * Relatórios: GET ?tipo=desempenho|energia|eficiencia|manutencao
 * Filtros: data_inicial, data_final (inclusiva), trem_id, status e tipo_manutencao (manutenção).
 * Gestor: frota inteira. Maquinista: somente o próprio trem.
 */

require "conexao.php";
require_once __DIR__ . "/../includes/relatorios.php";

$usuario = exigirCargo("gestor", "maquinista");

if (metodo() !== "GET") {
    erro("Método não permitido.", null, 405);
}

if (empty($_GET["tipo"])) {
    responder(["relatorios" => RELATORIOS]);
}

responder(gerarRelatorio($_GET["tipo"], $_GET, $usuario));
