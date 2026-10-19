/*
  FERRORAMA TR4 — GESTÃO GERAL / DASHBOARD (paginas/gestao-geral.html)

  - GET /api/trens/resumo -> gráfico "Em rota x Disponíveis" (desenhado num <canvas>)
  - GET /api/rotas        -> passageiros e quilometragem da rota escolhida
  - GET /api/trens        -> consumo e faturamento do trem escolhido
*/

TR4.iniciar(async function () {
    "use strict";

    const paineis = document.querySelectorAll(".card-metrica-painel");
    const seletorRota = document.getElementById("selecao-rota");
    const seletorTrem = document.getElementById("selecao-trem");

    const respostas = await Promise.all([
        TR4.api("/trens/resumo"),
        TR4.api("/rotas"),
        TR4.api("/trens")
    ]);
    const resumo = respostas[0], rotas = respostas[1], trens = respostas[2];

    /* ---------- Gráfico ---------- */
    function desenharGrafico() {
        const legendas = paineis[0].querySelectorAll(".texto-legenda");
        legendas[0].textContent = "Em rota (" + resumo.emRota + ")";
        legendas[1].textContent = "Disponíveis (" + resumo.disponiveis + ")";

        const caixa = paineis[0].querySelector(".imagem-grafico-box");
        let canvas = caixa.querySelector("canvas");
        if (!canvas) {
            canvas = document.createElement("canvas");
            canvas.setAttribute("role", "img");
            caixa.innerHTML = "";
            caixa.appendChild(canvas);
        }
        canvas.setAttribute("aria-label", resumo.emRota + " trens em rota, " + resumo.disponiveis +
            " disponíveis e " + resumo.emManutencao + " em manutenção");

        const estilos = getComputedStyle(document.documentElement);
        const corEmRota = estilos.getPropertyValue("--fr-primaria").trim() || "#06648c";
        const corTexto = estilos.getPropertyValue("--fr-azul-escuro").trim() || "#152c59";

        // Canvas nítido em telas de alta resolução
        const tamanho = 200;
        const escala = window.devicePixelRatio || 1;
        canvas.width = tamanho * escala;
        canvas.height = tamanho * escala;
        canvas.style.width = tamanho + "px";
        canvas.style.height = tamanho + "px";

        const ctx = canvas.getContext("2d");
        ctx.setTransform(escala, 0, 0, escala, 0, 0);
        ctx.clearRect(0, 0, tamanho, tamanho);

        const centro = tamanho / 2, raio = 86, espessura = 28;
        const fatias = [
            { valor: resumo.emRota, cor: corEmRota },
            { valor: resumo.disponiveis, cor: "#8fb3e8" },
            { valor: resumo.emManutencao, cor: "#c9d2e3" }
        ];
        const total = resumo.total || 1;

        let inicio = -Math.PI / 2; // começa no topo
        fatias.forEach(function (fatia) {
            if (!fatia.valor) return;
            const fim = inicio + (fatia.valor / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.arc(centro, centro, raio - espessura / 2, inicio, fim);
            ctx.strokeStyle = fatia.cor;
            ctx.lineWidth = espessura;
            ctx.stroke();
            inicio = fim;
        });

        ctx.fillStyle = corTexto;
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.font = "700 34px Inter, system-ui, sans-serif";
        ctx.fillText(String(resumo.total), centro, centro - 8);
        ctx.font = "500 13px Inter, system-ui, sans-serif";
        ctx.fillText("trens", centro, centro + 18);
    }

    /* ---------- Rota ---------- */
    function mostrarRota() {
        const rota = rotas.find(function (r) { return String(r.id) === seletorRota.value; });
        if (!rota) return;
        const textos = paineis[1].querySelectorAll(".texto-metrica-capital");
        textos[0].textContent = "PASSAGEIROS TRANSPORTADOS - " + TR4.formatar.numero(rota.passageiros);
        textos[1].textContent = "QUILOMETRAGEM - " + TR4.formatar.numero(rota.quilometragem) + " KM";
    }

    /* ---------- Trem ---------- */
    function mostrarTrem() {
        const trem = trens.find(function (t) { return String(t.id) === seletorTrem.value; });
        if (!trem) return;
        const textos = paineis[2].querySelectorAll(".texto-metrica-capital");
        textos[0].textContent = "CONSUMO DE COMBUSTÍVEL - " + TR4.formatar.numero(trem.consumo) + " km/L";
        textos[1].textContent = "FATURAMENTO - " + TR4.formatar.moeda(trem.faturamento);
        seletorTrem.title = trem.nome + ": " + trem.statusTexto + (trem.rota ? " (" + trem.rota + ")" : "");
    }

    seletorRota.setAttribute("aria-label", "Escolha a rota");
    seletorTrem.setAttribute("aria-label", "Escolha o trem");

    const rota36 = rotas.find(function (r) { return r.numero === 36; });
    TR4.preencherSelect(seletorRota, rotas.map(function (r) {
        return { valor: r.id, texto: r.nome };
    }), rota36 ? rota36.id : (rotas[0] && rotas[0].id));

    TR4.preencherSelect(seletorTrem, trens.map(function (t) {
        return { valor: t.id, texto: t.nome };
    }), trens[0] && trens[0].id);

    seletorRota.addEventListener("change", mostrarRota);
    seletorTrem.addEventListener("change", mostrarTrem);

    desenharGrafico();
    mostrarRota();
    mostrarTrem();

    // Redesenha quando a fonte Inter terminar de carregar (o número do meio usa ela)
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(desenharGrafico);
});
