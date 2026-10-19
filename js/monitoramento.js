/*
  FERRORAMA TR4 — MONITORAMENTO DE CARGAS (paginas/monitoramento.html)

  - GET /api/cargas?status=...  -> lista com barra de progresso
  - POST /api/cargas/simular    -> a cada 3 s, a API avança as cargas em
    trânsito (simulação do rastreamento). Ao chegar em 100%, o banco
    marca "entregue" e cria um alerta.
*/

TR4.iniciar(async function () {
    "use strict";

    const lista = document.getElementById("listaCargas");
    const filtros = document.getElementById("filtroStatus");
    const INTERVALO_MS = 3000;

    // Chave = status no banco ("" = todas)
    const FILTROS = [
        { status: "", texto: "Todas" },
        { status: "aguardando", texto: "Aguardando" },
        { status: "em_transito", texto: "Em trânsito" },
        { status: "entregue", texto: "Entregue" }
    ];
    let filtroAtual = "";

    filtros.innerHTML = FILTROS.map(function (f) {
        return '<button type="button" data-status="' + f.status + '" aria-pressed="' +
            (f.status === filtroAtual) + '">' + f.texto + "</button>";
    }).join("");

    filtros.addEventListener("click", function (e) {
        const botao = e.target.closest("button[data-status]");
        if (!botao) return;
        filtroAtual = botao.dataset.status;
        filtros.querySelectorAll("button").forEach(function (b) {
            b.setAttribute("aria-pressed", String(b === botao));
        });
        carregar().catch(TR4.mostrarErro);
    });

    function consulta() {
        return filtroAtual ? "?status=" + filtroAtual : "";
    }

    function desenhar(cargas) {
        if (cargas.length === 0) {
            const nome = FILTROS.find(function (f) { return f.status === filtroAtual; }).texto;
            lista.innerHTML = '<li class="estado-vazio">Nenhuma carga ' +
                (filtroAtual ? "com status \"" + nome + "\"" : "cadastrada") +
                ". Cadastre uma em Cadastro de Cargas.</li>";
            return;
        }

        lista.innerHTML = cargas.map(function (c) {
            return (
                '<li class="item-lista item-carga">' +
                '<div class="item-carga-topo">' +
                "<div><strong>" + TR4.escapar(c.tipo) + " · " + TR4.formatar.numero(c.toneladas) + " t</strong>" +
                '<span class="item-detalhe">Destino: ' + TR4.escapar(c.destino) +
                " · Partida: " + TR4.formatar.data(c.partida) + "</span></div>" +
                '<span class="selo selo--' + TR4.classeStatus(c.status) + '">' + TR4.escapar(c.statusTexto) + "</span>" +
                "</div>" +
                '<div class="barra-progresso" role="progressbar" aria-label="Progresso da viagem" ' +
                'aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + c.progresso + '">' +
                '<span style="width:' + c.progresso + '%"></span></div>' +
                '<span class="item-detalhe">' + c.progresso + "% do trajeto</span>" +
                "</li>"
            );
        }).join("");
    }

    async function carregar() {
        desenhar(await TR4.api("/cargas" + consulta()));
    }

    async function simular() {
        const resposta = await TR4.api("/cargas/simular" + consulta(), { metodo: "POST" });
        desenhar(resposta.cargas);
        resposta.entregues.forEach(function (c) {
            TR4.avisar(c.tipo + " chegou em " + c.destino + "!", "sucesso");
        });
    }

    lista.innerHTML = '<li class="estado-vazio">Carregando cargas…</li>';
    await carregar();

    // Se a API cair no meio, para a simulação e avisa uma vez só
    const relogio = setInterval(function () {
        simular().catch(function (erro) {
            clearInterval(relogio);
            TR4.mostrarErro(erro);
        });
    }, INTERVALO_MS);
});
