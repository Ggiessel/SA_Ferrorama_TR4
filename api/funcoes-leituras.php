<?php

$limites = [
    "velocidade_kmh" => 80,
    "temperatura_motor_c" => 105,
    "consumo_litros_hora" => 75,
    "vibracao_mm_s" => 7
];

function classificarLeitura($leitura)
{
    global $limites;
    $falhas = [];

    if ((float) $leitura["velocidade_kmh"] > $limites["velocidade_kmh"]) {
        $falhas[] = "Velocidade acima do limite";
    }
    if ((float) $leitura["temperatura_motor_c"] > $limites["temperatura_motor_c"]) {
        $falhas[] = "Temperatura acima do limite";
    }
    if ((float) $leitura["consumo_litros_hora"] > $limites["consumo_litros_hora"]) {
        $falhas[] = "Consumo acima do limite";
    }
    if ((float) $leitura["vibracao_mm_s"] > $limites["vibracao_mm_s"]) {
        $falhas[] = "Vibração acima do limite";
    }

    return $falhas;
}

function gravarLeitura($tremId, $dataHora, $leitura)
{
    executar(
        "INSERT INTO leituras (trem_id, data_hora, velocidade_kmh, temperatura_motor_c, consumo_litros_hora, vibracao_mm_s) VALUES (?, ?, ?, ?, ?, ?)",
        [$tremId, $dataHora, $leitura["velocidade_kmh"], $leitura["temperatura_motor_c"], $leitura["consumo_litros_hora"], $leitura["vibracao_mm_s"]]
    );
}

function buscarLeituras($filtros)
{
    $condicoes = [];
    $valores = [];
    $tremId = (int) ($filtros["trem_id"] ?? 0);
    $status = $filtros["status"] ?? "";
    $dataInicial = $filtros["data_inicial"] ?? "";
    $dataFinal = $filtros["data_final"] ?? "";

    if ($tremId > 0) {
        $condicoes[] = "leituras.trem_id = ?";
        $valores[] = $tremId;
    }
    if (in_array($status, ["em_rota", "disponivel", "manutencao"])) {
        $condicoes[] = "trens.status = ?";
        $valores[] = $status;
    }
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $dataInicial)) {
        $condicoes[] = "leituras.data_hora >= ?";
        $valores[] = $dataInicial . " 00:00:00";
    }
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $dataFinal)) {
        $condicoes[] = "leituras.data_hora <= ?";
        $valores[] = $dataFinal . " 23:59:59";
    }

    $sql = "SELECT leituras.*, trens.nome AS trem, trens.status AS status_trem
            FROM leituras
            JOIN trens ON trens.id = leituras.trem_id";

    if ($condicoes) {
        $sql .= " WHERE " . implode(" AND ", $condicoes);
    }

    $sql .= " ORDER BY leituras.data_hora DESC, leituras.id DESC LIMIT 500";

    $resultado = [];

    foreach (consultar($sql, $valores) as $leitura) {
        $leitura["falhas"] = classificarLeitura($leitura);

        if (!empty($filtros["somente_falha"]) && !$leitura["falhas"]) {
            continue;
        }

        $resultado[] = $leitura;
    }

    return $resultado;
}
