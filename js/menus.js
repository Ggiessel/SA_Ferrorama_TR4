/*
  FERRORAMA TR4 — MENUS
  (paginas/menu-gestor.html, menu-maquinista.html e menu.cliente.html)

  - Cada botão abre a página correspondente.
  - A barra de pesquisa filtra os botões enquanto digita.
*/

TR4.iniciar(function (usuario) {
    "use strict";

    // Texto do botão (sem acento, minúsculo) -> página
    const DESTINOS = {
        "gestao de geral": "gestao-geral.html",
        "gestao geral": "gestao-geral.html",
        "gestao de rotas": "gestao-rotas.html",
        "monitoramento de cargas": "monitoramento.html",
        "manutencao": "manutencao.html",
        "relatorios e analises": "relatorios.html",
        "alertas e notificacoes": "alertas.html",
        "cadastro de cargas": "cadastro-carga.html",
        "cadastro de sensores": "gestao-sensores.html"
    };

    const botoes = Array.from(document.querySelectorAll(".botao-menu"));
    const campoBusca = document.getElementById("search");
    const card = document.querySelector(".card");

    // Saudação acima dos botões
    const saudacao = document.createElement("p");
    saudacao.className = "saudacao";
    saudacao.textContent = "Olá, " + usuario.nome.split(" ")[0] + ". O que você quer acompanhar hoje?";
    card.insertAdjacentElement("beforebegin", saudacao);

    // Liga cada botão à sua página
    botoes.forEach(function (botao) {
        const destino = DESTINOS[TR4.normalizar(botao.textContent)];
        if (!destino) {
            botao.disabled = true;
            botao.title = "Página ainda não criada";
            return;
        }
        botao.addEventListener("click", function () {
            TR4.ir(destino);
        });
    });

    // Mensagem para quando a pesquisa não encontra nada
    const nenhum = document.createElement("p");
    nenhum.className = "estado-vazio";
    nenhum.hidden = true;
    card.insertAdjacentElement("afterend", nenhum);

    function filtrar() {
        const termo = TR4.normalizar(campoBusca.value);
        let visiveis = 0;

        botoes.forEach(function (botao) {
            const mostrar = TR4.normalizar(botao.textContent).includes(termo);
            botao.hidden = !mostrar;
            if (mostrar) visiveis++;
        });

        nenhum.hidden = visiveis > 0;
        nenhum.textContent = "Nenhuma opção com \"" + campoBusca.value.trim() + "\". Tente outra palavra.";
    }

    if (campoBusca) {
        campoBusca.addEventListener("input", filtrar);

        // Enter abre a primeira opção encontrada
        campoBusca.addEventListener("keydown", function (e) {
            if (e.key !== "Enter") return;
            const primeiro = botoes.find(function (b) { return !b.hidden && !b.disabled; });
            if (primeiro) primeiro.click();
        });

        // Esc limpa a pesquisa
        campoBusca.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                campoBusca.value = "";
                filtrar();
            }
        });
    }
});
