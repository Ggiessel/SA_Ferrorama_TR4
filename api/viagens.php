<?php

/*
 * Viagens: relacionam rota, trem e maquinista no tempo.
 * GET        lista (filtros: data_inicial, data_final, status, trem_id, rota_id)
 *            gestor e cliente: todas | maquinista: somente do seu trem
 * GET ?id=   uma viagem
 * POST       agenda (gestor)      PUT ?id=  edita (gestor)      DELETE ?id=  exclui viagem agendada/cancelada (gestor)
 * PATCH ?id=&acao=iniciar|concluir|cancelar|atrasar   gestor; maquinista só no próprio trem (iniciar/concluir/atrasar)
 */

require "conexao.php";

$usuario = exigirCargo();
$id = inteiro($_GET["id"] ?? 0);
const STATUS_VIAGEM = ["agendada", "em_andamento", "concluida", "atrasada", "cancelada"];

function buscarViagem($id)
{
    $viagem = consultarUm(
        "SELECT viagens.*, rotas.nome AS rota, rotas.origem, rotas.destino, rotas.distancia_km, trens.nome AS trem, usuarios.nome AS maquinista
         FROM viagens
         JOIN rotas ON rotas.id = viagens.rota_id
         JOIN trens ON trens.id = viagens.trem_id
         LEFT JOIN usuarios ON usuarios.id = viagens.maquinista_id
         WHERE viagens.id = ?",
        [$id]
    );
    if (!$viagem) {
        erro("Viagem não encontrada.", null, 404);
    }
    return $viagem;
}

function dadosDaViagem()
{
    $dados = dadosRecebidos();
    $rotaId = inteiro($dados["rota_id"] ?? 0);
    $tremId = inteiro($dados["trem_id"] ?? 0);
    $maquinistaId = inteiro($dados["maquinista_id"] ?? 0);
    $partida = dataHoraValida($dados["partida_prevista"] ?? "");
    $chegada = dataHoraValida($dados["chegada_prevista"] ?? "");
    $status = $dados["status"] ?? "agendada";
    $observacao = trim((string) ($dados["observacao"] ?? ""));

    if (!consultarUm("SELECT id FROM rotas WHERE id = ? AND ativa = 1", [$rotaId])) {
        erro("Escolha uma rota ativa.", "rotaViagem");
    }
    $trem = consultarUm("SELECT id, status FROM trens WHERE id = ?", [$tremId]);
    if (!$trem) {
        erro("Escolha o trem.", "tremViagem");
    }
    if ($maquinistaId && !consultarUm("SELECT id FROM usuarios WHERE id = ? AND cargo = 'maquinista' AND ativo = 1", [$maquinistaId])) {
        erro("Escolha um maquinista ativo.", "maquinistaViagem");
    }
    if (!$partida) {
        erro("Informe a data e a hora de partida.", "partidaViagem");
    }
    if (!$chegada || $chegada <= $partida) {
        erro("A chegada prevista precisa ser depois da partida.", "chegadaViagem");
    }
    if (!in_array($status, STATUS_VIAGEM, true)) {
        erro("Escolha a situação da viagem.", "statusViagem");
    }
    if (in_array($status, ["agendada", "em_andamento", "atrasada"], true) && in_array($trem["status"], ["manutencao", "inativo"], true)) {
        erro("O trem escolhido está em manutenção ou inativo.", "tremViagem");
    }
    if (mb_strlen($observacao) > 255) {
        erro("A observação pode ter no máximo 255 caracteres.", "observacaoViagem");
    }

    return [$rotaId, $tremId, $maquinistaId ?: null, $partida, $chegada, $status, $observacao ?: null];
}

/** Atualiza o trem conforme a viagem: em andamento coloca o trem em rota; concluída/cancelada o libera. */
function sincronizarTrem($viagem, $status)
{
    if (in_array($status, ["em_andamento", "atrasada"], true)) {
        executar("UPDATE trens SET status = 'em_rota', rota_id = ? WHERE id = ?", [$viagem["rota_id"], $viagem["trem_id"]]);
    } elseif (in_array($status, ["concluida", "cancelada"], true)) {
        $outras = consultarUm(
            "SELECT id FROM viagens WHERE trem_id = ? AND id <> ? AND status IN ('em_andamento', 'atrasada') LIMIT 1",
            [$viagem["trem_id"], $viagem["id"]]
        );
        if (!$outras) {
            executar("UPDATE trens SET status = 'disponivel', rota_id = NULL WHERE id = ? AND status = 'em_rota'", [$viagem["trem_id"]]);
        }
    }
}

if (metodo() === "PATCH") {
    $viagem = buscarViagem($id);
    $acao = $_GET["acao"] ?? "";
    $agora = date("Y-m-d H:i:s");

    if ($usuario["cargo"] === "cliente") {
        erro("Você não tem permissão para isso.", null, 403);
    }
    if ($usuario["cargo"] === "maquinista") {
        exigirAcessoTrem($usuario, $viagem["trem_id"]);
        if (!in_array($acao, ["iniciar", "concluir", "atrasar"], true)) {
            erro("O maquinista pode apenas iniciar, concluir ou informar atraso.", null, 403);
        }
    }

    if ($acao === "iniciar") {
        if ($viagem["status"] !== "agendada") {
            erro("Só uma viagem agendada pode ser iniciada.", null, 409);
        }
        if (consultarUm("SELECT id FROM viagens WHERE trem_id = ? AND status IN ('em_andamento', 'atrasada') AND id <> ?", [$viagem["trem_id"], $id])) {
            erro("Este trem já está em outra viagem.", null, 409);
        }
        executar("UPDATE viagens SET status = 'em_andamento', partida_real = ? WHERE id = ?", [$agora, $id]);
        sincronizarTrem($viagem, "em_andamento");
    } elseif ($acao === "concluir") {
        if (!in_array($viagem["status"], ["em_andamento", "atrasada"], true)) {
            erro("Só uma viagem em andamento pode ser concluída.", null, 409);
        }
        executar("UPDATE viagens SET status = 'concluida', chegada_real = ?, km_percorridos = ? WHERE id = ?", [$agora, $viagem["distancia_km"], $id]);
        executar("UPDATE rotas SET quilometragem = quilometragem + ? WHERE id = ?", [$viagem["distancia_km"], $viagem["rota_id"]]);
        sincronizarTrem($viagem, "concluida");
    } elseif ($acao === "atrasar") {
        if ($viagem["status"] !== "em_andamento") {
            erro("Só uma viagem em andamento pode ser marcada como atrasada.", null, 409);
        }
        executar("UPDATE viagens SET status = 'atrasada' WHERE id = ?", [$id]);
        criarAlerta("Viagem do " . $viagem["trem"] . " na " . $viagem["rota"] . " está atrasada.", "atencao", ["trem_id" => $viagem["trem_id"], "publico" => true, "criado_por" => $usuario["id"]]);
    } elseif ($acao === "cancelar") {
        if (!in_array($viagem["status"], ["agendada", "atrasada", "em_andamento"], true)) {
            erro("Esta viagem não pode ser cancelada.", null, 409);
        }
        executar("UPDATE viagens SET status = 'cancelada' WHERE id = ?", [$id]);
        sincronizarTrem($viagem, "cancelada");
    } else {
        erro("Ação inválida.");
    }

    responder(buscarViagem($id));
}

if (metodo() !== "GET" && !eGestor($usuario)) {
    erro("Só o gestor pode agendar, editar ou excluir viagens.", null, 403);
}

if (metodo() === "POST") {
    $valores = dadosDaViagem();
    executar(
        "INSERT INTO viagens (rota_id, trem_id, maquinista_id, partida_prevista, chegada_prevista, status, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)",
        $valores
    );
    $novo = buscarViagem(ultimoId());
    sincronizarTrem($novo, $novo["status"]);
    responder($novo, 201);
}

if (metodo() === "PUT") {
    $atual = buscarViagem($id);
    $valores = dadosDaViagem();
    $valores[] = $id;
    executar(
        "UPDATE viagens SET rota_id = ?, trem_id = ?, maquinista_id = ?, partida_prevista = ?, chegada_prevista = ?, status = ?, observacao = ? WHERE id = ?",
        $valores
    );
    $nova = buscarViagem($id);
    if ($nova["status"] !== $atual["status"]) {
        sincronizarTrem($nova, $nova["status"]);
    }
    responder($nova);
}

if (metodo() === "DELETE") {
    $viagem = buscarViagem($id);
    if (!in_array($viagem["status"], ["agendada", "cancelada"], true)) {
        erro("Só viagens agendadas ou canceladas podem ser excluídas. As demais fazem parte do histórico.", null, 409);
    }
    executar("DELETE FROM viagens WHERE id = ?", [$id]);
    responder(["ok" => true]);
}

if (metodo() !== "GET") {
    erro("Método não permitido.", null, 405);
}

if ($id) {
    $viagem = buscarViagem($id);
    exigirAcessoTrem($usuario, $viagem["trem_id"]);
    responder($viagem);
}

$condicoes = [];
$valores = [];
$restricao = restricaoTrem($usuario);

if ($restricao !== null) {
    $condicoes[] = "viagens.trem_id = ?";
    $valores[] = $restricao;
}
if (in_array($_GET["status"] ?? "", STATUS_VIAGEM, true)) {
    $condicoes[] = "viagens.status = ?";
    $valores[] = $_GET["status"];
}
if (($_GET["status"] ?? "") === "ativas") {
    $condicoes[] = "viagens.status IN ('em_andamento', 'atrasada')";
}
if (inteiro($_GET["trem_id"] ?? 0)) {
    $condicoes[] = "viagens.trem_id = ?";
    $valores[] = inteiro($_GET["trem_id"]);
}
if (inteiro($_GET["rota_id"] ?? 0)) {
    $condicoes[] = "viagens.rota_id = ?";
    $valores[] = inteiro($_GET["rota_id"]);
}
filtroPeriodo("viagens.partida_prevista", $_GET["data_inicial"] ?? "", $_GET["data_final"] ?? "", $condicoes, $valores);

$maquinista = $usuario["cargo"] === "cliente" ? "NULL AS maquinista" : "usuarios.nome AS maquinista";

responder(consultar(
    "SELECT viagens.id, viagens.rota_id, viagens.trem_id, viagens.maquinista_id, viagens.partida_prevista, viagens.chegada_prevista,
            viagens.partida_real, viagens.chegada_real, viagens.status, viagens.km_percorridos, viagens.observacao,
            rotas.nome AS rota, rotas.origem, rotas.destino, trens.nome AS trem, $maquinista
     FROM viagens
     JOIN rotas ON rotas.id = viagens.rota_id
     JOIN trens ON trens.id = viagens.trem_id
     LEFT JOIN usuarios ON usuarios.id = viagens.maquinista_id" . onde($condicoes) . "
     ORDER BY FIELD(viagens.status, 'em_andamento', 'atrasada', 'agendada', 'concluida', 'cancelada'), viagens.partida_prevista DESC
     LIMIT 300",
    $valores
));
