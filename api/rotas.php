<?php

/*
 * Rotas.
 * GET          lista (filtros: busca, ativa, trem_id) | GET ?id= rota com horários, trens e próximas viagens
 * POST         cadastra (gestor)       PUT ?id=  edita (gestor)       DELETE ?id=  exclui (gestor)
 * O cliente vê apenas rotas ativas e não recebe dados internos (passageiros, quilometragem).
 */

require "conexao.php";

$usuario = exigirCargo();
$metodo = metodo();
$cliente = $usuario["cargo"] === "cliente";

if ($metodo !== "GET" && !eGestor($usuario)) {
    erro("Só o gestor pode alterar as rotas.", null, 403);
}

function buscarRota($id, $cliente = false)
{
    $rota = consultarUm("SELECT * FROM rotas WHERE id = ?" . ($cliente ? " AND ativa = 1" : ""), [$id]);

    if (!$rota) {
        erro("Rota não encontrada.", null, 404);
    }

    $rota["horarios"] = array_column(consultar("SELECT horario FROM horarios WHERE rota_id = ? ORDER BY horario", [$rota["id"]]), "horario");
    $rota["trens"] = consultar("SELECT id, codigo, nome, status FROM trens WHERE rota_id = ? ORDER BY codigo", [$rota["id"]]);
    $rota["viagens"] = consultar(
        "SELECT viagens.id, viagens.partida_prevista, viagens.chegada_prevista, viagens.status, trens.nome AS trem
         FROM viagens JOIN trens ON trens.id = viagens.trem_id
         WHERE viagens.rota_id = ? AND (viagens.status IN ('em_andamento', 'atrasada') OR viagens.partida_prevista >= CURDATE())
         ORDER BY viagens.partida_prevista
         LIMIT 10",
        [$rota["id"]]
    );

    if ($cliente) {
        unset($rota["passageiros"], $rota["quilometragem"]);
    }

    return $rota;
}

function dadosDaRota($id = 0)
{
    $dados = dadosRecebidos();
    $nome = trim((string) ($dados["nome"] ?? ""));
    $origem = trim((string) ($dados["origem"] ?? ""));
    $destino = trim((string) ($dados["destino"] ?? ""));
    $distancia = $dados["distancia_km"] ?? "";
    $quilometragem = $dados["quilometragem"] ?? 0;
    $passageiros = $dados["passageiros"] ?? 0;
    $alerta = trim((string) ($dados["alerta"] ?? ""));
    $ativa = !isset($dados["ativa"]) || !empty($dados["ativa"]) ? 1 : 0;
    $horarios = [];

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 30) {
        erro("Informe o nome da rota (ex.: Rota 40).", "nomeRota");
    }
    if (consultar("SELECT id FROM rotas WHERE nome = ? AND id <> ?", [$nome, $id])) {
        erro("Já existe uma rota com esse nome.", "nomeRota");
    }
    if (mb_strlen($origem) < 3 || mb_strlen($origem) > 80) {
        erro("Informe a cidade de origem.", "origemRota");
    }
    if (mb_strlen($destino) < 3 || mb_strlen($destino) > 80) {
        erro("Informe a cidade de destino.", "destinoRota");
    }
    if (mb_strtolower($origem) === mb_strtolower($destino)) {
        erro("A origem e o destino precisam ser diferentes.", "destinoRota");
    }
    if (!is_numeric($distancia) || $distancia <= 0 || $distancia > 10000) {
        erro("Informe a distância da rota em km.", "distanciaRota");
    }
    if (!is_numeric($quilometragem) || $quilometragem < 0) {
        erro("Informe a quilometragem acumulada (0 se for nova).", "quilometragemRota");
    }
    if (!is_numeric($passageiros) || $passageiros < 0) {
        erro("Informe quantos passageiros a rota transporta.", "passageirosRota");
    }

    $listaHorarios = is_array($dados["horarios"] ?? null) ? $dados["horarios"] : explode(",", (string) ($dados["horarios"] ?? ""));
    foreach ($listaHorarios as $horario) {
        $horario = trim((string) $horario);
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
    if (mb_strlen($alerta) > 255) {
        erro("O aviso pode ter no máximo 255 caracteres.", "alertaRota");
    }

    sort($horarios);

    return [
        "valores" => [$nome, $origem, $destino, (int) $distancia, (int) $passageiros, (int) $quilometragem, $alerta === "" ? null : $alerta, $ativa],
        "horarios" => array_values(array_unique($horarios)),
        "ativa" => $ativa
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
        "INSERT INTO rotas (nome, origem, destino, distancia_km, passageiros, quilometragem, alerta, ativa) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        $rota["valores"]
    );
    $novoId = ultimoId();
    gravarHorarios($novoId, $rota["horarios"]);

    responder(["id" => $novoId], 201);
}

if ($metodo === "PUT") {
    $atual = buscarRota(inteiro($_GET["id"] ?? 0));
    $rota = dadosDaRota($atual["id"]);

    if (!$rota["ativa"] && consultar("SELECT id FROM viagens WHERE rota_id = ? AND status IN ('em_andamento', 'atrasada') LIMIT 1", [$atual["id"]])) {
        erro("A rota tem viagens em andamento e não pode ser desativada agora.", "ativaRota");
    }

    $valores = $rota["valores"];
    $valores[] = $atual["id"];

    executar(
        "UPDATE rotas SET nome = ?, origem = ?, destino = ?, distancia_km = ?, passageiros = ?, quilometragem = ?, alerta = ?, ativa = ? WHERE id = ?",
        $valores
    );
    gravarHorarios($atual["id"], $rota["horarios"]);

    if (!$rota["ativa"]) {
        executar("UPDATE trens SET status = 'disponivel', rota_id = NULL WHERE rota_id = ?", [$atual["id"]]);
    }

    responder(["ok" => true]);
}

if ($metodo === "DELETE") {
    $rota = buscarRota(inteiro($_GET["id"] ?? 0));

    if (consultar("SELECT id FROM manutencoes WHERE rota_id = ? LIMIT 1", [$rota["id"]])) {
        erro("A " . $rota["nome"] . " tem manutenções registradas e não pode ser excluída. Desative a rota.", null, 409);
    }
    if (consultar("SELECT id FROM viagens WHERE rota_id = ? LIMIT 1", [$rota["id"]])) {
        erro("A " . $rota["nome"] . " tem viagens registradas e não pode ser excluída. Desative a rota.", null, 409);
    }

    executar("UPDATE trens SET status = 'disponivel', rota_id = NULL WHERE rota_id = ?", [$rota["id"]]);
    executar("DELETE FROM rotas WHERE id = ?", [$rota["id"]]);

    responder(["ok" => true]);
}

if ($metodo !== "GET") {
    erro("Método não permitido.", null, 405);
}

if (isset($_GET["id"])) {
    responder(buscarRota(inteiro($_GET["id"]), $cliente));
}

$condicoes = [];
$valores = [];

if ($cliente) {
    $condicoes[] = "rotas.ativa = 1";
} elseif (in_array($_GET["ativa"] ?? "", ["0", "1"], true)) {
    $condicoes[] = "rotas.ativa = ?";
    $valores[] = (int) $_GET["ativa"];
}
$busca = trim($_GET["busca"] ?? "");
if ($busca !== "") {
    $condicoes[] = "(rotas.nome LIKE ? OR rotas.origem LIKE ? OR rotas.destino LIKE ?)";
    array_push($valores, "%$busca%", "%$busca%", "%$busca%");
}
if (inteiro($_GET["trem_id"] ?? 0)) {
    $condicoes[] = "(EXISTS (SELECT 1 FROM trens t WHERE t.rota_id = rotas.id AND t.id = ?) OR EXISTS (SELECT 1 FROM viagens v WHERE v.rota_id = rotas.id AND v.trem_id = ?))";
    array_push($valores, inteiro($_GET["trem_id"]), inteiro($_GET["trem_id"]));
}

$colunas = $cliente
    ? "rotas.id, rotas.nome, rotas.origem, rotas.destino, rotas.distancia_km, rotas.alerta, rotas.ativa"
    : "rotas.*";

responder(consultar(
    "SELECT $colunas,
            (SELECT GROUP_CONCAT(horario ORDER BY horario SEPARATOR ', ') FROM horarios WHERE horarios.rota_id = rotas.id) AS horarios,
            (SELECT GROUP_CONCAT(nome ORDER BY codigo SEPARATOR ', ') FROM trens WHERE trens.rota_id = rotas.id) AS trens,
            (SELECT COUNT(*) FROM viagens WHERE viagens.rota_id = rotas.id AND viagens.status IN ('em_andamento', 'atrasada')) AS viagens_em_andamento
     FROM rotas" . onde($condicoes) . "
     ORDER BY rotas.ativa DESC, CHAR_LENGTH(rotas.nome), rotas.nome",
    $valores
));
