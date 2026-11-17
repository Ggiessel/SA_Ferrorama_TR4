<?php

/*
 * Funções de leituras dos sensores. Os limites e a classificação
 * (Normal / Atenção / Crítico) ficam em includes/limites.php.
 */

const ORDEM_NIVEL = ["normal" => 0, "atencao" => 1, "critico" => 2];

/** Sensor com o nome do trem, ou null. */
function buscarSensor($id)
{
    return consultarUm(
        "SELECT sensores.*, trens.nome AS trem FROM sensores JOIN trens ON trens.id = sensores.trem_id WHERE sensores.id = ?",
        [$id]
    );
}

/**
 * Grava uma leitura de um sensor, classifica o valor e cria ou atualiza o alerta
 * do sensor quando o nível não é normal. Devolve ["id", "nivel"].
 */
function registrarLeitura($sensor, $valor, $dataHora = null)
{
    $valor = round((float) $valor, 2);
    $nivel = classificarValor($sensor["chave"], $valor);
    $dataHora = $dataHora ?: date("Y-m-d H:i:s");

    executar(
        "INSERT INTO leituras (sensor_id, trem_id, data_hora, valor, nivel) VALUES (?, ?, ?, ?, ?)",
        [$sensor["id"], $sensor["trem_id"], $dataHora, $valor, $nivel]
    );
    $leituraId = ultimoId();

    if ($nivel !== "normal") {
        registrarAlertaSensor($sensor, $leituraId, $valor, $nivel, $dataHora);
    }

    return ["id" => $leituraId, "nivel" => $nivel];
}

function registrarAlertaSensor($sensor, $leituraId, $valor, $nivel, $dataHora)
{
    $mensagem = $sensor["trem"] . " - " . $sensor["grandeza"] . " (" . $sensor["tipo"] . "): "
        . numeroBr($valor) . " " . $sensor["unidade"]
        . ($nivel === "critico" ? " - nível crítico." : " - nível de atenção.");

    $existente = consultarUm(
        "SELECT id, nivel FROM alertas WHERE sensor_id = ? AND situacao = 'ativo' AND origem = 'sensor' ORDER BY id DESC LIMIT 1",
        [$sensor["id"]]
    );

    if (!$existente) {
        criarAlerta($mensagem, $nivel, [
            "origem" => "sensor",
            "trem_id" => $sensor["trem_id"],
            "sensor_id" => $sensor["id"],
            "leitura_id" => $leituraId
        ]);
        return;
    }

    // Já existe alerta ativo do sensor: mantém um só, com a gravidade mais alta e a leitura mais recente.
    if (ORDEM_NIVEL[$nivel] >= ORDEM_NIVEL[$existente["nivel"]]) {
        executar(
            "UPDATE alertas SET mensagem = ?, nivel = ?, leitura_id = ?, data = ? WHERE id = ?",
            [$mensagem, $nivel, $leituraId, $dataHora, $existente["id"]]
        );
    }
}

/** Sorteia um valor plausível para a grandeza: maioria normal, alguns em atenção e poucos críticos. */
function valorSimulado($chave)
{
    $limite = LIMITES_SENSORES[$chave];
    [$minimo, $maximo] = $limite["faixa"];
    $sorteio = random_int(1, 100);
    $aleatorio = fn($a, $b) => $a + (random_int(0, 10000) / 10000) * ($b - $a);

    if ($limite["direcao"] === "abaixo") {
        if ($sorteio <= 80) {
            return $aleatorio($limite["atencao"], $maximo);
        }
        if ($sorteio <= 95) {
            return $aleatorio($limite["critico"], $limite["atencao"]);
        }
        return $aleatorio($minimo, $limite["critico"]);
    }

    if ($sorteio <= 80) {
        return $aleatorio($minimo, $limite["atencao"]);
    }
    if ($sorteio <= 95) {
        return $aleatorio($limite["atencao"], $limite["critico"]);
    }
    return $aleatorio($limite["critico"], $maximo);
}

/**
 * Consulta de leituras com filtros. Para o maquinista, a consulta é sempre
 * restrita ao trem dele (a restrição é aplicada no SQL).
 */
function buscarLeituras($filtros, $usuario, $limite = 500)
{
    $condicoes = [];
    $valores = [];
    $tremId = inteiro($filtros["trem_id"] ?? 0);
    $sensorId = inteiro($filtros["sensor_id"] ?? 0);
    $nivel = $filtros["nivel"] ?? "";
    $chave = $filtros["chave"] ?? "";
    $status = $filtros["status"] ?? "";
    $restricao = restricaoTrem($usuario);

    if ($restricao !== null) {
        $condicoes[] = "leituras.trem_id = ?";
        $valores[] = $restricao;
    }
    if ($tremId > 0) {
        $condicoes[] = "leituras.trem_id = ?";
        $valores[] = $tremId;
    }
    if ($sensorId > 0) {
        $condicoes[] = "leituras.sensor_id = ?";
        $valores[] = $sensorId;
    }
    if (isset(ORDEM_NIVEL[$nivel])) {
        $condicoes[] = "leituras.nivel = ?";
        $valores[] = $nivel;
    }
    if (!empty($filtros["somente_falha"])) {
        $condicoes[] = "leituras.nivel <> 'normal'";
    }
    if (isset(LIMITES_SENSORES[$chave])) {
        $condicoes[] = "sensores.chave = ?";
        $valores[] = $chave;
    }
    if (in_array($status, ["em_rota", "disponivel", "manutencao", "inativo"], true)) {
        $condicoes[] = "trens.status = ?";
        $valores[] = $status;
    }

    filtroPeriodo("leituras.data_hora", $filtros["data_inicial"] ?? "", $filtros["data_final"] ?? "", $condicoes, $valores);

    $where = onde($condicoes);
    $limite = max(1, min(5000, (int) $limite));

    $total = consultarUm(
        "SELECT COUNT(*) AS total, SUM(leituras.nivel = 'atencao') AS atencao, SUM(leituras.nivel = 'critico') AS critico
         FROM leituras
         JOIN sensores ON sensores.id = leituras.sensor_id
         JOIN trens ON trens.id = leituras.trem_id" . $where,
        $valores
    );

    $leituras = consultar(
        "SELECT leituras.id, leituras.sensor_id, leituras.trem_id, leituras.data_hora, leituras.valor, leituras.nivel,
                sensores.tipo, sensores.grandeza, sensores.chave, sensores.unidade,
                trens.nome AS trem, trens.status AS status_trem
         FROM leituras
         JOIN sensores ON sensores.id = leituras.sensor_id
         JOIN trens ON trens.id = leituras.trem_id" . $where . "
         ORDER BY leituras.data_hora DESC, leituras.id DESC
         LIMIT $limite",
        $valores
    );

    return [
        "total" => (int) $total["total"],
        "atencao" => (int) $total["atencao"],
        "critico" => (int) $total["critico"],
        "leituras" => $leituras
    ];
}

/**
 * Trecho SQL que junta a leitura mais recente de cada sensor (pela data e, no empate, pelo id).
 * Sensores sem leitura continuam no resultado, com as colunas da leitura nulas.
 */
function sqlUltimaLeitura()
{
    return "LEFT JOIN leituras ultima ON ultima.id = (
                SELECT l2.id FROM leituras l2
                WHERE l2.sensor_id = sensores.id
                ORDER BY l2.data_hora DESC, l2.id DESC
                LIMIT 1
            )";
}
