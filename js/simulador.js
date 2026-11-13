iniciarPagina(async () => {
    const opcoes = await api("opcoes.php");
    const trem = document.getElementById("tremSimulador");

    preencherSelect(trem, opcoes.trens.map((t) => ({ valor: t.id, texto: t.nome })), "Escolha o trem");

    document.querySelector(".btn-simular").addEventListener("click", async () => {
        limparErros();

        try {
            const resultado = await api("leituras.php?acao=simular", "POST", {
                trem_id: trem.value,
                quantidade: valor("quantidadeSimulador")
            });

            avisar(`${resultado.geradas} leituras geradas, ${resultado.acima_do_limite} acima dos limites.`, "sucesso");
            setTimeout(() => {
                location.href = "leituras.html?trem=" + trem.value;
            }, 1200);
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
