<?php

require "conexao.php";

/*
 * Trens.
 * GET (lista, filtros: status, busca) e GET ?id=  — gestor (todos) e maquinista (somente o próprio trem)
 * POST, PUT ?id=, DELETE ?id=                        — somente gestor
 * PATCH ?id=  {status, rota_id}                      — altera só a situação operacional (gestor)
 */

$usuario = exigirCargo("gestor", "maquinista");
$metodo = metodo();
const STATUS_TREM = ["em_rota", "disponivel", "manutencao", "inativo"];

if ($metodo !== "GET" && !eGestor($usuario)) {
    erro("Só o gestor pode alterar os trens.", null, 403);
}

function buscarTrem($id)
{
    $trem = consultarUm(
        "SELECT trens.*, rotas.nome AS rota FROM trens LEFT JOIN rotas ON rotas.id = trens.rota_id WHERE trens.id = ?",
        [$id]
    );

    if (!$trem) {
        erro("Trem não encontrado.", null, 404);
    }

    return $trem;
}

function validarSituacao($status, $rotaId)
{
    if (!in_array($status, STATUS_TREM, true)) {
        erro("Escolha a situação do trem.", "statusTrem");
    }
    if ($status === "em_rota" && !consultar("SELECT id FROM rotas WHERE id = ? AND ativa = 1", [$rotaId])) {
        erro("Escolha a rota (ativa) em que o trem está.", "rotaTrem");
    }
}

function dadosDoTrem($id = 0)
{
    $dados = dadosRecebidos();
    $codigoDigitado = trim($dados["codigo"] ?? "");
    $codigo = str_pad($codigoDigitado, 3, "0", STR_PAD_LEFT);
    $nome = trim($dados["nome"] ?? "");
    $modelo = trim($dados["modelo"] ?? "");
    $ano = $dados["ano_fabricacao"] ?? "";
    $capacidade = $dados["capacidade_toneladas"] ?? "";
    $status = $dados["status"] ?? "";
    $rotaId = $dados["rota_id"] ?? "";
    $consumo = $dados["consumo"] ?? "";

    if (!preg_match("/^\d{1,3}$/", $codigoDigitado)) {
        erro("Informe um código de até 3 números (ex.: 015).", "codigoTrem");
    }
    if (consultar("SELECT id FROM trens WHERE codigo = ? AND id <> ?", [$codigo, $id])) {
        erro("Já existe um trem com o código $codigo.", "codigoTrem");
    }
    if (strlen($nome) < 3 || strlen($nome) > 30) {
        erro("Informe o nome do trem (ex.: Trem 15).", "nomeTrem");
    }
    if (strlen($modelo) < 2 || strlen($modelo) > 80) {
        erro("Informe o modelo da locomotiva (ex.: GE AC44i).", "modeloTrem");
    }
    if (!is_numeric($ano) || $ano < 1901 || $ano > (int) date("Y")) {
        erro("Informe um ano de fabricação entre 1901 e " . date("Y") . ".", "anoTrem");
    }
    if (!is_numeric($capacidade) || $capacidade <= 0 || $capacidade > 999999) {
        erro("Informe uma capacidade maior que zero.", "capacidadeTrem");
    }
    validarSituacao($status, $rotaId);
    if (!is_numeric($consumo) || $consumo <= 0 || $consumo > 999) {
        erro("Informe o consumo em km/L (ex.: 13).", "consumoTrem");
    }

    return [$codigo, $nome, $modelo, (int) $ano, (float) $capacidade, $status, $status === "em_rota" ? (int) $rotaId : null, (float) $consumo];
}

if ($metodo === "POST") {
    executar(
        "INSERT INTO trens (codigo, nome, modelo, ano_fabricacao, capacidade_toneladas, status, rota_id, consumo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        dadosDoTrem()
    );

    responder(["id" => ultimoId()], 201);
}

if ($metodo === "PATCH") {
    $trem = buscarTrem(inteiro($_GET["id"] ?? 0));
    $dados = dadosRecebidos();
    $status = $dados["status"] ?? "";
    $rotaId = inteiro($dados["rota_id"] ?? 0);

    validarSituacao($status, $rotaId);
    executar("UPDATE trens SET status = ?, rota_id = ? WHERE id = ?", [$status, $status === "em_rota" ? $rotaId : null, $trem["id"]]);

    responder(buscarTrem($trem["id"]));
}

if ($metodo === "PUT") {
    $trem = buscarTrem(inteiro($_GET["id"] ?? 0));
    $valores = dadosDoTrem($trem["id"]);
    $valores[] = $trem["id"];

    executar(
        "UPDATE trens SET codigo = ?, nome = ?, modelo = ?, ano_fabricacao = ?, capacidade_toneladas = ?, status = ?, rota_id = ?, consumo = ? WHERE id = ?",
        $valores
    );

    responder(["ok" => true]);
}

if ($metodo === "DELETE") {
    $trem = buscarTrem(inteiro($_GET["id"] ?? 0));

    if (consultar("SELECT id FROM manutencoes WHERE trem_id = ? LIMIT 1", [$trem["id"]])) {
        erro("O " . $trem["nome"] . " tem manutenções registradas e não pode ser excluído. Use a situação Inativo.", null, 409);
    }
    if (consultar("SELECT id FROM viagens WHERE trem_id = ? LIMIT 1", [$trem["id"]])) {
        erro("O " . $trem["nome"] . " tem viagens registradas e não pode ser excluído. Use a situação Inativo.", null, 409);
    }

    executar("DELETE FROM trens WHERE id = ?", [$trem["id"]]);

    responder(["ok" => true]);
}

if ($metodo !== "GET") {
    erro("Método não permitido.", null, 405);
}

$restricao = restricaoTrem($usuario);

if (isset($_GET["id"])) {
    exigirAcessoTrem($usuario, inteiro($_GET["id"]));
    responder(buscarTrem(inteiro($_GET["id"])));
}

$condicoes = [];
$valores = [];

if ($restricao !== null) {
    $condicoes[] = "trens.id = ?";
    $valores[] = $restricao;
}
if (in_array($_GET["status"] ?? "", STATUS_TREM, true)) {
    $condicoes[] = "trens.status = ?";
    $valores[] = $_GET["status"];
}
$busca = trim($_GET["busca"] ?? "");
if ($busca !== "") {
    $condicoes[] = "(trens.nome LIKE ? OR trens.codigo LIKE ? OR trens.modelo LIKE ?)";
    array_push($valores, "%$busca%", "%$busca%", "%$busca%");
}

responder(consultar(
    "SELECT trens.*, rotas.nome AS rota,
            (SELECT COUNT(*) FROM sensores WHERE sensores.trem_id = trens.id AND sensores.status = 'ativo') AS sensores_ativos,
            (SELECT nome FROM usuarios WHERE usuarios.trem_id = trens.id AND usuarios.cargo = 'maquinista' AND usuarios.ativo = 1 ORDER BY id LIMIT 1) AS maquinista
     FROM trens
     LEFT JOIN rotas ON rotas.id = trens.rota_id" . onde($condicoes) . "
     ORDER BY trens.codigo",
    $valores
));
