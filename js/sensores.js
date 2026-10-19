/*
  FERRORAMA TR4 — SENSORES
  Usado em três páginas:
    - paginas/gestao-sensores.html  -> GET /api/sensores
    - paginas/adicionar-sensor.html -> POST /api/sensores
    - paginas/remover-sensor.html   -> PUT /api/sensores/:id/remover

  Instalar ou remover leva até 4 semanas; quem calcula o prazo é a API.
*/

TR4.iniciar(async function () {
    "use strict";

    const PRAZO_DIAS = 28;

    if (TR4.pagina === "gestao-sensores") await iniciarLista();
    if (TR4.pagina === "adicionar-sensor") await iniciarAdicionar();
    if (TR4.pagina === "remover-sensor") await iniciarRemover();

    // Só para mostrar a data na tela (a API grava a data oficial)
    function prazoPrevisto() {
        const d = new Date();
        d.setDate(d.getDate() + PRAZO_DIAS);
        return String(d.getDate()).padStart(2, "0") + "/" + String(d.getMonth() + 1).padStart(2, "0") + "/" + d.getFullYear();
    }

    /* ========================================================
       LISTA (gestão de sensores)
       ======================================================== */
    async function iniciarLista() {
        const lista = document.getElementById("listaSensores");
        lista.innerHTML = '<li class="estado-vazio">Carregando sensores…</li>';

        const sensores = await TR4.api("/sensores");

        if (sensores.length === 0) {
            lista.innerHTML = '<li class="estado-vazio">Nenhum sensor instalado. Use "Adicionar novo Sensor" para pedir o primeiro.</li>';
            return;
        }

        lista.innerHTML = sensores.map(function (s) {
            return (
                '<li class="item-lista">' +
                "<div><strong>" + TR4.escapar(s.tipo) + "</strong>" +
                '<span class="item-detalhe">' + TR4.escapar(s.rota) + "</span></div>" +
                '<span class="selo selo--' + TR4.classeStatus(s.status) + '">' + TR4.escapar(s.statusTexto) + "</span>" +
                "</li>"
            );
        }).join("");
    }

    /* ========================================================
       ADICIONAR
       ======================================================== */
    async function iniciarAdicionar() {
        const campoTipo = document.getElementById("tipoSensor");
        const campoLocal = document.getElementById("localSensor");
        const mensagem = document.getElementById("mensagemSensor");
        const botao = document.querySelector(".btn-adicionar");

        const opcoes = await TR4.opcoes();
        TR4.preencherSelect(campoTipo, opcoes.tiposSensor.map(function (t) {
            return { valor: t.id, texto: t.modelo + (t.descricao ? " (" + t.descricao + ")" : "") };
        }));
        TR4.preencherSelect(campoLocal, opcoes.rotas.map(function (r) {
            return { valor: r.id, texto: r.nome };
        }));

        mensagem.textContent = "O sensor será adicionado em até 4 semanas (até " + prazoPrevisto() + ").";

        botao.addEventListener("click", async function () {
            try {
                await TR4.ocupado(botao, function () {
                    return TR4.api("/sensores", {
                        metodo: "POST",
                        corpo: { tipoSensorId: Number(campoTipo.value), rotaId: Number(campoLocal.value) }
                    });
                });
                TR4.avisarDepois("Pedido de instalação registrado.", "sucesso");
                TR4.ir("gestao-sensores.html");
            } catch (erro) {
                TR4.mostrarErro(erro); // ex.: "Já existe um GEL 2747 nessa rota"
            }
        });
    }

    /* ========================================================
       REMOVER
       ======================================================== */
    async function iniciarRemover() {
        const campoTipo = document.getElementById("tipoSensor");
        const campoLocal = document.getElementById("localSensor");
        const mensagem = document.getElementById("mensagemSensor");
        const botao = document.querySelector(".btn-remover");

        // Só dá para remover o que está ativo
        const ativos = (await TR4.api("/sensores")).filter(function (s) { return s.status === "ativo"; });

        if (ativos.length === 0) {
            TR4.preencherSelect(campoTipo, [], null, "Nenhum sensor ativo");
            TR4.preencherSelect(campoLocal, [], null, "—");
            campoTipo.disabled = true;
            campoLocal.disabled = true;
            botao.disabled = true;
            mensagem.textContent = "Não há sensores ativos para remover.";
            return;
        }

        // Tipos sem repetição
        const tipos = ativos.map(function (s) { return s.tipo; })
            .filter(function (t, i, todos) { return todos.indexOf(t) === i; });
        TR4.preencherSelect(campoTipo, tipos);

        // O local mostra só as rotas onde o tipo escolhido está instalado
        function atualizarLocais() {
            TR4.preencherSelect(campoLocal, ativos
                .filter(function (s) { return s.tipo === campoTipo.value; })
                .map(function (s) { return { valor: s.id, texto: s.rota }; }));
        }
        campoTipo.addEventListener("change", atualizarLocais);
        atualizarLocais();

        mensagem.textContent = "O sensor será removido em até 4 semanas (até " + prazoPrevisto() + ").";

        botao.addEventListener("click", async function () {
            const sensor = ativos.find(function (s) { return String(s.id) === campoLocal.value; });
            if (!sensor) return;
            if (!window.confirm("Remover o sensor " + sensor.tipo + " da " + sensor.rota + "?")) return;

            try {
                await TR4.ocupado(botao, function () {
                    return TR4.api("/sensores/" + sensor.id + "/remover", { metodo: "PUT" });
                });
                TR4.avisarDepois("Pedido de remoção registrado.", "sucesso");
                TR4.ir("gestao-sensores.html");
            } catch (erro) {
                TR4.mostrarErro(erro);
            }
        });
    }
});
