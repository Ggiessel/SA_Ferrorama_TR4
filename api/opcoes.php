<?php

require "conexao.php";

exigirCargo();

responder([
    "tiposCarga" => ["Carvão", "Minério de ferro", "Sacos de milho", "Soja", "Contêineres", "Combustível", "Madeira"],
    "tamanhosCarga" => [1, 2, 5, 10, 20, 50, 100],
    "destinos" => ["Belo Horizonte", "Curitiba", "Joinville", "Rio de Janeiro", "Santos", "São Paulo"],
    "tiposSensor" => tiposSensor(),
    "rotas" => consultar("SELECT id, nome FROM rotas ORDER BY id"),
    "trens" => consultar("SELECT id, nome, codigo FROM trens ORDER BY codigo")
]);
