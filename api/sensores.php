<?php

/*
 * Sensores IoT dos trens.
 * GET                 lista com a leitura mais recente de cada sensor (filtros: trem_id, status, busca)
 * GET ?id=            um sensor
 * POST                cadastra (gestor)
 * PUT ?id=            edita tipo, trem, situação e observação (gestor)
 * DELETE ?id=         exclui (gestor) — se já tiver leituras, apenas desativa para preservar o histórico
 */

require "conexao.php";
require "funcoes-leituras.php";

$usuario = exigirCargo("gestor", "maquinista");
$id = inteiro($_GET["id"] ?? 0);

if (metodo() !== "GET" && !eGestor($usuario)) {
    erro("Só o gestor pode cadastrar, editar ou excluir sensores.", null, 403);
}

const STATUS_SENSOR = ["ativo", "instalacao", "remocao", "inativo"];

function sensorOuErro($id)
{
    $sensor = buscarSensor($id);
    if (!$sensor) {
        erro("Sensor não encontrado.", null, 404);
    }
    return $sensor;
}

function dadosDoSensor($id = 0)
{
    $dados = dadosRecebidos();
    $tipo = tipoSensor($dados["tipo"] ?? "");
    $tremId = inteiro($dados["trem_id"] ?? 0);
    $status = $dados["status"] ?? "instalacao";
    $prazo = trim((string) ($dados["prazo"] ?? ""));
    $observacao = trim((string) ($dados["observacao"] ?? ""));

    if (!$tipo) {
        erro("Escolha o tipo de sensor.", "tipoSensor");
    }
    $trem = consultarUm("SELECT nome FROM trens WHERE id = ?", [$tremId]);
    if (!$trem) {
        erro("Escolha o trem que vai receber o sensor.", "tremSensor");
    }
    if (!in_array($status, STATUS_SENSOR, true)) {
        erro("Escolha a situação do sensor.", "statusSensor");
    }
    if (in_array($status, ["instalacao", "remocao"], true) && !dataValida($prazo)) {
        $prazo = date("Y-m-d", strtotime("+28 days"));
    }
    if (!in_array($status, ["instalacao", "remocao"], true)) {
        $prazo = "";
    }
    if (mb_strlen($observacao) > 200) {
        erro("A observação pode ter no máximo 200 caracteres.", "observacaoSensor");
    }
    if ($status !== "inativo" && consultar(
        "SELECT id FROM sensores WHERE tipo = ? AND trem_id = ? AND status IN ('ativo', 'instalacao') AND id <> ?",
        [$tipo["tipo"], $tremId, $id]
    )) {
        erro("O " . $trem["nome"] . " já tem um sensor " . $tipo["tipo"] . " ativo ou em instalação.", "tremSensor");
    }

    return [
        "tipo" => $tipo,
        "trem" => $trem["nome"],
        "trem_id" => $tremId,
        "status" => $status,
        "prazo" => $prazo ?: null,
        "observacao" => $observacao ?: null
    ];
}

if (metodo() === "POST") {
    $s = dadosDoSensor();

    executar(
        "INSERT INTO sensores (tipo, grandeza, chave, unidade, trem_id, status, prazo, instalado_em, observacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$s["tipo"]["tipo"], $s["tipo"]["grandeza"], $s["tipo"]["chave"], $s["tipo"]["unidade"], $s["trem_id"], $s["status"], $s["prazo"],
         $s["status"] === "ativo" ? hoje() : null, $s["observacao"]]
    );
    $novoId = ultimoId();

    if ($s["status"] === "instalacao") {
        criarAlerta("Instalação do sensor " . $s["tipo"]["tipo"] . " (" . $s["tipo"]["grandeza"] . ") no " . $s["trem"] . " prevista até " . formatarData($s["prazo"]) . ".", "normal", ["trem_id" => $s["trem_id"], "sensor_id" => $novoId]);
    }

    responder(["id" => $novoId], 201);
}

if (metodo() === "PUT") {
    $atual = sensorOuErro($id);
    $s = dadosDoSensor($id);

    if ($atual["trem_id"] != $s["trem_id"] && consultar("SELECT id FROM leituras WHERE sensor_id = ? LIMIT 1", [$id])) {
        erro("Este sensor já tem leituras no " . $atual["trem"] . ". Para usá-lo em outro trem, desative-o e cadastre um novo.", "tremSensor");
    }

    executar(
        "UPDATE sensores SET tipo = ?, grandeza = ?, chave = ?, unidade = ?, trem_id = ?, status = ?, prazo = ?, observacao = ?,
                instalado_em = IF(? = 'ativo' AND instalado_em IS NULL, CURDATE(), instalado_em)
         WHERE id = ?",
        [$s["tipo"]["tipo"], $s["tipo"]["grandeza"], $s["tipo"]["chave"], $s["tipo"]["unidade"], $s["trem_id"], $s["status"], $s["prazo"],
         $s["observacao"], $s["status"], $id]
    );

    if ($s["status"] === "remocao" && $atual["status"] !== "remocao") {
        criarAlerta("Remoção do sensor " . $s["tipo"]["tipo"] . " do " . $s["trem"] . " prevista até " . formatarData($s["prazo"]) . ".", "atencao", ["trem_id" => $s["trem_id"], "sensor_id" => $id]);
    }

    responder(buscarSensor($id));
}

if (metodo() === "DELETE") {
    $sensor = sensorOuErro($id);

    if (consultar("SELECT id FROM leituras WHERE sensor_id = ? LIMIT 1", [$id])) {
        executar("UPDATE sensores SET status = 'inativo', prazo = NULL WHERE id = ?", [$id]);
        responder(["ok" => true, "desativado" => true, "mensagem" => "O sensor tem leituras registradas e foi desativado para preservar o histórico."]);
    }

    executar("DELETE FROM sensores WHERE id = ?", [$id]);
    responder(["ok" => true, "desativado" => false]);
}

if (metodo() !== "GET") {
    erro("Método não permitido.", null, 405);
}

// Prazos vencidos: instalação concluída vira ativo; remoção concluída vira inativo (o histórico é mantido).
executar("UPDATE sensores SET status = 'ativo', prazo = NULL, instalado_em = COALESCE(instalado_em, CURDATE()) WHERE status = 'instalacao' AND prazo <= ?", [hoje()]);
executar("UPDATE sensores SET status = 'inativo', prazo = NULL WHERE status = 'remocao' AND prazo <= ?", [hoje()]);

$condicoes = [];
$valores = [];
$restricao = restricaoTrem($usuario);

if ($restricao !== null) {
    $condicoes[] = "sensores.trem_id = ?";
    $valores[] = $restricao;
}
if ($id) {
    $condicoes[] = "sensores.id = ?";
    $valores[] = $id;
}
if (inteiro($_GET["trem_id"] ?? 0)) {
    $condicoes[] = "sensores.trem_id = ?";
    $valores[] = inteiro($_GET["trem_id"]);
}
if (in_array($_GET["status"] ?? "", STATUS_SENSOR, true)) {
    $condicoes[] = "sensores.status = ?";
    $valores[] = $_GET["status"];
}
if (isset(ORDEM_NIVEL[$_GET["nivel"] ?? ""])) {
    $condicoes[] = "ultima.nivel = ?";
    $valores[] = $_GET["nivel"];
}
$busca = trim($_GET["busca"] ?? "");
if ($busca !== "") {
    $condicoes[] = "(sensores.tipo LIKE ? OR sensores.grandeza LIKE ? OR trens.nome LIKE ?)";
    array_push($valores, "%$busca%", "%$busca%", "%$busca%");
}

$sensores = consultar(
    "SELECT sensores.*, trens.nome AS trem, trens.codigo AS codigo_trem,
            ultima.id AS ultima_leitura_id, ultima.valor AS ultimo_valor, ultima.nivel AS ultimo_nivel, ultima.data_hora AS ultima_data,
            (SELECT COUNT(*) FROM leituras l3 WHERE l3.sensor_id = sensores.id) AS total_leituras
     FROM sensores
     JOIN trens ON trens.id = sensores.trem_id
     " . sqlUltimaLeitura() . onde($condicoes) . "
     ORDER BY trens.codigo, sensores.tipo, sensores.id",
    $valores
);

if ($id) {
    if (!$sensores) {
        erro("Sensor não encontrado.", null, 404);
    }
    responder($sensores[0]);
}

responder($sensores);
