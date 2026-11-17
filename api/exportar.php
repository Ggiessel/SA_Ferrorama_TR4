<?php

/*
 * Exportação CSV (UTF-8 com BOM, separador ";", vírgula decimal) — abre direto no Excel.
 * GET ?tipo=desempenho|energia|eficiencia|manutencao|leituras  + os mesmos filtros da tela.
 * O arquivo contém somente dados: nenhuma marcação HTML é enviada.
 */

require "conexao.php";
require "funcoes-leituras.php";
require_once __DIR__ . "/../includes/relatorios.php";

$usuario = exigirCargo("gestor", "maquinista");
$tipo = $_GET["tipo"] ?? "";

if ($tipo === "leituras") {
    $dados = buscarLeituras($_GET, $usuario, 5000);
    $titulo = "Leituras dos sensores";
    $colunas = [
        ["chave" => "data_hora", "rotulo" => "Data e hora", "tipo" => "datahora"],
        ["chave" => "trem", "rotulo" => "Trem", "tipo" => "texto"],
        ["chave" => "tipo", "rotulo" => "Sensor", "tipo" => "texto"],
        ["chave" => "grandeza", "rotulo" => "Grandeza", "tipo" => "texto"],
        ["chave" => "valor", "rotulo" => "Valor", "tipo" => "numero"],
        ["chave" => "unidade", "rotulo" => "Unidade", "tipo" => "texto"],
        ["chave" => "nivel_nome", "rotulo" => "Nível", "tipo" => "texto"]
    ];
    $linhas = array_map(function ($l) {
        $l["nivel_nome"] = NOMES_NIVEL[$l["nivel"]];
        return $l;
    }, $dados["leituras"]);
} else {
    $relatorio = gerarRelatorio($tipo, $_GET, $usuario);
    $titulo = $relatorio["titulo"];
    $colunas = $relatorio["colunas"];
    $linhas = $relatorio["linhas"];
}

$nomeArquivo = ($tipo === "leituras" ? "leituras" : "relatorio-" . $tipo) . "-" . date("Y-m-d") . ".csv";

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nomeArquivo\"");

$saida = fopen("php://output", "w");
fwrite($saida, "\xEF\xBB\xBF");

fputcsv($saida, array_column($colunas, "rotulo"), ";", "\"", "");

foreach ($linhas as $linha) {
    $registro = [];
    foreach ($colunas as $coluna) {
        $registro[] = valorCsv($linha[$coluna["chave"]] ?? null, $coluna["tipo"]);
    }
    fputcsv($saida, $registro, ";", "\"", "");
}

fclose($saida);
exit;
