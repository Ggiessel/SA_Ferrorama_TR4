<?php

/* Listas usadas nos formulários. O maquinista recebe apenas o próprio trem. */

require "conexao.php";

$usuario = exigirCargo();
$restricao = restricaoTrem($usuario);

$tiposSensor = array_map(fn($t) => $t + ["unidade" => LIMITES_SENSORES[$t["chave"]]["unidade"]], tiposSensor());

$resposta = [
    "tiposCarga" => ["Carvão", "Minério de ferro", "Sacos de milho", "Soja", "Contêineres", "Combustível", "Madeira"],
    "tamanhosCarga" => [1, 2, 5, 10, 20, 50, 100],
    "destinos" => ["Belo Horizonte", "Curitiba", "Joinville", "Rio de Janeiro", "Santos", "São Paulo"],
    "rotas" => consultar("SELECT id, nome, ativa FROM rotas ORDER BY ativa DESC, CHAR_LENGTH(nome), nome")
];

if ($usuario["cargo"] !== "cliente") {
    $resposta["tiposSensor"] = $tiposSensor;
    $resposta["limites"] = LIMITES_SENSORES;
    $resposta["trens"] = $restricao !== null
        ? consultar("SELECT id, nome, codigo, status FROM trens WHERE id = ?", [$restricao])
        : consultar("SELECT id, nome, codigo, status FROM trens ORDER BY codigo");
}
if (eGestor($usuario)) {
    $resposta["maquinistas"] = consultar("SELECT id, nome, trem_id FROM usuarios WHERE cargo = 'maquinista' AND ativo = 1 ORDER BY nome");
}

responder($resposta);
