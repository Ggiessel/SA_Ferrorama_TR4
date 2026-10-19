/*
  FERRORAMA TR4 — MANUTENÇÃO (paginas/manutencao.html)

  - POST /api/manutencoes -> registra; o banco muda o trem para
    "manutenção" e cria o alerta (gatilho trg_manutencoes_apos_inserir).
  - "Salvar como PDF" abre a impressão do navegador
    (lá dá para escolher "Salvar como PDF").
*/

TR4.iniciar(async function () {
    "use strict";

    const campoCodigo = document.getElementById("codigoTrem");
    const campoRota = document.getElementById("rotaManutencao");
    const campoCarga = document.getElementById("cargaManutencao");
    const campoCausa = document.getElementById("causaManutencao");
    const botaoPdf = document.querySelector(".pdf-btn");
    const botaoSalvar = document.querySelector(".save-btn");

    const opcoes = await TR4.opcoes();
    const trens = await TR4.api("/trens");

    TR4.preencherSelect(campoRota, opcoes.rotas.map(function (r) {
        return { valor: r.id, texto: r.nome };
    }));
    // Valor vazio = "Sem carga" (vai como null para a API)
    TR4.preencherSelect(campoCarga, [{ valor: "", texto: "Sem carga" }].concat(
        opcoes.tiposCarga.map(function (t) { return { valor: t.id, texto: t.nome }; })
    ), "");

    // O código só aceita números
    campoCodigo.addEventListener("input", function () {
        campoCodigo.value = TR4.soNumeros(campoCodigo.value).slice(0, 3);
    });

    function acharTrem() {
        const codigo = campoCodigo.value.padStart(3, "0");
        return trens.find(function (t) { return t.codigo === codigo; }) || null;
    }

    // Ao digitar um código conhecido, já escolhe a rota em que o trem está
    campoCodigo.addEventListener("change", function () {
        const trem = acharTrem();
        if (trem && trem.rotaId) campoRota.value = trem.rotaId;
    });

    function formularioValido() {
        const codigos = trens.map(function (t) { return t.codigo; }).join(", ");
        return TR4.conferir([
            [campoCodigo, campoCodigo.value === "", "Informe o código do trem (3 números)."],
            [campoCodigo, !acharTrem(), "Nenhum trem com esse código. Códigos cadastrados: " + codigos + "."],
            [campoCausa, campoCausa.value.trim().length < 3, "Descreva a causa (ex.: Batida, Freio, Motor)."]
        ]);
    }

    botaoSalvar.addEventListener("click", async function () {
        if (!formularioValido()) return;

        try {
            const resposta = await TR4.ocupado(botaoSalvar, function () {
                return TR4.api("/manutencoes", {
                    metodo: "POST",
                    corpo: {
                        codigo: campoCodigo.value,
                        rotaId: Number(campoRota.value),
                        tipoCargaId: campoCarga.value ? Number(campoCarga.value) : null,
                        causa: campoCausa.value.trim()
                    }
                });
            });

            TR4.avisar(resposta.trem + " registrado na manutenção.", "sucesso");
            campoCodigo.value = "";
            campoCausa.value = "";
            campoCodigo.focus();
        } catch (erro) {
            TR4.mostrarErro(erro);
        }
    });

    botaoPdf.addEventListener("click", function () {
        if (formularioValido()) window.print();
    });
});
