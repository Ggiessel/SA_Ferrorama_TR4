<?php

/*
 * Leituras dos sensores.
 * GET                  consulta com filtros (trem_id, sensor_id, chave, nivel, somente_falha, status, data_inicial, data_final)
 * POST                 registra uma leitura: {sensor_id, valor}
 *                      ou, por compatibilidade com dispositivos, {trem_id, velocidade_kmh, temperatura_motor_c,
 *                      consumo_litros_hora, vibracao_mm_s} distribuídos aos sensores do trem
 * POST ?acao=simular   simulador (somente gestor): {trem_id, quantidade, sensor_id?}
 */

require "conexao.php";
require "funcoes-leituras.php";

$usuario = exigirCargo("gestor", "maquinista");
$acao = $_GET["acao"] ?? "";

if (metodo() === "POST" && $acao === "simular") {
    if (!eGestor($usuario)) {
        erro("Só o gestor pode usar o simulador.", null, 403);
    }

    $dados = dadosRecebidos();
    $quantidade = inteiro($dados["quantidade"] ?? 0);
    $tremId = inteiro($dados["trem_id"] ?? 0);
    $sensorId = inteiro($dados["sensor_id"] ?? 0);
    $trem = consultarUm("SELECT id, nome FROM trens WHERE id = ?", [$tremId]);

    if (!$trem) {
        erro("Escolha o trem.", "tremSimulador");
    }
    if ($quantidade < 1 || $quantidade > 100) {
        erro("Informe uma quantidade entre 1 e 100 leituras por sensor.", "quantidadeSimulador");
    }

    $condicao = $sensorId ? " AND sensores.id = ?" : "";
    $valores = $sensorId ? [$tremId, $sensorId] : [$tremId];
    $sensores = consultar(
        "SELECT sensores.*, trens.nome AS trem FROM sensores JOIN trens ON trens.id = sensores.trem_id
         WHERE sensores.trem_id = ? AND sensores.status = 'ativo'" . $condicao,
        $valores
    );

    if (!$sensores) {
        erro("O " . $trem["nome"] . " não tem sensores ativos" . ($sensorId ? " com esse código" : "") . ". Ative um sensor antes de simular.", "tremSimulador");
    }

    $contagem = ["normal" => 0, "atencao" => 0, "critico" => 0];
    $inicio = time() - ($quantidade - 1) * 300;

    global $pdo;
    $pdo->beginTransaction();
    for ($i = 0; $i < $quantidade; $i++) {
        $momento = date("Y-m-d H:i:s", $inicio + $i * 300);
        foreach ($sensores as $sensor) {
            $resultado = registrarLeitura($sensor, valorSimulado($sensor["chave"]), $momento);
            $contagem[$resultado["nivel"]]++;
        }
    }
    $pdo->commit();

    responder([
        "geradas" => array_sum($contagem),
        "sensores" => count($sensores),
        "niveis" => $contagem
    ], 201);
}

if (metodo() === "POST") {
    $dados = dadosRecebidos();

    // Formato 1: uma leitura de um sensor.
    if (isset($dados["sensor_id"])) {
        $sensor = buscarSensor(inteiro($dados["sensor_id"]));

        if (!$sensor) {
            erro("Sensor não encontrado. Nenhuma leitura foi gravada.", "sensorLeitura", 404);
        }
        exigirAcessoTrem($usuario, $sensor["trem_id"]);
        if ($sensor["status"] !== "ativo") {
            erro("O sensor não está ativo. Nenhuma leitura foi gravada.", "sensorLeitura", 409);
        }
        if (!is_numeric($dados["valor"] ?? null) || abs((float) $dados["valor"]) > 999999) {
            erro("Informe um valor numérico para a leitura.", "valorLeitura");
        }

        $resultado = registrarLeitura($sensor, $dados["valor"]);
        responder(["leitura_id" => $resultado["id"], "nivel" => $resultado["nivel"]], 201);
    }

    // Formato 2 (compatível com o dispositivo da referência): trem_id + 4 grandezas.
    $tremId = inteiro($dados["trem_id"] ?? 0);
    if (!consultarUm("SELECT id FROM trens WHERE id = ?", [$tremId])) {
        erro("Trem não encontrado. Nenhuma leitura foi gravada.", null, 404);
    }
    exigirAcessoTrem($usuario, $tremId);

    $mapa = [
        "velocidade_kmh" => "velocidade",
        "temperatura_motor_c" => "temperatura_motor",
        "consumo_litros_hora" => "consumo",
        "vibracao_mm_s" => "vibracao"
    ];
    foreach ($mapa as $campo => $chave) {
        if (!is_numeric($dados[$campo] ?? null)) {
            erro("Informe o valor de $campo.");
        }
    }

    $gravadas = [];
    foreach ($mapa as $campo => $chave) {
        $sensor = consultarUm(
            "SELECT sensores.*, trens.nome AS trem FROM sensores JOIN trens ON trens.id = sensores.trem_id
             WHERE sensores.trem_id = ? AND sensores.chave = ? AND sensores.status = 'ativo' ORDER BY sensores.id LIMIT 1",
            [$tremId, $chave]
        );
        if ($sensor) {
            $resultado = registrarLeitura($sensor, $dados[$campo]);
            $gravadas[] = ["grandeza" => $chave, "sensor_id" => (int) $sensor["id"], "nivel" => $resultado["nivel"]];
        }
    }

    if (!$gravadas) {
        erro("O trem não tem sensores ativos para essas grandezas. Nenhuma leitura foi gravada.", null, 409);
    }

    responder(["gravadas" => $gravadas], 201);
}

if (metodo() !== "GET") {
    erro("Método não permitido.", null, 405);
}

$resultado = buscarLeituras($_GET, $usuario, inteiro($_GET["limite"] ?? 500) ?: 500);
$resultado["limites"] = LIMITES_SENSORES;

responder($resultado);
