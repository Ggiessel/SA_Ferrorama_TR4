<?php
/*
  /opcoes — listas usadas para preencher os <select> do site
  Usado por: cadastro-carga.html, manutencao.html, adicionar-sensor.html
*/

declare(strict_types=1);

// Tamanhos oferecidos no cadastro de carga (toneladas)
const TAMANHOS_CARGA = [1, 2, 5, 10, 20, 50, 100];

// GET /opcoes
rota('GET', '/opcoes', function () {
    exigirCargo();

    responder([
        'tiposCarga' => consultar('SELECT id, nome FROM tipos_carga ORDER BY nome'),
        'cidades' => consultar('SELECT id, nome, uf FROM cidades ORDER BY nome'),
        'tiposSensor' => consultar('SELECT id, modelo, descricao FROM tipos_sensor ORDER BY modelo'),
        'rotas' => consultar("SELECT id, CONCAT('Rota ', numero) AS nome FROM rotas ORDER BY numero"),
        'tamanhosCarga' => TAMANHOS_CARGA,
    ]);
});
