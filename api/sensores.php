<?php

require "conexao.php";

exigirCargo("gestor");

$metodo = $_SERVER["REQUEST_METHOD"];
$prazo = date("Y-m-d", strtotime("+28 days"));

if ($metodo === "POST") {
    $dados = dadosRecebidos();
    $tipo = $dados["tipo"] ?? "";
    $tremId = $dados["trem_id"] ?? "";
    $grandeza = "";

    foreach (tiposSensor() as $sensor) {
        if ($sensor["tipo"] === $tipo) {
            $grandeza = $sensor["grandeza"];
        }
    }

    if ($grandeza === "") {
        erro("Escolha o tipo de sensor.", "tipoSensor");
    }

    $trem = consultar("SELECT nome FROM trens WHERE id = ?", [$tremId]);

    if (!$trem) {
        erro("Escolha o trem que vai receber o sensor.", "tremSensor");
    }
    if (consultar("SELECT id FROM sensores WHERE tipo = ? AND trem_id = ? AND status <> 'remocao'", [$tipo, $tremId])) {
        erro("O " . $trem[0]["nome"] . " já tem um sensor $tipo.", "tremSensor");
    }

    executar("INSERT INTO sensores (tipo, grandeza, trem_id, status, prazo) VALUES (?, ?, ?, 'instalacao', ?)", [$tipo, $grandeza, $tremId, $prazo]);
    criarAlerta("Instalação do sensor $tipo ($grandeza) no " . $trem[0]["nome"] . " prevista até " . formatarData($prazo) . ".");

    responder(["ok" => true], 201);
}

if ($metodo === "PUT") {
    $sensor = consultar(
        "SELECT sensores.*, trens.nome AS trem FROM sensores JOIN trens ON trens.id = sensores.trem_id WHERE sensores.id = ? AND sensores.status = 'ativo'",
        [$_GET["id"] ?? 0]
    );

    if (!$sensor) {
        erro("Sensor não encontrado.", "sensor", 404);
    }

    executar("UPDATE sensores SET status = 'remocao', prazo = ? WHERE id = ?", [$prazo, $sensor[0]["id"]]);
    criarAlerta("Remoção do sensor " . $sensor[0]["tipo"] . " do " . $sensor[0]["trem"] . " prevista até " . formatarData($prazo) . ".", "aviso");

    responder(["ok" => true]);
}

executar("UPDATE sensores SET status = 'ativo', prazo = NULL WHERE status = 'instalacao' AND prazo <= ?", [hoje()]);
executar("DELETE FROM sensores WHERE status = 'remocao' AND prazo <= ?", [hoje()]);

responder(consultar(
    "SELECT sensores.*, trens.nome AS trem
     FROM sensores
     JOIN trens ON trens.id = sensores.trem_id
     ORDER BY trens.codigo, sensores.tipo"
));
