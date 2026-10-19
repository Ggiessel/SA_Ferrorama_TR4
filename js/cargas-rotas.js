/*
  FERRORAMA TR4 — CARGAS E ROTAS
  Usado em duas páginas:
    - paginas/gestao-rotas.html   -> GET /api/rotas e GET /api/rotas/:id
    - paginas/cadastro-carga.html -> GET /api/opcoes e POST /api/cargas
*/

TR4.iniciar(async function () {
    "use strict";

    if (TR4.pagina === "gestao-rotas") await iniciarGestaoDeRotas();
    if (TR4.pagina === "cadastro-carga") await iniciarCadastroDeCarga();

    /* ========================================================
       GESTÃO DE ROTAS
       ======================================================== */
    async function iniciarGestaoDeRotas() {
        const seletor = document.querySelector(".rotas select");
        const gradeHorarios = document.querySelector(".grid-horarios-leitura");
        const statusLinhas = document.querySelector(".status-linhas-wrapper");
        const caixaAlerta = document.querySelector(".caixa-mensagem-alerta");

        const rotas = await TR4.api("/rotas");
        seletor.setAttribute("aria-label", "Escolha a rota");

        // Lembra a última rota vista; se não houver, abre a Rota 14 (a do protótipo)
        let inicial = null;
        try { inicial = sessionStorage.getItem("tr4_ultimaRota"); } catch (e) { /* ignora */ }
        if (!rotas.some(function (r) { return String(r.id) === inicial; })) {
            const rota14 = rotas.find(function (r) { return r.numero === 14; });
            inicial = rota14 ? rota14.id : (rotas[0] && rotas[0].id);
        }

        TR4.preencherSelect(seletor, rotas.map(function (r) {
            return { valor: r.id, texto: r.nome };
        }), inicial);

        async function desenhar() {
            const rota = await TR4.api("/rotas/" + seletor.value);

            gradeHorarios.innerHTML = rota.horarios.map(function (h) {
                return '<div class="card-horario-div">' + TR4.escapar(h) + "</div>";
            }).join("");

            statusLinhas.innerHTML = rota.linhas.map(function (linha) {
                return (
                    '<div class="bloco-linha">' +
                    '<span class="label-linha">' + TR4.escapar(linha.nome.toUpperCase()) + "</span>" +
                    '<div class="badge-status-leitura status-' + TR4.classeStatus(linha.status) + '">' +
                    TR4.escapar(linha.statusTexto) + "</div>" +
                    "</div>"
                );
            }).join("");

            if (rota.alerta) {
                caixaAlerta.innerHTML = '<span class="emoji-alerta" aria-hidden="true">⚠️</span>' +
                    "<p>" + TR4.escapar(rota.alerta) + "</p>";
            } else {
                caixaAlerta.innerHTML = '<span class="emoji-alerta" aria-hidden="true">✅</span>' +
                    "<p>Nenhum problema na " + TR4.escapar(rota.nome) + ". Trajeto: " +
                    TR4.escapar(rota.origem) + " → " + TR4.escapar(rota.destino) + ".</p>";
            }
        }

        seletor.addEventListener("change", function () {
            try { sessionStorage.setItem("tr4_ultimaRota", seletor.value); } catch (e) { /* ignora */ }
            desenhar().catch(TR4.mostrarErro);
        });
        if (rotas.length) await desenhar();
    }

    /* ========================================================
       CADASTRO DE CARGA
       ======================================================== */
    async function iniciarCadastroDeCarga() {
        const campoTipo = document.getElementById("tipoCarga");
        const campoTamanho = document.getElementById("tamanhoCarga");
        const campoDestino = document.getElementById("destinoCarga");
        const campoDia = document.getElementById("diaPartida");
        const campoMes = document.getElementById("mesPartida");
        const botaoSalvar = document.querySelector(".btn-salvar");

        const MESES = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho",
            "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

        const opcoes = await TR4.opcoes();

        TR4.preencherSelect(campoTipo, opcoes.tiposCarga.map(function (t) {
            return { valor: t.id, texto: t.nome };
        }), null, "Escolha a carga");
        TR4.preencherSelect(campoTamanho, opcoes.tamanhosCarga.map(function (t) {
            return { valor: t, texto: t + (t === 1 ? " tonelada" : " toneladas") };
        }), null, "Escolha o tamanho");
        TR4.preencherSelect(campoDestino, opcoes.cidades.map(function (c) {
            return { valor: c.id, texto: c.nome };
        }), null, "Escolha o destino");

        // Próximos 12 meses, a partir do mês atual. Valor: "2026-10"
        const hoje = new Date();
        const meses = [];
        for (let i = 0; i < 12; i++) {
            const d = new Date(hoje.getFullYear(), hoje.getMonth() + i, 1);
            meses.push({
                valor: d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0"),
                texto: MESES[d.getMonth()] + " " + d.getFullYear()
            });
        }
        TR4.preencherSelect(campoMes, meses, meses[0].valor);

        // Os dias dependem do mês (fevereiro tem 28/29; no mês atual não dá para escolher dia que já passou)
        function atualizarDias() {
            const partes = campoMes.value.split("-");
            const ano = Number(partes[0]), mes = Number(partes[1]);
            const ultimoDia = new Date(ano, mes, 0).getDate();
            const mesAtual = ano === hoje.getFullYear() && mes === hoje.getMonth() + 1;
            const primeiroDia = mesAtual ? hoje.getDate() : 1;
            const anterior = Number(campoDia.value);

            const dias = [];
            for (let d = primeiroDia; d <= ultimoDia; d++) {
                dias.push({ valor: d, texto: "Dia " + String(d).padStart(2, "0") });
            }
            const escolhido = anterior >= primeiroDia && anterior <= ultimoDia ? anterior : primeiroDia;
            TR4.preencherSelect(campoDia, dias, escolhido);
        }
        campoMes.addEventListener("change", atualizarDias);
        atualizarDias();

        botaoSalvar.addEventListener("click", async function () {
            const valido = TR4.conferir([
                [campoTipo, !campoTipo.value, "Escolha qual carga o trem vai levar."],
                [campoTamanho, !campoTamanho.value, "Escolha o tamanho da carga."],
                [campoDestino, !campoDestino.value, "Escolha o destino da carga."]
            ]);
            if (!valido) return;

            const partida = campoMes.value + "-" + String(campoDia.value).padStart(2, "0");

            try {
                await TR4.ocupado(botaoSalvar, function () {
                    return TR4.api("/cargas", {
                        metodo: "POST",
                        corpo: {
                            tipoCargaId: Number(campoTipo.value),
                            toneladas: Number(campoTamanho.value),
                            destinoId: Number(campoDestino.value),
                            partida: partida
                        }
                    });
                });

                TR4.avisar("Carga cadastrada! Acompanhe em Monitoramento de Cargas.", "sucesso");

                // Limpa o formulário para o próximo cadastro
                [campoTipo, campoTamanho, campoDestino].forEach(function (c) { c.selectedIndex = 0; });
                campoMes.selectedIndex = 0;
                atualizarDias();
                campoTipo.focus();
            } catch (erro) {
                TR4.mostrarErro(erro);
            }
        });
    }
});
