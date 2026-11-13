iniciarPagina(async () => {
    const resumo = await api("relatorios.php");
    const cargas = await api("cargas.php");
    const manutencoes = await api("manutencoes.php");

    document.getElementById("resumoRelatorio").innerHTML = `
        <div class="item-resumo"><strong>${resumo.cargas}</strong><span>cargas cadastradas</span></div>
        <div class="item-resumo"><strong>${resumo.em_transito}</strong><span>cargas em trânsito</span></div>
        <div class="item-resumo"><strong>${resumo.entregues}</strong><span>cargas entregues</span></div>
        <div class="item-resumo"><strong>${resumo.trens_manutencao}</strong><span>trens em manutenção</span></div>
        <div class="item-resumo"><strong>${resumo.sensores_ativos}</strong><span>sensores ativos</span></div>`;

    document.querySelector("#tabelaCargas tbody").innerHTML = cargas
        .map((carga) => `
            <tr>
                <td>${escapar(carga.tipo)}</td>
                <td>${carga.toneladas} t</td>
                <td>${escapar(carga.destino)}</td>
                <td>${formatarData(carga.partida)}</td>
                <td>${NOMES_STATUS[carga.status]}</td>
            </tr>`)
        .join("");

    document.querySelector("#tabelaManutencoes tbody").innerHTML = manutencoes
        .map((manutencao) => `
            <tr>
                <td>${manutencao.codigo}</td>
                <td>${escapar(manutencao.rota)}</td>
                <td>${escapar(manutencao.carga)}</td>
                <td>${escapar(manutencao.causa)}</td>
                <td>${formatarData(manutencao.data)}</td>
            </tr>`)
        .join("") || '<tr><td colspan="5">Nenhuma manutenção registrada.</td></tr>';

    document.getElementById("btnImprimir").addEventListener("click", () => window.print());

    const opcoes = await api("opcoes.php");
    const relTrem = document.getElementById("relTrem");

    preencherSelect(relTrem, opcoes.trens.map((trem) => ({ valor: trem.id, texto: trem.nome })), "Todos os trens");

    function numero(valor, unidade) {
        return Number(valor).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " " + unidade;
    }

    async function carregarLeituras() {
        const filtros = new URLSearchParams();
        const campos = { trem_id: "relTrem", status: "relStatus", data_inicial: "relDataInicial", data_final: "relDataFinal" };

        Object.entries(campos).forEach(([nome, id]) => {
            if (valor(id)) {
                filtros.set(nome, valor(id));
            }
        });

        document.getElementById("linkCsvRelatorio").href = API + "exportar-leituras.php?" + filtros;

        const resposta = await api("leituras.php?" + filtros);
        const leituras = resposta.leituras;
        const limites = resposta.limites;
        const total = leituras.length;
        const comAlerta = leituras.filter((leitura) => leitura.falhas.length > 0).length;
        const media = (campo) => (total ? leituras.reduce((soma, leitura) => soma + Number(leitura[campo]), 0) / total : 0);

        document.getElementById("resumoLeituras").innerHTML = `
            <div class="item-resumo"><strong>${total}</strong><span>leituras</span></div>
            <div class="item-resumo"><strong>${comAlerta}</strong><span>leituras com alerta</span></div>
            <div class="item-resumo"><strong>${numero(media("velocidade_kmh"), "")}</strong><span>velocidade média (km/h)</span></div>
            <div class="item-resumo"><strong>${numero(media("temperatura_motor_c"), "")}</strong><span>temperatura média (°C)</span></div>`;

        document.getElementById("limitesRelatorio").textContent =
            `Limites utilizados: velocidade ${limites.velocidade_kmh} km/h, temperatura ${limites.temperatura_motor_c} °C, consumo ${limites.consumo_litros_hora} L/h, vibração ${limites.vibracao_mm_s} mm/s.`;

        document.querySelector("#tabelaLeiturasRelatorio tbody").innerHTML = leituras
            .map((leitura) => `
                <tr>
                    <td>${formatarData(leitura.data_hora)} ${leitura.data_hora.slice(11, 16)}</td>
                    <td>${escapar(leitura.trem)}</td>
                    <td>${NOMES_STATUS[leitura.status_trem]}</td>
                    <td>${numero(leitura.velocidade_kmh, "km/h")}</td>
                    <td>${numero(leitura.temperatura_motor_c, "°C")}</td>
                    <td>${numero(leitura.consumo_litros_hora, "L/h")}</td>
                    <td>${numero(leitura.vibracao_mm_s, "mm/s")}</td>
                    <td>${leitura.falhas.length ? '<span class="selo selo--alerta">Alerta</span>' : '<span class="selo selo--normal">Normal</span>'}</td>
                </tr>`)
            .join("") || '<tr><td colspan="8">Nenhuma leitura encontrada para os filtros informados.</td></tr>';
    }

    document.getElementById("filtrosRelatorio").addEventListener("submit", (evento) => {
        evento.preventDefault();
        carregarLeituras().catch(mostrarErro);
    });

    await carregarLeituras();
});
