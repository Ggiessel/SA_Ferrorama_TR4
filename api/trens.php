<?php

require "conexao.php";

$usuario = exigirCargo();
$metodo = $_SERVER["REQUEST_METHOD"];

if ($metodo !== "GET" && $usuario["cargo"] !== "gestor") {
    erro("Só o gestor pode alterar os trens.", null, 403);
}

function buscarTrem($id)
{
    $trem = consultar("SELECT * FROM trens WHERE id = ?", [$id]);

    if (!$trem) {
        erro("Trem não encontrado.", null, 404);
    }

    return $trem[0];
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
    if (!in_array($status, ["em_rota", "disponivel", "manutencao"])) {
        erro("Escolha a situação do trem.", "statusTrem");
    }
    if ($status === "em_rota" && !consultar("SELECT id FROM rotas WHERE id = ?", [$rotaId])) {
        erro("Escolha a rota em que o trem está.", "rotaTrem");
    }
    if (!is_numeric($consumo) || $consumo <= 0 || $consumo > 999) {
        erro("Informe o consumo em km/L (ex.: 13).", "consumoTrem");
    }

    return [$codigo, $nome, $modelo, (int) $ano, (float) $capacidade, $status, $status === "em_rota" ? $rotaId : null, (float) $consumo];
}

if ($metodo === "POST") {
    executar(
        "INSERT INTO trens (codigo, nome, modelo, ano_fabricacao, capacidade_toneladas, status, rota_id, consumo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        dadosDoTrem()
    );

    responder(["ok" => true], 201);
}

if ($metodo === "PUT") {
    $trem = buscarTrem($_GET["id"] ?? 0);
    $valores = dadosDoTrem($trem["id"]);
    $valores[] = $trem["id"];

    executar(
        "UPDATE trens SET codigo = ?, nome = ?, modelo = ?, ano_fabricacao = ?, capacidade_toneladas = ?, status = ?, rota_id = ?, consumo = ? WHERE id = ?",
        $valores
    );

    responder(["ok" => true]);
}

if ($metodo === "DELETE") {
    $trem = buscarTrem($_GET["id"] ?? 0);

    if (consultar("SELECT id FROM manutencoes WHERE trem_id = ?", [$trem["id"]])) {
        erro("O " . $trem["nome"] . " tem manutenções registradas e não pode ser excluído.", null, 409);
    }

    executar("DELETE FROM trens WHERE id = ?", [$trem["id"]]);

    responder(["ok" => true]);
}

if (isset($_GET["id"])) {
    responder(buscarTrem($_GET["id"]));
}

responder(consultar(
    "SELECT trens.*, rotas.nome AS rota
     FROM trens
     LEFT JOIN rotas ON rotas.id = trens.rota_id
     ORDER BY trens.codigo"
));
