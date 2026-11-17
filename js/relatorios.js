/* Relatórios com dados do banco: desempenho, consumo de energia, eficiência e manutenção.
   Filtro de período (data final inclusiva), exportação CSV e impressão. */

iniciarPagina(async (usuario) => {
    const gestor = usuario.cargo === "gestor";
    const filtros = document.getElementById("filtrosRelatorio");
    const tabela = document.getElementById("tabelaRelatorio");
    let tipo = new URLSearchParams(location.search).get("tipo") || "desempenho";
    let ultimo = null;

    document.querySelectorAll(".somente-gestor").forEach((el) => (el.hidden = !gestor));
    if (gestor) {
        const opcoes = await api("opcoes.php");
        preencherSelect(document.getElementById("relTrem"), opcoesTrens(opcoes.trens), "Todos os trens");
    }

    document.getElementById("dataInicial").value = diasAtras(29);
    document.getElementById("dataFinal").value = hoje();

    function parametros() {
        const p = lerFiltros(filtros);
        p.set("tipo", tipo);
        if (tipo !== "manutencao") {
            p.delete("status");
            p.delete("tipo_manutencao");
        }
        return p;
    }

    async function carregar() {
        const r = await api("relatorios.php?" + parametros());
        ultimo = r;
        document.getElementById("tituloRelatorio").textContent = r.titulo;
        document.getElementById("resumoRelatorio").innerHTML = r.resumo.map((item) => `
            <div class="item"><strong>${formatarValor(item.valor, item.tipo)}</strong><span>${escapar(item.rotulo)}</span></div>`).join("");
        document.getElementById("observacaoRelatorio").textContent = r.observacao || "";

        tabela.querySelector("thead tr").innerHTML = r.colunas.map((c) => `<th>${escapar(c.rotulo)}</th>`).join("");
        preencherTabela(tabela, r.linhas.map((linha) => `
            <tr>${r.colunas.map((c) => `<td>${formatarValor(linha[c.chave], c.tipo)}</td>`).join("")}</tr>`).join(""),
            "Nenhum dado encontrado para o período informado.");

        const f = r.filtros;
        const periodo = f.data_inicial || f.data_final
            ? `${f.data_inicial ? formatarData(f.data_inicial) : "início"} a ${f.data_final ? formatarData(f.data_final) : "hoje"}`
            : "todo o período";
        const trem = document.getElementById("relTrem");
        const nomeTrem = !gestor ? (usuario.trem || "seu trem") : (trem.value ? trem.selectedOptions[0].textContent : "todos os trens");
        document.getElementById("cabecalhoImpressao").innerHTML =
            `<strong>Ferrorama TR4 — ${escapar(r.titulo)}</strong><br>Período: ${periodo} · Trem: ${escapar(nomeTrem)} · Gerado em ${formatarDataHora(r.gerado_em)} por ${escapar(usuario.nome)}`;
    }

    function selecionar(novoTipo) {
        tipo = novoTipo;
        document.querySelectorAll("#abasRelatorio button").forEach((b) => b.setAttribute("aria-selected", String(b.dataset.tipo === tipo)));
        document.querySelectorAll(".somente-manutencao").forEach((el) => (el.hidden = tipo !== "manutencao"));
        carregar().catch(mostrarErro);
    }

    document.querySelectorAll("#abasRelatorio button").forEach((b) => b.addEventListener("click", () => selecionar(b.dataset.tipo)));
    filtros.addEventListener("submit", (e) => { e.preventDefault(); carregar().catch(mostrarErro); });

    document.getElementById("btnCsv").addEventListener("click", (e) =>
        comCarregamento(e.currentTarget, () => baixarArquivo("exportar.php?" + parametros(), `relatorio-${tipo}.csv`)).catch(mostrarErro));

    document.getElementById("btnImprimir").addEventListener("click", () => {
        if (ultimo) window.print();
    });

    selecionar(["desempenho", "energia", "eficiencia", "manutencao"].includes(tipo) ? tipo : "desempenho");
});
