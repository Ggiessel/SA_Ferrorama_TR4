/* Simulador: gera leituras reais (gravadas no banco) para os sensores ativos de um trem. */

iniciarPagina(async () => {
    const opcoes = await api("opcoes.php");
    const sensores = (await api("sensores.php?status=ativo"));
    const trem = document.getElementById("tremSimulador");
    const sensor = document.getElementById("sensorSimulador");

    preencherSelect(trem, opcoesTrens(opcoes.trens), "Escolha o trem");
    trem.addEventListener("change", () => {
        const lista = sensores.filter((s) => s.trem_id == trem.value);
        preencherSelect(sensor, lista.map((s) => ({ valor: s.id, texto: `${s.tipo} (${s.grandeza})` })),
            lista.length ? "Todos os sensores ativos" : "Nenhum sensor ativo neste trem");
    });
    trem.dispatchEvent(new Event("change"));

    preencherTabela(document.getElementById("tabelaLimites"), Object.values(opcoes.limites).map((l) => {
        const sinal = l.direcao === "abaixo" ? "<" : ">";
        return `<tr><td>${escapar(l.nome)}</td><td>${escapar(l.unidade)}</td><td>${sinal} ${numero(l.atencao, 1)}</td><td class="valor-critico">${sinal} ${numero(l.critico, 1)}</td></tr>`;
    }).join(""), "");

    document.getElementById("formSimulador").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros();
        try {
            const r = await comCarregamento(document.getElementById("btnSimular"), () => api("leituras.php?acao=simular", "POST", {
                trem_id: trem.value,
                sensor_id: sensor.value,
                quantidade: valor("quantidadeSimulador")
            }));
            document.getElementById("resultadoSimulador").hidden = false;
            document.getElementById("resumoSimulador").innerHTML = `
                <div class="item"><strong>${r.geradas}</strong><span>leituras gravadas (${r.sensores} sensor(es))</span></div>
                <div class="item"><strong>${r.niveis.normal}</strong><span>normais</span></div>
                <div class="item"><strong class="valor-atencao">${r.niveis.atencao}</strong><span>em atenção</span></div>
                <div class="item"><strong class="valor-critico">${r.niveis.critico}</strong><span>críticas</span></div>`;
            document.getElementById("linkLeituras").href = "leituras.html?trem=" + trem.value + (sensor.value ? "&sensor=" + sensor.value : "");
            avisar(`${r.geradas} leituras gravadas.`, "sucesso");
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
