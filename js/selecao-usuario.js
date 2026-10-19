/*
  FERRORAMA TR4 — ESCOLHA DO CARGO (paginas/selecao-usuario.html)

  Salva o cargo escolhido na API (PUT /api/usuarios/eu/cargo)
  e abre o menu correspondente.
*/

TR4.iniciar(function (usuario) {
    "use strict";

    const NOMES = { cliente: "Cliente", maquinista: "Maquinista", gestor: "Gestor" };

    document.querySelectorAll("button[data-cargo]").forEach(function (botao) {
        const cargo = botao.dataset.cargo;

        // Destaca o cargo atual, se o usuário já tiver um
        if (usuario.cargo === cargo) botao.setAttribute("aria-pressed", "true");

        botao.addEventListener("click", async function () {
            try {
                const atualizado = await TR4.ocupado(botao, function () {
                    return TR4.api("/usuarios/eu/cargo", { metodo: "PUT", corpo: { cargo: cargo } });
                });
                TR4.avisarDepois("Você entrou como " + NOMES[cargo] + ".", "sucesso");
                TR4.irParaInicio(atualizado);
            } catch (erro) {
                TR4.mostrarErro(erro);
            }
        });
    });
});
