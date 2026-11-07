<?php

require "conexao.php";

exigirCargo("gestor");

$metodo = $_SERVER["REQUEST_METHOD"];
$prazo = date("Y-m-d", strtotime("+28 days"));

if ($metodo === "POST") {
    $dados = dadosRecebidos();
    $tipo = $dados["tipo"] ?? "";
    $rotaId = $dados["rota_id"] ?? "";

    $rota = consultar("SELECT nome FROM rotas WHERE id = ?", [$rotaId]);

    if (!$rota) {
        erro("Escolha o local do sensor.", "localSensor");
    }
    if (consultar("SELECT id FROM sensores WHERE tipo = ? AND rota_id = ? AND status <> 'remocao'", [$tipo, $rotaId])) {
        erro("Já existe um $tipo nessa rota.", "localSensor");
    }

    executar("INSERT INTO sensores (tipo, rota_id, status, prazo) VALUES (?, ?, 'instalacao', ?)", [$tipo, $rotaId, $prazo]);
    criarAlerta("Instalação do sensor $tipo na " . $rota[0]["nome"] . " prevista até " . formatarData($prazo) . ".");

    responder(["ok" => true], 201);
}

if ($metodo === "PUT") {
    $sensor = consultar(
        "SELECT sensores.*, rotas.nome AS rota FROM sensores JOIN rotas ON rotas.id = sensores.rota_id WHERE sensores.id = ? AND sensores.status = 'ativo'",
        [$_GET["id"] ?? 0]
    );

    if (!$sensor) {
        erro("Sensor não encontrado.", "sensor", 404);
    }

    executar("UPDATE sensores SET status = 'remocao', prazo = ? WHERE id = ?", [$prazo, $sensor[0]["id"]]);
    criarAlerta("Remoção do sensor " . $sensor[0]["tipo"] . " da " . $sensor[0]["rota"] . " prevista até " . formatarData($prazo) . ".", "aviso");

    responder(["ok" => true]);
}

executar("UPDATE sensores SET status = 'ativo', prazo = NULL WHERE status = 'instalacao' AND prazo <= ?", [hoje()]);
executar("DELETE FROM sensores WHERE status = 'remocao' AND prazo <= ?", [hoje()]);

responder(consultar(
    "SELECT sensores.*, rotas.nome AS rota
     FROM sensores
     JOIN rotas ON rotas.id = sensores.rota_id
     ORDER BY rotas.id, sensores.tipo"
));
