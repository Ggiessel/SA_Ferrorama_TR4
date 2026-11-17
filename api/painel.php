<?php

/*
 * Painel (dashboard) com os cinco indicadores, calculados no banco:
 * 1. Trens por situação operacional      2. Alertas ativos por gravidade
 * 3. Sensores com leitura mais recente crítica   4. Viagens em andamento
 * 5. Velocidade média da frota (leituras de velocidade das últimas 24 horas)
 * Gestor: frota inteira. Maquinista: indicadores 2, 3 e 5 restritos ao seu trem.
 */

require "conexao.php";
require "funcoes-leituras.php";

$usuario = exigirCargo("gestor", "maquinista");
$restricao = restricaoTrem($usuario);

// 1. Trens por situação (todas as situações aparecem, mesmo com zero)
$trens = ["em_rota" => 0, "disponivel" => 0, "manutencao" => 0, "inativo" => 0];
foreach (consultar("SELECT status, COUNT(*) AS total FROM trens GROUP BY status") as $linha) {
    $trens[$linha["status"]] = (int) $linha["total"];
}

// 2. Alertas ativos por gravidade
$filtroAlerta = $restricao !== null ? " AND (trem_id = ? OR publico = 1)" : "";
$valoresAlerta = $restricao !== null ? [$restricao] : [];
$alertas = ["critico" => 0, "atencao" => 0, "normal" => 0];
foreach (consultar("SELECT nivel, COUNT(*) AS total FROM alertas WHERE situacao = 'ativo'" . $filtroAlerta . " GROUP BY nivel", $valoresAlerta) as $linha) {
    $alertas[$linha["nivel"]] = (int) $linha["total"];
}

// 3. Sensores cuja leitura mais recente é crítica
$filtroSensor = $restricao !== null ? " AND sensores.trem_id = ?" : "";
$valoresSensor = $restricao !== null ? [$restricao] : [];
$sensoresCriticos = consultar(
    "SELECT sensores.id, sensores.tipo, sensores.grandeza, sensores.unidade, trens.nome AS trem,
            ultima.valor, ultima.data_hora
     FROM sensores
     JOIN trens ON trens.id = sensores.trem_id
     " . sqlUltimaLeitura() . "
     WHERE sensores.status = 'ativo' AND ultima.nivel = 'critico'" . $filtroSensor . "
     ORDER BY ultima.data_hora DESC",
    $valoresSensor
);
$sensoresMonitorados = (int) consultarUm(
    "SELECT COUNT(*) AS total FROM sensores WHERE status = 'ativo'" . $filtroSensor,
    $valoresSensor
)["total"];

// 4. Viagens em andamento (inclui as atrasadas, que também estão em curso)
$viagens = consultar(
    "SELECT viagens.id, viagens.status, viagens.partida_real, viagens.chegada_prevista,
            rotas.nome AS rota, rotas.origem, rotas.destino, trens.nome AS trem
     FROM viagens
     JOIN rotas ON rotas.id = viagens.rota_id
     JOIN trens ON trens.id = viagens.trem_id
     WHERE viagens.status IN ('em_andamento', 'atrasada')
     ORDER BY viagens.chegada_prevista"
);

// 5. Velocidade média da frota nas últimas 24 horas (null quando não há leituras)
$filtroVelocidade = $restricao !== null ? " AND leituras.trem_id = ?" : "";
$valoresVelocidade = array_merge([date("Y-m-d H:i:s", time() - 86400)], $restricao !== null ? [$restricao] : []);
$velocidade = consultarUm(
    "SELECT AVG(leituras.valor) AS media, COUNT(*) AS leituras, COUNT(DISTINCT leituras.trem_id) AS trens
     FROM leituras JOIN sensores ON sensores.id = leituras.sensor_id
     WHERE sensores.chave = 'velocidade' AND leituras.data_hora >= ?" . $filtroVelocidade,
    $valoresVelocidade
);
$velocidadePorTrem = consultar(
    "SELECT trens.nome AS trem, AVG(leituras.valor) AS media, COUNT(*) AS leituras
     FROM leituras
     JOIN sensores ON sensores.id = leituras.sensor_id
     JOIN trens ON trens.id = leituras.trem_id
     WHERE sensores.chave = 'velocidade' AND leituras.data_hora >= ?" . $filtroVelocidade . "
     GROUP BY trens.id, trens.nome, trens.codigo
     ORDER BY trens.codigo",
    $valoresVelocidade
);

responder([
    "gerado_em" => date("Y-m-d H:i:s"),
    "escopo" => $restricao !== null ? ($usuario["trem"] ?? "Seu trem") : "Frota",
    "trens_por_situacao" => $trens,
    "total_trens" => array_sum($trens),
    "alertas_ativos" => $alertas,
    "sensores_criticos" => $sensoresCriticos,
    "sensores_monitorados" => $sensoresMonitorados,
    "viagens_em_andamento" => $viagens,
    "velocidade_media" => [
        "media" => $velocidade["media"] !== null ? round((float) $velocidade["media"], 1) : null,
        "leituras" => (int) $velocidade["leituras"],
        "trens" => (int) $velocidade["trens"],
        "limite_atencao" => LIMITES_SENSORES["velocidade"]["atencao"],
        "limite_critico" => LIMITES_SENSORES["velocidade"]["critico"],
        "por_trem" => $velocidadePorTrem
    ]
]);
