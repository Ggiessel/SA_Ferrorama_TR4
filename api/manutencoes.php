<?php

require "conexao.php";

exigirCargo("gestor", "maquinista");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dados = dadosRecebidos();
    $codigo = str_pad(trim($dados["codigo"] ?? ""), 3, "0", STR_PAD_LEFT);
    $rotaId = $dados["rota_id"] ?? "";
    $carga = $dados["carga"] ?? "Sem carga";
    $causa = trim($dados["causa"] ?? "");

    if (trim($dados["codigo"] ?? "") === "") {
        erro("Informe o código do trem.", "codigoTrem");
    }

    $trens = consultar("SELECT * FROM trens WHERE codigo = ?", [$codigo]);

    if (!$trens) {
        erro("Nenhum trem com o código $codigo.", "codigoTrem");
    }
    if (strlen($causa) < 3) {
        erro("Descreva a causa (ex.: Batida, Freio, Motor).", "causaManutencao");
    }

    $trem = $trens[0];
    $rota = consultar("SELECT nome FROM rotas WHERE id = ?", [$rotaId]);

    if (!$rota) {
        erro("Escolha a rota.", "rotaManutencao");
    }

    executar(
        "INSERT INTO manutencoes (trem_id, rota_id, carga, causa, data) VALUES (?, ?, ?, ?, ?)",
        [$trem["id"], $rotaId, $carga, $causa, hoje()]
    );
    executar("UPDATE trens SET status = 'manutencao', rota_id = NULL WHERE id = ?", [$trem["id"]]);
    criarAlerta("O " . $trem["nome"] . " (" . $rota[0]["nome"] . ") entrou em manutenção. Causa: $causa.", "aviso");

    responder(["trem" => $trem["nome"]], 201);
}

responder(consultar(
    "SELECT manutencoes.*, trens.codigo, trens.nome AS trem, rotas.nome AS rota
     FROM manutencoes
     JOIN trens ON trens.id = manutencoes.trem_id
     JOIN rotas ON rotas.id = manutencoes.rota_id
     ORDER BY manutencoes.id DESC"
));
