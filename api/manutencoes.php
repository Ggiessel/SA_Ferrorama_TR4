<?php

/*
 * Manutenções.
 * GET        lista (filtros: trem_id, status, tipo, data_inicial, data_final) — maquinista: só o seu trem
 * POST       registra: gestor (qualquer trem) e maquinista (somente o seu trem). Coloca o trem em manutenção.
 * PUT ?id=   atualiza situação, custo e conclusão (gestor). Ao concluir, o trem volta a ficar disponível.
 */

require "conexao.php";

$usuario = exigirCargo("gestor", "maquinista");
$id = inteiro($_GET["id"] ?? 0);
const STATUS_MANUTENCAO = ["aberta", "em_andamento", "concluida"];
const TIPOS_MANUTENCAO = ["preventiva", "corretiva"];

function buscarManutencao($id)
{
    $m = consultarUm(
        "SELECT manutencoes.*, trens.codigo, trens.nome AS trem, rotas.nome AS rota, usuarios.nome AS responsavel
         FROM manutencoes
         JOIN trens ON trens.id = manutencoes.trem_id
         LEFT JOIN rotas ON rotas.id = manutencoes.rota_id
         LEFT JOIN usuarios ON usuarios.id = manutencoes.responsavel_id
         WHERE manutencoes.id = ?",
        [$id]
    );
    if (!$m) {
        erro("Manutenção não encontrada.", null, 404);
    }
    return $m;
}

if (metodo() === "POST") {
    $dados = dadosRecebidos();
    $codigoDigitado = trim((string) ($dados["codigo"] ?? ""));
    $tremId = inteiro($dados["trem_id"] ?? 0);
    $rotaId = inteiro($dados["rota_id"] ?? 0);
    $tipo = $dados["tipo"] ?? "corretiva";
    $carga = trim((string) ($dados["carga"] ?? ""));
    $causa = trim((string) ($dados["causa"] ?? ""));

    if (!$tremId && $codigoDigitado !== "") {
        if (!preg_match("/^\d{1,3}$/", $codigoDigitado)) {
            erro("Informe um código de até 3 números.", "codigoTrem");
        }
        $tremId = (int) (consultarUm("SELECT id FROM trens WHERE codigo = ?", [str_pad($codigoDigitado, 3, "0", STR_PAD_LEFT)])["id"] ?? 0);
        if (!$tremId) {
            erro("Nenhum trem com o código " . str_pad($codigoDigitado, 3, "0", STR_PAD_LEFT) . ".", "codigoTrem");
        }
    }
    if ($usuario["cargo"] === "maquinista") {
        if (!$usuario["trem_id"]) {
            erro("Você ainda não está vinculado a um trem. Procure o gestor.", null, 409);
        }
        if ($tremId && $tremId !== $usuario["trem_id"]) {
            erro("Você só pode registrar manutenção do seu trem.", "codigoTrem", 403);
        }
        $tremId = $usuario["trem_id"];
    }

    $trem = consultarUm("SELECT * FROM trens WHERE id = ?", [$tremId]);
    if (!$trem) {
        erro("Informe o trem.", "codigoTrem");
    }
    if (!in_array($tipo, TIPOS_MANUTENCAO, true)) {
        erro("Escolha o tipo de manutenção.", "tipoManutencao");
    }
    if (mb_strlen($causa) < 3 || mb_strlen($causa) > 200) {
        erro("Descreva a causa (ex.: Batida, Freio, Motor).", "causaManutencao");
    }
    if ($rotaId && !consultarUm("SELECT id FROM rotas WHERE id = ?", [$rotaId])) {
        erro("Escolha uma rota válida.", "rotaManutencao");
    }
    if (consultarUm("SELECT id FROM viagens WHERE trem_id = ? AND status IN ('em_andamento', 'atrasada')", [$tremId])) {
        erro("O " . $trem["nome"] . " está em viagem. Conclua ou cancele a viagem antes de registrar a manutenção.", "codigoTrem", 409);
    }

    executar(
        "INSERT INTO manutencoes (trem_id, rota_id, tipo, carga, causa, status, data, responsavel_id) VALUES (?, ?, ?, ?, ?, 'aberta', ?, ?)",
        [$tremId, $rotaId ?: null, $tipo, $carga ?: null, $causa, hoje(), $usuario["id"]]
    );
    $novo = ultimoId();
    executar("UPDATE trens SET status = 'manutencao', rota_id = NULL WHERE id = ?", [$tremId]);
    criarAlerta("O " . $trem["nome"] . " entrou em manutenção " . $tipo . ". Causa: " . $causa . ".", $tipo === "corretiva" ? "atencao" : "normal", [
        "origem" => "sistema", "trem_id" => $tremId, "criado_por" => $usuario["id"]
    ]);

    responder(["id" => $novo, "trem" => $trem["nome"]], 201);
}

if (metodo() === "PUT") {
    if (!eGestor($usuario)) {
        erro("Só o gestor pode atualizar manutenções.", null, 403);
    }
    $atual = buscarManutencao($id);
    $dados = dadosRecebidos();
    $status = $dados["status"] ?? "";
    $custo = $dados["custo"] ?? "";
    $conclusao = trim((string) ($dados["data_conclusao"] ?? ""));

    if (!in_array($status, STATUS_MANUTENCAO, true)) {
        erro("Escolha a situação.", "statusManutencao");
    }
    if ($custo !== "" && $custo !== null && (!is_numeric($custo) || $custo < 0)) {
        erro("Informe um custo válido (ou deixe em branco).", "custoManutencao");
    }
    if ($status === "concluida") {
        $conclusao = $conclusao ?: hoje();
        if (!dataValida($conclusao) || $conclusao < $atual["data"]) {
            erro("A conclusão precisa ser uma data a partir da abertura.", "conclusaoManutencao");
        }
    } else {
        $conclusao = null;
    }

    executar(
        "UPDATE manutencoes SET status = ?, custo = ?, data_conclusao = ? WHERE id = ?",
        [$status, ($custo === "" || $custo === null) ? null : round((float) $custo, 2), $conclusao, $id]
    );

    if ($status === "concluida" && $atual["status"] !== "concluida"
        && !consultarUm("SELECT id FROM manutencoes WHERE trem_id = ? AND status <> 'concluida' AND id <> ?", [$atual["trem_id"], $id])) {
        executar("UPDATE trens SET status = 'disponivel' WHERE id = ? AND status = 'manutencao'", [$atual["trem_id"]]);
        criarAlerta("O " . $atual["trem"] . " concluiu a manutenção e está disponível.", "normal", ["trem_id" => $atual["trem_id"], "criado_por" => $usuario["id"]]);
    }

    responder(buscarManutencao($id));
}

if (metodo() !== "GET") {
    erro("Método não permitido.", null, 405);
}

$condicoes = [];
$valores = [];
$restricao = restricaoTrem($usuario);
if ($restricao !== null) {
    $condicoes[] = "manutencoes.trem_id = ?";
    $valores[] = $restricao;
}
if (inteiro($_GET["trem_id"] ?? 0)) {
    $condicoes[] = "manutencoes.trem_id = ?";
    $valores[] = inteiro($_GET["trem_id"]);
}
if (in_array($_GET["status"] ?? "", STATUS_MANUTENCAO, true)) {
    $condicoes[] = "manutencoes.status = ?";
    $valores[] = $_GET["status"];
}
if (in_array($_GET["tipo"] ?? "", TIPOS_MANUTENCAO, true)) {
    $condicoes[] = "manutencoes.tipo = ?";
    $valores[] = $_GET["tipo"];
}
filtroPeriodo("manutencoes.data", $_GET["data_inicial"] ?? "", $_GET["data_final"] ?? "", $condicoes, $valores);

responder(consultar(
    "SELECT manutencoes.*, trens.codigo, trens.nome AS trem, rotas.nome AS rota, usuarios.nome AS responsavel
     FROM manutencoes
     JOIN trens ON trens.id = manutencoes.trem_id
     LEFT JOIN rotas ON rotas.id = manutencoes.rota_id
     LEFT JOIN usuarios ON usuarios.id = manutencoes.responsavel_id" . onde($condicoes) . "
     ORDER BY manutencoes.status = 'concluida', manutencoes.data DESC, manutencoes.id DESC",
    $valores
));
