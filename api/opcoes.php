<?php

require "conexao.php";

exigirCargo();

responder([
    "tiposCarga" => ["Carvão", "Minério de ferro", "Sacos de milho", "Soja", "Contêineres", "Combustível", "Madeira"],
    "tamanhosCarga" => [1, 2, 5, 10, 20, 50, 100],
    "destinos" => ["Belo Horizonte", "Curitiba", "Joinville", "Rio de Janeiro", "Santos", "São Paulo"],
    "tiposSensor" => ["GEL 2747", "GEL 3100", "TMP 220", "VBR 15", "PSO 80"],
    "rotas" => consultar("SELECT id, nome FROM rotas ORDER BY id")
]);
