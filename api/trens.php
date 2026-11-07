<?php

require "conexao.php";

exigirCargo();

responder(consultar(
    "SELECT trens.*, rotas.nome AS rota
     FROM trens
     LEFT JOIN rotas ON rotas.id = trens.rota_id
     ORDER BY trens.codigo"
));
