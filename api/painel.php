<?php

require "conexao.php";
require "funcoes-leituras.php";

exigirCargo();

$resumo = consultar(
    "SELECT trens.id, trens.nome,
            COUNT(leituras.id) AS total_leituras,
            AVG(leituras.velocidade_kmh) AS media_velocidade,
            MAX(leituras.velocidade_kmh) AS maior_velocidade,
            AVG(leituras.temperatura_motor_c) AS media_temperatura,
            MAX(leituras.temperatura_motor_c) AS maior_temperatura
     FROM trens
     LEFT JOIN leituras ON leituras.trem_id = trens.id
     GROUP BY trens.id, trens.nome, trens.codigo
     ORDER BY trens.codigo"
);

$alertas = [];

foreach (consultar(
    "SELECT leituras.*, trens.nome AS trem
     FROM leituras
     JOIN trens ON trens.id = leituras.trem_id
     WHERE leituras.velocidade_kmh > ?
        OR leituras.temperatura_motor_c > ?
        OR leituras.consumo_litros_hora > ?
        OR leituras.vibracao_mm_s > ?
     ORDER BY leituras.data_hora DESC
     LIMIT 15",
    [$limites["velocidade_kmh"], $limites["temperatura_motor_c"], $limites["consumo_litros_hora"], $limites["vibracao_mm_s"]]
) as $leitura) {
    $leitura["falhas"] = classificarLeitura($leitura);
    $alertas[] = $leitura;
}

responder([
    "limites" => $limites,
    "total_leituras" => (int) consultar("SELECT COUNT(*) AS total FROM leituras")[0]["total"],
    "resumo" => $resumo,
    "alertas" => $alertas
]);
