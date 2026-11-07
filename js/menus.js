iniciarPagina(() => {
    const paginas = {
        "Gestão Geral": "gestao-geral.html",
        "Gestão de Rotas": "gestao-rotas.html",
        "Monitoramento de Cargas": "monitoramento.html",
        "Manutenção": "manutencao.html",
        "Relatórios e Análises": "relatorios.html",
        "Alertas e Notificações": "alertas.html",
        "Cadastro de Cargas": "cadastro-carga.html",
        "Cadastro de Sensores": "gestao-sensores.html"
    };

    const botoes = document.querySelectorAll(".botao-menu");

    botoes.forEach((botao) => {
        botao.addEventListener("click", () => {
            location.href = paginas[botao.textContent.trim()];
        });
    });

    document.getElementById("search").addEventListener("input", (evento) => {
        const termo = evento.target.value.toLowerCase();
        botoes.forEach((botao) => {
            botao.hidden = !botao.textContent.toLowerCase().includes(termo);
        });
    });
});
