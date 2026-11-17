<?php

/*
 * Limites dos sensores do Ferrorama TR4.
 *
 * Cada grandeza (chave) tem dois limites:
 *  - "atencao": a partir dele a leitura é classificada como Atenção;
 *  - "critico": a partir dele a leitura é classificada como Crítico.
 * "direcao" indica se o problema é o valor ficar ACIMA ("acima") ou ABAIXO ("abaixo") do limite.
 *
 * Os limites críticos de velocidade, temperatura do motor, consumo e vibração são os mesmos
 * da referência técnica do projeto (80 km/h, 105 °C, 75 L/h e 7 mm/s).
 * "faixa" é usada apenas pelo simulador para sortear valores plausíveis.
 */

const LIMITES_SENSORES = [
    "velocidade" => ["nome" => "Velocidade", "unidade" => "km/h", "direcao" => "acima", "atencao" => 70, "critico" => 80, "faixa" => [20, 90]],
    "temperatura_motor" => ["nome" => "Temperatura do motor", "unidade" => "°C", "direcao" => "acima", "atencao" => 95, "critico" => 105, "faixa" => [65, 112]],
    "consumo" => ["nome" => "Consumo de combustível", "unidade" => "L/h", "direcao" => "acima", "atencao" => 65, "critico" => 75, "faixa" => [25, 82]],
    "vibracao" => ["nome" => "Vibração", "unidade" => "mm/s", "direcao" => "acima", "atencao" => 5.5, "critico" => 7, "faixa" => [0.5, 8.5]],
    "temperatura_escape" => ["nome" => "Temperatura dos gases de escape", "unidade" => "°C", "direcao" => "acima", "atencao" => 480, "critico" => 550, "faixa" => [300, 580]],
    "temperatura_rolamento" => ["nome" => "Temperatura do rolamento", "unidade" => "°C", "direcao" => "acima", "atencao" => 70, "critico" => 85, "faixa" => [35, 92]],
    "pressao_freio" => ["nome" => "Pressão do freio", "unidade" => "kPa", "direcao" => "abaixo", "atencao" => 420, "critico" => 350, "faixa" => [320, 660]],
    "temperatura_carga" => ["nome" => "Temperatura da carga", "unidade" => "°C", "direcao" => "acima", "atencao" => 30, "critico" => 35, "faixa" => [15, 38]]
];

const NOMES_NIVEL = ["normal" => "Normal", "atencao" => "Atenção", "critico" => "Crítico"];

function limitesSensores()
{
    return LIMITES_SENSORES;
}

/** Classifica um valor de uma grandeza em "normal", "atencao" ou "critico". */
function classificarValor($chave, $valor)
{
    if (!isset(LIMITES_SENSORES[$chave]) || !is_numeric($valor)) {
        return "normal";
    }

    $limite = LIMITES_SENSORES[$chave];
    $valor = (float) $valor;

    if ($limite["direcao"] === "abaixo") {
        if ($valor < $limite["critico"]) {
            return "critico";
        }
        return $valor < $limite["atencao"] ? "atencao" : "normal";
    }

    if ($valor > $limite["critico"]) {
        return "critico";
    }
    return $valor > $limite["atencao"] ? "atencao" : "normal";
}
