/*
  FERRORAMA TR4 — RELATÓRIOS E ANÁLISES (paginas/relatorios.html)

  - GET /api/relatorios/resumo -> números dos cartões
  - GET /api/cargas            -> tabela de cargas
  - GET /api/manutencoes       -> tabela de manutenções
  "Salvar como PDF" usa a impressão do navegador.
*/

TR4.iniciar(async function () {
    "use strict";

    const respostas = await Promise.all([
        TR4.api("/relatorios/resumo"),
        TR4.api("/cargas"),
        TR4.api("/manutencoes")
    ]);
    const resumo = respostas[0], cargas = respostas[1], manutencoes = respostas[2];

    /* ---------- Resumo ---------- */
    const cartoes = [
        { numero: resumo.cargasCadastradas, texto: "cargas cadastradas" },
        { numero: resumo.cargasEmTransito, texto: "cargas em trânsito" },
        { numero: resumo.cargasEntregues, texto: "cargas entregues" },
        { numero: resumo.trensEmManutencao, texto: "trens em manutenção" },
        { numero: resumo.sensoresAtivos, texto: "sensores ativos" }
    ];

    document.getElementById("resumoRelatorio").innerHTML = cartoes.map(function (c) {
        return '<div class="item-resumo"><strong>' + c.numero + "</strong><span>" + c.texto + "</span></div>";
    }).join("");

    /* ---------- Tabelas ---------- */
    function preencherTabela(id, linhas, colunas) {
        const corpo = document.querySelector("#" + id + " tbody");
        if (linhas.length === 0) {
            corpo.innerHTML = '<tr><td colspan="' + colunas + '" class="estado-vazio">Nenhum registro ainda.</td></tr>';
            return;
        }
        corpo.innerHTML = linhas.map(function (celulas) {
            return "<tr>" + celulas.map(function (c) { return "<td>" + TR4.escapar(c) + "</td>"; }).join("") + "</tr>";
        }).join("");
    }

    preencherTabela("tabelaCargas", cargas
        .slice()
        .sort(function (a, b) { return String(b.partida).localeCompare(String(a.partida)); })
        .map(function (c) {
            return [c.tipo, TR4.formatar.numero(c.toneladas) + " t", c.destino, TR4.formatar.data(c.partida), c.statusTexto];
        }), 5);

    preencherTabela("tabelaManutencoes", manutencoes.map(function (m) {
        return [m.codigo, m.rota, m.carga, m.causa, TR4.formatar.data(m.data)];
    }), 5);

    document.getElementById("btnImprimir").addEventListener("click", function () {
        window.print();
    });
});
