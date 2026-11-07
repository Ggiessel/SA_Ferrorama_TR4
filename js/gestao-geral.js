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

    const caixaGrafico = paineis[0].querySelector(".imagem-grafico-box");
    caixaGrafico.innerHTML = '<canvas width="200" height="200"></canvas>';
    const desenho = caixaGrafico.querySelector("canvas").getContext("2d");

    let inicio = 0;
    [[emRota, "#06648c"], [disponiveis, "#8fb3e8"], [emManutencao, "#c9d2e3"]].forEach(([quantidade, cor]) => {
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
});
