<?php

require "conexao.php";

$usuario = exigirCargo();
$metodo = $_SERVER["REQUEST_METHOD"];

if ($metodo !== "GET" && $usuario["cargo"] !== "gestor") {
    erro("Só o gestor pode alterar as rotas.", null, 403);
}

function buscarRota($id)
{
    $resultado = consultar("SELECT * FROM rotas WHERE id = ?", [$id]);

    if (!$resultado) {
        erro("Rota não encontrada.", null, 404);
    }

    $rota = $resultado[0];
    $rota["horarios"] = array_column(consultar("SELECT horario FROM horarios WHERE rota_id = ? ORDER BY horario", [$rota["id"]]), "horario");
    $rota["linhas"] = consultar("SELECT nome, status FROM linhas WHERE rota_id = ? ORDER BY nome", [$rota["id"]]);

    return $rota;
}

function dadosDaRota($id = 0)
{
    $dados = dadosRecebidos();
    $nome = trim($dados["nome"] ?? "");
    $origem = trim($dados["origem"] ?? "");
    $destino = trim($dados["destino"] ?? "");
    $quilometragem = $dados["quilometragem"] ?? "";
    $passageiros = $dados["passageiros"] ?? "";
    $alerta = trim($dados["alerta"] ?? "");
    $horarios = [];

    if (strlen($nome) < 3 || strlen($nome) > 30) {
        erro("Informe o nome da rota (ex.: Rota 40).", "nomeRota");
    }
    if (consultar("SELECT id FROM rotas WHERE nome = ? AND id <> ?", [$nome, $id])) {
        erro("Já existe uma rota com esse nome.", "nomeRota");
    }
    if (strlen($origem) < 3 || strlen($origem) > 80) {
        erro("Informe a cidade de origem.", "origemRota");
    }
    if (strlen($destino) < 3 || strlen($destino) > 80) {
        erro("Informe a cidade de destino.", "destinoRota");
    }
    if ($origem === $destino) {
        erro("A origem e o destino precisam ser diferentes.", "destinoRota");
    }
    if (!is_numeric($quilometragem) || $quilometragem <= 0) {
        erro("Informe a quilometragem da rota.", "quilometragemRota");
    }
    if (!is_numeric($passageiros) || $passageiros < 0) {
        erro("Informe quantos passageiros a rota transporta.", "passageirosRota");
    }

    foreach (explode(",", $dados["horarios"] ?? "") as $horario) {
        $horario = trim($horario);

        if ($horario === "") {
            continue;
        }
        if (!preg_match("/^([01]\d|2[0-3]):[0-5]\d$/", $horario)) {
            erro("O horário $horario não é válido. Use o formato 07:30.", "horariosRota");
        }

        $horarios[] = $horario;
    }

    if (!$horarios) {
        erro("Informe pelo menos um horário (ex.: 07:30, 15:00).", "horariosRota");
    }
    if (strlen($alerta) > 255) {
        erro("O aviso pode ter no máximo 255 caracteres.", "alertaRota");
    }

    return [
        "valores" => [$nome, $origem, $destino, (int) $passageiros, (int) $quilometragem, $alerta === "" ? null : $alerta],
        "horarios" => array_unique($horarios)
    ];
}

function gravarHorarios($rotaId, $horarios)
{
    executar("DELETE FROM horarios WHERE rota_id = ?", [$rotaId]);

    foreach ($horarios as $horario) {
        executar("INSERT INTO horarios (rota_id, horario) VALUES (?, ?)", [$rotaId, $horario]);
    }
}

if ($metodo === "POST") {
    $rota = dadosDaRota();

    executar(
        "INSERT INTO rotas (nome, origem, destino, passageiros, quilometragem, alerta) VALUES (?, ?, ?, ?, ?, ?)",
        $rota["valores"]
    );
    gravarHorarios($pdo->lastInsertId(), $rota["horarios"]);

    responder(["ok" => true], 201);
}

if ($metodo === "PUT") {
    $atual = buscarRota($_GET["id"] ?? 0);
    $rota = dadosDaRota($atual["id"]);
    $valores = $rota["valores"];
    $valores[] = $atual["id"];

    executar(
        "UPDATE rotas SET nome = ?, origem = ?, destino = ?, passageiros = ?, quilometragem = ?, alerta = ? WHERE id = ?",
        $valores
    );
    gravarHorarios($atual["id"], $rota["horarios"]);

    responder(["ok" => true]);
}

if ($metodo === "DELETE") {
    $rota = buscarRota($_GET["id"] ?? 0);

    if (consultar("SELECT id FROM manutencoes WHERE rota_id = ?", [$rota["id"]])) {
        erro("A " . $rota["nome"] . " tem manutenções registradas e não pode ser excluída.", null, 409);
    }

    executar("UPDATE trens SET status = 'disponivel', rota_id = NULL WHERE rota_id = ?", [$rota["id"]]);
    executar("DELETE FROM rotas WHERE id = ?", [$rota["id"]]);

    responder(["ok" => true]);
}

if (isset($_GET["id"])) {
    responder(buscarRota($_GET["id"]));
}

responder(consultar("SELECT * FROM rotas ORDER BY id"));
