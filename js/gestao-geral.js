iniciarPagina(async () => {
    const rotas = await api("rotas.php");
    const trens = await api("trens.php");
    const paineis = document.querySelectorAll(".card-metrica-painel");
    const selectRota = document.getElementById("selecao-rota");
    const selectTrem = document.getElementById("selecao-trem");

    const emRota = trens.filter((trem) => trem.status === "em_rota").length;
    const disponiveis = trens.filter((trem) => trem.status === "disponivel").length;
    const emManutencao = trens.length - emRota - disponiveis;

    const legendas = paineis[0].querySelectorAll(".texto-legenda");
    legendas[0].textContent = `Em rota (${emRota})`;
    legendas[1].textContent = `Disponíveis (${disponiveis})`;
    legendas[2].textContent = `Em manutenção (${emManutencao})`;

    const caixaGrafico = paineis[0].querySelector(".imagem-grafico-box");
    caixaGrafico.innerHTML = '<canvas width="200" height="200"></canvas>';
    const desenho = caixaGrafico.querySelector("canvas").getContext("2d");

    let inicio = 0;
    [[emRota, "#2e9d5b"], [disponiveis, "#9fd3b2"], [emManutencao, "#e0a800"]].forEach(([quantidade, cor]) => {
        const fim = inicio + (quantidade / trens.length) * 2 * Math.PI;
        desenho.beginPath();
        desenho.moveTo(100, 100);
        desenho.arc(100, 100, 90, inicio, fim);
        desenho.fillStyle = cor;
        desenho.fill();
        inicio = fim;
    });

    preencherSelect(selectRota, rotas.map((rota) => ({ valor: rota.id, texto: rota.nome })));
    preencherSelect(selectTrem, trens.map((trem) => ({ valor: trem.id, texto: trem.nome })));

    function mostrarRota() {
        const rota = rotas.find((r) => r.id == selectRota.value);
        const textos = paineis[1].querySelectorAll(".texto-metrica-capital");
        textos[0].textContent = "PASSAGEIROS TRANSPORTADOS - " + Number(rota.passageiros).toLocaleString("pt-BR");
        textos[1].textContent = "QUILOMETRAGEM - " + Number(rota.quilometragem).toLocaleString("pt-BR") + " KM";
    }

    function mostrarTrem() {
        const trem = trens.find((t) => t.id == selectTrem.value);
        const textos = paineis[2].querySelectorAll(".texto-metrica-capital");
        textos[0].textContent = "CONSUMO DE COMBUSTÍVEL - " + Number(trem.consumo) + " km/L";
        textos[1].textContent = "FATURAMENTO - " + Number(trem.faturamento).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
    }

    selectRota.addEventListener("change", mostrarRota);
    selectTrem.addEventListener("change", mostrarTrem);
    mostrarRota();
    mostrarTrem();

    const painel = await api("painel.php");
    const limites = painel.limites;

    function numero(valor, unidade) {
        return Number(valor).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " " + unidade;
    }

    document.getElementById("limitesPainel").textContent =
        `Total de ${painel.total_leituras} leituras registradas. Limites: velocidade ${limites.velocidade_kmh} km/h, temperatura ${limites.temperatura_motor_c} °C, consumo ${limites.consumo_litros_hora} L/h, vibração ${limites.vibracao_mm_s} mm/s.`;

    document.querySelector("#tabelaResumo tbody").innerHTML = painel.resumo
        .map((trem) => {
            const temLeituras = trem.total_leituras > 0;

            return `
                <tr>
                    <td>${escapar(trem.nome)}</td>
                    <td>${trem.total_leituras}</td>
                    <td>${temLeituras ? numero(trem.media_velocidade, "km/h") : "—"}</td>
                    <td class="${trem.maior_velocidade > limites.velocidade_kmh ? "acima" : ""}">${temLeituras ? numero(trem.maior_velocidade, "km/h") : "—"}</td>
                    <td>${temLeituras ? numero(trem.media_temperatura, "°C") : "—"}</td>
                    <td class="${trem.maior_temperatura > limites.temperatura_motor_c ? "acima" : ""}">${temLeituras ? numero(trem.maior_temperatura, "°C") : "—"}</td>
                </tr>`;
        })
        .join("");

    document.getElementById("graficoVelocidade").innerHTML = painel.resumo
        .filter((trem) => trem.total_leituras > 0)
        .map((trem) => {
            const largura = Math.min(100, (trem.media_velocidade / 120) * 100);

            return `
                <div class="barra-linha">
                    <span class="barra-rotulo">${escapar(trem.nome)}</span>
                    <span class="barra-trilho"><span class="barra-preenchida" style="width: ${largura.toFixed(2)}%"></span></span>
                    <span class="barra-valor">${Number(trem.media_velocidade).toLocaleString("pt-BR", { maximumFractionDigits: 1 })} km/h</span>
                </div>`;
        })
        .join("") || '<p class="apoio">Nenhuma leitura registrada ainda.</p>';

    document.querySelector("#tabelaAlertasSensores tbody").innerHTML = painel.alertas
        .map((leitura) => `
            <tr>
                <td>${formatarData(leitura.data_hora)} ${leitura.data_hora.slice(11, 16)}</td>
                <td>${escapar(leitura.trem)}</td>
                <td>${escapar(leitura.falhas.join(" · "))}</td>
            </tr>`)
        .join("") || '<tr><td colspan="3">Nenhuma leitura ultrapassou os limites definidos.</td></tr>';
});
