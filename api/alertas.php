<?php

/*
 * Alertas e notificações (níveis: normal, atencao, critico).
 * GET                  lista (filtros: busca, nivel, situacao, trem_id, origem, data_inicial, data_final)
 *                      gestor: todos | maquinista: do seu trem e avisos públicos | cliente: somente avisos públicos
 * POST                 cria alerta manual: gestor (qualquer trem) e maquinista (somente o seu trem)
 * PATCH ?id=&acao=resolver|reabrir   gestor
 * DELETE ?id=          gestor
 */

require "conexao.php";

$usuario = exigirCargo();
$id = inteiro($_GET["id"] ?? 0);
const NIVEIS = ["normal", "atencao", "critico"];

if (metodo() === "POST") {
    if (!in_array($usuario["cargo"], ["gestor", "maquinista"], true)) {
        erro("Você não tem permissão para criar alertas.", null, 403);
    }

    $dados = dadosRecebidos();
    $mensagem = trim((string) ($dados["mensagem"] ?? ""));
    $nivel = $dados["nivel"] ?? "";
    $tremId = inteiro($dados["trem_id"] ?? 0);
    $publico = !empty($dados["publico"]);

    if ($usuario["cargo"] === "maquinista") {
        $tremId = $usuario["trem_id"] ?: 0;
        if (!$tremId) {
            erro("Você ainda não está vinculado a um trem. Procure o gestor.", null, 409);
        }
        $publico = false;
    }
    if (mb_strlen($mensagem) < 5 || mb_strlen($mensagem) > 255) {
        erro("Descreva o alerta (entre 5 e 255 caracteres).", "mensagemAlerta");
    }
    if (!in_array($nivel, NIVEIS, true)) {
        erro("Escolha a gravidade do alerta.", "nivelAlerta");
    }
    if ($tremId && !consultarUm("SELECT id FROM trens WHERE id = ?", [$tremId])) {
        erro("Trem não encontrado.", "tremAlerta");
    }

    $novo = criarAlerta($mensagem, $nivel, [
        "origem" => "manual",
        "publico" => $publico,
        "trem_id" => $tremId ?: null,
        "criado_por" => $usuario["id"]
    ]);

    responder(["id" => $novo], 201);
}

if (in_array(metodo(), ["PATCH", "DELETE"], true)) {
    if (!eGestor($usuario)) {
        erro("Só o gestor pode resolver ou apagar alertas.", null, 403);
    }
    if (!consultarUm("SELECT id FROM alertas WHERE id = ?", [$id])) {
        erro("Alerta não encontrado.", null, 404);
    }

    if (metodo() === "DELETE") {
        executar("DELETE FROM alertas WHERE id = ?", [$id]);
        responder(["ok" => true]);
    }

    $acao = $_GET["acao"] ?? "";
    if ($acao === "resolver") {
        executar("UPDATE alertas SET situacao = 'resolvido', resolvido_em = ?, resolvido_por = ? WHERE id = ?", [date("Y-m-d H:i:s"), $usuario["id"], $id]);
    } elseif ($acao === "reabrir") {
        executar("UPDATE alertas SET situacao = 'ativo', resolvido_em = NULL, resolvido_por = NULL WHERE id = ?", [$id]);
    } else {
        erro("Ação inválida.");
    }
    responder(["ok" => true]);
}

if (metodo() !== "GET") {
    erro("Método não permitido.", null, 405);
}

$condicoes = [];
$valores = [];

if ($usuario["cargo"] === "cliente") {
    $condicoes[] = "alertas.publico = 1";
} elseif ($usuario["cargo"] === "maquinista") {
    $condicoes[] = "(alertas.trem_id = ? OR alertas.publico = 1)";
    $valores[] = $usuario["trem_id"] ?: -1;
}

$busca = trim($_GET["busca"] ?? "");
if ($busca !== "") {
    $condicoes[] = "(alertas.mensagem LIKE ? OR trens.nome LIKE ?)";
    array_push($valores, "%$busca%", "%$busca%");
}
if (in_array($_GET["nivel"] ?? "", NIVEIS, true)) {
    $condicoes[] = "alertas.nivel = ?";
    $valores[] = $_GET["nivel"];
}
if (in_array($_GET["situacao"] ?? "", ["ativo", "resolvido"], true)) {
    $condicoes[] = "alertas.situacao = ?";
    $valores[] = $_GET["situacao"];
}
if (in_array($_GET["origem"] ?? "", ["sensor", "manual", "sistema"], true)) {
    $condicoes[] = "alertas.origem = ?";
    $valores[] = $_GET["origem"];
}
if (inteiro($_GET["trem_id"] ?? 0)) {
    $condicoes[] = "alertas.trem_id = ?";
    $valores[] = inteiro($_GET["trem_id"]);
}
filtroPeriodo("alertas.data", $_GET["data_inicial"] ?? "", $_GET["data_final"] ?? "", $condicoes, $valores);

// Para o cliente não são enviados dados técnicos de sensor.
$colunasTecnicas = $usuario["cargo"] === "cliente"
    ? "NULL AS sensor_id, NULL AS sensor, NULL AS valor, NULL AS unidade"
    : "alertas.sensor_id, sensores.tipo AS sensor, leituras.valor, sensores.unidade";

responder(consultar(
    "SELECT alertas.id, alertas.mensagem, alertas.nivel, alertas.origem, alertas.situacao, alertas.publico,
            alertas.trem_id, trens.nome AS trem, alertas.data, alertas.resolvido_em,
            criador.nome AS criado_por, $colunasTecnicas
     FROM alertas
     LEFT JOIN trens ON trens.id = alertas.trem_id
     LEFT JOIN sensores ON sensores.id = alertas.sensor_id
     LEFT JOIN leituras ON leituras.id = alertas.leitura_id
     LEFT JOIN usuarios criador ON criador.id = alertas.criado_por" . onde($condicoes) . "
     ORDER BY alertas.situacao = 'ativo' DESC, FIELD(alertas.nivel, 'critico', 'atencao', 'normal'), alertas.data DESC, alertas.id DESC
     LIMIT 300",
    $valores
));
