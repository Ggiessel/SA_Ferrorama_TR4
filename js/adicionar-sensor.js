iniciarPagina(async () => {
    const opcoes = await api("opcoes.php");

    preencherSelect(document.getElementById("tipoSensor"), opcoes.tiposSensor);
    preencherSelect(document.getElementById("localSensor"), opcoes.rotas.map((rota) => ({ valor: rota.id, texto: rota.nome })));

    document.querySelector(".btn-adicionar").addEventListener("click", async () => {
        limparErros();

        try {
            await api("sensores.php", "POST", {
                tipo: valor("tipoSensor"),
                rota_id: valor("localSensor")
            });
            location.href = "gestao-sensores.html";
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
