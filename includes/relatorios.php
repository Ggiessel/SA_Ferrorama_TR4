<?php

/*
 * Relatórios do Ferrorama TR4. Usados por api/relatorios.php (JSON) e api/exportar.php (CSV).
 * Cada relatório devolve: titulo, colunas [chave, rotulo, tipo], linhas e resumo.
 * Tipos de coluna: texto, inteiro, numero, moeda, porcentagem, data, datahora.
 * Médias e divisões sem dados resultam em null (exibido como "—"), nunca em zero inventado.
 */

const RELATORIOS = [
    "desempenho" => "Desempenho operacional",
    "energia" => "Consumo de energia (combustível)",
    "eficiencia" => "Eficiência operacional",
    "manutencao" => "Manutenção"
];

function dividir($a, $b, $multiplicador = 1)
{
    return ($b === null || (float) $b == 0.0 || $a === null) ? null : round((float) $a / (float) $b * $multiplicador, 2);
}

function numeroOuNulo($valor, $casas = 2)
{
    return $valor === null ? null : round((float) $valor, $casas);
}

/** Lê e valida os filtros comuns. O maquinista fica sempre restrito ao próprio trem. */
function filtrosRelatorio($entrada, $usuario)
{
    $filtros = [
        "data_inicial" => trim((string) ($entrada["data_inicial"] ?? "")),
        "data_final" => trim((string) ($entrada["data_final"] ?? "")),
        "trem_id" => inteiro($entrada["trem_id"] ?? 0),
        "status" => (string) ($entrada["status"] ?? ""),
        "tipo_manutencao" => (string) ($entrada["tipo_manutencao"] ?? "")
    ];
    $restricao = restricaoTrem($usuario);
    if ($restricao !== null) {
        $filtros["trem_id"] = $restricao;
    }
    // Valida as datas (dispara erro 400 se inválidas).
    $c = [];
    $v = [];
    filtroPeriodo("x", $filtros["data_inicial"], $filtros["data_final"], $c, $v);
    return $filtros;
}

/** Condições de período + trem para uma tabela. */
function condicoes($filtros, $colunaData, $colunaTrem)
{
    $condicoes = [];
    $valores = [];
    filtroPeriodo($colunaData, $filtros["data_inicial"], $filtros["data_final"], $condicoes, $valores);
    if ($filtros["trem_id"]) {
        $condicoes[] = "$colunaTrem = ?";
        $valores[] = $filtros["trem_id"];
    }
    return [$condicoes, $valores];
}

function trensDoRelatorio($filtros)
{
    $sql = "SELECT id, codigo, nome, consumo FROM trens";
    $valores = [];
    if ($filtros["trem_id"]) {
        $sql .= " WHERE id = ?";
        $valores[] = $filtros["trem_id"];
    }
    return consultar($sql . " ORDER BY codigo", $valores);
}

function indexarPorTrem($linhas)
{
    $resultado = [];
    foreach ($linhas as $linha) {
        $resultado[$linha["trem_id"]] = $linha;
    }
    return $resultado;
}

function dadosViagens($filtros)
{
    [$c, $v] = condicoes($filtros, "viagens.partida_prevista", "viagens.trem_id");
    return indexarPorTrem(consultar(
        "SELECT trem_id,
                COUNT(*) AS viagens,
                SUM(status = 'concluida') AS concluidas,
                SUM(status = 'cancelada') AS canceladas,
                SUM(status IN ('em_andamento', 'atrasada')) AS em_andamento,
                SUM(status = 'concluida' AND chegada_real <= chegada_prevista + INTERVAL 15 MINUTE) AS no_prazo,
                AVG(CASE WHEN status = 'concluida' THEN GREATEST(TIMESTAMPDIFF(MINUTE, chegada_prevista, chegada_real), 0) END) AS atraso_medio,
                SUM(CASE WHEN status = 'concluida' THEN km_percorridos ELSE 0 END) AS km,
                SUM(CASE WHEN partida_real IS NOT NULL AND status <> 'cancelada'
                         THEN TIMESTAMPDIFF(MINUTE, partida_real, COALESCE(chegada_real, NOW())) ELSE 0 END) / 60 AS horas
         FROM viagens" . onde($c) . "
         GROUP BY trem_id",
        $v
    ));
}

function dadosLeituras($filtros, $chave)
{
    [$c, $v] = condicoes($filtros, "leituras.data_hora", "leituras.trem_id");
    $c[] = "sensores.chave = ?";
    $v[] = $chave;
    return indexarPorTrem(consultar(
        "SELECT leituras.trem_id, COUNT(*) AS leituras, AVG(leituras.valor) AS media, MAX(leituras.valor) AS maximo,
                SUM(leituras.nivel = 'critico') AS criticas
         FROM leituras JOIN sensores ON sensores.id = leituras.sensor_id" . onde($c) . "
         GROUP BY leituras.trem_id",
        $v
    ));
}

function dadosNiveis($filtros)
{
    [$c, $v] = condicoes($filtros, "leituras.data_hora", "leituras.trem_id");
    return indexarPorTrem(consultar(
        "SELECT trem_id, COUNT(*) AS leituras, SUM(nivel = 'normal') AS normais, SUM(nivel = 'atencao') AS atencao, SUM(nivel = 'critico') AS criticas
         FROM leituras" . onde($c) . " GROUP BY trem_id",
        $v
    ));
}

function horasDoPeriodo($filtros)
{
    if (!dataValida($filtros["data_inicial"]) || !dataValida($filtros["data_final"])) {
        return null;
    }
    $fim = min(strtotime($filtros["data_final"] . " +1 day"), time());
    $horas = ($fim - strtotime($filtros["data_inicial"])) / 3600;
    return $horas > 0 ? $horas : null;
}

function relatorioDesempenho($filtros)
{
    $viagens = dadosViagens($filtros);
    $velocidade = dadosLeituras($filtros, "velocidade");
    $linhas = [];
    $total = ["viagens" => 0, "concluidas" => 0, "no_prazo" => 0, "km" => 0];

    foreach (trensDoRelatorio($filtros) as $trem) {
        $vi = $viagens[$trem["id"]] ?? null;
        $ve = $velocidade[$trem["id"]] ?? null;
        $concluidas = (int) ($vi["concluidas"] ?? 0);
        $linhas[] = [
            "trem" => $trem["nome"],
            "viagens" => (int) ($vi["viagens"] ?? 0),
            "concluidas" => $concluidas,
            "canceladas" => (int) ($vi["canceladas"] ?? 0),
            "pontualidade" => dividir($vi["no_prazo"] ?? null, $concluidas, 100),
            "atraso_medio" => numeroOuNulo($vi["atraso_medio"] ?? null, 1),
            "km" => (int) ($vi["km"] ?? 0),
            "velocidade_media" => numeroOuNulo($ve["media"] ?? null, 1),
            "velocidade_maxima" => numeroOuNulo($ve["maximo"] ?? null, 1)
        ];
        $total["viagens"] += (int) ($vi["viagens"] ?? 0);
        $total["concluidas"] += $concluidas;
        $total["no_prazo"] += (int) ($vi["no_prazo"] ?? 0);
        $total["km"] += (int) ($vi["km"] ?? 0);
    }

    return [
        "colunas" => [
            ["trem", "Trem", "texto"], ["viagens", "Viagens", "inteiro"], ["concluidas", "Concluídas", "inteiro"],
            ["canceladas", "Canceladas", "inteiro"], ["pontualidade", "Pontualidade", "porcentagem"],
            ["atraso_medio", "Atraso médio (min)", "numero"], ["km", "Km percorridos", "inteiro"],
            ["velocidade_media", "Velocidade média (km/h)", "numero"], ["velocidade_maxima", "Velocidade máxima (km/h)", "numero"]
        ],
        "linhas" => $linhas,
        "resumo" => [
            ["Viagens no período", $total["viagens"], "inteiro"],
            ["Viagens concluídas", $total["concluidas"], "inteiro"],
            ["Pontualidade geral", dividir($total["no_prazo"], $total["concluidas"], 100), "porcentagem"],
            ["Km percorridos", $total["km"], "inteiro"]
        ]
    ];
}

function relatorioEnergia($filtros)
{
    $viagens = dadosViagens($filtros);
    $consumo = dadosLeituras($filtros, "consumo");
    $linhas = [];
    $totalLitros = 0;
    $totalHoras = 0;
    $temLitros = false;

    foreach (trensDoRelatorio($filtros) as $trem) {
        $vi = $viagens[$trem["id"]] ?? null;
        $co = $consumo[$trem["id"]] ?? null;
        $horas = $vi ? round((float) $vi["horas"], 1) : 0;
        $media = numeroOuNulo($co["media"] ?? null);
        $litros = $media !== null ? round($media * $horas, 1) : null;
        $linhas[] = [
            "trem" => $trem["nome"],
            "leituras" => (int) ($co["leituras"] ?? 0),
            "consumo_medio" => $media,
            "consumo_maximo" => numeroOuNulo($co["maximo"] ?? null),
            "leituras_criticas" => (int) ($co["criticas"] ?? 0),
            "horas" => $horas,
            "litros" => $litros,
            "km" => (int) ($vi["km"] ?? 0)
        ];
        $totalHoras += $horas;
        if ($litros !== null) {
            $totalLitros += $litros;
            $temLitros = true;
        }
    }

    return [
        "colunas" => [
            ["trem", "Trem", "texto"], ["leituras", "Leituras de consumo", "inteiro"], ["consumo_medio", "Consumo médio (L/h)", "numero"],
            ["consumo_maximo", "Consumo máximo (L/h)", "numero"], ["leituras_criticas", "Leituras críticas", "inteiro"],
            ["horas", "Horas em viagem", "numero"], ["litros", "Combustível estimado (L)", "numero"], ["km", "Km percorridos", "inteiro"]
        ],
        "linhas" => $linhas,
        "resumo" => [
            ["Horas em viagem", round($totalHoras, 1), "numero"],
            ["Combustível estimado (L)", $temLitros ? round($totalLitros, 1) : null, "numero"],
            ["Limite crítico de consumo (L/h)", LIMITES_SENSORES["consumo"]["critico"], "numero"]
        ],
        "observacao" => "Combustível estimado = consumo médio medido pelo sensor (L/h) × horas em viagem no período. Trens sem sensor de consumo aparecem sem estimativa."
    ];
}

function relatorioEficiencia($filtros)
{
    $viagens = dadosViagens($filtros);
    $consumo = dadosLeituras($filtros, "consumo");
    $niveis = dadosNiveis($filtros);
    $horasPeriodo = horasDoPeriodo($filtros);
    $linhas = [];

    foreach (trensDoRelatorio($filtros) as $trem) {
        $vi = $viagens[$trem["id"]] ?? null;
        $co = $consumo[$trem["id"]] ?? null;
        $ni = $niveis[$trem["id"]] ?? null;
        $horas = $vi ? (float) $vi["horas"] : 0;
        $km = (int) ($vi["km"] ?? 0);
        $litros = isset($co["media"]) ? (float) $co["media"] * $horas : null;
        $kmPorLitro = $km > 0 ? dividir($km, $litros) : null;
        $linhas[] = [
            "trem" => $trem["nome"],
            "km" => $km,
            "km_l_real" => $kmPorLitro,
            "km_l_nominal" => (float) $trem["consumo"],
            "eficiencia" => $kmPorLitro !== null ? dividir($kmPorLitro, $trem["consumo"], 100) : null,
            "utilizacao" => $horasPeriodo ? min(100, dividir($horas, $horasPeriodo, 100)) : null,
            "leituras_normais" => dividir($ni["normais"] ?? null, $ni["leituras"] ?? null, 100),
            "leituras_criticas" => (int) ($ni["criticas"] ?? 0),
            "canceladas" => (int) ($vi["canceladas"] ?? 0)
        ];
    }

    $comNormais = array_filter(array_column($linhas, "leituras_normais"), fn($x) => $x !== null);
    $comUtilizacao = array_filter(array_column($linhas, "utilizacao"), fn($x) => $x !== null);

    return [
        "colunas" => [
            ["trem", "Trem", "texto"], ["km", "Km percorridos", "inteiro"], ["km_l_real", "Km/L real", "numero"],
            ["km_l_nominal", "Km/L nominal", "numero"], ["eficiencia", "Real ÷ nominal", "porcentagem"],
            ["utilizacao", "Utilização da frota", "porcentagem"], ["leituras_normais", "Leituras normais", "porcentagem"],
            ["leituras_criticas", "Leituras críticas", "inteiro"], ["canceladas", "Viagens canceladas", "inteiro"]
        ],
        "linhas" => $linhas,
        "resumo" => [
            ["Leituras normais (média)", $comNormais ? round(array_sum($comNormais) / count($comNormais), 1) : null, "porcentagem"],
            ["Utilização média", $comUtilizacao ? round(array_sum($comUtilizacao) / count($comUtilizacao), 1) : null, "porcentagem"],
            ["Trens analisados", count($linhas), "inteiro"]
        ],
        "observacao" => "Utilização = horas em viagem ÷ horas do período (exige data inicial e final). Km/L real = km percorridos ÷ combustível estimado."
    ];
}

function relatorioManutencao($filtros)
{
    [$c, $v] = condicoes($filtros, "manutencoes.data", "manutencoes.trem_id");
    if (in_array($filtros["status"], ["aberta", "em_andamento", "concluida"], true)) {
        $c[] = "manutencoes.status = ?";
        $v[] = $filtros["status"];
    }
    if (in_array($filtros["tipo_manutencao"], ["preventiva", "corretiva"], true)) {
        $c[] = "manutencoes.tipo = ?";
        $v[] = $filtros["tipo_manutencao"];
    }

    $linhas = consultar(
        "SELECT manutencoes.data, trens.nome AS trem, manutencoes.tipo, manutencoes.causa, manutencoes.status,
                manutencoes.custo, manutencoes.data_conclusao, rotas.nome AS rota,
                DATEDIFF(COALESCE(manutencoes.data_conclusao, CURDATE()), manutencoes.data) AS dias
         FROM manutencoes
         JOIN trens ON trens.id = manutencoes.trem_id
         LEFT JOIN rotas ON rotas.id = manutencoes.rota_id" . onde($c) . "
         ORDER BY manutencoes.data DESC, manutencoes.id DESC",
        $v
    );

    $nomes = ["preventiva" => "Preventiva", "corretiva" => "Corretiva", "aberta" => "Aberta", "em_andamento" => "Em andamento", "concluida" => "Concluída"];
    $custoTotal = 0;
    $temCusto = false;
    $abertas = 0;
    $diasConcluidas = [];

    foreach ($linhas as &$linha) {
        if ($linha["custo"] !== null) {
            $custoTotal += (float) $linha["custo"];
            $temCusto = true;
            $linha["custo"] = (float) $linha["custo"];
        }
        if ($linha["status"] !== "concluida") {
            $abertas++;
        } else {
            $diasConcluidas[] = (int) $linha["dias"];
        }
        $linha["dias"] = (int) $linha["dias"];
        $linha["tipo"] = $nomes[$linha["tipo"]];
        $linha["status"] = $nomes[$linha["status"]];
        $linha["rota"] = $linha["rota"] ?? "—";
    }
    unset($linha);

    return [
        "colunas" => [
            ["data", "Abertura", "data"], ["trem", "Trem", "texto"], ["tipo", "Tipo", "texto"], ["causa", "Causa", "texto"],
            ["rota", "Rota", "texto"], ["status", "Situação", "texto"], ["custo", "Custo", "moeda"],
            ["data_conclusao", "Conclusão", "data"], ["dias", "Dias parado", "inteiro"]
        ],
        "linhas" => $linhas,
        "resumo" => [
            ["Manutenções", count($linhas), "inteiro"],
            ["Abertas ou em andamento", $abertas, "inteiro"],
            ["Custo total", $temCusto ? round($custoTotal, 2) : null, "moeda"],
            ["Tempo médio de conclusão (dias)", $diasConcluidas ? round(array_sum($diasConcluidas) / count($diasConcluidas), 1) : null, "numero"]
        ]
    ];
}

function gerarRelatorio($tipo, $entrada, $usuario)
{
    if (!isset(RELATORIOS[$tipo])) {
        erro("Relatório inválido.", null, 400);
    }
    $filtros = filtrosRelatorio($entrada, $usuario);
    $funcoes = [
        "desempenho" => "relatorioDesempenho",
        "energia" => "relatorioEnergia",
        "eficiencia" => "relatorioEficiencia",
        "manutencao" => "relatorioManutencao"
    ];
    $relatorio = $funcoes[$tipo]($filtros);
    $relatorio["tipo"] = $tipo;
    $relatorio["titulo"] = RELATORIOS[$tipo];
    $relatorio["filtros"] = $filtros;
    $relatorio["colunas"] = array_map(fn($c) => ["chave" => $c[0], "rotulo" => $c[1], "tipo" => $c[2]], $relatorio["colunas"]);
    $relatorio["resumo"] = array_map(fn($r) => ["rotulo" => $r[0], "valor" => $r[1], "tipo" => $r[2]], $relatorio["resumo"]);
    $relatorio["gerado_em"] = date("Y-m-d H:i:s");
    return $relatorio;
}

/** Formata um valor para o CSV no padrão brasileiro (vírgula decimal, sem separador de milhar). */
function valorCsv($valor, $tipo)
{
    if ($valor === null || $valor === "") {
        return "";
    }
    switch ($tipo) {
        case "inteiro":
            return (string) (int) $valor;
        case "numero":
        case "moeda":
            return number_format((float) $valor, 2, ",", "");
        case "porcentagem":
            return number_format((float) $valor, 1, ",", "") . "%";
        case "data":
            return date("d/m/Y", strtotime($valor));
        case "datahora":
            return date("d/m/Y H:i", strtotime($valor));
        default:
            return (string) $valor;
    }
}
