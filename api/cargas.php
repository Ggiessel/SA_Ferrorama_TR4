<?php

require "conexao.php";

/*
 * Cargas. GET (todos os perfis, filtro status) | POST cadastra (gestor) | POST ?acao=avancar simula o avanço (gestor)
 */

$usuario = exigirCargo();
$metodo = metodo();
$acao = $_GET["acao"] ?? "";

if ($metodo === "POST" && $acao === "avancar") {
    if (!eGestor($usuario)) {
        erro("Só o gestor pode atualizar o andamento das cargas.", null, 403);
    }
    $entregues = [];

    foreach (consultar("SELECT * FROM cargas WHERE status = 'em_transito'") as $carga) {
        $progresso = min(100, $carga["progresso"] + rand(2, 7));
        $status = $progresso === 100 ? "entregue" : "em_transito";

        executar("UPDATE cargas SET progresso = ?, status = ? WHERE id = ?", [$progresso, $status, $carga["id"]]);

        if ($status === "entregue") {
            $mensagem = $carga["tipo"] . " chegou em " . $carga["destino"] . ".";
            criarAlerta("Carga entregue: " . $mensagem, "normal", ["publico" => true]);
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
    $tipo = trim((string) ($dados["tipo"] ?? ""));
    $toneladas = (int) ($dados["toneladas"] ?? 0);
    $destino = trim((string) ($dados["destino"] ?? ""));
    $partida = (string) ($dados["partida"] ?? "");

    if ($tipo === "" || mb_strlen($tipo) > 60) {
        erro("Escolha qual carga o trem vai levar.", "tipoCarga");
    }
    if ($toneladas <= 0 || $toneladas > 100000) {
        erro("Escolha o tamanho da carga.", "tamanhoCarga");
    }
    if ($destino === "" || mb_strlen($destino) > 80) {
        erro("Escolha o destino da carga.", "destinoCarga");
    }
    if (!dataValida($partida) || $partida < hoje()) {
        erro("Escolha uma data de partida a partir de hoje.", "dataPartida");
    }

    $status = $partida === hoje() ? "em_transito" : "aguardando";

    executar(
        "INSERT INTO cargas (tipo, toneladas, destino, partida, status, progresso) VALUES (?, ?, ?, ?, ?, 0)",
        [$tipo, $toneladas, $destino, $partida, $status]
    );

    criarAlerta("Nova carga cadastrada: $tipo ($toneladas t) para $destino, partida em " . formatarData($partida) . ".", "normal", ["publico" => true]);

    responder(["ok" => true], 201);
}

executar("UPDATE cargas SET status = 'em_transito' WHERE status = 'aguardando' AND partida <= ?", [hoje()]);

if (in_array($_GET["status"] ?? "", ["aguardando", "em_transito", "entregue"], true)) {
    responder(consultar("SELECT * FROM cargas WHERE status = ? ORDER BY partida", [$_GET["status"]]));
}

responder(consultar("SELECT * FROM cargas ORDER BY partida"));
