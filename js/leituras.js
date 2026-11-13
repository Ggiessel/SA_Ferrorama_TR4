iniciarPagina(async () => {
    const opcoes = await api("opcoes.php");
    const filtroTrem = document.getElementById("filtroTrem");
    const somenteFalha = document.getElementById("somenteFalha");
    const linkCsv = document.getElementById("linkCsv");
    const parametros = new URLSearchParams(location.search);

    preencherSelect(filtroTrem, opcoes.trens.map((trem) => ({ valor: trem.id, texto: trem.nome })), "Todos os trens");
    filtroTrem.value = parametros.get("trem") || "";

    function numero(valor, unidade) {
        return Number(valor).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " " + unidade;
    }

    async function carregarLeituras() {
        const filtros = new URLSearchParams();

        if (filtroTrem.value) {
            filtros.set("trem_id", filtroTrem.value);
        }
        if (somenteFalha.checked) {
            filtros.set("somente_falha", "1");
        }

        linkCsv.href = API + "exportar-leituras.php?" + filtros;

        const resposta = await api("leituras.php?" + filtros);
        const limites = resposta.limites;

        document.getElementById("limitesLeituras").textContent =
            `Limites: velocidade ${limites.velocidade_kmh} km/h, temperatura ${limites.temperatura_motor_c} °C, consumo ${limites.consumo_litros_hora} L/h, vibração ${limites.vibracao_mm_s} mm/s.`;

        document.querySelector("#tabelaLeituras tbody").innerHTML = resposta.leituras
            .map((leitura) => `
                <tr>
                    <td>${formatarData(leitura.data_hora)} ${leitura.data_hora.slice(11, 16)}</td>
                    <td>${escapar(leitura.trem)}</td>
                    <td class="${leitura.velocidade_kmh > limites.velocidade_kmh ? "acima" : ""}">${numero(leitura.velocidade_kmh, "km/h")}</td>
                    <td class="${leitura.temperatura_motor_c > limites.temperatura_motor_c ? "acima" : ""}">${numero(leitura.temperatura_motor_c, "°C")}</td>
                    <td class="${leitura.consumo_litros_hora > limites.consumo_litros_hora ? "acima" : ""}">${numero(leitura.consumo_litros_hora, "L/h")}</td>
                    <td class="${leitura.vibracao_mm_s > limites.vibracao_mm_s ? "acima" : ""}">${numero(leitura.vibracao_mm_s, "mm/s")}</td>
                    <td>${leitura.falhas.length
                        ? `<span class="selo selo--alerta" title="${escapar(leitura.falhas.join(", "))}">${leitura.falhas.length} alerta(s)</span>`
                        : '<span class="selo selo--normal">Normal</span>'}</td>
                </tr>`)
            .join("") || '<tr><td colspan="7">Nenhuma leitura encontrada. Use o simulador para gerar leituras.</td></tr>';
    }

    document.getElementById("filtrosLeituras").addEventListener("submit", (evento) => {
        evento.preventDefault();
        carregarLeituras().catch(mostrarErro);
    });

    await carregarLeituras();
});
