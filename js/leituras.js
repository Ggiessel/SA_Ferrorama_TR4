/* Leituras: consulta com filtros, exportação CSV e registro manual de leitura. */

iniciarPagina(async () => {
    const opcoes = await api("opcoes.php");
    const sensores = await api("sensores.php");
    const filtros = document.getElementById("filtrosLeituras");
    const tabela = document.getElementById("tabelaLeituras");
    const filtroTrem = document.getElementById("filtroTrem");
    const filtroSensor = document.getElementById("filtroSensor");
    const parametros = new URLSearchParams(location.search);

    preencherSelect(filtroTrem, opcoesTrens(opcoes.trens), "Todos os trens");
    preencherSelect(document.getElementById("filtroChave"),
        Object.entries(opcoes.limites).map(([chave, l]) => ({ valor: chave, texto: l.nome })), "Todas");

    function atualizarSensores() {
        const lista = sensores.filter((s) => !filtroTrem.value || s.trem_id == filtroTrem.value);
        preencherSelect(filtroSensor, lista.map((s) => ({ valor: s.id, texto: `${s.tipo} · ${s.trem}` })), "Todos os sensores");
    }
    filtroTrem.addEventListener("change", atualizarSensores);

    filtroTrem.value = parametros.get("trem") || "";
    atualizarSensores();
    filtroSensor.value = parametros.get("sensor") || "";

    const ativos = sensores.filter((s) => s.status === "ativo");
    preencherSelect(document.getElementById("sensorLeitura"),
        ativos.map((s) => ({ valor: s.id, texto: `${s.trem} · ${s.tipo} (${s.grandeza}, ${s.unidade})` })), "Escolha o sensor");

    async function carregar() {
        const resposta = await api("leituras.php?" + lerFiltros(filtros));
        const r = resposta;

        document.getElementById("resumoLeituras").innerHTML = `
            <div class="item"><strong>${numero(r.total, 0)}</strong><span>leituras encontradas</span></div>
            <div class="item"><strong class="valor-atencao">${numero(r.atencao, 0)}</strong><span>em atenção</span></div>
            <div class="item"><strong class="valor-critico">${numero(r.critico, 0)}</strong><span>críticas</span></div>`;
        document.getElementById("avisoLimite").textContent = r.total > r.leituras.length
            ? `Mostrando as ${r.leituras.length} leituras mais recentes. Use os filtros ou exporte o CSV para ver todas.`
            : "";

        preencherTabela(tabela, r.leituras.map((l) => `
            <tr>
                <td>${formatarDataHora(l.data_hora)}</td>
                <td>${escapar(l.trem)}</td>
                <td>${escapar(l.tipo)}</td>
                <td>${escapar(l.grandeza)}</td>
                <td class="${l.nivel === "critico" ? "valor-critico" : l.nivel === "atencao" ? "valor-atencao" : ""}">${numero(l.valor, 2, l.unidade)}</td>
                <td>${seloNivel(l.nivel)}</td>
            </tr>`).join(""), "Nenhuma leitura encontrada para os filtros informados.");
    }

    filtros.addEventListener("submit", (e) => { e.preventDefault(); carregar().catch(mostrarErro); });
    filtros.addEventListener("reset", () => setTimeout(() => { atualizarSensores(); carregar().catch(mostrarErro); }));

    document.getElementById("btnCsvLeituras").addEventListener("click", (e) =>
        comCarregamento(e.currentTarget, () => baixarArquivo("exportar.php?tipo=leituras&" + lerFiltros(filtros), "leituras.csv")).catch(mostrarErro));

    document.getElementById("btnNovaLeitura").addEventListener("click", () => {
        document.getElementById("formLeitura").reset();
        abrirModal("modalLeitura");
    });

    document.getElementById("formLeitura").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros(evento.target);
        if (!valor("sensorLeitura")) {
            mostrarErro(Object.assign(new Error("Escolha o sensor."), { campo: "sensorLeitura" }));
            return;
        }
        try {
            const r = await comCarregamento(document.getElementById("btnSalvarLeitura"), () =>
                api("leituras.php", "POST", { sensor_id: valor("sensorLeitura"), valor: valor("valorLeitura") }));
            fecharModal("modalLeitura");
            avisar(`Leitura registrada: nível ${NOMES_NIVEL[r.nivel]}.${r.nivel !== "normal" ? " Alerta gerado." : ""}`, r.nivel === "critico" ? "erro" : "sucesso");
            await carregar();
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    await carregar();
});
