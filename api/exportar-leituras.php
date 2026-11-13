<?php

require "conexao.php";
require "funcoes-leituras.php";

exigirCargo("gestor", "maquinista");

$leituras = buscarLeituras($_GET);

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=leituras.csv");

$saida = fopen("php://output", "w");
fwrite($saida, "\xEF\xBB\xBF");

fputcsv($saida, ["Trem", "Data e hora", "Velocidade (km/h)", "Temperatura do motor (°C)", "Consumo (L/h)", "Vibração (mm/s)", "Situação"], ";", "\"", "");

foreach ($leituras as $leitura) {
    fputcsv($saida, [
        $leitura["trem"],
        date("d/m/Y H:i", strtotime($leitura["data_hora"])),
        number_format($leitura["velocidade_kmh"], 2, ",", ""),
        number_format($leitura["temperatura_motor_c"], 2, ",", ""),
        number_format($leitura["consumo_litros_hora"], 2, ",", ""),
        number_format($leitura["vibracao_mm_s"], 2, ",", ""),
        $leitura["falhas"] ? implode(" / ", $leitura["falhas"]) : "Normal"
    ], ";", "\"", "");
}

fclose($saida);
