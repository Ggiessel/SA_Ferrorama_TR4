iniciarPagina(async () => {
    const opcoes = await api("opcoes.php");

    preencherSelect(document.getElementById("tipoSensor"), opcoes.tiposSensor.map((sensor) => ({ valor: sensor.tipo, texto: sensor.tipo + " - " + sensor.grandeza })));
    preencherSelect(document.getElementById("tremSensor"), opcoes.trens.map((trem) => ({ valor: trem.id, texto: trem.nome })));

    document.querySelector(".btn-adicionar").addEventListener("click", async () => {
        limparErros();

        try {
            await api("sensores.php", "POST", {
                tipo: valor("tipoSensor"),
                trem_id: valor("tremSensor")
            });
            location.href = "gestao-sensores.html";
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
