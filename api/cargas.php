<?php

require "conexao.php";

$usuario = exigirCargo();
$metodo = $_SERVER["REQUEST_METHOD"];
$acao = $_GET["acao"] ?? "";

if ($metodo === "POST" && $acao === "avancar") {
    $entregues = [];

    foreach (consultar("SELECT * FROM cargas WHERE status = 'em_transito'") as $carga) {
        $progresso = min(100, $carga["progresso"] + rand(2, 7));
        $status = $progresso === 100 ? "entregue" : "em_transito";

        executar("UPDATE cargas SET progresso = ?, status = ? WHERE id = ?", [$progresso, $status, $carga["id"]]);

        if ($status === "entregue") {
            $mensagem = $carga["tipo"] . " chegou em " . $carga["destino"] . ".";
            criarAlerta("Carga entregue: " . $mensagem);
            $entregues[] = $mensagem;
        }
    }

    responder(["entregues" => $entregues]);
}

if ($metodo === "POST") {
    if ($usuario["cargo"] !== "gestor") {
        erro("Só o gestor pode cadastrar cargas.", null, 403);
    }

    $dados = dadosRecebidos();
    $tipo = $dados["tipo"] ?? "";
    $toneladas = (int) ($dados["toneladas"] ?? 0);
    $destino = $dados["destino"] ?? "";
    $partida = $dados["partida"] ?? "";

    if ($tipo === "") {
        erro("Escolha qual carga o trem vai levar.", "tipoCarga");
    }
    if ($toneladas <= 0) {
        erro("Escolha o tamanho da carga.", "tamanhoCarga");
    }
    if ($destino === "") {
        erro("Escolha o destino da carga.", "destinoCarga");
    }
    if ($partida === "" || $partida < hoje()) {
        erro("Escolha uma data de partida a partir de hoje.", "dataPartida");
    }

    $status = $partida === hoje() ? "em_transito" : "aguardando";

    executar(
        "INSERT INTO cargas (tipo, toneladas, destino, partida, status, progresso) VALUES (?, ?, ?, ?, ?, 0)",
        [$tipo, $toneladas, $destino, $partida, $status]
    );

    criarAlerta("Nova carga cadastrada: $tipo ($toneladas t) para $destino, partida em " . formatarData($partida) . ".");

    responder(["ok" => true], 201);
}

executar("UPDATE cargas SET status = 'em_transito' WHERE status = 'aguardando' AND partida <= ?", [hoje()]);

if (!empty($_GET["status"])) {
    responder(consultar("SELECT * FROM cargas WHERE status = ? ORDER BY partida", [$_GET["status"]]));
}

responder(consultar("SELECT * FROM cargas ORDER BY partida"));
