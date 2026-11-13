<?php

require "conexao.php";
require "funcoes-leituras.php";

$usuario = exigirCargo("gestor", "maquinista");
$metodo = $_SERVER["REQUEST_METHOD"];
$acao = $_GET["acao"] ?? "";

if ($metodo === "POST" && $acao === "simular") {
    if ($usuario["cargo"] !== "gestor") {
        erro("Só o gestor pode usar o simulador.", null, 403);
    }

    $dados = dadosRecebidos();
    $quantidade = (int) ($dados["quantidade"] ?? 0);
    $trem = consultar("SELECT id, nome FROM trens WHERE id = ?", [$dados["trem_id"] ?? 0]);

    if (!$trem) {
        erro("Escolha o trem.", "tremSimulador");
    }
    if ($quantidade < 1 || $quantidade > 200) {
        erro("Informe uma quantidade entre 1 e 200.", "quantidadeSimulador");
    }

    $momento = time() - ($quantidade * 300);
    $acimaDoLimite = 0;

    for ($i = 0; $i < $quantidade; $i++) {
        $momento = $momento + 300;

        $leitura = [
            "velocidade_kmh" => rand(0, 9000) / 100,
            "temperatura_motor_c" => rand(6000, 11500) / 100,
            "consumo_litros_hora" => rand(2000, 9000) / 100,
            "vibracao_mm_s" => rand(50, 900) / 100
        ];

        gravarLeitura($trem[0]["id"], date("Y-m-d H:i:s", $momento), $leitura);

        if (classificarLeitura($leitura)) {
            $acimaDoLimite++;
        }
    }

    if ($acimaDoLimite > 0) {
        criarAlerta("Sensores do " . $trem[0]["nome"] . ": $acimaDoLimite leituras acima dos limites.", "aviso");
    }

    responder(["geradas" => $quantidade, "acima_do_limite" => $acimaDoLimite], 201);
}

if ($metodo === "POST") {
    $dados = dadosRecebidos();
    $trem = consultar("SELECT id, nome FROM trens WHERE id = ?", [$dados["trem_id"] ?? 0]);

    if (!$trem) {
        erro("Trem não encontrado. Nenhuma leitura foi gravada.", null, 404);
    }

    foreach (["velocidade_kmh", "temperatura_motor_c", "consumo_litros_hora", "vibracao_mm_s"] as $campo) {
        if (!is_numeric($dados[$campo] ?? "")) {
            erro("Informe o valor de $campo.");
        }
    }

    gravarLeitura($trem[0]["id"], date("Y-m-d H:i:s"), $dados);
    $falhas = classificarLeitura($dados);

    if ($falhas) {
        criarAlerta("Sensores do " . $trem[0]["nome"] . ": " . implode(", ", $falhas) . ".", "aviso");
    }

    responder(["falhas" => $falhas, "situacao" => $falhas ? "alerta" : "normal"], 201);
}

responder([
    "limites" => $limites,
    "leituras" => buscarLeituras($_GET)
]);
