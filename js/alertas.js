/*
  FERRORAMA TR4 — ALERTAS E NOTIFICAÇÕES (paginas/alertas.html)

  - GET /api/alertas?busca=...  -> lista (o mais novo primeiro)
  - DELETE /api/alertas/:id     -> apagar (só o gestor)
  Os alertas são criados automaticamente pelos gatilhos do banco.
*/

TR4.iniciar(async function (usuario) {
    "use strict";

    const lista = document.getElementById("listaAlertas");
    const campoBusca = document.getElementById("search");
    const podeApagar = usuario.cargo === "gestor";

    const ICONES = {
        info: "bi-info-circle",
        aviso: "bi-exclamation-triangle",
        atraso: "bi-clock-history"
    };

    async function carregar() {
        const termo = campoBusca.value.trim();
        const alertas = await TR4.api("/alertas" + (termo ? "?busca=" + encodeURIComponent(termo) : ""));

        if (alertas.length === 0) {
            lista.innerHTML = '<p class="estado-vazio">' +
                (termo ? "Nenhum alerta encontrado para \"" + TR4.escapar(termo) + "\"."
                    : "Nenhum alerta por enquanto. Quando algo acontecer nas rotas, ele aparece aqui.") +
                "</p>";
            return;
        }

        lista.innerHTML = alertas.map(function (alerta) {
            const tipo = ICONES[alerta.tipo] ? alerta.tipo : "info";
            return (
                '<div class="cardAlerta cardAlerta--' + tipo + '">' +
                '<i class="bi ' + ICONES[tipo] + ' icone-alerta" aria-hidden="true"></i>' +
                '<div class="texto-alerta">' +
                "<p>" + TR4.escapar(alerta.mensagem) + "</p>" +
                '<time datetime="' + TR4.escapar(alerta.data) + '">' + TR4.formatar.dataHora(alerta.data) + "</time>" +
                "</div>" +
                (podeApagar
                    ? '<button type="button" class="btn-apagar" data-id="' + alerta.id + '" aria-label="Apagar alerta">' +
                      '<i class="bi bi-trash3"></i></button>'
                    : "") +
                "</div>"
            );
        }).join("");
    }

    lista.addEventListener("click", async function (e) {
        const botao = e.target.closest(".btn-apagar");
        if (!botao) return;
        try {
            await TR4.api("/alertas/" + botao.dataset.id, { metodo: "DELETE" });
            TR4.avisar("Alerta apagado.", "info");
            await carregar();
        } catch (erro) {
            TR4.mostrarErro(erro);
        }
    });

    // Pesquisa enquanto digita (espera 300 ms sem digitar para chamar a API)
    campoBusca.addEventListener("input", TR4.esperarDigitar(function () {
        carregar().catch(TR4.mostrarErro);
    }));

    lista.innerHTML = '<p class="estado-vazio">Carregando alertas…</p>';
    await carregar();
});
